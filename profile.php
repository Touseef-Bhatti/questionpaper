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
