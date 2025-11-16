<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db_connect.php';
try {
    $stmt = $pdo->query("SELECT supplier_id, supplier_name, contact_info, rating FROM suppliers ORDER BY supplier_name");
    $rows = $stmt->fetchAll();
    echo json_encode(['status' => 'ok', 'data' => $rows], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
