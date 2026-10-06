<?php
// This endpoint is consumed exclusively as JSON. Buffer every accidental
// warning/output byte so a PDF download or PHP notice can never corrupt the
// response body expected by fetch().
ob_start();
$bookQuestionJsonResponseSent = false;

register_shutdown_function(static function () use (&$bookQuestionJsonResponseSent): void {
    if ($bookQuestionJsonResponseSent) {
        return;
    }

    $lastError = error_get_last();
    if (!$lastError || !in_array((int) $lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    error_log('Book question API fatal error: ' . ($lastError['message'] ?? 'Unknown error') . ' in ' . ($lastError['file'] ?? '') . ':' . ($lastError['line'] ?? 0));
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8', true);
    echo json_encode([
        'ok' => false,
        'error' => 'The book question service could not complete this request. Check the server log and try again.',
    ], JSON_UNESCAPED_UNICODE);
});
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'cookie_samesite' => 'Lax',
]);
header('Content-Type: application/json');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../../services/GoogleDriveService.php';
require_once __DIR__ . '/../../includes/book_questions/BookQuestionGenerator.php';

requireAdminAuth();

function jsonResponse(array $payload, int $code = 200): void
{
    global $bookQuestionJsonResponseSent;
    $bookQuestionJsonResponseSent = true;
    http_response_code($code);
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        $encoded = json_encode([
            'ok' => false,
            'error' => 'The server could not encode the book question response.',
        ], JSON_UNESCAPED_UNICODE);
        http_response_code(500);
    }

    while (ob_get_level() > 0) {
        $bufferedOutput = ob_get_clean();
        if (is_string($bufferedOutput) && trim($bufferedOutput) !== '') {
            error_log('Discarded unexpected output from book question API: ' . substr($bufferedOutput, 0, 500));
        }
    }
    header('Content-Type: application/json; charset=UTF-8', true);
    header('X-Content-Type-Options: nosniff');
    echo $encoded;
    exit;
}

function uploadErrorMessage(int $code): string
{
    $map = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded PDF exceeds the server upload_max_filesize limit.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded PDF exceeds the form upload limit.',
        UPLOAD_ERR_PARTIAL => 'The PDF upload was incomplete.',
        UPLOAD_ERR_NO_FILE => 'No PDF file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload folder.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded PDF to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the PDF upload.',
    ];
    return $map[$code] ?? 'File upload failed. Error code: ' . $code;
}

function bookQuestionsIniBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    if ($unit === 'g') {
        $number *= 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $number *= 1024 * 1024;
    } elseif ($unit === 'k') {
        $number *= 1024;
    }
    return (int) $number;
}

