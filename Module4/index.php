<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/scm_integration.php';

$scm = new SCMIntegration($pdo);

// Fetch raw data from Modules 1 & 3
$inventoryDataRaw = $scm->fetchInventoryFromModule1();
$supplierDeliveriesRaw = $scm->fetchSupplierDeliveriesFromProcurement();

// Decode JSON if returned as string
if (is_string($inventoryDataRaw)) {
    $inventoryDataRaw = json_decode($inventoryDataRaw, true);
}
if (is_string($supplierDeliveriesRaw)) {
    $supplierDeliveriesRaw = json_decode($supplierDeliveriesRaw, true);
}

// Extract the actual data arrays
$inventoryData = $inventoryDataRaw['data'] ?? [];
$supplierDeliveries = $supplierDeliveriesRaw['data'] ?? [];

function isSuccess($data) {
    return is_array($data) && !empty($data);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Module 4 - Supply Chain Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background-color: #f7f9fb; color: #333; }
        h1, h2 { color: #333; }
        .status { margin: 20px 0; padding: 10px; border-radius: 6px; }
        .status.success { background-color: #e6ffed; border: 1px solid #26a65b; color: #155724; }
        .status.error { background-color: #ffe6e6; border: 1px solid #cc0000; color: #a94442; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; box-shadow: 0 0 4px rgba(0,0,0,0.1); }
        th, td { padding: 10px 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #0078D7; color: #fff; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        footer { text-align: center; margin-top: 40px; font-size: 0.9em; color: #777; }
    </style>
</head>
<body>

<h1>📦 Supply Chain Management Dashboard (Module 4)</h1>
<p>This module pulls live data from <strong>Inventory (Module 1)</strong> and <strong>Procurement (Module 3)</strong>.</p>

<!-- Integration Status -->
<div class="status <?php echo isSuccess($inventoryData) ? 'success' : 'error'; ?>">
    Inventory API Connection:
    <strong><?php echo isSuccess($inventoryData) ? 'Connected' : 'Failed'; ?></strong>
</div>

<div class="status <?php echo isSuccess($supplierDeliveries) ? 'success' : 'error'; ?>">
    Procurement API Connection:
    <strong><?php echo isSuccess($supplierDeliveries) ? 'Connected' : 'Failed'; ?></strong>
</div>

<hr>

<!-- Inventory Section -->
<h2>📊 Current Inventory (from Module 1)</h2>
<?php if (isSuccess($inventoryData)): ?>
<table>
    <thead>
        <tr>
            <th>Product ID</th>
            <th>Product Name</th>
            <th>Current Stock</th>
            <th>Reorder Level</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($inventoryData as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item['product_id'] ?? '') ?></td>
            <td><?= htmlspecialchars($item['product_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($item['current_stock_qty'] ?? '') ?></td>
            <td><?= htmlspecialchars($item['reorder_level'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:red;">Unable to load inventory data.</p>
<?php endif; ?>

<hr>

<!-- Supplier Deliveries Section -->
<h2>🚚 Supplier Deliveries (from Module 3)</h2>
<?php if (isSuccess($supplierDeliveries)): ?>
<table>
    <thead>
        <tr>
            <th>Delivery ID</th>
            <th>Supplier Name</th>
            <th>Status</th>
            <th>Total Amount</th>
            <th>Expected Delivery</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($supplierDeliveries as $delivery): ?>
        <tr>
            <td><?= htmlspecialchars($delivery['delivery_id'] ?? '') ?></td>
            <td><?= htmlspecialchars($delivery['supplier_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($delivery['status'] ?? '') ?></td>
            <td><?= htmlspecialchars($delivery['total_amount'] ?? '') ?></td>
            <td><?= htmlspecialchars($delivery['expected_delivery_date'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:red;">Unable to load supplier delivery data.</p>
<?php endif; ?>

<footer>
    <p>ERP System – Module 4 (Supply Chain Management) | Live Integration Test</p>
</footer>

</body>
</html>
