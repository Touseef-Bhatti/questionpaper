<?php
// online_quiz_live_stats.php - JSON endpoint for live quiz statistics
require_once '../db_connect.php';

header('Content-Type: application/json');

$room_code = $_GET['room_code'] ?? '';
if (empty($room_code)) {
    echo json_encode([]);
    exit;
}

// Get room id
$stmt = $conn->prepare("SELECT id FROM quiz_rooms WHERE room_code = ?");
$stmt->bind_param('s', $room_code);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $room_id = $row['id'];
} else {
    echo json_encode([]);
    exit;
}
$stmt->close();

// Get participants and their scores
// We can join quiz_participants with quiz_participant_answers
// Or just use the score column in quiz_participants if we updated it (we did in online_quiz_save_answer.php)
$sql = "SELECT p.id, p.name, p.roll_number, p.status, p.score, p.total_questions, p.started_at, p.finished_at, p.current_question,
        (SELECT COUNT(*) FROM quiz_participant_answers a WHERE a.room_code = ? AND a.roll_number = p.roll_number) as answered_count
        FROM quiz_participants p
        WHERE p.room_id = ?
        ORDER BY p.score DESC, TIMESTAMPDIFF(SECOND, p.started_at, COALESCE(p.finished_at, NOW())) ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $room_code, $room_id);
$stmt->execute();
$result = $stmt->get_result();

$participants = [];
while ($row = $result->fetch_assoc()) {
    $participants[] = $row;
}
$stmt->close();

echo json_encode(['participants' => $participants]);
?>
