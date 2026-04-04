<?php
// Test MailHog email functionality
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/email/phpmailer_mailer.php';

$message = '';

if (isset($_POST['send_test'])) {
    $testEmail = trim($_POST['email'] ?? 'test@example.com');
    $emailType = $_POST['email_type'] ?? 'verification';

    try {
        $result = false;

        switch ($emailType) {
            case 'verification':
                $result = sendVerificationEmail($testEmail, 'test-token-123');
                $message = $result ? 'Verification email sent successfully!' : 'Failed to send verification email';
                break;
            case 'welcome':
                $result = sendWelcomeEmail($testEmail, 'Test User');
                $message = $result ? 'Welcome email sent successfully!' : 'Failed to send welcome email';
                break;
            case 'password_reset':
                $resetLink = EnvLoader::get('APP_URL', 'http://localhost:8000') . '/auth/reset_password.php?token=test-token';
                // Use the send_reset_email function from forgot_password.php
                require_once '../auth/forgot_password.php';
                $result = send_reset_email($testEmail, $resetLink);
                $message = $result ? 'Password reset email sent successfully!' : 'Failed to send password reset email';
                break;
        }

        $message .= '<br><br><a href="http://localhost:8025" target="_blank">📧 View emails in MailHog</a>';

    } catch (Exception $e) {
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test MailHog Emails - Ahmad Learning Hub</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .container { background: #f5f5f5; padding: 30px; border-radius: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #4f6ef7; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #3b5bdb; }
        .message { margin-top: 20px; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 20px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test MailHog Email Functionality</h1>

        <div class="info">
            <h3>MailHog Configuration:</h3>
            <ul>
                <li><strong>SMTP Host:</strong> <?= htmlspecialchars(EnvLoader::get('SMTP_HOST', 'mailhog')) ?></li>
                <li><strong>SMTP Port:</strong> <?= htmlspecialchars(EnvLoader::get('SMTP_PORT', '1025')) ?></li>
                <li><strong>Web Interface:</strong> <a href="http://localhost:8025" target="_blank">http://localhost:8025</a></li>
                <li><strong>From Email:</strong> <?= htmlspecialchars(getMailerFromAddress()) ?></li>
            </ul>
            <p><strong>Note:</strong> Make sure Docker containers are running with <code>docker compose --env-file config/.env.local up -d</code></p>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="email">Test Email Address:</label>
                <input type="email" id="email" name="email" value="test@example.com" required>
            </div>

            <div class="form-group">
                <label for="email_type">Email Type:</label>
                <select id="email_type" name="email_type" required>
                    <option value="verification">User Registration Verification</option>
                    <option value="welcome">Welcome Email (After Verification)</option>
                    <option value="password_reset">Password Reset</option>
                </select>
            </div>

            <button type="submit" name="send_test">📧 Send Test Email</button>
        </form>

        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'successfully') !== false ? 'success' : 'error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 30px; padding: 20px; background: #e8f4f8; border-radius: 5px;">
            <h3>📋 Available Email Types:</h3>
            <ul>
                <li><strong>Registration Verification:</strong> Sent when user registers (contains verification link)</li>
                <li><strong>Welcome Email:</strong> Sent after email verification (professional welcome message)</li>
                <li><strong>Password Reset:</strong> Sent when user requests password reset (contains reset link)</li>
            </ul>
        </div>
    </div>
</body>
</html>