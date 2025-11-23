<?php
// integration_test.php
include "database.php";
include "services/api_clients.php";

$config = include "config.php";
$results = fetch_all_modules($config["endpoints"]);

$output = [];

foreach ($results as $key => $res) {
    $entry = [
        "module" => $key,
        "url" => $res["url"],
        "status" => $res["ok"] ? "ok" : "failed",
    ];

    if ($res["ok"]) {
        $entry["count"] = is_array($res["data"]) ? count($res["data"]) : 1;
    } else {
        $entry["error"] = $res["error"];
    }

    $output[] = $entry;
}

file_put_contents("integration_test_output.json", json_encode($output, JSON_PRETTY_PRINT));

echo "Integration Test Complete. Check integration_test_output.json\n";
?>
