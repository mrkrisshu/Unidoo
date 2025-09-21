<?php
/**
 * Create Manufacturing Order
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDBConnection();
        
        // Validate inputs
        $product_id = intval($_POST['product_id'] ?? 0);
        $bom_id = intval($_POST['bom_id'] ?? 0);
        $quantity = floatval($_POST['quantity'] ?? 0);
        $priority = sanitizeInput($_POST['priority'] ?? 'medium');
        $planned_start_date = sanitizeInput($_POST['planned_start_date'] ?? '');
        $planned_end_date = sanitizeInput($_POST['planned_end_date'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        
        // Validation
        if ($product_id <= 0) {
            $errors[] = 'Please select a product';
        }
        
        if ($quantity <= 0) {
            $errors[] = 'Quantity must be greater than 0';
        }
        
        if (empty($planned_start_date)) {
            $errors[] = 'Planned start date is required';
        }
        
        if (empty($planned_end_date)) {
            $errors[] = 'Planned end date is required';
        }
        
        if (!empty($planned_start_date) && !empty($planned_end_date) && 
            strtotime($planned_end_date) <= strtotime($planned_start_date)) {
            $errors[] = 'Planned end date must be after start date';
        }
        
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
            $errors[] = 'Invalid priority selected';
        }
        
        // Verify product exists
        if ($product_id > 0) {
            $stmt = $conn->prepare("SELECT id, product_name FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            if (!$stmt->fetch()) {
                $errors[] = 'Selected product does not exist';
            }
        }
        
        // Verify BOM exists if selected
        if ($bom_id > 0) {
            $stmt = $conn->prepare("SELECT id FROM bom_header WHERE id = ? AND product_id = ?");
            $stmt->execute([$bom_id, $product_id]);
            if (!$stmt->fetch()) {
                $errors[] = 'Selected BOM does not exist or does not match the product';
            }
        }
        
        if (empty($errors)) {
            // Generate MO number
            $mo_number = generateMONumber($conn);
            
            // Insert manufacturing order
            $stmt = $conn->prepare("
                INSERT INTO manufacturing_orders (
                    mo_number, product_id, bom_id, quantity_to_produce, priority, 
                    scheduled_start_date, scheduled_end_date, notes, 
                    status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'planned', ?, NOW())
            ");
            
            $stmt->execute([
                $mo_number,
                $product_id,
                $bom_id > 0 ? $bom_id : null,
                $quantity,
                $priority,
                $planned_start_date,
                $planned_end_date,
                $description,
                $_SESSION['user_id']
            ]);
            
            $mo_id = $conn->lastInsertId();
            
            $success_message = "Manufacturing Order $mo_number created successfully!";
            
            // Redirect to view page after 2 seconds
            header("refresh:2;url=view.php?id=$mo_id");
        }
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        $errors[] = 'An error occurred while creating the manufacturing order';
    }
}

// Get products for dropdown
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT id, product_code, product_name, unit_of_measure
        FROM products 
        WHERE is_active = 1
        ORDER BY product_name
    ");
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    error_log($e->getMessage());
    $products = [];
}

function generateMONumber($conn) {
    $prefix = 'MO';
    $year = date('Y');
    $month = date('m');
    
    // Get the last MO number for this month
    $stmt = $conn->prepare("
        SELECT mo_number 
        FROM manufacturing_orders 
        WHERE mo_number LIKE ? 
        ORDER BY mo_number DESC 
        LIMIT 1
    ");
    $stmt->execute(["$prefix$year$month%"]);
    $last_mo = $stmt->fetch();
    
    if ($last_mo) {
        $last_number = intval(substr($last_mo['mo_number'], -4));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    return $prefix . $year . $month . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}

$page_title = 'Create Manufacturing Order';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Create Manufacturing Order</h1>
            <p class="text-muted">Create a new production order for manufacturing</p>
        </div>
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Manufacturing Orders
        </a>
    </div>
    
    <!-- Success Message -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <form method="POST" id="moForm">
        <div class="row">
            <!-- Main Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Manufacturing Order Details</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                                    <select name="product_id" id="product_id" class="form-select" required>
                                        <option value="">Select Product</option>
                                        <?php foreach ($products as $product): ?>
                                            <option value="<?php echo $product['id']; ?>" 
                                                    data-unit="<?php echo htmlspecialchars($product['unit_of_measure']); ?>"
                                                    <?php echo (isset($_POST['product_id']) && $_POST['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($product['product_code'] . ' - ' . $product['product_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bom_id" class="form-label">Bill of Materials</label>
                                    <select name="bom_id" id="bom_id" class="form-select">
                                        <option value="">Select BOM (Optional)</option>
                                    </select>
                                    <div class="form-text">BOM will auto-populate based on selected product</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="quantity" id="quantity" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>" 
                                               min="0.01" step="0.01" required>
                                        <span class="input-group-text" id="unit-display">Units</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                                    <select name="priority" id="priority" class="form-select" required>
                                        <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'low') ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'high') ? 'selected' : ''; ?>>High</option>
                                        <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Estimated Cost</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" id="estimated_cost" class="form-control" readonly 
                                               placeholder="Auto-calculated">
                                    </div>
                                    <div class="form-text">Based on BOM and quantity</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="planned_start_date" class="form-label">Planned Start Date <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="planned_start_date" id="planned_start_date" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['planned_start_date'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="planned_end_date" class="form-label">Planned End Date <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="planned_end_date" id="planned_end_date" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['planned_end_date'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3" 
                                      placeholder="Additional notes or special instructions..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- BOM Preview -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">BOM Preview</h3>
                    </div>
                    <div class="card-body" id="bom-preview">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-list-alt fa-2x mb-2"></i>
                            <p>Select a product to view BOM details</p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Actions</h3>
                    </div>
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-save"></i> Create Manufacturing Order
                        </button>
                        <button type="button" class="btn btn-outline w-100 mb-2" onclick="saveDraft()">
                            <i class="fas fa-file-alt"></i> Save as Draft
                        </button>
                        <a href="index.php" class="btn btn-secondary w-100">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
                
                <!-- Tips -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Tips</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-lightbulb text-warning"></i>
                                <small>Select a product with an active BOM for automatic material calculation</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-clock text-info"></i>
                                <small>Plan realistic start and end dates considering resource availability</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-exclamation-triangle text-danger"></i>
                                <small>High priority orders will be processed first in the queue</small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
$inline_js = "
document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('product_id');
    const bomSelect = document.getElementById('bom_id');
    const quantityInput = document.getElementById('quantity');
    const unitDisplay = document.getElementById('unit-display');
    const estimatedCostInput = document.getElementById('estimated_cost');
    const bomPreview = document.getElementById('bom-preview');
    
    // Set default dates
    const now = new Date();
    const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);
    const nextWeek = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
    
    if (!document.getElementById('planned_start_date').value) {
        document.getElementById('planned_start_date').value = tomorrow.toISOString().slice(0, 16);
    }
    if (!document.getElementById('planned_end_date').value) {
        document.getElementById('planned_end_date').value = nextWeek.toISOString().slice(0, 16);
    }
    
    productSelect.addEventListener('change', function() {
        const productId = this.value;
        const selectedOption = this.options[this.selectedIndex];
        const unit = selectedOption.dataset.unit || 'Units';
        
        unitDisplay.textContent = unit;
        
        if (productId) {
            loadBOMs(productId);
            loadBOMPreview(productId);
        } else {
            bomSelect.innerHTML = '<option value=\"\">Select BOM (Optional)</option>';
            bomPreview.innerHTML = `
                <div class=\"text-center text-muted py-4\">
                    <i class=\"fas fa-list-alt fa-2x mb-2\"></i>
                    <p>Select a product to view BOM details</p>
                </div>
            `;
            estimatedCostInput.value = '';
        }
    });
    
    bomSelect.addEventListener('change', function() {
        calculateEstimatedCost();
    });
    
    quantityInput.addEventListener('input', function() {
        calculateEstimatedCost();
    });
    
    function loadBOMs(productId) {
        fetch('../api/get_boms.php?product_id=' + productId)
            .then(response => response.json())
            .then(data => {
                bomSelect.innerHTML = '<option value=\"\">Select BOM (Optional)</option>';
                if (data.success && data.boms.length > 0) {
                    data.boms.forEach(bom => {
                        const option = document.createElement('option');
                        option.value = bom.id;
                        option.textContent = bom.bom_code + ' - ' + bom.bom_name;
                        bomSelect.appendChild(option);
                    });
                    
                    // Auto-select first BOM if only one exists
                    if (data.boms.length === 1) {
                        bomSelect.value = data.boms[0].id;
                        calculateEstimatedCost();
                    }
                }
            })
            .catch(error => {
                console.error('Error loading BOMs:', error);
            });
    }
    
    function loadBOMPreview(productId) {
        bomPreview.innerHTML = '<div class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i> Loading...</div>';
        
        fetch('../api/get_bom_preview.php?product_id=' + productId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.bom) {
                    const bom = data.bom;
                    let html = `
                        <div class=\"mb-3\">
                            <h6 class=\"fw-bold\">\${bom.bom_code}</h6>
                            <p class=\"text-muted small mb-2\">\${bom.bom_name}</p>
                        </div>
                    `;
                    
                    if (data.materials && data.materials.length > 0) {
                        html += `
                            <div class=\"mb-3\">
                                <h6 class=\"text-primary\">Materials (\${data.materials.length})</h6>
                                <div class=\"table-responsive\">
                                    <table class=\"table table-sm\">
                        `;
                        data.materials.forEach(material => {
                            html += `
                                <tr>
                                    <td class=\"small\">\${material.material_name}</td>
                                    <td class=\"small text-end\">\${material.quantity} \${material.unit}</td>
                                </tr>
                            `;
                        });
                        html += `
                                    </table>
                                </div>
                            </div>
                        `;
                    }
                    
                    if (data.operations && data.operations.length > 0) {
                        html += `
                            <div class=\"mb-3\">
                                <h6 class=\"text-success\">Operations (\${data.operations.length})</h6>
                                <div class=\"list-group list-group-flush\">
                        `;
                        data.operations.forEach(operation => {
                            html += `
                                <div class=\"list-group-item px-0 py-2\">
                                    <div class=\"d-flex justify-content-between\">
                                        <small class=\"fw-medium\">\${operation.operation_name}</small>
                                        <small class=\"text-muted\">\${operation.duration} min</small>
                                    </div>
                                </div>
                            `;
                        });
                        html += `
                                </div>
                            </div>
                        `;
                    }
                    
                    if (bom.total_cost) {
                        html += `
                            <div class=\"border-top pt-2\">
                                <div class=\"d-flex justify-content-between\">
                                    <span class=\"fw-bold\">Total Cost:</span>
                                    <span class=\"fw-bold text-primary\">$\${parseFloat(bom.total_cost).toFixed(2)}</span>
                                </div>
                            </div>
                        `;
                    }
                    
                    bomPreview.innerHTML = html;
                } else {
                    bomPreview.innerHTML = `
                        <div class=\"text-center text-muted py-4\">
                            <i class=\"fas fa-info-circle fa-2x mb-2\"></i>
                            <p>No BOM found for this product</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading BOM preview:', error);
                bomPreview.innerHTML = `
                    <div class=\"text-center text-danger py-4\">
                        <i class=\"fas fa-exclamation-triangle fa-2x mb-2\"></i>
                        <p>Error loading BOM preview</p>
                    </div>
                `;
            });
    }
    
    function calculateEstimatedCost() {
        const bomId = bomSelect.value;
        const quantity = parseFloat(quantityInput.value) || 0;
        
        if (bomId && quantity > 0) {
            fetch('../api/calculate_mo_cost.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    bom_id: bomId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    estimatedCostInput.value = parseFloat(data.total_cost).toFixed(2);
                } else {
                    estimatedCostInput.value = '';
                }
            })
            .catch(error => {
                console.error('Error calculating cost:', error);
                estimatedCostInput.value = '';
            });
        } else {
            estimatedCostInput.value = '';
        }
    }
});

function saveDraft() {
    const form = document.getElementById('moForm');
    const formData = new FormData(form);
    formData.append('save_as_draft', '1');
    
    fetch('create.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Handle draft save response
        alert('Draft saved successfully!');
    })
    .catch(error => {
        console.error('Error saving draft:', error);
        alert('Error saving draft');
    });
}
";

include '../includes/footer.php';
?>