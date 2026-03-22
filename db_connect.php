<?php
// Dynamic asset base calculation (robust for subdirectory deployments and URL rewriting)
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
$scriptDir  = str_replace('\\', '/', dirname($scriptPath));
$docRoot    = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
$appDirFs   = str_replace('\\', '/', __DIR__);
$requestUri = explode('?', $_SERVER['REQUEST_URI'] ?? '/')[0];

$assetBase = '';

if ($docRoot !== '' && strpos($appDirFs, $docRoot) === 0) {
    $appBase = substr($appDirFs, strlen($docRoot));
    if ($appBase === '') { $appBase = '/'; }

    // Use REQUEST_URI to determine depth for the browser, but fallback to SCRIPT_NAME if needed
    $uriPath = (strpos($requestUri, $appBase) === 0) ? $requestUri : $scriptPath;
    $rel = ltrim(substr($uriPath, strlen($appBase)), '/');
    // We don't rtrim() here because a trailing slash means the browser treats it as a directory
    $depth = ($rel === '' || $rel === '/') ? 0 : substr_count($rel, '/');
    $assetBase = ($depth > 0) ? str_repeat('../', $depth) : '';
} else {
    // Fallback: assume root vs deep levels
    $depth = substr_count(trim($requestUri, '/'), '/');
    $assetBase = ($depth > 0) ? str_repeat('../', $depth) : '';
}
// Load environment configuration
require_once __DIR__ . '/config/env.php';

// ========== CRITICAL: Enable error logging for all environments ==========
// Ensure errors are logged to a file even in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Set a custom error handler to catch all errors including fatal ones
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $error_message = "[$errno] $errstr (File: $errfile, Line: $errline)";
    error_log($error_message);
    
    // For fatal errors during production, still return instead of displaying
    if (EnvLoader::isProduction() && ($errno & (E_ERROR | E_COMPILE_ERROR | E_PARSE | E_CORE_ERROR))) {
        // Log it and continue gracefully where possible
        return true;
    }
    return false;
});

// Catch fatal errors
register_shutdown_function(function() {
    $lastError = error_get_last();
    if ($lastError !== null) {
        $error_message = "FATAL: " . $lastError['message'] . " (File: " . $lastError['file'] . ", Line: " . $lastError['line'] . ")";
        error_log($error_message);
    }
});

// Set PHP Time Zone to Pakistan Standard Time (PST)
date_default_timezone_set('Asia/Karachi');

// Database configuration from environment variables
// Support both DB_USERNAME/DB_DATABASE and legacy DB_USER/DB_NAME keys
$host = EnvLoader::get('DB_HOST', EnvLoader::get('MYSQL_HOST', 'localhost'));
$user = EnvLoader::get('DB_USERNAME', EnvLoader::get('DB_USER', 'your_db_user'));
$password = EnvLoader::get('DB_PASSWORD', EnvLoader::get('MYSQL_PASSWORD', 'your_db_password'));
$database = EnvLoader::get('DB_DATABASE', EnvLoader::get('DB_NAME', 'your_database_name'));
$port = EnvLoader::get('DB_PORT', 3306);

// Create connection with error handling
try {
    $conn = new mysqli($host, $user, $password, $database, $port);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Set charset for security
    $conn->set_charset("utf8mb4");

    // Set Time Zone to PST (Pakistan Standard Time, UTC+5)
    $conn->query("SET time_zone = '+05:00';");
    
// Database connection established
// Note: Schema installation moved to install.php
} catch (Exception $e) {
  //  // Log error securely
    error_log("Database connection error: " . $e->getMessage());
    
    // Show user-friendly error in development, generic in production
     if (EnvLoader::isDevelopment()) {
         die("Database connection failed: " . $e->getMessage());
     } else {
         die("Service temporarily unavailable. Please try again later.");
 }
}
