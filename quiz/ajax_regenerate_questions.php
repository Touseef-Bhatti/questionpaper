<?php
// ajax_regenerate_questions.php
// Generates new MCQs for the selected topics using AI
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
require_once '../db_connect.php';
require_once 'mcq_generator.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $topicsJson = $_POST['topics'] ?? '[]';
    $topics = json_decode($topicsJson, true);
    
    // Optional: Allow specifying how many to generate per topic
    $countPerTopic = intval($_POST['count'] ?? 3); // Default 3 new questions per topic to avoid timeout

    if (empty($topics)) {
        throw new Exception('No topics provided');
    }
    
    // Limit topics to prevent timeout (max 5 topics at a time)
    // If more, we'll just take the first 5 or random 5
    if (count($topics) > 5) {
        $topics = array_slice($topics, 0, 5);
    }

    $totalCount = count($topics) * $countPerTopic;
    $generated = [];
    $errors = [];
    try {
        $generated = generateMCQsBulkWithGemini($topics, $totalCount);
    } catch (Exception $e) {
        $errors[] = "Error generating: " . $e->getMessage();
    }
    $totalGenerated = count($generated);

    echo json_encode([
        'success' => true, 
        'message' => "Generated $totalGenerated new questions.",
        'count' => $totalGenerated,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
