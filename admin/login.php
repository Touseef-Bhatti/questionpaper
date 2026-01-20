<?php
require_once __DIR__ . '/../db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if already logged in as admin
if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin','superadmin'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($email !== '' && $password !== '') {
        $emailEsc = $conn->real_escape_string($email);
        // Authenticate against admins table now that admins and users are separate
        $sql = "SELECT id, name, email, password, role FROM admins WHERE email = '$emailEsc' LIMIT 1";
        $res = $conn->query($sql);
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
        $error = 'Invalid credentials or not authorized.';
    } else {
        $error = 'Please enter email and password.';
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
