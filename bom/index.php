<?php
/**
 * Bill of Materials (BOM) Management
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

// Handle search and filters
$search = $_GET['search'] ?? '';
$product_id = $_GET['product_id'] ?? '';
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
        $where_conditions[] = "(b.bom_name LIKE ? OR b.bom_code LIKE ? OR p.product_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($product_id)) {
        $where_conditions[] = "b.product_id = ?";
        $params[] = $product_id;
    }
    
    if ($status !== '') {
        $where_conditions[] = "b.is_active = ?";
        $params[] = $status;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM bom_header b 
        LEFT JOIN products p ON b.product_id = p.id 
        WHERE $where_clause
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);

    // Get BOMs with product information
    $stmt = $conn->prepare("
        SELECT b.*, p.product_name, p.product_code,
               (SELECT COUNT(*) FROM bom_materials WHERE bom_id = b.id) as material_count,
               (SELECT COUNT(*) FROM bom_operations WHERE bom_id = b.id) as operation_count,
               u.full_name as created_by_name
        FROM bom_header b 
        LEFT JOIN products p ON b.product_id = p.id 
        LEFT JOIN users u ON b.created_by = u.id
        WHERE $where_clause 
        ORDER BY b.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([...$params, $per_page, $offset]);
    $boms = $stmt->fetchAll();
    
    // Get products for filter
    $prod_stmt = $conn->prepare("SELECT id, product_name, product_code FROM products WHERE is_active = 1 ORDER BY product_name");
    $prod_stmt->execute();
    $products = $prod_stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $boms = [];
    $products = [];
    $total_pages = 1;
}

$page_title = 'Bill of Materials';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Bill of Materials (BOM)</h1>
            <p class="text-muted">Manage product recipes and manufacturing processes</p>
        </div>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New BOM
        </a>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by BOM name, code, or product..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Product</label>
                    <select name="product_id" class="form-select">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" 
                                    <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['product_code'] . ' - ' . $product['product_name']); ?>
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
    
    <!-- BOMs Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">BOMs (<?php echo number_format($total_records); ?>)</h3>
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
            <?php if (empty($boms)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-list-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No BOMs found</h5>
                    <p class="text-muted">Start by creating your first Bill of Materials or adjust your search filters.</p>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create First BOM
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>BOM Code</th>
                                <th>BOM Name</th>
                                <th>Product</th>
                                <th>Version</th>
                                <th>Materials</th>
                                <th>Operations</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($boms as $bom): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($bom['bom_code']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($bom['bom_name']); ?></strong>
                                            <?php if ($bom['description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($bom['description']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($bom['product_code']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($bom['product_name']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">v<?php echo $bom['version']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <?php echo number_format($bom['material_count']); ?> items
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo number_format($bom['operation_count']); ?> ops
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $bom['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $bom['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($bom['created_by_name']); ?></small>
                                        <br><small class="text-muted"><?php echo formatDate($bom['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $bom['id']; ?>" 
                                               class="btn btn-sm btn-outline" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $bom['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="copy.php?id=<?php echo $bom['id']; ?>" 
                                               class="btn btn-sm btn-success" title="Copy BOM">
                                                <i class="fas fa-copy"></i>
                                            </a>
                                            <button onclick="deleteBOM(<?php echo $bom['id']; ?>)" 
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
function deleteBOM(id) {
    if (confirm('Are you sure you want to delete this BOM? This action cannot be undone and may affect existing manufacturing orders.')) {
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
            alert('Error deleting BOM');
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