<?php
// online_quiz_rehost.php - Clone an existing quiz room
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$source_room_code = $_GET['room'] ?? '';

if (!$source_room_code) {
    die("Room code required.");
}

// 1. Fetch source room details
$stmt = $conn->prepare("SELECT class_id, book_id, mcq_count, quiz_duration_minutes, user_id FROM quiz_rooms WHERE room_code = ? AND user_id = ?");
$stmt->bind_param("si", $source_room_code, $user_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    die("Room not found or access denied.");
}

// 2. Fetch source questions
$stmt = $conn->prepare("SELECT q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option 
                        FROM quiz_room_questions q 
                        JOIN quiz_rooms r ON q.room_id = r.id 
                        WHERE r.room_code = ?");
$stmt->bind_param("s", $source_room_code);
$stmt->execute();
$questionsResult = $stmt->get_result();
$questions = [];
while ($row = $questionsResult->fetch_assoc()) {
    $questions[] = $row;
}
$stmt->close();

if (empty($questions)) {
    die("No questions found in the source room.");
}

// 3. Create NEW room
function generate_room_code($conn, $length = 6) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $exists = 0;
        $stmt = $conn->prepare("SELECT 1 FROM quiz_rooms WHERE room_code = ? LIMIT 1");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows;
        $stmt->close();
    } while ($exists > 0);
    return $code;
}

$new_room_code = generate_room_code($conn);

// Insert new room
$stmt = $conn->prepare("INSERT INTO quiz_rooms (room_code, class_id, book_id, mcq_count, user_id, quiz_duration_minutes, status, quiz_started, lobby_enabled) VALUES (?, ?, ?, ?, ?, ?, 'active', 0, 1)");
$stmt->bind_param("siiiii", $new_room_code, $room['class_id'], $room['book_id'], $room['mcq_count'], $user_id, $room['quiz_duration_minutes']);

if (!$stmt->execute()) {
    die("Failed to create new room: " . $conn->error);
}
$new_room_id = $stmt->insert_id;
$stmt->close();

// 4. Insert questions into new room
$ins = $conn->prepare("INSERT INTO quiz_room_questions (room_id, question, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($questions as $q) {
    $ins->bind_param('issssss', $new_room_id, $q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_option']);
    $ins->execute();
}
$ins->close();

// 5. Redirect to Dashboard (Room Detail)
header("Location: online_quiz_dashboard.php?room=" . urlencode($new_room_code));
exit;
?>
