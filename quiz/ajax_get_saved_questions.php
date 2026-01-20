<?php
// ajax_get_saved_questions.php - Fetch saved questions for the current user
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

try {
    $stmt = $conn->prepare("SELECT id, question_text as question, option_a, option_b, option_c, option_d, correct_option as correct FROM user_saved_questions WHERE user_id = ? ORDER BY created_at DESC");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    
    echo json_encode(['success' => true, 'questions' => $questions]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>