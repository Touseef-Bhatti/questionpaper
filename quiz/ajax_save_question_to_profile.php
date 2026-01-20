<?php
// ajax_save_question_to_profile.php
require_once '../auth/auth_check.php';
include '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$question = trim($_POST['question'] ?? '');
$option_a = trim($_POST['option_a'] ?? '');
$option_b = trim($_POST['option_b'] ?? '');
$option_c = trim($_POST['option_c'] ?? '');
$option_d = trim($_POST['option_d'] ?? '');
$correct_option = strtoupper(trim($_POST['correct_option'] ?? ''));

if (empty($question) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!in_array($correct_option, ['A', 'B', 'C', 'D'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid correct option']);
    exit;
}

// Check for duplicates
$stmt = $conn->prepare("SELECT id FROM user_saved_questions WHERE user_id = ? AND question_text = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $user_id, $question);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Question already exists in your profile']);
    exit;
}
$stmt->close();

$stmt = $conn->prepare("INSERT INTO user_saved_questions (user_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("issssss", $user_id, $question, $option_a, $option_b, $option_c, $option_d, $correct_option);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Question saved to profile successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
?>
