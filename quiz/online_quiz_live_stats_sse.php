<?php
// online_quiz_live_stats_sse.php - Server-Sent Events stream for live participant stats
require_once '../db_connect.php';

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
while (ob_get_level() > 0) { @ob_end_flush(); }
@ob_implicit_flush(1);

ignore_user_abort(true);

$room_code = $_GET['room_code'] ?? '';
$room_code = strtoupper(trim($room_code));
if ($room_code === '') {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Missing room_code']) . "\n\n";
    exit;
}

// Resolve room id once
$stmt = $conn->prepare("SELECT id FROM quiz_rooms WHERE room_code = ?");
$stmt->bind_param('s', $room_code);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $room_id = (int)$row['id'];
} else {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Room not found']) . "\n\n";
    exit;
}
$stmt->close();

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

$start = time();
$maxSeconds = 60 * 10; // keep stream for 10 minutes; browser auto-reconnects
$lastHash = '';

while (!connection_aborted() && (time() - $start) < $maxSeconds) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sii', $room_code, $room_id, $room_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $participants = [];
    while ($r = $result->fetch_assoc()) {
        $participants[] = $r;
    }
    $stmt->close();

    $payload = ['participants' => $participants, 'server_time' => time()];
    $json = json_encode($payload);
    $hash = md5($json);

    // Only push when something changes, to reduce noise
    if ($hash !== $lastHash) {
        $lastHash = $hash;
        echo "event: participants\n";
        echo "data: " . $json . "\n\n";
        @flush();
    } else {
        // Keep-alive ping so proxies don't close idle connections
        echo ": ping\n\n";
        @flush();
    }

    sleep(1);
}

// Let the client reconnect cleanly
echo "event: end\n";
echo "data: " . json_encode(['done' => true]) . "\n\n";
@flush();

