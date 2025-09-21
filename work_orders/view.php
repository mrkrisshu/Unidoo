<?php
/**
 * Work Order Details View
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$wo_id = intval($_GET['id'] ?? 0);

if (!$wo_id) {
    header('Location: index.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get work order details
    $stmt = $conn->prepare("
        SELECT wo.*, mo.mo_number, mo.product_id, mo.quantity as mo_quantity, mo.priority,
               p.product_name, p.product_code,
               bo.operation_name, bo.description as operation_description, 
               bo.duration as planned_duration, bo.setup_time, bo.machine_time,
               wc.center_name, wc.hourly_rate, wc.capacity,
               assigned_user.username as assigned_to_name,
               created_user.username as created_by_name,
               updated_user.username as updated_by_name
        FROM work_orders wo
        LEFT JOIN manufacturing_orders mo ON wo.mo_id = mo.id
        LEFT JOIN products p ON mo.product_id = p.id
        LEFT JOIN bom_operations bo ON wo.operation_id = bo.id
        LEFT JOIN work_centers wc ON wo.work_center_id = wc.id
        LEFT JOIN users assigned_user ON wo.assigned_to = assigned_user.id
        LEFT JOIN users created_user ON wo.created_by = created_user.id
        LEFT JOIN users updated_user ON wo.updated_by = updated_user.id
        WHERE wo.id = ?
    ");
    $stmt->execute([$wo_id]);
    $work_order = $stmt->fetch();
    
    if (!$work_order) {
        header('Location: index.php');
        exit;
    }
    
    // Get work order logs
    $stmt = $conn->prepare("
        SELECT wol.*, u.username
        FROM work_order_logs wol
        LEFT JOIN users u ON wol.user_id = u.id
        WHERE wol.wo_id = ?
        ORDER BY wol.created_at DESC
    ");
    $stmt->execute([$wo_id]);
    $logs = $stmt->fetchAll();
    
    // Get materials consumed in this operation
    $stmt = $conn->prepare("
        SELECT bm.*, m.material_name, m.material_code, m.unit, m.cost_per_unit,
               (bm.quantity * ?) as total_required,
               COALESCE(consumed.total_consumed, 0) as consumed_quantity
        FROM bom_materials bm
        JOIN materials m ON bm.material_id = m.id
        JOIN bom_operations bo ON bm.bom_id = bo.bom_id
        LEFT JOIN (
            SELECT material_id, SUM(quantity) as total_consumed
            FROM stock_movements
            WHERE work_order_id = ? AND movement_type = 'out'
            GROUP BY material_id
        ) consumed ON bm.material_id = consumed.material_id
        WHERE bo.id = ? AND bm.consumed_in_operation = ?
    ");
    $stmt->execute([$work_order['quantity'], $wo_id, $work_order['operation_id'], $work_order['operation_id']]);
    $materials = $stmt->fetchAll();
    
    // Get stock movements for this work order
    $stmt = $conn->prepare("
        SELECT sm.*, m.material_name, m.unit, u.username as created_by_name
        FROM stock_movements sm
        LEFT JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.created_by = u.id
        WHERE sm.work_order_id = ?
        ORDER BY sm.created_at DESC
    ");
    $stmt->execute([$wo_id]);
    $stock_movements = $stmt->fetchAll();
    
    // Calculate costs
    $labor_cost = 0;
    if ($work_order['actual_duration'] && $work_order['hourly_rate']) {
        $labor_cost = ($work_order['actual_duration'] / 60) * $work_order['hourly_rate'];
    }
    
    $material_cost = 0;
    foreach ($materials as $material) {
        $material_cost += $material['consumed_quantity'] * $material['cost_per_unit'];
    }
    
    $total_cost = $labor_cost + $material_cost;
    
    // Get work centers for assignment
    $stmt = $conn->prepare("
        SELECT id, center_name
        FROM work_centers
        WHERE status = 'active'
        ORDER BY center_name
    ");
    $stmt->execute();
    $work_centers = $stmt->fetchAll();
    
    // Get operators for assignment
    $stmt = $conn->prepare("
        SELECT id, username
        FROM users
        WHERE role IN ('operator', 'supervisor', 'admin') AND status = 'active'
        ORDER BY username
    ");
    $stmt->execute();
    $operators = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: index.php');
    exit;
}

$page_title = 'Work Order - ' . $work_order['wo_number'];
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Work Orders</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($work_order['wo_number']); ?></li>
                </ol>
            </nav>
            <h1 class="mb-1"><?php echo htmlspecialchars($work_order['wo_number']); ?></h1>
            <p class="text-muted"><?php echo htmlspecialchars($work_order['operation_name']); ?></p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($work_order['status'] === 'pending'): ?>
                <button class="btn btn-success" onclick="startWorkOrder()">
                    <i class="fas fa-play"></i> Start
                </button>
            <?php elseif ($work_order['status'] === 'in_progress'): ?>
                <button class="btn btn-warning" onclick="pauseWorkOrder()">
                    <i class="fas fa-pause"></i> Pause
                </button>
                <button class="btn btn-success" onclick="completeWorkOrder()">
                    <i class="fas fa-check"></i> Complete
                </button>
            <?php elseif ($work_order['status'] === 'paused'): ?>
                <button class="btn btn-primary" onclick="resumeWorkOrder()">
                    <i class="fas fa-play"></i> Resume
                </button>
            <?php endif; ?>
            
            <?php if (in_array($work_order['status'], ['pending', 'paused'])): ?>
                <button class="btn btn-outline" onclick="showAssignmentModal()">
                    <i class="fas fa-user"></i> Assign
                </button>
            <?php endif; ?>
            
            <button class="btn btn-outline" onclick="printWorkOrder()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Work Order Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Work Order Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-medium">WO Number:</td>
                                    <td><?php echo htmlspecialchars($work_order['wo_number']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Manufacturing Order:</td>
                                    <td>
                                        <a href="../manufacturing_orders/view.php?id=<?php echo $work_order['mo_id']; ?>" class="text-primary">
                                            <?php echo htmlspecialchars($work_order['mo_number']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Product:</td>
                                    <td>
                                        <?php echo htmlspecialchars($work_order['product_name']); ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($work_order['product_code']); ?>)</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Operation:</td>
                                    <td><?php echo htmlspecialchars($work_order['operation_name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Sequence:</td>
                                    <td><?php echo $work_order['sequence_number']; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Quantity:</td>
                                    <td><?php echo number_format($work_order['quantity']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-medium">Status:</td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo match($work_order['status']) {
                                                'pending' => 'secondary',
                                                'in_progress' => 'primary',
                                                'paused' => 'warning',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $work_order['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Work Center:</td>
                                    <td>
                                        <?php if ($work_order['center_name']): ?>
                                            <?php echo htmlspecialchars($work_order['center_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Assigned To:</td>
                                    <td>
                                        <?php if ($work_order['assigned_to_name']): ?>
                                            <?php echo htmlspecialchars($work_order['assigned_to_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Created:</td>
                                    <td>
                                        <?php echo date('M j, Y g:i A', strtotime($work_order['created_at'])); ?>
                                        <br><small class="text-muted">by <?php echo htmlspecialchars($work_order['created_by_name']); ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Priority:</td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo match($work_order['priority']) {
                                                'high' => 'danger',
                                                'medium' => 'warning',
                                                'low' => 'success',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($work_order['priority']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($work_order['operation_description']): ?>
                        <div class="mt-3">
                            <h6>Operation Description:</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($work_order['operation_description'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Time Tracking -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Time Tracking</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-primary"><?php echo $work_order['planned_duration']; ?> min</div>
                                <div class="text-muted">Planned Duration</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 <?php echo $work_order['actual_duration'] ? ($work_order['actual_duration'] > $work_order['planned_duration'] ? 'text-warning' : 'text-success') : 'text-muted'; ?>">
                                    <?php echo $work_order['actual_duration'] ? $work_order['actual_duration'] . ' min' : 'N/A'; ?>
                                </div>
                                <div class="text-muted">Actual Duration</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-info">
                                    <?php 
                                    if ($work_order['start_time']) {
                                        echo date('g:i A', strtotime($work_order['start_time']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                                <div class="text-muted">Start Time</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-success">
                                    <?php 
                                    if ($work_order['end_time']) {
                                        echo date('g:i A', strtotime($work_order['end_time']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                                <div class="text-muted">End Time</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($work_order['actual_duration'] && $work_order['planned_duration']): ?>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Efficiency</span>
                                <span class="fw-medium">
                                    <?php 
                                    $efficiency = ($work_order['planned_duration'] / $work_order['actual_duration']) * 100;
                                    echo number_format($efficiency, 1) . '%';
                                    ?>
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar <?php echo $efficiency >= 100 ? 'bg-success' : ($efficiency >= 80 ? 'bg-warning' : 'bg-danger'); ?>" 
                                     style="width: <?php echo min(100, $efficiency); ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Materials -->
            <?php if (!empty($materials)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Materials Required</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th>Required</th>
                                        <th>Consumed</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materials as $material): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-medium"><?php echo htmlspecialchars($material['material_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($material['material_code']); ?></small>
                                            </td>
                                            <td><?php echo number_format($material['total_required'], 2) . ' ' . $material['unit']; ?></td>
                                            <td><?php echo number_format($material['consumed_quantity'], 2) . ' ' . $material['unit']; ?></td>
                                            <td>$<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                                            <td>$<?php echo number_format($material['consumed_quantity'] * $material['cost_per_unit'], 2); ?></td>
                                            <td>
                                                <?php if ($material['consumed_quantity'] >= $material['total_required']): ?>
                                                    <span class="badge badge-success">Complete</span>
                                                <?php elseif ($material['consumed_quantity'] > 0): ?>
                                                    <span class="badge badge-warning">Partial</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Stock Movements -->
            <?php if (!empty($stock_movements)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Stock Movements</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Material</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Notes</th>
                                        <th>Created By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stock_movements as $movement): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y g:i A', strtotime($movement['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($movement['material_name']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $movement['movement_type'] === 'in' ? 'success' : 'danger'; ?>">
                                                    <?php echo strtoupper($movement['movement_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo number_format($movement['quantity'], 2) . ' ' . $movement['unit']; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($movement['notes']); ?></td>
                                            <td><?php echo htmlspecialchars($movement['created_by_name']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Cost Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Cost Summary</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Labor Cost:</span>
                        <span class="fw-medium">$<?php echo number_format($labor_cost, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Material Cost:</span>
                        <span class="fw-medium">$<?php echo number_format($material_cost, 2); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="fw-medium">Total Cost:</span>
                        <span class="fw-bold text-primary">$<?php echo number_format($total_cost, 2); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Activity Timeline -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Activity Timeline</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($logs)): ?>
                        <div class="timeline">
                            <?php foreach ($logs as $log): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-<?php 
                                        echo match($log['action']) {
                                            'start' => 'success',
                                            'pause' => 'warning',
                                            'complete' => 'primary',
                                            'assign' => 'info',
                                            default => 'secondary'
                                        };
                                    ?>"></div>
                                    <div class="timeline-content">
                                        <div class="fw-medium"><?php echo ucfirst($log['action']); ?></div>
                                        <div class="text-muted small">
                                            <?php echo htmlspecialchars($log['notes']); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                            by <?php echo htmlspecialchars($log['username']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-clock fa-2x mb-2"></i>
                            <p>No activity recorded yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
                    <div class="mb-3">
                        <label for="assign_work_center" class="form-label">Work Center</label>
                        <select id="assign_work_center" name="work_center_id" class="form-select">
                            <option value="">Select Work Center</option>
                            <?php foreach ($work_centers as $center): ?>
                                <option value="<?php echo $center['id']; ?>" 
                                        <?php echo $work_order['work_center_id'] == $center['id'] ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $operator['id']; ?>" 
                                        <?php echo $work_order['assigned_to'] == $operator['id'] ? 'selected' : ''; ?>>
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
const woId = " . $wo_id . ";

function startWorkOrder() {
    updateWorkOrderStatus('in_progress', 'start');
}

function pauseWorkOrder() {
    updateWorkOrderStatus('paused', 'pause');
}

function resumeWorkOrder() {
    updateWorkOrderStatus('in_progress', 'resume');
}

function completeWorkOrder() {
    if (confirm('Mark this work order as completed?')) {
        updateWorkOrderStatus('completed', 'complete');
    }
}

function updateWorkOrderStatus(status, action) {
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

function showAssignmentModal() {
    const modal = new bootstrap.Modal(document.getElementById('assignmentModal'));
    modal.show();
}

function saveAssignment() {
    const formData = new FormData(document.getElementById('assignmentForm'));
    const data = Object.fromEntries(formData);
    data.wo_id = woId;
    
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

function printWorkOrder() {
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