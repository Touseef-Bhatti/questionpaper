<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

// Function to safely count table with existence check
function safeCountTable(mysqli $conn, string $table): int {
    $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $res = $conn->query("SELECT COUNT(*) AS c FROM `$table`");
        if ($res && ($row = $res->fetch_assoc())) return (int)$row['c'];
    }
    return 0;
}

// Get comprehensive stats
$stats = [
    'users' => safeCountTable($conn, 'users'),
    'payments' => safeCountTable($conn, 'payments'),
    'active_subscriptions' => 0,
    'total_revenue' => 0,
    'pending_payments' => 0,
    'completed_payments' => 0,
    'failed_payments' => 0
];

// Get subscription stats if table exists
if (safeCountTable($conn, 'user_subscriptions') > 0) {
    $activeSubsResult = $conn->query("SELECT COUNT(*) as count FROM user_subscriptions WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW())");
    if ($activeSubsResult) {
        $stats['active_subscriptions'] = $activeSubsResult->fetch_assoc()['count'];
    }
}

// Get payment stats if table exists
if (safeCountTable($conn, 'payments') > 0) {
    $revenueResult = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
    if ($revenueResult) {
        $stats['total_revenue'] = floatval($revenueResult->fetch_assoc()['total'] ?? 0);
    }
    
    $pendingResult = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status IN ('pending', 'processing')");
    if ($pendingResult) {
        $stats['pending_payments'] = $pendingResult->fetch_assoc()['count'];
    }
    
    $completedResult = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'completed'");
    if ($completedResult) {
        $stats['completed_payments'] = $completedResult->fetch_assoc()['count'];
    }
    
    $failedResult = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'failed'");
    if ($failedResult) {
        $stats['failed_payments'] = $failedResult->fetch_assoc()['count'];
    }
}

// Get recent users
$recentUsers = [];
$userQuery = "SELECT id, name, email, role, created_at, 
              CASE 
                  WHEN role = 'admin' THEN 'Administrator'
                  WHEN role = 'superadmin' THEN 'Super Admin'
                  WHEN role = 'super_admin' THEN 'Super Admin'
                  ELSE 'User'
              END as role_display
              FROM users ORDER BY created_at DESC LIMIT 15";
$userResult = $conn->query($userQuery);
if ($userResult) {
    while ($row = $userResult->fetch_assoc()) {
        $recentUsers[] = $row;
    }
}

// Get recent payments if table exists
$recentPayments = [];
if (safeCountTable($conn, 'payments') > 0) {
    $paymentQuery = "SELECT p.id, p.order_id, p.amount, p.currency, p.status, p.created_at, u.name as user_name, u.email as user_email 
                     FROM payments p 
                     LEFT JOIN users u ON p.user_id = u.id 
                     ORDER BY p.created_at DESC LIMIT 20";
    $paymentResult = $conn->query($paymentQuery);
    if ($paymentResult) {
        while ($row = $paymentResult->fetch_assoc()) {
            $recentPayments[] = $row;
        }
    }
}

