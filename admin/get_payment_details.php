<?php
/**
 * Payment Details API
 * Returns detailed payment information for admin panel
 */

require_once '../includes/admin_auth.php';

// Require super admin access
requireSuperAdmin();

header('Content-Type: application/json');

$paymentId = $_GET['id'] ?? null;
if (!$paymentId || !is_numeric($paymentId)) {
    echo json_encode(['error' => 'Invalid payment ID']);
    exit;
}

require_once '../db_connect.php';

// Get detailed payment information
$sql = "SELECT p.*, 
               sp.display_name as plan_name, 
               sp.price as plan_price,
               sp.duration_days,
               u.name as user_name, 
               u.email as user_email,
               u.role as user_role,
               u.created_at as user_registered,
               s.id as subscription_id,
               s.status as subscription_status,
               s.started_at as subscription_start,
               s.expires_at as subscription_end
        FROM payments p
        JOIN subscription_plans sp ON p.plan_id = sp.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN user_subscriptions s ON p.subscription_id = s.id
        WHERE p.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $paymentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Payment not found']);
    exit;
}

$payment = $result->fetch_assoc();

// Get refund information if exists
$refundSql = "SELECT * FROM payment_refunds WHERE payment_id = ? ORDER BY created_at DESC";
$refundStmt = $conn->prepare($refundSql);
$refundStmt->bind_param("i", $paymentId);
$refundStmt->execute();
$refunds = $refundStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Generate HTML
ob_start();
?>

<div class="row">
    <!-- Payment Information -->
    <div class="col-md-6">
        <h6 class="fw-bold mb-3"><i class="fas fa-credit-card"></i> Payment Information</h6>
        <table class="table table-sm table-borderless">
            <tr>
                <td class="fw-bold">Payment ID:</td>
                <td>#<?= $payment['id'] ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Order ID:</td>
                <td><code><?= htmlspecialchars($payment['order_id']) ?></code></td>
            </tr>
            <tr>
                <td class="fw-bold">Status:</td>
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
            </tr>
            <tr>
                <td class="fw-bold">Amount:</td>
                <td><strong><?= $payment['currency'] ?> <?= number_format($payment['amount'], 2) ?></strong></td>
            </tr>
            <tr>
                <td class="fw-bold">Payment Method:</td>
                <td><?= $payment['payment_method'] ? htmlspecialchars($payment['payment_method']) : '<em class="text-muted">Not recorded</em>' ?></td>
            </tr>
            <tr>
                <td class="fw-bold">SafePay Token:</td>
                <td><?= $payment['safepay_token'] ? '<small class="font-monospace">' . substr($payment['safepay_token'], 0, 20) . '...</small>' : '<em class="text-muted">None</em>' ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Tracker:</td>
                <td><?= $payment['tracker'] ? htmlspecialchars($payment['tracker']) : '<em class="text-muted">None</em>' ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Retry Count:</td>
                <td>
                    <?= $payment['retry_count'] ?? 0 ?>
                    <?php if ($payment['retry_count'] > 0): ?>
                    <small class="text-warning"><i class="fas fa-redo"></i></small>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- User & Plan Information -->
    <div class="col-md-6">
        <h6 class="fw-bold mb-3"><i class="fas fa-user"></i> User & Plan Information</h6>
        <table class="table table-sm table-borderless">
            <tr>
                <td class="fw-bold">User:</td>
                <td>
                    <strong><?= htmlspecialchars($payment['user_name']) ?></strong>
                    <?php
                    $roleBadges = [
                        'user' => '<span class="badge badge-secondary">User</span>',
                        'admin' => '<span class="badge badge-warning">Admin</span>',
                        'super_admin' => '<span class="badge badge-danger">Super Admin</span>'
                    ];
                    echo $roleBadges[$payment['user_role']] ?? '';
                    ?>
                </td>
            </tr>
            <tr>
                <td class="fw-bold">Email:</td>
                <td><?= htmlspecialchars($payment['user_email']) ?></td>
            </tr>
            <tr>
                <td class="fw-bold">User Registered:</td>
                <td><?= date('M d, Y', strtotime($payment['user_registered'])) ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Plan:</td>
                <td><span class="badge bg-info"><?= htmlspecialchars($payment['plan_name']) ?></span></td>
            </tr>
            <tr>
                <td class="fw-bold">Plan Duration:</td>
                <td><?= $payment['duration_days'] ?> days</td>
            </tr>
            <?php if ($payment['subscription_id']): ?>
            <tr>
                <td class="fw-bold">Subscription:</td>
                <td>
                    #<?= $payment['subscription_id'] ?>
                    <span class="badge bg-<?= $payment['subscription_status'] === 'active' ? 'success' : 'secondary' ?>">
                        <?= ucfirst($payment['subscription_status']) ?>
                    </span>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Timestamps -->
