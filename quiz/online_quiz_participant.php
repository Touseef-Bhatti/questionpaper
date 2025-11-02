<?php
// online_quiz_participant.php - Detailed view of a participant's responses
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

$pid = intval($_GET['pid'] ?? 0);
if ($pid <= 0) {
  http_response_code(400);
  echo '<h2 style="color:red;">Missing participant ID</h2>';
  exit;
}

// Load participant and room
$stmt = $conn->prepare("SELECT p.id, p.name, p.roll_number, p.started_at, p.finished_at, p.score, p.total_questions, r.room_code, r.class_id, r.book_id
                        FROM quiz_participants p
                        JOIN quiz_rooms r ON r.id = p.room_id
                        WHERE p.id = ?");
$stmt->bind_param('i', $pid);
$stmt->execute();
$participant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$participant) {
  echo '<h2 style="color:red;">Participant not found</h2>';
  exit;
}

$room_code = $participant['room_code'];

// Load responses with questions
$q = $conn->prepare("SELECT qrq.id as question_id, qrq.question, qrq.option_a, qrq.option_b, qrq.option_c, qrq.option_d, qrq.correct_option,
                            r.selected_option, r.is_correct, r.time_spent_sec
                     FROM quiz_room_questions qrq
                     LEFT JOIN quiz_responses r ON r.question_id = qrq.id AND r.participant_id = ?
                     JOIN quiz_participants p ON p.room_id = qrq.room_id AND p.id = ?
                     ORDER BY qrq.id ASC");
$q->bind_param('ii', $pid, $pid);
$q->execute();
$responses = $q->get_result();
$q->close();

// Class and book names
$class_name = 'Unknown Class';
$book_name = 'Unknown Book';
$s = $conn->prepare("SELECT class_name FROM class WHERE class_id = ?");
$s->bind_param('i', $participant['class_id']);
$s->execute();
$r = $s->get_result();
if ($row = $r->fetch_assoc()) $class_name = $row['class_name'];
$s->close();
$s = $conn->prepare("SELECT book_name FROM book WHERE book_id = ?");
$s->bind_param('i', $participant['book_id']);
$s->execute();
$r = $s->get_result();
if ($row = $r->fetch_assoc()) $book_name = $row['book_name'];
$s->close();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Participant Details - QPaperGen</title>
  <link rel="stylesheet" href="../css/main.css">
  <style>
    .container-narrow { max-width: 1100px; margin: 24px auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); padding: 20px; }
    .muted { color: #6b7280; }
    .btn { padding: 8px 14px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .btn.secondary { background: #e9eef8; color: #2d3e50; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 700; }
    .badge.correct { background: #dcfce7; color: #166534; }
    .badge.incorrect { background: #fee2e2; color: #991b1b; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    th { background: #f9fafb; font-weight: 700; color: #374151; }
    .qtext { font-weight: 600; }
    .opt { margin: 2px 0; }
  </style>
</head>
<body>
<?php include '../header.php'; ?>
<div class="main-content">
  <div class="container-narrow">
    <div style="display:flex; justify-content: space-between; align-items:center; flex-wrap: wrap; gap: 10px;">
      <div>
        <h1 style="margin:0;">Participant: <?= h($participant['name']) ?> (<?= h($participant['roll_number']) ?>)</h1>
        <div class="muted">Room <?= h($room_code) ?> • Class: <?= h($class_name) ?> • Book: <?= h($book_name) ?></div>
        <div class="muted">Started: <?= h($participant['started_at']) ?> • Finished: <?= h($participant['finished_at']) ?></div>
        <div class="muted">Score: <?= h((string)$participant['score']) ?> / <?= h((string)$participant['total_questions']) ?></div>
      </div>
      <div>
        <a class="btn secondary" href="online_quiz_dashboard.php?room=<?= h($room_code) ?>">Back to Room</a>
      </div>
    </div>

    <h2 style="margin-top:16px;">Responses</h2>
    <table>
      <thead>
        <tr>
          <th style="width:50%;">Question</th>
          <th>Selected</th>
          <th>Correct</th>
          <th>Time (s)</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($responses && $responses->num_rows > 0): while ($row = $responses->fetch_assoc()):
            $selectedLetter = $row['selected_option'];
            // Compute letters for correct
            $correctLetter = '';
            if ($row['correct_option'] === $row['option_a']) $correctLetter = 'A';
            else if ($row['correct_option'] === $row['option_b']) $correctLetter = 'B';
            else if ($row['correct_option'] === $row['option_c']) $correctLetter = 'C';
            else if ($row['correct_option'] === $row['option_d']) $correctLetter = 'D';
            $isCorrect = !is_null($row['is_correct']) ? (int)$row['is_correct'] === 1 : null;
      ?>
        <tr>
          <td>
            <div class="qtext"><?= h($row['question']) ?></div>
            <div class="opt">A) <?= h($row['option_a']) ?></div>
            <div class="opt">B) <?= h($row['option_b']) ?></div>
            <div class="opt">C) <?= h($row['option_c']) ?></div>
            <div class="opt">D) <?= h($row['option_d']) ?></div>
          </td>
          <td>
            <?php if ($selectedLetter): ?>
              <span class="badge <?= $isCorrect ? 'correct' : 'incorrect' ?>"><?= h($selectedLetter) ?></span>
            <?php else: ?>
              <span class="muted">Not answered</span>
            <?php endif; ?>
          </td>
          <td><?= h($correctLetter) ?></td>
          <td><?= h((string)($row['time_spent_sec'] ?? '')) ?></td>
        </tr>
      <?php endwhile; else: ?>
        <tr><td colspan="4" class="muted">No responses found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include '../footer.php'; ?>
</body>
</html>
