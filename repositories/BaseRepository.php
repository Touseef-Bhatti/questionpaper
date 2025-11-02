<?php
/**
 * Base Repository Interface
 * Provides common database operations with caching and optimization
 */

abstract class BaseRepository
{
    protected $conn;
    protected $cache;
    protected $table;
    
    public function __construct($connection, $cache = null)
    {
        $this->conn = $connection;
        $this->cache = $cache;
    }
    
    /**
     * Find by ID with caching
     */
    public function findById($id, $cacheTtl = 3600)
    {
        $cacheKey = $this->getCacheKey('id', $id);
        
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return json_decode($cached, true);
        }
        
        $query = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result && $this->cache) {
            $this->cache->setex($cacheKey, $cacheTtl, json_encode($result));
        }
        
        return $result ?: null;
    }
    
    /**
     * Find by multiple conditions
     */
    public function findWhere($conditions, $limit = null, $offset = null)
    {
        $whereClause = [];
        $params = [];
        $types = '';
        
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $placeholders = str_repeat('?,', count($value) - 1) . '?';
                $whereClause[] = "{$field} IN ({$placeholders})";
                $params = array_merge($params, $value);
                $types .= str_repeat('s', count($value));
            } else {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
        }
        
        $query = "SELECT * FROM {$this->table}";
        if (!empty($whereClause)) {
            $query .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
            if ($offset) {
                $query .= " OFFSET {$offset}";
            }
        }
        
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        return $rows;
    }
    
    /**
     * Create new record
     */
    public function create($data)
    {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $query = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ({$placeholders})";
        
        $stmt = $this->conn->prepare($query);
        $values = array_values($data);
        $types = str_repeat('s', count($values)); // Default to string, override in child classes if needed
        
        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        
        if ($success) {
            $id = $this->conn->insert_id;
            $this->invalidateCache();
            return $id;
        }
        
        return false;
    }
    
    /**
     * Update record by ID
     */
    public function update($id, $data)
    {
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        
        $query = "UPDATE {$this->table} SET {$setClause} WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $values = array_values($data);
        $values[] = $id;
        $types = str_repeat('s', count($values) - 1) . 'i'; // Last parameter is ID (integer)
        
        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        
        if ($success) {
            $this->invalidateCache($id);
        }
        
        return $success;
    }
    
    /**
     * Delete record by ID
     */
    public function delete($id)
    {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        
        if ($success) {
            $this->invalidateCache($id);
        }
        
        return $success;
    }
    
    /**
     * Count records with conditions
     */
    public function count($conditions = [])
    {
        $cacheKey = $this->getCacheKey('count', serialize($conditions));
        
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return (int)$cached;
        }
        
        $whereClause = [];
        $params = [];
        $types = '';
        
        foreach ($conditions as $field => $value) {
            $whereClause[] = "{$field} = ?";
            $params[] = $value;
            $types .= is_int($value) ? 'i' : 's';
        }
        
        $query = "SELECT COUNT(*) as count FROM {$this->table}";
        if (!empty($whereClause)) {
            $query .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $count = (int)$result['count'];
        
        if ($this->cache) {
            $this->cache->setex($cacheKey, 600, $count); // Cache for 10 minutes
        }
        
        return $count;
    }
    
    /**
     * Execute custom query with caching
     */
    protected function queryWithCache($query, $params = [], $cacheKey = null, $cacheTtl = 3600)
    {
        if ($cacheKey && $this->cache && $cached = $this->cache->get($cacheKey)) {
            return json_decode($cached, true);
        }
        
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                $types .= is_int($param) ? 'i' : (is_float($param) ? 'd' : 's');
            }
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        if ($cacheKey && $this->cache) {
            $this->cache->setex($cacheKey, $cacheTtl, json_encode($rows));
        }
        
        return $rows;
    }
    
    /**
     * Get cache key for this repository
     */
    protected function getCacheKey($type, $identifier = '')
    {
        return "{$this->table}_{$type}_{$identifier}";
    }
    
    /**
     * Invalidate cache for this repository
     */
    protected function invalidateCache($id = null)
    {
        if (!$this->cache) return;
        
        // Invalidate specific record cache
        if ($id) {
            $this->cache->del($this->getCacheKey('id', $id));
        }
        
        // Invalidate count cache
        $keys = $this->cache->keys($this->getCacheKey('count', '*'));
        if ($keys) {
            $this->cache->del($keys);
        }
        
        // Invalidate list caches
        $keys = $this->cache->keys($this->getCacheKey('list', '*'));
        if ($keys) {
            $this->cache->del($keys);
        }
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        $this->conn->autocommit(false);
    }
    
    /**
     * Commit transaction
     */
    public function commit()
    {
        $this->conn->commit();
        $this->conn->autocommit(true);
    }
    
    /**
     * Rollback transaction
     */
    public function rollback()
    {
        $this->conn->rollback();
        $this->conn->autocommit(true);
    }
}

/**
 * Question Repository
 * Handles all question-related database operations
 */
class QuestionRepository extends BaseRepository
{
    protected $table = 'questions';
    
