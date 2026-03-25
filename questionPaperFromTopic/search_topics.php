<?php
/**
 * AJAX endpoint to search topics.
 * Searches across multiple database tables for topics matching the search term.
 * Returns JSON array of unique matching topics.
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../db_connect.php';

// Input validation
$search = trim($_GET['search'] ?? '');
$types = $_GET['type'] ?? [];
if (!is_array($types)) {
    $types = $types ? [$types] : [];
}

if ($search === '' || strlen($search) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Search term must be at least 2 characters', 'topics' => []]);
    exit;
}

// Build LIKE search term
$term = '%' . $search . '%';
$topics = [];

// Ensure we have valid types
if (empty($types) || $types[0] === 'mcqs') {
    $types = ['mcqs'];
}

// SQL search for topics
if (in_array('mcqs', $types) || in_array('all', $types)) {
    $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE topic LIKE ? LIMIT 50");
    if ($stmt) {
        $stmt->bind_param('s', $term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $topics[] = $row['topic'];
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic LIKE ? LIMIT 50");
    if ($stmt) {
        $stmt->bind_param('s', $term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $topics[] = $row['topic'];
        }
        $stmt->close();
    }
}

$otherTypes = array_filter($types, function ($t) {
    return $t === 'short' || $t === 'long';
});
if (in_array('all', $types)) {
    $otherTypes = ['short', 'long'];
}

if (!empty($otherTypes)) {
    foreach ($otherTypes as $ot) {
        $stmt = $conn->prepare("SELECT DISTINCT topic FROM questions WHERE question_type = ? AND topic LIKE ? LIMIT 50");
        if ($stmt) {
            $stmt->bind_param('ss', $ot, $term);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $topics[] = $row['topic'];
            }
            $stmt->close();
        }

        if ($ot === 'short') {
            $stmt = $conn->prepare("
                SELECT DISTINCT t.topic_name 
                FROM AIGeneratedShortQuestions q
                JOIN AIQuestionsTopic t ON q.topic_id = t.id
                WHERE t.topic_name LIKE ? 
                LIMIT 50
            ");
            if ($stmt) {
                $stmt->bind_param('s', $term);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $topics[] = $row['topic_name'];
                }
                $stmt->close();
            }
        } elseif ($ot === 'long') {
            $stmt = $conn->prepare("
                SELECT DISTINCT t.topic_name 
                FROM AIGeneratedLongQuestions q
                JOIN AIQuestionsTopic t ON q.topic_id = t.id
                WHERE t.topic_name LIKE ? 
                LIMIT 50
            ");
            if ($stmt) {
                $stmt->bind_param('s', $term);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $topics[] = $row['topic_name'];
                }
                $stmt->close();
            }
        }
    }
}

$stmt = $conn->prepare("SELECT DISTINCT topic_name FROM AIQuestionsTopic WHERE topic_name LIKE ? LIMIT 50");
if ($stmt) {
    $stmt->bind_param('s', $term);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row['topic_name'];
    }
    $stmt->close();
}

    $stmt = $conn->prepare("SELECT DISTINCT topic_name FROM generated_topics WHERE topic_name LIKE ? OR source_term LIKE ? OR keywords LIKE ? LIMIT 100");
    if ($stmt) {
        $stmt->bind_param('sss', $term, $term, $term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $topics[] = $row['topic_name'];
        }
        $stmt->close();
    }

// Remove duplicates and return unique topics
$uniqueTopics = array_values(array_unique(array_map('trim', array_filter($topics))));

// Limit results
$uniqueTopics = array_slice($uniqueTopics, 0, 100);

echo json_encode([
    'success' => !empty($uniqueTopics),
    'topics' => $uniqueTopics,
    'count' => count($uniqueTopics),
]);
exit;
