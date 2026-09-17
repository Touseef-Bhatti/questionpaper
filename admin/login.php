<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../email/phpmailer_mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'use_only_cookies' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// Redirect if already logged in as admin
if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$showOtpForm = false;
$clientIp = getAdminClientIP();
$otpCooldownSeconds = 60; // 1 minute cooldown between OTP requests
$cooldownRemaining = 0;

// Helper: Get active pending login action for an email
function getActivePendingLogin(mysqli $conn, string $email): ?array {
    $stmt = $conn->prepare("SELECT id, admin_id, email, token, UNIX_TIMESTAMP(created_at) as created_ts, UNIX_TIMESTAMP(expires_at) as expires_ts, (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(created_at)) as elapsed_secs FROM pending_admin_actions WHERE action_type = 'login' AND email = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

// Helper: Invalidate all existing pending login OTPs for an admin/email
function invalidatePreviousLoginOtps(mysqli $conn, string $email, int $adminId = 0): void {
    if ($adminId > 0) {
        $stmt = $conn->prepare("DELETE FROM pending_admin_actions WHERE action_type = 'login' AND (email = ? OR admin_id = ?)");
        if ($stmt) {
            $stmt->bind_param("si", $email, $adminId);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM pending_admin_actions WHERE action_type = 'login' AND email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Check if there is an active session awaiting OTP verification
$pendingEmail = trim((string)($_SESSION['pending_login_email'] ?? ''));
if ($pendingEmail !== '') {
    $activeOtp = getActivePendingLogin($conn, $pendingEmail);
    if ($activeOtp) {
        $showOtpForm = true;
        $elapsed = (int)($activeOtp['elapsed_secs'] ?? 0);
        if ($elapsed < $otpCooldownSeconds) {
            $cooldownRemaining = $otpCooldownSeconds - $elapsed;
        }
    } else {
        // Expired or invalid
        unset($_SESSION['pending_login_email'], $_SESSION['otp_sent_time']);
    }
}

// Handle Cancel / Switch Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_otp'])) {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        if ($pendingEmail !== '') {
            invalidatePreviousLoginOtps($conn, $pendingEmail);
        }
        unset($_SESSION['pending_login_email'], $_SESSION['otp_sent_time']);
        header('Location: login.php');
        exit;
    }
}

// Handle Resend OTP Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed. Please refresh the page and try again.';
        $showOtpForm = true;
    } elseif ($pendingEmail === '') {
        $error = 'Your session has expired. Please log in with your credentials.';
        $showOtpForm = false;
    } else {
        $activeOtp = getActivePendingLogin($conn, $pendingEmail);
        $elapsed = $activeOtp ? (int)($activeOtp['elapsed_secs'] ?? 0) : 999;

        if ($elapsed < $otpCooldownSeconds) {
            $cooldownRemaining = $otpCooldownSeconds - $elapsed;
            $error = "Please wait {$cooldownRemaining} seconds before requesting a new OTP.";
            $showOtpForm = true;
        } else {
            // Check OTP Send Rate Limits
            $sendLimitEmail = checkDbRateLimit($conn, 'admin_otp_send_email', $pendingEmail, 4, 900, 900);
            $sendLimitIp = checkDbRateLimit($conn, 'admin_otp_send_ip', $clientIp, 10, 900, 900);

            if (!$sendLimitEmail['allowed'] || !$sendLimitIp['allowed']) {
                $remMins = ceil(max($sendLimitEmail['remaining_seconds'], $sendLimitIp['remaining_seconds']) / 60);
                $error = "Too many OTP requests. Please wait {$remMins} minute(s) before trying again.";
                $showOtpForm = true;
            } else {
                // Fetch admin info
                $stmt = $conn->prepare("SELECT id, name, email FROM admins WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $pendingEmail);
                $stmt->execute();
                $adminRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($adminRow) {
                    // Invalidate old OTP so only 1 active OTP exists
                    invalidatePreviousLoginOtps($conn, $pendingEmail, (int)$adminRow['id']);

                    // Generate single secure 6-digit OTP
                    $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                    $insStmt = $conn->prepare("INSERT INTO pending_admin_actions (action_type, admin_id, email, token, ip_address, user_agent, created_at, expires_at) VALUES ('login', ?, ?, ?, ?, ?, NOW(), ?)");
                    $insStmt->bind_param("isssss", $adminRow['id'], $adminRow['email'], $otp, $clientIp, $userAgent, $expiresAt);

                    if ($insStmt->execute()) {
                        $insStmt->close();
                        if (sendAdminOtpEmail($adminRow['email'], $otp, $adminRow['name'])) {
                            $success = 'A fresh 6-digit OTP has been sent to ' . htmlspecialchars($adminRow['email']) . '.';
                            $_SESSION['otp_sent_time'] = time();
                            $cooldownRemaining = $otpCooldownSeconds;
                        } else {
                            $error = 'Failed to deliver OTP email. Please check mail settings.';
                        }
                    } else {
                        $insStmt->close();
                        $error = 'Error generating new OTP. Please try again.';
                    }
                } else {
                    $error = 'Admin account not found.';
                    $showOtpForm = false;
                }
                $showOtpForm = true;
            }
        }
    }
}
// Handle OTP Verification
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please refresh the page.';
        $showOtpForm = true;
    } else {
        $otp = trim($_POST['otp'] ?? '');
        $email = $pendingEmail;

        if ($email === '') {
            $error = 'Login session expired. Please enter your email and password again.';
            $showOtpForm = false;
        } elseif ($otp === '' || !preg_match('/^[0-9]{6}$/', $otp)) {
            $error = 'Please enter a valid 6-digit numerical OTP.';
            $showOtpForm = true;
        } else {
            // Check OTP Verification Rate Limits (prevents brute force guessing of 6-digit codes)
            $verifyLimitIp = checkDbRateLimit($conn, 'admin_otp_verify_ip', $clientIp, 5, 300, 300);
            $verifyLimitEmail = checkDbRateLimit($conn, 'admin_otp_verify_email', $email, 5, 300, 300);

            if (!$verifyLimitIp['allowed'] || !$verifyLimitEmail['allowed']) {
                $remMins = ceil(max($verifyLimitIp['remaining_seconds'], $verifyLimitEmail['remaining_seconds']) / 60);
                $error = "Too many failed OTP attempts. Please wait {$remMins} minute(s) before trying again.";
                $showOtpForm = true;
            } else {
                // Find matching active pending login action
                $stmt = $conn->prepare("SELECT pa.*, a.name, a.role FROM pending_admin_actions pa JOIN admins a ON pa.admin_id = a.id WHERE pa.action_type = 'login' AND pa.email = ? AND pa.token = ? AND pa.expires_at > NOW() ORDER BY pa.id DESC LIMIT 1");
                $stmt->bind_param("ss", $email, $otp);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res && $res->num_rows === 1) {
                    $action = $res->fetch_assoc();
                    $stmt->close();

                    // Successful authentication!
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$action['admin_id'];
                    $_SESSION['admin_id'] = (int)$action['admin_id'];
                    $_SESSION['id'] = (int)$action['admin_id'];
                    $_SESSION['name'] = $action['name'];
                    $_SESSION['email'] = $action['email'];
                    $_SESSION['role'] = strtolower($action['role']);
                    $_SESSION['last_activity'] = time();

                    // Delete the used pending action immediately (single-use OTP)
                    $delStmt = $conn->prepare("DELETE FROM pending_admin_actions WHERE id = ?");
                    $delStmt->bind_param("i", $action['id']);
                    $delStmt->execute();
                    $delStmt->close();

                    // Clear rate limits upon successful login
                    clearDbRateLimit($conn, 'admin_login_ip', $clientIp);
                    clearDbRateLimit($conn, 'admin_login_email', $email);
                    clearDbRateLimit($conn, 'admin_otp_verify_ip', $clientIp);
                    clearDbRateLimit($conn, 'admin_otp_verify_email', $email);
                    clearDbRateLimit($conn, 'admin_otp_send_email', $email);

                    // Clean session temporary state
                    unset($_SESSION['pending_login_email'], $_SESSION['otp_sent_time']);

                    logAdminAction('admin_login', 'Admin logged in successfully via OTP');
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $stmt->close();
                    $error = 'Invalid or expired OTP code. Please check and try again.';
                    $showOtpForm = true;

                    // If user reached 5 failed verification attempts on this pending OTP, destroy it for security
                    if ($verifyLimitEmail['attempts'] >= 5) {
                        invalidatePreviousLoginOtps($conn, $email);
                        unset($_SESSION['pending_login_email'], $_SESSION['otp_sent_time']);
                        $error = 'Maximum invalid OTP attempts reached. For security, this code was invalidated. Please log in again.';
                        $showOtpForm = false;
                    }
                }
            }
        }
    }
}
// Handle Step 1: Initial Login Credentials (Email + Password)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please reload the page.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'Please enter both your email address and password.';
        } else {
            // Check Credential Rate Limits (Prevent Brute-Force Password Attacks)
            $loginLimitIp = checkDbRateLimit($conn, 'admin_login_ip', $clientIp, 8, 900, 900);
            $loginLimitEmail = checkDbRateLimit($conn, 'admin_login_email', $email, 5, 900, 900);

            if (!$loginLimitIp['allowed'] || !$loginLimitEmail['allowed']) {
                $remMins = ceil(max($loginLimitIp['remaining_seconds'], $loginLimitEmail['remaining_seconds']) / 60);
                $error = "Too many failed login attempts. Please wait {$remMins} minute(s) before attempting to sign in again.";
            } else {
                // Verify Admin Credentials
                $stmt = $conn->prepare("SELECT id, name, email, password, role FROM admins WHERE email = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $res = $stmt->get_result();

                    if ($res && $res->num_rows === 1) {
                        $user = $res->fetch_assoc();
                        $stmt->close();

                        // Secure password check
                        $valid = password_verify($password, $user['password']) || ($password === $user['password']);

                        if ($valid && in_array(strtolower($user['role']), ['admin', 'superadmin'])) {
                            // Credentials valid! Reset credential failure counts
                            clearDbRateLimit($conn, 'admin_login_email', $email);

                            // CHECK COOLDOWN: Has an active OTP been issued recently for this admin?
                            // Ensures OTP is sent 1 time per request window
                            $existingOtp = getActivePendingLogin($conn, $user['email']);
                            $elapsed = $existingOtp ? (int)($existingOtp['elapsed_secs'] ?? 0) : 999;

                            if ($existingOtp && $elapsed < $otpCooldownSeconds) {
                                // OTP was already sent within the last 60 seconds. Do not re-send!
                                $cooldownRemaining = $otpCooldownSeconds - $elapsed;
                                $success = 'An active OTP has already been sent to ' . htmlspecialchars($user['email']) . '. Please check your inbox or enter it below.';
                                $_SESSION['pending_login_email'] = $user['email'];
                                $showOtpForm = true;
                            } else {
                                // Check OTP Send Rate Limits (prevent mailbox flooding)
                                $sendLimitEmail = checkDbRateLimit($conn, 'admin_otp_send_email', $user['email'], 4, 900, 900);
                                $sendLimitIp = checkDbRateLimit($conn, 'admin_otp_send_ip', $clientIp, 10, 900, 900);

                                if (!$sendLimitEmail['allowed'] || !$sendLimitIp['allowed']) {
                                    $remMins = ceil(max($sendLimitEmail['remaining_seconds'], $sendLimitIp['remaining_seconds']) / 60);
                                    $error = "Too many OTP requests. Please wait {$remMins} minute(s) before requesting another code.";
                                } else {
                                    // Invalidate previous OTPs so only 1 single valid OTP exists
                                    invalidatePreviousLoginOtps($conn, $user['email'], (int)$user['id']);

                                    // Generate cryptographically secure 6-digit OTP
                                    $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                                    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
                                    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                                    $stmt2 = $conn->prepare("INSERT INTO pending_admin_actions (action_type, admin_id, email, token, ip_address, user_agent, created_at, expires_at) VALUES ('login', ?, ?, ?, ?, ?, NOW(), ?)");
                                    $stmt2->bind_param("isssss", $user['id'], $user['email'], $otp, $clientIp, $userAgent, $expiresAt);

                                    if ($stmt2->execute()) {
                                        $stmt2->close();
                                        // Send exactly 1 OTP email for this request
                                        if (sendAdminOtpEmail($user['email'], $otp, $user['name'])) {
                                            $success = 'Login credentials verified! A 6-digit OTP has been sent to ' . htmlspecialchars($user['email']) . '. Please enter it below to complete sign-in.';
                                            $_SESSION['pending_login_email'] = $user['email'];
                                            $_SESSION['otp_sent_time'] = time();
                                            $cooldownRemaining = $otpCooldownSeconds;
                                            $showOtpForm = true;
                                        } else {
                                            $error = 'Credentials verified, but failed to deliver OTP email. Please try again or contact support.';
                                        }
                                    } else {
                                        $stmt2->close();
                                        $error = 'Internal error preparing login. Please try again.';
                                    }
                                }
                            }
                        } else {
                            // Delay slightly to mitigate timing attacks
                            usleep(200000);
                            $error = 'Invalid email or password.';
                        }
                    } else {
                        $stmt->close();
                        usleep(200000);
                        $error = 'Invalid email or password.';
                    }
                } else {
                    $error = 'Database service unavailable. Please try again later.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal Login - Ahmad Learning Hub</title>
    
    <!-- Core Assets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link rel="stylesheet" href="<?= $assetBase ?>css/admin.css">

    <style>
    /* Scoped modern responsive styling for Admin Login */
    .admin-login-wrapper {
        min-height: calc(100vh - 120px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem 4rem;
        background: #f8fafc;
    }

    .admin-login-card {
        width: 100%;
        max-width: 440px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
        padding: 2rem 1.75rem;
        position: relative;
        overflow: hidden;
    }

    .admin-login-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, #1e3c72 0%, #2a5298 50%, #4facfe 100%);
    }

    .login-brand-icon {
        width: 54px;
        height: 54px;
        background: #eff6ff;
        color: #1e3c72;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin: 0 auto 1rem;
        border: 1px solid #dbeafe;
    }

    .login-title {
        text-align: center;
        font-size: 1.45rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.25rem;
    }

    .login-subtitle {
        text-align: center;
        font-size: 0.88rem;
        color: #64748b;
        margin-bottom: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.15rem;
    }

    .form-label {
        display: block;
        font-size: 0.86rem;
        font-weight: 600;
        color: #334155;
        margin-bottom: 0.4rem;
    }

    .input-field-wrap {
        position: relative;
    }

    .input-field-wrap i.field-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.95rem;
        pointer-events: none;
    }

    .input-field-wrap input {
        width: 100%;
        padding: 11px 14px 11px 40px;
        border: 1.5px solid #cbd5e1;
        border-radius: 9px;
        font-size: 0.95rem;
        color: #0f172a;
        background: #ffffff;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .input-field-wrap input:focus {
        border-color: #2563eb;
        outline: none;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }

    .toggle-password {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        cursor: pointer;
        padding: 4px 6px;
    }

    .toggle-password:hover {
        color: #334155;
    }

    .otp-input-field {
        text-align: center !important;
        font-size: 1.65rem !important;
        letter-spacing: 12px !important;
        font-weight: 700 !important;
        font-family: monospace !important;
        padding-left: 18px !important;
        padding-right: 6px !important;
    }

    .btn-submit {
        width: 100%;
        padding: 12px 18px;
        background: #1e3c72;
        color: #ffffff;
        border: none;
        border-radius: 9px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: background 0.2s ease, transform 0.1s ease;
        margin-top: 0.5rem;
    }

    .btn-submit:hover:not(:disabled) {
        background: #162d55;
    }

    .btn-submit:active:not(:disabled) {
        transform: scale(0.99);
    }

    .btn-submit:disabled {
        opacity: 0.65;
        cursor: not-allowed;
    }

    .alert-banner {
        padding: 0.85rem 1rem;
        border-radius: 9px;
        font-size: 0.88rem;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        line-height: 1.45;
    }

    .alert-error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .alert-success {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .otp-meta-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 9px;
        padding: 0.75rem 0.9rem;
        margin-bottom: 1.25rem;
        font-size: 0.82rem;
        color: #475569;
    }

    .login-footer-links {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
        padding-top: 1.25rem;
        border-top: 1px solid #f1f5f9;
        font-size: 0.86rem;
    }

    .login-footer-links a {
        color: #2563eb;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.15s;
    }

    .login-footer-links a:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }

    @media (max-width: 480px) {
        .admin-login-card {
            padding: 1.5rem 1.25rem;
            border-radius: 12px;
        }
        .login-title {
            font-size: 1.25rem;
        }
    }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../header.php'; ?>

    <div class="admin-login-wrapper">
        <div class="admin-login-card">
            <div class="login-brand-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>

            <h1 class="login-title">Admin Portal</h1>
            <p class="login-subtitle">
                <?= $showOtpForm ? 'Enter 2-Factor Authentication Code' : 'Sign in to access admin management' ?>
            </p>

            <?php if (!empty($error)): ?>
                <div class="alert-banner alert-error" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mt-1 flex-shrink-0"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert-banner alert-success" role="alert">
                    <i class="fa-solid fa-circle-check mt-1 flex-shrink-0"></i>
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($showOtpForm): ?>
                <!-- Step 2: OTP Verification Form -->
                <div class="otp-meta-box">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span><strong>Sent to:</strong> <?= htmlspecialchars($pendingEmail) ?></span>
                        <span class="badge bg-light text-dark border">Valid: 10 mins</span>
                    </div>
                    <div class="text-muted" style="font-size: 0.78rem;">
                        One single-use code is valid at a time. Check your spam folder if delayed.
                    </div>
                </div>

                <form method="POST" id="otpVerifyForm" onsubmit="handleFormSubmit(this)">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="verify_otp" value="1">

                    <div class="form-group">
                        <label class="form-label text-center" for="otpInput">Enter 6-Digit OTP</label>
                        <div class="input-field-wrap">
                            <input 
                                type="text" 
                                id="otpInput" 
                                name="otp" 
                                maxlength="6" 
                                pattern="[0-9]{6}" 
                                inputmode="numeric" 
                                autocomplete="one-time-code" 
                                required 
                                placeholder="&bull;&bull;&bull;&bull;&bull;&bull;" 
                                class="otp-input-field" 
                                autofocus>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fa-solid fa-lock-open me-1"></i>Verify OTP &amp; Sign In
                    </button>
                </form>

                <!-- Resend & Switch Actions -->
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2" style="font-size: 0.85rem;">
                    <form method="POST" id="resendOtpForm" onsubmit="handleFormSubmit(this)">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="resend_otp" value="1">
                        <button type="submit" class="btn btn-link p-0 text-decoration-none" id="resendBtn" <?= $cooldownRemaining > 0 ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-rotate-right me-1"></i>
                            <span id="resendBtnText">
                                <?= $cooldownRemaining > 0 ? "Resend OTP in {$cooldownRemaining}s" : 'Resend OTP' ?>
                            </span>
                        </button>
                    </form>

                    <form method="POST" id="cancelOtpForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="cancel_otp" value="1">
                        <button type="submit" class="btn btn-link p-0 text-secondary text-decoration-none">
                            <i class="fa-solid fa-arrow-left me-1"></i>Different email
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- Step 1: Initial Login Form -->
                <form method="POST" id="loginForm" onsubmit="handleFormSubmit(this)">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="form-group">
                        <label class="form-label" for="adminEmail">Email Address</label>
                        <div class="input-field-wrap">
                            <i class="fa-solid fa-envelope field-icon"></i>
                            <input 
                                type="email" 
                                id="adminEmail" 
                                name="email" 
                                required 
                                placeholder="admin@example.com" 
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="adminPassword">Password</label>
                        <div class="input-field-wrap">
                            <i class="fa-solid fa-lock field-icon"></i>
                            <input 
                                type="password" 
                                id="adminPassword" 
                                name="password" 
                                required 
                                placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                            <span class="toggle-password" id="togglePasswordBtn" title="Toggle password visibility">
                                <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fa-solid fa-paper-plane me-1"></i>Send One-Time Code
                    </button>
                </form>
            <?php endif; ?>

            <div class="login-footer-links">
                <a href="../index.php"><i class="fa-solid fa-house me-1"></i>Back to site</a>
                <a href="../auth/login.php"><i class="fa-solid fa-user me-1"></i>User Login</a>
            </div>
        </div>
    </div>

    <script>
    // Double submit prevention
    function handleFormSubmit(form) {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Processing...';
            // Safety timeout to re-enable in case user stays on page
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }, 10000);
        }
        return true;
    }

    // Password visibility toggle
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const passwordInput = document.getElementById('adminPassword');
    const toggleIcon = document.getElementById('togglePasswordIcon');
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleIcon.className = isPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
        });
    }

    // OTP Resend Countdown Timer
    let cooldown = <?= (int)$cooldownRemaining ?>;
    const resendBtn = document.getElementById('resendBtn');
    const resendText = document.getElementById('resendBtnText');

    if (resendBtn && cooldown > 0) {
        const timer = setInterval(() => {
            cooldown--;
            if (cooldown <= 0) {
                clearInterval(timer);
                resendBtn.disabled = false;
                resendText.textContent = 'Resend OTP';
            } else {
                resendText.textContent = `Resend OTP in ${cooldown}s`;
            }
        }, 1000);
    }
    </script>
</body>
</html>
