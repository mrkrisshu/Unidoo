<?php
/**
 * Create Work Center
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $center_name = trim($_POST['center_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $capacity_per_hour = floatval($_POST['capacity'] ?? 0);
    $hourly_cost = floatval($_POST['hourly_rate'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($center_name)) {
        $errors[] = 'Work center name is required';
    }
    
    if ($capacity_per_hour <= 0) {
        $errors[] = 'Capacity must be greater than 0';
    }
    
    if ($hourly_cost < 0) {
        $errors[] = 'Hourly rate cannot be negative';
    }
    
    if (!in_array($status, ['active', 'inactive', 'maintenance'])) {
        $errors[] = 'Invalid status selected';
    }
    
    // Check for duplicate name
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id FROM work_centers WHERE center_name = ?");
            $stmt->execute([$center_name]);
            if ($stmt->fetch()) {
                $errors[] = 'A work center with this name already exists';
            }
        } catch (Exception $e) {
            $errors[] = 'Database error occurred';
            error_log($e->getMessage());
        }
    }
    
    // Create work center
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            $conn->beginTransaction();
            
            // Generate center code
            $center_code = strtoupper(substr($center_name, 0, 3)) . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            
            // Convert status to is_active boolean
            $is_active = ($status === 'active') ? 1 : 0;
            
            $stmt = $conn->prepare("
                INSERT INTO work_centers (
                    center_code, center_name, description, hourly_cost, 
                    capacity_per_hour, is_active
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $center_code, $center_name, $description, $hourly_cost,
                $capacity_per_hour, $is_active
            ]);
            
            $center_id = $conn->lastInsertId();
            
            $conn->commit();
            $success = true;
            
            // Redirect to index page with success message
            header("Location: index.php?created=1");
            exit;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'Failed to create work center: ' . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}

$page_title = 'Create Work Center';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Create Work Center</h1>
            <p class="text-muted">Add a new manufacturing location or resource</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Work Centers
        </a>
    </div>
    
    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- Create Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Work Center Information</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="createForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="center_name" class="form-label">Work Center Name <span class="text-danger">*</span></label>
                                    <input type="text" id="center_name" name="center_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['center_name'] ?? ''); ?>" required>
                                    <div class="form-text">Unique name for this work center</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select id="status" name="status" class="form-select" required>
                                        <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="maintenance" <?php echo ($_POST['status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="form-text">Brief description of this work center's purpose</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="capacity" class="form-label">Capacity <span class="text-danger">*</span></label>
                                    <input type="number" id="capacity" name="capacity" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['capacity'] ?? ''); ?>" 
                                           min="0.01" step="0.01" required>
                                    <div class="form-text">Maximum concurrent work orders</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="hourly_rate" class="form-label">Hourly Rate ($)</label>
                                    <input type="number" id="hourly_rate" name="hourly_rate" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['hourly_rate'] ?? ''); ?>" 
                                           min="0" step="0.01">
                                    <div class="form-text">Cost per hour of operation</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Work Center
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Help Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-info-circle text-info"></i> Work Center Guidelines
                    </h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Naming Convention</h6>
                        <p class="small text-muted">Use descriptive names like "Assembly Line 1", "Quality Control", or "Packaging Station".</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Capacity Planning</h6>
                        <p class="small text-muted">Set capacity based on maximum concurrent work orders this center can handle efficiently.</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Hourly Rate</h6>
                        <p class="small text-muted">Include equipment depreciation, utilities, and overhead costs in the hourly rate calculation.</p>
                    </div>
                    
                    <div class="mb-0">
                        <h6>Status Management</h6>
                        <ul class="small text-muted mb-0">
                            <li><strong>Active:</strong> Available for work orders</li>
                            <li><strong>Maintenance:</strong> Temporarily unavailable</li>
                            <li><strong>Inactive:</strong> Not in use</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-bar text-primary"></i> System Overview
                    </h3>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $conn = getDBConnection();
                        $stmt = $conn->prepare("
                            SELECT 
                                COUNT(*) as total_centers,
                                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_centers,
                                AVG(capacity) as avg_capacity,
                                AVG(hourly_rate) as avg_rate
                            FROM work_centers
                        ");
                        $stmt->execute();
                        $overview = $stmt->fetch();
                    } catch (Exception $e) {
                        $overview = ['total_centers' => 0, 'active_centers' => 0, 'avg_capacity' => 0, 'avg_rate' => 0];
                    }
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="h5 text-primary"><?php echo number_format($overview['total_centers']); ?></div>
                            <div class="small text-muted">Total Centers</div>
                        </div>
                        <div class="col-6">
                            <div class="h5 text-success"><?php echo number_format($overview['active_centers']); ?></div>
                            <div class="small text-muted">Active Centers</div>
                        </div>
                        <div class="col-6 mt-3">
                            <div class="h5 text-info"><?php echo number_format($overview['avg_capacity'], 1); ?></div>
                            <div class="small text-muted">Avg Capacity</div>
                        </div>
                        <div class="col-6 mt-3">
                            <div class="h5 text-warning">$<?php echo number_format($overview['avg_rate'], 2); ?></div>
                            <div class="small text-muted">Avg Rate/Hr</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
document.getElementById('createForm').addEventListener('submit', function(e) {
    const centerName = document.getElementById('center_name').value.trim();
    const capacity = parseFloat(document.getElementById('capacity').value);
    
    if (!centerName) {
        e.preventDefault();
        showAlert('Work center name is required', 'error');
        return;
    }
    
    if (capacity <= 0) {
        e.preventDefault();
        showAlert('Capacity must be greater than 0', 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type=\"submit\"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i> Creating...';
    submitBtn.disabled = true;
    
    // Re-enable button after 10 seconds as fallback
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 10000);
});

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