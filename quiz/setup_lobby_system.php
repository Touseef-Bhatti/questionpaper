<?php
// setup_lobby_system.php - Automated via install.php
// This file is now obsolete as all database schema setup has been moved to install.php

require_once __DIR__ . '/../db_connect.php';

echo "<h1>Lobby System Setup Status</h1>";
echo "<p>The quiz lobby system is configured through the main install.php script.</p>";

// Quick verification that the schema is properly set up
try {
    $checks = [
        ['table' => 'quiz_rooms', 'columns' => ['quiz_started', 'lobby_enabled', 'start_time', 'quiz_duration_minutes']],
        ['table' => 'quiz_participants', 'columns' => ['status', 'current_question', 'time_remaining_sec', 'last_activity']],
        ['table' => 'live_quiz_events', 'columns' => []]
    ];
    
    echo "<h2>Schema Verification</h2>";
    $allOk = true;
    
    foreach ($checks as $check) {
        $table = $check['table'];
        $columns = $check['columns'];
        
        // Check if table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            echo "<p style='color:red;'>❌ Table '$table' does not exist</p>";
            $allOk = false;
            continue;
        }
        
        echo "<p style='color:green;'>✓ Table '$table' exists</p>";
        
        // Check columns if specified
        if (!empty($columns)) {
            foreach ($columns as $col) {
                $colCheck = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
                if ($colCheck && $colCheck->num_rows > 0) {
                    echo "<span style='margin-left:20px; color:green;'>✓ Column '$col' found</span><br>";
                } else {
                    echo "<span style='margin-left:20px; color:orange;'>⚠ Column '$col' not found</span><br>";
                    $allOk = false;
                }
            }
        }
    }
    
    if ($allOk) {
        echo "<h2 style='color: green;'>✅ Lobby System is Ready!</h2>";
        echo "<p>All required tables and columns are properly configured.</p>";
    } else {
        echo "<h2 style='color: orange;'>⚠ Some Schema Elements Missing</h2>";
        echo "<p>Please run the install.php script to ensure all tables and columns are created properly.</p>";
        echo "<a href='../install.php' class='btn' style='display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;'>Run Installation Script</a>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Error</h2>";
    echo "<p>Failed to verify schema: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Close connection
$conn->close();
?>

<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    max-width: 900px; 
    margin: 40px auto; 
    padding: 20px; 
    background: #f8fafc;
}

h1, h2 { 
    color: #1e293b; 
    border-bottom: 2px solid #e2e8f0; 
    padding-bottom: 10px;
}

h1 {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 30px;
    border: none;
}

.btn { 
    display: inline-block; 
    padding: 10px 20px; 
    background: #3498db; 
    color: white; 
    text-decoration: none; 
    border-radius: 5px; 
    margin-top: 20px; 
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin: 20px 0;
}

.alert-info {
    background: #e7f3ff;
    border-left: 4px solid #2196F3;
    color: #0c5aa0;
}

code {
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Consolas', 'Monaco', monospace;
}

p {
    line-height: 1.6;
}
</style>
