<?php
/**
 * ============================================================================
 * Database Cleanup - Remove Legacy Tables
 * ============================================================================
 * 
 * This script cleans up old/legacy tables that have been replaced by the
 * new AI Key Management System
 */

require_once __DIR__ . '/db_connect.php';

echo "<h2>Database Cleanup - Remove Legacy Tables</h2>\n";
echo "<hr>\n";

// Tables to potentially remove
$legacyTables = [
    'api_keys' => 'Old API keys table (replaced by ai_api_keys)',
    'AIGeneratedQuestion' => 'Legacy AI question table',
    'AIMCQsVerification' => 'Legacy MCQs verification table',
    'AIQuestionsTopic' => 'Legacy questions/topics table'
];

$removed = [];
$kept = [];

foreach ($legacyTables as $tableName => $description) {
    $tableExists = $conn->query("SHOW TABLES LIKE '$tableName'")->num_rows > 0;
    
    if (!$tableExists) {
        echo "<p><span style='color:gray;'>⊖ Table not found: <code>$tableName</code></span></p>\n";
        continue;
    }
    
    // Check if table is in use (has dependencies)
    $isInUse = false;
    $rowCount = 0;
    
    $countResult = $conn->query("SELECT COUNT(*) as count FROM `$tableName`");
    if ($countResult) {
        $countRow = $countResult->fetch_assoc();
        $rowCount = intval($countRow['count']);
    }
    
    echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 4px;'>\n";
    echo "<p><strong>Table:</strong> <code>$tableName</code></p>\n";
    echo "<p><strong>Purpose:</strong> $description</p>\n";
    echo "<p><strong>Rows:</strong> $rowCount</p>\n";
    
    // For api_keys table, check if data was migrated
    if ($tableName === 'api_keys' && $rowCount > 0) {
        echo "<p><span style='color:orange;'>⚠ This table has $rowCount rows. ";
        
        $migratedCount = $conn->query("SELECT COUNT(DISTINCT api_key_hash) as count FROM ai_api_keys")->fetch_assoc()['count'];
        
        if ($migratedCount >= $rowCount) {
            echo "All data appears to be migrated to <code>ai_api_keys</code>.</span></p>\n";
            
            echo "<form method='POST' style='display:inline;'>\n";
            echo "<input type='hidden' name='action' value='delete'>\n";
            echo "<input type='hidden' name='table' value='$tableName'>\n";
            echo "<button type='submit' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete table $tableName? This cannot be undone.\");'>";
            echo "🗑️ Delete Table</button>\n";
            echo "</form>\n";
        } else {
            echo "WARNING: Only $migratedCount out of $rowCount rows migrated. ";
            echo "<strong style='color:red;'>Do NOT delete this table yet.</strong></span></p>\n";
        }
    } else if ($tableName !== 'api_keys' && $rowCount > 0) {
        echo "<p><span style='color:orange;'>⚠ This table has data. Deleting is not recommended.</span></p>\n";
    } else {
        echo "<form method='POST' style='display:inline;'>\n";
        echo "<input type='hidden' name='action' value='delete'>\n";
        echo "<input type='hidden' name='table' value='$tableName'>\n";
        echo "<button type='submit' class='btn btn-warning btn-sm' onclick='return confirm(\"Delete table $tableName?\");'>";
        echo "🗑️ Delete Empty Table</button>\n";
        echo "</form>\n";
    }
    
    echo "</div>\n";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $tableToDelete = $_POST['table'] ?? '';
    
    // Validate table name (whitelist)
    $allowedTables = array_keys($legacyTables);
    if (!in_array($tableToDelete, $allowedTables)) {
        echo "<div class='alert alert-danger'>Invalid table name</div>\n";
    } else {
        // Backup first
        $backupTable = $tableToDelete . '_backup_' . date('YmdHis');
        $backupQuery = "RENAME TABLE `$tableToDelete` TO `$backupTable`";
        
        if ($conn->query($backupQuery)) {
            echo "<div class='alert alert-success'>\n";
            echo "<strong>✓ Table backed up as: <code>$backupTable</code></strong>\n";
            echo "<p>The table has been renamed (not deleted). If needed, you can restore it later.</p>\n";
            echo "</div>\n";
        } else {
            echo "<div class='alert alert-danger'>\n";
            echo "<strong>✗ Error backing up table: " . htmlspecialchars($conn->error) . "</strong>\n";
            echo "</div>\n";
        }
    }
}

echo "<hr>\n";
echo "<h3>Current Database Structure</h3>\n";

$showTables = $conn->query("SHOW TABLES");
echo "<ul>\n";
while ($row = $showTables->fetch_row()) {
    echo "<li><code>" . htmlspecialchars($row[0]) . "</code></li>\n";
}
echo "</ul>\n";

echo "<style>\n";
echo ".btn { padding: 4px 8px; text-decoration: none; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; }\n";
echo ".btn-danger { background-color: #dc3545; color: white; }\n";
echo ".btn-warning { background-color: #ffc107; color: black; }\n";
echo ".btn-sm { font-size: 0.875rem; }\n";
echo "</style>\n";

$conn->close();
?>
