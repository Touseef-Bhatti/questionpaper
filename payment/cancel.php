<?php
session_start();
require_once '../db_connect.php';
require_once '../services/PaymentService.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$orderId = $_GET['order_id'] ?? null;
$paymentService = new PaymentService();

// Cancel the payment if order_id is provided
if ($orderId) {
    $paymentService->cancelPayment($orderId, 'User cancelled payment');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - QPaperGen</title>
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
            max-width: 500px;
            background: white;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        }
        
        .cancel-icon {
            width: 80px;
            height: 80px;
            background: #ffc107;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }
        
        .cancel-icon::before {
            content: '⚠';
            color: white;
            font-size: 3rem;
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
        <div class="cancel-icon"></div>
        <h1>Payment Cancelled</h1>
        <div class="message">
            You cancelled the payment process. No charges have been made to your account.
            <?php if ($orderId): ?>
            <br><br>
            <strong>Order ID:</strong> <?= htmlspecialchars($orderId) ?>
            <?php endif; ?>
        </div>
        
        <div class="actions">
            <a href="../subscription.php" class="btn btn-primary">View Plans Again</a>
            <a href="../index.php" class="btn btn-secondary">Go to Home</a>
        </div>
    </div>
</body>
</html>
