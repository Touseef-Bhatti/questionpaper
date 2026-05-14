<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';

// Check if admin is authenticated
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'super_admin')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$chapter_id = intval($_GET['chapter_id'] ?? 0);
$book_id = intval($_GET['book_id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);

if (!$chapter_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid Chapter ID']);
    exit;
}

$response = [
    'mcqs' => [],
    'short' => [],
    'long' => []
];

// Fetch MCQs
$mcq_stmt = $conn->prepare("SELECT mcq_id, question FROM mcqs WHERE chapter_id = ? AND book_id = ? AND class_id = ?");
$mcq_stmt->bind_param("iii", $chapter_id, $book_id, $class_id);
$mcq_stmt->execute();
$mcq_res = $mcq_stmt->get_result();
while ($row = $mcq_res->fetch_assoc()) {
    $response['mcqs'][] = [
        'id' => 'mcq_' . $row['mcq_id'],
        'text' => $row['question']
    ];
}
$mcq_stmt->close();

// Fetch Short and Long Questions
$q_stmt = $conn->prepare("SELECT id, question_text, question_type FROM questions WHERE chapter_id = ? AND book_id = ? AND class_id = ?");
$q_stmt->bind_param("iii", $chapter_id, $book_id, $class_id);
$q_stmt->execute();
$q_res = $q_stmt->get_result();
while ($row = $q_res->fetch_assoc()) {
    $type = ($row['question_type'] === 'long') ? 'long' : 'short';
    $response[$type][] = [
        'id' => 'q_' . $row['id'],
        'text' => $row['question_text']
    ];
}
$q_stmt->close();

header('Content-Type: application/json');
echo json_encode($response);
exit;
