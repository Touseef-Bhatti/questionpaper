<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Security token invalid.');
    }

    $title = trim($_POST['title']);
    $class_id = intval($_POST['class_id']);
    $book_id = intval($_POST['book_id']);
    $chapter_ids = isset($_POST['chapter_ids']) ? implode(',', $_POST['chapter_ids']) : '';
    $selection_type = $_POST['selection_type'];
    
    $mcq_count = intval($_POST['mcq_count'] ?? 0);
    $short_count = intval($_POST['short_count'] ?? 0);
    $long_count = intval($_POST['long_count'] ?? 0);
    $question_ids = isset($_POST['question_ids']) ? json_encode($_POST['question_ids']) : NULL;
    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE exam_preparations SET title = ?, class_id = ?, book_id = ?, chapter_ids = ?, mcq_count = ?, short_count = ?, long_count = ?, selection_type = ?, question_ids = ? WHERE id = ?");
        $stmt->bind_param("siisiiissi", $title, $class_id, $book_id, $chapter_ids, $mcq_count, $short_count, $long_count, $selection_type, $question_ids, $id);
        $success_msg = "updated";
    } else {
        $stmt = $conn->prepare("INSERT INTO exam_preparations (title, class_id, book_id, chapter_ids, mcq_count, short_count, long_count, selection_type, question_ids) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siisiiiss", $title, $class_id, $book_id, $chapter_ids, $mcq_count, $short_count, $long_count, $selection_type, $question_ids);
        $success_msg = "created";
    }

    if ($stmt->execute()) {
        $exam_id = $id ?: $conn->insert_id;
        if ($selection_type === 'manual' && !$question_ids) {
            header("Location: select_questions.php?id=$exam_id");
        } else {
            header("Location: index.php?msg=$success_msg");
        }
    } else {
        echo "Error: " . $conn->error;
    }
    $stmt->close();
}
?>
