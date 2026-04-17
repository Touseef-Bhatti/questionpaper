<?php
/**
 * Debug Environment Configuration
 * Helps diagnose which .env file is being loaded
 */

echo "<h1>🔍 Environment Configuration Diagnostics</h1>";
echo "<pre style='background: #f5f5f5; padding: 20px; border-radius: 5px;'>";

// Show server info
echo "=== SERVER INFORMATION ===\n";
echo "Server Name: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "\n";
echo "Hostname: " . gethostname() . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Current Directory: " . getcwd() . "\n";
echo "Docker Environment: " . (file_exists('/.dockerenv') ? "YES ✅" : "NO ❌") . "\n";
echo "APP_ENV Variable: " . (getenv('APP_ENV') ?: 'Not set') . "\n";
echo "\n";

// Show available .env files
echo "=== AVAILABLE ENV FILES ===\n";
$envDir = __DIR__;
$files = [
    '.env'
];

foreach ($files as $file) {
    $path = $envDir . '/' . $file;
    $exists = file_exists($path);
    $status = $exists ? "✅ EXISTS" : "❌ MISSING";
    echo "$file: $status\n";
    if ($exists) {
        $mtime = filemtime($path);
        echo "  Modified: " . date('Y-m-d H:i:s', $mtime) . "\n";
        $size = filesize($path);
        echo "  Size: " . number_format($size) . " bytes\n";
    }
}
echo "\n";

// Load and show which file is being used
require_once __DIR__ . '/env.php';
EnvLoader::load();

echo "=== LOADED CONFIGURATION ===\n";
$config = [
    'APP_ENV' => 'APP_ENV',
    'APP_NAME' => 'APP_NAME',
    'APP_URL' => 'APP_URL',
    'DB_HOST' => 'DB_HOST',
    'DB_USER' => 'DB_USER',
    'DB_NAME' => 'DB_NAME',
    'SAFEPAY_ENVIRONMENT' => 'SAFEPAY_ENVIRONMENT',
];

foreach ($config as $label => $key) {
    $value = EnvLoader::get(str_replace(' (masked)', '', $key), 'NOT SET');
    if (strpos($label, 'masked') !== false) {
        $value = $value ? substr($value, 0, 10) . '...' : 'NOT SET';
    }
    echo "$label: $value\n";
}
echo "\n";

// Debug environment info
echo "=== ENVIRONMENT INFO ===\n";
echo "All configuration is loaded from config/.env\n";
echo "APP_ENV value controls development vs production behavior\n";
echo "\n";

echo "</pre>";

// Provide actionable recommendations
echo "<div style='background: #e3f2fd; padding: 20px; border-left: 4px solid #2196F3; margin-top: 20px;'>";
echo "<h3>📋 Recommendations:</h3>";
echo "<ul>";

if (!file_exists(__DIR__ . '/.env')) {
    echo "<li>⚠️ config/.env file is missing! Copy from config/.env.template and fill in your values.</li>";
}

echo "<li>Keep sensitive keys in config/.env (not in version control)</li>";
echo "</ul>";
echo "</div>";
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 20px;
    background: #f9f9f9;
}
h1 { color: #2196F3; }
h3 { color: #1976D2; }
pre {
    font-family: 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.6;
}
ul { line-height: 1.8; }
</style>
