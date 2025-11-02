<?php
// Load environment configuration
require_once __DIR__ . '/config/env.php';

// Database configuration from environment variables
$host = EnvLoader::get('DB_HOST', 'localhost');
$user = EnvLoader::get('DB_USER', 'your_db_user');
$password = EnvLoader::get('DB_PASSWORD', 'your_db_password');
$database = EnvLoader::get('DB_NAME', 'your_database_name');

// Create connection with error handling
try {
    $conn = new mysqli($host, $user, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Set charset for security
    $conn->set_charset("utf8mb4");
    
    // Log successful connection in development
    if (EnvLoader::isDevelopment()) {
        error_log("Database connected successfully to: $database");
    }
    
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
// Create question_papers table for saving generated papers per user
$conn->query("CREATE TABLE IF NOT EXISTS question_papers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    paper_title VARCHAR(255),
    paper_content TEXT,
    is_favourite TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Add Google OAuth columns to users table if they don't exist
try {
    // Check and add google_id column
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'google_id'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email");
    }
    
    // Check and add oauth_provider column
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'oauth_provider'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN oauth_provider ENUM('local', 'google') DEFAULT 'local' AFTER google_id");
    }
    
    // Modify password column to allow NULL for OAuth users
    $conn->query("ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL");
    
    // Add index for google_id for better performance
    $result = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_google_id'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD INDEX idx_google_id (google_id)");
    }
} catch (Exception $e) {
    // Log the error but don't break the connection
    error_log('Google OAuth migration error: ' . $e->getMessage());
}

// Create contact_messages table for storing contact form submissions
$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    email VARCHAR(191) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    user_agent TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Initialize subscription and payment system tables
if (file_exists(__DIR__ . '/database/subscription_schema.sql')) {
    $schema_sql = file_get_contents(__DIR__ . '/database/subscription_schema.sql');
    // Remove comments and split by semicolon
    $statements = array_filter(array_map('trim', explode(';', $schema_sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            $conn->query($statement);
        }
    }
}

// Create password_resets table for forgot-password flow
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
?>
