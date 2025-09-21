<?php
/**
 * Reports Dashboard
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

try {
    $conn = getDBConnection();
    
    // Get date range (default to last 30 days)
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    // Manufacturing Orders Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_orders,
            SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released_orders,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            SUM(quantity) as total_quantity,
            SUM(CASE WHEN status = 'completed' THEN quantity ELSE 0 END) as completed_quantity,
            AVG(CASE WHEN status = 'completed' AND material_cost > 0 THEN material_cost ELSE NULL END) as avg_material_cost,
            AVG(CASE WHEN status = 'completed' AND labor_cost > 0 THEN labor_cost ELSE NULL END) as avg_labor_cost
        FROM manufacturing_orders 
        WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$start_date, $end_date]);
    $mo_stats = $stmt->fetch();
    
    // Work Orders Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_work_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_work_orders,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_work_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_work_orders,
            AVG(CASE WHEN status = 'completed' AND actual_duration > 0 THEN actual_duration ELSE NULL END) as avg_duration,
            SUM(CASE WHEN status = 'completed' THEN labor_cost ELSE 0 END) as total_labor_cost,
            AVG(CASE 
                WHEN status = 'completed' AND estimated_duration > 0 AND actual_duration > 0 
                THEN (estimated_duration / actual_duration) * 100 
                ELSE NULL 
            END) as avg_efficiency
        FROM work_orders 
        WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$start_date, $end_date]);
    $wo_stats = $stmt->fetch();
    
    // Stock Movement Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_movements,
            SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END) as total_in,
            SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END) as total_out,
            SUM(CASE WHEN movement_type = 'adjustment' AND quantity > 0 THEN quantity ELSE 0 END) as positive_adjustments,
            SUM(CASE WHEN movement_type = 'adjustment' AND quantity < 0 THEN ABS(quantity) ELSE 0 END) as negative_adjustments,
            COUNT(DISTINCT material_id) as materials_affected
        FROM stock_movements 
        WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$start_date, $end_date]);
    $stock_stats = $stmt->fetch();
    
    // Work Center Performance
    $stmt = $conn->prepare("
        SELECT 
            wc.center_name,
            COUNT(wo.id) as total_orders,
            SUM(CASE WHEN wo.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            AVG(CASE WHEN wo.status = 'completed' AND wo.actual_duration > 0 THEN wo.actual_duration ELSE NULL END) as avg_duration,
            SUM(CASE WHEN wo.status = 'completed' THEN wo.labor_cost ELSE 0 END) as total_cost,
            AVG(CASE 
                WHEN wo.status = 'completed' AND wo.estimated_duration > 0 AND wo.actual_duration > 0 
                THEN (wo.estimated_duration / wo.actual_duration) * 100 
                ELSE NULL 
            END) as efficiency
        FROM work_centers wc
        LEFT JOIN work_orders wo ON wc.id = wo.work_center_id 
            AND wo.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        WHERE wc.status = 'active'
        GROUP BY wc.id, wc.center_name
        ORDER BY completed_orders DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $wc_performance = $stmt->fetchAll();
    
    // Daily Production Trend (last 30 days)
    $stmt = $conn->prepare("
        SELECT 
            DATE(wo.updated_at) as date,
            COUNT(*) as completed_orders,
            SUM(mo.quantity) as total_quantity,
            AVG(wo.actual_duration) as avg_duration,
            SUM(wo.labor_cost) as daily_labor_cost
        FROM work_orders wo
        INNER JOIN manufacturing_orders mo ON wo.manufacturing_order_id = mo.id
        WHERE wo.status = 'completed' 
        AND wo.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(wo.updated_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute();
    $production_trend = $stmt->fetchAll();
    
    // Top Materials by Usage
    $stmt = $conn->prepare("
        SELECT 
            m.material_name,
            m.unit,
            SUM(ABS(sm.quantity)) as total_usage,
            COUNT(sm.id) as movement_count,
            AVG(m.unit_cost) as avg_cost
        FROM materials m
        INNER JOIN stock_movements sm ON m.id = sm.material_id
        WHERE sm.movement_type = 'out' 
        AND sm.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY m.id, m.material_name, m.unit
        ORDER BY total_usage DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_materials = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $mo_stats = $wo_stats = $stock_stats = [];
    $wc_performance = $production_trend = $top_materials = [];
}

$page_title = 'Reports & Analytics';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Reports & Analytics</h1>
            <p class="text-muted">Manufacturing performance insights and data analysis</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline" onclick="exportAllReports()">
                <i class="fas fa-download"></i> Export All
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customReportModal">
                <i class="fas fa-chart-bar"></i> Custom Report
            </button>
        </div>
    </div>
    
    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                        <button type="button" class="btn btn-outline" onclick="setQuickRange('today')">Today</button>
                        <button type="button" class="btn btn-outline" onclick="setQuickRange('week')">This Week</button>
                        <button type="button" class="btn btn-outline" onclick="setQuickRange('month')">This Month</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Key Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h3 text-primary"><?php echo number_format($mo_stats['total_orders'] ?? 0); ?></div>
                    <div class="text-muted">Manufacturing Orders</div>
                    <div class="small text-success">
                        <?php echo number_format($mo_stats['completed_orders'] ?? 0); ?> completed
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h3 text-info"><?php echo number_format($wo_stats['total_work_orders'] ?? 0); ?></div>
                    <div class="text-muted">Work Orders</div>
                    <div class="small text-success">
                        <?php echo number_format($wo_stats['avg_efficiency'] ?? 0, 1); ?>% efficiency
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h3 text-warning"><?php echo number_format($stock_stats['total_movements'] ?? 0); ?></div>
                    <div class="text-muted">Stock Movements</div>
                    <div class="small text-info">
                        <?php echo number_format($stock_stats['materials_affected'] ?? 0); ?> materials
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h3 text-success">$<?php echo number_format($wo_stats['total_labor_cost'] ?? 0, 2); ?></div>
                    <div class="text-muted">Labor Cost</div>
                    <div class="small text-secondary">
                        <?php echo $wo_stats['avg_duration'] ? number_format($wo_stats['avg_duration'], 1) . 'h avg' : 'N/A'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Production Trend Chart -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Production Trend (Last 30 Days)</h3>
                    <button class="btn btn-sm btn-outline" onclick="exportChart('production')">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
                <div class="card-body">
                    <canvas id="productionChart" height="300"></canvas>
                </div>
            </div>
            
            <!-- Work Center Performance -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Work Center Performance</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($wc_performance)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Work Center</th>
                                        <th>Total Orders</th>
                                        <th>Completed</th>
                                        <th>Avg Duration</th>
                                        <th>Total Cost</th>
                                        <th>Efficiency</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wc_performance as $wc): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($wc['center_name']); ?></td>
                                            <td><?php echo number_format($wc['total_orders']); ?></td>
                                            <td>
                                                <span class="text-success"><?php echo number_format($wc['completed_orders']); ?></span>
                                                <?php if ($wc['total_orders'] > 0): ?>
                                                    <small class="text-muted">
                                                        (<?php echo number_format(($wc['completed_orders'] / $wc['total_orders']) * 100, 1); ?>%)
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $wc['avg_duration'] ? number_format($wc['avg_duration'], 1) . 'h' : 'N/A'; ?>
                                            </td>
                                            <td>$<?php echo number_format($wc['total_cost'], 2); ?></td>
                                            <td>
                                                <?php if ($wc['efficiency']): ?>
                                                    <span class="badge badge-<?php echo $wc['efficiency'] >= 100 ? 'success' : ($wc['efficiency'] >= 80 ? 'warning' : 'danger'); ?>">
                                                        <?php echo number_format($wc['efficiency'], 1); ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-bar fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No work center data available for the selected period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Reports -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Quick Reports</h3>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="manufacturing_orders.php" class="btn btn-outline">
                            <i class="fas fa-industry"></i> Manufacturing Orders Report
                        </a>
                        <a href="work_orders.php" class="btn btn-outline">
                            <i class="fas fa-tasks"></i> Work Orders Report
                        </a>
                        <a href="inventory.php" class="btn btn-outline">
                            <i class="fas fa-boxes"></i> Inventory Report
                        </a>
                        <a href="costs.php" class="btn btn-outline">
                            <i class="fas fa-dollar-sign"></i> Cost Analysis Report
                        </a>
                        <a href="efficiency.php" class="btn btn-outline">
                            <i class="fas fa-chart-line"></i> Efficiency Report
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Top Materials -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Top Materials by Usage</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_materials)): ?>
                        <?php foreach ($top_materials as $material): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <div class="fw-medium"><?php echo htmlspecialchars($material['material_name']); ?></div>
                                    <small class="text-muted"><?php echo number_format($material['movement_count']); ?> movements</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-medium"><?php echo number_format($material['total_usage'], 2); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($material['unit']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-box fa-2x text-muted mb-2"></i>
                            <p class="text-muted small">No material usage data available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Manufacturing Status -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Manufacturing Status</h3>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="h5 text-warning"><?php echo number_format($mo_stats['draft_orders'] ?? 0); ?></div>
                            <div class="small text-muted">Draft</div>
                        </div>
                        <div class="col-6">
                            <div class="h5 text-info"><?php echo number_format($mo_stats['released_orders'] ?? 0); ?></div>
                            <div class="small text-muted">Released</div>
                        </div>
                        <div class="col-6 mt-3">
                            <div class="h5 text-primary"><?php echo number_format($mo_stats['in_progress_orders'] ?? 0); ?></div>
                            <div class="small text-muted">In Progress</div>
                        </div>
                        <div class="col-6 mt-3">
                            <div class="h5 text-success"><?php echo number_format($mo_stats['completed_orders'] ?? 0); ?></div>
                            <div class="small text-muted">Completed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Report Modal -->
<div class="modal fade" id="customReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Custom Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="customReportForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select id="report_type" name="report_type" class="form-select" required>
                                    <option value="">Select Report Type</option>
                                    <option value="manufacturing_orders">Manufacturing Orders</option>
                                    <option value="work_orders">Work Orders</option>
                                    <option value="inventory">Inventory Analysis</option>
                                    <option value="costs">Cost Analysis</option>
                                    <option value="efficiency">Efficiency Report</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="report_format" class="form-label">Format</label>
                                <select id="report_format" name="report_format" class="form-select" required>
                                    <option value="csv">CSV</option>
                                    <option value="pdf">PDF</option>
                                    <option value="excel">Excel</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="custom_start_date" class="form-label">Start Date</label>
                                <input type="date" id="custom_start_date" name="start_date" class="form-control" 
                                       value="<?php echo $start_date; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="custom_end_date" class="form-label">End Date</label>
                                <input type="date" id="custom_end_date" name="end_date" class="form-control" 
                                       value="<?php echo $end_date; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Additional Filters</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_cancelled" name="include_cancelled">
                                    <label class="form-check-label" for="include_cancelled">Include Cancelled</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_costs" name="include_costs" checked>
                                    <label class="form-check-label" for="include_costs">Include Costs</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_materials" name="include_materials">
                                    <label class="form-check-label" for="include_materials">Include Materials</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="generateCustomReport()">
                    <i class="fas fa-download"></i> Generate Report
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
// Production Chart
const productionData = " . json_encode(array_reverse($production_trend)) . ";
const ctx = document.getElementById('productionChart').getContext('2d');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: productionData.map(item => item.date),
        datasets: [{
            label: 'Completed Orders',
            data: productionData.map(item => item.completed_orders),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1
        }, {
            label: 'Total Quantity',
            data: productionData.map(item => item.total_quantity),
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            tension: 0.1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Orders'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Quantity'
                },
                grid: {
                    drawOnChartArea: false,
                }
            }
        }
    }
});

function setQuickRange(range) {
    const today = new Date();
    let startDate, endDate = today.toISOString().split('T')[0];
    
    switch(range) {
        case 'today':
            startDate = endDate;
            break;
        case 'week':
            const weekStart = new Date(today);
            weekStart.setDate(today.getDate() - today.getDay());
            startDate = weekStart.toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            break;
    }
    
    document.getElementById('start_date').value = startDate;
    document.getElementById('end_date').value = endDate;
    document.querySelector('form').submit();
}

function exportAllReports() {
    if (confirm('This will generate and download all available reports. Continue?')) {
        window.open('export_all.php?start_date=' + encodeURIComponent('{$start_date}') + '&end_date=' + encodeURIComponent('{$end_date}'), '_blank');
    }
}

function exportChart(type) {
    const canvas = document.getElementById('productionChart');
    const url = canvas.toDataURL('image/png');
    const link = document.createElement('a');
    link.download = type + '_chart_' + new Date().toISOString().split('T')[0] + '.png';
    link.href = url;
    link.click();
}

function generateCustomReport() {
    const form = document.getElementById('customReportForm');
    const formData = new FormData(form);
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const params = new URLSearchParams(formData);
    window.open('generate_custom.php?' + params.toString(), '_blank');
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('customReportModal'));
    modal.hide();
}
";

include '../includes/footer.php';
?>