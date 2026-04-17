<?php
// online_quiz_server_diagnostic.php
// Upload this file to server and open in browser to inspect quiz dashboard compatibility.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db_connect.php';

header('Content-Type: text/plain; charset=utf-8');

function out($line = '') {
    echo $line . PHP_EOL;
}

function has_table(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$t}'");
    return $res && $res->num_rows > 0;
}

function has_column(mysqli $conn, string $table, string $column): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    return $res && $res->num_rows > 0;
}

function status(bool $ok): string {
    return $ok ? '[OK]' : '[FAIL]';
}

$roomCode = strtoupper(trim($_GET['room'] ?? ''));

out('=== Online Quiz Server Diagnostic ===');
out('Time: ' . date('Y-m-d H:i:s'));
out('PHP: ' . PHP_VERSION);
out('MySQL server: ' . ($conn->server_info ?? 'unknown'));
out('Room param: ' . ($roomCode !== '' ? $roomCode : '(none)'));
out();

out('--- Table checks ---');
$tables = ['quiz_rooms', 'quiz_participants', 'quiz_room_questions', 'live_quiz_events'];
foreach ($tables as $t) {
    $ok = has_table($conn, $t);
    out(status($ok) . " table `{$t}`");
}
out();

out('--- Column checks: quiz_rooms ---');
$roomCols = [
    'id', 'room_code', 'status', 'class_id', 'book_id',
    'custom_class', 'custom_book',
    'quiz_started', 'lobby_enabled', 'start_time', 'quiz_duration_minutes',
    'active_question_ids'
];
foreach ($roomCols as $c) {
    $ok = has_column($conn, 'quiz_rooms', $c);
    out(status($ok) . " quiz_rooms.`{$c}`");
}
out();

out('--- Column checks: quiz_participants ---');
$participantCols = [
    'id', 'room_id', 'name', 'roll_number', 'score', 'total_questions',
    'status', 'current_question', 'last_activity', 'started_at', 'finished_at',
    'is_screen_locked', 'lock_message'
];
foreach ($participantCols as $c) {
    $ok = has_column($conn, 'quiz_participants', $c);
    out(status($ok) . " quiz_participants.`{$c}`");
}
out();

out('--- Query compatibility checks ---');

// 1) Dashboard room query compatibility
$sqlRoom = "
SELECT r.id, r.room_code, r.created_at, r.status, r.class_id, r.book_id
FROM quiz_rooms r
WHERE r.room_code = ?
LIMIT 1
";
$stmt = $conn->prepare($sqlRoom);
if (!$stmt) {
    out('[FAIL] prepare dashboard room query: ' . $conn->error);
} else {
    $bindCode = ($roomCode !== '' ? $roomCode : 'ZZZZZZ');
    $stmt->bind_param('s', $bindCode);
    $execOk = $stmt->execute();
    out(status($execOk) . ' execute dashboard room query' . ($execOk ? '' : ': ' . $stmt->error));
    $stmt->close();
}

// 2) Participants query compatibility
$sqlParticipants = "
SELECT p.id, p.name, p.roll_number, p.started_at, p.finished_at, p.score, p.total_questions, p.status
FROM quiz_participants p
WHERE p.room_id = ?
ORDER BY p.score DESC, TIMESTAMPDIFF(SECOND, p.started_at, COALESCE(p.finished_at, NOW())) ASC
LIMIT 5
";
$stmt = $conn->prepare($sqlParticipants);
if (!$stmt) {
    out('[FAIL] prepare participants query: ' . $conn->error);
} else {
    $rid = 0;
    if ($roomCode !== '') {
        $roomStmt = $conn->prepare("SELECT id FROM quiz_rooms WHERE room_code = ? LIMIT 1");
        if ($roomStmt) {
            $roomStmt->bind_param('s', $roomCode);
            if ($roomStmt->execute()) {
                $rr = $roomStmt->get_result()->fetch_assoc();
                if ($rr) $rid = (int)$rr['id'];
            }
            $roomStmt->close();
        }
    }
    $stmt->bind_param('i', $rid);
    $execOk = $stmt->execute();
    out(status($execOk) . ' execute participants query' . ($execOk ? '' : ': ' . $stmt->error));
    $stmt->close();
}

// 3) live events query compatibility
$sqlEvents = "
SELECT participant_id, event_type, event_data, created_at
FROM live_quiz_events
WHERE room_id = ?
ORDER BY created_at DESC
LIMIT 5
";
$stmt = $conn->prepare($sqlEvents);
if (!$stmt) {
    out('[FAIL] prepare live events query: ' . $conn->error);
} else {
    $rid = 0;
    $stmt->bind_param('i', $rid);
    $execOk = $stmt->execute();
    out(status($execOk) . ' execute live events query' . ($execOk ? '' : ': ' . $stmt->error));
    $stmt->close();
}
out();

if ($roomCode !== '') {
    out('--- Room-level diagnostics ---');
    $stmt = $conn->prepare("SELECT id, status, quiz_started, start_time, quiz_duration_minutes FROM quiz_rooms WHERE room_code = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $roomCode);
        if ($stmt->execute()) {
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                out('[OK] Room found');
                out('  room_id=' . (int)$row['id'] . ', status=' . ($row['status'] ?? 'NULL') . ', quiz_started=' . (string)($row['quiz_started'] ?? 'NULL'));
                out('  start_time=' . (string)($row['start_time'] ?? 'NULL') . ', quiz_duration_minutes=' . (string)($row['quiz_duration_minutes'] ?? 'NULL'));

                $rid = (int)$row['id'];
                $count = $conn->prepare("SELECT COUNT(*) c FROM quiz_participants WHERE room_id = ?");
                if ($count) {
                    $count->bind_param('i', $rid);
                    if ($count->execute()) {
                        $c = $count->get_result()->fetch_assoc();
                        out('  participants=' . (int)($c['c'] ?? 0));
                    }
                    $count->close();
                }
            } else {
                out('[FAIL] Room code not found in quiz_rooms');
            }
        } else {
            out('[FAIL] execute room diagnostics query: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        out('[FAIL] prepare room diagnostics query: ' . $conn->error);
    }
    out();
}

out('--- Environment sanity ---');
out('SCRIPT_NAME=' . ($_SERVER['SCRIPT_NAME'] ?? ''));
out('REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? ''));
out('DOCUMENT_ROOT=' . ($_SERVER['DOCUMENT_ROOT'] ?? ''));
out();

out('=== End of diagnostic ===');

