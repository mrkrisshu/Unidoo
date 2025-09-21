<?php
/**
 * Custom Report Generator
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

try {
    $conn = getDBConnection();
    
    // Get parameters
    $report_type = $_GET['report_type'] ?? '';
    $format = $_GET['report_format'] ?? 'csv';
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $include_cancelled = isset($_GET['include_cancelled']);
    $include_costs = isset($_GET['include_costs']);
    $include_materials = isset($_GET['include_materials']);
    
    if (!$report_type) {
        throw new Exception('Report type is required');
    }
    
    $filename = $report_type . '_report_' . date('Y-m-d_H-i-s');
    
    // Set headers based on format
    switch ($format) {
        case 'csv':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            break;
        case 'excel':
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
            break;
        case 'pdf':
            // For PDF, we'll generate HTML and let browser handle PDF conversion
            header('Content-Type: text/html');
            break;
        default:
            throw new Exception('Invalid format');
    }
    
    // Generate report based on type
    switch ($report_type) {
        case 'manufacturing_orders':
            generateManufacturingOrdersReport($conn, $format, $start_date, $end_date, $include_cancelled, $include_costs, $include_materials);
            break;
        case 'work_orders':
            generateWorkOrdersReport($conn, $format, $start_date, $end_date, $include_cancelled, $include_costs);
            break;
        case 'inventory':
            generateInventoryReport($conn, $format, $start_date, $end_date, $include_materials);
            break;
        case 'costs':
            generateCostAnalysisReport($conn, $format, $start_date, $end_date, $include_materials);
            break;
        case 'efficiency':
            generateEfficiencyReport($conn, $format, $start_date, $end_date);
            break;
        default:
            throw new Exception('Invalid report type');
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(400);
    echo 'Error generating report: ' . $e->getMessage();
}

function generateManufacturingOrdersReport($conn, $format, $start_date, $end_date, $include_cancelled, $include_costs, $include_materials) {
    $where_conditions = ["mo.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)"];
    $params = [$start_date, $end_date];
    
    if (!$include_cancelled) {
        $where_conditions[] = "mo.status != 'cancelled'";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $query = "
        SELECT 
            mo.order_number,
            mo.status,
            mo.priority,
            mo.quantity,
            mo.start_date,
            mo.due_date,
            mo.created_at,
            m.material_name,
            m.unit,
            u.username as created_by";
    
    if ($include_costs) {
        $query .= ", mo.material_cost, mo.labor_cost";
    }
    
    if ($include_materials) {
        $query .= ", m.description as material_description, m.unit_cost";
    }
    
    $query .= "
        FROM manufacturing_orders mo
        LEFT JOIN materials m ON mo.material_id = m.id
        LEFT JOIN users u ON mo.created_by = u.id
        WHERE {$where_clause}
        ORDER BY mo.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    if ($format === 'csv' || $format === 'excel') {
        $output = fopen('php://output', 'w');
        
        // Headers
        $headers = ['Order Number', 'Status', 'Priority', 'Material', 'Quantity', 'Unit', 'Start Date', 'Due Date', 'Created Date', 'Created By'];
        
        if ($include_costs) {
            $headers = array_merge($headers, ['Material Cost', 'Labor Cost', 'Total Cost']);
        }
        
        if ($include_materials) {
            $headers = array_merge($headers, ['Material Description', 'Unit Cost']);
        }
        
        fputcsv($output, $headers);
        
        // Data
        foreach ($orders as $order) {
            $row = [
                $order['order_number'],
                ucfirst($order['status']),
                ucfirst($order['priority']),
                $order['material_name'],
                $order['quantity'],
                $order['unit'],
                $order['start_date'],
                $order['due_date'],
                $order['created_at'],
                $order['created_by']
            ];
            
            if ($include_costs) {
                $row = array_merge($row, [
                    $order['material_cost'],
                    $order['labor_cost'],
                    ($order['material_cost'] + $order['labor_cost'])
                ]);
            }
            
            if ($include_materials) {
                $row = array_merge($row, [
                    $order['material_description'],
                    $order['unit_cost']
                ]);
            }
            
            fputcsv($output, $row);
        }
        
        fclose($output);
    } else {
        // PDF format - generate HTML
        generatePDFReport('Manufacturing Orders Report', $orders, $start_date, $end_date);
    }
}

function generateWorkOrdersReport($conn, $format, $start_date, $end_date, $include_cancelled, $include_costs) {
    $where_conditions = ["wo.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)"];
    $params = [$start_date, $end_date];
    
    if (!$include_cancelled) {
        $where_conditions[] = "wo.status != 'cancelled'";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $query = "
        SELECT 
            wo.work_order_number,
            wo.operation_name,
            wo.status,
            wo.priority,
            wo.estimated_duration,
            wo.actual_duration,
            wo.assigned_to,
            wo.created_at,
            wo.updated_at,
            mo.order_number as manufacturing_order,
            wc.center_name as work_center,
            u1.username as assigned_user,
            u2.username as created_by";
    
    if ($include_costs) {
        $query .= ", wo.labor_cost, wo.material_cost";
    }
    
    $query .= "
        FROM work_orders wo
        LEFT JOIN manufacturing_orders mo ON wo.manufacturing_order_id = mo.id
        LEFT JOIN work_centers wc ON wo.work_center_id = wc.id
        LEFT JOIN users u1 ON wo.assigned_to = u1.id
        LEFT JOIN users u2 ON wo.created_by = u2.id
        WHERE {$where_clause}
        ORDER BY wo.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    if ($format === 'csv' || $format === 'excel') {
        $output = fopen('php://output', 'w');
        
        // Headers
        $headers = ['Work Order', 'Operation', 'Status', 'Priority', 'Manufacturing Order', 'Work Center', 
                   'Assigned To', 'Estimated Duration', 'Actual Duration', 'Efficiency %', 'Created Date'];
        
        if ($include_costs) {
            $headers = array_merge($headers, ['Labor Cost', 'Material Cost', 'Total Cost']);
        }
        
        fputcsv($output, $headers);
        
        // Data
        foreach ($orders as $order) {
            $efficiency = '';
            if ($order['estimated_duration'] && $order['actual_duration']) {
                $efficiency = number_format(($order['estimated_duration'] / $order['actual_duration']) * 100, 1);
            }
            
            $row = [
                $order['work_order_number'],
                $order['operation_name'],
                ucfirst($order['status']),
                ucfirst($order['priority']),
                $order['manufacturing_order'],
                $order['work_center'],
                $order['assigned_user'],
                $order['estimated_duration'],
                $order['actual_duration'],
                $efficiency,
                $order['created_at']
            ];
            
            if ($include_costs) {
                $row = array_merge($row, [
                    $order['labor_cost'],
                    $order['material_cost'],
                    ($order['labor_cost'] + $order['material_cost'])
                ]);
            }
            
            fputcsv($output, $row);
        }
        
        fclose($output);
    } else {
        generatePDFReport('Work Orders Report', $orders, $start_date, $end_date);
    }
}

function generateInventoryReport($conn, $format, $start_date, $end_date, $include_materials) {
    $query = "
        SELECT 
            m.material_name,
            m.unit,
            m.unit_cost,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END), 0) as total_in,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END), 0) as total_out,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'adjustment' THEN sm.quantity ELSE 0 END), 0) as adjustments,
            (SELECT COALESCE(SUM(
                CASE 
                    WHEN movement_type = 'in' THEN quantity
                    WHEN movement_type = 'out' THEN -quantity
                    WHEN movement_type = 'adjustment' THEN quantity
                    ELSE 0
                END
            ), 0) FROM stock_movements WHERE material_id = m.id) as current_stock,
            COUNT(sm.id) as total_movements
        FROM materials m
        LEFT JOIN stock_movements sm ON m.id = sm.material_id 
            AND sm.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY m.id, m.material_name, m.unit, m.unit_cost
        ORDER BY m.material_name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $inventory = $stmt->fetchAll();
    
    if ($format === 'csv' || $format === 'excel') {
        $output = fopen('php://output', 'w');
        
        // Headers
        $headers = ['Material', 'Unit', 'Current Stock', 'Stock In', 'Stock Out', 'Adjustments', 'Total Movements', 'Unit Cost', 'Stock Value'];
        
        fputcsv($output, $headers);
        
        // Data
        foreach ($inventory as $item) {
            $stock_value = $item['current_stock'] * $item['unit_cost'];
            
            $row = [
                $item['material_name'],
                $item['unit'],
                $item['current_stock'],
                $item['total_in'],
                $item['total_out'],
                $item['adjustments'],
                $item['total_movements'],
                $item['unit_cost'],
                $stock_value
            ];
            
            fputcsv($output, $row);
        }
        
        fclose($output);
    } else {
        generatePDFReport('Inventory Report', $inventory, $start_date, $end_date);
    }
}

function generateCostAnalysisReport($conn, $format, $start_date, $end_date, $include_materials) {
    $query = "
        SELECT 
            mo.order_number,
            m.material_name,
            mo.quantity,
            mo.material_cost,
            mo.labor_cost,
            (mo.material_cost + mo.labor_cost) as total_cost,
            (mo.material_cost + mo.labor_cost) / mo.quantity as cost_per_unit,
            mo.status,
            mo.created_at
        FROM manufacturing_orders mo
        LEFT JOIN materials m ON mo.material_id = m.id
        WHERE mo.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        AND mo.status IN ('completed', 'in_progress')
        ORDER BY total_cost DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $costs = $stmt->fetchAll();
    
    if ($format === 'csv' || $format === 'excel') {
        $output = fopen('php://output', 'w');
        
        // Headers
        $headers = ['Order Number', 'Material', 'Quantity', 'Material Cost', 'Labor Cost', 'Total Cost', 'Cost Per Unit', 'Status', 'Date'];
        
        fputcsv($output, $headers);
        
        // Data
        foreach ($costs as $cost) {
            $row = [
                $cost['order_number'],
                $cost['material_name'],
                $cost['quantity'],
                $cost['material_cost'],
                $cost['labor_cost'],
                $cost['total_cost'],
                $cost['cost_per_unit'],
                ucfirst($cost['status']),
                $cost['created_at']
            ];
            
            fputcsv($output, $row);
        }
        
        fclose($output);
    } else {
        generatePDFReport('Cost Analysis Report', $costs, $start_date, $end_date);
    }
}

function generateEfficiencyReport($conn, $format, $start_date, $end_date) {
    $query = "
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
            wo.status,
            u.username as assigned_to,
            wo.created_at,
            wo.updated_at
        FROM work_orders wo
        LEFT JOIN work_centers wc ON wo.work_center_id = wc.id
        LEFT JOIN users u ON wo.assigned_to = u.id
        WHERE wo.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        AND wo.status = 'completed'
        AND wo.actual_duration > 0
        ORDER BY efficiency_percentage DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $efficiency = $stmt->fetchAll();
    
    if ($format === 'csv' || $format === 'excel') {
        $output = fopen('php://output', 'w');
        
        // Headers
        $headers = ['Work Center', 'Work Order', 'Operation', 'Estimated Duration', 'Actual Duration', 'Efficiency %', 'Assigned To', 'Completed Date'];
        
        fputcsv($output, $headers);
        
        // Data
        foreach ($efficiency as $item) {
            $row = [
                $item['center_name'],
                $item['work_order_number'],
                $item['operation_name'],
                $item['estimated_duration'],
                $item['actual_duration'],
                $item['efficiency_percentage'] ? number_format($item['efficiency_percentage'], 1) : 'N/A',
                $item['assigned_to'],
                $item['updated_at']
            ];
            
            fputcsv($output, $row);
        }
        
        fclose($output);
    } else {
        generatePDFReport('Efficiency Report', $efficiency, $start_date, $end_date);
    }
}

function generatePDFReport($title, $data, $start_date, $end_date) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title><?php echo $title; ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .date-range { text-align: center; color: #666; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo $title; ?></h1>
            <div class="date-range">
                Report Period: <?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>
            </div>
            <div class="no-print">
                <button onclick="window.print()">Print Report</button>
                <button onclick="window.close()">Close</button>
            </div>
        </div>
        
        <table>
            <?php if (!empty($data)): ?>
                <thead>
                    <tr>
                        <?php foreach (array_keys($data[0]) as $header): ?>
                            <th><?php echo ucwords(str_replace('_', ' ', $header)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td><?php echo htmlspecialchars($value); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php else: ?>
                <tr>
                    <td colspan="100%" class="text-center">No data available for the selected period.</td>
                </tr>
            <?php endif; ?>
        </table>
        
        <div style="margin-top: 30px; font-size: 12px; color: #666;">
            Generated on <?php echo date('M j, Y \a\t g:i A'); ?> by <?php echo htmlspecialchars($_SESSION['username']); ?>
        </div>
    </body>
    </html>
    <?php
}
?>