function ensureBookQuestionSchema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS book_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        book_id INT NOT NULL,
        drive_file_id VARCHAR(255) NOT NULL,
        drive_url VARCHAR(500) NOT NULL,
        drive_folder_id VARCHAR(255) DEFAULT NULL,
        local_pdf_path VARCHAR(500) NOT NULL DEFAULT '',
        original_filename VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
        file_size BIGINT DEFAULT 0,
        pdf_page_count INT NOT NULL DEFAULT 0,
        page_offset INT NOT NULL DEFAULT 0,
        status ENUM('active','archived') NOT NULL DEFAULT 'active',
        drive_status ENUM('available','missing') NOT NULL DEFAULT 'available',
        drive_deleted_at DATETIME DEFAULT NULL,
        uploaded_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_book_uploads_book (class_id, book_id, status),
        INDEX idx_book_uploads_drive_status (drive_status),
        INDEX idx_book_uploads_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $driveStatusColumnCheck = $conn->query("SHOW COLUMNS FROM book_uploads LIKE 'drive_status'");
    if (!$driveStatusColumnCheck || $driveStatusColumnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE book_uploads ADD COLUMN drive_status ENUM('available','missing') NOT NULL DEFAULT 'available' AFTER status");
    }
    $driveDeletedAtColumnCheck = $conn->query("SHOW COLUMNS FROM book_uploads LIKE 'drive_deleted_at'");
    if (!$driveDeletedAtColumnCheck || $driveDeletedAtColumnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE book_uploads ADD COLUMN drive_deleted_at DATETIME DEFAULT NULL AFTER drive_status");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS book_chapter_page_ranges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        upload_id INT NOT NULL,
        class_id INT NOT NULL,
        book_id INT NOT NULL,
        chapter_id INT NOT NULL,
        chapter_no INT NOT NULL,
        printed_start_page INT NOT NULL,
        printed_end_page INT NOT NULL,
        pdf_start_page INT NOT NULL,
        pdf_end_page INT NOT NULL,
        page_offset INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_upload_chapter (upload_id, chapter_id),
        INDEX idx_book_ranges_book (class_id, book_id),
        INDEX idx_book_ranges_chapter (chapter_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS book_mcq_drafts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id VARCHAR(64) NOT NULL,
        upload_id INT NOT NULL,
        class_id INT NOT NULL,
        book_id INT NOT NULL,
        chapter_id INT NOT NULL,
        question_text TEXT NOT NULL,
        option_a TEXT NOT NULL,
        option_b TEXT NOT NULL,
        option_c TEXT NOT NULL,
        option_d TEXT NOT NULL,
        correct_option ENUM('A','B','C','D') NOT NULL,
        difficulty_level ENUM('Easy','Medium','Hard') DEFAULT 'Medium',
        status ENUM('pending','approved','deleted','discarded') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        approved_at DATETIME DEFAULT NULL,
        approved_by INT DEFAULT NULL,
        INDEX idx_book_mcq_drafts_job (job_id, status),
        INDEX idx_book_mcq_drafts_chapter (chapter_id, status),
        INDEX idx_book_mcq_drafts_upload (upload_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS book_question_drafts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id VARCHAR(64) NOT NULL,
        upload_id INT NOT NULL,
        class_id INT NOT NULL,
        book_id INT NOT NULL,
        chapter_id INT NOT NULL,
        question_kind ENUM('mcq','short','long') NOT NULL,
        question_text TEXT NOT NULL,
        option_a TEXT NULL,
        option_b TEXT NULL,
        option_c TEXT NULL,
        option_d TEXT NULL,
        correct_option ENUM('A','B','C','D') NULL,
        difficulty_level ENUM('Easy','Medium','Hard') DEFAULT 'Medium',
        status ENUM('pending','approved','deleted','discarded') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        approved_at DATETIME DEFAULT NULL,
        approved_by INT DEFAULT NULL,
        INDEX idx_book_drafts_job (job_id, status),
        INDEX idx_book_drafts_chapter (chapter_id, question_kind, status),
        INDEX idx_book_drafts_upload (upload_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function fetchBook(mysqli $conn, int $classId, int $bookId): ?array
{
    $stmt = $conn->prepare('SELECT b.book_id, b.book_name, b.class_id, c.class_name FROM book b INNER JOIN class c ON c.class_id = b.class_id WHERE b.book_id = ? AND b.class_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $bookId, $classId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function fetchUpload(mysqli $conn, int $uploadId): ?array
{
    $stmt = $conn->prepare('SELECT u.*, b.book_name, c.class_name FROM book_uploads u INNER JOIN book b ON b.book_id = u.book_id INNER JOIN class c ON c.class_id = u.class_id WHERE u.id = ? AND u.status = "active" LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $uploadId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? normalizeBookUploadRecord($row) : null;
}

function extractDriveFileId(string $value): string
{
    $value = trim($value);
    if (preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
        return $value;
    }
    if (preg_match('#/file/d/([A-Za-z0-9_-]+)#', $value, $matches)) {
        return $matches[1];
    }
    if (preg_match('/(?:[?&])id=([A-Za-z0-9_-]+)/', $value, $matches)) {
        return $matches[1];
    }
    return '';
}

function normalizeBookUploadRecord(array $upload): array
{
    $status = strtolower(trim((string) ($upload['drive_status'] ?? 'available')));
    $upload['drive_status'] = $status === 'missing' ? 'missing' : 'available';

    $driveId = extractDriveFileId((string) ($upload['drive_file_id'] ?? ''));
    if ($driveId === '') {
        $driveId = extractDriveFileId((string) ($upload['drive_url'] ?? ''));
    }
    $upload['drive_file_id'] = $driveId;
    return $upload;
}

function bookQuestionPathWithin(string $path, string $root): bool
{
    $resolvedPath = realpath($path);
    $resolvedRoot = realpath($root);
    if ($resolvedPath === false || $resolvedRoot === false) {
        return false;
    }

    $resolvedPath = rtrim(strtolower(str_replace('\\', '/', $resolvedPath)), '/') . '/';
    $resolvedRoot = rtrim(strtolower(str_replace('\\', '/', $resolvedRoot)), '/') . '/';
    return str_starts_with($resolvedPath, $resolvedRoot);
}

/**
 * Resolve a legacy local PDF without allowing arbitrary filesystem reads.
 */
function resolveLegacyBookPdfPath(array $upload): ?string
{
    $configuredPath = trim((string) ($upload['local_pdf_path'] ?? ''));
    if ($configuredPath === '') {
        return null;
    }

    $projectRoot = dirname(__DIR__, 2);
    $candidate = $configuredPath;
    if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $candidate)) {
        $candidate = $projectRoot . DIRECTORY_SEPARATOR . ltrim($candidate, "\\/");
    }

    $allowedRoots = [
        $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'book_uploads',
    ];
    foreach ($allowedRoots as $allowedRoot) {
        if (is_file($candidate) && is_readable($candidate) && bookQuestionPathWithin($candidate, $allowedRoot)) {
            return realpath($candidate) ?: null;
        }
    }

    return null;
}

/**
 * Return source metadata without exposing local filesystem paths to the UI.
 * @return array{available:bool,type:string}
 */
function bookUploadSourceInfo(array $upload): array
{
    if (resolveLegacyBookPdfPath($upload) !== null) {
        return ['available' => true, 'type' => 'local'];
    }
    if (($upload['drive_status'] ?? 'available') === 'available'
        && preg_match('/^[A-Za-z0-9_-]+$/', (string) ($upload['drive_file_id'] ?? ''))) {
        return ['available' => true, 'type' => 'drive'];
    }
    return ['available' => false, 'type' => 'none'];
}

function publicBookUploadPayload(array $upload): array
{
    $source = bookUploadSourceInfo($upload);
    unset($upload['local_pdf_path'], $upload['drive_file_id']);
    $upload['source_available'] = $source['available'];
    $upload['source_type'] = $source['type'];
    return $upload;
}

/**
 * Prepare a PDF in the job temp directory from either a legacy local row or
 * the canonical Google Drive file. This keeps both storage generations valid.
 *
 * @return array{path:string,page_count:int}
 */
function prepareBookPdfForJob(array $upload, BookQuestionGenerator $generator, string $jobKey): array
{
    $destination = $generator->getTemporaryPdfPath($jobKey);
    $sourcePath = resolveLegacyBookPdfPath($upload);

    if ($sourcePath !== null) {
        if (!@copy($sourcePath, $destination)) {
            @unlink($destination);
            throw new RuntimeException('The stored local textbook could not be copied for processing.');
        }
    } else {
        $driveFileId = trim((string) ($upload['drive_file_id'] ?? ''));
        if ($driveFileId === '') {
            throw new RuntimeException('This stored textbook has no local copy or Google Drive file ID. Re-upload it before generating questions.');
        }

        $drive = new GoogleDriveService();
        $drive->downloadFileToTemp($driveFileId, $destination);
    }

    $isPdf = false;
    $handle = @fopen($destination, 'rb');
    if ($handle !== false) {
        $isPdf = fread($handle, 5) === '%PDF-';
        fclose($handle);
    }
    if (!$isPdf) {
        @unlink($destination);
        throw new RuntimeException('The stored textbook download was not a valid PDF.');
    }

    $pageCount = (int) ($upload['pdf_page_count'] ?? 0);
    if ($pageCount <= 0) {
        $extractor = new BookChapterExtractor();
        $pageInfo = $extractor->getPdfPageCount($destination);
        if (!$pageInfo['ok']) {
            @unlink($destination);
            throw new RuntimeException($pageInfo['error'] ?? 'Could not read the stored textbook page count.');
        }
        $pageCount = (int) ($pageInfo['page_count'] ?? 0);
    }

    return [
        'path' => $destination,
        'page_count' => max(1, $pageCount),
    ];
}

function listBookPayload(mysqli $conn): array
{
    $classes = [];
    $res = $conn->query('SELECT class_id, class_name FROM class ORDER BY class_id ASC');
    while ($res && ($row = $res->fetch_assoc())) {
        $classes[] = $row;
    }

    $books = [];
    $res = $conn->query('SELECT book_id, book_name, class_id FROM book ORDER BY class_id ASC, book_name ASC');
    while ($res && ($row = $res->fetch_assoc())) {
        $books[] = $row;
    }

    $uploads = [];
    $sql = "SELECT u.*, b.book_name, c.class_name,
                   (SELECT COUNT(*) FROM book_chapter_page_ranges r WHERE r.upload_id = u.id) AS mapped_chapters
            FROM book_uploads u
            INNER JOIN book b ON b.book_id = u.book_id
            INNER JOIN class c ON c.class_id = u.class_id
            WHERE u.status = 'active'
            ORDER BY u.created_at DESC";
    $res = $conn->query($sql);
    while ($res && ($row = $res->fetch_assoc())) {
        $uploads[] = publicBookUploadPayload(normalizeBookUploadRecord($row));
    }

    return ['classes' => $classes, 'books' => $books, 'uploads' => $uploads];
}

ensureBookQuestionSchema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Invalid request method.'], 405);
}

$postMaxBytes = bookQuestionsIniBytes((string) ini_get('post_max_size'));
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
    jsonResponse(['ok' => false, 'error' => 'Uploaded PDF is too large. Current post_max_size limit: ' . round($postMaxBytes / 1024 / 1024) . ' MB.'], 413);
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf)) {
    jsonResponse(['ok' => false, 'error' => 'Security token invalid. Reload the page and try again.'], 403);
}

$action = trim((string) ($_POST['action'] ?? ''));
$generator = new BookQuestionGenerator($conn);

if ($action === 'list_data') {
    jsonResponse(['ok' => true] + listBookPayload($conn));
}

if ($action === 'list_review_jobs') {
    $jobs = [];
    $sql = "SELECT d.job_id, d.class_id, d.book_id, d.chapter_id, COUNT(*) AS pending_count, MAX(d.created_at) AS last_created,
                   c.class_name, b.book_name, ch.chapter_no, ch.chapter_name
            FROM book_mcq_drafts d
            INNER JOIN class c ON c.class_id = d.class_id
            INNER JOIN book b ON b.book_id = d.book_id
            INNER JOIN chapter ch ON ch.chapter_id = d.chapter_id
            WHERE d.status = 'pending'
            GROUP BY d.job_id, d.class_id, d.book_id, d.chapter_id, c.class_name, b.book_name, ch.chapter_no, ch.chapter_name
            UNION ALL
            SELECT d.job_id, d.class_id, d.book_id, d.chapter_id, COUNT(*) AS pending_count, MAX(d.created_at) AS last_created,
                   c.class_name, b.book_name, ch.chapter_no, ch.chapter_name
            FROM book_question_drafts d
            INNER JOIN class c ON c.class_id = d.class_id
            INNER JOIN book b ON b.book_id = d.book_id
            INNER JOIN chapter ch ON ch.chapter_id = d.chapter_id
            WHERE d.status = 'pending'
            GROUP BY d.job_id, d.class_id, d.book_id, d.chapter_id, c.class_name, b.book_name, ch.chapter_no, ch.chapter_name
            ORDER BY last_created DESC";
    $result = $conn->query($sql);
    while ($result && ($row = $result->fetch_assoc())) {
        $jobIdValue = (string) $row['job_id'];
        if (!isset($jobs[$jobIdValue])) {
            $jobs[$jobIdValue] = $row;
            $jobs[$jobIdValue]['pending_count'] = 0;
        }
        $jobs[$jobIdValue]['pending_count'] += (int) $row['pending_count'];
    }
    jsonResponse(['ok' => true, 'jobs' => array_values($jobs)]);
}

if ($action === 'test_drive') {
    try {
        $drive = new GoogleDriveService();
        jsonResponse($drive->diagnoseConnection());
    } catch (Throwable $e) {
        jsonResponse(['ok' => false, 'success' => false, 'error' => $e->getMessage(), 'errors' => [$e->getMessage()]]);
    }
}

if ($action === 'get_upload_details') {
    $uploadId = intval($_POST['upload_id'] ?? 0);
    $upload = fetchUpload($conn, $uploadId);
    if (!$upload) {
        jsonResponse(['ok' => false, 'error' => 'Uploaded book not found.']);
    }

    $chapters = [];
    $stmt = $conn->prepare('SELECT chapter_id, chapter_no, chapter_name FROM chapter WHERE class_id = ? AND book_id = ? ORDER BY chapter_no ASC, chapter_name ASC');
    $stmt->bind_param('ii', $upload['class_id'], $upload['book_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $chapters[] = $row;
    }
    $stmt->close();

    $ranges = [];
    $stmt = $conn->prepare('SELECT * FROM book_chapter_page_ranges WHERE upload_id = ? ORDER BY chapter_no ASC');
    $stmt->bind_param('i', $uploadId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ranges[(int) $row['chapter_id']] = $row;
    }
    $stmt->close();

    jsonResponse(['ok' => true, 'upload' => publicBookUploadPayload($upload), 'chapters' => $chapters, 'ranges' => $ranges]);
}

if ($action === 'upload_book') {
    $classId = intval($_POST['class_id'] ?? 0);
    $bookId = intval($_POST['book_id'] ?? 0);
    $pageOffset = 0;
    $book = fetchBook($conn, $classId, $bookId);
    if (!$book) {
        jsonResponse(['ok' => false, 'error' => 'Selected book does not belong to the selected class.']);
    }
    if (!isset($_FILES['book_file']) || !is_array($_FILES['book_file'])) {
        jsonResponse(['ok' => false, 'error' => 'Upload a textbook PDF.']);
    }

    $file = $_FILES['book_file'];
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'error' => uploadErrorMessage($uploadError)]);
    }
    $origName = basename((string) ($file['name'] ?? 'book.pdf'));
    if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'pdf') {
        jsonResponse(['ok' => false, 'error' => 'Only PDF textbooks are supported.']);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file($file['tmp_name']);
    if (strpos(strtolower($mimeType), 'pdf') === false) {
        jsonResponse(['ok' => false, 'error' => 'Uploaded file is not a valid PDF.']);
    }

    $localPath = (string) ($file['tmp_name'] ?? '');
    if ($localPath === '' || !is_uploaded_file($localPath)) {
        jsonResponse(['ok' => false, 'error' => 'Uploaded textbook temporary file is not available.']);
    }

    $extractor = new BookChapterExtractor();
    $pageInfo = $extractor->getPdfPageCount($localPath);
    if (!$pageInfo['ok']) {
        jsonResponse(['ok' => false, 'error' => $pageInfo['error'] ?? 'Could not read PDF page count.']);
    }

    try {
        $drive = new GoogleDriveService();
        $classLabel = $book['class_name'] ?: ('Class ' . $classId);
        $driveResult = $drive->uploadBookFile($localPath, $origName, $mimeType, $classLabel, (string) $book['book_name']);
    } catch (Throwable $e) {
        jsonResponse(['ok' => false, 'error' => 'Google Drive upload failed: ' . $e->getMessage()]);
    }

    $adminId = (int) ($_SESSION['admin_id'] ?? ($_SESSION['user_id'] ?? 0));
    $fileSize = (int) ($file['size'] ?? filesize($localPath));
    $pdfPages = (int) $pageInfo['page_count'];
    $folderId = (string) ($driveResult['folder_id'] ?? '');
    $driveFileId = (string) $driveResult['file_id'];
    $driveUrl = (string) $driveResult['url'];
    $storedLocalPath = '';
    $stmt = $conn->prepare(
        'INSERT INTO book_uploads (class_id, book_id, drive_file_id, drive_url, drive_folder_id, local_pdf_path, original_filename, mime_type, file_size, pdf_page_count, page_offset, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iissssssiiii', $classId, $bookId, $driveFileId, $driveUrl, $folderId, $storedLocalPath, $origName, $mimeType, $fileSize, $pdfPages, $pageOffset, $adminId);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        jsonResponse(['ok' => false, 'error' => 'Could not save uploaded book in database: ' . $err]);
    }
    $uploadId = (int) $stmt->insert_id;
    $stmt->close();
    @unlink($localPath);

    logAdminAction('book_pdf_uploaded', 'Upload ID ' . $uploadId);
    jsonResponse(['ok' => true, 'upload_id' => $uploadId, 'pdf_page_count' => $pdfPages, 'drive_url' => $driveUrl] + listBookPayload($conn));
}

