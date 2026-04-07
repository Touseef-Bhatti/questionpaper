<?php
// forgot_password.php - Request password reset link via email
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

// PHPMailer classes for email functionality
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../PHPMailer-master/src/Exception.php';
require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';
require '../config/env.php';
require_once __DIR__ . '/../email/phpmailer_mailer.php';

$submitted = false;
$error = '';

function get_site_url() {
    // Prefer environment-configured URL from .env.local / .env.production
    $defaultUrl = 'https://ahmadlearninghub.com.pk';

    if (class_exists('EnvLoader')) {
        $appUrl = EnvLoader::get('APP_URL', EnvLoader::get('SITE_URL', $defaultUrl));
        $siteUrl = EnvLoader::get('SITE_URL', $appUrl);
        $url = $appUrl ?: $siteUrl ?: $defaultUrl;
    } else {
        $url = $defaultUrl;
    }

    // Trim trailing slash
    return rtrim($url, "/");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email !== '') {
        // Look up user (explicit check)
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

        if (!$uid) {
            $error = "This email address is not registered in our records.";
        } else {
            // Check rate limit: max 3 emails per hour
            $stmt_rl = $conn->prepare("SELECT COUNT(*) AS cnt FROM password_resets WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
            if ($stmt_rl) {
                $stmt_rl->bind_param('i', $uid);
                $stmt_rl->execute();
                $res_rl = $stmt_rl->get_result();
                $row_rl = $res_rl->fetch_assoc();
                $attempts = (int)($row_rl['cnt'] ?? 0);
                $stmt_rl->close();

                if ($attempts >= 3) {
                    $error = "Too many tries try again later";
                }
            }

            if ($error === '') {
                $submitted = true;
                // Basic rate limit: avoid issuing multiple tokens within 5 minutes
                $rl = $conn->prepare("SELECT COUNT(*) AS cnt FROM password_resets WHERE user_id = ? AND used = 0 AND created_at > (NOW() - INTERVAL 5 MINUTE)");
                if ($rl) { 
                    $rl->bind_param('i', $uid); 
                    $rl->execute(); 
                    $r = $rl->get_result(); 
                    $row = $r ? $r->fetch_assoc() : ['cnt'=>0]; 
                    $recent = (int)($row['cnt'] ?? 0); 
                    $rl->close(); 
                } else { 
                    $recent = 0; 
                }
                
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
                        $email_sent = send_reset_email($email, $reset_link);
                    }
                }
                // Cleanup: remove expired tokens
                $conn->query("DELETE FROM password_resets WHERE expires_at <= NOW() OR (used = 1 AND created_at < (NOW() - INTERVAL 30 DAY))");
            }
        }
    } else {
        $error = "Please enter your email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Ahmad Learning Hub</title>
  <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
  <style>
    .card { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 24px; }
    .btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .btn.primary { background: #4f6ef7; color: #fff; }
    .muted { color: #6b7280; }
    label { display:block; margin: 8px 0 6px; font-weight: 600; }
    input[type=email] { width: 100%; padding: 10px; border: 1px solid #dbe1ea; border-radius: 8px; background: #f9fbff; }
    .success { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:10px 12px; border-radius:8px; margin-bottom:12px; }
    .error { background:#fff5f5; border:1px solid #feb2b2; color:#c53030; padding:10px 12px; border-radius:8px; margin-bottom:12px; }
  </style>
</head>
<body>
<?php include '../header.php'; ?>

<br><br><br><br>
<div class="main-content">
  <div class="card">
    <h1 style="margin-top:0;">Forgot Password</h1>
    
    <?php if ($error): ?>
      <div class="error">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($submitted): ?>
      <div class="success">
        A password reset link has been sent to your email.
      </div>
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
