<?php
/**
 * Export Products
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$export = $_GET['export'] ?? 'excel';

try {
    $conn = getDBConnection();
    
    // Build query with filters
    $where_conditions = ['1=1'];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(product_name LIKE ? OR product_code LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($category)) {
        $where_conditions[] = "category = ?";
        $params[] = $category;
    }
    
    if ($status !== '') {
        $where_conditions[] = "is_active = ?";
        $params[] = $status;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get products
    $stmt = $conn->prepare("
        SELECT * FROM products 
        WHERE $where_clause 
        ORDER BY product_name ASC
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    if ($export === 'pdf') {
        exportToPDF($products);
    } else {
        exportToExcel($products);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Export failed: ' . $e->getMessage();
}

function exportToExcel($products) {
    // Set headers for CSV download (Excel compatible)
    $filename = 'products_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Product Code',
        'Product Name',
        'Description',
        'Product Type',
        'Category',
        'Unit of Measure',
        'Unit Cost',
        'Current Stock',
        'Minimum Stock',
        'Status',
        'Created Date',
        'Updated Date'
    ]);
    
    // Output data
    foreach ($products as $product) {
        fputcsv($output, [
            $product['product_code'],
            $product['product_name'],
            $product['description'],
            $product['product_type'],
            $product['category'],
            $product['unit_of_measure'],
            $product['unit_cost'],
            $product['current_stock'],
            $product['minimum_stock'],
            $product['is_active'] ? 'Active' : 'Inactive',
            $product['created_at'],
            $product['updated_at']
        ]);
    }
    
    fclose($output);
}

function exportToPDF($products) {
    // Simple HTML to PDF conversion
    $filename = 'products_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Set headers for HTML (browser will handle PDF conversion)
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    // For now, we'll create a simple HTML table that browsers can print to PDF
    // In a production environment, you'd use a library like TCPDF or DOMPDF
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Products Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .date { text-align: right; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Products Report</h1>
        </div>
        <div class="date">
            Generated on: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Product Code</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Current Stock</th>
                    <th>Min Stock</th>
                    <th>Unit Cost</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['product_code']); ?></td>
                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($product['unit_of_measure']); ?></td>
                    <td><?php echo number_format($product['current_stock'], 2); ?></td>
                    <td><?php echo number_format($product['minimum_stock'], 2); ?></td>
                    <td><?php echo number_format($product['unit_cost'], 2); ?></td>
                    <td><?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <script>
            // Auto-print for PDF generation
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
}
?>