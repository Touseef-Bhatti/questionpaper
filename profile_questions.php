<?php
// profile_questions.php - Manage saved custom questions
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);
$message = '';

// Handle deletion
if (isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM user_saved_questions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $del_id, $user_id);
    if ($stmt->execute()) {
        $message = "Question deleted successfully.";
    } else {
        $message = "Error deleting question.";
    }
}

// Handle addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $q_text = trim($_POST['question_text']);
    $op_a = trim($_POST['option_a']);
    $op_b = trim($_POST['option_b']);
    $op_c = trim($_POST['option_c']);
    $op_d = trim($_POST['option_d']);
    $correct = strtoupper(trim($_POST['correct_option']));

    if ($q_text && $op_a && $op_b && $op_c && $op_d && in_array($correct, ['A','B','C','D'])) {
        $stmt = $conn->prepare("INSERT INTO user_saved_questions (user_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $user_id, $q_text, $op_a, $op_b, $op_c, $op_d, $correct);
        if ($stmt->execute()) {
            $message = "Question saved successfully.";
        } else {
            $message = "Error saving question.";
        }
    } else {
        $message = "Please fill all fields and select a valid correct option.";
    }
}

// Fetch questions
$result = $conn->query("SELECT * FROM user_saved_questions WHERE user_id = $user_id ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Saved Questions - Ahmad Learning Hub</title>
    <link rel="stylesheet" href="css/main.css">
    <?php include 'header.php'; ?>
    <style>
        .container { max-width: 900px; margin: 30px auto; padding: 20px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-control { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; color: white; }
        .btn-primary { background: #4f46e5; }
        .btn-danger { background: #ef4444; }
        .question-item { border-bottom: 1px solid #eee; padding: 15px 0; }
        .question-item:last-child { border-bottom: none; }
        .badge { background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h2>Add New Question</h2>
        <?php if ($message): ?>
            <div style="padding: 10px; background: #e0f2fe; color: #0369a1; border-radius: 5px; margin-bottom: 15px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Question Text</label>
                <textarea name="question_text" class="form-control" rows="3" required></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Option A</label>
                    <input type="text" name="option_a" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Option B</label>
                    <input type="text" name="option_b" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Option C</label>
                    <input type="text" name="option_c" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Option D</label>
                    <input type="text" name="option_d" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Correct Option</label>
                <select name="correct_option" class="form-control" required>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
            </div>
            <button type="submit" name="add_question" class="btn btn-primary">Save Question</button>
            <a href="profile.php" class="btn" style="background: #6b7280; text-decoration: none;">Back to Profile</a>
        </form>
    </div>

    <div class="card">
        <h2>Your Saved Questions</h2>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="question-item">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <strong><?= htmlspecialchars($row['question_text']) ?></strong>
                            <div style="margin-top: 5px; color: #555;">
                                A) <?= htmlspecialchars($row['option_a']) ?> | 
                                B) <?= htmlspecialchars($row['option_b']) ?> | 
                                C) <?= htmlspecialchars($row['option_c']) ?> | 
                                D) <?= htmlspecialchars($row['option_d']) ?>
                            </div>
                            <div style="margin-top: 5px;">
                                Correct: <span class="badge"><?= htmlspecialchars($row['correct_option']) ?></span>
                            </div>
                        </div>
                        <form method="POST" onsubmit="return confirm('Delete this question?');">
                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.9em;">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No saved questions yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>