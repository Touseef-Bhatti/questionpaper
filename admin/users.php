<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'verify') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("UPDATE users SET verified = 1 WHERE id = $id");
            $message = 'User verified.';
        }
    } elseif ($action === 'unverify') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("UPDATE users SET verified = 0 WHERE id = $id");
            $message = 'User unverified.';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("DELETE FROM users WHERE id = $id");
            $message = 'User deleted.';
        }
    }
}

$users = $conn->query("SELECT id, name, email, role, verified, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .nav { margin-bottom: 20px; }
        .nav a { color: #1e3c72; text-decoration: none; font-weight: 500; }
        .nav a:hover { text-decoration: underline; }
        .msg { background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8f9fa; color: #374151; padding: 12px; text-align: left; border-bottom: 2px solid #e1e5e9; font-weight: 600; }
        td { padding: 12px; border-bottom: 1px solid #e1e5e9; }
        tr:hover { background: #f8f9fa; }
        button { background: #1e3c72; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 0.9rem; margin-right: 4px; }
        button:hover { background: #152d52; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
    </style>
    
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="admin-container">
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        <h1>👥 All Users</h1>
        <?php if ($message): ?><p class="msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 0;
                while ($row = $users->fetch_assoc()): 
                    $count++;
                ?>
                <tr>
                    <td>#<?= (int)$row['id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td>
                        <?php if ($row['verified']): ?>
                            <span class="badge badge-success">✓ Verified</span>
                        <?php else: ?>
                            <span class="badge badge-warning">⚠ Unverified</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(ucfirst($row['role'] ?? 'user')) ?></td>
                    <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                    <td>
                        <?php if (!$row['verified']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="verify">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" style="background: #28a745;">Verify</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="unverify">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" style="background: #ffc107; color: #000;">Unverify</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" style="background: #dc3545;">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <?php if ($count === 0): ?>
            <div style="text-align: center; padding: 40px; color: #6b7280;">
                <p>No users found.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>


