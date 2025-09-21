<?php
/**
 * Work Orders Management
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

// Get filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');
$work_center_filter = intval($_GET['work_center'] ?? 0);
$assigned_to_filter = intval($_GET['assigned_to'] ?? 0);
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
        $where_conditions[] = "(wo.wo_number LIKE ? OR mo.mo_number LIKE ? OR wo.operation_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "wo.status = ?";
        $params[] = $status_filter;
    }
    
    if ($work_center_filter > 0) {
        $where_conditions[] = "wo.work_center_id = ?";
        $params[] = $work_center_filter;
    }
    
    if ($assigned_to_filter > 0) {
        $where_conditions[] = "wo.assigned_to = ?";
        $params[] = $assigned_to_filter;
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(wo.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(wo.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total
        FROM work_orders wo
        LEFT JOIN manufacturing_orders mo ON wo.mo_id = mo.id
        LEFT JOIN work_centers wc ON wo.work_center_id = wc.id
        LEFT JOIN users u ON wo.assigned_to = u.id
        $where_clause
    ";
    
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $per_page);
    
    // Get work orders
    $sql = "
        SELECT wo.*, mo.mo_number, mo.product_id, p.product_name,
               wo.operation_name, wo.operation_time_minutes as planned_duration,
               wc.center_name, wc.hourly_cost,
               u.username as assigned_to_name,
               creator.username as created_by_name
        FROM work_orders wo
        LEFT JOIN manufacturing_orders mo ON wo.mo_id = mo.id
        LEFT JOIN products p ON mo.product_id = p.id
        LEFT JOIN work_centers wc ON wo.work_center_id = wc.id
        LEFT JOIN users u ON wo.assigned_to = u.id
        LEFT JOIN users creator ON wo.created_by = creator.id
        $where_clause
        ORDER BY 
            CASE wo.status 
                WHEN 'started' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'paused' THEN 3
                WHEN 'completed' THEN 4
                WHEN 'cancelled' THEN 5
                ELSE 6
            END,
            wo.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $work_orders = $stmt->fetchAll();
    
    // Get status counts for dashboard
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count
        FROM work_orders
        GROUP BY status
    ");
    $stmt->execute();
    $status_counts = [];
    while ($row = $stmt->fetch()) {
        $status_counts[$row['status']] = $row['count'];
    }
    
    // Get work centers for filter
    $stmt = $conn->prepare("
        SELECT id, center_name
        FROM work_centers
        WHERE is_active = 1
        ORDER BY center_name
    ");
    $stmt->execute();
    $work_centers = $stmt->fetchAll();
    
    // Get operators for filter
    $stmt = $conn->prepare("
        SELECT id, username
        FROM users
        WHERE role IN ('operator', 'manager', 'admin') AND is_active = 1
        ORDER BY username
    ");
    $stmt->execute();
    $operators = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $work_orders = [];
    $total_records = 0;
    $total_pages = 0;
    $status_counts = [];
    $work_centers = [];
    $operators = [];
}

$page_title = 'Work Orders';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Work Orders</h1>
            <p class="text-muted">Manage and track individual manufacturing operations</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline" onclick="refreshWorkOrders()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>
    
    <!-- Status Overview -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-icon bg-secondary">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $status_counts['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-icon bg-primary">
                    <i class="fas fa-play"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $status_counts['in_progress'] ?? 0; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-icon bg-warning">
                    <i class="fas fa-pause"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $status_counts['paused'] ?? 0; ?></div>
                    <div class="stat-label">Paused</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
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
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-icon bg-danger">
                    <i class="fas fa-times"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $status_counts['cancelled'] ?? 0; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-icon bg-info">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo array_sum($status_counts); ?></div>
                    <div class="stat-label">Total</div>
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
                           placeholder="WO number, MO number, operation...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="paused" <?php echo $status_filter === 'paused' ? 'selected' : ''; ?>>Paused</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Work Center</label>
                    <select name="work_center" class="form-select">
                        <option value="">All Centers</option>
                        <?php foreach ($work_centers as $center): ?>
                            <option value="<?php echo $center['id']; ?>" 
                                    <?php echo $work_center_filter === $center['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($center['center_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">All Operators</option>
                        <?php foreach ($operators as $operator): ?>
                            <option value="<?php echo $operator['id']; ?>" 
                                    <?php echo $assigned_to_filter === $operator['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($operator['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">To</label>
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
    
    <!-- Work Orders Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Work Orders (<?php echo number_format($total_records); ?>)</h3>
            <div class="d-flex gap-2">
                <button class="btn btn-outline btn-sm" onclick="exportWOs()">
                    <i class="fas fa-download"></i> Export
                </button>
                <button class="btn btn-outline btn-sm" onclick="printWOs()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($work_orders)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>WO Number</th>
                                <th>MO Number</th>
                                <th>Product</th>
                                <th>Operation</th>
                                <th>Work Center</th>
                                <th>Assigned To</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($work_orders as $wo): ?>
                                <tr>
                                    <td>
                                        <a href="view.php?id=<?php echo $wo['id']; ?>" class="fw-medium text-primary">
                                            <?php echo htmlspecialchars($wo['wo_number']); ?>
                                        </a>
                                        <br>
                                        <small class="text-muted">Seq: <?php echo $wo['sequence_number']; ?></small>
                                    </td>
                                    <td>
                                        <a href="../manufacturing_orders/view.php?id=<?php echo $wo['mo_id']; ?>" class="text-info">
                                            <?php echo htmlspecialchars($wo['mo_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-medium"><?php echo htmlspecialchars($wo['product_name']); ?></span>
                                            <small class="text-muted">Qty: <?php echo number_format($wo['quantity']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($wo['operation_name']); ?></td>
                                    <td>
                                        <?php if ($wo['center_name']): ?>
                                            <span class="text-info"><?php echo htmlspecialchars($wo['center_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($wo['assigned_to_name']): ?>
                                            <?php echo htmlspecialchars($wo['assigned_to_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <small>Planned: <?php echo $wo['planned_duration']; ?> min</small>
                                            <?php if ($wo['actual_duration']): ?>
                                                <small class="<?php echo $wo['actual_duration'] > $wo['planned_duration'] ? 'text-warning' : 'text-success'; ?>">
                                                    Actual: <?php echo $wo['actual_duration']; ?> min
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo match($wo['status']) {
                                                'pending' => 'secondary',
                                                'in_progress' => 'primary',
                                                'paused' => 'warning',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $wo['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($wo['status'] === 'completed'): ?>
                                            <span class="text-success">
                                                <i class="fas fa-check-circle"></i> 100%
                                            </span>
                                        <?php elseif ($wo['status'] === 'in_progress'): ?>
                                            <span class="text-primary">
                                                <i class="fas fa-spinner fa-spin"></i> Working
                                            </span>
                                        <?php elseif ($wo['status'] === 'paused'): ?>
                                            <span class="text-warning">
                                                <i class="fas fa-pause"></i> Paused
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-clock"></i> Waiting
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $wo['id']; ?>" 
                                               class="btn btn-sm btn-outline" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($wo['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="startWorkOrder(<?php echo $wo['id']; ?>)" 
                                                        title="Start">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            <?php elseif ($wo['status'] === 'in_progress'): ?>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="pauseWorkOrder(<?php echo $wo['id']; ?>)" 
                                                        title="Pause">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="completeWorkOrder(<?php echo $wo['id']; ?>)" 
                                                        title="Complete">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php elseif ($wo['status'] === 'paused'): ?>
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="resumeWorkOrder(<?php echo $wo['id']; ?>)" 
                                                        title="Resume">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($wo['status'], ['pending', 'paused'])): ?>
                                                <button class="btn btn-sm btn-outline" 
                                                        onclick="assignWorkOrder(<?php echo $wo['id']; ?>)" 
                                                        title="Assign">
                                                    <i class="fas fa-user"></i>
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
                    <i class="fas fa-cogs"></i>
                    <h4>No Work Orders Found</h4>
                    <p>Work orders are generated from manufacturing orders with BOMs.</p>
                    <a href="../manufacturing_orders/index.php" class="btn btn-primary">
                        <i class="fas fa-industry"></i> View Manufacturing Orders
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Assignment Modal -->
<div class="modal fade" id="assignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Work Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignmentForm">
                    <input type="hidden" id="assign_wo_id" name="wo_id">
                    <div class="mb-3">
                        <label for="assign_work_center" class="form-label">Work Center</label>
                        <select id="assign_work_center" name="work_center_id" class="form-select">
                            <option value="">Select Work Center</option>
                            <?php foreach ($work_centers as $center): ?>
                                <option value="<?php echo $center['id']; ?>">
                                    <?php echo htmlspecialchars($center['center_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="assign_operator" class="form-label">Operator</label>
                        <select id="assign_operator" name="assigned_to" class="form-select">
                            <option value="">Select Operator</option>
                            <?php foreach ($operators as $operator): ?>
                                <option value="<?php echo $operator['id']; ?>">
                                    <?php echo htmlspecialchars($operator['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAssignment()">Assign</button>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
function startWorkOrder(woId) {
    updateWorkOrderStatus(woId, 'in_progress', 'start');
}

function pauseWorkOrder(woId) {
    updateWorkOrderStatus(woId, 'paused', 'pause');
}

function resumeWorkOrder(woId) {
    updateWorkOrderStatus(woId, 'in_progress', 'resume');
}

function completeWorkOrder(woId) {
    if (confirm('Mark this work order as completed?')) {
        updateWorkOrderStatus(woId, 'completed', 'complete');
    }
}

function updateWorkOrderStatus(woId, status, action) {
    fetch('update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            wo_id: woId, 
            status: status,
            action: action
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message || 'Work order updated successfully', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message || 'Failed to update work order', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred while updating the work order', 'error');
    });
}

function assignWorkOrder(woId) {
    document.getElementById('assign_wo_id').value = woId;
    const modal = new bootstrap.Modal(document.getElementById('assignmentModal'));
    modal.show();
}

function saveAssignment() {
    const formData = new FormData(document.getElementById('assignmentForm'));
    const data = Object.fromEntries(formData);
    
    fetch('assign.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Work order assigned successfully', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('assignmentModal'));
            modal.hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message || 'Failed to assign work order', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred while assigning the work order', 'error');
    });
}

function refreshWorkOrders() {
    location.reload();
}

function exportWOs() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '?' + params.toString();
}

function printWOs() {
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

// Auto-refresh every 30 seconds for active work orders
setInterval(() => {
    const hasActiveWO = document.querySelector('.badge-primary');
    if (hasActiveWO) {
        location.reload();
    }
}, 30000);
";

include '../includes/footer.php';
?>