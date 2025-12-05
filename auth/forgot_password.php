<?php
// forgot_password.php - Request password reset link via email
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

$submitted = false;

function get_site_url() {
    // Prefer environment-configured URL for production
    if (class_exists('EnvLoader')) {
        $url = EnvLoader::get('APP_URL', EnvLoader::get('SITE_URL', 'https://paper.bhattichemicalsindustry.com.pk'));
    } else {
        $url = 'https://paper.bhattichemicalsindustry.com.pk';
    }
    // Trim trailing slash
    return rtrim($url, "/");
}

function send_reset_email($to, $reset_link) {
    $subject = 'Reset your Ahmad Learning Hub password';
    $message = "Hello,\n\nWe received a request to reset your password. Click the link below to set a new password:\n\n$reset_link\n\nIf you didn't request this, please ignore this email.\n\nThanks,\nAhmad Learning Hub";
    // From header using configured domain
    $fromDomain = parse_url(get_site_url(), PHP_URL_HOST) ?: 'Ahmad Learning Hub.local';
    $headers = 'From: Ahmad Learning Hub <no-reply@' . $fromDomain . ">\r\n" . 'Reply-To: no-reply@' . $fromDomain . "\r\n" . 'X-Mailer: PHP/' . phpversion();
    // Attempt to send via mail(); production servers typically configure this
    @mail($to, $subject, $message, $headers);
    // Also log to file for troubleshooting
    $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'password_reset_links.log', date('c') . "\t" . $to . "\t" . $reset_link . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;
    $email = trim($_POST['email'] ?? '');
    if ($email !== '') {
        // Look up user silently (no user enumeration)
        $uid = null;
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $uid = (int)$row['id'];
            }
            $stmt->close();
        }
        if ($uid) {
            // Basic rate limit: avoid issuing multiple tokens within 5 minutes
            $rl = $conn->prepare("SELECT COUNT(*) AS cnt FROM password_resets WHERE user_id = ? AND used = 0 AND created_at > (NOW() - INTERVAL 5 MINUTE)");
            if ($rl) { $rl->bind_param('i', $uid); $rl->execute(); $r = $rl->get_result(); $row = $r ? $r->fetch_assoc() : ['cnt'=>0]; $recent = (int)($row['cnt'] ?? 0); $rl->close(); } else { $recent = 0; }
            if ($recent === 0) {
                // Create token
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                // Expiry minutes (default 3) from env if available
                $expiryMinutes = 3;
                if (class_exists('EnvLoader')) {
                    $m = (int)EnvLoader::get('PASSWORD_RESET_EXP_MIN', 3);
                    if ($m > 0) { $expiryMinutes = $m; }
                }
                // Insert row with DB-side NOW() to avoid timezone mismatch
                $sql = "INSERT INTO password_resets (user_id, token_hash, expires_at, used) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL $expiryMinutes MINUTE), 0)";
                $ins = $conn->prepare($sql);
                if ($ins) {
                    $ins->bind_param('is', $uid, $token_hash);
                    $ins->execute();
                    $ins->close();
                    // Build absolute URL based on configured SITE/APP URL
$reset_link = get_site_url() . '/auth/reset_password.php?token=' . urlencode($token);
                    send_reset_email($email, $reset_link);
                }
            }
            // Cleanup: remove expired tokens
            $conn->query("DELETE FROM password_resets WHERE expires_at <= NOW() OR (used = 1 AND created_at < (NOW() - INTERVAL 30 DAY))");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Ahmad Learning Hub</title>
  <link rel="stylesheet" href="../css/main.css">
  <style>
    .card { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 24px; }
    .btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .btn.primary { background: #4f6ef7; color: #fff; }
    .muted { color: #6b7280; }
    label { display:block; margin: 8px 0 6px; font-weight: 600; }
    input[type=email] { width: 100%; padding: 10px; border: 1px solid #dbe1ea; border-radius: 8px; background: #f9fbff; }
    .success { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:10px 12px; border-radius:8px; margin-bottom:12px; }
  </style>
</head>
<body>
<?php include '../header.php'; ?>
<div class="main-content">
  <div class="card">
    <h1 style="margin-top:0;">Forgot Password</h1>
    <?php if ($submitted): ?>
      <div class="success">If an account with that email exists, we have sent a password reset link.</div>
    <?php endif; ?>
    <p class="muted">Enter your account email and we will send you a link to reset your password.</p>
    <form method="POST" action="">
      <label for="email">Email</label>
      <input type="email" name="email" id="email" required />
      <div style="margin-top:12px; display:flex; gap:8px;">
        <button class="btn primary" type="submit">Send Reset Link</button>
        <a class="btn" style="background:#e9eef8; color:#2d3e50; text-decoration:none;" href="login.php">Back to Login</a>
      </div>
    </form>
  </div>
</div>
<?php include '../footer.php'; ?>
</body>
</html>
