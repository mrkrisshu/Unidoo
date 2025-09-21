<?php
/**
 * Create Bill of Materials (BOM)
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$errors = [];
$form_data = [];

// Get products for dropdown
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, product_code, product_name FROM products WHERE is_active = TRUE ORDER BY product_name");
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    // Get materials for BOM
    $stmt = $conn->prepare("SELECT id, product_code, product_name, unit_of_measure FROM products WHERE is_active = TRUE ORDER BY product_name");
    $stmt->execute();
    $materials = $stmt->fetchAll();
    
    // Get work centers for operations
    $stmt = $conn->prepare("SELECT id, center_name FROM work_centers WHERE is_active = TRUE ORDER BY center_name");
    $stmt->execute();
    $work_centers = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("BOM Create - Database Error: " . $e->getMessage());
    $products = [];
    $materials = [];
    $work_centers = [];
    $db_error = "Error loading data: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $form_data = [
        'bom_code' => sanitizeInput($_POST['bom_code'] ?? ''),
        'bom_name' => sanitizeInput($_POST['bom_name'] ?? ''),
        'product_id' => intval($_POST['product_id'] ?? 0),
        'description' => sanitizeInput($_POST['description'] ?? ''),
        'version' => floatval($_POST['version'] ?? 1.0),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    // Validation
    if (empty($form_data['bom_code'])) {
        $errors[] = 'BOM code is required';
    }
    
    if (empty($form_data['bom_name'])) {
        $errors[] = 'BOM name is required';
    }
    
    if ($form_data['product_id'] <= 0) {
        $errors[] = 'Please select a product';
    }
    
    if ($form_data['version'] <= 0) {
        $errors[] = 'Version must be greater than 0';
    }
    
    // Validate materials
    $bom_materials = [];
    if (isset($_POST['materials']) && is_array($_POST['materials'])) {
        foreach ($_POST['materials'] as $index => $material) {
            if (!empty($material['material_id']) && !empty($material['quantity'])) {
                $bom_materials[] = [
                    'material_id' => intval($material['material_id']),
                    'quantity' => floatval($material['quantity']),
                    'unit_cost' => floatval($material['unit_cost'] ?? 0),
                    'notes' => sanitizeInput($material['notes'] ?? '')
                ];
            }
        }
    }
    
    if (empty($bom_materials)) {
        $errors[] = 'At least one material is required';
    }
    
    // Validate operations
    $bom_operations = [];
    if (isset($_POST['operations']) && is_array($_POST['operations'])) {
        foreach ($_POST['operations'] as $index => $operation) {
            if (!empty($operation['operation_name'])) {
                $bom_operations[] = [
                    'operation_name' => sanitizeInput($operation['operation_name']),
                    'work_center_id' => intval($operation['work_center_id'] ?? 0),
                    'operation_sequence' => intval($operation['operation_sequence'] ?? 0),
                    'setup_time_minutes' => floatval($operation['setup_time'] ?? 0),
                    'operation_time_minutes' => floatval($operation['run_time'] ?? 0),
                    'cost_per_hour' => floatval($operation['cost_per_hour'] ?? 0),
                    'description' => sanitizeInput($operation['description'] ?? '')
                ];
            }
        }
    }
    
    // Check for duplicate BOM code
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM bom_header WHERE bom_code = ?");
            $stmt->execute([$form_data['bom_code']]);
            if ($stmt->fetch()) {
                $errors[] = 'BOM code already exists';
            }
        } catch (Exception $e) {
            $errors[] = 'Database error occurred';
            error_log($e->getMessage());
        }
    }
    
    // Insert BOM if no errors
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Insert BOM
            $stmt = $conn->prepare("
                INSERT INTO bom_header (
                    bom_code, bom_name, product_id, description, version, is_active, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $form_data['bom_code'],
                $form_data['bom_name'],
                $form_data['product_id'],
                $form_data['description'],
                $form_data['version'],
                $form_data['is_active'],
                $_SESSION['user_id']
            ]);
            
            $bom_id = $conn->lastInsertId();
            
            // Insert BOM materials
            $stmt = $conn->prepare("
                INSERT INTO bom_materials (bom_id, material_id, quantity, unit_cost, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($bom_materials as $material) {
                $stmt->execute([
                    $bom_id,
                    $material['material_id'],
                    $material['quantity'],
                    $material['unit_cost'],
                    $material['notes']
                ]);
            }
            
            // Insert BOM operations
            if (!empty($bom_operations)) {
                $stmt = $conn->prepare("
                    INSERT INTO bom_operations (
                        bom_id, operation_name, work_center_id, operation_sequence, 
                        setup_time_minutes, operation_time_minutes, cost_per_hour, description
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($bom_operations as $operation) {
                    $stmt->execute([
                        $bom_id,
                        $operation['operation_name'],
                        $operation['work_center_id'] ?: null,
                        $operation['operation_sequence'],
                        $operation['setup_time_minutes'],
                    $operation['operation_time_minutes'],
                        $operation['cost_per_hour'],
                        $operation['description']
                    ]);
                }
            }
            
            $conn->commit();
            $_SESSION['success_message'] = 'BOM created successfully';
            header('Location: index.php');
            exit;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'Failed to create BOM';
            error_log($e->getMessage());
        }
    }
}

$page_title = 'Create BOM';
$additional_css = ['../assets/css/bom-form.css'];
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Create New BOM</h1>
            <p class="text-muted">Define materials and operations for product manufacturing</p>
        </div>
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to BOMs
        </a>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" id="bomForm">
        <!-- Basic Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Basic Information</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="bom_code" class="form-label required">BOM Code</label>
                            <input type="text" id="bom_code" name="bom_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($form_data['bom_code'] ?? ''); ?>" 
                                   placeholder="e.g., BOM-WT-001" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="bom_name" class="form-label required">BOM Name</label>
                            <input type="text" id="bom_name" name="bom_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($form_data['bom_name'] ?? ''); ?>" 
                                   placeholder="e.g., Wooden Table Assembly" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="product_id" class="form-label required">Product</label>
                            <?php if (isset($db_error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($db_error); ?></div>
                            <?php endif; ?>
                            <select id="product_id" name="product_id" class="form-select" required>
                                <option value="">Select Product</option>
                                <?php if (!empty($products)): ?>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['id']; ?>" 
                                                <?php echo ($form_data['product_id'] ?? 0) == $product['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($product['product_code'] . ' - ' . $product['product_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No products available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="version" class="form-label">Version</label>
                            <input type="number" id="version" name="version" class="form-control" 
                                   value="<?php echo $form_data['version'] ?? '1.0'; ?>" 
                                   step="0.1" min="0.1">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" 
                              placeholder="Optional description of the BOM"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" id="is_active" name="is_active" class="form-check-input" 
                               <?php echo ($form_data['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="is_active" class="form-check-label">Active BOM</label>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Materials Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Materials Required</h3>
                <button type="button" class="btn btn-primary btn-sm" onclick="addMaterial()">
                    <i class="fas fa-plus"></i> Add Material
                </button>
            </div>
            <div class="card-body">
                <div id="materialsContainer">
                    <div class="material-row" data-index="0">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Material</label>
                                <select name="materials[0][material_id]" class="form-select material-select" required>
                                    <option value="">Select Material</option>
                                    <?php foreach ($materials as $material): ?>
                                        <option value="<?php echo $material['id']; ?>" data-unit="<?php echo htmlspecialchars($material['unit_of_measure']); ?>">
                                            <?php echo htmlspecialchars($material['product_code'] . ' - ' . $material['product_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="materials[0][quantity]" class="form-control" 
                                       step="0.01" min="0" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Unit</label>
                                <input type="text" class="form-control unit-display" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Unit Cost</label>
                                <input type="number" name="materials[0][unit_cost]" class="form-control" 
                                       step="0.01" min="0">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeMaterial(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-11">
                                <input type="text" name="materials[0][notes]" class="form-control" 
                                       placeholder="Optional notes for this material">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Operations Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Manufacturing Operations</h3>
                <button type="button" class="btn btn-primary btn-sm" onclick="addOperation()">
                    <i class="fas fa-plus"></i> Add Operation
                </button>
            </div>
            <div class="card-body">
                <div id="operationsContainer">
                    <div class="operation-row" data-index="0">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Operation Name</label>
                                <input type="text" name="operations[0][operation_name]" class="form-control" 
                                       placeholder="e.g., Assembly, Painting">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Work Center</label>
                                <select name="operations[0][work_center_id]" class="form-select">
                                    <option value="">Select Work Center</option>
                                    <?php foreach ($work_centers as $wc): ?>
                                        <option value="<?php echo $wc['id']; ?>">
                                            <?php echo htmlspecialchars($wc['center_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Sequence</label>
                                <input type="number" name="operations[0][operation_sequence]" class="form-control" 
                                       min="1" value="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Setup Time (min)</label>
                                <input type="number" name="operations[0][setup_time]" class="form-control" 
                                       step="0.1" min="0">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeOperation(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3">
                                <label class="form-label">Run Time (min)</label>
                                <input type="number" name="operations[0][run_time]" class="form-control" 
                                       step="0.1" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cost per Hour</label>
                                <input type="number" name="operations[0][cost_per_hour]" class="form-control" 
                                       step="0.01" min="0">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Description</label>
                                <input type="text" name="operations[0][description]" class="form-control" 
                                       placeholder="Optional operation description">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="card">
            <div class="card-body">
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create BOM
                    </button>
                    <a href="index.php" class="btn btn-outline">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
$inline_js = "
let materialIndex = 1;
let operationIndex = 1;

function addMaterial() {
    const container = document.getElementById('materialsContainer');
    const template = container.querySelector('.material-row').cloneNode(true);
    
    template.setAttribute('data-index', materialIndex);
    template.querySelectorAll('input, select').forEach(input => {
        const name = input.getAttribute('name');
        if (name) {
            input.setAttribute('name', name.replace(/\[\d+\]/, '[' + materialIndex + ']'));
        }
        input.value = '';
    });
    
    container.appendChild(template);
    materialIndex++;
}

function removeMaterial(button) {
    const container = document.getElementById('materialsContainer');
    if (container.children.length > 1) {
        button.closest('.material-row').remove();
    }
}

function addOperation() {
    const container = document.getElementById('operationsContainer');
    const template = container.querySelector('.operation-row').cloneNode(true);
    
    template.setAttribute('data-index', operationIndex);
    template.querySelectorAll('input, select').forEach(input => {
        const name = input.getAttribute('name');
        if (name) {
            input.setAttribute('name', name.replace(/\[\d+\]/, '[' + operationIndex + ']'));
        }
        input.value = '';
    });
    
    // Set sequence number
    const sequenceInput = template.querySelector('input[name*=\"operation_sequence\"]');
    if (sequenceInput) {
        sequenceInput.value = operationIndex + 1;
    }
    
    container.appendChild(template);
    operationIndex++;
}

function removeOperation(button) {
    const container = document.getElementById('operationsContainer');
    if (container.children.length > 1) {
        button.closest('.operation-row').remove();
    }
}

// Update unit display when material is selected
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('material-select')) {
        const selectedOption = e.target.options[e.target.selectedIndex];
        const unit = selectedOption.getAttribute('data-unit');
        const unitDisplay = e.target.closest('.material-row').querySelector('.unit-display');
        unitDisplay.value = unit || '';
    }
});

document.getElementById('bom_code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
";

include '../includes/footer.php';
?>