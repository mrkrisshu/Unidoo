<?php
/**
 * Stock Movements View
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$material_id = intval($_GET['material_id'] ?? 0);
$location = $_GET['location'] ?? '';
$limit = intval($_GET['limit'] ?? 50);

if (!$material_id) {
    echo '<div class="alert alert-danger">Material ID is required</div>';
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get material info
    $stmt = $conn->prepare("
        SELECT material_name, material_code, unit
        FROM materials
        WHERE id = ?
    ");
    $stmt->execute([$material_id]);
    $material = $stmt->fetch();
    
    if (!$material) {
        echo '<div class="alert alert-danger">Material not found</div>';
        exit;
    }
    
    // Build WHERE clause for location filter
    $where_location = '';
    $params = [$material_id];
    
    if ($location) {
        $where_location = 'AND sm.location = ?';
        $params[] = $location;
    }
    
    $params[] = $limit;
    
    // Get stock movements
    $stmt = $conn->prepare("
        SELECT sm.*, 
               u.username as created_by_name,
               wo.wo_number,
               mo.mo_number,
               CASE 
                   WHEN sm.work_order_id IS NOT NULL THEN 'Work Order'
                   WHEN sm.mo_id IS NOT NULL THEN 'Manufacturing Order'
                   WHEN sm.reference_type = 'manual_adjustment' THEN 'Manual Adjustment'
                   WHEN sm.reference_type = 'stock_reservation' THEN 'Stock Reservation'
                   WHEN sm.reference_type = 'stock_release' THEN 'Stock Release'
                   ELSE 'Other'
               END as movement_source
        FROM stock_movements sm
        LEFT JOIN users u ON sm.created_by = u.id
        LEFT JOIN work_orders wo ON sm.work_order_id = wo.id
        LEFT JOIN manufacturing_orders mo ON sm.mo_id = mo.id
        WHERE sm.material_id = ? $where_location
        ORDER BY sm.created_at DESC
        LIMIT ?
    ");
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
    
    // Get current stock
    $stmt = $conn->prepare("
        SELECT current_stock, location
        FROM stock_ledger
        WHERE material_id = ?" . ($location ? " AND location = ?" : "")
    );
    $location_params = [$material_id];
    if ($location) {
        $location_params[] = $location;
    }
    $stmt->execute($location_params);
    $stock_entries = $stmt->fetchAll();
    
    $total_stock = array_sum(array_column($stock_entries, 'current_stock'));
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo '<div class="alert alert-danger">Error loading movements: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="mb-3">
    <h6><?php echo htmlspecialchars($material['material_name']); ?></h6>
    <small class="text-muted">
        Code: <?php echo htmlspecialchars($material['material_code']); ?> | 
        Current Stock: <?php echo number_format($total_stock, 2) . ' ' . $material['unit']; ?>
        <?php if ($location): ?>
            | Location: <?php echo htmlspecialchars($location); ?>
        <?php endif; ?>
    </small>
</div>

<?php if (!empty($movements)): ?>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Location</th>
                    <th>Source</th>
                    <th>Reference</th>
                    <th>Notes</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $running_balance = $total_stock;
                foreach ($movements as $movement): 
                    // Calculate running balance (working backwards)
                    if ($movement['movement_type'] === 'in') {
                        $running_balance -= $movement['quantity'];
                    } else {
                        $running_balance += $movement['quantity'];
                    }
                ?>
                    <tr>
                        <td>
                            <div><?php echo date('M j, Y', strtotime($movement['created_at'])); ?></div>
                            <small class="text-muted"><?php echo date('g:i A', strtotime($movement['created_at'])); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $movement['movement_type'] === 'in' ? 'success' : 'danger'; ?>">
                                <?php echo strtoupper($movement['movement_type']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?php echo $movement['movement_type'] === 'in' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($movement['movement_type'] === 'in' ? '+' : '-') . number_format($movement['quantity'], 2); ?>
                            </span>
                            <small class="text-muted"><?php echo $material['unit']; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($movement['location'] ?: 'Default'); ?></td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($movement['movement_source']); ?></small>
                        </td>
                        <td>
                            <?php if ($movement['wo_number']): ?>
                                <a href="../work_orders/view.php?id=<?php echo $movement['work_order_id']; ?>" class="text-primary small">
                                    <?php echo htmlspecialchars($movement['wo_number']); ?>
                                </a>
                            <?php elseif ($movement['mo_number']): ?>
                                <a href="../manufacturing_orders/view.php?id=<?php echo $movement['mo_id']; ?>" class="text-primary small">
                                    <?php echo htmlspecialchars($movement['mo_number']); ?>
                                </a>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($movement['notes']); ?></small>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($movement['created_by_name']); ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (count($movements) >= $limit): ?>
        <div class="text-center mt-3">
            <small class="text-muted">Showing last <?php echo $limit; ?> movements</small>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="text-center py-4">
        <i class="fas fa-history fa-2x text-muted mb-2"></i>
        <p class="text-muted">No stock movements found for this material</p>
    </div>
<?php endif; ?>

<!-- Stock Summary by Location -->
<?php if (count($stock_entries) > 1): ?>
    <div class="mt-4">
        <h6>Stock by Location</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Current Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stock_entries as $entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($entry['location'] ?: 'Default'); ?></td>
                            <td><?php echo number_format($entry['current_stock'], 2) . ' ' . $material['unit']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline" 
                                        onclick="viewLocationMovements('<?php echo htmlspecialchars($entry['location']); ?>')">
                                    View Movements
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
function viewLocationMovements(location) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('location', location);
    
    fetch(currentUrl.toString())
    .then(response => response.text())
    .then(html => {
        document.getElementById('movementsContent').innerHTML = html;
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('movementsContent').innerHTML = '<div class="alert alert-danger">Failed to load movements</div>';
    });
}
</script>