<?php
/**
 * Create Product
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$errors = [];
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $form_data = [
        'product_code' => sanitizeInput($_POST['product_code'] ?? ''),
        'product_name' => sanitizeInput($_POST['product_name'] ?? ''),
        'description' => sanitizeInput($_POST['description'] ?? ''),
        'product_type' => sanitizeInput($_POST['product_type'] ?? ''),
        'unit_of_measure' => sanitizeInput($_POST['unit_of_measure'] ?? ''),
        'unit_cost' => floatval($_POST['unit_cost'] ?? 0),
        'current_stock' => floatval($_POST['current_stock'] ?? 0),
        'minimum_stock' => floatval($_POST['minimum_stock'] ?? 0),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    // Validation
    if (empty($form_data['product_code'])) {
        $errors[] = 'Product code is required';
    }
    
    if (empty($form_data['product_name'])) {
        $errors[] = 'Product name is required';
    }
    
    if (empty($form_data['unit_of_measure'])) {
        $errors[] = 'Unit of measure is required';
    }
    
    if (empty($form_data['product_type'])) {
        $errors[] = 'Product type is required';
    }
    
    if ($form_data['unit_cost'] < 0) {
        $errors[] = 'Unit cost cannot be negative';
    }
    
    if ($form_data['current_stock'] < 0) {
        $errors[] = 'Current stock cannot be negative';
    }
    
    if ($form_data['minimum_stock'] < 0) {
        $errors[] = 'Minimum stock cannot be negative';
    }
    
    // Check for duplicate product code
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id FROM products WHERE product_code = ?");
            $stmt->execute([$form_data['product_code']]);
            if ($stmt->fetch()) {
                $errors[] = 'Product code already exists';
            }
        } catch (Exception $e) {
            $errors[] = 'Database error occurred';
            error_log($e->getMessage());
        }
    }
    
    // Insert product if no errors
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO products (
                    product_code, product_name, description, product_type, unit_of_measure, 
                    unit_cost, current_stock, minimum_stock, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $form_data['product_code'],
                $form_data['product_name'],
                $form_data['description'],
                $form_data['product_type'],
                $form_data['unit_of_measure'],
                $form_data['unit_cost'],
                $form_data['current_stock'],
                $form_data['minimum_stock'],
                $form_data['is_active']
            ]);
            
            $_SESSION['success_message'] = 'Product created successfully';
            header('Location: index.php');
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Failed to create product';
            error_log($e->getMessage());
        }
    }
}

$page_title = 'Create Product';
include '../includes/header.php';
?>

<div class="content-area">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Create New Product</h1>
            <p class="text-muted">Add a new product or material to your inventory</p>
        </div>
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
    </div>
    
    <!-- Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Product Information</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="productForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="product_code" class="form-label required">Product Code</label>
                                    <input type="text" id="product_code" name="product_code" class="form-control" 
                                           value="<?php echo htmlspecialchars($form_data['product_code'] ?? ''); ?>" 
                                           placeholder="e.g., WP-001" required>
                                    <small class="form-text text-muted">Unique identifier for the product</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="product_name" class="form-label required">Product Name</label>
                                    <input type="text" id="product_name" name="product_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($form_data['product_name'] ?? ''); ?>" 
                                           placeholder="e.g., Wood Plank" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3" 
                                      placeholder="Optional description of the product"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="product_type" class="form-label required">Product Type</label>
                                    <select id="product_type" name="product_type" class="form-control" required>
                                        <option value="">Select Product Type</option>
                                        <option value="raw_material" <?php echo ($form_data['product_type'] ?? '') === 'raw_material' ? 'selected' : ''; ?>>Raw Material</option>
                                        <option value="finished_good" <?php echo ($form_data['product_type'] ?? '') === 'finished_good' ? 'selected' : ''; ?>>Finished Good</option>
                                        <option value="semi_finished" <?php echo ($form_data['product_type'] ?? '') === 'semi_finished' ? 'selected' : ''; ?>>Semi-Finished</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="unit_of_measure" class="form-label required">Unit of Measure</label>
                                    <input type="text" id="unit_of_measure" name="unit_of_measure" class="form-control" 
                                           value="<?php echo htmlspecialchars($form_data['unit_of_measure'] ?? ''); ?>" 
                                           placeholder="e.g., pcs, kg, m, liters" 
                                           list="unitList" required>
                                    <datalist id="unitList">
                                        <option value="pcs">
                                        <option value="kg">
                                        <option value="m">
                                        <option value="m²">
                                        <option value="m³">
                                        <option value="liters">
                                        <option value="boxes">
                                        <option value="sets">
                                    </datalist>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="unit_cost" class="form-label">Unit Cost</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" id="unit_cost" name="unit_cost" class="form-control" 
                                               value="<?php echo $form_data['unit_cost'] ?? '0.00'; ?>" 
                                               step="0.01" min="0" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="current_stock" class="form-label">Current Stock</label>
                                    <input type="number" id="current_stock" name="current_stock" class="form-control" 
                                           value="<?php echo $form_data['current_stock'] ?? '0'; ?>" 
                                           step="0.01" min="0" placeholder="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="minimum_stock" class="form-label">Minimum Stock Level</label>
                                    <input type="number" id="minimum_stock" name="minimum_stock" class="form-control" 
                                           value="<?php echo $form_data['minimum_stock'] ?? '0'; ?>" 
                                           step="0.01" min="0" placeholder="0">
                                    <small class="form-text text-muted">Alert when stock falls below this level</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" id="is_active" name="is_active" class="form-check-input" 
                                       <?php echo ($form_data['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label for="is_active" class="form-check-label">Active Product</label>
                                <small class="form-text text-muted d-block">Inactive products won't appear in selections</small>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Product
                            </button>
                            <a href="index.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Tips</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-lightbulb"></i> Product Code Guidelines</h6>
                        <ul class="mb-0 small">
                            <li>Use a consistent naming convention</li>
                            <li>Include category prefix (e.g., RM- for Raw Materials)</li>
                            <li>Keep it short but descriptive</li>
                            <li>Avoid special characters</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> Stock Management</h6>
                        <ul class="mb-0 small">
                            <li>Set realistic minimum stock levels</li>
                            <li>Consider lead times for reordering</li>
                            <li>Regular stock audits are recommended</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
document.getElementById('product_code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

document.getElementById('productForm').addEventListener('submit', function(e) {
    const productCode = document.getElementById('product_code').value.trim();
    const productName = document.getElementById('product_name').value.trim();
    const unit = document.getElementById('unit').value.trim();
    
    if (!productCode || !productName || !unit) {
        e.preventDefault();
        alert('Please fill in all required fields');
        return false;
    }
    
    const unitCost = parseFloat(document.getElementById('unit_cost').value);
    const currentStock = parseFloat(document.getElementById('current_stock').value);
    const minimumStock = parseFloat(document.getElementById('minimum_stock').value);
    
    if (unitCost < 0 || currentStock < 0 || minimumStock < 0) {
        e.preventDefault();
        alert('Stock values and unit cost cannot be negative');
        return false;
    }
});
";

include '../includes/footer.php';
?>