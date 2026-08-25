<?php
/**
 * Read-only status endpoint for quiz MCQ background verification.
 * The quiz polls this while ajax_verify_background.php performs the AI work.
 */
ini_set('display_errors', '0');
require_once __DIR__ . '/../db_connect.php';
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$input = json_decode((string) file_get_contents('php://input'), true);
$ids = $input['mcq_ids'] ?? [];
if (!is_array($ids) || empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No MCQ IDs provided']);
    exit;
}

$aiIds = [];
$manualIds = [];
foreach (array_slice($ids, 0, 100) as $rawId) {
    if (!is_string($rawId) && !is_int($rawId)) {
        continue;
    }
    $id = (string) $rawId;
    if (strpos($id, 'book_') === 0) {
        continue; // Book MCQs do not use this recheck pipeline.
    }
    if (strpos($id, 'ai_') === 0) {
        $numeric = intval(substr($id, 3));
        if ($numeric > 0) $aiIds[$numeric] = $numeric;
        continue;
    }
    $numeric = intval($id);
    if ($numeric > 0) $manualIds[$numeric] = $numeric;
}

$updates = [];
$completedIds = [];

if (!empty($aiIds)) {
    $placeholders = implode(',', array_fill(0, count($aiIds), '?'));
    $types = str_repeat('i', count($aiIds));
    $values = array_values($aiIds);
    $sql = "SELECT m.id, m.question_text, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option,
                   COALESCE(NULLIF(TRIM(m.explanation), ''), NULLIF(TRIM(v.explanation), '')) AS explanation,
                   COALESCE(v.verification_status, 'pending') AS verification_status
            FROM AIGeneratedMCQs m
            LEFT JOIN MCQVerification v ON v.source = 'AIGeneratedMCQs' AND v.mcq_id = m.id
            WHERE m.id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $row['source'] = 'ai';
            $updates[] = $row;
            if (($row['verification_status'] ?? 'pending') !== 'pending') {
                $completedIds[] = 'ai_' . (int) $row['id'];
            }
        }
        $stmt->close();
    }
}

if (!empty($manualIds)) {
    $placeholders = implode(',', array_fill(0, count($manualIds), '?'));
    $types = str_repeat('i', count($manualIds));
    $values = array_values($manualIds);
    $sql = "SELECT m.mcq_id AS id, m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option,
                   COALESCE(NULLIF(TRIM(v.explanation), ''), NULLIF(TRIM(m.explanation), '')) AS explanation,
                   COALESCE(v.verification_status, 'pending') AS verification_status
            FROM mcqs m
            LEFT JOIN MCQsVerification v ON v.mcq_id = m.mcq_id
            WHERE m.mcq_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $row['source'] = 'manual';
            $updates[] = $row;
            if (($row['verification_status'] ?? 'pending') !== 'pending') {
                $completedIds[] = (string) (int) $row['id'];
            }
        }
        $stmt->close();
    }
}

$requestedIds = [];
foreach ($aiIds as $id) $requestedIds[] = 'ai_' . $id;
foreach ($manualIds as $id) $requestedIds[] = (string) $id;
$pendingIds = array_values(array_diff($requestedIds, array_unique($completedIds)));

echo json_encode([
    'success' => true,
    'complete' => empty($pendingIds),
    'completed_ids' => array_values(array_unique($completedIds)),
    'pending_ids' => $pendingIds,
    'explanations' => $updates,
]);
