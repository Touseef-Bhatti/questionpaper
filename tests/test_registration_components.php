<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Testing Registration Components</h1>";

echo "<h2>1. Testing Database Connection:</h2>";
try {
    require_once __DIR__ . '/db_connect.php';
    if (isset($conn) && $conn->ping()) {
        echo "<p style='color: green;'>&#10004; Database connected successfully!</p>";
        // Test a simple query
        $result = $conn->query("SELECT 1");
        if ($result) {
            echo "<p style='color: green;'>&#10004; Simple database query successful.</p>";
            $result->close();
        } else {
            echo "<p style='color: red;'>&#10008; Simple database query failed: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: red;'>&#10008; Database connection failed or \$conn not set.</p>";
        if (isset($conn)) {
            echo "<p style='color: red;'>Connection error: " . $conn->connect_error . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>&#10008; Exception during database connection: " . $e->getMessage() . "</p>";
}

echo "<h2>2. Testing Email Sending (PHPMailer):</h2>";
// Temporarily include env.php if not already loaded by db_connect.php
if (!class_exists('EnvLoader')) {
    require_once __DIR__ . '/config/env.php';
}

// Dummy data for email test
$testEmail = 'test@example.com'; // Replace with a real email if you want to actually send
$testToken = 'dummy_token_12345';

try {
    require_once __DIR__ . '/phpmailer_mailer.php';
    echo "<p>Attempting to send a test verification email to: " . htmlspecialchars($testEmail) . "</p>";
    
    // Note: sendVerificationEmail function might log errors internally.
    // For a real test, replace test@example.com with an actual email you can check.
    if (function_exists('sendVerificationEmail')) {
        if (sendVerificationEmail($testEmail, $testToken)) {
            echo "<p style='color: green;'>&#10004; Test email function called successfully. Check your email for " . htmlspecialchars($testEmail) . " (if it's a real address and SMTP is configured).</p>";
        } else {
            echo "<p style='color: red;'>&#10008; Test email function failed to send. Check PHP error logs for Mailer Error details.</p>";
        }
    } else {
        echo "<p style='color: red;'>&#10008; 'sendVerificationEmail' function not found. Check phpmailer_mailer.php inclusion.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>&#10008; Exception during email sending test: " . $e->getMessage() . "</p>";
}

echo "<h2>3. Testing Pending Users Table Creation (from register.php logic):</h2>";
try {
    // This part mimics the table creation from register.php
    if (isset($conn) && $conn->ping()) {
        $createTableSql = "CREATE TABLE IF NOT EXISTS pending_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            email VARCHAR(191) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            token VARCHAR(64),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if ($conn->query($createTableSql)) {
            echo "<p style='color: green;'>&#10004; 'pending_users' table creation/check successful.</p>";
        } else {
            echo "<p style='color: red;'>&#10008; 'pending_users' table creation/check failed: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>&#9888; Cannot test 'pending_users' table creation, database connection not established.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>&#10008; Exception during 'pending_users' table test: " . $e->getMessage() . "</p>";
}

echo "<h2>4. Current APP_ENV Setting:</h2>";
if (class_exists('EnvLoader')) {
    echo "<p>APP_ENV is set to: " . htmlspecialchars(EnvLoader::get('APP_ENV', 'undefined')) . "</p>";
    if (EnvLoader::isDevelopment()) {
        echo "<p style='color: orange;'>&#9888; Currently in DEVELOPMENT mode. Detailed errors should be visible.</p>";
    } else {
        echo "<p style='color: green;'>&#10004; Currently in PRODUCTION mode. Generic errors are expected.</p>";
    }
} else {
    echo "<p style='color: red;'>&#10008; EnvLoader class not found. Environment variables might not be loaded.</p>";
}

?>