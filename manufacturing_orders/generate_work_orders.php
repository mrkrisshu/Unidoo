<?php
/**
 * Generate Work Orders from Manufacturing Order
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
$mo_id = intval($input['mo_id'] ?? 0);

if ($mo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid manufacturing order ID']);
    exit;
}

try {
    $conn = getDBConnection();
    $conn->beginTransaction();
    
    // Get manufacturing order details
    $stmt = $conn->prepare("
        SELECT mo.*, p.product_name, b.bom_code
        FROM manufacturing_orders mo
        LEFT JOIN products p ON mo.product_id = p.id
        LEFT JOIN bom b ON mo.bom_id = b.id
        WHERE mo.id = ? AND mo.status IN ('draft', 'released')
    ");
    $stmt->execute([$mo_id]);
    $mo = $stmt->fetch();
    
    if (!$mo) {
        throw new Exception('Manufacturing order not found or cannot generate work orders');
    }
    
    if (!$mo['bom_id']) {
        throw new Exception('No BOM assigned to this manufacturing order');
    }
    
    // Check if work orders already exist
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM work_orders WHERE mo_id = ?");
    $stmt->execute([$mo_id]);
    $existing_count = $stmt->fetch()['count'];
    
    if ($existing_count > 0) {
        throw new Exception('Work orders already exist for this manufacturing order');
    }
    
    // Get BOM operations
    $stmt = $conn->prepare("
        SELECT bo.*, wc.id as default_work_center_id
        FROM bom_operations bo
        LEFT JOIN work_centers wc ON bo.work_center_id = wc.id
        WHERE bo.bom_id = ?
        ORDER BY bo.sequence_number
    ");
    $stmt->execute([$mo['bom_id']]);
    $operations = $stmt->fetchAll();
    
    if (empty($operations)) {
        throw new Exception('No operations found in the BOM');
    }
    
    $work_orders_created = 0;
    
    // Create work orders for each operation
    foreach ($operations as $operation) {
        // Generate WO number
        $wo_number = generateWONumber($conn, $mo['mo_number']);
        
        // Calculate planned duration based on quantity
        $planned_duration = $operation['duration'] * $mo['quantity'];
        
        // Insert work order
        $stmt = $conn->prepare("
            INSERT INTO work_orders (
                wo_number, mo_id, operation_id, work_center_id,
                sequence_number, planned_duration, quantity,
                status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        
        $stmt->execute([
            $wo_number,
            $mo_id,
            $operation['id'],
            $operation['default_work_center_id'],
            $operation['sequence_number'],
            $planned_duration,
            $mo['quantity'],
            $_SESSION['user_id']
        ]);
        
        $work_orders_created++;
    }
    
    // Update manufacturing order status to released if it was draft
    if ($mo['status'] === 'draft') {
        $stmt = $conn->prepare("
            UPDATE manufacturing_orders 
            SET status = 'released', updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$mo_id]);
    }
    
    // Create stock reservations for materials
    createStockReservations($conn, $mo_id, $mo['quantity'], $mo['bom_id']);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully generated {$work_orders_created} work orders",
        'work_orders_count' => $work_orders_created
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function generateWONumber($conn, $mo_number) {
    // Get the last WO number for this MO
    $stmt = $conn->prepare("
        SELECT wo_number 
        FROM work_orders 
        WHERE mo_id = (SELECT id FROM manufacturing_orders WHERE mo_number = ?)
        ORDER BY wo_number DESC 
        LIMIT 1
    ");
    $stmt->execute([$mo_number]);
    $last_wo = $stmt->fetch();
    
    if ($last_wo) {
        // Extract sequence number and increment
        $parts = explode('-', $last_wo['wo_number']);
        $sequence = intval(end($parts)) + 1;
    } else {
        $sequence = 1;
    }
    
    return $mo_number . '-WO' . str_pad($sequence, 3, '0', STR_PAD_LEFT);
}

function createStockReservations($conn, $mo_id, $quantity, $bom_id) {
    // Get BOM materials
    $stmt = $conn->prepare("
        SELECT bm.*, m.material_name, m.current_stock
        FROM bom_materials bm
        JOIN materials m ON bm.material_id = m.id
        WHERE bm.bom_id = ?
    ");
    $stmt->execute([$bom_id]);
    $materials = $stmt->fetchAll();
    
    foreach ($materials as $material) {
        $required_quantity = $material['quantity'] * $quantity;
        
        // Check if enough stock is available
        if ($material['current_stock'] < $required_quantity) {
            // Log warning but don't fail the process
            error_log("Insufficient stock for material {$material['material_name']}. Required: {$required_quantity}, Available: {$material['current_stock']}");
        }
        
        // Create stock ledger entry for reservation
        $stmt = $conn->prepare("
            INSERT INTO stock_ledger (
                material_id, movement_type, quantity, reference_type, 
                reference_id, notes, created_by, created_at
            ) VALUES (?, 'reservation', ?, 'manufacturing_order', ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $material['material_id'],
            $required_quantity,
            $mo_id,
            "Reserved for MO production",
            $_SESSION['user_id']
        ]);
        
        // Update material reserved quantity
        $stmt = $conn->prepare("
            UPDATE materials 
            SET reserved_stock = reserved_stock + ? 
            WHERE id = ?
        ");
        $stmt->execute([$required_quantity, $material['material_id']]);
    }
}
?>