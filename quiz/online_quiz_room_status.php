<?php
// online_quiz_room_status.php - Toggle room status (open/close)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

function back_to($code){
  header('Location: online_quiz_dashboard.php?room=' . urlencode($code));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: online_quiz_dashboard.php');
  exit;
}

$room_code = strtoupper(trim($_POST['room_code'] ?? ''));
$action = strtolower(trim($_POST['action'] ?? ''));
if ($room_code === '' || !in_array($action, ['open','close'], true)) {
  back_to($room_code ?: '');
}

$status = $action === 'open' ? 'active' : 'closed';
$stmt = $conn->prepare("UPDATE quiz_rooms SET status = ? WHERE room_code = ?");
$stmt->bind_param('ss', $status, $room_code);
$stmt->execute();
$stmt->close();

back_to($room_code);
