<?php
require_once 'db_connect.php';

try {
    echo "<h2>Adding subscription_id Column</h2>";
    
    // Check if column exists
    $checkResult = $conn->query("SHOW COLUMNS FROM payments LIKE 'subscription_id'");
    
    if ($checkResult->num_rows == 0) {
        echo "<p>Adding subscription_id column...</p>";
        
        $sql = "ALTER TABLE payments ADD COLUMN subscription_id INT DEFAULT NULL";
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ subscription_id column added successfully!</p>";
        } else {
            echo "<p style='color: red;'>❌ Error adding column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ subscription_id column already exists!</p>";
    }
    
    // Add index
    $indexSql = "CREATE INDEX idx_payments_subscription_id ON payments(subscription_id)";
    if ($conn->query($indexSql)) {
        echo "<p style='color: green;'>✅ Index added successfully!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Index may already exist: " . $conn->error . "</p>";
    }
    
    // Show updated table structure
    echo "<h3>Updated payments table structure:</h3>";
    $result = $conn->query("DESCRIBE payments");
    if ($result) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $highlight = ($row['Field'] === 'subscription_id') ? 'background: #d4edda;' : '';
            echo "<tr style='$highlight'>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
