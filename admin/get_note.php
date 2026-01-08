<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

header('Content-Type: application/json');

$noteId = validateInt($_GET['note_id'] ?? 0);

if (!$noteId) {
    echo json_encode(['error' => 'Invalid note ID']);
    exit;
}

$stmt = $conn->prepare("SELECT note_id, title, description, class_id, book_id, chapter_id FROM uploaded_notes WHERE note_id = ?");
$stmt->bind_param('i', $noteId);
$stmt->execute();
$result = $stmt->get_result();

if ($note = $result->fetch_assoc()) {
    echo json_encode($note);
} else {
    echo json_encode(['error' => 'Note not found']);
}

$stmt->close();
?>
