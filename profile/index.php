<?php
/**
 * User Profile Management
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$page_title = 'My Profile';
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDBConnection();
        
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        
        // Validate input
        if (empty($full_name) || empty($email)) {
            throw new Exception('Full name and email are required.');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception('This email is already registered to another user.');
        }
        
        // Update user profile
        $stmt = $conn->prepare("
            UPDATE users 
            SET full_name = ?, email = ?, phone = ?, department = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$full_name, $email, $phone, $department, $user_id]);
        
        // Update session data
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
        
        $_SESSION['success_message'] = 'Profile updated successfully!';
        header('Location: index.php');
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get user data
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found.');
    }
} catch (Exception $e) {
    $error = 'Error loading profile: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Profile Header -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="user-avatar large mb-3" style="width: 80px; height: 80px; font-size: 2rem; margin: 0 auto;">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                    <span class="badge badge-<?php echo ($user['user_role'] === 'admin') ? 'danger' : 'primary'; ?>">
                        <?php echo ucfirst($user['user_role']); ?>
                    </span>
                </div>
            </div>

            <!-- Profile Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user"></i> Profile Information
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    <div class="invalid-feedback">
                                        Please provide a valid full name.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    <div class="invalid-feedback">
                                        Please provide a valid email address.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" name="department" 
                                           value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">User Role</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo ucfirst($user['user_role']); ?>" readonly>
                                    <small class="form-text text-muted">Contact administrator to change your role.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Member Since</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                            <a href="settings.php" class="btn btn-outline">
                                <i class="fas fa-cog"></i> Account Settings
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Statistics -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar"></i> Account Activity
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="stat-item">
                                <div class="h4 text-primary">
                                    <?php 
                                    // Get user's manufacturing orders count
                                    try {
                                        $stmt = $conn->prepare("SELECT COUNT(*) FROM manufacturing_orders WHERE created_by = ?");
                                        $stmt->execute([$user_id]);
                                        echo number_format($stmt->fetchColumn());
                                    } catch (Exception $e) {
                                        echo '0';
                                    }
                                    ?>
                                </div>
                                <div class="text-muted small">Manufacturing Orders Created</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-item">
                                <div class="h4 text-success">
                                    <?php 
                                    // Get user's work orders count
                                    try {
                                        $stmt = $conn->prepare("SELECT COUNT(*) FROM work_orders WHERE assigned_to = ?");
                                        $stmt->execute([$user_id]);
                                        echo number_format($stmt->fetchColumn());
                                    } catch (Exception $e) {
                                        echo '0';
                                    }
                                    ?>
                                </div>
                                <div class="text-muted small">Work Orders Assigned</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-item">
                                <div class="h4 text-info">
                                    <?php echo date('j', strtotime($user['created_at'])); ?>
                                </div>
                                <div class="text-muted small">Days as Member</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include '../includes/footer.php'; ?>