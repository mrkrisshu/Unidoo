<?php
/**
 * View Manufacturing Order
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$mo_id = intval($_GET['id'] ?? 0);

if ($mo_id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get manufacturing order details
    $stmt = $conn->prepare("
        SELECT mo.*, p.product_code, p.product_name, p.unit_of_measure,
               b.bom_code, b.bom_name, b.total_cost as bom_cost,
               u.username as created_by_name,
               (SELECT COUNT(*) FROM work_orders wo WHERE wo.mo_id = mo.id) as work_orders_count,
               (SELECT COUNT(*) FROM work_orders wo WHERE wo.mo_id = mo.id AND wo.status = 'completed') as completed_work_orders,
               (SELECT SUM(wo.actual_duration) FROM work_orders wo WHERE wo.mo_id = mo.id AND wo.status = 'completed') as total_actual_duration,
               (SELECT SUM(bo.duration * mo.quantity) FROM bom_operations bo 
                JOIN bom b2 ON bo.bom_id = b2.id WHERE b2.id = mo.bom_id) as planned_duration
        FROM manufacturing_orders mo
        LEFT JOIN products p ON mo.product_id = p.id
        LEFT JOIN bom b ON mo.bom_id = b.id
        LEFT JOIN users u ON mo.created_by = u.id
        WHERE mo.id = ?
    ");
    $stmt->execute([$mo_id]);
    $mo = $stmt->fetch();
    
    if (!$mo) {
        header('Location: index.php');
        exit;
    }
    
    // Get work orders
    $stmt = $conn->prepare("
        SELECT wo.*, wc.center_name, u.username as assigned_to_name,
               bo.operation_name, bo.duration as planned_duration
        FROM work_orders wo
        LEFT JOIN work_centers wc ON wo.work_center_id = wc.id
        LEFT JOIN users u ON wo.assigned_to = u.id
        LEFT JOIN bom_operations bo ON wo.operation_id = bo.id
        WHERE wo.mo_id = ?
        ORDER BY wo.sequence_number, wo.created_at
    ");
    $stmt->execute([$mo_id]);
    $work_orders = $stmt->fetchAll();
    
    // Get BOM materials if BOM exists
    $materials = [];
    if ($mo['bom_id']) {
        $stmt = $conn->prepare("
            SELECT bm.*, m.material_name, m.unit_of_measure, m.cost_per_unit,
                   (bm.quantity * ?) as total_quantity_needed,
                   (bm.quantity * ? * m.cost_per_unit) as total_cost
            FROM bom_materials bm
            JOIN materials m ON bm.material_id = m.id
            WHERE bm.bom_id = ?
            ORDER BY bm.sequence_number
        ");
        $stmt->execute([$mo['quantity'], $mo['quantity'], $mo['bom_id']]);
        $materials = $stmt->fetchAll();
    }
    
    // Get stock movements related to this MO
    $stmt = $conn->prepare("
        SELECT sl.*, m.material_name, u.username as created_by_name
        FROM stock_ledger sl
        LEFT JOIN materials m ON sl.material_id = m.id
        LEFT JOIN users u ON sl.created_by = u.id
        WHERE sl.reference_type = 'manufacturing_order' AND sl.reference_id = ?
        ORDER BY sl.created_at DESC
    ");
    $stmt->execute([$mo_id]);
    $stock_movements = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: index.php');
    exit;
}

// Calculate progress
$progress = $mo['work_orders_count'] > 0 ? 
    round(($mo['completed_work_orders'] / $mo['work_orders_count']) * 100) : 0;

// Calculate estimated cost
$estimated_cost = 0;
if ($mo['bom_cost']) {
    $estimated_cost = $mo['bom_cost'] * $mo['quantity'];
}

$page_title = 'Manufacturing Order - ' . $mo['mo_number'];
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1"><?php echo htmlspecialchars($mo['mo_number']); ?></h1>
            <p class="text-muted"><?php echo htmlspecialchars($mo['product_name']); ?></p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($mo['status'] === 'draft'): ?>
                <a href="edit.php?id=<?php echo $mo['id']; ?>" class="btn btn-outline">
                    <i class="fas fa-edit"></i> Edit
                </a>
            <?php endif; ?>
            
            <?php if (in_array($mo['status'], ['draft', 'released']) && $mo['bom_id']): ?>
                <button class="btn btn-success" onclick="generateWorkOrders()">
                    <i class="fas fa-cogs"></i> Generate Work Orders
                </button>
            <?php endif; ?>
            
            <?php if ($mo['status'] === 'draft'): ?>
                <button class="btn btn-warning" onclick="releaseMO()">
                    <i class="fas fa-play"></i> Release
                </button>
            <?php endif; ?>
            
            <div class="dropdown">
                <button class="btn btn-outline dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="printMO()">
                        <i class="fas fa-print"></i> Print
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportMO()">
                        <i class="fas fa-download"></i> Export
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if (in_array($mo['status'], ['draft', 'released'])): ?>
                        <li><a class="dropdown-item text-danger" href="#" onclick="cancelMO()">
                            <i class="fas fa-times"></i> Cancel Order
                        </a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- MO Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Order Details</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-medium">Product:</td>
                                    <td><?php echo htmlspecialchars($mo['product_code'] . ' - ' . $mo['product_name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">BOM:</td>
                                    <td>
                                        <?php if ($mo['bom_code']): ?>
                                            <a href="../bom/view.php?id=<?php echo $mo['bom_id']; ?>" class="text-primary">
                                                <?php echo htmlspecialchars($mo['bom_code'] . ' - ' . $mo['bom_name']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No BOM assigned</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Quantity:</td>
                                    <td>
                                        <span class="badge badge-info fs-6">
                                            <?php echo number_format($mo['quantity']); ?> <?php echo htmlspecialchars($mo['unit_of_measure']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Priority:</td>
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
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-medium">Status:</td>
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
                                        ?> fs-6">
                                            <?php echo ucfirst(str_replace('_', ' ', $mo['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Progress:</td>
                                    <td>
                                        <div class="progress mb-1" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $progress; ?>%"
                                                 aria-valuenow="<?php echo $progress; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $progress; ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $mo['completed_work_orders']; ?>/<?php echo $mo['work_orders_count']; ?> Work Orders
                                        </small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Planned Start:</td>
                                    <td><?php echo date('M d, Y H:i', strtotime($mo['planned_start_date'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">Planned End:</td>
                                    <td><?php echo date('M d, Y H:i', strtotime($mo['planned_end_date'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($mo['description']): ?>
                        <div class="mt-3">
                            <h6 class="fw-medium">Description:</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($mo['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Work Orders -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Work Orders (<?php echo count($work_orders); ?>)</h3>
                    <?php if (empty($work_orders) && $mo['bom_id'] && in_array($mo['status'], ['draft', 'released'])): ?>
                        <button class="btn btn-primary btn-sm" onclick="generateWorkOrders()">
                            <i class="fas fa-plus"></i> Generate Work Orders
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($work_orders)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>WO Number</th>
                                        <th>Operation</th>
                                        <th>Work Center</th>
                                        <th>Assigned To</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($work_orders as $wo): ?>
                                        <tr>
                                            <td>
                                                <a href="../work_orders/view.php?id=<?php echo $wo['id']; ?>" class="text-primary fw-medium">
                                                    <?php echo htmlspecialchars($wo['wo_number']); ?>
                                                </a>
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
                                                        <small class="text-success">Actual: <?php echo $wo['actual_duration']; ?> min</small>
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
                                                <a href="../work_orders/view.php?id=<?php echo $wo['id']; ?>" 
                                                   class="btn btn-sm btn-outline" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-cogs"></i>
                            <h4>No Work Orders</h4>
                            <p>Work orders will be generated based on the BOM operations.</p>
                            <?php if ($mo['bom_id'] && in_array($mo['status'], ['draft', 'released'])): ?>
                                <button class="btn btn-primary" onclick="generateWorkOrders()">
                                    <i class="fas fa-plus"></i> Generate Work Orders
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Materials Required -->
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
                                        <th>Unit Qty</th>
                                        <th>Total Qty</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_material_cost = 0;
                                    foreach ($materials as $material): 
                                        $total_material_cost += $material['total_cost'];
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($material['material_name']); ?></td>
                                            <td><?php echo number_format($material['quantity'], 2); ?> <?php echo htmlspecialchars($material['unit_of_measure']); ?></td>
                                            <td><?php echo number_format($material['total_quantity_needed'], 2); ?> <?php echo htmlspecialchars($material['unit_of_measure']); ?></td>
                                            <td>$<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                                            <td>$<?php echo number_format($material['total_cost'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-active">
                                        <th colspan="4">Total Material Cost:</th>
                                        <th>$<?php echo number_format($total_material_cost, 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Stock Movements -->
            <?php if (!empty($stock_movements)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Stock Movements</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Material</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Created By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stock_movements as $movement): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i', strtotime($movement['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($movement['material_name']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $movement['movement_type'] === 'in' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($movement['movement_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($movement['quantity'], 2); ?></td>
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
            <!-- Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Summary</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Estimated Cost:</span>
                        <span class="fw-bold">$<?php echo number_format($estimated_cost, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Work Orders:</span>
                        <span><?php echo $mo['work_orders_count']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Completed:</span>
                        <span class="text-success"><?php echo $mo['completed_work_orders']; ?></span>
                    </div>
                    <?php if ($mo['planned_duration']): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Planned Duration:</span>
                            <span><?php echo number_format($mo['planned_duration']); ?> min</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($mo['total_actual_duration']): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Actual Duration:</span>
                            <span class="text-info"><?php echo number_format($mo['total_actual_duration']); ?> min</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Timeline</h3>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Created</h6>
                                <p class="text-muted mb-0"><?php echo date('M d, Y H:i', strtotime($mo['created_at'])); ?></p>
                                <small class="text-muted">by <?php echo htmlspecialchars($mo['created_by_name']); ?></small>
                            </div>
                        </div>
                        
                        <?php if ($mo['status'] !== 'draft'): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-warning"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Released</h6>
                                    <p class="text-muted mb-0"><?php echo date('M d, Y H:i', strtotime($mo['updated_at'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($mo['status'] === 'in_progress'): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-info"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">In Progress</h6>
                                    <p class="text-muted mb-0">Production started</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($mo['status'] === 'completed'): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Completed</h6>
                                    <p class="text-muted mb-0"><?php echo date('M d, Y H:i', strtotime($mo['updated_at'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
function generateWorkOrders() {
    if (confirm('Generate work orders for this manufacturing order? This will create work orders based on the BOM operations.')) {
        fetch('generate_work_orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mo_id: {$mo['id']} })
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

function releaseMO() {
    if (confirm('Release this manufacturing order? Once released, it cannot be edited.')) {
        fetch('update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                mo_id: {$mo['id']}, 
                status: 'released' 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Manufacturing order released successfully', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to release manufacturing order', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred while releasing the order', 'error');
        });
    }
}

function cancelMO() {
    if (confirm('Cancel this manufacturing order? This action cannot be undone.')) {
        fetch('update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                mo_id: {$mo['id']}, 
                status: 'cancelled' 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Manufacturing order cancelled', 'warning');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to cancel manufacturing order', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred while cancelling the order', 'error');
        });
    }
}

function printMO() {
    window.print();
}

function exportMO() {
    window.location.href = 'export.php?id={$mo['id']}&format=pdf';
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