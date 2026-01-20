<?php
// fetch_more_topics.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/env.php';
// Include mcq_generator for the AI search function
// We need to ensure we don't trigger side effects, but mcq_generator.php strictly defines functions.
require_once __DIR__ . '/../quiz/mcq_generator.php'; 

header('Content-Type: application/json');

$search = $_GET['search'] ?? '';

if (!$search) {
    echo json_encode(['success' => false, 'message' => 'Search term required']);
    exit;
}

// 1. Ensure storage table exists
$conn->query("CREATE TABLE IF NOT EXISTS generated_topics (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    topic_name VARCHAR(255) UNIQUE, 
    source_term VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// 2. Scan DB for existing matching topics to avoid AI cost if we have many
// (Optional, but user specifically asked to use API to load more)
// We will call AI.

try {
    // 3. Call AI
    // searchTopicsWithGemini returns: [['topic' => 'Name', ...], ...]
    $results = searchTopicsWithGemini($search);
    
    $newTopics = [];
    foreach ($results as $res) {
        if (!empty($res['topic'])) {
            $topicName = trim($res['topic']);
            // Store in DB
            $stmt = $conn->prepare("INSERT IGNORE INTO generated_topics (topic_name, source_term) VALUES (?, ?)");
            $stmt->bind_param("ss", $topicName, $search);
            $stmt->execute();
            if ($stmt->affected_rows > 0 || $stmt->errno == 0) {
                // If inserted or already exists, we consider it a valid topic to return
                $newTopics[] = $topicName;
            }
        }
    }
    
    // Also fetch any previously generated topics for this search term that we might have missed in the AI response (cache effect)
    // or just return the AI results.
    // Let's purely return the unique names.
    $uniqueTopics = array_values(array_unique($newTopics));
    
    echo json_encode(['success' => true, 'topics' => $uniqueTopics]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
