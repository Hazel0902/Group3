<?php
require_once 'bi_module.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

 $reportId = $_GET['id'];
 $bi = new BIModule();
 $reportData = $bi->getReportData($reportId);

if (!$reportData) {
    header('Location: index.php');
    exit;
}

 $report = $reportData['report'];
 $data = $reportData['data'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $report['report_name']; ?> - Module 7</title>
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
        .report-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Reports</a>
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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1><?php echo $report['report_name']; ?></h1>
                    <a href="index.php" class="btn btn-secondary">Back to Reports</a>
                </div>
                
                <!-- Report Details -->
                <div class="report-header">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Report Type:</strong> <?php echo $report['report_type']; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Date Range:</strong> 
                            <?php 
                            if ($report['date_from'] && $report['date_to']) {
                                echo $report['date_from'] . ' to ' . $report['date_to'];
                            } else {
                                echo 'All time';
                            }
                            ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Department:</strong> <?php echo $report['department'] ?: 'All'; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Region:</strong> <?php echo $report['region'] ?: 'All'; ?>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <strong>Created At:</strong> <?php echo $report['created_at']; ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Last Updated:</strong> <?php echo $report['updated_at']; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Report Data -->
                <div class="card">
                    <div class="card-header">
                        <h3>Report Data</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <?php if ($report['report_type'] === 'Sales Summary'): ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Product Name</th>
                                        <th>Total Quantity</th>
                                        <th>Total Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><?php echo $row['product_id']; ?></td>
                                        <td><?php echo $row['product_name']; ?></td>
                                        <td><?php echo $row['total_quantity']; ?></td>
                                        <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                        <td><?php echo $row['date']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php elseif ($report['report_type'] === 'Inventory Stock'): ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Product Name</th>
                                        <th>Current Stock</th>
                                        <th>Reorder Level</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><?php echo $row['product_id']; ?></td>
                                        <td><?php echo $row['product_name']; ?></td>
                                        <td><?php echo $row['current_stock']; ?></td>
                                        <td><?php echo $row['reorder_level']; ?></td>
                                        <td>
                                            <?php 
                                            if ($row['current_stock'] <= $row['reorder_level']) {
                                                echo '<span class="badge badge-danger">Low Stock</span>';
                                            } else {
                                                echo '<span class="badge badge-success">In Stock</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php elseif ($report['report_type'] === 'Profit & Loss'): ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Revenue</th>
                                        <th>Expenses</th>
                                        <th>Profit/Loss</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><?php echo $row['date']; ?></td>
                                        <td>$<?php echo number_format($row['revenue'], 2); ?></td>
                                        <td>$<?php echo number_format($row['expenses'], 2); ?></td>
                                        <td>
                                            <?php 
                                            if ($row['profit'] >= 0) {
                                                echo '<span class="text-success">$' . number_format($row['profit'], 2) . '</span>';
                                            } else {
                                                echo '<span class="text-danger">-$' . number_format(abs($row['profit']), 2) . '</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php elseif ($report['report_type'] === 'Transaction Report'): ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><?php echo $row['transaction_id']; ?></td>
                                        <td><?php echo $row['transaction_type']; ?></td>
                                        <td>$<?php echo number_format($row['amount'], 2); ?></td>
                                        <td><?php echo $row['date']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
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