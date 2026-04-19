<?php
/**
 * admin/topics/ajax_generate_keywords.php - AI Handler for Keyword Generation using Google Gemini
 */
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../services/AIKeyRotator.php';
require_once __DIR__ . '/TopicAIService.php';

// Verify admin access
$admin = requireAdminRole('admin');

// Load environment variables
EnvLoader::load();

// Use the rotator for keyword generation
$rotator = new AIKeyRotator();
$aiData = $rotator->getNextKey();

if (!$aiData) {
    die(json_encode([
        'success' => false, 
        'error' => 'No active AI API keys found in config/.env.'
    ]));
}

$apiKey = $aiData['key'];
$model = $aiData['model'];

$idsJson = $_POST['ids'] ?? '[]';
$ids = json_decode($idsJson, true);

if (empty($ids)) {
    die(json_encode(['success' => false, 'error' => 'No topic IDs provided.']));
}

$log = "";

foreach ($ids as $id) {
    $id = (int)$id;
    
    // Fetch topic name
    $stmt = $conn->prepare("SELECT topic_name FROM generated_topics WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $topicData = $stmt->get_result()->fetch_assoc();
    
    if (!$topicData) {
        $log .= "<div><span class='text-danger'>ID $id not found.</span></div>";
        continue;
    }
    
    $topicName = $topicData['topic_name'];
    
    // Use AI for keyword generation (handles both NVIDIA and OpenRouter keys)
    $prompt = "For the topic: \"$topicName\", return exactly 5 relevant keywords as a simple comma-separated string. Keywords should be short (1-3 words each). Return ONLY the comma-separated string. No other text.";
    
    list($respBody, $code) = TopicAIService::callAI($apiKey, $prompt, $model);
    
    if ($code === 200 && !empty($respBody)) {
        // Clean up the response
        $keywords = trim($respBody, " \n\r\t\"'#*");
        $keywords = preg_replace('/^keywords:\s*/i', '', $keywords);
        
        // Update the keywords in the database
        $updateStmt = $conn->prepare("UPDATE generated_topics SET keywords = ? WHERE id = ?");
        $updateStmt->bind_param("si", $keywords, $id);
        
        if ($updateStmt->execute()) {
            $log .= "<div><span class='text-success'>✓</span> Generated for <strong>$topicName</strong>: <span class='badge bg-info text-white'>$keywords</span></div>";
        } else {
            $log .= "<div><span class='text-danger'>Update failed for $topicName: " . $conn->error . "</span></div>";
        }
        $updateStmt->close();
    } else {
        $log .= "<div><span class='text-danger'>AI API Error ($code): " . htmlspecialchars($respBody) . "</span></div>";
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'log' => $log
]);
?>
