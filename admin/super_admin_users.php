<?php
/**
 * Super Admin - All Users Management
 * Comprehensive user management interface for super administrators
 */

require_once '../includes/admin_auth.php';
require_once '../email/phpmailer_mailer.php';
require_once 'security.php';

function superAdminUserStatusFromPlanName($planName) {
    $planName = strtolower((string)$planName);
    $statusMap = [
        'free' => 'free',
        'premium' => 'premium',
        'pro' => 'pro',
        'yearly_premium' => 'premium',
        'yearly_pro' => 'pro'
    ];

    if (isset($statusMap[$planName])) {
        return $statusMap[$planName];
    }

    if (strpos($planName, 'pro') !== false) {
        return 'pro';
    }

    if (strpos($planName, 'premium') !== false) {
        return 'premium';
    }

    return 'free';
}

function superAdminTableHasColumn($conn, $table, $column) {
    $sql = "SHOW COLUMNS FROM `$table` LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $column);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function superAdminTrySubscriptionEmail($email, $name, $planDisplayName, $expiresAt = null) {
    if (!function_exists('sendSubscriptionUpdateEmail')) {
        error_log('Subscription update email skipped: sendSubscriptionUpdateEmail() is not defined.');
        return false;
    }

    try {
        return (bool)sendSubscriptionUpdateEmail($email, $name, $planDisplayName, $expiresAt);
    } catch (Throwable $e) {
        error_log('Subscription update email failed: ' . $e->getMessage());
        return false;
    }
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    $ajaxBufferLevel = ob_get_level();
    ob_start();
    header('Content-Type: application/json');

    $sendJson = function($payload, $statusCode = 200) use ($ajaxBufferLevel) {
        while (ob_get_level() > $ajaxBufferLevel) {
            ob_end_clean();
        }

        http_response_code($statusCode);
        echo json_encode($payload);
        exit;
    };

    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    $action = $_GET['action'];
    $userId = intval($_POST['user_id'] ?? 0);
    
    if ($userId <= 0) {
        $sendJson(['error' => 'Invalid user ID'], 400);
    }

    try {
        switch ($action) {
        case 'change_role':
            $newRole = $_POST['new_role'] ?? '';
            $validRoles = ['user', 'admin', 'super_admin'];
            
            if (!in_array($newRole, $validRoles)) {
                $sendJson(['error' => 'Invalid role'], 400);
            }
            
            // Prevent users from downgrading themselves
            if ($userId == $_SESSION['user_id'] && $newRole !== 'super_admin') {
                $sendJson(['error' => 'Cannot change your own role'], 400);
            }
            
            $sql = "UPDATE users SET role = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $newRole, $userId);
            
            if ($stmt->execute()) {
                $sendJson(['success' => true, 'message' => 'User role updated successfully']);
            } else {
                $sendJson(['error' => 'Failed to update user role'], 500);
            }
            
        case 'toggle_verification':
            $sql = "UPDATE users SET verified = NOT verified WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                $sendJson(['success' => true, 'message' => 'User verification status updated']);
            } else {
                $sendJson(['error' => 'Failed to update verification status'], 500);
            }
            
        case 'reset_password':
            $newPassword = bin2hex(random_bytes(4)); // Generate 8-character password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $hashedPassword, $userId);
            
            if ($stmt->execute()) {
                $sendJson([
                    'success' => true, 
                    'message' => 'Password reset successfully',
                    'new_password' => $newPassword
                ]);
            } else {
                $sendJson(['error' => 'Failed to reset password'], 500);
            }
 
         case 'change_subscription':
             $newPlanId = intval($_POST['plan_id'] ?? 0);
             $days = intval($_POST['days'] ?? 30);
             
             if ($newPlanId < 0) {
                 $sendJson(['error' => 'Invalid plan'], 400);
             }

             if ($newPlanId > 0 && ($days < 1 || $days > 3650)) {
                 $sendJson(['error' => 'Duration must be between 1 and 3650 days'], 400);
             }
             
             // Start transaction
            $transactionStarted = false;
            $conn->begin_transaction();
            $transactionStarted = true;
            
            try {
                // Get user details for email
                $userSql = "SELECT name, email FROM users WHERE id = ?";
                $uStmt = $conn->prepare($userSql);
                $uStmt->bind_param("i", $userId);
                $uStmt->execute();
                $userData = $uStmt->get_result()->fetch_assoc();
                
                if (!$userData) {
                    throw new Exception("User not found");
                }

                $planData = null;
                $expiresAt = null;
                $planDisplayName = 'Free Plan';
                $userSubscriptionStatus = 'free';

                if ($newPlanId > 0) {
                    $planSql = "SELECT name, display_name FROM subscription_plans WHERE id = ?";
                    $pStmt = $conn->prepare($planSql);
                    $pStmt->bind_param("i", $newPlanId);
                    $pStmt->execute();
                    $planData = $pStmt->get_result()->fetch_assoc();

                    if (!$planData) {
                        throw new Exception("Selected subscription plan was not found");
                    }

                    $expiresAt = date('Y-m-d H:i:s', strtotime("+$days days"));
                    $planDisplayName = $planData['display_name'] ?? 'Premium';
                    $userSubscriptionStatus = superAdminUserStatusFromPlanName($planData['name'] ?? '');
                }

                // 1. Deactivate current active subscriptions
                $sql = "UPDATE user_subscriptions SET status = 'expired' WHERE user_id = ? AND status = 'active'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                
                if ($newPlanId > 0) {
                    // 2. Insert new subscription
                    $startedAt = date('Y-m-d H:i:s');

                    $columns = ['user_id', 'plan_id', 'status'];
                    $placeholders = ['?', '?', "'active'"];
                    $bindTypes = 'ii';
                    $bindValues = [$userId, $newPlanId];

                    if (superAdminTableHasColumn($conn, 'user_subscriptions', 'started_at')) {
                        $columns[] = 'started_at';
                        $placeholders[] = '?';
                        $bindTypes .= 's';
                        $bindValues[] = $startedAt;
                    }

                    if (superAdminTableHasColumn($conn, 'user_subscriptions', 'expires_at')) {
                        $columns[] = 'expires_at';
                        $placeholders[] = '?';
                        $bindTypes .= 's';
                        $bindValues[] = $expiresAt;
                    }

                    if (superAdminTableHasColumn($conn, 'user_subscriptions', 'auto_renew')) {
                        $columns[] = 'auto_renew';
                        $placeholders[] = '0';
                    }

                    $sql = "INSERT INTO user_subscriptions (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($bindTypes, ...$bindValues);
                    $stmt->execute();
                    
                    // 3. Update users table status
                    if (superAdminTableHasColumn($conn, 'users', 'subscription_expires_at')) {
                        $sql = "UPDATE users SET subscription_status = ?, subscription_expires_at = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssi", $userSubscriptionStatus, $expiresAt, $userId);
                    } else {
                        $sql = "UPDATE users SET subscription_status = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $userSubscriptionStatus, $userId);
                    }
                    $stmt->execute();
                } else {
                    // Reset to free
                    if (superAdminTableHasColumn($conn, 'users', 'subscription_expires_at')) {
                        $sql = "UPDATE users SET subscription_status = 'free', subscription_expires_at = NULL WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $userId);
                    } else {
                        $sql = "UPDATE users SET subscription_status = 'free' WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $userId);
                    }
                    $stmt->execute();
                }
                
                $conn->commit();
                $transactionStarted = false;

                superAdminTrySubscriptionEmail($userData['email'], $userData['name'], $planDisplayName, $expiresAt);
                $message = 'Subscription updated successfully';

                $sendJson(['success' => true, 'message' => $message]);
            } catch (Throwable $e) {
                 if ($transactionStarted) {
                     $conn->rollback();
                 }

                 error_log('Super admin subscription update failed: ' . $e->getMessage());
                 $sendJson(['error' => 'Failed to update subscription: ' . $e->getMessage()], 500);
             }
    }
    
        $sendJson(['error' => 'Unknown action'], 400);
    } catch (Throwable $e) {
        error_log('Super admin user action failed: ' . $e->getMessage());
        $sendJson(['error' => 'Action failed: ' . $e->getMessage()], 500);
    }
}

