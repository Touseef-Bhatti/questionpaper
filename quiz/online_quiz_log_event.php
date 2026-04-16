<?php
// online_quiz_log_event.php - Logs cheating/activity events from participants
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

header('Content-Type: application/json');

/**
 * Ensure live_quiz_events schema accepts our proctoring event types.
 * We keep this lightweight and idempotent to avoid heavy migrations here.
 */
try {
    $colRes = $conn->query("SHOW COLUMNS FROM live_quiz_events LIKE 'event_type'");
    if ($colRes && $colRes->num_rows > 0) {
        $colInfo = $colRes->fetch_assoc();
        // If event_type is an ENUM that might not include our values, relax it to VARCHAR(50)
        if (stripos($colInfo['Type'] ?? '', 'enum(') === 0) {
            $conn->query("ALTER TABLE live_quiz_events MODIFY COLUMN event_type VARCHAR(50) NOT NULL");
        }
    }
} catch (Throwable $e) {
    // Don't block logging on schema inspection errors; just record them
    error_log('online_quiz_log_event.php: Schema check failed: ' . $e->getMessage());
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    error_log("online_quiz_log_event.php: No/invalid JSON body: " . substr($rawBody ?? '', 0, 2000));
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

$room_code = strtoupper(trim($data['room_code'] ?? ''));
$participant_id = (int)($data['participant_id'] ?? 0);
$event_type = trim($data['event_type'] ?? '');
$event_details = trim($data['event_details'] ?? '');

if ($room_code === '' || $participant_id <= 0 || $event_type === '') {
    error_log("online_quiz_log_event.php: Missing required fields: room_code=$room_code, p_id=$participant_id, type=$event_type");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Get room_id
$stmt = $conn->prepare("SELECT id FROM quiz_rooms WHERE room_code = ?");
if (!$stmt) {
    error_log("online_quiz_log_event.php: Prepare failed (room lookup): " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (room lookup)']);
    exit;
}

$stmt->bind_param('s', $room_code);
if (!$stmt->execute()) {
    error_log("online_quiz_log_event.php: Execute failed (room lookup): " . $stmt->error);
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (room lookup)']);
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
    error_log("online_quiz_log_event.php: Prepare failed (insert): " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (insert)']);
    exit;
}

$stmt->bind_param('iiss', $room_id, $participant_id, $event_type, $event_data);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    error_log("online_quiz_log_event.php: Insert failed: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $stmt->error]);
}
$stmt->close();
?>