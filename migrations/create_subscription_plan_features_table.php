<?php
/**
 * Migration: Create subscription_plan_features table
 * 
 * This migration creates the subscription_plan_features table which stores
 * feature descriptions for each subscription plan.
 * 
 * Usage: 
 *   - Visit: https://yoursite.com/migrations/create_subscription_plan_features_table.php
 *   - Or run via command line: php migrations/create_subscription_plan_features_table.php
 *   - Delete this file after running
 */

require_once __DIR__ . '/../db_connect.php';

// Check if table already exists
$tableExists = $conn->query("SHOW TABLES LIKE 'subscription_plan_features'");

if ($tableExists && $tableExists->num_rows > 0) {
    die('✓ Table subscription_plan_features already exists. Nothing to do.');
}

// Create the table
$sql = "CREATE TABLE subscription_plan_features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    feature_text VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE,
    KEY (plan_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    echo '<h2>✓ Success</h2>';
    echo '<p>Table subscription_plan_features has been created successfully.</p>';
    
    // Insert sample features for free plan
    $freeResult = $conn->query("SELECT id FROM subscription_plans WHERE name = 'free' LIMIT 1");
    if ($freeResult && $freeResult->num_rows > 0) {
        $freeRow = $freeResult->fetch_assoc();
        $freeId = $freeRow['id'];
        
        $features = [
            'Limited question papers per day',
            'Basic MCQ tests',
            'Standard templates',
            'Display of ads',
            'Community support'
        ];
        
        foreach ($features as $idx => $feature) {
            $order = $idx + 1;
            $feature = $conn->real_escape_string($feature);
            $conn->query("INSERT INTO subscription_plan_features (plan_id, feature_text, sort_order) 
                         VALUES ($freeId, '$feature', $order)");
        }
        
        echo '<p>Inserted sample features for free plan.</p>';
    }
    
    echo '<p><a href="../index.php">Return to home</a></p>';
} else {
    echo '<h2>✗ Error</h2>';
    echo '<p>Failed to create table: ' . $conn->error . '</p>';
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Migration Result</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        h2 { color: #0066cc; }
    </style>
</head>
<body>
</body>
</html>
