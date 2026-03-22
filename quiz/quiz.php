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
$studyLevel = $_GET['study_level'] ?? $_POST['study_level'] ?? '';

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
    } else {
        // Fallback: Check if it's a comma-separated string
        $exploded = explode(',', urldecode($topics));
        $topicsArray = array_filter(array_map('trim', $exploded));
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
    
    $aiSql = "SELECT mcq_id, question, option_a, option_b, option_c, option_d, correct_option 
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
                'mcq_id' => 'ai_' . $row['mcq_id'],
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

// If still don't have enough questions, auto-generate in 1 API request
if (count($questions) < $mcq_count && !empty($topicsArray)) {
    $neededCount = $mcq_count - count($questions);
    $generatedMCQs = generateMCQsBulkWithGemini($topicsArray, $neededCount, $studyLevel ?? '');
    if (!empty($generatedMCQs)) {
        foreach ($generatedMCQs as $genMCQ) {
            $questions[] = [
                'mcq_id' => 'ai_' . ($genMCQ['id'] ?? $genMCQ['mcq_id'] ?? uniqid()),
                'question' => $genMCQ['question'],
                'option_a' => $genMCQ['option_a'],
                'option_b' => $genMCQ['option_b'],
                'option_c' => $genMCQ['option_c'],
                'option_d' => $genMCQ['option_d'],
                'correct_option' => $genMCQ['correct_option']
            ];
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

// Scan funny sounds directories
$correctSounds = [];
$incorrectSounds = [];

$correctDir = __DIR__ . '/funny_sounds/correct';
$incorrectDir = __DIR__ . '/funny_sounds/incorrect';

if (is_dir($correctDir)) {
    $files = scandir($correctDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && (str_ends_with($file, '.mp3') || str_ends_with($file, '.wav'))) {
            $correctSounds[] = $file;
        }
    }
}

if (is_dir($incorrectDir)) {
    $files = scandir($incorrectDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && (str_ends_with($file, '.mp3') || str_ends_with($file, '.wav'))) {
            $incorrectSounds[] = $file;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google tag (gtag.js) -->
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - <?= htmlspecialchars($book_name) ?> | Ahmad Learning Hub</title>
    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ─── Copy Protection ─────────────────────────────────── */
        * { -webkit-user-select: none; -moz-user-select: none; user-select: none; box-sizing: border-box; }

        /* ─── Base ────────────────────────────────────────────── */
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; margin: 0; padding: 0; }

        .main-content { padding: 24px 16px 60px; }

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
        .quiz-header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .quiz-title { font-size: 1.5rem; font-weight: 900; margin: 0; line-height: 1.2; }
        .quiz-subtitle { font-size: 0.9rem; opacity: 0.85; margin-top: 4px; }
        .quiz-timer-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 10px 18px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 800;
            border: 1px solid rgba(255,255,255,0.2);
            white-space: nowrap;
        }
        .quiz-timer-badge.warning { background: rgba(245,158,11,0.25); color: #fef3c7; }
        .quiz-timer-badge.danger { background: rgba(239,68,68,0.25); color: #fee2e2; animation: pulse 1s infinite; }

        @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.7; } }

        /* ─── Progress ────────────────────────────────────────── */
        .progress-track { height: 6px; background: rgba(255,255,255,0.2); }
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
        .q-difficulty {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 600;
        }

        .question-text {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.6;
            margin-bottom: 28px;
            padding: 20px 24px;
            background: #f8fafc;
            border-radius: 16px;
            border-left: 4px solid #4f46e5;
        }

        /* ─── Options ─────────────────────────────────────────── */
        .options { display: flex; flex-direction: column; gap: 14px; }
        .option {
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 20px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }
        .option::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(79,70,229,0.03));
            opacity: 0;
            transition: opacity 0.3s;
        }
        .option:hover:not(.disabled) {
            border-color: #818cf8;
            background: #f5f3ff;
            transform: translateX(6px);
            box-shadow: 0 4px 12px rgba(79,70,229,0.1);
        }
        .option:hover:not(.disabled)::before { opacity: 1; }

        .option-label {
            width: 38px; height: 38px;
            border-radius: 10px;
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            color: #64748b;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }
        .option-text { font-size: 1rem; font-weight: 500; color: #1e293b; line-height: 1.4; }

        /* Selected */
        .option.selected { border-color: #818cf8; background: #f5f3ff; }
        .option.selected .option-label { background: #4f46e5; border-color: #4f46e5; color: white; }

        /* Correct */
        .option.correct {
            border-color: #16a34a;
            background: linear-gradient(to right, #f0fdf4, #dcfce7);
            transform: none !important;
        }
        .option.correct .option-label { background: #16a34a; border-color: #16a34a; color: white; }
        .option.correct .option-text { color: #14532d; font-weight: 700; }

        /* Incorrect */
        .option.incorrect {
            border-color: #dc2626;
            background: linear-gradient(to right, #fff5f5, #fee2e2);
            animation: shake 0.4s ease;
        }
        .option.incorrect .option-label { background: #dc2626; border-color: #dc2626; color: white; }
        .option.incorrect .option-text { color: #7f1d1d; }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
        }

        .option.disabled { cursor: not-allowed; pointer-events: none; }

        /* ─── Feedback ────────────────────────────────────────── */
        .feedback-box {
            margin-top: 20px;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            display: none;
            align-items: center;
            gap: 10px;
            animation: fadeInUp 0.3s ease;
        }
        .feedback-box.correct-fb { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .feedback-box.incorrect-fb { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

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
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-quiz.primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            box-shadow: 0 6px 16px rgba(79,70,229,0.3);
        }
        .btn-quiz.primary:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(79,70,229,0.4);
        }
        .btn-quiz.primary:disabled { background: #94a3b8; box-shadow: none; cursor: not-allowed; }
        .btn-quiz.outline {
            background: white;
            color: #4f46e5;
            border: 2px solid #e2e8f0;
        }
        .btn-quiz.outline:hover { border-color: #4f46e5; background: #f5f3ff; }

        /* ─── Results Screen ──────────────────────────────────── */
        .results-screen { display: none; }

        .results-hero {
            text-align: center;
            padding: 48px 32px 32px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-bottom: 1px solid #e2e8f0;
        }
        .result-emoji { font-size: 5rem; margin-bottom: 16px; display: block; animation: bounceIn 0.6s cubic-bezier(0.175,0.885,0.32,1.275); }
        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            60% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        .results-title { font-size: 2rem; font-weight: 900; color: #0f172a; margin: 0 0 8px; }
        .results-subtitle { color: #64748b; font-size: 1rem; margin: 0; }

        .result-score-ring {
            width: 140px; height: 140px;
            border-radius: 50%;
            background: conic-gradient(var(--score-color, #4f46e5) var(--score-deg, 0deg), #e2e8f0 0deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 28px auto;
            position: relative;
            box-shadow: 0 8px 24px rgba(79,70,229,0.2);
        }
        .result-score-ring::before {
            content: '';
            position: absolute;
            inset: 12px;
            background: white;
            border-radius: 50%;
        }
        .score-number { position: relative; z-index: 1; font-size: 2.5rem; font-weight: 900; color: #0f172a; }
        .score-label { font-size: 0.75rem; color: #94a3b8; display: block; font-weight: 600; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 28px 32px;
            border-bottom: 1px solid #f1f5f9;
        }
        .stat-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 2px solid #e2e8f0;
        }
        .stat-card.correct-stat { border-color: #86efac; background: #f0fdf4; }
        .stat-card.incorrect-stat { border-color: #fca5a5; background: #fff5f5; }
        .stat-card.time-stat { border-color: #bfdbfe; background: #eff6ff; }
        .stat-value { font-size: 2.2rem; font-weight: 900; line-height: 1; }
        .stat-card.correct-stat .stat-value { color: #16a34a; }
        .stat-card.incorrect-stat .stat-value { color: #dc2626; }
        .stat-card.time-stat .stat-value { color: #2563eb; }
        .stat-label { font-size: 0.8rem; color: #64748b; font-weight: 700; margin-top: 6px; text-transform: uppercase; letter-spacing: 0.05em; }

        /* ─── Detailed Review ─────────────────────────────────── */
        .review-section { padding: 32px; }
        .review-section-title {
            font-size: 1.3rem;
            font-weight: 900;
            color: #0f172a;
            margin: 0 0 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .review-item {
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            margin-bottom: 20px;
            overflow: hidden;
            transition: box-shadow 0.3s ease;
        }
        .review-item:hover { box-shadow: 0 8px 16px rgba(0,0,0,0.08); }
        .review-item.is-correct { border-color: #86efac; }
        .review-item.is-incorrect { border-color: #fca5a5; }

        .review-item-header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .review-item.is-correct .review-item-header { background: #f0fdf4; }
        .review-item.is-incorrect .review-item-header { background: #fff5f5; }

        .review-status-icon {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .is-correct .review-status-icon { background: #16a34a; color: white; }
        .is-incorrect .review-status-icon { background: #dc2626; color: white; }

        .review-q-number { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
        .review-q-text { font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-top: 2px; }

        /* All-options review layout */
        .review-options-grid {
            padding: 16px 20px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: white;
        }
        .review-option {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 11px 16px;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
        }
        .review-option-letter {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 0.85rem;
            flex-shrink: 0;
            background: #e2e8f0; color: #64748b;
        }
        .review-option-text { font-size: 0.9rem; font-weight: 500; color: #374151; flex: 1; }
        .review-option-tag {
            font-size: 0.68rem; font-weight: 800;
            text-transform: uppercase;
            padding: 2px 8px; border-radius: 20px;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        /* State: correct answer */
        .review-option.opt-correct {
            background: #f0fdf4; border-color: #86efac;
        }
        .review-option.opt-correct .review-option-letter { background: #16a34a; color: white; }
        .review-option.opt-correct .review-option-text { color: #14532d; font-weight: 700; }
        .review-option.opt-correct .review-option-tag { background: #dcfce7; color: #15803d; }

        /* State: user's wrong pick */
        .review-option.opt-wrong {
            background: #fff5f5; border-color: #fca5a5;
        }
        .review-option.opt-wrong .review-option-letter { background: #dc2626; color: white; }
        .review-option.opt-wrong .review-option-text { color: #7f1d1d; }
        .review-option.opt-wrong .review-option-tag { background: #fee2e2; color: #b91c1c; }

        .review-actions {
            padding: 24px 32px;
            background: #f8fafc;
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            border-top: 1px solid #e2e8f0;
        }

        /* ─── Funny Mode ─────────────────────────────────────── */
        .funny-mode-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.4);
            color: white;
            padding: 10px 22px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2);
            transform: translateY(-2px);
        }
        .funny-mode-btn:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-4px);
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.3);
        }
        .funny-mode-btn.active {
            background: linear-gradient(135deg, #facc15 0%, #eab308 100%);
            color: #1e1b4b;
            border-color: #fef08a;
            box-shadow: 0 0 25px rgba(250, 204, 21, 0.6), inset 0 0 10px rgba(255, 255, 255, 0.3);
        }
        .funny-mode-btn.active i {
            animation: bounce 0.5s infinite alternate;
        }
        @keyframes bounce {
            from { transform: translateY(0); }
            to { transform: translateY(-2px); }
        }

        /* ─── Responsive ──────────────────────────────────────── */
        @media (max-width: 640px) {
            .quiz-body, .quiz-actions, .review-section, .stats-grid { padding: 20px 16px; }
            .quiz-header { padding: 20px 16px; }
            .quiz-header-top { flex-direction: column; gap: 12px; align-items: flex-start; }
            .question-text { font-size: 1rem; padding: 14px 16px; }
            .stats-grid { grid-template-columns: 1fr; gap: 12px; }
            .review-answers { grid-template-columns: 1fr; }
            .results-hero { padding: 32px 16px 24px; }
        }

        .hidden { display: none !important; }
    </style>
</head>
<body>
<?php include_once '../header.php'; ?>

<div class="main-content">
    <div class="quiz-container">
        <!-- Header -->
        <div class="quiz-header">
            <div class="quiz-header-top">
                <div>
                    <div class="quiz-title"><i class="fas fa-brain" style="opacity:0.8;"></i> <?= htmlspecialchars($book_name) ?> Quiz</div>
                    <div class="quiz-subtitle"><?= htmlspecialchars($class_name) ?> &nbsp;•&nbsp; <?= count($questions) ?> Questions</div>
                    <button id="funnyModeBtn" class="funny-mode-btn" onclick="toggleFunnyMode()">
                        <i class="fas fa-face-laugh-wink"></i> Funny Mode: OFF
                    </button>
                </div>
                <div class="quiz-timer-badge" id="timerBadge"><i class="fas fa-stopwatch"></i> 00:00</div>
            </div>
            <div class="progress-track">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <div class="progress-text" id="progressText">Question 0 of <?= count($questions) ?></div>
        </div>

        <!-- Quiz Body -->
        <div class="quiz-body" id="quizBody">
            <div id="questionContainer"></div>
            <div class="quiz-actions" id="quizActions">
                <button type="button" class="btn-quiz outline" id="skipBtn" onclick="skipQuestion()">
                    <i class="fas fa-forward"></i> Skip
                </button>
                <button type="button" class="btn-quiz primary" id="nextBtn" onclick="nextQuestion()" disabled>
                    Next <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Results Screen -->
        <div class="results-screen" id="resultsScreen">
            <div class="results-hero">
                <span class="result-emoji" id="resultEmoji">🎉</span>
                <h2 class="results-title" id="resultsTitle">Quiz Completed!</h2>
                <p class="results-subtitle" id="resultsSubtitle">Here's how you did</p>
                <div class="result-score-ring" id="scoreRing">
                    <div class="score-number" id="scoreNumber">0%</div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card correct-stat">
                    <div class="stat-value" id="correctCount">0</div>
                    <div class="stat-label"><i class="fas fa-check-circle"></i> Correct</div>
                </div>
                <div class="stat-card incorrect-stat">
                    <div class="stat-value" id="incorrectCount">0</div>
                    <div class="stat-label"><i class="fas fa-times-circle"></i> Incorrect</div>
                </div>
                <div class="stat-card time-stat">
                    <div class="stat-value" id="totalTime">0:00</div>
                    <div class="stat-label"><i class="fas fa-clock"></i> Time</div>
                </div>
            </div>

            <div class="review-section">
                <div class="review-section-title">
                    <i class="fas fa-list-check" style="color:#4f46e5;"></i> Detailed Review
                </div>
                <div id="reviewList"></div>
            </div>

            <div class="review-actions">
                <button class="btn-quiz outline" onclick="window.location.href='mcqs_topic.php'">
                    <i class="fas fa-search"></i> New Topics
                </button>
                <button class="btn-quiz outline" onclick="window.location.href='quiz_setup.php'">
                    <i class="fas fa-home"></i> Back to Setup
                </button>
                
            </div>
        </div>
    </div>
</div>

<script src="funny_sounds/funny_audio_manager.js"></script>
<script>
// ─── Data ──────────────────────────────────────────────────────────
const questions = <?= json_encode($questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let currentQuestion = 0;
let score = 0;
let answers = [];
let startTime = Date.now();
let questionStartTime = Date.now();
let quizStarted = false;
let quizCompleted = false;

// Funny Mode Sounds
const funnySounds = {
    correct: <?= json_encode($correctSounds) ?>,
    incorrect: <?= json_encode($incorrectSounds) ?>
};
let lastPlayedSound = { correct: null, incorrect: null };

// ─── Audio (Web Audio API) ─────────────────────────────────────────
const audioCtx = new (window.AudioContext || window.webkitAudioContext)();

/** Helper: play a sequence of oscillator notes */
function _playNotes(notes, waveType = 'sine', volume = 0.3) {
    notes.forEach(({ f, t, d }) => {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.value = f;
        osc.type = waveType;
        gain.gain.setValueAtTime(volume, audioCtx.currentTime + t);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + t + d);
        osc.start(audioCtx.currentTime + t);
        osc.stop(audioCtx.currentTime + t + d + 0.05);
    });
}

// ─── CORRECT Sound Effects (uncomment the one you want to use) ───────

// [CORRECT #1] Rising chord ding — cheerful, classic quiz feel
function soundCorrect_1() {
    _playNotes([
        { f: 523, t: 0,    d: 0.12 },   // C5
        { f: 659, t: 0.12, d: 0.12 },   // E5
        { f: 784, t: 0.24, d: 0.22 }    // G5
    ], 'sine', 0.3);
}

// [CORRECT #2] Two-tone success ping
// function soundCorrect_2() {
//     _playNotes([
//         { f: 800, t: 0,    d: 0.1 },
//         { f: 1050,t: 0.12, d: 0.18 }
//     ], 'sine', 0.28);
// }

// [CORRECT #3] Soft ascending 4-note fanfare
function soundCorrect_3() {
    _playNotes([
        { f: 440, t: 0,    d: 0.1 },
        { f: 554, t: 0.1,  d: 0.1 },
        { f: 659, t: 0.2,  d: 0.1 },
        { f: 880, t: 0.3,  d: 0.2 }
    ], 'triangle', 0.25);
}

// [CORRECT #4] Video game coin collect (short blip)
// function soundCorrect_4() {
//     _playNotes([
//         { f: 988,  t: 0,    d: 0.07 },
//         { f: 1319, t: 0.08, d: 0.12 }
//     ], 'square', 0.15);
// }

// [CORRECT #5] Deep bell chime
// function soundCorrect_5() {
//     _playNotes([
//         { f: 349, t: 0,   d: 0.4 },
//         { f: 523, t: 0.1, d: 0.35 }
//     ], 'sine', 0.2);
// }

// ─── WRONG Sound Effects (uncomment the one you want to use) ─────────

// [WRONG #1] Low descending buzz — default
function soundWrong_1() {
    _playNotes([
        { f: 311, t: 0,    d: 0.15 },
        { f: 261, t: 0.15, d: 0.28 }
    ], 'sawtooth', 0.28);
}

// [WRONG #2] Flat buzzer (game-show style)
// function soundWrong_2() {
//     _playNotes([
//         { f: 220, t: 0,   d: 0.35 }
//     ], 'sawtooth', 0.35);
// }

// [WRONG #3] Double-drop error tone
function soundWrong_3() {
    _playNotes([
        { f: 400, t: 0,    d: 0.12 },
        { f: 280, t: 0.14, d: 0.2  }
    ], 'square', 0.2);
}

// [WRONG #4] Soft thud (low frequency rumble)
// function soundWrong_4() {
//     _playNotes([
//         { f: 100, t: 0,    d: 0.18 },
//         { f: 80,  t: 0.12, d: 0.25 }
//     ], 'sine', 0.4);
// }

// [WRONG #5] Retro video game fail jingle
// function soundWrong_5() {
//     _playNotes([
//         { f: 494, t: 0,    d: 0.1 },
//         { f: 370, t: 0.1,  d: 0.1 },
//         { f: 294, t: 0.2,  d: 0.18 }
//     ], 'square', 0.18);
// }

/** Active dispatcher — swap function names above to switch effect */
function playSound(type) {
    const funnyList = type === 'correct' ? funnySounds.correct : funnySounds.incorrect;
    
    if (window.funnyModeActive && funnyList && funnyList.length > 0) {
        // Play funny sound if mode is active AND sounds exist in that folder
        playFunnySound(type);
    } else {
        // Otherwise play default oscillator sounds
        if (type === 'correct') soundCorrect_1();
        else                    soundWrong_1();
    }
}

/** Funny Mode Logic */
window.funnyModeActive = localStorage.getItem('funnyMode') === 'true';
function updateFunnyModeUI() {
    const btn = document.getElementById('funnyModeBtn');
    if (btn) {
        if (window.funnyModeActive) {
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-face-laugh-wink"></i> Funny Mode: ON';
        } else {
            btn.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-face-laugh-wink"></i> Funny Mode: OFF';
        }
    }
}

function toggleFunnyMode() {
    window.funnyModeActive = !window.funnyModeActive;
    localStorage.setItem('funnyMode', window.funnyModeActive);
    updateFunnyModeUI();
    
    // Play activation sound via manager (handles 3s limit & cache)
    FunnyAudioManager.toggleModeSound();
}

function playFunnySound(type) {
    const list = type === 'correct' ? funnySounds.correct : funnySounds.incorrect;
    if (!list || list.length === 0) return;

    let soundFile;
    if (list.length === 1) {
        soundFile = list[0];
    } else {
        do {
            soundFile = list[Math.floor(Math.random() * list.length)];
        } while (soundFile === lastPlayedSound[type] && list.length > 1);
    }
    
    lastPlayedSound[type] = soundFile;
    // Use manager for caching and 3s duration limit
    FunnyAudioManager.playAnswerSound(type, soundFile);
}

// ─── Pre-cache Funny Sounds ────────────────────────────────────────
if (funnySounds.correct) {
    funnySounds.correct.forEach(s => FunnyAudioManager._getAudio(`funny_sounds/correct/${s}`));
}
if (funnySounds.incorrect) {
    funnySounds.incorrect.forEach(s => FunnyAudioManager._getAudio(`funny_sounds/incorrect/${s}`));
}


// Initialize UI on load
document.addEventListener('DOMContentLoaded', updateFunnyModeUI);

// ─── Timer ─────────────────────────────────────────────────────────
let timerInterval = setInterval(updateTimer, 1000);

function updateTimer() {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const m = Math.floor(elapsed / 60);
    const s = elapsed % 60;
    const badge = document.getElementById('timerBadge');
    badge.innerHTML = `<i class="fas fa-stopwatch"></i> ${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
    badge.className = 'quiz-timer-badge' + (elapsed > 600 ? ' danger' : elapsed > 300 ? ' warning' : '');
}

// ─── Render Question ───────────────────────────────────────────────
function renderQuestion() {
    const q = questions[currentQuestion];
    const pct = (currentQuestion / questions.length) * 100;
    document.getElementById('progressFill').style.width = pct + '%';
    document.getElementById('progressText').textContent =
        `Question ${currentQuestion + 1} of ${questions.length}`;

    document.getElementById('questionContainer').innerHTML = `
        <div class="question-card">
            <div class="question-meta">
                <span class="q-badge">Q ${currentQuestion + 1} / ${questions.length}</span>
            </div>
            <div class="question-text">${q.question}</div>
            <div class="options" id="optionsContainer">
                ${['A','B','C','D'].map(letter => `
                    <div class="option" data-option="${letter}" id="opt-${letter}" onclick="selectOption('${letter}')">
                        <div class="option-label">${letter}</div>
                        <div class="option-text">${q['option_' + letter.toLowerCase()]}</div>
                    </div>
                `).join('')}
            </div>
            <div class="feedback-box" id="feedbackBox"></div>
        </div>
    `;

    document.getElementById('nextBtn').disabled = true;
    questionStartTime = Date.now();
}

// ─── Select Option ─────────────────────────────────────────────────
/** Helper: Identify which letter (A,B,C,D) is correct */
function getCorrectLetter(q) {
    const clean = s => s ? s.toString().trim().toLowerCase() : '';
    const correctVal = clean(q.correct_option);
    
    // 1. Check if it matches any option text exactly
    if (correctVal === clean(q.option_a)) return 'A';
    if (correctVal === clean(q.option_b)) return 'B';
    if (correctVal === clean(q.option_c)) return 'C';
    if (correctVal === clean(q.option_d)) return 'D';
    
    // 2. Check if it IS a letter
    if (['a','b','c','d'].includes(correctVal)) return correctVal.toUpperCase();
    
    // 3. Check if it's a number (1-4)
    if (correctVal === '1') return 'A';
    if (correctVal === '2') return 'B';
    if (correctVal === '3') return 'C';
    if (correctVal === '4') return 'D';
    
    return '';
}

// ─── Select Option ─────────────────────────────────────────────────
function selectOption(letter) {
    const q = questions[currentQuestion];
    const correctLetter = getCorrectLetter(q);
    const isCorrect = (letter === correctLetter);
    
    if (isCorrect) score++;

    answers[currentQuestion] = {
        selected: letter,
        selectedText: q['option_' + letter.toLowerCase()],
        correct: correctLetter,
        correctText: q['option_' + (correctLetter || 'a').toLowerCase()],
        isCorrect,
        question: q.question,
        options: { A: q.option_a, B: q.option_b, C: q.option_c, D: q.option_d },
        timeSpent: Math.floor((Date.now() - questionStartTime) / 1000)
    };

    // Disable all options
    ['A','B','C','D'].forEach(l => {
        const el = document.getElementById('opt-' + l);
        if (!el) return;
        el.classList.add('disabled');
        if (l === letter) el.classList.add(isCorrect ? 'correct' : 'incorrect');
        if (l === correctLetter && l !== letter) el.classList.add('correct');
    });

    // Feedback
    const fb = document.getElementById('feedbackBox');
    if (fb) {
        fb.className = 'feedback-box ' + (isCorrect ? 'correct-fb' : 'incorrect-fb');
        fb.innerHTML = isCorrect
            ? `<i class="fas fa-check-circle"></i> Excellent! That's the correct answer!`
            : `<i class="fas fa-times-circle"></i> Incorrect. The correct answer is <strong>${correctLetter}: ${q['option_' + correctLetter.toLowerCase()]}</strong>`;
        fb.style.display = 'flex';
    }

    // Sound
    playSound(isCorrect ? 'correct' : 'wrong');

    // Disable buttons
    document.getElementById('nextBtn').disabled = false;
    document.getElementById('skipBtn').disabled = true;

    // Auto-advance
    setTimeout(() => {
        if (currentQuestion < questions.length - 1) nextQuestion();
        else showResults();
    }, 2200);
}

// ─── Next / Skip ───────────────────────────────────────────────────
function nextQuestion() {
    if (currentQuestion >= questions.length - 1) { showResults(); return; }
    currentQuestion++;
    document.getElementById('nextBtn').disabled = true;
    document.getElementById('skipBtn').disabled = false;
    renderQuestion();
}

function skipQuestion() {
    const q = questions[currentQuestion];
    const correctLetter = getCorrectLetter(q);
    
    answers[currentQuestion] = {
        selected: null, 
        selectedText: 'Skipped', 
        isCorrect: false,
        correct: correctLetter, 
        correctText: q['option_' + (correctLetter || 'a').toLowerCase()], 
        question: q.question,
        options: { A: q.option_a, B: q.option_b, C: q.option_c, D: q.option_d },
        timeSpent: 0
    };
    nextQuestion();
}

// ─── Show Results ──────────────────────────────────────────────────
function showResults() {
    clearInterval(timerInterval);
    quizCompleted = true;

    const totalTime = Math.floor((Date.now() - startTime) / 1000);
    const pct = Math.round((score / questions.length) * 100);
    const incorrect = questions.length - score;

    // Switch view
    document.getElementById('quizBody').classList.add('hidden');
    const rs = document.getElementById('resultsScreen');
    rs.style.display = 'block';
    document.getElementById('progressFill').style.width = '100%';
    document.getElementById('progressText').textContent = 'Quiz Complete!';

    // Emoji & title
    let emoji = '🏆', title = 'Outstanding!', subtitle = 'Exceptional performance!';
    if (pct >= 90)      { emoji='🏆'; title='Outstanding!'; subtitle='You absolutely nailed it!'; }
    else if (pct >= 75) { emoji='🎉'; title='Great Job!'; subtitle='Solid performance, well done!'; }
    else if (pct >= 60) { emoji='👍'; title='Good Effort!'; subtitle='Keep practicing to improve.'; }
    else if (pct >= 40) { emoji='📚'; title='Keep Studying!'; subtitle='Review the topics and try again.'; }
    else                { emoji='💪'; title='Don\'t Give Up!'; subtitle='Every expert was once a beginner.'; }

    document.getElementById('resultEmoji').textContent = emoji;
    document.getElementById('resultsTitle').textContent = title;
    document.getElementById('resultsSubtitle').textContent = subtitle;

    // Score ring
    const deg = Math.round(pct * 3.6);
    const color = pct >= 75 ? '#16a34a' : pct >= 50 ? '#f59e0b' : '#dc2626';
    const ring = document.getElementById('scoreRing');
    ring.style.setProperty('--score-deg', deg + 'deg');
    ring.style.setProperty('--score-color', color);
    document.getElementById('scoreNumber').innerHTML = `${pct}%<span class="score-label">Score</span>`;

    // Stats
    document.getElementById('correctCount').textContent = score;
    document.getElementById('incorrectCount').textContent = incorrect;
    const mins = Math.floor(totalTime / 60), secs = totalTime % 60;
    document.getElementById('totalTime').textContent = `${mins}:${secs.toString().padStart(2,'0')}`;

    // Play specific result sounds via FunnyAudioManager
    setTimeout(() => FunnyAudioManager.playResultSound(pct), 300);

    // Detailed review
    const reviewList = document.getElementById('reviewList');
    reviewList.innerHTML = questions.map((q, i) => {
        const ans = answers[i] || { isCorrect: false, selected: null, selectedText: 'Not answered', correct: '', options: {} };
        const yourText = ans.selectedText || 'Not answered';
        const correctLetter = ans.correct || '?';
        const correctText = ans.options[correctLetter] || ans.correctText || 'N/A';
        const isWrong = !ans.isCorrect;

        return `
            <div class="review-item ${ans.isCorrect ? 'is-correct' : 'is-incorrect'}">
                <div class="review-item-header">
                    <div class="review-status-icon">
                        <i class="fas fa-${ans.isCorrect ? 'check' : 'times'}"></i>
                    </div>
                    <div>
                        <div class="review-q-number">Question ${i + 1}</div>
                        <div class="review-q-text">${q.question}</div>
                    </div>
                </div>
                <div class="review-options-grid">
                    ${['A','B','C','D'].map(l => {
                        const optText = ans.options[l] || 'N/A';
                        const isTheCorrect = (l === correctLetter);
                        const isTheWrong   = (l === ans.selected && !ans.isCorrect);
                        const isUserRight  = (l === ans.selected && ans.isCorrect);

                        let cls = '';
                        let tag = '';
                        if (isTheCorrect)   { cls = 'opt-correct'; tag = `<span class="review-option-tag">✓ Correct</span>`; }
                        if (isTheWrong)     { cls = 'opt-wrong';   tag = `<span class="review-option-tag">✗ Your pick</span>`; }
                        if (isUserRight)    { cls = 'opt-correct'; tag = `<span class="review-option-tag">✓ Your pick</span>`; }

                        return `
                            <div class="review-option ${cls}">
                                <div class="review-option-letter">${l}</div>
                                <div class="review-option-text">${optText}</div>
                                ${tag}
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }).join('');
}

// ─── Navigation Guards ─────────────────────────────────────────────
window.addEventListener('beforeunload', e => {
    if (quizStarted && !quizCompleted) {
        e.preventDefault(); e.returnValue = '';
    }
});
history.pushState(null, null, location.href);
window.addEventListener('popstate', () => {
    if (quizStarted && !quizCompleted) {
        history.pushState(null, null, location.href);
        if (confirm('Leave quiz? Your progress will be lost.')) {
            quizCompleted = true; window.location.href = 'quiz_setup.php';
        }
    }
});
document.addEventListener('keydown', e => {
    if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
        if (quizStarted && !quizCompleted) { e.preventDefault(); }
    }
    if (e.ctrlKey && ['c','a','v','x'].includes(e.key)) e.preventDefault();
    if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) e.preventDefault();
});
document.addEventListener('contextmenu', e => e.preventDefault());

// ─── Start ─────────────────────────────────────────────────────────
quizStarted = true;
FunnyAudioManager.playStartSound();
renderQuestion();
</script>

<?php include '../footer.php'; ?>
</body>
</html>
