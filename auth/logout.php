<?php
/**
 * Logout Page
 * Manufacturing Management System
 */

require_once '../config/config.php';

// Clear session token from database if exists
if (isset($_SESSION['session_token'])) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$_SESSION['session_token']]);
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

// Clear all session data
session_unset();
session_destroy();

// Start new session for flash message
session_start();
$_SESSION['logout_message'] = 'You have been successfully logged out.';

// Redirect to login page
header('Location: login.php');
exit();
?>