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

// 0. Set Time Zone to PST (Pakistan Standard Time, UTC+5)
// We set session time zone only, as GLOBAL requires SUPER privileges which might not be available
runQuery($conn, "SET time_zone = '+05:00';", "Setting Session Time Zone to PST (+05:00)");


// 0. Core Content Tables
runQuery($conn, "CREATE TABLE IF NOT EXISTS class (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: class");

runQuery($conn, "CREATE TABLE IF NOT EXISTS book (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    book_name VARCHAR(255) NOT NULL,
    class_id INT NOT NULL,
    FOREIGN KEY (class_id) REFERENCES class(class_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: book");

runQuery($conn, "CREATE TABLE IF NOT EXISTS chapter (
    chapter_id INT AUTO_INCREMENT PRIMARY KEY,
    chapter_name VARCHAR(255) NOT NULL,
    chapter_no INT NOT NULL,
    class_id INT NOT NULL,
    book_id INT NOT NULL,
    book_name VARCHAR(255) NOT NULL,
    FOREIGN KEY (class_id) REFERENCES class(class_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES book(book_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: chapter");

runQuery($conn, "CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    book_id INT NOT NULL,
    chapter_id INT NOT NULL,
    question_type ENUM('short', 'long', 'mcq') NOT NULL DEFAULT 'short',
    question_text TEXT NOT NULL,
    topic VARCHAR(255) DEFAULT NULL,
    book_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES class(class_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES book(book_id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapter(chapter_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: questions");

runQuery($conn, "CREATE TABLE IF NOT EXISTS uploaded_notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    class_id INT NOT NULL,
    book_id INT NOT NULL,
    chapter_id INT NOT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME DEFAULT NULL,
    deleted_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES class(class_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES book(book_id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapter(chapter_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: uploaded_notes");

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
    `question` text NOT NULL,
    `option_a` text NOT NULL,
    `option_b` text NOT NULL,
    `option_c` text NOT NULL,
    `option_d` text NOT NULL,
    `correct_option` enum('A','B','C','D') NOT NULL,
    `difficulty_level` enum('Easy','Medium','Hard') DEFAULT 'Medium',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`mcq_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: mcqs");

// 7. API Keys (APIKeyManager.php)
runQuery($conn, "CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL DEFAULT 'openai',
    key_value TEXT NOT NULL,
    key_hash VARCHAR(64) NOT NULL,
    account_name VARCHAR(100) DEFAULT 'Default',
    status ENUM('active', 'inactive', 'rate_limited', 'quota_exceeded') DEFAULT 'active',
    usage_count INT DEFAULT 0,
    last_used DATETIME DEFAULT NULL,
    error_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_key_hash (key_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: api_keys");

try {
    $conn->query("CREATE INDEX idx_status ON api_keys(status)");
    echo "Index: api_keys.idx_status created (or existed).\n";
} catch (Exception $e) {
    // Ignore if exists
}

// 8. AI MCQs Verification (manage_ai_mcqs.php)
// Note: Assuming AIGeneratedMCQs exists (usually created by other parts or needs creation)
// Adding AIGeneratedMCQs creation just in case
runQuery($conn, "CREATE TABLE IF NOT EXISTS AIGeneratedMCQs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic VARCHAR(255),
    question TEXT,
    option_a TEXT,
    option_b TEXT,
    option_c TEXT,
    option_d TEXT,
    correct_option TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: AIGeneratedMCQs (if not exists)");

runQuery($conn, "CREATE TABLE IF NOT EXISTS AIMCQsVerification (
    mcq_id INT PRIMARY KEY,
    verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
    last_checked_at DATETIME,
    suggested_correct_option TEXT,
    original_correct_option TEXT, 
    ai_notes TEXT,
    FOREIGN KEY (mcq_id) REFERENCES AIGeneratedMCQs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: AIMCQsVerification");

// 9. MCQs Verification (for standard MCQs)
runQuery($conn, "CREATE TABLE IF NOT EXISTS MCQsVerification (
    mcq_id INT PRIMARY KEY,
    verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
    last_checked_at DATETIME,
    suggested_correct_option TEXT,
    original_correct_option TEXT,
    ai_notes TEXT,
    FOREIGN KEY (mcq_id) REFERENCES mcqs(mcq_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: MCQsVerification");

// 10. Settings (admin/settings.php)
runQuery($conn, "CREATE TABLE IF NOT EXISTS settings (
    skey VARCHAR(191) PRIMARY KEY,
    svalue TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: settings");

// 11. Class Table Fixes
echo "Checking Class Table Structure...\n";
try {
    // Ensure class_id is PRIMARY and AUTO_INCREMENT
    $result = $conn->query("DESCRIBE class");
    $class_id_is_primary = false;
    $class_id_has_auto_increment = false;

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['Field'] === 'class_id') {
                $class_id_is_primary = ($row['Key'] === 'PRI');
                $class_id_has_auto_increment = (strpos($row['Extra'], 'auto_increment') !== false);
                break;
            }
        }
    }

    if (!$class_id_is_primary) {
        runQuery($conn, "ALTER TABLE class ADD PRIMARY KEY (class_id)", "Class: Add PRIMARY KEY");
    }
    if (!$class_id_has_auto_increment) {
        runQuery($conn, "ALTER TABLE class MODIFY COLUMN class_id INT AUTO_INCREMENT", "Class: Add AUTO_INCREMENT");
    }
} catch (Exception $e) {
    echo "Error checking class table: " . $e->getMessage() . "\n";
}

// 12. Book Table Fixes
echo "Checking Book Table Structure...\n";
try {
    // Ensure book_id is PRIMARY and AUTO_INCREMENT
    $result = $conn->query("DESCRIBE book");
    $book_id_is_primary = false;
    $book_id_has_auto_increment = false;

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['Field'] === 'book_id') {
                $book_id_is_primary = ($row['Key'] === 'PRI');
                $book_id_has_auto_increment = (strpos($row['Extra'], 'auto_increment') !== false);
                break;
            }
        }
    }

    if (!$book_id_is_primary) {
        runQuery($conn, "ALTER TABLE book ADD PRIMARY KEY (book_id)", "Book: Add PRIMARY KEY");
    }
    if (!$book_id_has_auto_increment) {
        runQuery($conn, "ALTER TABLE book MODIFY COLUMN book_id INT AUTO_INCREMENT", "Book: Add AUTO_INCREMENT");
    }

    // Foreign Key Check
    $fk_result = $conn->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'book' 
        AND COLUMN_NAME = 'class_id' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");

    if ($fk_result && $fk_result->num_rows == 0) {
        // Cleanup invalid references before adding FK
        $conn->query("DELETE FROM book WHERE class_id NOT IN (SELECT class_id FROM class)");
        runQuery($conn, "ALTER TABLE book ADD CONSTRAINT fk_book_class FOREIGN KEY (class_id) REFERENCES class(class_id) ON DELETE CASCADE", "Book: Add FK to class");
    }
} catch (Exception $e) {
    echo "Error checking book table: " . $e->getMessage() . "\n";
}

// 13. Fix Missing Columns (from fix_missing_columns.php)
echo "Checking Missing Columns...\n";
$tables = ['AIMCQsVerification', 'MCQsVerification'];
foreach ($tables as $table) {
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
        if ($checkTable->num_rows > 0) {
            $colCheck = $conn->query("SHOW COLUMNS FROM $table LIKE 'original_correct_option'");
            if ($colCheck && $colCheck->num_rows === 0) {
                runQuery($conn, "ALTER TABLE $table ADD COLUMN original_correct_option TEXT AFTER suggested_correct_option", "Table $table: Add original_correct_option");
            }
        }
    } catch (Exception $e) {
        echo "Error checking columns for $table: " . $e->getMessage() . "\n";
    }
}

// 14. Quiz Rooms Table
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

// 15. Quiz Participants Table
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

// 16. Quiz Room Questions (Snapshot)
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

// 17. Quiz Participant Answers (Scoring)
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

// 18. Live Quiz Events
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

// 19. User Saved Questions
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

// 20. AI Topic Cache
runQuery($conn, "CREATE TABLE IF NOT EXISTS generated_topics (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    topic_name VARCHAR(255) UNIQUE, 
    source_term VARCHAR(255),
    question_types VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: generated_topics");

echo "</pre>";
echo "<h1>Installation Complete</h1>";
echo "<p><a href='index.php'>Return to Home</a></p>";
