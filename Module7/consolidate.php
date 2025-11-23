<?php

include "database.php";
include "services/api_clients.php";

$config = include "config.php";
$endpoints = $config["endpoints"];

$pulls = fetch_all_modules($endpoints);

$totalSales = 0;
totalOrders = 0;
$totalCustomers = 0;
$totalInventoryItems = 0;

foreach ($pulls as $key => $result) {

    if (!$result["ok"]) {
        $conn->query("
            INSERT INTO module_pull_log (module_name, endpoint, status, raw_response)
            VALUES ('{$key}', '{$result["url"]}', 'failed', '{\"error\":\"{$result["error"]}\"}')
        ");
        continue;
    }

    $data = $result["data"];
    $raw = $conn->real_escape_string(json_encode($data));

    $count = is_array($data) ? count($data) : 1;
    $conn->query("
        INSERT INTO module_pull_log (module_name, endpoint, record_count, raw_response, status)
        VALUES ('{$key}', '{$result["url"]}', '{$count}', '{$raw}', 'ok')
    ");

    // ---- Aggregation Rules ----
    if ($key === "m1") { // inventory
        foreach ($data as $item) {
            $totalInventoryItems += intval($item["stock"] ?? 0);
        }
    }

    if ($key === "m8") { // sales
        if (isset($data["orders"])) {
            $totalOrders += count($data["orders"]);
            foreach ($data["orders"] as $o) {
                $totalSales += floatval($o["total"] ?? 0);
            }
        }
    }
}

$today = date("Y-m-d");
$metrics = $conn->real_escape_string(json_encode(["pulled_at" => date("c")]));

$conn->query("
    INSERT INTO daily_summary (date, total_sales, total_orders, total_customers, total_inventory_items, other_metrics)
    VALUES ('$today', '$totalSales', '$totalOrders', '$totalCustomers', '$totalInventoryItems', '$metrics')
    ON DUPLICATE KEY UPDATE
        total_sales = '$totalSales',
        total_orders = '$totalOrders',
        total_customers = '$totalCustomers',
        total_inventory_items = '$totalInventoryItems',
        other_metrics = '$metrics'
");

echo json_encode([
    "ok" => true,
    "summary" => [
        "date" => $today,
        "totalSales" => $totalSales,
        "totalOrders" => $totalOrders,
        "totalCustomers" => $totalCustomers,
        "totalInventoryItems" => $totalInventoryItems
    ]
]);
?>
