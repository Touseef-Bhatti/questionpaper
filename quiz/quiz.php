<?php
if (session_status() === PHP_SESSION_NONE) session_start(); // Must be the very first thing
include '../db_connect.php';
require_once 'mcq_generator.php';


// Validate POST or GET data (GET for topic-based redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = intval($_POST['class_id'] ?? 0);
    $book_id = intval($_POST['book_id'] ?? 0);
    $mcq_count = intval($_POST['mcq_count'] ?? 10);
    $chapter_ids = $_POST['chapter_ids'] ?? '';
    $topic = $_POST['topic'] ?? '';
    $topics = $_POST['topics'] ?? '';
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Allow GET for topic-based redirects
    $class_id = intval($_GET['class_id'] ?? 0);
    $book_id = intval($_GET['book_id'] ?? 0);
    $mcq_count = intval($_GET['mcq_count'] ?? 10);
    $chapter_ids = $_GET['chapter_ids'] ?? '';
    $topic = $_GET['topic'] ?? '';
    $topics = $_GET['topics'] ?? '';
} else {
    header('Location: quiz_setup.php');
    exit;
}

// Validate parameters: Either class+book OR topics must be provided
// Allow topics-only requests (class_id and book_id can be 0 when topics are provided)
$hasTopics = !empty($topics) || !empty($topic);
$hasClassBook = ($class_id > 0 && $book_id > 0);

if (!$hasTopics && !$hasClassBook) {
    die('<h2 style="color:red;">Invalid quiz parameters. Please select a filter criteria.</h2>');
}

// Build WHERE clause based on filters
$whereConditions = ['correct_option IS NOT NULL', 'correct_option != ""'];
$params = [];
$types = '';

// Add class/book filters only if provided
if ($class_id > 0) {
    $whereConditions[] = 'class_id = ?';
    $params[] = $class_id;
    $types .= 'i';
}

if ($book_id > 0) {
    $whereConditions[] = 'book_id = ?';
    $params[] = $book_id;
    $types .= 'i';
}

if (!empty($chapter_ids)) {
    $chapterIdsArray = array_filter(array_map('intval', explode(',', $chapter_ids)));
    if (!empty($chapterIdsArray)) {
        $placeholders = str_repeat('?,', count($chapterIdsArray) - 1) . '?';
        $whereConditions[] = "chapter_id IN ($placeholders)";
        $params = array_merge($params, $chapterIdsArray);
        $types .= str_repeat('i', count($chapterIdsArray));
    }
}

// Add topic filter if provided (support both single topic and multiple topics)
$topicsArray = [];
if (!empty($topics)) {
    // Decode JSON array of topics
    $decodedTopics = json_decode(urldecode($topics), true);
    if (is_array($decodedTopics) && !empty($decodedTopics)) {
        $topicsArray = $decodedTopics;
    }
} else if (!empty($topic)) {
    // Single topic (backward compatibility)
    $topicsArray = [$topic];
}

if (!empty($topicsArray)) {
    // Use IN clause for multiple topics
    $placeholders = str_repeat('?,', count($topicsArray) - 1) . '?';
    $whereConditions[] = "topic IN ($placeholders)";
    $params = array_merge($params, $topicsArray);
    $types .= str_repeat('s', count($topicsArray));
}

if (empty($whereConditions)) {
     die('<h2 style="color:red;">Invalid quiz filters.</h2>');
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Fetch random MCQs
$sql = "SELECT mcq_id, question, option_a, option_b, option_c, option_d, correct_option 
        FROM mcqs $whereClause 
        ORDER BY RAND() 
        LIMIT ?";
$params[] = $mcq_count;
$types .= 'i';

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    die('<h2 style="color:red;">Database error: Unable to fetch quiz questions.</h2>');
}

