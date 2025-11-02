<?php
// database/enhance_quiz_tables.php - Add lobby functionality to quiz system
require_once __DIR__ . '/../db_connect.php';

echo "<h1>Enhancing Quiz Tables for Lobby Functionality</h1>";
echo "<p>Adding lobby and live performance tracking capabilities...</p>";

try {
    // Add lobby functionality columns to quiz_rooms table
    echo "<h2>1. Enhancing quiz_rooms table</h2>";
    
    $enhancements = [
        "ADD COLUMN quiz_started BOOLEAN DEFAULT FALSE",
        "ADD COLUMN lobby_enabled BOOLEAN DEFAULT TRUE", 
        "ADD COLUMN start_time DATETIME NULL",
        "ADD COLUMN quiz_duration_minutes INT DEFAULT 30"
    ];
    
    foreach ($enhancements as $enhancement) {
        $sql = "ALTER TABLE quiz_rooms $enhancement";
        try {
            $conn->query($sql);
            echo "✓ Added: " . htmlspecialchars($enhancement) . "<br>";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "ℹ Already exists: " . htmlspecialchars($enhancement) . "<br>";
            } else {
                echo "⚠ Error with $enhancement: " . htmlspecialchars($e->getMessage()) . "<br>";
            }
        }
    }
    
    // Add participant status tracking
    echo "<h2>2. Enhancing quiz_participants table</h2>";
    
    $participant_enhancements = [
        "ADD COLUMN status ENUM('waiting', 'active', 'completed', 'disconnected') DEFAULT 'waiting'",
        "ADD COLUMN current_question INT DEFAULT 0",
        "ADD COLUMN time_remaining_sec INT NULL",
        "ADD COLUMN last_activity DATETIME DEFAULT CURRENT_TIMESTAMP"
    ];
    
    foreach ($participant_enhancements as $enhancement) {
        $sql = "ALTER TABLE quiz_participants $enhancement";
        try {
            $conn->query($sql);
            echo "✓ Added: " . htmlspecialchars($enhancement) . "<br>";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "ℹ Already exists: " . htmlspecialchars($enhancement) . "<br>";
            } else {
                echo "⚠ Error with $enhancement: " . htmlspecialchars($e->getMessage()) . "<br>";
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
    
    echo "<h2>✅ Enhancement Complete!</h2>";
    echo "<p>The quiz system has been enhanced with:</p>";
    echo "<ul>";
    echo "<li>✓ Lobby functionality (quiz_started, lobby_enabled)</li>";
    echo "<li>✓ Quiz timing control (start_time, duration)</li>";
    echo "<li>✓ Participant status tracking (status, current_question)</li>";
    echo "<li>✓ Real-time activity monitoring (last_activity)</li>";
    echo "<li>✓ Live events logging system</li>";
    echo "</ul>";
    
    echo "<p><a href='../online_quiz_host.php' class='btn'>← Back to Quiz Host</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Error</h2>";
    echo "<p>Failed to enhance quiz tables: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Close connection
$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
h1, h2 { color: #2c3e50; }
.btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
ul { background: #f8f9fa; padding: 15px; border-radius: 5px; }
</style>
