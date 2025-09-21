-- Sample Data Insert Script for Manufacturing Management System
-- Run these commands in phpMyAdmin to populate your database with test data

USE manufacturing_management;

-- Insert additional products (raw materials and finished goods)
INSERT INTO `products` (`product_code`, `product_name`, `product_type`, `unit_of_measure`, `unit_cost`, `current_stock`, `minimum_stock`, `description`, `is_active`) VALUES
('RM005', 'Steel Bolts', 'raw_material', 'PCS', 0.25, 2000.000, 500.000, 'High quality steel bolts', 1),
('RM006', 'Rubber Pads', 'raw_material', 'PCS', 1.50, 500.000, 100.000, 'Anti-slip rubber pads', 1),
('RM007', 'Wood Glue', 'raw_material', 'BTL', 12.00, 30.000, 5.000, 'Strong wood adhesive', 1),
('RM008', 'Sandpaper', 'raw_material', 'SHEET', 2.00, 200.000, 50.000, 'Fine grit sandpaper', 1),
('FG002', 'Wooden Chair', 'finished_good', 'PCS', 0.00, 0.000, 3.000, 'Comfortable wooden chair', 1),
('FG003', 'Wooden Desk', 'finished_good', 'PCS', 0.00, 0.000, 2.000, 'Office wooden desk', 1),
('SF001', 'Table Frame', 'semi_finished', 'PCS', 0.00, 0.000, 5.000, 'Assembled table frame without top', 1);

-- Insert BOM Headers (Bill of Materials)
INSERT INTO `bom_header` (`bom_code`, `product_id`, `version`, `quantity`, `is_active`, `created_by`) VALUES
('BOM-FG001-V1', 5, '1.0', 1.000, 1, 1),  -- BOM for Wooden Table
('BOM-FG002-V1', 9, '1.0', 1.000, 1, 1),  -- BOM for Wooden Chair
('BOM-FG003-V1', 10, '1.0', 1.000, 1, 1), -- BOM for Wooden Desk
('BOM-SF001-V1', 11, '1.0', 1.000, 1, 1); -- BOM for Table Frame

-- Insert BOM Materials (what materials are needed for each BOM)
-- BOM for Wooden Table (BOM ID 1)
INSERT INTO `bom_materials` (`bom_id`, `material_id`, `quantity_required`, `wastage_percentage`, `unit_cost`) VALUES
(1, 1, 4.000, 5.00, 5.50),  -- 4 Wooden Legs
(1, 2, 1.000, 2.00, 15.00), -- 1 Wooden Top
(1, 3, 16.000, 0.00, 0.10), -- 16 Screws
(1, 4, 0.500, 0.00, 8.00),  -- 0.5 Varnish Bottle
(1, 7, 1.000, 0.00, 1.50),  -- 1 Rubber Pad
(1, 9, 1.000, 0.00, 12.00); -- 1 Wood Glue

-- BOM for Wooden Chair (BOM ID 2)
INSERT INTO `bom_materials` (`bom_id`, `material_id`, `quantity_required`, `wastage_percentage`, `unit_cost`) VALUES
(2, 1, 4.000, 5.00, 5.50),  -- 4 Wooden Legs (for chair)
(2, 6, 1.000, 2.00, 10.00), -- 1 Chair Seat (using woodplank)
(2, 3, 12.000, 0.00, 0.10), -- 12 Screws
(2, 4, 0.300, 0.00, 8.00),  -- 0.3 Varnish Bottle
(2, 9, 0.500, 0.00, 12.00); -- 0.5 Wood Glue

-- BOM for Wooden Desk (BOM ID 3)
INSERT INTO `bom_materials` (`bom_id`, `material_id`, `quantity_required`, `wastage_percentage`, `unit_cost`) VALUES
(3, 1, 4.000, 5.00, 5.50),  -- 4 Wooden Legs
(3, 6, 2.000, 3.00, 10.00), -- 2 Wood planks for desk top
(3, 7, 8.000, 0.00, 0.25),  -- 8 Steel Bolts
(3, 4, 0.800, 0.00, 8.00),  -- 0.8 Varnish Bottle
(3, 9, 1.500, 0.00, 12.00); -- 1.5 Wood Glue

-- BOM for Table Frame (BOM ID 4)
INSERT INTO `bom_materials` (`bom_id`, `material_id`, `quantity_required`, `wastage_percentage`, `unit_cost`) VALUES
(4, 1, 4.000, 5.00, 5.50),  -- 4 Wooden Legs
(4, 3, 8.000, 0.00, 0.10),  -- 8 Screws
(4, 9, 0.500, 0.00, 12.00); -- 0.5 Wood Glue

-- Insert BOM Operations (manufacturing steps)
-- Operations for Wooden Table (BOM ID 1)
INSERT INTO `bom_operations` (`bom_id`, `operation_sequence`, `operation_name`, `work_center_id`, `setup_time_minutes`, `operation_time_minutes`, `description`) VALUES
(1, 10, 'Cut and Prepare Materials', 4, 15, 30, 'Cut wooden legs and top to size'),
(1, 20, 'Sand Components', 4, 5, 45, 'Sand all wooden components smooth'),
(1, 30, 'Assemble Frame', 1, 10, 60, 'Assemble table frame with legs'),
(1, 40, 'Attach Table Top', 1, 5, 30, 'Secure table top to frame'),
(1, 50, 'Apply Finish', 2, 20, 90, 'Apply varnish and let dry'),
(1, 60, 'Final Inspection', 3, 5, 15, 'Quality check and packaging');

