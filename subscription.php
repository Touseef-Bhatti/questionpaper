<?php
session_start();
require_once 'db_connect.php';
require_once 'services/SubscriptionService.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$subscriptionService = new SubscriptionService();
$userId = $_SESSION['user_id'];

// Get user's current subscription
$currentSubscription = $subscriptionService->getCurrentSubscription($userId);
$availablePlans = $subscriptionService->getAvailablePlans();
$userLimits = $subscriptionService->getUserLimits($userId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans - QPaperGen</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/header.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .current-plan {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .current-plan h2 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .plan-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .status-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .status-card h3 {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .status-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .plan-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        }
        
        .plan-card.popular {
            border: 3px solid #007bff;
            transform: scale(1.05);
        }
        
        .plan-card.popular::before {
            content: 'Most Popular';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: #007bff;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .plan-card.current {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .plan-card.current .btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .plan-name {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .plan-card.current .plan-name {
            color: white;
        }
        
        .plan-price {
            font-size: 3rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .plan-card.current .plan-price {
            color: white;
        }
        
        .plan-currency {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .plan-card.current .plan-currency {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .plan-period {
            color: #666;
            margin-bottom: 20px;
        }
        
        .plan-card.current .plan-period {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .plan-features {
            list-style: none;
            text-align: left;
            margin-bottom: 30px;
        }
        
        .plan-features li {
            padding: 8px 0;
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
        
        .plan-card.current .plan-features li::before {
            color: rgba(255, 255, 255, 0.8);
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
            text-decoration: none;
            display: inline-block;
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
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: white;
            text-decoration: none;
            font-weight: bold;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .plan-card.popular {
                transform: none;
            }
            
            .plan-status {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="container">
        <a href="profile.php" class="back-link">← Back to Profile</a>
        
        <div class="header">
            <h1>Subscription Plans</h1>
            <p>Choose the perfect plan for your question paper generation needs</p>
        </div>
        
        <?php if ($currentSubscription): ?>
        <div class="current-plan">
            <h2>Your Current Plan: <?= htmlspecialchars($currentSubscription['display_name']) ?></h2>
            <div class="plan-status">
                <div class="status-card">
                    <h3>Papers Used</h3>
                    <div class="status-value">
                        <?= $userLimits['papers_used_this_month'] ?> / 
                        <?= $userLimits['max_papers_per_month'] == -1 ? '∞' : $userLimits['max_papers_per_month'] ?>
                    </div>
                </div>
                <div class="status-card">
                    <h3>Chapters Limit</h3>
                    <div class="status-value">
                        <?= $userLimits['max_chapters_per_paper'] == -1 ? 'Unlimited' : $userLimits['max_chapters_per_paper'] ?>
                    </div>
                </div>
                <div class="status-card">
                    <h3>Questions Limit</h3>
                    <div class="status-value">
                        <?= $userLimits['max_questions_per_paper'] == -1 ? 'Unlimited' : $userLimits['max_questions_per_paper'] ?>
                    </div>
                </div>
                <?php if ($userLimits['expires_at']): ?>
                <div class="status-card">
                    <h3>Expires</h3>
                    <div class="status-value">
                        <?= date('M d, Y', strtotime($userLimits['expires_at'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="plans-grid">
            <?php foreach ($availablePlans as $index => $plan): ?>
            <div class="plan-card <?= $index === 1 ? 'popular' : '' ?> <?= $currentSubscription['plan_name'] === $plan['name'] ? 'current' : '' ?>">
                <div class="plan-name"><?= htmlspecialchars($plan['display_name']) ?></div>
                <div class="plan-price">
                    <?php if ($plan['price'] == 0): ?>
                        Free
                    <?php else: ?>
                        <?= number_format($plan['price'], 0) ?>
                        <span class="plan-currency">PKR</span>
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
                
                <?php if ($currentSubscription['plan_name'] === $plan['name']): ?>
                    <button class="btn btn-success" disabled>Current Plan</button>
                <?php elseif ($plan['price'] == 0): ?>
                    <button class="btn" disabled>Default Plan</button>
                <?php else: ?>
                    <a href="payment/checkout.php?plan_id=<?= $plan['id'] ?>" class="btn">
                        Choose Plan
                    </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
   
    <?php include 'footer.php'; ?>
</body>
</html>
