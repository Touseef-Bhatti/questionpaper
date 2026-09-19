<?php
session_start();
include '../db_connect.php';
require_once '../services/QuestionService.php';

// --- POST Action Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    $action = $_POST['action'] ?? ($jsonInput['action'] ?? '');

    if ($action === 'save_answer') {
        header('Content-Type: application/json');
        $type = $jsonInput['type'] ?? '';
        $id = preg_replace('/[^A-Za-z0-9_:-]/', '', (string)($jsonInput['id'] ?? ''));
        $answer = $jsonInput['answer'] ?? '';

        if (!isset($_SESSION['test_answers'])) {
            $_SESSION['test_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
        }

        if (!isset($_SESSION['submitted_answers'])) {
            $_SESSION['submitted_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
        }

        $_SESSION['test_answers'][$type][$id] = $answer;
        $_SESSION['submitted_answers'][$type][$id] = true;
        session_write_close();
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'edit_answer') {
        header('Content-Type: application/json');
        $type = $jsonInput['type'] ?? '';
        $id = preg_replace('/[^A-Za-z0-9_:-]/', '', (string)($jsonInput['id'] ?? ''));
        if (isset($_SESSION['submitted_answers'][$type][$id])) {
            unset($_SESSION['submitted_answers'][$type][$id]);
        }
        session_write_close();
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'save_draft_answer') {
        header('Content-Type: application/json');
        $type = $jsonInput['type'] ?? '';
        $id = preg_replace('/[^A-Za-z0-9_:-]/', '', (string)($jsonInput['id'] ?? ''));
        $answer = $jsonInput['answer'] ?? '';
        if (in_array($type, ['short', 'long'], true) && $id !== '') {
            if (!isset($_SESSION['test_answers'])) {
                $_SESSION['test_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
            }
            $_SESSION['test_answers'][$type][$id] = $answer;
        }
        session_write_close();
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'save_mcq_result') {
        header('Content-Type: application/json');
        $id = preg_replace('/[^A-Za-z0-9_:-]/', '', (string)($jsonInput['id'] ?? ''));
        $isCorrect = $jsonInput['isCorrect'] ?? false;
        $selected = $jsonInput['selected'] ?? '';

        if (!isset($_SESSION['test_answers'])) {
            $_SESSION['test_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
        }

        if (!isset($_SESSION['submitted_answers'])) {
            $_SESSION['submitted_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
        }

        $_SESSION['test_answers']['mcqs'][$id] = [
            'isCorrect' => $isCorrect,
            'selected' => $selected
        ];
        $_SESSION['submitted_answers']['mcqs'][$id] = true;
        session_write_close();
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'edit_mcq_result') {
        header('Content-Type: application/json');
        $id = preg_replace('/[^A-Za-z0-9_:-]/', '', (string)($jsonInput['id'] ?? ''));
        unset($_SESSION['submitted_answers']['mcqs'][$id], $_SESSION['test_answers']['mcqs'][$id]);
        session_write_close();
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'save_all_answers') {
        header('Content-Type: application/json');
        $answers = $jsonInput['answers'] ?? [];
        $mcqResults = $jsonInput['mcqResults'] ?? [];
        
        if (!isset($_SESSION['test_answers'])) {
            $_SESSION['test_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
        }

        if (!isset($_SESSION['submitted_answers'])) {
            $_SESSION['submitted_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
        }

        // Save subjective answers
        foreach ($answers as $a) {
            $type = $a['type'] ?? '';
            $id = preg_replace('/[^A-Za-z0-9_:-]/', '', (string)($a['id'] ?? ''));
            $val = $a['answer'] ?? '';
            if ($type && $id) {
                $_SESSION['test_answers'][$type][$id] = $val;
                $_SESSION['submitted_answers'][$type][$id] = true;
            }
        }

        // Save MCQ results
        foreach ($mcqResults as $mcq) {
            $id = preg_replace('/[^A-Za-z0-9_:-]/', '', (string)($mcq['id'] ?? ''));
            if ($id) {
                $_SESSION['test_answers']['mcqs'][$id] = [
                    'isCorrect' => $mcq['isCorrect'] ?? false,
                    'selected' => $mcq['selected'] ?? ''
                ];
            }
        }
        
        session_write_close();
        echo json_encode(['status' => 'success']);
        exit;
    }
}


$exam_id = intval($_GET['exam_id'] ?? 0);
$is_custom = isset($_GET['custom']);
$from_paper = isset($_POST['from_paper']);
$from_ai_paper = isset($_POST['from_ai_paper']);
$is_generated_paper = $from_paper || $from_ai_paper;
$resume_session_test = !$is_generated_paper && !$exam_id && !$is_custom && !empty($_SESSION['current_test_questions']);

// --- Caching Logic ---
require_once '../services/CacheManager.php';
$cacheManager = new CacheManager();
// Unique key based on parameters
$cacheKey = $resume_session_test
    ? ($_SESSION['current_test_key'] ?? '')
    : "take_test_" . md5(serialize($_GET) . '|' . serialize($_POST));
$cachedData = ($is_generated_paper || $resume_session_test) ? null : $cacheManager->get($cacheKey);

if ($cachedData && is_array($cachedData)) {
    $questions_data = $cachedData['questions_data'];
    $exam = $cachedData['exam'] ?? null;
    $pageTitle = $cachedData['pageTitle'];
    $info = $cachedData['info'] ?? null;
} else {
    $questions_data = [
        'mcqs' => [],
        'short' => [],
        'long' => []
    ];

    if ($resume_session_test) {
        $questions_data = $_SESSION['current_test_questions'];
    } elseif ($from_paper) {
        $mcqIds = decodeIdList($_POST['mcq_ids'] ?? '');
        $shortIds = decodeIdList($_POST['short_ids'] ?? '');
        $longIds = decodeIdList($_POST['long_ids'] ?? '');

        if (!empty($mcqIds)) {
            $questions_data['mcqs'] = fetchRowsByIds('mcqs', 'mcq_id', $mcqIds);
        }

        $subjectiveIds = array_values(array_unique(array_merge($shortIds, $longIds)));
        if (!empty($subjectiveIds)) {
            $rows = fetchRowsByIds('questions', 'id', $subjectiveIds);
            foreach ($rows as $row) {
                if (($row['question_type'] ?? '') === 'short') {
                    $questions_data['short'][] = $row;
                } else {
                    $questions_data['long'][] = $row;
                }
            }
        }
    } elseif ($from_ai_paper) {
        $aiQuestions = json_decode((string)($_POST['ai_questions_json'] ?? ''), true);
        if (is_array($aiQuestions)) {
            foreach (($aiQuestions['mcqs'] ?? []) as $index => $question) {
                if (empty($question['question'])) continue;
                $questionId = $question['mcq_id'] ?? 'ai_' . substr(sha1('mcq|' . $index . '|' . $question['question']), 0, 16);
                $questions_data['mcqs'][] = [
                    'mcq_id' => (string)$questionId,
                    'question' => (string)$question['question'],
                    'option_a' => (string)($question['option_a'] ?? ''),
                    'option_b' => (string)($question['option_b'] ?? ''),
                    'option_c' => (string)($question['option_c'] ?? ''),
                    'option_d' => (string)($question['option_d'] ?? ''),
                    'correct_option' => (string)($question['correct_option'] ?? ''),
                    'explanation' => (string)($question['explanation'] ?? '')
                ];
            }
            foreach (['short', 'long'] as $type) {
                foreach (($aiQuestions[$type] ?? []) as $index => $question) {
                    if (empty($question['question'])) continue;
                    $questionId = $question['id'] ?? 'ai_' . substr(sha1($type . '|' . $index . '|' . $question['question']), 0, 16);
                    $questions_data[$type][] = [
                        'id' => (string)$questionId,
                        'question_text' => (string)$question['question'],
                        'typical_answer' => (string)($question['typical_answer'] ?? '')
                    ];
                }
            }
        }
    } elseif ($exam_id) {
        // Fetch pre-created exam
        $stmt = $conn->prepare("SELECT * FROM exam_preparations WHERE id = ?");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $exam = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exam) die("Exam not found.");

        if ($exam['selection_type'] === 'manual' && !empty($exam['question_ids'])) {
            $qids = json_decode($exam['question_ids'], true);
            if (!is_array($qids)) $qids = [];
            
            $mcq_ids = [];
            $other_ids = [];
            foreach ($qids as $qid) {
                if (strpos($qid, 'mcq_') === 0) {
                    $mcq_ids[] = intval(substr($qid, 4));
                } else {
                    $other_ids[] = intval(substr($qid, 2));
                }
            }

            if (!empty($mcq_ids)) {
                $ids_str = implode(',', $mcq_ids);
                $res = $conn->query("SELECT * FROM mcqs WHERE mcq_id IN ($ids_str)");
                if ($res) while ($r = $res->fetch_assoc()) $questions_data['mcqs'][] = $r;
            }
            if (!empty($other_ids)) {
                $ids_str = implode(',', $other_ids);
                $res = $conn->query("SELECT * FROM questions WHERE id IN ($ids_str)");
                if ($res) {
                    while ($r = $res->fetch_assoc()) {
                        if ($r['question_type'] === 'short') $questions_data['short'][] = $r;
                        else $questions_data['long'][] = $r;
                    }
                }
            }
        } else {
            // Random selection based on exam rules OR if manual selection is empty
            $questions_data = fetchRandomQuestions($exam['class_id'], $exam['book_id'], $exam['chapter_ids'], $exam['mcq_count'], $exam['short_count'], $exam['long_count']);
        }
    } elseif ($is_custom) {
        $class_id = intval($_GET['class_id']);
        $book_id = intval($_GET['book_id']);
        
        // Handle chapter_ids as array (from form) or string (from SEO URL)
        $chapter_ids_input = $_GET['chapter_ids'] ?? '';
        if (is_array($chapter_ids_input)) {
            $chapter_ids = implode(',', array_map('intval', $chapter_ids_input));
        } else {
            // Handle hyphenated string from SEO URL
            $chapter_ids = implode(',', array_filter(array_map('intval', explode('-', $chapter_ids_input))));
        }

        $mcq_count = intval($_GET['mcq_count'] ?? 10);
        $short_count = intval($_GET['short_count'] ?? 5);
        $long_count = intval($_GET['long_count'] ?? 2);

        $questions_data = fetchRandomQuestions($class_id, $book_id, $chapter_ids, $mcq_count, $short_count, $long_count);
    }

    // Fetch SEO data
    $pageTitle = "Practice-Test-Assessment";
    $info = null;
    if (isset($exam)) {
        $pageTitle = str_replace(' ', '-', $exam['title']);
    } elseif ($is_custom) {
        $info = $conn->query("SELECT b.book_name, c.class_name FROM book b JOIN class c ON b.class_id = c.class_id WHERE b.book_id = $book_id")->fetch_assoc();
        $pageTitle = str_replace(' ', '-', ($info['class_name'] ?? '')) . "-" . str_replace(' ', '-', ($info['book_name'] ?? '')) . "-Practice-Test";
    }

    // Generated papers are already complete POST payloads; keep them isolated
    // from the shared GET cache.
    if (!$is_generated_paper) {
        $cacheManager->setex($cacheKey, 3600, [
            'questions_data' => $questions_data,
            'exam' => $exam ?? null,
            'pageTitle' => $pageTitle,
            'info' => $info
        ]);
    }
}

function decodeIdList($value) {
    $ids = is_array($value) ? $value : json_decode((string)$value, true);
    if (!is_array($ids)) return [];
    return array_values(array_unique(array_filter(array_map(static function ($id) {
        $id = trim((string)$id);
        return preg_match('/^(?:book_|bookq_)?[1-9][0-9]*$/', $id) ? $id : null;
    }, $ids))));
}

function fetchRowsByIds($table, $idColumn, array $ids) {
    global $conn;
    $allowed = ['mcqs' => 'mcq_id', 'questions' => 'id'];
    if (!isset($allowed[$table]) || $allowed[$table] !== $idColumn || empty($ids)) return [];

    $rows = [];

    $numericIds = array_values(array_filter($ids, static fn($id) => ctype_digit((string)$id)));
    if (!empty($numericIds)) {
        $placeholders = implode(',', array_fill(0, count($numericIds), '?'));
        $numericIds = array_map('intval', $numericIds);
        $stmt = $conn->prepare("SELECT * FROM {$table} WHERE {$idColumn} IN ({$placeholders})");
        if ($stmt) {
            $stmt->bind_param(str_repeat('i', count($numericIds)), ...$numericIds);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) $rows[] = $row;
            $stmt->close();
        }
    }

    $bookIds = array_values(array_filter($ids, static fn($id) => strpos((string)$id, $table === 'mcqs' ? 'book_' : 'bookq_') === 0));
    if (!empty($bookIds)) {
        $bookNumericIds = array_map(static fn($id) => intval(substr((string)$id, $table === 'mcqs' ? 5 : 6)), $bookIds);
        $placeholders = implode(',', array_fill(0, count($bookNumericIds), '?'));
        if ($table === 'mcqs') {
            $sql = "SELECT mcq_id, chapter_id, question, option_a, option_b, option_c, option_d, correct_option, '' AS explanation FROM mcqs_from_book WHERE mcq_id IN ({$placeholders})";
        } else {
            $sql = "SELECT CONCAT('bookq_', id) AS id, question_text, question_type, '' AS typical_answer FROM questions_from_book WHERE id IN ({$placeholders})";
        }
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param(str_repeat('i', count($bookNumericIds)), ...$bookNumericIds);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if ($table === 'mcqs') $row['mcq_id'] = 'book_' . $row['mcq_id'];
                $rows[] = $row;
            }
            $stmt->close();
        }
    }

    return $rows;
}

function fetchRandomQuestions($class_id, $book_id, $chapter_ids, $mcq_c, $short_c, $long_c) {
    global $conn;
    $data = ['mcqs' => [], 'short' => [], 'long' => []];

    $bookName = '';
    $bookStmt = $conn->prepare("SELECT book_name FROM book WHERE book_id = ? LIMIT 1");
    if ($bookStmt) {
        $bookStmt->bind_param('i', $book_id);
        $bookStmt->execute();
        $bookRow = $bookStmt->get_result()->fetch_assoc();
        $bookName = $bookRow['book_name'] ?? '';
        $bookStmt->close();
    }

    $chapterIds = array_values(array_filter(array_map('intval', explode(',', (string)$chapter_ids))));
    if (empty($chapterIds)) {
        $chapterStmt = $conn->prepare("SELECT chapter_id FROM chapter WHERE class_id = ? AND book_id = ?");
        if ($chapterStmt) {
            $chapterStmt->bind_param('ii', $class_id, $book_id);
            $chapterStmt->execute();
            $chapterResult = $chapterStmt->get_result();
            while ($row = $chapterResult->fetch_assoc()) {
                $chapterIds[] = intval($row['chapter_id']);
            }
            $chapterStmt->close();
        }
    }

    if (empty($chapterIds)) {
        return $data;
    }

    $questionService = new QuestionService($conn);
    $targets = [
        'mcqs' => ['count' => intval($mcq_c), 'type' => null],
        'short' => ['count' => intval($short_c), 'type' => 'short'],
        'long' => ['count' => intval($long_c), 'type' => 'long'],
    ];

    foreach ($targets as $bucket => $config) {
        $targetCount = max(0, (int)$config['count']);
        if ($targetCount <= 0) {
            continue;
        }
        $perChapter = max(1, (int)ceil($targetCount / count($chapterIds)));
        foreach ($chapterIds as $chapterId) {
            if ($bucket === 'mcqs') {
                $data[$bucket] = array_merge($data[$bucket], $questionService->getRandomMCQs($chapterId, $perChapter, $class_id, $bookName));
            } else {
                $data[$bucket] = array_merge($data[$bucket], $questionService->getRandomQuestions($chapterId, $config['type'], $perChapter, $class_id, $bookName));
            }
        }
        shuffle($data[$bucket]);
        $data[$bucket] = array_slice($data[$bucket], 0, $targetCount);
    }

    return $data;
}

$assetBase = '../';
include '../header.php';

// Reset test answers in session only if it's a new test configuration
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $is_generated_paper) {
    $prev_key = $_SESSION['current_test_key'] ?? '';
    if ($prev_key !== $cacheKey) {
        $_SESSION['test_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
        $_SESSION['submitted_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
    }
    // Update current test key and questions in session
    $_SESSION['current_test_key'] = $cacheKey;
    $_SESSION['current_test_questions'] = $questions_data;
}
?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Ahmad Learning Hub</title>
    <link rel="stylesheet" href="../css/exam_prep.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .paper-container {
            background: white;
            padding: 50px;
            box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.04), 0 30px 60px -15px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(15, 23, 42, 0.02);
            border-radius: 24px;
            max-width: 900px;
            margin: 40px auto;
            border: 1px solid rgba(241, 245, 249, 0.8);
            position: relative;
        }
        
        .paper-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 30px;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .paper-header h2 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            font-size: 2.2rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .paper-header .row {
            background: #f8fafc;
            padding: 20px 25px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            margin-top: 25px !important;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
        }
        
        .paper-header .col-6 {
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .paper-header .col-6 strong {
            color: #1e293b;
            font-weight: 700;
            margin-right: 8px;
        }
        
        .paper-header .d-flex.justify-content-between {
            margin-top: 20px !important;
            gap: 12px;
        }
        
        .paper-header .d-flex.justify-content-between span {
            display: inline-flex;
            align-items: center;
            background: #f1f5f9;
            color: #475569;
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        
        .paper-header .d-flex.justify-content-between span:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        
        .paper-header .d-flex.justify-content-between span i {
            color: #4f46e5;
            margin-right: 8px;
            font-size: 0.95rem;
        }

        .section-title {
            background: linear-gradient(90deg, #f8fafc 0%, rgba(248, 250, 252, 0) 100%);
            padding: 14px 20px;
            border-left: 5px solid #4f46e5;
            border-radius: 0 12px 12px 0;
            margin: 50px 0 30px 0;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            color: #0f172a;
            letter-spacing: -0.01em;
            text-transform: none;
            box-shadow: inset 1px 0 0 0 rgba(0,0,0,0.05);
        }
        
        .question-item {
            margin-bottom: 40px;
            padding: 28px;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.01);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .question-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -10px rgba(15, 23, 42, 0.05);
            border-color: #cbd5e1;
        }
        
        .question-item p {
            font-family: 'Inter', sans-serif;
            font-size: 1.05rem !important;
            line-height: 1.6;
            font-weight: 600;
            color: #1e293b !important;
            margin-bottom: 24px !important;
        }
        
        .mcq-option {
            padding: 16px 22px;
            border: 2px solid #f1f5f9;
            border-radius: 14px;
            margin-bottom: 14px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 14px;
            background: #fff;
            color: #334155;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.01);
        }
        
        .mcq-option:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            transform: translateX(6px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }
        
        .mcq-option strong {
            width: 32px;
            height: 32px;
            background: #f1f5f9;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }
        
        .mcq-option:hover strong {
            background: #e2e8f0;
            color: #0f172a;
        }
        
        .mcq-option.correct {
            background: #ecfdf5 !important;
            border-color: #10b981 !important;
            color: #065f46 !important;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.08) !important;
            font-weight: 600;
        }
        
        .mcq-option.correct strong {
            background: #10b981 !important;
            color: white !important;
        }
        
        .mcq-option.wrong {
            background: #fef2f2 !important;
            border-color: #ef4444 !important;
            color: #991b1b !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.08) !important;
            font-weight: 600;
        }
        
        .mcq-option.wrong strong {
            background: #ef4444 !important;
            color: white !important;
        }
        
        .answer-box {
            width: 100%;
            min-height: 140px;
            padding: 18px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            margin-top: 15px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            line-height: 1.6;
            resize: vertical;
            transition: all 0.2s ease;
            background: #f8fafc;
            color: #0f172a;
        }
        
        .answer-box:focus {
            outline: none;
            border-color: #4f46e5;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .question-item .btn-outline-primary {
            border-radius: 10px;
            font-weight: 600;
            padding: 8px 20px;
            border: 2px solid #4f46e5;
            color: #4f46e5;
            background: transparent;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .question-item .btn-outline-primary:hover {
            border-color: #4f46e5;
            color: white;
            background: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
        }
        
        .question-item .btn-success {
            border-radius: 10px;
            font-weight: 600;
            padding: 8px 20px;
            border: 2px solid #10b981;
            background: #10b981;
            color: white;
            transition: all 0.2s ease;
        }
        
        .question-item .btn-danger {
            border-radius: 10px;
            font-weight: 600;
            padding: 8px 20px;
            border: 2px solid #ef4444;
            background: #ef4444;
            color: white;
            transition: all 0.2s ease;
        }

        .btn-premium {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white !important;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            padding: 12px 26px;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.25);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        }
        .btn-premium:active {
            transform: translateY(0);
        }

        .btn-exit {
            background: #ffffff;
            color: #475569 !important;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-weight: 600;
            padding: 12px 26px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-exit:hover {
            background: #f8fafc;
            color: #0f172a !important;
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .btn-exit:active {
            transform: translateY(0);
        }

        .btn-check-all {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 16px 40px !important;
            color: white;
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3), 0 8px 10px -6px rgba(16, 185, 129, 0.3) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        
        .btn-check-all:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 35px -5px rgba(16, 185, 129, 0.4), 0 12px 15px -6px rgba(16, 185, 129, 0.4) !important;
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
        
        .btn-check-all:active {
            transform: translateY(-1px);
        }

        @media print {
            .ALH_nav, .ALH_footer, .no-print { display: none !important; }
            .paper-container { box-shadow: none; margin: 0; padding: 0; max-width: 100%; border: none; }
            body { padding-top: 0 !important; background: white !important; }
            .main-content { padding-top: 0 !important; }
            .mcq-option { border-color: #ddd; }
            .answer-box { border-color: #ddd; min-height: 200px; }
            .question-item { border: none; padding: 15px 0; margin-bottom: 25px; box-shadow: none; }
        }

        /* --- Attractive MCQ Notice --- */
        .mcq-notice {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #bfdbfe;
            border-radius: 16px;
            padding: 14px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
            color: #1e40af;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.03);
        }
        
        .mcq-notice i {
            font-size: 1.25rem;
            color: #3b82f6;
            animation: pulse-blue 2s infinite;
        }
        
        @keyframes pulse-blue {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        @media (max-width: 768px) {
            .paper-container { padding: 25px 20px; margin: 16px auto; border-radius: 18px; }
            .paper-header h2 { font-size: 1.6rem; }
            .paper-header .row { padding: 15px; }
            .section-title { font-size: 1.1rem; padding: 12px 16px; margin: 35px 0 20px 0; }
            .question-item { padding: 20px 16px; margin-bottom: 25px; }
            .question-item p { font-size: 0.98rem !important; margin-bottom: 18px !important; }
            .mcq-option { padding: 12px 16px; font-size: 0.9rem; gap: 10px; }
            .mcq-option strong { width: 28px; height: 28px; font-size: 0.85rem; border-radius: 8px; }
            .answer-box { min-height: 100px; padding: 14px; font-size: 0.9rem; }
        }

        .test-toolbar {
            max-width: 900px;
            margin: 0 auto 22px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, .06);
        }
        .test-topbar { max-width: 900px; margin: 0 auto 22px; min-height: 48px; display: flex; align-items: center; justify-content: space-between; gap: 14px; }
        .test-topbar-actions { display: flex; align-items: center; gap: 10px; }
        .test-topbar .btn-exit, .test-topbar .btn-premium { min-height: 46px; padding: 0 18px; }
        .timer-intro { display: flex; align-items: center; gap: 12px; min-width: 210px; }
        .timer-icon { width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 14px; color: #4338ca; background: #eef2ff; font-size: 1.15rem; }
        .timer-intro h3 { margin: 0; color: #0f172a; font: 800 1rem 'Outfit', sans-serif; }
        .timer-intro p { margin: 2px 0 0; color: #64748b; font-size: .78rem; }
        .timer-controls { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 8px; }
        .timer-controls label { color: #475569; font-size: .78rem; font-weight: 700; }
        .timer-controls select, .custom-time input { min-height: 44px; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; color: #0f172a; padding: 0 10px; font-size: .88rem; }
        .custom-time { display: inline-flex; align-items: center; gap: 4px; }
        .custom-time input { width: 58px; text-align: center; }
        .timer-button { min-height: 44px; border: 0; border-radius: 10px; padding: 0 13px; font-weight: 700; cursor: pointer; transition: background-color .2s, transform .2s; }
        .timer-button:focus-visible, .edit-answer-btn:focus-visible, .submit-answer-btn:focus-visible, .mcq-option:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 2px; }
        .timer-button:hover { transform: translateY(-1px); }
        .timer-start { background: #4f46e5; color: #fff; }
        .timer-pause { background: #fef3c7; color: #92400e; }
        .timer-reset { background: #f1f5f9; color: #334155; }
        .timer-display { min-width: 76px; padding: 8px 10px; border-radius: 10px; background: #0f172a; color: #fff; font: 800 1.15rem 'Outfit', sans-serif; text-align: center; letter-spacing: .04em; }
        .timer-display.warning { background: #b45309; }
        .timer-display.expired { background: #b91c1c; }
        .question-actions { min-height: 44px; display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
        .answer-status { margin-right: auto; color: #64748b; font-size: .82rem; font-weight: 700; }
        .answer-status i { color: #94a3b8; margin-right: 4px; }
        .answer-status.submitted { color: #047857; }
        .answer-status.submitted i { color: #10b981; }
        .answer-box.is-locked { background: #f1f5f9; color: #475569; cursor: not-allowed; border-color: #cbd5e1; }
        .submit-answer-btn { cursor: pointer !important; pointer-events: auto !important; }
        .edit-answer-btn { min-height: 42px; padding: 0 14px; border: 1px solid #c7d2fe; border-radius: 10px; background: #eef2ff; color: #4338ca; font-weight: 700; cursor: pointer; }
        .edit-answer-btn:hover { background: #e0e7ff; }
        .mcq-option { min-height: 52px; }
        .mcq-options-container.answered .mcq-option:not(.correct):not(.wrong) { opacity: .65; }
        .mcq-options-container.answered .mcq-option { cursor: not-allowed; }
        @media (max-width: 700px) {
            .test-topbar { margin-bottom: 16px; gap: 8px; }
            .test-topbar > *, .test-topbar-actions { flex: 1 1 0; }
            .test-topbar .btn-exit, .test-topbar .btn-premium { width: 100%; padding: 0 10px; font-size: .82rem; white-space: nowrap; }
            .test-toolbar { align-items: stretch; flex-direction: column; margin: 0 0 16px; padding: 15px; border-radius: 16px; }
            .timer-controls { justify-content: stretch; }
            .timer-controls > * { flex: 1 1 auto; }
            .timer-controls label { flex: 0 0 auto; align-self: center; }
            .timer-display { flex: 0 0 76px !important; }
            .paper-container { margin-top: 12px; }
            .paper-header .row .col-6 { width: 100%; text-align: left !important; margin-bottom: 7px; }
            .question-actions { justify-content: stretch; }
            .answer-status { flex: 1 1 100%; margin-right: 0; }
            .question-actions button { flex: 1 1 150px; }
        }
        @media (max-width: 390px) {
            .timer-controls label { flex-basis: 100%; }
            .timer-controls select, .timer-button { width: 100%; }
            .custom-time { width: 100%; justify-content: center; }
            .timer-display { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; scroll-behavior: auto !important; }
        }
    </style>
</head>
<body style="background: #f8fafc;">

<div class="main-content container py-4">
    <div class="test-topbar no-print">
        <a href="select_class_for_test.php" class="btn-exit shadow-sm">
            <i class="fas fa-arrow-left me-2"></i> Exit Test
        </a>
        <div class="test-topbar-actions">
            <button onclick="handleDownloadClick()" class="btn-premium shadow-sm">
                <i class="fas fa-download me-2"></i> Download Paper
            </button>
        </div>
    </div>

    <section class="test-toolbar no-print" aria-label="Test controls">
        <div class="timer-intro">
            <span class="timer-icon"><i class="fas fa-stopwatch" aria-hidden="true"></i></span>
            <div>
                <h3>Timed practice</h3>
                <p>Choose a duration, then start when you are ready.</p>
            </div>
        </div>
        <div class="timer-controls">
            <label for="timerPreset">Duration</label>
            <select id="timerPreset" aria-label="Choose test duration">
                <option value="600">10 minutes</option>
                <option value="1200">20 minutes</option>
                <option value="1800">30 minutes</option>
                <option value="3600">1 hour</option>
                <option value="7200">2 hours</option>
                <option value="custom">Custom</option>
            </select>
            <div class="custom-time" id="customTimeFields" hidden>
                <label class="visually-hidden" for="timerHours">Hours</label>
                <input id="timerHours" type="number" min="0" max="23" value="0" inputmode="numeric" placeholder="HH">
                <span>:</span>
                <label class="visually-hidden" for="timerMinutes">Minutes</label>
                <input id="timerMinutes" type="number" min="0" max="59" value="30" inputmode="numeric" placeholder="MM">
            </div>
            <button type="button" class="timer-button timer-start" id="startTimerBtn"><i class="fas fa-play" aria-hidden="true"></i> Start timer</button>
            <button type="button" class="timer-button timer-pause" id="pauseTimerBtn" hidden><i class="fas fa-pause" aria-hidden="true"></i> Pause</button>
            <button type="button" class="timer-button timer-reset" id="resetTimerBtn" hidden>Reset</button>
            <div class="timer-display" id="timerDisplay" aria-live="polite">10:00</div>
        </div>
    </section>

    <div class="paper-container">
        <div class="paper-header">
            <h2><?= isset($exam) ? htmlspecialchars($exam['title']) : 'Practice Test Assessment' ?></h2>
            <div class="row mt-4 text-start">
                <div class="col-6"><strong>Candidate Name:</strong> _____________________</div>
                <div class="col-6 text-end"><strong>Roll Number:</strong> _________________</div>
            </div>
            <div class="d-flex justify-content-between mt-3 text-muted small">
                <span id="paperDurationLabel"><i class="far fa-clock me-1"></i> Duration: Not started</span>
                <span><i class="fas fa-trophy me-1"></i> Total Marks: <?= (count($questions_data['mcqs']) * 1) + (count($questions_data['short']) * 2) + (count($questions_data['long']) * 5) ?></span>
            </div>
        </div>

        <!-- Objective Section (MCQs) -->
        <?php if (!empty($questions_data['mcqs'])): ?>
            <div class="section-title">Section A: Objective Type (MCQs)</div>
            <div class="mcq-notice no-print">
                <i class="fas fa-circle-info"></i>
                <span>Click on any option to check your answer instantly!</span>
            </div>
            <?php foreach ($questions_data['mcqs'] as $index => $m): ?>
                <?php $mcqSubmitted = !empty($_SESSION['submitted_answers']['mcqs'][$m['mcq_id']]); ?>
                <div class="question-item" data-db-id="<?= htmlspecialchars((string)$m['mcq_id']) ?>" data-submitted="<?= $mcqSubmitted ? '1' : '0' ?>">
                    <p style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 20px;">
                        Q<?= $index + 1 ?>. <?= htmlspecialchars($m['question']) ?>
                    </p>
                    <div class="row mcq-options-container" data-correct="<?= htmlspecialchars($m['correct_option']) ?>">
                        <?php 
                        $options = [
                            'A' => $m['option_a'],
                            'B' => $m['option_b'],
                            'C' => $m['option_c'],
                            'D' => $m['option_d']
                        ];
                        foreach ($options as $key => $val): ?>
                            <div class="col-md-6">
                                <div class="mcq-option" data-key="<?= $key ?>" onclick="checkMcq(this)">
                                    <strong><?= $key ?></strong> <span><?= htmlspecialchars($val) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="question-actions no-print">
                        <span class="answer-status<?= $mcqSubmitted ? ' submitted' : '' ?>" aria-live="polite"><i class="fas fa-circle-check" aria-hidden="true"></i> <span><?= $mcqSubmitted ? 'Submitted' : 'Not submitted' ?></span></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Subjective Section (Short) -->
        <?php if (!empty($questions_data['short'])): ?>
            <div class="section-title">Section B: Short Answer Questions</div>
            <?php foreach ($questions_data['short'] as $index => $q): ?>
                <div class="question-item" data-id="<?= htmlspecialchars((string)$q['id']) ?>" data-type="short">
                    <p style="font-size: 1.1rem; font-weight: 600; color: #1e293b;">
                        Q<?= $index + 1 ?>. <?= htmlspecialchars($q['question_text']) ?>
                    </p>
                    <?php $shortSubmitted = !empty($_SESSION['submitted_answers']['short'][$q['id']]); ?>
                    <textarea class="answer-box no-print<?= $shortSubmitted ? ' is-locked' : '' ?>" placeholder="Write your answer here for practice..." id="answer_short_<?= htmlspecialchars((string)$q['id']) ?>"<?= $shortSubmitted ? ' disabled' : '' ?>><?= htmlspecialchars($_SESSION['test_answers']['short'][$q['id']] ?? '') ?></textarea>
                    <div class="question-actions no-print">
                        <span class="answer-status<?= $shortSubmitted ? ' submitted' : '' ?>" aria-live="polite"><i class="fas fa-circle-check" aria-hidden="true"></i> <span><?= $shortSubmitted ? 'Submitted' : 'Not submitted' ?></span></span>
                        <button type="button" class="submit-answer-btn btn btn-sm btn-outline-primary" data-answer-type="short" data-answer-id="<?= htmlspecialchars((string)$q['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $shortSubmitted ? ' hidden' : '' ?>>
                            <i class="fas fa-paper-plane me-1" aria-hidden="true"></i> Submit answer
                        </button>
                        <button type="button" class="edit-answer-btn" data-answer-type="short" data-answer-id="<?= htmlspecialchars((string)$q['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $shortSubmitted ? '' : ' hidden' ?>><i class="fas fa-pen" aria-hidden="true"></i> Edit answer</button>
                    </div>
                    <div class="print-only" style="display:none; height: 150px; border: 1px solid #eee; margin-top: 10px;"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Subjective Section (Long) -->
        <?php if (!empty($questions_data['long'])): ?>
            <div class="section-title">Section C: Descriptive / Long Questions</div>
            <?php foreach ($questions_data['long'] as $index => $q): ?>
                <div class="question-item" data-id="<?= htmlspecialchars((string)$q['id']) ?>" data-type="long">
                    <p style="font-size: 1.1rem; font-weight: 600; color: #1e293b;">
                        Q<?= $index + 1 ?>. <?= htmlspecialchars($q['question_text']) ?>
                    </p>
                    <?php $longSubmitted = !empty($_SESSION['submitted_answers']['long'][$q['id']]); ?>
                    <textarea class="answer-box no-print<?= $longSubmitted ? ' is-locked' : '' ?>" placeholder="Write a detailed answer here..." id="answer_long_<?= htmlspecialchars((string)$q['id']) ?>"<?= $longSubmitted ? ' disabled' : '' ?>><?= htmlspecialchars($_SESSION['test_answers']['long'][$q['id']] ?? '') ?></textarea>
                    <div class="question-actions no-print">
                        <span class="answer-status<?= $longSubmitted ? ' submitted' : '' ?>" aria-live="polite"><i class="fas fa-circle-check" aria-hidden="true"></i> <span><?= $longSubmitted ? 'Submitted' : 'Not submitted' ?></span></span>
                        <button type="button" class="submit-answer-btn btn btn-sm btn-outline-primary" data-answer-type="long" data-answer-id="<?= htmlspecialchars((string)$q['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $longSubmitted ? ' hidden' : '' ?>>
                            <i class="fas fa-paper-plane me-1" aria-hidden="true"></i> Submit answer
                        </button>
                        <button type="button" class="edit-answer-btn" data-answer-type="long" data-answer-id="<?= htmlspecialchars((string)$q['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $longSubmitted ? '' : ' hidden' ?>><i class="fas fa-pen" aria-hidden="true"></i> Edit answer</button>
                    </div>
                    <div class="print-only" style="display:none; height: 300px; border: 1px solid #eee; margin-top: 10px;"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="text-center mt-5 no-print">
            <button class="btn-check-all px-5 shadow" onclick="checkAll()">
                <i class="fas fa-check-double me-2"></i> Check All Answers
            </button>
        </div>

        <div class="text-center mt-5 pt-4" style="border-top: 1px solid #f1f5f9;">
            <p class="text-muted small">Quality Education by <strong>Ahmad Learning Hub</strong></p>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/ai_loader.php'; ?>

<form id="downloadForm" action="../questionPaperFromTopic/download_docx.php" method="POST" target="_blank" style="display:none;">
    <input type="hidden" name="content" id="downloadContent">
    <input type="hidden" name="filename" id="downloadFilename">
</form>

<script>
const quizApiUrl = window.location.href;
const testStateStorageKey = <?= json_encode('take_test_state_' . $cacheKey) ?>;

function readTestState() {
    try { return JSON.parse(localStorage.getItem(testStateStorageKey) || '{}'); } catch (error) { return {}; }
}

function writeTestState(state) {
    try { localStorage.setItem(testStateStorageKey, JSON.stringify(state)); } catch (error) {}
}

function saveDraftLocally(type, id, answer) {
    const state = readTestState();
    state.answers = state.answers || { short: {}, long: {}, mcqs: {} };
    state.answers[type] = state.answers[type] || {};
    state.answers[type][id] = answer;
    writeTestState(state);
}

function queueDraftSave(textarea) {
    const item = textarea.closest('.question-item');
    if (!item) return;
    const type = item.getAttribute('data-type');
    const id = item.getAttribute('data-id');
    if (!type || !id) return;
    saveDraftLocally(type, id, textarea.value);
    window.clearTimeout(textarea._draftTimer);
    textarea._draftTimer = window.setTimeout(() => {
        fetch(quizApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_draft_answer', type, id, answer: textarea.value })
        }).catch(() => {});
    }, 250);
}

function handleDownloadClick() {
    triggerActualDownload();
}

function triggerActualDownload() {
    const paper = document.querySelector('.paper-container').cloneNode(true);
    
    // Remove all no-print elements
    paper.querySelectorAll('.no-print').forEach(el => el.remove());
    
    // Make sure all print-only elements are displayed in the export
    paper.querySelectorAll('.print-only').forEach(el => {
        el.style.display = 'block';
    });
    
    // Convert Candidate Name / Roll Number grid to table for perfect Word layout
    const headerRow = paper.querySelector('.paper-header .row');
    if (headerRow) {
        const table = document.createElement('table');
        table.style.width = '100%';
        table.style.marginTop = '20px';
        table.style.marginBottom = '20px';
        const tr = document.createElement('tr');
        
        const td1 = document.createElement('td');
        td1.style.width = '50%';
        td1.innerHTML = '<strong>Candidate Name:</strong> _____________________';
        
        const td2 = document.createElement('td');
        td2.style.width = '50%';
        td2.style.textAlign = 'right';
        td2.innerHTML = '<strong>Roll Number:</strong> _________________';
        
        tr.appendChild(td1);
        tr.appendChild(td2);
        table.appendChild(tr);
        headerRow.replaceWith(table);
    }
    
    // Convert Duration / Total Marks flexbox to table for perfect Word layout
    const flexRow = paper.querySelector('.paper-header .d-flex.justify-content-between');
    if (flexRow) {
        const table = document.createElement('table');
        table.style.width = '100%';
        table.style.marginTop = '10px';
        table.style.borderBottom = '2px solid #000';
        table.style.paddingBottom = '10px';
        const tr = document.createElement('tr');
        
        const spans = flexRow.querySelectorAll('span');
        const td1 = document.createElement('td');
        td1.style.width = '50%';
        td1.innerHTML = spans[0] ? spans[0].textContent.trim() : 'Duration: 1.5 Hours';
        
        const td2 = document.createElement('td');
        td2.style.width = '50%';
        td2.style.textAlign = 'right';
        td2.innerHTML = spans[1] ? spans[1].textContent.trim() : '';
        
        tr.appendChild(td1);
        tr.appendChild(td2);
        table.appendChild(tr);
        flexRow.replaceWith(table);
    }

    // Convert MCQ options to a neat 2-column table for Word export
    paper.querySelectorAll('.mcq-options-container').forEach(container => {
        const options = Array.from(container.querySelectorAll('.mcq-option'));
        if (options.length === 4) {
            const table = document.createElement('table');
            table.style.width = '100%';
            table.style.marginTop = '10px';
            table.style.marginBottom = '15px';
            
            const tr1 = document.createElement('tr');
            const tdA = document.createElement('td');
            tdA.style.width = '50%';
            tdA.style.padding = '5px';
            tdA.innerHTML = `(A) ${options[0].querySelector('span').innerHTML}`;
            
            const tdB = document.createElement('td');
            tdB.style.width = '50%';
            tdB.style.padding = '5px';
            tdB.innerHTML = `(B) ${options[1].querySelector('span').innerHTML}`;
            
            tr1.appendChild(tdA);
            tr1.appendChild(tdB);
            
            const tr2 = document.createElement('tr');
            const tdC = document.createElement('td');
            tdC.style.width = '50%';
            tdC.style.padding = '5px';
            tdC.innerHTML = `(C) ${options[2].querySelector('span').innerHTML}`;
            
            const tdD = document.createElement('td');
            tdD.style.width = '50%';
            tdD.style.padding = '5px';
            tdD.innerHTML = `(D) ${options[3].querySelector('span').innerHTML}`;
            
            tr2.appendChild(tdC);
            tr2.appendChild(tdD);
            
            table.appendChild(tr1);
            table.appendChild(tr2);
            
            container.replaceWith(table);
        }
    });
    
    const form = document.getElementById('downloadForm');
    const contentInput = document.getElementById('downloadContent');
    const filenameInput = document.getElementById('downloadFilename');
    
    contentInput.value = paper.innerHTML;
    const docTitle = document.title.split(' - ')[0] || 'Practice_Test_Assessment';
    filenameInput.value = docTitle.replace(/[^a-zA-Z0-9_.-]/g, '_') + '_' + new Date().toISOString().slice(0,10);
    
    form.submit();
}

function checkMcq(element) {
    const container = element.closest('.mcq-options-container');
    if (container.classList.contains('answered')) return;
    
    const correctKey = container.getAttribute('data-correct');
    const selectedKey = element.getAttribute('data-key');
    const selectedText = element.querySelector('span').textContent.trim();
    
    container.classList.add('answered');
    const questionItem = container.closest('.question-item');
    saveDraftLocally('mcqs', questionItem.getAttribute('data-db-id'), selectedKey);
    setQuestionSubmitted(questionItem, true);
    
    let isCorrect = (selectedKey === correctKey) || (selectedText === correctKey);
    
    if (isCorrect) {
        element.classList.add('correct');
    } else {
        element.classList.add('wrong');
        container.querySelectorAll('.mcq-option').forEach(opt => {
            const optKey = opt.getAttribute('data-key');
            const optText = opt.querySelector('span').textContent.trim();
            if (optKey === correctKey || optText === correctKey) {
                opt.classList.add('correct');
            }
        });
    }

    // Save MCQ result to session
    // We need the database ID of the MCQ. Let's ensure it's in the HTML.
    const dbId = container.closest('.question-item').getAttribute('data-db-id');
    if (dbId) {
        fetch(quizApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_mcq_result',
                id: dbId,
                isCorrect: isCorrect,
                selected: selectedKey
            })
        });
    }
}

function setQuestionSubmitted(questionItem, submitted) {
    if (!questionItem) return;
    const status = questionItem.querySelector('.answer-status');
    const label = status ? status.querySelector('span') : null;
    const editButton = questionItem.querySelector('.edit-answer-btn');
    if (status) status.classList.toggle('submitted', submitted);
    if (label) label.textContent = submitted ? 'Submitted' : 'Not submitted';
    if (editButton) editButton.hidden = !submitted;
}

function editMcq(button) {
    const questionItem = button.closest('.question-item');
    const container = questionItem ? questionItem.querySelector('.mcq-options-container') : null;
    if (!container) return;
    container.classList.remove('answered');
    container.querySelectorAll('.mcq-option').forEach(option => option.classList.remove('correct', 'wrong'));
    setQuestionSubmitted(questionItem, false);
    fetch(quizApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'edit_mcq_result', id: questionItem.getAttribute('data-db-id') })
    }).catch(() => {});
}

async function saveAnswer(type, id) {
    const textarea = document.getElementById(`answer_${type}_${id}`);
    if (!textarea) return;

    const answer = textarea.value.trim();
    const questionItem = textarea.closest('.question-item');
    const actionBar = questionItem ? questionItem.querySelector('.question-actions') : null;
    if (!actionBar) return;
    const btn = actionBar.querySelector('.submit-answer-btn');
    if (!btn) return;
    const originalText = btn.innerHTML;
    const editButton = actionBar.querySelector('.edit-answer-btn');

    // Apply the visible state immediately so the question does not appear
    // unchanged while the session-save request is in flight.
    textarea.disabled = true;
    textarea.classList.add('is-locked');
    btn.hidden = true;
    if (editButton) editButton.hidden = false;
    setQuestionSubmitted(questionItem, true);
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
        const response = await fetch(quizApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_answer',
                type: type,
                id: id,
                answer: answer
            })
        });
        
        if (response.ok) {
            btn.innerHTML = '<i class="fas fa-check"></i> Submitted';
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-success');
        } else {
            throw new Error('Failed to save');
        }
    } catch (error) {
        textarea.disabled = false;
        textarea.classList.remove('is-locked');
        btn.hidden = false;
        if (editButton) editButton.hidden = true;
        setQuestionSubmitted(questionItem, false);
        btn.innerHTML = '<i class="fas fa-times"></i> Error';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-danger');
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-outline-primary');
            btn.disabled = false;
        }, 2000);
    }
}

async function editAnswer(type, id) {
    const textarea = document.getElementById(`answer_${type}_${id}`);
    if (!textarea) return;
    textarea.disabled = false;
    textarea.classList.remove('is-locked');
    const questionItem = textarea.closest('.question-item');
    const actionBar = questionItem ? questionItem.querySelector('.question-actions') : null;
    if (!actionBar) return;
    const submitButton = actionBar.querySelector('.submit-answer-btn');
    const editButton = actionBar.querySelector('.edit-answer-btn');
    if (submitButton) { submitButton.hidden = false; submitButton.disabled = false; }
    if (editButton) editButton.hidden = true;
    setQuestionSubmitted(questionItem, false);
    textarea.focus();
    try {
        await fetch(quizApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'edit_answer', type, id })
        });
    } catch (error) {
        // The answer remains editable locally; it will be persisted on submit.
    }
}

const timerState = { remaining: 600, interval: null, running: false };

function persistTimer() {
    const state = readTestState();
    state.timer = {
        remaining: timerState.remaining,
        running: timerState.running,
        savedAt: Date.now(),
        preset: document.getElementById('timerPreset')?.value || '600',
        hours: document.getElementById('timerHours')?.value || '0',
        minutes: document.getElementById('timerMinutes')?.value || '30'
    };
    writeTestState(state);
}

function formatTimer(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    return `${hours > 0 ? String(hours).padStart(2, '0') + ':' : ''}${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function renderTimer() {
    const display = document.getElementById('timerDisplay');
    if (!display) return;
    display.textContent = formatTimer(timerState.remaining);
    const durationLabel = document.getElementById('paperDurationLabel');
    if (durationLabel) durationLabel.innerHTML = `<i class="far fa-clock me-1"></i> Duration: ${formatTimer(timerState.remaining)}`;
    display.classList.toggle('warning', timerState.remaining > 0 && timerState.remaining <= 60);
    display.classList.toggle('expired', timerState.remaining <= 0);
}

function selectedDuration() {
    const preset = document.getElementById('timerPreset').value;
    if (preset !== 'custom') return Number(preset);
    const hours = Math.max(0, Number(document.getElementById('timerHours').value) || 0);
    const minutes = Math.max(0, Math.min(59, Number(document.getElementById('timerMinutes').value) || 0));
    return (hours * 3600) + (minutes * 60);
}

function stopTimer() {
    window.clearInterval(timerState.interval);
    timerState.interval = null;
    timerState.running = false;
    persistTimer();
}

function startTimer() {
    if (timerState.running) return;
    if (timerState.remaining <= 0) timerState.remaining = selectedDuration();
    if (timerState.remaining <= 0) { alert('Please choose a valid duration.'); return; }
    timerState.running = true;
    document.getElementById('startTimerBtn').hidden = true;
    document.getElementById('pauseTimerBtn').hidden = false;
    document.getElementById('resetTimerBtn').hidden = false;
    document.getElementById('timerPreset').disabled = true;
    persistTimer();
    timerState.interval = window.setInterval(() => {
        timerState.remaining -= 1;
        renderTimer();
        persistTimer();
        if (timerState.remaining <= 0) {
            stopTimer();
            document.getElementById('pauseTimerBtn').hidden = true;
            alert('Time is up. Your test will be submitted now.');
            checkAll();
        }
    }, 1000);
}

function resetTimer() {
    stopTimer();
    timerState.remaining = selectedDuration();
    document.getElementById('startTimerBtn').hidden = false;
    document.getElementById('pauseTimerBtn').hidden = true;
    document.getElementById('resetTimerBtn').hidden = true;
    document.getElementById('timerPreset').disabled = false;
    const state = readTestState();
    delete state.timer;
    writeTestState(state);
    renderTimer();
}

document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', event => {
        const submitButton = event.target.closest('.submit-answer-btn');
        if (submitButton && !submitButton.hidden && !submitButton.disabled) {
            event.preventDefault();
            saveAnswer(submitButton.dataset.answerType, submitButton.dataset.answerId);
            return;
        }

        const editButton = event.target.closest('.edit-answer-btn');
        if (editButton && editButton.dataset.answerType && !editButton.hidden) {
            event.preventDefault();
            editAnswer(editButton.dataset.answerType, editButton.dataset.answerId);
        }
    });
    const savedState = readTestState();
    const savedTimer = savedState.timer;
    const preset = document.getElementById('timerPreset');
    const customFields = document.getElementById('customTimeFields');
    if (savedTimer) {
        if (savedTimer.preset) preset.value = savedTimer.preset;
        if (savedTimer.hours !== undefined) document.getElementById('timerHours').value = savedTimer.hours;
        if (savedTimer.minutes !== undefined) document.getElementById('timerMinutes').value = savedTimer.minutes;
        const elapsed = savedTimer.running ? Math.floor((Date.now() - Number(savedTimer.savedAt || Date.now())) / 1000) : 0;
        timerState.remaining = Math.max(0, Number(savedTimer.remaining || selectedDuration()) - elapsed);
        customFields.hidden = preset.value !== 'custom';
    }
    document.querySelectorAll('.answer-box').forEach(textarea => {
        const item = textarea.closest('.question-item');
        const type = item?.getAttribute('data-type');
        const id = item?.getAttribute('data-id');
        const savedAnswer = savedState.answers?.[type]?.[id];
        if (savedAnswer !== undefined && !textarea.disabled) textarea.value = savedAnswer;
        textarea.addEventListener('input', () => queueDraftSave(textarea));
    });
    document.querySelectorAll('.mcq-options-container').forEach(container => {
        const item = container.closest('.question-item');
        const savedChoice = savedState.answers?.mcqs?.[item?.getAttribute('data-db-id')];
        if (savedChoice && !container.classList.contains('answered')) {
            const option = Array.from(container.querySelectorAll('.mcq-option')).find(candidate => candidate.dataset.key === savedChoice);
            if (option) checkMcq(option);
        }
    });
    preset.addEventListener('change', () => {
        customFields.hidden = preset.value !== 'custom';
        if (!timerState.running) { timerState.remaining = selectedDuration(); renderTimer(); }
    });
    ['timerHours', 'timerMinutes'].forEach(id => document.getElementById(id).addEventListener('input', () => {
        if (!timerState.running && preset.value === 'custom') { timerState.remaining = selectedDuration(); renderTimer(); }
    }));
    document.getElementById('startTimerBtn').addEventListener('click', startTimer);
    document.getElementById('pauseTimerBtn').addEventListener('click', () => {
        stopTimer();
        document.getElementById('pauseTimerBtn').hidden = true;
        document.getElementById('startTimerBtn').hidden = false;
    });
    document.getElementById('resetTimerBtn').addEventListener('click', resetTimer);
    document.querySelectorAll('.question-item[data-submitted="1"]').forEach(item => {
        const options = item.querySelector('.mcq-options-container');
        if (options) options.classList.add('answered');
    });
    renderTimer();
    if (savedTimer?.running && timerState.remaining > 0) {
        timerState.running = false;
        startTimer();
    }
    window.addEventListener('beforeunload', persistTimer);
});

async function checkAll() {
    const btn = document.querySelector('button[onclick="checkAll()"]');
    btn.disabled = true;

    // Show AI loader
    showAILoader(
        [
            { label: 'Saving your answers', duration: 2000 },
            { label: 'Checking MCQ results', duration: 3000 },
            { label: 'AI is analyzing your responses', duration: 12000 },
            { label: 'Preparing your results page', duration: 8000 }
        ],
        'Your answers are being saved and analyzed...',
        'Submitting Test'
    );

    const answers = [];
    
    // Collect subjective answers (short/long)
    document.querySelectorAll('.answer-box').forEach(textarea => {
        const type = textarea.closest('.question-item').getAttribute('data-type');
        const id = textarea.closest('.question-item').getAttribute('data-id');
        if (type && id) {
            answers.push({
                type: type,
                id: id,
                answer: textarea.value.trim()
            });
        }
    });

    // Collect MCQ results from already-answered questions
    const mcqResults = [];
    document.querySelectorAll('.mcq-options-container.answered').forEach(container => {
        const questionItem = container.closest('.question-item');
        const dbId = questionItem ? questionItem.getAttribute('data-db-id') : null;
        if (!dbId) return;

        const selectedOpt = container.querySelector('.mcq-option.correct, .mcq-option.wrong');
        if (!selectedOpt) return;

        const selectedKey = selectedOpt.getAttribute('data-key');
        const isCorrect = selectedOpt.classList.contains('correct');

        mcqResults.push({
            id: dbId,
            isCorrect: isCorrect,
            selected: selectedKey
        });
    });

    try {
        await fetch(quizApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_all_answers',
                answers: answers,
                mcqResults: mcqResults
            })
        });
        window.location.href = 'check_test.php' + window.location.search;
    } catch (error) {
        console.error('Error saving answers:', error);
        hideAILoader();
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-times me-2"></i> Error Saving. Try Again.';
        btn.classList.add('btn-danger');
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-check-double me-2"></i> Check All Answers';
            btn.classList.remove('btn-danger');
        }, 3000);
    }
}

</script>

<?php include '../footer.php'; ?>