// Get user subscription info
$userSubscriptions = [];
if (safeCountTable($conn, 'user_subscriptions') > 0) {
    $subsQuery = "SELECT us.*, sp.display_name as plan_name, u.name as user_name, u.email as user_email
                  FROM user_subscriptions us
                  LEFT JOIN subscription_plans sp ON us.plan_id = sp.id
                  LEFT JOIN users u ON us.user_id = u.id
                  WHERE us.status = 'active'
                  ORDER BY us.created_at DESC LIMIT 15";
    $subsResult = $conn->query($subsQuery);
    if ($subsResult) {
        while ($row = $subsResult->fetch_assoc()) {
            $userSubscriptions[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Overview - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .overview-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .section-card {
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 12px;
            margin-bottom: 32px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .section-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px 24px;
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }
        
        .section-content {
            padding: 24px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .data-table th {
            background: #f8f9fa;
            color: #374151;
            font-weight: 600;
            padding: 12px 16px;
            border-bottom: 2px solid #e1e5e9;
            font-size: 0.9rem;
        }
        
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .role-badge, .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .role-user { background: #e3f2fd; color: #1565c0; }
        .role-admin { background: #fff3e0; color: #ef6c00; }
        .role-superadmin, .role-super_admin { background: #fce4ec; color: #c2185b; }
        
        .status-completed { background: #e8f5e8; color: #2e7d32; }
        .status-pending { background: #fff3e0; color: #f57c00; }
        .status-processing { background: #e3f2fd; color: #1976d2; }
        .status-failed { background: #ffebee; color: #d32f2f; }
        .status-cancelled { background: #f3e5f5; color: #7b1fa2; }
        .status-active { background: #e8f5e8; color: #2e7d32; }
        .status-expired { background: #ffebee; color: #d32f2f; }
        
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .user-name {
            font-weight: 600;
            color: #374151;
        }
        
        .user-email {
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        .order-id {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #1e3c72;
            font-weight: 600;
        }
        
        .amount {
            font-weight: 600;
            color: #059669;
        }
        
        .nav-breadcrumb {
            margin-bottom: 20px;
        }
        
        .nav-breadcrumb a {
            color: #1e3c72;
            text-decoration: none;
            font-weight: 500;
        }
        
        .nav-breadcrumb a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .overview-stats {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 16px;
            }
            
            .data-table {
                font-size: 0.8rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="admin-container">
        <div class="nav-breadcrumb">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        
        <div class="top">
            <h1>📊 System Overview</h1>
            <div>
                <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></strong>
                <a class="logout" href="logout.php">Logout</a>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="overview-stats">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['users']) ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['payments']) ?></div>
                <div class="stat-label">Total Payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">PKR <?= number_format($stats['total_revenue']) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['active_subscriptions']) ?></div>
                <div class="stat-label">Active Subscriptions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['pending_payments']) ?></div>
                <div class="stat-label">Pending Payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['completed_payments']) ?></div>
                <div class="stat-label">Completed Payments</div>
            </div>
        </div>

        <!-- Recent Users Section -->
        <div class="section-card">
            <h2 class="section-header">👤 All Users (Recent 15)</h2>
            <div class="section-content">
                <?php if (!empty($recentUsers)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td>#<?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="role-badge role-<?= strtolower($user['role'] ?? 'user') ?>">
                                        <?= htmlspecialchars($user['role_display']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y H:i', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <a href="get_user_details.php?id=<?= $user['id'] ?>" class="btn-small btn-primary">View Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px; text-align: center;">
                        <a href="users.php" class="btn btn-secondary">View All Users</a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #6b7280;">
                        <p>No users found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Payments Section -->
        <?php if ($stats['payments'] > 0): ?>
        <div class="section-card">
            <h2 class="section-header">💳 Recent Payments (Last 20)</h2>
            <div class="section-content">
                <?php if (!empty($recentPayments)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td class="order-id"><?= htmlspecialchars($payment['order_id']) ?></td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($payment['user_name'] ?? 'Unknown') ?></div>
                                        <div class="user-email"><?= htmlspecialchars($payment['user_email'] ?? '') ?></div>
                                    </div>
                                </td>
                                <td class="amount"><?= $payment['currency'] ?> <?= number_format($payment['amount'], 2) ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($payment['status']) ?>">
                                        <?= ucfirst($payment['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y H:i', strtotime($payment['created_at'])) ?></td>
                                <td>
                                    <a href="get_payment_details.php?id=<?= $payment['id'] ?>" class="btn-small btn-primary">Details</a>
                                    <?php if ($payment['status'] === 'pending'): ?>
                                        <a href="verify_payment.php?order_id=<?= urlencode($payment['order_id']) ?>" class="btn-small btn-warning">Verify</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px; text-align: center;">
                        <a href="super_admin_payments.php" class="btn btn-primary">View All Payments</a>
                        <a href="payment_analytics.php" class="btn btn-secondary">Payment Analytics</a>
                        <a href="payment_health.php" class="btn btn-warning">Payment Health</a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #6b7280;">
                        <p>No payments found.</p>
                        <a href="../subscription.php" class="btn btn-primary">View Subscription Plans</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Subscriptions Section -->
        <?php if (!empty($userSubscriptions)): ?>
        <div class="section-card">
            <h2 class="section-header">📋 Active Subscriptions</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th>Expires</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userSubscriptions as $sub): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-name"><?= htmlspecialchars($sub['user_name'] ?? 'Unknown') ?></div>
                                    <div class="user-email"><?= htmlspecialchars($sub['user_email'] ?? '') ?></div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($sub['plan_name'] ?? 'Unknown Plan') ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower($sub['status']) ?>">
                                    <?= ucfirst($sub['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($sub['started_at'])) ?></td>
                            <td>
                                <?php if ($sub['expires_at']): ?>
                                    <?= date('M j, Y', strtotime($sub['expires_at'])) ?>
                                <?php else: ?>
                                    <span style="color: #28a745; font-weight: 600;">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="get_user_details.php?id=<?= $sub['user_id'] ?>" class="btn-small btn-primary">View User</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="section-card">
            <h2 class="section-header">⚡ Quick Management Actions</h2>
            <div class="section-content">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px; border: 1px solid #e1e5e9; border-radius: 8px;">
                        <h4 style="margin: 0 0 12px; color: #1e3c72;">👥 User Management</h4>
                        <p style="margin: 0 0 16px; color: #6b7280;">Manage all users and admin accounts</p>
                        <a href="users.php" class="btn btn-primary">Manage Users</a>
                    </div>
                    <div style="text-align: center; padding: 20px; border: 1px solid #e1e5e9; border-radius: 8px;">
                        <h4 style="margin: 0 0 12px; color: #1e3c72;">💰 Payment Management</h4>
                        <p style="margin: 0 0 16px; color: #6b7280;">Monitor and manage all payments</p>
                        <a href="super_admin_payments.php" class="btn btn-primary">Manage Payments</a>
                    </div>
                    <div style="text-align: center; padding: 20px; border: 1px solid #e1e5e9; border-radius: 8px;">
                        <h4 style="margin: 0 0 12px; color: #1e3c72;">🔒 Security</h4>
                        <p style="margin: 0 0 16px; color: #6b7280;">System security and audit logs</p>
                        <a href="security.php" class="btn btn-primary">Security Dashboard</a>
                    </div>
                    <div style="text-align: center; padding: 20px; border: 1px solid #e1e5e9; border-radius: 8px;">
                        <h4 style="margin: 0 0 12px; color: #1e3c72;">⚙️ Settings</h4>
                        <p style="margin: 0 0 16px; color: #6b7280;">System configuration and settings</p>
                        <a href="settings.php" class="btn btn-primary">System Settings</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>
