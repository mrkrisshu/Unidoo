<?php
/**
 * Main Configuration File
 * Manufacturing Management System
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ================= APP CONFIG ================= //
define('APP_NAME', 'Manufacturing Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/Hackpro/');

// ================= SECURITY ================= //
define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_TIMEOUT', 3600); // 1 hour
define('OTP_EXPIRY', 300); // 5 minutes

// ================= FILE UPLOAD ================= //
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// ================= PAGINATION ================= //
define('RECORDS_PER_PAGE', 10);

// ================= EMAIL CONFIG ================= //
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'mrkrisshu@gmail.com'); 
define('SMTP_PASSWORD', 'imtz uaqd zfwc oehe'); // Gmail App Password
define('FROM_EMAIL', 'mrkrisshu@gmail.com'); 
define('FROM_NAME', 'Manufacturing System');

// ================= PHPMailer INCLUDES ================= //
require __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-master/src/SMTP.php';
require __DIR__ . '/../PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ================= EMAIL FUNCTIONS ================= //
function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Email template system
function sendTemplateEmail($to, $template, $variables = []) {
    $templates = [
        'forgot_password' => [
            'subject' => 'Password Reset Request - ' . APP_NAME,
            'body' => '
                <html>
                <head><title>Password Reset</title></head>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                        <h2 style="color: #2c3e50;">Password Reset Request</h2>
                        <p>Hello,</p>
                        <p>You have requested to reset your password for your ' . APP_NAME . ' account.</p>
                        <p>Your verification code is: <strong style="font-size: 18px; color: #e74c3c;">{otp}</strong></p>
                        <p>This code will expire in 5 minutes.</p>
                        <p>If you did not request this password reset, please ignore this email.</p>
                        <hr style="border: 1px solid #eee; margin: 20px 0;">
                        <p style="font-size: 12px; color: #666;">
                            This is an automated message from ' . APP_NAME . '. Please do not reply to this email.
                        </p>
                    </div>
                </body>
                </html>
            '
        ]
    ];
    
    if (!isset($templates[$template])) {
        return false;
    }
    
    $email_template = $templates[$template];
    $subject = $email_template['subject'];
    $body = $email_template['body'];
    
    foreach ($variables as $key => $value) {
        $body = str_replace('{' . $key . '}', $value, $body);
    }
    
    return sendEmail($to, $subject, $body);
}

// ================= DATABASE & UTILITIES ================= //
require_once __DIR__ . '/database.php';

function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit();
    }
}

function hasRole($required_roles) {
    if (!isLoggedIn()) return false;
    if (is_string($required_roles)) $required_roles = [$required_roles];
    return in_array($_SESSION['user_role'], $required_roles);
}

function requireRole($required_roles) {
    if (!hasRole($required_roles)) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied. Insufficient permissions.');
    }
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function formatDate($date, $format = 'Y-m-d') {
    return empty($date) ? '' : date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    return empty($datetime) ? '' : date($format, strtotime($datetime));
}

// ================= ERROR & TIMEZONE ================= //
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');
