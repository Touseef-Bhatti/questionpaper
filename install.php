<?php
require_once __DIR__ . '/db_connect.php';

echo "<h1>Installing Database Schema...</h1>";
echo "<pre>";

// Helper function to check if index exists
function indexExists($conn, $tableName, $indexName) {
    $result = $conn->query("SHOW INDEX FROM $tableName WHERE Key_name = '$indexName'");
    return $result && $result->num_rows > 0;
}

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

// Helper to create index only if it doesn't exist
function createIndexIfNotExists($conn, $tableName, $indexName, $columns) {
    $msg = "Index: $tableName($columns)";
    if (indexExists($conn, $tableName, $indexName)) {
        echo "Processing: $msg... <span style='color:blue;'>Skipped (exists)</span>\n";
        return true;
    }
    runQuery($conn, "CREATE INDEX $indexName ON $tableName($columns);", $msg);
}

// 0. Set Time Zone to PST (Pakistan Standard Time, UTC+5)
runQuery($conn, "SET time_zone = '+05:00';", "Setting Session Time Zone to PST (+05:00)");

// 0.1 User Subscription Columns
try {
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'subscription_status'");
    if ($result && $result->num_rows == 0) {
        runQuery($conn, "ALTER TABLE users ADD COLUMN subscription_status ENUM('free', 'premium', 'pro') DEFAULT 'free' AFTER role", "Column: users.subscription_status");
    }
    
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'subscription_expires_at'");
    if ($result && $result->num_rows == 0) {
        runQuery($conn, "ALTER TABLE users ADD COLUMN subscription_expires_at DATETIME NULL AFTER subscription_status", "Column: users.subscription_expires_at");
        runQuery($conn, "ALTER TABLE users ADD INDEX idx_users_subscription (subscription_status, subscription_expires_at)", "Index: users.idx_users_subscription");
    }
} catch (Exception $e) {
    echo "Error checking user columns: " . $e->getMessage() . "\n";
}

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

// 2. Contact Messages
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

// 3. Password Resets
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

// 4. Subscription Schema
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

// 5.1 Subscription Plans Structure Updates
echo "Updating subscription_plans table structure...\n";
try {
    $columns_to_delete = ['max_papers_per_month', 'max_chapters_per_paper', 'max_questions_per_paper', 'max_topics_per_quiz'];
    foreach ($columns_to_delete as $col) {
        $result = $conn->query("SHOW COLUMNS FROM subscription_plans LIKE '$col'");
        if ($result && $result->num_rows > 0) {
            runQuery($conn, "ALTER TABLE subscription_plans DROP COLUMN $col", "Drop: subscription_plans.$col");
        }
    }

    $new_columns = [
        'questionPaperPerDay' => "INT DEFAULT -1 AFTER duration_days",
        'TopicsForOnlineMCQs' => "INT DEFAULT -1 AFTER questionPaperPerDay",
        'CustomPaperTemplate' => "TINYINT(1) DEFAULT 0 AFTER TopicsForOnlineMCQs",
        'Ads' => "TINYINT(1) DEFAULT 1 AFTER CustomPaperTemplate"
    ];

    foreach ($new_columns as $col => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM subscription_plans LIKE '$col'");
        if ($result && $result->num_rows == 0) {
            runQuery($conn, "ALTER TABLE subscription_plans ADD COLUMN $col $definition", "Add: subscription_plans.$col");
        }
    }
} catch (Exception $e) {
    echo "Error updating subscription_plans: " . $e->getMessage() . "\n";
}

