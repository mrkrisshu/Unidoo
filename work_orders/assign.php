<?php
/**
 * Assign Work Order API
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

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['wo_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing work order ID']);
    exit;
}

$wo_id = intval($input['wo_id']);
$work_center_id = !empty($input['work_center_id']) ? intval($input['work_center_id']) : null;
$assigned_to = !empty($input['assigned_to']) ? intval($input['assigned_to']) : null;
$user_id = $_SESSION['user_id'];

try {
    $conn = getDBConnection();
    $conn->beginTransaction();
    
    // Get current work order
    $stmt = $conn->prepare("
        SELECT wo.*, mo.mo_number, bo.operation_name
        FROM work_orders wo
        LEFT JOIN manufacturing_orders mo ON wo.mo_id = mo.id
        LEFT JOIN bom_operations bo ON wo.operation_id = bo.id
        WHERE wo.id = ?
    ");
    $stmt->execute([$wo_id]);
    $work_order = $stmt->fetch();
    
    if (!$work_order) {
        throw new Exception('Work order not found');
    }
    
    // Check if work order can be assigned (not completed or cancelled)
    if (in_array($work_order['status'], ['completed', 'cancelled'])) {
        throw new Exception('Cannot assign completed or cancelled work orders');
    }
    
    // Validate work center if provided
    if ($work_center_id) {
        $stmt = $conn->prepare("
            SELECT id, center_name, status
            FROM work_centers
            WHERE id = ?
        ");
        $stmt->execute([$work_center_id]);
        $work_center = $stmt->fetch();
        
        if (!$work_center) {
            throw new Exception('Work center not found');
        }
        
        if ($work_center['status'] !== 'active') {
            throw new Exception('Work center is not active');
        }
    }
    
    // Validate operator if provided
    if ($assigned_to) {
        $stmt = $conn->prepare("
            SELECT id, username, role, status
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$assigned_to]);
        $operator = $stmt->fetch();
        
        if (!$operator) {
            throw new Exception('Operator not found');
        }
        
        if ($operator['status'] !== 'active') {
            throw new Exception('Operator is not active');
        }
        
        if (!in_array($operator['role'], ['operator', 'supervisor', 'admin'])) {
            throw new Exception('User cannot be assigned as operator');
        }
    }
    
    // Check work center capacity if assigning
    if ($work_center_id) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as active_orders
            FROM work_orders
            WHERE work_center_id = ? AND status IN ('pending', 'in_progress', 'paused')
        ");
        $stmt->execute([$work_center_id]);
        $capacity_check = $stmt->fetch();
        
        // Get work center capacity
        $stmt = $conn->prepare("
            SELECT capacity
            FROM work_centers
            WHERE id = ?
        ");
        $stmt->execute([$work_center_id]);
        $center_capacity = $stmt->fetch()['capacity'] ?? 1;
        
        if ($capacity_check['active_orders'] >= $center_capacity) {
            throw new Exception('Work center is at full capacity');
        }
    }
    
    // Check operator availability if assigning
    if ($assigned_to) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as active_orders
            FROM work_orders
            WHERE assigned_to = ? AND status IN ('in_progress', 'paused')
        ");
        $stmt->execute([$assigned_to]);
        $operator_check = $stmt->fetch();
        
        if ($operator_check['active_orders'] > 0) {
            throw new Exception('Operator is already assigned to active work orders');
        }
    }
    
    // Update work order assignment
    $update_fields = ['updated_at = ?', 'updated_by = ?'];
    $update_params = [date('Y-m-d H:i:s'), $user_id];
    
    if ($work_center_id !== null) {
        $update_fields[] = 'work_center_id = ?';
        $update_params[] = $work_center_id;
    }
    
    if ($assigned_to !== null) {
        $update_fields[] = 'assigned_to = ?';
        $update_params[] = $assigned_to;
    }
    
    $update_params[] = $wo_id;
    
    $stmt = $conn->prepare("
        UPDATE work_orders 
        SET " . implode(', ', $update_fields) . "
        WHERE id = ?
    ");
    $stmt->execute($update_params);
    
    // Log the assignment
    $notes = [];
    if ($work_center_id) {
        $notes[] = "Assigned to work center: " . ($work_center['center_name'] ?? 'Unknown');
    }
    if ($assigned_to) {
        $notes[] = "Assigned to operator: " . ($operator['username'] ?? 'Unknown');
    }
    
    if (!empty($notes)) {
        $stmt = $conn->prepare("
            INSERT INTO work_order_logs (wo_id, status_from, status_to, action, user_id, created_at, notes)
            VALUES (?, ?, ?, 'assign', ?, ?, ?)
        ");
        $stmt->execute([
            $wo_id, 
            $work_order['status'], 
            $work_order['status'], 
            $user_id, 
            date('Y-m-d H:i:s'), 
            implode(', ', $notes)
        ]);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Work order assigned successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Work Order Assignment Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>