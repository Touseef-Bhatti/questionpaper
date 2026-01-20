<?php
require_once __DIR__ . '/db_connect.php';

// Check if columns exist
$check = $conn->query("SHOW COLUMNS FROM AIGeneratedMCQs LIKE 'verification_status'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE AIGeneratedMCQs 
            ADD COLUMN verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
            ADD COLUMN last_checked_at DATETIME NULL";
    
    if ($conn->query($sql)) {
        echo "Schema updated successfully.";
    } else {
        echo "Error updating schema: " . $conn->error;
    }
} else {
    echo "Columns already exist.";
}
?>