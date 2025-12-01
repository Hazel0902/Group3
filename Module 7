<?php
require_once 'db_connection.php';
require_once 'api_client.php';

class BIModule {
    private $db;
    private $apiClient;
    
    public function __construct() {
        $this->db = new DatabaseConnection();
        $this->apiClient = new APIClient($this->db);
    }
    
    public function generateReport($reportType, $dateFrom = null, $dateTo = null, $department = null, $region = null) {
        // Create a new report record
        $reportType = $this->db->escape($reportType);
        $dateFrom = $dateFrom ? "'".$this->db->escape($dateFrom)."'" : 'NULL';
        $dateTo = $dateTo ? "'".$this->db->escape($dateTo)."'" : 'NULL';
        $department = $department ? "'".$this->db->escape($department)."'" : 'NULL';
        $region = $region ? "'".$this->db->escape($region)."'" : 'NULL';
        
        $sql = "INSERT INTO bi_reports (report_name, report_type, date_from, date_to, department, region) 
                VALUES ('$reportType Report', '$reportType', $dateFrom, $dateTo, $department, $region)";
        
        $this->db->query($sql);
        $reportId = $this->db->getConnection()->insert_id;
        
        // Fetch data from all modules
        $allData = $this->apiClient->fetchAllModuleData();
        
        // Process data based on report type
        switch ($reportType) {
            case 'Sales Summary':
                $this->processSalesSummary($reportId, $allData);
                break;
            case 'Inventory Stock':
                $this->processInventoryStock($reportId, $allData);
                break;
            case 'Profit & Loss':
                $this->processProfitLoss($reportId, $allData);
                break;
            case 'Transaction Report':
                $this->processTransactionReport($reportId, $allData);
                break;
        }
        
        return $reportId;
    }
    
    private function processSalesSummary($reportId, $allData) {
        if (isset($allData['Sales'])) {
            $salesData = $allData['Sales'];
            
            foreach ($salesData as $sale) {
                $productId = $this->db->escape($sale['product_id']);
                $productName = $this->db->escape($sale['product_name']);
                $quantity = (int)$sale['quantity'];
                $amount = (float)$sale['amount'];
                $date = $sale['date'];
                
                $sql = "INSERT INTO bi_sales_summary (report_id, product_id, product_name, total_quantity, total_amount, date) 
                        VALUES ($reportId, '$productId', '$productName', $quantity, $amount, '$date')";
                
                $this->db->query($sql);
            }
        }
    }
    
    private function processInventoryStock($reportId, $allData) {
        if (isset($allData['Inventory'])) {
            $inventoryData = $allData['Inventory'];
            
            foreach ($inventoryData as $item) {
                $productId = $this->db->escape($item['product_id']);
                $productName = $this->db->escape($item['product_name']);
                $stock = (int)$item['stock'];
                $reorderLevel = (int)$item['reorder_level'];
                
                $sql = "INSERT INTO bi_inventory_summary (report_id, product_id, product_name, current_stock, reorder_level) 
                        VALUES ($reportId, '$productId', '$productName', $stock, $reorderLevel)";
                
                $this->db->query($sql);
            }
        }
    }
    
    private function processProfitLoss($reportId, $allData) {
        if (isset($allData['Accounting'])) {
            $financialData = $allData['Accounting'];
            
            foreach ($financialData as $record) {
                $revenue = (float)$record['revenue'];
                $expenses = (float)$record['expenses'];
                $profit = $revenue - $expenses;
                $date = $record['date'];
                
                $sql = "INSERT INTO bi_profit_loss (report_id, revenue, expenses, profit, date) 
                        VALUES ($reportId, $revenue, $expenses, $profit, '$date')";
                
                $this->db->query($sql);
            }
        }
    }
    
    private function processTransactionReport($reportId, $allData) {
        if (isset($allData['Accounting'])) {
            $transactionData = $allData['Accounting']['transactions'];
            
            foreach ($transactionData as $transaction) {
                $transactionId = $this->db->escape($transaction['transaction_id']);
                $transactionType = $this->db->escape($transaction['type']);
                $amount = (float)$transaction['amount'];
                $date = $transaction['date'];
                
                $sql = "INSERT INTO bi_transactions (report_id, transaction_id, transaction_type, amount, date) 
                        VALUES ($reportId, '$transactionId', '$transactionType', $amount, '$date')";
                
                $this->db->query($sql);
            }
        }
    }
    
    public function getReportData($reportId) {
        $reportId = (int)$reportId;
        
        // Get report details
        $sql = "SELECT * FROM bi_reports WHERE id = $reportId";
        $result = $this->db->query($sql);
        $report = $result->fetch_assoc();
        
        if (!$report) {
            return null;
        }
        
        // Get report data based on type
        $reportType = $report['report_type'];
        $data = array();
        
        switch ($reportType) {
            case 'Sales Summary':
                $sql = "SELECT * FROM bi_sales_summary WHERE report_id = $reportId";
                $result = $this->db->query($sql);
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                break;
            case 'Inventory Stock':
                $sql = "SELECT * FROM bi_inventory_summary WHERE report_id = $reportId";
                $result = $this->db->query($sql);
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                break;
            case 'Profit & Loss':
                $sql = "SELECT * FROM bi_profit_loss WHERE report_id = $reportId";
                $result = $this->db->query($sql);
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                break;
            case 'Transaction Report':
                $sql = "SELECT * FROM bi_transactions WHERE report_id = $reportId";
                $result = $this->db->query($sql);
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                break;
        }
        
        return array(
            'report' => $report,
            'data' => $data
        );
    }
    
    public function getRecentReports($limit = 10) {
        $limit = (int)$limit;
        $sql = "SELECT * FROM bi_reports ORDER BY created_at DESC LIMIT $limit";
        $result = $this->db->query($sql);
        
        $reports = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $reports[] = $row;
            }
        }
        
        return $reports;
    }
    
    public function getAPICallLogs($limit = 50) {
        $limit = (int)$limit;
        $sql = "SELECT * FROM api_call_logs ORDER BY timestamp DESC LIMIT $limit";
        $result = $this->db->query($sql);
        
        $logs = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
        }
        
        return $logs;
    }
    
    public function close() {
        $this->db->close();
    }

    /**
 * Get the API Client for testing purposes
 * @return APIClient
 */
public function getAPIClient() {
    return $this->apiClient;

}

} //
