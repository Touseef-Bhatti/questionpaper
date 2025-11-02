<?php
// Test file to preview the email verification template
require_once __DIR__ . '/config/env.php';

$token = 'test-token-123';
$fromName = EnvLoader::get('APP_NAME', 'QPaperGen');
$baseUrl = EnvLoader::get('APP_URL', 'https://paper.bhattichemicalsindustry.com.pk');

// Clean and build the verification URL
$verifyUrl = rtrim($baseUrl, '/') . '/verify_email.php?token=' . urlencode($token);

echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification Preview</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <div style="padding: 20px; text-align: center;">
        <h1 style="color: #333;">Email Verification Template Preview</h1>
        <p style="color: #666;">This is how the verification email will look:</p>
        <hr style="margin: 30px 0; border: 1px solid #ddd;">
    </div>
    
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
    
    <div style="padding: 20px; text-align: center;">
        <hr style="margin: 30px 0; border: 1px solid #ddd;">
        <p style="color: #666;">End of email template preview</p>
        <p style="color: #999; font-size: 12px;">Generated URL: ' . $verifyUrl . '</p>
    </div>
</body>
</html>';
?>
