<?php
/**
 * Detached CLI worker for newly inserted AI MCQs.
 * Usage: php mcq_verify_worker.php 12,13,14
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ignore_user_abort(true);
@set_time_limit(600);

require_once __DIR__ . '/mcq_generator.php';

$rawIds = (string) ($argv[1] ?? '');
$ids = [];
foreach (explode(',', $rawIds) as $rawId) {
    $id = intval($rawId);
    if ($id > 0) $ids[$id] = $id;
}
$ids = array_slice(array_values($ids), 0, 100);
if (empty($ids)) exit;

// One provider request can comfortably verify a normal 10-question quiz.
// Larger batches reduce HTTP round trips while retaining the core missing-ID
// retry if a response is incomplete.
foreach (array_chunk($ids, 12) as $chunk) {
    $result = null;
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $result = checkMCQsWithAI(count($chunk), null, null, 'AIGeneratedMCQs', $chunk);
        if (!empty($result['success'])) {
            break;
        }
        if ($attempt < 2) {
            usleep(1500000);
        }
    }
    if (empty($result['success'])) {
        error_log('mcq_verify_worker failed for IDs ' . implode(',', $chunk) . ': ' . ($result['message'] ?? 'unknown error'));
    }
}
