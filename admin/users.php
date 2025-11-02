<?php
require_once __DIR__ . '/../db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    header('Location: login.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        // Manage ADMINS table (separate from regular users)
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = strtolower(trim($_POST['role'] ?? 'admin'));
        if ($name !== '' && $email !== '' && $password !== '' && in_array($role, ['admin','superadmin'])) {
            $nameEsc = $conn->real_escape_string($name);
            $emailEsc = $conn->real_escape_string($email);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $roleEsc = $conn->real_escape_string($role);
            $conn->query("CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(191) NOT NULL, email VARCHAR(191) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, role ENUM('admin','superadmin') NOT NULL DEFAULT 'admin', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $conn->query("INSERT INTO admins (name, email, password, role) VALUES ('$nameEsc', '$emailEsc', '$hash', '$roleEsc')");
            $message = 'Admin created.';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("DELETE FROM admins WHERE id=$id");
            $message = 'Admin deleted.';
        }
    }
}

$users = $conn->query("SELECT id, name, email, role FROM admins ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/footer.css">
    
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="wrap">
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        <h1>Manage Admins</h1>
        <?php if ($message): ?><p class="msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <h3>Create New Admin</h3>
        <form method="POST" class="row">
            <input type="hidden" name="action" value="create">
            <input type="text" name="name" placeholder="Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role" required>
                <option value="admin" selected>Admin</option>
                <option value="superadmin">Super Admin</option>
            </select>
            <button type="submit">Add</button>
        </form>

        <h3>Existing Admins</h3>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
            <tbody>
            <?php while ($row = $users->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['role']) ?></td>
                    <td>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this user?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>


