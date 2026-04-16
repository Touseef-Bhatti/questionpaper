<?php
// online_quiz_moderate_participant.php - Teacher actions on a single participant (lock/end quiz)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$participant_id = (int)($_POST['participant_id'] ?? 0);
$room_code = strtoupper(trim($_POST['room_code'] ?? ''));
$action = trim($_POST['action'] ?? '');

if ($participant_id <= 0 || $room_code === '' || $action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing participant_id, room_code or action']);
    exit;
}

// Only supported actions for now
if (!in_array($action, ['lock_screen', 'unlock_screen', 'end_quiz'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    // Add lock columns if they don't exist yet
    $lockColCheck = $conn->query("SHOW COLUMNS FROM quiz_participants LIKE 'is_screen_locked'");
    if ($lockColCheck && $lockColCheck->num_rows === 0) {
        $conn->query("ALTER TABLE quiz_participants ADD COLUMN is_screen_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER current_question");
    }
    $lockMessageColCheck = $conn->query("SHOW COLUMNS FROM quiz_participants LIKE 'lock_message'");
    if ($lockMessageColCheck && $lockMessageColCheck->num_rows === 0) {
        $conn->query("ALTER TABLE quiz_participants ADD COLUMN lock_message VARCHAR(255) DEFAULT NULL AFTER is_screen_locked");
    }

    // Verify participant belongs to this room and the room belongs to this teacher
    $stmt = $conn->prepare("
        SELECT p.id, p.room_id, p.name, p.roll_number, p.status, r.id as room_db_id, r.user_id
        FROM quiz_participants p
        JOIN quiz_rooms r ON r.id = p.room_id
        WHERE p.id = ? AND r.room_code = ?
    ");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('is', $participant_id, $room_code);
    $stmt->execute();
    $participant = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$participant) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Participant not found']);
        exit;
    }

    if (!empty($participant['user_id']) && (int)$participant['user_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized for this room']);
        exit;
    }

    $room_id = (int)$participant['room_id'];

    if ($action === 'lock_screen') {
        $upd = $conn->prepare("
            UPDATE quiz_participants
            SET is_screen_locked = 1,
                lock_message = 'Your screen has been locked by your teacher.'
            WHERE id = ?
        ");
        if (!$upd) {
            throw new Exception('Prepare failed (lock): ' . $conn->error);
        }
        $upd->bind_param('i', $participant_id);
        $upd->execute();
        $upd->close();

        $event_type = 'force_locked';
        $success_message = 'Student screen locked successfully';
    } elseif ($action === 'unlock_screen') {
        $upd = $conn->prepare("
            UPDATE quiz_participants
            SET is_screen_locked = 0,
                lock_message = NULL
            WHERE id = ?
        ");
        if (!$upd) {
            throw new Exception('Prepare failed (unlock): ' . $conn->error);
        }
        $upd->bind_param('i', $participant_id);
        $upd->execute();
        $upd->close();

        $event_type = 'force_unlocked';
        $success_message = 'Student screen unlocked successfully';
    } else {
        $upd = $conn->prepare("
            UPDATE quiz_participants
            SET status = 'completed',
                is_screen_locked = 1,
                lock_message = 'Your quiz has been ended by your teacher.',
                finished_at = COALESCE(finished_at, NOW())
            WHERE id = ?
        ");
        if (!$upd) {
            throw new Exception('Prepare failed (end): ' . $conn->error);
        }
        $upd->bind_param('i', $participant_id);
        $upd->execute();
        $upd->close();

        $event_type = 'force_ended';
        $success_message = 'Student quiz ended successfully';
    }

    $event_stmt = $conn->prepare("
        INSERT INTO live_quiz_events (room_id, participant_id, event_type, event_data)
        VALUES (?, ?, ?, ?)
    ");
    if ($event_stmt) {
        $event_data = json_encode([
            'moderated_by' => $user_id,
            'action' => $action,
            'name' => $participant['name'],
            'roll_number' => $participant['roll_number']
        ]);
        $event_stmt->bind_param('iiss', $room_id, $participant_id, $event_type, $event_data);
        $event_stmt->execute();
        $event_stmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => $success_message,
        'action' => $action
    ]);
} catch (Exception $e) {
    error_log('online_quiz_moderate_participant.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>

