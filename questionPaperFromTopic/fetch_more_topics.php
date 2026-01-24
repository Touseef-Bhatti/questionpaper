<?php
// fetch_more_topics.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/env.php';
// Include mcq_generator for the AI search function
require_once __DIR__ . '/../quiz/mcq_generator.php'; 

header('Content-Type: application/json');

$search = $_GET['search'] ?? '';
$types = $_GET['type'] ?? [];
if (!is_array($types)) {
    $types = $types ? [$types] : [];
}

if (!$search) {
    echo json_encode(['success' => false, 'message' => 'Search term required']);
    exit;
}

// 1. Ensure storage table exists
$conn->query("CREATE TABLE IF NOT EXISTS generated_topics (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    topic_name VARCHAR(255) UNIQUE, 
    source_term VARCHAR(255),
    question_types VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

try {
    // 2. Call AI
    // We pass the types context to get better results
    $typeStr = !empty($types) ? implode(', ', $types) : 'all';
    
    // Custom logic to enhance the search query for AI if needed
    // Actually searchTopicsWithGemini in mcq_generator.php already handles the AI call.
    // We can wrap it or modify it to accept types.
    
    $results = searchTopicsWithGemini($search, 0, 0, $types);
    
    $newTopics = [];
    foreach ($results as $res) {
        if (!empty($res['topic'])) {
            $topicName = trim($res['topic']);
            // Store in DB
            $stmt = $conn->prepare("INSERT IGNORE INTO generated_topics (topic_name, source_term, question_types) VALUES (?, ?, ?)");
            $typesJson = json_encode($types);
            $stmt->bind_param("sss", $topicName, $search, $typesJson);
            $stmt->execute();
            if ($stmt->affected_rows > 0 || $stmt->errno == 0) {
                $newTopics[] = $topicName;
            }
        }
    }
    
    $uniqueTopics = array_values(array_unique($newTopics));
    
    echo json_encode(['success' => true, 'topics' => $uniqueTopics]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
