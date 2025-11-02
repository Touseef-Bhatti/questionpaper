-- QPaperGen Database Performance Optimization
-- This script implements critical performance improvements
-- Run this script to optimize database performance

-- ==========================================
-- CRITICAL PERFORMANCE INDEXES
-- ==========================================

-- Questions table optimization
ALTER TABLE questions ADD INDEX IF NOT EXISTS idx_chapter_type (chapter_id, question_type);
ALTER TABLE questions ADD INDEX IF NOT EXISTS idx_chapter_id_performance (chapter_id, id);
ALTER TABLE questions ADD INDEX IF NOT EXISTS idx_question_type (question_type);
ALTER TABLE questions ADD INDEX IF NOT EXISTS idx_book_chapter (book_name, chapter_id);

-- MCQs table optimization  
ALTER TABLE mcqs ADD INDEX IF NOT EXISTS idx_chapter_random (chapter_id, mcq_id);
ALTER TABLE mcqs ADD INDEX IF NOT EXISTS idx_class_book_chapter (class_id, book_id, chapter_id);
ALTER TABLE mcqs ADD INDEX IF NOT EXISTS idx_topic (topic);

-- Chapter table optimization
ALTER TABLE chapter ADD INDEX IF NOT EXISTS idx_class_book (class_id, book_name);
ALTER TABLE chapter ADD INDEX IF NOT EXISTS idx_chapter_no (chapter_no);

-- Book table optimization
ALTER TABLE book ADD INDEX IF NOT EXISTS idx_class_book (class_id, book_name);

-- Payment system optimization
ALTER TABLE payments ADD INDEX IF NOT EXISTS idx_user_status (user_id, status);
ALTER TABLE payments ADD INDEX IF NOT EXISTS idx_status_created (status, created_at);
ALTER TABLE payments ADD INDEX IF NOT EXISTS idx_order_lookup (order_id, status);

-- User subscriptions optimization
ALTER TABLE user_subscriptions ADD INDEX IF NOT EXISTS idx_user_active (user_id, status, expires_at);
ALTER TABLE user_subscriptions ADD INDEX IF NOT EXISTS idx_expires_at (expires_at);
ALTER TABLE user_subscriptions ADD INDEX IF NOT EXISTS idx_status_active (status, expires_at);

-- User table optimization
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_email_verified (email, verified);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_subscription (subscription_status, subscription_expires_at);

-- Contact messages optimization
ALTER TABLE contact_messages ADD INDEX IF NOT EXISTS idx_status_created (status, created_at);

-- Quiz system optimization
ALTER TABLE quiz_rooms ADD INDEX IF NOT EXISTS idx_teacher_created (teacher_id, created_at);
ALTER TABLE quiz_participants ADD INDEX IF NOT EXISTS idx_room_started (room_id, started_at);
ALTER TABLE quiz_room_questions ADD INDEX IF NOT EXISTS idx_room_order (room_id, id);

-- ==========================================
-- PERFORMANCE MONITORING VIEWS
-- ==========================================

-- Create view for question statistics
CREATE OR REPLACE VIEW question_stats AS
SELECT 
    chapter_id,
    question_type,
    COUNT(*) as question_count,
    AVG(marks) as avg_marks
FROM questions 
GROUP BY chapter_id, question_type;

-- Create view for performance monitoring
CREATE OR REPLACE VIEW performance_stats AS
SELECT 
    'questions' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT chapter_id) as unique_chapters,
    COUNT(DISTINCT question_type) as unique_types
FROM questions
UNION ALL
SELECT 
    'mcqs' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT chapter_id) as unique_chapters,
    COUNT(DISTINCT class_id) as unique_classes
FROM mcqs
UNION ALL
SELECT 
    'users' as table_name,
    COUNT(*) as total_records,
    COUNT(CASE WHEN verified = 1 THEN 1 END) as verified_users,
    COUNT(CASE WHEN subscription_status != 'free' THEN 1 END) as premium_users
FROM users;

-- ==========================================
-- OPTIMIZE TABLE STORAGE
-- ==========================================

-- Optimize table storage and analyze for better performance
OPTIMIZE TABLE questions;
OPTIMIZE TABLE mcqs;
OPTIMIZE TABLE chapter;
OPTIMIZE TABLE book;
OPTIMIZE TABLE users;
OPTIMIZE TABLE payments;
OPTIMIZE TABLE user_subscriptions;

-- Analyze tables for index optimization
ANALYZE TABLE questions;
ANALYZE TABLE mcqs;
ANALYZE TABLE chapter;
ANALYZE TABLE book;
ANALYZE TABLE users;
ANALYZE TABLE payments;
ANALYZE TABLE user_subscriptions;

-- ==========================================
-- CONFIGURATION OPTIMIZATIONS
-- ==========================================

-- Enable query cache if available
-- SET GLOBAL query_cache_size = 1048576 * 64; -- 64MB
-- SET GLOBAL query_cache_type = ON;

-- Set better InnoDB settings
-- SET GLOBAL innodb_buffer_pool_size = 1073741824; -- 1GB
-- SET GLOBAL innodb_log_file_size = 268435456; -- 256MB

-- ==========================================
-- PERFORMANCE TESTING FUNCTIONS
-- ==========================================

-- Function to test random question selection performance
DELIMITER $$
CREATE OR REPLACE FUNCTION test_question_performance(chapter_id INT, question_type VARCHAR(20), limit_count INT)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE start_time BIGINT;
    DECLARE end_time BIGINT;
    DECLARE result JSON;
    
    SET start_time = UNIX_TIMESTAMP(NOW(6)) * 1000000 + MICROSECOND(NOW(6));
    
    -- Optimized random selection query
    SELECT COUNT(*) FROM questions 
    WHERE chapter_id = chapter_id 
    AND question_type = question_type 
    ORDER BY RAND() 
    LIMIT limit_count;
    
    SET end_time = UNIX_TIMESTAMP(NOW(6)) * 1000000 + MICROSECOND(NOW(6));
    
    SET result = JSON_OBJECT(
        'execution_time_microseconds', end_time - start_time,
        'chapter_id', chapter_id,
        'question_type', question_type,
        'limit_count', limit_count
    );
    
    RETURN result;
END$$
DELIMITER ;

-- ==========================================
-- COMPLETION MESSAGE
-- ==========================================
SELECT 'Database optimization completed successfully!' as status,
       NOW() as completed_at,
       'Run SHOW INDEX FROM [table_name] to verify indexes were created' as verification;
