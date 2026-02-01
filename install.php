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
    topic VARCHAR(255) NOT NULL,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_option TEXT NOT NULL,
    generated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: AIGeneratedMCQs");

// 8.1 AI Questions Topic (Normalized Topic Storage)
runQuery($conn, "CREATE TABLE IF NOT EXISTS AIQuestionsTopic (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: AIQuestionsTopic");

// 8.2 Update AIGeneratedMCQs to link to AIQuestionsTopic
// First, check if topic_id column exists
$result = $conn->query("SHOW COLUMNS FROM AIGeneratedMCQs LIKE 'topic_id'");
if ($result && $result->num_rows == 0) {
    runQuery($conn, "ALTER TABLE AIGeneratedMCQs ADD COLUMN topic_id INT DEFAULT NULL AFTER id", "Column: AIGeneratedMCQs.topic_id");
    runQuery($conn, "ALTER TABLE AIGeneratedMCQs ADD INDEX idx_topic_id (topic_id)", "Index: AIGeneratedMCQs.idx_topic_id");
    runQuery($conn, "ALTER TABLE AIGeneratedMCQs ADD CONSTRAINT fk_ai_mcq_topic FOREIGN KEY (topic_id) REFERENCES AIQuestionsTopic(id) ON DELETE SET NULL", "FK: AIGeneratedMCQs.topic_id");
}

// 8.3 Fix AIGeneratedMCQs column names if needed (question_text vs question)
// The user reported 'Unknown column question_text'. The table creation above uses 'question'.
// Let's standardize on 'question_text' to match the PHP code request, or update PHP to match table.
// User's error: "Unknown column 'question_text'". This means the PHP code tries to insert into 'question_text', but the table has something else (likely 'question').
// We will alter the table to have 'question_text' instead of 'question' if 'question' exists.
$result = $conn->query("SHOW COLUMNS FROM AIGeneratedMCQs LIKE 'question'");
if ($result && $result->num_rows > 0) {
    runQuery($conn, "ALTER TABLE AIGeneratedMCQs CHANGE COLUMN question question_text TEXT", "Rename: AIGeneratedMCQs.question to question_text");
}
// Ensure question_text exists if it wasn't renamed (e.g. table created fresh with question_text or just needs adding)
$result = $conn->query("SHOW COLUMNS FROM AIGeneratedMCQs LIKE 'question_text'");
if ($result && $result->num_rows == 0) {
     runQuery($conn, "ALTER TABLE AIGeneratedMCQs ADD COLUMN question_text TEXT AFTER topic_id", "Column: AIGeneratedMCQs.question_text");
}

// 8.4 Ensure generated_at exists
$result = $conn->query("SHOW COLUMNS FROM AIGeneratedMCQs LIKE 'generated_at'");
if ($result && $result->num_rows == 0) {
    runQuery($conn, "ALTER TABLE AIGeneratedMCQs ADD COLUMN generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP", "Column: AIGeneratedMCQs.generated_at");
}

runQuery($conn, "CREATE TABLE IF NOT EXISTS AIMCQsVerification (
    mcq_id INT PRIMARY KEY,
    verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
    verified_by INT DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    correction_notes TEXT,
    last_checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mcq_id) REFERENCES AIGeneratedMCQs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: AIMCQsVerification");

