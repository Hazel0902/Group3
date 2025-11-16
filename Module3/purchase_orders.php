<?php
session_start();
require '../db.php';
require '../shared/config.php';

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $pdo->beginTransaction();

        // Check if PO is delivered
        $stmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $po = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($po && $po['status'] === 'delivered') {
            // Get all goods receipts related to this PO
            $stmt = $pdo->prepare("SELECT id FROM goods_receipts WHERE po_id = ?");
            $stmt->execute([$_GET['id']]);
            $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($receipts as $receipt) {
                // Get goods receipt items
                $stmt = $pdo->prepare("
                    SELECT gri.*, poi.product_id, poi.quantity, pl.location_id 
                    FROM goods_receipt_items gri
                    JOIN purchase_order_items poi ON gri.po_item_id = poi.id
                    JOIN product_locations pl ON poi.product_id = pl.product_id
                    WHERE gri.receipt_id = ?
                ");
                $stmt->execute([$receipt['id']]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Update inventory to reverse the stock-in
                foreach ($items as $item) {
                    $stmt = $pdo->prepare("
                        UPDATE product_locations 
                        SET quantity = quantity - ? 
                        WHERE product_id = ? AND location_id = ?
                    ");
                    $stmt->execute([$item['quantity'], $item['product_id'], $item['location_id']]);
                }

                // Delete goods receipt items
                $stmt = $pdo->prepare("DELETE FROM goods_receipt_items WHERE receipt_id = ?");
                $stmt->execute([$receipt['id']]);

                // Delete stock transactions related to this goods receipt
                $stmt = $pdo->prepare("DELETE FROM stock_transactions WHERE reference_id = ? AND reference_type = 'gr'");
                $stmt->execute([$receipt['id']]);
            }

            // Delete goods receipts
            $stmt = $pdo->prepare("DELETE FROM goods_receipts WHERE po_id = ?");
            $stmt->execute([$_GET['id']]);
        }

        // Delete purchase order items
        $stmt = $pdo->prepare("DELETE FROM purchase_order_items WHERE po_id = ?");
        $stmt->execute([$_GET['id']]);

        // Delete purchase order
        $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        $pdo->commit();
        header("Location: purchase_orders.php?status=deleted");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting purchase order: " . $e->getMessage();
    }
}

// Fetch suppliers from Module 4 API
$suppliers = [];
try {
    $ch = curl_init("http://localhost/Module4/api/get_suppliers.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $supplier_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $supplier_data = json_decode($supplier_response, true);
        if ($supplier_data['status'] === 'ok') {
            $suppliers = $supplier_data['data'];
        } else {
            error_log("Supplier API Error: " . ($supplier_data['message'] ?? 'Unknown error'));
            // Fallback to local database if API fails
            $suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        throw new Exception("HTTP Error: " . $http_code);
    }
} catch (Exception $e) {
    error_log("Failed to fetch suppliers from Module 4: " . $e->getMessage());
    // Fallback to local database
    $suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission for new purchase order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_number = 'PO-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

    // Sanitize inputs
    $requisition_id = $_POST['requisition_id'] ?? null;
    $requisition_id = ($requisition_id === '' ? null : (int)$requisition_id);
    $supplier_id = (int)$_POST['supplier_id'];
    $order_date = $_POST['order_date'] ?? date('Y-m-d H:i:s');
    $status = $_POST['status'] ?? 'draft';
    $notes = $_POST['notes'];

    try {
        // Validate requisition_id if provided
        if (!empty($requisition_id)) {
            $stmt = $pdo->prepare("SELECT id FROM purchase_requisitions WHERE id = ?");
            $stmt->execute([$requisition_id]);
            $valid_requisition = $stmt->fetchColumn();

            if (!$valid_requisition) {
                throw new Exception("Invalid requisition selected.");
            }
        }

        $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, requisition_id, supplier_id, order_date, status, notes) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$po_number, $requisition_id, $supplier_id, $order_date, $status, $notes]);

        $po_id = $pdo->lastInsertId();

        // Handle items
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['product_id']) && $item['quantity'] > 0) {
                    $stmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, quantity, unit_price) 
                                           VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $po_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_price']
                    ]);
                }
            }
        }

        // Update requisition status if created from requisition
        if ($requisition_id) {
            $stmt = $pdo->prepare("UPDATE purchase_requisitions SET status = 'converted_to_po' WHERE id = ?");
            $stmt->execute([$requisition_id]);
        }

        header("Location: purchase_orders.php?status=success");
        exit;
    } catch (Exception $e) {
        $error = "Error creating purchase order: " . $e->getMessage();
    }
}

