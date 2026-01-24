<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php'; // Include security helper for CSRF

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if already logged in as admin
if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin','superadmin'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token. Please reload the page.';
    } else {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if ($email !== '' && $password !== '') {
            // Use prepared statement
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
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = strtolower($user['role']);
                        header('Location: dashboard.php');
                        exit;
                    }
                }
                $stmt->close();
            }
            $error = 'Invalid credentials or not authorized.';
        } else {
            $error = 'Please enter email and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  
    <link rel="stylesheet" href="../css/main.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Admin Login</title>
    <link rel="stylesheet" href="../css/admin.css">
    
</head>
<body>
    <?php include __DIR__ . '/../header.php'; ?>
    <div class="admin-auth">
        <h2>Admin Login</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Sign in</button>
        </form>
        <a class="back" href="../index.php">← Back to site</a>
        <a class="back" href="../login.php">User Login</a>
    </div>
    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>
