<?php
/**
 * API to get BOM preview for a specific product
 * Manufacturing Management System
 */

require_once '../config/config.php';

header('Content-Type: application/json');

try {
    $product_id = intval($_GET['product_id'] ?? 0);
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Get the active BOM for this product
    $stmt = $conn->prepare("
        SELECT id, bom_code, bom_name, version
        FROM bom_header 
        WHERE product_id = ? AND is_active = 1
        ORDER BY version DESC, created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$product_id]);
    $bom = $stmt->fetch();
    
    if (!$bom) {
        echo json_encode([
            'success' => false,
            'message' => 'No active BOM found for this product'
        ]);
        exit;
    }
    
    // Get BOM materials
    $stmt = $conn->prepare("
        SELECT bm.*, p.product_code, p.product_name, p.unit_of_measure,
               (bm.quantity * bm.unit_cost) as total_cost
        FROM bom_materials bm
        LEFT JOIN products p ON bm.material_id = p.id
        WHERE bm.bom_id = ?
        ORDER BY p.product_name
    ");
    $stmt->execute([$bom['id']]);
    $materials = $stmt->fetchAll();
    
    // Get BOM operations
    $stmt = $conn->prepare("
        SELECT bo.*, wc.work_center_name,
               (bo.setup_time_minutes + bo.operation_time_minutes) as total_time,
               ((bo.setup_time_minutes + bo.operation_time_minutes) / 60 * bo.cost_per_hour) as operation_cost
        FROM bom_operations bo
        LEFT JOIN work_centers wc ON bo.work_center_id = wc.id
        WHERE bo.bom_id = ?
        ORDER BY bo.operation_sequence, bo.id
    ");
    $stmt->execute([$bom['id']]);
    $operations = $stmt->fetchAll();
    
    // Calculate totals
    $total_material_cost = array_sum(array_column($materials, 'total_cost'));
    $total_operation_cost = array_sum(array_column($operations, 'operation_cost'));
    $total_bom_cost = $total_material_cost + $total_operation_cost;
    
    echo json_encode([
        'success' => true,
        'bom' => $bom,
        'materials' => $materials,
        'operations' => $operations,
        'totals' => [
            'material_cost' => $total_material_cost,
            'operation_cost' => $total_operation_cost,
            'total_cost' => $total_bom_cost
        ]
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>