if ($action === 'replace_book') {
    $uploadId = intval($_POST['upload_id'] ?? 0);
    $upload = fetchUpload($conn, $uploadId);
    if (!$upload) {
        jsonResponse(['ok' => false, 'error' => 'Uploaded book record not found.']);
    }
    if (($upload['drive_status'] ?? 'available') !== 'missing') {
        jsonResponse(['ok' => false, 'error' => 'This book is not marked as missing from Google Drive.']);
    }
    if (!isset($_FILES['book_file']) || !is_array($_FILES['book_file'])) {
        jsonResponse(['ok' => false, 'error' => 'Upload a replacement textbook PDF.']);
    }

    $file = $_FILES['book_file'];
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'error' => uploadErrorMessage($uploadError)]);
    }
    $replacementPath = (string) ($file['tmp_name'] ?? '');
    $origName = basename((string) ($file['name'] ?? 'book.pdf'));
    if ($replacementPath === '' || !is_uploaded_file($replacementPath) || strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'pdf') {
        jsonResponse(['ok' => false, 'error' => 'Only a valid replacement PDF is supported.']);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file($replacementPath);
    if (strpos(strtolower($mimeType), 'pdf') === false) {
        jsonResponse(['ok' => false, 'error' => 'Replacement file is not a valid PDF.']);
    }

    $extractor = new BookChapterExtractor();
    $pageInfo = $extractor->getPdfPageCount($replacementPath);
    if (!$pageInfo['ok']) {
        jsonResponse(['ok' => false, 'error' => $pageInfo['error'] ?? 'Could not read replacement PDF page count.']);
    }

    try {
        $drive = new GoogleDriveService();
        $driveResult = $drive->uploadBookFile(
            $replacementPath,
            $origName,
            $mimeType,
            (string) $upload['class_name'],
            (string) $upload['book_name']
        );
        $fileSize = (int) filesize($replacementPath);
        $stmt = $conn->prepare("UPDATE book_uploads
            SET drive_file_id = ?, drive_url = ?, drive_folder_id = ?, local_pdf_path = '',
                original_filename = ?, mime_type = ?, file_size = ?, pdf_page_count = ?,
                drive_status = 'available', drive_deleted_at = NULL
            WHERE id = ?");
        $stmt->bind_param(
            'sssssiii',
            $driveResult['file_id'],
            $driveResult['url'],
            $driveResult['folder_id'],
            $origName,
            $mimeType,
            $fileSize,
            $pageInfo['page_count'],
            $uploadId
        );
        $success = $stmt->execute();
        $updateError = $stmt->error;
        $stmt->close();
        if (!$success) {
            try {
                $drive->deleteFile((string) $driveResult['file_id']);
            } catch (Throwable $cleanupError) {
                error_log('Failed to clean up replacement textbook: ' . $cleanupError->getMessage());
            }
            jsonResponse(['ok' => false, 'error' => 'Replacement uploaded, but the book record could not be updated: ' . $updateError]);
        }
        jsonResponse(['ok' => true] + listBookPayload($conn));
    } catch (Throwable $e) {
        error_log('Textbook replacement upload failed: ' . $e->getMessage());
        jsonResponse(['ok' => false, 'error' => 'Replacement upload failed. Check the Google Drive connection.']);
    } finally {
        if (is_file($replacementPath)) {
            @unlink($replacementPath);
        }
    }
}

if ($action === 'purge_missing_records') {
    // Remove only textbook upload rows already confirmed missing from Drive,
    // together with their page mappings and generated draft relationships.
    $conn->begin_transaction();
    try {
        $missingIds = [];
        $missingRes = $conn->query("SELECT id FROM book_uploads WHERE drive_status = 'missing'");
        while ($missingRes && ($missingRow = $missingRes->fetch_assoc())) {
            $missingIds[] = (int) $missingRow['id'];
        }

        foreach (['book_chapter_page_ranges', 'book_mcq_drafts', 'book_question_drafts'] as $relatedTable) {
            $tableCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($relatedTable) . "'");
            if (!$tableCheck || $tableCheck->num_rows === 0 || $missingIds === []) {
                continue;
            }
            $relatedStmt = $conn->prepare("DELETE FROM {$relatedTable} WHERE upload_id = ?");
            if (!$relatedStmt) {
                throw new RuntimeException('Could not prepare missing-textbook cleanup.');
            }
            foreach ($missingIds as $missingId) {
                $relatedStmt->bind_param('i', $missingId);
                $relatedStmt->execute();
            }
            $relatedStmt->close();
        }

        $deleteStmt = $conn->prepare("DELETE FROM book_uploads WHERE drive_status = 'missing'");
        if (!$deleteStmt || !$deleteStmt->execute()) {
            throw new RuntimeException('Could not remove missing textbook records.');
        }
        $deletedCount = (int) $deleteStmt->affected_rows;
        $deleteStmt->close();
        $conn->commit();
        jsonResponse(['ok' => true, 'deleted_count' => $deletedCount] + listBookPayload($conn));
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Missing textbook record purge failed: ' . $e->getMessage());
        jsonResponse(['ok' => false, 'error' => 'Could not remove missing textbook records.']);
    }
}

