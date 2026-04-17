<?php
// online_quiz_close_room.php - Closes a quiz room
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

header('Content-Type: application/json');

$room_code = strtoupper(trim($_POST['room_code'] ?? ''));

if (empty($room_code)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Room code is required.']);
    exit;
}

// Check if the room exists and is active
$stmt = $conn->prepare("SELECT id, status FROM quiz_rooms WHERE room_code = ?");
$stmt->bind_param('s', $room_code);
$stmt->execute();
$res = $stmt->get_result();
$room = $res->fetch_assoc();
$stmt->close();

if (!$room) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Room not found.']);
    exit;
}

// Only allow closing if the room is active
if ($room['status'] !== 'active') {
    echo json_encode(['status' => 'info', 'message' => 'Room is already closed or not active.']);
    exit;
}

// Update room status to 'closed'
$stmt = $conn->prepare("UPDATE quiz_rooms SET status = 'closed', ended_at = NOW() WHERE id = ?");
$stmt->bind_param('i', $room['id']);
$stmt->execute();
$stmt->close();

// End all active participants as well (time-over / room close should finalize quizzes)
try {
    // Ensure lock columns exist (safe/idempotent)
    $lockColCheck = $conn->query("SHOW COLUMNS FROM quiz_participants LIKE 'is_screen_locked'");
    if ($lockColCheck && $lockColCheck->num_rows === 0) {
        $conn->query("ALTER TABLE quiz_participants ADD COLUMN is_screen_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER current_question");
    }
    $lockMessageColCheck = $conn->query("SHOW COLUMNS FROM quiz_participants LIKE 'lock_message'");
    if ($lockMessageColCheck && $lockMessageColCheck->num_rows === 0) {
        $conn->query("ALTER TABLE quiz_participants ADD COLUMN lock_message VARCHAR(255) DEFAULT NULL AFTER is_screen_locked");
    }

    $room_id = (int)$room['id'];
    $end = $conn->prepare("
        UPDATE quiz_participants
        SET status = 'completed',
            is_screen_locked = 1,
            lock_message = 'Time is over. Your quiz has been ended automatically.',
            finished_at = COALESCE(finished_at, NOW())
        WHERE room_id = ? AND status = 'active'
    ");
    if ($end) {
        $end->bind_param('i', $room_id);
        $end->execute();
        $ended_count = $end->affected_rows;
        $end->close();
    } else {
        $ended_count = 0;
    }

    // Log event (best-effort)
    $evt = $conn->prepare("INSERT INTO live_quiz_events (room_id, event_type, event_data) VALUES (?, 'room_time_over', ?)");
    if ($evt) {
        $event_data = json_encode(['ended_participants' => (int)$ended_count, 'room_code' => $room_code]);
        $evt->bind_param('is', $room_id, $event_data);
        $evt->execute();
        $evt->close();
    }
} catch (Throwable $e) {
    // Don't fail the close request if participant ending fails; just log
    error_log('online_quiz_close_room.php: failed ending participants: ' . $e->getMessage());
}

echo json_encode(['status' => 'success', 'message' => 'Room ' . $room_code . ' has been closed and participants ended.']);
?>
