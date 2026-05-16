<?php
/**
 * AJAX Background MCQ Verification
 * This script is called asynchronously to verify and generate explanations for MCQs
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mcq_generator.php';

header('Content-Type: application/json');

// Get raw input
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);

$mcqIds = $jsonInput['mcq_ids'] ?? [];
$sourceTable = $jsonInput['source_table'] ?? 'AIGeneratedMCQs';

if (empty($mcqIds)) {
    echo json_encode(['success' => false, 'message' => 'No MCQ IDs provided']);
    exit;
}

// Separate IDs by source
$aiMcqIds = [];
$manualMcqIds = [];

foreach ($mcqIds as $id) {
    $idStr = (string)$id;
    if (strpos($idStr, 'ai_') === 0) {
        $aiMcqIds[] = intval(substr($idStr, 3));
    } else {
        $manualMcqIds[] = intval($idStr);
    }
}

$results = [
    'success' => true,
    'stats' => ['checked' => 0, 'verified' => 0, 'corrected' => 0, 'flagged' => 0, 'processed_ids' => []],
    'explanations' => []
];

// Batch size for AI verification - reduced for better reliability
$chunkSize = 5;

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
        }
    }
}

echo json_encode($results);
exit;
