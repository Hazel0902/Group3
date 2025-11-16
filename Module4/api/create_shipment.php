<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../scm_integration.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid JSON payload']);
    exit;
}

try {
    $scm = new SCMIntegration($pdo);
    $shipmentId = $scm->createShipment($input);
    echo json_encode(['status'=>'ok','shipment_id' => (int)$shipmentId], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
