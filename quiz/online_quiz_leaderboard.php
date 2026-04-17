<?php
// online_quiz_leaderboard.php - Public leaderboard for a room (students view)
require_once '../db_connect.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$room_code = strtoupper(trim($_GET['room_code'] ?? ''));
if ($room_code === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing room_code']);
    exit;
}

// Get room id
$stmt = $conn->prepare("SELECT id FROM quiz_rooms WHERE room_code = ?");
$stmt->bind_param('s', $room_code);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Room not found']);
    exit;
}

$room_id = (int)$room['id'];

// Leaderboard: score desc, then time taken asc
$sql = "
    SELECT 
        p.id,
        p.name,
        p.roll_number,
        p.status,
        p.score,
        p.total_questions,
        p.started_at,
        p.finished_at,
        CASE 
            WHEN p.started_at IS NULL THEN NULL
            WHEN p.finished_at IS NULL THEN NULL
            ELSE TIMESTAMPDIFF(SECOND, p.started_at, p.finished_at)
        END AS time_sec
    FROM quiz_participants p
    WHERE p.room_id = ?
    ORDER BY 
        COALESCE(p.score, 0) DESC,
        CASE 
            WHEN p.started_at IS NULL OR p.finished_at IS NULL THEN 999999999
            ELSE TIMESTAMPDIFF(SECOND, p.started_at, p.finished_at)
        END ASC,
        p.roll_number ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $room_id);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
    // Avoid leaking any sensitive fields; keep it minimal
    $rows[] = [
        'id' => (int)$r['id'],
        'name' => (string)$r['name'],
        'roll_number' => (string)$r['roll_number'],
        'status' => (string)$r['status'],
        'score' => is_null($r['score']) ? 0 : (int)$r['score'],
        'total_questions' => is_null($r['total_questions']) ? 0 : (int)$r['total_questions'],
        'time_sec' => is_null($r['time_sec']) ? null : (int)$r['time_sec'],
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'participants' => $rows]);

