<?php
// Admin Payment Verification Interface
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

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = trim($_POST['order_id'] ?? '');
    $action = $_POST['action'] ?? '';
    
    if (empty($orderId)) {
        $error = 'Please provide an order ID';
    } else {
        switch ($action) {
            case 'verify':
                $result = $paymentService->manualVerifyPayment($orderId, $_SESSION['admin_id']);
                if ($result['success']) {
                    $message = 'Payment verified successfully!';
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'cancel':
                $result = $paymentService->cancelPayment($orderId, 'Manually cancelled by admin');
                if ($result['success']) {
                    $message = 'Payment cancelled successfully!';
                } else {
                    $error = $result['error'];
                }
                break;
        }
    }
}

// Get recent payments
$recentPayments = $paymentService->getPaymentStatistics(7); // Last 7 days
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification - Admin</title>
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
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #007bff;
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
        
        .verify-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
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
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
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
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="header">
            <h1>Payment Verification</h1>
            <p>Manually verify or cancel payments when needed</p>
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
                <div class="stat-value"><?= $recentPayments['total_payments'] ?? 0 ?></div>
                <div class="stat-label">Total Payments (7 days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $recentPayments['successful_payments'] ?? 0 ?></div>
                <div class="stat-label">Successful</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $recentPayments['failed_payments'] ?? 0 ?></div>
                <div class="stat-label">Failed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">PKR <?= number_format($recentPayments['total_revenue'] ?? 0, 0) ?></div>
                <div class="stat-label">Revenue</div>
            </div>
        </div>
        
        <div class="verify-form">
            <h3>Manual Payment Verification</h3>
            <p style="margin-bottom: 20px; color: #666;">
                Use this only when a payment has been confirmed through SafePay dashboard but not processed automatically.
            </p>
            
            <form method="POST">
                <div class="form-group">
                    <label for="order_id">Order ID:</label>
                    <input type="text" id="order_id" name="order_id" class="form-control" 
                           placeholder="e.g., QPG_1693123456_123_2" required>
                </div>
                
                <button type="submit" name="action" value="verify" class="btn btn-primary">
                    ✅ Verify Payment
                </button>
                <button type="submit" name="action" value="cancel" class="btn btn-danger" 
                        onclick="return confirm('Are you sure you want to cancel this payment?')">
                    ❌ Cancel Payment
                </button>
            </form>
        </div>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px;">
            <h4 style="color: #856404; margin-bottom: 10px;">⚠️ Important Notes:</h4>
            <ul style="color: #856404; margin-left: 20px;">
                <li>Only verify payments that you have confirmed in the SafePay dashboard</li>
                <li>Cancelling a payment cannot be undone</li>
                <li>Check the SafePay dashboard before taking any action</li>
                <li>All actions are logged for audit purposes</li>
            </ul>
        </div>
    </div>
</body>
</html>
