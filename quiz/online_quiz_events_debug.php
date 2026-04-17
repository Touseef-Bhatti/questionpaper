<?php
// online_quiz_events_debug.php
// Lightweight endpoint to verify proctoring events are being stored.
require_once '../db_connect.php';

header('Content-Type: text/plain; charset=utf-8');

$room_code = strtoupper(trim($_GET['room'] ?? ''));
if ($room_code === '') {
    echo "Missing room code. Use ?room=ROOMCODE\n";
    exit;
}

$stmt = $conn->prepare("SELECT id FROM quiz_rooms WHERE room_code = ? LIMIT 1");
$stmt->bind_param('s', $room_code);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    echo "Room not found.\n";
    exit;
}

$room_id = (int)$room['id'];

echo "Room: {$room_code} (id={$room_id})\n";
echo "Last 30 events:\n\n";

$q = $conn->prepare("SELECT id, participant_id, event_type, event_data, created_at
                     FROM live_quiz_events
                     WHERE room_id = ?
                     ORDER BY id DESC
                     LIMIT 30");
$q->bind_param('i', $room_id);
$q->execute();
$res = $q->get_result();

while ($r = $res->fetch_assoc()) {
    echo '#' . $r['id'] . ' | p=' . ($r['participant_id'] ?? 'NULL') . ' | type=' . $r['event_type'] . ' | at=' . $r['created_at'] . "\n";
}
$q->close();

