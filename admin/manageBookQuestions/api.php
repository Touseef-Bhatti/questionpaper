<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../../includes/book_questions/BookQuestionGenerator.php';

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $payload, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('uploadErrorMessage')) {
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
}

if (!function_exists('bookQuestionsIniBytes')) {
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
}

if (!function_exists('recordUploadProblem')) {
    function recordUploadProblem(string $pdfPath, string $message): void
    {
        $logPath = $pdfPath . '.error.txt';
        $lines = [
            'Book question generation upload problem',
            'Time: ' . date('c'),
            'PDF: ' . basename($pdfPath),
            'Problem: ' . $message,
        ];
        @file_put_contents($logPath, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    }
}

requireAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Invalid request method.'], 405);
}

$postMaxBytes = bookQuestionsIniBytes((string) ini_get('post_max_size'));
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
    jsonResponse([
        'ok' => false,
        'error' => 'Uploaded PDF is too large for the current PHP post_max_size limit. Current limit: ' . round($postMaxBytes / 1024 / 1024) . ' MB.',
    ], 413);
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf)) {
    jsonResponse(['ok' => false, 'error' => 'Security token invalid. Reload the page and try again.'], 403);
}

$action = trim((string) ($_POST['action'] ?? ''));
$generator = new BookQuestionGenerator($conn);

if ($action === 'pdf_page_count') {
    if (!isset($_FILES['book_file']) || !is_array($_FILES['book_file'])) {
        jsonResponse(['ok' => false, 'error' => 'Upload a PDF file.']);
    }
    $file = $_FILES['book_file'];
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'error' => uploadErrorMessage($uploadError)]);
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        jsonResponse(['ok' => false, 'error' => 'Only PDF files are supported for page mapping.']);
    }
    $tmp = tempnam(sys_get_temp_dir(), 'bkpdf_');
    if ($tmp === false || !move_uploaded_file($file['tmp_name'], $tmp)) {
        jsonResponse(['ok' => false, 'error' => 'Could not read uploaded PDF.']);
    }
    $extractor = new BookChapterExtractor();
    $info = $extractor->getPdfPageCount($tmp);
    @unlink($tmp);
    if (!$info['ok']) {
        jsonResponse(['ok' => false, 'error' => $info['error'] ?? 'Could not read PDF page count.']);
    }
    jsonResponse(['ok' => true, 'pdf_page_count' => (int) $info['page_count']]);
}

if ($action === 'calculate_pages') {
    $pageOffset = intval($_POST['page_offset'] ?? 0);
    $chaptersJson = $_POST['chapters'] ?? '[]';
    $chapters = json_decode((string) $chaptersJson, true);
    if (!is_array($chapters)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid chapter data.']);
    }

    $pdfPageCount = intval($_POST['pdf_page_count'] ?? 0);
    if ($pdfPageCount <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Upload a PDF first to calculate page mapping.']);
    }

    $rows = [];
    foreach ($chapters as $chapter) {
        $start = intval($chapter['start_page'] ?? 0);
        $end = intval($chapter['end_page'] ?? 0);
        $extractor = new BookChapterExtractor();
        $range = $extractor->validatePrintedPageRange($start, $end, $pageOffset, $pdfPageCount);
        $rows[] = [
            'chapter_no' => intval($chapter['chapter_no'] ?? 0),
            'chapter_name' => trim((string) ($chapter['chapter_name'] ?? '')),
            'printed_start' => $start,
            'printed_end' => $end,
            'ok' => $range['ok'],
            'error' => $range['error'] ?? '',
            'pdf_start' => $range['pdf_start'] ?? null,
            'pdf_end' => $range['pdf_end'] ?? null,
        ];
    }

    jsonResponse(['ok' => true, 'rows' => $rows, 'pdf_page_count' => $pdfPageCount, 'page_offset' => $pageOffset]);
}