if ($action === 'delete_book_record') {
    $uploadId = intval($_POST['upload_id'] ?? 0);
    $upload = fetchUpload($conn, $uploadId);
    if (!$upload) {
        jsonResponse(['ok' => false, 'error' => 'Uploaded book record not found.']);
    }
    if (($upload['drive_status'] ?? 'available') !== 'missing') {
        jsonResponse(['ok' => false, 'error' => 'Only books already deleted from Google Drive can be removed this way.']);
    }

    $conn->begin_transaction();
    try {
        foreach (['book_chapter_page_ranges', 'book_mcq_drafts', 'book_question_drafts'] as $relatedTable) {
            $tableCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($relatedTable) . "'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $stmt = $conn->prepare("DELETE FROM {$relatedTable} WHERE upload_id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $uploadId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        $stmt = $conn->prepare("DELETE FROM book_uploads WHERE id = ? AND drive_status = 'missing'");
        $stmt->bind_param('i', $uploadId);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();
        if (!$deleted) {
            throw new RuntimeException('The book record could not be deleted.');
        }
        $conn->commit();
        jsonResponse(['ok' => true] + listBookPayload($conn));
    } catch (Throwable $e) {
        $conn->rollback();
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
}

if ($action === 'save_chapter_ranges') {
    $uploadId = intval($_POST['upload_id'] ?? 0);
    $upload = fetchUpload($conn, $uploadId);
    if (!$upload) {
        jsonResponse(['ok' => false, 'error' => 'Uploaded book not found.']);
    }
    if (!bookUploadSourceInfo($upload)['available']) {
        jsonResponse(['ok' => false, 'error' => 'This textbook has no usable local copy or Drive file. Upload it again before editing chapter ranges.']);
    }
    $pageOffset = 0;
    $rows = json_decode((string) ($_POST['ranges'] ?? '[]'), true);
    if (!is_array($rows)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid chapter page range data.']);
    }

    $extractor = new BookChapterExtractor();
    $conn->begin_transaction();
    try {
        $saved = 0;
        foreach ($rows as $row) {
            $chapterId = intval($row['chapter_id'] ?? 0);
            $start = intval($row['printed_start_page'] ?? 0);
            $end = intval($row['printed_end_page'] ?? 0);
            if ($chapterId <= 0 || $start <= 0 || $end <= 0) {
                continue;
            }
            $chStmt = $conn->prepare('SELECT chapter_no FROM chapter WHERE chapter_id = ? AND class_id = ? AND book_id = ? LIMIT 1');
            $chStmt->bind_param('iii', $chapterId, $upload['class_id'], $upload['book_id']);
            $chStmt->execute();
            $chapterRow = $chStmt->get_result()->fetch_assoc();
            $chStmt->close();
            if (!$chapterRow) {
                throw new RuntimeException('Invalid chapter selected.');
            }
            $range = $extractor->validatePrintedPageRange($start, $end, $pageOffset, (int) $upload['pdf_page_count']);
            if (!$range['ok']) {
                throw new RuntimeException($range['error'] ?? 'Invalid PDF page range.');
            }

            $stmt = $conn->prepare(
                'INSERT INTO book_chapter_page_ranges
                    (upload_id, class_id, book_id, chapter_id, chapter_no, printed_start_page, printed_end_page, pdf_start_page, pdf_end_page, page_offset)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    printed_start_page = VALUES(printed_start_page),
                    printed_end_page = VALUES(printed_end_page),
                    pdf_start_page = VALUES(pdf_start_page),
                    pdf_end_page = VALUES(pdf_end_page),
                    page_offset = VALUES(page_offset),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $chapterNo = (int) $chapterRow['chapter_no'];
            $pdfStart = (int) $range['pdf_start'];
            $pdfEnd = (int) $range['pdf_end'];
            $stmt->bind_param('iiiiiiiiii', $uploadId, $upload['class_id'], $upload['book_id'], $chapterId, $chapterNo, $start, $end, $pdfStart, $pdfEnd, $pageOffset);
            if (!$stmt->execute()) {
                throw new RuntimeException('Could not save chapter page range.');
            }
            $stmt->close();
            $saved++;
        }
        $stmt = $conn->prepare('UPDATE book_uploads SET page_offset = ? WHERE id = ?');
        $stmt->bind_param('ii', $pageOffset, $uploadId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }

    jsonResponse(['ok' => true, 'saved' => $saved]);
}

if ($action === 'init_chapter_job') {
    $uploadId = intval($_POST['upload_id'] ?? 0);
    $chapterId = intval($_POST['chapter_id'] ?? 0);
    $upload = fetchUpload($conn, $uploadId);
    if (!$upload) {
        jsonResponse(['ok' => false, 'error' => 'Uploaded book not found.']);
    }
    if (!bookUploadSourceInfo($upload)['available']) {
        jsonResponse(['ok' => false, 'error' => 'This textbook has no usable local copy or Drive file. Upload it again before generating questions.']);
    }

    $stmt = $conn->prepare('SELECT r.*, ch.chapter_name FROM book_chapter_page_ranges r INNER JOIN chapter ch ON ch.chapter_id = r.chapter_id WHERE r.upload_id = ? AND r.chapter_id = ? LIMIT 1');
    $stmt->bind_param('ii', $uploadId, $chapterId);
    $stmt->execute();
    $range = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$range) {
        jsonResponse(['ok' => false, 'error' => 'Save this chapter page range before generating questions.']);
    }

    $chapterCheck = $generator->normalizeChaptersInput([[
        'chapter_no' => (int) $range['chapter_no'],
        'chapter_name' => (string) $range['chapter_name'],
        'start_page' => (int) $range['printed_start_page'],
        'end_page' => (int) $range['printed_end_page'],
        'mcq_count' => intval($_POST['mcq_count'] ?? 0),
        'short_count' => intval($_POST['short_count'] ?? 0),
        'long_count' => intval($_POST['long_count'] ?? 0),
    ]]);
    if (!$chapterCheck['ok']) {
        jsonResponse(['ok' => false, 'error' => $chapterCheck['error'] ?? 'Invalid question counts.']);
    }

    $temporaryPdfPath = '';
    try {
        $preparedPdf = prepareBookPdfForJob(
            $upload,
            $generator,
            'upload_' . $uploadId . '_' . bin2hex(random_bytes(8))
        );
        $temporaryPdfPath = $preparedPdf['path'];
        $pdfPageCount = (int) $preparedPdf['page_count'];

        // Drive sync creates metadata rows before the page count is known.
        // Backfill it now so later range validation and the UI show accurate data.
        if ((int) ($upload['pdf_page_count'] ?? 0) !== $pdfPageCount) {
            $pageStmt = $conn->prepare('UPDATE book_uploads SET pdf_page_count = ? WHERE id = ?');
            if ($pageStmt) {
                $pageStmt->bind_param('ii', $pdfPageCount, $uploadId);
                $pageStmt->execute();
                $pageStmt->close();
            }
        }
    } catch (Throwable $e) {
        if ($temporaryPdfPath !== '' && is_file($temporaryPdfPath)) {
            @unlink($temporaryPdfPath);
        }
        error_log('Book question PDF preparation failed: ' . $e->getMessage());
        jsonResponse(['ok' => false, 'error' => $e->getMessage() ?: 'The textbook could not be prepared for processing.']);
    }

    $job = $generator->createJob((int) $upload['class_id'], (int) $upload['book_id'], 0, $temporaryPdfPath, (string) $upload['original_filename'], $pdfPageCount, $chapterCheck['chapters'], 'one', $uploadId, true);
    if (!$job['ok']) {
        if (is_file($temporaryPdfPath)) {
            @unlink($temporaryPdfPath);
        }
        jsonResponse(['ok' => false, 'error' => $job['error'] ?? 'Could not create generation job.']);
    }
    logAdminAction('book_question_draft_job_created', 'Job ' . ($job['job_id'] ?? ''));
    jsonResponse(['ok' => true, 'job_id' => $job['job_id'], 'progress' => $generator->buildProgress($job['state'])]);
}

$jobId = trim((string) ($_POST['job_id'] ?? ($_SESSION['book_question_job_id'] ?? '')));

if ($action === 'process_batch') {
    if ($jobId === '') {
        jsonResponse(['ok' => false, 'error' => 'Missing generation job ID.']);
    }
    $result = $generator->processNextBatch($jobId);
    if (!empty($result['done']) && isset($result['state']) && is_array($result['state'])) {
        $generator->cleanupTemporaryPdf($result['state']);
    }
    if (!$result['ok']) {
        jsonResponse(['ok' => false, 'error' => $result['error'] ?? 'Batch failed.', 'progress' => isset($result['state']) ? $generator->buildProgress($result['state']) : null]);
    }
    jsonResponse(['ok' => true, 'done' => !empty($result['done']), 'progress' => $result['progress'] ?? null, 'batch' => $result['batch'] ?? null]);
}

if ($action === 'get_extracted_text') {
    if ($jobId === '') {
        jsonResponse(['ok' => false, 'error' => 'Missing generation job ID.']);
    }
    $result = $generator->getExtractedText($jobId, intval($_POST['chapter_index'] ?? 0));
    if (!$result['ok']) {
        jsonResponse(['ok' => false, 'error' => $result['error'] ?? 'Text unavailable.']);
    }
    jsonResponse(['ok' => true, 'text' => $result['text'] ?? '']);
}

if ($action === 'get_drafts') {
    if ($jobId === '') {
        jsonResponse(['ok' => false, 'error' => 'Missing generation job ID.']);
    }
    $items = [];

    $stmt = $conn->prepare(
        "SELECT d.id, 'mcq' AS question_kind, 'mcq' AS draft_type, d.question_text, d.option_a, d.option_b, d.option_c, d.option_d, d.correct_option, d.difficulty_level,
                c.class_name, b.book_name, ch.chapter_no, ch.chapter_name
         FROM book_mcq_drafts d
         INNER JOIN class c ON c.class_id = d.class_id
         INNER JOIN book b ON b.book_id = d.book_id
         INNER JOIN chapter ch ON ch.chapter_id = d.chapter_id
         WHERE d.job_id = ? AND d.status = 'pending'
         ORDER BY d.id ASC"
    );
    $stmt->bind_param('s', $jobId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT d.id, d.question_kind, 'question' AS draft_type, d.question_text,
                '' AS option_a, '' AS option_b, '' AS option_c, '' AS option_d, '' AS correct_option, '' AS difficulty_level,
                c.class_name, b.book_name, ch.chapter_no, ch.chapter_name
         FROM book_question_drafts d
         INNER JOIN class c ON c.class_id = d.class_id
         INNER JOIN book b ON b.book_id = d.book_id
         INNER JOIN chapter ch ON ch.chapter_id = d.chapter_id
         WHERE d.job_id = ? AND d.status = 'pending' AND d.question_kind IN ('short', 'long')
         ORDER BY FIELD(d.question_kind, 'short', 'long'), d.id ASC"
    );
    $stmt->bind_param('s', $jobId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT d.id, 'mcq' AS question_kind, 'legacy_mcq' AS draft_type, d.question_text, d.option_a, d.option_b, d.option_c, d.option_d, d.correct_option, d.difficulty_level,
                c.class_name, b.book_name, ch.chapter_no, ch.chapter_name
         FROM book_question_drafts d
         INNER JOIN class c ON c.class_id = d.class_id
         INNER JOIN book b ON b.book_id = d.book_id
         INNER JOIN chapter ch ON ch.chapter_id = d.chapter_id
         WHERE d.job_id = ? AND d.status = 'pending' AND d.question_kind = 'mcq'
         ORDER BY d.id ASC"
    );
    $stmt->bind_param('s', $jobId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    jsonResponse(['ok' => true, 'items' => $items]);
}

if ($action === 'update_draft') {
    $draftId = intval($_POST['draft_id'] ?? 0);
    $kind = trim((string) ($_POST['question_kind'] ?? ''));
    $draftType = trim((string) ($_POST['draft_type'] ?? ''));
    $question = trim((string) ($_POST['question_text'] ?? ''));
    if ($draftId <= 0 || $question === '') {
        jsonResponse(['ok' => false, 'error' => 'Question text is required.']);
    }
    if ($draftType === 'mcq' || $draftType === 'legacy_mcq' || $kind === 'mcq') {
        $a = trim((string) ($_POST['option_a'] ?? ''));
        $b = trim((string) ($_POST['option_b'] ?? ''));
        $c = trim((string) ($_POST['option_c'] ?? ''));
        $d = trim((string) ($_POST['option_d'] ?? ''));
        $correct = strtoupper(trim((string) ($_POST['correct_option'] ?? '')));
        $difficulty = trim((string) ($_POST['difficulty_level'] ?? 'Medium'));
        if ($a === '' || $b === '' || $c === '' || $d === '' || !in_array($correct, ['A', 'B', 'C', 'D'], true)) {
            jsonResponse(['ok' => false, 'error' => 'Complete all MCQ options and select A, B, C, or D.']);
        }
        if (!in_array($difficulty, ['Easy', 'Medium', 'Hard'], true)) {
            $difficulty = 'Medium';
        }
        $table = $draftType === 'legacy_mcq' ? 'book_question_drafts' : 'book_mcq_drafts';
        $stmt = $conn->prepare("UPDATE $table SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, difficulty_level = ? WHERE id = ? AND status = 'pending'");
        $stmt->bind_param('sssssssi', $question, $a, $b, $c, $d, $correct, $difficulty, $draftId);
    } else {
        $stmt = $conn->prepare("UPDATE book_question_drafts SET question_text = ? WHERE id = ? AND status = 'pending'");
        $stmt->bind_param('si', $question, $draftId);
    }
    $ok = $stmt->execute();
    $stmt->close();
    jsonResponse(['ok' => $ok]);
}

if ($action === 'delete_draft') {
    $draftId = intval($_POST['draft_id'] ?? 0);
    $draftType = trim((string) ($_POST['draft_type'] ?? 'question'));
    $table = $draftType === 'mcq' ? 'book_mcq_drafts' : 'book_question_drafts';
    $stmt = $conn->prepare("UPDATE $table SET status = 'deleted' WHERE id = ? AND status = 'pending'");
    $stmt->bind_param('i', $draftId);
    $ok = $stmt->execute();
    $stmt->close();
    jsonResponse(['ok' => $ok]);
}

if ($action === 'discard_drafts') {
    if ($jobId === '') {
        jsonResponse(['ok' => false, 'error' => 'Missing generation job ID.']);
    }
    $stmt = $conn->prepare("UPDATE book_mcq_drafts SET status = 'discarded' WHERE job_id = ? AND status = 'pending'");
    $stmt->bind_param('s', $jobId);
    $ok = $stmt->execute();
    $mcqDiscarded = $stmt->affected_rows;
    $stmt->close();

    $stmt = $conn->prepare("UPDATE book_question_drafts SET status = 'discarded' WHERE job_id = ? AND status = 'pending'");
    $stmt->bind_param('s', $jobId);
    $ok = $stmt->execute() && $ok;
    $questionDiscarded = $stmt->affected_rows;
    $stmt->close();

    logAdminAction('book_question_drafts_discarded', 'Job ' . $jobId);
    jsonResponse(['ok' => $ok, 'discarded' => $mcqDiscarded + $questionDiscarded]);
}

if ($action === 'approve_drafts') {
    if ($jobId === '') {
        jsonResponse(['ok' => false, 'error' => 'Missing generation job ID.']);
    }

    $selectionPayload = trim((string) ($_POST['drafts'] ?? ''));
    $selection = null;
    $selectedIds = ['mcq' => [], 'question' => [], 'legacy_mcq' => []];
    if ($selectionPayload !== '') {
        $selection = json_decode($selectionPayload, true);
        if (!is_array($selection)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid approval selection.']);
        }
        foreach ($selection as $draft) {
            $draftId = (int) ($draft['id'] ?? 0);
            $draftType = trim((string) ($draft['draft_type'] ?? ''));
            if ($draftId <= 0 || !array_key_exists($draftType, $selectedIds)) {
                jsonResponse(['ok' => false, 'error' => 'Invalid approval selection.']);
            }
            $selectedIds[$draftType][] = $draftId;
        }
        foreach ($selectedIds as $type => $ids) {
            $selectedIds[$type] = array_values(array_unique($ids));
        }
    }
    $hasSelection = is_array($selection);
    $selectFilter = static function (array $ids) use ($hasSelection): string {
        if (!$hasSelection) {
            return '';
        }
        if ($ids === []) {
            return ' AND 1 = 0';
        }
        return ' AND d.id IN (' . implode(',', array_map('intval', $ids)) . ')';
    };
    $updateFilter = static function (array $ids) use ($hasSelection): string {
        if (!$hasSelection) {
            return '';
        }
        if ($ids === []) {
            return ' AND 1 = 0';
        }
        return ' AND id IN (' . implode(',', array_map('intval', $ids)) . ')';
    };
    $adminId = (int) ($_SESSION['admin_id'] ?? ($_SESSION['user_id'] ?? 0));
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT d.*, ch.chapter_name FROM book_mcq_drafts d INNER JOIN chapter ch ON ch.chapter_id = d.chapter_id WHERE d.job_id = ? AND d.status = 'pending'" . $selectFilter($selectedIds['mcq']) . " ORDER BY d.id ASC");
        $stmt->bind_param('s', $jobId);
        $stmt->execute();
        $res = $stmt->get_result();
        $approved = 0;
        while ($row = $res->fetch_assoc()) {
            $classId = (int) $row['class_id'];
            $bookId = (int) $row['book_id'];
            $chapterId = (int) $row['chapter_id'];
            $questionText = (string) $row['question_text'];
            $optionA = (string) $row['option_a'];
            $optionB = (string) $row['option_b'];
            $optionC = (string) $row['option_c'];
            $optionD = (string) $row['option_d'];
            $correctOption = (string) $row['correct_option'];
            $difficulty = (string) $row['difficulty_level'];
            $topic = (string) ($row['chapter_name'] ?? '');
            $ins = $conn->prepare('INSERT INTO mcqs_from_book (class_id, book_id, chapter_id, topic, question, option_a, option_b, option_c, option_d, correct_option, difficulty_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->bind_param('iiissssssss', $classId, $bookId, $chapterId, $topic, $questionText, $optionA, $optionB, $optionC, $optionD, $correctOption, $difficulty);
            if (!$ins || !$ins->execute()) {
                throw new RuntimeException('Could not publish generated question.');
            }
            $ins->close();
            $approved++;
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT d.*, ch.chapter_name FROM book_question_drafts d INNER JOIN chapter ch ON ch.chapter_id = d.chapter_id WHERE d.job_id = ? AND d.status = 'pending' AND d.question_kind = 'mcq'" . $selectFilter($selectedIds['legacy_mcq']) . " ORDER BY d.id ASC");
        $stmt->bind_param('s', $jobId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $classId = (int) $row['class_id'];
            $bookId = (int) $row['book_id'];
            $chapterId = (int) $row['chapter_id'];
            $questionText = (string) $row['question_text'];
            $optionA = (string) $row['option_a'];
            $optionB = (string) $row['option_b'];
            $optionC = (string) $row['option_c'];
            $optionD = (string) $row['option_d'];
            $correctOption = (string) $row['correct_option'];
            $difficulty = (string) $row['difficulty_level'];
            $topic = (string) ($row['chapter_name'] ?? '');
            $ins = $conn->prepare('INSERT INTO mcqs_from_book (class_id, book_id, chapter_id, topic, question, option_a, option_b, option_c, option_d, correct_option, difficulty_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->bind_param('iiissssssss', $classId, $bookId, $chapterId, $topic, $questionText, $optionA, $optionB, $optionC, $optionD, $correctOption, $difficulty);
            if (!$ins || !$ins->execute()) {
                throw new RuntimeException('Could not publish generated MCQ.');
            }
            $ins->close();
            $approved++;
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT d.*, b.book_name, ch.chapter_name FROM book_question_drafts d INNER JOIN book b ON b.book_id = d.book_id INNER JOIN chapter ch ON ch.chapter_id = d.chapter_id WHERE d.job_id = ? AND d.status = 'pending'" . $selectFilter($selectedIds['question']) . " ORDER BY d.id ASC");
        $stmt->bind_param('s', $jobId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if ($row['question_kind'] === 'mcq') {
                continue;
            }
            $classId = (int) $row['class_id'];
            $bookId = (int) $row['book_id'];
            $chapterId = (int) $row['chapter_id'];
            $questionKind = (string) $row['question_kind'];
            $questionText = (string) $row['question_text'];
            $chapterName = (string) $row['chapter_name'];
            $bookName = (string) $row['book_name'];
            $ins = $conn->prepare('INSERT INTO questions_from_book (class_id, book_id, chapter_id, question_type, question_text, topic, book_name) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $ins->bind_param('iiissss', $classId, $bookId, $chapterId, $questionKind, $questionText, $chapterName, $bookName);
            if (!$ins || !$ins->execute()) {
                throw new RuntimeException('Could not publish generated question.');
            }
            $ins->close();
            $approved++;
        }
        $stmt->close();

        $stmt = $conn->prepare("UPDATE book_mcq_drafts SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE job_id = ? AND status = 'pending'" . $updateFilter($selectedIds['mcq']));
        $stmt->bind_param('is', $adminId, $jobId);
        $stmt->execute();
        $stmt->close();

        $questionIds = array_merge($selectedIds['question'], $selectedIds['legacy_mcq']);
        $stmt = $conn->prepare("UPDATE book_question_drafts SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE job_id = ? AND status = 'pending'" . $updateFilter($questionIds));
        $stmt->bind_param('is', $adminId, $jobId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }

    $generator->syncBookTablesToMainTables();
    logAdminAction('book_question_drafts_approved', 'Job ' . $jobId);
    jsonResponse(['ok' => true, 'approved' => $approved]);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400);
