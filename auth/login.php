<?php
/**
 * Login Page
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
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id, username, email, password_hash, full_name, role, is_active FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Create session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Create session token
                $token = generateToken();
                $expires_at = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
                
                $stmt = $conn->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token, $expires_at]);
                
                $_SESSION['session_token'] = $token;
                
                header('Location: ../dashboard/index.php');
                exit();
            } else {
                $error = 'Invalid email or password.';
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
    <title>Login - <?php echo APP_NAME; ?></title>
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
            max-width: 400px;
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
        
        .divider {
            text-align: center;
            margin: 1rem 0;
            position: relative;
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-color);
            z-index: 1;
        }
        
        .divider span {
            background: white;
            padding: 0 1rem;
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <i class="fas fa-industry fa-2x mb-2"></i>
            <h1><?php echo APP_NAME; ?></h1>
            <p>Sign in to your account</p>
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
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="Enter your email" required 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <div class="auth-links">
                <p>Don't have an account? <a href="register.php">Sign up here</a></p>
                <p><a href="forgot-password.php">Forgot your password?</a></p>
            </div>
            
            <div class="divider">
                <span>Demo Accounts</span>
            </div>
            
            <div style="background: var(--light-color); padding: 1rem; border-radius: 0.5rem; font-size: 0.875rem;">
                <p><strong>Admin:</strong> admin@manufacturing.com / admin123</p>
                <p><strong>Manager:</strong> manager@manufacturing.com / manager123</p>
                <p><strong>Operator:</strong> operator@manufacturing.com / operator123</p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>