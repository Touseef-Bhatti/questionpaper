<?php
// online_quiz_participant_status.php - Check room status and time for participants
require_once '../db_connect.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$room_code = $_GET['room_code'] ?? '';
if (empty($room_code)) {
    echo json_encode(['error' => 'Missing room_code']);
    exit;
}

$stmt = $conn->prepare("SELECT id, status, quiz_started, start_time, quiz_duration_minutes FROM quiz_rooms WHERE room_code = ?");
$stmt->bind_param('s', $room_code);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $durationMin = (int)$row['quiz_duration_minutes'] > 0 ? (int)$row['quiz_duration_minutes'] : 30;
    $durationSec = $durationMin * 60;
    
    $startTs = isset($row['start_time']) ? strtotime($row['start_time']) : 0;
    $nowTs = time();
    
    $remaining = 0;
    if ($startTs > 0 && $row['quiz_started']) {
        $elapsed = $nowTs - $startTs;
        $remaining = max(0, $durationSec - $elapsed);
    } else {
        // If not started yet or invalid start time, remaining is full duration
        // Or if start_time is null, we can assume it hasn't started counting down
        $remaining = $durationSec;
    }
    
    echo json_encode([
        'status' => $row['status'],
        'quiz_started' => (bool)$row['quiz_started'],
        'remaining_seconds' => $remaining,
        'server_time' => $nowTs
    ]);
} else {
    echo json_encode(['error' => 'Room not found']);
}
$stmt->close();
?>
