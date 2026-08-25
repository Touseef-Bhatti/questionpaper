<?php
/**
 * AJAX Background MCQ Verification
 * This script is called asynchronously to verify and generate explanations for MCQs
 */
ini_set('display_errors', '0');
ignore_user_abort(true);
@set_time_limit(300);

if (session_status() === PHP_SESSION_NONE) session_start();
// This request can spend minutes waiting for the AI. It does not use session
// data, so release the lock immediately and keep the quiz responsive.
session_write_close();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mcq_generator.php';
// db_connect enables display_errors for normal pages; an AJAX JSON response
// must keep warnings in the log so its body remains valid JSON.
ini_set('display_errors', '0');

header('Content-Type: application/json');

// Get raw input
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);

$mcqIds = $jsonInput['mcq_ids'] ?? [];

if (!is_array($mcqIds) || empty($mcqIds)) {
    echo json_encode(['success' => false, 'message' => 'No MCQ IDs provided']);
    exit;
}
$mcqIds = array_values(array_filter($mcqIds, static function ($id) {
    return is_string($id) || is_int($id);
}));
$mcqIds = array_slice(array_values(array_unique($mcqIds, SORT_STRING)), 0, 100);
if (empty($mcqIds)) {
    echo json_encode(['success' => false, 'message' => 'No valid MCQ IDs provided']);
    exit;
}

// Separate IDs by source
$aiMcqIds = [];
$manualMcqIds = [];
$bookMcqIds = [];

foreach ($mcqIds as $id) {
    $idStr = (string)$id;
    if (strpos($idStr, 'ai_') === 0) {
        $numericId = intval(substr($idStr, 3));
        if ($numericId > 0) $aiMcqIds[] = $numericId;
    } elseif (strpos($idStr, 'book_') === 0) {
        $numericId = intval(substr($idStr, 5));
        if ($numericId > 0) $bookMcqIds[] = $numericId;
    } else {
        $numericId = intval($idStr);
        if ($numericId > 0) $manualMcqIds[] = $numericId;
    }
}

if (empty($aiMcqIds) && empty($manualMcqIds) && empty($bookMcqIds)) {
    echo json_encode(['success' => false, 'message' => 'No stored MCQ IDs provided']);
    exit;
}

// Prevent duplicate browser retries from rechecking the same batch at once.
$lockParts = [];
foreach ($aiMcqIds as $id) $lockParts[] = 'a' . $id;
foreach ($manualMcqIds as $id) $lockParts[] = 'm' . $id;
sort($lockParts, SORT_STRING);
$verificationLockName = 'alh_mcq_verify_' . sha1(implode(',', $lockParts));
$verificationLockAcquired = null;
if (!empty($lockParts)) {
    $lockStmt = $conn->prepare('SELECT GET_LOCK(?, 0)');
    if ($lockStmt) {
        $lockStmt->bind_param('s', $verificationLockName);
        $lockStmt->execute();
        $lockStmt->bind_result($verificationLockAcquired);
        $lockStmt->fetch();
        $lockStmt->close();
    }
}
if ($verificationLockAcquired === 0) {
    echo json_encode(['success' => true, 'queued' => true, 'already_running' => true]);
    exit;
}

// On PHP-FPM, acknowledge the job immediately and keep working after the HTTP
// response is closed. The quiz polls ajax_verification_status.php for updates.
// Other SAPIs retain the original asynchronous-fetch behaviour.
$responseDetached = false;
if (function_exists('fastcgi_finish_request')) {
    http_response_code(202);
    echo json_encode([
        'success' => true,
        'queued' => true,
        'mcq_count' => count($aiMcqIds) + count($manualMcqIds),
    ]);
    fastcgi_finish_request();
    $responseDetached = true;
}

$results = [
    'success' => true,
    'stats' => ['checked' => 0, 'verified' => 0, 'corrected' => 0, 'flagged' => 0, 'processed_ids' => []],
    'explanations' => [],
    'errors' => []
];

// Match the detached worker so a typical quiz completes in one provider call.
$chunkSize = 12;

