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
        (SELECT COUNT(*) FROM quiz_participant_answers a WHERE a.room_code = ? AND a.roll_number = p.roll_number) as answered_count
        FROM quiz_participants p
        WHERE p.room_id = ?
        ORDER BY p.score DESC, TIMESTAMPDIFF(SECOND, p.started_at, COALESCE(p.finished_at, NOW())) ASC";

$start = time();
$maxSeconds = 60 * 10; // keep stream for 10 minutes; browser auto-reconnects
$lastHash = '';

while (!connection_aborted() && (time() - $start) < $maxSeconds) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $room_code, $room_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $participants = [];
    while ($r = $result->fetch_assoc()) {
        $r['alerts'] = '[]';
        $participants[] = $r;
    }
    $stmt->close();

    $alertsByParticipant = [];
    $evt = $conn->prepare("SELECT participant_id, event_type, event_data, created_at
                           FROM live_quiz_events
                           WHERE room_id = ? AND event_type IN ('tab_switch', 'window_blur', 'copy_text', 'inspect_mode', 'right_click')
                           ORDER BY created_at DESC");
    if ($evt) {
        $evt->bind_param('i', $room_id);
        $evt->execute();
        $evtRes = $evt->get_result();
        while ($er = $evtRes->fetch_assoc()) {
            $pid = (int)$er['participant_id'];
            if ($pid <= 0) continue;
            if (!isset($alertsByParticipant[$pid])) $alertsByParticipant[$pid] = [];
            $alertsByParticipant[$pid][] = [
                'type' => $er['event_type'],
                'data' => $er['event_data'],
                'at' => $er['created_at']
            ];
        }
        $evt->close();
    }

    foreach ($participants as &$p) {
        $pid = (int)$p['id'];
        $p['alerts'] = json_encode($alertsByParticipant[$pid] ?? []);
    }
    unset($p);

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

