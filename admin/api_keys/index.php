<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../../includes/APIKeyManager.php';

// Set timezone to Pakistan Standard Time
date_default_timezone_set('Asia/Karachi');

requireAdminAuth();
requireSuperAdmin(); // Only Super Admin

$keyManager = new APIKeyManager();
$keyManager->ensureTableExists();

// Helper to verify admin password
function verifyAdminPassword($password) {
    global $conn;
    $email = $_SESSION['email'] ?? '';
    if (empty($email)) return false;
    
    $emailEsc = $conn->real_escape_string($email);
    $sql = "SELECT password FROM admins WHERE email = '$emailEsc' LIMIT 1";
    $res = $conn->query($sql);
    
    if ($res && $row = $res->fetch_assoc()) {
        return password_verify($password, $row['password']) || $password === $row['password'];
    }
    return false;
}

// Helper to format date to Karachi Time
function formatToKarachi($dateString) {
    if (!$dateString) return 'Never';
    try {
        // Assume DB stores UTC
        $dt = new DateTime($dateString, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Karachi'));
        return $dt->format('d M Y, h:i A');
    } catch (Exception $e) {
        return $dateString;
    }
}

// Handle Actions
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'import') {
        $count = $keyManager->importFromEnv();
        $message = "Imported $count new keys from environment files.";
        $messageType = "success";
    } elseif (in_array($_POST['action'], ['delete', 'toggle_status'])) {
        // Password verification for critical actions
        $adminPass = $_POST['admin_password'] ?? '';
        if (empty($adminPass) || !verifyAdminPassword($adminPass)) {
            $message = "Authentication Failed: Incorrect Admin Password.";
            $messageType = "error";
        } else {
            if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
                $keyManager->deleteKey($_POST['id']);
                $message = "Key deleted successfully.";
                $messageType = "success";
            } elseif ($_POST['action'] === 'toggle_status' && isset($_POST['id']) && isset($_POST['status'])) {
                $newStatus = $_POST['status'] === 'active' ? 'inactive' : 'active';
                $keyManager->updateStatus($_POST['id'], $newStatus);
                $message = "Key status updated.";
                $messageType = "success";
            }
        }
    }
}

$keys = $keyManager->getAllKeys();
$stats = $keyManager->getStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Key Management - Admin Dashboard</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/footer.css">
    <link rel="stylesheet" href="../../css/main.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .key-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        .key-table th, .key-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .key-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .status-active { background: #e6f4ea; color: #1e7e34; }
        .status-inactive { background: #feeced; color: #dc3545; }
        .status-rate_limited { background: #fff3cd; color: #856404; }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
        }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-primary { background: #007bff; color: white; }
        
        .key-value {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        .add-form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 15px; }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../header.php'; ?>
    <div class="admin-container" style="padding: 20px;">
        <div class="top">
            <h1>API Key Management</h1>
            <div>
                <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></strong>
                <a class="logout" href="../../logout.php">Logout</a>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?= $messageType ?>" style="padding: 15px; margin-bottom: 20px; border-radius: 4px; background: <?= $messageType === 'success' ? '#d4edda' : '#f8d7da' ?>; color: <?= $messageType === 'success' ? '#155724' : '#721c24' ?>;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Section -->
        <h2>Usage Statistics</h2>
        <div class="stats-grid">
            <?php foreach ($stats as $stat): ?>
            <div class="stat-card">
                <h3><?= htmlspecialchars($stat['account_name']) ?></h3>
                <p><strong>Total Keys:</strong> <?= $stat['total_keys'] ?></p>
                <p><strong>Active:</strong> <?= $stat['active_keys'] ?></p>
                <p><strong>Total Usage:</strong> <?= $stat['total_usage'] ?></p>
                <p><strong>Errors:</strong> <?= $stat['total_errors'] ?></p>
            </div>
            <?php endforeach; ?>
            <?php if (empty($stats)): ?>
            <div class="stat-card">
                <p>No stats available. Import keys to get started.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Add New Key (Removed as per requirements) -->
        <div class="add-form">
            <h3>Import Keys</h3>
            <p>Manually adding keys is disabled. Please import from your environment configuration.</p>
            <div style="margin-top: 10px;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="import">
                    <button type="submit" class="action-btn btn-warning">Import from .env File</button>
                </form>
            </div>
        </div>

        <!-- Keys List -->
        <h2>Manage Keys</h2>
        <table class="key-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Key</th>
                    <th>Status</th>
                    <th>Usage</th>
                    <th>Errors</th>
                    <th>Last Used</th>
                    <th>Auth & Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($keys as $key): ?>
                <tr>
                    <td><?= htmlspecialchars($key['account_name']) ?></td>
                    <td><span class="key-value"><?= substr($key['key_value'], 0, 8) . '...' . substr($key['key_value'], -4) ?></span></td>
                    <td>
                        <span class="status-badge status-<?= $key['status'] ?>">
                            <?= ucfirst($key['status']) ?>
                        </span>
                    </td>
                    <td><?= $key['usage_count'] ?></td>
                    <td><?= $key['error_count'] ?></td>
                    <td><?= formatToKarachi($key['last_used']) ?></td>
                    <td>
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <button type="button" class="action-btn btn-warning" onclick="requestAuth('toggle_status', <?= $key['id'] ?>, '<?= $key['status'] ?>')">
                                <?= $key['status'] === 'active' ? 'Disable' : 'Enable' ?>
                            </button>
                            
                            <button type="button" class="action-btn btn-danger" onclick="requestAuth('delete', <?= $key['id'] ?>, '<?= $key['status'] ?>')">Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($keys)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No API keys found. Import from .env to get started.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Auth Modal -->
    <div id="authModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
        <div style="background-color:#fefefe; margin:15% auto; padding:20px; border:1px solid #888; width:300px; border-radius:8px; box-shadow:0 4px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">Confirm Action</h3>
                <span onclick="closeModal()" style="color:#aaa; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
            </div>
            <p>Please enter your admin password to confirm this action.</p>
            <form method="POST">
                <input type="hidden" name="id" id="modal_id">
                <input type="hidden" name="action" id="modal_action">
                <input type="hidden" name="status" id="modal_status">
                <div class="form-group">
                    <input type="password" name="admin_password" class="form-control" placeholder="Admin Password" required autofocus>
                </div>
                <div style="text-align: right; margin-top: 15px;">
                    <button type="button" onclick="closeModal()" class="action-btn" style="background: #ccc;">Cancel</button>
                    <button type="submit" class="action-btn btn-primary">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function requestAuth(action, id, status) {
        if (action === 'delete') {
            if (!confirm('Are you sure you want to delete this key?')) return;
        }
        document.getElementById('modal_id').value = id;
        document.getElementById('modal_action').value = action;
        document.getElementById('modal_status').value = status;
        document.getElementById('authModal').style.display = 'block';
    }
    function closeModal() {
        document.getElementById('authModal').style.display = 'none';
    }
    // Close on outside click
    window.onclick = function(event) {
        var modal = document.getElementById('authModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
    <?php include __DIR__ . '/../../footer.php'; ?>
</body>
</html>
