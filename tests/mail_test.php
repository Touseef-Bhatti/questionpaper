<?php
// Show errors (for debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer-master/src/Exception.php';
require __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/PHPMailer-master/src/SMTP.php';

echo "🚀 Ahmad Learning Hub Mail Test<br><br>";

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'bhattichemicalsindustry.com.pk';   // Your SMTP host
    $mail->SMTPAuth   = true;
    $mail->Username   = 'paper@bhattichemicalsindustry.com.pk'; // Your email
    $mail->Password   = 'Touseef.paper@321'; // Your email password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL encryption
    $mail->Port       = 465;

    // Recipients
    $mail->setFrom('paper@bhattichemicalsindustry.com.pk', 'Ahmad Learning Hub Test');
    $mail->addAddress('231370223@gift.edu.pk'); // change to your own test email

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Ahmad Learning Hub Test Email';
    $mail->Body    = '<h2>✅ SMTP Test Successful!</h2><p>This is a test email from Ahmad Learning Hub.</p>';

    $mail->send();
    echo "✔ Test email sent successfully!";
} catch (Exception $e) {
    echo "❌ Mailer Error: " . $mail->ErrorInfo;
}
