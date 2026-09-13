<?php
/**
 * AJAX API endpoints for class notes filters and upload forms.
 * Queries DB tables: class, book, chapter, and class_notes.
 */
include '../db_connect.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'subjects':
        $class = $_GET['class'] ?? '';
        $subjects = [];

        // 1. Query from book table
        if (in_array($class, ['9','10','11','12'])) {
            $stmt = $conn->prepare("SELECT DISTINCT book_name FROM book WHERE class_id = ? ORDER BY book_name ASC");
            $stmt->bind_param("i", $class);
        } else {
            $stmt = $conn->prepare("SELECT DISTINCT book_name FROM book ORDER BY book_name ASC");
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['book_name'])) {
                $subjects[] = $row['book_name'];
            }
        }
        $stmt->close();

        // 2. Also union with any distinct approved subjects from class_notes
        if (in_array($class, ['9','10','11','12'])) {
            $stmt2 = $conn->prepare("SELECT DISTINCT subject FROM class_notes WHERE status = 'approved' AND class = ? AND subject IS NOT NULL AND subject != ''");
            $stmt2->bind_param("s", $class);
        } else {
            $stmt2 = $conn->prepare("SELECT DISTINCT subject FROM class_notes WHERE status = 'approved' AND subject IS NOT NULL AND subject != ''");
        }
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($row = $res2->fetch_assoc()) {
            if (!in_array($row['subject'], $subjects, true)) {
                $subjects[] = $row['subject'];
            }
        }
        $stmt2->close();

        sort($subjects);
        echo json_encode($subjects);
        break;

    case 'chapters':
        $subject = trim($_GET['subject'] ?? '');
        $class = trim($_GET['class'] ?? '');
        
        if (empty($subject)) {
            echo json_encode([]);
            break;
        }

        $chapters = [];
        $bookId = 0;

        if (in_array($class, ['9','10','11','12'])) {
            $bookStmt = $conn->prepare("SELECT book_id FROM book WHERE class_id = ? AND book_name = ? LIMIT 1");
            $bookStmt->bind_param("is", $class, $subject);
            $bookStmt->execute();
            $bookRow = $bookStmt->get_result()->fetch_assoc();
            $bookStmt->close();
            $bookId = intval($bookRow['book_id'] ?? 0);
        }

        // 1. Query from chapter table
        if (in_array($class, ['9','10','11','12'])) {
            $stmt = $conn->prepare("SELECT DISTINCT chapter_name, chapter_no FROM chapter WHERE class_id = ? AND (book_name = ? OR (book_id IS NOT NULL AND book_id = ?)) ORDER BY chapter_no ASC, chapter_name ASC");
            $stmt->bind_param("isi", $class, $subject, $bookId);
        } else {
            $stmt = $conn->prepare("SELECT DISTINCT chapter_name, chapter_no FROM chapter WHERE book_name = ? ORDER BY chapter_no ASC, chapter_name ASC");
            $stmt->bind_param("s", $subject);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $displayName = !empty($row['chapter_no']) ? "Chapter {$row['chapter_no']}: {$row['chapter_name']}" : $row['chapter_name'];
            $chapters[] = [
                'name' => $row['chapter_name'],
                'display' => $displayName
            ];
        }
        $stmt->close();

        // 2. Union with chapters from class_notes
        if (in_array($class, ['9','10','11','12'])) {
            $stmt2 = $conn->prepare("SELECT DISTINCT chapter FROM class_notes WHERE status = 'approved' AND class = ? AND subject = ? AND chapter IS NOT NULL AND chapter != ''");
            $stmt2->bind_param("ss", $class, $subject);
        } else {
            $stmt2 = $conn->prepare("SELECT DISTINCT chapter FROM class_notes WHERE status = 'approved' AND subject = ? AND chapter IS NOT NULL AND chapter != ''");
            $stmt2->bind_param("s", $subject);
        }
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $existingNames = array_column($chapters, 'name');
        while ($row = $res2->fetch_assoc()) {
            if (!in_array($row['chapter'], $existingNames, true)) {
                $chapters[] = [
                    'name' => $row['chapter'],
                    'display' => $row['chapter']
                ];
            }
        }
        $stmt2->close();

        echo json_encode($chapters);
        break;

    case 'test_drive':
        require_once __DIR__ . '/../services/GoogleDriveService.php';
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
        break;

    default:
        echo json_encode(['error' => 'Invalid type parameter']);
        break;
}
