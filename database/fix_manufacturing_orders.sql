-- Fix manufacturing_orders table to allow NULL bom_id
-- This allows creating manufacturing orders without a BOM

USE manufacturing_management;

-- Modify the bom_id column to allow NULL values
ALTER TABLE manufacturing_orders 
MODIFY COLUMN bom_id INT NULL;

-- Update the foreign key constraint to handle NULL values properly
-- First drop the existing constraint if it exists
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing foreign key constraint
ALTER TABLE manufacturing_orders 
DROP FOREIGN KEY IF EXISTS manufacturing_orders_ibfk_2;

-- Add the foreign key constraint back with proper NULL handling
ALTER TABLE manufacturing_orders 
ADD CONSTRAINT manufacturing_orders_ibfk_2 
FOREIGN KEY (bom_id) REFERENCES bom_header(id) 
ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- Verify the change
DESCRIBE manufacturing_orders;