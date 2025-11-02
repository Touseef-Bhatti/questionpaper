<?php
// Fix the SQL syntax error in subscription_schema.sql
$file_path = __DIR__ . '/database/subscription_schema.sql';
$content = file_get_contents($file_path);

// Replace the problematic dynamic SQL with a simpler approach
$search = "-- Add subscription_expires_at column if it doesn't exist (using proper MySQL syntax)
SET @stmt = (SELECT IF(
    EXISTS(
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'users' 
        AND COLUMN_NAME = 'subscription_expires_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN subscription_expires_at TIMESTAMP DEFAULT NULL'
));
PREPARE dynamic_stmt FROM @stmt;
EXECUTE dynamic_stmt;
DEALLOCATE PREPARE dynamic_stmt;";

$replace = "-- Add subscription_expires_at column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS subscription_expires_at TIMESTAMP DEFAULT NULL;";

$new_content = str_replace($search, $replace, $content);

// Save the fixed file
file_put_contents($file_path, $new_content);

echo "Fixed subscription_schema.sql successfully!";
?>