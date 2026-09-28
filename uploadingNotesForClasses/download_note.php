<?php
/**
 * Download proxy for class notes stored on Google Drive.
 * Validates that the note exists and is approved before redirecting to the Drive download URL.
 */
include '../db_connect.php';
require_once __DIR__ . '/../services/GoogleDriveService.php';

$driveStatusColumnCheck = $conn->query("SHOW COLUMNS FROM class_notes LIKE 'drive_status'");
if (!$driveStatusColumnCheck || $driveStatusColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE class_notes ADD COLUMN drive_status ENUM('available','missing') NOT NULL DEFAULT 'available' AFTER approved_by");
}
$driveDeletedAtColumnCheck = $conn->query("SHOW COLUMNS FROM class_notes LIKE 'drive_deleted_at'");
if (!$driveDeletedAtColumnCheck || $driveDeletedAtColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE class_notes ADD COLUMN drive_deleted_at DATETIME DEFAULT NULL AFTER drive_status");
}

$noteId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($noteId <= 0) {
    http_response_code(400);
    die('Invalid note ID.');
}

// Fetch note
$stmt = $conn->prepare("SELECT drive_file_id, drive_url, original_filename, mime_type, status, drive_status FROM class_notes WHERE id = ?");
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

try {
    if (($note['drive_status'] ?? 'available') === 'missing' || !(new GoogleDriveService())->isFileAvailable((string) $note['drive_file_id'])) {
        $markMissing = $conn->prepare("UPDATE class_notes SET drive_status = 'missing', drive_deleted_at = COALESCE(drive_deleted_at, NOW()) WHERE id = ?");
        if ($markMissing) {
            $markMissing->bind_param('i', $noteId);
            $markMissing->execute();
            $markMissing->close();
        }
        http_response_code(404);
        die('This note is no longer available in Google Drive.');
    }
} catch (Throwable $e) {
    error_log('Note download Drive availability check failed: ' . $e->getMessage());
    http_response_code(503);
    die('Google Drive is temporarily unavailable. Please try again.');
}

// Redirect to Google Drive direct download URL
$downloadUrl = "https://drive.google.com/uc?export=download&id=" . urlencode($note['drive_file_id']);
header("Location: $downloadUrl");
exit;
