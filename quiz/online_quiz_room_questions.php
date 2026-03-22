<?php
// online_quiz_room_questions.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$room_code = $_GET['room'] ?? '';

// Check room ownership
$stmt = $conn->prepare("SELECT id, mcq_count, class_id, book_id FROM quiz_rooms WHERE room_code = ? AND user_id = ?");
$stmt->bind_param("si", $room_code, $user_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    die("Room not found or access denied.");
}
$room_id = $room['id'];
$target_count = $room['mcq_count'];

// Initialize services for refill
require_once '../services/CacheManager.php';
require_once '../services/QuestionService.php';
require_once 'mcq_generator.php';

$cache = new CacheManager();
$questionService = new QuestionService($conn, $cache);

$msg = '';
$error = '';

if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete Question
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['question_id'])) {
        $del_id = (int)$_POST['question_id'];
        $del = $conn->prepare("DELETE FROM quiz_room_questions WHERE id = ? AND room_id = ?");
        $del->bind_param("ii", $del_id, $room_id);
        if ($del->execute()) {
            $msg = "Question deleted successfully.";
        } else {
            $error = "Failed to delete question.";
        }
        $del->close();
    }
    // Refill Questions
    elseif (isset($_POST['action']) && $_POST['action'] === 'refill') {
        $needed = (int)$_POST['needed_count'];
        $topic = trim($_POST['refill_topic']);
        
        if ($needed > 0 && !empty($topic)) {
            $added_count = 0;
            
            // Log related topics from generated_topics for wider search
            $related_topics = [$topic];
            $topic_search = "%$topic%";
            $gt_stmt = $conn->prepare("SELECT DISTINCT topic_name FROM generated_topics WHERE source_term LIKE ? OR topic_name LIKE ?");
            $gt_stmt->bind_param("ss", $topic_search, $topic_search);
            $gt_stmt->execute();
            $gt_res = $gt_stmt->get_result();
            while ($row = $gt_res->fetch_assoc()) {
                if (!empty($row['topic_name']) && !in_array($row['topic_name'], $related_topics)) {
                    $related_topics[] = $row['topic_name'];
                }
            }
            $gt_stmt->close();

            // Prepare for uniqueness check
            $existing_q = [];
            $e_stmt = $conn->prepare("SELECT question FROM quiz_room_questions WHERE room_id = ?");
            $e_stmt->bind_param("i", $room_id);
            $e_stmt->execute();
            $e_res = $e_stmt->get_result();
            while ($row = $e_res->fetch_assoc()) {
                $existing_q[] = $row['question'];
            }
            $e_stmt->close();
            
            $new_questions = [];
            $fetch_limit = $needed * 2;
            
            // 1. Search in mcqs table
            $placeholders = implode(',', array_fill(0, count($related_topics), '?'));
            $db_sql = "SELECT question, option_a, option_b, option_c, option_d, correct_option FROM mcqs WHERE topic IN ($placeholders) OR topic LIKE ? ORDER BY RAND() LIMIT ?";
            $db_stmt = $conn->prepare($db_sql);
            
            $types = str_repeat('s', count($related_topics)) . 'si';
            $params = array_merge($related_topics, [$topic_search, $fetch_limit]);
            $db_stmt->bind_param($types, ...$params);
            $db_stmt->execute();
            $db_res = $db_stmt->get_result();
            
            while ($row = $db_res->fetch_assoc()) {
                if ($added_count >= $needed) break;
                if (!in_array($row['question'], $existing_q)) {
                    $new_questions[] = $row;
                    $existing_q[] = $row['question'];
                    $added_count++;
                }
            }
            $db_stmt->close();
            
            // 2. Search AIGeneratedMCQs table
            if ($added_count < $needed) {
                $rem = $needed - $added_count;
                $ai_sql = "SELECT question_text AS question, option_a, option_b, option_c, option_d, correct_option FROM AIGeneratedMCQs WHERE topic IN ($placeholders) OR topic LIKE ? ORDER BY RAND() LIMIT ?";
                $ai_stmt = $conn->prepare($ai_sql);
                
                $ai_params = array_merge($related_topics, [$topic_search, $rem]);
                $ai_stmt->bind_param($types, ...$ai_params);
                $ai_stmt->execute();
                $ai_res = $ai_stmt->get_result();
                while ($row = $ai_res->fetch_assoc()) {
                    if ($added_count >= $needed) break;
                    if (!in_array($row['question'], $existing_q)) {
                        $new_questions[] = $row;
                        $existing_q[] = $row['question'];
                        $added_count++;
                    }
                }
                $ai_stmt->close();
            }
            
            // 3. Generate with AI
            if ($added_count < $needed) {
                $rem = $needed - $added_count;
                if (function_exists('generateMCQsWithGemini')) {
                    // Generate rem + 1 buffer
                    $gen_mcqs = generateMCQsWithGemini($topic, $rem + 1);
                    if (!empty($gen_mcqs)) {
                        foreach ($gen_mcqs as $gen) {
                             if ($added_count >= $needed) break;
                             if (!in_array($gen['question'], $existing_q)) {
                                $new_questions[] = [
                                    'question' => $gen['question'],
                                    'option_a' => $gen['option_a'],
                                    'option_b' => $gen['option_b'],
                                    'option_c' => $gen['option_c'],
                                    'option_d' => $gen['option_d'],
                                    'correct_option' => $gen['correct_option']
                                ];
                                $existing_q[] = $gen['question'];
                                $added_count++;
                             }
                        }
                    }
                }
            }
            
            // Insert new questions
            if (!empty($new_questions)) {
                $ins = $conn->prepare("INSERT INTO quiz_room_questions (room_id, question, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($new_questions as $nq) {
                    $ins->bind_param("issssss", $room_id, $nq['question'], $nq['option_a'], $nq['option_b'], $nq['option_c'], $nq['option_d'], $nq['correct_option']);
                    $ins->execute();
                }
                $ins->close();
                $msg = "Successfully added " . count($new_questions) . " questions.";
            } else {
                $error = "Could not find or generate enough questions for topic: $topic";
            }
            
        } else {
            $error = "Please provide a valid topic and count.";
        }
    }
    // Update Question
    elseif (isset($_POST['question_id']) && !isset($_POST['action'])) {
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
    
    // Redirect to prevent form resubmission
    if ($msg) $_SESSION['msg'] = $msg;
    if ($error) $_SESSION['error'] = $error;
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?room=" . urlencode($room_code));
    exit();
}