// 9. Topic Question Counts (Materialized View for Performance)
runQuery($conn, "CREATE TABLE IF NOT EXISTS TopicQuestionCounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_name VARCHAR(255) NOT NULL,
    question_count INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_topic (topic_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: TopicQuestionCounts");

// 9.1 Populate TopicQuestionCounts with existing data
runQuery($conn, "INSERT INTO TopicQuestionCounts (topic_name, question_count)
SELECT t.topic_name, COUNT(m.id) as total
FROM AIGeneratedMCQs m
JOIN AIQuestionsTopic t ON m.topic_id = t.id
GROUP BY t.topic_name
ON DUPLICATE KEY UPDATE question_count = VALUES(question_count)", "Populating TopicQuestionCounts");

// 9.2 AI Generated Short Questions
runQuery($conn, "CREATE TABLE IF NOT EXISTS AIGeneratedShortQuestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT DEFAULT NULL,
    question_text TEXT NOT NULL,
    typical_answer TEXT DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES AIQuestionsTopic(id) ON DELETE SET NULL,
    INDEX idx_topic_id (topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: AIGeneratedShortQuestions");

// 9.3 Topic Short Question Counts
runQuery($conn, "CREATE TABLE IF NOT EXISTS TopicShortQuestionCounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_name VARCHAR(255) NOT NULL,
    question_count INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_topic (topic_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: TopicShortQuestionCounts");

// 9.4 AI Generated Long Questions
runQuery($conn, "CREATE TABLE IF NOT EXISTS AIGeneratedLongQuestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT DEFAULT NULL,
    question_text TEXT NOT NULL,
    typical_answer TEXT DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES AIQuestionsTopic(id) ON DELETE SET NULL,
    INDEX idx_topic_id (topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: AIGeneratedLongQuestions");

// 9.5 Topic Long Question Counts
runQuery($conn, "CREATE TABLE IF NOT EXISTS TopicLongQuestionCounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_name VARCHAR(255) NOT NULL,
    question_count INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_topic (topic_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: TopicLongQuestionCounts");

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

// ============================================================================
// 21. AI API Key Orchestration System Tables & Data
// ============================================================================
echo "\n<strong>Setting up AI Key Orchestration System...</strong>\n";

// Create AI Accounts Table
runQuery($conn, "CREATE TABLE IF NOT EXISTS ai_accounts (
    account_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(100) NOT NULL COMMENT 'e.g., OpenAI, Gemini, Anthropic',
    account_name VARCHAR(255) NOT NULL COMMENT 'e.g., OpenAI Account 1, OpenAI Account 2',
    priority INT NOT NULL DEFAULT 10 COMMENT 'Lower number = higher priority (1 = highest)',
    status ENUM('active', 'suspended', 'disabled') DEFAULT 'active' COMMENT 'Overall account status',
    daily_quota INT NOT NULL DEFAULT 1000000 COMMENT 'Total tokens per day',
    monthly_budget DECIMAL(10, 2) DEFAULT NULL COMMENT 'Monthly spending limit',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notes TEXT COMMENT 'Admin notes',
    INDEX idx_provider_priority (provider_name, priority),
    INDEX idx_status (status),
    UNIQUE KEY uk_account_name (account_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Table: ai_accounts");

// Create AI API Keys Table
runQuery($conn, "CREATE TABLE IF NOT EXISTS ai_api_keys (
    key_id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    api_key_hash VARCHAR(255) NOT NULL COMMENT 'SHA256 hash for quick lookups',
    api_key_encrypted LONGBLOB NOT NULL COMMENT 'AES-256 encrypted API key',
    key_name VARCHAR(100) COMMENT 'Human-readable name (e.g., key1, key2)',
    model_name VARCHAR(255) DEFAULT 'gpt-4-turbo' COMMENT 'AI model for this key (e.g., gpt-4, gpt-3.5-turbo, gemini-pro)',
    daily_limit INT NOT NULL DEFAULT 100000 COMMENT 'Max tokens/requests per day',
    used_today INT NOT NULL DEFAULT 0 COMMENT 'Reset daily',
    last_reset_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'temporarily_blocked', 'exhausted', 'disabled') DEFAULT 'active',
    last_used_at TIMESTAMP NULL COMMENT 'Last successful request',
    consecutive_failures INT DEFAULT 0 COMMENT 'Circuit breaker counter',
    temporary_block_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    disabled_reason VARCHAR(255),
    INDEX idx_account_id (account_id),
    INDEX idx_status_daily_limit (status, used_today),
    INDEX idx_last_used_at (last_used_at),
    INDEX idx_temporary_block_until (temporary_block_until),
    INDEX idx_model_name (model_name),
    UNIQUE KEY uk_api_key_hash (api_key_hash),
    FOREIGN KEY (account_id) REFERENCES ai_accounts(account_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Table: ai_api_keys");

// Create Request Logs Table
runQuery($conn, "CREATE TABLE IF NOT EXISTS ai_request_logs (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    key_id INT NOT NULL,
    request_id VARCHAR(36) UNIQUE COMMENT 'UUID for tracing',
    service VARCHAR(50) NOT NULL COMMENT 'e.g., openai, gemini',
    model VARCHAR(100) NOT NULL COMMENT 'e.g., gpt-4',
    request_type VARCHAR(50),
    tokens_used INT NOT NULL DEFAULT 0,
    estimated_cost DECIMAL(8, 6),
    response_status INT COMMENT 'HTTP status code',
    error_code VARCHAR(100),
    error_message TEXT,
    is_retry BOOLEAN DEFAULT FALSE,
    retry_count INT DEFAULT 0,
    original_request_id VARCHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    duration_ms INT,
    user_id INT,
    source_ip VARCHAR(45),
    INDEX idx_account_id_created (account_id, created_at),
    INDEX idx_key_id_created (key_id, created_at),
    INDEX idx_request_id (request_id),
    INDEX idx_service_model (service, model),
    INDEX idx_response_status (response_status),
    INDEX idx_user_id_created (user_id, created_at),
    FOREIGN KEY (account_id) REFERENCES ai_accounts(account_id) ON DELETE RESTRICT,
    FOREIGN KEY (key_id) REFERENCES ai_api_keys(key_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Table: ai_request_logs");

// Create Failover Events Table
runQuery($conn, "CREATE TABLE IF NOT EXISTS ai_failover_events (
    failover_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    from_account_id INT NOT NULL,
    from_key_id INT NOT NULL,
    to_account_id INT NOT NULL,
    to_key_id INT NOT NULL,
    reason ENUM('quota_exhausted', 'rate_limited', 'temporary_block_expired', 'key_disabled', 'account_suspended', 'key_status_changed', 'consecutive_failures') NOT NULL,
    related_request_id VARCHAR(36),
    http_status_code INT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_from_account_to_account (from_account_id, to_account_id),
    INDEX idx_reason_created (reason, created_at),
    FOREIGN KEY (from_account_id) REFERENCES ai_accounts(account_id) ON DELETE RESTRICT,
    FOREIGN KEY (from_key_id) REFERENCES ai_api_keys(key_id) ON DELETE RESTRICT,
    FOREIGN KEY (to_account_id) REFERENCES ai_accounts(account_id) ON DELETE RESTRICT,
    FOREIGN KEY (to_key_id) REFERENCES ai_api_keys(key_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Table: ai_failover_events");

// Create Account Quotas Table
runQuery($conn, "CREATE TABLE IF NOT EXISTS ai_account_quotas (
    quota_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    snapshot_date DATE NOT NULL,
    total_requests INT DEFAULT 0,
    total_tokens_used INT DEFAULT 0,
    total_cost DECIMAL(10, 6) DEFAULT 0.00,
    remaining_quota INT,
    number_of_active_keys INT,
    number_of_blocked_keys INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_account_date (account_id, snapshot_date),
    FOREIGN KEY (account_id) REFERENCES ai_accounts(account_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Table: ai_account_quotas");

// Create Health Checks Table
runQuery($conn, "CREATE TABLE IF NOT EXISTS ai_key_health_checks (
    health_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    key_id INT NOT NULL,
    is_healthy BOOLEAN DEFAULT TRUE,
    http_status INT,
    error_message TEXT,
    response_time_ms INT,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_key_id_checked (key_id, checked_at),
    FOREIGN KEY (key_id) REFERENCES ai_api_keys(key_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Table: ai_key_health_checks");

// ============================================================================
// Add missing columns to ai_api_keys table (compatibility fix)
// ============================================================================
echo "\n<strong>Ensuring ai_api_keys table has required columns...</strong>\n";

// Safely check and add columns using ALTER IGNORE or conditional logic
try {
    // Try to add key_name if it doesn't exist
    @$conn->query("ALTER TABLE ai_api_keys ADD COLUMN key_name VARCHAR(100) COMMENT 'Human-readable name (e.g., api_key_1_primary)' AFTER api_key_encrypted");
    
    // Try to add model_name if it doesn't exist
    @$conn->query("ALTER TABLE ai_api_keys ADD COLUMN model_name VARCHAR(255) DEFAULT 'gpt-4-turbo' COMMENT 'AI model for this key' AFTER key_name");
    
    // Try to add index if it doesn't exist
    @$conn->query("ALTER TABLE ai_api_keys ADD INDEX idx_model_name (model_name)");
    
    echo "<span style='color:green;'>✓ Schema compatibility check completed</span>\n";
} catch (Exception $e) {
    // Columns might already exist - that's OK
    echo "<span style='color:blue;'>ℹ Schema already has required columns</span>\n";
}

// ============================================================================
// Verify AI Keys Schema
// ============================================================================
echo "\n<strong>Verifying AI API Keys schema...</strong>\n";

// ============================================================================
// Load API Keys from .env.local or .env.production using new AIKeyConfigManager
// ============================================================================
require_once __DIR__ . '/config/AIKeyConfigManager.php';

try {
    // Determine which environment file to use
    $envFile = __DIR__ . '/config/.env.local';
    
    // Check if in production environment
    if ((defined('ENVIRONMENT') && ENVIRONMENT === 'production') || getenv('APP_ENV') === 'production') {
        $envFile = __DIR__ . '/config/.env.production';
    }
    
    // Use .env.production if it exists and .env.local doesn't
    if (!file_exists($envFile)) {
        $altFile = (strpos($envFile, '.env.production') !== false) ? 
            __DIR__ . '/config/.env.local' : 
            __DIR__ . '/config/.env.production';
        if (file_exists($altFile)) {
            $envFile = $altFile;
        }
    }
    
    $configManager = new AIKeyConfigManager($envFile);
    $encryption_key = getenv('AI_ENCRYPTION_KEY') ?: (defined('AI_ENCRYPTION_KEY') ? AI_ENCRYPTION_KEY : null);
    
    echo "Found " . $configManager->getTotalKeyCount() . " key(s) in .env.local\n";
    
    $allAccounts = $configManager->getAllAccounts();
    
    foreach ($allAccounts as $accountData) {
        // Check if account exists
        $accountCheck = $conn->query("SELECT account_id FROM ai_accounts WHERE account_name = '" . $conn->real_escape_string($accountData['name']) . "'");
        
        if ($accountCheck && $accountCheck->num_rows == 0) {
            // Insert account
            $insertAcctSql = "INSERT INTO ai_accounts (provider_name, account_name, priority, status, daily_quota, notes) 
                             VALUES ('{$accountData['provider']}', '{$conn->real_escape_string($accountData['name'])}', {$accountData['priority']}, 'active', 1000000, 'Loaded from .env.local')";
            runQuery($conn, $insertAcctSql, "Account: {$accountData['name']}");
            $acctId = $conn->insert_id;
        } else {
            $row = $accountCheck->fetch_assoc();
            $acctId = $row['account_id'];
            echo "Account '{$accountData['name']}' already exists (skipped).\n";
        }
        
        // Insert keys for this account
        foreach ($accountData['keys'] as $keyData) {
            if (empty($keyData['value'])) continue;
            
            $keyHash = hash('sha256', $keyData['value']);
            $keyName = $keyData['name'];
            $modelName = $keyData['model'] ?? 'gpt-4-turbo';
            
            // Check if key already exists
            $existing = $conn->query("SELECT key_id FROM ai_api_keys WHERE api_key_hash = '$keyHash'");
            if ($existing && $existing->num_rows == 0) {
                // Encrypt the key
                if ($encryption_key) {
                    $iv = openssl_random_pseudo_bytes(16);
                    $encrypted = openssl_encrypt($keyData['value'], 'AES-256-CBC', base64_decode($encryption_key), OPENSSL_RAW_DATA, $iv);
                    $encryptedBlob = base64_encode($iv . $encrypted);
                } else {
                    echo "<span style='color:orange;'>Warning: AI_ENCRYPTION_KEY not set. Storing key without encryption.</span>\n";
                    $encryptedBlob = base64_encode($keyData['value']);
                }
                
                $encryptedEscaped = $conn->real_escape_string($encryptedBlob);
                $insertKeySql = "INSERT INTO ai_api_keys (account_id, api_key_hash, api_key_encrypted, key_name, model_name, daily_limit, status) 
                                VALUES ($acctId, '$keyHash', '$encryptedEscaped', '{$conn->real_escape_string($keyName)}', '{$conn->real_escape_string($modelName)}', 100000, 'active')";
                runQuery($conn, $insertKeySql, "Key: {$accountData['name']} - $keyName (model: $modelName)");
            } else {
                echo "Key $keyName already exists (skipped).\n";
            }
        }
    }
    
    echo "<span style='color:green;'>All API keys and accounts loaded successfully from .env.local!</span>\n";
    
} catch (Exception $e) {
    echo "<span style='color:red;'>Error loading API keys: " . htmlspecialchars($e->getMessage()) . "</span>\n";
}

    // ============================================================================
    // Ensure expected columns exist across the schema (compatibility fixes)
    // ============================================================================
    echo "\n<strong>Ensuring required columns exist (compatibility fixes)...</strong>\n";

    $compat_columns = [
        'generated_topics' => [
            'question_types' => "VARCHAR(255) DEFAULT NULL",
            'source_term' => "VARCHAR(255) DEFAULT NULL"
        ],
        'AIGeneratedMCQs' => [
            'topic_id' => "INT DEFAULT NULL",
            'question_text' => "TEXT",
            'generated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
        ],
        'questions' => [
            'question_text' => "TEXT NOT NULL"
        ],
        'user_saved_questions' => [
            'question_text' => "TEXT NOT NULL"
        ]
    ];

    foreach ($compat_columns as $table => $cols) {
        foreach ($cols as $col => $definition) {
            try {
                $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
                if (!($check && $check->num_rows > 0)) {
                    $sql = "ALTER TABLE `$table` ADD COLUMN $col $definition";
                    runQuery($conn, $sql, "Alter: $table ADD $col");
                } else {
                    echo "Column $col already exists on $table (skipped).\n";
                }
            } catch (Exception $e) {
                echo "Error checking/adding column $col on $table: " . $e->getMessage() . "\n";
            }
        }
    }

    // AI Keys verification - ensures .env keys are loaded
    echo "<h3>AI API Keys</h3>\n";
    if (file_exists(__DIR__ . '/services/AIKeyRotator.php') && class_exists('EnvLoader')) {
        require_once __DIR__ . '/services/AIKeyRotator.php';
        $cacheManager = null;
        if (file_exists(__DIR__ . '/services/CacheManager.php')) {
            require_once __DIR__ . '/services/CacheManager.php';
            try { $cacheManager = new CacheManager(); } catch (Exception $e) {}
        }
        $rotator = new AIKeyRotator($cacheManager);
        $keys = $rotator->getAllKeys();
        $count = count($keys);
        if ($count > 0) {
            echo "<span style='color:green;'>OK</span> " . $count . " API key(s) loaded from .env (KEY_1, KEY_2, etc.)<br>\n";
        } else {
            echo "<span style='color:orange;'>Warning</span> No AI keys found. Add KEY_1=sk-... to config/.env.local<br>\n";
        }
    } else {
        echo "<span style='color:gray;'>Skip</span> AIKeyRotator not available<br>\n";
    }

    echo "</pre>";
    echo "<h1>Installation Complete</h1>";
    echo "<p><a href='index.php'>Return to Home</a></p>";
