<?php
/**
 * Environment Configuration Diagnostic
 * Access: https://paper.bhattichemicalsindustry.com.pk/env-debug.php?token=YOUR_TOKEN
 * 
 * Token is: b955114cbf6c7c12ec8de00a8a008e53
 */

// Security: Simple token check
$expectedToken = md5('meilisearch_debug_2026');
$providedToken = $_GET['token'] ?? '';
$isTokenValid = ($providedToken === $expectedToken);

// Allow if IP is localhost or from same server
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
$isSameServer = ($_SERVER['REMOTE_ADDR'] ?? '') === ($_SERVER['SERVER_ADDR'] ?? '');

// Also allow if APP_ENV is development
$isDevelopment = getenv('APP_ENV') === 'development' || getenv('APP_ENV') === 'local';

// TEMPORARILY DISABLED - Remove this and restore security check after debugging
$isAllowed = true;

if (!$isAllowed && getenv('APP_ENV') !== 'local') {
    // SECURITY CHECK TEMPORARILY DISABLED FOR DEBUGGING
    // Uncomment the code below to re-enable after debugging
    /*
    http_response_code(403);
    echo '<h2>403 Forbidden</h2>';
    echo '<p>Access denied. This tool is for debugging only.</p>';
    echo '<p style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">';
    echo '<strong>To access, use the token in the URL:</strong><br>';
    echo 'https://paper.bhattichemicalsindustry.com.pk/env-debug.php?token=<strong>b955114cbf6c7c12ec8de00a8a008e53</strong>';
    echo '</p>';
    exit;
    */
}

echo "<!DOCTYPE html><html><head>";
echo "<title>Environment Debug | Paper Generator</title>";
echo "<style>";
echo "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }";
echo ".container { max-width: 900px; margin: 0 auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }";
echo "h1 { color: #2196F3; border-bottom: 2px solid #2196F3; padding-bottom: 15px; }";
echo ".section { margin: 25px 0; }";
echo ".status-ok { color: #4CAF50; font-weight: bold; }";
echo ".status-err { color: #f44336; font-weight: bold; }";
echo ".status-warn { color: #FF9800; font-weight: bold; }";
echo "pre { background: #f5f5f5; padding: 15px; border-radius: 5px; border-left: 4px solid #2196F3; overflow-x: auto; }";
echo "table { width: 100%; border-collapse: collapse; margin: 15px 0; }";
echo "th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }";
echo "th { background: #f5f5f5; font-weight: 600; }";
echo ".alert { padding: 15px; border-radius: 5px; margin: 15px 0; }";
echo ".alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }";
echo ".alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }";
echo ".alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }";
echo "</style>";
echo "</head><body>";

echo "<div class='container'>";
echo "<h1>🔍 Environment Configuration Diagnostic</h1>";
echo "<div class='alert alert-danger' style='background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
echo "<strong>⚠️ SECURITY WARNING:</strong> This debug page is temporarily accessible without authentication for debugging purposes.";
echo "<br><strong>Action Required:</strong> Delete this file after debugging or re-enable security!";
echo "</div>";

// Load environment
require_once __DIR__ . '/config/env.php';
EnvLoader::load();

// 1. SERVER INFO
echo "<div class='section'>";
echo "<h2>📡 Server Information</h2>";
echo "<table>";
echo "<tr><th>Property</th><th>Value</th></tr>";
echo "<tr><td>Server Name</td><td>" . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "</td></tr>";
echo "<tr><td>Remote Address</td><td>" . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "</td></tr>";
echo "<tr><td>PHP Version</td><td>" . PHP_VERSION . "</td></tr>";
echo "<tr><td>Document Root</td><td>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</td></tr>";
echo "<tr><td>Docker</td><td>" . (file_exists('/.dockerenv') ? '<span class="status-ok">✅ YES</span>' : '<span class="status-err">❌ NO</span>') . "</td></tr>";
echo "<tr><td>APP_ENV</td><td>" . (getenv('APP_ENV') ?: '<span class="status-warn">Not Set</span>') . "</td></tr>";
echo "</table>";
echo "</div>";

// 2. ENV FILES
echo "<div class='section'>";
echo "<h2>📁 Available .env Files</h2>";
echo "<table>";
echo "<tr><th>File</th><th>Status</th><th>Size</th><th>Modified</th></tr>";
$configDir = __DIR__ . '/config';
foreach (['.env', '.env.local', '.env.production'] as $file) {
    $path = $configDir . '/' . $file;
    if (file_exists($path)) {
        $status = '<span class="status-ok">✅ EXISTS</span>';
        $size = number_format(filesize($path)) . ' B';
        $mtime = date('Y-m-d H:i:s', filemtime($path));
    } else {
        $status = '<span class="status-err">❌ MISSING</span>';
        $size = '-';
        $mtime = '-';
    }
    echo "<tr><td>$file</td><td>$status</td><td>$size</td><td>$mtime</td></tr>";
}
echo "</table>";
echo "</div>";

