<?php
require_once __DIR__ . '/../config/env.php';

// Force load environment file for CLI
$envPath = __DIR__ . '/../config/.env.local';

// Check if in production environment
if ((defined('ENVIRONMENT') && ENVIRONMENT === 'production') || getenv('APP_ENV') === 'production') {
    $envPath = __DIR__ . '/../config/.env.production';
}

// Use .env.production if it exists and .env.local doesn't
if (!file_exists($envPath)) {
    $alternativePath = (strpos($envPath, '.env.production') !== false) ? 
        __DIR__ . '/../config/.env.local' : 
        __DIR__ . '/../config/.env.production';
    if (file_exists($alternativePath)) {
        $envPath = $alternativePath;
    }
}

if (file_exists($envPath)) {
    EnvLoader::reset(); // Reset to allow reloading
    EnvLoader::load($envPath);
}

echo "DB_HOST: " . EnvLoader::get('DB_HOST') . "\n";
echo "DB_USER: " . EnvLoader::get('DB_USER') . "\n";
echo "DB_USERNAME: " . EnvLoader::get('DB_USERNAME') . "\n";
echo "DB_NAME: " . EnvLoader::get('DB_NAME') . "\n";
$pass = EnvLoader::get('DB_PASSWORD');
echo "DB_PASSWORD length: " . strlen($pass) . "\n";

// Get database connection
require_once __DIR__ . '/../db_connect.php';

// Ensure APP_KEY is available
$appKey = EnvLoader::get('APP_KEY');
if (empty($appKey)) {
    die("Error: APP_KEY is missing in environment variables. Please add it to .env.local first.\n");
}

echo "Starting Encryption Migration...\n";

// 1. Add key_hash column if not exists
$checkCol = $conn->query("SHOW COLUMNS FROM api_keys LIKE 'key_hash'");
if ($checkCol->num_rows === 0) {
    echo "Adding key_hash column...\n";
    $conn->query("ALTER TABLE api_keys ADD COLUMN key_hash VARCHAR(64) AFTER provider");
}

// 2. Drop old unique index on key_value if exists
// We need to check if it exists first to avoid error, or just try and catch
try {
    $conn->query("ALTER TABLE api_keys DROP INDEX unique_key");
    echo "Dropped old unique index on key_value.\n";
} catch (Exception $e) {
    echo "Notice: Could not drop index (might not exist or already dropped): " . $e->getMessage() . "\n";
}

// 3. Migrate Data
echo "Migrating existing keys...\n";
$result = $conn->query("SELECT id, key_value, key_hash FROM api_keys");
$updated = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // If key_hash is already set, assume this row is already migrated
        if (!empty($row['key_hash'])) {
            continue;
        }

        $plainKey = $row['key_value'];
        
        // Calculate Hash
        $hash = hash('sha256', $plainKey);
        
        // Encrypt Key
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($plainKey, 'aes-256-cbc', $appKey, 0, $iv);
        $encryptedPayload = base64_encode($iv . $encrypted);
        
        // Update DB
        $stmt = $conn->prepare("UPDATE api_keys SET key_value = ?, key_hash = ? WHERE id = ?");
        $stmt->bind_param('ssi', $encryptedPayload, $hash, $row['id']);
        if ($stmt->execute()) {
            $updated++;
        } else {
            echo "Failed to update ID {$row['id']}: " . $stmt->error . "\n";
        }
    }
}

echo "Migrated $updated keys.\n";

// 4. Add new unique index on key_hash
try {
    $conn->query("ALTER TABLE api_keys ADD UNIQUE KEY unique_key_hash (key_hash)");
    echo "Added new unique index on key_hash.\n";
} catch (Exception $e) {
    echo "Notice: Could not add unique index: " . $e->getMessage() . "\n";
}

echo "Migration completed successfully.\n";
?>