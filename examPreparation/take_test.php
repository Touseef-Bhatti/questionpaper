<?php
session_start();
include '../db_connect.php';

// --- POST Action Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    $action = $_POST['action'] ?? ($jsonInput['action'] ?? '');

    if ($action === 'save_answer') {
        header('Content-Type: application/json');
        $type = $jsonInput['type'] ?? '';
        $id = intval($jsonInput['id'] ?? 0);
        $answer = $jsonInput['answer'] ?? '';

        if (!isset($_SESSION['test_answers'])) {
            $_SESSION['test_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
        }

        $_SESSION['test_answers'][$type][$id] = $answer;
        session_write_close();
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'save_mcq_result') {
        header('Content-Type: application/json');
        $id = intval($jsonInput['id'] ?? 0);
        $isCorrect = $jsonInput['isCorrect'] ?? false;
        $selected = $jsonInput['selected'] ?? '';

        if (!isset($_SESSION['test_answers'])) {
            $_SESSION['test_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
        }

        $_SESSION['test_answers']['mcqs'][$id] = [
            'isCorrect' => $isCorrect,
            'selected' => $selected
        ];
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

        // Save subjective answers
        foreach ($answers as $a) {
            $type = $a['type'] ?? '';
            $id = intval($a['id'] ?? 0);
            $val = $a['answer'] ?? '';
            if ($type && $id) {
                $_SESSION['test_answers'][$type][$id] = $val;
            }
        }

        // Save MCQ results
        foreach ($mcqResults as $mcq) {
            $id = intval($mcq['id'] ?? 0);
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

// --- Caching Logic ---
require_once '../services/CacheManager.php';
$cacheManager = new CacheManager();
// Unique key based on parameters
$cacheKey = "take_test_" . md5(serialize($_GET));
$cachedData = $cacheManager->get($cacheKey);

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

    if ($exam_id) {
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

    // Store in cache for 1 hour (3600 seconds)
    $cacheManager->setex($cacheKey, 3600, [
        'questions_data' => $questions_data,
        'exam' => $exam ?? null,
        'pageTitle' => $pageTitle,
        'info' => $info
    ]);
}

function fetchRandomQuestions($class_id, $book_id, $chapter_ids, $mcq_c, $short_c, $long_c) {
    global $conn;
    $data = ['mcqs' => [], 'short' => [], 'long' => []];
    $where_chapters = $chapter_ids ? "AND chapter_id IN ($chapter_ids)" : "";

    if ($mcq_c > 0) {
        $res = $conn->query("SELECT * FROM mcqs WHERE class_id=$class_id AND book_id=$book_id $where_chapters ORDER BY RAND() LIMIT $mcq_c");
        while ($r = $res->fetch_assoc()) $data['mcqs'][] = $r;
    }
    if ($short_c > 0) {
        $res = $conn->query("SELECT * FROM questions WHERE class_id=$class_id AND book_id=$book_id AND question_type='short' $where_chapters ORDER BY RAND() LIMIT $short_c");
        while ($r = $res->fetch_assoc()) $data['short'][] = $r;
    }
    if ($long_c > 0) {
        $res = $conn->query("SELECT * FROM questions WHERE class_id=$class_id AND book_id=$book_id AND question_type='long' $where_chapters ORDER BY RAND() LIMIT $long_c");
        while ($r = $res->fetch_assoc()) $data['long'][] = $r;
    }
    return $data;
}

$assetBase = '../';
include '../header.php';

// Reset test answers in session only if it's a new test configuration
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $prev_key = $_SESSION['current_test_key'] ?? '';
    if ($prev_key !== $cacheKey) {
        $_SESSION['test_answers'] = ['mcqs' => [], 'short' => [], 'long' => []];
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
            border-radius: 12px;
            font-weight: 600;
            padding: 8px 18px;
            border: 2px solid #e2e8f0;
            color: #475569;
            background: white;
            transition: all 0.2s ease;
        }
        
        .question-item .btn-outline-primary:hover {
            border-color: #4f46e5;
            color: #4f46e5;
            background: #f5f3ff;
            transform: translateY(-1px);
        }
        
        .question-item .btn-success {
            border-radius: 12px;
            font-weight: 600;
            padding: 8px 18px;
            border: 2px solid #10b981;
            background: #10b981;
            color: white;
        }
        
        .question-item .btn-danger {
            border-radius: 12px;
            font-weight: 600;
            padding: 8px 18px;
            border: 2px solid #ef4444;
            background: #ef4444;
            color: white;
        }

        .btn-primary.btn-lg.px-5.shadow {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 16px 40px !important;
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.35), 0 8px 10px -6px rgba(79, 70, 229, 0.35) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-primary.btn-lg.px-5.shadow:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 35px -5px rgba(79, 70, 229, 0.45), 0 12px 15px -6px rgba(79, 70, 229, 0.45) !important;
            background: linear-gradient(135deg, #4338ca 0%, #6d28d9 100%);
        }
        
        .btn-primary.btn-lg.px-5.shadow:active {
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
    </style>
</head>
<body style="background: #f8fafc;">

<div class="main-content container py-4">
    <div class="no-print d-flex justify-content-between align-items-center mb-5" style="max-width: 900px; margin: 0 auto;">
        <a href="javascript:history.back()" class="btn btn-light shadow-sm" style="border-radius: 12px; font-weight: 600; padding: 10px 20px;">
            <i class="fas fa-arrow-left me-2"></i> Exit Test
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-dark shadow-sm" style="border-radius: 12px; font-weight: 600; padding: 10px 20px;">
                <i class="fas fa-print me-2"></i> Print Paper
            </button>
        </div>
    </div>

    <div class="paper-container">
        <div class="paper-header">
            <h2><?= isset($exam) ? htmlspecialchars($exam['title']) : 'Practice Test Assessment' ?></h2>
            <div class="row mt-4 text-start">
                <div class="col-6"><strong>Candidate Name:</strong> _____________________</div>
                <div class="col-6 text-end"><strong>Roll Number:</strong> _________________</div>
            </div>
            <div class="d-flex justify-content-between mt-3 text-muted small">
                <span><i class="far fa-clock me-1"></i> Duration: 1.5 Hours</span>
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
                <div class="question-item" data-db-id="<?= $m['mcq_id'] ?>">
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
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Subjective Section (Short) -->
        <?php if (!empty($questions_data['short'])): ?>
            <div class="section-title">Section B: Short Answer Questions</div>
            <?php foreach ($questions_data['short'] as $index => $q): ?>
                <div class="question-item" data-id="<?= $q['id'] ?>" data-type="short">
                    <p style="font-size: 1.1rem; font-weight: 600; color: #1e293b;">
                        Q<?= $index + 1 ?>. <?= htmlspecialchars($q['question_text']) ?>
                    </p>
                    <textarea class="answer-box no-print" placeholder="Write your answer here for practice..." id="answer_short_<?= $q['id'] ?>"><?= htmlspecialchars($_SESSION['test_answers']['short'][$q['id']] ?? '') ?></textarea>
                    <div class="text-end mt-2 no-print">
                        <button class="btn btn-sm btn-outline-primary" onclick="saveAnswer('short', <?= $q['id'] ?>)">
                            <i class="fas fa-save me-1"></i> Submit
                        </button>
                    </div>
                    <div class="print-only" style="display:none; height: 150px; border: 1px solid #eee; margin-top: 10px;"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Subjective Section (Long) -->
        <?php if (!empty($questions_data['long'])): ?>
            <div class="section-title">Section C: Descriptive / Long Questions</div>
            <?php foreach ($questions_data['long'] as $index => $q): ?>
                <div class="question-item" data-id="<?= $q['id'] ?>" data-type="long">
                    <p style="font-size: 1.1rem; font-weight: 600; color: #1e293b;">
                        Q<?= $index + 1 ?>. <?= htmlspecialchars($q['question_text']) ?>
                    </p>
                    <textarea class="answer-box no-print" placeholder="Write a detailed answer here..." id="answer_long_<?= $q['id'] ?>"><?= htmlspecialchars($_SESSION['test_answers']['long'][$q['id']] ?? '') ?></textarea>
                    <div class="text-end mt-2 no-print">
                        <button class="btn btn-sm btn-outline-primary" onclick="saveAnswer('long', <?= $q['id'] ?>)">
                            <i class="fas fa-save me-1"></i> Submit
                        </button>
                    </div>
                    <div class="print-only" style="display:none; height: 300px; border: 1px solid #eee; margin-top: 10px;"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="text-center mt-5 no-print">
            <button class="btn btn-primary btn-lg px-5 shadow" style="border-radius: 12px; font-weight: 700;" onclick="checkAll()">
                <i class="fas fa-check-double me-2"></i> Check All Answers
            </button>
        </div>

        <div class="text-center mt-5 pt-4" style="border-top: 1px solid #f1f5f9;">
            <p class="text-muted small">Quality Education by <strong>Ahmad Learning Hub</strong></p>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/ai_loader.php'; ?>

<script>
const quizApiUrl = window.location.href;

function checkMcq(element) {
    const container = element.closest('.mcq-options-container');
    if (container.classList.contains('answered')) return;
    
    const correctKey = container.getAttribute('data-correct');
    const selectedKey = element.getAttribute('data-key');
    const selectedText = element.querySelector('span').textContent.trim();
    
    container.classList.add('answered');
    
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

async function saveAnswer(type, id) {
    const textarea = document.getElementById(`answer_${type}_${id}`);
    if (!textarea) return;

    const answer = textarea.value.trim();
    const btn = textarea.nextElementSibling.querySelector('button');
    const originalText = btn.innerHTML;
    
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
            btn.innerHTML = '<i class="fas fa-check"></i> Saved';
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-success');
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-primary');
                btn.disabled = false;
            }, 2000);
        } else {
            throw new Error('Failed to save');
        }
    } catch (error) {
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
            id: parseInt(dbId),
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
