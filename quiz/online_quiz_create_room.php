<?php
// online_quiz_create_room.php - Handles room creation and persists room/questions
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';
require_once '../services/CacheManager.php';
require_once '../services/QuestionService.php';

// Initialize optimized services
$cache = new CacheManager();
$questionService = new QuestionService($conn, $cache);

// Current user
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

function respond_error($msg) {
    http_response_code(400);
    echo '<h2 style="color:red;">' . htmlspecialchars($msg) . '</h2>';
    echo '<p><a href="online_quiz_host.php">Go back</a></p>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: online_quiz_host.php');
    exit;
}

// Ensure necessary tables exist
function ensure_tables(mysqli $conn) {
    $queries = [
        "CREATE TABLE IF NOT EXISTS quiz_rooms (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            room_code VARCHAR(10) NOT NULL UNIQUE,\n            class_id INT NOT NULL,\n            book_id INT NOT NULL,\n            mcq_count INT NOT NULL DEFAULT 0,\n            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            status ENUM('active','closed') NOT NULL DEFAULT 'active',\n            quiz_started BOOLEAN DEFAULT FALSE,\n            lobby_enabled BOOLEAN DEFAULT TRUE,\n            start_time DATETIME NULL,\n            quiz_duration_minutes INT DEFAULT 30,\n            user_id INT NULL\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS quiz_room_questions (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            room_id INT NOT NULL,\n            question TEXT NOT NULL,\n            option_a TEXT,\n            option_b TEXT,\n            option_c TEXT,\n            option_d TEXT,\n            correct_option TEXT,\n            FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS quiz_participants (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            room_id INT NOT NULL,\n            name VARCHAR(255) NOT NULL,\n            roll_number VARCHAR(100) NOT NULL,\n            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            finished_at DATETIME NULL,\n            score INT DEFAULT NULL,\n            total_questions INT DEFAULT NULL,\n            status ENUM('waiting', 'active', 'completed', 'disconnected') DEFAULT 'waiting',\n            current_question INT DEFAULT 0,\n            time_remaining_sec INT NULL,\n            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS quiz_responses (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            participant_id INT NOT NULL,\n            question_id INT NOT NULL,\n            selected_option VARCHAR(1) NULL,\n            is_correct TINYINT(1) NULL,\n            time_spent_sec INT NULL,\n            FOREIGN KEY (participant_id) REFERENCES quiz_participants(id) ON DELETE CASCADE,\n            FOREIGN KEY (question_id) REFERENCES quiz_room_questions(id) ON DELETE CASCADE\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS live_quiz_events (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            room_id INT NOT NULL,\n            participant_id INT NULL,\n            event_type ENUM('participant_joined', 'participant_left', 'quiz_started', 'question_answered', 'quiz_completed') NOT NULL,\n            event_data JSON NULL,\n            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE,\n            FOREIGN KEY (participant_id) REFERENCES quiz_participants(id) ON DELETE SET NULL,\n            INDEX idx_room_created (room_id, created_at),\n            INDEX idx_event_type (event_type)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];
    foreach ($queries as $sql) {
        $conn->query($sql); // best-effort; if fails, later operations will fail distinctly
    }
}

function generate_room_code(mysqli $conn, $length = 6) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // avoid ambiguous chars
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $exists = 0;
        $stmt = $conn->prepare("SELECT 1 FROM quiz_rooms WHERE room_code = ? LIMIT 1");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows;
        $stmt->close();
    } while ($exists > 0);
    return $code;
}

$class_id = intval($_POST['class_id'] ?? 0);
$book_id = intval($_POST['book_id'] ?? 0);
$mcq_count = intval($_POST['mcq_count'] ?? 0);
$chapter_ids = trim($_POST['chapter_ids'] ?? '');
$quiz_duration_minutes = max(1, min(120, intval($_POST['quiz_duration_minutes'] ?? 30)));
$custom_mcqs_json = $_POST['custom_mcqs'] ?? '[]';

// Parse custom MCQs JSON early for validation flexibility
$custom_mcqs = json_decode($custom_mcqs_json, true);
if (!is_array($custom_mcqs)) { $custom_mcqs = []; }

$hasAnyCustom = false;
foreach ($custom_mcqs as $mcq) {
    $q = trim($mcq['question'] ?? '');
    $a = trim($mcq['option_a'] ?? '');
    $b = trim($mcq['option_b'] ?? '');
    $c = trim($mcq['option_c'] ?? '');
    $d = trim($mcq['option_d'] ?? '');
    $correctLetter = strtoupper(trim($mcq['correct'] ?? ''));
    if ($q !== '' && $a !== '' && $b !== '' && $c !== '' && $d !== '' && in_array($correctLetter, ['A','B','C','D'], true)) {
        $hasAnyCustom = true;
        break;
    }
}

