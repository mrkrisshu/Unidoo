<?php
/**
 * Work Centers Management
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    $conn = getDBConnection();
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(center_name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status) {
        $where_conditions[] = "is_active = ?";
        $params[] = ($status === 'active') ? 1 : 0;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get work centers with pagination
    $stmt = $conn->prepare("
        SELECT wc.*, 
               created_user.username as created_by_name,
               (SELECT COUNT(*) FROM work_orders wo WHERE wo.work_center_id = wc.id AND wo.status IN ('pending', 'started', 'paused')) as active_orders,
               (SELECT COUNT(*) FROM work_orders wo WHERE wo.work_center_id = wc.id AND wo.status = 'completed' AND DATE(wo.updated_at) = CURDATE()) as completed_today
        FROM work_centers wc
        LEFT JOIN users created_user ON wc.created_by = created_user.id
        $where_clause
        ORDER BY wc.center_name
        LIMIT ? OFFSET ?
    ");
    
    $count_params = $params;
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $work_centers = $stmt->fetchAll();
    
    // Get total count
    $count_stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM work_centers wc
        $where_clause
    ");
    $count_stmt->execute($count_params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $per_page);
    
    // Get statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_centers,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_centers,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_centers,
            0 as maintenance_centers,
            AVG(hourly_cost) as avg_hourly_rate,
            SUM(capacity_per_hour) as total_capacity
        FROM work_centers
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $work_centers = [];
    $total_items = 0;
    $total_pages = 0;
    $stats = ['total_centers' => 0, 'active_centers' => 0, 'inactive_centers' => 0, 'avg_hourly_rate' => 0, 'total_capacity' => 0];
}

$page_title = 'Work Centers';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Work Centers</h1>
            <p class="text-muted">Manage manufacturing locations and resources</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline" onclick="exportWorkCenters()">
                <i class="fas fa-download"></i> Export
            </button>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Work Center
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-primary"><?php echo number_format($stats['total_centers']); ?></div>
                    <div class="text-muted small">Total Centers</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-success"><?php echo number_format($stats['active_centers']); ?></div>
                    <div class="text-muted small">Active</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-warning"><?php echo number_format($stats['maintenance_centers']); ?></div>
                    <div class="text-muted small">Maintenance</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-secondary"><?php echo number_format($stats['inactive_centers']); ?></div>
                    <div class="text-muted small">Inactive</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-info">$<?php echo number_format($stats['avg_hourly_rate'], 2); ?></div>
                    <div class="text-muted small">Avg Rate/Hr</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-dark"><?php echo number_format($stats['total_capacity']); ?></div>
                    <div class="text-muted small">Total Capacity</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           placeholder="Work center name or description..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="maintenance" <?php echo $status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Work Centers Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Work Centers (<?php echo number_format($total_items); ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($work_centers)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Work Center</th>
                                <th>Capacity</th>
                                <th>Hourly Rate</th>
                                <th>Status</th>
                                <th>Active Orders</th>
                                <th>Completed Today</th>
                                <th>Utilization</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($work_centers as $center): ?>
                                <?php
                                $utilization = 0;
                                if ($center['capacity'] > 0) {
                                    $utilization = ($center['active_orders'] / $center['capacity']) * 100;
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium"><?php echo htmlspecialchars($center['center_name']); ?></div>
                                        <?php if ($center['description']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($center['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-medium"><?php echo number_format($center['capacity']); ?></span>
                                        <small class="text-muted">units</small>
                                    </td>
                                    <td>$<?php echo number_format($center['hourly_rate'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            if ($center['status'] === 'active') echo 'bg-success';
                                            elseif ($center['status'] === 'maintenance') echo 'bg-warning';
                                            elseif ($center['status'] === 'inactive') echo 'bg-secondary';
                                            else echo 'bg-secondary';
                                        ?>">
                                            <?php echo ucfirst($center['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($center['active_orders'] > 0): ?>
                                            <a href="../work_orders/index.php?work_center_id=<?php echo $center['id']; ?>&status=active" class="text-primary">
                                                <?php echo number_format($center['active_orders']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($center['completed_today'] > 0): ?>
                                            <span class="text-success"><?php echo number_format($center['completed_today']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                <div class="progress-bar <?php 
                                                    echo $utilization >= 100 ? 'bg-danger' : ($utilization >= 80 ? 'bg-warning' : 'bg-success'); 
                                                ?>" style="width: <?php echo min(100, $utilization); ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($utilization, 0); ?>%</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo date('M j, Y', strtotime($center['created_at'])); ?></div>
                                        <small class="text-muted">by Admin</small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $center['id']; ?>" class="btn btn-outline-primary btn-sm" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $center['id']; ?>" class="btn btn-outline-secondary btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-outline-warning btn-sm" onclick="toggleStatus(<?php echo $center['id']; ?>, '<?php echo $center['status']; ?>')" 
                                                    title="<?php echo $center['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $center['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
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
                    <i class="fas fa-industry fa-3x text-muted mb-3"></i>
                    <h5>No work centers found</h5>
                    <p class="text-muted">Create your first work center to start managing manufacturing resources.</p>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Work Center
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$inline_js = "
function toggleStatus(centerId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'activate' : 'deactivate';
    
    if (confirm(`Are you sure you want to \${action} this work center?`)) {
        fetch('update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                center_id: centerId, 
                status: newStatus 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(`Work center \${action}d successfully`, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || `Failed to \${action} work center`, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred while updating the work center', 'error');
        });
    }
}

function exportWorkCenters() {
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