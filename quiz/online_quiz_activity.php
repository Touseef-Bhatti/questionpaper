<?php
// online_quiz_activity.php - API endpoint for updating participant activity
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

if (!$participant_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing participant_id']);
    exit;
}

try {
    // Update participant's last activity
    $stmt = $conn->prepare("UPDATE quiz_participants SET last_activity = NOW() WHERE id = ?");
    $stmt->bind_param('i', $participant_id);
    $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Activity updated'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'error' => 'Participant not found'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Activity update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => 'Unable to update activity'
    ]);
}
?>
