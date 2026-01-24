<?php
// Define __DIR__ if not defined (older PHP) - not needed here but good practice
require_once __DIR__ . '/../config/env.php';

// Force load .env.local if it exists
if (file_exists(__DIR__ . '/../config/.env.local')) {
    EnvLoader::load(__DIR__ . '/../config/.env.local');
    echo "Loaded .env.local\n";
} else {
    EnvLoader::load(__DIR__ . '/../config/.env');
    echo "Loaded .env\n";
}

// Now require db_connect
// We need to suppress the output of db_connect if it succeeds, or handle it.
// db_connect.php does require config/env.php again, but EnvLoader has a static $loaded check.
// However, we want to make sure the variables are set BEFORE db_connect is included.

// Modify db_connect to NOT require env.php if EnvLoader is already loaded? 
// No, EnvLoader::load checks self::$loaded.

// Let's manually set the DB vars to be sure if EnvLoader fails in CLI
// Check if EnvLoader worked
$host = EnvLoader::get('DB_HOST', EnvLoader::get('MYSQL_HOST', 'localhost'));
echo "DB Host: " . $host . "\n";

require_once __DIR__ . '/../db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL DEFAULT 'openai',
    key_value VARCHAR(255) NOT NULL,
    account_name VARCHAR(100) DEFAULT 'Default',
    status ENUM('active', 'inactive', 'rate_limited', 'quota_exceeded') DEFAULT 'active',
    usage_count INT DEFAULT 0,
    last_used DATETIME DEFAULT NULL,
    error_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_key (key_value)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'api_keys' created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Add index on status for faster retrieval
$indexSql = "CREATE INDEX idx_status ON api_keys(status)";
try {
    $conn->query($indexSql);
} catch (Exception $e) {
    // Index might already exist, ignore
}

echo "Database setup complete.";
?>
