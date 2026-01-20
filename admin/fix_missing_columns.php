<?php
// Fix for missing original_correct_option column
// This script checks and adds the column to both verification tables if missing.

require_once __DIR__ . '/../db_connect.php'; // Adjust path if needed, assuming admin/fix_missing_columns.php

$tables = ['AIMCQsVerification', 'MCQsVerification'];

foreach ($tables as $table) {
    echo "Checking table: $table...<br>";
    
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
    if ($checkTable->num_rows === 0) {
        echo "Table $table does not exist. Skipping.<br>";
        continue;
    }
    
    // Check if column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM $table LIKE 'original_correct_option'");
    if ($colCheck && $colCheck->num_rows === 0) {
        echo "Adding 'original_correct_option' to $table... ";
        $sql = "ALTER TABLE $table ADD COLUMN original_correct_option TEXT AFTER suggested_correct_option";
        if ($conn->query($sql)) {
            echo "<span style='color:green'>Success</span><br>";
        } else {
            echo "<span style='color:red'>Error: " . $conn->error . "</span><br>";
        }
    } else {
        echo "Column 'original_correct_option' already exists.<br>";
    }
}

echo "Done.";
?>
