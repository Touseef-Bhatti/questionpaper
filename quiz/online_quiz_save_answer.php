<?php
// online_quiz_save_answer.php - Saves a single answer for live scoring
session_start();
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$room_code = $_POST['room_code'] ?? '';
$roll_number = $_POST['roll_number'] ?? ''; 
$question_id = (int)($_POST['question_id'] ?? 0);
$selected_option = $_POST['selected_option'] ?? ''; // Expect 'A', 'B', 'C', 'D'

if (empty($room_code) || $question_id <= 0 || empty($selected_option)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

// Get participant ID securely
$participant_id = isset($_SESSION['quiz_participant_id']) ? (int)$_SESSION['quiz_participant_id'] : 0;
if ($participant_id <= 0) {
    // Fallback: lookup by roll number and room
    $stmt = $conn->prepare("SELECT p.id, p.room_id FROM quiz_participants p JOIN quiz_rooms r ON p.room_id = r.id WHERE r.room_code = ? AND p.roll_number = ?");
    $stmt->bind_param('ss', $room_code, $roll_number);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $participant_id = $row['id'];
        $room_id = $row['room_id'];
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Participant not found']);
        exit;
    }
    $stmt->close();
} else {
    // Get room_id from session or query
     $room_id = isset($_SESSION['quiz_room_id']) ? (int)$_SESSION['quiz_room_id'] : 0;
}

// Verify correctness
$is_correct = 0;
$stmt = $conn->prepare("SELECT option_a, option_b, option_c, option_d, correct_option FROM quiz_room_questions WHERE id = ?");
$stmt->bind_param('i', $question_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $selectedText = '';
    switch($selected_option) {
        case 'A': $selectedText = $row['option_a']; break;
        case 'B': $selectedText = $row['option_b']; break;
        case 'C': $selectedText = $row['option_c']; break;
        case 'D': $selectedText = $row['option_d']; break;
    }
    
    // Check if correct_option matches selectedText
    // Note: correct_option in DB is the text of the correct answer
    if (trim($selectedText) === trim($row['correct_option'])) {
        $is_correct = 1;
    }
}
$stmt->close();

// Upsert into quiz_responses
$sql = "INSERT INTO quiz_responses (participant_id, question_id, selected_option, is_correct) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), is_correct = VALUES(is_correct)";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iisi', $participant_id, $question_id, $selected_option, $is_correct);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'is_correct' => $is_correct]);
    
    // Update participant's current score in quiz_participants table
    $countSql = "SELECT COUNT(*) as score FROM quiz_responses WHERE participant_id = ? AND is_correct = 1";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param('i', $participant_id);
    $countStmt->execute();
    $cRes = $countStmt->get_result();
    $score = 0;
    if ($cRow = $cRes->fetch_assoc()) {
        $score = $cRow['score'];
    }
    $countStmt->close();
    
    // Update participant record
    $upd = $conn->prepare("UPDATE quiz_participants SET score = ?, last_activity = CURRENT_TIMESTAMP WHERE id = ?");
    $upd->bind_param('ii', $score, $participant_id);
    $upd->execute();
    $upd->close();
    
    // Log live event
    $event_type = 'question_answered';
    $event_data = json_encode(['question_id' => $question_id, 'is_correct' => $is_correct]);
    $log = $conn->prepare("INSERT INTO live_quiz_events (room_id, participant_id, event_type, event_data) VALUES (?, ?, ?, ?)");
    if ($log) {
        $log->bind_param('iiss', $room_id, $participant_id, $event_type, $event_data);
        $log->execute();
        $log->close();
    }
    
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}
$stmt->close();
?>