// Validation rules
if ($mcq_count <= 0 && !$hasAnyCustom) {
    respond_error('Please select at least 1 Random MCQ or add at least 1 Custom MCQ.');
}
if ($mcq_count > 0 && (!$class_id || !$book_id)) {
    respond_error('Class and Book are required when selecting Random MCQs.');
}

// Parse chapter IDs if provided
$chapterIdsArray = [];
if (!empty($chapter_ids)) {
    $chapterIdsArray = array_filter(array_map('intval', explode(',', $chapter_ids)));
}

// Create tables
ensure_tables($conn);

// Insert room row
$room_code = generate_room_code($conn);

// Ensure quiz_rooms has user_id and quiz_duration_minutes columns (safe to run repeatedly)
$hasUserIdCol = false;
if ($colRes = $conn->query("SHOW COLUMNS FROM quiz_rooms LIKE 'user_id'")) {
    $hasUserIdCol = ($colRes->num_rows > 0);
    $colRes->close();
}
if (!$hasUserIdCol) {
    // Try to add the column; ignore failure if it already exists or lacks privileges
    $conn->query("ALTER TABLE quiz_rooms ADD COLUMN user_id INT NULL");
    if ($colRes2 = $conn->query("SHOW COLUMNS FROM quiz_rooms LIKE 'user_id'")) {
        $hasUserIdCol = ($colRes2->num_rows > 0);
        $colRes2->close();
    }
}

$hasDurationCol = false;
if ($colRes3 = $conn->query("SHOW COLUMNS FROM quiz_rooms LIKE 'quiz_duration_minutes'")) {
    $hasDurationCol = ($colRes3->num_rows > 0);
    $colRes3->close();
}
// Try to add if missing
if (!$hasDurationCol) {
    $conn->query("ALTER TABLE quiz_rooms ADD COLUMN quiz_duration_minutes INT DEFAULT 30");
    if ($colRes4 = $conn->query("SHOW COLUMNS FROM quiz_rooms LIKE 'quiz_duration_minutes'")) {
        $hasDurationCol = ($colRes4->num_rows > 0);
        $colRes4->close();
    }
}

