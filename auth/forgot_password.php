<?php
// forgot_password.php - Request password reset link via email
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

// PHPMailer classes for email functionality
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';
require '../config/env.php';

$submitted = false;

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

function send_reset_email($to, $reset_link) {
    try {
        // Use PHPMailer with MailHog configuration
        require_once __DIR__ . '/../email/phpmailer_mailer.php';

        $mail = new PHPMailer(true);

        // Server settings for MailHog
        $mail->isSMTP();
        $mail->Host       = EnvLoader::get('SMTP_HOST', 'mailhog');
        $mail->SMTPAuth   = false; // MailHog doesn't require authentication
        $mail->Username   = '';
        $mail->Password   = '';

        // Handle SSL vs TLS vs none
        $smtpSecure = EnvLoader::get('SMTP_SECURE', 'none');
        if (strtolower($smtpSecure) === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (strtolower($smtpSecure) === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = ''; // No encryption for MailHog
            $mail->SMTPAutoTLS = false;
        }

        $mail->Port       = EnvLoader::getInt('SMTP_PORT', 1025);

        // Recipients
        $fromEmail = EnvLoader::get('SMTP_FROM_EMAIL', 'test@ahmadlearninghub.com.pk');
        $fromName = EnvLoader::get('APP_NAME', 'Ahmad Learning Hub');
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Reset your password - ' . $fromName;

        // Professional HTML email template for password reset
        $htmlMessage = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
</head>
<body style="margin: 0; padding: 0; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
    <table width="100%" cellspacing="0" cellpadding="0" border="0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 60px 40px; text-align: center; position: relative;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 36px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3); z-index: 1; position: relative;">🔐 Password Reset</h1>
                            <p style="color: #ffffff; margin: 15px 0 0 0; font-size: 18px; opacity: 0.95; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Secure your account</p>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 50px 40px;">
                            <h2 style="color: #2d3748; margin: 0 0 25px 0; font-size: 28px; font-weight: 600;">Reset Your Password</h2>

                            <p style="color: #4a5568; font-size: 16px; line-height: 1.7; margin: 0 0 25px 0;">We received a request to reset your password for your <strong style="color: #4f6ef7;">' . htmlspecialchars($fromName) . '</strong> account. Don\'t worry, it happens to the best of us!</p>

                            <p style="color: #4a5568; font-size: 16px; line-height: 1.7; margin: 0 0 35px 0;">Click the button below to securely reset your password. This link will expire in 1 hour for your security.</p>

                            <!-- Reset Password Button -->
                            <table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 40px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="' . $reset_link . '" target="_blank" rel="noopener noreferrer" style="
                                            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                                            color: #ffffff !important;
                                            padding: 16px 32px;
                                            text-decoration: none !important;
                                            border-radius: 12px;
                                            font-weight: 600;
                                            font-size: 16px;
                                            display: inline-block;
                                            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
                                            transition: all 0.3s ease;
                                            text-align: center;
                                            min-width: 200px;
                                        ">🔑 Reset My Password</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #666; font-size: 14px; line-height: 1.5; margin: 30px 0 10px 0;">If the button doesn\'t work, copy and paste this link into your browser:</p>
                            <p style="color: #dc3545; font-size: 14px; word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 0 0 30px 0; border-left: 4px solid #dc3545;">' . $reset_link . '</p>

                            <!-- Security Notice -->
                            <div style="background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%); border-radius: 12px; padding: 25px; margin: 30px 0; border-left: 4px solid #dc3545;">
                                <h4 style="color: #c53030; margin: 0 0 15px 0; font-size: 18px; font-weight: 600;">🛡️ Security Notice</h4>
                                <ul style="color: #4a5568; font-size: 15px; line-height: 1.6; margin: 0; padding-left: 20px;">
                                    <li>This password reset link will expire in 1 hour</li>
                                    <li>Only click this link if you requested a password reset</li>
                                    <li>Never share this link with anyone</li>
                                    <li>If you didn\'t request this reset, please ignore this email</li>
                                </ul>
                            </div>

                            <!-- Footer -->
                            <hr style="border: none; height: 1px; background: linear-gradient(90deg, #e2e8f0 0%, #cbd5e0 50%, #e2e8f0 100%); margin: 40px 0;">
                            <p style="color: #718096; font-size: 14px; line-height: 1.6; margin: 0 0 10px 0; text-align: center;">Need help? Contact our support team at <a href="mailto:support@bhattichemicalsindustry.com.pk" style="color: #4f6ef7; text-decoration: none;">support@bhattichemicalsindustry.com.pk</a></p>
                            <p style="color: #a0aec0; font-size: 12px; margin: 0; text-align: center;">© ' . date('Y') . ' ' . htmlspecialchars($fromName) . '. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

        $mail->Body    = $htmlMessage;
        $mail->AltBody = "Hello,\n\nWe received a request to reset your password. Click the link below to set a new password:\n\n$reset_link\n\nIf you didn't request this, please ignore this email.\n\nThanks,\n" . $fromName;

        $mail->send();
        error_log('Password reset email sent successfully to: ' . $to);
        return true;

    } catch (Exception $e) {
        error_log('Password reset email failed for: ' . $to . ' - Error: ' . $e->getMessage());
        // Fallback to basic mail function if PHPMailer fails
        $subject = 'Reset your ' . EnvLoader::get('APP_NAME', 'Ahmad Learning Hub') . ' password';
        $message = "Hello,\n\nWe received a request to reset your password. Click the link below to set a new password:\n\n$reset_link\n\nIf you didn't request this, please ignore this email.\n\nThanks,\n" . EnvLoader::get('APP_NAME', 'Ahmad Learning Hub');
        $fromDomain = parse_url(EnvLoader::get('APP_URL', 'http://localhost:8000'), PHP_URL_HOST) ?: 'localhost';
        $headers = 'From: ' . EnvLoader::get('APP_NAME', 'Ahmad Learning Hub') . ' <no-reply@' . $fromDomain . ">\r\n" . 'Reply-To: no-reply@' . $fromDomain . "\r\n" . 'X-Mailer: PHP/' . phpversion();
        @mail($to, $subject, $message, $headers);
        error_log('Fallback password reset email sent to: ' . $to);
        return false;
    }
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
  <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
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
