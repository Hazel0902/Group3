<?php
require_once 'bi_module.php';

 $bi = new BIModule();

echo "<h1>Module 7 Integration Test</h1>";

// Test 1: Fetch data from all modules
echo "<h2>Test 1: Fetching data from all modules</h2>";
$allData = $bi->getAPIClient()->fetchAllModuleData();

foreach ($allData as $module => $data) {
    if (isset($data['error'])) {
        echo "<p><strong>$module:</strong> <span style='color: red;'>Failed - " . $data['error'] . "</span></p>";
    } else {
        echo "<p><strong>$module:</strong> <span style='color: green;'>Success</span></p>";
    }
}

// Test 2: Generate each type of report
echo "<h2>Test 2: Generating reports</h2>";

 $reportTypes = ['Sales Summary', 'Inventory Stock', 'Profit & Loss', 'Transaction Report'];
 $reportIds = [];

foreach ($reportTypes as $reportType) {
    $reportId = $bi->generateReport($reportType);
    $reportIds[$reportType] = $reportId;
    echo "<p><strong>$reportType:</strong> Report generated with ID $reportId</p>";
}

// Test 3: Retrieve and display report data
echo "<h2>Test 3: Retrieving report data</h2>";

foreach ($reportIds as $reportType => $reportId) {
    $reportData = $bi->getReportData($reportId);
    
    if ($reportData) {
        $dataCount = count($reportData['data']);
        echo "<p><strong>$reportType:</strong> Retrieved $dataCount records</p>";
    } else {
        echo "<p><strong>$reportType:</strong> <span style='color: red;'>Failed to retrieve data</span></p>";
    }
}

// Test 4: Check API call logs
echo "<h2>Test 4: API call logs</h2>";

 $apiLogs = $bi->getAPICallLogs(20);
 $successCount = 0;
 $failureCount = 0;

foreach ($apiLogs as $log) {
    if ($log['response_code'] >= 200 && $log['response_code'] < 300) {
        $successCount++;
    } else {
        $failureCount++;
    }
}

echo "<p><strong>Successful API calls:</strong> $successCount</p>";
echo "<p><strong>Failed API calls:</strong> $failureCount</p>";

// Test 5: Generate a comprehensive report
echo "<h2>Test 5: Comprehensive report generation</h2>";

 $reportId = $bi->generateReport(
    'Sales Summary',
    '2023-01-01',
    '2023-12-31',
    'Sales',
    'North America'
);

 $reportData = $bi->getReportData($reportId);

if ($reportData) {
    $dataCount = count($reportData['data']);
    echo "<p><strong>Comprehensive Sales Summary Report:</strong> Generated with ID $reportId, containing $dataCount records</p>";
    echo "<p><strong>Date Range:</strong> " . $reportData['report']['date_from'] . " to " . $reportData['report']['date_to'] . "</p>";
    echo "<p><strong>Department:</strong> " . $reportData['report']['department'] . "</p>";
    echo "<p><strong>Region:</strong> " . $reportData['report']['region'] . "</p>";
} else {
    echo "<p><strong>Comprehensive Sales Summary Report:</strong> <span style='color: red;'>Failed to generate</span></p>";
}

echo "<h2>Integration Test Complete</h2>";
echo "<p><a href='index.php'>Return to BI Dashboard</a></p>";

 $bi->close();
?>