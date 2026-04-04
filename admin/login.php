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
$verificationEmail = 'touseef12345bhatti@gmail.com';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                        // Create a login verification request
                        $token = bin2hex(random_bytes(32));
                        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                        
                        $stmt2 = $conn->prepare("INSERT INTO pending_admin_actions (action_type, admin_id, email, token, ip_address, user_agent, created_at, expires_at) VALUES ('login', ?, ?, ?, ?, ?, NOW(), ?)");
                        $stmt2->bind_param("isssss", $user['id'], $user['email'], $token, $ipAddress, $userAgent, $expiresAt);
                        
                        if ($stmt2->execute()) {
                            $details = "<strong>Admin:</strong> " . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ")<br>";
                            $details .= "<strong>IP Address:</strong> " . htmlspecialchars($ipAddress) . "<br>";
                            $details .= "<strong>Time:</strong> " . date('Y-m-d H:i:s');
                            
                            if (sendAdminActionVerificationEmail($verificationEmail, 'login', $token, $details)) {
                                $success = 'Login credentials verified! A verification email has been sent to ' . htmlspecialchars($verificationEmail) . '. Please check your email and click the verification link to complete login.';
                                // Store email in session for reference (optional)
                                $_SESSION['pending_login_email'] = $email;
                            } else {
                                $error = 'Credentials verified but failed to send verification email. Please try again later.';
                            }
                        } else {
                            $error = 'Error processing login verification. Please try again.';
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
                <h3 style="margin-top: 0; color: #155724;">✓ Check Your Email</h3>
                <p><?= $success ?></p>
                <p style="margin-bottom: 0; font-size: 0.9em; color: #0c5aa0;">
                    <strong>Verification Link Valid For:</strong> 30 minutes<br>
                    <strong>Not Received Email?</strong> Check your spam folder or try logging in again.
                </p>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <button type="submit">Sign in</button>
            </form>
        <?php endif; ?>
        
        <a class="back" href="../index.php">← Back to site</a>
        <a class="back" href="../login.php">User Login</a>
    </div>
    
</body>
</html>
