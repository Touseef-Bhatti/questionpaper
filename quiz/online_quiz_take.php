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

// Fetch questions snapshot - SHUFFLED for each participant
$questions = [];
$stmt = $conn->prepare("SELECT id as qrq_id, question, option_a, option_b, option_c, option_d, correct_option FROM quiz_room_questions WHERE room_id = ? ORDER BY RAND()");
$stmt->bind_param('i', $room_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $questions[] = $row;
}
$stmt->close();

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
    <link rel="stylesheet" href="css/main.css">
    <style>
        * { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
        .quiz-container { max-width: 800px; margin: 20px auto; background: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); overflow: hidden; }
        .quiz-header { background: linear-gradient(135deg, #4f6ef7, #6366f1); color: white; padding: 20px 24px; text-align: center; }
        .quiz-header h1 { margin: 0 0 8px; font-size: 24px; }
        .quiz-info { font-size: 14px; opacity: 0.9; }
        .progress-bar { height: 4px; background: #e5e7eb; position: relative; }
        .progress-fill { height: 100%; background: #10b981; transition: width 0.3s ease; width: 0%; }
        .quiz-body { padding: 32px 24px; }
        .question-card { background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
        .question-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .question-number { background: #4f6ef7; color: white; padding: 8px 16px; border-radius: 20px; font-weight: 600; font-size: 14px; }
        .question-text { font-size: 18px; font-weight: 500; color: #1f2937; line-height: 1.5; margin-bottom: 20px; }
        .options { display: flex; flex-direction: column; gap: 12px; }
        .option { background: white; border: 2px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; gap: 12px; }
        .option:hover { background: #f0f9ff; border-color: #3b82f6; }
        .option.selected { background: #dbeafe; border-color: #3b82f6; }
        .option.correct { background: #dcfce7; border-color: #16a34a; }
        .option.incorrect { background: #fee2e2; border-color: #dc2626; }
        .option-label { background: #6b7280; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; }
        .option.selected .option-label { background: #3b82f6; }
        .option.correct .option-label { background: #16a34a; }
        .option.incorrect .option-label { background: #dc2626; }
        .quiz-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 32px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .btn.primary { background: #4f6ef7; color: white; }
        .btn.primary:hover { background: #3b5bd1; }
        .btn.primary:disabled { background: #9ca3af; cursor: not-allowed; }
        .btn.secondary { background: #f3f4f6; color: #374151; }
        .timer { font-size: 18px; font-weight: 600; color: #1f2937; }
        .timer.warning { color: #f59e0b; }
        .timer.danger { color: #ef4444; }
        .results-card { background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border: 2px solid #0ea5e9; border-radius: 12px; padding: 32px; text-align: center; margin-top: 24px; }
        .results-card h2 { color: #0c4a6e; margin-bottom: 16px; }
        .score-display { font-size: 48px; font-weight: 700; color: #0284c7; margin-bottom: 16px; }
        .score-breakdown { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin: 24px 0; }
        .score-item { background: white; padding: 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .score-item .value { font-size: 24px; font-weight: 600; color: #1f2937; }
        .score-item .label { font-size: 14px; color: #6b7280; margin-top: 4px; }
        @media (max-width: 768px) { .quiz-container { margin: 10px; } .quiz-body { padding: 20px 16px; } .question-text { font-size: 16px; } .score-breakdown { grid-template-columns: 1fr; } }
        .hidden { display: none !important; }
    </style>
</head>
<body>
<?php include '../header.php'; ?>
<div class="main-content">
  <div class="quiz-container">
    <div class="quiz-header">
      <h1><?php echo htmlspecialchars($book_name); ?> Quiz</h1>
      <div class="quiz-info"><?php echo htmlspecialchars($class_name); ?> • <?php echo count($questions); ?> Questions</div>
    </div>
    <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
    <div class="quiz-body">
      <div id="questionContainer"></div>
      <div class="quiz-actions">
        <div class="timer" id="timer">Time: 00:00</div>
        <button type="button" class="btn primary" id="nextBtn" onclick="nextQuestion()" disabled>Next Question</button>
      </div>
      <div class="results-card hidden" id="resultsCard">
        <h2>🎉 Quiz Completed!</h2>
        <div class="score-display" id="scoreDisplay">0%</div>
        <div class="score-breakdown">
          <div class="score-item"><div class="value" id="correctCount">0</div><div class="label">Correct</div></div>
          <div class="score-item"><div class="value" id="incorrectCount">0</div><div class="label">Incorrect</div></div>
          <div class="score-item"><div class="value" id="totalTime">0:00</div><div class="label">Time Taken</div></div>
        </div>
        <div style="margin-top: 20px;">
          <button type="button" class="btn secondary" onclick="window.location.href='online_quiz_join.php?room=<?php echo urlencode($room_code); ?>'">Finish</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
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
  
  container.innerHTML = `
    <div class="question-card">
      <div class="question-header">
        <span class="question-number">Question ${currentQuestion + 1} of ${questions.length}</span>
      </div>
      <div class="question-text">${q.question}</div>
      <div class="options">
        <div class="option" data-option="A" onclick="selectOption('A')"><div class="option-label">A</div><div>${q.option_a}</div></div>
        <div class="option" data-option="B" onclick="selectOption('B')"><div class="option-label">B</div><div>${q.option_b}</div></div>
        <div class="option" data-option="C" onclick="selectOption('C')"><div class="option-label">C</div><div>${q.option_c}</div></div>
        <div class="option" data-option="D" onclick="selectOption('D')"><div class="option-label">D</div><div>${q.option_d}</div></div>
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
              opt.classList.add(savedAnswer.isCorrect ? 'correct' : 'incorrect');
          } else if (optionLetter === savedAnswer.correct) {
              opt.classList.add('correct');
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
  let correctOptionLetter = '';
  switch(option) {
    case 'A': selectedOptionText = q.option_a; break;
    case 'B': selectedOptionText = q.option_b; break;
    case 'C': selectedOptionText = q.option_c; break;
    case 'D': selectedOptionText = q.option_d; break;
  }
  if (q.correct_option === q.option_a) correctOptionLetter = 'A';
  else if (q.correct_option === q.option_b) correctOptionLetter = 'B';
  else if (q.correct_option === q.option_c) correctOptionLetter = 'C';
  else if (q.correct_option === q.option_d) correctOptionLetter = 'D';
  const isCorrect = selectedOptionText === q.correct_option;
  answers[currentQuestion] = {
    question_id: q.qrq_id,
    selected: option,
    selectedText: selectedOptionText,
    correct: correctOptionLetter,
    correctText: q.correct_option,
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
      opt.classList.add(isCorrect ? 'correct' : 'incorrect');
    } else if (optionLetter === correctOptionLetter) {
      opt.classList.add('correct');
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
  const totalTime = Math.floor((Date.now() - startTime) / 1000);
  const percentage = Math.round((score / questions.length) * 100);
  document.getElementById('questionContainer').innerHTML = '';
  document.querySelector('.quiz-actions').style.display = 'none';
  document.getElementById('resultsCard').classList.remove('hidden');
  document.getElementById('scoreDisplay').textContent = percentage + '%';
  document.getElementById('correctCount').textContent = score;
  document.getElementById('incorrectCount').textContent = questions.length - score;
  const minutes = Math.floor(totalTime / 60);
  const seconds = totalTime % 60;
  document.getElementById('totalTime').textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
  document.getElementById('progressFill').style.width = '100%';

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
            let correctOptionLetter = '';
            if (q.correct_option === q.option_a) correctOptionLetter = 'A';
            else if (q.correct_option === q.option_b) correctOptionLetter = 'B';
            else if (q.correct_option === q.option_c) correctOptionLetter = 'C';
            else if (q.correct_option === q.option_d) correctOptionLetter = 'D';

            const isCorrect = (savedOption === correctOptionLetter);
            if (isCorrect) score++;

            let selectedText = '';
            if (savedOption === 'A') selectedText = q.option_a;
            if (savedOption === 'B') selectedText = q.option_b;
            if (savedOption === 'C') selectedText = q.option_c;
            if (savedOption === 'D') selectedText = q.option_d;

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
</body>
</html>
