<?php
// Load environment configuration
require_once __DIR__ . '/../config/env.php';

// PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';

/**
 * Load and normalize email settings from environment variables.
 */
function getMailerFromAddress() {
    $default = 'admin@ahmadlearninghub.com.pk';
    return EnvLoader::get('SMTP_FROM_EMAIL', EnvLoader::get('SMTP_USERNAME', $default));
}

function getMailerFromName() {
    return EnvLoader::get('SMTP_FROM_NAME', EnvLoader::get('APP_NAME', 'Ahmad Learning Hub'));
}

function getAppUrl() {
    $baseUrl = EnvLoader::get('APP_URL', EnvLoader::get('SITE_URL', 'https://ahmadlearninghub.com.pk'));
    if (!preg_match('/^https?:\/\//', $baseUrl)) {
        $baseUrl = 'https://' . $baseUrl;
    }
    return rtrim($baseUrl, '/');
}

function configureMailerSmtp(PHPMailer $mail) {
    $mail->isSMTP();
    $mail->CharSet = 'UTF-8';
    $mail->Host       = EnvLoader::get('SMTP_HOST', 'mailhog');

    $smtpPassword = EnvLoader::get('SMTP_PASSWORD', '');
    $smtpUsername = EnvLoader::get('SMTP_USERNAME', '');
    $smtpAuth = EnvLoader::getBool('SMTP_AUTH', !empty($smtpPassword));

    $mail->SMTPAuth = $smtpAuth;
    if ($smtpAuth) {
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
    } else {
        $mail->Username = '';
        $mail->Password = '';
    }

    $smtpSecure = EnvLoader::get('SMTP_SECURE', 'none');
    if (strtolower($smtpSecure) === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif (strtolower($smtpSecure) === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    }

    $mail->Port = EnvLoader::getInt('SMTP_PORT', 1025);
    $mail->SMTPDebug = EnvLoader::getInt('SMTP_DEBUG', 0);
    $mail->Timeout = 10; // Set a 10-second timeout for connection and SMTP commands
    
    // Add custom error handler to log more detailed SMTP errors
    $mail->SMTPDebug = 2; // Enable detailed debug output
    $mail->Debugoutput = function($str, $level) {
        if (strpos($str, 'Connection: Failed') !== false || strpos($str, 'SMTP Error: Could not connect') !== false) {
            error_log('SMTP Debug [' . $level . ']: ' . $str);
        }
    };
}

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
 * Send HTML verification email using PHPMailer with MailHog
 */
function sendHtmlEmail($to, $token) {
    try {
        $mail = new PHPMailer(true);

        configureMailerSmtp($mail);

        // Recipients
        $mail->setFrom(getMailerFromAddress(), getMailerFromName());
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $fromName = getMailerFromName();
        $verifyUrl = getAppUrl() . '/email/verify_email.php?token=' . urlencode($token);
        
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
        
        $mail->Subject = $subject;
        $mail->Body    = $htmlMessage;
        $mail->AltBody = "Welcome to " . $fromName . "!\n\nPlease verify your email address by clicking this link:\n" . $verifyUrl . "\n\nIf you did not create this account, please ignore this email.\n\nBest regards,\nThe " . $fromName . " Team";

        $mail->send();
        error_log('Verification email sent successfully to: ' . $to);
        return true;

    } catch (Exception $e) {
        error_log('PHPMailer verification email error: ' . $e->getMessage());
        return sendFallbackEmail($to, $token);
    }
}

/**
 * Fallback email function using PHP's built-in mail() function
 * This works on most shared hosting providers
 */
function sendFallbackEmail($to, $token) {
    try {
        $fromEmail = getMailerFromAddress();
        $fromName = getMailerFromName();
        $verifyUrl = getAppUrl() . '/email/verify_email.php?token=' . urlencode($token);
        
        $subject = 'Verify your email address - ' . $fromName;
        // Clean subject of any newlines
        $subject = str_replace(["\r", "\n"], '', $subject);
        
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
        configureMailerSmtp($mail);

        // Recipients
        $fromName = getMailerFromName();
        $mail->setFrom(getMailerFromAddress(), $fromName);
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
        $verifyUrl = getAppUrl() . '/admin/verify_admin_action.php?token=' . urlencode($token);

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
        // Fallback to PHP mail()
        try {
            $fromEmail = getMailerFromAddress();
            $fromName = getMailerFromName();
            $verifyUrl = getAppUrl() . '/admin/verify_admin_action.php?token=' . urlencode($token);
            
            $actionTitles = [
                'login' => 'Verify Admin Login',
                'password_change' => 'Verify Password Change',
                'create' => 'Verify New Admin Creation',
                'delete' => 'Verify Admin Deletion'
            ];
            $actionTitle = $actionTitles[$actionType] ?? 'Verify Admin Action';
            $subject = $actionTitle . ' - ' . $fromName;
            $subject = str_replace(["\r", "\n"], '', $subject);

            $message = "Dear Admin,\n\n" .
                      strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $actionDesc)) . "\n\n" .
                      "Please verify here: " . $verifyUrl . "\n\n" .
                      "Best regards,\nThe " . $fromName . " Security Team";
            
            $headers = "From: " . $fromName . " <" . $fromEmail . ">\r\n" .
                      "Reply-To: " . $fromEmail . "\r\n" .
                      "MIME-Version: 1.0\r\n" .
                      "Content-Type: text/plain; charset=UTF-8\r\n";
            
            return mail($to, $subject, $message, $headers);
        } catch (Exception $ex) {
            error_log('Admin action fallback email error: ' . $ex->getMessage());
            return false;
        }
    }
}

