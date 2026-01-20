<?php
require_once __DIR__ . '/db_connect.php';

echo "<h1>Installing Database Schema...</h1>";
echo "<pre>";

// Helper to run query with logging
function runQuery($conn, $sql, $message) {
    echo "Processing: $message... ";
    try {
        if ($conn->query($sql) === TRUE) {
            echo "<span style='color:green;'>OK</span>\n";
        } else {
            echo "<span style='color:red;'>Error: " . $conn->error . "</span>\n";
        }
    } catch (Exception $e) {
        echo "<span style='color:red;'>Exception: " . $e->getMessage() . "</span>\n";
    }
}

// 1. Core Tables from db_connect.php
runQuery($conn, "CREATE TABLE IF NOT EXISTS question_papers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    paper_title VARCHAR(255),
    paper_content TEXT,
    is_favourite TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: question_papers");

// 2. User modifications (OAuth)
echo "Checking User Columns...\n";
try {
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'google_id'");
    if ($result && $result->num_rows == 0) {
        runQuery($conn, "ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email", "Column: users.google_id");
    }
    
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'oauth_provider'");
    if ($result && $result->num_rows == 0) {
        runQuery($conn, "ALTER TABLE users ADD COLUMN oauth_provider ENUM('local', 'google') DEFAULT 'local' AFTER google_id", "Column: users.oauth_provider");
    }
    
    runQuery($conn, "ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL", "Modify: users.password nullable");
    
    $result = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_google_id'");
    if ($result && $result->num_rows == 0) {
        runQuery($conn, "ALTER TABLE users ADD INDEX idx_google_id (google_id)", "Index: users.idx_google_id");
    }
} catch (Exception $e) {
    echo "Error in User modifications: " . $e->getMessage() . "\n";
}

// 3. Contact Messages
runQuery($conn, "CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    email VARCHAR(191) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    user_agent TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: contact_messages");

// 4. Password Resets
runQuery($conn, "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: password_resets");

// 5. Subscription Schema
if (file_exists(__DIR__ . '/database/subscription_schema.sql')) {
    echo "Running Subscription Schema...\n";
    $schema_sql = file_get_contents(__DIR__ . '/database/subscription_schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $schema_sql)));
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            runQuery($conn, $statement, "Subscription SQL Statement");
        }
    }
}

