<?php
/**
 * Email Preview Test
 * This shows you exactly how the verification email will look
 */
require_once __DIR__ . '/config/env.php';

$fromName = 'QPaperGen';
$testToken = 'sample_token_for_preview_12345';
$baseUrl = 'paper.bhattichemicalsindustry.com.pk';
$verifyUrl = 'https://' . $baseUrl . '/verify_email.php?token=' . urlencode($testToken);

echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email Preview - QPaperGen</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f0f0f0; }
        .preview-container { background: white; padding: 20px; border-radius: 10px; max-width: 800px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .preview-title { color: #4f6ef7; margin-bottom: 20px; }
        .email-frame { border: 2px solid #ddd; border-radius: 5px; overflow: hidden; }
    </style>
</head>
<body>
    <div class="preview-container">
        <h1 class="preview-title">📧 Email Preview - Verification Email</h1>
        <p><strong>This is how your verification emails will look:</strong></p>
        <div class="email-frame">';

// The actual email content
echo '
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
                                        <!-- Outlook/Email Client Safe Button -->
                                        <table cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="
                                                    background-color: #4f6ef7;
                                                    border-radius: 6px;
                                                    text-align: center;
                                                ">
                                                    <a href="' . $verifyUrl . '" target="_blank" style="
                                                        background-color: #4f6ef7;
                                                        border: 20px solid #4f6ef7;
                                                        color: #ffffff;
                                                        display: inline-block;
                                                        font-family: Arial, sans-serif;
                                                        font-size: 16px;
                                                        font-weight: bold;
                                                        line-height: 1.1;
                                                        text-align: center;
                                                        text-decoration: none;
                                                        border-radius: 6px;
                                                        -webkit-text-size-adjust: none;
                                                        mso-hide: all;
                                                    ">✓ VERIFY EMAIL ADDRESS</a>
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

echo '</div>
        <div style="margin-top: 20px; padding: 15px; background: #e8f5e8; border-radius: 5px;">
            <h3 style="color: #2d6e2d; margin: 0 0 10px 0;">✅ Email Features:</h3>
            <ul style="color: #2d6e2d; margin: 0;">
                <li><strong>Beautiful Button:</strong> Large, prominent verification button</li>
                <li><strong>Professional Design:</strong> Gradient header with brand name</li>
                <li><strong>Mobile Responsive:</strong> Works on all devices and email clients</li>
                <li><strong>Backup Link:</strong> Copy-paste URL if button doesn\'t work</li>
                <li><strong>Clean Layout:</strong> Easy to read with proper spacing</li>
            </ul>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 5px;">
            <h3 style="color: #1e6091; margin: 0 0 10px 0;">🚀 Speed Improvements:</h3>
            <ul style="color: #1e6091; margin: 0;">
                <li><strong>Instant Send:</strong> No more 1-2 minute delays</li>
                <li><strong>Direct Delivery:</strong> Uses PHP mail() for immediate sending</li>
                <li><strong>No Timeouts:</strong> Bypasses PHPMailer connection issues</li>
                <li><strong>Fixed URLs:</strong> No more malformed http:https links</li>
            </ul>
        </div>
        
        <p style="text-align: center; margin-top: 30px;">
            <a href="register.php" style="background: #4f6ef7; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Test Registration Now</a>
        </p>
    </div>
</body>
</html>';
?>
