<?php
/**
 * admin/topics/ajax_topic_actions.php - General topic actions (delete, fetch missing)
 */
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../db_connect.php';

// Verify admin access
$admin = requireAdminRole('admin');

$action = $_POST['action'] ?? '';

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) die(json_encode(['success' => false, 'error' => 'Invalid ID']));
    
    $stmt = $conn->prepare("DELETE FROM generated_topics WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
} elseif ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $topicName = trim($_POST['topic_name'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');
    
    if ($id <= 0 || empty($topicName)) {
        die(json_encode(['success' => false, 'error' => 'Invalid data']));
    }
    
    $stmt = $conn->prepare("UPDATE generated_topics SET topic_name = ?, keywords = ? WHERE id = ?");
    $stmt->bind_param("ssi", $topicName, $keywords, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
} elseif ($action === 'auto_generate') {
    require_once __DIR__ . '/../../config/env.php';
    require_once __DIR__ . '/../../services/AIKeyRotator.php';
    require_once __DIR__ . '/TopicAutoGenerator.php';
    
    EnvLoader::load();
    $rotator = new AIKeyRotator();
    $aiData = $rotator->getNextKey();
    
    if (!$aiData) {
        die(json_encode(['success' => false, 'error' => 'No active AI API keys found.']));
    }
    
    $apiKey = $aiData['key'];
    $model = $aiData['model'];
    
    $count = (int)($_POST['count'] ?? 10);
    $generator = new TopicAutoGenerator($conn, $apiKey, $model);
    $result = $generator->run($count);
    
    echo json_encode($result);
} elseif ($action === 'get_next_missing') {
    $count = (int)($_POST['count'] ?? 10);
    if ($count <= 0) $count = 10;
    if ($count > 50) $count = 50; // Safety cap
    
    $query = "SELECT id FROM generated_topics WHERE (keywords IS NULL OR keywords = '') ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $count);
    $stmt->execute();
    $ids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'ids' => array_column($ids, 'id')
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>
