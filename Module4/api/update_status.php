<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../scm_integration.php';

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['shipment_id']) || empty($input['status'])) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'shipment_id and status required']);
    exit;
}

try {
    $scm = new SCMIntegration($pdo);
    $rows = $scm->updateShipmentStatus((int)$input['shipment_id'], $input['status']);
    echo json_encode(['status'=>'ok','rows_affected'=>$rows], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
