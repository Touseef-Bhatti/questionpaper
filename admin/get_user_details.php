<?php
/**
 * User Details API
 * Returns detailed user information for admin panel
 */

require_once '../includes/admin_auth.php';

// Require super admin access
requireSuperAdmin();

header('Content-Type: application/json');

$userId = $_GET['id'] ?? null;
if (!$userId || !is_numeric($userId)) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

require_once '../db_connect.php';

// Get detailed user information
$sql = "SELECT u.*,
               s.id as active_subscription_id,
               s.status as subscription_status,
               s.plan_id as active_plan_id,
               s.starts_at as subscription_start,
               s.expires_at as subscription_expires,
               sp.display_name as active_plan_name,
               sp.price as active_plan_price
        FROM users u
        LEFT JOIN user_subscriptions s ON u.id = s.user_id AND s.status = 'active'
        LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();

// Get user's payment history
$paymentSql = "SELECT p.*, sp.display_name as plan_name
               FROM payments p
               JOIN subscription_plans sp ON p.plan_id = sp.id
               WHERE p.user_id = ?
               ORDER BY p.created_at DESC
               LIMIT 10";
$paymentStmt = $conn->prepare($paymentSql);
$paymentStmt->bind_param("i", $userId);
$paymentStmt->execute();
$payments = $paymentStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's subscription history
$subscriptionSql = "SELECT s.*, sp.display_name as plan_name
                    FROM user_subscriptions s
                    JOIN subscription_plans sp ON s.plan_id = sp.id
                    WHERE s.user_id = ?
                    ORDER BY s.created_at DESC
                    LIMIT 5";
$subscriptionStmt = $conn->prepare($subscriptionSql);
$subscriptionStmt->bind_param("i", $userId);
$subscriptionStmt->execute();
$subscriptions = $subscriptionStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate user stats
$userStatsSql = "SELECT 
                 COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
                 COUNT(*) as total_payments,
                 SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_spent,
                 MAX(CASE WHEN status = 'completed' THEN created_at END) as last_payment_date
                 FROM payments WHERE user_id = ?";
$userStatsStmt = $conn->prepare($userStatsSql);
$userStatsStmt->bind_param("i", $userId);
$userStatsStmt->execute();
$userStats = $userStatsStmt->get_result()->fetch_assoc();

// Generate HTML
ob_start();
?>

<div class="row">
    <!-- User Information -->
    <div class="col-md-6">
        <h6 class="fw-bold mb-3"><i class="fas fa-user"></i> User Information</h6>
        <table class="table table-sm table-borderless">
            <tr>
                <td class="fw-bold">User ID:</td>
                <td>#<?= $user['id'] ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Name:</td>
                <td><?= htmlspecialchars($user['name']) ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Email:</td>
                <td><?= htmlspecialchars($user['email']) ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Role:</td>
                <td><?= getRoleBadge($user['role']) ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Verification:</td>
                <td>
                    <?php if ($user['verified']): ?>
                        <span class="badge bg-success"><i class="fas fa-check"></i> Verified</span>
                    <?php else: ?>
                        <span class="badge bg-warning"><i class="fas fa-exclamation"></i> Unverified</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="fw-bold">Registered:</td>
                <td><?= date('M d, Y H:i', strtotime($user['created_at'])) ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Token:</td>
                <td><?= $user['token'] ? '<small class="font-monospace">' . substr($user['token'], 0, 12) . '...</small>' : '<em class="text-muted">None</em>' ?></td>
            </tr>
        </table>
    </div>
    
    <!-- Subscription & Payment Stats -->
    <div class="col-md-6">
        <h6 class="fw-bold mb-3"><i class="fas fa-chart-bar"></i> Statistics</h6>
        <table class="table table-sm table-borderless">
            <tr>
                <td class="fw-bold">Current Plan:</td>
                <td>
                    <?php if ($user['active_plan_name']): ?>
                        <span class="badge bg-success"><?= htmlspecialchars($user['active_plan_name']) ?></span>
                        <small class="d-block text-muted">PKR <?= number_format($user['active_plan_price'], 2) ?></small>
                    <?php else: ?>
                        <span class="badge bg-secondary">Free Plan</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($user['subscription_expires']): ?>
            <tr>
                <td class="fw-bold">Subscription Expires:</td>
                <td>
                    <?= date('M d, Y', strtotime($user['subscription_expires'])) ?>
                    <?php
                    $daysLeft = floor((strtotime($user['subscription_expires']) - time()) / 86400);
                    if ($daysLeft > 0) {
                        echo "<small class=\"text-success\">($daysLeft days left)</small>";
                    } else {
                        echo "<small class=\"text-danger\">(Expired)</small>";
                    }
                    ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="fw-bold">Total Payments:</td>
                <td>
                    <strong><?= number_format($userStats['total_payments']) ?></strong> 
                    <small class="text-muted">(<?= number_format($userStats['successful_payments']) ?> successful)</small>
                </td>
            </tr>
            <tr>
                <td class="fw-bold">Total Spent:</td>
                <td><strong>PKR <?= number_format($userStats['total_spent'], 2) ?></strong></td>
            </tr>
            <?php if ($userStats['last_payment_date']): ?>
            <tr>
                <td class="fw-bold">Last Payment:</td>
                <td><?= date('M d, Y', strtotime($userStats['last_payment_date'])) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Payment History -->
<div class="row mt-4">
    <div class="col-12">
        <h6 class="fw-bold mb-3"><i class="fas fa-history"></i> Recent Payment History</h6>
        <?php if (empty($payments)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No payment history found for this user.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Order ID</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td>#<?= $payment['id'] ?></td>
                        <td><small><?= htmlspecialchars($payment['order_id']) ?></small></td>
                        <td><?= htmlspecialchars($payment['plan_name']) ?></td>
                        <td><?= $payment['currency'] ?> <?= number_format($payment['amount'], 2) ?></td>
                        <td>
                            <?php
                            $statusColors = [
                                'completed' => 'success', 'processing' => 'warning', 'pending' => 'info',
                                'failed' => 'danger', 'cancelled' => 'secondary', 'expired' => 'dark', 'refunded' => 'warning'
                            ];
                            $statusColor = $statusColors[$payment['status']] ?? 'light';
                            ?>
                            <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($payment['status']) ?></span>
                        </td>
                        <td><?= date('M d, Y H:i', strtotime($payment['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Subscription History -->
<div class="row mt-4">
    <div class="col-12">
        <h6 class="fw-bold mb-3"><i class="fas fa-crown"></i> Subscription History</h6>
        <?php if (empty($subscriptions)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No subscription history found for this user.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Subscription ID</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Period</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $subscription): ?>
                    <tr>
                        <td>#<?= $subscription['id'] ?></td>
                        <td><?= htmlspecialchars($subscription['plan_name']) ?></td>
                        <td>
                            <span class="badge bg-<?= $subscription['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($subscription['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?= date('M d, Y', strtotime($subscription['starts_at'])) ?> - 
                            <?= date('M d, Y', strtotime($subscription['expires_at'])) ?>
                        </td>
                        <td><?= date('M d, Y', strtotime($subscription['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$html = ob_get_clean();
echo json_encode(['success' => true, 'html' => $html]);
?>
