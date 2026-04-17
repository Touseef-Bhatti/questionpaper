<?php
// Show errors (for debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load configuration from .env
require_once __DIR__ . '/../config/env.php';
EnvLoader::load();

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Support repository layouts where tests may run from /tests and PHPMailer-master lives in project root
$phpmailerDir = __DIR__ . '/../PHPMailer-master';
if (!is_dir($phpmailerDir) && is_dir(__DIR__ . '/PHPMailer-master')) {
    $phpmailerDir = __DIR__ . '/PHPMailer-master';
}

if (!is_dir($phpmailerDir)) {
    die('❌ PHPMailer directory not found. Expected at: ' . __DIR__ . '/../PHPMailer-master or ./PHPMailer-master');
}

require_once $phpmailerDir . '/src/Exception.php';
require_once $phpmailerDir . '/src/PHPMailer.php';
require_once $phpmailerDir . '/src/SMTP.php';

echo "🚀 Ahmad Learning Hub Mail Test<br><br>";

// Load SMTP credentials from environment (.env)
$smtpHost = EnvLoader::get('SMTP_HOST', 'mail.ahmadlearninghub.com.pk');
$smtpUsername = EnvLoader::get('SMTP_USERNAME', 'admin@ahmadlearninghub.com.pk');
$smtpPassword = EnvLoader::get('SMTP_PASSWORD', '');
$smtpPort = EnvLoader::getInt('SMTP_PORT', 465);
$smtpSecure = EnvLoader::get('SMTP_SECURE', 'ssl'); // 'ssl' or 'tls'
$fromEmail = EnvLoader::get('SMTP_FROM_EMAIL', $smtpUsername);
$fromName = EnvLoader::get('APP_NAME', 'Ahmad Learning Hub');
$testEmail = EnvLoader::get('TEST_EMAIL_ADDRESS', '231370223@gift.edu.pk');

// Display loaded configuration (hide password)
echo "<strong>📧 SMTP Configuration (from .env):</strong><br>";
echo "Host: $smtpHost<br>";
echo "Port: $smtpPort<br>";
echo "Username: " . (!empty($smtpUsername) ? $smtpUsername : '(none - MailHog)') . "<br>";
echo "From: $fromEmail<br>";
echo "Test Email: $testEmail<br>";
echo "Password: " . (strlen($smtpPassword) > 0 ? '✓ Loaded' : '✗ Not Found (MailHog)') . "<br>";
echo "Security: $smtpSecure<br><br>";

if (strtolower($smtpHost) === 'mailhog') {
    echo "<strong style='color: blue;'>🐷 Using MailHog (Local Development)</strong><br>";
    echo "📧 View sent emails at: <a href='http://localhost:8025' target='_blank'>http://localhost:8025</a><br><br>";
} elseif (empty($smtpPassword)) {
    echo "<strong style='color: red;'>⚠️ Warning: SMTP_PASSWORD not found in .env</strong><br><br>";
}

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUsername;
    $mail->Password   = $smtpPassword;
    
    // Handle SSL vs TLS vs none
    if (strtolower($smtpSecure) === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif (strtolower($smtpSecure) === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = ''; // No encryption for MailHog
        $mail->SMTPAutoTLS = false;
    }
    
    $mail->Port       = $smtpPort;

    // Recipients
    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($testEmail);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Ahmad Learning Hub Test Email';
    $mail->Body    = '<h2>✅ SMTP Test Successful!</h2><p>This is a test email from Ahmad Learning Hub.</p><p><strong>Configuration loaded from .env</strong></p>';

    $mail->send();
    echo "✔ Test email sent successfully to $testEmail!";
} catch (Exception $e) {
    echo "❌ Mailer Error: " . $mail->ErrorInfo . '<br>' . $e->getMessage();
}
