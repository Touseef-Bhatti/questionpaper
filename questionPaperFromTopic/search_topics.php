<?php
/**
 * AJAX endpoint to search topics from database
 * Searches MCQs table first, then AIGeneratedMCQs if no results
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';

$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';

if (!$search) {
    echo json_encode(['success' => false, 'message' => 'Search term required', 'topics' => []]);
    exit;
}

$topics = [];
$term = "%$search%";

if ($type === 'mcqs') {
    // Step 1: Search main MCQs table
    $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE topic LIKE ? LIMIT 50");
    $stmt->bind_param('s', $term);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row['topic'];
    }

    // Step 2: If no results, search AIGeneratedMCQs table
    if (empty($topics)) {
        $stmt = $conn->prepare("SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic LIKE ? LIMIT 50");
        $stmt->bind_param('s', $term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $topics[] = $row['topic'];
        }
    }
} else {
    // Search Short or Long questions
    $stmt = $conn->prepare("SELECT DISTINCT topic FROM questions WHERE question_type = ? AND topic LIKE ? LIMIT 50");
    $stmt->bind_param('ss', $type, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row['topic'];
    }
}

// Remove duplicates
$topics = array_values(array_unique($topics));

echo json_encode([
    'success' => !empty($topics),
    'topics' => $topics,
    'count' => count($topics)
]);
