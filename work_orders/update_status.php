<?php
/**
 * Update Work Order Status API
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

if (!$input || !isset($input['wo_id']) || !isset($input['status']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$wo_id = intval($input['wo_id']);
$new_status = sanitizeInput($input['status']);
$action = sanitizeInput($input['action']);
$user_id = $_SESSION['user_id'];

// Validate status
$valid_statuses = ['pending', 'in_progress', 'paused', 'completed', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $conn = getDBConnection();
    $conn->beginTransaction();
    
    // Get current work order details
    $stmt = $conn->prepare("
        SELECT wo.*, mo.mo_number, mo.product_id, mo.quantity as mo_quantity,
               bo.operation_name, bo.duration as planned_duration,
               p.product_name
        FROM work_orders wo
        LEFT JOIN manufacturing_orders mo ON wo.mo_id = mo.id
        LEFT JOIN bom_operations bo ON wo.operation_id = bo.id
        LEFT JOIN products p ON mo.product_id = p.id
        WHERE wo.id = ?
    ");
    $stmt->execute([$wo_id]);
    $work_order = $stmt->fetch();
    
    if (!$work_order) {
        throw new Exception('Work order not found');
    }
    
    $current_status = $work_order['status'];
    $current_time = date('Y-m-d H:i:s');
    
    // Validate status transitions
    $valid_transitions = [
        'pending' => ['in_progress', 'cancelled'],
        'in_progress' => ['paused', 'completed', 'cancelled'],
        'paused' => ['in_progress', 'cancelled'],
        'completed' => [], // Cannot change from completed
        'cancelled' => []  // Cannot change from cancelled
    ];
    
    if (!in_array($new_status, $valid_transitions[$current_status])) {
        throw new Exception("Cannot change status from {$current_status} to {$new_status}");
    }
    
    // Calculate duration for time tracking
    $actual_duration = null;
    $start_time = $work_order['start_time'];
    $end_time = null;
    
    if ($action === 'start') {
        $start_time = $current_time;
    } elseif ($action === 'complete') {
        $end_time = $current_time;
        if ($start_time) {
            $start_timestamp = strtotime($start_time);
            $end_timestamp = strtotime($end_time);
            $actual_duration = round(($end_timestamp - $start_timestamp) / 60); // minutes
        }
    }
    
    // Update work order
    $update_fields = [
        'status = ?',
        'updated_at = ?',
        'updated_by = ?'
    ];
    $update_params = [$new_status, $current_time, $user_id];
    
    if ($start_time && $action === 'start') {
        $update_fields[] = 'start_time = ?';
        $update_params[] = $start_time;
    }
    
    if ($end_time) {
        $update_fields[] = 'end_time = ?';
        $update_params[] = $end_time;
    }
    
    if ($actual_duration !== null) {
        $update_fields[] = 'actual_duration = ?';
        $update_params[] = $actual_duration;
    }
    
    $update_params[] = $wo_id;
    
    $stmt = $conn->prepare("
        UPDATE work_orders 
        SET " . implode(', ', $update_fields) . "
        WHERE id = ?
    ");
    $stmt->execute($update_params);
    
    // Log the status change
    $stmt = $conn->prepare("
        INSERT INTO work_order_logs (wo_id, status_from, status_to, action, user_id, created_at, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $notes = "Status changed from {$current_status} to {$new_status}";
    if ($actual_duration) {
        $notes .= " (Duration: {$actual_duration} minutes)";
    }
    $stmt->execute([$wo_id, $current_status, $new_status, $action, $user_id, $current_time, $notes]);
    
    // Handle completion - create stock movements
    if ($new_status === 'completed') {
        // Get BOM materials for this operation (if any material consumption)
        $stmt = $conn->prepare("
            SELECT bm.material_id, bm.quantity as bom_quantity, m.material_name, m.unit
            FROM bom_materials bm
            JOIN materials m ON bm.material_id = m.id
            JOIN bom_operations bo ON bm.bom_id = bo.bom_id
            WHERE bo.id = ? AND bm.consumed_in_operation = ?
        ");
        $stmt->execute([$work_order['operation_id'], $work_order['operation_id']]);
        $materials = $stmt->fetchAll();
        
        // Create material consumption entries
        foreach ($materials as $material) {
            $consumed_qty = $material['bom_quantity'] * $work_order['quantity'];
            
            // Create stock movement for material consumption
            $stmt = $conn->prepare("
                INSERT INTO stock_movements (
                    material_id, movement_type, quantity, reference_type, reference_id,
                    work_order_id, notes, created_by, created_at
                ) VALUES (?, 'out', ?, 'work_order', ?, ?, ?, ?, ?)
            ");
            $notes = "Material consumed in WO {$work_order['wo_number']} - {$work_order['operation_name']}";
            $stmt->execute([
                $material['material_id'], 
                $consumed_qty, 
                $wo_id, 
                $wo_id,
                $notes, 
                $user_id, 
                $current_time
            ]);
            
            // Update material stock
            $stmt = $conn->prepare("
                UPDATE materials 
                SET stock_quantity = stock_quantity - ?
                WHERE id = ?
            ");
            $stmt->execute([$consumed_qty, $material['material_id']]);
        }
        
        // Check if all work orders for this MO are completed
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM work_orders 
            WHERE mo_id = ?
        ");
        $stmt->execute([$work_order['mo_id']]);
        $mo_progress = $stmt->fetch();
        
        if ($mo_progress['total'] == $mo_progress['completed']) {
            // All work orders completed - update MO status and create finished goods
            $stmt = $conn->prepare("
                UPDATE manufacturing_orders 
                SET status = 'completed', completed_at = ?, updated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$current_time, $user_id, $work_order['mo_id']]);
            
            // Create finished goods stock movement
            $stmt = $conn->prepare("
                INSERT INTO stock_movements (
                    material_id, movement_type, quantity, reference_type, reference_id,
                    manufacturing_order_id, notes, created_by, created_at
                ) VALUES (?, 'in', ?, 'manufacturing_order', ?, ?, ?, ?, ?)
            ");
            $notes = "Finished goods from MO {$work_order['mo_number']}";
            $stmt->execute([
                $work_order['product_id'], 
                $work_order['mo_quantity'], 
                $work_order['mo_id'], 
                $work_order['mo_id'],
                $notes, 
                $user_id, 
                $current_time
            ]);
            
            // Update product stock (assuming products table has stock_quantity)
            $stmt = $conn->prepare("
                UPDATE products 
                SET stock_quantity = COALESCE(stock_quantity, 0) + ?
                WHERE id = ?
            ");
            $stmt->execute([$work_order['mo_quantity'], $work_order['product_id']]);
        }
    }
    
    $conn->commit();
    
    $message = match($action) {
        'start' => 'Work order started successfully',
        'pause' => 'Work order paused',
        'resume' => 'Work order resumed',
        'complete' => 'Work order completed successfully',
        default => 'Work order status updated'
    };
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'new_status' => $new_status,
        'actual_duration' => $actual_duration
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Work Order Status Update Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>