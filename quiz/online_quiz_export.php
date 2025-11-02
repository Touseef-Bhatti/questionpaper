<?php
// online_quiz_export.php - Export room participants to CSV
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

$room_code = strtoupper(trim($_GET['room'] ?? ''));
if ($room_code === '') {
  http_response_code(400);
  echo 'Missing room code';
  exit;
}

// Find room
$stmt = $conn->prepare("SELECT id, room_code FROM quiz_rooms WHERE room_code = ?");
$stmt->bind_param('s', $room_code);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
  http_response_code(404);
  echo 'Room not found';
  exit;
}

$room_id = (int)$room['id'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="room_' . $room_code . '_results.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Name', 'Roll Number', 'Score', 'Total Questions', 'Percentage', 'Started At', 'Finished At']);

$pstmt = $conn->prepare("SELECT name, roll_number, score, total_questions, started_at, finished_at FROM quiz_participants WHERE room_id = ? ORDER BY started_at ASC");
$pstmt->bind_param('i', $room_id);
$pstmt->execute();
$res = $pstmt->get_result();
while ($row = $res->fetch_assoc()) {
  $score = is_null($row['score']) ? '' : (int)$row['score'];
  $total = is_null($row['total_questions']) ? '' : (int)$row['total_questions'];
  $pct = ($score !== '' && $total !== '' && $total > 0) ? round(($score/$total)*100, 2) : '';
  fputcsv($out, [
    $row['name'],
    $row['roll_number'],
    $score,
    $total,
    $pct,
    $row['started_at'],
    $row['finished_at']
  ]);
}
$pstmt->close();
fclose($out);
exit;
