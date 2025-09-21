<?php
/**
 * Export Work Centers
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$export_type = $_GET['export_type'] ?? 'centers';

try {
    $conn = getDBConnection();
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(center_name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status) {
        $where_conditions[] = "status = ?";
        $params[] = $status;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Set headers for CSV download
    $filename = 'work_centers_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    if ($export_type === 'performance') {
        exportPerformanceData($conn, $output, $where_clause, $params);
    } else {
        exportWorkCenters($conn, $output, $where_clause, $params);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Export failed: ' . $e->getMessage();
}

function exportWorkCenters($conn, $output, $where_clause, $params) {
    // CSV headers
    fputcsv($output, [
        'Work Center ID',
        'Work Center Name',
        'Description',
        'Status',
        'Capacity',
        'Hourly Rate',
        'Location',
        'Total Orders',
        'Active Orders',
        'Completed Orders',
        'Utilization %',
        'Total Labor Cost',
        'Average Duration (hrs)',
        'Created Date',
        'Created By',
        'Last Updated',
        'Updated By',
        'Notes'
    ]);
    
    // Get work centers with statistics
    $stmt = $conn->prepare("
        SELECT wc.*, 
               created_user.username as created_by_name,
               updated_user.username as updated_by_name,
               COALESCE(stats.total_orders, 0) as total_orders,
               COALESCE(stats.active_orders, 0) as active_orders,
               COALESCE(stats.completed_orders, 0) as completed_orders,
               COALESCE(stats.total_labor_cost, 0) as total_labor_cost,
               COALESCE(stats.avg_duration, 0) as avg_duration
        FROM work_centers wc
        LEFT JOIN users created_user ON wc.created_by = created_user.id
        LEFT JOIN users updated_user ON wc.updated_by = updated_user.id
        LEFT JOIN (
            SELECT 
                work_center_id,
                COUNT(*) as total_orders,
                SUM(CASE WHEN status IN ('pending', 'in_progress', 'paused') THEN 1 ELSE 0 END) as active_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'completed' THEN labor_cost ELSE 0 END) as total_labor_cost,
                AVG(CASE WHEN status = 'completed' AND actual_duration IS NOT NULL THEN actual_duration ELSE NULL END) as avg_duration
            FROM work_orders
            GROUP BY work_center_id
        ) stats ON wc.id = stats.work_center_id
        $where_clause
        ORDER BY wc.center_name
    ");
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        $utilization = 0;
        if ($row['capacity'] > 0) {
            $utilization = ($row['active_orders'] / $row['capacity']) * 100;
        }
        
        fputcsv($output, [
            $row['id'],
            $row['center_name'],
            $row['description'],
            ucfirst($row['status']),
            $row['capacity'],
            number_format($row['hourly_rate'], 2),
            $row['location'],
            $row['total_orders'],
            $row['active_orders'],
            $row['completed_orders'],
            number_format($utilization, 1),
            number_format($row['total_labor_cost'], 2),
            $row['avg_duration'] ? number_format($row['avg_duration'], 2) : '',
            $row['created_at'],
            $row['created_by_name'],
            $row['updated_at'],
            $row['updated_by_name'],
            $row['notes']
        ]);
    }
}

function exportPerformanceData($conn, $output, $where_clause, $params) {
    // CSV headers
    fputcsv($output, [
        'Work Center ID',
        'Work Center Name',
        'Date',
        'Completed Orders',
        'Average Duration (hrs)',
        'Total Labor Cost',
        'Utilization %',
        'Efficiency Score'
    ]);
    
    // Get performance data for the last 30 days
    $stmt = $conn->prepare("
        SELECT wc.id, wc.center_name, wc.capacity,
               DATE(wo.updated_at) as date,
               COUNT(*) as completed_count,
               AVG(wo.actual_duration) as avg_duration,
               SUM(wo.labor_cost) as daily_cost,
               AVG(CASE 
                   WHEN wo.estimated_duration > 0 AND wo.actual_duration > 0 
                   THEN (wo.estimated_duration / wo.actual_duration) * 100 
                   ELSE NULL 
               END) as efficiency_score
        FROM work_centers wc
        INNER JOIN work_orders wo ON wc.id = wo.work_center_id
        WHERE wo.status = 'completed' 
        AND wo.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        $where_clause
        GROUP BY wc.id, wc.center_name, wc.capacity, DATE(wo.updated_at)
        ORDER BY wc.center_name, date DESC
    ");
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        // Calculate daily utilization (simplified)
        $daily_utilization = ($row['completed_count'] / $row['capacity']) * 100;
        
        fputcsv($output, [
            $row['id'],
            $row['center_name'],
            $row['date'],
            $row['completed_count'],
            $row['avg_duration'] ? number_format($row['avg_duration'], 2) : '',
            number_format($row['daily_cost'], 2),
            number_format($daily_utilization, 1),
            $row['efficiency_score'] ? number_format($row['efficiency_score'], 1) : ''
        ]);
    }
}
?>