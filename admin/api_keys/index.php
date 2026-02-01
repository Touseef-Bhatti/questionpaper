<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../../config/AIKeyConfigManager.php';
require_once __DIR__ . '/../../services/AIKeysSystem.php';

// Set timezone to Pakistan Standard Time
date_default_timezone_set('Asia/Karachi');

requireAdminAuth();
requireSuperAdmin(); // Only Super Admin

$aiKeysSystem = new AIKeysSystem($conn);
$health = $aiKeysSystem->getSystemHealth();

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

// Helper for relative time
function time_elapsed_string($datetime, $full = false) {
    if (!$datetime) return 'Never';
    try {
        $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
        $ago = new DateTime($datetime, new DateTimeZone('UTC')); // Assume DB is UTC
        $ago->setTimezone(new DateTimeZone('Asia/Karachi'));
        
        $diff = $now->diff($ago);

        $weeks = floor($diff->d / 7);
        $days = $diff->d - ($weeks * 7);

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($k === 'w') {
                $val = $weeks;
            } elseif ($k === 'd') {
                $val = $days;
            } else {
                $val = $diff->$k;
            }

            if ($val) {
                $v = $val . ' ' . $v . ($val > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    } catch (Exception $e) {
        return 'Never';
    }
}

// Handle Actions
$message = '';
$messageType = '';

if (isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_status' && isset($_POST['id']) && isset($_POST['status'])) {
        $keyId = intval($_POST['id']);
        $currentStatus = $_POST['status'];
        
        if ($currentStatus === 'active') {
            // Disable/Block
            $aiKeysSystem->disableKey($keyId, 'Manually disabled by admin');
            $message = "Key disabled successfully.";
            $messageType = "success";
        } else {
            // Re-enable (requires custom query as unblockExpiredKeys is for temp blocks)
            // We'll use a direct update for simplicity as AIKeysSystem doesn't have a direct 'enable' method for disabled keys
            $stmt = $conn->prepare("UPDATE ai_api_keys SET status = 'active', disabled_reason = NULL WHERE key_id = ?");
            $stmt->bind_param('i', $keyId);
            if ($stmt->execute()) {
                $message = "Key enabled successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to enable key.";
                $messageType = "error";
            }
        }
    }
}

// Get all accounts and keys
$accounts = $aiKeysSystem->getAllAccounts();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI API Key Management - Admin Dashboard</title>
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
        .account-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .account-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .key-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
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
        .status-disabled { background: #feeced; color: #dc3545; }
        .status-temporarily_blocked { background: #fff3cd; color: #856404; }
        .status-exhausted { background: #e2e3e5; color: #383d41; }
        
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
        .btn-success { background: #28a745; color: white; }
        
        .key-value {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            color: #666;
        }
        .model-badge {
            background: #e7f1ff;
            color: #0d6efd;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../header.php'; ?>
    <div class="admin-container" style="padding: 20px;">
        <div class="top">
            <h1>AI API Key Management</h1>
            <div>
                <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></strong>
                <a class="logout" href="../../logout.php">Logout</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?>" style="padding: 15px; margin-bottom: 20px; border-radius: 4px; background: <?= $messageType === 'success' ? '#d4edda' : '#f8d7da' ?>; color: <?= $messageType === 'success' ? '#155724' : '#721c24' ?>;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- System Health Stats -->
        <h2>System Status</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Keys</h3>
                <p style="font-size: 2em; margin: 10px 0;"><?= $health['total_keys'] ?></p>
                <p class="text-muted">Across <?= $health['accounts'] ?> accounts</p>
            </div>
            <div class="stat-card">
                <h3>Active Keys</h3>
                <p style="font-size: 2em; margin: 10px 0; color: #28a745;"><?= $health['active_keys'] ?></p>
                <p class="text-muted"><?= $health['disabled_keys'] ?> disabled</p>
            </div>
            <div class="stat-card">
                <h3>Security</h3>
                <p style="font-size: 2em; margin: 10px 0;">
                    <?= $health['encryption_enabled'] ? '🔒 Encrypted' : '⚠️ Unencrypted' ?>
                </p>
                <p class="text-muted"><?= $health['healthy'] ? 'System Healthy' : 'System Issues' ?></p>
            </div>
        </div>

        <!-- Accounts and Keys -->
        <?php 
        $hasVisibleAccounts = false;
        foreach ($accounts as $account): 
            $keys = $aiKeysSystem->getAccountKeys($account['account_id'], false); // Get all keys, not just active
            if (empty($keys)) continue; // Skip accounts with no keys (useless)
            $hasVisibleAccounts = true;
        ?>
        <div class="account-section">
            <div class="account-header">
                <div>
                    <h2 style="margin: 0;"><?= htmlspecialchars($account['account_name'] ?? 'Unnamed Account') ?></h2>
                    <span style="color: #666; font-size: 0.9em;">
                        Provider: <strong><?= htmlspecialchars($account['provider_name'] ?? 'Unknown') ?></strong> | 
                        Priority: <strong><?= $account['priority'] ?? 0 ?></strong>
                    </span>
                </div>
                <div style="text-align: right;">
                    <?php $accStatus = $account['status'] ?? 'unknown'; ?>
                    <div class="status-badge status-<?= htmlspecialchars($accStatus) ?>" style="display: inline-block;">
                        Account <?= ucfirst(htmlspecialchars($accStatus)) ?>
                    </div>
                    <div style="margin-top: 5px; font-size: 0.9em;">
                        Used: <?= number_format($account['total_used_today'] ?? 0) ?> / <?= number_format($account['total_daily_limit'] ?? 0) ?>
                    </div>
                </div>
            </div>

            <?php 
            // Keys are already fetched above
            if (empty($keys)): 
            ?>
                <p>No keys found for this account.</p>
            <?php else: ?>
                <table class="key-table">
                    <thead>
                        <tr>
                            <th>Key Name</th>
                            <th>Model</th>
                            <th>Status</th>
                            <th>Used Today</th>
                            <th>Total Usage</th>
                            <th>Last Used</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($keys as $key): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($key['key_name'] ?? 'Unnamed Key') ?></strong><br>
                                <span class="key-value">...<?= isset($key['key_hash']) ? substr($key['key_hash'], 0, 8) : '????' ?></span>
                            </td>
                            <td>
                                <span class="model-badge"><?= htmlspecialchars($key['model_name'] ?? 'Default') ?></span>
                            </td>
                            <td>
                                <?php $status = $key['status'] ?? 'unknown'; ?>
                                <span class="status-badge status-<?= htmlspecialchars($status) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                </span>
                                <?php if ($status === 'temporarily_blocked' && !empty($key['temporary_block_until'])): ?>
                                    <br><small style="color: #856404;">Until <?= formatToKarachi($key['temporary_block_until']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $used = $key['used_today'] ?? 0;
                                    $limit = $key['daily_limit'] ?? 1; // Avoid div by zero
                                    $percent = ($limit > 0) ? min(100, ($used / $limit) * 100) : 0;
                                ?>
                                <?= number_format($used) ?> / <?= number_format($key['daily_limit'] ?? 0) ?>
                                <div style="background: #eee; height: 4px; border-radius: 2px; margin-top: 4px; width: 100px;">
                                    <div style="background: #0d6efd; height: 100%; border-radius: 2px; width: <?= $percent ?>%;"></div>
                                </div>
                            </td>
                            <td>
                                Failures: <?= $key['consecutive_failures'] ?? 0 ?>
                            </td>
                            <td><?= time_elapsed_string($key['last_used_at']) ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id" value="<?= $key['key_id'] ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                                    
                                    <?php if ($status === 'active'): ?>
                                        <button type="submit" class="action-btn btn-danger" onclick="return confirm('Disable this key?')">Disable</button>
                                    <?php else: ?>
                                        <button type="submit" class="action-btn btn-success">Enable</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if (!$hasVisibleAccounts): ?>
            <div class="alert alert-warning">
                No active keys found. Please check your .env.local configuration.
            </div>
        <?php endif; ?>
        
    </div>
    <?php include __DIR__ . '/../../footer.php'; ?>
</body>
</html>
