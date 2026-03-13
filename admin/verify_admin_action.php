<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
// Verification script doesn't necessarily need SuperAdmin if accessed via token link, 
// but the action itself is for admins.

$message = '';
$error = '';

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid verification link.");
}

// Find the pending action
$stmt = $conn->prepare("SELECT id, action_type, admin_id, name, email, password_hash, role, expires_at FROM pending_admin_actions WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();
$action = $res->fetch_assoc();
$stmt->close();

if (!$action) {
    die("Invalid or expired token.");
}

if (strtotime($action['expires_at']) < time()) {
    // Delete expired action
    $stmt = $conn->prepare("DELETE FROM pending_admin_actions WHERE id = ?");
    $stmt->bind_param("i", $action['id']);
    $stmt->execute();
    $stmt->close();
    die("Verification link has expired.");
}

// Perform the action
$success = false;
if ($action['action_type'] === 'create') {
    // Check if admin already exists
    $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
    $stmt->bind_param("s", $action['email']);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $error = 'Admin with this email already exists.';
        $stmt->close();
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO admins (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $action['name'], $action['email'], $action['password_hash'], $action['role']);
        if ($stmt->execute()) {
            $success = true;
            $message = "Admin account for " . htmlspecialchars($action['name']) . " has been created successfully.";
        } else {
            $error = "Error creating admin account.";
        }
        $stmt->close();
    }
} elseif ($action['action_type'] === 'delete') {
    $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->bind_param("i", $action['admin_id']);
    if ($stmt->execute()) {
        $success = true;
        $message = "Admin account has been deleted successfully.";
    } else {
        $error = "Error deleting admin account.";
    }
    $stmt->close();
}

if ($success) {
    // Delete the pending action
    $stmt = $conn->prepare("DELETE FROM pending_admin_actions WHERE id = ?");
    $stmt->bind_param("i", $action['id']);
    $stmt->execute();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Admin Action - Admin</title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .container { max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .success { color: #155724; background: #d4edda; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .error { color: #721c24; background: #f8d7da; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #1e3c72; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Verification Status</h1>
        <?php if ($message): ?>
            <div class="success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        <a href="manage_admins.php" class="btn">Back to Manage Admins</a>
    </div>
</body>
</html>