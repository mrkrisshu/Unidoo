<?php
/**
 * User Management
 * Manufacturing Management System
 */

require_once '../config/config.php';
requireLogin();

// Check if user has admin role
if (!hasRole(['admin'])) {
    $_SESSION['error_message'] = 'Access denied. Admin privileges required.';
    header('Location: ../dashboard/index.php');
    exit();
}

$page_title = 'User Management';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDBConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'toggle_status':
                    $user_id = intval($_POST['user_id']);
                    $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
                    
                    $stmt = $conn->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$new_status, $user_id]);
                    
                    $_SESSION['success_message'] = 'User status updated successfully!';
                    break;
                    
                case 'update_role':
                    $user_id = intval($_POST['user_id']);
                    $new_role = $_POST['role'];
                    
                    if (in_array($new_role, ['admin', 'manager', 'operator'])) {
                        $stmt = $conn->prepare("UPDATE users SET user_role = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$new_role, $user_id]);
                        
                        $_SESSION['success_message'] = 'User role updated successfully!';
                    }
                    break;
            }
        }
        
        header('Location: index.php');
        exit();
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get users with pagination
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    $conn = getDBConnection();
    
    // Build WHERE clause
    $where_conditions = ['1=1'];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(full_name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($role_filter)) {
        $where_conditions[] = "user_role = ?";
        $params[] = $role_filter;
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE $where_clause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
    // Get users
    $stmt = $conn->prepare("
        SELECT id, full_name, email, user_role, status, created_at, updated_at,
               (SELECT COUNT(*) FROM manufacturing_orders WHERE created_by = users.id) as mo_count,
               (SELECT COUNT(*) FROM work_orders WHERE assigned_to = users.id) as wo_count
        FROM users 
        WHERE $where_clause 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([...$params, $per_page, $offset]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_stmt = $conn->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN user_role = 'admin' THEN 1 ELSE 0 END) as admin_users,
            SUM(CASE WHEN user_role = 'manager' THEN 1 ELSE 0 END) as manager_users,
            SUM(CASE WHEN user_role = 'operator' THEN 1 ELSE 0 END) as operator_users
        FROM users
    ");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Error loading users: ' . $e->getMessage();
    $users = [];
    $stats = [];
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">
            <i class="fas fa-users"></i> User Management
        </h2>
        <div class="d-flex gap-2">
            <button class="btn btn-outline" onclick="exportUsers()">
                <i class="fas fa-download"></i> Export
            </button>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add User
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-primary"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                    <div class="text-muted small">Total Users</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-success"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                    <div class="text-muted small">Active</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-danger"><?php echo number_format($stats['admin_users'] ?? 0); ?></div>
                    <div class="text-muted small">Admins</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-warning"><?php echo number_format($stats['manager_users'] ?? 0); ?></div>
                    <div class="text-muted small">Managers</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h4 text-info"><?php echo number_format($stats['operator_users'] ?? 0); ?></div>
                    <div class="text-muted small">Operators</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Name or email...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="manager" <?php echo $role_filter === 'manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="operator" <?php echo $role_filter === 'operator' ? 'selected' : ''; ?>>Operator</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="index.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                Users (<?php echo number_format($total_records); ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger m-3">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No users found</h5>
                    <p class="text-muted">Try adjusting your search criteria</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Activity</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-3">
                                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-medium"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo match($user['user_role']) {
                                                'admin' => 'danger',
                                                'manager' => 'warning',
                                                'operator' => 'info',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($user['user_role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo ($user['status'] === 'active') ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            MO: <?php echo number_format($user['mo_count']); ?> | 
                                            WO: <?php echo number_format($user['wo_count']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline" onclick="editUser(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-outline" onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status'] ?? 'active'; ?>')">
                                                    <i class="fas fa-<?php echo ($user['status'] === 'active') ? 'ban' : 'check'; ?>"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Users pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function editUser(userId) {
    // Implement user editing functionality
    alert('Edit user functionality would be implemented here');
}

function toggleUserStatus(userId, currentStatus) {
    const action = currentStatus === 'active' ? 'deactivate' : 'activate';
    
    if (confirm(`Are you sure you want to ${action} this user?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="status" value="${currentStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function exportUsers() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.open('export.php?' + params.toString(), '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>