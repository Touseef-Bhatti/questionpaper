<?php
session_start();
require_once '../db_connect.php';
require_once '../services/PaymentService.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$paymentService = new PaymentService();
$success = false;
$error = null;
$payment = null;

// Check for order_id and payment verification data
$orderId = $_GET['order_id'] ?? null;
$tracker = $_GET['tracker'] ?? $_POST['tracker'] ?? null;
$signature = $_GET['sig'] ?? $_POST['sig'] ?? null;

if ($orderId) {
    // First, check current payment status
    $statusCheck = $paymentService->checkPaymentStatusFromGateway($orderId);
    
    if ($statusCheck['success'] && $statusCheck['status'] === 'completed') {
        // Payment is already completed
        $success = true;
        $payment = $statusCheck['payment'];
    } else if ($tracker && $signature) {
        // Process payment verification with signature
        $result = $paymentService->processSuccessfulPayment($orderId, $tracker, $signature);
        
        if (isset($result['success']) && $result['success']) {
            $success = true;
            $payment = $result['payment'];
        } else {
            $error = $result['error'] ?? 'Payment verification failed';
        }
    } else {
        // Get payment info and wait for verification
        $payment = $paymentService->getPaymentByOrderId($orderId);
        
        if ($payment) {
            switch ($payment['status']) {
                case 'completed':
                    $success = true;
                    break;
                case 'failed':
                    $error = 'Payment failed: ' . ($payment['failure_reason'] ?? 'Unknown reason');
                    break;
                case 'cancelled':
                    $error = 'Payment was cancelled';
                    break;
                default:
                    $error = 'Payment is still being processed. Please wait a moment...';
                    // Mark this as pending for auto-refresh
                    $pending = true;
            }
        } else {
            $error = 'Payment record not found';
        }
    }
} else {
    $error = 'Missing payment information';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $success ? 'Payment Successful' : 'Payment Issue' ?> - Ahmad Learning Hub</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            background: white;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: bounce 0.6s ease-out;
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            background: #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: shake 0.6s ease-out;
        }
        
        .success-icon::before {
            content: '✓';
            color: white;
            font-size: 3rem;
            font-weight: bold;
        }
        
        .error-icon::before {
            content: '✕';
            color: white;
            font-size: 3rem;
            font-weight: bold;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 2rem;
        }
        
        .message {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .payment-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .payment-details h3 {
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: bold;
            color: #495057;
        }
        
        .detail-value {
            color: #333;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% {
                transform: translate3d(0,0,0);
            }
            40%, 43% {
                transform: translate3d(0,-20px,0);
            }
            70% {
                transform: translate3d(0,-10px,0);
            }
            90% {
                transform: translate3d(0,-4px,0);
            }
        }
        
        @keyframes shake {
            0%, 100% {
                transform: translateX(0);
            }
            10%, 30%, 50%, 70%, 90% {
                transform: translateX(-5px);
            }
            20%, 40%, 60%, 80% {
                transform: translateX(5px);
            }
        }
        
        .loading {
            display: none;
            margin: 20px 0;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="success-icon"></div>
            <h1>Payment Successful!</h1>
            <div class="message">
                Thank you for your subscription! Your account has been upgraded and you can now enjoy all the premium features.
            </div>
            
            <?php if ($payment): ?>
            <div class="payment-details">
                <h3>Payment Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Plan:</span>
                    <span class="detail-value"><?= htmlspecialchars($payment['plan_display_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value"><?= $payment['currency'] ?> <?= number_format($payment['amount'], 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Order ID:</span>
                    <span class="detail-value"><?= htmlspecialchars($payment['order_id']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value"><?= date('M d, Y H:i', strtotime($payment['created_at'])) ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="actions">
                <a href="../index.php" class="btn btn-success">Start Creating Papers</a>
                <a href="../subscription.php" class="btn btn-secondary">View Subscription</a>
            </div>
            
        <?php else: ?>
            <div class="error-icon"></div>
            <h1>Payment Issue</h1>
            <div class="message">
                <?php if ($error): ?>
                    <?= htmlspecialchars($error) ?>
                <?php else: ?>
                    There was an issue processing your payment. Please contact support if the problem persists.
                <?php endif; ?>
            </div>
            
            <?php if ($orderId): ?>
            <div class="payment-details">
                <h3>Order Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Order ID:</span>
                    <span class="detail-value"><?= htmlspecialchars($orderId) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span id="statusText">Checking...</span>
                        <div class="loading" id="loadingSpinner">
                            <div class="spinner"></div>
                            <div>Verifying payment status...</div>
                        </div>
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="actions">
                <a href="../subscription.php" class="btn btn-primary">Try Again</a>
                <a href="mailto:support@questionpaper.com" class="btn btn-secondary">Contact Support</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!$success && $orderId): ?>
    <script>
        // Real-time payment status checking with AJAX
        let checkCount = 0;
        const maxChecks = 12; // Check for 2 minutes (12 * 10 seconds)
        const orderId = '<?= htmlspecialchars($orderId, ENT_QUOTES) ?>';
        
        function checkPaymentStatus() {
            if (checkCount >= maxChecks) {
                document.getElementById('statusText').textContent = 'Timeout - Please contact support';
                document.getElementById('loadingSpinner').style.display = 'none';
                return;
            }
            
            const loadingSpinner = document.getElementById('loadingSpinner');
            const statusText = document.getElementById('statusText');
            
            if (loadingSpinner) loadingSpinner.style.display = 'block';
            if (statusText) statusText.textContent = 'Checking payment status...';
            
            fetch('check_status.php?order_id=' + encodeURIComponent(orderId), {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.payment) {
                    const status = data.payment.status;
                    
                    if (status === 'completed') {
                        // Payment successful - reload page to show success
                        window.location.reload();
                    } else if (status === 'failed') {
                        if (statusText) statusText.textContent = 'Payment failed';
                        if (loadingSpinner) loadingSpinner.style.display = 'none';
                        const messageDiv = document.querySelector('.message');
                        if (messageDiv) {
                            messageDiv.innerHTML = 'Payment failed: ' + (data.payment.failure_reason || 'Unknown reason');
                        }
                    } else if (status === 'cancelled') {
                        if (statusText) statusText.textContent = 'Payment cancelled';
                        if (loadingSpinner) loadingSpinner.style.display = 'none';
                    } else {
                        // Still processing, check again
                        checkCount++;
                        setTimeout(checkPaymentStatus, 10000); // Check again in 10 seconds
                    }
                } else {
                    console.error('Payment status check failed:', data.error);
                    checkCount++;
                    setTimeout(checkPaymentStatus, 10000);
                }
            })
            .catch(error => {
                console.error('Error checking payment status:', error);
                checkCount++;
                setTimeout(checkPaymentStatus, 10000);
            });
        }
        
        // Start checking status after 2 seconds
        setTimeout(checkPaymentStatus, 2000);
    </script>
    <?php endif; ?>
</body>
</html>
