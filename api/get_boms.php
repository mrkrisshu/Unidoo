<?php
/**
 * API to get BOMs for a specific product
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
    
    $stmt = $conn->prepare("
        SELECT id, bom_code, bom_name, version, is_active
        FROM bom_header 
        WHERE product_id = ? AND is_active = 1
        ORDER BY version DESC, created_at DESC
    ");
    $stmt->execute([$product_id]);
    $boms = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'boms' => $boms
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>