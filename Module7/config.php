<?php
// config.php
return [
    "db_host" => "localhost",
    "db_user" => "root",
    "db_pass" => "",
    "db_name" => "module7_bi",

    // API endpoints (change to your real module endpoints)
    "endpoints" => [
        "m1"  => "http://localhost:3001/api/inventory",
        "m2"  => "http://localhost:3002/api/module2",
        "m3"  => "http://localhost:3003/api/module3",
        "m4"  => "http://localhost:3004/api/module4",
        "m5"  => "http://localhost:3005/api/module5",
        "m8"  => "http://localhost:3008/api/sales",
        "m9"  => "http://localhost:3009/api/module9",
    ]
];
