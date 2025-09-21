<?php
/**
 * Manufacturing Orders Management
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

// Get filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');
$priority_filter = sanitizeInput($_GET['priority'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    $conn = getDBConnection();
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(mo.mo_number LIKE ? OR p.product_name LIKE ? OR mo.notes LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "mo.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($priority_filter)) {
        $where_conditions[] = "mo.priority = ?";
        $params[] = $priority_filter;
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(mo.scheduled_start_date) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(mo.scheduled_end_date) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total
        FROM manufacturing_orders mo
        LEFT JOIN products p ON mo.product_id = p.id
        LEFT JOIN bom b ON mo.bom_id = b.id
        $where_clause
    ";
    
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $per_page);
    
    // Get manufacturing orders
    $sql = "
        SELECT mo.*, p.product_code, p.product_name, b.bom_code, b.bom_name,
               u.username as created_by_name,
               (SELECT COUNT(*) FROM work_orders wo WHERE wo.mo_id = mo.id) as work_orders_count,
               (SELECT COUNT(*) FROM work_orders wo WHERE wo.mo_id = mo.id AND wo.status = 'completed') as completed_work_orders
        FROM manufacturing_orders mo
        LEFT JOIN products p ON mo.product_id = p.id
        LEFT JOIN bom b ON mo.bom_id = b.id
        LEFT JOIN users u ON mo.created_by = u.id
        $where_clause
        ORDER BY mo.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
   

// Get status counts for dashboard (map planned => draft)
$stmt = $conn->prepare("
    SELECT 
        CASE 
            WHEN status = 'planned' THEN 'draft'
            ELSE status
        END as status,
        COUNT(*) as count
    FROM manufacturing_orders
    GROUP BY status
");

    $stmt->execute();
    $status_counts = [];
    while ($row = $stmt->fetch()) {
        $status_counts[$row['status']] = $row['count'];
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $manufacturing_orders = [];
    $total_records = 0;
    $total_pages = 0;
    $status_counts = [];
}

$page_title = 'Manufacturing Orders';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Manufacturing Orders</h1>
            <p class="text-muted">Manage production orders and track manufacturing progress</p>
        </div>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create Manufacturing Order
        </a>
    </div>
    
    <!-- Status Overview -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-info">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $status_counts['draft'] ?? 0; ?></div>
                    <div class="stat-label">Draft</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-warning">
                    <i class="fas fa-play"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $status_counts['released'] ?? 0; ?></div>
                    <div class="stat-label">Released</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $status_counts['in_progress'] ?? 0; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-success">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $status_counts['completed'] ?? 0; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="MO number, product, description...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="released" <?php echo $status_filter === 'released' ? 'selected' : ''; ?>>Released</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="">All Priority</option>
                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Manufacturing Orders Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Manufacturing Orders (<?php echo number_format($total_records); ?>)</h3>
            <div class="d-flex gap-2">
                <button class="btn btn-outline btn-sm" onclick="exportMOs()">
                    <i class="fas fa-download"></i> Export
                </button>
                <button class="btn btn-outline btn-sm" onclick="printMOs()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($manufacturing_orders)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>MO Number</th>
                                <th>Product</th>
                                <th>BOM</th>
                                <th>Quantity</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Planned Dates</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($manufacturing_orders as $mo): ?>
                                <tr>
                                    <td>
                                        <a href="view.php?id=<?php echo $mo['id']; ?>" class="fw-medium text-primary">
                                            <?php echo htmlspecialchars($mo['mo_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-medium"><?php echo htmlspecialchars($mo['product_name']); ?></span>
                                            <small class="text-muted"><?php echo htmlspecialchars($mo['product_code']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($mo['bom_code']): ?>
                                            <span class="text-info"><?php echo htmlspecialchars($mo['bom_code']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">No BOM</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo number_format($mo['quantity']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo match($mo['priority']) {
                                                'low' => 'secondary',
                                                'medium' => 'info',
                                                'high' => 'warning',
                                                'urgent' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($mo['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo match($mo['status']) {
                                                'draft' => 'secondary',
                                                'released' => 'warning',
                                                'in_progress' => 'primary',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $mo['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $progress = $mo['work_orders_count'] > 0 ? 
                                            round(($mo['completed_work_orders'] / $mo['work_orders_count']) * 100) : 0;
                                        ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $progress; ?>%"
                                                 aria-valuenow="<?php echo $progress; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $progress; ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $mo['completed_work_orders']; ?>/<?php echo $mo['work_orders_count']; ?> WOs
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <small>Start: <?php echo date('M d, Y', strtotime($mo['scheduled_start_date'])); ?></small>
                                            <small>End: <?php echo date('M d, Y', strtotime($mo['scheduled_end_date'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($mo['created_by_name']); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $mo['id']; ?>" 
                                               class="btn btn-sm btn-outline" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($mo['status'] === 'draft'): ?>
                                                <a href="edit.php?id=<?php echo $mo['id']; ?>" 
                                                   class="btn btn-sm btn-outline" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (in_array($mo['status'], ['draft', 'released'])): ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="generateWorkOrders(<?php echo $mo['id']; ?>)" 
                                                        title="Generate Work Orders">
                                                    <i class="fas fa-cogs"></i>
                                                </button>
                                            <?php endif; ?>
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
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
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
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-industry"></i>
                    <h4>No Manufacturing Orders Found</h4>
                    <p>Start by creating your first manufacturing order to begin production planning.</p>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Manufacturing Order
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$inline_js = "
function generateWorkOrders(moId) {
    if (confirm('Generate work orders for this manufacturing order? This will create work orders based on the BOM operations.')) {
        fetch('generate_work_orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mo_id: moId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Work orders generated successfully', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to generate work orders', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred while generating work orders', 'error');
        });
    }
}

function exportMOs() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '?' + params.toString();
}

function printMOs() {
    window.print();
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