<?php
/**
 * Server Diagnostic Script - Check server configuration and errors
 * Access this at: http://yoursite.com/debug_server.php
 * DELETE THIS FILE after debugging!
 */

// Load configuration
require_once 'config/env.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Server Diagnostic Report</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
        h2 { color: #0066cc; margin-top: 20px; }
        .status { margin: 10px 0; padding: 10px; border-left: 4px solid #ddd; }
        .status.ok { background: #d4edda; border-left-color: #28a745; color: #155724; }
        .status.error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .status.warning { background: #fff3cd; border-left-color: #ffc107; color: #856404; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .errors-box { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 4px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table th, table td { text-align: left; padding: 10px; border-bottom: 1px solid #ddd; }
        table th { background: #f8f9fa; font-weight: bold; }
        .warning-box { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0; border: 1px solid #ffc107; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Server Diagnostic Report</h1>
    <p>Generated: <?= date('Y-m-d H:i:s') ?></p>

    <h2>PHP Configuration</h2>
    <div class="status <?= ini_get('display_errors') ? 'ok' : 'warning' ?>">
        <strong>Display Errors:</strong> <?= ini_get('display_errors') ? 'Enabled' : 'Disabled (PRODUCTION MODE)' ?>
    </div>
    <div class="status <?= ini_get('log_errors') ? 'ok' : 'error' ?>">
        <strong>Error Logging:</strong> <?= ini_get('log_errors') ? 'Enabled' : 'Disabled' ?>
    </div>
    <div class="status">
        <strong>Error Log File:</strong> <code><?= ini_get('error_log') ?: 'Not configured' ?></code>
    </div>
    <div class="status">
        <strong>Error Reporting:</strong> <code><?= ini_get('error_reporting') ?></code>
    </div>
    <div class="status">
        <strong>PHP Version:</strong> <?= PHP_VERSION ?>
    </div>
    <div class="status">
        <strong>Memory Limit:</strong> <?= ini_get('memory_limit') ?>
    </div>

    <h2>Application Environment</h2>
    <div class="status">
        <strong>Environment:</strong> <code><?= EnvLoader::get('APP_ENV', 'development') ?></code>
    </div>
    <div class="status">
        <strong>App URL:</strong> <code><?= EnvLoader::get('BASE_URL', 'Not set') ?></code>
    </div>
    <div class="status">
        <strong>App Name:</strong> <?= EnvLoader::get('APP_NAME', 'Not set') ?>
    </div>

    <h2>Database Connection</h2>
    <?php
    try {
        require_once 'db_connect.php';
        if ($conn && $conn->connect_error === null) {
            echo '<div class="status ok"><strong>✓ Database Connected Successfully</strong></div>';
            
            // Get database info
            $result = $conn->query("SELECT VERSION() as version; SELECT DATABASE() as database;");
            if ($result) {
                $row = $result->fetch_assoc();
                echo '<div class="status"><strong>MySQL Version:</strong> ' . $row['version'] . '</div>';
            }
            
            // Check subscription tables
            $tableCheck = $conn->query("SHOW TABLES LIKE 'user_subscriptions'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                echo '<div class="status ok"><strong>✓ Subscription tables exist</strong></div>';
            } else {
                echo '<div class="status warning"><strong>⚠ Subscription tables NOT found</strong></div>';
            }
        } else {
            echo '<div class="status error"><strong>✗ Database Connection Failed</strong>: ' . $conn->connect_error . '</div>';
        }
    } catch (Exception $e) {
        echo '<div class="status error"><strong>✗ Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>

    <h2>File Permissions</h2>
    <?php
    $dirs = [
        'logs' => __DIR__ . '/logs',
        'uploads' => __DIR__ . '/uploads',
        'config' => __DIR__ . '/config',
    ];
    
    foreach ($dirs as $name => $path) {
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
        $status = $exists ? ($writable ? 'ok' : 'warning') : 'error';
        $text = $exists ? ($writable ? '✓ Exists & Writable' : '⚠ Exists but NOT writable') : '✗ NOT found';
        echo '<div class="status ' . $status . '"><strong>' . ucfirst($name) . ':</strong> ' . $text . '</div>';
    }
    ?>

    <h2>Recent PHP Errors</h2>
    <?php
    $logFile = __DIR__ . '/logs/php_errors.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $recent = array_slice($lines, -20); // Last 20 lines
        
        if (count($recent) > 0) {
            echo '<div class="errors-box"><strong>Last 20 errors:</strong><pre style="background: white; padding: 10px; overflow-x: auto; max-height: 300px; font-size: 12px;">';
            foreach (array_reverse($recent) as $line) {
                echo htmlspecialchars($line);
            }
            echo '</pre></div>';
        } else {
            echo '<div class="status ok">✓ No errors logged</div>';
        }
    } else {
        echo '<div class="status warning">⚠ Error log file not created yet: <code>' . $logFile . '</code></div>';
    }
    ?>

    <h2>Session Configuration</h2>
    <table>
        <tr>
            <th>Setting</th>
            <th>Value</th>
        </tr>
        <tr>
            <td>session.save_path</td>
            <td><code><?= ini_get('session.save_path') ?: '(default)' ?></code></td>
        </tr>
        <tr>
            <td>session.gc_maxlifetime</td>
            <td><?= ini_get('session.gc_maxlifetime') ?> seconds</td>
        </tr>
        <tr>
            <td>session.cookie_httponly</td>
            <td><?= ini_get('session.cookie_httponly') ? 'Yes' : 'No' ?></td>
        </tr>
        <tr>
            <td>session.cookie_secure</td>
            <td><?= ini_get('session.cookie_secure') ? 'Yes' : 'No' ?></td>
        </tr>
    </table>

    <div class="warning-box">
        <strong>⚠️ IMPORTANT:</strong> This is a diagnostic tool for debugging. <strong>DELETE this file (debug_server.php) when done!</strong> It can expose sensitive information.
    </div>

    <h2>Next Steps</h2>
    <ol>
        <li>Check the <strong>Recent PHP Errors</strong> section above for any error messages</li>
        <li>Review all <strong>Status indicators</strong> - fix any marked as errors</li>
        <li>If database connected but subscription tables don't exist, run <code>install.php</code></li>
        <li>Try logging in again and refresh this page to see any new errors</li>
        <li>Make sure logs directory is writable for error logging</li>
    </ol>
</div>

</body>
</html>
