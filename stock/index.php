<?php
/**
 * Stock Ledger - Main Inventory Management
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

// Get filter parameters
$search = $_GET['search'] ?? '';
$material_id = $_GET['material_id'] ?? '';
$location = $_GET['location'] ?? '';
$low_stock = isset($_GET['low_stock']) ? 1 : 0;
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

try {
    $conn = getDBConnection();
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(p.product_name LIKE ? OR p.product_code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($material_id) {
        $where_conditions[] = "p.id = ?";
        $params[] = $material_id;
    }
    
    if ($low_stock) {
        $where_conditions[] = "p.current_stock <= p.minimum_stock";
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get stock ledger data with pagination
    $stmt = $conn->prepare("
        SELECT p.id, p.product_code, p.product_name, p.unit_of_measure, p.unit_cost, 
               p.current_stock, p.minimum_stock,
               (p.current_stock * p.unit_cost) as stock_value,
               CASE 
                   WHEN p.current_stock <= 0 THEN 'out_of_stock'
                   WHEN p.current_stock <= p.minimum_stock THEN 'low_stock'
                   ELSE 'normal'
               END as stock_status
        FROM products p
        $where_clause
        ORDER BY 
            CASE 
                WHEN p.current_stock <= 0 THEN 1
                WHEN p.current_stock <= p.minimum_stock THEN 2
                ELSE 3
            END,
            p.product_name
        LIMIT ? OFFSET ?
    ");
    
    $count_params = $params;
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $stock_items = $stmt->fetchAll();
    
    // Get total count
    $count_stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM products p
        $where_clause
    ");
    $count_stmt->execute($count_params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $per_page);
    
    // Get materials for filter dropdown
    $stmt = $conn->prepare("
        SELECT id, product_name, product_code
        FROM products
        WHERE is_active = 1
        ORDER BY product_name
    ");
    $stmt->execute();
    $materials = $stmt->fetchAll();
    
    // Define locations array (can be expanded later)
    $locations = ['Warehouse A', 'Warehouse B', 'Production Floor', 'Quality Control'];
    
    // Get stock statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_items,
            SUM(CASE WHEN p.current_stock <= 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN p.current_stock <= p.minimum_stock AND p.current_stock > 0 THEN 1 ELSE 0 END) as low_stock,
            SUM(p.current_stock * p.unit_cost) as total_value
        FROM products p
        WHERE p.is_active = 1
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Stock Index Error: " . $e->getMessage());
    $stock_items = [];
    $total_pages = 0;
    $total_items = 0;
    $materials = [];
    $stats = ['total_items' => 0, 'out_of_stock' => 0, 'low_stock' => 0, 'total_value' => 0];
}

$page_title = 'Stock Ledger';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Stock Ledger</h1>
            <p class="text-muted">Inventory management and stock tracking</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline" onclick="showAdjustmentModal()">
                <i class="fas fa-edit"></i> Stock Adjustment
            </button>
            <button class="btn btn-outline" onclick="exportStock()">
                <i class="fas fa-download"></i> Export
            </button>
            <button class="btn btn-primary" onclick="refreshStock()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h3 text-primary"><?php echo number_format($stats['total_items']); ?></div>
                    <div class="text-muted">Total Items</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h3 text-danger"><?php echo number_format($stats['out_of_stock']); ?></div>
                    <div class="text-muted">Out of Stock</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h3 text-warning"><?php echo number_format($stats['low_stock']); ?></div>
                    <div class="text-muted">Low Stock</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h3 text-success">$<?php echo number_format($stats['total_value'], 2); ?></div>
                    <div class="text-muted">Total Value</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           placeholder="Material name or code..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label for="material_id" class="form-label">Material</label>
                    <select id="material_id" name="material_id" class="form-select">
                        <option value="">All Materials</option>
                        <?php foreach ($materials as $material): ?>
                            <option value="<?php echo $material['id']; ?>" 
                                    <?php echo $material_id == $material['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($material['product_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="location" class="form-label">Location</label>
                    <select id="location" name="location" class="form-select">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc); ?>" 
                                    <?php echo $location === $loc ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="form-check">
                        <input type="checkbox" id="low_stock" name="low_stock" class="form-check-input" 
                               <?php echo $low_stock ? 'checked' : ''; ?>>
                        <label for="low_stock" class="form-check-label">Low Stock Only</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="index.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Stock Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Stock Items (<?php echo number_format($total_items); ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($stock_items)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Location</th>
                                <th>Current Stock</th>
                                <th>Min/Max</th>
                                <th>Unit Cost</th>
                                <th>Stock Value</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stock_items as $item): ?>
                                <tr class="<?php echo $item['stock_status'] === 'out_of_stock' ? 'table-danger' : ($item['stock_status'] === 'low_stock' ? 'table-warning' : ''); ?>">
                                    <td>
                                        <div class="fw-medium"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($item['product_code']); ?></small>
                                    </td>
                                    <td>Default</td>
                                    <td>
                                        <span class="fw-medium"><?php echo number_format($item['current_stock'], 2); ?></span>
                                        <small class="text-muted"><?php echo htmlspecialchars($item['unit_of_measure']); ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo number_format($item['minimum_stock'], 0); ?> / 
                                            <?php echo number_format($item['minimum_stock'] * 2, 0); ?>
                                        </small>
                                    </td>
                                    <td>$<?php echo number_format($item['unit_cost'], 2); ?></td>
                                    <td>$<?php echo number_format($item['stock_value'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            if ($item['stock_status'] === 'out_of_stock') echo 'bg-danger';
                                            elseif ($item['stock_status'] === 'low_stock') echo 'bg-warning';
                                            else echo 'bg-success';
                                        ?>">
                                            <?php 
                                            if ($item['stock_status'] === 'out_of_stock') echo 'Out of Stock';
                                            elseif ($item['stock_status'] === 'low_stock') echo 'Low Stock';
                                            else echo 'Normal';
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($item['updated_at']) && $item['updated_at']): ?>
                                            <?php echo date('M j, Y', strtotime($item['updated_at'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary btn-sm" onclick="viewMovements(<?php echo $item['id']; ?>)" title="View Movements">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm" onclick="adjustStock(<?php echo $item['id']; ?>)" title="Adjust Stock">
                                                <i class="fas fa-edit"></i>
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
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-boxes fa-3x text-muted mb-3"></i>
                    <h5>No stock items found</h5>
                    <p class="text-muted">Try adjusting your search criteria or add some materials to the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="adjustmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Stock Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="adjustmentForm">
                    <div class="mb-3">
                        <label for="adj_material_id" class="form-label">Material</label>
                        <select id="adj_material_id" name="material_id" class="form-select" required>
                            <option value="">Select Material</option>
                            <?php foreach ($materials as $material): ?>
                                <option value="<?php echo $material['id']; ?>">
                                    <?php echo htmlspecialchars($material['material_name'] . ' (' . $material['material_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="adj_location" class="form-label">Location</label>
                        <input type="text" id="adj_location" name="location" class="form-control" placeholder="Storage location">
                    </div>
                    <div class="mb-3">
                        <label for="adj_type" class="form-label">Adjustment Type</label>
                        <select id="adj_type" name="adjustment_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="in">Stock In (+)</option>
                            <option value="out">Stock Out (-)</option>
                            <option value="adjustment">Adjustment</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="adj_quantity" class="form-label">Quantity</label>
                        <input type="number" id="adj_quantity" name="quantity" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="adj_notes" class="form-label">Notes</label>
                        <textarea id="adj_notes" name="notes" class="form-control" rows="3" placeholder="Reason for adjustment..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAdjustment()">Save Adjustment</button>
            </div>
        </div>
    </div>
</div>

<!-- Stock Movements Modal -->
<div class="modal fade" id="movementsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Stock Movements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="movementsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
function showAdjustmentModal() {
    const modal = new bootstrap.Modal(document.getElementById('adjustmentModal'));
    modal.show();
}

function adjustStock(materialId, location) {
    document.getElementById('adj_material_id').value = materialId;
    document.getElementById('adj_location').value = location || '';
    showAdjustmentModal();
}

function saveAdjustment() {
    const formData = new FormData(document.getElementById('adjustmentForm'));
    const data = Object.fromEntries(formData);
    
    if (!data.material_id || !data.adjustment_type || !data.quantity) {
        showAlert('Please fill in all required fields', 'error');
        return;
    }
    
    fetch('adjust_stock.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Stock adjustment saved successfully', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('adjustmentModal'));
            modal.hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message || 'Failed to save stock adjustment', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred while saving the adjustment', 'error');
    });
}

function viewMovements(materialId) {
    const modal = new bootstrap.Modal(document.getElementById('movementsModal'));
    modal.show();
    
    fetch(`movements.php?material_id=\${materialId}`)
    .then(response => response.text())
    .then(html => {
        document.getElementById('movementsContent').innerHTML = html;
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('movementsContent').innerHTML = '<div class=\"alert alert-danger\">Failed to load movements</div>';
    });
}

function refreshStock() {
    location.reload();
}

function exportStock() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.open('export.php?' + params.toString(), '_blank');
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-\${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        \${message}
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>
    `;
    
    const container = document.querySelector('.content-area');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
";

include '../includes/footer.php';
?>