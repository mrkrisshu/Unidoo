<?php
/**
 * Main Entry Point
 * Manufacturing Management System
 */

require_once 'config/config.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Redirect to dashboard
    header('Location: dashboard/index.php');
} else {
    // Redirect to login
    header('Location: auth/login.php');
}
exit();
?>