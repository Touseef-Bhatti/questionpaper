<?php
// online_quiz_submit.php - Records quiz results (score and optionally answers)
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$participant_id = intval($data['participant_id'] ?? 0);
$room_code = strtoupper(trim($data['room_code'] ?? ''));
$score = intval($data['score'] ?? 0);
$total = intval($data['total'] ?? 0);
$answers = $data['answers'] ?? [];

if ($participant_id <= 0 || $room_code === '' || $total <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// Validate participant belongs to the room
$stmt = $conn->prepare("SELECT p.id, p.room_id, r.id as rid FROM quiz_participants p JOIN quiz_rooms r ON r.id = p.room_id WHERE p.id = ? AND r.room_code = ?");
$stmt->bind_param('is', $participant_id, $room_code);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$room_id = (int)$row['room_id'];

// Update participant final score
$stmt = $conn->prepare("UPDATE quiz_participants SET finished_at = NOW(), score = ?, total_questions = ? WHERE id = ?");
$stmt->bind_param('iii', $score, $total, $participant_id);
$stmt->execute();
$stmt->close();

// Optionally store responses (best effort)
if (is_array($answers) && !empty($answers)) {
    $ins = $conn->prepare("INSERT INTO quiz_responses (participant_id, question_id, selected_option, is_correct, time_spent_sec) VALUES (?, ?, ?, ?, ?)");
    foreach ($answers as $ans) {
        $qid = intval($ans['question_id'] ?? 0);
        $sel = substr(strtoupper(trim($ans['selected'] ?? '')), 0, 1);
        $isc = !empty($ans['isCorrect']) ? 1 : 0;
        $tsp = intval($ans['timeSpent'] ?? 0);
        if ($qid > 0 && in_array($sel, ['A','B','C','D'])) {
            $ins->bind_param('iisii', $participant_id, $qid, $sel, $isc, $tsp);
            $ins->execute();
        }
    }
    $ins->close();
}

echo json_encode(['status' => 'ok']);
