<?php
// fetch_more_topics.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../quiz/mcq_generator.php';
require_once __DIR__ . '/../services/MeilisearchService.php';

header('Content-Type: application/json');

$search = $_GET['search'] ?? '';
$types = $_GET['type'] ?? [];
if (!is_array($types)) {
    $types = $types ? [$types] : [];
}
$exclude = $_GET['exclude'] ?? [];
if (!is_array($exclude)) {
    $exclude = $exclude ? [$exclude] : [];
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
    // 2. Call AI with forceRefresh = true to skip cache and get new results
    // Also pass $exclude to get DIFFERENT topics than those already shown
    $results = searchTopicsWithGemini($search, 0, 0, $types, true, $exclude);
    
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
                try {
                    $meili = new MeilisearchService();
                    $meili->addTopic($topicName, 'generated_topics', 'mcq');
                } catch (Throwable $e) {
                    /* ignore */
                }
            }
        }
    }
    
    $uniqueTopics = array_values(array_unique($newTopics));
    
    echo json_encode(['success' => true, 'topics' => $uniqueTopics]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
