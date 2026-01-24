<?php
/**
 * Database Schema Installer for Quiz Module
 * Consolidates all table requirements for the online quiz system
 */
require_once __DIR__ . '/../db_connect.php';

echo "<h1>Installing Quiz Module Schema...</h1>";
echo "<pre>";

// Helper to run query with logging
function runQuery($conn, $sql, $message) {
    echo "Processing: $message... ";
    try {
        if ($conn->query($sql) === TRUE) {
            echo "<span style='color:green;'>OK</span>\n";
        } else {
            if (strpos($conn->error, 'already exists') !== false) {
                echo "<span style='color:blue;'>ALREADY EXISTS</span>\n";
            } else {
                echo "<span style='color:red;'>Error: " . $conn->error . "</span>\n";
            }
        }
    } catch (Exception $e) {
        echo "<span style='color:red;'>Exception: " . $e->getMessage() . "</span>\n";
    }
}

// 1. Quiz Rooms Table
runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(10) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    class_id INT,
    book_id INT,
    mcq_count INT DEFAULT 10,
    quiz_duration_minutes INT DEFAULT 30,
    quiz_started BOOLEAN DEFAULT FALSE,
    lobby_enabled BOOLEAN DEFAULT TRUE,
    start_time DATETIME NULL,
    status ENUM('active', 'completed', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_rooms");

// 2. Quiz Participants Table
runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NULL,
    name VARCHAR(100) NOT NULL,
    roll_number VARCHAR(50),
    status ENUM('waiting', 'active', 'completed', 'disconnected') DEFAULT 'waiting',
    current_question INT DEFAULT 0,
    time_remaining_sec INT NULL,
    score INT DEFAULT 0,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE,
    INDEX idx_room_status (room_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_participants");

// 3. Quiz Room Questions (Snapshot of questions for the session)
runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_room_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_option TEXT NOT NULL,
    display_order INT DEFAULT 0,
    FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_room_questions");

// 4. Quiz Participant Answers (Scoring)
runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_participant_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    participant_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1) NOT NULL,
    is_correct BOOLEAN NOT NULL,
    answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES quiz_participants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant_answer (participant_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_participant_answers");

// 5. Live Quiz Events (Real-time tracking)
runQuery($conn, "CREATE TABLE IF NOT EXISTS live_quiz_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    participant_id INT NULL,
    event_type ENUM('participant_joined', 'participant_left', 'quiz_started', 'question_answered', 'quiz_completed') NOT NULL,
    event_data JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE,
    INDEX idx_room_events (room_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: live_quiz_events");

// 6. User Saved Questions (Personal Question Bank)
runQuery($conn, "CREATE TABLE IF NOT EXISTS user_saved_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_option CHAR(1) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_saved (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: user_saved_questions");

// 7. AI Topic Cache
runQuery($conn, "CREATE TABLE IF NOT EXISTS generated_topics (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    topic_name VARCHAR(255) UNIQUE, 
    source_term VARCHAR(255),
    question_types VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: generated_topics");

echo "</pre>";
echo "<h2>Quiz Module Installation Complete</h2>";
echo "<a href='online_quiz_dashboard.php' style='display: inline-block; padding: 12px 24px; background: #4f6ef7; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>Return to Dashboard</a>";
?>
