<?php
/**
 * Work Center Details
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$center_id = intval($_GET['id'] ?? 0);
$created = $_GET['created'] ?? false;

if ($center_id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get work center details
    $stmt = $conn->prepare("
        SELECT wc.*, 
               created_user.username as created_by_name,
               updated_user.username as updated_by_name
        FROM work_centers wc
        LEFT JOIN users created_user ON wc.created_by = created_user.id
        LEFT JOIN users updated_user ON wc.updated_by = updated_user.id
        WHERE wc.id = ?
    ");
    $stmt->execute([$center_id]);
    $center = $stmt->fetch();
    
    if (!$center) {
        header('Location: index.php');
        exit;
    }
    
    // Get work order statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_orders,
            SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            AVG(CASE WHEN status = 'completed' AND actual_duration IS NOT NULL THEN actual_duration ELSE NULL END) as avg_duration,
            SUM(CASE WHEN status = 'completed' THEN labor_cost ELSE 0 END) as total_labor_cost
        FROM work_orders 
        WHERE work_center_id = ?
    ");
    $stmt->execute([$center_id]);
    $stats = $stmt->fetch();
    
    // Get recent work orders
    $stmt = $conn->prepare("
        SELECT wo.*, mo.mo_number, mo.product_name,
               u.username as assigned_operator_name
        FROM work_orders wo
        LEFT JOIN manufacturing_orders mo ON wo.manufacturing_order_id = mo.id
        LEFT JOIN users u ON wo.assigned_operator = u.id
        WHERE wo.work_center_id = ?
        ORDER BY wo.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$center_id]);
    $recent_orders = $stmt->fetchAll();
    
    // Get performance metrics for the last 30 days
    $stmt = $conn->prepare("
        SELECT 
            DATE(wo.updated_at) as date,
            COUNT(*) as completed_count,
            AVG(wo.actual_duration) as avg_duration,
            SUM(wo.labor_cost) as daily_cost
        FROM work_orders wo
        WHERE wo.work_center_id = ? 
        AND wo.status = 'completed' 
        AND wo.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(wo.updated_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute([$center_id]);
    $performance_data = $stmt->fetchAll();
    
    // Get activity logs
    $stmt = $conn->prepare("
        SELECT wcl.*, u.username as user_name
        FROM work_center_logs wcl
        LEFT JOIN users u ON wcl.created_by = u.id
        WHERE wcl.work_center_id = ?
        ORDER BY wcl.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$center_id]);
    $logs = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: index.php');
    exit;
}

$utilization = 0;
if ($center['capacity'] > 0) {
    $active_orders = $stats['pending_orders'] + $stats['in_progress_orders'] + $stats['paused_orders'];
    $utilization = ($active_orders / $center['capacity']) * 100;
}

$page_title = $center['center_name'];
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Success Message -->
    <?php if ($created): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> Work center created successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1"><?php echo htmlspecialchars($center['center_name']); ?></h1>
            <p class="text-muted"><?php echo htmlspecialchars($center['description'] ?: 'Work center details and performance metrics'); ?></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline" onclick="printWorkCenter()">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="edit.php?id=<?php echo $center['id']; ?>" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Work Center Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Work Center Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-medium">Status:</td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo match($center['status']) {
                                                'active' => 'success',
                                                'maintenance' => 'warning',
                                                'inactive' => 'secondary',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($center['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Capacity:</td>
                                    <td><?php echo number_format($center['capacity']); ?> units</td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Hourly Rate:</td>
                                    <td>$<?php echo number_format($center['hourly_rate'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Location:</td>
                                    <td><?php echo htmlspecialchars($center['location'] ?: 'Not specified'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-medium">Created:</td>
                                    <td>
                                        <?php echo date('M j, Y g:i A', strtotime($center['created_at'])); ?>
                                        <br><small class="text-muted">by <?php echo htmlspecialchars($center['created_by_name']); ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Last Updated:</td>
                                    <td>
                                        <?php echo date('M j, Y g:i A', strtotime($center['updated_at'])); ?>
                                        <br><small class="text-muted">by <?php echo htmlspecialchars($center['updated_by_name']); ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Current Utilization:</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                <div class="progress-bar <?php 
                                                    echo $utilization >= 100 ? 'bg-danger' : ($utilization >= 80 ? 'bg-warning' : 'bg-success'); 
                                                ?>" style="width: <?php echo min(100, $utilization); ?>%"></div>
                                            </div>
                                            <span class="fw-medium"><?php echo number_format($utilization, 0); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($center['notes']): ?>
                        <div class="mt-3">
                            <h6>Notes:</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($center['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Work Order Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Work Order Statistics</h3>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-2">
                            <div class="h4 text-primary"><?php echo number_format($stats['total_orders']); ?></div>
                            <div class="text-muted small">Total Orders</div>
                        </div>
                        <div class="col-md-2">
                            <div class="h4 text-warning"><?php echo number_format($stats['pending_orders']); ?></div>
                            <div class="text-muted small">Pending</div>
                        </div>
                        <div class="col-md-2">
                            <div class="h4 text-info"><?php echo number_format($stats['in_progress_orders']); ?></div>
                            <div class="text-muted small">In Progress</div>
                        </div>
                        <div class="col-md-2">
                            <div class="h4 text-success"><?php echo number_format($stats['completed_orders']); ?></div>
                            <div class="text-muted small">Completed</div>
                        </div>
                        <div class="col-md-2">
                            <div class="h4 text-secondary"><?php echo number_format($stats['paused_orders']); ?></div>
                            <div class="text-muted small">Paused</div>
                        </div>
                        <div class="col-md-2">
                            <div class="h4 text-danger"><?php echo number_format($stats['cancelled_orders']); ?></div>
                            <div class="text-muted small">Cancelled</div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row text-center">
                        <div class="col-md-6">
                            <div class="h5 text-info"><?php echo $stats['avg_duration'] ? number_format($stats['avg_duration'], 1) . ' hrs' : 'N/A'; ?></div>
                            <div class="text-muted small">Average Duration</div>
                        </div>
                        <div class="col-md-6">
                            <div class="h5 text-success">$<?php echo number_format($stats['total_labor_cost'], 2); ?></div>
                            <div class="text-muted small">Total Labor Cost</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Work Orders -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Recent Work Orders</h3>
                    <a href="../work_orders/index.php?work_center_id=<?php echo $center['id']; ?>" class="btn btn-sm btn-outline">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_orders)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>WO Number</th>
                                        <th>Product</th>
                                        <th>Status</th>
                                        <th>Operator</th>
                                        <th>Duration</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>
                                                <a href="../work_orders/view.php?id=<?php echo $order['id']; ?>" class="fw-medium">
                                                    <?php echo htmlspecialchars($order['wo_number']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($order['product_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($order['mo_number']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo match($order['status']) {
                                                        'pending' => 'warning',
                                                        'in_progress' => 'info',
                                                        'paused' => 'secondary',
                                                        'completed' => 'success',
                                                        'cancelled' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($order['assigned_operator_name'] ?: 'Unassigned'); ?></td>
                                            <td>
                                                <?php if ($order['actual_duration']): ?>
                                                    <?php echo number_format($order['actual_duration'], 1); ?>h
                                                <?php elseif ($order['estimated_duration']): ?>
                                                    <span class="text-muted"><?php echo number_format($order['estimated_duration'], 1); ?>h (est.)</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <a href="../work_orders/view.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No work orders found for this work center.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-<?php echo $center['status'] === 'active' ? 'warning' : 'success'; ?>" 
                                onclick="toggleStatus('<?php echo $center['status']; ?>')">
                            <i class="fas fa-<?php echo $center['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                            <?php echo $center['status'] === 'active' ? 'Set Maintenance' : 'Activate'; ?>
                        </button>
                        <a href="../work_orders/index.php?work_center_id=<?php echo $center['id']; ?>" class="btn btn-outline">
                            <i class="fas fa-list"></i> View Work Orders
                        </a>
                        <a href="edit.php?id=<?php echo $center['id']; ?>" class="btn btn-outline">
                            <i class="fas fa-edit"></i> Edit Details
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Performance Chart -->
            <?php if (!empty($performance_data)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">30-Day Performance</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart" height="200"></canvas>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Activity Timeline -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Activity</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($logs)): ?>
                        <div class="timeline">
                            <?php foreach (array_slice($logs, 0, 10) as $log): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <div class="fw-medium"><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></div>
                                        <div class="text-muted small">
                                            <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                            by <?php echo htmlspecialchars($log['user_name']); ?>
                                        </div>
                                        <?php if ($log['details']): ?>
                                            <?php $details = json_decode($log['details'], true); ?>
                                            <?php if ($details && isset($details['old_status'], $details['new_status'])): ?>
                                                <div class="text-muted small">
                                                    Status changed from <?php echo $details['old_status']; ?> to <?php echo $details['new_status']; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-history fa-2x text-muted mb-2"></i>
                            <p class="text-muted small">No activity recorded yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
function toggleStatus(currentStatus) {
    const newStatus = currentStatus === 'active' ? 'maintenance' : 'active';
    const action = newStatus === 'active' ? 'activate' : 'set to maintenance';
    
    if (confirm(`Are you sure you want to \${action} this work center?`)) {
        fetch('update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                center_id: {$center['id']}, 
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

function printWorkCenter() {
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

// Add Chart.js for performance chart if data exists
if (!empty($performance_data)) {
    $chart_data = [
        'labels' => array_reverse(array_column($performance_data, 'date')),
        'completed' => array_reverse(array_column($performance_data, 'completed_count')),
        'duration' => array_reverse(array_column($performance_data, 'avg_duration')),
        'cost' => array_reverse(array_column($performance_data, 'daily_cost'))
    ];
    
    $inline_js .= "
    // Performance Chart
    const ctx = document.getElementById('performanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: " . json_encode($chart_data['labels']) . ",
            datasets: [{
                label: 'Completed Orders',
                data: " . json_encode($chart_data['completed']) . ",
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    ";
}

include '../includes/footer.php';
?>