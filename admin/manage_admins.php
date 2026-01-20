<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireSuperAdmin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = strtolower(trim($_POST['role'] ?? 'admin'));
        
        if ($name !== '' && $email !== '' && $password !== '' && in_array($role, ['admin', 'superadmin', 'super_admin'])) {
            $nameEsc = $conn->real_escape_string($name);
            $emailEsc = $conn->real_escape_string($email);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $roleEsc = $conn->real_escape_string($role);
            
            // Check if admin already exists
            $checkResult = $conn->query("SELECT id FROM admins WHERE email = '$emailEsc'");
            if ($checkResult && $checkResult->num_rows > 0) {
                $message = 'Admin with this email already exists.';
            } else {
                $conn->query("INSERT INTO admins (name, email, password, role, created_at) VALUES ('$nameEsc', '$emailEsc', '$hash', '$roleEsc', NOW())");
                $message = 'Admin created successfully.';
            }
        } else {
            $message = 'Please fill all fields correctly.';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // Prevent deleting the current admin
            $currentAdminId = $_SESSION['id'] ?? $_SESSION['admin_id'] ?? null;
            if ($id === $currentAdminId) {
                $message = 'You cannot delete your own account.';
            } else {
                $conn->query("DELETE FROM admins WHERE id = $id");
                $message = 'Admin deleted successfully.';
            }
        }
    }
}

$admins = $conn->query("SELECT id, name, email, role, created_at FROM admins ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .nav { margin-bottom: 20px; }
        .nav a { color: #1e3c72; text-decoration: none; font-weight: 500; }
        .nav a:hover { text-decoration: underline; }
        .msg { background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        
        .form-section { background: #fff; border: 1px solid #e1e5e9; border-radius: 12px; padding: 24px; margin-bottom: 32px; }
        .form-section h2 { margin-top: 0; color: #1e3c72; margin-bottom: 20px; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #374151; }
        .form-group input, 
        .form-group select { width: 100%; padding: 10px; border: 1px solid #e1e5e9; border-radius: 6px; font-size: 1rem; }
        
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        
        button[type="submit"] { background: #1e3c72; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: 600; }
        button[type="submit"]:hover { background: #152d52; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; border: 1px solid #e1e5e9; border-radius: 12px; overflow: hidden; }
        th { background: #f8f9fa; color: #374151; padding: 12px 16px; text-align: left; border-bottom: 2px solid #e1e5e9; font-weight: 600; }
        td { padding: 12px 16px; border-bottom: 1px solid #f1f3f4; }
        tr:hover { background: #f8f9fa; }
        
        .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .badge-admin { background: #fff3e0; color: #ef6c00; }
        .badge-superadmin { background: #fce4ec; color: #c2185b; }
        
        .btn-small { padding: 8px 12px; font-size: 0.85rem; border: none; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; }
        .btn-small.btn-delete { background: #dc3545; color: white; }
        .btn-small.btn-delete:hover { background: #c82333; }
        
        .empty-state { text-align: center; padding: 40px; color: #6b7280; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="admin-container">
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        
        <h1>👨‍💼 Manage Admins</h1>
        <?php if ($message): ?>
            <p class="msg"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <!-- Create New Admin Form -->
        <div class="form-section">
            <h2>Create New Admin</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" placeholder="Enter admin name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="Enter email address" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter password" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Super Admin</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit">Create Admin</button>
            </form>
        </div>

        <!-- Existing Admins Table -->
        <div class="form-section">
            <h2>Existing Admins</h2>
            
            <?php 
            $adminCount = 0;
            $adminList = [];
            if ($admins) {
                while ($row = $admins->fetch_assoc()) {
                    $adminCount++;
                    $adminList[] = $row;
                }
            }
            ?>
            
            <?php if ($adminCount > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminList as $admin): ?>
                        <tr>
                            <td>#<?= (int)$admin['id'] ?></td>
                            <td><?= htmlspecialchars($admin['name']) ?></td>
                            <td><?= htmlspecialchars($admin['email']) ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower(str_replace('_', '', $admin['role'])) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $admin['role'])) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y H:i', strtotime($admin['created_at'])) ?></td>
                            <td>
                                <?php $currentAdminId = $_SESSION['id'] ?? $_SESSION['admin_id'] ?? null; ?>
                                <?php if ($admin['id'] !== $currentAdminId): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this admin account?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$admin['id'] ?>">
                                        <button type="submit" class="btn-small btn-delete">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #6b7280; font-size: 0.9rem;">Current Admin</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No admin accounts found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>
