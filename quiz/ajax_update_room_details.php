<?php
// ajax_update_room_details.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$room_code = strtoupper(trim($_POST['room_code'] ?? ''));
$custom_class = trim($_POST['custom_class'] ?? '');
$custom_book = trim($_POST['custom_book'] ?? '');

if (empty($room_code)) {
    echo json_encode(['success' => false, 'message' => 'Room code is required']);
    exit;
}

// Verify ownership and update
$stmt = $conn->prepare("UPDATE quiz_rooms SET custom_class = ?, custom_book = ? WHERE room_code = ? AND user_id = ?");
$stmt->bind_param('sssi', $custom_class, $custom_book, $room_code, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
        echo json_encode(['success' => true, 'message' => 'Room details updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made or room not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
$stmt->close();
?>