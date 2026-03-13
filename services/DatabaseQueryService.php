<?php
/**
 * Optimized Database Query Service
 * Centralized queries with caching and prepared statements
 * Production-ready performance optimizations
 */

class DatabaseQueryService
{
    private $conn;
    private $cache;
    const CACHE_TTL = 3600; // 1 hour
    
    public function __construct($connection, $cache = null)
    {
        $this->conn = $connection;
        $this->cache = $cache;
    }
    
    /**
     * Get classes with caching
     */
    public function getClasses()
    {
        $cacheKey = 'classes_all';
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return json_decode($cached, true);
        }
        
        $stmt = $this->conn->prepare("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $classes = [];
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        $stmt->close();
        
        if ($this->cache) {
            $this->cache->set($cacheKey, json_encode($classes), self::CACHE_TTL);
        }
        
        return $classes;
    }
    
    /**
     * Get books by class with caching
     */
    public function getBooks($classId)
    {
        $classId = intval($classId);
        $cacheKey = "books_class_{$classId}";
        
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return json_decode($cached, true);
        }
        
        $stmt = $this->conn->prepare("SELECT book_id, book_name FROM book WHERE class_id = ? ORDER BY book_id ASC");
        $stmt->bind_param('i', $classId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $books = [];
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
        $stmt->close();
        
        if ($this->cache) {
            $this->cache->set($cacheKey, json_encode($books), self::CACHE_TTL);
        }
        
        return $books;
    }
    
    /**
     * Get chapters by class and book with caching
     */
    public function getChapters($classId, $bookName = null, $bookId = null)
    {
        $classId = intval($classId);
        $cacheKey = "chapters_class_{$classId}";
        
        if ($bookName) {
            $bookName = trim($bookName);
            $cacheKey .= "_book_" . md5($bookName);
        } elseif ($bookId) {
            $bookId = intval($bookId);
            $cacheKey .= "_bookid_{$bookId}";
        }
        
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return json_decode($cached, true);
        }
        
        if ($bookName) {
            $stmt = $this->conn->prepare("SELECT chapter_id, chapter_name, chapter_no FROM chapter WHERE class_id = ? AND book_name = ? ORDER BY chapter_id ASC");
            $stmt->bind_param('is', $classId, $bookName);
        } elseif ($bookId) {
            $stmt = $this->conn->prepare("SELECT chapter_id, chapter_name, chapter_no FROM chapter WHERE class_id = ? AND book_id = ? ORDER BY chapter_id ASC");
            $stmt->bind_param('ii', $classId, $bookId);
        } else {
            $stmt = $this->conn->prepare("SELECT chapter_id, chapter_name, chapter_no FROM chapter WHERE class_id = ? ORDER BY chapter_id ASC");
            $stmt->bind_param('i', $classId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $chapters = [];
        while ($row = $result->fetch_assoc()) {
            $chapters[] = $row;
        }
        $stmt->close();
        
        if ($this->cache) {
            $this->cache->set($cacheKey, json_encode($chapters), self::CACHE_TTL);
        }
        
        return $chapters;
    }
    
    /**
     * Get question count by type and chapter (for validation)
     */
    public function getQuestionCount($chapterId, $questionType = null)
    {
        $chapterId = intval($chapterId);
        
        if ($questionType) {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM questions WHERE chapter_id = ? AND question_type = ?");
            $stmt->bind_param('is', $chapterId, $questionType);
        } else {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM questions WHERE chapter_id = ?");
            $stmt->bind_param('i', $chapterId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return intval($row['count'] ?? 0);
    }
    
    /**
     * Get class name with caching
     */
    public function getClassName($classId)
    {
        $classId = intval($classId);
        $cacheKey = "class_name_{$classId}";
        
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $stmt = $this->conn->prepare("SELECT class_name FROM class WHERE class_id = ? LIMIT 1");
        $stmt->bind_param('i', $classId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $className = $row['class_name'] ?? 'Unknown';
        
        if ($this->cache) {
            $this->cache->set($cacheKey, $className, self::CACHE_TTL);
        }
        
        return $className;
    }
    
    /**
     * Batch fetch questions from multiple chapters efficiently
     */
    public function getQuestionsByChapters($chapterIds, $questionType = null, $limit = null)
    {
        if (empty($chapterIds)) {
            return [];
        }
        
        // Convert to integers
        $chapterIds = array_map('intval', $chapterIds);
        $chapterIds = array_unique($chapterIds);
        
        $placeholders = str_repeat('?,', count($chapterIds) - 1) . '?';
        $sql = "SELECT id, chapter_id, question_text, marks, topic FROM questions WHERE chapter_id IN ($placeholders)";
        
        if ($questionType) {
            $sql .= " AND question_type = ?";
        }
        
        $sql .= " ORDER BY chapter_id, RAND() LIMIT " . ($limit ? intval($limit) : 1000);
        
        $stmt = $this->conn->prepare($sql);
        
        if ($questionType) {
            $types = str_repeat('i', count($chapterIds)) . 's';
            $params = array_merge($chapterIds, [$questionType]);
            $stmt->bind_param($types, ...$params);
        } else {
            $types = str_repeat('i', count($chapterIds));
            $stmt->bind_param($types, ...$chapterIds);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $questions = [];
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmt->close();
        
        return $questions;
    }
}
?>
