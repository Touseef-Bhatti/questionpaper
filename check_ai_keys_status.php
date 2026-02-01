<?php
/**
 * ============================================================================
 * AI Keys System - Verification & Status Report
 * ============================================================================
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config/AIKeyConfigManager.php';
require_once __DIR__ . '/services/AIKeysSystem.php';

echo "<h2>🔍 AI Keys System - Status Report</h2>\n";
echo "<hr>\n";

try {
    // Load configuration
    $configManager = new AIKeyConfigManager(__DIR__ . '/config/.env.local');
    echo "<h3>✅ Configuration Loaded</h3>\n";
    
    // Get system info
    $systemConfig = $configManager->getSystemConfig();
    
    echo "<table border='1' cellpadding='8'>\n";
    echo "<tr><th>Setting</th><th>Value</th></tr>\n";
    foreach ($systemConfig as $key => $value) {
        $value = is_array($value) ? json_encode($value) : $value;
        echo "<tr><td>$key</td><td>$value</td></tr>\n";
    }
    echo "</table>\n";
    
    // Get keys from config
    echo "<h3>📋 Keys from .env.local</h3>\n";
    $envKeys = $configManager->getAllKeys();
    
    if (empty($envKeys)) {
        echo "<p style='color:orange;'>⚠ No keys found in new KEY_N format</p>\n";
        echo "<p>Make sure .env.local has:</p>\n";
        echo "<pre>\nKEY_1=sk-or-v1-your_key\nKEY_1_MODEL=gpt-4-turbo\nKEY_1_PROVIDER=openai\n</pre>\n";
    } else {
        echo "<table border='1' cellpadding='8'>\n";
        echo "<tr><th>Name</th><th>Provider</th><th>Model</th><th>Status</th></tr>\n";
        foreach ($envKeys as $key) {
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($key['name']) . "</code></td>";
            echo "<td>" . htmlspecialchars($key['provider']) . "</td>";
            echo "<td>" . htmlspecialchars($key['model']) . "</td>";
            echo "<td><span style='color:green;'>✓</span></td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // Get accounts
    echo "<h3>📊 Accounts in Database</h3>\n";
    
    try {
        $aiKeys = new AIKeysSystem($conn);
        $dbAccounts = $aiKeys->getAllAccounts();
        
        if (empty($dbAccounts)) {
            echo "<p style='color:orange;'>⚠ No accounts found in database</p>\n";
        } else {
            echo "<table border='1' cellpadding='8'>\n";
            echo "<tr><th>ID</th><th>Name</th><th>Priority</th><th>Keys</th><th>Remaining Quota</th><th>Status</th></tr>\n";
            foreach ($dbAccounts as $account) {
                echo "<tr>";
                echo "<td>" . $account['account_id'] . "</td>";
                echo "<td>" . htmlspecialchars($account['account_name']) . "</td>";
                echo "<td>" . $account['priority'] . "</td>";
                echo "<td><strong>" . ($account['active_keys'] ?? 0) . "</strong></td>";
                echo "<td>" . number_format($account['remaining_quota'] ?? 0) . "</td>";
                echo "<td><span style='color:" . ($account['status'] === 'active' ? 'green' : 'red') . ";'>" . htmlspecialchars($account['status']) . "</span></td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>✗ Error loading accounts from database: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    // Database keys
    echo "<h3>🔑 Database Keys (ai_api_keys)</h3>\n";
    
    $keyCountResult = $conn->query("SELECT COUNT(*) as count FROM ai_api_keys");
    $keyRow = $keyCountResult->fetch_assoc();
    $totalDbKeys = intval($keyRow['count']);
    
    echo "<p><strong>Total keys in database:</strong> $totalDbKeys</p>\n";
    
    if ($totalDbKeys > 0) {
        $dbKeysResult = $conn->query("
            SELECT k.key_id, k.key_name, k.model_name, a.account_name, k.status, k.used_today, k.daily_limit
            FROM ai_api_keys k
            LEFT JOIN ai_accounts a ON k.account_id = a.account_id
            ORDER BY a.priority, k.key_id
            LIMIT 10
        ");
        
        echo "<table border='1' cellpadding='8'>\n";
        echo "<tr><th>ID</th><th>Name</th><th>Account</th><th>Model</th><th>Status</th><th>Usage</th></tr>\n";
        while ($row = $dbKeysResult->fetch_assoc()) {
            $usage = $row['used_today'] . " / " . $row['daily_limit'];
            echo "<tr>";
            echo "<td>" . $row['key_id'] . "</td>";
            echo "<td><code>" . htmlspecialchars($row['key_name'] ?? '-') . "</code></td>";
            echo "<td>" . htmlspecialchars($row['account_name'] ?? 'Unknown') . "</td>";
            echo "<td>" . htmlspecialchars($row['model_name']) . "</td>";
            echo "<td><span style='color:" . ($row['status'] === 'active' ? 'green' : 'red') . ";'>" . htmlspecialchars($row['status']) . "</span></td>";
            echo "<td>$usage</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // System health
    echo "<h3>💚 System Health</h3>\n";
    try {
        $health = $aiKeys->getSystemHealth();
        
        echo "<p>✓ Total Keys: <strong>" . $health['total_keys'] . "</strong></p>\n";
        echo "<p>✓ Active Keys: <strong style='color:green;'>" . $health['active_keys'] . "</strong></p>\n";
        echo "<p>✗ Disabled Keys: <strong style='color:red;'>" . $health['disabled_keys'] . "</strong></p>\n";
        echo "<p>✓ Encryption: <strong style='color:" . ($health['encryption_enabled'] ? 'green' : 'red') . ";'>" . ($health['encryption_enabled'] ? 'Enabled' : 'Disabled') . "</strong></p>\n";
        echo "<p>✓ System Status: <strong style='color:" . ($health['healthy'] ? 'green' : 'red') . ";'>" . ($health['healthy'] ? 'Healthy' : 'No Active Keys') . "</strong></p>\n";
    } catch (Exception $e) {
        echo "<p style='color:red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    // Recommendations
    echo "<h3>📝 Next Steps</h3>\n";
    echo "<ol>\n";
    echo "<li>If you see keys in '.env.local' but not in 'Database Keys': Run <code>install.php</code> to import them</li>\n";
    echo "<li>If encryption shows 'Disabled': Set <code>AI_ENCRYPTION_KEY</code> in .env.local</li>\n";
    echo "<li>Visit admin dashboard: <a href='admin/manage_ai_keys.php'><code>admin/manage_ai_keys.php</code></a></li>\n";
    echo "<li>To add new keys: Edit .env.local with <code>KEY_N=sk-...</code>, then run install.php</li>\n";
    echo "</ol>\n";
    
} catch (Exception $e) {
    echo "<h3 style='color:red;'>✗ Error</h3>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Check:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>.env.local exists and is readable</li>\n";
    echo "<li>config/AIKeyConfigManager.php exists</li>\n";
    echo "<li>services/AIKeysSystem.php exists</li>\n";
    echo "</ul>\n";
}

echo "<hr>\n";
echo "<p><small>Last checked: " . date('Y-m-d H:i:s') . "</small></p>\n";

$conn->close();
?>
