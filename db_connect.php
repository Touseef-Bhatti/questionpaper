<?php
// Load environment configuration
require_once __DIR__ . '/config/env.php';

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
