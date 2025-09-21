<?php
/**
 * View Bill of Materials (BOM) Details
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$bom_id = intval($_GET['id'] ?? 0);

if ($bom_id <= 0) {
    $_SESSION['error_message'] = 'Invalid BOM ID';
    header('Location: index.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get BOM details with product information
    $stmt = $conn->prepare("
        SELECT b.*, p.product_code, p.product_name, p.unit as product_unit,
               u.username as created_by_name
        FROM bom_header b
        LEFT JOIN products p ON b.product_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bom_id]);
    $bom = $stmt->fetch();
    
    if (!$bom) {
        $_SESSION['error_message'] = 'BOM not found';
        header('Location: index.php');
        exit;
    }
    
    // Get BOM materials with product details
    $stmt = $conn->prepare("
        SELECT bm.*, p.product_code, p.product_name, p.unit,
               (bm.quantity * bm.unit_cost) as total_cost
        FROM bom_materials bm
        LEFT JOIN products p ON bm.material_id = p.id
        WHERE bm.bom_id = ?
        ORDER BY p.product_name
    ");
    $stmt->execute([$bom_id]);
    $bom_materials = $stmt->fetchAll();
    
    // Get BOM operations with work center details
    $stmt = $conn->prepare("
        SELECT bo.*, wc.work_center_name, wc.location,
               (bo.setup_time_minutes + bo.operation_time_minutes) as total_time,
        ((bo.setup_time_minutes + bo.operation_time_minutes) / 60 * bo.cost_per_hour) as operation_cost
        FROM bom_operations bo
        LEFT JOIN work_centers wc ON bo.work_center_id = wc.id
        WHERE bo.bom_id = ?
        ORDER BY bo.operation_sequence, bo.id
    ");
    $stmt->execute([$bom_id]);
    $bom_operations = $stmt->fetchAll();
    
    // Calculate totals
    $total_material_cost = array_sum(array_column($bom_materials, 'total_cost'));
    $total_operation_cost = array_sum(array_column($bom_operations, 'operation_cost'));
    $total_bom_cost = $total_material_cost + $total_operation_cost;
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred';
    header('Location: index.php');
    exit;
}

$page_title = 'View BOM - ' . $bom['bom_name'];
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1"><?php echo htmlspecialchars($bom['bom_name']); ?></h1>
            <p class="text-muted">BOM Code: <?php echo htmlspecialchars($bom['bom_code']); ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="edit.php?id=<?php echo $bom_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit BOM
            </a>
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to BOMs
            </a>
        </div>
    </div>
    
    <!-- BOM Information -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">BOM Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <label class="info-label">BOM Code:</label>
                                <span class="info-value"><?php echo htmlspecialchars($bom['bom_code']); ?></span>
                            </div>
                            <div class="info-group">
                                <label class="info-label">BOM Name:</label>
                                <span class="info-value"><?php echo htmlspecialchars($bom['bom_name']); ?></span>
                            </div>
                            <div class="info-group">
                                <label class="info-label">Product:</label>
                                <span class="info-value">
                                    <?php echo htmlspecialchars($bom['product_code'] . ' - ' . $bom['product_name']); ?>
                                </span>
                            </div>
                            <div class="info-group">
                                <label class="info-label">Version:</label>
                                <span class="info-value"><?php echo $bom['version']; ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <label class="info-label">Status:</label>
                                <span class="badge <?php echo $bom['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $bom['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div class="info-group">
                                <label class="info-label">Created By:</label>
                                <span class="info-value"><?php echo htmlspecialchars($bom['created_by_name'] ?? 'Unknown'); ?></span>
                            </div>
                            <div class="info-group">
                                <label class="info-label">Created Date:</label>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($bom['created_at'])); ?></span>
                            </div>
                            <div class="info-group">
                                <label class="info-label">Last Updated:</label>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($bom['updated_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($bom['description'])): ?>
                        <div class="info-group mt-3">
                            <label class="info-label">Description:</label>
                            <p class="info-value"><?php echo nl2br(htmlspecialchars($bom['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Cost Summary</h3>
                </div>
                <div class="card-body">
                    <div class="cost-summary">
                        <div class="cost-item">
                            <span>Material Cost:</span>
                            <span class="cost-value">$<?php echo number_format($total_material_cost, 2); ?></span>
                        </div>
                        <div class="cost-item">
                            <span>Operation Cost:</span>
                            <span class="cost-value">$<?php echo number_format($total_operation_cost, 2); ?></span>
                        </div>
                        <div class="cost-item total-cost">
                            <span>Total BOM Cost:</span>
                            <span class="cost-value">$<?php echo number_format($total_bom_cost, 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Cost calculated based on current material prices and operation rates
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Materials Section -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Required Materials (<?php echo count($bom_materials); ?>)</h3>
            <button class="btn btn-outline btn-sm" onclick="exportMaterials()">
                <i class="fas fa-download"></i> Export Materials
            </button>
        </div>
        <div class="card-body">
            <?php if (!empty($bom_materials)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Material Code</th>
                                <th>Material Name</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Unit Cost</th>
                                <th>Total Cost</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bom_materials as $material): ?>
                                <tr>
                                    <td>
                                        <span class="fw-medium"><?php echo htmlspecialchars($material['product_code']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($material['product_name']); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo number_format($material['quantity'], 2); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($material['unit']); ?></td>
                                    <td>$<?php echo number_format($material['unit_cost'], 2); ?></td>
                                    <td>
                                        <span class="fw-medium">$<?php echo number_format($material['total_cost'], 2); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($material['notes'])): ?>
                                            <span class="text-muted" title="<?php echo htmlspecialchars($material['notes']); ?>">
                                                <i class="fas fa-sticky-note"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <td colspan="5" class="text-end fw-medium">Total Material Cost:</td>
                                <td class="fw-bold">$<?php echo number_format($total_material_cost, 2); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-boxes"></i>
                    <h4>No Materials Defined</h4>
                    <p>This BOM doesn't have any materials defined yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Operations Section -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Manufacturing Operations (<?php echo count($bom_operations); ?>)</h3>
            <button class="btn btn-outline btn-sm" onclick="exportOperations()">
                <i class="fas fa-download"></i> Export Operations
            </button>
        </div>
        <div class="card-body">
            <?php if (!empty($bom_operations)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Seq</th>
                                <th>Operation</th>
                                <th>Work Center</th>
                                <th>Setup Time</th>
                                <th>Run Time</th>
                                <th>Total Time</th>
                                <th>Cost/Hour</th>
                                <th>Operation Cost</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bom_operations as $operation): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-primary"><?php echo $operation['operation_sequence']; ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-medium"><?php echo htmlspecialchars($operation['operation_name']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($operation['work_center_name']): ?>
                                            <span class="text-primary"><?php echo htmlspecialchars($operation['work_center_name']); ?></span>
                                            <?php if ($operation['location']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($operation['location']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($operation['setup_time_minutes'], 1); ?> min</td>
                                        <td><?php echo number_format($operation['operation_time_minutes'], 1); ?> min</td>
                                    <td>
                                        <span class="fw-medium"><?php echo number_format($operation['total_time'], 1); ?> min</span>
                                    </td>
                                    <td>$<?php echo number_format($operation['cost_per_hour'], 2); ?></td>
                                    <td>
                                        <span class="fw-medium">$<?php echo number_format($operation['operation_cost'], 2); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($operation['description'])): ?>
                                            <span class="text-muted" title="<?php echo htmlspecialchars($operation['description']); ?>">
                                                <i class="fas fa-info-circle"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <td colspan="7" class="text-end fw-medium">Total Operation Cost:</td>
                                <td class="fw-bold">$<?php echo number_format($total_operation_cost, 2); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-cogs"></i>
                    <h4>No Operations Defined</h4>
                    <p>This BOM doesn't have any manufacturing operations defined yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.info-group {
    margin-bottom: 1rem;
}

.info-label {
    font-weight: 600;
    color: var(--text-muted);
    display: block;
    margin-bottom: 0.25rem;
}

.info-value {
    color: var(--text-color);
    font-size: 1rem;
}

.cost-summary {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 1rem;
}

.cost-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
}

.cost-item:last-child {
    margin-bottom: 0;
}

.cost-item.total-cost {
    border-top: 2px solid var(--primary-color);
    padding-top: 0.75rem;
    font-weight: 600;
    font-size: 1.1rem;
}

.cost-value {
    font-weight: 600;
    color: var(--primary-color);
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h4 {
    margin-bottom: 0.5rem;
}

.fw-medium {
    font-weight: 500;
}

.fw-bold {
    font-weight: 700;
}
</style>

<?php
$inline_js = "
function exportMaterials() {
    // Create CSV content for materials
    const materials = " . json_encode($bom_materials) . ";
    let csvContent = 'Material Code,Material Name,Quantity,Unit,Unit Cost,Total Cost,Notes\\n';
    
    materials.forEach(material => {
        csvContent += `\"\${material.product_code}\",\"\${material.product_name}\",\${material.quantity},\"\${material.unit}\",\${material.unit_cost},\${material.total_cost},\"\${material.notes || ''}\"\n`;
    });
    
    downloadCSV(csvContent, 'bom_materials_" . $bom['bom_code'] . ".csv');
}

function exportOperations() {
    // Create CSV content for operations
    const operations = " . json_encode($bom_operations) . ";
    let csvContent = 'Sequence,Operation,Work Center,Setup Time,Run Time,Total Time,Cost per Hour,Operation Cost,Description\\n';
    
    operations.forEach(operation => {
        csvContent += `\${operation.operation_sequence},\"\${operation.operation_name}\",\"\${operation.work_center_name || ''}\",\${operation.setup_time_minutes},\${operation.operation_time_minutes},\${operation.total_time},\${operation.cost_per_hour},\${operation.operation_cost},\"\${operation.description || ''}\"\n`;
    });
    
    downloadCSV(csvContent, 'bom_operations_" . $bom['bom_code'] . ".csv');
}

function downloadCSV(csvContent, filename) {
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}
";

include '../includes/footer.php';
?>