<?php
/**
 * Database Schema Fix for Registration System
 * This script ensures all tables have the correct structure for the registration system
 */

require_once __DIR__ . '/db_connect.php';

echo "<!DOCTYPE html>\n<html><head><title>Registration Schema Fix</title></head><body>";
echo "<h1>Fixing Registration Database Schema</h1>";
echo "<style>body { font-family: Arial; margin: 40px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>";

try {
    // Fix users table to include all required fields
    echo "<h2>1. Fixing Users Table Schema</h2>";
    
    // Check and add token column if it doesn't exist
    $checkToken = $conn->query("SHOW COLUMNS FROM users LIKE 'token'");
    if ($checkToken->num_rows == 0) {
        if ($conn->query("ALTER TABLE users ADD COLUMN token VARCHAR(64)")) {
            echo "<p class='success'>✓ Added token column to users table</p>";
        } else {
            echo "<p class='error'>✗ Error adding token column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p class='success'>✓ Token column already exists</p>";
    }
    
    // Check and add verified column if it doesn't exist
    $checkVerified = $conn->query("SHOW COLUMNS FROM users LIKE 'verified'");
    if ($checkVerified->num_rows == 0) {
        if ($conn->query("ALTER TABLE users ADD COLUMN verified TINYINT(1) DEFAULT 1")) {
            echo "<p class='success'>✓ Added verified column to users table</p>";
        } else {
            echo "<p class='error'>✗ Error adding verified column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p class='success'>✓ Verified column already exists</p>";
    }
    
    // Ensure pending_users table exists with correct structure
    echo "<h2>2. Ensuring Pending Users Table Exists</h2>";
    
    $createPending = "CREATE TABLE IF NOT EXISTS pending_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        email VARCHAR(191) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        token VARCHAR(64),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($createPending)) {
        echo "<p class='success'>✓ Pending users table is ready</p>";
    } else {
        echo "<p class='error'>✗ Error creating pending_users table: " . $conn->error . "</p>";
    }
    
    // Add index for performance (MySQL compatible)
    echo "<h2>3. Adding Performance Indexes</h2>";
    
    $indexes = [
        ['name' => 'idx_users_email', 'sql' => 'CREATE INDEX idx_users_email ON users(email)'],
        ['name' => 'idx_users_token', 'sql' => 'CREATE INDEX idx_users_token ON users(token)'],
        ['name' => 'idx_pending_email', 'sql' => 'CREATE INDEX idx_pending_email ON pending_users(email)'],
        ['name' => 'idx_pending_token', 'sql' => 'CREATE INDEX idx_pending_token ON pending_users(token)'],
        ['name' => 'idx_pending_created', 'sql' => 'CREATE INDEX idx_pending_created ON pending_users(created_at)']
    ];
    
    foreach ($indexes as $indexInfo) {
        // Check if index exists first
        $table = (strpos($indexInfo['name'], 'users_') !== false && strpos($indexInfo['name'], 'pending_') === false) ? 'users' : 'pending_users';
        $checkIndex = $conn->query("SHOW INDEX FROM $table WHERE Key_name = '{$indexInfo['name']}'");
        
        if ($checkIndex->num_rows == 0) {
            if ($conn->query($indexInfo['sql'])) {
                echo "<p class='success'>✓ Added index {$indexInfo['name']}</p>";
            } else {
                echo "<p class='info'>ℹ Could not add index {$indexInfo['name']}: " . $conn->error . "</p>";
            }
        } else {
            echo "<p class='success'>✓ Index {$indexInfo['name']} already exists</p>";
        }
    }
    
    // Clean up old pending registrations (older than 7 days)
    echo "<h2>4. Cleaning Up Old Pending Registrations</h2>";
    
    $cleanup = "DELETE FROM pending_users WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $result = $conn->query($cleanup);
    
    if ($result) {
        echo "<p class='success'>✓ Cleaned up " . $conn->affected_rows . " old pending registrations</p>";
    } else {
        echo "<p class='error'>✗ Error cleaning up: " . $conn->error . "</p>";
    }
    
    // Verify table structures
    echo "<h2>5. Verifying Table Structures</h2>";
    
    $tables = ['users', 'pending_users'];
    foreach ($tables as $table) {
        echo "<h3>$table table structure:</h3>";
        $result = $conn->query("DESCRIBE $table");
        if ($result) {
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table><br>";
        }
    }
    
    echo "<h2>✅ Schema Fix Complete!</h2>";
    echo "<p class='success'>All database tables are now properly configured for the registration system.</p>";
    echo "<p><a href='register.php'>Test Registration</a> | <a href='login.php'>Go to Login</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>Fatal Error: " . $e->getMessage() . "</p>";
    error_log("Schema fix error: " . $e->getMessage());
}

echo "</body></html>";
?>
