<?php
// online_quiz_delete_room.php - Handles deletion of a quiz room
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: online_quiz_dashboard.php');
    exit;
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$room_code = trim($_POST['room_code'] ?? '');

if (empty($room_code)) {
    $_SESSION['error_message'] = 'Room code is missing.';
    header('Location: online_quiz_dashboard.php');
    exit;
}

// Start a transaction
$conn->begin_transaction();

try {
    // Get room ID and verify ownership
    $stmt = $conn->prepare("SELECT id FROM quiz_rooms WHERE room_code = ? AND user_id = ?");
    $stmt->bind_param('si', $room_code, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();

    if (!$room) {
        throw new Exception('Room not found or you do not have permission to delete it.');
    }

    $room_id = (int)$room['id'];

    // Delete related data first (quiz_participants, quiz_room_questions)
    $stmt = $conn->prepare("DELETE FROM quiz_participants WHERE room_id = ?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM quiz_room_questions WHERE room_id = ?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $stmt->close();

    // Finally, delete the room itself
    $stmt = $conn->prepare("DELETE FROM quiz_rooms WHERE id = ?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $_SESSION['success_message'] = 'Room ' . htmlspecialchars($room_code) . ' and all associated data deleted successfully.';
    header('Location: online_quiz_dashboard.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = 'Error deleting room: ' . $e->getMessage();
    header('Location: online_quiz_dashboard.php');
    exit;
}
?>