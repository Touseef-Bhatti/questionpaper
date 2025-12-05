<?php
/**
 * Admin Authentication and Access Control
 */

/**
 * Check if user is logged in and has required admin role
 */
function requireAdminRole($requiredRole = 'admin') {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        die(json_encode(['error' => 'Authentication required']));
    }
    
    global $conn;
    require_once __DIR__ . '/../db_connect.php';
    
    // Get user role
    $sql = "SELECT role, name, email FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        die(json_encode(['error' => 'User not found']));
    }
    
    $user = $result->fetch_assoc();
    $userRole = $user['role'] ?? 'user';
    
    // Role hierarchy: super_admin > admin > user
    $roleHierarchy = ['user' => 1, 'admin' => 2, 'super_admin' => 3];
    
    $userLevel = $roleHierarchy[$userRole] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 999;
    
    if ($userLevel < $requiredLevel) {
        http_response_code(403);
        die(json_encode(['error' => 'Insufficient permissions. Required: ' . $requiredRole . ', Current: ' . $userRole]));
    }
    
    return $user;
}

/**
 * Check if current user has admin access (any admin level)
 */
function isAdmin() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    global $conn;
    require_once __DIR__ . '/../db_connect.php';
    
    $sql = "SELECT role FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $user = $result->fetch_assoc();
    return in_array($user['role'] ?? 'user', ['admin', 'super_admin']);
}

/**
 * Check if current user is super admin
 */
function isSuperAdmin() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    global $conn;
    require_once __DIR__ . '/../db_connect.php';
    
    $sql = "SELECT role FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $user = $result->fetch_assoc();
    return ($user['role'] ?? 'user') === 'super_admin';
}

/**
 * Get user role display name
 */
function getRoleDisplayName($role) {
    $roleNames = [
        'user' => 'User',
        'admin' => 'Administrator', 
        'super_admin' => 'Super Administrator'
    ];
    
    return $roleNames[$role] ?? 'Unknown';
}

/**
 * Get role badge HTML
 */
function getRoleBadge($role) {
    $badges = [
        'user' => '<span class="badge badge-secondary">User</span>',
        'admin' => '<span class="badge badge-warning">Admin</span>',
        'super_admin' => '<span class="badge badge-danger">Super Admin</span>'
    ];
    
    return $badges[$role] ?? '<span class="badge badge-light">Unknown</span>';
}

/**
 * Require super admin access for the current page
 */
function requireSuperAdmin() {
    return requireAdminRole('super_admin');
}

/**
 * Admin authentication wrapper for pages
 */
function adminPageHeader($title, $requiredRole = 'admin') {
    $user = requireAdminRole($requiredRole);
    
    echo "<!DOCTYPE html>
    <html lang=\"en\">
    <head>
        <meta charset=\"UTF-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <title>$title - Ahmad Learning Hub Admin</title>
        <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\">
        <link href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css\" rel=\"stylesheet\">
        <style>
            .admin-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 1rem 0;
                margin-bottom: 2rem;
            }
            .admin-nav {
                background: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
                padding: 0.5rem 0;
                margin-bottom: 2rem;
            }
            .admin-nav .nav-link {
                color: #495057;
                font-weight: 500;
            }
            .admin-nav .nav-link.active {
                color: #007bff;
                font-weight: bold;
            }
            .badge { margin-left: 0.5rem; }
            .table-responsive { margin-top: 1rem; }
            .status-badge {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
        </style>
    </head>
    <body>
        <div class=\"admin-header\">
            <div class=\"container\">
                <div class=\"row align-items-center\">
                    <div class=\"col\">
                        <h1><i class=\"fas fa-cog\"></i> $title</h1>
                        <p class=\"mb-0\">Welcome, {$user['name']} " . getRoleBadge($user['role']) . "</p>
                    </div>
                    <div class=\"col-auto\">
                        <a href=\"../index.php\" class=\"btn btn-light\"><i class=\"fas fa-home\"></i> Home</a>
                        <a href=\"../logout.php\" class=\"btn btn-outline-light\"><i class=\"fas fa-sign-out-alt\"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>";
        
    return $user;
}

/**
 * Admin navigation for super admin pages
 */
function adminNavigation($currentPage = '') {
    if (!isSuperAdmin()) {
        return;
    }
    
    $navItems = [
        'payment_analytics.php' => ['icon' => 'chart-line', 'title' => 'Analytics'],
        'payment_refunds.php' => ['icon' => 'undo', 'title' => 'Refunds'],
        'verify_payment.php' => ['icon' => 'check-circle', 'title' => 'Verify Payments'],
        'payment_health.php' => ['icon' => 'heartbeat', 'title' => 'Health Check'],
        'super_admin_payments.php' => ['icon' => 'credit-card', 'title' => 'All Payments'],
        'super_admin_users.php' => ['icon' => 'users', 'title' => 'All Users']
    ];
    
    echo '<div class="admin-nav">
            <div class="container">
                <nav class="nav nav-pills">';
                
    foreach ($navItems as $file => $item) {
        $active = (basename($_SERVER['PHP_SELF']) === $file) ? 'active' : '';
        echo "<a class=\"nav-link $active\" href=\"$file\">
                <i class=\"fas fa-{$item['icon']}\"></i> {$item['title']}
              </a>";
    }
    
    echo '    </nav>
            </div>
          </div>';
}
?>
