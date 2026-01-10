<?php
// ajax_update_correct_option.php
// Updates the correct option for a specific MCQ
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $mcqId = intval($_POST['mcq_id'] ?? 0);
    $source = $_POST['source'] ?? '';
    $correctOption = $_POST['correct_option'] ?? '';

    if ($mcqId <= 0 || !in_array($source, ['standard', 'ai']) || !in_array(strtoupper($correctOption), ['A', 'B', 'C', 'D'])) {
        throw new Exception('Invalid parameters');
    }

    $tableName = ($source === 'ai') ? 'AIGeneratedMCQs' : 'mcqs';
    $idColumn = ($source === 'ai') ? 'id' : 'mcq_id';

    $sql = "UPDATE $tableName SET correct_option = ? WHERE $idColumn = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $correctOption, $mcqId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Correct option updated successfully']);
    } else {
        throw new Exception('Database update failed: ' . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
