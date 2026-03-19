<?php
/**
 * AJAX endpoint to search topics.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';

$search = trim($_GET['search'] ?? '');
$types = $_GET['type'] ?? [];
if (!is_array($types)) {
    $types = $types ? [$types] : [];
}

if ($search === '') {
    echo json_encode(['success' => false, 'message' => 'Search term required', 'topics' => []]);
    exit;
}

$topics = [];

// SQL search for topics

    if (in_array('mcqs', $types) || in_array('all', $types)) {
        $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE topic LIKE ? LIMIT 50");
        $stmt->bind_param('s', $term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $topics[] = $row['topic'];
        }

        $stmt = $conn->prepare("SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic LIKE ? LIMIT 50");
        $stmt->bind_param('s', $term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $topics[] = $row['topic'];
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
            $stmt->bind_param('ss', $ot, $term);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $topics[] = $row['topic'];
            }

            if ($ot === 'short') {
                $stmt = $conn->prepare("
                    SELECT DISTINCT t.topic_name 
                    FROM AIGeneratedShortQuestions q
                    JOIN AIQuestionsTopic t ON q.topic_id = t.id
                    WHERE t.topic_name LIKE ? 
                    LIMIT 50
                ");
                $stmt->bind_param('s', $term);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $topics[] = $row['topic_name'];
                }
            } elseif ($ot === 'long') {
                $stmt = $conn->prepare("
                    SELECT DISTINCT t.topic_name 
                    FROM AIGeneratedLongQuestions q
                    JOIN AIQuestionsTopic t ON q.topic_id = t.id
                    WHERE t.topic_name LIKE ? 
                    LIMIT 50
                ");
                $stmt->bind_param('s', $term);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $topics[] = $row['topic_name'];
                }
            }
        }
    }

    $stmt = $conn->prepare("SELECT DISTINCT topic_name FROM AIQuestionsTopic WHERE topic_name LIKE ? LIMIT 50");
    $stmt->bind_param('s', $term);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row['topic_name'];
    }

    $stmt = $conn->prepare("SELECT DISTINCT topic_name FROM generated_topics WHERE topic_name LIKE ? OR source_term LIKE ? OR keywords LIKE ? LIMIT 100");
    $stmt->bind_param('sss', $term, $term, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row['topic_name'];
    }

$topics = array_values(array_unique($topics));

echo json_encode([
    'success' => !empty($topics),
    'topics' => $topics,
    'count' => count($topics),
]);
