<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Manufacturing Management System</title>
    
    <!-- CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Additional page-specific CSS -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-industry"></i>
                    <span class="logo-text">MMS</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <div class="sidebar-menu">
                <ul class="nav-list">
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a href="../dashboard/index.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>
                    
                    <!-- Production Management -->
                    <li class="nav-section">
                        <span class="nav-section-title">Production</span>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../products/index.php" class="nav-link">
                            <i class="fas fa-box"></i>
                            <span class="nav-text">Products</span>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../bom/index.php" class="nav-link">
                            <i class="fas fa-list-alt"></i>
                            <span class="nav-text">BOM</span>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../manufacturing_orders/index.php" class="nav-link">
                            <i class="fas fa-clipboard-list"></i>
                            <span class="nav-text">Manufacturing</span>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../work_orders/index.php" class="nav-link">
                            <i class="fas fa-tasks"></i>
                            <span class="nav-text">Work Orders</span>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../work_centers/index.php" class="nav-link">
                            <i class="fas fa-cogs"></i>
                            <span class="nav-text">Work Centers</span>
                        </a>
                    </li>
                    
                    <!-- Inventory Management -->
                    <li class="nav-section">
                        <span class="nav-section-title">Inventory</span>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../stock/index.php" class="nav-link">
                            <i class="fas fa-warehouse"></i>
                            <span class="nav-text">Stock Ledger</span>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../stock/movements.php" class="nav-link">
                            <i class="fas fa-exchange-alt"></i>
                            <span class="nav-text">Stock Movements</span>
                        </a>
                    </li>
                    
                    <!-- Reports & Analytics -->
                    <li class="nav-section">
                        <span class="nav-section-title">Reports</span>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../reports/index.php" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span class="nav-text">Reports</span>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../reports/manufacturing_orders.php" class="nav-link">
                            <i class="fas fa-industry"></i>
                            <span class="nav-text">Manufacturing Reports</span>
                        </a>
                    </li>
                    
                    <!-- Administration -->
                    <?php if (hasRole(['admin'])): ?>
                    <li class="nav-section">
                        <span class="nav-section-title">Administration</span>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../users/index.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span class="nav-text">User Management</span>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../reports/export_all.php" class="nav-link">
                            <i class="fas fa-download"></i>
                            <span class="nav-text">Export Data</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="mobile-sidebar-toggle" id="mobileSidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title"><?php echo $page_title ?? 'Manufacturing Management System'; ?></h1>
                </div>
                
                <div class="header-right">
                    <!-- Notifications -->
                    <div class="header-item dropdown">
                        <button class="header-btn" id="notificationToggle">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge">3</span>
                        </button>
                        <div class="dropdown-menu notification-dropdown" id="notificationDropdown">
                            <div class="dropdown-header">
                                <h6>Notifications</h6>
                            </div>
                            <div class="notification-list">
                                <div class="notification-item">
                                    <div class="notification-icon warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p>Low stock alert for Wood Planks</p>
                                        <small>2 minutes ago</small>
                                    </div>
                                </div>
                                <div class="notification-item">
                                    <div class="notification-icon success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p>Work Order WO-001 completed</p>
                                        <small>15 minutes ago</small>
                                    </div>
                                </div>
                                <div class="notification-item">
                                    <div class="notification-icon info">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p>New Manufacturing Order created</p>
                                        <small>1 hour ago</small>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown-footer">
                                <a href="#" class="btn btn-sm btn-outline">View All</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="header-item dropdown">
                        <button class="header-btn user-btn" id="userMenuToggle">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu user-dropdown" id="userDropdown">
                            <div class="dropdown-header">
                                <div class="user-info">
                                    <div class="user-avatar large">
                                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h6><?php echo htmlspecialchars($_SESSION['full_name']); ?></h6>
                                        <small><?php echo htmlspecialchars($_SESSION['email']); ?></small>
                                        <span class="badge badge-<?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') ? 'danger' : 'primary'; ?> badge-sm">
                                            <?php echo isset($_SESSION['user_role']) ? ucfirst($_SESSION['user_role']) : 'User'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown-body">
                                <a href="../profile/index.php" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <a href="../profile/settings.php" class="dropdown-item">
                                    <i class="fas fa-cog"></i> Settings
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="../auth/logout.php" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="page-content">
                <?php
                // Display flash messages
                if (isset($_SESSION['success_message'])) {
                    echo '<div class="alert alert-success alert-dismissible">';
                    echo '<i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success_message']);
                    echo '<button type="button" class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
                    echo '</div>';
                    unset($_SESSION['success_message']);
                }
                
                if (isset($_SESSION['error_message'])) {
                    echo '<div class="alert alert-danger alert-dismissible">';
                    echo '<i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error_message']);
                    echo '<button type="button" class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
                    echo '</div>';
                    unset($_SESSION['error_message']);
                }
                
                if (isset($_SESSION['warning_message'])) {
                    echo '<div class="alert alert-warning alert-dismissible">';
                    echo '<i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($_SESSION['warning_message']);
                    echo '<button type="button" class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
                    echo '</div>';
                    unset($_SESSION['warning_message']);
                }
                
                if (isset($_SESSION['info_message'])) {
                    echo '<div class="alert alert-info alert-dismissible">';
                    echo '<i class="fas fa-info-circle"></i> ' . htmlspecialchars($_SESSION['info_message']);
                    echo '<button type="button" class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
                    echo '</div>';
                    unset($_SESSION['info_message']);
                }
                ?>