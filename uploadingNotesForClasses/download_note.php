<?php
/**
 * Download proxy for class notes stored on Google Drive.
 * Validates that the note exists and is approved before redirecting to the Drive download URL.
 */
include '../db_connect.php';

$noteId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($noteId <= 0) {
    http_response_code(400);
    die('Invalid note ID.');
}

// Fetch note
$stmt = $conn->prepare("SELECT drive_file_id, drive_url, original_filename, mime_type, status FROM class_notes WHERE id = ?");
$stmt->bind_param('i', $noteId);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();
$stmt->close();

if (!$note) {
    http_response_code(404);
    die('Note not found.');
}

if ($note['status'] !== 'approved') {
    http_response_code(403);
    die('This note is not yet approved for download.');
}

// Redirect to Google Drive direct download URL
$downloadUrl = "https://drive.google.com/uc?export=download&id=" . urlencode($note['drive_file_id']);
header("Location: $downloadUrl");
exit;
