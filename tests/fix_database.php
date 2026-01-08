<?php
// Fix database table issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Fix</h2>";

try {
    require_once __DIR__ . '/db_connect.php';
    echo "✅ Database connected<br>";
    
    // Fix the pending_users table
    echo "<h3>Fixing pending_users table...</h3>";
    
    // Drop and recreate the table with proper structure
    $conn->query("DROP TABLE IF EXISTS pending_users");
    echo "✅ Dropped existing table<br>";
    
    $sql = "CREATE TABLE pending_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        email VARCHAR(191) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        token VARCHAR(64),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ pending_users table created successfully<br>";
    } else {
        echo "❌ Failed to create table: " . $conn->error . "<br>";
    }
    
    // Test insert
    echo "<h3>Testing insert...</h3>";
    $testEmail = 'test_' . time() . '@example.com';
    $stmt = $conn->prepare("INSERT INTO pending_users (name, email, password, token) VALUES (?, ?, ?, ?)");
    
    if ($stmt) {
        $name = 'Test User';
        $password = password_hash('test123', PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $stmt->bind_param('ssss', $name, $testEmail, $password, $token);
        
        if ($stmt->execute()) {
            echo "✅ Test insert successful<br>";
            // Clean up
            $conn->query("DELETE FROM pending_users WHERE email = '$testEmail'");
            echo "✅ Test record cleaned up<br>";
        } else {
            echo "❌ Test insert failed: " . $stmt->error . "<br>";
        }
        $stmt->close();
    } else {
        echo "❌ Failed to prepare statement: " . $conn->error . "<br>";
    }
    
    echo "<br><strong>✅ Database fix completed!</strong>";
    
} catch (Exception $e) {
    echo "<br><strong>❌ Error: " . $e->getMessage() . "</strong>";
}

echo "<br><a href='debug_register.php'>Run Debug Again</a>";
echo "<br><a href='register.php'>Test Registration</a>";
?>
