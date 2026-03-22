<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../quiz/mcq_generator.php';

$stmt = $conn->prepare("SELECT id, topic_name FROM generated_topics WHERE keywords IS NULL OR keywords = ''");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $topic = $row['topic_name'];
    $id = $row['id'];

    $keywords = generateKeywordsForTopic($topic);

    if (!empty($keywords)) {
        $updateStmt = $conn->prepare("UPDATE generated_topics SET keywords = ? WHERE id = ?");
        $updateStmt->bind_param('si', $keywords, $id);
        $updateStmt->execute();
    }
}

function generateKeywordsForTopic($topic) {
    $prompt = "Generate 5-7 relevant keywords for the topic \"$topic\". Return them as a comma-separated list.";
    list($keyItem, $model, $rotator) = getAiKeyAndModel(null);
    if (!$keyItem) return '';

    list($resp, $code) = callOpenRouter($keyItem['key'], $model, $prompt, 100, 20);

    if ($code === 200 && $resp) {
        $rotator->logSuccess($keyItem['key']);
        return $resp;
    } else {
        $rotator->markExhausted($keyItem['key']);
        return '';
    }
}
?>
