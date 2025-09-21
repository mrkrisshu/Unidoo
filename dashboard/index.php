<?php
/**
 * Main Dashboard
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

// Get dashboard statistics
try {
    $conn = getDBConnection();
    
    // Manufacturing Orders Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned_orders,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
        FROM manufacturing_orders
    ");
    $stmt->execute();
    $mo_stats = $stmt->fetch();
    
    // Work Orders Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_work_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_work_orders,
            SUM(CASE WHEN status = 'started' THEN 1 ELSE 0 END) as started_work_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_work_orders
        FROM work_orders
    ");
    $stmt->execute();
    $wo_stats = $stmt->fetch();
    
    // Low Stock Products
    $stmt = $conn->prepare("
        SELECT COUNT(*) as low_stock_count
        FROM products 
        WHERE current_stock <= minimum_stock AND is_active = 1
    ");
    $stmt->execute();
    $stock_stats = $stmt->fetch();
    
    // Recent Manufacturing Orders
    $stmt = $conn->prepare("
        SELECT mo.*, p.product_name, u.full_name as assignee_name
        FROM manufacturing_orders mo
        LEFT JOIN products p ON mo.product_id = p.id
        LEFT JOIN users u ON mo.assignee_id = u.id
        ORDER BY mo.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $mo_stats = $wo_stats = $stock_stats = ['total_orders' => 0];
    $recent_orders = [];
}

$page_title = 'Dashboard';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Welcome Section -->
    <div class="mb-4">
        <h1 class="mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
        <p class="text-muted">Here's what's happening in your manufacturing operations today.</p>
    </div>
    
    <!-- KPI Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($mo_stats['total_orders'] ?? 0); ?></h3>
                <p>Total Manufacturing Orders</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($mo_stats['in_progress_orders'] ?? 0); ?></h3>
                <p>Orders In Progress</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($mo_stats['completed_orders'] ?? 0); ?></h3>
                <p>Completed Orders</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stock_stats['low_stock_count'] ?? 0); ?></h3>
                <p>Low Stock Items</p>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="d-flex gap-2 flex-wrap">
                <a href="../manufacturing-orders/create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Manufacturing Order
                </a>
                <a href="../bom/create.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Create BOM
                </a>
                <a href="../products/create.php" class="btn btn-success">
                    <i class="fas fa-box"></i> Add Product
                </a>
                <a href="../work-centers/index.php" class="btn btn-info">
                    <i class="fas fa-industry"></i> Manage Work Centers
                </a>
            </div>
        </div>
    </div>
    
    <!-- Recent Manufacturing Orders -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Recent Manufacturing Orders</h3>
            <a href="../manufacturing-orders/index.php" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($recent_orders)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No manufacturing orders found.</p>
                    <a href="../manufacturing-orders/create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Your First Order
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Assignee</th>
                                <th>Start Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['mo_number']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    <td><?php echo number_format($order['quantity_to_produce'], 0); ?></td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'planned' => 'badge-secondary',
                                            'in_progress' => 'badge-warning',
                                            'completed' => 'badge-success',
                                            'cancelled' => 'badge-danger'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $status_class[$order['status']] ?? 'badge-secondary'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['assignee_name'] ?? 'Unassigned'); ?></td>
                                    <td><?php echo formatDate($order['scheduled_start_date']); ?></td>
                                    <td>
                                        <a href="../manufacturing-orders/view.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-sm btn-outline" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>