<?php
// Set error reporting based on environment
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/google_oauth.php';

if (EnvLoader::isDevelopment()) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    mysqli_report(MYSQLI_REPORT_ERROR);
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
}

// Set execution timeout for slow servers
set_time_limit(60);

// Include required files (remove duplicates)
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../email/phpmailer_mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if already registered
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $userExists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($userExists) {
            $error = 'This email is already registered. Please login.';
        } else {
            // Check pending
            $stmt = $conn->prepare("SELECT id, created_at FROM pending_users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $pendingUser = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($pendingUser) {
                $hoursSince = (time() - strtotime($pendingUser['created_at'])) / 3600;
                if ($hoursSince < 24) {
                    $error = 'A verification email was already sent. Please check your inbox.';
                } else {
                    $stmt = $conn->prepare("DELETE FROM pending_users WHERE email = ?");
                    $stmt->bind_param('s', $email);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            if (empty($error)) {
                try {
                    $hash  = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32));

                    // Table 'pending_users' is created in install.php - with safety fallback for runtime
                    $conn->query("CREATE TABLE IF NOT EXISTS pending_users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(191) NOT NULL,
                        email VARCHAR(191) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        token VARCHAR(64),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                    // Begin transaction for data consistency
                    $conn->autocommit(FALSE);
                    
                    $stmt = $conn->prepare("INSERT INTO pending_users (name, email, password, token) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param('ssss', $name, $email, $hash, $token);
                        if ($stmt->execute()) {
                            $stmt->close();
                            
                            // Commit the database transaction first
                            $conn->commit();
                            
                            // Try to send email after database commit (non-blocking)
                            $emailSent = false;
                            try {
                                // Set a shorter timeout for email sending
                                set_time_limit(30);
                                $emailSent = sendVerificationEmail($email, $token);
                            } catch (Exception $e) {
                                error_log('Email sending exception: ' . $e->getMessage());
                                $emailSent = false;
                            }
                            
                            if ($emailSent) {
                                $success = 'Registration successful! Please check your email for verification. The verification link has been sent.';
                            } else {
                                // Don't fail registration if email fails - user is already registered
                                error_log('Email sending failed for: ' . $email . ' but user was registered successfully');
                                $success = 'Registration successful! However, there was an issue sending the verification email.';
                                
                                if (EnvLoader::get('APP_ENV') === 'development') {
                                    $verifyUrl = getAppUrl() . '/email/verify_email.php?token=' . urlencode($token);
                                    $success .= '<br><br><strong>🛠️ Development Mode:</strong><br>Verification Link: <a href="' . $verifyUrl . '">' . $verifyUrl . '</a>';
                                } else {
                                    $success .= ' Please use the "Resend Verification" option or contact support.';
                                }
                            }
                        } else {
                            $conn->rollback();
                            $error = 'Registration failed. Please try again.';
                            error_log('Database insert failed: ' . $stmt->error);
                            $stmt->close();
                        }
                    } else {
                        $conn->rollback();
                        $error = 'Registration failed. Please try again.';
                        error_log('Prepare statement failed: ' . $conn->error);
                    }
                    
                    $conn->autocommit(TRUE);
                    
                } catch (Exception $e) {
                    if (isset($conn)) {
                        $conn->rollback();
                        $conn->autocommit(TRUE);
                    }
                    error_log('Registration exception: ' . $e->getMessage());
                    $error = 'Registration failed due to a server error. Please try again.';
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
    <title>Register - Ahmad Learning Hub</title>
<?php
// Compute a web-root relative base path so CSS links work when this file is included
// Use the directory of the actual requested script (single dirname) which maps to the app base when served
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$prefix = ($scriptDir === '/' || $scriptDir === '') ? '' : $scriptDir;
// Debug aid: leave an HTML comment showing the computed prefix (remove in production if needed)
echo "<!-- CSS prefix: " . htmlspecialchars($prefix) . " -->\n";
?>
    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link rel="stylesheet" href="<?= $assetBase ?>css/login.css">
</head>
<body>
<?php include '../header.php'; ?>

<div class="main-content" style="margin-top:13%">
    <div class="auth-page">
        <div class="auth-card">
            <h1 class="auth-title">Create your account</h1>
            <p class="auth-subtitle">Sign up to access Ahmad Learning Hub</p>

            <form method="POST" autocomplete="off" style="margin-bottom:0;">
                <div class="input-group">
                    <label for="name">Name</label>
                    <input class="form-control" type="text" name="name" id="name" required placeholder="Your full name" value="<?= htmlspecialchars($name ?? '') ?>">
                </div>

                <div class="input-group">
                    <label for="email">Email</label>
                    <input class="form-control" type="email" name="email" id="email" required placeholder="you@email.com" value="<?= htmlspecialchars($email ?? '') ?>">
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="password-container">
                        <input class="form-control" type="password" name="password" id="password" required placeholder="Password" style="padding-right:42px;">
                        <button type="button" class="password-toggle" onclick="togglePassword('password')" aria-label="Show password">
                            <svg id="password-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="input-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-container">
                        <input class="form-control" type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm Password" style="padding-right:42px;">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')" aria-label="Show confirm password">
                            <svg id="confirm_password-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <button class="btn primary btn-block" type="submit">Create account</button>
            </form>

            <!-- OAuth Section -->
            <div class="oauth-section" id="google-oauth">
                <div class="oauth-divider"><span>or continue with</span></div>
                <a href="<?= GoogleOAuthConfig::getAuthUrl('register') ?>" class="google-signin-btn">
                    <svg style="height:20px;" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid" viewBox="0 0 256 262"><path fill="#4285F4" d="M255.878 133.451c0-10.734-.871-18.567-2.756-26.69H130.55v48.448h71.947c-1.45 12.04-9.283 30.172-26.69 42.356l-.244 1.622 38.755 30.023 2.685.268c24.659-22.774 38.875-56.282 38.875-96.027"></path><path fill="#34A853" d="M130.55 261.1c35.248 0 64.839-11.605 86.453-31.622l-41.196-31.913c-11.024 7.688-25.82 13.055-45.257 13.055-34.523 0-63.824-22.773-74.269-54.25l-1.531.13-40.298 31.187-.527 1.465C35.393 231.798 79.49 261.1 130.55 261.1"></path><path fill="#FBBC05" d="M56.281 156.37c-2.756-8.123-4.351-16.827-4.351-25.82 0-8.994 1.595-17.697 4.206-25.82l-.073-1.73L15.26 71.312l-1.335.635C5.077 89.644 0 109.517 0 130.55s5.077 40.905 13.925 58.602l42.356-32.782"></path><path fill="#EB4335" d="M130.55 50.479c24.514 0 41.05 10.589 50.479 19.438l36.844-35.974C195.245 12.91 165.798 0 130.55 0 79.49 0 35.393 29.301 13.925 71.947l42.211 32.783c10.59-31.477 39.891-54.251 74.414-54.251"></path></svg>
                    <span>Continue with Google</span>
                </a>
            </div>

            <p class="signup-text">Already registered? <a href="login.php">Login</a></p>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<!-- Popup used by auth pages for consistent messages -->
<div class="popup" id="popupMsg">
    <div class="popup-content">
        <div id="popupText"></div>
        <button onclick="closePopup()">OK</button>
    </div>
</div>
<script>
function showPopup(msg) {
    document.getElementById('popupText').innerHTML = msg;
    document.getElementById('popupMsg').style.display = 'flex';
}
function closePopup() {
    document.getElementById('popupMsg').style.display = 'none';
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon  = document.getElementById(fieldId + '-icon');
    if (!field) return;
    const isHidden = field.type === 'password';
    field.type = isHidden ? 'text' : 'password';
    icon.innerHTML = isHidden
        ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}
</script>

<?php if (!empty($error)): ?>
<script>showPopup("<?= htmlspecialchars($error, ENT_QUOTES) ?>");</script>
<?php endif; ?>
<?php if (!empty($success)): ?>
<script>
    // In development mode, the success message might contain HTML (the verification link)
    // We use a longer timeout or no timeout if the dev link is present
    <?php if (strpos($success, '🛠️') !== false): ?>
        showPopup(`<?= $success ?>`);
    <?php else: ?>
        showPopup("<?= htmlspecialchars($success, ENT_QUOTES) ?>");
        setTimeout(function(){ window.location.href = 'login.php'; }, 2500);
    <?php endif; ?>
</script>
<?php endif; ?>
</body>
</html>
