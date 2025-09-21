<?php
/**
 * User Account Settings
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

$page_title = 'Account Settings';
$user_id = $_SESSION['user_id'];

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $conn = getDBConnection();
        
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception('All password fields are required.');
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception('New passwords do not match.');
        }
        
        if (strlen($new_password) < 6) {
            throw new Exception('New password must be at least 6 characters long.');
        }
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($current_password, $user['password'])) {
            throw new Exception('Current password is incorrect.');
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        
        $_SESSION['success_message'] = 'Password changed successfully!';
        header('Location: settings.php');
        exit();
        
    } catch (Exception $e) {
        $password_error = $e->getMessage();
    }
}

// Handle notification preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    try {
        $conn = getDBConnection();
        
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        
        // Update or insert user preferences
        $stmt = $conn->prepare("
            INSERT INTO user_preferences (user_id, email_notifications, sms_notifications, push_notifications, updated_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            email_notifications = VALUES(email_notifications),
            sms_notifications = VALUES(sms_notifications),
            push_notifications = VALUES(push_notifications),
            updated_at = VALUES(updated_at)
        ");
        $stmt->execute([$user_id, $email_notifications, $sms_notifications, $push_notifications]);
        
        $_SESSION['success_message'] = 'Preferences updated successfully!';
        header('Location: settings.php');
        exit();
        
    } catch (Exception $e) {
        $preferences_error = $e->getMessage();
    }
}

// Get user data and preferences
try {
    $conn = getDBConnection();
    
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user preferences (create table if not exists)
    try {
        $stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Create user_preferences table if it doesn't exist
        $conn->exec("
            CREATE TABLE IF NOT EXISTS user_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                email_notifications TINYINT(1) DEFAULT 1,
                sms_notifications TINYINT(1) DEFAULT 0,
                push_notifications TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        $preferences = [
            'email_notifications' => 1,
            'sms_notifications' => 0,
            'push_notifications' => 1
        ];
    }
    
} catch (Exception $e) {
    $error = 'Error loading settings: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Settings Navigation -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <a href="index.php" class="btn btn-outline btn-sm me-3">
                            <i class="fas fa-arrow-left"></i> Back to Profile
                        </a>
                        <h4 class="mb-0">Account Settings</h4>
                    </div>
                </div>
            </div>

            <!-- Password Change -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-lock"></i> Change Password
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($password_error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($password_error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password *</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <div class="invalid-feedback">
                                Please enter your current password.
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="new_password" class="form-label">New Password *</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           minlength="6" required>
                                    <div class="invalid-feedback">
                                        Password must be at least 6 characters long.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           minlength="6" required>
                                    <div class="invalid-feedback">
                                        Please confirm your new password.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notification Preferences -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bell"></i> Notification Preferences
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($preferences_error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($preferences_error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="update_preferences" value="1">
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="email_notifications" 
                                       name="email_notifications" <?php echo (isset($preferences['email_notifications']) && $preferences['email_notifications']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_notifications">
                                    <strong>Email Notifications</strong>
                                    <br><small class="text-muted">Receive notifications via email for important updates</small>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sms_notifications" 
                                       name="sms_notifications" <?php echo (isset($preferences['sms_notifications']) && $preferences['sms_notifications']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="sms_notifications">
                                    <strong>SMS Notifications</strong>
                                    <br><small class="text-muted">Receive urgent notifications via SMS</small>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="push_notifications" 
                                       name="push_notifications" <?php echo (isset($preferences['push_notifications']) && $preferences['push_notifications']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="push_notifications">
                                    <strong>Push Notifications</strong>
                                    <br><small class="text-muted">Receive browser push notifications</small>
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Information -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i> Account Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Account Created:</strong> <?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo date('F j, Y g:i A', strtotime($user['updated_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>User ID:</strong> #<?php echo str_pad($user['id'], 6, '0', STR_PAD_LEFT); ?></p>
                            <p><strong>Account Status:</strong> 
                                <span class="badge badge-success">Active</span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Need help?</strong> Contact your system administrator for account-related issues or role changes.
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

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value) {
        confirmPassword.dispatchEvent(new Event('input'));
    }
});
</script>

<?php include '../includes/footer.php'; ?>