// Fetch questions
$stmt = $conn->prepare("SELECT * FROM quiz_room_questions WHERE room_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$all_questions = [];
while ($row = $result->fetch_assoc()) {
    $all_questions[] = $row;
}
$stmt->close();

// Fetch quiz status & locked question set
$quiz_is_started = false;
$active_id_set = [];

// Ensure column exists to avoid fatal errors (compatible way)
$check_col = $conn->query("SHOW COLUMNS FROM quiz_rooms LIKE 'active_question_ids'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE quiz_rooms ADD active_question_ids TEXT DEFAULT NULL");
}

$qstatus_stmt = $conn->prepare("SELECT quiz_started, active_question_ids FROM quiz_rooms WHERE id = ?");
$qstatus_stmt->bind_param("i", $room_id);
$qstatus_stmt->execute();
$qstatus_row = $qstatus_stmt->get_result()->fetch_assoc();
$qstatus_stmt->close();

if ($qstatus_row) {
    $quiz_is_started = (bool)($qstatus_row['quiz_started'] ?? false);
    $active_ids_json = $qstatus_row['active_question_ids'] ?? null;
    if ($active_ids_json) {
        $decoded = json_decode($active_ids_json, true);
        if (is_array($decoded)) {
            $active_id_set = array_flip($decoded); // for O(1) lookup
        }
    }
}