// Handle status update
if (isset($_GET['action']) && in_array($_GET['action'], ['send', 'confirm', 'deliver', 'cancel']) && isset($_GET['id'])) {
    $status_map = [
        'send' => 'sent',
        'confirm' => 'confirmed',
        'deliver' => 'delivered',
        'cancel' => 'cancelled'
    ];

    try {
        $pdo->beginTransaction();

        // Update purchase order status
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
        $stmt->execute([$status_map[$_GET['action']], $_GET['id']]);

        if ($_GET['action'] === 'deliver') {
            $po_id = $_GET['id'];
            
            $stmt = $pdo->prepare("SELECT po.*, s.name as supplier_name FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id = s.id WHERE po.id = ?");
            $stmt->execute([$po_id]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$po) {
                throw new Exception("Purchase order not found");
            }
            
            $stmt = $pdo->prepare("
                SELECT poi.id as po_item_id, poi.product_id, poi.quantity, p.warehouse_id as location_id
                FROM purchase_order_items poi
                JOIN products p ON poi.product_id = p.id
                WHERE poi.po_id = ?
            ");
            $stmt->execute([$po_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($items)) {
                throw new Exception("No items found in purchase order");
            }
            
            // Use first location_id or fallback
            $receipt_location = $items[0]['location_id'] ?? null;
            
            // Insert goods receipt
            $receipt_number = 'GR-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("
                INSERT INTO goods_receipts 
                (receipt_number, po_id, receipt_date, received_by, location_id, notes)
                VALUES (?, ?, NOW(), ?, ?, ?)
            ");
            $notes = "Auto-generated from PO: " . $po['po_number'] . " - Supplier: " . $po['supplier_name'];
            
            $stmt->execute([
                $receipt_number,
                $po_id,
                $_SESSION['user_name'] ?? 'System',
                $receipt_location,
                $notes
            ]);
            
            $gr_id = $pdo->lastInsertId();
            
            foreach ($items as $item) {
                // Insert goods receipt items
                $stmt = $pdo->prepare("
                    INSERT INTO goods_receipt_items (receipt_id, po_item_id, quantity_received, expiration_date)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$gr_id, $item['po_item_id'], $item['quantity'], null]);
                
                // Update product_locations quantity
                $stmt = $pdo->prepare("
                    INSERT INTO product_locations (product_id, location_id, quantity)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ");
                $stmt->execute([$item['product_id'], $item['location_id'], $item['quantity']]);
                
                // Insert stock transaction
                $stmt = $pdo->prepare("
                    INSERT INTO stock_transactions 
                    (product_id, location_from, location_to, qty, type, reference, note, trans_date, user_name, reference_id, reference_type)
                    VALUES (?, NULL, ?, ?, 'stock-in', ?, ?, NOW(), ?, ?, 'gr')
                ");
                $stmt->execute([
                    $item['product_id'],
                    $item['location_id'],
                    $item['quantity'],
                    $receipt_number,
                    "Received from PO: " . $po['po_number'],
                    $_SESSION['user_name'] ?? 'System',
                    $gr_id
                ]);
            }

            /* -----------------------------------------------------------
               MODULE 4 (SCM) INTEGRATION - Update Delivery Status
            ------------------------------------------------------------ */
            $scm_payload = [
                'shipment_id' => $po_id,
                'status' => 'delivered'
            ];

            $ch = curl_init("http://localhost/Module4/api/update_status.php");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($scm_payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $scm_response = curl_exec($ch);
            curl_close($ch);

            // Log SCM response
            error_log("SCM Status Update Response: " . $scm_response);
        }

        // For other status updates (send, confirm, cancel)
        if (in_array($_GET['action'], ['send', 'confirm', 'cancel'])) {
            $scm_status_map = [
                'send' => 'in_transit',
                'confirm' => 'pending',
                'cancel' => 'cancelled'
            ];

            $scm_payload = [
                'shipment_id' => $_GET['id'],
                'status' => $scm_status_map[$_GET['action']]
            ];

            $ch = curl_init("http://localhost/Module4/api/update_status.php");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($scm_payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $scm_response = curl_exec($ch);
            curl_close($ch);

            // Log SCM response
            error_log("SCM Status Update Response: " . $scm_response);
        }

        $pdo->commit();
        header("Location: purchase_orders.php?status=updated");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "<div style='color:red; padding:20px; border:1px solid red; margin:20px;'>";
        $error .= "<h3>Detailed Error:</h3>";
        $error .= "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
        $error .= "<p><strong>File:</strong> " . $e->getFile() . "</p>";
        $error .= "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
        $error .= "</div>";
    }
}

// Load all purchase orders
$purchase_orders = $pdo->query("
    SELECT po.*,
           s.name as supplier_name,
           pr.requisition_number,
           (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as item_count,
           (SELECT SUM(quantity * unit_price) FROM purchase_order_items WHERE po_id = po.id) as total_amount
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN purchase_requisitions pr ON po.requisition_id = pr.id
    ORDER BY po.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Load approved requisitions
$requisitions = $pdo->query("
    SELECT * FROM purchase_requisitions 
    WHERE status = 'approved' 
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Load products
$products = $pdo->query("SELECT * FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Orders</title>
    <link rel="stylesheet" href="styles.css">
    <base href="<?php echo BASE_URL; ?>">
    <style>
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 100;
        }
        .modal-content {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .modal-header h2 {
            margin: 0;
            color: #2c3e50;
        }
        .close {
            cursor: pointer;
            font-size: 24px;
            color: #999;
        }
        .close:hover {
            color: #333;
        }
        .item-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .item-row input, .item-row select {
            flex: 1;
        }
        .btn-remove {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-add {
            background: #27ae60;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .req-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .req-details h3 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: bold;
            width: 150px;
            color: #555;
        }
        .detail-value {
            flex: 1;
        }
        .items-section {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .items-header {
            background: #34495e;
            color: white;
            padding: 12px 15px;
            font-weight: bold;
        }
        .items-list {
            padding: 0;
            margin: 0;
            list-style: none;
        }
        .items-list li {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .items-list li:last-child {
            border-bottom: none;
        }
        .item-info {
            flex: 1;
        }
        .item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .item-meta {
            color: #666;
            font-size: 14px;
        }
        .item-price {
            text-align: right;
            min-width: 150px;
        }
        .item-unit-price {
            color: #666;
            font-size: 14px;
        }
        .item-total {
            font-weight: bold;
            color: #2c3e50;
            font-size: 16px;
        }
        .total-section {
            background: #ecf0f1;
            padding: 15px;
            text-align: right;
            font-weight: bold;
            font-size: 18px;
            color: #2c3e50;
            border-top: 1px solid #ddd;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-draft {
            background: #fff3cd;
            color: #856404;
        }
        .status-sent {
            background: #cce5ff;
            color: #004085;
        }
        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-delivered {
            background: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        .requisition-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border: 1px solid #ddd;
        }
        .requisition-preview h4 {
            margin-top: 0;
            color: #2c3e50;
        }
        .requisition-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .requisition-item:last-child {
            border-bottom: none;
        }
        .modal-actions {
            margin-top: 20px;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <?php include '../shared/sidebar.php'; ?>

    <div class="container" style="margin-left: 18rem;">
        <div class="header">
            <h1>Purchase Orders</h1>
            <div>
                <button class="btn btn-primary" onclick="openAddModal()">+ New Purchase Order</button>
            </div>
        </div>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
            <div class="card" style="background-color: #d4edda; border-left: 4px solid #27ae60; margin-bottom: 20px;">
                <p style="color: #155724; margin: 0;">Purchase order created successfully!</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'updated'): ?>
            <div class="card" style="background-color: #d4edda; border-left: 4px solid #27ae60; margin-bottom: 20px;">
                <p style="color: #155724; margin: 0;">Purchase order updated successfully!</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
            <div class="card" style="background-color: #d4edda; border-left: 4px solid #27ae60; margin-bottom: 20px;">
                <p style="color: #155724; margin: 0;">Purchase order deleted successfully!</p>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="card" style="background-color: #f8d7da; border-left: 4px solid #e74c3c; margin-bottom: 20px;">
                <p style="color: #721c24; margin: 0;"><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Requisition</th>
                        <th>Supplier</th>
                        <th>Order Date</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchase_orders as $po): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                            <td>
                                <?php if ($po['requisition_number']): ?>
                                    <a href="<?php echo BASE_URL; ?>Module3/purchase_requisitions.php?req=<?php echo urlencode($po['requisition_number']) ?>" target="_blank">
                                        <?php echo htmlspecialchars($po['requisition_number']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($po['order_date'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($po['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($po['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo (int)$po['item_count']; ?></td>
                            <td>₱<?php echo number_format($po['total_amount'], 2); ?></td>
                            <td>
                                <button class="btn" onclick="viewPurchaseOrder(<?php echo $po['id']; ?>)">View</button>
                                <?php if ($po['status'] === 'draft'): ?>
                                    <button class="btn btn-success" onclick="updateStatus(<?php echo $po['id']; ?>, 'send')">Send</button>
                                <?php endif; ?>
                                <?php if ($po['status'] === 'sent'): ?>
                                    <button class="btn btn-info" onclick="updateStatus(<?php echo $po['id']; ?>, 'confirm')">Confirm</button>
                                <?php endif; ?>
                                <?php if ($po['status'] === 'confirmed'): ?>
                                    <button class="btn btn-primary" onclick="updateStatus(<?php echo $po['id']; ?>, 'deliver')">Deliver</button>
                                <?php endif; ?>
                                
                                <!-- Delete button for draft, sent, confirmed, cancelled, and delivered status -->
                                <?php if (in_array($po['status'], ['draft', 'sent', 'confirmed', 'cancelled', 'delivered'])): ?>
                                    <button class="btn btn-danger" onclick="deletePurchaseOrder(<?php echo $po['id']; ?>)">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Purchase Order Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>New Purchase Order</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="post" action="<?php echo BASE_URL; ?>Module3/purchase_orders.php" id="poForm">
                <div class="form-row">
                    <div class="col">
                        <label>Create From Requisition (Optional)
                            <select class="input" name="requisition_id" id="requisitionSelect">
                                <option value="">Create Manually</option>
                                <?php foreach ($requisitions as $req): ?>
                                    <option value="<?php echo $req['id']; ?>">
                                        <?php echo htmlspecialchars($req['requisition_number']); ?> - 
                                        <?php echo htmlspecialchars(substr($req['description'], 0, 30)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="col">
                        <label>Supplier
                            <select class="input" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['supplier_id']; ?>"><?php echo htmlspecialchars($supplier['supplier_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="col">
                        <label>Order Date<input type="datetime-local" class="input" name="order_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required></label>
                    </div>
                </div>
                
                <div class="form-row">
                    <label>Notes<textarea class="input" name="notes" rows="2"></textarea></label>
                </div>
                
                <div id="requisitionPreview" style="display: none;" class="requisition-preview">
                    <h4>Requisition Items Preview</h4>
                    <div id="requisitionItems"></div>
                </div>
                
                <h3>Order Items</h3>
                <div id="itemsContainer">
                    <div class="item-row">
                        <select class="input" name="items[0][product_id]" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" class="input" placeholder="Qty" name="items[0][quantity]" min="1" required style="width: 100px;">
                        <input type="number" class="input" placeholder="Unit Price" name="items[0][unit_price]" step="0.01" required style="width: 120px;">
                        <button type="button" class="btn-remove" onclick="removeItem(this)">×</button>
                    </div>
                </div>
                <button type="button" class="btn-add" onclick="addItem()">+ Add Item</button>
                
                <div class="form-row" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Create Purchase Order</button>
                    <button type="button" class="btn" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Purchase Order Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Purchase Order Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div id="viewContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
    let itemCount = 1;

    function openAddModal() {
        console.log('openAddModal called');
        const modal = document.getElementById('addModal');
        if (modal) {
            modal.style.display = 'flex';
            console.log('Modal opened');
        } else {
            console.error('Modal not found');
        }
    }

    function closeAddModal() {
        console.log('closeAddModal called');
        const modal = document.getElementById('addModal');
        if (modal) {
            modal.style.display = 'none';
            document.getElementById('poForm').reset();
            resetItems();
            console.log('Modal closed');
        } else {
            console.error('Modal not found');
        }
    }

    function addItem() {
        console.log('addItem called');
        const container = document.getElementById('itemsContainer');
        if (container) {
            const newItem = document.createElement('div');
            newItem.className = 'item-row';
            newItem.innerHTML = `
                <select class="input" name="items[${itemCount}][product_id]" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" class="input" placeholder="Qty" name="items[${itemCount}][quantity]" min="1" required style="width: 100px;">
                <input type="number" class="input" placeholder="Unit Price" name="items[${itemCount}][unit_price]" step="0.01" required style="width: 120px;">
                <button type="button" class="btn-remove" onclick="removeItem(this)">×</button>
            `;
            container.appendChild(newItem);
            itemCount++;
            console.log('Item added, total items:', itemCount);
        } else {
            console.error('Items container not found');
        }
    }

    function removeItem(button) {
        console.log('removeItem called');
        const container = document.getElementById('itemsContainer');
        if (container && container.children.length > 1) {
            button.parentElement.remove();
            console.log('Item removed');
        } else {
            console.error('Cannot remove item');
        }
    }

    function resetItems() {
        console.log('resetItems called');
        const container = document.getElementById('itemsContainer');
        if (container) {
            container.innerHTML = `
                <div class="item-row">
                    <select class="input" name="items[0][product_id]" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" class="input" placeholder="Qty" name="items[0][quantity]" min="1" required style="width: 100px;">
                    <input type="number" class="input" placeholder="Unit Price" name="items[0][unit_price]" step="0.01" required style="width: 120px;">
                    <button type="button" class="btn-remove" onclick="removeItem(this)">×</button>
                </div>
            `;
            itemCount = 1;
            console.log('Items reset');
        } else {
            console.error('Items container not found');
        }
    }

    function viewPurchaseOrder(id) {
        console.log('viewPurchaseOrder called with id:', id);
        const url = `<?php echo BASE_URL; ?>Module3/get_po_details.php?id=${id}`;
        console.log('Fetching URL:', url);
        
        fetch(url)
            .then(response => {
                console.log('Fetch response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Fetch response data:', data);
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                const po = data.purchase_order;
                
                // Build purchase order details HTML
                let detailsHtml = `
                    <div class="req-details">
                        <h3>Purchase Order Information</h3>
                        <div class="detail-row">
                            <div class="detail-label">PO Number:</div>
                            <div class="detail-value">${po.po_number}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Supplier:</div>
                            <div class="detail-value">${po.supplier_name}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Order Date:</div>
                            <div class="detail-value">${new Date(po.order_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value">
                                <span class="status-badge status-${po.status.toLowerCase()}">${po.status.charAt(0).toUpperCase() + po.status.slice(1)}</span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Notes:</div>
                            <div class="detail-value">${po.notes || 'No notes'}</div>
                        </div>
                    </div>
                `;
                
                // Build items list HTML
                let itemsHtml = `
                    <div class="items-section">
                        <div class="items-header">Order Items</div>
                        <ul class="items-list">
                `;
                
                let totalAmount = 0;
                
                if (data.items && data.items.length > 0) {
                    data.items.forEach(item => {
                        const itemTotal = item.quantity * item.unit_price;
                        totalAmount += itemTotal;
                        
                        itemsHtml += `
                            <li>
                                <div class="item-info">
                                    <div class="item-name">${item.product_name}</div>
                                    <div class="item-meta">${item.quantity} units</div>
                                </div>
                                <div class="item-price">
                                    <div class="item-unit-price">₱${parseFloat(item.unit_price).toFixed(2)} each</div>
                                    <div class="item-total">₱${itemTotal.toFixed(2)}</div>
                                </div>
                            </li>
                        `;
                    });
                } else {
                    itemsHtml += '<li style="text-align: center; color: #999;">No items found</li>';
                }
                
                itemsHtml += `
                        </ul>
                        <div class="total-section">
                            Total Amount: ₱${totalAmount.toFixed(2)}
                        </div>
                    </div>
                `;
                
                // NEW: Check for related invoices
                let invoiceHtml = '';
                if (data.invoices && data.invoices.length > 0) {
                    invoiceHtml = `
                        <div class="items-section">
                            <div class="items-header">Related Invoices</div>
                            <ul class="items-list">
                    `;
                    
                    data.invoices.forEach(invoice => {
                        invoiceHtml += `
                            <li>
                                <div class="item-info">
                                    <div class="item-name">
                                        <a href="<?php echo BASE_URL; ?>Module3/invoices.php" target="_blank">
                                            ${invoice.invoice_number}
                                        </a>
                                    </div>
                                    <div class="item-meta">
                                        ${new Date(invoice.invoice_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                                        - Due: ${new Date(invoice.due_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                                    </div>
                                </div>
                                <div class="item-price">
                                    <div class="item-unit-price">
                                        <span class="status-badge status-${invoice.status.toLowerCase()}">
                                            ${invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1)}
                                        </span>
                                    </div>
                                    <div class="item-total">₱${parseFloat(invoice.total_amount).toFixed(2)}</div>
                                </div>
                            </li>
                        `;
                    });
                    
                    invoiceHtml += `
                            </ul>
                        </div>
                    `;
                } else if (po.status === 'delivered') {
                    // If PO is delivered but no invoice exists, show option to create one
                    invoiceHtml = `
                        <div class="items-section" style="background-color: #f8f9fa; border: 1px dashed #ddd;">
                            <div class="items-header">Invoice</div>
                            <div style="padding: 15px; text-align: center;">
                                <p style="margin: 0 0 15px 0; color: #666;">No invoice created yet for this purchase order</p>
                                <button class="btn btn-primary" onclick="createInvoice(${po.id})">Create Invoice</button>
                            </div>
                        </div>
                    `;
                }
                
                // Add action buttons at the bottom
                let actionsHtml = `
                    <div class="modal-actions">
                `;
                
                // Add status-based action buttons
                if (po.status === 'draft') {
                    actionsHtml += `
                        <button class="btn btn-success" onclick="updateStatusFromModal(${po.id}, 'send')">Send</button>
                    `;
                }
                if (po.status === 'sent') {
                    actionsHtml += `
                        <button class="btn btn-info" onclick="updateStatusFromModal(${po.id}, 'confirm')">Confirm</button>
                    `;
                }
                if (po.status === 'confirmed') {
                    actionsHtml += `
                        <button class="btn btn-primary" onclick="updateStatusFromModal(${po.id}, 'deliver')">Deliver</button>
                    `;
                }
                
                // Add delete button for appropriate statuses
                if (inArray(po.status, ['draft', 'sent', 'confirmed', 'cancelled', 'delivered'])) {
                    actionsHtml += `
                        <button class="btn btn-danger" onclick="deletePurchaseOrderFromModal(${po.id})">Delete</button>
                    `;
                }
                
                actionsHtml += `
                        <button class="btn" onclick="closeViewModal()">Close</button>
                    </div>
                `;
                
                const viewContent = document.getElementById('viewContent');
                if (viewContent) {
                    viewContent.innerHTML = detailsHtml + itemsHtml + invoiceHtml + actionsHtml;
                    document.getElementById('viewModal').style.display = 'flex';
                    console.log('View modal opened');
                } else {
                    console.error('View content container not found');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error loading purchase order details. Please check the console for more information.');
            });
    }

    // NEW: Function to create invoice from PO modal
    function createInvoice(poId) {
        window.location.href = `<?php echo BASE_URL; ?>Module3/invoices.php?po_id=${poId}`;
    }

    function closeViewModal() {
        console.log('closeViewModal called');
        const modal = document.getElementById('viewModal');
        if (modal) {
            modal.style.display = 'none';
            console.log('View modal closed');
        } else {
            console.error('View modal not found');
        }
    }

    function updateStatus(id, action) {
        console.log('updateStatus called with id:', id, 'action:', action);
        const actionMap = {
            'send': 'send this purchase order to the supplier?',
            'confirm': 'confirm this purchase order?',
            'deliver': 'mark this purchase order as delivered and update inventory?',
            'cancel': 'cancel this purchase order?'
        };
        
        if (confirm(`Are you sure you want to ${actionMap[action]}`)) {
            // Show processing indicator for delivery action
            if (action === 'deliver') {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = 'Processing...';
                btn.disabled = true;
                
                // Use fetch to show progress
                fetch(`<?php echo BASE_URL; ?>Module3/purchase_orders.php?action=${action}&id=${id}`)
                    .then(response => {
                        if (!response.redirected) {
                            throw new Error('Server error');
                        }
                        window.location.href = response.url;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating purchase order status');
                        btn.textContent = originalText;
                        btn.disabled = false;
                    });
            } else {
                window.location.href = `<?php echo BASE_URL; ?>Module3/purchase_orders.php?action=${action}&id=${id}`;
            }
        }
    }

    function deletePurchaseOrder(id) {
        console.log('deletePurchaseOrder called with id:', id);
        if (confirm('Are you sure you want to delete this purchase order? If it has been delivered, this will also remove all related goods receipts and reverse inventory updates. This action cannot be undone.')) {
            const url = '<?php echo BASE_URL; ?>Module3/purchase_orders.php?action=delete&id=' + id;
            console.log('Redirecting to:', url);
            window.location.href = url;
        }
    }

    // Helper function to check if value is in array
    function inArray(value, array) {
        return array.indexOf(value) !== -1;
    }

    // Update status from modal
    function updateStatusFromModal(id, action) {
        const actionMap = {
            'send': 'send this purchase order to the supplier?',
            'confirm': 'confirm this purchase order?',
            'deliver': 'mark this purchase order as delivered and update inventory?',
            'cancel': 'cancel this purchase order?'
        };
        
        if (confirm(`Are you sure you want to ${actionMap[action]}`)) {
            if (action === 'deliver') {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = 'Processing...';
                btn.disabled = true;
                
                fetch(`<?php echo BASE_URL; ?>Module3/purchase_orders.php?action=${action}&id=${id}`)
                    .then(response => {
                        if (!response.redirected) {
                            throw new Error('Server error');
                        }
                        window.location.href = response.url;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating purchase order status');
                        btn.textContent = originalText;
                        btn.disabled = false;
                    });
            } else {
                window.location.href = `<?php echo BASE_URL; ?>Module3/purchase_orders.php?action=${action}&id=${id}`;
            }
        }
    }

    // Delete purchase order from modal
    function deletePurchaseOrderFromModal(id) {
        if (confirm('Are you sure you want to delete this purchase order? If it has been delivered, this will also remove all related goods receipts and reverse inventory updates. This action cannot be undone.')) {
            window.location.href = `<?php echo BASE_URL; ?>Module3/purchase_orders.php?action=delete&id=${id}`;
        }
    }

    window.onclick = function(event) {
        if (event.target === document.getElementById('addModal')) {
            closeAddModal();
        }
        if (event.target === document.getElementById('viewModal')) {
            closeViewModal();
        }
    }
</script>
</body>
</html>
