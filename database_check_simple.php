<?php
// Simple database check - run this in your browser
echo "<h1>Manufacturing Management Database Check</h1>";

// Database connection settings
$host = 'localhost';
$dbname = 'manufacturing_management';
$username = 'root';
$password = ''; // Change this if you have a password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✓ Connected to database successfully</p>";
    
    // Check if tables exist
    $tables = ['products', 'bom_header', 'manufacturing_orders', 'stock_ledger'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<p>Table '$table': $count records</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>Table '$table': ERROR - " . $e->getMessage() . "</p>";
        }
    }
    
    // Check products specifically
    echo "<h2>Products Check</h2>";
    $stmt = $pdo->query("SELECT * FROM products LIMIT 3");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        echo "<p style='color: red;'>No products found in database!</p>";
    } else {
        echo "<p style='color: green;'>Found " . count($products) . " products (showing first 3):</p>";
        foreach ($products as $product) {
            echo "<p>- ID: {$product['id']}, Code: {$product['product_code']}, Name: {$product['product_name']}, Active: " . ($product['is_active'] ? 'Yes' : 'No') . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>XAMPP MySQL is running</li>";
    echo "<li>Database 'manufacturing_management' exists</li>";
    echo "<li>Database credentials are correct</li>";
    echo "</ul>";
}
?>