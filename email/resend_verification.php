<?php
require_once __DIR__ . '/../db_connect.php';
require_once 'phpmailer_mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Invalid email address.';
    } else {
        $emailEsc = $conn->real_escape_string($email);
        $sql = "SELECT id, token, verified FROM users WHERE email = '$emailEsc'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['verified'] == 1) {
                $msg = 'Your account is already verified.';
            } else {
                // Optionally generate a new token here
                $token = $row['token'];
                if (!$token) {
                    $token = bin2hex(random_bytes(32));
                    $conn->query("UPDATE users SET token = '$token' WHERE id = " . intval($row['id']));
                }
                if (sendVerificationEmail($email, $token)) {
                    $msg = 'Verification email resent. Please check your inbox.';
                } else {
                    $msg = 'Failed to send verification email.';
                }
            }
        } else {
            $msg = 'No account found with that email.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resend Verification Email</title>
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
<?php include '../header.php'; ?>
<div class="login-content">
    <h2>Resend Verification Email</h2>
    <?php if (!empty($msg)): ?><p style="color:blue;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <form method="POST">
        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>
        <button type="submit">Resend Email</button>
    </form>
    <p><a href="../auth/login.php">Back to Login</a></p>
</div>
<?php include '../footer.php'; ?>
</body>
</html>
