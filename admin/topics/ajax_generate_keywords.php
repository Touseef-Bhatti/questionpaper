<?php
/**
 * admin/topics/ajax_generate_keywords.php - AI Handler for Keyword Generation using Google Gemini
 */
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/TopicAIService.php';

// Verify admin access
$admin = requireAdminRole('admin');

// Load environment variables
EnvLoader::load();

// Use the specific API key for keyword generation (NVIDIA Key)
$apiKey = EnvLoader::get('GENERATING_KEYWORDS_KEY');

if (!$apiKey) {
    die(json_encode([
        'success' => false, 
        'error' => 'NVIDIA API key "GENERATING_KEYWORDS_KEY" not found in .env.local or .env.production.'
    ]));
}

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
    
    // Use NVIDIA AI for keyword generation
    $prompt = "For the topic: \"$topicName\", return exactly 5 relevant keywords as a simple comma-separated string. Keywords should be short (1-3 words each). Return ONLY the comma-separated string. No other text.";
    
    list($respBody, $code) = TopicAIService::callAI($apiKey, $prompt);
    
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
