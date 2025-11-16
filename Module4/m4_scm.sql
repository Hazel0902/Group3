CREATE DATABASE IF NOT EXISTS erp_scm;
USE erp_scm;

-- Table: scm_forecast
CREATE TABLE IF NOT EXISTS scm_forecast (
  forecast_id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  forecast_period DATE NOT NULL,     
  forecast_qty INT NOT NULL DEFAULT 0,
  confidence_level DECIMAL(5,2) DEFAULT 0.00, 
  source VARCHAR(50) DEFAULT 'auto',    
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_product_period (product_id, forecast_period)
);

-- Table: scm_suppliers
CREATE TABLE IF NOT EXISTS scm_suppliers (
  supplier_id INT PRIMARY KEY,
  supplier_name VARCHAR(255) NOT NULL,
  delivery_status VARCHAR(50) DEFAULT 'Unknown',
  expected_delivery_date DATE NULL,
  last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  contact_info TEXT,
  rating DECIMAL(3,2) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: scm_shipments
CREATE TABLE IF NOT EXISTS scm_shipments (
  shipment_id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL,
  warehouse_id INT DEFAULT NULL,
  route_id VARCHAR(100) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'Pending', 
  departure_date DATETIME NULL,
  arrival_date DATETIME NULL,
  tracking_no VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_supplier_status (supplier_id, status)
);

-- Table: scm_distribution (inter-warehouse transfers / allocation)
CREATE TABLE IF NOT EXISTS scm_distribution (
  distribution_id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  warehouse_from INT DEFAULT NULL,
  warehouse_to INT DEFAULT NULL,
  transfer_qty INT NOT NULL DEFAULT 0,
  transfer_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(50) DEFAULT 'Scheduled',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_product_transfer (product_id, warehouse_from, warehouse_to)
);

-- Optional Audit / Logs
CREATE TABLE IF NOT EXISTS scm_sync_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  job_name VARCHAR(100) NOT NULL,
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  finished_at TIMESTAMP NULL,
  status VARCHAR(50) DEFAULT 'running',
  message TEXT
);
