<?php
// online_quiz_start.php - API endpoint for starting quiz from lobby
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get current user
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$room_code = strtoupper(trim($input['room_code'] ?? ''));

if (empty($room_code)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing room_code']);
    exit;
}

try {
    // Get room info and verify ownership
    $stmt = $conn->prepare("
        SELECT r.id, r.room_code, r.quiz_started, r.status, r.user_id, r.lobby_enabled,
               (SELECT COUNT(*) FROM quiz_participants WHERE room_id = r.id AND status = 'waiting') as waiting_participants
        FROM quiz_rooms r 
        WHERE r.room_code = ?
    ");
    $stmt->bind_param('s', $room_code);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$room) {
        http_response_code(404);
        echo json_encode(['error' => 'Room not found']);
        exit;
    }
    
    // Check ownership (if user_id column exists and is set)
    if (isset($room['user_id']) && $room['user_id'] && $room['user_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied - not room owner']);
        exit;
    }
    
    // Check if room is active
    if ($room['status'] !== 'active') {
        http_response_code(400);
        echo json_encode(['error' => 'Room is not active']);
        exit;
    }
    
    // Check if quiz is already started
    if ($room['quiz_started']) {
        echo json_encode([
            'success' => true,
            'message' => 'Quiz is already started',
            'quiz_started' => true
        ]);
        exit;
    }
    
    // Check if there are participants waiting
    if ($room['waiting_participants'] == 0) {
        http_response_code(400);
        echo json_encode(['error' => 'No participants waiting in lobby']);
        exit;
    }
    
    $room_id = $room['id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Mark quiz as started and set start time
        $start_stmt = $conn->prepare("UPDATE quiz_rooms SET quiz_started = TRUE, start_time = NOW() WHERE id = ?");
        $start_stmt->bind_param('i', $room_id);
        $start_stmt->execute();
        $start_stmt->close();
        
        // 2. Move all waiting participants to active status
        $activate_stmt = $conn->prepare("UPDATE quiz_participants SET status = 'active' WHERE room_id = ? AND status = 'waiting'");
        $activate_stmt->bind_param('i', $room_id);
        $activate_stmt->execute();
        $activated_count = $activate_stmt->affected_rows;
        $activate_stmt->close();
        
        // 3. Record quiz started event
        $event_stmt = $conn->prepare("INSERT INTO live_quiz_events (room_id, event_type, event_data) VALUES (?, 'quiz_started', ?)");
        $event_data = json_encode([
            'started_by_user_id' => $user_id,
            'participants_count' => $activated_count,
            'start_time' => date('Y-m-d H:i:s')
        ]);
        $event_stmt->bind_param('is', $room_id, $event_data);
        $event_stmt->execute();
        $event_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quiz started successfully',
            'quiz_started' => true,
            'participants_moved' => $activated_count,
            'start_time' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Start quiz error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => 'Unable to start quiz: ' . $e->getMessage()
    ]);
}
?>
