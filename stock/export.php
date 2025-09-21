<?php
/**
 * Stock Export
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$export_type = $_GET['type'] ?? 'stock';
$format = $_GET['format'] ?? 'csv';

// Get filter parameters (same as index.php)
$search = $_GET['search'] ?? '';
$material_id = $_GET['material_id'] ?? '';
$location = $_GET['location'] ?? '';
$low_stock = isset($_GET['low_stock']) ? 1 : 0;

try {
    $conn = getDBConnection();
    
    if ($export_type === 'movements') {
        exportMovements($conn, $format);
    } else {
        exportStock($conn, $format, $search, $material_id, $location, $low_stock);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Export failed: ' . $e->getMessage();
}

function exportStock($conn, $format, $search, $material_id, $location, $low_stock) {
    // Build WHERE clause (same logic as index.php)
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(m.material_name LIKE ? OR m.material_code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($material_id) {
        $where_conditions[] = "m.id = ?";
        $params[] = $material_id;
    }
    
    if ($location) {
        $where_conditions[] = "sl.location = ?";
        $params[] = $location;
    }
    
    if ($low_stock) {
        $where_conditions[] = "sl.current_stock <= m.minimum_stock";
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get stock data
    $stmt = $conn->prepare("
        SELECT 
            m.material_code,
            m.material_name,
            m.unit,
            sl.location,
            sl.current_stock,
            m.minimum_stock,
            m.maximum_stock,
            m.cost_per_unit,
            (sl.current_stock * m.cost_per_unit) as stock_value,
            CASE 
                WHEN sl.current_stock <= 0 THEN 'Out of Stock'
                WHEN sl.current_stock <= m.minimum_stock THEN 'Low Stock'
                WHEN sl.current_stock >= m.maximum_stock THEN 'Overstock'
                ELSE 'Normal'
            END as stock_status,
            sl.updated_at
        FROM stock_ledger sl
        JOIN materials m ON sl.material_id = m.id
        $where_clause
        ORDER BY m.material_name, sl.location
    ");
    $stmt->execute($params);
    $stock_data = $stmt->fetchAll();
    
    if ($format === 'csv') {
        // Set headers for CSV download
        $filename = 'stock_report_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Material Code',
            'Material Name',
            'Unit',
            'Location',
            'Current Stock',
            'Minimum Stock',
            'Maximum Stock',
            'Unit Cost',
            'Stock Value',
            'Status',
            'Last Updated'
        ]);
        
        // CSV data
        foreach ($stock_data as $row) {
            fputcsv($output, [
                $row['material_code'],
                $row['material_name'],
                $row['unit'],
                $row['location'] ?: 'Default',
                number_format($row['current_stock'], 2),
                number_format($row['minimum_stock'], 0),
                number_format($row['maximum_stock'], 0),
                number_format($row['cost_per_unit'], 2),
                number_format($row['stock_value'], 2),
                $row['stock_status'],
                $row['updated_at'] ? date('Y-m-d H:i:s', strtotime($row['updated_at'])) : ''
            ]);
        }
        
        fclose($output);
    }
}

function exportMovements($conn, $format) {
    $material_id = intval($_GET['material_id'] ?? 0);
    $days = intval($_GET['days'] ?? 30);
    
    // Build query for movements
    $where_conditions = ["sm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"];
    $params = [$days];
    
    if ($material_id) {
        $where_conditions[] = "sm.material_id = ?";
        $params[] = $material_id;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $stmt = $conn->prepare("
        SELECT 
            sm.created_at,
            m.material_code,
            m.material_name,
            m.unit,
            sm.movement_type,
            sm.quantity,
            sm.location,
            sm.notes,
            u.username as created_by,
            wo.wo_number,
            mo.mo_number,
            CASE 
                WHEN sm.work_order_id IS NOT NULL THEN 'Work Order'
                WHEN sm.mo_id IS NOT NULL THEN 'Manufacturing Order'
                WHEN sm.reference_type = 'manual_adjustment' THEN 'Manual Adjustment'
                WHEN sm.reference_type = 'stock_reservation' THEN 'Stock Reservation'
                WHEN sm.reference_type = 'stock_release' THEN 'Stock Release'
                ELSE 'Other'
            END as movement_source
        FROM stock_movements sm
        JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.created_by = u.id
        LEFT JOIN work_orders wo ON sm.work_order_id = wo.id
        LEFT JOIN manufacturing_orders mo ON sm.mo_id = mo.id
        $where_clause
        ORDER BY sm.created_at DESC
        LIMIT 10000
    ");
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
    
    if ($format === 'csv') {
        // Set headers for CSV download
        $filename = 'stock_movements_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Date',
            'Time',
            'Material Code',
            'Material Name',
            'Unit',
            'Movement Type',
            'Quantity',
            'Location',
            'Source',
            'Reference',
            'Notes',
            'Created By'
        ]);
        
        // CSV data
        foreach ($movements as $row) {
            $reference = '';
            if ($row['wo_number']) {
                $reference = $row['wo_number'];
            } elseif ($row['mo_number']) {
                $reference = $row['mo_number'];
            }
            
            fputcsv($output, [
                date('Y-m-d', strtotime($row['created_at'])),
                date('H:i:s', strtotime($row['created_at'])),
                $row['material_code'],
                $row['material_name'],
                $row['unit'],
                strtoupper($row['movement_type']),
                number_format($row['quantity'], 2),
                $row['location'] ?: 'Default',
                $row['movement_source'],
                $reference,
                $row['notes'],
                $row['created_by']
            ]);
        }
        
        fclose($output);
    }
}
?>