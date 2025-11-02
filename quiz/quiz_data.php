<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');

include '../db_connect.php';

$type = $_GET['type'] ?? '';
$class_id = intval($_GET['class_id'] ?? 0);
$book_id = intval($_GET['book_id'] ?? 0);
$chapters = $_GET['chapters'] ?? '';

$result = [];

try {
    switch ($type) {
        case 'books':
            if ($class_id) {
                $stmt = $conn->prepare("SELECT book_id, book_name FROM book WHERE class_id = ? ORDER BY book_name");
                $stmt->bind_param('i', $class_id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $result[] = $row;
                }
                $stmt->close();
            }
            break;
            
        case 'chapters':
            if ($class_id && $book_id) {
                $stmt = $conn->prepare("SELECT chapter_id, chapter_name FROM chapter WHERE class_id = ? AND book_id = ? ORDER BY chapter_no ASC, chapter_id ASC");
                $stmt->bind_param('ii', $class_id, $book_id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $result[] = $row;
                }
                $stmt->close();
            }
            break;
            
        case 'topics':
            if ($class_id && $book_id) {
                // If specific chapters are selected, filter by those
                if (!empty($chapters)) {
                    $chapterIds = array_filter(array_map('intval', explode(',', $chapters)));
                    if (!empty($chapterIds)) {
                        $placeholders = str_repeat('?,', count($chapterIds) - 1) . '?';
                        $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE class_id = ? AND book_id = ? AND chapter_id IN ($placeholders) ORDER BY topic");
                        $types = 'ii' . str_repeat('i', count($chapterIds));
                        $params = array_merge([$class_id, $book_id], $chapterIds);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        while ($row = $res->fetch_assoc()) {
                            if (!empty($row['topic'])) {
                                $result[] = $row['topic'];
                            }
                        }
                        $stmt->close();
                    }
                } else {
                    // All topics for this class and book
                    $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE class_id = ? AND book_id = ? ORDER BY topic");
                    $stmt->bind_param('ii', $class_id, $book_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        if (!empty($row['topic'])) {
                            $result[] = $row['topic'];
                        }
                    }
                    $stmt->close();
                }
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

echo json_encode($result);
?>
