<?php
// online_quiz_take.php - Student takes the quiz for a room
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

$room_code = strtoupper(trim($_GET['room'] ?? ''));
if ($room_code === '') {
    header('Location: online_quiz_join.php');
    exit;
}

// Validate room - also get quiz_started status and timing
$stmt = $conn->prepare("SELECT id, class_id, book_id, status, quiz_started, start_time, quiz_duration_minutes FROM quiz_rooms WHERE room_code = ?");
$stmt->bind_param('s', $room_code);
$stmt->execute();
$res = $stmt->get_result();
$room = $res->fetch_assoc();
$stmt->close();

if (!$room || $room['status'] !== 'active') {
    die('<h2 style="color:red;">Invalid or closed room.</h2>');
}
$room_id = (int)$room['id'];

// Review popup settings (reuse quiz.php behavior)
$isLoggedIn = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
$hasAlreadyReviewed = false;
if (
    (isset($_SESSION['quiz_review_submitted']) && $_SESSION['quiz_review_submitted'] === true) ||
    (isset($_SESSION['site_review_submitted']) && $_SESSION['site_review_submitted'] === true)
) {
    $hasAlreadyReviewed = true;
} elseif ($isLoggedIn) {
    $uid = (int)$_SESSION['user_id'];
    $rev = $conn->prepare("SELECT id FROM user_reviews WHERE user_id = ? LIMIT 1");
    if ($rev) {
        $rev->bind_param('i', $uid);
        $rev->execute();
        $r = $rev->get_result();
        if ($r && $r->num_rows > 0) {
            $hasAlreadyReviewed = true;
            $_SESSION['quiz_review_submitted'] = true;
            $_SESSION['site_review_submitted'] = true;
        }
        $rev->close();
    }
}

// Compute server-side elapsed and duration to make timer resilient across refreshes
$durationMin = isset($room['quiz_duration_minutes']) && (int)$room['quiz_duration_minutes'] > 0 ? (int)$room['quiz_duration_minutes'] : 30;
$durationSec = $durationMin * 60;
$startTs = isset($room['start_time']) ? strtotime($room['start_time']) : null;
$nowTs = time();
if (!$startTs || $startTs <= 0) { $startTs = $nowTs; }
$elapsedSec = max(0, $nowTs - $startTs);
if ($elapsedSec > $durationSec) { $elapsedSec = $durationSec; }

// Validate participant session - check both old and new session variable names
$participant_id = intval($_SESSION['quiz_participant_id'] ?? $_SESSION['participant_id'] ?? 0);
$participant_room_id = intval($_SESSION['quiz_room_id'] ?? $_SESSION['participant_room_id'] ?? 0);

if ($participant_id <= 0 || $participant_room_id !== $room_id) {
    // Try to find participant by session and room
    if ($participant_id > 0) {
        $check_stmt = $conn->prepare("SELECT room_id FROM quiz_participants WHERE id = ? AND room_id = ?");
        $check_stmt->bind_param('ii', $participant_id, $room_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            // Participant not found in this room, redirect to join
            $check_stmt->close();
            header('Location: online_quiz_join.php?room=' . urlencode($room_code));
            exit;
        }
        $check_stmt->close();
    } else {
        // No participant session at all
        header('Location: online_quiz_join.php?room=' . urlencode($room_code));
        exit;
    }
}

// Fetch participant roll number
$participant_roll = '';
if ($participant_id > 0) {
    $stmt = $conn->prepare("SELECT roll_number FROM quiz_participants WHERE id = ?");
    $stmt->bind_param('i', $participant_id);
    $stmt->execute();
    $stmt->bind_result($participant_roll);
    $stmt->fetch();
    $stmt->close();
}

// Fetch questions snapshot — use the locked question set if available
// This ensures ALL participants see the same questions, in the same seeded order,
// and host edits to quiz_room_questions after quiz start have NO effect.
$questions = [];

// Re-fetch room to get active_question_ids (column added at start time)
$room_ext = $conn->query("SHOW COLUMNS FROM quiz_rooms LIKE 'active_question_ids'")->num_rows > 0;

if ($room_ext) {
    $aq_stmt = $conn->prepare("SELECT active_question_ids FROM quiz_rooms WHERE id = ?");
    $aq_stmt->bind_param('i', $room_id);
    $aq_stmt->execute();
    $aq_row = $aq_stmt->get_result()->fetch_assoc();
    $aq_stmt->close();
    $active_ids = json_decode($aq_row['active_question_ids'] ?? 'null', true);
} else {
    $active_ids = null;
}

