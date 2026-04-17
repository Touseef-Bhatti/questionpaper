<?php
/**
 * Environment Detection Test Script
 * Run this script to verify that environment detection is working correctly
 */

// Include the environment loader
require_once 'config/env.php';

// Display current environment detection results
echo "<h2>Environment Detection Test</h2>\n";
echo "<pre>\n";

// Show server information
echo "Server Name: " . ($_SERVER['SERVER_NAME'] ?? 'Not set') . "\n";

echo "\n--- Environment Variables ---\n";
echo "APP_NAME: " . EnvLoader::get('APP_NAME', 'Not set') . "\n";
echo "APP_URL: " . EnvLoader::get('APP_URL', 'Not set') . "\n";
echo "APP_ENV: " . EnvLoader::get('APP_ENV', 'Not set') . "\n";
echo "DB_HOST: " . EnvLoader::get('DB_HOST', 'Not set') . "\n";
echo "DB_NAME: " . EnvLoader::get('DB_NAME', 'Not set') . "\n";
echo "ORDER_ID_PREFIX: " . EnvLoader::get('ORDER_ID_PREFIX', 'Not set') . "\n";
echo "SEND_PAYMENT_EMAILS: " . (EnvLoader::getBool('SEND_PAYMENT_EMAILS') ? 'true' : 'false') . "\n";

echo "\n--- Environment Check Methods ---\n";
echo "isProduction(): " . (EnvLoader::isProduction() ? 'true' : 'false') . "\n";
echo "isDevelopment(): " . (EnvLoader::isDevelopment() ? 'true' : 'false') . "\n";

echo "</pre>\n";

// Check if environment file exists
echo "<h3>Environment File Status</h3>\n";
echo "<pre>\n";

$envFile = __DIR__ . '/config/.env';

echo ".env exists: " . (file_exists($envFile) ? 'YES' : 'NO') . "\n";

echo "</pre>\n";
?>
