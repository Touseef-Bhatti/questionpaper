<?php
// online_quiz_create_room.php - Handles room creation and persists room/questions
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';
require_once '../services/CacheManager.php';
require_once '../services/QuestionService.php';
require_once 'mcq_generator.php';

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
// Schema creation moved to install.php

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
$source = $_POST['source'] ?? '';
$quiz_duration_minutes = max(1, min(120, intval($_POST['quiz_duration_minutes'] ?? 30)));
$custom_mcqs_json = $_POST['custom_mcqs'] ?? '[]';
$selected_mcq_ids_str = $_POST['selected_mcq_ids'] ?? '';
$topics_json = $_POST['topics'] ?? '[]';
$topics = json_decode($topics_json, true);
if (!is_array($topics)) { $topics = []; }

// Parse custom MCQs JSON early for validation flexibility
$custom_mcqs = json_decode($custom_mcqs_json, true);
if (!is_array($custom_mcqs)) { $custom_mcqs = []; }

// Filter out invalid custom MCQs to ensure accurate count
$custom_mcqs = array_filter($custom_mcqs, function($mcq) {
    $q = trim($mcq['question'] ?? '');
    $a = trim($mcq['option_a'] ?? '');
    $b = trim($mcq['option_b'] ?? '');
    $c = trim($mcq['option_c'] ?? '');
    $d = trim($mcq['option_d'] ?? '');
    return ($q !== '' && $a !== '' && $b !== '' && $c !== '' && $d !== '');
});

$selected_mcq_ids = [];
if (!empty($selected_mcq_ids_str)) {
    $selected_mcq_ids = array_filter(array_map('intval', explode(',', $selected_mcq_ids_str)));
}

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
if ($mcq_count <= 0 && !$hasAnyCustom && empty($topics) && empty($selected_mcq_ids)) {
    respond_error('Please select at least 1 Random MCQ, add at least 1 Custom MCQ, or select specific questions.');
}

// Calculate if we need to generate random questions
$custom_count = count($custom_mcqs);
$selected_count = count($selected_mcq_ids);
$actual_manual_count = $custom_count + $selected_count;

// We only REQUIRE Class/Book if the user hasn't provided enough custom/selected questions to meet their target mcq_count
$random_needed_for_target = max(0, $mcq_count - $actual_manual_count);

if ($random_needed_for_target > 0 && (!$class_id || !$book_id) && empty($topics)) {
    respond_error('Class and Book (or Topics) are required to generate the remaining ' . $random_needed_for_target . ' random questions.');
}

// Always build EXACTLY mcq_count questions (no extra buffer)
$buffer_extra = 0;
$random_needed = max(0, $mcq_count - $actual_manual_count);

// Parse chapter IDs if provided
$chapterIdsArray = [];
if (!empty($chapter_ids)) {
    $chapterIdsArray = array_filter(array_map('intval', explode(',', $chapter_ids)));
}

// Create tables
// ensure_tables($conn); // Moved to install.php

// Insert room row
$room_code = generate_room_code($conn);

// Ensure quiz_rooms has user_id and quiz_duration_minutes columns (safe to run repeatedly)
/* Schema update moved to install.php */

/* Schema update moved to install.php */