<div class="row mt-3">
    <div class="col-12">
        <h6 class="fw-bold mb-3"><i class="fas fa-clock"></i> Timeline</h6>
        <div class="timeline">
            <div class="timeline-item">
                <i class="fas fa-plus-circle text-info"></i>
                <strong>Created:</strong> <?= date('M d, Y H:i:s', strtotime($payment['created_at'])) ?>
            </div>
            <?php if ($payment['processed_at']): ?>
            <div class="timeline-item">
                <i class="fas fa-check-circle text-success"></i>
                <strong>Processed:</strong> <?= date('M d, Y H:i:s', strtotime($payment['processed_at'])) ?>
            </div>
            <?php endif; ?>
            <?php if ($payment['last_retry_at']): ?>
            <div class="timeline-item">
                <i class="fas fa-redo text-warning"></i>
                <strong>Last Retry:</strong> <?= date('M d, Y H:i:s', strtotime($payment['last_retry_at'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Technical Details -->
<div class="row mt-3">
    <div class="col-12">
        <h6 class="fw-bold mb-3"><i class="fas fa-code"></i> Technical Details</h6>
        
        <?php if ($payment['safepay_response']): ?>
        <div class="mb-3">
            <h6>SafePay Response:</h6>
            <pre class="bg-light p-3 rounded"><code><?= htmlspecialchars($payment['safepay_response']) ?></code></pre>
        </div>
        <?php endif; ?>
        
        <?php if ($payment['webhook_data']): ?>
        <div class="mb-3">
            <h6>Webhook Data:</h6>
            <pre class="bg-light p-3 rounded"><code><?= htmlspecialchars($payment['webhook_data']) ?></code></pre>
        </div>
        <?php endif; ?>
        
        <?php if ($payment['failure_reason']): ?>
        <div class="mb-3">
            <h6>Failure Reason:</h6>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($payment['failure_reason']) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Refunds -->
<?php if (!empty($refunds)): ?>
<div class="row mt-3">
    <div class="col-12">
        <h6 class="fw-bold mb-3"><i class="fas fa-undo"></i> Refunds</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Refund ID</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($refunds as $refund): ?>
                    <tr>
                        <td>#<?= $refund['id'] ?></td>
                        <td>PKR <?= number_format($refund['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($refund['reason'] ?? 'No reason provided') ?></td>
                        <td><span class="badge bg-warning"><?= ucfirst($refund['status']) ?></span></td>
                        <td><?= date('M d, Y H:i', strtotime($refund['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.timeline-item {
    padding: 0.5rem 0;
    border-left: 2px solid #e9ecef;
    padding-left: 1.5rem;
    position: relative;
    margin-left: 0.75rem;
}

.timeline-item i {
    position: absolute;
    left: -0.6rem;
    background: white;
    padding: 0.2rem;
}

pre {
    max-height: 300px;
    overflow-y: auto;
    font-size: 0.8rem;
}
</style>

<?php
$html = ob_get_clean();
echo json_encode(['success' => true, 'html' => $html]);
?>
