<?php
require_once __DIR__ . '/../db_connect.php';

echo "<h1>Setting Up Quiz Enhancements</h1>";

// 1. user_saved_questions
$sql1 = "CREATE TABLE IF NOT EXISTS user_saved_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_option CHAR(1) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql1)) {
    echo "✓ Created user_saved_questions table<br>";
} else {
    echo "❌ Error creating user_saved_questions: " . $conn->error . "<br>";
}

// 2. quiz_participant_answers (for live scoring)
$sql2 = "CREATE TABLE IF NOT EXISTS quiz_participant_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(10) NOT NULL,
    roll_number VARCHAR(50) NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1) NOT NULL,
    is_correct BOOLEAN NOT NULL,
    answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_answer (room_code, roll_number, question_id),
    INDEX (room_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql2)) {
    echo "✓ Created quiz_participant_answers table<br>";
} else {
    echo "❌ Error creating quiz_participant_answers: " . $conn->error . "<br>";
}

// 3. quiz_room_questions (for room-specific questions and overrides)
$sql3 = "CREATE TABLE IF NOT EXISTS quiz_room_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    original_question_id INT NULL,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_option CHAR(1) NOT NULL,
    display_order INT DEFAULT 0,
    FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql3)) {
    echo "✓ Created quiz_room_questions table<br>";
} else {
    echo "❌ Error creating quiz_room_questions: " . $conn->error . "<br>";
}

echo "<h2>Setup Complete</h2>";
?>
