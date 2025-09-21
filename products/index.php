<?php
/**
 * Products Management
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

// Handle search and filters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

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
    
    // Get total count
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE $where_clause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
    // Get products
    $stmt = $conn->prepare("
        SELECT * FROM products 
        WHERE $where_clause 
        ORDER BY product_name ASC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([...$params, $per_page, $offset]);
    $products = $stmt->fetchAll();
    
    // Get categories for filter
    $cat_stmt = $conn->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $products = [];
    $categories = [];
    $total_pages = 1;
}

$page_title = 'Products Management';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Products Management</h1>
            <p class="text-muted">Manage your products and materials inventory</p>
        </div>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Product
        </a>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by name, code, or description..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                    <?php echo $category === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="index.php" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Products Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Products (<?php echo number_format($total_records); ?>)</h3>
            <div class="d-flex gap-2">
                <button class="btn btn-outline btn-sm" onclick="exportData('excel')">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button class="btn btn-outline btn-sm" onclick="exportData('pdf')">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No products found</h5>
                    <p class="text-muted">Start by adding your first product or adjust your search filters.</p>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Product
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
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
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['product_code']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                            <?php if ($product['description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($product['description']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['unit_of_measure']); ?></td>
                                    <td>
                                        <span class="<?php echo $product['current_stock'] <= $product['minimum_stock'] ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo number_format($product['current_stock'], 2); ?>
                                        </span>
                                        <?php if ($product['current_stock'] <= $product['minimum_stock']): ?>
                                            <i class="fas fa-exclamation-triangle text-warning ms-1" title="Low Stock"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($product['minimum_stock'], 2); ?></td>
                                    <td><?php echo formatCurrency($product['unit_cost']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $product['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-sm btn-outline" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button onclick="deleteProduct(<?php echo $product['id']; ?>)" 
                                                    class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            Showing <?php echo number_format($offset + 1); ?> to 
                            <?php echo number_format(min($offset + $per_page, $total_records)); ?> of 
                            <?php echo number_format($total_records); ?> entries
                        </div>
                        <nav>
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$inline_js = "
function deleteProduct(id) {
    if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: id})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error deleting product');
        });
    }
}

function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open('export.php?' + params.toString(), '_blank');
}
";

include '../includes/footer.php';
?>