// Build insert based on available columns
// Simplified insert - assumes schema is correct (run install.php)
    $stmt = $conn->prepare("INSERT INTO quiz_rooms (room_code, class_id, book_id, mcq_count, user_id, quiz_duration_minutes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('siiiii', $room_code, $class_id, $book_id, $mcq_count, $user_id, $quiz_duration_minutes);

if (!$stmt->execute()) {
    respond_error('Failed to create room.');
}
$room_id = $stmt->insert_id;
$stmt->close();

// Fetch bookName for precise question selection
$bookName = '';
if ($book_id > 0) {
    $bnStmt = $conn->prepare("SELECT book_name FROM book WHERE book_id = ? LIMIT 1");
    $bnStmt->bind_param("i", $book_id);
    $bnStmt->execute();
    $bnRes = $bnStmt->get_result()->fetch_assoc();
    if ($bnRes) $bookName = $bnRes['book_name'];
    $bnStmt->close();
}

// Use optimized question service instead of ORDER BY RAND()
$selectedQuestions = [];

// 1. Fetch specifically selected MCQs
if (!empty($selected_mcq_ids)) {
    $placeholders = str_repeat('?,', count($selected_mcq_ids) - 1) . '?';
    $types = str_repeat('i', count($selected_mcq_ids));
    
    $stmt = $conn->prepare("SELECT mcq_id, question, option_a, option_b, option_c, option_d, correct_option, explanation FROM mcqs WHERE mcq_id IN ($placeholders)");
    $stmt->bind_param($types, ...$selected_mcq_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $selectedQuestions[] = $row;
    }
    $stmt->close();
}

// 2. Fill remainder if needed (exact target count)
$remaining_needed = $mcq_count - count($selectedQuestions) - count($custom_mcqs);

if ($remaining_needed > 0) {
    // Exclude already selected IDs
    $exclude_ids = $selected_mcq_ids; 
    
    if (!empty($topics)) {
        // Normalize topics
        $normalizedTopics = array_unique(array_filter(array_map('trim', $topics)));
        if (empty($normalizedTopics)) { $normalizedTopics = ['General']; }

        // --- STEP 1: COMPREHENSIVE DB CHECK (mcqs + AIGeneratedMCQs) ---
        // We want to avoid AI generation if possible.
        
        // 1a. Fetch from mcqs table
        $placeholders = str_repeat('?,', count($normalizedTopics) - 1) . '?';
        $types = str_repeat('s', count($normalizedTopics));
        $params = $normalizedTopics;
        
        $excludeClause = "";
        if (!empty($exclude_ids)) {
            $numericExclude = array_filter($exclude_ids, 'is_numeric');
            if (!empty($numericExclude)) {
                $exPlaceholders = str_repeat('?,', count($numericExclude) - 1) . '?';
                $excludeClause = " AND m.mcq_id NOT IN ($exPlaceholders) ";
                $types .= str_repeat('i', count($numericExclude));
                $params = array_merge($params, $numericExclude);
            }
        }
        
        $sql = "SELECT m.mcq_id, m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option, v.explanation 
                FROM mcqs m
                LEFT JOIN MCQsVerification v ON m.mcq_id = v.mcq_id
                WHERE m.topic IN ($placeholders) $excludeClause
                ORDER BY RAND() LIMIT ?";
        $params[] = $remaining_needed;
        $types .= 'i';
    
        try {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $selectedQuestions[] = $row;
                    $exclude_ids[] = $row['mcq_id'];
                }
                $stmt->close();
            }
        } catch (Exception $e) {}

        // 1b. If still needed, fetch from AIGeneratedMCQs table
        $still_needed_from_db = $mcq_count - count($selectedQuestions) - count($custom_mcqs);
        if ($still_needed_from_db > 0) {
            $aiPlaceholders = str_repeat('?,', count($normalizedTopics) - 1) . '?';
            $aiTypes = str_repeat('s', count($normalizedTopics));
            $aiParams = $normalizedTopics;
            
            $aiExcludeClause = "";
            $aiExcludeIds = [];
            foreach ($exclude_ids as $eid) {
                if (strpos((string)$eid, 'ai_') === 0) {
                    $aiExcludeIds[] = intval(substr((string)$eid, 3));
                }
            }
            if (!empty($aiExcludeIds)) {
                $aiExPlaceholders = str_repeat('?,', count($aiExcludeIds) - 1) . '?';
                $aiExcludeClause = " AND id NOT IN ($aiExPlaceholders) ";
                $aiTypes .= str_repeat('i', count($aiExcludeIds));
                $aiParams = array_merge($aiParams, $aiExcludeIds);
            }
            
            $aiSql = "SELECT id as mcq_id, question_text as question, option_a, option_b, option_c, option_d, correct_option, explanation 
                      FROM AIGeneratedMCQs 
                      WHERE topic IN ($aiPlaceholders) $aiExcludeClause
                      ORDER BY RAND() LIMIT ?";
            $aiParams[] = $still_needed_from_db;
            $aiTypes .= 'i';
            
            try {
                $stmt = $conn->prepare($aiSql);
                if ($stmt) {
                    $stmt->bind_param($aiTypes, ...$aiParams);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $selectedQuestions[] = [
                            'mcq_id' => 'ai_' . $row['mcq_id'],
                            'question' => $row['question'],
                            'option_a' => $row['option_a'],
                            'option_b' => $row['option_b'],
                            'option_c' => $row['option_c'],
                            'option_d' => $row['option_d'],
                            'correct_option' => $row['correct_option'],
                            'explanation' => $row['explanation'] ?? ''
                        ];
                        $exclude_ids[] = 'ai_' . $row['mcq_id'];
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {}
        }

        // --- STEP 2: AI GENERATION (ONLY AS LAST RESORT) ---
        $final_needed = $mcq_count - count($selectedQuestions) - count($custom_mcqs);
        if ($final_needed > 0) {
            // Extract AI IDs to exclude for the generator
            $aiOnlyExcludeIds = [];
            foreach ($exclude_ids as $eid) {
                if (strpos((string)$eid, 'ai_') === 0) $aiOnlyExcludeIds[] = substr((string)$eid, 3);
            }

            // We call the generator with forceAI = false so it can still check its own cache first
            $generated = generateMCQsBulkWithGemini($normalizedTopics, $final_needed, '', true, false, $aiOnlyExcludeIds);
            if (!empty($generated)) {
                foreach ($generated as $gen) {
                    if (count($selectedQuestions) >= ($mcq_count - count($custom_mcqs))) break;
                    $selectedQuestions[] = [
                        'mcq_id' => 'ai_' . ($gen['id'] ?? $gen['mcq_id'] ?? uniqid()),
                        'question' => $gen['question'],
                        'option_a' => $gen['option_a'],
                        'option_b' => $gen['option_b'],
                        'option_c' => $gen['option_c'],
                        'option_d' => $gen['option_d'],
                        'correct_option' => $gen['correct_option'],
                        'explanation' => $gen['explanation'] ?? ''
                    ];
                }
            }
        }
        
    } else if ($remaining_needed > 0) {
        // Chapter/Book based selection
        if (!empty($chapterIdsArray)) {
            // Get MCQs from specific chapters
            $questionsPerChapter = max(1, ceil($remaining_needed / count($chapterIdsArray)));
            $collectedMcqs = [];
            
            foreach ($chapterIdsArray as $chapterId) {
                $mcqs = $questionService->getRandomMCQs($chapterId, $questionsPerChapter, $class_id, $bookName); // Note: Service doesn't support exclude yet easily
                foreach ($mcqs as $mcq) {
                    if (in_array($mcq['mcq_id'], $exclude_ids)) continue; // Manual exclusion check
                    
                    $collectedMcqs[] = [
                            'mcq_id' => $mcq['mcq_id'],
                            'question' => $mcq['question'],
                            'option_a' => $mcq['option_a'],
                            'option_b' => $mcq['option_b'],
                            'option_c' => $mcq['option_c'],
                            'option_d' => $mcq['option_d'],
                            'correct_option' => $mcq['correct_option'],
                            'explanation' => $mcq['explanation'] ?? ''
                        ];
                    $exclude_ids[] = $mcq['mcq_id'];
                }
            }
            
            // Shuffle and limit to requested count
            shuffle($collectedMcqs);
            $selectedQuestions = array_merge($selectedQuestions, array_slice($collectedMcqs, 0, $remaining_needed));
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
                $questionsPerChapter = max(1, ceil($remaining_needed / count($availableChapters)));
                $collectedMcqs = [];
                
                foreach ($availableChapters as $chapterId) {
                    $mcqs = $questionService->getRandomMCQs($chapterId, $questionsPerChapter, $class_id, $bookName);
                    foreach ($mcqs as $mcq) {
                        if (in_array($mcq['mcq_id'], $exclude_ids)) continue; // Manual exclusion check
                        
                        $collectedMcqs[] = [
                            'mcq_id' => $mcq['mcq_id'],
                            'question' => $mcq['question'],
                            'option_a' => $mcq['option_a'],
                            'option_b' => $mcq['option_b'],
                            'option_c' => $mcq['option_c'],
                            'option_d' => $mcq['option_d'],
                            'correct_option' => $mcq['correct_option'],
                            'explanation' => $mcq['explanation'] ?? ''
                        ];
                        $exclude_ids[] = $mcq['mcq_id'];
                    }
                    
                    // Stop if we have enough questions
                    if (count($collectedMcqs) >= $remaining_needed) {
                        break;
                    }
                }
                
                // Shuffle and limit to requested count
                shuffle($collectedMcqs);
                $selectedQuestions = array_merge($selectedQuestions, array_slice($collectedMcqs, 0, $remaining_needed));
            }
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
    $ins = $conn->prepare("INSERT INTO quiz_room_questions (room_id, question, option_a, option_b, option_c, option_d, correct_option, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($selectedQuestions as $q) {
        $exp = $q['explanation'] ?? '';
        $ins->bind_param('isssssss', $room_id, $q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_option'], $exp);
        $ins->execute();
    }
    $ins->close();

    // Recheck MCQs for the room using AI Recheck API
    // MOVED TO BACKGROUND AJAX CALL
}

// Auto-save user's custom questions to their profile
if (!empty($custom_mcqs) && $user_id > 0) {
    $saveToProfileStmt = $conn->prepare("INSERT INTO user_saved_questions (user_id, question_text, option_a, option_b, option_c, option_d, correct_option) SELECT ?, ?, ?, ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM user_saved_questions WHERE user_id = ? AND question_text = ?)");
    
    if ($saveToProfileStmt) {
        foreach ($custom_mcqs as $mcq) {
            $q = trim($mcq['question'] ?? '');
            $a = trim($mcq['option_a'] ?? '');
            $b = trim($mcq['option_b'] ?? '');
            $c = trim($mcq['option_c'] ?? '');
            $d = trim($mcq['option_d'] ?? '');
            $correctLetter = strtoupper(trim($mcq['correct'] ?? 'A'));
            
            if ($q === '' || $a === '' || $b === '' || $c === '' || $d === '') continue;

            $saveToProfileStmt->bind_param('issssssis', 
                $user_id, $q, $a, $b, $c, $d, $correctLetter,
                $user_id, $q
            );
            $saveToProfileStmt->execute();
        }
        $saveToProfileStmt->close();
    }
}

$baseUrl = rtrim(EnvLoader::get('BASE_URL', 'https://ahmadlearninghub.com.pk'), '/');
$joinUrl = $baseUrl . '/quiz/online_quiz_join.php?room=' . urlencode($room_code);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
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
<?php include_once '../header.php'; ?>
<div class="main-content" style="margin-top: 10%;">
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

<script>
    // Trigger background verification for room questions
    const roomId = <?= (int)$room_id ?>;
    if (roomId > 0) {
        console.log('Triggering background verification for Room ID:', roomId);
        fetch('ajax_verify_room_background.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ room_id: roomId })
        })
        .then(res => res.json())
        .then(data => {
            console.log('Background room verification started/finished:', data);
        })
        .catch(err => {
            console.error('Background room verification trigger failed:', err);
        });
    }
</script>

<?php include '../footer.php'; ?>
</body>
</html>
