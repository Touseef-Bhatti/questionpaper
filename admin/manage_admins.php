<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../email/phpmailer_mailer.php';
requireSuperAdmin();

$message = '';
$error = '';

$verificationEmail = 'touseef12345bhatti@gmail.com';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = strtolower(trim($_POST['role'] ?? 'admin'));
            
            if ($name !== '' && $email !== '' && $password !== '' && in_array($role, ['admin', 'superadmin', 'super_admin'])) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $message = 'Admin with this email already exists.';
                } else {
                    $stmt->close();
                    
                    // Create pending action
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    $stmt = $conn->prepare("INSERT INTO pending_admin_actions (action_type, name, email, password_hash, role, token, expires_at) VALUES ('create', ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $name, $email, $hash, $role, $token, $expires);
                    
                    if ($stmt->execute()) {
                        $details = "<strong>Name:</strong> $name<br><strong>Email:</strong> $email<br><strong>Role:</strong> " . ucfirst($role);
                        if (sendAdminActionVerificationEmail($verificationEmail, 'create', $token, $details)) {
                            $message = 'A verification email has been sent to ' . $verificationEmail . '. Please verify to complete admin creation.';
                        } else {
                            $message = 'Pending action created, but failed to send verification email.';
                        }
                    } else {
                        $message = 'Error creating pending action.';
                    }
                }
                $stmt->close();
            } else {
                $message = 'Please fill all fields correctly.';
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $currentAdminId = $_SESSION['id'] ?? $_SESSION['admin_id'] ?? null;
                if ($id === $currentAdminId) {
                    $message = 'You cannot delete your own account.';
                } else {
                    // Fetch admin details for the email
                    $stmt = $conn->prepare("SELECT name, email FROM admins WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $adminToDelete = $res->fetch_assoc();
                    $stmt->close();

                    if ($adminToDelete) {
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                        
                        $stmt = $conn->prepare("INSERT INTO pending_admin_actions (action_type, admin_id, token, expires_at) VALUES ('delete', ?, ?, ?)");
                        $stmt->bind_param("iss", $id, $token, $expires);
                        
                        if ($stmt->execute()) {
                            $details = "<strong>Admin to Delete:</strong> " . htmlspecialchars($adminToDelete['name']) . " (" . htmlspecialchars($adminToDelete['email']) . ")";
                            if (sendAdminActionVerificationEmail($verificationEmail, 'delete', $token, $details)) {
                                $message = 'A verification email has been sent to ' . $verificationEmail . '. Please verify to complete admin deletion.';
                            } else {
                                $message = 'Pending deletion created, but failed to send verification email.';
                            }
                        } else {
                            $message = 'Error creating pending deletion action.';
                        }
                    } else {
                        $message = 'Admin not found.';
                    }
                }
            }
        }
    }
}

$admins = $conn->query("SELECT id, name, email, role, created_at FROM admins ORDER BY created_at DESC");

// Fetch pending actions
$pendingActions = $conn->query("SELECT id, action_type, name, email, role, created_at FROM pending_admin_actions ORDER BY created_at DESC");
include_once __DIR__ . '/header.php';
?>
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

    <div class="admin-container">
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        
        <h1>👨‍💼 Manage Admins</h1>
        <?php if ($message): ?>
            <p class="msg"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <!-- Pending Actions Section -->
        <?php if ($pendingActions && $pendingActions->num_rows > 0): ?>
            <div class="form-section" style="border-left: 5px solid #f59e0b;">
                <h2 style="color: #d97706;">⏳ Pending Verifications</h2>
                <p style="color: #6b7280; margin-bottom: 15px;">These actions have been initiated and are waiting for verification from <strong><?= htmlspecialchars($verificationEmail) ?></strong>.</p>
                <table>
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Role</th>
                            <th>Initiated</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pending = $pendingActions->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="badge" style="background: <?= $pending['action_type'] === 'create' ? '#dcfce7' : '#fee2e2' ?>; color: <?= $pending['action_type'] === 'create' ? '#166534' : '#991b1b' ?>;">
                                    <?= strtoupper($pending['action_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($pending['name'] ?: 'Admin ID: ' . $pending['admin_id']) ?>
                                <?php if ($pending['email']): ?><br><small><?= htmlspecialchars($pending['email']) ?></small><?php endif; ?>
                            </td>
                            <td><?= $pending['role'] ? ucfirst($pending['role']) : '-' ?></td>
                            <td><?= date('M d, H:i', strtotime($pending['created_at'])) ?></td>
                            <td style="color: #f59e0b; font-weight: 600;">Waiting for Verification</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Create New Admin Form -->
        <div class="form-section">
            <h2>Create New Admin</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
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
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
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
    
    