// 5.2 Subscription Plan Features Table
runQuery($conn, "CREATE TABLE IF NOT EXISTS subscription_plan_features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    feature_text VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE,
    KEY (plan_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Table: subscription_plan_features");

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

// 8. AI MCQs Verification (manage_ai_mcqs.php)
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

runQuery($conn, "CREATE TABLE IF NOT EXISTS AIMCQsVerification (
    mcq_id INT PRIMARY KEY,
    verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
    last_checked_at DATETIME,
    suggested_correct_option TEXT,
    original_correct_option TEXT,
    ai_notes TEXT,
    FOREIGN KEY (mcq_id) REFERENCES AIGeneratedMCQs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: AIMCQsVerification");

runQuery($conn, "CREATE TABLE IF NOT EXISTS MCQsVerification (
    mcq_id INT PRIMARY KEY,
    verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
    last_checked_at DATETIME,
    suggested_correct_option TEXT,
    original_correct_option TEXT,
    ai_notes TEXT,
    FOREIGN KEY (mcq_id) REFERENCES mcqs(mcq_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: MCQsVerification");

// 8.1 AI Questions Topic (Normalized Topic Storage)
runQuery($conn, "CREATE TABLE IF NOT EXISTS AIQuestionsTopic (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: AIQuestionsTopic");

// 18. Quiz Rooms
runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host_id INT NOT NULL,
    room_code VARCHAR(10) NOT NULL UNIQUE,
    class_id INT NOT NULL,
    book_id INT NOT NULL,
    duration_mins INT DEFAULT 30,
    lobby_enabled BOOLEAN DEFAULT TRUE,
    start_time DATETIME NULL,
    active_question_ids TEXT DEFAULT NULL,
    status ENUM('active', 'completed', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_rooms");

runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_room_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_option TEXT NOT NULL,
    FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_room_questions");

runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    roll_number VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    score INT DEFAULT 0,
    question_order TEXT NULL,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('lobby', 'in_progress', 'completed') DEFAULT 'lobby',
    FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (room_id, roll_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_participants");

runQuery($conn, "CREATE TABLE IF NOT EXISTS live_quiz_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    participant_id INT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE,
    INDEX idx_room_events (room_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: live_quiz_events");

// 18.1 Quiz Responses (Live answer tracking)
runQuery($conn, "CREATE TABLE IF NOT EXISTS quiz_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option VARCHAR(1) NULL,
    is_correct TINYINT(1) NULL,
    time_spent_sec INT NULL,
    FOREIGN KEY (participant_id) REFERENCES quiz_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_room_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_response (participant_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: quiz_responses");

// 18.2 Usage Tracking
runQuery($conn, "CREATE TABLE IF NOT EXISTS usage_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id INT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(100) NULL,
    resource_id INT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_action (user_id, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: usage_tracking");

// 18.3 User Generated Papers Log (Specific tracking for papers)
runQuery($conn, "CREATE TABLE IF NOT EXISTS user_generated_papers_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    paper_type ENUM('standard', 'ai') NOT NULL DEFAULT 'standard',
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_generated (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: user_generated_papers_log");

// 22. Admin Management (admins, admin_logs, pending_admin_actions)
runQuery($conn, "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'superadmin', 'super_admin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: admins");

runQuery($conn, "CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: admin_logs");

runQuery($conn, "CREATE TABLE IF NOT EXISTS pending_admin_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_type ENUM('create', 'delete') NOT NULL,
    admin_id INT NULL,
    name VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    password_hash VARCHAR(255) NULL,
    role VARCHAR(50) NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "Table: pending_admin_actions");

// ========== MIGRATE MISSING COLUMNS (For old installations) ==========
echo "\n\n=== Migrating Schema (Adding missing columns) ===\n";

// Check if questions table has created_at column
$result = $conn->query("SHOW COLUMNS FROM questions LIKE 'created_at'");
if (!$result || $result->num_rows == 0) {
    runQuery($conn, "ALTER TABLE questions ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP", "Column: questions.created_at");
}

// Check if quiz_rooms table has host_id column
$result = $conn->query("SHOW COLUMNS FROM quiz_rooms LIKE 'host_id'");
if (!$result || $result->num_rows == 0) {
    runQuery($conn, "ALTER TABLE quiz_rooms ADD COLUMN host_id INT NOT NULL DEFAULT 1", "Column: quiz_rooms.host_id");
}

// Check if AIMCQsVerification has original_correct_option
$result = $conn->query("SHOW COLUMNS FROM AIMCQsVerification LIKE 'original_correct_option'");
if (!$result || $result->num_rows == 0) {
    runQuery($conn, "ALTER TABLE AIMCQsVerification ADD COLUMN original_correct_option TEXT AFTER suggested_correct_option", "Column: AIMCQsVerification.original_correct_option");
}

// Check if MCQsVerification has original_correct_option
$result = $conn->query("SHOW COLUMNS FROM MCQsVerification LIKE 'original_correct_option'");
if (!$result || $result->num_rows == 0) {
    runQuery($conn, "ALTER TABLE MCQsVerification ADD COLUMN original_correct_option TEXT AFTER suggested_correct_option", "Column: MCQsVerification.original_correct_option");
}
// ========== PERFORMANCE INDEXES - CRITICAL FOR PRODUCTION ==========
echo "\n\n=== Creating Performance Indexes ===\n";

// Class & Book Indexes
createIndexIfNotExists($conn, "class", "idx_class_id", "class_id");
createIndexIfNotExists($conn, "book", "idx_book_class", "class_id");
createIndexIfNotExists($conn, "book", "idx_book_id", "book_id");

// Chapter Indexes - Most Critical
createIndexIfNotExists($conn, "chapter", "idx_chapter_class_book", "class_id, book_id");
createIndexIfNotExists($conn, "chapter", "idx_chapter_book_name", "book_name, class_id");
createIndexIfNotExists($conn, "chapter", "idx_chapter_id", "chapter_id");
createIndexIfNotExists($conn, "chapter", "idx_chapter_book_id", "book_id");

// Questions Indexes - MOST CRITICAL FOR PERFORMANCE
createIndexIfNotExists($conn, "questions", "idx_questions_chapter", "chapter_id");
createIndexIfNotExists($conn, "questions", "idx_questions_book", "book_id");
createIndexIfNotExists($conn, "questions", "idx_questions_class", "class_id");
createIndexIfNotExists($conn, "questions", "idx_questions_type_chapter", "question_type, chapter_id");
createIndexIfNotExists($conn, "questions", "idx_questions_topic_type", "topic, question_type");
createIndexIfNotExists($conn, "questions", "idx_questions_class_book_chapter", "class_id, book_id, chapter_id, question_type");
createIndexIfNotExists($conn, "questions", "idx_questions_type_date", "question_type, created_at");

// MCQs Indexes
createIndexIfNotExists($conn, "mcqs", "idx_mcqs_chapter", "chapter_id");
createIndexIfNotExists($conn, "mcqs", "idx_mcqs_book", "book_id");
createIndexIfNotExists($conn, "mcqs", "idx_mcqs_class", "class_id");
createIndexIfNotExists($conn, "mcqs", "idx_mcqs_class_book_chapter", "class_id, book_id, chapter_id");

// Question Papers Indexes
createIndexIfNotExists($conn, "question_papers", "idx_papers_user", "user_id");
createIndexIfNotExists($conn, "question_papers", "idx_papers_user_date", "user_id, created_at");
createIndexIfNotExists($conn, "question_papers", "idx_papers_favourite", "is_favourite");

// Subscription Indexes
createIndexIfNotExists($conn, "user_subscriptions", "idx_user_subscriptions_user", "user_id");
createIndexIfNotExists($conn, "user_subscriptions", "idx_user_subscriptions_status", "status");
createIndexIfNotExists($conn, "user_subscriptions", "idx_user_subscriptions_active", "user_id, status, expires_at");
createIndexIfNotExists($conn, "subscription_plans", "idx_subscription_plans_id", "id");
createIndexIfNotExists($conn, "subscription_plans", "idx_subscription_plans_name", "name");
createIndexIfNotExists($conn, "subscription_plan_features", "idx_subscription_plan_features_plan", "plan_id");

// Quiz Indexes
createIndexIfNotExists($conn, "quiz_rooms", "idx_quiz_rooms_host", "host_id");
createIndexIfNotExists($conn, "quiz_rooms", "idx_quiz_rooms_code", "room_code");
createIndexIfNotExists($conn, "quiz_rooms", "idx_quiz_rooms_status", "status");
createIndexIfNotExists($conn, "quiz_participants", "idx_quiz_participants_room", "room_id");
createIndexIfNotExists($conn, "quiz_responses", "idx_quiz_responses_participant", "participant_id");
createIndexIfNotExists($conn, "quiz_responses", "idx_quiz_responses_room", "participant_id, question_id");
createIndexIfNotExists($conn, "live_quiz_events", "idx_live_quiz_events_room_created", "room_id, created_at");

// Usage Tracking Indexes
createIndexIfNotExists($conn, "usage_tracking", "idx_usage_tracking_user_date", "user_id, created_at");
createIndexIfNotExists($conn, "usage_tracking", "idx_usage_tracking_action_date", "action, created_at");
createIndexIfNotExists($conn, "usage_tracking", "idx_usage_tracking_user_action_date", "user_id, action, created_at");

// User Generated Papers Log Indexes
createIndexIfNotExists($conn, "user_generated_papers_log", "idx_papers_log_user_date", "user_id, created_at");

// API Keys Indexes
createIndexIfNotExists($conn, "api_keys", "idx_api_keys_status", "status");
createIndexIfNotExists($conn, "api_keys", "idx_api_keys_provider", "provider");

// Uploaded Notes Indexes
createIndexIfNotExists($conn, "uploaded_notes", "idx_notes_chapter", "chapter_id");
createIndexIfNotExists($conn, "uploaded_notes", "idx_notes_class_book", "class_id, book_id");
createIndexIfNotExists($conn, "uploaded_notes", "idx_notes_deleted", "is_deleted");

// Users Indexes
createIndexIfNotExists($conn, "users", "idx_users_email", "email");
createIndexIfNotExists($conn, "users", "idx_users_created_at", "created_at");

// Admin Indexes
createIndexIfNotExists($conn, "admin_logs", "idx_admin_logs_admin_date", "admin_id, created_at");
createIndexIfNotExists($conn, "pending_admin_actions", "idx_pending_admin_token", "token");

echo "\n\n=== Performance Indexes Created ===\n";

echo "\nInstallation complete!\n";
echo "</pre>";
?>
