<?php
/**
 * Local Database Setup Script for XAMPP
 * This script helps create the local database with proper settings
 */

echo "<h2>Local Database Setup</h2>\n";
echo "<pre>\n";

// Try to connect to MySQL without database first
$host = 'localhost';
$user = 'root';
$password = '';

echo "Attempting to connect to MySQL...\n";

try {
    $conn = new mysqli($host, $user, $password);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error . "\n");
    }
    
    echo "✅ Connected to MySQL successfully!\n\n";
    
    // Create database if it doesn't exist
    $dbName = 'questionbank';
    $sql = "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        echo "✅ Database '$dbName' created/verified successfully\n";
    } else {
        echo "❌ Error creating database: " . $conn->error . "\n";
    }
    
    // Select the database
    $conn->select_db($dbName);
    
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "✅ Users table already exists\n";
    } else {
        echo "ℹ️  Users table doesn't exist - will be created when needed\n";
    }
    
    echo "\n=== Database Setup Complete ===\n";
    echo "Your .env.local should use these settings:\n";
    echo "DB_HOST=localhost\n";
    echo "DB_USER=root\n";
    echo "DB_PASSWORD=\n";
    echo "DB_NAME=questionbank\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Make sure XAMPP MySQL is running\n";
    echo "2. Check if there's a password set for root user\n";
    echo "3. Try accessing phpMyAdmin at http://localhost/phpmyadmin/\n";
}

echo "</pre>\n";
?>