// If we don't have enough questions, check AIGeneratedMCQs table
if (count($questions) < $mcq_count && !empty($topicsArray)) {
    $needed = $mcq_count - count($questions);
    
    // Prepare topic placeholders for AIGeneratedMCQs query
    $placeholders = str_repeat('?,', count($topicsArray) - 1) . '?';
    $types = str_repeat('s', count($topicsArray));
    $params = $topicsArray;
    
    // Add limit param
    $params[] = $needed;
    $types .= 'i';
    
    $aiSql = "SELECT id, question, option_a, option_b, option_c, option_d, correct_option 
              FROM AIGeneratedMCQs 
              WHERE topic IN ($placeholders) 
              ORDER BY RAND() 
              LIMIT ?";
              
    try {
        $stmt = $conn->prepare($aiSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $questions[] = [
                'mcq_id' => 'ai_' . $row['id'],
                'question' => $row['question'],
                'option_a' => $row['option_a'],
                'option_b' => $row['option_b'],
                'option_c' => $row['option_c'],
                'option_d' => $row['option_d'],
                'correct_option' => $row['correct_option']
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching from AIGeneratedMCQs: " . $e->getMessage());
    }
}

// If still don't have enough questions, auto-generate the missing ones using API
if (count($questions) < $mcq_count && !empty($topicsArray)) {
    $neededCount = $mcq_count - count($questions);
    $generatedCount = 0;
    
    // Shuffle topics to randomize generation if multiple topics
    shuffle($topicsArray);
    
    foreach ($topicsArray as $topic) {
        if ($generatedCount >= $neededCount) break;
        
        // Calculate how many to generate for this topic
        // Try to distribute evenly, but ensure we get enough
        $remainingNeeded = $neededCount - $generatedCount;
        $toGenerate = ($remainingNeeded > 2) ? ceil($remainingNeeded / 2) : $remainingNeeded;
        
        $generatedMCQs = generateMCQsWithGemini($topic, $toGenerate);
        
        if (!empty($generatedMCQs)) {
            foreach ($generatedMCQs as $genMCQ) {
                if ($generatedCount >= $neededCount) break;
                
                $questions[] = [
                    'mcq_id' => 'ai_' . ($genMCQ['id'] ?? uniqid()),
                    'question' => $genMCQ['question'],
                    'option_a' => $genMCQ['option_a'],
                    'option_b' => $genMCQ['option_b'],
                    'option_c' => $genMCQ['option_c'],
                    'option_d' => $genMCQ['option_d'],
                    'correct_option' => $genMCQ['correct_option']
                ];
                $generatedCount++;
            }
        }
    }
}

// If still empty after generation attempt, show error
if (empty($questions)) {
    die('<h2 style="color:red;">Unable to generate quiz. Please try again or contact support.</h2>');
}

// Limit to requested count and shuffle
shuffle($questions);
$questions = array_slice($questions, 0, $mcq_count);

// Get class and book names for display
$class_name = 'Unknown Class';
$book_name = 'Unknown Book';

$stmt = $conn->prepare("SELECT class_name FROM class WHERE class_id = ?");
$stmt->bind_param('i', $class_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $class_name = $row['class_name'];
}
$stmt->close();

$stmt = $conn->prepare("SELECT book_name FROM book WHERE book_id = ?");
$stmt->bind_param('i', $book_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $book_name = $row['book_name'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - <?= htmlspecialchars($book_name) ?> | Ahmad Learning Hub</title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        /* Copy protection - disable text selection */
        * {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        /* Quiz container styling */
        .quiz-container { 
            max-width: 800px; 
            margin: 20px auto; 
            background: #fff; 
            border-radius: 16px; 
            box-shadow: 0 8px 24px rgba(0,0,0,0.1); 
            overflow: hidden;
        }
        
        .quiz-header { 
            background: linear-gradient(135deg, #4f6ef7, #6366f1); 
            color: white; 
            padding: 20px 24px; 
            text-align: center; 
        }
        .quiz-header h1 { margin: 0 0 8px; font-size: 24px; }
        .quiz-info { font-size: 14px; opacity: 0.9; }
        
        .progress-bar { 
            height: 4px; 
            background: #e5e7eb; 
            position: relative; 
        }
        .progress-fill { 
            height: 100%; 
            background: #10b981; 
            transition: width 0.3s ease; 
            width: 0%; 
        }
        
        .quiz-body { padding: 32px 24px; }
        
        .question-card { 
            background: #f8fafc; 
            border: 2px solid #e2e8f0; 
            border-radius: 12px; 
            padding: 24px; 
            margin-bottom: 24px; 
        }
        .question-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 16px; 
        }
        .question-number { 
            background: #4f6ef7; 
            color: white; 
            padding: 8px 16px; 
            border-radius: 20px; 
            font-weight: 600; 
            font-size: 14px; 
        }
        .question-text { 
            font-size: 18px; 
            font-weight: 500; 
            color: #1f2937; 
            line-height: 1.5; 
            margin-bottom: 20px; 
        }
        
        .options { display: flex; flex-direction: column; gap: 12px; }
        .option { 
            background: white; 
            border: 2px solid #e5e7eb; 
            border-radius: 8px; 
            padding: 16px 20px; 
            cursor: pointer; 
            transition: all 0.2s ease; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }
        .option:hover { 
            background: #f0f9ff; 
            border-color: #3b82f6; 
        }
        .option.selected { 
            background: #dbeafe; 
            border-color: #3b82f6; 
        }
        .option.correct { 
            background: #dcfce7; 
            border-color: #16a34a; 
        }
        .option.incorrect { 
            background: #fee2e2; 
            border-color: #dc2626; 
        }
        .option-label { 
            background: #6b7280; 
            color: white; 
            width: 32px; 
            height: 32px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 600; 
            font-size: 14px; 
        }
        .option.selected .option-label { background: #3b82f6; }
        .option.correct .option-label { background: #16a34a; }
        .option.incorrect .option-label { background: #dc2626; }
        
        .quiz-actions { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-top: 32px; 
        }
        .btn { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.2s ease; 
        }
        .btn.primary { 
            background: #4f6ef7; 
            color: white; 
        }
        .btn.primary:hover { 
            background: #3b5bd1; 
        }
        .btn.primary:disabled { 
            background: #9ca3af; 
            cursor: not-allowed; 
        }
        .btn.secondary { 
            background: #f3f4f6; 
            color: #374151; 
        }
        
        .timer { 
            font-size: 18px; 
            font-weight: 600; 
            color: #1f2937; 
        }
        .timer.warning { color: #f59e0b; }
        .timer.danger { color: #ef4444; }
        
        .results-card { 
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe); 
            border: 2px solid #0ea5e9; 
            border-radius: 12px; 
            padding: 32px; 
            text-align: center; 
            margin-top: 24px; 
        }
        .results-card h2 { color: #0c4a6e; margin-bottom: 16px; }
        .score-display { 
            font-size: 48px; 
            font-weight: 700; 
            color: #0284c7; 
            margin-bottom: 16px; 
        }
        .score-breakdown { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 16px; 
            margin: 24px 0; 
        }
        .score-item { 
            background: white; 
            padding: 16px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        .score-item .value { 
            font-size: 24px; 
            font-weight: 600; 
            color: #1f2937; 
        }
        .score-item .label { 
            font-size: 14px; 
            color: #6b7280; 
            margin-top: 4px; 
        }
        
        @media (max-width: 768px) {
            .quiz-container { margin: 10px; }
            .quiz-body { padding: 20px 16px; }
            .question-text { font-size: 16px; }
            .score-breakdown { grid-template-columns: 1fr; }
        }
        
        .hidden { display: none !important; }
    </style>
</head>
<body>
<?php include '../header.php'; ?>

<div class="main-content">
    <div class="quiz-container">
        <div class="quiz-header">
            <h1><?= htmlspecialchars($book_name) ?> Quiz</h1>
            <div class="quiz-info"><?= htmlspecialchars($class_name) ?> • <?= count($questions) ?> Questions</div>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        
        <div class="quiz-body">
            <!-- Questions will be populated here by JavaScript -->
            <div id="questionContainer"></div>
            
            <div class="quiz-actions">
                <div class="timer" id="timer">Time: 00:00</div>
                <button type="button" class="btn primary" id="nextBtn" onclick="nextQuestion()" disabled>Next Question</button>
            </div>
            
            <!-- Results Section (initially hidden) -->
            <div class="results-card hidden" id="resultsCard">
                <h2>🎉 Quiz Completed!</h2>
                <div class="score-display" id="scoreDisplay">0%</div>
                <div class="score-breakdown">
                    <div class="score-item">
                        <div class="value" id="correctCount">0</div>
                        <div class="label">Correct</div>
                    </div>
                    <div class="score-item">
                        <div class="value" id="incorrectCount">0</div>
                        <div class="label">Incorrect</div>
                    </div>
                    <div class="score-item">
                        <div class="value" id="totalTime">0:00</div>
                        <div class="label">Time Taken</div>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="button" class="btn secondary" onclick="window.location.href='quiz_setup.php'">Take Another Quiz</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Quiz data and state
const questions = <?= json_encode($questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let currentQuestion = 0;
let score = 0;
let answers = [];
let startTime = Date.now();
let questionStartTime = Date.now();

// Timer
let timerInterval = setInterval(updateTimer, 1000);

function updateTimer() {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    const timerEl = document.getElementById('timer');
    timerEl.textContent = `Time: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    // Change color based on time
    if (elapsed > 600) { // 10 minutes
        timerEl.className = 'timer danger';
    } else if (elapsed > 300) { // 5 minutes
        timerEl.className = 'timer warning';
    }
}

function renderQuestion() {
    const q = questions[currentQuestion];
    const container = document.getElementById('questionContainer');
    
    container.innerHTML = `
        <div class="question-card">
            <div class="question-header">
                <span class="question-number">Question ${currentQuestion + 1} of ${questions.length}</span>
            </div>
            <div class="question-text">${q.question}</div>
            <div class="options">
                <div class="option" data-option="A" onclick="selectOption('A')">
                    <div class="option-label">A</div>
                    <div>${q.option_a}</div>
                </div>
                <div class="option" data-option="B" onclick="selectOption('B')">
                    <div class="option-label">B</div>
                    <div>${q.option_b}</div>
                </div>
                <div class="option" data-option="C" onclick="selectOption('C')">
                    <div class="option-label">C</div>
                    <div>${q.option_c}</div>
                </div>
                <div class="option" data-option="D" onclick="selectOption('D')">
                    <div class="option-label">D</div>
                    <div>${q.option_d}</div>
                </div>
            </div>
        </div>
    `;
    
    // Update progress
    const progress = ((currentQuestion) / questions.length) * 100;
    document.getElementById('progressFill').style.width = progress + '%';
    
    questionStartTime = Date.now();
}

function selectOption(option) {
    const q = questions[currentQuestion];
    
    // Get the text of the selected option
    let selectedOptionText = '';
    let correctOptionLetter = '';
    
    switch(option) {
        case 'A': selectedOptionText = q.option_a; break;
        case 'B': selectedOptionText = q.option_b; break;
        case 'C': selectedOptionText = q.option_c; break;
        case 'D': selectedOptionText = q.option_d; break;
    }
    
    // Find which letter corresponds to the correct answer
    if (q.correct_option === q.option_a) correctOptionLetter = 'A';
    else if (q.correct_option === q.option_b) correctOptionLetter = 'B';
    else if (q.correct_option === q.option_c) correctOptionLetter = 'C';
    else if (q.correct_option === q.option_d) correctOptionLetter = 'D';
    
    const isCorrect = selectedOptionText === q.correct_option;
    
    // Mark answer
    answers[currentQuestion] = {
        selected: option,
        selectedText: selectedOptionText,
        correct: correctOptionLetter,
        correctText: q.correct_option,
        isCorrect: isCorrect,
        timeSpent: Math.floor((Date.now() - questionStartTime) / 1000)
    };
    
    // Update score
    if (isCorrect) {
        score++;
    }
    
    // Visual feedback
    const options = document.querySelectorAll('.option');
    options.forEach(opt => {
        const optionLetter = opt.dataset.option;
        if (optionLetter === option) {
            opt.classList.add(isCorrect ? 'correct' : 'incorrect');
        } else if (optionLetter === correctOptionLetter) {
            opt.classList.add('correct');
        }
        opt.style.pointerEvents = 'none'; // Disable further clicks
    });
    
    // Enable next button
    document.getElementById('nextBtn').disabled = false;
    
    // Auto-advance after 1.5 seconds
    setTimeout(() => {
        nextQuestion();
    }, 1500);
}

function nextQuestion() {
    currentQuestion++;
    
    if (currentQuestion >= questions.length) {
        showResults();
    } else {
        document.getElementById('nextBtn').disabled = true;
        renderQuestion();
    }
}

function showResults() {
    clearInterval(timerInterval);
    quizCompleted = true; // Mark quiz as completed
    
    const totalTime = Math.floor((Date.now() - startTime) / 1000);
    const percentage = Math.round((score / questions.length) * 100);
    
    // Hide question container
    document.getElementById('questionContainer').innerHTML = '';
    document.querySelector('.quiz-actions').style.display = 'none';
    
    // Show results
    document.getElementById('resultsCard').classList.remove('hidden');
    document.getElementById('scoreDisplay').textContent = percentage + '%';
    document.getElementById('correctCount').textContent = score;
    document.getElementById('incorrectCount').textContent = questions.length - score;
    
    const minutes = Math.floor(totalTime / 60);
    const seconds = totalTime % 60;
    document.getElementById('totalTime').textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    
    // Update progress to 100%
    document.getElementById('progressFill').style.width = '100%';
}

// Prevent page refresh/navigation during quiz
let quizStarted = false;
let quizCompleted = false;

window.addEventListener('beforeunload', function(e) {
    if (quizStarted && !quizCompleted) {
        e.preventDefault();
        e.returnValue = 'Are you sure you want to leave? Your quiz progress will be lost.';
        return 'Are you sure you want to leave? Your quiz progress will be lost.';
    }
});

// Disable browser back button during quiz
history.pushState(null, null, location.href);
window.addEventListener('popstate', function(e) {
    if (quizStarted && !quizCompleted) {
        history.pushState(null, null, location.href);
        if (confirm('Are you sure you want to leave the quiz? Your progress will be lost.')) {
            quizCompleted = true;
            window.location.href = 'quiz_setup.php';
        }
    }
});

// Disable refresh (F5, Ctrl+R)
document.addEventListener('keydown', function(e) {
    // Prevent F5 refresh
    if (e.key === 'F5') {
        if (quizStarted && !quizCompleted) {
            e.preventDefault();
            alert('Please complete the quiz. Refresh is disabled during the quiz.');
        }
    }
    
    // Prevent Ctrl+R refresh
    if (e.ctrlKey && e.key === 'r') {
        if (quizStarted && !quizCompleted) {
            e.preventDefault();
            alert('Please complete the quiz. Refresh is disabled during the quiz.');
        }
    }
    
    // Disable copy/paste/select all
    if (e.ctrlKey && (e.key === 'c' || e.key === 'a' || e.key === 'v' || e.key === 'x')) {
        e.preventDefault();
    }
    
    // Disable developer tools
    if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) {
        e.preventDefault();
    }
});

// Disable right-click
document.addEventListener('contextmenu', e => e.preventDefault());

// Mark quiz as started and initialize
quizStarted = true;
renderQuestion();
</script>

<?php include '../footer.php'; ?>
</body>
</html>
