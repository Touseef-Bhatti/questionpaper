<?php
// online_quiz_room_questions.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$room_code = $_GET['room'] ?? '';

// Check room ownership
$stmt = $conn->prepare("SELECT id FROM quiz_rooms WHERE room_code = ? AND user_id = ?");
$stmt->bind_param("si", $room_code, $user_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    die("Room not found or access denied.");
}
$room_id = $room['id'];

$msg = '';
$error = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question_id'])) {
    $q_id = (int)$_POST['question_id'];
    $correct_letter = $_POST['correct_option_letter'];
    $q_text = $_POST['question_text'];
    $op_a = $_POST['option_a'];
    $op_b = $_POST['option_b'];
    $op_c = $_POST['option_c'];
    $op_d = $_POST['option_d'];
    
    // Map letter to correct text
    $correct_text = $op_a; // Default to A
    if ($correct_letter === 'B') $correct_text = $op_b;
    else if ($correct_letter === 'C') $correct_text = $op_c;
    else if ($correct_letter === 'D') $correct_text = $op_d;
    
    $upd = $conn->prepare("UPDATE quiz_room_questions SET question=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=? WHERE id=? AND room_id=?");
    $upd->bind_param("ssssssii", $q_text, $op_a, $op_b, $op_c, $op_d, $correct_text, $q_id, $room_id);
    
    if ($upd->execute()) {
        $msg = "Question updated successfully.";
    } else {
        $error = "Error updating question.";
    }
    $upd->close();
}

// Fetch questions
$stmt = $conn->prepare("SELECT * FROM quiz_room_questions WHERE room_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$questions = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Room Questions - <?= htmlspecialchars($room_code) ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .container { max-width: 900px; margin: 20px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .question-card { border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .form-group { margin-bottom: 10px; }
        .form-label { display: block; font-weight: 600; margin-bottom: 5px; }
        .form-input { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; }
        .btn { padding: 8px 16px; border-radius: 4px; cursor: pointer; border: none; font-weight: 600; }
        .btn-primary { background: #4f46e5; color: white; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <?php include '../header.php'; ?>
    <div class="main-content">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1>Edit Questions for Room <?= htmlspecialchars($room_code) ?></h1>
               <a href="online_quiz_dashboard.php?room=<?= urlencode($room_code) ?>"
   class="btn btn-secondary"
   style="color: #374151;">
   Back to Dashboard
</a>

            </div>

            <?php if ($msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php while ($q = $questions->fetch_assoc()): ?>
                <form method="POST" class="question-card">
                    <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Question</label>
                        <input type="text" name="question_text" class="form-input" value="<?= htmlspecialchars($q['question']) ?>" required>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label class="form-label">Option A</label>
                            <input type="text" name="option_a" class="form-input" value="<?= htmlspecialchars($q['option_a']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Option B</label>
                            <input type="text" name="option_b" class="form-input" value="<?= htmlspecialchars($q['option_b']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Option C</label>
                            <input type="text" name="option_c" class="form-input" value="<?= htmlspecialchars($q['option_c']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Option D</label>
                            <input type="text" name="option_d" class="form-input" value="<?= htmlspecialchars($q['option_d']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Correct Option</label>
                        <select name="correct_option_letter" class="form-input" required>
                            <?php
                            $current_letter = 'A';
                            if ($q['correct_option'] == $q['option_b']) $current_letter = 'B';
                            if ($q['correct_option'] == $q['option_c']) $current_letter = 'C';
                            if ($q['correct_option'] == $q['option_d']) $current_letter = 'D';
                            ?>
                            <option value="A" <?= $current_letter == 'A' ? 'selected' : '' ?>>Option A</option>
                            <option value="B" <?= $current_letter == 'B' ? 'selected' : '' ?>>Option B</option>
                            <option value="C" <?= $current_letter == 'C' ? 'selected' : '' ?>>Option C</option>
                            <option value="D" <?= $current_letter == 'D' ? 'selected' : '' ?>>Option D</option>
                        </select>
                        <small style="color: #6b7280;">Note: Select which option (A, B, C, or D) is correct.</small>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" class="btn btn-primary">Update Question</button>
                    </div>
                </form>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
