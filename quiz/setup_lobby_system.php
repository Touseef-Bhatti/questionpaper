<?php
// setup_lobby_system.php - Quick setup script for lobby functionality
require_once __DIR__ . '/../db_connect.php';

echo "<h1>Setting Up Lobby System</h1>";
echo "<p>Adding required database columns and tables...</p>";

try {
    // Add lobby functionality columns to quiz_rooms table
    echo "<h2>1. Enhancing quiz_rooms table</h2>";
    
    $room_enhancements = [
        "ALTER TABLE quiz_rooms ADD COLUMN quiz_started BOOLEAN DEFAULT FALSE",
        "ALTER TABLE quiz_rooms ADD COLUMN lobby_enabled BOOLEAN DEFAULT TRUE", 
        "ALTER TABLE quiz_rooms ADD COLUMN start_time DATETIME NULL",
        "ALTER TABLE quiz_rooms ADD COLUMN quiz_duration_minutes INT DEFAULT 30"
    ];
    
    foreach ($room_enhancements as $sql) {
        try {
            $conn->query($sql);
            echo "✓ Added column: " . htmlspecialchars(substr($sql, 28, 50)) . "...<br>";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "ℹ Already exists: " . htmlspecialchars(substr($sql, 28, 50)) . "...<br>";
            } else {
                echo "⚠ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
            }
        }
    }
    
    // Add participant status tracking
    echo "<h2>2. Enhancing quiz_participants table</h2>";
    
    $participant_enhancements = [
        "ALTER TABLE quiz_participants ADD COLUMN status ENUM('waiting', 'active', 'completed', 'disconnected') DEFAULT 'waiting'",
        "ALTER TABLE quiz_participants ADD COLUMN current_question INT DEFAULT 0",
        "ALTER TABLE quiz_participants ADD COLUMN time_remaining_sec INT NULL",
        "ALTER TABLE quiz_participants ADD COLUMN last_activity DATETIME DEFAULT CURRENT_TIMESTAMP"
    ];
    
    foreach ($participant_enhancements as $sql) {
        try {
            $conn->query($sql);
            echo "✓ Added column: " . htmlspecialchars(substr($sql, 35, 50)) . "...<br>";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "ℹ Already exists: " . htmlspecialchars(substr($sql, 35, 50)) . "...<br>";
            } else {
                echo "⚠ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
            }
        }
    }
    
    // Create live_quiz_events table for real-time tracking
    echo "<h2>3. Creating live quiz events table</h2>";
    
    $events_table = "
        CREATE TABLE IF NOT EXISTS live_quiz_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            participant_id INT NULL,
            event_type ENUM('participant_joined', 'participant_left', 'quiz_started', 'question_answered', 'quiz_completed') NOT NULL,
            event_data JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE,
            FOREIGN KEY (participant_id) REFERENCES quiz_participants(id) ON DELETE SET NULL,
            INDEX idx_room_created (room_id, created_at),
            INDEX idx_event_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    if ($conn->query($events_table)) {
        echo "✓ Created live_quiz_events table<br>";
    } else {
        echo "⚠ Error creating live_quiz_events table: " . htmlspecialchars($conn->error) . "<br>";
    }
    
    // Test the enhancements
    echo "<h2>4. Testing enhanced functionality</h2>";
    
    // Test quiz_rooms query
    $test_query = "SELECT id, room_code, quiz_started, lobby_enabled, start_time, quiz_duration_minutes FROM quiz_rooms LIMIT 1";
    $test_result = $conn->query($test_query);
    
    if ($test_result) {
        echo "✓ Enhanced quiz_rooms table structure verified<br>";
    } else {
        echo "❌ Error testing quiz_rooms: " . htmlspecialchars($conn->error) . "<br>";
    }
    
    // Test quiz_participants query
    $test_query2 = "SELECT id, name, status, current_question, time_remaining_sec, last_activity FROM quiz_participants LIMIT 1";
    $test_result2 = $conn->query($test_query2);
    
    if ($test_result2) {
        echo "✓ Enhanced quiz_participants table structure verified<br>";
    } else {
        echo "❌ Error testing quiz_participants: " . htmlspecialchars($conn->error) . "<br>";
    }
    
    // Test events table
    $test_query3 = "SELECT COUNT(*) as count FROM live_quiz_events";
    $test_result3 = $conn->query($test_query3);
    
    if ($test_result3) {
        echo "✓ Live quiz events table verified<br>";
    } else {
        echo "❌ Error testing live_quiz_events: " . htmlspecialchars($conn->error) . "<br>";
    }
    
    echo "<h2 style='color: green;'>✅ Lobby System Setup Complete!</h2>";
    echo "<p>The quiz system has been successfully enhanced with:</p>";
    echo "<ul>";
    echo "<li>✓ Lobby functionality (quiz_started, lobby_enabled)</li>";
    echo "<li>✓ Quiz timing control (start_time, duration)</li>";
    echo "<li>✓ Participant status tracking (status, current_question)</li>";
    echo "<li>✓ Real-time activity monitoring (last_activity)</li>";
    echo "<li>✓ Live events logging system</li>";
    echo "</ul>";
    
    echo "<div style='background: #f0f9ff; border: 2px solid #0ea5e9; border-radius: 12px; padding: 20px; margin: 20px 0;'>";
    echo "<h3 style='color: #0c4a6e;'>🎉 Ready to Use!</h3>";
    echo "<p style='color: #075985;'>Your quiz hosting system now includes:</p>";
    echo "<ul style='color: #075985;'>";
    echo "<li><strong>Lobby System:</strong> Participants wait in a lobby until you start the quiz</li>";
    echo "<li><strong>Dashboard Control:</strong> Start quizzes when ready from your dashboard</li>";
    echo "<li><strong>Live Tracking:</strong> Monitor participant progress in real-time</li>";
    echo "<li><strong>Professional Experience:</strong> Smooth, controlled quiz hosting</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='margin: 30px 0; text-align: center;'>";
    echo "<a href='online_quiz_host.php' style='display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-right: 10px;'>🚀 Create Your First Room</a>";
    echo "<a href='online_quiz_dashboard.php' style='display: inline-block; padding: 12px 24px; background: #4f6ef7; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>📊 Open Dashboard</a>";
    echo "</div>";
    
    // Clean up this setup file for security
    echo "<hr>";
    echo "<p style='font-size: 12px; color: #666;'><em>Note: You can safely delete this setup file (setup_lobby_system.php) after the setup is complete.</em></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Error</h2>";
    echo "<p>Failed to enhance quiz tables: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and permissions.</p>";
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

ul { 
    background: #ffffff; 
    padding: 20px; 
    border-radius: 8px; 
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-left: 4px solid #10b981;
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

code {
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Consolas', 'Monaco', monospace;
}
</style>
