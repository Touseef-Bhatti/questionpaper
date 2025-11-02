<?php
// Payment Refunds Management Interface
session_start();
require_once '../db_connect.php';
require_once '../services/PaymentService.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$paymentService = new PaymentService();
$message = null;
$error = null;

// Handle refund processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = trim($_POST['order_id'] ?? '');
    $refundAmount = floatval($_POST['refund_amount'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if (empty($orderId)) {
        $error = 'Please provide an order ID';
    } elseif ($refundAmount <= 0) {
        $error = 'Please provide a valid refund amount';
    } elseif (empty($reason)) {
        $error = 'Please provide a reason for the refund';
    } else {
        $result = $paymentService->processRefund($orderId, $refundAmount, $reason, $_SESSION['admin_id']);
        
        if ($result['success']) {
            $message = 'Refund processed successfully! Refund ID: ' . $result['refund_id'];
        } else {
            $error = $result['error'];
        }
    }
}

// Get recent refunds
$sql = "SELECT pr.*, p.order_id, p.amount as original_amount, u.name as user_name, u.email as user_email,
               sp.display_name as plan_name
        FROM payment_refunds pr
        JOIN payments p ON pr.payment_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN subscription_plans sp ON p.plan_id = sp.id
        ORDER BY pr.created_at DESC LIMIT 50";
$recentRefunds = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get refund statistics
$sql = "SELECT 
            COUNT(*) as total_refunds,
            SUM(amount) as total_refund_amount,
            COUNT(CASE WHEN status = 'processed' THEN 1 END) as processed_refunds,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_refunds
        FROM payment_refunds 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$refundStats = $conn->query($sql)->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Refunds - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9fa;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #dc3545;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .refund-form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-danger { background: #dc3545; color: white; }
        .btn-primary { background: #007bff; color: white; }
        .btn:hover { transform: translateY(-2px); }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .refunds-table {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        .status-processed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="payment_analytics.php" class="back-link">← Back to Analytics</a>
        
        <div class="header">
            <h1>💸 Payment Refunds</h1>
            <p>Process refunds and manage refund requests</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($refundStats['total_refunds'] ?? 0) ?></div>
                <div class="stat-label">Total Refunds (30 days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">PKR <?= number_format($refundStats['total_refund_amount'] ?? 0) ?></div>
                <div class="stat-label">Refund Amount</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($refundStats['processed_refunds'] ?? 0) ?></div>
                <div class="stat-label">Processed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($refundStats['pending_refunds'] ?? 0) ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        
        <div class="refund-form">
            <h3>Process New Refund</h3>
            <p style="margin-bottom: 20px; color: #666;">
                ⚠️ Refunds will automatically cancel the associated subscription and deactivate user access.
            </p>
            
            <form method="POST" onsubmit="return confirm('Are you sure you want to process this refund? This action cannot be undone.')">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="order_id">Order ID:</label>
                        <input type="text" id="order_id" name="order_id" class="form-control" 
                               placeholder="e.g., QPG_1693123456_123_2" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="refund_amount">Refund Amount (PKR):</label>
                        <input type="number" id="refund_amount" name="refund_amount" class="form-control" 
                               step="0.01" min="0.01" placeholder="0.00" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="reason">Refund Reason:</label>
                    <textarea id="reason" name="reason" class="form-control" rows="3" 
                              placeholder="Describe the reason for this refund..." required></textarea>
                </div>
                
                <button type="submit" class="btn btn-danger">
                    💸 Process Refund
                </button>
                
                <button type="button" class="btn btn-primary" onclick="lookupPayment()" style="margin-left: 10px;">
                    🔍 Lookup Payment Details
                </button>
            </form>
        </div>
        
        <div class="refunds-table">
            <h3>Recent Refunds</h3>
            
            <?php if (empty($recentRefunds)): ?>
                <p style="text-align: center; color: #666; margin-top: 20px;">No refunds processed yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>User</th>
                                <th>Plan</th>
                                <th>Original Amount</th>
                                <th>Refund Amount</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRefunds as $refund): ?>
                            <tr>
                                <td><?= htmlspecialchars($refund['order_id']) ?></td>
                                <td>
                                    <?= htmlspecialchars($refund['user_name']) ?><br>
                                    <small style="color: #666;"><?= htmlspecialchars($refund['user_email']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($refund['plan_name']) ?></td>
                                <td>PKR <?= number_format($refund['original_amount'], 2) ?></td>
                                <td>PKR <?= number_format($refund['amount'], 2) ?></td>
                                <td><?= htmlspecialchars(substr($refund['reason'], 0, 50)) ?><?= strlen($refund['reason']) > 50 ? '...' : '' ?></td>
                                <td>
                                    <span class="status-badge status-<?= $refund['status'] ?>">
                                        <?= ucfirst($refund['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($refund['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function lookupPayment() {
            const orderId = document.getElementById('order_id').value;
            if (!orderId) {
                alert('Please enter an Order ID first');
                return;
            }
            
            fetch(`../payment/check_status.php?order_id=${encodeURIComponent(orderId)}`, {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.payment) {
                    const payment = data.payment;
                    document.getElementById('refund_amount').value = payment.amount;
                    
                    alert(`Payment Details:
Order ID: ${payment.order_id}
Plan: ${payment.plan_name}
Amount: ${payment.currency} ${payment.amount}
Status: ${payment.status}
Date: ${new Date(payment.created_at).toLocaleDateString()}`);
                } else {
                    alert('Payment not found or error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error looking up payment details');
            });
        }
    </script>
</body>
</html>
