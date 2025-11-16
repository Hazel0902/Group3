<?php
require '../db.php';
require '../shared/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $from_id = $_POST['from_warehouse_id'];
    $to_id = $_POST['to_warehouse_id'];
    $qty = (int)$_POST['quantity'];
    $note = $_POST['note'] ?? '';

    // Validate inputs
    if (!$product_id || !$from_id || !$to_id || $qty <= 0) {
        header("Location: " . BASE_URL . "Module1/index.php?status=transfer_error");
        exit;
    }

    if ($from_id == $to_id) {
        header("Location: " . BASE_URL . "Module1/index.php?status=error_same_warehouse");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Get source warehouse stock
        $stmt = $pdo->prepare("SELECT quantity FROM product_locations 
                               WHERE product_id = ? AND location_id = ?");
        $stmt->execute([$product_id, $from_id]);
        $source_stock = $stmt->fetchColumn();

        if ($source_stock === false || $source_stock < $qty) {
            $pdo->rollBack();
            ?>
            <!doctype html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Transfer Error</title>
                <link rel="stylesheet" href="styles.css">
                <base href="<?php echo BASE_URL; ?>">
            </head>
            <body>
                <?php include '../shared/sidebar.php'; ?>
                <div class="container" style="margin-left: 18rem;">
                    <h1 style="color:red;">Transfer Failed</h1>
                    <p><strong>Reason:</strong> Not enough stock in source warehouse.</p>
                    <p><strong>Available Stock:</strong> <?php echo (int)$source_stock; ?></p>
                    <p><strong>Requested Quantity:</strong> <?php echo $qty; ?></p>
                    <a class="btn" href="<?php echo BASE_URL; ?>Module1/index.php">Back to Inventory</a>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        // Deduct from source warehouse
        $stmt = $pdo->prepare("UPDATE product_locations SET quantity = quantity - ? 
                               WHERE product_id = ? AND location_id = ?");
        $stmt->execute([$qty, $product_id, $from_id]);

        // Add to destination warehouse
        $stmt = $pdo->prepare("
            INSERT INTO product_locations (product_id, location_id, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $stmt->execute([$product_id, $to_id, $qty]);

        // Log transaction
        $stmt = $pdo->prepare("
            INSERT INTO stock_transactions 
            (product_id, type, location_from, location_to, qty, note, trans_date)
            VALUES (?, 'TRANSFER', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$product_id, $from_id, $to_id, $qty, $note]);

        $pdo->commit();
        header("Location: " . BASE_URL . "Module1/index.php?status=transfer_success");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Transfer Error</title>
            <link rel="stylesheet" href="styles.css">
            <base href="<?php echo BASE_URL; ?>">
        </head>
        <body>
            <?php include '../shared/sidebar.php'; ?>
            <div class="container" style="margin-left: 18rem;">
                <h1 style="color:red;">Transfer Error</h1>
                <p><strong>Message:</strong> <?php echo htmlspecialchars($e->getMessage()); ?></p>
                <a class="btn" href="<?php echo BASE_URL; ?>Module1/index.php">Back to Inventory</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>