$current_count = count($all_questions);
$missing_count = max(0, $target_count - $current_count);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Room Questions - <?= htmlspecialchars($room_code) ?></title>
    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --secondary: #f3f4f6;
            --secondary-hover: #e5e7eb;
            --danger: #fee2e2;
            --danger-text: #991b1b;
            --danger-border: #fca5a5;
            --success: #dcfce7;
            --success-text: #166534;
            --warning-bg: #fff7ed;
            --warning-border: #fdba74;
            --warning-text: #c2410c;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
        }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.5;
        }
        .container { 
            max-width: 900px; 
            margin: 40px auto; 
            padding: 30px; 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); 
        }
        h1 {
            font-size: 1.875rem;
            font-weight: 800;
            color: var(--gray-800);
            margin: 0;
        }
        .question-card { 
            background: white;
            border: 1px solid var(--gray-200); 
            padding: 24px; 
            border-radius: 12px; 
            margin-bottom: 24px; 
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .question-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            border-color: var(--gray-300);
            transform: translateY(-2px);
        }
        .form-group { margin-bottom: 16px; }
        .form-label { 
            display: block; 
            font-weight: 600; 
            margin-bottom: 8px; 
            color: var(--gray-700);
            font-size: 0.875rem;
        }
        .form-input { 
            width: 100%; 
            padding: 10px 12px; 
            border: 1px solid var(--gray-300); 
            border-radius: 6px; 
            font-size: 0.95rem;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            box-sizing: border-box;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .btn { 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px; 
            border-radius: 6px; 
            cursor: pointer; 
            border: none; 
            font-weight: 600; 
            font-size: 0.875rem;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary { 
            background: var(--primary); 
            color: white; 
        }
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        .btn-secondary { 
            background: var(--secondary); 
            color: var(--gray-700); 
        }
        .btn-secondary:hover {
            background: var(--secondary-hover);
        }
        .btn-danger { 
            background: var(--danger); 
            color: var(--danger-text); 
            border: 1px solid var(--danger-border); 
        }
        .btn-danger:hover {
            background: #fecaca;
        }
        .alert { 
            padding: 16px; 
            border-radius: 8px; 
            margin-bottom: 24px; 
            border-left: 4px solid transparent;
        }
        .alert-success { 
            background: var(--success); 
            color: var(--success-text); 
            border-left-color: #059669;
        }
        .alert-error { 
            background: var(--danger); 
            color: var(--danger-text); 
            border-left-color: #dc2626;
        }
        .status-bar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            padding: 16px 24px; 
            background: var(--gray-100); 
            border-radius: 8px; 
            border: 1px solid var(--gray-200);
        }
        .refill-box { 
            margin-bottom: 30px; 
            padding: 24px; 
            background: var(--warning-bg); 
            border: 1px solid var(--warning-border); 
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .refill-box h3 {
            margin-top: 0;
            margin-bottom: 12px;
            color: var(--warning-text);
            font-size: 1.25rem;
        }
        .options-grid {
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px;
        }
        .question-header {
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 16px;
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: 12px;
        }
        @media (max-width: 640px) {
            .options-grid { grid-template-columns: 1fr; }
            .container { padding: 15px; margin: 10px; }
            .status-bar { flex-direction: column; gap: 10px; align-items: flex-start; }
            .question-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>
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

            <div class="status-bar">
                <div>
                    <strong>Status:</strong> <?= $current_count ?> / <?= $target_count ?> Questions
                </div>
            <?php if ($missing_count > 0): ?>
                    <div style="color: #c2410c; font-weight: bold;">
                        ⚠️ Missing <?= $missing_count ?> questions
                    </div>
                <?php else: ?>
                    <div style="color: #15803d; font-weight: bold;">
                        ✅ Quiz Complete
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($quiz_is_started && !empty($active_id_set)): ?>
            <div style="background: #eff6ff; border: 2px solid #3b82f6; border-radius: 12px; padding: 18px 22px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 14px;">
                <span style="font-size: 1.6rem; flex-shrink: 0;">🔒</span>
                <div>
                    <strong style="color: #1d4ed8; font-size: 1rem;">Quiz is Live — Edits Here Don't Affect Students</strong>
                    <p style="margin: 6px 0 0; color: #1e40af; font-size: 0.9rem;">
                        When the quiz started, <strong><?= count($active_id_set) ?></strong> questions were locked into the active quiz set.
                        Any edits or deletions you make here <em>do not</em> change what participants are currently seeing.
                        Questions marked <span style="background:#dcfce7;color:#166534;padding:1px 7px;border-radius:20px;font-size:0.8rem;font-weight:700;">IN ACTIVE QUIZ</span>
                        are the ones students are answering. Questions marked
                        <span style="background:#f3f4f6;color:#6b7280;padding:1px 7px;border-radius:20px;font-size:0.8rem;font-weight:700;">EXTRA POOL</span>
                        were added as alternates but are not part of this session.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($missing_count > 0): ?>
            <div class="refill-box">
                <h3 style="margin-top: 0; color: #c2410c;">Add Missing Questions</h3>
                <p>You need <strong><?= $missing_count ?></strong> more questions to reach the target count.</p>
                <form method="POST" style="display: flex; gap: 10px; align-items: flex-end;">
                    <input type="hidden" name="action" value="refill">
                    <input type="hidden" name="needed_count" value="<?= $missing_count ?>">
                    
                    <div style="flex-grow: 1;">
                        <label class="form-label">Topic for new questions:</label>
                        <input type="text" name="refill_topic" class="form-input" placeholder="e.g. Organic Chemistry, Newton's Laws..." required>
                    </div>
                    
                    <button type="submit" class="btn" style="background: #ea580c; color: white;">
                        ✨ Add <?= $missing_count ?> Questions 
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <?php foreach ($all_questions as $q):
                $is_active = $quiz_is_started && isset($active_id_set[$q['id']]);
                $is_extra  = $quiz_is_started && !$is_active;
                $card_border = $is_extra ? 'border-color: #d1d5db; opacity: 0.75;' : '';
            ?>
                <form method="POST" class="question-card" style="<?= $card_border ?>">
                    <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                    
                    <div class="form-group">
                        <div class="question-header">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label class="form-label" style="margin-bottom: 0;">Question</label>
                                <?php if ($is_active): ?>
                                    <span style="background:#dcfce7;color:#166534;padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;">✅ IN ACTIVE QUIZ</span>
                                <?php elseif ($is_extra): ?>
                                    <span style="background:#f3f4f6;color:#6b7280;padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;">〇 EXTRA POOL</span>
                                <?php endif; ?>
                            </div>
                            <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this question?');" style="padding: 6px 12px; font-size: 0.8em;">
                                🗑️ Delete
                            </button>
                        </div>
                        <input type="text" name="question_text" class="form-input" value="<?= htmlspecialchars($q['question']) ?>" required>
                    </div>
                    
                    <div class="options-grid">
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
                            $co = trim($q['correct_option'] ?? '');
                            $current_letter = 'A';
                            
                            // Check if stored as letter
                            if (in_array(strtoupper($co), ['A','B','C','D'])) {
                                $current_letter = strtoupper($co);
                            } else {
                                // Match by text if stored as full option
                                if ($co === trim($q['option_b'])) $current_letter = 'B';
                                elseif ($co === trim($q['option_c'])) $current_letter = 'C';
                                elseif ($co === trim($q['option_d'])) $current_letter = 'D';
                                elseif ($co === trim($q['option_a'])) $current_letter = 'A';
                            }
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
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
