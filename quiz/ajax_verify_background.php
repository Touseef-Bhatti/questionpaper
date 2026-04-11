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
    if (strpos($id, 'ai_') === 0) {
        $aiMcqIds[] = intval(substr($id, 3));
    } else {
        $manualMcqIds[] = intval($id);
    }
}

$results = [
    'success' => true,
    'stats' => ['checked' => 0, 'verified' => 0, 'corrected' => 0, 'flagged' => 0, 'processed_ids' => []],
    'explanations' => []
];

// Process AI MCQs
if (!empty($aiMcqIds)) {
    $aiRes = checkMCQsWithAI(count($aiMcqIds), null, null, 'AIGeneratedMCQs', $aiMcqIds);
    if ($aiRes['success']) {
        foreach (['checked', 'verified', 'corrected', 'flagged'] as $k) {
            $results['stats'][$k] += $aiRes['stats'][$k];
        }
        $results['stats']['processed_ids'] = array_merge($results['stats']['processed_ids'], $aiRes['stats']['processed_ids']);
        
        $idsStr = implode(',', $aiMcqIds);
        $res = $conn->query("SELECT id, correct_option, explanation FROM AIGeneratedMCQs WHERE id IN ($idsStr)");
        while ($row = $res->fetch_assoc()) {
            $row['source'] = 'ai';
            $results['explanations'][] = $row;
        }
    }
}

// Process Manual MCQs
if (!empty($manualMcqIds)) {
    $manualRes = checkMCQsWithAI(count($manualMcqIds), null, null, 'mcqs', $manualMcqIds);
    if ($manualRes['success']) {
        foreach (['checked', 'verified', 'corrected', 'flagged'] as $k) {
            $results['stats'][$k] += $manualRes['stats'][$k];
        }
        $results['stats']['processed_ids'] = array_merge($results['stats']['processed_ids'], $manualRes['stats']['processed_ids']);
        
        $idsStr = implode(',', $manualMcqIds);
        $res = $conn->query("SELECT m.mcq_id as id, m.correct_option, v.explanation 
                             FROM mcqs m 
                             LEFT JOIN MCQsVerification v ON m.mcq_id = v.mcq_id 
                             WHERE m.mcq_id IN ($idsStr)");
        while ($row = $res->fetch_assoc()) {
            $row['source'] = 'manual';
            $results['explanations'][] = $row;
        }
    }
}

echo json_encode($results);
exit;
