<?php
/**
 * Manufacturing Orders Report
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

try {
    $conn = getDBConnection();
    
    // Get filters
    $status = $_GET['status'] ?? '';
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $material_id = $_GET['material_id'] ?? '';
    $export = $_GET['export'] ?? '';
    
    // Build query
    $where_conditions = ["mo.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)"];
    $params = [$start_date, $end_date];
    
    if ($status) {
        $where_conditions[] = "mo.status = ?";
        $params[] = $status;
    }
    
    if ($material_id) {
        $where_conditions[] = "mo.material_id = ?";
        $params[] = $material_id;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get manufacturing orders
    $stmt = $conn->prepare("
        SELECT 
            mo.*,
            m.material_name,
            m.unit,
            u.username as created_by_name,
            COUNT(wo.id) as work_orders_count,
            SUM(CASE WHEN wo.status = 'completed' THEN 1 ELSE 0 END) as completed_work_orders,
            SUM(wo.labor_cost) as total_labor_cost,
            SUM(wo.actual_duration) as total_duration
        FROM manufacturing_orders mo
        LEFT JOIN materials m ON mo.material_id = m.id
        LEFT JOIN users u ON mo.created_by = u.id
        LEFT JOIN work_orders wo ON mo.id = wo.manufacturing_order_id
        WHERE {$where_clause}
        GROUP BY mo.id
        ORDER BY mo.created_at DESC
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Get summary statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(quantity) as total_quantity,
            SUM(material_cost) as total_material_cost,
            SUM(labor_cost) as total_labor_cost,
            AVG(CASE WHEN status = 'completed' AND material_cost > 0 THEN material_cost ELSE NULL END) as avg_material_cost,
            AVG(CASE WHEN status = 'completed' AND labor_cost > 0 THEN labor_cost ELSE NULL END) as avg_labor_cost,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
        FROM manufacturing_orders mo
        WHERE {$where_clause}
    ");
    $stmt->execute($params);
    $summary = $stmt->fetch();
    
    // Get materials for filter
    $stmt = $conn->prepare("SELECT id, material_name FROM materials ORDER BY material_name");
    $stmt->execute();
    $materials = $stmt->fetchAll();
    
    // Handle export
    if ($export) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="manufacturing_orders_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Order Number', 'Material', 'Quantity', 'Unit', 'Status', 
            'Priority', 'Start Date', 'Due Date', 'Created Date', 'Created By',
            'Material Cost', 'Labor Cost', 'Total Cost', 'Work Orders', 
            'Completed Work Orders', 'Total Duration (hours)', 'Notes'
        ]);
        
        // CSV data
        foreach ($orders as $order) {
            fputcsv($output, [
                $order['order_number'],
                $order['material_name'],
                $order['quantity'],
                $order['unit'],
                ucfirst($order['status']),
                ucfirst($order['priority']),
                $order['start_date'],
                $order['due_date'],
                $order['created_at'],
                $order['created_by_name'],
                $order['material_cost'],
                $order['total_labor_cost'] ?: $order['labor_cost'],
                ($order['material_cost'] + ($order['total_labor_cost'] ?: $order['labor_cost'])),
                $order['work_orders_count'],
                $order['completed_work_orders'],
                $order['total_duration'],
                $order['notes']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $orders = [];
    $summary = [];
    $materials = [];
}

$page_title = 'Manufacturing Orders Report';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Manufacturing Orders Report</h1>
            <p class="text-muted">Detailed analysis of manufacturing orders and production data</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </a>
            <button class="btn btn-primary" onclick="exportReport()">
                <i class="fas fa-download"></i> Export CSV
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="released" <?php echo $status === 'released' ? 'selected' : ''; ?>>Released</option>
                        <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="material_id" class="form-label">Material</label>
                    <select id="material_id" name="material_id" class="form-select">
                        <option value="">All Materials</option>
                        <?php foreach ($materials as $material): ?>
                            <option value="<?php echo $material['id']; ?>" 
                                    <?php echo $material_id == $material['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($material['material_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="manufacturing_orders.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-primary"><?php echo number_format($summary['total_orders'] ?? 0); ?></div>
                    <div class="text-muted small">Total Orders</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-info"><?php echo number_format($summary['total_quantity'] ?? 0); ?></div>
                    <div class="text-muted small">Total Quantity</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-success">$<?php echo number_format($summary['total_material_cost'] ?? 0, 2); ?></div>
                    <div class="text-muted small">Material Cost</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-warning">$<?php echo number_format($summary['total_labor_cost'] ?? 0, 2); ?></div>
                    <div class="text-muted small">Labor Cost</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-success"><?php echo number_format($summary['completed_count'] ?? 0); ?></div>
                    <div class="text-muted small">Completed</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-primary"><?php echo number_format($summary['in_progress_count'] ?? 0); ?></div>
                    <div class="text-muted small">In Progress</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Distribution Chart -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Status Distribution</h3>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Cost Breakdown</h3>
                </div>
                <div class="card-body">
                    <canvas id="costChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Orders Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Manufacturing Orders (<?php echo number_format(count($orders)); ?> orders)</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($orders)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Material</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Progress</th>
                                <th>Material Cost</th>
                                <th>Labor Cost</th>
                                <th>Total Cost</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="../manufacturing_orders/view.php?id=<?php echo $order['id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($order['order_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($order['material_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['unit']); ?></small>
                                    </td>
                                    <td><?php echo number_format($order['quantity']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo match($order['status']) {
                                                'draft' => 'secondary',
                                                'released' => 'info',
                                                'in_progress' => 'primary',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo match($order['priority']) {
                                                'high' => 'danger',
                                                'medium' => 'warning',
                                                'low' => 'success',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($order['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($order['due_date']): ?>
                                            <?php 
                                            $due_date = new DateTime($order['due_date']);
                                            $now = new DateTime();
                                            $is_overdue = $due_date < $now && $order['status'] !== 'completed';
                                            ?>
                                            <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                                <?php echo $due_date->format('M j, Y'); ?>
                                            </span>
                                            <?php if ($is_overdue): ?>
                                                <i class="fas fa-exclamation-triangle text-danger ms-1"></i>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order['work_orders_count'] > 0): ?>
                                            <div class="progress" style="height: 20px;">
                                                <?php 
                                                $progress = ($order['completed_work_orders'] / $order['work_orders_count']) * 100;
                                                ?>
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo $progress; ?>%">
                                                    <?php echo number_format($progress, 1); ?>%
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $order['completed_work_orders']; ?>/<?php echo $order['work_orders_count']; ?> work orders
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">No work orders</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format($order['material_cost'], 2); ?></td>
                                    <td>$<?php echo number_format($order['total_labor_cost'] ?: $order['labor_cost'], 2); ?></td>
                                    <td>
                                        <strong>$<?php echo number_format($order['material_cost'] + ($order['total_labor_cost'] ?: $order['labor_cost']), 2); ?></strong>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../manufacturing_orders/view.php?id=<?php echo $order['id']; ?>" 
                                               class="btn btn-outline" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
                                                <a href="../manufacturing_orders/edit.php?id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-outline" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-industry fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Manufacturing Orders Found</h5>
                    <p class="text-muted">No orders match the selected criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$inline_js = "
// Status Distribution Chart
const statusData = {
    labels: ['Draft', 'Released', 'In Progress', 'Completed', 'Cancelled'],
    datasets: [{
        data: [
            " . ($summary['draft_count'] ?? 0) . ",
            " . ($summary['released_count'] ?? 0) . ",
            " . ($summary['in_progress_count'] ?? 0) . ",
            " . ($summary['completed_count'] ?? 0) . ",
            " . ($summary['cancelled_count'] ?? 0) . "
        ],
        backgroundColor: [
            '#6c757d',
            '#17a2b8',
            '#007bff',
            '#28a745',
            '#dc3545'
        ]
    }]
};

const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: statusData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Cost Breakdown Chart
const costData = {
    labels: ['Material Cost', 'Labor Cost'],
    datasets: [{
        data: [
            " . ($summary['total_material_cost'] ?? 0) . ",
            " . ($summary['total_labor_cost'] ?? 0) . "
        ],
        backgroundColor: [
            '#28a745',
            '#ffc107'
        ]
    }]
};

const costCtx = document.getElementById('costChart').getContext('2d');
new Chart(costCtx, {
    type: 'doughnut',
    data: costData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = '?' + params.toString();
}
";

include '../includes/footer.php';
?>