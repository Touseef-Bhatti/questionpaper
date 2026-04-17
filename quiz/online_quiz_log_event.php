<?php
// online_quiz_log_event.php - Logs cheating/activity events from participants
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

header('Content-Type: application/json');

/**
 * Shared-hosting safe: if event_type is ENUM (old schema), relax it to VARCHAR(50)
 * so proctoring event types (tab_switch/window_blur/...) can be inserted.
 */
try {
    $col = $conn->query("SHOW COLUMNS FROM live_quiz_events LIKE 'event_type'");
    if ($col && $col->num_rows > 0) {
        $meta = $col->fetch_assoc();
        $typeDef = strtolower((string)($meta['Type'] ?? ''));
        if (strpos($typeDef, 'enum(') === 0) {
            $conn->query("ALTER TABLE live_quiz_events MODIFY COLUMN event_type VARCHAR(50) NOT NULL");
        }
    }
} catch (Throwable $e) {
    error_log('online_quiz_log_event.php schema check failed: ' . $e->getMessage());
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Fallback for form-encoded requests
if (!is_array($data)) {
    $data = $_POST;
}

if (!is_array($data) || empty($data)) {
    error_log("online_quiz_log_event.php: No request data provided");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No data provided']);
    exit;
}

$room_code = strtoupper(trim($data['room_code'] ?? ''));
$participant_id = (int)($data['participant_id'] ?? 0);
$event_type = trim((string)($data['event_type'] ?? ''));
$event_details = trim((string)($data['event_details'] ?? ''));

if ($room_code === '' || $participant_id <= 0 || $event_type === '') {
    error_log("online_quiz_log_event.php: Missing required fields: room_code=$room_code, p_id=$participant_id, type=$event_type");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Get room_id
$stmt = $conn->prepare("SELECT id FROM quiz_rooms WHERE room_code = ?");
if (!$stmt) {
    error_log("online_quiz_log_event.php: room lookup prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (room lookup prepare)']);
    exit;
}

$stmt->bind_param('s', $room_code);
if (!$stmt->execute()) {
    error_log("online_quiz_log_event.php: room lookup execute failed: " . $stmt->error);
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (room lookup execute)']);
    exit;
}

$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Invalid room']);
    exit;
}

$room_id = (int)$room['id'];

// Log event
$event_data = json_encode(['details' => $event_details], JSON_UNESCAPED_UNICODE);
$stmt = $conn->prepare("INSERT INTO live_quiz_events (room_id, participant_id, event_type, event_data) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    error_log("online_quiz_log_event.php: insert prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (insert prepare)', 'error' => $conn->error]);
    exit;
}

$stmt->bind_param('iiss', $room_id, $participant_id, $event_type, $event_data);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    error_log("online_quiz_log_event.php: insert failed: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $stmt->error]);
}
$stmt->close();
?>