    public function getByChapterAndType($chapterId, $questionType, $limit = null)
    {
        $cacheKey = $this->getCacheKey('chapter_type', "{$chapterId}_{$questionType}_{$limit}");
        
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return json_decode($cached, true);
        }
        
        $query = "SELECT id, question_text, marks, topic 
                 FROM questions 
                 WHERE chapter_id = ? AND question_type = ?
                 ORDER BY id";
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
        }
        
        $result = $this->queryWithCache($query, [$chapterId, $questionType], $cacheKey, 1800);
        return $result;
    }
    
    public function searchQuestions($searchTerm, $filters = [])
    {
        $cacheKey = $this->getCacheKey('search', md5($searchTerm . serialize($filters)));
        
        $where = ["1=1"];
        $params = [];
        
        if (!empty($searchTerm)) {
            $where[] = "(question_text LIKE ? OR topic LIKE ?)";
            $params[] = "%{$searchTerm}%";
            $params[] = "%{$searchTerm}%";
        }
        
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $where[] = "{$field} = ?";
                $params[] = $value;
            }
        }
        
        $whereClause = implode(" AND ", $where);
        $query = "SELECT id, question_text, marks, topic, chapter_id, question_type 
                 FROM questions 
                 WHERE {$whereClause}
                 ORDER BY id 
                 LIMIT 100";
        
        return $this->queryWithCache($query, $params, $cacheKey, 600);
    }
    
    public function getQuestionStats($chapterId = null)
    {
        $cacheKey = $this->getCacheKey('stats', $chapterId ?? 'all');
        
        $where = $chapterId ? "WHERE chapter_id = ?" : "";
        $params = $chapterId ? [$chapterId] : [];
        
        $query = "SELECT 
                    chapter_id,
                    question_type,
                    COUNT(*) as count,
                    AVG(marks) as avg_marks,
                    MIN(id) as min_id,
                    MAX(id) as max_id
                 FROM questions 
                 {$where}
                 GROUP BY chapter_id, question_type
                 ORDER BY chapter_id, question_type";
        
        return $this->queryWithCache($query, $params, $cacheKey, 3600);
    }
}

/**
 * MCQ Repository
 */
class MCQRepository extends BaseRepository
{
    protected $table = 'mcqs';
    
    public function getByChapter($chapterId, $limit = null)
    {
        $cacheKey = $this->getCacheKey('chapter', "{$chapterId}_{$limit}");
        
        $query = "SELECT mcq_id, chapter_id, question, option_a, option_b, option_c, option_d, correct_option 
                 FROM mcqs 
                 WHERE chapter_id = ?
                 ORDER BY mcq_id";
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
        }
        
        return $this->queryWithCache($query, [$chapterId], $cacheKey, 1800);
    }
    
    public function getByClassAndBook($classId, $bookId, $limit = null)
    {
        $cacheKey = $this->getCacheKey('class_book', "{$classId}_{$bookId}_{$limit}");
        
        $query = "SELECT mcq_id, chapter_id, question, option_a, option_b, option_c, option_d, correct_option 
                 FROM mcqs 
                 WHERE class_id = ? AND book_id = ?
                 ORDER BY mcq_id";
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
        }
        
        return $this->queryWithCache($query, [$classId, $bookId], $cacheKey, 1800);
    }
}

/**
 * User Repository
 */
class UserRepository extends BaseRepository
{
    protected $table = 'users';
    
    public function findByEmail($email)
    {
        $cacheKey = $this->getCacheKey('email', md5($email));
        
        $query = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $result = $this->queryWithCache($query, [$email], $cacheKey, 3600);
        
        return !empty($result) ? $result[0] : null;
    }
    
    public function findByGoogleId($googleId)
    {
        $cacheKey = $this->getCacheKey('google', $googleId);
        
        $query = "SELECT * FROM users WHERE google_id = ? LIMIT 1";
        $result = $this->queryWithCache($query, [$googleId], $cacheKey, 3600);
        
        return !empty($result) ? $result[0] : null;
    }
    
    public function updateLastLogin($userId)
    {
        $query = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $success = $stmt->execute();
        
        if ($success) {
            $this->invalidateCache($userId);
        }
        
        return $success;
    }
}

/**
 * Chapter Repository
 */
class ChapterRepository extends BaseRepository
{
    protected $table = 'chapter';
    
    public function getByClassAndBook($classId, $bookName = null)
    {
        $cacheKey = $this->getCacheKey('class_book', "{$classId}_{$bookName}");
        
        $where = ["class_id = ?"];
        $params = [$classId];
        
        if ($bookName) {
            $where[] = "book_name = ?";
            $params[] = $bookName;
        }
        
        $whereClause = implode(' AND ', $where);
        $query = "SELECT chapter_id, chapter_name, chapter_no, class_id, book_name 
                 FROM chapter 
                 WHERE {$whereClause}
                 ORDER BY chapter_no ASC, chapter_id ASC";
        
        return $this->queryWithCache($query, $params, $cacheKey, 3600);
    }
}
?>
