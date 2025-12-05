<?php
/**
 * Simple Email Verification - Works with any table structure
 */
require_once __DIR__ . '/../db_connect.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Email Verification - Ahmad Learning Hub</title>
    <style>
        body { font-family: Arial; margin: 0; padding: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { background: white; padding: 40px; border-radius: 15px; max-width: 500px; margin: 0 auto; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .success { color: #27ae60; font-size: 18px; margin: 20px 0; }
        .error { color: #e74c3c; font-size: 18px; margin: 20px 0; }
        .warning { color: #f39c12; font-size: 18px; margin: 20px 0; }
        h1 { color: #4f6ef7; margin-bottom: 30px; }
        .btn { display: inline-block; padding: 12px 24px; background: #4f6ef7; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; font-weight: bold; }
        .btn:hover { background: #3e5cd8; }
        .icon { font-size: 48px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Ahmad Learning Hub Email Verification</h1>";

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    if (empty($token)) {
        echo "<div class='error'>
                <div class='icon'>❌</div>
                Invalid verification link.
              </div>";
    } else {
        try {
            // Step 1: Find the pending user
            $stmt = $conn->prepare("SELECT id, name, email, password FROM pending_users WHERE token = ? LIMIT 1");
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                
                // Step 2: Ensure users table has correct structure
                $conn->query("CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(191) NOT NULL,
                    email VARCHAR(191) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Check current users table structure
                $columns = $conn->query("SHOW COLUMNS FROM users");
                $hasToken = false;
                $hasVerified = false;
                $hasAutoIncrement = false;
                
                while ($column = $columns->fetch_assoc()) {
                    if ($column['Field'] == 'token') $hasToken = true;
                    if ($column['Field'] == 'verified') $hasVerified = true;
                    if ($column['Field'] == 'id' && strpos($column['Extra'], 'auto_increment') !== false) {
                        $hasAutoIncrement = true;
                    }
                }
                
                // Fix AUTO_INCREMENT if missing
                if (!$hasAutoIncrement) {
                    $conn->query("ALTER TABLE users MODIFY id INT AUTO_INCREMENT PRIMARY KEY");
                }
                
                // Step 3: Check if user already exists
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $checkStmt->bind_param('s', $row['email']);
                $checkStmt->execute();
                $existingUser = $checkStmt->get_result();
                
                if ($existingUser->num_rows > 0) {
                    $checkStmt->close();
                    
                    // User already exists, remove from pending
                    $deleteStmt = $conn->prepare("DELETE FROM pending_users WHERE id = ?");
                    $deleteStmt->bind_param('i', $row['id']);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                    
                    echo "<div class='warning'>
                            <div class='icon'>⚠️</div>
                            <strong>Already Verified!</strong><br>
                            This email is already registered and verified.<br>
<a href='../auth/login.php' class='btn'>Go to Login</a>
                          </div>";
                } else {
                    $checkStmt->close();
                    
                    // Step 4: Insert user with basic fields only
                    if ($hasToken && $hasVerified) {
                        // Full table with token and verified fields
                        $insertStmt = $conn->prepare("INSERT INTO users (name, email, password, token, verified) VALUES (?, ?, ?, ?, 1)");
                        $insertStmt->bind_param('ssss', $row['name'], $row['email'], $row['password'], $token);
                    } else {
                        // Basic table - just name, email, password
                        $insertStmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                        $insertStmt->bind_param('sss', $row['name'], $row['email'], $row['password']);
                    }
                    
                    if ($insertStmt->execute()) {
                        $insertStmt->close();
                        
                        // Remove from pending users
                        $deleteStmt = $conn->prepare("DELETE FROM pending_users WHERE id = ?");
                        $deleteStmt->bind_param('i', $row['id']);
                        $deleteStmt->execute();
                        $deleteStmt->close();
                        
                        echo "<div class='success'>
                                <div class='icon'>✅</div>
                                <strong>Email Verified Successfully!</strong><br>
                                Welcome to Ahmad Learning Hub, " . htmlspecialchars($row['name']) . "!<br>
                                Your account is now active.<br>
<a href='../auth/login.php' class='btn'>Login Now</a>
                              </div>";
                    } else {
                        $insertStmt->close();
                        echo "<div class='error'>
                                <div class='icon'>❌</div>
                                <strong>Database Error</strong><br>
                                " . htmlspecialchars($conn->error) . "<br>
                                Please try again or contact support.
                              </div>";
                    }
                }
            } else {
                $stmt->close();
                
                // Check if already in users table
                $checkStmt = $conn->prepare("SELECT id, name FROM users WHERE " . ($hasToken ? "token = ?" : "email LIKE ?") . " LIMIT 1");
                $checkParam = $hasToken ? $token : "%";
                $checkStmt->bind_param('s', $checkParam);
                $checkStmt->execute();
                $userResult = $checkStmt->get_result();
                
                if ($userResult->num_rows > 0) {
                    $user = $userResult->fetch_assoc();
                    $checkStmt->close();
                    echo "<div class='warning'>
                            <div class='icon'>⚠️</div>
                            <strong>Already Verified!</strong><br>
                            This account has already been verified.<br>
<a href='../auth/login.php' class='btn'>Go to Login</a>
                          </div>";
                } else {
                    $checkStmt->close();
                    echo "<div class='error'>
                            <div class='icon'>❌</div>
                            <strong>Invalid Token</strong><br>
                            This verification link is invalid or has expired.<br>
<a href='../auth/register.php' class='btn'>Register Again</a>
                          </div>";
                }
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>
                    <div class='icon'>❌</div>
                    <strong>System Error</strong><br>
                    " . htmlspecialchars($e->getMessage()) . "
                  </div>";
        }
    }
} else {
    echo "<div class='error'>
            <div class='icon'>❌</div>
            <strong>No Token Provided</strong><br>
            Please use the verification link from your email.
          </div>";
}

echo "        <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px;'>
<a href='../auth/register.php'>Register New Account</a> | 
            <a href='../auth/login.php'>Login</a> | 
            <a href='resend_verification.php'>Resend Verification</a>
          </div>
    </div>
</body>
</html>";
?>