// 3. CONFIGURATION VALUES
echo "<div class='section'>";
echo "<h2>⚙️ Loaded Configuration</h2>";
$config = [
    'APP_ENV' => 'Environment',
    'APP_NAME' => 'App Name',
    'MEILISEARCH_HOST' => 'Meilisearch Host',
    'MEILISEARCH_API_KEY' => 'Meilisearch API Key',
    'DB_HOST' => 'Database Host',
    'DB_USER' => 'Database User',
    'SAFEPAY_ENVIRONMENT' => 'SafePay Environment',
];
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
foreach ($config as $env => $label) {
    $value = EnvLoader::get($env, '❌ NOT SET');
    // Mask sensitive data
    if (strpos($env, 'KEY') !== false || strpos($env, 'PASSWORD') !== false || strpos($env, 'SECRET') !== false) {
        $value = $value ? substr($value, 0, 8) . '...' : $value;
    }
    $status = substr($value, 0, 3) === '❌' ? 'status-err' : 'status-ok';
    echo "<tr><td>$label</td><td><span class='$status'>$value</span></td></tr>";
}
echo "</table>";
echo "</div>";

// 4. MEILISEARCH CONNECTION TEST
echo "<div class='section'>";
echo "<h2>🔗 Meilisearch Connection Test</h2>";

require_once __DIR__ . '/services/MeilisearchService.php';
$meili = new MeilisearchService();

if ($meili->isAvailable()) {
    echo "<div class='alert alert-success'>";
    echo "✅ <strong>Meilisearch is CONFIGURED</strong>";
    echo "<pre>";
    echo "Host: " . EnvLoader::get('MEILISEARCH_HOST') . "\n";
    echo "API Key: " . substr(EnvLoader::get('MEILISEARCH_API_KEY', ''), 0, 10) . "...\n";
    echo "Timeout: " . EnvLoader::get('MEILISEARCH_TIMEOUT', '10') . "s";
    echo "</pre>";
    
    echo "<strong>Testing connection...</strong><br>";
    try {
        $testResult = $meili->ensureIndex();
        if ($testResult) {
            echo "<div class='alert alert-success'>✅ Connection SUCCESSFUL</div>";
        } else {
            echo "<div class='alert alert-danger'>⚠️ Connection failed - could not ensure index</div>";
        }
    } catch (Throwable $e) {
        echo "<div class='alert alert-danger'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='alert alert-danger'>";
    echo "❌ <strong>Meilisearch is NOT CONFIGURED</strong>";
    echo "<p>Missing values:</p>";
    echo "<ul>";
    if (!EnvLoader::get('MEILISEARCH_HOST')) echo "<li>MEILISEARCH_HOST</li>";
    if (!EnvLoader::get('MEILISEARCH_API_KEY')) echo "<li>MEILISEARCH_API_KEY</li>";
    echo "</ul>";
    echo "</div>";
}
echo "</div>";

// 5. ACTIONS
echo "<div class='section'>";
echo "<h2>🔧 Troubleshooting Actions</h2>";
echo "<div class='alert alert-info'>";
echo "<strong>If Meilisearch shows as NOT CONFIGURED:</strong><br>";
echo "1. Verify .env.production has the correct MEILISEARCH_HOST and MEILISEARCH_API_KEY<br>";
echo "2. Check that APP_ENV environment variable is set to 'production'<br>";
echo "3. For Docker, ensure the .env file is mounted correctly<br>";
echo "4. After making changes, restart PHP/web server<br>";
echo "5. Refresh this page to confirm changes";
echo "</div>";
echo "</div>";

// 6. HELPFUL LINKS
echo "<div class='section'>";
echo "<h2>📚 Resources</h2>";
echo "<ul>";
echo "<li><a href='https://cloud.meilisearch.com' target='_blank'>Meilisearch Cloud Dashboard</a></li>";
echo "<li><a href='" . __DIR__ . "/MEILISEARCH_CLOUD_SETUP.md' target='_blank'>Setup Guide</a></li>";
echo "<li><a href='phpinfo.php'>PHP Info</a></li>";
echo "</ul>";
echo "</div>";

// 7. SECURITY RESTORATION INSTRUCTIONS
echo "<div class='section' style='background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 20px;'>";
echo "<h2>🔒 Restore Security</h2>";
echo "<p>After debugging, restore the security protections by doing ONE of the following:</p>";
echo "<ol>";
echo "<li><strong>DELETE THIS FILE:</strong><br>";
echo "<code>rm /path/to/env-debug.php</code></li>";
echo "<li><strong>OR... Re-enable authentication by uncommenting lines in the file:</strong><br>";
echo "Edit env-debug.php and change:<br>";
echo "<code>\$isAllowed = true;</code><br>";
echo "To:<br>";
echo "<code>\$isAllowed = \$isLocalhost || \$isSameServer || \$isTokenValid || \$isDevelopment;</code>";
echo "</li>";
echo "</ol>";
echo "</div>";

echo "</div>";
echo "</body></html>";
?>
