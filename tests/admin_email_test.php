<?php
// Test Admin Action Verification Email
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load configuration
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../email/phpmailer_mailer.php';

EnvLoader::load();

echo "🧪 Testing Admin Action Verification Email<br><br>";

// Test parameters
$testEmail = '231370223@gift.edu.pk';
$actionType = 'login';
$token = bin2hex(random_bytes(16)); // Shorter token for testing
$details = "<strong>Test Admin:</strong> John Doe (john@example.com)<br><strong>IP:</strong> 127.0.0.1<br><strong>Time:</strong> " . date('Y-m-d H:i:s');

// Display test configuration
echo "<strong>Test Configuration:</strong><br>";
echo "To: $testEmail<br>";
echo "Action: $actionType<br>";
echo "Token: $token<br>";
echo "Details: $details<br><br>";

// Test the email function
echo "<strong>Sending email...</strong><br>";
$result = sendAdminActionVerificationEmail($testEmail, $actionType, $token, $details);

if ($result) {
    echo "✅ Email sent successfully!<br>";
    echo "📧 Check MailHog at: <a href='http://localhost:8025' target='_blank'>http://localhost:8025</a><br>";
} else {
    echo "❌ Email sending failed!<br>";
    echo "Check PHP error logs for details.<br>";
}
?>