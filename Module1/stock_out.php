<?php
require '../db.php';
require '../shared/config.php';

// Check GET params
$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    header('Location: ' . BASE_URL . 'Module1/index.php');
    exit;
}

// Fetch product info including current warehouse
$stmt = $pdo->prepare("
    SELECT p.*, 
           pl.quantity AS current_qty,
           l.id AS warehouse_id,
           l.name AS warehouse_name
    FROM products p
    LEFT JOIN product_locations pl ON pl.product_id = p.id
    LEFT JOIN locations l ON l.id = pl.location_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: ' . BASE_URL . 'Module1/index.php');
    exit;
}

// Handle Stock-Out POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qty = (int)($_POST['quantity'] ?? 0);
    if ($qty <= 0) {
        $error = "Quantity must be greater than zero.";
    } else if ($qty > $product['current_qty']) {
        $error = "Not enough stock available. Current stock: " . $product['current_qty'];
    } else {
        // Update product_locations quantity
        $stmt = $pdo->prepare("
            UPDATE product_locations SET quantity = quantity - ?
            WHERE product_id = ? AND location_id = ?
        ");
        $stmt->execute([$qty, $product_id, $product['warehouse_id']]);

        // FIXED: Update total_quantity in products table (decrease)
        $stmt = $pdo->prepare("
            UPDATE products 
            SET total_quantity = total_quantity - ?
            WHERE id = ?
        ");
        $stmt->execute([$qty, $product_id]);

        // Add stock transaction record
        $stmt = $pdo->prepare("
            INSERT INTO stock_transactions (product_id, location_from, location_to, qty, type, note, trans_date)
            VALUES (?, ?, NULL, ?, 'OUT', 'Stock-out', NOW())
        ");
        $stmt->execute([$product_id, $product['warehouse_id'], $qty]);

        // Add to Module1/stock_out.php after successful stock-out
        // Inside the else block after successful stock-out

        /* -----------------------------------------------------------
           MODULE 4 (SCM) INTEGRATION - Create Outbound Shipment Record
        ------------------------------------------------------------ */

        $scm_payload = [
            'external_ref' => 'M1-STOCKOUT-' . time(),
            'supplier_id'  => null,
            'type'         => 'outbound',
            'status'       => 'pending',
            'origin'       => $product['warehouse_name'],
            'destination'  => 'Sales/Customer',
            'departure_at' => date('Y-m-d H:i:s'),
            'expected_arrival_at' => date('Y-m-d H:i:s'),
            'items' => [
                [
                    'product_id'   => $product_id,
                    'product_name' => $product['name'],
                    'quantity'     => $qty
                ]
            ]
        ];

        $ch = curl_init("http://localhost/Module4/api/create_shipment.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($scm_payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log response for debugging
        error_log("SCM Response: " . $response);

        // Prepare data for Finance module
        $finance_data = [
            'product_id' => $product_id,
            'quantity' => $qty,
            'transaction_type' => 'OUT',
            'transaction_date' => date('Y-m-d H:i:s'),
            'warehouse_id' => $product['warehouse_id'],
            'reference_id' => null, // Could be populated with sales order ID if available
            'reference_type' => null
        ];

        // Send to Finance module API
        $ch = curl_init('http://your-domain.com/Module5/api_stock_transaction.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($finance_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log response for debugging
        error_log("Finance API Response: $response (HTTP $http_code)");

        echo "<script>
        alert('Stock-out successful!');
        window.location.href = '" . BASE_URL . "Module1/index.php';
      </script>";
        exit;
    }
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Stock-Out: <?php echo htmlspecialchars($product['name']); ?></title>
    <link rel="stylesheet" href="styles.css">
    <base href="<?php echo BASE_URL; ?>">
</head>

<body>
    <?php include '../shared/sidebar.php'; ?>

    <div class="container" style="margin-left: 18rem;">
        <h1>Stock-Out Product</h1>
        <p><strong>Product:</strong> <?php echo htmlspecialchars($product['sku'] . ' - ' . $product['name']); ?></p>
        <p><strong>Warehouse:</strong> <?php echo htmlspecialchars($product['warehouse_name']); ?></p>
        <p><strong>Current Quantity:</strong> <?php echo (int)$product['current_qty']; ?></p>

        <?php if (isset($error)): ?>
            <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="post">
            <label>
                Quantity to Remove:
                <input type="number" name="quantity" value="0" min="1" required>
            </label>
            <br><br>
            <button class="btn btn-primary" type="submit">Stock Out</button>
            <a class="btn" href="<?php echo BASE_URL; ?>Module1/index.php">Cancel</a>
        </form>
    </div>
</body>

</html>