if ($action === 'init_job') {
    $classId = intval($_POST['class_id'] ?? 0);
    $bookId = intval($_POST['book_id'] ?? 0);
    $pageOffset = intval($_POST['page_offset'] ?? 0);
    $mode = trim((string) ($_POST['mode'] ?? 'one'));

    if ($classId <= 0 || $bookId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Select a valid class and book.']);
    }

    if (!isset($_FILES['book_file']) || !is_array($_FILES['book_file'])) {
        jsonResponse(['ok' => false, 'error' => 'Upload the complete textbook PDF.']);
    }

    $file = $_FILES['book_file'];
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'error' => uploadErrorMessage($uploadError)]);
    }

    $origName = (string) ($file['name'] ?? 'book.pdf');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        jsonResponse(['ok' => false, 'error' => 'PDF is required for exact chapter page extraction.']);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = (string) $finfo->file($file['tmp_name']);
    if (strpos($detectedMime, 'pdf') === false) {
        jsonResponse(['ok' => false, 'error' => 'Uploaded file is not a valid PDF.']);
    }

    $chaptersJson = $_POST['chapters'] ?? '[]';
    $chaptersInput = json_decode((string) $chaptersJson, true);
    if (!is_array($chaptersInput)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid chapter rows.']);
    }

    $normalized = [];
    foreach ($chaptersInput as $row) {
        $normalized[] = [
            'chapter_no' => intval($row['chapter_no'] ?? 0),
            'chapter_name' => trim((string) ($row['chapter_name'] ?? '')),
            'start_page' => intval($row['start_page'] ?? 0),
            'end_page' => intval($row['end_page'] ?? 0),
            'mcq_count' => intval($row['mcq_count'] ?? 0),
            'short_count' => intval($row['short_count'] ?? 0),
            'long_count' => intval($row['long_count'] ?? 0),
        ];
    }

    $chapterCheck = $generator->normalizeChaptersInput($normalized);
    if (!$chapterCheck['ok']) {
        jsonResponse(['ok' => false, 'error' => $chapterCheck['error'] ?? 'Invalid chapter data.']);
    }

    $uploadDir = $generator->getUploadDir();
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0750, true)) {
        jsonResponse(['ok' => false, 'error' => 'Could not create book upload folder: ' . $uploadDir]);
    }
    if (!is_writable($uploadDir)) {
        jsonResponse(['ok' => false, 'error' => 'Book upload folder is not writable: ' . $uploadDir]);
    }

    $storedName = 'book_' . bin2hex(random_bytes(12)) . '.pdf';
    $destPath = $uploadDir . '/' . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        jsonResponse(['ok' => false, 'error' => 'Could not store uploaded book securely.']);
    }

    $extractor = new BookChapterExtractor();
    $pageInfo = $extractor->getPdfPageCount($destPath);
    if (!$pageInfo['ok']) {
        $message = $pageInfo['error'] ?? 'Could not read PDF.';
        recordUploadProblem($destPath, $message);
        jsonResponse([
            'ok' => false,
            'error' => $message,
            'stored_pdf' => basename($destPath),
            'stored_pdf_note' => 'The uploaded PDF was kept in storage/book_uploads for inspection.',
        ]);
    }

    $job = $generator->createJob(
        $classId,
        $bookId,
        $pageOffset,
        $destPath,
        $origName,
        (int) $pageInfo['page_count'],
        $chapterCheck['chapters'],
        $mode
    );

    if (!$job['ok']) {
        $message = $job['error'] ?? 'Could not start generation job.';
        recordUploadProblem($destPath, $message);
        jsonResponse([
            'ok' => false,
            'error' => $message,
            'stored_pdf' => basename($destPath),
            'stored_pdf_note' => 'The uploaded PDF was kept in storage/book_uploads for inspection.',
        ]);
    }

    logAdminAction('book_question_job_created', 'Job ' . ($job['job_id'] ?? ''));

    jsonResponse([
        'ok' => true,
        'job_id' => $job['job_id'],
        'pdf_page_count' => (int) $pageInfo['page_count'],
        'progress' => $generator->buildProgress($job['state']),
    ]);
}

$jobId = trim((string) ($_POST['job_id'] ?? ($_SESSION['book_question_job_id'] ?? '')));
if ($jobId === '') {
    jsonResponse(['ok' => false, 'error' => 'Missing generation job ID.']);
}

if ($action === 'process_batch') {
    $result = $generator->processNextBatch($jobId);
    if (!$result['ok']) {
        jsonResponse([
            'ok' => false,
            'error' => $result['error'] ?? 'Batch failed.',
            'progress' => isset($result['state']) ? $generator->buildProgress($result['state']) : null,
        ]);
    }

    jsonResponse([
        'ok' => true,
        'done' => !empty($result['done']),
        'progress' => $result['progress'] ?? null,
        'batch' => $result['batch'] ?? null,
    ]);
}

