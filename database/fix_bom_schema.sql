-- Database Schema Fixes for BOM Module
-- Run these SQL commands to fix the database schema mismatches

-- Fix 1: Update bom_header table - Add missing columns
ALTER TABLE bom_header 
ADD COLUMN bom_name VARCHAR(100) NOT NULL AFTER bom_code,
ADD COLUMN description TEXT AFTER version;

-- Fix 2: Update bom_materials table - Fix column names and add missing column
ALTER TABLE bom_materials 
CHANGE COLUMN quantity_required quantity DECIMAL(10,3) NOT NULL,
ADD COLUMN notes TEXT AFTER unit_cost;

-- Fix 3: Update bom_operations table - Add missing column and make work_center_id nullable
ALTER TABLE bom_operations 
ADD COLUMN cost_per_hour DECIMAL(8,2) DEFAULT 0.00 AFTER operation_time_minutes,
MODIFY COLUMN work_center_id INT NULL;

-- Verify the changes
DESCRIBE bom_header;
DESCRIBE bom_materials;
DESCRIBE bom_operations;