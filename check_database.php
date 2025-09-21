<?php
/**
 * Database Check Script
 * Check if products and BOMs exist in the manufacturing_management database
 */

require_once 'config/config.php';

echo "<h2>Database Status Check</h2>";
echo "<hr>";

try {
    $conn = getDBConnection();
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check products table
    echo "<h3>Products Table</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total_products FROM products");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p>Total products: " . $result['total_products'] . "</p>";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as active_products FROM products WHERE is_active = 1");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p>Active products: " . $result['active_products'] . "</p>";
    
    // Show sample products
    $stmt = $conn->prepare("SELECT id, product_code, product_name, is_active FROM products LIMIT 5");
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    if (!empty($products)) {
        echo "<h4>Sample Products:</h4>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Active</th></tr>";
        foreach ($products as $product) {
            echo "<tr>";
            echo "<td>" . $product['id'] . "</td>";
            echo "<td>" . htmlspecialchars($product['product_code']) . "</td>";
            echo "<td>" . htmlspecialchars($product['product_name']) . "</td>";
            echo "<td>" . ($product['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check BOM header table
    echo "<h3>BOM Header Table</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total_boms FROM bom_header");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p>Total BOMs: " . $result['total_boms'] . "</p>";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as active_boms FROM bom_header WHERE is_active = 1");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p>Active BOMs: " . $result['active_boms'] . "</p>";
    
    // Show sample BOMs
    $stmt = $conn->prepare("
        SELECT bh.id, bh.bom_code, bh.bom_name, bh.is_active, p.product_name 
        FROM bom_header bh 
        LEFT JOIN products p ON bh.product_id = p.id 
        LIMIT 5
    ");
    $stmt->execute();
    $boms = $stmt->fetchAll();
    
    if (!empty($boms)) {
        echo "<h4>Sample BOMs:</h4>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>BOM Code</th><th>BOM Name</th><th>Product</th><th>Active</th></tr>";
        foreach ($boms as $bom) {
            echo "<tr>";
            echo "<td>" . $bom['id'] . "</td>";
            echo "<td>" . htmlspecialchars($bom['bom_code']) . "</td>";
            echo "<td>" . htmlspecialchars($bom['bom_name']) . "</td>";
            echo "<td>" . htmlspecialchars($bom['product_name']) . "</td>";
            echo "<td>" . ($bom['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check manufacturing orders table
    echo "<h3>Manufacturing Orders Table</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total_orders FROM manufacturing_orders");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p>Total manufacturing orders: " . $result['total_orders'] . "</p>";
    
    // Check stock ledger table
    echo "<h3>Stock Ledger Table</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total_entries FROM stock_ledger");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p>Total stock ledger entries: " . $result['total_entries'] . "</p>";
    
    // Test the products query used in manufacturing orders
    echo "<h3>Manufacturing Orders Products Query Test</h3>";
    $stmt = $conn->prepare("
        SELECT id, product_code, product_name, unit_of_measure
        FROM products 
        WHERE is_active = 1
        ORDER BY product_name
    ");
    $stmt->execute();
    $products_for_mo = $stmt->fetchAll();
    echo "<p>Products available for manufacturing orders: " . count($products_for_mo) . "</p>";
    
    if (empty($products_for_mo)) {
        echo "<p style='color: red;'>⚠️ No active products found for manufacturing orders dropdown!</p>";
    } else {
        echo "<p style='color: green;'>✓ Products are available for manufacturing orders</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f2f2f2; }
</style>