// Require super admin access
$user = requireAdminRole('super_admin');

// Get filter parameters
$role = $_GET['role'] ?? 'all';
$verification = $_GET['verification'] ?? 'all';
$subscription = $_GET['subscription'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if ($role !== 'all') {
    $whereConditions[] = "u.role = ?";
    $params[] = $role;
    $paramTypes .= 's';
}

if ($verification !== 'all') {
    $verified = $verification === 'verified' ? 1 : 0;
    $whereConditions[] = "u.verified = ?";
    $params[] = $verified;
    $paramTypes .= 'i';
}

if ($subscription !== 'all') {
    $whereConditions[] = "u.subscription_status = ?";
    $params[] = $subscription;
    $paramTypes .= 's';
}

if (!empty($search)) {
    $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= 'ss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get users with subscription data
$sql = "SELECT u.*, 
               s.id as subscription_id,
               s.status as subscription_status,
               s.plan_id,
               s.expires_at as subscription_expires,
               sp.display_name as current_plan,
               (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id AND p.status = 'completed') as total_payments,
               (SELECT SUM(amount) FROM payments p WHERE p.user_id = u.id AND p.status = 'completed') as total_spent
        FROM users u
        LEFT JOIN user_subscriptions s ON u.id = s.user_id AND s.status = 'active'
        LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
        $whereClause
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$paramTypes .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM users u $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($params) && count($params) > 2) {
    $countParams = array_slice($params, 0, -2);
    $countParamTypes = substr($paramTypes, 0, -2);
    if (!empty($countParams)) {
        $countStmt->bind_param($countParamTypes, ...$countParams);
    }
}
$countStmt->execute();
$totalUsers = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $limit);

