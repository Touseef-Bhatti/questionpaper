<?php
// online_quiz_lobby_status.php - API endpoint for lobby status checking
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
$room_code = strtoupper(trim($input['room_code'] ?? ''));
$participant_id = (int)($input['participant_id'] ?? 0);

if (empty($room_code) || !$participant_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing room_code or participant_id']);
    exit;
}

try {
    // Get room status
    $stmt = $conn->prepare("
        SELECT r.id, r.quiz_started, r.status, r.lobby_enabled 
        FROM quiz_rooms r 
        WHERE r.room_code = ?
    ");
    $stmt->bind_param('s', $room_code);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$room) {
        echo json_encode([
            'room_closed' => true,
            'message' => 'Room not found'
        ]);
        exit;
    }
    
    $room_id = $room['id'];
    
    // Check if room is closed
    if ($room['status'] === 'closed') {
        echo json_encode([
            'room_closed' => true,
            'message' => 'Room has been closed'
        ]);
        exit;
    }
    
    // Check if quiz has started
    if ($room['quiz_started']) {
        echo json_encode([
            'quiz_started' => true,
            'message' => 'Quiz has started'
        ]);
        exit;
    }
    
    // Update participant's last activity
    $conn->query("UPDATE quiz_participants SET last_activity = NOW() WHERE id = $participant_id");
    
    // Get all participants in the lobby (waiting status only for lobby display)
    $participants_stmt = $conn->prepare("
        SELECT p.id, p.name, p.roll_number, p.status, p.last_activity
        FROM quiz_participants p 
        WHERE p.room_id = ? AND p.status = 'waiting'
        ORDER BY p.started_at ASC
    ");
    $participants_stmt->bind_param('i', $room_id);
    $participants_stmt->execute();
    $participants_result = $participants_stmt->get_result();
    
    $participants = [];
    while ($row = $participants_result->fetch_assoc()) {
        $participants[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'roll_number' => $row['roll_number'],
            'status' => $row['status'],
            'last_activity' => $row['last_activity']
        ];
    }
    $participants_stmt->close();
    
    // Return status
    echo json_encode([
        'quiz_started' => false,
        'room_closed' => false,
        'lobby_enabled' => (bool)$room['lobby_enabled'],
        'participants' => $participants,
        'participant_count' => count($participants)
    ]);
    
} catch (Exception $e) {
    error_log("Lobby status error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => 'Unable to check lobby status'
    ]);
}
?>
