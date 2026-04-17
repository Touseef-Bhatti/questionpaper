<?php
// online_quiz_log_event.php - Logs cheating/activity events from participants
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    error_log("online_quiz_log_event.php: No JSON data provided");
    echo json_encode(['success' => false, 'message' => 'No data provided']);
    exit;
}

$room_code = strtoupper(trim($data['room_code'] ?? ''));
$participant_id = intval($data['participant_id'] ?? 0);
$event_type = trim($data['event_type'] ?? '');
$event_details = trim($data['event_details'] ?? '');

if (empty($room_code) || $participant_id <= 0 || empty($event_type)) {
    error_log("online_quiz_log_event.php: Missing required fields: room_code=$room_code, p_id=$participant_id, type=$event_type");
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Get room_id
$stmt = $conn->prepare("SELECT id FROM quiz_rooms WHERE room_code = ?");
$stmt->bind_param('s', $room_code);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Invalid room']);
    exit;
}

$room_id = (int)$room['id'];

// Log event
$event_data = json_encode(['details' => $event_details]);
$stmt = $conn->prepare("INSERT INTO live_quiz_events (room_id, participant_id, event_type, event_data) VALUES (?, ?, ?, ?)");
$stmt->bind_param('iiss', $room_id, $participant_id, $event_type, $event_data);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
$stmt->close();
?>