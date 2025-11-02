<?php
/**
 * Simple MySQL Connection Test for XAMPP
 * Tests various common XAMPP configurations
 */

echo "<h2>MySQL Connection Test</h2>\n";
echo "<pre>\n";

$configurations = [
    ['host' => 'localhost', 'user' => 'root', 'password' => '', 'name' => 'Default XAMPP (no password)'],
    ['host' => 'localhost', 'user' => 'root', 'password' => 'root', 'name' => 'XAMPP with root password'],
    ['host' => 'localhost', 'user' => 'root', 'password' => '', 'name' => 'XAMPP (no password, no DB)'],
    ['host' => '127.0.0.1', 'user' => 'root', 'password' => '', 'name' => '127.0.0.1 (no password)'],
];

foreach ($configurations as $config) {
    echo "Testing: {$config['name']}\n";
    echo "  Host: {$config['host']}\n";
    echo "  User: {$config['user']}\n";
    echo "  Password: " . (empty($config['password']) ? '(empty)' : '(has password)') . "\n";
    
    try {
        $conn = new mysqli($config['host'], $config['user'], $config['password']);
        
        if ($conn->connect_error) {
            echo "  ❌ Failed: " . $conn->connect_error . "\n";
        } else {
            echo "  ✅ Success! Connected to MySQL\n";
            
            // Try to create/use database
            $dbName = 'questionbank';
            if ($conn->query("CREATE DATABASE IF NOT EXISTS `$dbName`")) {
                echo "  ✅ Database '$dbName' created/exists\n";
                $conn->select_db($dbName);
                echo "  ✅ Successfully selected database\n";
            } else {
                echo "  ⚠️  Could not create database: " . $conn->error . "\n";
            }
            
            $conn->close();
            echo "  ✅ This configuration works!\n\n";
            break; // Stop testing once we find a working configuration
        }
    } catch (Exception $e) {
        echo "  ❌ Exception: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "\n=== Additional Information ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "MySQL Extension: " . (extension_loaded('mysqli') ? 'Available' : 'Not available') . "\n";

// Check if XAMPP is running by testing typical ports
$xamppChecks = [
    ['service' => 'Apache', 'host' => 'localhost', 'port' => 80],
    ['service' => 'MySQL', 'host' => 'localhost', 'port' => 3306],
];

foreach ($xamppChecks as $check) {
    $connection = @fsockopen($check['host'], $check['port'], $errno, $errstr, 1);
    if ($connection) {
        echo "{$check['service']}: ✅ Running on port {$check['port']}\n";
        fclose($connection);
    } else {
        echo "{$check['service']}: ❌ Not running on port {$check['port']}\n";
    }
}

echo "</pre>\n";
?>
