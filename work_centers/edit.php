<?php
/**
 * Edit Work Center
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$center_id = intval($_GET['id'] ?? 0);

if ($center_id <= 0) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = false;

try {
    $conn = getDBConnection();
    
    // Get work center details
    $stmt = $conn->prepare("SELECT * FROM work_centers WHERE id = ?");
    $stmt->execute([$center_id]);
    $center = $stmt->fetch();
    
    if (!$center) {
        header('Location: index.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $center_name = trim($_POST['center_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $capacity = floatval($_POST['capacity'] ?? 0);
    $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($center_name)) {
        $errors[] = 'Work center name is required';
    }
    
    if ($capacity <= 0) {
        $errors[] = 'Capacity must be greater than 0';
    }
    
    if ($hourly_rate < 0) {
        $errors[] = 'Hourly rate cannot be negative';
    }
    
    if (!in_array($status, ['active', 'inactive', 'maintenance'])) {
        $errors[] = 'Invalid status selected';
    }
    
    // Check for duplicate name (excluding current center)
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM work_centers WHERE center_name = ? AND id != ?");
            $stmt->execute([$center_name, $center_id]);
            if ($stmt->fetch()) {
                $errors[] = 'A work center with this name already exists';
            }
        } catch (Exception $e) {
            $errors[] = 'Database error occurred';
            error_log($e->getMessage());
        }
    }
    
    // Check for active work orders if changing to inactive/maintenance
    if (empty($errors) && in_array($status, ['inactive', 'maintenance']) && $center['status'] === 'active') {
        try {
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM work_orders 
                WHERE work_center_id = ? AND status IN ('pending', 'in_progress', 'paused')
            ");
            $stmt->execute([$center_id]);
            $active_orders = $stmt->fetchColumn();
            
            if ($active_orders > 0) {
                $errors[] = "Cannot change status to {$status}: {$active_orders} active work orders found. Complete or reassign them first.";
            }
        } catch (Exception $e) {
            $errors[] = 'Error checking active work orders';
            error_log($e->getMessage());
        }
    }
    
    // Update work center
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("
                UPDATE work_centers 
                SET center_name = ?, description = ?, capacity = ?, hourly_rate = ?, 
                    location = ?, status = ?, notes = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $center_name, $description, $capacity, $hourly_rate,
                $location, $status, $notes, $_SESSION['user_id'], $center_id
            ]);
            
            // Log the update if status changed
            if ($center['status'] !== $status) {
                $stmt = $conn->prepare("
                    INSERT INTO work_center_logs (
                        work_center_id, action, details, created_by
                    ) VALUES (?, ?, ?, ?)
                ");
                
                $details = json_encode([
                    'old_status' => $center['status'],
                    'new_status' => $status,
                    'changed_at' => date('Y-m-d H:i:s')
                ]);
                
                $stmt->execute([
                    $center_id,
                    'status_change',
                    $details,
                    $_SESSION['user_id']
                ]);
            }
            
            // Log general update
            $stmt = $conn->prepare("
                INSERT INTO work_center_logs (
                    work_center_id, action, details, created_by
                ) VALUES (?, ?, ?, ?)
            ");
            
            $details = json_encode([
                'updated_fields' => [
                    'center_name' => $center_name,
                    'capacity' => $capacity,
                    'hourly_rate' => $hourly_rate,
                    'status' => $status
                ],
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            $stmt->execute([
                $center_id,
                'update',
                $details,
                $_SESSION['user_id']
            ]);
            
            $conn->commit();
            $success = true;
            
            // Update the center array for display
            $center['center_name'] = $center_name;
            $center['description'] = $description;
            $center['capacity'] = $capacity;
            $center['hourly_rate'] = $hourly_rate;
            $center['location'] = $location;
            $center['status'] = $status;
            $center['notes'] = $notes;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'Failed to update work center';
            error_log($e->getMessage());
        }
    }
}

$page_title = 'Edit Work Center';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Edit Work Center</h1>
            <p class="text-muted">Update work center information and settings</p>
        </div>
        <div class="d-flex gap-2">
            <a href="view.php?id=<?php echo $center['id']; ?>" class="btn btn-outline">
                <i class="fas fa-eye"></i> View Details
            </a>
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
    
    <!-- Success Message -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> Work center updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
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
    
    <!-- Edit Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Work Center Information</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="editForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="center_name" class="form-label">Work Center Name <span class="text-danger">*</span></label>
                                    <input type="text" id="center_name" name="center_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($center['center_name']); ?>" required>
                                    <div class="form-text">Unique name for this work center</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select id="status" name="status" class="form-select" required>
                                        <option value="active" <?php echo $center['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $center['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="maintenance" <?php echo $center['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($center['description']); ?></textarea>
                            <div class="form-text">Brief description of this work center's purpose</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="capacity" class="form-label">Capacity <span class="text-danger">*</span></label>
                                    <input type="number" id="capacity" name="capacity" class="form-control" 
                                           value="<?php echo $center['capacity']; ?>" 
                                           min="0.01" step="0.01" required>
                                    <div class="form-text">Maximum concurrent work orders</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="hourly_rate" class="form-label">Hourly Rate ($)</label>
                                    <input type="number" id="hourly_rate" name="hourly_rate" class="form-control" 
                                           value="<?php echo $center['hourly_rate']; ?>" 
                                           min="0" step="0.01">
                                    <div class="form-text">Cost per hour of operation</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" id="location" name="location" class="form-control" 
                                           value="<?php echo htmlspecialchars($center['location']); ?>">
                                    <div class="form-text">Physical location or area</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea id="notes" name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($center['notes']); ?></textarea>
                            <div class="form-text">Additional notes or special instructions</div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Work Center
                            </button>
                            <a href="view.php?id=<?php echo $center['id']; ?>" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Current Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Current Status</h3>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <span class="badge badge-<?php 
                            echo match($center['status']) {
                                'active' => 'success',
                                'maintenance' => 'warning',
                                'inactive' => 'secondary',
                                default => 'secondary'
                            };
                        ?> fs-6 mb-3">
                            <?php echo ucfirst($center['status']); ?>
                        </span>
                        
                        <?php
                        try {
                            $stmt = $conn->prepare("
                                SELECT COUNT(*) 
                                FROM work_orders 
                                WHERE work_center_id = ? AND status IN ('pending', 'in_progress', 'paused')
                            ");
                            $stmt->execute([$center_id]);
                            $active_orders = $stmt->fetchColumn();
                            
                            $utilization = 0;
                            if ($center['capacity'] > 0) {
                                $utilization = ($active_orders / $center['capacity']) * 100;
                            }
                        } catch (Exception $e) {
                            $active_orders = 0;
                            $utilization = 0;
                        }
                        ?>
                        
                        <div class="mt-3">
                            <div class="h5"><?php echo $active_orders; ?> / <?php echo number_format($center['capacity']); ?></div>
                            <div class="text-muted small">Active Orders / Capacity</div>
                            
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar <?php 
                                    echo $utilization >= 100 ? 'bg-danger' : ($utilization >= 80 ? 'bg-warning' : 'bg-success'); 
                                ?>" style="width: <?php echo min(100, $utilization); ?>%"></div>
                            </div>
                            <div class="text-muted small mt-1"><?php echo number_format($utilization, 0); ?>% Utilization</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Warning Messages -->
            <?php if ($center['status'] === 'active' && $active_orders > 0): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-exclamation-triangle text-warning me-2 mt-1"></i>
                            <div>
                                <h6 class="mb-1">Active Work Orders</h6>
                                <p class="small text-muted mb-0">
                                    This work center has <?php echo $active_orders; ?> active work orders. 
                                    Changing status to inactive or maintenance will require reassigning these orders.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Update History -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Update History</h3>
                </div>
                <div class="card-body">
                    <div class="small">
                        <div class="mb-2">
                            <strong>Created:</strong><br>
                            <?php echo date('M j, Y g:i A', strtotime($center['created_at'])); ?>
                        </div>
                        <div>
                            <strong>Last Updated:</strong><br>
                            <?php echo date('M j, Y g:i A', strtotime($center['updated_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
document.getElementById('editForm').addEventListener('submit', function(e) {
    const centerName = document.getElementById('center_name').value.trim();
    const capacity = parseFloat(document.getElementById('capacity').value);
    const status = document.getElementById('status').value;
    
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
    
    // Warn about status change if there are active orders
    const activeOrders = {$active_orders};
    const currentStatus = '{$center['status']}';
    
    if (activeOrders > 0 && currentStatus === 'active' && (status === 'inactive' || status === 'maintenance')) {
        if (!confirm(`This work center has \${activeOrders} active work orders. Changing status to \${status} may affect these orders. Continue?`)) {
            e.preventDefault();
            return;
        }
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type=\"submit\"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i> Updating...';
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