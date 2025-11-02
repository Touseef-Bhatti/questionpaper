<?php
/**
 * Database Migration: Add Google OAuth support
 * Run this script once to add Google OAuth columns to the users table
 */

require_once __DIR__ . '/../db_connect.php';

try {
    // Check if users table exists and add google_id column if it doesn't exist
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'google_id'");
    
    if ($result->num_rows == 0) {
        // Add google_id column
        $sql = "ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email";
        $conn->query($sql);
        echo "Added google_id column to users table.\n";
    } else {
        echo "google_id column already exists in users table.\n";
    }
    
    // Check if users table exists and add oauth_provider column if it doesn't exist
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'oauth_provider'");
    
    if ($result->num_rows == 0) {
        // Add oauth_provider column
        $sql = "ALTER TABLE users ADD COLUMN oauth_provider ENUM('local', 'google') DEFAULT 'local' AFTER google_id";
        $conn->query($sql);
        echo "Added oauth_provider column to users table.\n";
    } else {
        echo "oauth_provider column already exists in users table.\n";
    }
    
    // Modify password column to allow NULL for OAuth users
    $sql = "ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL";
    $conn->query($sql);
    echo "Modified password column to allow NULL for OAuth users.\n";
    
    // Create index for google_id for better performance
    $result = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_google_id'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE users ADD INDEX idx_google_id (google_id)";
        $conn->query($sql);
        echo "Added index for google_id column.\n";
    } else {
        echo "Index for google_id already exists.\n";
    }
    
    echo "Database migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
