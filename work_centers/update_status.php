<?php
/**
 * Update Work Center Status API
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $center_id = intval($input['center_id'] ?? 0);
    $new_status = $input['status'] ?? '';
    
    // Validation
    if ($center_id <= 0) {
        throw new Exception('Invalid work center ID');
    }
    
    if (!in_array($new_status, ['active', 'inactive', 'maintenance'])) {
        throw new Exception('Invalid status. Must be active, inactive, or maintenance');
    }
    
    $conn = getDBConnection();
    $conn->beginTransaction();
    
    // Get current work center info
    $stmt = $conn->prepare("
        SELECT id, center_name, status 
        FROM work_centers 
        WHERE id = ?
    ");
    $stmt->execute([$center_id]);
    $center = $stmt->fetch();
    
    if (!$center) {
        throw new Exception('Work center not found');
    }
    
    if ($center['status'] === $new_status) {
        throw new Exception('Work center is already in this status');
    }
    
    // Check for active work orders if changing to inactive/maintenance
    if (in_array($new_status, ['inactive', 'maintenance'])) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM work_orders 
            WHERE work_center_id = ? AND status IN ('pending', 'in_progress', 'paused')
        ");
        $stmt->execute([$center_id]);
        $active_orders = $stmt->fetchColumn();
        
        if ($active_orders > 0) {
            throw new Exception("Cannot change status: {$active_orders} active work orders found. Complete or reassign them first.");
        }
    }
    
    // Update work center status
    $stmt = $conn->prepare("
        UPDATE work_centers 
        SET status = ?, updated_by = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$new_status, $_SESSION['user_id'], $center_id]);
    
    // Log the status change
    $stmt = $conn->prepare("
        INSERT INTO work_center_logs (
            work_center_id, action, details, created_by
        ) VALUES (?, ?, ?, ?)
    ");
    
    $details = json_encode([
        'old_status' => $center['status'],
        'new_status' => $new_status,
        'changed_at' => date('Y-m-d H:i:s')
    ]);
    
    $stmt->execute([
        $center_id,
        'status_change',
        $details,
        $_SESSION['user_id']
    ]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Work center status updated to {$new_status}",
        'data' => [
            'center_id' => $center_id,
            'center_name' => $center['center_name'],
            'old_status' => $center['status'],
            'new_status' => $new_status
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    error_log("Work center status update error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>