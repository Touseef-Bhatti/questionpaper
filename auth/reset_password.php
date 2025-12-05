<?php
// reset_password.php - Verify token and set a new password
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

$token = $_GET['token'] ?? '';
$token = trim($token);
$token_hash = $token ? hash('sha256', $token) : '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $token_hash = $token ? hash('sha256', $token) : '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($token_hash === '') {
        $error = 'Invalid request.';
    } else {
        // Find valid reset row
        $sql = "SELECT pr.id, pr.user_id FROM password_resets pr WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > NOW() LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $token_hash);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $uid = (int)$row['user_id'];
                // Update user password
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $up = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($up) {
                    $up->bind_param('si', $hash, $uid);
                    $up->execute();
                    $up->close();
                    // Mark token used and optionally invalidate others
                    $conn->query("UPDATE password_resets SET used = 1 WHERE token_hash = '" . $conn->real_escape_string($token_hash) . "'");
                    $conn->query("UPDATE password_resets SET used = 1 WHERE user_id = $uid AND expires_at < NOW()");
                    $success = true;
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            } else {
                $error = 'This reset link is invalid or has expired.';
            }
            $stmt->close();
        } else {
            $error = 'Unable to process request at this time.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - Ahmad Learning Hub</title>
  <link rel="stylesheet" href="../css/main.css">
  <style>
    .card { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 24px; }
    .btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .btn.primary { background: #4f6ef7; color: #fff; }
    .btn.secondary { background: #e9eef8; color: #2d3e50; text-decoration: none; display:inline-block; }
    .muted { color: #6b7280; }
    label { display:block; margin: 8px 0 6px; font-weight: 600; }
    input[type=password] { width: 100%; padding: 10px; border: 1px solid #dbe1ea; border-radius: 8px; background: #f9fbff; }
    .error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:10px 12px; border-radius:8px; margin-bottom:12px; }
    .success { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:10px 12px; border-radius:8px; margin-bottom:12px; }
  </style>
</head>
<body>
<?php include '../header.php'; ?>
<div class="main-content">
  <div class="card">
    <h1 style="margin-top:0;">Reset Password</h1>
    <?php if ($success): ?>
      <div class="success">Your password has been updated. You can now log in.</div>
      <a class="btn secondary" href="login.php">Go to Login</a>
    <?php else: ?>
      <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <form method="POST" action="">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />
        <label for="password">New Password</label>
        <input type="password" id="password" name="password" required minlength="8" />
        <label for="confirm">Confirm Password</label>
        <input type="password" id="confirm" name="confirm" required minlength="8" />
        <div style="margin-top:12px; display:flex; gap:8px;">
          <button class="btn primary" type="submit">Update Password</button>
          <a class="btn secondary" href="login.php">Cancel</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php include '../footer.php'; ?>
</body>
</html>
