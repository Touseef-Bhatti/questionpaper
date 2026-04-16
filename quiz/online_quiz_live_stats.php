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
$lockColCheck = $conn->query("SHOW COLUMNS FROM quiz_participants LIKE 'is_screen_locked'");
$hasLockColumns = $lockColCheck && $lockColCheck->num_rows > 0;
$lockSelect = $hasLockColumns ? "COALESCE(p.is_screen_locked, 0) as is_screen_locked," : "0 as is_screen_locked,";

$sql = "SELECT p.id, p.name, p.roll_number, p.status, p.score, p.total_questions, p.started_at, p.finished_at, p.current_question,
        $lockSelect
        (SELECT COUNT(*) FROM quiz_participant_answers a WHERE a.room_code = ? AND a.roll_number = p.roll_number) as answered_count,
        (SELECT JSON_ARRAYAGG(JSON_OBJECT('type', event_type, 'data', event_data, 'at', created_at)) 
         FROM live_quiz_events e 
         WHERE e.participant_id = p.id AND e.room_id = ? AND e.event_type IN ('tab_switch', 'window_blur', 'copy_text', 'inspect_mode', 'right_click')) as alerts
        FROM quiz_participants p
        WHERE p.room_id = ?
        ORDER BY p.score DESC, TIMESTAMPDIFF(SECOND, p.started_at, COALESCE(p.finished_at, NOW())) ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('sii', $room_code, $room_id, $room_id);
$stmt->execute();
$result = $stmt->get_result();

$participants = [];
while ($row = $result->fetch_assoc()) {
    $participants[] = $row;
}
$stmt->close();

echo json_encode(['participants' => $participants]);
?>
