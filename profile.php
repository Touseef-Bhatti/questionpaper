<?php
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);
$sql = "SELECT name, email, created_at FROM users WHERE id = $user_id";
$result = $conn->query($sql);
$user = $result ? $result->fetch_assoc() : null;



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Ahmad Learning Hub</title>
    <link rel="stylesheet" href="css/profile.css">
    <style>
        .quota-info-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        html.dark-mode .quota-info-card {
            background: #1e293b;
            border-color: #334155;
        }
        .quota-info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .quota-icon {
            font-size: 1.5rem;
            color: #4f46e5;
            background: #eef2ff;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            flex-shrink: 0;
        }
        html.dark-mode .quota-icon {
            background: rgba(79, 70, 229, 0.1);
            color: #818cf8;
        }
        .quota-details {
            flex-grow: 1;
        }
        .quota-details h4 {
            margin: 0;
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .quota-details .quota-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin: 2px 0;
        }
        html.dark-mode .quota-details .quota-value {
            color: #f8fafc;
        }
        .quota-meta {
            font-size: 0.85rem;
            color: #94a3b8;
            margin: 0;
        }
        .profile-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        html.dark-mode .profile-section-title {
            color: #f8fafc;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="main-content">
  <div class="profile-content">
    <h2>Your Profile</h2>

    <?php if ($user): ?>
      <div class="profile-info">
        <div class="info">
          <span class="label">Name:</span>
          <span class="value"><?= htmlspecialchars($user['name']) ?></span>
        </div>
        <div class="info">
          <span class="label">Email:</span>
          <span class="value"><?= htmlspecialchars($user['email']) ?></span>
        </div>
        <div class="info">
          <span class="label">Member Since:</span>
          <span class="value"><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
        </div>
      </div>

      <!-- Subscription & Usage Section -->
      <?php
      if (file_exists(__DIR__ . '/middleware/SubscriptionCheck.php')) {
          require_once __DIR__ . '/middleware/SubscriptionCheck.php';
          $subInfo = getSubscriptionInfo($_SESSION['user_id']);
          if ($subInfo): ?>
            <h3 class="profile-section-title"><i class="fas fa-crown"></i> Membership & Quota</h3>
            
            <div class="quota-info-card">
              <div class="quota-icon">
                <i class="fas fa-file-invoice"></i>
              </div>
              <div class="quota-details">
                <h4>Daily Paper Quota</h4>
                <div class="quota-value">
                  <?= $subInfo['papers_remaining'] === -1 ? 'Unlimited' : $subInfo['papers_remaining'] . ' Left Today' ?>
                </div>
                <p class="quota-meta">Used: <?= $subInfo['papers_used_today'] ?> / <?= $subInfo['papers_limit'] == -1 ? '∞' : $subInfo['papers_limit'] ?></p>
              </div>
              <div class="quota-action">
                <a href="subscription.php" class="btn-sm-upgrade" style="font-size: 0.8rem; color: #4f46e5; text-decoration: none; font-weight: 600;">Upgrade</a>
              </div>
            </div>
            
            <div class="quota-info-card">
              <div class="quota-icon">
                <i class="fas fa-id-card"></i>
              </div>
              <div class="quota-details">
                <h4>Current Subscription</h4>
                <div class="quota-value"><?= htmlspecialchars($subInfo['plan_name']) ?></div>
                <p class="quota-meta"><?= $subInfo['expires_at'] ? 'Valid Until: ' . date('M d, Y', strtotime($subInfo['expires_at'])) : 'Lifetime Plan' ?></p>
              </div>
            </div>
          <?php endif;
      } ?>
    <?php else: ?>
      <div class="info">Profile not found.</div>
    <?php endif; ?>

    <div class="actions-row">
      <a href="index.php" class="back-home">← Back to Home</a>
      <a href="profile_questions.php" class="quiz-dashboard-btn" style="background: #4f46e5;">Manage Saved Questions</a>
      <a href="quiz/online_quiz_dashboard.php" class="quiz-dashboard-btn">Open Quiz Dashboard</a>
      <a href="auth/logout.php" class="logout-btn">Logout</a>
    </div>

  </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