// Build insert based on available columns
if ($hasUserIdCol && $hasDurationCol) {
    $stmt = $conn->prepare("INSERT INTO quiz_rooms (room_code, class_id, book_id, mcq_count, user_id, quiz_duration_minutes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('siiiii', $room_code, $class_id, $book_id, $mcq_count, $user_id, $quiz_duration_minutes);
} elseif ($hasUserIdCol && !$hasDurationCol) {
    $stmt = $conn->prepare("INSERT INTO quiz_rooms (room_code, class_id, book_id, mcq_count, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('siiii', $room_code, $class_id, $book_id, $mcq_count, $user_id);
} elseif (!$hasUserIdCol && $hasDurationCol) {
    $stmt = $conn->prepare("INSERT INTO quiz_rooms (room_code, class_id, book_id, mcq_count, quiz_duration_minutes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('siiii', $room_code, $class_id, $book_id, $mcq_count, $quiz_duration_minutes);
} else {
    // Fallback (legacy schema)
    $stmt = $conn->prepare("INSERT INTO quiz_rooms (room_code, class_id, book_id, mcq_count) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('siii', $room_code, $class_id, $book_id, $mcq_count);
}

if (!$stmt->execute()) {
    respond_error('Failed to create room.');
}
$room_id = $stmt->insert_id;
$stmt->close();

// Use optimized question service instead of ORDER BY RAND()
$selectedQuestions = [];
if ($mcq_count > 0) {
    if (!empty($chapterIdsArray)) {
        // Get MCQs from specific chapters
        $questionsPerChapter = max(1, ceil($mcq_count / count($chapterIdsArray)));
        $collectedMcqs = [];
        
        foreach ($chapterIdsArray as $chapterId) {
            $mcqs = $questionService->getRandomMCQs($chapterId, $questionsPerChapter);
            foreach ($mcqs as $mcq) {
                $collectedMcqs[] = [
                    'mcq_id' => $mcq['mcq_id'],
                    'question' => $mcq['question'],
                    'option_a' => $mcq['option_a'],
                    'option_b' => $mcq['option_b'],
                    'option_c' => $mcq['option_c'],
                    'option_d' => $mcq['option_d'],
                    'correct_option' => $mcq['correct_option']
                ];
            }
        }
        
        // Shuffle and limit to requested count
        shuffle($collectedMcqs);
        $selectedQuestions = array_slice($collectedMcqs, 0, $mcq_count);
    } else {
        // Get MCQs from any chapter in the book
        $chaptersQuery = "SELECT chapter_id FROM chapter WHERE class_id = ? AND book_id = ?";
        $chaptersStmt = $conn->prepare($chaptersQuery);
        $chaptersStmt->bind_param('ii', $class_id, $book_id);
        $chaptersStmt->execute();
        $chaptersResult = $chaptersStmt->get_result();
        
        $availableChapters = [];
        while ($chapterRow = $chaptersResult->fetch_assoc()) {
            $availableChapters[] = $chapterRow['chapter_id'];
        }
        $chaptersStmt->close();
        
        if (!empty($availableChapters)) {
            // Distribute questions across available chapters
            $questionsPerChapter = max(1, ceil($mcq_count / count($availableChapters)));
            $collectedMcqs = [];
            
            foreach ($availableChapters as $chapterId) {
                $mcqs = $questionService->getRandomMCQs($chapterId, $questionsPerChapter);
                foreach ($mcqs as $mcq) {
                    $collectedMcqs[] = [
                        'mcq_id' => $mcq['mcq_id'],
                        'question' => $mcq['question'],
                        'option_a' => $mcq['option_a'],
                        'option_b' => $mcq['option_b'],
                        'option_c' => $mcq['option_c'],
                        'option_d' => $mcq['option_d'],
                        'correct_option' => $mcq['correct_option']
                    ];
                }
                
                // Stop if we have enough questions
                if (count($collectedMcqs) >= $mcq_count) {
                    break;
                }
            }
            
            // Shuffle and limit to requested count
            shuffle($collectedMcqs);
            $selectedQuestions = array_slice($collectedMcqs, 0, $mcq_count);
        }
    }
}

// Normalize and merge custom MCQs
foreach ($custom_mcqs as $mcq) {
    $q = trim($mcq['question'] ?? '');
    $a = trim($mcq['option_a'] ?? '');
    $b = trim($mcq['option_b'] ?? '');
    $c = trim($mcq['option_c'] ?? '');
    $d = trim($mcq['option_d'] ?? '');
    $correctLetter = strtoupper(trim($mcq['correct'] ?? 'A'));
    if ($q === '' || $a === '' || $b === '' || $c === '' || $d === '') { continue; }
    $correctText = $a;
    if ($correctLetter === 'B') $correctText = $b;
    if ($correctLetter === 'C') $correctText = $c;
    if ($correctLetter === 'D') $correctText = $d;
    $selectedQuestions[] = [
        'mcq_id' => null,
        'question' => $q,
        'option_a' => $a,
        'option_b' => $b,
        'option_c' => $c,
        'option_d' => $d,
        'correct_option' => $correctText,
    ];
}

// Insert into quiz_room_questions (snapshot)
if (!empty($selectedQuestions)) {
    $ins = $conn->prepare("INSERT INTO quiz_room_questions (room_id, question, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($selectedQuestions as $q) {
        $ins->bind_param('issssss', $room_id, $q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_option']);
        $ins->execute();
    }
    $ins->close();
}

$joinUrl = 'online_quiz_join.php?room=' . urlencode($room_code);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Created - Ahmad Learning Hub</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        .card { max-width: 700px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); padding: 24px; text-align: center; }
        .code { font-size: 32px; font-weight: 800; letter-spacing: 4px; color: #111827; background: #f3f4f6; display: inline-block; padding: 8px 16px; border-radius: 8px; }
        .actions { margin-top: 16px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn.primary { background: #4f6ef7; color: white; }
        .btn.secondary { background: #e9eef8; color: #2d3e50; }
        .muted { color: #6b7280; margin-top: 8px; }
        input.link { width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; }
    </style>
</head>
<body>
<?php include '../header.php'; ?>
<div class="main-content">
  <div class="card">
    <h1>Room Created</h1>
    <p>Share this room code with your students:</p>
    <div class="code"><?php echo htmlspecialchars($room_code); ?></div>
    <div class="muted">Or share the link:</div>
    <input class="link" readonly value="<?php echo htmlspecialchars($joinUrl); ?>" onclick="this.select();" />
    <div class="actions">
      <!-- <a href="<?php echo htmlspecialchars($joinUrl); ?>" class="btn primary">Open Join Page</a> -->
      <a href="online_quiz_dashboard.php?room=<?php echo htmlspecialchars($room_code); ?>" class="btn primary">Open Dashboard</a>
      <a href="online_quiz_host.php" class="btn secondary">Create Another Room</a>
      <a href="index.php" class="btn secondary">Back to Home</a>
    </div>
  </div>
</div>
<?php include '../footer.php'; ?>
</body>
</html>
