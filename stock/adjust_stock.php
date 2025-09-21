<?php
/**
 * Stock Adjustment API
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
    
    $material_id = intval($input['material_id'] ?? 0);
    $location = trim($input['location'] ?? 'Default');
    $adjustment_type = $input['adjustment_type'] ?? '';
    $quantity = floatval($input['quantity'] ?? 0);
    $notes = trim($input['notes'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    // Validation
    if (!$material_id) {
        throw new Exception('Material is required');
    }
    
    if (!in_array($adjustment_type, ['in', 'out', 'adjustment'])) {
        throw new Exception('Invalid adjustment type');
    }
    
    if ($quantity <= 0) {
        throw new Exception('Quantity must be greater than 0');
    }
    
    $conn = getDBConnection();
    $conn->beginTransaction();
    
    // Verify material exists
    $stmt = $conn->prepare("SELECT material_name, unit FROM materials WHERE id = ?");
    $stmt->execute([$material_id]);
    $material = $stmt->fetch();
    
    if (!$material) {
        throw new Exception('Material not found');
    }
    
    // Get or create stock ledger entry
    $stmt = $conn->prepare("
        SELECT id, current_stock 
        FROM stock_ledger 
        WHERE material_id = ? AND location = ?
    ");
    $stmt->execute([$material_id, $location]);
    $stock_entry = $stmt->fetch();
    
    $current_stock = $stock_entry ? $stock_entry['current_stock'] : 0;
    
    // Calculate new stock based on adjustment type
    switch ($adjustment_type) {
        case 'in':
            $new_stock = $current_stock + $quantity;
            $movement_type = 'in';
            $movement_quantity = $quantity;
            break;
            
        case 'out':
            if ($current_stock < $quantity) {
                throw new Exception('Insufficient stock. Current stock: ' . number_format($current_stock, 2) . ' ' . $material['unit']);
            }
            $new_stock = $current_stock - $quantity;
            $movement_type = 'out';
            $movement_quantity = $quantity;
            break;
            
        case 'adjustment':
            // For adjustments, quantity represents the new total stock
            $new_stock = $quantity;
            if ($quantity > $current_stock) {
                $movement_type = 'in';
                $movement_quantity = $quantity - $current_stock;
            } else {
                $movement_type = 'out';
                $movement_quantity = $current_stock - $quantity;
            }
            break;
    }
    
    // Update or insert stock ledger entry
    if ($stock_entry) {
        $stmt = $conn->prepare("
            UPDATE stock_ledger 
            SET current_stock = ?, updated_at = NOW(), updated_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$new_stock, $user_id, $stock_entry['id']]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO stock_ledger (material_id, location, current_stock, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$material_id, $location, $new_stock, $user_id, $user_id]);
    }
    
    // Record stock movement
    $movement_notes = $notes ?: ucfirst($adjustment_type) . ' adjustment';
    if ($adjustment_type === 'adjustment') {
        $movement_notes .= ' (from ' . number_format($current_stock, 2) . ' to ' . number_format($new_stock, 2) . ')';
    }
    
    $stmt = $conn->prepare("
        INSERT INTO stock_movements (
            material_id, movement_type, quantity, location, notes, 
            reference_type, created_by
        ) VALUES (?, ?, ?, ?, ?, 'manual_adjustment', ?)
    ");
    $stmt->execute([
        $material_id, 
        $movement_type, 
        $movement_quantity, 
        $location, 
        $movement_notes, 
        $user_id
    ]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Stock adjustment completed successfully',
        'data' => [
            'material_name' => $material['material_name'],
            'previous_stock' => $current_stock,
            'new_stock' => $new_stock,
            'adjustment_quantity' => $movement_quantity,
            'adjustment_type' => $movement_type
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    error_log("Stock adjustment error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>