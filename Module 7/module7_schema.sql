-- File: module7_schema.sql
-- Description: Database schema for Module 7 (Business Intelligence)

-- Create database for Module 7 (Business Intelligence)
CREATE DATABASE IF NOT EXISTS module7_bi;
USE module7_bi;

-- Table to store BI reports
CREATE TABLE IF NOT EXISTS bi_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_name VARCHAR(100) NOT NULL,
    report_type VARCHAR(50) NOT NULL,
    date_from DATE,
    date_to DATE,
    department VARCHAR(50),
    region VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table to store summarized sales data
CREATE TABLE IF NOT EXISTS bi_sales_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT,
    product_id VARCHAR(50),
    product_name VARCHAR(100),
    total_quantity INT,
    total_amount DECIMAL(10,2),
    date DATE,
    FOREIGN KEY (report_id) REFERENCES bi_reports(id)
);

-- Table to store summarized inventory data
CREATE TABLE IF NOT EXISTS bi_inventory_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT,
    product_id VARCHAR(50),
    product_name VARCHAR(100),
    current_stock INT,
    reorder_level INT,
    FOREIGN KEY (report_id) REFERENCES bi_reports(id)
);

-- Table to store profit & loss data
CREATE TABLE IF NOT EXISTS bi_profit_loss (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT,
    revenue DECIMAL(10,2),
    expenses DECIMAL(10,2),
    profit DECIMAL(10,2),
    date DATE,
    FOREIGN KEY (report_id) REFERENCES bi_reports(id)
);

-- Table to store transaction data
CREATE TABLE IF NOT EXISTS bi_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT,
    transaction_id VARCHAR(50),
    transaction_type VARCHAR(50),
    amount DECIMAL(10,2),
    date DATE,
    FOREIGN KEY (report_id) REFERENCES bi_reports(id)
);

-- Table to log API calls to other modules
CREATE TABLE IF NOT EXISTS api_call_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_name VARCHAR(50),
    endpoint VARCHAR(255),
    request_method VARCHAR(10),
    request_data TEXT,
    response_code INT,
    response_data TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table to store API endpoints for other modules
CREATE TABLE IF NOT EXISTS module_endpoints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT,
    module_name VARCHAR(50),
    endpoint_name VARCHAR(100),
    endpoint_url VARCHAR(255),
    request_method VARCHAR(10) DEFAULT 'GET',
    description TEXT
);

-- Insert API endpoints for all modules
INSERT INTO module_endpoints (module_id, module_name, endpoint_name, endpoint_url, request_method, description) VALUES
(1, 'Inventory', 'Get All Products', 'http://localhost/module1/api/products', 'GET', 'Retrieve all products from inventory'),
(1, 'Inventory', 'Get Stock Levels', 'http://localhost/module1/api/stock', 'GET', 'Retrieve current stock levels'),
(2, 'Purchasing', 'Get Purchase Orders', 'http://localhost/module2/api/purchase_orders', 'GET', 'Retrieve all purchase orders'),
(3, 'Accounting', 'Get Financial Data', 'http://localhost/module3/api/financial', 'GET', 'Retrieve financial data'),
(4, 'HR', 'Get Employee Data', 'http://localhost/module4/api/employees', 'GET', 'Retrieve employee data'),
(5, 'Manufacturing', 'Get Production Data', 'http://localhost/module5/api/production', 'GET', 'Retrieve production data'),
(8, 'Sales', 'Get Sales Data', 'http://localhost/module8/api/sales', 'GET', 'Retrieve sales data'),
(9, 'CRM', 'Get Customer Data', 'http://localhost/module9/api/customers', 'GET', 'Retrieve customer data'),
(10, 'Projects', 'Get Project Data', 'http://localhost/module10/api/projects', 'GET', 'Retrieve project data');
