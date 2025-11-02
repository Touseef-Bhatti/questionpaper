<?php
// online_quiz_leave.php - API endpoint for leaving quiz lobby
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$participant_id = (int)($input['participant_id'] ?? 0);
$room_code = strtoupper(trim($input['room_code'] ?? ''));

if (!$participant_id || empty($room_code)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing participant_id or room_code']);
    exit;
}

try {
    // Get participant and room info
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.roll_number, p.room_id, r.id as room_db_id
        FROM quiz_participants p 
        JOIN quiz_rooms r ON r.id = p.room_id
        WHERE p.id = ? AND r.room_code = ?
    ");
    $stmt->bind_param('is', $participant_id, $room_code);
    $stmt->execute();
    $participant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$participant) {
        http_response_code(404);
        echo json_encode(['error' => 'Participant not found']);
        exit;
    }
    
    $room_id = $participant['room_id'];
    
    // Record participant left event
    $event_stmt = $conn->prepare("INSERT INTO live_quiz_events (room_id, participant_id, event_type, event_data) VALUES (?, ?, 'participant_left', ?)");
    $event_data = json_encode(['name' => $participant['name'], 'roll_number' => $participant['roll_number']]);
    $event_stmt->bind_param('iis', $room_id, $participant_id, $event_data);
    $event_stmt->execute();
    $event_stmt->close();
    
    // Remove participant from the quiz
    $delete_stmt = $conn->prepare("DELETE FROM quiz_participants WHERE id = ?");
    $delete_stmt->bind_param('i', $participant_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Clear session data
    unset($_SESSION['quiz_participant_id']);
    unset($_SESSION['quiz_room_code']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully left the lobby'
    ]);
    
} catch (Exception $e) {
    error_log("Leave lobby error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => 'Unable to leave lobby'
    ]);
}
?>
