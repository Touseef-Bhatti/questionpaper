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
            max-width: 820px;
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
            .quiz-container { border-radius: 0; border: none; }
            .quiz-header { padding: 32px 20px 24px; }
            .quiz-body { padding: 32px 20px; }
            .stats-grid { grid-template-columns: 1fr; padding: 24px 20px; }
            .results-hero { padding: 48px 20px 32px; }
            .results-title { font-size: 1.75rem; }
            .score-display { font-size: 2.5rem; }
            .quiz-timer-badge { font-size: 0.95rem; padding: 8px 14px; }
        }

        .hidden { display: none !important; }
    </style>
</head>
<body>
<?php include_once '../header.php'; ?>

<!-- SIDE SKYSCRAPER ADS -->
<?= renderAd('skyscraper', 'Online Quiz Page Right Skyscraper', 'right', 'margin-top: 10%;') ?>
<div class="main-content">
  <!-- TOP AD BANNER -->
  <?= renderAd('banner', 'Online Quiz Page Top Banner', 'ad-placement-top', 'margin-bottom: 20px;') ?>

  <div class="quiz-container">
    <!-- Header with Progress -->
    <div class="quiz-header" id="quizHeader">
      <div class="quiz-header-top">
        <div class="quiz-title-wrapper">
          <div class="quiz-title">
            <i class="fas fa-bolt" style="color: #fbbf24;"></i>
            <span><?php echo htmlspecialchars($book_name); ?></span>
          </div>
          <div class="quiz-subtitle"><?php echo htmlspecialchars($class_name); ?> &nbsp;•&nbsp; Room: <?php echo htmlspecialchars($room_code); ?></div>
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
      </div>
    </div>
  </div>

  <!-- BOTTOM AD BANNER -->
  <div style="margin-top: 30px;">
    <?= renderAd('banner', 'Online Quiz Page Bottom Banner') ?>
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
let currentQuestion = 0;
let score = 0;
let answers = [];
const durationSec = <?php echo (int)$durationSec; ?>;
const serverElapsedSec = <?php echo (int)$elapsedSec; ?>;
let remainingSec = Math.max(0, durationSec - serverElapsedSec);
let startTime = Date.now(); // For tracking time spent on questions
let questionStartTime = Date.now();

let timerInterval = setInterval(updateTimer, 1000);
let statusInterval = setInterval(checkServerStatus, 5000);

function checkServerStatus() {
    fetch('online_quiz_participant_status.php?room_code=' + encodeURIComponent(roomCode))
    .then(res => res.json())
    .then(data => {
        if (data.error) return;

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
}

// Initialize first question
restoreProgress();
renderQuestion();

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
