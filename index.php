<?php
require_once 'bi_module.php';

 $bi = new BIModule();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $reportType = $_POST['report_type'];
    $dateFrom = $_POST['date_from'] ?: null;
    $dateTo = $_POST['date_to'] ?: null;
    $department = $_POST['department'] ?: null;
    $region = $_POST['region'] ?: null;
    
    $reportId = $bi->generateReport($reportType, $dateFrom, $dateTo, $department, $region);
    $reportData = $bi->getReportData($reportId);
}

// Get recent reports
 $recentReports = $bi->getRecentReports(5);

// Get API call logs
 $apiLogs = $bi->getAPICallLogs(10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module 7 - Business Intelligence</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .sidebar {
            height: 100vh;
            background-color: #343a40;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #007bff;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
        }
        .table th {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="#">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Data Sources</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Settings</a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <h1>Business Intelligence (Module 7)</h1>
                
                <!-- Report Generation Form -->
                <div class="card">
                    <div class="card-header">
                        <h3>Generate Report</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="report_type">Report Type</label>
                                    <select class="form-control" id="report_type" name="report_type" required>
                                        <option value="">Select Report Type</option>
                                        <option value="Sales Summary">Sales Summary</option>
                                        <option value="Inventory Stock">Inventory Stock</option>
                                        <option value="Profit & Loss">Profit & Loss</option>
                                        <option value="Transaction Report">Transaction Report</option>
                                    </select>
                                </div>
                            </div>
                            
                            <h5>Customize Reports</h5>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label for="date_from">From Date</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="date_to">To Date</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="department">Department</label>
                                    <input type="text" class="form-control" id="department" name="department" placeholder="Department">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="region">Region</label>
                                    <input type="text" class="form-control" id="region" name="region" placeholder="Region">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" name="generate_report">Generate Report</button>
                        </form>
                    </div>
                </div>
                
                <!-- Available Reports -->
                <div class="card">
                    <div class="card-header">
                        <h3>Available Reports</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Report Type</th>
                                        <th>Date Range</th>
                                        <th>Department</th>
                                        <th>Region</th>
                                        <th>Created At</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentReports as $report): ?>
                                    <tr>
                                        <td><?php echo $report['report_type']; ?></td>
                                        <td>
                                            <?php 
                                            if ($report['date_from'] && $report['date_to']) {
                                                echo $report['date_from'] . ' to ' . $report['date_to'];
                                            } else {
                                                echo 'All time';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $report['department'] ?: 'All'; ?></td>
                                        <td><?php echo $report['region'] ?: 'All'; ?></td>
                                        <td><?php echo $report['created_at']; ?></td>
                                        <td>
                                            <a href="view_report.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-info">View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- API Call Logs -->
                <div class="card">
                    <div class="card-header">
                        <h3>API Call Logs</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <th>Endpoint</th>
                                        <th>Method</th>
                                        <th>Response Code</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($apiLogs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['module_name']; ?></td>
                                        <td><?php echo $log['endpoint']; ?></td>
                                        <td><?php echo $log['request_method']; ?></td>
                                        <td>
                                            <?php 
                                            if ($log['response_code'] >= 200 && $log['response_code'] < 300) {
                                                echo '<span class="badge badge-success">' . $log['response_code'] . '</span>';
                                            } else {
                                                echo '<span class="badge badge-danger">' . $log['response_code'] . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $log['timestamp']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php $bi->close(); ?>