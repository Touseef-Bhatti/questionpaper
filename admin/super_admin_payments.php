<?php
/**
 * Super Admin - All Payments Management
 * Comprehensive payment management interface for super administrators
 */

require_once '../includes/admin_auth.php';
require_once '../services/PaymentService.php';

// Require super admin access
$user = adminPageHeader('All Payments Management', 'super_admin');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $paymentService = new PaymentService();
    $action = $_GET['action'];
    
    switch ($action) {
        case 'verify_payment':
            $orderId = $_POST['order_id'] ?? '';
            if (empty($orderId)) {
                echo json_encode(['error' => 'Order ID required']);
                exit;
            }
            
            $result = $paymentService->manualVerifyPayment($orderId, $user['id']);
            echo json_encode($result);
            exit;
            
        case 'cancel_payment':
            $orderId = $_POST['order_id'] ?? '';
            $reason = $_POST['reason'] ?? 'Admin cancellation';
            
            if (empty($orderId)) {
                echo json_encode(['error' => 'Order ID required']);
                exit;
            }
            
            $result = $paymentService->cancelPayment($orderId, $reason);
            echo json_encode($result);
            exit;
            
        case 'process_refund':
            $orderId = $_POST['order_id'] ?? '';
            $amount = $_POST['amount'] ?? null;
            $reason = $_POST['reason'] ?? 'Admin refund';
            
            if (empty($orderId)) {
                echo json_encode(['error' => 'Order ID required']);
                exit;
            }
            
            $result = $paymentService->processRefund($orderId, $amount, $reason, $user['id']);
            echo json_encode($result);
            exit;
    }
    
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$planId = $_GET['plan_id'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if ($status !== 'all') {
    $whereConditions[] = "p.status = ?";
    $params[] = $status;
    $paramTypes .= 's';
}

if ($planId !== 'all') {
    $whereConditions[] = "p.plan_id = ?";
    $params[] = $planId;
    $paramTypes .= 'i';
}

$whereConditions[] = "DATE(p.created_at) BETWEEN ? AND ?";
$params[] = $dateFrom;
$params[] = $dateTo;
$paramTypes .= 'ss';

if (!empty($search)) {
    $whereConditions[] = "(p.order_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= 'sss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get payments with pagination
$sql = "SELECT p.*, 
               sp.display_name as plan_name,
               u.name as user_name, 
               u.email as user_email,
               u.role as user_role,
               s.status as subscription_status
        FROM payments p
        JOIN subscription_plans sp ON p.plan_id = sp.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN user_subscriptions s ON p.subscription_id = s.id
        $whereClause
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$paramTypes .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total
             FROM payments p
             JOIN subscription_plans sp ON p.plan_id = sp.id
             JOIN users u ON p.user_id = u.id
             $whereClause";

$countStmt = $conn->prepare($countSql);
if (!empty($params) && count($params) > 2) {
    // Remove limit and offset from params for count query
    $countParams = array_slice($params, 0, -2);
    $countParamTypes = substr($paramTypes, 0, -2);
    if (!empty($countParams)) {
        $countStmt->bind_param($countParamTypes, ...$countParams);
    }
}
$countStmt->execute();
$totalPayments = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalPayments / $limit);

// Get subscription plans for filter
$plansQuery = "SELECT id, display_name FROM subscription_plans ORDER BY price";
$plansResult = $conn->query($plansQuery);
$plans = $plansResult->fetch_all(MYSQLI_ASSOC);

// Get payment statistics
$paymentService = new PaymentService();
$stats = $paymentService->getPaymentStatistics(30);

adminNavigation();
?>

<div class="container-fluid">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($stats['total_payments'] ?? 0) ?></h4>
                            <p class="mb-0">Total Payments (30d)</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-credit-card fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($stats['successful_payments'] ?? 0) ?></h4>
                            <p class="mb-0">Successful Payments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($stats['failed_payments'] ?? 0) ?></h4>
                            <p class="mb-0">Failed Payments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4>PKR <?= number_format($stats['total_revenue'] ?? 0, 2) ?></h4>
                            <p class="mb-0">Total Revenue</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-money-bill-wave fa-2x"></i>
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
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="refunded" <?= $status === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Plan</label>
                    <select name="plan_id" class="form-select">
                        <option value="all">All Plans</option>
                        <?php foreach ($plans as $plan): ?>
                        <option value="<?= $plan['id'] ?>" <?= $planId == $plan['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($plan['display_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Order ID, User name, Email" 
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

    <!-- Payments Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-credit-card"></i> All Payments</h5>
            <div>
                <span class="text-muted">Showing <?= count($payments) ?> of <?= $totalPayments ?> payments</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Payment ID</th>
                            <th>Order ID</th>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No payments found matching your criteria</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td>
                                <strong>#<?= $payment['id'] ?></strong>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($payment['order_id']) ?></small>
                            </td>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($payment['user_name']) ?></strong>
                                    <?= getRoleBadge($payment['user_role']) ?>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($payment['user_email']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info"><?= htmlspecialchars($payment['plan_name']) ?></span>
                            </td>
                            <td>
                                <strong><?= $payment['currency'] ?> <?= number_format($payment['amount'], 2) ?></strong>
                            </td>
                            <td>
                                <?php if ($payment['payment_method']): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($payment['payment_method']) ?></span>
                                <?php else: ?>
                                    <small class="text-muted">Not recorded</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusColors = [
                                    'completed' => 'success',
                                    'processing' => 'warning', 
                                    'pending' => 'info',
                                    'failed' => 'danger',
                                    'cancelled' => 'secondary',
                                    'expired' => 'dark',
                                    'refunded' => 'warning'
                                ];
                                $statusColor = $statusColors[$payment['status']] ?? 'light';
                                ?>
                                <span class="badge bg-<?= $statusColor ?> status-badge">
                                    <?= ucfirst($payment['status']) ?>
                                </span>
                                
                                <?php if ($payment['retry_count'] > 0): ?>
                                <small class="d-block text-warning">
                                    <i class="fas fa-redo"></i> Retried <?= $payment['retry_count'] ?>x
                                </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= date('M d, Y', strtotime($payment['created_at'])) ?></div>
                                <small class="text-muted"><?= date('H:i', strtotime($payment['created_at'])) ?></small>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="showPaymentDetails(<?= $payment['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if ($payment['status'] === 'processing'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success"
                                            onclick="verifyPayment('<?= htmlspecialchars($payment['order_id']) ?>')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($payment['status'], ['pending', 'processing'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="cancelPayment('<?= htmlspecialchars($payment['order_id']) ?>')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($payment['status'] === 'completed'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                            onclick="processRefund('<?= htmlspecialchars($payment['order_id']) ?>', <?= $payment['amount'] ?>)">
                                        <i class="fas fa-undo"></i>
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

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="paymentDetailsContent">
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

function showPaymentDetails(paymentId) {
    document.getElementById('paymentDetailsContent').innerHTML = 
        '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    const modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
    modal.show();
    
    fetch(`get_payment_details.php?id=${paymentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('paymentDetailsContent').innerHTML = data.html;
            } else {
                document.getElementById('paymentDetailsContent').innerHTML = 
                    '<div class="alert alert-danger">Error loading payment details: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('paymentDetailsContent').innerHTML = 
                '<div class="alert alert-danger">Failed to load payment details</div>';
        });
}

function verifyPayment(orderId) {
    currentAction = { type: 'verify', orderId: orderId };
    
    document.getElementById('actionModalTitle').textContent = 'Verify Payment';
    document.getElementById('actionModalBody').innerHTML = 
        `<p>Are you sure you want to manually verify payment for order <strong>${orderId}</strong>?</p>
         <div class="alert alert-warning">
             <i class="fas fa-exclamation-triangle"></i> Only verify if you've confirmed this payment was successful in SafePay dashboard.
         </div>`;
    
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    modal.show();
}

function cancelPayment(orderId) {
    currentAction = { type: 'cancel', orderId: orderId };
    
    document.getElementById('actionModalTitle').textContent = 'Cancel Payment';
    document.getElementById('actionModalBody').innerHTML = 
        `<p>Are you sure you want to cancel payment for order <strong>${orderId}</strong>?</p>
         <div class="mb-3">
             <label class="form-label">Cancellation Reason</label>
             <textarea class="form-control" id="cancelReason" placeholder="Enter reason for cancellation"></textarea>
         </div>`;
    
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    modal.show();
}

function processRefund(orderId, amount) {
    currentAction = { type: 'refund', orderId: orderId, maxAmount: amount };
    
    document.getElementById('actionModalTitle').textContent = 'Process Refund';
    document.getElementById('actionModalBody').innerHTML = 
        `<p>Process refund for order <strong>${orderId}</strong>?</p>
         <div class="mb-3">
             <label class="form-label">Refund Amount</label>
             <div class="input-group">
                 <span class="input-group-text">PKR</span>
                 <input type="number" class="form-control" id="refundAmount" 
                        min="0.01" max="${amount}" step="0.01" value="${amount}">
             </div>
         </div>
         <div class="mb-3">
             <label class="form-label">Refund Reason</label>
             <textarea class="form-control" id="refundReason" placeholder="Enter reason for refund"></textarea>
         </div>
         <div class="alert alert-warning">
             <i class="fas fa-exclamation-triangle"></i> This will cancel the associated subscription.
         </div>`;
    
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    modal.show();
}

document.getElementById('confirmActionBtn').addEventListener('click', function() {
    if (!currentAction) return;
    
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
    
    let formData = new FormData();
    formData.append('order_id', currentAction.orderId);
    
    let endpoint = '';
    
    switch (currentAction.type) {
        case 'verify':
            endpoint = 'super_admin_payments.php?action=verify_payment';
            break;
        case 'cancel':
            endpoint = 'super_admin_payments.php?action=cancel_payment';
            formData.append('reason', document.getElementById('cancelReason').value || 'Admin cancellation');
            break;
        case 'refund':
            endpoint = 'super_admin_payments.php?action=process_refund';
            formData.append('amount', document.getElementById('refundAmount').value);
            formData.append('reason', document.getElementById('refundReason').value || 'Admin refund');
            break;
    }
    
    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = 'Confirm';
        
        if (data.success) {
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
    
    bootstrap.Modal.getInstance(document.getElementById('actionModal')).hide();
});
</script>

</body>
</html>