if ($action === 'status') {
    $state = $generator->loadState($jobId);
    if (!$state) {
        jsonResponse(['ok' => false, 'error' => 'Generation job not found.']);
    }
    jsonResponse(['ok' => true, 'progress' => $generator->buildProgress($state)]);
}

if ($action === 'cancel') {
    $result = $generator->cancelJob($jobId);
    if (!$result['ok']) {
        jsonResponse(['ok' => false, 'error' => $result['error'] ?? 'Cancel failed.']);
    }
    logAdminAction('book_question_job_cancelled', 'Job ' . $jobId);
    jsonResponse(['ok' => true, 'progress' => $generator->buildProgress($result['state'])]);
}

if ($action === 'retry_failed') {
    $state = $generator->loadState($jobId);
    if (!$state) {
        jsonResponse(['ok' => false, 'error' => 'Generation job not found.']);
    }
    $state['cancelled'] = false;
    if (($state['status'] ?? '') === 'cancelled') {
        $state['status'] = 'ready';
    }
    $chapterIndex = (int) ($state['current_chapter_index'] ?? 0);
    if (isset($state['chapters'][$chapterIndex])) {
        $type = (string) ($state['chapters'][$chapterIndex]['current_type'] ?? 'mcq');
        $batchNum = max(0, (int) ($state['chapters'][$chapterIndex]['current_batch'] ?? 0) - 1);
        $batchKey = $type . ':' . $batchNum;
        $completed = $state['chapters'][$chapterIndex]['completed_batches'] ?? [];
        $state['chapters'][$chapterIndex]['completed_batches'] = array_values(array_filter($completed, static function ($k) use ($batchKey) {
            return $k !== $batchKey;
        }));
        if (isset($state['chapters'][$chapterIndex]['failed_batches'][$batchKey])) {
            unset($state['chapters'][$chapterIndex]['failed_batches'][$batchKey]);
        }
        $state['chapters'][$chapterIndex]['current_batch'] = $batchNum;
        if (($state['chapters'][$chapterIndex]['status'] ?? '') === 'failed') {
            $state['chapters'][$chapterIndex]['status'] = 'generating';
            unset($state['chapters'][$chapterIndex]['error']);
        }
    }
    $generator->saveState($jobId, $state);
    jsonResponse(['ok' => true, 'progress' => $generator->buildProgress($state)]);
}

if ($action === 'continue_job') {
    $state = $generator->loadState($jobId);
    if (!$state) {
        jsonResponse(['ok' => false, 'error' => 'Generation job not found.']);
    }
    $state['cancelled'] = false;
    if (($state['status'] ?? '') === 'cancelled') {
        $state['status'] = 'ready';
    }
    $chapterIndex = (int) ($state['current_chapter_index'] ?? 0);
    if (isset($state['chapters'][$chapterIndex]) && ($state['chapters'][$chapterIndex]['status'] ?? '') === 'failed') {
        $state['chapters'][$chapterIndex]['status'] = 'generating';
        unset($state['chapters'][$chapterIndex]['error']);
    }
    $generator->saveState($jobId, $state);
    jsonResponse(['ok' => true, 'progress' => $generator->buildProgress($state)]);
}

if ($action === 'get_extracted_text') {
    $chapterIndex = intval($_POST['chapter_index'] ?? 0);
    $result = $generator->getExtractedText($jobId, $chapterIndex);
    if (!$result['ok']) {
        jsonResponse(['ok' => false, 'error' => $result['error'] ?? 'Text unavailable.']);
    }
    jsonResponse(['ok' => true, 'text' => $result['text'] ?? '']);
}

if ($action === 'get_saved_questions') {
    $chapterIndex = intval($_POST['chapter_index'] ?? 0);
    $result = $generator->getSavedQuestions($jobId, $chapterIndex);
    if (!$result['ok']) {
        jsonResponse(['ok' => false, 'error' => $result['error'] ?? 'Questions unavailable.']);
    }
    jsonResponse(['ok' => true, 'items' => $result['items'] ?? []]);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400);
