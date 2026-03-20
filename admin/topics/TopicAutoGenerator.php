<?php
/**
 * admin/topics/TopicAutoGenerator.php - Core Logic for Auto-Generating Missing Keywords
 * Supports generating in batches of 10, 20, 30, 40, 50.
 */

class TopicAutoGenerator {
    private $conn;
    private $apiKey;
    
    public function __construct($conn, $apiKey) {
        $this->conn = $conn;
        $this->apiKey = $apiKey;
    }
    
    /**
     * Run generation for a specific number of missing topics
     * @param int $limit The number of topics to process (10, 20, 30, 40, 50)
     * @return array [success => bool, log => string, processed => int]
     */
    public function run($limit) {
        require_once __DIR__ . '/TopicAIService.php';
        
        $limit = min(50, max(1, (int)$limit));
        $log = "";
        $processed = 0;
        
        // 1. Fetch missing topics
        $query = "SELECT id, topic_name FROM generated_topics 
                  WHERE (keywords IS NULL OR keywords = '') 
                  ORDER BY created_at DESC 
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $topics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($topics)) {
            return [
                'success' => true,
                'log' => "<div><i class='fas fa-info-circle me-1'></i>No topics with missing keywords found.</div>",
                'processed' => 0
            ];
        }
        
        $log .= "<div><strong>Starting auto-generation for " . count($topics) . " topics...</strong></div>";
        
        // 2. Process each topic
        foreach ($topics as $topic) {
            $id = $topic['id'];
            $name = $topic['topic_name'];
            
            $prompt = "For the topic: \"$name\", return exactly 5 relevant keywords as a simple comma-separated string. Keywords should be short (1-3 words each). Return ONLY the comma-separated string. No other text.";
            
            list($respBody, $code) = TopicAIService::callAI($this->apiKey, $prompt);
            
            if ($code === 200 && !empty($respBody)) {
                $keywords = trim($respBody, " \n\r\t\"'#*");
                $keywords = preg_replace('/^keywords:\s*/i', '', $keywords);
                
                $updateStmt = $this->conn->prepare("UPDATE generated_topics SET keywords = ? WHERE id = ?");
                $updateStmt->bind_param("si", $keywords, $id);
                
                if ($updateStmt->execute()) {
                    $log .= "<div><span class='text-success'>✓</span> $name: <span class='badge bg-info text-white'>$keywords</span></div>";
                    $processed++;
                } else {
                    $log .= "<div><span class='text-danger'>✗</span> $name: Update failed (" . $this->conn->error . ")</div>";
                }
                $updateStmt->close();
            } else {
                $log .= "<div><span class='text-danger'>✗</span> $name: AI Error ($code)</div>";
            }
            
            // Pacing to avoid API issues
            usleep(200000); // 0.2 seconds
        }
        
        $log .= "<div class='mt-2'><strong>Auto-generation complete. Processed $processed topics.</strong></div>";
        
        return [
            'success' => true,
            'log' => $log,
            'processed' => $processed
        ];
    }
}
?>
