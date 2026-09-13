<?php
/**
 * Admin API handler for class notes management.
 * Handles approval, rejection, deletion, admin upload, and dynamic class/book/chapter dropdown data.
 */
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../../services/GoogleDriveService.php';

requireAdminAuth();

$emailColumnCheck = $conn->query("SHOW COLUMNS FROM class_notes LIKE 'uploader_email'");
if (!$emailColumnCheck || $emailColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE class_notes ADD COLUMN uploader_email VARCHAR(255) DEFAULT NULL AFTER uploader_name");
}

$action = $_REQUEST['action'] ?? '';

// GET/POST endpoints for dynamic dropdowns (Class -> Books -> Chapters)
if ($action === 'get_books') {
    header('Content-Type: application/json');
    $classId = intval($_GET['class_id'] ?? 0);
    if ($classId <= 0) {
        echo json_encode([]);
        exit;
    }
    $stmt = $conn->prepare("SELECT book_id, book_name, class_id FROM book WHERE class_id = ? ORDER BY book_name ASC");
    $stmt->bind_param("i", $classId);
    $stmt->execute();
    $result = $stmt->get_result();
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = [
            'book_id' => $row['book_id'],
            'book_name' => $row['book_name']
        ];
    }
    $stmt->close();
    echo json_encode($books);
    exit;
}