// Process AI MCQs in chunks
if (!empty($aiMcqIds)) {
    $chunks = array_chunk($aiMcqIds, $chunkSize);
    foreach ($chunks as $chunk) {
        $aiRes = checkMCQsWithAI(count($chunk), null, null, 'AIGeneratedMCQs', $chunk);
        if ($aiRes['success']) {
            foreach (['checked', 'verified', 'corrected', 'flagged'] as $k) {
                $results['stats'][$k] += $aiRes['stats'][$k];
            }
            $results['stats']['processed_ids'] = array_merge($results['stats']['processed_ids'], $aiRes['stats']['processed_ids']);
            
            $idsStr = implode(',', $chunk);
            $res = $conn->query("SELECT m.id, m.question_text, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option, 
                                        COALESCE(NULLIF(TRIM(m.explanation), ''), NULLIF(TRIM(v.explanation), '')) as explanation 
                                 FROM AIGeneratedMCQs m 
                                 LEFT JOIN MCQVerification v ON v.source = 'AIGeneratedMCQs' AND v.mcq_id = m.id 
                                 WHERE m.id IN ($idsStr)");
            while ($row = $res->fetch_assoc()) {
                $row['source'] = 'ai';
                $results['explanations'][] = $row;
            }
        } else {
            $message = $aiRes['message'] ?? 'Unknown AI MCQ verification error';
            $results['errors'][] = $message;
            error_log('ajax_verify_background: AI verification failed for IDs (' . implode(',', $chunk) . '): ' . $message);
        }
    }
}

// Book-generated MCQs are already saved from the textbook extraction flow.
// Return their current data so the quiz UI can keep the prefixed IDs intact.
if (!empty($bookMcqIds)) {
    $chunks = array_chunk($bookMcqIds, $chunkSize);
    foreach ($chunks as $chunk) {
        $idsStr = implode(',', array_map('intval', $chunk));
        $res = $conn->query("SELECT mcq_id as id, question, option_a, option_b, option_c, option_d, correct_option, '' as explanation
                             FROM mcqs_from_book
                             WHERE mcq_id IN ($idsStr)");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row['source'] = 'book';
                $results['explanations'][] = $row;
            }
        }
    }
}

// Process Manual MCQs in chunks
if (!empty($manualMcqIds)) {
    $chunks = array_chunk($manualMcqIds, $chunkSize);
    foreach ($chunks as $chunk) {
        $idsStr = implode(',', $chunk);
        $manualRes = checkMCQsWithAI(count($chunk), null, null, 'mcqs', $chunk);
        if ($manualRes['success']) {
            foreach (['checked', 'verified', 'corrected', 'flagged'] as $k) {
                $results['stats'][$k] += $manualRes['stats'][$k];
            }
            $results['stats']['processed_ids'] = array_merge($results['stats']['processed_ids'], $manualRes['stats']['processed_ids']);
            
            $res = $conn->query("SELECT m.mcq_id as id, m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option, 
                                        COALESCE(NULLIF(TRIM(v.explanation), ''), NULLIF(TRIM(m.explanation), '')) as explanation 
                                 FROM mcqs m 
                                 LEFT JOIN MCQsVerification v ON m.mcq_id = v.mcq_id 
                                 WHERE m.mcq_id IN ($idsStr)");
            while ($row = $res->fetch_assoc()) {
                $row['source'] = 'manual';
                $results['explanations'][] = $row;
            }
        } else {
            // Log the failure message to help debug
            error_log('ajax_verify_background: manual verification failed for IDs (' . $idsStr . '): ' . ($manualRes['message'] ?? 'Unknown error'));
            $results['errors'][] = $manualRes['message'] ?? 'Unknown manual MCQ verification error';
        }
    }
}

$results['success'] = empty($results['errors']);
$results['partial'] = !$results['success'] && !empty($results['explanations']);

if (!$responseDetached) {
    echo json_encode($results);
} elseif (!$results['success']) {
    error_log('ajax_verify_background: detached job completed with errors: ' . implode('; ', $results['errors']));
}
if ($verificationLockAcquired === 1) {
    $releaseStmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    if ($releaseStmt) {
        $releaseStmt->bind_param('s', $verificationLockName);
        $releaseStmt->execute();
        $releaseStmt->close();
    }
}
exit;
