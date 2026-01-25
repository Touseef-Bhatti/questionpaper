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
$random_needed = max(0, $mcq_count - $custom_count - $selected_count);

if ($random_needed > 0 && (!$class_id || !$book_id) && empty($topics)) {
    respond_error('Class and Book are required when selecting Random MCQs.');
}

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

// Use optimized question service instead of ORDER BY RAND()
$selectedQuestions = [];

// 1. Fetch specifically selected MCQs
if (!empty($selected_mcq_ids)) {
    $placeholders = str_repeat('?,', count($selected_mcq_ids) - 1) . '?';
    $types = str_repeat('i', count($selected_mcq_ids));
    
    $stmt = $conn->prepare("SELECT mcq_id, question, option_a, option_b, option_c, option_d, correct_option FROM mcqs WHERE mcq_id IN ($placeholders)");
    $stmt->bind_param($types, ...$selected_mcq_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $selectedQuestions[] = $row;
    }
    $stmt->close();
}

// 2. Fill remainder if needed
$remaining_needed = $mcq_count - count($selectedQuestions) - count($custom_mcqs);

if ($remaining_needed > 0) {
    // Exclude already selected IDs
    $exclude_ids = $selected_mcq_ids; // Start with manually selected
    
    if (!empty($topics)) {
        // Topic-based selection logic
        $placeholders = str_repeat('?,', count($topics) - 1) . '?';
        $types = str_repeat('s', count($topics));
        $params = $topics;
        
        $excludeClause = "";
        if (!empty($exclude_ids)) {
            $exPlaceholders = str_repeat('?,', count($exclude_ids) - 1) . '?';
            $excludeClause = " AND mcq_id NOT IN ($exPlaceholders) ";
            $types .= str_repeat('i', count($exclude_ids));
            $params = array_merge($params, $exclude_ids);
        }
        
        // Fetch random MCQs matching topics
        $sql = "SELECT mcq_id, question, option_a, option_b, option_c, option_d, correct_option 
                FROM mcqs 
                WHERE topic IN ($placeholders) 
                $excludeClause
                ORDER BY RAND() 
                LIMIT ?";
        $params[] = $remaining_needed;
        $types .= 'i';
    
        try {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $selectedQuestions[] = $row;
                $exclude_ids[] = $row['mcq_id']; // Add to exclusion list
            }
            $stmt->close();
        } catch (Exception $e) {
            // Fallback or ignore
        }
    
        // Check AIGeneratedMCQs if needed
        $target_db_count = $mcq_count - count($custom_mcqs);
        if (count($selectedQuestions) < $target_db_count) {
            $needed = $target_db_count - count($selectedQuestions);
            
            // Re-prepare params for AI table (same topics)
            $aiParams = $topics;
            $aiTypes = str_repeat('s', count($topics));
            $aiParams[] = $needed;
            $aiTypes .= 'i';
            
            // Note: AI table might not have standard IDs to exclude easily, 
            // but usually they don't overlap with standard MCQs. 
            // We'll skip complex exclusion for AI table for now to keep it simple.
            
            $aiSql = "SELECT question, option_a, option_b, option_c, option_d, correct_option 
                      FROM AIGeneratedMCQs 
                      WHERE topic IN ($placeholders) 
                      ORDER BY RAND() 
                      LIMIT ?";
                      
            try {
                $stmt = $conn->prepare($aiSql);
                $stmt->bind_param($aiTypes, ...$aiParams);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $selectedQuestions[] = [
                        'mcq_id' => null, // AI questions don't have standard mcq_id
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
                // Ignore
            }
        }
    
        // Generate if still needed
        if (count($selectedQuestions) < $target_db_count) {
            $neededCount = $target_db_count - count($selectedQuestions);
            $generatedCount = 0;
            $shuffledTopics = $topics;
            shuffle($shuffledTopics);
            
            foreach ($shuffledTopics as $topic) {
                if ($generatedCount >= $neededCount) break;
                
                $remainingNeeded = $neededCount - $generatedCount;
                $toGenerate = ($remainingNeeded > 2) ? ceil($remainingNeeded / 2) : $remainingNeeded;
                
                // Assuming generateMCQsWithGemini is available (we added require)
                if (function_exists('generateMCQsWithGemini')) {
                    // Generate remaining questions + 2 extra as requested to ensure sufficient valid questions
                    $generatedMCQs = generateMCQsWithGemini($topic, $toGenerate + 2);
                    
                    if (!empty($generatedMCQs)) {
                        foreach ($generatedMCQs as $genMCQ) {
                            if ($generatedCount >= $neededCount) break;
                            
                            $selectedQuestions[] = [
                                'mcq_id' => null,
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
        }
        
        // Shuffle final selection
        shuffle($selectedQuestions);
        // We do NOT slice here because we might have manual selections + random ones, 
        // and if manual + random > mcq_count, we generally want to keep manual ones.
        // But if user asked for 10 and selected 5, remaining is 5. Total 10.
        // If user asked for 5 and selected 10, remaining is -5. Logic skips filling.
        // So we just rely on the loop limits.
    
    } else if ($remaining_needed > 0) {
        // Chapter/Book based selection
        if (!empty($chapterIdsArray)) {
            // Get MCQs from specific chapters
            $questionsPerChapter = max(1, ceil($remaining_needed / count($chapterIdsArray)));
            $collectedMcqs = [];
            
            foreach ($chapterIdsArray as $chapterId) {
                $mcqs = $questionService->getRandomMCQs($chapterId, $questionsPerChapter); // Note: Service doesn't support exclude yet easily
                foreach ($mcqs as $mcq) {
                    if (in_array($mcq['mcq_id'], $exclude_ids)) continue; // Manual exclusion check
                    
                    $collectedMcqs[] = [
                        'mcq_id' => $mcq['mcq_id'],
                        'question' => $mcq['question'],
                        'option_a' => $mcq['option_a'],
                        'option_b' => $mcq['option_b'],
                        'option_c' => $mcq['option_c'],
                        'option_d' => $mcq['option_d'],
                        'correct_option' => $mcq['correct_option']
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
                    $mcqs = $questionService->getRandomMCQs($chapterId, $questionsPerChapter);
                    foreach ($mcqs as $mcq) {
                        if (in_array($mcq['mcq_id'], $exclude_ids)) continue; // Manual exclusion check
                        
                        $collectedMcqs[] = [
                            'mcq_id' => $mcq['mcq_id'],
                            'question' => $mcq['question'],
                            'option_a' => $mcq['option_a'],
                            'option_b' => $mcq['option_b'],
                            'option_c' => $mcq['option_c'],
                            'option_d' => $mcq['option_d'],
                            'correct_option' => $mcq['correct_option']
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
