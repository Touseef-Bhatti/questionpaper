<?php
/**
 * Debug Verification Issues
 * This helps identify why verification is failing
 */

require_once 'db_connect.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Verification - QPaperGen</title>
    <style>
        body { font-family: Arial; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; padding: 10px; background: #d5f4e6; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; padding: 10px; background: #fdf2f2; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; padding: 10px; background: #ebf3fd; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4f6ef7; color: white; }
        .token { word-break: break-all; font-family: monospace; background: #f8f9fa; padding: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Verification Debug Tool</h1>";

// Check if token is provided
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    echo "<h2>Debugging Token: <span class='token'>" . htmlspecialchars($token) . "</span></h2>";
    
    // Check token length
    echo "<div class='info'>Token Length: " . strlen($token) . " characters</div>";
    
    try {
        // 1. Check pending_users table
        echo "<h3>1. Checking pending_users table...</h3>";
        $stmt = $conn->prepare("SELECT id, name, email, token, created_at FROM pending_users WHERE token = ?");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "<div class='success'>✓ Token found in pending_users!</div>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Token (First 20 chars)</th><th>Created</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td class='token'>" . htmlspecialchars(substr($row['token'], 0, 20)) . "...</td>";
                echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='error'>✗ Token NOT found in pending_users</div>";
        }
        $stmt->close();
        
        // 2. Check users table
        echo "<h3>2. Checking users table...</h3>";
        $stmt2 = $conn->prepare("SELECT id, name, email, verified, created_at FROM users WHERE token = ?");
        $stmt2->bind_param('s', $token);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        if ($result2->num_rows > 0) {
            echo "<div class='info'>ℹ Token found in users table (already verified?)</div>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Verified</th><th>Created</th></tr>";
            while ($row = $result2->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . ($row['verified'] ? 'Yes' : 'No') . "</td>";
                echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='info'>ℹ Token not found in users table</div>";
        }
        $stmt2->close();
        
        // 3. Show all pending users (for debugging)
        echo "<h3>3. All pending users (last 10):</h3>";
        $allPending = $conn->query("SELECT id, name, email, LEFT(token, 20) as token_start, created_at FROM pending_users ORDER BY created_at DESC LIMIT 10");
        
        if ($allPending->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Token Start</th><th>Created</th></tr>";
            while ($row = $allPending->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td class='token'>" . htmlspecialchars($row['token_start']) . "...</td>";
                echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='info'>No pending users found</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>Database Error: " . $e->getMessage() . "</div>";
    }
    
} else {
    // No token provided - show instructions
    echo "<h2>🚀 How to Use This Debug Tool</h2>";
    echo "<div class='info'>";
    echo "<p><strong>Step 1:</strong> Register a new user</p>";
    echo "<p><strong>Step 2:</strong> Copy the verification link from your email</p>";
    echo "<p><strong>Step 3:</strong> Replace 'verify_email.php' with 'debug_verification.php' in the URL</p>";
    echo "<p><strong>Example:</strong></p>";
    echo "<code>https://your-domain.com/debug_verification.php?token=abc123...</code>";
    echo "</div>";
    
    echo "<h3>Quick Tests:</h3>";
    echo "<p><a href='register.php'>🔗 Test Registration</a></p>";
    echo "<p><a href='test_email_preview.php'>📧 Preview Email Format</a></p>";
}

echo "    </div>
</body>
</html>";
?>
