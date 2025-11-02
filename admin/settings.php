<?php
require_once __DIR__ . '/../db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    header('Location: login.php');
    exit;
}

$message = '';
// Example: save a site setting in a simple table `settings(key, value)`
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = trim($_POST['site_name'] ?? 'QPaperGen');
    $siteNameEsc = $conn->real_escape_string($siteName);
    $conn->query("CREATE TABLE IF NOT EXISTS settings (skey VARCHAR(191) PRIMARY KEY, svalue TEXT)");
    $conn->query("INSERT INTO settings (skey, svalue) VALUES ('site_name', '$siteNameEsc') ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)");
    $message = 'Settings saved.';
}

$siteName = 'QPaperGen';
$res = $conn->query("SELECT svalue FROM settings WHERE skey='site_name' LIMIT 1");
if ($res && $res->num_rows === 1) { $siteName = $res->fetch_assoc()['svalue']; }
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
        <form method="POST">
            <label for="site_name">Site Name</label>
            <input id="site_name" type="text" name="site_name" value="<?= htmlspecialchars($siteName) ?>" required>
            <button type="submit">Save Settings</button>
        </form>
    </div>
    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>


