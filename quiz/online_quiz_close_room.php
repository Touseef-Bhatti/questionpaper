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

echo json_encode(['status' => 'success', 'message' => 'Room ' . $room_code . ' has been closed.']);
?>