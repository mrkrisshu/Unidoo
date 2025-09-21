<?php
/**
 * Export All Reports
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

try {
    $conn = getDBConnection();
    
    // Get date range
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    // Create temporary directory for reports
    $temp_dir = sys_get_temp_dir() . '/manufacturing_reports_' . uniqid();
    if (!mkdir($temp_dir, 0777, true)) {
        throw new Exception('Failed to create temporary directory');
    }
    
    // Generate all reports
    generateAllReports($conn, $temp_dir, $start_date, $end_date);
    
    // Create ZIP file
    $zip_filename = 'manufacturing_reports_' . date('Y-m-d_H-i-s') . '.zip';
    $zip_path = $temp_dir . '/' . $zip_filename;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        throw new Exception('Failed to create ZIP file');
    }
    
    // Add all CSV files to ZIP
    $files = glob($temp_dir . '/*.csv');
    foreach ($files as $file) {
        $zip->addFile($file, basename($file));
    }
    
    $zip->close();
    
    // Send ZIP file to browser
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    
    readfile($zip_path);
    
    // Clean up temporary files
    array_map('unlink', glob($temp_dir . '/*'));
    rmdir($temp_dir);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'Error generating reports: ' . $e->getMessage();
}

function generateAllReports($conn, $temp_dir, $start_date, $end_date) {
    // 1. Manufacturing Orders Report
    generateManufacturingOrdersCSV($conn, $temp_dir, $start_date, $end_date);
    
    // 2. Work Orders Report
    generateWorkOrdersCSV($conn, $temp_dir, $start_date, $end_date);
    
    // 3. Inventory Report
    generateInventoryCSV($conn, $temp_dir, $start_date, $end_date);
    
    // 4. Stock Movements Report
    generateStockMovementsCSV($conn, $temp_dir, $start_date, $end_date);
    
    // 5. Work Centers Report
    generateWorkCentersCSV($conn, $temp_dir, $start_date, $end_date);
    
    // 6. Cost Analysis Report
    generateCostAnalysisCSV($conn, $temp_dir, $start_date, $end_date);
    
    // 7. Efficiency Report
    generateEfficiencyCSV($conn, $temp_dir, $start_date, $end_date);
    
    // 8. Materials Usage Report
    generateMaterialsUsageCSV($conn, $temp_dir, $start_date, $end_date);
    
    // 9. Summary Report
    generateSummaryCSV($conn, $temp_dir, $start_date, $end_date);
}

function generateManufacturingOrdersCSV($conn, $temp_dir, $start_date, $end_date) {
    $stmt = $conn->prepare("
        SELECT 
            mo.order_number,
            mo.status,
            mo.priority,
            mo.quantity,
            mo.start_date,
            mo.due_date,
            mo.created_at,
            mo.material_cost,
            mo.labor_cost,
            (mo.material_cost + mo.labor_cost) as total_cost,
            m.material_name,
            m.unit,
            u.username as created_by,
            COUNT(wo.id) as work_orders_count,
            SUM(CASE WHEN wo.status = 'completed' THEN 1 ELSE 0 END) as completed_work_orders
        FROM manufacturing_orders mo
        LEFT JOIN materials m ON mo.material_id = m.id
        LEFT JOIN users u ON mo.created_by = u.id
        LEFT JOIN work_orders wo ON mo.id = wo.manufacturing_order_id
        WHERE mo.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY mo.id
        ORDER BY mo.created_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $orders = $stmt->fetchAll();
    
    $file = fopen($temp_dir . '/manufacturing_orders.csv', 'w');
    
    // Headers
    fputcsv($file, [
        'Order Number', 'Status', 'Priority', 'Material', 'Quantity', 'Unit',
        'Start Date', 'Due Date', 'Created Date', 'Created By',
        'Material Cost', 'Labor Cost', 'Total Cost',
        'Work Orders', 'Completed Work Orders', 'Progress %'
    ]);
    
    // Data
    foreach ($orders as $order) {
        $progress = $order['work_orders_count'] > 0 ? 
            ($order['completed_work_orders'] / $order['work_orders_count']) * 100 : 0;
        
        fputcsv($file, [
            $order['order_number'],
            ucfirst($order['status']),
            ucfirst($order['priority']),
            $order['material_name'],
            $order['quantity'],
            $order['unit'],
            $order['start_date'],
            $order['due_date'],
            $order['created_at'],
            $order['created_by'],
            $order['material_cost'],
            $order['labor_cost'],
            $order['total_cost'],
            $order['work_orders_count'],
            $order['completed_work_orders'],
            number_format($progress, 1)
        ]);
    }
    
    fclose($file);
}

function generateWorkOrdersCSV($conn, $temp_dir, $start_date, $end_date) {
    $stmt = $conn->prepare("
        SELECT 
            wo.work_order_number,
            wo.operation_name,
            wo.status,
            wo.priority,
            wo.estimated_duration,
            wo.actual_duration,
            CASE 
                WHEN wo.actual_duration > 0 AND wo.estimated_duration > 0 
                THEN (wo.estimated_duration / wo.actual_duration) * 100 
                ELSE NULL 
            END as efficiency,
            wo.labor_cost,
            wo.material_cost,
            wo.created_at,
            wo.updated_at,
            mo.order_number as manufacturing_order,
            wc.center_name as work_center,
            u1.username as assigned_to,
            u2.username as created_by
        FROM work_orders wo
        LEFT JOIN manufacturing_orders mo ON wo.manufacturing_order_id = mo.id
        LEFT JOIN work_centers wc ON wo.work_center_id = wc.id
        LEFT JOIN users u1 ON wo.assigned_to = u1.id
        LEFT JOIN users u2 ON wo.created_by = u2.id
        WHERE wo.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY wo.created_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $orders = $stmt->fetchAll();
    
    $file = fopen($temp_dir . '/work_orders.csv', 'w');
    
    // Headers
    fputcsv($file, [
        'Work Order Number', 'Operation', 'Status', 'Priority',
        'Manufacturing Order', 'Work Center', 'Assigned To',
        'Estimated Duration', 'Actual Duration', 'Efficiency %',
        'Labor Cost', 'Material Cost', 'Total Cost',
        'Created Date', 'Updated Date', 'Created By'
    ]);
    
    // Data
    foreach ($orders as $order) {
        fputcsv($file, [
            $order['work_order_number'],
            $order['operation_name'],
            ucfirst($order['status']),
            ucfirst($order['priority']),
            $order['manufacturing_order'],
            $order['work_center'],
            $order['assigned_to'],
            $order['estimated_duration'],
            $order['actual_duration'],
            $order['efficiency'] ? number_format($order['efficiency'], 1) : 'N/A',
            $order['labor_cost'],
            $order['material_cost'],
            ($order['labor_cost'] + $order['material_cost']),
            $order['created_at'],
            $order['updated_at'],
            $order['created_by']
        ]);
    }
    
    fclose($file);
}

function generateInventoryCSV($conn, $temp_dir, $start_date, $end_date) {
    $stmt = $conn->prepare("
        SELECT 
            m.material_name,
            m.description,
            m.unit,
            m.unit_cost,
            (SELECT COALESCE(SUM(
                CASE 
                    WHEN movement_type = 'in' THEN quantity
                    WHEN movement_type = 'out' THEN -quantity
                    WHEN movement_type = 'adjustment' THEN quantity
                    ELSE 0
                END
            ), 0) FROM stock_movements WHERE material_id = m.id) as current_stock,
            m.reorder_level,
            m.max_stock_level,
            CASE 
                WHEN (SELECT COALESCE(SUM(
                    CASE 
                        WHEN movement_type = 'in' THEN quantity
                        WHEN movement_type = 'out' THEN -quantity
                        WHEN movement_type = 'adjustment' THEN quantity
                        ELSE 0
                    END
                ), 0) FROM stock_movements WHERE material_id = m.id) <= m.reorder_level 
                THEN 'Low Stock'
                ELSE 'Normal'
            END as stock_status,
            m.created_at
        FROM materials m
        ORDER BY m.material_name
    ");
    $stmt->execute();
    $materials = $stmt->fetchAll();
    
    $file = fopen($temp_dir . '/inventory.csv', 'w');
    
    // Headers
    fputcsv($file, [
        'Material Name', 'Description', 'Unit', 'Unit Cost',
        'Current Stock', 'Stock Value', 'Reorder Level', 'Max Level',
        'Stock Status', 'Created Date'
    ]);
    
    // Data
    foreach ($materials as $material) {
        $stock_value = $material['current_stock'] * $material['unit_cost'];
        
        fputcsv($file, [
            $material['material_name'],
            $material['description'],
            $material['unit'],
            $material['unit_cost'],
            $material['current_stock'],
            number_format($stock_value, 2),
            $material['reorder_level'],
            $material['max_stock_level'],
            $material['stock_status'],
            $material['created_at']
        ]);
    }
    
    fclose($file);
}

function generateStockMovementsCSV($conn, $temp_dir, $start_date, $end_date) {
    $stmt = $conn->prepare("
        SELECT 
            sm.created_at,
            m.material_name,
            sm.movement_type,
            sm.quantity,
            sm.location,
            sm.reference_type,
            sm.reference_id,
            sm.notes,
            u.username as created_by
        FROM stock_movements sm
        LEFT JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.created_by = u.id
        WHERE sm.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY sm.created_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $movements = $stmt->fetchAll();
    
    $file = fopen($temp_dir . '/stock_movements.csv', 'w');
    
    // Headers
    fputcsv($file, [
        'Date', 'Material', 'Movement Type', 'Quantity', 'Location',
        'Reference Type', 'Reference ID', 'Notes', 'Created By'
    ]);
    
    // Data
    foreach ($movements as $movement) {
        fputcsv($file, [
            $movement['created_at'],
            $movement['material_name'],
            ucfirst($movement['movement_type']),
            $movement['quantity'],
            $movement['location'],
            $movement['reference_type'],
            $movement['reference_id'],
            $movement['notes'],
            $movement['created_by']
        ]);
    }
    
    fclose($file);
}

function generateWorkCentersCSV($conn, $temp_dir, $start_date, $end_date) {
    $stmt = $conn->prepare("
        SELECT 
            wc.center_name,
            wc.description,
            wc.capacity_per_hour,
            wc.hourly_rate,
            wc.status,
            COUNT(wo.id) as total_work_orders,
            SUM(CASE WHEN wo.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            AVG(CASE WHEN wo.status = 'completed' AND wo.actual_duration > 0 THEN wo.actual_duration ELSE NULL END) as avg_duration,
            SUM(CASE WHEN wo.status = 'completed' THEN wo.labor_cost ELSE 0 END) as total_revenue,
            AVG(CASE 
                WHEN wo.status = 'completed' AND wo.estimated_duration > 0 AND wo.actual_duration > 0 
                THEN (wo.estimated_duration / wo.actual_duration) * 100 
                ELSE NULL 
            END) as avg_efficiency,
            wc.created_at
        FROM work_centers wc
        LEFT JOIN work_orders wo ON wc.id = wo.work_center_id 
            AND wo.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY wc.id
        ORDER BY wc.center_name
    ");
    $stmt->execute([$start_date, $end_date]);
    $centers = $stmt->fetchAll();
    
    $file = fopen($temp_dir . '/work_centers.csv', 'w');
    
    // Headers
    fputcsv($file, [
        'Work Center', 'Description', 'Capacity/Hour', 'Hourly Rate', 'Status',
        'Total Work Orders', 'Completed Orders', 'Completion Rate %',
        'Avg Duration', 'Total Revenue', 'Avg Efficiency %', 'Created Date'
    ]);
    
    // Data
    foreach ($centers as $center) {
        $completion_rate = $center['total_work_orders'] > 0 ? 
            ($center['completed_orders'] / $center['total_work_orders']) * 100 : 0;
        
        fputcsv($file, [
            $center['center_name'],
            $center['description'],
            $center['capacity_per_hour'],
            $center['hourly_rate'],
            ucfirst($center['status']),
            $center['total_work_orders'],
            $center['completed_orders'],
            number_format($completion_rate, 1),
            $center['avg_duration'] ? number_format($center['avg_duration'], 1) : 'N/A',
            number_format($center['total_revenue'], 2),
            $center['avg_efficiency'] ? number_format($center['avg_efficiency'], 1) : 'N/A',
            $center['created_at']
        ]);
    }
    
    fclose($file);
}

function generateCostAnalysisCSV($conn, $temp_dir, $start_date, $end_date) {
    $stmt = $conn->prepare("
        SELECT 
            mo.order_number,
            m.material_name,
            mo.quantity,
            mo.material_cost,
            mo.labor_cost,
            (mo.material_cost + mo.labor_cost) as total_cost,
            (mo.material_cost + mo.labor_cost) / mo.quantity as cost_per_unit,
            mo.material_cost / (mo.material_cost + mo.labor_cost) * 100 as material_cost_percentage,
            mo.labor_cost / (mo.material_cost + mo.labor_cost) * 100 as labor_cost_percentage,
            mo.status,
            mo.created_at
        FROM manufacturing_orders mo
        LEFT JOIN materials m ON mo.material_id = m.id
        WHERE mo.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        AND (mo.material_cost > 0 OR mo.labor_cost > 0)
        ORDER BY total_cost DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $costs = $stmt->fetchAll();
    
    $file = fopen($temp_dir . '/cost_analysis.csv', 'w');
    
    // Headers
    fputcsv($file, [
        'Order Number', 'Material', 'Quantity', 'Material Cost', 'Labor Cost',
        'Total Cost', 'Cost Per Unit', 'Material Cost %', 'Labor Cost %',
        'Status', 'Date'
    ]);
    
    // Data
    foreach ($costs as $cost) {
        fputcsv($file, [
            $cost['order_number'],
            $cost['material_name'],
            $cost['quantity'],
            number_format($cost['material_cost'], 2),
            number_format($cost['labor_cost'], 2),
            number_format($cost['total_cost'], 2),
            number_format($cost['cost_per_unit'], 2),
            number_format($cost['material_cost_percentage'], 1),
            number_format($cost['labor_cost_percentage'], 1),
            ucfirst($cost['status']),
            $cost['created_at']
        ]);
    }
    
    fclose($file);
}

function generateEfficiencyCSV($conn, $temp_dir, $start_date, $end_date) {
    $stmt = $conn->prepare("
        SELECT 
            wc.center_name,
            wo.work_order_number,
            wo.operation_name,
            wo.estimated_duration,
            wo.actual_duration,
            CASE 
                WHEN wo.actual_duration > 0 AND wo.estimated_duration > 0 
                THEN (wo.estimated_duration / wo.actual_duration) * 100 
                ELSE NULL 
            END as efficiency_percentage,
            CASE 
                WHEN wo.actual_duration > wo.estimated_duration 
                THEN wo.actual_duration - wo.estimated_duration
                ELSE 0
            END as time_overrun,
            wo.labor_cost,
            u.username as assigned_to,
            wo.updated_at
        FROM work_orders wo
        LEFT JOIN work_centers wc ON wo.work_center_id = wc.id
        LEFT JOIN users u ON wo.assigned_to = u.id
        WHERE wo.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        AND wo.status = 'completed'
        AND wo.actual_duration > 0
        ORDER BY efficiency_percentage DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $efficiency = $stmt->fetchAll();
    
    $file = fopen($temp_dir . '/efficiency_analysis.csv', 'w');
    
    // Headers
    fputcsv($file, [
        'Work Center', 'Work Order', 'Operation', 'Estimated Duration',
        'Actual Duration', 'Efficiency %', 'Time Overrun', 'Labor Cost',
        'Assigned To', 'Completed Date'
    ]);
    
    // Data
    foreach ($efficiency as $item) {
        fputcsv($file, [
            $item['center_name'],
            $item['work_order_number'],
            $item['operation_name'],
            $item['estimated_duration'],
            $item['actual_duration'],
            $item['efficiency_percentage'] ? number_format($item['efficiency_percentage'], 1) : 'N/A',
            $item['time_overrun'],
            number_format($item['labor_cost'], 2),
            $item['assigned_to'],
            $item['updated_at']
        ]);
    }
    
    fclose($file);
}

function generateMaterialsUsageCSV($conn, $temp_dir, $start_date, $end_date) {
    $stmt = $conn->prepare("
        SELECT 
            m.material_name,
            m.unit,
            m.unit_cost,
            SUM(ABS(sm.quantity)) as total_usage,
            COUNT(sm.id) as movement_count,
            SUM(ABS(sm.quantity)) * m.unit_cost as usage_value,
            AVG(ABS(sm.quantity)) as avg_usage_per_movement
        FROM materials m
        INNER JOIN stock_movements sm ON m.id = sm.material_id
        WHERE sm.movement_type = 'out' 
        AND sm.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY m.id, m.material_name, m.unit, m.unit_cost
        ORDER BY total_usage DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $usage = $stmt->fetchAll();
    
    $file = fopen($temp_dir . '/materials_usage.csv', 'w');
    
    // Headers
    fputcsv($file, [
        'Material Name', 'Unit', 'Unit Cost', 'Total Usage',
        'Usage Value', 'Movement Count', 'Avg Usage Per Movement'
    ]);
    
    // Data
    foreach ($usage as $item) {
        fputcsv($file, [
            $item['material_name'],
            $item['unit'],
            number_format($item['unit_cost'], 2),
            number_format($item['total_usage'], 2),
            number_format($item['usage_value'], 2),
            $item['movement_count'],
            number_format($item['avg_usage_per_movement'], 2)
        ]);
    }
    
    fclose($file);
}

function generateSummaryCSV($conn, $temp_dir, $start_date, $end_date) {
    // Get summary statistics
    $stmt = $conn->prepare("
        SELECT 
            'Manufacturing Orders' as metric,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(material_cost + labor_cost) as total_cost
        FROM manufacturing_orders 
        WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        
        UNION ALL
        
        SELECT 
            'Work Orders' as metric,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(labor_cost + material_cost) as total_cost
        FROM work_orders 
        WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        
        UNION ALL
        
        SELECT 
            'Stock Movements' as metric,
            COUNT(*) as total,
            COUNT(DISTINCT material_id) as completed,
            0 as total_cost
        FROM stock_movements 
        WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
    $summary = $stmt->fetchAll();
    
    $file = fopen($temp_dir . '/summary_report.csv', 'w');
    
    // Headers
    fputcsv($file, ['Report Summary', 'Period: ' . $start_date . ' to ' . $end_date]);
    fputcsv($file, ['Metric', 'Total', 'Completed/Unique', 'Total Cost']);
    
    // Data
    foreach ($summary as $item) {
        fputcsv($file, [
            $item['metric'],
            $item['total'],
            $item['completed'],
            number_format($item['total_cost'], 2)
        ]);
    }
    
    fclose($file);
}
?>