/**
 * Send Welcome Email to New User After Verification
 * @param string $to - Email recipient
 * @param string $userName - User's name
 */
function sendWelcomeEmail($to, $userName) {
    try {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('Invalid email address: ' . $to);
            return false;
        }

        $mail = new PHPMailer(true);
        configureMailerSmtp($mail);

        $fromName = getMailerFromName();
        $mail->setFrom(getMailerFromAddress(), $fromName);
        $mail->addAddress($to);

        $baseUrl = getAppUrl();
        $loginUrl = rtrim($baseUrl, '/') . '/auth/login.php';
        $dashboardUrl = rtrim($baseUrl, '/') . '/index.php';

        $mail->isHTML(true);
        $mail->Subject = '🎉 Welcome to ' . $fromName . ' - Your Learning Journey Begins!';

        // Professional and engaging HTML welcome email
        $htmlMessage = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to ' . htmlspecialchars($fromName) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
    <table width="100%" cellspacing="0" cellpadding="0" border="0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden;">
                    <!-- Hero Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #4f6ef7 0%, #6ac1ff 100%); padding: 60px 40px; text-align: center; position: relative;">
                            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url(\'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDYwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxnIGNsaXBwYXRoPSJ1cmwoI2NsaXAwXzBfXzE5NDUpIj4KPHBhdGggZD0iTTAgMEg2MDBWMjAwSDBWMFoiIGZpbGw9InVybCgjZ3JhZGllbnQwX2xpbmVhcl8wXzBfXzE5NDUpIi8+CjwvZz4KPGRlZnM+CjxsaW5lYXJHcmFkaWVudCBpZD0iZ3JhZGllbnQwX2xpbmVhcl8wXzBfXzE5NDUiIGcxeD0iMCUiIGcxeT0iMCUiIGcyeD0iMTAwJSIgZzJ5PSIxMDAlIj4KPHN0b3Agc3RvcC1jb2xvcj0iIzRGNkVGNyIgc3RvcC1vcGFjaXR5PSIwLjEiLz4KPHN0b3Agb2Zmc2V0PSIxIiBzdG9wLWNvbG9yPSIjNmFjMWZmIiBzdG9wLW9wYWNpdHk9IjAuMSIvPgo8L2xpbmVhckdyYWRpZW50Pgo8L2RlZnM+Cjwvc3ZnPg==\') no-repeat center; opacity: 0.1;"></div>
                            <h1 style="color: #ffffff; margin: 0; font-size: 36px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3); z-index: 1; position: relative;">🎉 Welcome To Ahmad Learnig Hub!</h1>
                            <p style="color: #ffffff; margin: 15px 0 0 0; font-size: 18px; opacity: 0.95; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Your learning adventure starts here</p>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 50px 40px;">
                            <!-- Personal Greeting -->
                            <h2 style="color: #2d3748; margin: 0 0 25px 0; font-size: 28px; font-weight: 600;">Hello ' . htmlspecialchars($userName) . ',</h2>

                            <p style="color: #4a5568; font-size: 16px; line-height: 1.7; margin: 0 0 25px 0;">Welcome to <strong style="color: #4f6ef7;">' . htmlspecialchars($fromName) . '</strong>! We\'re absolutely thrilled to have you join our community of learners. Your account has been successfully verified, and you\'re now ready to embark on an exciting educational journey.</p>

                            <!-- What We Offer -->
                            <div style="background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 12px; padding: 30px; margin: 30px 0; border-left: 4px solid #4f6ef7;">
                                <h3 style="color: #2d3748; margin: 0 0 20px 0; font-size: 22px; font-weight: 600;">🚀 What You Can Do Right Now:</h3>
                                <ul style="color: #4a5568; font-size: 16px; line-height: 1.8; margin: 0; padding-left: 20px;">
                                    <li><strong>Generate Custom Question Papers:</strong> Create personalized test papers for classes 9th and 10th</li>
                                    <li><strong>Access Free Study Materials:</strong> Download comprehensive notes and textbooks</li>
                                    <li><strong>Take Online Quizzes:</strong> Test your knowledge with interactive assessments</li>
                                    <li><strong>Explore Educational Resources:</strong> Access a vast library of learning materials</li>
                                </ul>
                            </div>

                            <!-- Quick Start Guide -->
                            <h3 style="color: #2d3748; margin: 35px 0 20px 0; font-size: 22px; font-weight: 600;">📚 Quick Start Guide</h3>
                            <div style="background: #f8f9fa; border-radius: 10px; padding: 25px; margin: 20px 0;">
                                <ol style="color: #4a5568; font-size: 15px; line-height: 1.8; margin: 0; padding-left: 20px;">
                                    <li><strong>Choose Your Class:</strong> Select 9th or 10th grade to get started</li>
                                    <li><strong>Select Subjects:</strong> Pick the subjects you want to study</li>
                                    <li><strong>Generate Papers:</strong> Create custom question papers instantly</li>
                                    <li><strong>Download & Study:</strong> Access notes, textbooks, and practice materials</li>
                                </ol>
                            </div>

                            <!-- Call to Action Buttons -->
                            <table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 40px 0;">
                                <tr>
                                    <td align="center" style="padding: 10px;">
                                        <a href="' . $dashboardUrl . '" style="
                                            background: linear-gradient(135deg, #4f6ef7 0%, #6ac1ff 100%);
                                            color: #ffffff !important;
                                            padding: 16px 32px;
                                            text-decoration: none !important;
                                            border-radius: 12px;
                                            font-weight: 600;
                                            font-size: 16px;
                                            display: inline-block;
                                            box-shadow: 0 8px 20px rgba(79, 110, 247, 0.3);
                                            transition: all 0.3s ease;
                                            text-align: center;
                                            min-width: 180px;
                                        ">🚀 Start Learning Now</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding: 10px;">
                                        <a href="' . $loginUrl . '" style="
                                            background: #ffffff;
                                            color: #4f6ef7 !important;
                                            padding: 14px 30px;
                                            text-decoration: none !important;
                                            border-radius: 12px;
                                            font-weight: 600;
                                            font-size: 16px;
                                            display: inline-block;
                                            border: 2px solid #4f6ef7;
                                            transition: all 0.3s ease;
                                            text-align: center;
                                            min-width: 180px;
                                        ">🔑 Login to Your Account</a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Community & Support -->
                            <div style="background: linear-gradient(135deg, #e6fffa 0%, #b2f5ea 100%); border-radius: 12px; padding: 25px; margin: 30px 0; border-left: 4px solid #38b2ac;">
                                <h4 style="color: #234e52; margin: 0 0 15px 0; font-size: 18px; font-weight: 600;">🤝 Join Our Learning Community</h4>
                                <p style="color: #2d3748; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">Connect with thousands of students and teachers who are already benefiting from our platform. Share your experiences, ask questions, and grow together!</p>
                                <p style="color: #2d3748; font-size: 15px; line-height: 1.6; margin: 0;"><strong>Need Help?</strong> Our support team is here for you. Contact us anytime at <a href="mailto:admin@ahmadlearninghub.com.pk" style="color: #38b2ac; text-decoration: none;">admin@ahmadlearninghub.com.pk</a></p>
                            </div>

                            <!-- Footer -->
                            <hr style="border: none; height: 1px; background: linear-gradient(90deg, #e2e8f0 0%, #cbd5e0 50%, #e2e8f0 100%); margin: 40px 0;">
                            <p style="color: #718096; font-size: 14px; line-height: 1.6; margin: 0 0 10px 0; text-align: center;">Thank you for choosing <strong>' . htmlspecialchars($fromName) . '</strong> as your learning partner!</p>
                            <p style="color: #a0aec0; font-size: 12px; margin: 0; text-align: center;">This is an automated message. Please do not reply to this email.</p>
                            <p style="color: #a0aec0; font-size: 12px; margin: 10px 0 0 0; text-align: center;">© ' . date('Y') . ' ' . htmlspecialchars($fromName) . '. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

        $mail->Body = $htmlMessage;
        $mail->AltBody = "Welcome to " . $fromName . "!\n\nYour account has been successfully verified. Login here: " . $loginUrl;

        $mail->send();
        error_log('Welcome email sent successfully to: ' . $to);
        return true;

    } catch (Exception $e) {
        error_log('Welcome email error: ' . $e->getMessage());
        // Fallback to PHP mail()
        return sendFallbackWelcomeEmail($to, $userName);
    }
}

/**
 * Fallback Welcome Email using PHP mail()
 */
function sendFallbackWelcomeEmail($to, $userName) {
    try {
        $fromEmail = getMailerFromAddress();
        $fromName = getMailerFromName();
        $baseUrl = getAppUrl();
        $loginUrl = rtrim($baseUrl, '/') . '/auth/login.php';

        $subject = '🎉 Welcome to ' . $fromName . ' - Your Learning Journey Begins!';
        $subject = str_replace(["\r", "\n"], '', $subject);
        
        $message = "Hello " . $userName . ",\n\n" .
                  "Welcome to " . $fromName . "! Your account has been successfully verified.\n\n" .
                  "You can now login here: " . $loginUrl . "\n\n" .
                  "Best regards,\nThe " . $fromName . " Team";
        
        $headers = "From: " . $fromName . " <" . $fromEmail . ">\r\n" .
                  "Reply-To: " . $fromEmail . "\r\n" .
                  "MIME-Version: 1.0\r\n" .
                  "Content-Type: text/plain; charset=UTF-8\r\n";
        
        return mail($to, $subject, $message, $headers);
    } catch (Exception $e) {
        error_log('Fallback welcome email error: ' . $e->getMessage());
        return false;
    }
}


/**
 * Send Password Reset Email
 */
function send_reset_email($to, $reset_link) {
    try {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('Invalid email address: ' . $to);
            return false;
        }

        $mail = new PHPMailer(true);
        configureMailerSmtp($mail);

        // Recipients
        $fromName = getMailerFromName();
        $mail->setFrom(getMailerFromAddress(), $fromName);
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
                            <p style="color: #718096; font-size: 14px; line-height: 1.6; margin: 0 0 10px 0; text-align: center;">Need help? Contact our support team at <a href="mailto:admin@ahmadlearninghub.com.pk" style="color: #4f6ef7; text-decoration: none;">admin@ahmadlearninghub.com.pk</a></p>
                            <p style="color: #a0aec0; font-size: 12px; margin: 0; text-align: center;">© ' . date('Y') . ' ' . htmlspecialchars($fromName) . '. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        $mail->Body = $htmlMessage;
        $mail->AltBody = "Hello,\n\nWe received a request to reset your password. Click the link below to set a new password:\n\n$reset_link\n\nIf you didn't request this, please ignore this email.\n\nThanks,\n" . $fromName;

        $mail->send();
        error_log('Reset email sent successfully to: ' . $to);
        return true;

    } catch (Exception $e) {
        error_log('Password reset email error: ' . $e->getMessage());
        // Fallback to PHP mail()
        try {
            $fromEmail = getMailerFromAddress();
            $fromName = getMailerFromName();
            $subject = 'Reset your password - ' . $fromName;
            $subject = str_replace(["\r", "\n"], '', $subject);

            $message = "Hello,\n\nWe received a request to reset your password. Click the link below to set a new password:\n\n$reset_link\n\nIf you didn't request this, please ignore this email.\n\nThanks,\n" . $fromName;
            
            $headers = "From: " . $fromName . " <" . $fromEmail . ">\r\n" .
                      "Reply-To: " . $fromEmail . "\r\n" .
                      "MIME-Version: 1.0\r\n" .
                      "Content-Type: text/plain; charset=UTF-8\r\n";
            
            return mail($to, $subject, $message, $headers);
        } catch (Exception $ex) {
            error_log('Password reset fallback email error: ' . $ex->getMessage());
            return false;
        }
    }
}
