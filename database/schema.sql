-- Manufacturing Management System Database Schema
-- Version: 1.0.0 - Complete Implementation
-- Created for NMIT Hackathon Problem Statement 1

CREATE DATABASE IF NOT EXISTS manufacturing_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE manufacturing_management;

-- Users table for authentication and role management
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'operator', 'inventory') NOT NULL DEFAULT 'operator',
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User sessions for OTP and session management
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    otp_code VARCHAR(6),
    otp_expires_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Products master table (raw materials and finished goods)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_code VARCHAR(50) UNIQUE NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    product_type ENUM('raw_material', 'finished_good', 'semi_finished') NOT NULL,
    unit_of_measure VARCHAR(20) NOT NULL,
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    current_stock DECIMAL(10,3) DEFAULT 0.000,
    minimum_stock DECIMAL(10,3) DEFAULT 0.000,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Work centers (manufacturing locations and resources)
CREATE TABLE work_centers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    center_code VARCHAR(50) UNIQUE NOT NULL,
    center_name VARCHAR(100) NOT NULL,
    description TEXT,
    hourly_cost DECIMAL(8,2) DEFAULT 0.00,
    capacity_per_hour DECIMAL(8,2) DEFAULT 1.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- BOM header (Bill of Materials definitions)
CREATE TABLE bom_header (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bom_code VARCHAR(50) UNIQUE NOT NULL,
    product_id INT NOT NULL,
    version VARCHAR(10) DEFAULT '1.0',
    quantity DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- BOM materials (components and quantities)
CREATE TABLE bom_materials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bom_id INT NOT NULL,
    material_id INT NOT NULL,
    quantity_required DECIMAL(10,3) NOT NULL,
    wastage_percentage DECIMAL(5,2) DEFAULT 0.00,
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bom_id) REFERENCES bom_header(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES products(id)
);

-- BOM operations (manufacturing steps and time)
CREATE TABLE bom_operations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bom_id INT NOT NULL,
    operation_sequence INT NOT NULL,
    operation_name VARCHAR(100) NOT NULL,
    work_center_id INT NOT NULL,
    setup_time_minutes INT DEFAULT 0,
    operation_time_minutes INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bom_id) REFERENCES bom_header(id) ON DELETE CASCADE,
    FOREIGN KEY (work_center_id) REFERENCES work_centers(id)
);

-- Manufacturing orders (production orders)
CREATE TABLE manufacturing_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mo_number VARCHAR(50) UNIQUE NOT NULL,
    product_id INT NOT NULL,
    bom_id INT NOT NULL,
    quantity_to_produce DECIMAL(10,3) NOT NULL,
    quantity_produced DECIMAL(10,3) DEFAULT 0.000,
    scheduled_start_date DATE NOT NULL,
    scheduled_end_date DATE,
    actual_start_date DATE,
    actual_end_date DATE,
    assignee_id INT,
    status ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (bom_id) REFERENCES bom_header(id),
    FOREIGN KEY (assignee_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Work orders (individual manufacturing steps)
CREATE TABLE work_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    wo_number VARCHAR(50) UNIQUE NOT NULL,
    mo_id INT NOT NULL,
    operation_id INT NOT NULL,
    work_center_id INT NOT NULL,
    operation_name VARCHAR(100) NOT NULL,
    assigned_to INT,
    planned_start_time DATETIME,
    planned_end_time DATETIME,
    actual_start_time DATETIME,
    actual_end_time DATETIME,
    status ENUM('pending', 'started', 'paused', 'completed', 'cancelled') DEFAULT 'pending',
    setup_time_minutes INT DEFAULT 0,
    operation_time_minutes INT NOT NULL,
    actual_time_minutes INT DEFAULT 0,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mo_id) REFERENCES manufacturing_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (operation_id) REFERENCES bom_operations(id),
    FOREIGN KEY (work_center_id) REFERENCES work_centers(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Stock ledger (all material movements)
CREATE TABLE stock_ledger (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    transaction_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    total_value DECIMAL(12,2) DEFAULT 0.00,
    reference_type ENUM('mo', 'wo', 'adjustment', 'purchase', 'sale') NOT NULL,
    reference_id INT,
    balance_after DECIMAL(10,3) NOT NULL,
    remarks TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Insert default admin user
INSERT INTO users (username, email, password_hash, full_name, role) VALUES 
('admin', 'admin@manufacturing.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');

-- Insert sample work centers
INSERT INTO work_centers (center_code, center_name, description, hourly_cost, capacity_per_hour) VALUES
('ASM001', 'Assembly Line', 'Main assembly line for product assembly', 25.00, 10.00),
('PNT001', 'Paint Floor', 'Painting and finishing operations', 30.00, 8.00),
('PCK001', 'Packaging Line', 'Final packaging and quality check', 20.00, 15.00),
('CUT001', 'Cutting Station', 'Material cutting and preparation', 35.00, 5.00);

-- Insert sample raw materials
INSERT INTO products (product_code, product_name, product_type, unit_of_measure, unit_cost, current_stock, minimum_stock) VALUES
('RM001', 'Wooden Legs', 'raw_material', 'PCS', 5.50, 200.000, 50.000),
('RM002', 'Wooden Top', 'raw_material', 'PCS', 15.00, 100.000, 20.000),
('RM003', 'Screws', 'raw_material', 'PCS', 0.10, 5000.000, 1000.000),
('RM004', 'Varnish Bottle', 'raw_material', 'BTL', 8.00, 50.000, 10.000),
('FG001', 'Wooden Table', 'finished_good', 'PCS', 0.00, 0.000, 5.000);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_products_code ON products(product_code);
CREATE INDEX idx_mo_status ON manufacturing_orders(status);
CREATE INDEX idx_wo_status ON work_orders(status);
CREATE INDEX idx_stock_ledger_product ON stock_ledger(product_id);
CREATE INDEX idx_stock_ledger_date ON stock_ledger(created_at);