// Get user statistics
$userStatsQuery = "SELECT 
                    COUNT(*) as total_users,
                    COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_users,
                    COUNT(CASE WHEN role = 'super_admin' THEN 1 END) as super_admin_users,
                    COUNT(CASE WHEN verified = 1 THEN 1 END) as verified_users,
                    COUNT(CASE WHEN subscription_status != 'free' THEN 1 END) as premium_users
                   FROM users";
$userStats = $conn->query($userStatsQuery)->fetch_assoc();

// Get available plans for the subscription change modal
$plansResult = $conn->query("SELECT id, display_name, name, price FROM subscription_plans ORDER BY price ASC");
$availablePlans = $plansResult->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users Management - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; }
        .welcome-header {
            background: #fff;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-bottom: 1px solid #eee;
        }
        .welcome-header h1 { font-size: 24px; margin-bottom: 5px; color: #333; }
        .welcome-header p { margin: 0; color: #666; font-size: 15px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.05); }
        .card-header { background-color: #fff; border-bottom: 1px solid #f1f1f1; padding: 15px 20px; }
        .card-header h5 { margin: 0; font-weight: 600; color: #333; }
        .table thead th { background-color: #343a40; color: #fff; border: none; font-weight: 500; }
        .badge { font-weight: 500; padding: 6px 10px; border-radius: 6px; }
        .btn-group .btn { margin: 0 2px; }
        .user-action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            min-width: 260px;
        }
        .user-action-buttons .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin: 0;
            border-radius: 6px !important;
            white-space: nowrap;
        }
        @media (max-width: 768px) {
            body { overflow-x: hidden; }
            .welcome-header { padding: 16px 0; margin-bottom: 18px; }
            .welcome-header .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
            }
            .welcome-header h1 {
                font-size: 20px;
                line-height: 1.2;
            }
            .welcome-header .btn {
                width: 100%;
                margin: 0 0 8px 0 !important;
            }
            .container-fluid {
                padding-left: 12px;
                padding-right: 12px;
            }
            .card-header .row,
            .card-body .row {
                row-gap: 12px;
            }
            .card-header .d-flex,
            .card-footer .d-flex {
                flex-direction: column;
                align-items: stretch !important;
                gap: 10px;
            }
            .btn-group,
            .user-action-buttons {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 6px;
                width: 100%;
            }
            .btn-group .btn,
            .user-action-buttons .btn {
                margin: 0;
            }
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .table-responsive > table {
                width: max-content;
                min-width: 100%;
                max-width: none;
                table-layout: auto;
                white-space: nowrap;
            }
            .table-responsive th,
            .table-responsive td {
                white-space: nowrap;
                overflow-wrap: normal;
            }
        }
    </style>
</head>
<body>

<div class="welcome-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>All Users Management</h1>
                <p>Welcome, touseef <span class="badge bg-danger">Super Admin</span></p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-2"><i class="fas fa-arrow-left"></i> Dashboard</a>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($userStats['total_users']) ?></h4>
                            <p class="mb-0">Total Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($userStats['verified_users']) ?></h4>
                            <p class="mb-0">Verified Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($userStats['premium_users']) ?></h4>
                            <p class="mb-0">Premium Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-crown fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($userStats['admin_users'] + $userStats['super_admin_users']) ?></h4>
                            <p class="mb-0">Admin Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-shield fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="all" <?= $role === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>User</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="super_admin" <?= $role === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Verification</label>
                    <select name="verification" class="form-select">
                        <option value="all" <?= $verification === 'all' ? 'selected' : '' ?>>All Users</option>
                        <option value="verified" <?= $verification === 'verified' ? 'selected' : '' ?>>Verified</option>
                        <option value="unverified" <?= $verification === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Subscription</label>
                    <select name="subscription" class="form-select">
                        <option value="all" <?= $subscription === 'all' ? 'selected' : '' ?>>All Types</option>
                        <option value="free" <?= $subscription === 'free' ? 'selected' : '' ?>>Free</option>
                        <option value="premium" <?= $subscription === 'premium' ? 'selected' : '' ?>>Premium</option>
                        <option value="pro" <?= $subscription === 'pro' ? 'selected' : '' ?>>Pro</option>
                    </select>
                </div>
                
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name or Email" 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-users"></i> All Users</h5>
            <div>
                <span class="text-muted">Showing <?= count($users) ?> of <?= $totalUsers ?> users</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>User ID</th>
                            <th>User Details</th>
                            <th>Role</th>
                            <th>Subscription</th>
                            <th>Payment History</th>
                            <th>Registration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No users found matching your criteria</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($users as $userRecord): ?>
                        <tr>
                            <td>
                                <strong>#<?= $userRecord['id'] ?></strong>
                            </td>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($userRecord['name']) ?></strong>
                                    <?php if ($userRecord['verified']): ?>
                                        <i class="fas fa-check-circle text-success" title="Verified"></i>
                                    <?php else: ?>
                                        <i class="fas fa-exclamation-circle text-warning" title="Unverified"></i>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($userRecord['email']) ?></small>
                            </td>
                            <td>
                                <?= getRoleBadge($userRecord['role']) ?>
                            </td>
                            <td>
                                <?php if ($userRecord['current_plan']): ?>
                                    <div>
                                        <span class="badge bg-success"><?= htmlspecialchars($userRecord['current_plan']) ?></span>
                                    </div>
                                    <small class="text-muted">
                                        Expires: <?= date('M d, Y', strtotime($userRecord['subscription_expires'])) ?>
                                    </small>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Free Plan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>
                                    <strong><?= number_format($userRecord['total_payments'] ?? 0) ?></strong> payments
                                </div>
                                <small class="text-muted">
                                    PKR <?= number_format($userRecord['total_spent'] ?? 0, 2) ?> total
                                </small>
                            </td>
                            <td>
                                <div><?= date('M d, Y', strtotime($userRecord['created_at'])) ?></div>
                                <small class="text-muted"><?= date('H:i', strtotime($userRecord['created_at'])) ?></small>
                            </td>
                            <td>
                                <div class="user-action-buttons" role="group" aria-label="User actions for <?= htmlspecialchars($userRecord['email']) ?>">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="showUserDetails(<?= $userRecord['id'] ?>)"
                                            title="View User Details">
                                        <i class="fas fa-eye"></i><span>View</span>
                                    </button>
                                    
                                    <?php if ($userRecord['id'] != $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                            onclick="changeUserRole(<?= $userRecord['id'] ?>, '<?= $userRecord['role'] ?>')"
                                            title="Change User Role">
                                        <i class="fas fa-user-tag"></i><span>Role</span>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-success"
                                            onclick="changeSubscription(<?= $userRecord['id'] ?>, <?= $userRecord['plan_id'] ?? 0 ?>)"
                                            title="Manage Subscription">
                                        <i class="fas fa-crown"></i><span>Plan</span>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-info"
                                            onclick="toggleVerification(<?= $userRecord['id'] ?>, <?= $userRecord['verified'] ? 'true' : 'false' ?>)"
                                            title="<?= $userRecord['verified'] ? 'Unverify User' : 'Verify User' ?>">
                                        <i class="fas fa-<?= $userRecord['verified'] ? 'times' : 'check' ?>"></i><span><?= $userRecord['verified'] ? 'Unverify' : 'Verify' ?></span>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="resetUserPassword(<?= $userRecord['id'] ?>)"
                                            title="Reset Password">
                                        <i class="fas fa-key"></i><span>Reset</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Action Modals -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="actionModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmActionBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentAction = null;
const actionModalElement = document.getElementById('actionModal');
const confirmActionBtn = document.getElementById('confirmActionBtn');

function getActionModal() {
    return bootstrap.Modal.getOrCreateInstance(actionModalElement);
}

function resetConfirmButton() {
    confirmActionBtn.disabled = false;
    confirmActionBtn.innerHTML = 'Confirm';
}

function showUserDetails(userId) {
    document.getElementById('userDetailsContent').innerHTML = 
        '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('userDetailsModal'));
    modal.show();
    
    fetch(`get_user_details.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('userDetailsContent').innerHTML = data.html;
            } else {
                document.getElementById('userDetailsContent').innerHTML = 
                    '<div class="alert alert-danger">Error loading user details: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('userDetailsContent').innerHTML = 
                '<div class="alert alert-danger">Failed to load user details</div>';
        });
}

function changeUserRole(userId, currentRole) {
    currentAction = { type: 'change_role', userId: userId };
    
    document.getElementById('actionModalTitle').textContent = 'Change User Role';
    document.getElementById('actionModalBody').innerHTML = 
        `<p>Change role for user ID <strong>${userId}</strong>?</p>
         <div class="mb-3">
             <label class="form-label">New Role</label>
             <select class="form-select" id="newRole">
                 <option value="user" ${currentRole === 'user' ? 'selected' : ''}>User</option>
                 <option value="admin" ${currentRole === 'admin' ? 'selected' : ''}>Administrator</option>
                 <option value="super_admin" ${currentRole === 'super_admin' ? 'selected' : ''}>Super Administrator</option>
             </select>
         </div>
         <div class="alert alert-warning">
             <i class="fas fa-exclamation-triangle"></i> This will immediately change user permissions.
         </div>`;
    
    resetConfirmButton();
    getActionModal().show();
}

function changeSubscription(userId, currentPlanId) {
    currentAction = { type: 'change_subscription', userId: userId };
    
    const plans = <?= json_encode($availablePlans) ?>;
    let planOptions = '<option value="0">Free Plan (No active subscription)</option>';
    
    plans.forEach(plan => {
        planOptions += `<option value="${plan.id}" ${plan.id == currentPlanId ? 'selected' : ''}>${plan.display_name} (PKR ${plan.price})</option>`;
    });

    document.getElementById('actionModalTitle').textContent = 'Change Subscription';
    document.getElementById('actionModalBody').innerHTML = 
        `<p>Update subscription for user ID <strong>${userId}</strong>?</p>
         <div class="mb-3">
             <label class="form-label">Select Plan</label>
             <select class="form-select" id="newPlanId">
                 ${planOptions}
             </select>
         </div>
         <div class="mb-3">
             <label class="form-label">Duration (Days)</label>
             <input type="number" class="form-control" id="subscriptionDays" value="30" min="1" max="3650">
         </div>
         <div class="alert alert-info">
             <i class="fas fa-info-circle"></i> This will deactivate current active subscriptions and create a new one.
         </div>`;
    
    resetConfirmButton();
    getActionModal().show();
}

function toggleVerification(userId, currentVerified) {
    currentAction = { type: 'toggle_verification', userId: userId };
    
    const action = currentVerified ? 'unverify' : 'verify';
    document.getElementById('actionModalTitle').textContent = `${action.charAt(0).toUpperCase() + action.slice(1)} User`;
    document.getElementById('actionModalBody').innerHTML = 
        `<p>Are you sure you want to ${action} user ID <strong>${userId}</strong>?</p>
         <div class="alert alert-info">
             <i class="fas fa-info-circle"></i> This will ${currentVerified ? 'remove' : 'grant'} verification status.
         </div>`;
    
    resetConfirmButton();
    getActionModal().show();
}

function resetUserPassword(userId) {
    currentAction = { type: 'reset_password', userId: userId };
    
    document.getElementById('actionModalTitle').textContent = 'Reset User Password';
    document.getElementById('actionModalBody').innerHTML = 
        `<p>Reset password for user ID <strong>${userId}</strong>?</p>
         <div class="alert alert-warning">
             <i class="fas fa-exclamation-triangle"></i> A new temporary password will be generated. Make sure to share it with the user securely.
         </div>`;
    
    resetConfirmButton();
    getActionModal().show();
}

confirmActionBtn.addEventListener('click', function() {
    if (!currentAction) return;
    
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
    
    let formData = new FormData();
    formData.append('user_id', currentAction.userId);
    
    let endpoint = '';
    
    switch (currentAction.type) {
        case 'change_role':
            endpoint = 'super_admin_users.php?action=change_role';
            formData.append('new_role', document.getElementById('newRole').value);
            break;
        case 'change_subscription':
            endpoint = 'super_admin_users.php?action=change_subscription';
            formData.append('plan_id', document.getElementById('newPlanId').value);
            formData.append('days', document.getElementById('subscriptionDays').value);
            break;
        case 'toggle_verification':
            endpoint = 'super_admin_users.php?action=toggle_verification';
            break;
        case 'reset_password':
            endpoint = 'super_admin_users.php?action=reset_password';
            break;
    }
    
    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text().then(text => {
        try {
            const data = JSON.parse(text);
            if (!response.ok && !data.error) {
                data.error = 'Request failed with status ' + response.status;
            }
            return data;
        } catch (error) {
            const plainText = text
                .replace(/<[^>]*>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            throw new Error(plainText || 'Server returned an invalid response');
        }
    }))
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = 'Confirm';
        
        if (data.success) {
            if (currentAction.type === 'reset_password' && data.new_password) {
                alert('Password reset successfully!\nNew password: ' + data.new_password + '\n\nPlease share this with the user securely.');
            } else if (currentAction.type === 'change_subscription' && data.message) {
                alert(data.message);
            }
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = 'Confirm';
        alert('Action failed: ' + error.message);
    });
    
    getActionModal().hide();
});
</script>

</body>
</html>
