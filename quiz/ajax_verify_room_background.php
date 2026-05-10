<?php
/**
 * ajax_verify_room_background.php
 * Background verification for quiz room questions
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mcq_generator.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);

$roomId = intval($jsonInput['room_id'] ?? 0);

if ($roomId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Room ID']);
    exit;
}

try {
    $res = verifyQuizRoomQuestions($conn, $roomId);
    echo json_encode($res);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
