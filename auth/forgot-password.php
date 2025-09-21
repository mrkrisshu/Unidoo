<?php
/**
 * Forgot Password Page
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
$step = 1; // 1: Email, 2: OTP, 3: New Password

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['step'])) {
        $step = (int)$_POST['step'];
        
        switch ($step) {
            case 1: // Send OTP
                $email = sanitizeInput($_POST['email'] ?? '');
                
                if (empty($email)) {
                    $error = 'Please enter your email address.';
                } else {
                    try {
                        $conn = getDBConnection();
                        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch();
                        
                        if ($user) {
                            // Generate OTP
                            $otp = generateOTP();
                            $expires_at = date('Y-m-d H:i:s', time() + OTP_EXPIRY);
                            
                            // Store OTP in session
                            $_SESSION['reset_email'] = $email;
                            $_SESSION['reset_otp'] = $otp;
                            $_SESSION['reset_otp_expires'] = $expires_at;
                            
                            // Send OTP via email
                            try {
                                $email_sent = sendTemplateEmail($email, 'forgot_password', ['otp' => $otp]);
                                
                                if ($email_sent) {
                                    $success = "OTP sent to your email address. Please check your inbox and enter the verification code below.";
                                } else {
                                    $success = "OTP generated successfully. For demo purposes (email not configured), your OTP is: <strong>$otp</strong>";
                                }
                                $step = 2;
                            } catch (Exception $e) {
                                error_log("Email sending failed: " . $e->getMessage());
                                $success = "OTP generated successfully. For demo purposes (email error), your OTP is: <strong>$otp</strong>";
                                $step = 2;
                            }
                        } else {
                            $error = 'Email address not found.';
                        }
                    } catch (Exception $e) {
                        $error = 'An error occurred. Please try again.';
                        error_log($e->getMessage());
                    }
                }
                break;
                
            case 2: // Verify OTP
                $otp = sanitizeInput($_POST['otp'] ?? '');
                
                if (empty($otp)) {
                    $error = 'Please enter the OTP.';
                } elseif (!isset($_SESSION['reset_otp']) || $_SESSION['reset_otp'] !== $otp) {
                    $error = 'Invalid OTP.';
                } elseif (time() > strtotime($_SESSION['reset_otp_expires'])) {
                    $error = 'OTP has expired. Please request a new one.';
                    unset($_SESSION['reset_otp'], $_SESSION['reset_otp_expires']);
                    $step = 1;
                } else {
                    $success = 'OTP verified successfully. Please enter your new password.';
                    $step = 3;
                }
                break;
                
            case 3: // Reset Password
                $password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($password) || empty($confirm_password)) {
                    $error = 'Please fill in all fields.';
                } elseif ($password !== $confirm_password) {
                    $error = 'Passwords do not match.';
                } elseif (strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters long.';
                } else {
                    try {
                        $conn = getDBConnection();
                        $password_hash = password_hash($password, HASH_ALGO);
                        
                        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                        $stmt->execute([$password_hash, $_SESSION['reset_email']]);
                        
                        // Clear session data
                        unset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_otp_expires']);
                        
                        $success = 'Password reset successfully! You can now sign in with your new password.';
                        $step = 4; // Success step
                    } catch (Exception $e) {
                        $error = 'An error occurred. Please try again.';
                        error_log($e->getMessage());
                    }
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
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
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--border-color);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 600;
            margin: 0 0.5rem;
        }
        
        .step.active {
            background: var(--primary-color);
            color: white;
        }
        
        .step.completed {
            background: var(--success-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <i class="fas fa-key fa-2x mb-2"></i>
            <h1>Reset Password</h1>
            <p>
                <?php
                switch ($step) {
                    case 1: echo 'Enter your email address'; break;
                    case 2: echo 'Enter the OTP sent to your email'; break;
                    case 3: echo 'Create a new password'; break;
                    case 4: echo 'Password reset successful'; break;
                }
                ?>
            </p>
        </div>
        
        <div class="auth-body">
            <?php if ($step < 4): ?>
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                    <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                    <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
                </div>
            <?php endif; ?>
            
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
            
            <?php if ($step == 1): ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="step" value="1">
                    
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="Enter your email address" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send OTP
                    </button>
                </form>
                
            <?php elseif ($step == 2): ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="step" value="2">
                    
                    <div class="form-group">
                        <label for="otp" class="form-label">
                            <i class="fas fa-shield-alt"></i> Enter OTP
                        </label>
                        <input type="text" id="otp" name="otp" class="form-control" 
                               placeholder="Enter 6-digit OTP" required maxlength="6" 
                               style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Verify OTP
                    </button>
                </form>
                
                <div class="auth-links">
                    <p><a href="?">Didn't receive OTP? Try again</a></p>
                </div>
                
            <?php elseif ($step == 3): ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="step" value="3">
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> New Password
                        </label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Enter new password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i> Confirm Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               placeholder="Confirm new password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
                
            <?php else: ?>
                <div class="text-center">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <p>Your password has been reset successfully!</p>
                </div>
            <?php endif; ?>
            
            <div class="auth-links">
                <p><a href="login.php"><i class="fas fa-arrow-left"></i> Back to Sign In</a></p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // OTP input formatting
        const otpInput = document.getElementById('otp');
        if (otpInput) {
            otpInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
            });
        }
        
        // Password confirmation validation
        const confirmPassword = document.getElementById('confirm_password');
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                const password = document.getElementById('password').value;
                const confirmPassword = this.value;
                
                if (password !== confirmPassword) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>