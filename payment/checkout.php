<?php
session_start();
require_once '../db_connect.php';
require_once '../services/PaymentService.php';
require_once '../services/SubscriptionService.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Check if plan_id is provided
if (!isset($_GET['plan_id']) || !is_numeric($_GET['plan_id'])) {
    header('Location: ../subscription.php?error=invalid_plan');
    exit;
}

$userId = $_SESSION['user_id'];
$planId = intval($_GET['plan_id']);

$paymentService = new PaymentService();
$subscriptionService = new SubscriptionService();

// Get plan details
$plan = $subscriptionService->getPlanById($planId);
if (!$plan) {
    header('Location: ../subscription.php?error=plan_not_found');
    exit;
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Security validation failed. Please try again.';
    } else {
        // Additional validation
        $userEmail = trim($_POST['user_email'] ?? '');
        $userName = trim($_POST['user_name'] ?? '');
        
        if (empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } else if (empty($userName) || strlen($userName) < 2) {
            $error = 'Please provide a valid name.';
        } else {
            // Check if user already has a pending payment for this plan
            $existingPayment = $paymentService->getUserPendingPayment($userId, $planId);
            if ($existingPayment) {
                $error = 'You already have a pending payment for this plan. Please complete or cancel it first.';
            } else {
                // Process payment
                $result = $paymentService->createPaymentOrder($userId, $planId);
                
                if (isset($result['success']) && $result['success']) {
                    // Store payment info in session for tracking
                    $_SESSION['payment_order_id'] = $result['order_id'];
                    $_SESSION['payment_id'] = $result['payment_id'];
                    
                    // Log payment initiation
                    error_log("Payment initiated - User: $userId, Plan: $planId, Order: {$result['order_id']}");
                    
                    // Redirect to SafePay checkout
                    header('Location: ' . $result['checkout_url']);
                    exit;
                } else {
                    $error = $result['error'] ?? 'Payment processing failed. Please try again.';
                    error_log("Payment creation failed - User: $userId, Plan: $planId, Error: " . ($result['error'] ?? 'Unknown'));
                }
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/checkout.css">
    <?php include '../header.php'; ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?= htmlspecialchars($plan['display_name']) ?></title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
        }
        
        .plan-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .plan-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .plan-price {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        
        .plan-period {
            color: #666;
            margin-bottom: 20px;
        }
        
        .plan-features {
            list-style: none;
        }
        
        .plan-features li {
            padding: 5px 0;
            position: relative;
            padding-left: 25px;
        }
        
        .plan-features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }
        
        .payment-form {
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .security-notice {
            background: #e8f5e8;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        
        .security-notice h3 {
            color: #28a745;
            margin-bottom: 10px;
        }
        
        .security-notice p {
            color: #666;
            margin: 0;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .payment-methods {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .payment-method {
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 0.9rem;
            color: #666;
            border: 1px solid #e9ecef;
        }
        
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
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
            
            .plan-price {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../subscription.php" class="back-link">← Back to Plans</a>
        
        <div class="header">
            <h1>Complete Your Purchase</h1>
            <p>You're about to upgrade to our premium service</p>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="error">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <div class="plan-summary">
            <div class="plan-name"><?= htmlspecialchars($plan['display_name']) ?></div>
            <div class="plan-price">
                <?php if ($plan['price'] > 0): ?>
                    PKR <?= number_format($plan['price'], 2) ?>
                <?php else: ?>
                    Free
                <?php endif; ?>
            </div>
            <div class="plan-period">
                <?= $plan['duration_days'] == 365 ? 'per year' : 'per month' ?>
            </div>
            
            <ul class="plan-features">
                <?php foreach ($plan['features'] as $feature): ?>
                <li><?= htmlspecialchars($feature) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="security-notice">
            <h3>🔒 Secure Payment</h3>
            <p>Your payment is processed securely through SafePay. We don't store your payment information on our servers.</p>
        </div>
        
        <div class="payment-methods">
            <span class="payment-method">💳 Credit Card</span>
            <span class="payment-method">🏦 Bank Transfer</span>
            <span class="payment-method">📱 Mobile Wallet</span>
            <span class="payment-method">💰 EasyPaisa</span>
        </div>
        
        <form class="payment-form" method="POST" id="checkoutForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label for="user_email">Email Address</label>
                <input type="email" id="user_email" name="user_email" class="form-control" 
                       value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="user_name">Full Name</label>
                <input type="text" id="user_name" name="user_name" class="form-control" 
                       value="<?= htmlspecialchars($_SESSION['name'] ?? '') ?>" readonly>
            </div>
            
            <button type="submit" class="btn" id="submitBtn">
                <span class="spinner" id="spinner"></span>
                <span id="btnText">Proceed to Payment</span>
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <small style="color: #666;">
                By proceeding, you agree to our Terms of Service and Privacy Policy.<br>
                Your subscription will start immediately after successful payment.
            </small>
        </div>
    </div>
    
    <script>
        document.getElementById('checkoutForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            const spinner = document.getElementById('spinner');
            const btnText = document.getElementById('btnText');
            
            // Show loading state
            submitBtn.disabled = true;
            spinner.style.display = 'inline-block';
            btnText.textContent = 'Processing...';
            
            // Re-enable after 30 seconds as fallback
            setTimeout(function() {
                submitBtn.disabled = false;
                spinner.style.display = 'none';
                btnText.textContent = 'Proceed to Payment';
            }, 30000);
        });
    </script>
</body>
</html>
