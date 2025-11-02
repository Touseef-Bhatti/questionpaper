<?php
// Load environment configuration
require_once __DIR__ . '/../config/env.php';

// Reset the environment loader to ensure fresh load
EnvLoader::reset();

echo "=== ENVIRONMENT DEBUG ===\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'Not set') . "\n";

// Check if we're loading the right env file
$serverName = $_SERVER['SERVER_NAME'] ?? 'production';
$isLocal = in_array($serverName, ['localhost', '127.0.0.1', '::1']);
$envFile = $isLocal ? __DIR__ . '/../config/.env.local' : __DIR__ . '/../config/.env.production';

echo "Is Local: " . ($isLocal ? 'YES' : 'NO') . "\n";
echo "Env file: " . $envFile . "\n";
echo "File exists: " . (file_exists($envFile) ? 'YES' : 'NO') . "\n";

echo "\n=== DATABASE VARIABLES ===\n";
echo "DB_HOST: '" . EnvLoader::get('DB_HOST', 'NOT_SET') . "'\n";
echo "DB_USER: '" . EnvLoader::get('DB_USER', 'NOT_SET') . "'\n";
echo "DB_PASSWORD: '" . EnvLoader::get('DB_PASSWORD', 'NOT_SET') . "'\n";
echo "DB_NAME: '" . EnvLoader::get('DB_NAME', 'NOT_SET') . "'\n";

echo "\n=== RAW ENV CHECK ===\n";
echo "RAW \$_ENV['DB_PASSWORD']: ";
var_dump($_ENV['DB_PASSWORD'] ?? 'NOT_SET');
echo "RAW getenv('DB_PASSWORD'): ";
var_dump(getenv('DB_PASSWORD'));

echo "\n=== TEST DATABASE CONNECTION ===\n";
$host = EnvLoader::get('DB_HOST', 'localhost');
$user = EnvLoader::get('DB_USER', 'root');
$password = EnvLoader::get('DB_PASSWORD', '');
$database = EnvLoader::get('DB_NAME', 'test');

echo "Connection params:\n";
echo "  Host: '$host'\n";
echo "  User: '$user'\n";
echo "  Password: '" . (empty($password) ? 'EMPTY' : 'HAS_VALUE') . "' (length: " . strlen($password) . ")\n";
echo "  Database: '$database'\n";

try {
    $conn = new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        echo "Connection failed: " . $conn->connect_error . "\n";
    } else {
        echo "Connection successful!\n";
        $conn->close();
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
