<?php
// Load environment configuration
require_once __DIR__ . '/../config/env.php';

// PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-master/src/SMTP.php';

/**
 * Send admin verification email for create/delete actions
 */
function sendAdminActionVerificationEmail($to, $actionType, $token, $details) {
    try {
        $fromEmail = EnvLoader::get('SMTP_USERNAME', 'paper@bhattichemicalsindustry.com.pk');
        $fromName = EnvLoader::get('APP_NAME', 'Ahmad Learning Hub');
        $baseUrl = EnvLoader::get('APP_URL', 'https://paper.bhattichemicalsindustry.com.pk');
        
        if (!preg_match('/^https?:\/\//', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }
        
        $verifyUrl = rtrim($baseUrl, '/') . '/admin/verify_admin_action.php?token=' . urlencode($token);
        
        $actionName = ($actionType === 'create') ? 'Create New Admin' : 'Delete Admin Account';
        $subject = "Verification Required: $actionName - " . $fromName;
        
        $htmlMessage = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .header { background: #1e3c72; color: #fff; padding: 10px 20px; border-radius: 6px 6px 0 0; }
        .content { padding: 20px; }
        .footer { font-size: 12px; color: #888; text-align: center; margin-top: 20px; }
        .btn { display: inline-block; padding: 12px 24px; background: #1e3c72; color: #fff !important; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .details { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>Verification Required</h2>
        </div>
        <div class='content'>
            <p>Hello,</p>
            <p>An administrative action requires your verification:</p>
            <div class='details'>
                <strong>Action:</strong> $actionName<br>
                $details
            </div>
            <p>If you approve this action, please click the button below to verify and complete it:</p>
            <p style='text-align: center;'>
                <a href='$verifyUrl' class='btn'>Verify and Complete Action</a>
            </p>
            <p>If you did not authorize this action, please ignore this email.</p>
        </div>
        <div class='footer'>
            &copy; " . date('Y') . " " . htmlspecialchars($fromName) . ". All rights reserved.
        </div>
    </div>
</body>
</html>";

        $headers = "From: " . $fromName . " <" . $fromEmail . ">\r\n" .
                  "Reply-To: " . $fromEmail . "\r\n" .
                  "MIME-Version: 1.0\r\n" .
                  "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($to, $subject, $htmlMessage, $headers);
    } catch (Exception $e) {
        error_log("Admin verification email error: " . $e->getMessage());
        return false;
    }
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
