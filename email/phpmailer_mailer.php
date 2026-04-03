<?php
// Load environment configuration
require_once __DIR__ . '/../config/env.php';

// PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-master/src/SMTP.php';

function sendVerificationEmail($to, $token) {
    // Basic email validation
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Invalid email address: ' . $to);
        return false;
    }

    // Use direct HTML email for faster delivery
    return sendHtmlEmail($to, $token);
}

/**
 * Fast HTML email function using PHP's built-in mail()
 * This bypasses PHPMailer timeouts and sends immediately
 */
function sendHtmlEmail($to, $token) {
    try {
        $fromEmail = EnvLoader::get('SMTP_USERNAME', 'paper@bhattichemicalsindustry.com.pk');
        $fromName = EnvLoader::get('APP_NAME', 'Ahmad Learning Hub');
        
        // Fix URL formatting issue - properly handle the base URL
        $baseUrl = EnvLoader::get('APP_URL', 'https://paper.bhattichemicalsindustry.com.pk');
        
        // Ensure proper URL format
        if (!preg_match('/^https?:\/\//', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }
        
        // Clean and build the verification URL
$verifyUrl = rtrim($baseUrl, '/') . '/email/verify_email.php?token=' . urlencode($token);
        
        $subject = 'Verify your email address - ' . $fromName;
        
        // Beautiful HTML email with button
        $htmlMessage = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #4f6ef7, #6ac1ff); padding: 40px 20px; text-align: center;">
                            <h1 style="color: white; margin: 0; font-size: 28px; font-weight: bold;">' . htmlspecialchars($fromName) . '</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #4f6ef7; margin: 0 0 20px 0; font-size: 24px;">Welcome! Please verify your email</h2>
                            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">Dear User,</p>
                            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">Thank you for registering with ' . htmlspecialchars($fromName) . '. To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
                            
                            <!-- Verification Button -->
                            <table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <!-- Enhanced Outlook/Email Client Safe Button -->
                                        <table cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td align="center" style="
                                                    background-color: #4f6ef7;
                                                    border-radius: 8px;
                                                    text-align: center;
                                                    box-shadow: 0 4px 12px rgba(79, 110, 247, 0.3);
                                                ">
                                                    <a href="' . $verifyUrl . '" target="_blank" rel="noopener noreferrer" style="
                                                        background-color: #4f6ef7;
                                                        border: 18px solid #4f6ef7;
                                                        color: #ffffff !important;
                                                        display: inline-block;
                                                        font-family: Arial, Helvetica, sans-serif;
                                                        font-size: 16px;
                                                        font-weight: bold;
                                                        line-height: 1.2;
                                                        text-align: center;
                                                        text-decoration: none !important;
                                                        border-radius: 8px;
                                                        -webkit-text-size-adjust: none;
                                                        -ms-text-size-adjust: none;
                                                        mso-line-height-rule: exactly;
                                                    ">🔐 VERIFY EMAIL ADDRESS</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #666; font-size: 14px; line-height: 1.5; margin: 30px 0 10px 0;">If the button doesn\'t work, copy and paste this link into your browser:</p>
                            <p style="color: #4f6ef7; font-size: 14px; word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 0 0 30px 0;">' . $verifyUrl . '</p>
                            
                            <hr style="border: none; height: 1px; background: #eee; margin: 30px 0;">
                            <p style="color: #888; font-size: 12px; margin: 0;">If you did not create this account, please ignore this email.</p>
                            <p style="color: #888; font-size: 12px; margin: 10px 0 0 0;">Best regards,<br><strong>The ' . htmlspecialchars($fromName) . ' Team</strong></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        // Headers for HTML email
        $headers = "From: " . $fromName . " <" . $fromEmail . ">\r\n" .
                  "Reply-To: " . $fromEmail . "\r\n" .
                  "X-Mailer: PHP/" . phpversion() . "\r\n" .
                  "MIME-Version: 1.0\r\n" .
                  "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Send the email
        $result = mail($to, $subject, $htmlMessage, $headers);
        
        if ($result) {
            error_log('HTML email sent successfully to: ' . $to);
            return true;
        } else {
            error_log('HTML email failed for: ' . $to . ', trying fallback');
            return sendFallbackEmail($to, $token);
        }
        
    } catch (Exception $e) {
        error_log('HTML email error: ' . $e->getMessage());
        return sendFallbackEmail($to, $token);
    }
}

/**
 * Fallback email function using PHP's built-in mail() function
 * This works on most shared hosting providers
 */
function sendFallbackEmail($to, $token) {
    try {
        $fromEmail = EnvLoader::get('SMTP_USERNAME', 'paper@bhattichemicalsindustry.com.pk');
        $fromName = EnvLoader::get('APP_NAME', 'Ahmad Learning Hub');
        // Fix URL formatting for fallback too
        $baseUrl = EnvLoader::get('APP_URL', 'https://paper.bhattichemicalsindustry.com.pk');
        if (!preg_match('/^https?:\/\//', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }
$verifyUrl = rtrim($baseUrl, '/') . '/email/verify_email.php?token=' . urlencode($token);
        
        $subject = 'Verify your email address - ' . $fromName;
        $message = "Welcome to " . $fromName . "!\n\n" .
                  "Please verify your email address by clicking this link:\n" .
                  $verifyUrl . "\n\n" .
                  "If you did not create this account, please ignore this email.\n\n" .
                  "Best regards,\nThe " . $fromName . " Team";
        
        $headers = "From: " . $fromName . " <" . $fromEmail . ">\r\n" .
                  "Reply-To: " . $fromEmail . "\r\n" .
                  "X-Mailer: PHP/" . phpversion() . "\r\n" .
                  "MIME-Version: 1.0\r\n" .
                  "Content-Type: text/plain; charset=UTF-8\r\n";
        
        $result = mail($to, $subject, $message, $headers);
        
        if ($result) {
            error_log('Fallback email sent successfully to: ' . $to);
            return true;
        } else {
            error_log('Fallback email also failed for: ' . $to);
            return false;
        }
        
    } catch (Exception $e) {
        error_log('Fallback email error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send Admin Action Verification Email (for admin login, password change, etc.)
 * @param string $to - Email recipient
 * @param string $actionType - Type of action (login, password_change, create, delete)
 * @param string $token - Verification token
 * @param string $details - HTML details about the action
 */
function sendAdminActionVerificationEmail($to, $actionType, $token, $details = '') {
    try {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('Invalid email address: ' . $to);
            return false;
        }

        $mail = new PHPMailer(true);
        
        // Server settings
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
        
        // Determine action details for email subject and button text
        $actionTitles = [
            'login' => 'Verify Admin Login',
            'password_change' => 'Verify Password Change',
            'create' => 'Verify New Admin Creation',
            'delete' => 'Verify Admin Deletion'
        ];
        $actionTitle = $actionTitles[$actionType] ?? 'Verify Admin Action';
        $mail->Subject = $actionTitle . ' - ' . $fromName;

        // Determine action description for email body
        $actionDescriptions = [
            'login' => 'Someone (hopefully you) attempted to log in to your admin account. Click the button below to verify this login attempt.',
            'password_change' => 'Someone (hopefully you) requested to change the admin password. Click the button below to verify this password change request.',
            'create' => 'A new admin account has been created. Click the button below to verify and complete the creation of this admin account.',
            'delete' => 'An admin account deletion has been requested. Click the button below to verify and complete the deletion.'
        ];
        $actionDesc = $actionDescriptions[$actionType] ?? 'Please verify this admin action by clicking the button below.';

        // Build verification URL
        $baseUrl = EnvLoader::get('APP_URL', 'http://localhost:8000');
        if (!preg_match('/^https?:\/\//', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }
        $verifyUrl = rtrim($baseUrl, '/') . '/admin/verify_admin_action.php?token=' . urlencode($token);

        // Beautiful HTML email with action details
        $htmlMessage = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($actionTitle) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 40px 20px; text-align: center;">
                            <h1 style="color: white; margin: 0; font-size: 28px; font-weight: bold;">🔐 ' . htmlspecialchars($fromName) . '</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;">Admin Security Verification</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #667eea; margin: 0 0 20px 0; font-size: 24px;">' . htmlspecialchars($actionTitle) . '</h2>
                            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">Dear Admin,</p>
                            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">' . $actionDesc . '</p>
                            
                            ' . (!empty($details) ? '<div style="background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0 30px 0; border-radius: 4px;">' . $details . '</div>' : '') . '
                            
                            <!-- Verification Button -->
                            <table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <table cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td align="center" style="
                                                    background-color: #667eea;
                                                    border-radius: 8px;
                                                    text-align: center;
                                                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
                                                ">
                                                    <a href="' . $verifyUrl . '" target="_blank" rel="noopener noreferrer" style="
                                                        background-color: #667eea;
                                                        border: 18px solid #667eea;
                                                        color: #ffffff !important;
                                                        display: inline-block;
                                                        font-family: Arial, Helvetica, sans-serif;
                                                        font-size: 16px;
                                                        font-weight: bold;
                                                        line-height: 1.2;
                                                        text-align: center;
                                                        text-decoration: none !important;
                                                        border-radius: 8px;
                                                        -webkit-text-size-adjust: none;
                                                        -ms-text-size-adjust: none;
                                                        mso-line-height-rule: exactly;
                                                    ">✓ VERIFY & APPROVE</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #666; font-size: 14px; line-height: 1.5; margin: 30px 0 10px 0;">If the button doesn\'t work, copy and paste this link:</p>
                            <p style="color: #667eea; font-size: 14px; word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 0 0 30px 0;">' . $verifyUrl . '</p>
                            
                            <hr style="border: none; height: 1px; background: #eee; margin: 30px 0;">
                            <p style="color: #888; font-size: 12px; margin: 0;"><strong>⚠️ Security Notice:</strong> If you did not request this action, please do not click the verification button. Your account remains secure.</p>
                            <p style="color: #888; font-size: 12px; margin: 10px 0 0 0;">Best regards,<br><strong>The ' . htmlspecialchars($fromName) . ' Security Team</strong></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        $mail->Body = $htmlMessage;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $actionDesc)) . "\n\nVerify here: " . $verifyUrl;

        $mail->send();
        
        error_log('Admin action verification email sent to: ' . $to . ' (action: ' . $actionType . ')');
        return true;
        
    } catch (Exception $e) {
        error_log('Admin action verification email error: ' . $e->getMessage());
        return false;
    }
}
