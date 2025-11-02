
<?php
include '../db_connect.php'; // mysqli $conn
require_once '../config/google_oauth.php';
session_start();

// Handle error messages from OAuth callback
$error = '';
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// When form submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"] ?? '';
    $password = $_POST["password"] ?? '';

    // Prepared statement with mysqli
    if ($stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1")) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($user && password_verify($password, $user["password"])) {
            // Regenerate session to prevent session fixation
            session_regenerate_id(true);

            // Store session data
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["email"] = $user["email"];
            // Normal site users don't use role; set a default
            $_SESSION["role"] = $_SESSION["role"] ?? 'user';
            
            // Handle remember me functionality
            if (isset($_POST['remember_me']) && $_POST['remember_me']) {
                // Set cookies for 30 days
                $expiry = time() + (30 * 24 * 60 * 60);
                setcookie('remember_email', $email, $expiry, '/', '', true, true);
                setcookie('remember_user', $user['name'], $expiry, '/', '', true, true);
            } else {
                // Clear remember me cookies if unchecked
                setcookie('remember_email', '', time() - 3600, '/', '', true, true);
                setcookie('remember_user', '', time() - 3600, '/', '', true, true);
            }

            // Redirect to intended page or default to site root index.php
            $redirectTo = $_SESSION['redirect_after_login'] ?? '../index.php';
            unset($_SESSION['redirect_after_login']); // Clean up the redirect session variable
            header("Location: " . $redirectTo);
            exit;
        } else {
            $error = "Invalid email or password";
        }
    } else {
        $error = "Login unavailable. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - QPaperGen</title>
    <!-- <link rel="stylesheet" href="../css/main.css"> -->
 
    <link rel="stylesheet" href="../css/login.css">

    </style>
</head>
<body>
<?php include '../header.php'; ?>

<div class="main-content">
  <div class="auth-page">
    <div class="auth-card">
      <h1 class="auth-title">Welcome back</h1>
      <p class="auth-subtitle">Sign in to your account</p>
      <form method="POST" action="" autocomplete="off" style="margin-bottom: 0;">
        <div class="input-group">
          <label for="email">Email</label>
          <input class="form-control" type="email" name="email" id="email" required placeholder="you@email.com" value="<?= isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : '' ?>">
        </div>
        <div class="input-group">
          <label for="password">Password</label>
          <div class="password-container">
            <input class="form-control" type="password" name="password" id="password" required placeholder="Password" style="padding-right: 40px;">
            <button type="button" class="password-toggle" onclick="togglePassword('password')">
              <span id="password-icon">👁️</span>
            </button>
          </div>
        </div>
        <div class="form-row">
          <div class="remember-me">
            <input type="checkbox" name="remember_me" id="remember_me" <?= isset($_COOKIE['remember_email']) ? 'checked' : '' ?>>
            <label for="remember_me">Remember me</label>
          </div>
          <a href="forgot_password.php" class="link-muted">Forgot your password?</a>
        </div>
        <button class="btn primary btn-block" type="submit">Sign In</button>
      </form>

      <!-- OAuth Section -->
      <div class="oauth-section"  id="google-oauth" >
        <div class="oauth-divider"><span>or continue with</span></div>
        <a href="<?= GoogleOAuthConfig::getAuthUrl('login') ?>" class="google-signin-btn">
           <svg style="height: 24px;" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid" viewBox="0 0 256 262">
  <path fill="#4285F4" d="M255.878 133.451c0-10.734-.871-18.567-2.756-26.69H130.55v48.448h71.947c-1.45 12.04-9.283 30.172-26.69 42.356l-.244 1.622 38.755 30.023 2.685.268c24.659-22.774 38.875-56.282 38.875-96.027"></path>
  <path fill="#34A853" d="M130.55 261.1c35.248 0 64.839-11.605 86.453-31.622l-41.196-31.913c-11.024 7.688-25.82 13.055-45.257 13.055-34.523 0-63.824-22.773-74.269-54.25l-1.531.13-40.298 31.187-.527 1.465C35.393 231.798 79.49 261.1 130.55 261.1"></path>
  <path fill="#FBBC05" d="M56.281 156.37c-2.756-8.123-4.351-16.827-4.351-25.82 0-8.994 1.595-17.697 4.206-25.82l-.073-1.73L15.26 71.312l-1.335.635C5.077 89.644 0 109.517 0 130.55s5.077 40.905 13.925 58.602l42.356-32.782"></path>
  <path fill="#EB4335" d="M130.55 50.479c24.514 0 41.05 10.589 50.479 19.438l36.844-35.974C195.245 12.91 165.798 0 130.55 0 79.49 0 35.393 29.301 13.925 71.947l42.211 32.783c10.59-31.477 39.891-54.251 74.414-54.251"></path>
</svg>
          <span>Continue with Google</span>
        </a>
      </div>

      <p class="signup-text">
        Don't have an account? <br>
        <a href="register.php">Register here</a>
      </p>
    </div>
  </div>
</div> <!-- main-content -->

<?php include '../footer.php'; ?>

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
    const passwordField = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        icon.textContent = '🙈';
    } else {
        passwordField.type = 'password';
        icon.textContent = '👁️';
    }
}
</script>
<?php if (!empty($error)): ?>
<script>showPopup("<?= htmlspecialchars($error) ?>");</script>
<?php endif; ?>
</body>
</html>
