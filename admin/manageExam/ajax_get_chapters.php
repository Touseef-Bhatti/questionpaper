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

$class_id = intval($_GET['class_id'] ?? 0);
$book_id = intval($_GET['book_id'] ?? 0);

if (!$class_id || !$book_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid Class or Book ID']);
    exit;
}

// First, get the book name for this book_id to ensure we match correctly
$book_stmt = $conn->prepare("SELECT book_name FROM book WHERE book_id = ? AND class_id = ?");
$book_stmt->bind_param("ii", $book_id, $class_id);
$book_stmt->execute();
$book_res = $book_stmt->get_result();
$book = $book_res->fetch_assoc();
$book_stmt->close();

if (!$book) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Book not found']);
    exit;
}

$book_name = $book['book_name'];

// Fetch chapters. We filter by book_id OR (class_id AND book_name) to be robust
$stmt = $conn->prepare("SELECT chapter_id, chapter_name, chapter_no FROM chapter WHERE book_id = ? OR (class_id = ? AND book_name = ?) ORDER BY chapter_no ASC, chapter_name ASC");
$stmt->bind_param("iis", $book_id, $class_id, $book_name);
$stmt->execute();
$result = $stmt->get_result();

$chapters = [];
while ($row = $result->fetch_assoc()) {
    $chapters[] = $row;
}
$stmt->close();

header('Content-Type: application/json');
echo json_encode($chapters);
exit;