-- Operations for Wooden Chair (BOM ID 2)
INSERT INTO `bom_operations` (`bom_id`, `operation_sequence`, `operation_name`, `work_center_id`, `setup_time_minutes`, `operation_time_minutes`, `description`) VALUES
(2, 10, 'Cut Chair Components', 4, 10, 25, 'Cut legs and seat components'),
(2, 20, 'Sand Chair Parts', 4, 5, 35, 'Sand all chair components'),
(2, 30, 'Assemble Chair Frame', 1, 8, 45, 'Assemble chair legs and frame'),
(2, 40, 'Attach Seat', 1, 5, 20, 'Secure seat to chair frame'),
(2, 50, 'Apply Finish', 2, 15, 60, 'Apply varnish finish'),
(2, 60, 'Quality Check', 3, 3, 10, 'Final inspection and packaging');

-- Operations for Wooden Desk (BOM ID 3)
INSERT INTO `bom_operations` (`bom_id`, `operation_sequence`, `operation_name`, `work_center_id`, `setup_time_minutes`, `operation_time_minutes`, `description`) VALUES
(3, 10, 'Prepare Desk Materials', 4, 20, 40, 'Cut and prepare all desk components'),
(3, 20, 'Sand All Components', 4, 8, 60, 'Sand desk top and legs'),
(3, 30, 'Assemble Desk Frame', 1, 15, 75, 'Assemble desk frame structure'),
(3, 40, 'Install Desk Top', 1, 10, 45, 'Secure desk top to frame'),
(3, 50, 'Apply Protective Finish', 2, 25, 120, 'Apply multiple coats of finish'),
(3, 60, 'Final Assembly Check', 3, 5, 20, 'Complete quality inspection');

-- Insert some Manufacturing Orders
INSERT INTO `manufacturing_orders` (`mo_number`, `product_id`, `bom_id`, `quantity_to_produce`, `scheduled_start_date`, `scheduled_end_date`, `status`, `priority`, `notes`, `created_by`) VALUES
('MO-2025-001', 5, 1, 10.000, '2025-01-15', '2025-01-20', 'planned', 'high', 'Rush order for wooden tables', 1),
('MO-2025-002', 9, 2, 20.000, '2025-01-18', '2025-01-25', 'planned', 'medium', 'Standard chair production run', 1),
('MO-2025-003', 10, 3, 5.000, '2025-01-22', '2025-01-30', 'planned', 'low', 'Office desk order', 1),
('MO-2025-004', 11, 4, 15.000, '2025-01-16', '2025-01-19', 'in_progress', 'high', 'Table frames for assembly', 1);

-- Insert some Stock Ledger entries (inventory movements)
INSERT INTO `stock_ledger` (`product_id`, `transaction_type`, `quantity`, `unit_cost`, `total_value`, `reference_type`, `balance_after`, `remarks`, `created_by`) VALUES
(1, 'in', 200.000, 5.50, 1100.00, 'purchase', 200.000, 'Initial stock purchase', 1),
(2, 'in', 100.000, 15.00, 1500.00, 'purchase', 100.000, 'Initial stock purchase', 1),
(3, 'in', 5000.000, 0.10, 500.00, 'purchase', 5000.000, 'Bulk screw purchase', 1),
(4, 'in', 50.000, 8.00, 400.00, 'purchase', 50.000, 'Varnish stock', 1),
(6, 'in', 10000.000, 1000.00, 10000000.00, 'purchase', 10000.000, 'Wood plank inventory', 1),
(7, 'in', 2000.000, 0.25, 500.00, 'purchase', 2000.000, 'Steel bolts inventory', 1),
(8, 'in', 500.000, 1.50, 750.00, 'purchase', 500.000, 'Rubber pads stock', 1),
(9, 'in', 30.000, 12.00, 360.00, 'purchase', 30.000, 'Wood glue inventory', 1),
(10, 'in', 200.000, 2.00, 400.00, 'purchase', 200.000, 'Sandpaper stock', 1);

-- Insert additional users for testing
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`, `phone`, `is_active`) VALUES
('manager1', 'manager@manufacturing.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Production Manager', 'manager', '555-0101', 1),
('operator1', 'operator1@manufacturing.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Machine Operator 1', 'operator', '555-0102', 1),
('operator2', 'operator2@manufacturing.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Machine Operator 2', 'operator', '555-0103', 1),
('inventory1', 'inventory@manufacturing.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Inventory Manager', 'inventory', '555-0104', 1);

-- Verify the data insertion
SELECT 'Products Count:' as Info, COUNT(*) as Count FROM products WHERE is_active = 1
UNION ALL
SELECT 'BOM Headers Count:', COUNT(*) FROM bom_header WHERE is_active = 1
UNION ALL
SELECT 'BOM Materials Count:', COUNT(*) FROM bom_materials
UNION ALL
SELECT 'BOM Operations Count:', COUNT(*) FROM bom_operations
UNION ALL
SELECT 'Manufacturing Orders Count:', COUNT(*) FROM manufacturing_orders
UNION ALL
SELECT 'Work Centers Count:', COUNT(*) FROM work_centers WHERE is_active = 1
UNION ALL
SELECT 'Users Count:', COUNT(*) FROM users WHERE is_active = 1
UNION ALL
SELECT 'Stock Ledger Entries:', COUNT(*) FROM stock_ledger;

-- Show sample data from key tables
SELECT 'PRODUCTS:' as TableName, product_code, product_name, product_type, current_stock FROM products WHERE is_active = 1 LIMIT 5;
SELECT 'BOM HEADERS:' as TableName, bom_code, version, quantity FROM bom_header WHERE is_active = 1;
SELECT 'MANUFACTURING ORDERS:' as TableName, mo_number, quantity_to_produce, status, priority FROM manufacturing_orders LIMIT 5;