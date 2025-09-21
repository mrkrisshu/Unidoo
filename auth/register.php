<?php
/**
 * Registration Page
 * Manufacturing Management System
 */

require_once '../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../dashboard/index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? 'operator');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            $conn = getDBConnection();
            
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                // Create new user
                $password_hash = password_hash($password, HASH_ALGO);
                
                $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash, $full_name, $phone, $role]);
                
                $success = 'Account created successfully! You can now sign in.';
                
                // Clear form data
                $username = $email = $full_name = $phone = '';
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .auth-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .auth-header {
            background: var(--primary-color);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .auth-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .auth-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-size: 0.875rem;
        }
        
        .auth-body {
            padding: 2rem;
        }
        
        .auth-form .form-group {
            margin-bottom: 1.5rem;
        }
        
        .auth-form .form-control {
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border-radius: 0.5rem;
        }
        
        .auth-form .btn {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .auth-links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .auth-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 480px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <i class="fas fa-industry fa-2x mb-2"></i>
            <h1><?php echo APP_NAME; ?></h1>
            <p>Create your account</p>
        </div>
        
        <div class="auth-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="full_name" class="form-label">
                        <i class="fas fa-user"></i> Full Name *
                    </label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           placeholder="Enter your full name" required 
                           value="<?php echo htmlspecialchars($full_name ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-at"></i> Username *
                        </label>
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="Choose username" required 
                               value="<?php echo htmlspecialchars($username ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="role" class="form-label">
                            <i class="fas fa-user-tag"></i> Role
                        </label>
                        <select id="role" name="role" class="form-control form-select">
                            <option value="operator" <?php echo ($role ?? 'operator') == 'operator' ? 'selected' : ''; ?>>Operator</option>
                            <option value="inventory" <?php echo ($role ?? '') == 'inventory' ? 'selected' : ''; ?>>Inventory Manager</option>
                            <option value="manager" <?php echo ($role ?? '') == 'manager' ? 'selected' : ''; ?>>Manager</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i> Email *
                        </label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="Enter your email" required 
                               value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">
                            <i class="fas fa-phone"></i> Phone
                        </label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               placeholder="Enter phone number" 
                               value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> Password *
                        </label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Create password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i> Confirm Password *
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               placeholder="Confirm password" required minlength="6">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            
            <div class="auth-links">
                <p>Already have an account? <a href="login.php">Sign in here</a></p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>