if ($action === 'get_chapters') {
    header('Content-Type: application/json');
    $classId = intval($_GET['class_id'] ?? 0);
    $bookName = trim($_GET['book_name'] ?? '');
    $bookId = intval($_GET['book_id'] ?? 0);

    if ($classId <= 0 || ($bookName === '' && $bookId <= 0)) {
        echo json_encode([]);
        exit;
    }

    if ($bookId > 0 && $bookName === '') {
        $bStmt = $conn->prepare("SELECT book_name FROM book WHERE book_id = ?");
        $bStmt->bind_param("i", $bookId);
        $bStmt->execute();
        $bRes = $bStmt->get_result();
        if ($bRow = $bRes->fetch_assoc()) {
            $bookName = $bRow['book_name'];
        }
        $bStmt->close();
    }

    $stmt = $conn->prepare("SELECT chapter_id, chapter_name, chapter_no, book_name 
                            FROM chapter 
                            WHERE class_id = ? AND (book_name = ? OR (book_id IS NOT NULL AND book_id = ?))
                            ORDER BY chapter_no ASC, chapter_name ASC");
    $stmt->bind_param("isi", $classId, $bookName, $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    $chapters = [];
    while ($row = $result->fetch_assoc()) {
        $display = $row['chapter_no'] ? "Chapter {$row['chapter_no']}: {$row['chapter_name']}" : $row['chapter_name'];
        $chapters[] = [
            'chapter_id' => $row['chapter_id'],
            'chapter_no' => $row['chapter_no'],
            'chapter_name' => $row['chapter_name'],
            'display_name' => $display
        ];
    }
    $stmt->close();
    echo json_encode($chapters);
    exit;
}

// CSRF validation for mutating actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        if ($action === 'admin_upload') {
            $_SESSION['cn_error'] = 'Invalid CSRF token. Please reload and try again.';
            header('Location: index.php');
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

// Admin Upload
if ($action === 'admin_upload') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $customSubject = trim($_POST['custom_subject'] ?? '');
    if ($subject === 'Other' && !empty($customSubject)) {
        $subject = $customSubject;
    }

    $chapter = trim($_POST['chapter'] ?? '');
    $customChapter = trim($_POST['custom_chapter'] ?? '');
    if ($chapter === '__custom__' && !empty($customChapter)) {
        $chapter = $customChapter;
    }

    $adminId = $_SESSION['admin_id'] ?? ($_SESSION['user_id'] ?? null);
    $adminName = $_SESSION['name'] ?? 'Admin';
    $adminEmail = $_SESSION['email'] ?? '';
    if (!empty($adminId)) {
        $adminLookup = $conn->prepare("SELECT name, email FROM admins WHERE id = ? LIMIT 1");
        $adminLookup->bind_param('i', $adminId);
        $adminLookup->execute();
        $adminRow = $adminLookup->get_result()->fetch_assoc();
        $adminLookup->close();
        if ($adminRow) {
            $adminName = $adminRow['name'] ?: $adminName;
            $adminEmail = $adminRow['email'] ?: $adminEmail;
        } else {
            $userLookup = $conn->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
            $userLookup->bind_param('i', $adminId);
            $userLookup->execute();
            $userRow = $userLookup->get_result()->fetch_assoc();
            $userLookup->close();
            if ($userRow) {
                $adminName = $userRow['name'] ?: $adminName;
                $adminEmail = $userRow['email'] ?: $adminEmail;
            }
        }
    }

    if (empty($title) || empty($class) || empty($subject)) {
        $_SESSION['cn_error'] = 'Title, Class, and Subject are required.';
        header('Location: index.php');
        exit;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['cn_error'] = 'Please choose a valid file to upload.';
        header('Location: index.php');
        exit;
    }

    $file = $_FILES['file'];
    $fileSize = $file['size'];
    $fileTmpPath = $file['tmp_name'];
    $originalFileName = basename($file['name']);
    $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

    $allowedExtensions = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        $_SESSION['cn_error'] = 'File type not allowed. Supported: ' . implode(', ', $allowedExtensions);
        header('Location: index.php');
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmpPath);
    finfo_close($finfo);

    try {
        $driveService = new GoogleDriveService();
        $driveResult = $driveService->uploadFile($fileTmpPath, $originalFileName, $mimeType, $class, $subject);

        $stmt = $conn->prepare("INSERT INTO class_notes 
            (title, description, subject, class, chapter, drive_file_id, drive_url, original_filename, mime_type, file_size, status, uploaded_by, uploader_name, uploader_email, uploader_type, approved_at, approved_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, 'admin', NOW(), ?)");

        $stmt->bind_param('sssssssssiissi',
            $title, $description, $subject, $class, $chapter,
            $driveResult['file_id'], $driveResult['url'],
            $originalFileName, $mimeType, $fileSize,
            $adminId, $adminName, $adminEmail, $adminId
        );

        if ($stmt->execute()) {
            $_SESSION['cn_message'] = "Note '{$title}' uploaded and automatically approved!";
        } else {
            $_SESSION['cn_error'] = 'Failed to save note in database: ' . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['cn_error'] = 'Upload failed: ' . $e->getMessage();
    } finally {
        if (file_exists($fileTmpPath)) {
            @unlink($fileTmpPath);
        }
    }

    header('Location: index.php?status=approved');
    exit;
}

// JSON API responses for approve, reject, delete
header('Content-Type: application/json');

$adminId = $_SESSION['admin_id'] ?? ($_SESSION['user_id'] ?? null);

switch ($action) {
    case 'test_drive':
        try {
            $drive = new GoogleDriveService();
            $diag = $drive->diagnoseConnection();
            echo json_encode($diag);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ]);
        }
        exit;

    case 'get_note':
        $noteId = intval($_GET['note_id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM class_notes WHERE id = ?");
        $stmt->bind_param("i", $noteId);
        $stmt->execute();
        $note = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode(['success' => (bool)$note, 'note' => $note]);
        exit;

    case 'edit_note':
        $noteId = intval($_POST['note_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $class = trim($_POST['class'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $chapter = trim($_POST['chapter'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if ($subject === 'Other' && !empty($_POST['custom_subject'])) {
            $subject = trim($_POST['custom_subject']);
        }
        if ($chapter === '__custom__' && !empty($_POST['custom_chapter'])) {
            $chapter = trim($_POST['custom_chapter']);
        }

        if ($noteId <= 0 || empty($title) || empty($class) || empty($subject)) {
            echo json_encode(['success' => false, 'error' => 'Title, Class, and Subject are required']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE class_notes SET title = ?, class = ?, subject = ?, chapter = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $title, $class, $subject, $chapter, $desc, $noteId);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
        exit;

    case 'approve':
        $noteId = intval($_POST['note_id'] ?? 0);
        if ($noteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid note ID']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE class_notes SET status = 'approved', approved_at = NOW(), approved_by = ?, rejection_reason = NULL WHERE id = ?");
        $stmt->bind_param("ii", $adminId, $noteId);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
        exit;

    case 'reject':
        $noteId = intval($_POST['note_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if ($noteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid note ID']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE class_notes SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmt->bind_param("si", $reason, $noteId);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
        exit;

    case 'delete':
        $noteId = intval($_POST['note_id'] ?? 0);
        if ($noteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid note ID']);
            exit;
        }
        // Retrieve drive file id to remove from Google Drive
        $stmt = $conn->prepare("SELECT drive_file_id FROM class_notes WHERE id = ?");
        $stmt->bind_param("i", $noteId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res && !empty($res['drive_file_id'])) {
            try {
                $driveService = new GoogleDriveService();
                $driveService->deleteFile($res['drive_file_id']);
            } catch (Exception $e) {
                error_log("Failed to delete from Drive: " . $e->getMessage());
            }
        }

        $delStmt = $conn->prepare("DELETE FROM class_notes WHERE id = ?");
        $delStmt->bind_param("i", $noteId);
        $success = $delStmt->execute();
        $delStmt->close();

        echo json_encode(['success' => $success]);
        exit;

    case 'bulk_approve':
        $rawIds = json_decode($_POST['note_ids'] ?? '[]', true);
        $ids = array_filter(array_map('intval', $rawIds));
        if (empty($ids)) {
            echo json_encode(['success' => false, 'error' => 'No notes selected']);
            exit;
        }
        $inClause = implode(',', $ids);
        $conn->query("UPDATE class_notes SET status = 'approved', approved_at = NOW(), approved_by = " . intval($adminId) . ", rejection_reason = NULL WHERE id IN ($inClause)");
        echo json_encode(['success' => true, 'count' => count($ids)]);
        exit;

    case 'bulk_reject':
        $rawIds = json_decode($_POST['note_ids'] ?? '[]', true);
        $ids = array_filter(array_map('intval', $rawIds));
        if (empty($ids)) {
            echo json_encode(['success' => false, 'error' => 'No notes selected']);
            exit;
        }
        $inClause = implode(',', $ids);
        $conn->query("UPDATE class_notes SET status = 'rejected' WHERE id IN ($inClause)");
        echo json_encode(['success' => true, 'count' => count($ids)]);
        exit;

    case 'bulk_delete':
        $rawIds = json_decode($_POST['note_ids'] ?? '[]', true);
        $ids = array_filter(array_map('intval', $rawIds));
        if (empty($ids)) {
            echo json_encode(['success' => false, 'error' => 'No notes selected']);
            exit;
        }
        $inClause = implode(',', $ids);

        // Fetch drive_file_ids for drive deletion
        $dRes = $conn->query("SELECT drive_file_id FROM class_notes WHERE id IN ($inClause)");
        try {
            $driveService = new GoogleDriveService();
            while ($row = $dRes->fetch_assoc()) {
                if (!empty($row['drive_file_id'])) {
                    $driveService->deleteFile($row['drive_file_id']);
                }
            }
        } catch (Exception $e) {
            error_log("Bulk delete Drive error: " . $e->getMessage());
        }

        $conn->query("DELETE FROM class_notes WHERE id IN ($inClause)");
        echo json_encode(['success' => true, 'count' => count($ids)]);
        exit;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        exit;
}
