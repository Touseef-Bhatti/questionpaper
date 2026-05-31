<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../email/phpmailer_mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if already logged in as admin
if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin','superadmin'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$showOtpForm = false;

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token. Please reload the page.';
    } else {
        $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
        $email = isset($_SESSION['pending_login_email']) ? $_SESSION['pending_login_email'] : '';
        
        if ($otp !== '' && $email !== '') {
            // Find the pending login action with this OTP
            $stmt = $conn->prepare("SELECT pa.*, a.name, a.role FROM pending_admin_actions pa JOIN admins a ON pa.admin_id = a.id WHERE pa.action_type = 'login' AND pa.email = ? AND pa.token = ? AND pa.expires_at > NOW() ORDER BY pa.created_at DESC LIMIT 1");
            $stmt->bind_param("ss", $email, $otp);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($res && $res->num_rows === 1) {
                $action = $res->fetch_assoc();
                
                // Login successful
                session_regenerate_id(true);
                $_SESSION['user_id'] = $action['admin_id'];
                $_SESSION['admin_id'] = $action['admin_id'];
                $_SESSION['id'] = $action['admin_id'];
                $_SESSION['name'] = $action['name'];
                $_SESSION['email'] = $action['email'];
                $_SESSION['role'] = strtolower($action['role']);
                
                // Delete the pending action
                $stmt2 = $conn->prepare("DELETE FROM pending_admin_actions WHERE id = ?");
                $stmt2->bind_param("i", $action['id']);
                $stmt2->execute();
                $stmt2->close();
                
                // Clear session variables
                unset($_SESSION['pending_login_email']);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid or expired OTP. Please try again.';
                $showOtpForm = true;
            }
            $stmt->close();
        } else {
            $error = 'Please enter the OTP.';
            $showOtpForm = true;
        }
    }
}
// Handle initial login with email/password
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token. Please reload the page.';
    } else {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if ($email !== '' && $password !== '') {
            // Verify admin credentials
            $stmt = $conn->prepare("SELECT id, name, email, password, role FROM admins WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $res = $stmt->get_result();
                
                if ($res && $res->num_rows === 1) {
                    $user = $res->fetch_assoc();
                    // Accept either hashed or plain for early setups
                    $valid = password_verify($password, $user['password']) || $password === $user['password'];
                    
                    if ($valid && in_array(strtolower($user['role']), ['admin', 'superadmin'])) {
                        // Generate 6-digit OTP
                        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        
                        // Store OTP in pending_admin_actions (using token field for OTP)
                        $stmt2 = $conn->prepare("INSERT INTO pending_admin_actions (action_type, admin_id, email, token, ip_address, user_agent, created_at, expires_at) VALUES ('login', ?, ?, ?, ?, ?, NOW(), ?)");
                        $stmt2->bind_param("isssss", $user['id'], $user['email'], $otp, $ipAddress, $userAgent, $expiresAt);
                        
                        if ($stmt2->execute()) {
                            // Send OTP to admin's own email
                            if (sendAdminOtpEmail($user['email'], $otp, $user['name'])) {
                                $success = 'Login credentials verified! A 6-digit OTP has been sent to ' . htmlspecialchars($user['email']) . '. Please enter the OTP below to complete login.';
                                $_SESSION['pending_login_email'] = $email;
                                $showOtpForm = true;
                            } else {
                                $error = 'Credentials verified but failed to send OTP email. Please try again later.';
                            }
                        } else {
                            $error = 'Error processing login. Please try again.';
                        }
                        $stmt2->close();
                    } else {
                        $error = 'Invalid credentials or not authorized as admin.';
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
                $stmt->close();
            } else {
                $error = 'Database error. Please try again.';
            }
        } else {
            $error = 'Please enter both email and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
  
    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Admin Login</title>
    <link rel="stylesheet" href="<?= $assetBase ?>css/admin.css">
    
</head>
<body>
    <?php include __DIR__ . '/../header.php'; ?>
    <div class="admin-auth">
        <h2>Admin Login</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; color: #155724;">✓ OTP Sent!</h3>
                <p><?= $success ?></p>
                <p style="margin-bottom: 0; font-size: 0.9em; color: #0c5aa0;">
                    <strong>OTP Valid For:</strong> 10 minutes
                </p>
            </div>
        <?php endif; ?>
        
        <?php if ($showOtpForm): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="verify_otp" value="1">
                <label for="otp">Enter 6-digit OTP</label>
                <input type="text" id="otp" name="otp" maxlength="6" pattern="[0-9]{6}" required placeholder="000000" style="text-align: center; font-size: 24px; letter-spacing: 10px;">
                <button type="submit">Verify OTP & Login</button>
            </form>
            <p style="margin-top: 15px;">
                <a href="login.php" style="color: #667eea; text-decoration: none;">← Use different email</a>
            </p>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <button type="submit">Send OTP</button>
            </form>
        <?php endif; ?>
        
        <a class="back" href="../index.php">← Back to site</a>
        <a class="back" href="../login.php">User Login</a>
    </div>
    
</body>
</html>
