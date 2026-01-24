<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

$message = '';
$error = '';

// Example: save a site setting in a simple table `settings(key, value)`
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        $siteName = trim($_POST['site_name'] ?? 'Ahmad Learning Hub');
        
        // Use prepared statement
        // Table creation moved to install.php
        $stmt = $conn->prepare("INSERT INTO settings (skey, svalue) VALUES ('site_name', ?) ON DUPLICATE KEY UPDATE svalue = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $siteName, $siteName);
            if ($stmt->execute()) {
                $message = 'Settings saved.';
            } else {
                $error = "Error saving settings: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

$siteName = 'Ahmad Learning Hub';
$stmt = $conn->prepare("SELECT svalue FROM settings WHERE skey='site_name' LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) { 
        $siteName = $res->fetch_assoc()['svalue']; 
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <style>
        .wrap { max-width: 700px; margin: 24px auto; padding: 0 12px; }
        label { display:block; margin: 12px 0 6px; font-weight:600; }
        input[type=text] { width:100%; padding:8px; }
        button { margin-top:12px; padding: 10px 14px; }
        .nav { margin-bottom: 12px; }
        .nav a { margin-right: 8px; }
        .msg { color: green; }
        .err { color: red; }
    </style>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="main-content">
</head>
<body>
    <div class="wrap">
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        <h1>Site Settings</h1>
        <?php if ($message): ?><p class="msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <label for="site_name">Site Name</label>
            <input id="site_name" type="text" name="site_name" value="<?= htmlspecialchars($siteName) ?>" required>
            <button type="submit">Save Settings</button>
        </form>
    </div>
    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>


