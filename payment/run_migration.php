<?php
// Simple migration runner
require_once 'db_connect.php';

function runMigration($filename) {
    global $conn;
    
    $migrationPath = __DIR__ . '/migrations/' . $filename;
    if (!file_exists($migrationPath)) {
        throw new Exception("Migration file not found: $filename");
    }
    
    $sql = file_get_contents($migrationPath);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)), 
        function($stmt) { return !empty($stmt) && !preg_match('/^\s*--/', $stmt); }
    );
    
    $executed = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        if (trim($statement)) {
            try {
                if ($conn->query($statement)) {
                    $executed++;
                } else {
                    $errors[] = "Error executing: " . trim($statement) . " - " . $conn->error;
                }
            } catch (Exception $e) {
                $errors[] = "Exception executing: " . trim($statement) . " - " . $e->getMessage();
            }
        }
    }
    
    return [
        'executed' => $executed,
        'errors' => $errors
    ];
}

try {
    echo "<h2>Database Migration Runner</h2>";
    echo "<h3>Running migration: add_subscription_id_to_payments.sql</h3>";
    
    $result = runMigration('add_subscription_id_to_payments.sql');
    
    echo "<p><strong>Executed statements:</strong> " . $result['executed'] . "</p>";
    
    if (empty($result['errors'])) {
        echo "<p style='color: green;'><strong>✅ Migration completed successfully!</strong></p>";
    } else {
        echo "<p style='color: orange;'><strong>⚠️ Migration completed with some warnings:</strong></p>";
        echo "<ul>";
        foreach ($result['errors'] as $error) {
            echo "<li style='color: red;'>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
        echo "<p><em>Note: Some errors may be expected if columns already exist.</em></p>";
    }
    
    // Test the payments table structure
    echo "<h3>Verifying payments table structure:</h3>";
    $result = $conn->query("DESCRIBE payments");
    if ($result) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
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
    echo "<p style='color: red;'><strong>❌ Migration failed:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