if (!empty($active_ids) && is_array($active_ids)) {
    // Locked set: fetch questions by their exact IDs
    $placeholders = implode(',', array_fill(0, count($active_ids), '?'));
    $types = str_repeat('i', count($active_ids));
    $stmt = $conn->prepare(
        "SELECT id as qrq_id, question, option_a, option_b, option_c, option_d, correct_option
         FROM quiz_room_questions
         WHERE room_id = ? AND id IN ($placeholders)"
    );
    $params = array_merge([$room_id], $active_ids);
    $types = 'i' . $types;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $by_id = [];
    while ($row = $res->fetch_assoc()) {
        $by_id[(int)$row['qrq_id']] = $row;
    }
    $stmt->close();

    // Re-order according to a per-participant deterministic shuffle
    // so each student sees a different order, but stable on refresh.
    foreach ($active_ids as $qid) {
        if (isset($by_id[$qid])) {
            $questions[] = $by_id[$qid];
        }
    }

    if (!empty($questions) && $participant_id > 0) {
        usort($questions, function($a, $b) use ($participant_id) {
            $ka = crc32($participant_id . '-' . $a['qrq_id']);
            $kb = crc32($participant_id . '-' . $b['qrq_id']);
            if ($ka === $kb) return 0;
            return ($ka < $kb) ? -1 : 1;
        });
    }
} else {
    // Fallback: quiz not started yet or legacy room — use seeded shuffle
    // RAND(room_id) keeps order consistent for all participants in the same room
    $stmt = $conn->prepare(
        "SELECT id as qrq_id, question, option_a, option_b, option_c, option_d, correct_option
         FROM quiz_room_questions
         WHERE room_id = ?
         ORDER BY RAND(?)"
    );
    $stmt->bind_param('ii', $room_id, $room_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmt->close();
}


if (empty($questions)) {
    die('<h2 style="color:red;">This room has no questions configured.</h2>');
}

// Load any existing responses for this participant (for restore on refresh)
$existingResponses = [];
if ($participant_id > 0) {
    $stmt = $conn->prepare("SELECT question_id, selected_option FROM quiz_responses WHERE participant_id = ?");
    $stmt->bind_param('i', $participant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $qid = (int)$row['question_id'];
        $opt = $row['selected_option'];
        if ($qid > 0 && $opt !== null && $opt !== '') {
            $existingResponses[$qid] = $opt;
        }
    }
    $stmt->close();
}

// Get class and book names
$class_name = 'Unknown Class';
$book_name = 'Unknown Book';
$stmt = $conn->prepare("SELECT class_name FROM class WHERE class_id = ?");
$stmt->bind_param('i', $room['class_id']);
$stmt->execute();
$r = $stmt->get_result();
if ($row = $r->fetch_assoc()) { $class_name = $row['class_name']; }
$stmt->close();
$stmt = $conn->prepare("SELECT book_name FROM book WHERE book_id = ?");
$stmt->bind_param('i', $room['book_id']);
$stmt->execute();
$r = $stmt->get_result();
if ($row = $r->fetch_assoc()) { $book_name = $row['book_name']; }
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - <?php echo htmlspecialchars($book_name); ?> | Ahmad Learning Hub</title>
    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ─── Copy Protection ─────────────────────────────────── */
        * { -webkit-user-select: none; -moz-user-select: none; user-select: none; box-sizing: border-box; }

        /* ─── Base ────────────────────────────────────────────── */
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; margin: 0; padding: 0; min-height: 100vh; }

        .main-content { padding: 40px 16px 80px; }

        /* ─── Quiz Wrapper ────────────────────────────────────── */
        .quiz-container {
            width: 70%;
            margin: 0 auto;
            background: #fff;
            border-radius: 28px;
            box-shadow: 0 20px 60px -10px rgba(79,70,229,0.15);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        /* ─── Header ──────────────────────────────────────────── */
        .quiz-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 28px 32px 24px;
        }
        .quiz-header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; gap: 20px; }
        .quiz-title-wrapper { flex: 1; }
        .quiz-title { font-size: 1.5rem; font-weight: 900; margin: 0; line-height: 1.2; display: flex; align-items: center; gap: 10px; }
        .quiz-subtitle { font-size: 0.9rem; opacity: 0.85; margin-top: 4px; }
        .quiz-timer-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 10px 18px;
            border-radius: 50px;
            font-size: 1.05rem;
            font-weight: 800;
            border: 1px solid rgba(255,255,255,0.2);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quiz-timer-badge.warning { background: rgba(245,158,11,0.25); color: #fef3c7; border-color: rgba(245,158,11,0.4); }
        .quiz-timer-badge.danger { background: rgba(239,68,68,0.25); color: #fee2e2; border-color: rgba(239,68,68,0.4); animation: pulse 1s infinite; }

        @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.03); opacity: 0.9; } }

        /* ─── Progress ────────────────────────────────────────── */
        .progress-track { height: 6px; background: rgba(255,255,255,0.2); position: relative; overflow: hidden; }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #34d399, #10b981);
            transition: width 0.5s cubic-bezier(0.4,0,0.2,1);
            width: 0%;
            box-shadow: 0 0 8px rgba(52,211,153,0.5);
        }
        .progress-text {
            font-size: 0.8rem;
            opacity: 0.85;
            margin-top: 8px;
            letter-spacing: 0.05em;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* ─── Body ────────────────────────────────────────────── */
        .quiz-body { padding: 36px 32px; }

        /* ─── Question Card ───────────────────────────────────── */
        .question-card {
            animation: slideIn 0.4s cubic-bezier(0.16,1,0.3,1);
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .question-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .q-badge {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            font-size: 0.8rem;
            font-weight: 800;
            padding: 6px 16px;
            border-radius: 50px;
            letter-spacing: 0.04em;
        }

        .question-text {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.6;
            margin-bottom: 28px;
            padding: 20px 24px;
            background: #f8fafc;
            border-radius: 18px;
            border-left: 5px solid #4f46e5;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        }

        /* ─── Options ─────────────────────────────────────────── */
        .options { display: flex; flex-direction: column; gap: 14px; }
        .option {
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 22px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .option:hover:not(.disabled) {
            border-color: #818cf8;
            background: #f5f3ff;
            transform: translateX(6px);
            box-shadow: 0 4px 12px rgba(79,70,229,0.1);
        }

        .option-label {
            width: 38px; height: 38px;
            border-radius: 12px;
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.95rem;
            color: #64748b;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }
        .option-text { font-size: 1.05rem; font-weight: 600; color: #1e293b; line-height: 1.4; flex: 1; }

        /* Selected State */
        .option.selected { 
            border-color: #4f46e5; 
            background: #eef2ff; 
            box-shadow: 0 0 0 1px #4f46e5, 0 4px 12px rgba(79,70,229,0.15);
        }
        .option.selected .option-label { 
            background: #4f46e5; 
            border-color: #4f46e5; 
            color: white;
            box-shadow: 0 0 10px rgba(79,70,229,0.4);
        }
        .option.selected .option-text { color: #312e81; }

        .option.disabled { cursor: not-allowed; pointer-events: none; opacity: 0.8; }

        /* ─── Actions ─────────────────────────────────────────── */
        .quiz-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #f1f5f9;
        }
        .btn-quiz {
            padding: 14px 32px;
            border: none;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        .btn-quiz.primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            box-shadow: 0 6px 16px rgba(79,70,229,0.3);
        }
        .btn-quiz.primary:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(79,70,229,0.4);
        }
        .btn-quiz.primary:active:not(:disabled) { transform: translateY(-1px); }
        .btn-quiz.primary:disabled { background: #94a3b8; box-shadow: none; cursor: not-allowed; opacity: 0.7; }
        
        .btn-quiz.secondary {
            background: #f8fafc;
            color: #1e293b;
            border: 2px solid #e2e8f0;
        }
        .btn-quiz.secondary:hover { border-color: #94a3b8; background: #f1f5f9; }

        /* ─── Results Screen ──────────────────────────────────── */
        .results-hero {
            text-align: center;
            padding: 56px 32px 40px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid #e2e8f0;
        }
        .result-emoji { font-size: 5rem; margin-bottom: 20px; display: block; animation: bounceIn 0.8s cubic-bezier(0.175,0.885,0.32,1.275); }
        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            60% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        .results-title { font-size: 2.25rem; font-weight: 900; color: #0f172a; margin: 0 0 12px; }
        .results-subtitle { color: #64748b; font-size: 1.1rem; margin: 0; font-weight: 500; }

        .result-score-ring {
            width: 160px; height: 160px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 36px auto;
            position: relative;
            box-shadow: 0 12px 32px rgba(0,0,0,0.08);
            border: 8px solid white;
        }
        .score-display {
            font-size: 3rem;
            font-weight: 900;
            color: #4f46e5;
            z-index: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 36px 32px;
        }
        .stat-card {
            background: #fff;
            border-radius: 20px;
            padding: 24px 20px;
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.correct-stat { border-color: #86efac; background: #f0fdf4; }
        .stat-card.incorrect-stat { border-color: #fca5a5; background: #fff5f5; }
        .stat-card.time-stat { border-color: #bfdbfe; background: #eff6ff; }
        
        .stat-value { font-size: 2.5rem; font-weight: 900; line-height: 1; margin-bottom: 8px; }
        .correct-stat .stat-value { color: #16a34a; }
        .incorrect-stat .stat-value { color: #dc2626; }
        .time-stat .stat-value { color: #2563eb; }
        
        .stat-label { font-size: 0.8rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; display: flex; align-items: center; justify-content: center; gap: 6px; }

        .results-actions {
            padding: 32px;
            display: flex;
            justify-content: center;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        /* ─── Responsive ──────────────────────────────────────── */
        @media (max-width: 640px) {
            .quiz-container { border-radius: 0; border: none;width: 90%; }
            .quiz-header { padding: 32px 20px 24px; }
            .quiz-body { padding: 32px 20px; }
            .stats-grid { grid-template-columns: 1fr; padding: 24px 20px; }
            .results-hero { padding: 48px 20px 32px; }
            .results-title { font-size: 1.75rem; }
            .score-display { font-size: 2.5rem; }
            .quiz-timer-badge { font-size: 0.95rem; padding: 8px 14px; }
        }

        .hidden { display: none !important; }
        .screen-lock-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(15, 23, 42, 0.92);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .screen-lock-card {
            max-width: 560px;
            width: 100%;
            background: #fff;
            border-radius: 24px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            border: 2px solid #fecaca;
        }
        .screen-lock-icon {
            width: 84px;
            height: 84px;
            margin: 0 auto 18px;
            border-radius: 999px;
            background: #fee2e2;
            color: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        .screen-lock-title {
            font-size: 1.6rem;
            font-weight: 900;
            color: #111827;
            margin-bottom: 10px;
        }
        .screen-lock-text {
            color: #4b5563;
            font-size: 1rem;
            line-height: 1.6;
        }

        /* ─── Leaderboard ─────────────────────────────────────── */
        .leaderboard-section { padding: 32px; border-top: 1px solid #e2e8f0; background: #fff; }
        .leaderboard-title { font-size: 1.25rem; font-weight: 900; color: #0f172a; margin: 0 0 14px; display: flex; align-items: center; gap: 10px; }
        .leaderboard-table { width: 100%; border-collapse: collapse; }
        .leaderboard-table th, .leaderboard-table td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; font-size: 0.95rem; }
        .leaderboard-table th { background: #f8fafc; font-weight: 800; color: #374151; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.06em; }
        .leaderboard-rank { font-weight: 900; color: #4f46e5; }
        .leaderboard-you { background: #eef2ff; }
        .leaderboard-muted { color: #6b7280; font-size: 0.85rem; margin-top: 6px; }

        /* ─── Review Modal (copied style from quiz.php) ───────── */
        .review-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 18px;
        }
        .review-modal.open { display: flex; }
        .review-modal-card {
            width: 100%;
            max-width: 560px;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #dbe3ef;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
            overflow: hidden;
        }
        .review-modal-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #ffffff;
            padding: 24px 26px 20px;
        }
        .review-modal-title {
            margin: 0 0 8px;
            font-size: 1.5rem;
            font-weight: 900;
            line-height: 1.2;
        }
        .review-modal-subtitle {
            margin: 0;
            font-size: 0.95rem;
            color: #eef2ff;
        }
        .review-modal-body { padding: 24px 26px 10px; }
        .star-row { display: flex; gap: 10px; margin-bottom: 16px; }
        .star-btn {
            width: 48px; height: 48px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #94a3b8;
            font-size: 1.3rem;
            cursor: pointer;
            transition: transform 0.18s ease, background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
        }
        .star-btn:hover { transform: translateY(-1px); border-color: #f59e0b; color: #f59e0b; background: #fff7ed; }
        .star-btn.active { border-color: #f59e0b; background: #fff7ed; color: #f59e0b; }
        .review-modal textarea {
            width: 100%;
            min-height: 125px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            padding: 14px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            color: #0f172a;
            resize: vertical;
            outline: none;
        }
        .review-modal textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14); }
        .review-modal-message { margin-top: 10px; font-size: 0.9rem; font-weight: 700; min-height: 24px; }
        .review-modal-message.error { color: #b91c1c; }
        .review-modal-message.success { color: #166534; }
        .review-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 0 26px 24px;
            flex-wrap: wrap;
        }
        .review-modal-actions .btn-quiz { min-width: 130px; justify-content: center; }
    </style>
</head>
<body>
<?php include_once '../header.php'; ?>

<!-- SIDE SKYSCRAPER ADS -->
<?= renderAd('skyscraper', 'Online Quiz Page Right Skyscraper', 'right', 'margin-top: 10%;') ?>
<div class="main-content">
  <!-- TOP AD BANNER -->
  <?= renderAd('banner', 'Online Quiz Page Top Banner', 'ad-placement-top', 'margin-bottom: 20px;') ?>

  <div class="quiz-container" style="margin-top: 10%;">
    <!-- Header with Progress -->
    <div class="quiz-header" id="quizHeader">
      <div class="quiz-header-top">
        <div class="quiz-title-wrapper">
          <div class="quiz-title">
            <i class="fas fa-bolt" style="color: #fbbf24;"></i>
            <span><?php echo htmlspecialchars($book_name); ?></span>
          </div>
          <div class="quiz-subtitle">
            <?php echo htmlspecialchars($class_name); ?> &nbsp;•&nbsp; Room: <?php echo htmlspecialchars($room_code); ?>
            <span id="proctorAlertBadge" class="hidden" style="margin-left: 10px; padding: 4px 10px; border-radius: 999px; background:#fee2e2; color:#b91c1c; font-size: 0.75rem; font-weight:700; text-transform:uppercase;">
              Suspicious activity recorded
            </span>
          </div>
        </div>
        <div class="quiz-timer-badge" id="timer">
          <i class="fas fa-clock"></i> 00:00
        </div>
      </div>
      <div class="progress-track"><div class="progress-fill" id="progressFill"></div></div>
      <div class="progress-text">
        Question <span id="currentQNum">1</span> of <?php echo count($questions); ?>
      </div>
    </div>

    <div class="quiz-body">
      <!-- Question Container -->
      <div id="questionContainer"></div>

      <!-- Actions -->
      <div class="quiz-actions" id="quizActions">
        <div style="font-size: 0.9rem; color: #64748b; font-weight: 600;">
          Roll: <span style="color: #4f46e5;"><?php echo htmlspecialchars($participant_roll); ?></span>
        </div>
        <button type="button" class="btn-quiz primary" id="nextBtn" onclick="nextQuestion()" disabled>
          <span>Next Question</span>
          <i class="fas fa-arrow-right"></i>
        </button>
      </div>

      <!-- Results screen wrapper -->
      <div class="hidden" id="resultsCard">
        <div class="results-hero">
            <span class="result-emoji">🎉</span>
            <h2 class="results-title">Quiz Completed!</h2>
            <p class="results-subtitle">Excellent effort! Here is your performance summary.</p>
            
            <div class="result-score-ring">
                <div class="score-display" id="scoreDisplay">0%</div>
            </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card correct-stat">
            <div class="stat-value" id="correctCount">0</div>
            <div class="stat-label"><i class="fas fa-check-circle"></i> Correct</div>
          </div>
          <div class="stat-card incorrect-stat">
            <div class="stat-value" id="incorrectCount">0</div>
            <div class="stat-label"><i class="fas fa-times-circle"></i> Errors</div>
          </div>
          <div class="stat-card time-stat">
            <div class="stat-value" id="totalTime">0:00</div>
            <div class="stat-label"><i class="fas fa-hourglass-half"></i> Total Time</div>
          </div>
        </div>

        <div class="results-actions">
          <button type="button" class="btn-quiz secondary" onclick="window.location.href='online_quiz_join.php?room=<?php echo urlencode($room_code); ?>'">
            <i class="fas fa-door-open"></i> Return to Join Page
          </button>
        </div>

        <div class="leaderboard-section">
          <div class="leaderboard-title"><i class="fas fa-trophy" style="color:#f59e0b;"></i> Leaderboard</div>
          <div class="leaderboard-muted">Ranking is based on marks first, then time taken.</div>
          <div style="overflow-x:auto; margin-top: 12px;">
            <table class="leaderboard-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Roll</th>
                  <th>Name</th>
                  <th>Score</th>
                  <th>Time</th>
                </tr>
              </thead>
              <tbody id="leaderboardBody">
                <tr><td colspan="5" class="leaderboard-muted">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- BOTTOM AD BANNER -->
  <div style="margin-top: 30px;">
    <?= renderAd('banner', 'Online Quiz Page Bottom Banner') ?>
  </div>
</div>

<div class="review-modal" id="reviewModal">
  <div class="review-modal-card">
    <div class="review-modal-header">
      <h3 class="review-modal-title">Rate Your Quiz Experience</h3>
      <p class="review-modal-subtitle">Your review helps us improve the platform for students and teachers.</p>
    </div>
    <div class="review-modal-body">
      <div class="star-row" id="starRow">
        <button type="button" class="star-btn" data-rating="1"><i class="fas fa-star"></i></button>
        <button type="button" class="star-btn" data-rating="2"><i class="fas fa-star"></i></button>
        <button type="button" class="star-btn" data-rating="3"><i class="fas fa-star"></i></button>
        <button type="button" class="star-btn" data-rating="4"><i class="fas fa-star"></i></button>
        <button type="button" class="star-btn" data-rating="5"><i class="fas fa-star"></i></button>
      </div>
      <textarea id="reviewFeedback" maxlength="1000" placeholder="Share your experience about this quiz..." oninput="updateCharCount()"></textarea>
      <div style="display: flex; justify-content: flex-end; margin-top: 4px;">
        <span id="charCount" style="font-size: 0.75rem; color: #94a3b8; font-weight: 500;">0 / 1000</span>
      </div>
      <?php if ($isLoggedIn): ?>
      <div style="margin-top: 10px; display: flex; align-items: center; gap: 8px;">
        <input type="checkbox" id="reviewAnonymous" style="width: 18px; height: 18px; cursor: pointer;">
        <label for="reviewAnonymous" style="font-size: 0.9rem; color: #334155; cursor: pointer; font-weight: 600;">Post review anonymously</label>
      </div>
      <?php endif; ?>
      <div class="review-modal-message" id="reviewMessage"></div>
    </div>
    <div class="review-modal-actions">
      <a href="../reviews.php" class="btn-quiz secondary" style="text-decoration: none;"><i class="fas fa-comments"></i> All Reviews</a>
      <button type="button" class="btn-quiz secondary" onclick="closeReviewModal()">Skip</button>
      <button type="button" class="btn-quiz primary" id="submitReviewBtn" onclick="submitReview()">Submit Review</button>
    </div>
  </div>
</div>

<div id="screenLockOverlay" class="screen-lock-overlay hidden">
  <div class="screen-lock-card">
    <div class="screen-lock-icon"><i class="fas fa-lock"></i></div>
    <div class="screen-lock-title">Screen Locked</div>
    <div class="screen-lock-text" id="screenLockMessage">
      Your screen has been locked by your teacher. Please wait for further instructions.
    </div>
  </div>
</div>

<script>

    // Original functions will be defined below, we'll let them initialize and then we can override if needed, 
    // but better to just replace them in place to avoid confusion.

const participantId = <?php echo json_encode($participant_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const roomCode = <?php echo json_encode($room_code, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const participantRoll = <?php echo json_encode($participant_roll ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const questions = <?php echo json_encode($questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const existingResponses = <?php echo json_encode($existingResponses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const hasAlreadyReviewedServer = <?php echo json_encode($hasAlreadyReviewed); ?>;
const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;
let currentQuestion = 0;
let score = 0;
let answers = [];
const durationSec = <?php echo (int)$durationSec; ?>;
const serverElapsedSec = <?php echo (int)$elapsedSec; ?>;
let remainingSec = Math.max(0, durationSec - serverElapsedSec);
let startTime = Date.now(); // For tracking time spent on questions
let questionStartTime = Date.now();
let screenLocked = false;
let selectedReviewRating = 0;
let reviewPopupShown = false;

let timerInterval = setInterval(updateTimer, 1000);
let statusInterval = setInterval(checkServerStatus, 5000);

function checkServerStatus() {
    const url = 'online_quiz_participant_status.php?room_code=' + encodeURIComponent(roomCode) +
                (participantId ? '&participant_id=' + encodeURIComponent(participantId) : '');
    fetch(url)
    .then(res => res.json())
    .then(data => {
        if (data.error) return;

        if (data.is_screen_locked) {
            lockStudentScreen(data.lock_message || 'Your screen has been locked by your teacher.');
        } else {
            unlockStudentScreen();
        }

        // If this participant has been force-ended/completed by the teacher, end immediately
        if (data.participant_status && (data.participant_status === 'completed' || data.participant_status === 'finished')) {
            if (!document.getElementById('resultsCard').classList.contains('hidden')) return;
            clearInterval(statusInterval);
            clearInterval(timerInterval);
            alert("Your quiz has been ended by your teacher.");
            showResults();
            return;
        }

        // If room is closed, force finish
        if (data.status === 'closed') {
             if (!document.getElementById('resultsCard').classList.contains('hidden')) return; 
             
             clearInterval(statusInterval);
             alert("The host has closed the quiz. Submitting your answers now.");
             showResults();
             return;
        }

        // Sync timer
        if (typeof data.remaining_seconds !== 'undefined') {
            const serverRemaining = parseInt(data.remaining_seconds);
            
            // If server says time is up
            if (serverRemaining <= 0) {
                 if (!document.getElementById('resultsCard').classList.contains('hidden')) return;
                 clearInterval(statusInterval);
                 alert("Time Over! Your quiz is being submitted automatically.");
                 showResults();
                 return;
            }

            // Allow small drift (e.g. 3 seconds) to avoid jitter, but hard sync if large difference
            if (Math.abs(remainingSec - serverRemaining) > 3) {
                remainingSec = serverRemaining;
            }
        }
    })
    .catch(err => console.error('Status check failed', err));
}

function lockStudentScreen(message) {
  screenLocked = true;
  const overlay = document.getElementById('screenLockOverlay');
  const messageEl = document.getElementById('screenLockMessage');
  if (messageEl) {
    messageEl.textContent = message || 'Your screen has been locked by your teacher.';
  }
  if (overlay) {
    overlay.classList.remove('hidden');
  }
  const nextBtn = document.getElementById('nextBtn');
  if (nextBtn) {
    nextBtn.disabled = true;
  }
}

function unlockStudentScreen() {
  if (!screenLocked) return;
  screenLocked = false;
  const overlay = document.getElementById('screenLockOverlay');
  if (overlay) {
    overlay.classList.add('hidden');
  }
  const savedAnswer = answers[currentQuestion];
  const nextBtn = document.getElementById('nextBtn');
  if (nextBtn) {
    nextBtn.disabled = !savedAnswer;
  }
}

function updateTimer() {
  remainingSec--;
  
  if (remainingSec <= 0) {
      remainingSec = 0;
      clearInterval(timerInterval);
      const timerEl = document.getElementById('timer');
      timerEl.textContent = "Time Remaining: 00:00";
      timerEl.className = 'timer danger';
      
      alert("Time Over! Your quiz is being submitted automatically.");
      showResults();
      return;
  }

  const minutes = Math.floor(remainingSec / 60);
  const seconds = remainingSec % 60;
  const timerEl = document.getElementById('timer');
  timerEl.textContent = `Time Remaining: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
  
  if (remainingSec < 60) timerEl.className = 'timer danger';
  else if (remainingSec < 300) timerEl.className = 'timer warning';
  else timerEl.className = 'timer';
}

function renderQuestion() {
  const q = questions[currentQuestion];
  const container = document.getElementById('questionContainer');
  
  // Check if this question is already answered
  const savedAnswer = answers[currentQuestion];
  const isAnswered = !!savedAnswer;
  
  // Update header question number
  const qNumDisplay = document.getElementById('currentQNum');
  if (qNumDisplay) qNumDisplay.textContent = currentQuestion + 1;

  container.innerHTML = `
    <div class="question-card">
      <div class="question-meta">
        <div class="q-badge">TOPIC: ROOM QUIZ</div>
        <div style="font-size: 0.85rem; color: #94a3b8; font-weight: 700;">PROCTORING ACTIVE</div>
      </div>
      <div class="question-text">${q.question}</div>
      <div class="options">
        <div class="option" data-option="A" onclick="selectOption('A')"><div class="option-label">A</div><div class="option-text">${q.option_a}</div></div>
        <div class="option" data-option="B" onclick="selectOption('B')"><div class="option-label">B</div><div class="option-text">${q.option_b}</div></div>
        <div class="option" data-option="C" onclick="selectOption('C')"><div class="option-label">C</div><div class="option-text">${q.option_c}</div></div>
        <div class="option" data-option="D" onclick="selectOption('D')"><div class="option-label">D</div><div class="option-text">${q.option_d}</div></div>
      </div>
    </div>`;
  const progress = ((currentQuestion) / questions.length) * 100;
  document.getElementById('progressFill').style.width = progress + '%';
  questionStartTime = Date.now();
  
  // Apply saved state if answered
  if (isAnswered) {
      const options = document.querySelectorAll('.option');
      options.forEach(opt => {
          const optionLetter = opt.dataset.option;
          if (optionLetter === savedAnswer.selected) {
              opt.classList.add('selected');
          }
          opt.style.pointerEvents = 'none';
      });
      
      // Enable Next button if answered
      document.getElementById('nextBtn').disabled = false;
  } else {
      document.getElementById('nextBtn').disabled = true;
  }
}

function selectOption(option) {
  if (screenLocked) return;
  const q = questions[currentQuestion];
  let selectedOptionText = '';
  switch(option) {
    case 'A': selectedOptionText = q.option_a; break;
    case 'B': selectedOptionText = q.option_b; break;
    case 'C': selectedOptionText = q.option_c; break;
    case 'D': selectedOptionText = q.option_d; break;
  }

  // Determine correct option letter. correct_option may be either:
  // - the letter 'A'/'B'/'C'/'D'
  // - or the full text of the correct answer.
  let correctOptionLetter = '';
  const coRaw = String(q.correct_option || '').trim();
  const coUpper = coRaw.toUpperCase();

  if (['A', 'B', 'C', 'D'].includes(coUpper)) {
    correctOptionLetter = coUpper;
  } else {
    const optAText = String(q.option_a || '').trim();
    const optBText = String(q.option_b || '').trim();
    const optCText = String(q.option_c || '').trim();
    const optDText = String(q.option_d || '').trim();

    if (coRaw.toLowerCase() === optAText.toLowerCase()) correctOptionLetter = 'A';
    else if (coRaw.toLowerCase() === optBText.toLowerCase()) correctOptionLetter = 'B';
    else if (coRaw.toLowerCase() === optCText.toLowerCase()) correctOptionLetter = 'C';
    else if (coRaw.toLowerCase() === optDText.toLowerCase()) correctOptionLetter = 'D';
  }

  const isCorrect = option === correctOptionLetter;

  let correctText = '';
  if (correctOptionLetter === 'A') correctText = q.option_a;
  else if (correctOptionLetter === 'B') correctText = q.option_b;
  else if (correctOptionLetter === 'C') correctText = q.option_c;
  else if (correctOptionLetter === 'D') correctText = q.option_d;

  answers[currentQuestion] = {
    question_id: q.qrq_id,
    selected: option,
    selectedText: selectedOptionText,
    correct: correctOptionLetter,
    correctText: correctText || q.correct_option,
    isCorrect: isCorrect,
    question: q.question,
    options: { A: q.option_a, B: q.option_b, C: q.option_c, D: q.option_d },
    timeSpent: Math.floor((Date.now() - questionStartTime) / 1000)
  };
  if (isCorrect) score++;
  
  // Save answer live
  const formData = new FormData();
  formData.append('room_code', roomCode);
  formData.append('roll_number', participantRoll);
  formData.append('question_id', q.qrq_id);
  formData.append('selected_option', option);
  
  fetch('online_quiz_save_answer.php', {
      method: 'POST',
      body: formData
  }).catch(err => console.error('Error saving answer:', err));
  
  const options = document.querySelectorAll('.option');
  options.forEach(opt => {
    const optionLetter = opt.dataset.option;
    if (optionLetter === option) {
      opt.classList.add('selected');
    }
    opt.style.pointerEvents = 'none';
  });
  document.getElementById('nextBtn').disabled = false;
  setTimeout(() => { nextQuestion(); }, 1500);
}

function nextQuestion() {
  if (screenLocked) return;
  currentQuestion++;
  if (currentQuestion >= questions.length) { showResults(); }
  else {
    document.getElementById('nextBtn').disabled = true;
    renderQuestion();
  }
}

async function showResults() {
  clearInterval(timerInterval);
  if (typeof statusInterval !== 'undefined') clearInterval(statusInterval);
  const totalTime = Math.floor((Date.now() - startTime) / 1000);
  const percentage = Math.round((score / questions.length) * 100);
  
  // UI Changes for Results
  document.getElementById('quizHeader').classList.add('hidden');
  document.getElementById('quizActions').classList.add('hidden');
  document.getElementById('questionContainer').classList.add('hidden');
  
  document.getElementById('resultsCard').classList.remove('hidden');
  document.getElementById('scoreDisplay').textContent = percentage + '%';
  document.getElementById('correctCount').textContent = score;
  document.getElementById('incorrectCount').textContent = questions.length - score;
  const minutes = Math.floor(totalTime / 60);
  const seconds = totalTime % 60;
  document.getElementById('totalTime').textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

  // Submit results to server
  try {
    await fetch('online_quiz_submit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ participant_id: participantId, room_code: roomCode, score: score, total: questions.length, answers: answers })
    });
  } catch (e) {
    console.error('Failed to submit results', e);
  }

  // Load leaderboard
  loadLeaderboard();

  // Review popup (same behavior as quiz.php)
  const hasReviewedLocally = localStorage.getItem('site_review_submitted') === 'true' || localStorage.getItem('quiz_review_submitted') === 'true';
  if (!reviewPopupShown && !hasAlreadyReviewedServer && !hasReviewedLocally) {
      reviewPopupShown = true;
      setTimeout(() => { openReviewModal(); }, 10000);
  }
}

function formatTime(sec) {
  if (sec === null || typeof sec === 'undefined') return '-';
  const s = parseInt(sec, 10);
  if (!Number.isFinite(s) || s < 0) return '-';
  const m = Math.floor(s / 60);
  const r = s % 60;
  return `${m}:${String(r).padStart(2,'0')}`;
}

function loadLeaderboard() {
  const tbody = document.getElementById('leaderboardBody');
  if (!tbody) return;
  fetch(`online_quiz_leaderboard.php?room_code=${encodeURIComponent(roomCode)}`)
    .then(res => res.json())
    .then(data => {
      if (!data.success || !Array.isArray(data.participants)) {
        tbody.innerHTML = `<tr><td colspan="5" class="leaderboard-muted">Unable to load leaderboard</td></tr>`;
        return;
      }
      const participants = data.participants;
      tbody.innerHTML = '';
      participants.forEach((p, idx) => {
        const tr = document.createElement('tr');
        if (parseInt(p.id,10) === parseInt(participantId,10)) tr.classList.add('leaderboard-you');
        tr.innerHTML = `
          <td class="leaderboard-rank">${idx + 1}</td>
          <td>${(p.roll_number || '').toString()}</td>
          <td>${(p.name || '').toString()}</td>
          <td><strong>${parseInt(p.score || 0, 10)}</strong> / ${parseInt(p.total_questions || 0, 10)}</td>
          <td>${formatTime(p.time_sec)}</td>
        `;
        tbody.appendChild(tr);
      });
    })
    .catch(() => {
      tbody.innerHTML = `<tr><td colspan="5" class="leaderboard-muted">Unable to load leaderboard</td></tr>`;
    });
}

function openReviewModal() {
  const modal = document.getElementById('reviewModal');
  if (!modal) return;
  modal.classList.add('open');
}

function closeReviewModal() {
  const modal = document.getElementById('reviewModal');
  if (!modal) return;
  modal.classList.remove('open');
}

function refreshStars(hoverRating = 0) {
  const stars = document.querySelectorAll('#starRow .star-btn');
  stars.forEach(star => {
    const val = Number(star.dataset.rating || 0);
    const displayRating = hoverRating || selectedReviewRating;
    star.classList.toggle('active', val <= displayRating);
  });
}

function updateCharCount() {
  const textarea = document.getElementById('reviewFeedback');
  const countEl = document.getElementById('charCount');
  if (!textarea || !countEl) return;
  const len = textarea.value.length;
  countEl.textContent = `${len} / 1000`;
  countEl.style.color = len >= 900 ? '#dc2626' : (len >= 750 ? '#f59e0b' : '#94a3b8');
}

function setReviewMessage(message, isSuccess = false) {
  const messageEl = document.getElementById('reviewMessage');
  if (!messageEl) return;
  messageEl.className = 'review-modal-message ' + (isSuccess ? 'success' : 'error');
  messageEl.textContent = message;
}

async function submitReview() {
  const feedbackEl = document.getElementById('reviewFeedback');
  const submitBtn = document.getElementById('submitReviewBtn');
  const anonCheckbox = document.getElementById('reviewAnonymous');
  if (!feedbackEl || !submitBtn) return;

  const feedback = feedbackEl.value.trim();
  const isAnon = anonCheckbox ? (anonCheckbox.checked ? 1 : 0) : 1;

  if (selectedReviewRating < 1 || selectedReviewRating > 5) {
    setReviewMessage('Please select your star rating first.');
    return;
  }
  if (feedback.length < 3) {
    setReviewMessage('Please write your feedback in the textbox.');
    return;
  }

  submitBtn.disabled = true;
  setReviewMessage('');

  try {
    const response = await fetch('quiz.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'submit_quiz_review',
        rating: selectedReviewRating,
        feedback: feedback,
        is_anonymous: isAnon
      })
    });
    const data = await response.json();
    if (!response.ok || data.status !== 'success') {
      setReviewMessage(data.message || 'Unable to submit review right now.');
      submitBtn.disabled = false;
      return;
    }

    localStorage.setItem('quiz_review_submitted', 'true');
    localStorage.setItem('site_review_submitted', 'true');
    setReviewMessage('Thank you for your review.', true);
    setTimeout(() => { closeReviewModal(); }, 900);
  } catch (e) {
    setReviewMessage('Unable to submit review right now.');
    submitBtn.disabled = false;
  }
}

// Star interactions
document.querySelectorAll('#starRow .star-btn').forEach(star => {
  star.addEventListener('mouseenter', () => refreshStars(Number(star.dataset.rating || 0)));
  star.addEventListener('mouseleave', () => refreshStars(0));
  star.addEventListener('click', () => {
    selectedReviewRating = Number(star.dataset.rating || 0);
    refreshStars(0);
  });
});

// Initialize first question
restoreProgress();
renderQuestion();

// --- Cheating Detection System ---
const cheatingDetection = {
    lastTabSwitch: 0,
    lastWindowBlur: 0,
    isWindowFocused: true,
    devToolsOpen: false,

    sendPayload: function (payload) {
        const json = JSON.stringify(payload);

        // Prefer sendBeacon for background/unload style events when available
        if (navigator.sendBeacon) {
            try {
                const blob = new Blob([json], { type: 'application/json' });
                const ok = navigator.sendBeacon('online_quiz_log_event.php', blob);
                if (!ok) {
                    // Fallback to fetch if beacon was rejected
                    return fetch('online_quiz_log_event.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: json,
                        keepalive: true
                    });
                }
                return Promise.resolve();
            } catch (e) {
                console.warn('Cheating detection sendBeacon failed, falling back to fetch', e);
            }
        }

        return fetch('online_quiz_log_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: json,
            keepalive: true
        });
    },

    logEvent: function(type, details, options = {}) {
        if (!roomCode || !participantId) {
            console.error('[Cheating Detection] Missing roomCode or participantId');
            return;
        }
        console.log(`[Cheating Detection] ${type}: ${details}`);

        // Prevent spamming events (e.g. rapid tab/window switching)
        const now = Date.now();
        if (type === 'tab_switch') {
            if (now - this.lastTabSwitch < 2000) return;
            this.lastTabSwitch = now;
        }
        if (type === 'window_blur') {
            if (now - this.lastWindowBlur < 2000) return;
            this.lastWindowBlur = now;
        }

        const payload = {
            room_code: roomCode,
            participant_id: participantId,
            event_type: type,
            event_details: details
        };

        // Show a red proctoring badge to the student once any suspicious activity is recorded
        if (['tab_switch', 'window_blur', 'copy_text', 'inspect_mode', 'right_click'].includes(type)) {
            const badge = document.getElementById('proctorAlertBadge');
            if (badge) {
                badge.classList.remove('hidden');
            }
        }

        const useBeacon = !!options.useBeacon;

        // For explicit beacon usage (visibilitychange / unload-style events), don't wait on the promise
        if (useBeacon) {
            this.sendPayload(payload).catch(err => {
                console.error('Cheating detection beacon error:', err);
            });
            return;
        }

        this.sendPayload(payload)
            .then(async (res) => {
                if (!res) return;
                if (!res.ok) {
                    let body;
                    try {
                        body = await res.json();
                    } catch (e) {
                        body = { message: 'Non-JSON response from server' };
                    }
                    console.error('Cheating detection server error:', res.status, body);
                } else {
                    // Optionally inspect success body for debugging
                    // const data = await res.json();
                    // console.debug('Cheating detection logged:', data);
                }
            })
            .catch(err => console.error('Cheating detection error:', err));
    },

    // Detection for DevTools
    checkDevTools: function() {
        const threshold = 160;
        const widthThreshold = window.outerWidth - window.innerWidth > threshold;
        const heightThreshold = window.outerHeight - window.innerHeight > threshold;

        if (widthThreshold || heightThreshold) {
            if (!this.devToolsOpen) {
                this.devToolsOpen = true;
                this.logEvent('inspect_mode', 'DevTools (Inspect Mode) detected as open (docked)');
            }
        } else {
            // Check via debugger statement (detects undocked DevTools too)
            const startTime = performance.now();
            debugger;
            const endTime = performance.now();
            if (endTime - startTime > 100) {
                if (!this.devToolsOpen) {
                    this.devToolsOpen = true;
                    this.logEvent('inspect_mode', 'DevTools (Inspect Mode) detected as open (undocked/debugger)');
                }
            } else {
                this.devToolsOpen = false;
            }
        }
    }
};

// 1. Detect Tab Switching / Minimizing
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        cheatingDetection.logEvent('tab_switch', 'User switched tab or minimized window', { useBeacon: true });
    }
});

// 2. Detect Window Focus Loss (Alt-Tab, separate window)
window.addEventListener('blur', () => {
    cheatingDetection.isWindowFocused = false;
    cheatingDetection.logEvent('window_blur', 'User clicked outside or switched window', { useBeacon: true });
});

window.addEventListener('focus', () => {
    cheatingDetection.isWindowFocused = true;
});

// 2.1 Detect Window Resizing (often happens when DevTools is opened)
let resizeTimeout;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        cheatingDetection.checkDevTools();
    }, 500);
});

// 3. Detect Copying Text
document.addEventListener('copy', (e) => {
    cheatingDetection.logEvent('copy_text', 'User attempted to copy text from screen');
});

// 4. Disable Right-Click (Prevent Inspect Element)
document.addEventListener('contextmenu', (e) => {
    e.preventDefault();
    cheatingDetection.logEvent('right_click', 'User attempted to right-click (Context Menu blocked)');
    alert("Right-click is disabled during the quiz to maintain integrity.");
});

// 5. Disable DevTools Keyboard Shortcuts
document.addEventListener('keydown', (e) => {
    // F12
    if (e.keyCode === 123) {
        e.preventDefault();
        cheatingDetection.logEvent('inspect_mode', 'User pressed F12 (DevTools shortcut)');
        alert("Inspect mode is disabled.");
    }
    // Ctrl+Shift+I (Inspect)
    if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
        e.preventDefault();
        cheatingDetection.logEvent('inspect_mode', 'User pressed Ctrl+Shift+I (Inspect shortcut)');
        alert("Inspect mode is disabled.");
    }
    // Ctrl+Shift+J (Console)
    if (e.ctrlKey && e.shiftKey && e.keyCode === 74) {
        e.preventDefault();
        cheatingDetection.logEvent('inspect_mode', 'User pressed Ctrl+Shift+J (Console shortcut)');
        alert("Inspect mode is disabled.");
    }
    // Ctrl+U (View Source)
    if (e.ctrlKey && e.keyCode === 85) {
        e.preventDefault();
        cheatingDetection.logEvent('inspect_mode', 'User pressed Ctrl+U (View Source shortcut)');
        alert("View Source is disabled.");
    }
    // Ctrl+Shift+C (Element Inspector)
    if (e.ctrlKey && e.shiftKey && e.keyCode === 67) {
        e.preventDefault();
        cheatingDetection.logEvent('inspect_mode', 'User pressed Ctrl+Shift+C (Element Inspector)');
        alert("Inspect mode is disabled.");
    }
});

// 6. Periodic DevTools Detection
setInterval(() => {
    cheatingDetection.checkDevTools();
}, 2000);

function restoreProgress() {
    // Reconstruct answers and score from existingResponses
    questions.forEach((q, index) => {
        const savedOption = existingResponses[q.qrq_id];
        if (savedOption) {
            const q_co = String(q.correct_option || '').trim();
            const q_oa = String(q.option_a || '').trim();
            const q_ob = String(q.option_b || '').trim();
            const q_oc = String(q.option_c || '').trim();
            const q_od = String(q.option_d || '').trim();

            let correctOptionLetter = '';
            if (['A','B','C','D'].includes(q_co.toUpperCase())) {
                correctOptionLetter = q_co.toUpperCase();
            } else {
                if (q_co.toLowerCase() === q_oa.toLowerCase()) correctOptionLetter = 'A';
                else if (q_co.toLowerCase() === q_ob.toLowerCase()) correctOptionLetter = 'B';
                else if (q_co.toLowerCase() === q_oc.toLowerCase()) correctOptionLetter = 'C';
                else if (q_co.toLowerCase() === q_od.toLowerCase()) correctOptionLetter = 'D';
            }

            const isCorrect = (savedOption.toUpperCase() === correctOptionLetter);
            if (isCorrect) score++;

            let selectedText = '';
            if (savedOption === 'A') selectedText = q.option_a;
            else if (savedOption === 'B') selectedText = q.option_b;
            else if (savedOption === 'C') selectedText = q.option_c;
            else if (savedOption === 'D') selectedText = q.option_d;

            answers[index] = {
                question_id: q.qrq_id,
                selected: savedOption,
                selectedText: selectedText,
                correct: correctOptionLetter,
                correctText: q.correct_option,
                isCorrect: isCorrect,
                timeSpent: 0 // Cannot recover time spent accurately on refresh
            };
        }
    });
    
    // Update UI counters if needed, but they are mostly hidden until result
    // We could jump to the first unanswered question
    const firstUnanswered = questions.findIndex(q => !existingResponses[q.qrq_id]);
    if (firstUnanswered !== -1) {
        currentQuestion = firstUnanswered;
    } else if (questions.length > 0 && Object.keys(existingResponses).length === questions.length) {
        // All answered? maybe stay on last or show results?
        // Let's stay on last question to allow review if we allow it, 
        // but typically we might want to show results if time is up.
        // For now, just go to last question
        currentQuestion = questions.length - 1;
    }
}
</script>
<?php include '../footer.php'; ?>
</body>
</html>