// 6. Admin Questions (manage_questions.php)
runQuery($conn, "CREATE TABLE IF NOT EXISTS `mcqs` (
    `mcq_id` int(11) NOT NULL AUTO_INCREMENT,
    `class_id` int(11) NOT NULL,
    `book_id` int(11) NOT NULL,
    `chapter_id` int(11) NOT NULL,
    `topic` varchar(255) NOT NULL,
    `question` text NOT NULL,
    `option_a` varchar(255) NOT NULL,
    `option_b` varchar(255) NOT NULL,
    `option_c` varchar(255) NOT NULL,
    `option_d` varchar(255) NOT NULL,
    `correct_option` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`mcq_id`),
    KEY `class_id` (`class_id`),
    KEY `book_id` (`book_id`),
    KEY `chapter_id` (`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", "Table: mcqs");

runQuery($conn, "CREATE TABLE IF NOT EXISTS deleted_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    class_id INT NOT NULL,
    book_name VARCHAR(191) NULL,
    chapter_id INT NOT NULL,
    question_type ENUM('mcq','short','long') NOT NULL,
    question_text TEXT NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: deleted_questions");

// 7. Admin Notes (manage_notes.php)
runQuery($conn, "CREATE TABLE IF NOT EXISTS `uploaded_notes` (
    `note_id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `file_name` VARCHAR(255) NOT NULL,
    `original_file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` VARCHAR(50) NOT NULL,
    `file_size` BIGINT NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `class_id` INT DEFAULT NULL,
    `book_id` INT DEFAULT NULL,
    `chapter_id` INT DEFAULT NULL,
    `uploaded_by` INT NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_class_book_chapter` (`class_id`, `book_id`, `chapter_id`),
    INDEX `idx_is_deleted` (`is_deleted`),
    INDEX `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Table: uploaded_notes");

// 8. AI Generated MCQs (quiz/mcq_generator.php)
runQuery($conn, "CREATE TABLE IF NOT EXISTS `AIGeneratedMCQs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `topic` varchar(255) NOT NULL,
    `question` text NOT NULL,
    `option_a` varchar(255) NOT NULL,
    `option_b` varchar(255) NOT NULL,
    `option_c` varchar(255) NOT NULL,
    `option_d` varchar(255) NOT NULL,
    `correct_option` varchar(255) DEFAULT NULL,
    `generated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `topic` (`topic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", "Table: AIGeneratedMCQs");

// AI table modifications
echo "Checking AIGeneratedMCQs columns...\n";
$ai_cols = ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option'];
foreach ($ai_cols as $col) {
    $check = $conn->query("SHOW COLUMNS FROM AIGeneratedMCQs LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $type = ($col === 'question') ? 'TEXT NOT NULL' : 'VARCHAR(255) NOT NULL';
        if ($col === 'correct_option') $type = 'VARCHAR(255) DEFAULT NULL';
        runQuery($conn, "ALTER TABLE AIGeneratedMCQs ADD COLUMN $col $type", "Column: AIGeneratedMCQs.$col");
    }
}
// Clean up legacy cols in AI table
$ai_remove = ['class_id', 'book_id', 'chapter_id', 'mcq_id'];
foreach ($ai_remove as $col) {
    if ($col === 'mcq_id') {
        // Try to drop FK first
         $fkCheck = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'AIGeneratedMCQs' AND COLUMN_NAME = 'mcq_id' AND REFERENCED_TABLE_NAME = 'mcqs' LIMIT 1");
        if ($fkCheck && $row = $fkCheck->fetch_assoc()) {
            $fkName = $row['CONSTRAINT_NAME'];
            runQuery($conn, "ALTER TABLE AIGeneratedMCQs DROP FOREIGN KEY `$fkName`", "Drop FK for $col");
        }
    }
    $check = $conn->query("SHOW COLUMNS FROM AIGeneratedMCQs LIKE '$col'");
    if ($check && $check->num_rows > 0) {
        runQuery($conn, "ALTER TABLE AIGeneratedMCQs DROP COLUMN $col", "Drop Column: AIGeneratedMCQs.$col");
    }
}

// 9. Quiz Rooms (quiz/online_quiz_create_room.php)
runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(10) NOT NULL UNIQUE,
    class_id INT NOT NULL,
    book_id INT NOT NULL,
    mcq_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','closed') NOT NULL DEFAULT 'active',
    quiz_started BOOLEAN DEFAULT FALSE,
    lobby_enabled BOOLEAN DEFAULT TRUE,
    start_time DATETIME NULL,
    quiz_duration_minutes INT DEFAULT 30,
    user_id INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_rooms");

// Quiz rooms modifications
echo "Checking quiz_rooms columns...\n";
$result = $conn->query("SHOW COLUMNS FROM quiz_rooms LIKE 'user_id'");
if ($result && $result->num_rows == 0) {
    runQuery($conn, "ALTER TABLE quiz_rooms ADD COLUMN user_id INT NULL", "Column: quiz_rooms.user_id");
}
$result = $conn->query("SHOW COLUMNS FROM quiz_rooms LIKE 'quiz_duration_minutes'");
if ($result && $result->num_rows == 0) {
    runQuery($conn, "ALTER TABLE quiz_rooms ADD COLUMN quiz_duration_minutes INT DEFAULT 30", "Column: quiz_rooms.quiz_duration_minutes");
}

runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_room_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a TEXT,
    option_b TEXT,
    option_c TEXT,
    option_d TEXT,
    correct_option TEXT,
    FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_room_questions");

runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    roll_number VARCHAR(100) NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    score INT DEFAULT NULL,
    total_questions INT DEFAULT NULL,
    status ENUM('waiting', 'active', 'completed', 'disconnected') DEFAULT 'waiting',
    current_question INT DEFAULT 0,
    time_remaining_sec INT NULL,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_participants");

runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option VARCHAR(1) NULL,
    is_correct TINYINT(1) NULL,
    time_spent_sec INT NULL,
    FOREIGN KEY (participant_id) REFERENCES quiz_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_room_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_responses");

runQuery($conn, "CREATE TABLE IF NOT EXISTS live_quiz_events (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: live_quiz_events");

// 10. Enhancements (quiz/setup_quiz_enhancements.php)
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
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: user_saved_questions");

runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_participant_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(10) NOT NULL,
    roll_number VARCHAR(50) NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1) NOT NULL,
    is_correct BOOLEAN NOT NULL,
    answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_answer (room_code, roll_number, question_id),
    INDEX (room_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: quiz_participant_answers");

// 11. AI Verification & New AI Generation
runQuery($conn, "CREATE TABLE IF NOT EXISTS AIMCQsVerification (
    mcq_id INT PRIMARY KEY,
    verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
    last_checked_at DATETIME,
    suggested_correct_option TEXT,
    ai_notes TEXT,
    FOREIGN KEY (mcq_id) REFERENCES AIGeneratedMCQs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: AIMCQsVerification");

runQuery($conn, "CREATE TABLE IF NOT EXISTS AIGeneratedQuestion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NULL, 
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_option CHAR(1) NOT NULL,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_topic (topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: AIGeneratedQuestion");

echo "</pre>";
echo "<h2>Installation / Update Complete!</h2>";
?>