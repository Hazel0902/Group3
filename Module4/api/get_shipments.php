<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../scm_integration.php';
try {
    $scm = new SCMIntegration($pdo);
    $ships = $scm->listShipments(500);
    echo json_encode(['status'=>'ok','data'=>$ships], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
