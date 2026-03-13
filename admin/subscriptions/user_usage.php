<?php
/**
 * admin/subscriptions/user_usage.php - Manage User Quotas and Usage
 */

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../services/SubscriptionService.php';

// Check admin access
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../header.php';

$subService = new SubscriptionService($conn);

// Handle Quota Reset or Adjustment (AJAX or POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = intval($_POST['user_id']);
    $action = $_POST['action'];
    
    if ($action === 'reset_daily') {
        // Delete today's paper generation records for this user
        $stmt = $conn->prepare("DELETE FROM usage_tracking WHERE user_id = ? AND action = 'paper_generated' AND DATE(created_at) = CURDATE()");
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            $success_msg = "Daily quota reset successfully for user ID: $userId";
        }
    }
}

// Search and Pagination
$search = $_GET['search'] ?? '';
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$where = "";
if ($search) {
    $search_safe = $conn->real_escape_string($search);
    $where = "WHERE u.name LIKE '%$search_safe%' OR u.email LIKE '%$search_safe%'";
}

// Fetch Users with their current subscription and daily usage
$sql = "SELECT u.id, u.name, u.email, u.subscription_status, u.subscription_expires_at,
               sp.display_name as plan_name, sp.questionPaperPerDay,
               (SELECT COUNT(*) FROM usage_tracking ut 
                WHERE ut.user_id = u.id AND ut.action = 'paper_generated' 
                AND DATE(ut.created_at) = CURDATE()) as used_today,
               (SELECT MAX(created_at) FROM usage_tracking 
                WHERE user_id = u.id AND action = 'paper_generated') as last_generated,
               (SELECT COUNT(*) FROM user_generated_papers_log 
                WHERE user_id = u.id) as total_papers
        FROM users u
        LEFT JOIN user_subscriptions us ON u.id = us.user_id AND us.status = 'active' 
             AND (us.expires_at IS NULL OR us.expires_at > NOW())
        LEFT JOIN subscription_plans sp ON us.plan_id = sp.id
        $where
        ORDER BY 
            (CASE WHEN u.subscription_status != 'free' THEN 1 ELSE 2 END) ASC,
            (SELECT MAX(created_at) FROM usage_tracking WHERE user_id = u.id AND action = 'paper_generated') DESC,
            u.id DESC
        LIMIT $limit OFFSET $offset";

$result = $conn->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);

// Count total for pagination
$total_result = $conn->query("SELECT COUNT(*) FROM users u $where");
$total_users = $total_result->fetch_row()[0];
$total_pages = ceil($total_users / $limit);

?>

<div class="container-fluid mt-4">
    <style>
        .user-usage-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem;
        }
        .user-usage-table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f5;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #e9ecef;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #495057;
            flex-shrink: 0;
        }
        .usage-progress-container {
            min-width: 120px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-dot-active { background-color: #28a745; box-shadow: 0 0 5px rgba(40, 167, 69, 0.5); }
        .status-dot-inactive { background-color: #adb5bd; }
        
        .plan-badge {
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .plan-free { background: #f1f3f5; color: #495057; border: 1px solid #dee2e6; }
        .plan-premium { background: #e7f5ff; color: #007bff; border: 1px solid #a5d8ff; }
        .plan-pro { background: #fff4e6; color: #fd7e14; border: 1px solid #ffd8a8; }
        
        .action-btn {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .card { border-radius: 15px; overflow: hidden; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">📊 User Usage & Quotas</h1>
            <p class="text-muted small">Monitor real-time paper generation and manage daily limits</p>
        </div>
        <div class="d-flex gap-3">
            <form action="" method="GET" class="position-relative">
                <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                <input type="text" name="search" class="form-control ps-5 rounded-pill" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>" style="width: 300px;">
            </form>
        </div>
    </div>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover user-usage-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>User Details</th>
                        <th>Subscription Plan</th>
                        <th>Status</th>
                        <th>Daily Quota</th>
                        <th>Total Papers</th>
                        <th>Last Activity</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php 
                            $limit = $user['questionPaperPerDay'] ?? 1;
                            if ($user['subscription_status'] === 'free' && !$user['plan_name']) {
                                $limit = 1;
                            }
                            $used = $user['used_today'];
                            $remaining = ($limit == -1) ? '∞' : max(0, $limit - $used);
                            $usage_pct = ($limit == -1) ? 0 : ($used / $limit) * 100;
                            $usage_color = $usage_pct >= 90 ? '#fa5252' : ($usage_pct >= 70 ? '#fd7e14' : '#40c057');
                            
                            $plan_class = 'plan-free';
                            $plan_icon = 'fas fa-user';
                            if (strpos(strtolower($user['plan_name'] ?? ''), 'premium') !== false) {
                                $plan_class = 'plan-premium';
                                $plan_icon = 'fas fa-star';
                            } elseif (strpos(strtolower($user['plan_name'] ?? ''), 'pro') !== false) {
                                $plan_class = 'plan-pro';
                                $plan_icon = 'fas fa-crown';
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($user['name']) ?></div>
                                        <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($user['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="plan-badge <?= $plan_class ?>">
                                    <i class="<?= $plan_icon ?>"></i> <?= htmlspecialchars($user['plan_name'] ?: 'Free Plan') ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="status-dot <?= $user['subscription_status'] !== 'free' ? 'status-dot-active' : 'status-dot-inactive' ?>"></span>
                                    <span class="small fw-medium text-capitalize"><?= $user['subscription_status'] ?></span>
                                </div>
                                <?php if ($user['subscription_expires_at']): ?>
                                    <div class="text-muted" style="font-size: 0.7rem;">Exp: <?= date('M d, Y', strtotime($user['subscription_expires_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="usage-progress-container">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-bold small"><?= $used ?> / <?= $limit == -1 ? '∞' : $limit ?></span>
                                        <span class="text-muted small"><?= $remaining === '∞' ? 'Unlimited' : $remaining . ' Left' ?></span>
                                    </div>
                                    <div class="progress" style="height: 6px; background-color: #f1f3f5;">
                                        <div class="progress-bar" style="width: <?= min(100, $usage_pct) ?>%; background-color: <?= $usage_color ?>;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-center">
                                    <span class="badge rounded-pill bg-light text-dark border px-3"><?= $user['total_papers'] ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="small">
                                    <?php if ($user['last_generated']): ?>
                                        <div class="text-dark fw-medium"><?= date('M d, h:i A', strtotime($user['last_generated'])) ?></div>
                                        <div class="text-muted smaller">Last Generation</div>
                                    <?php else: ?>
                                        <span class="text-muted">No activity</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <form action="" method="POST" onsubmit="return confirm('Reset daily quota for this user?')">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="reset_daily">
                                        <button type="submit" class="btn btn-outline-warning action-btn" title="Reset Daily Quota">
                                            <i class="fas fa-redo-alt fa-xs"></i>
                                        </button>
                                    </form>
                                    <a href="../super_admin_users.php?search=<?= urlencode($user['email']) ?>" class="btn btn-outline-primary action-btn" title="Manage Subscription">
                                        <i class="fas fa-cog fa-xs"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white py-3">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
