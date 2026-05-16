<?php
session_start();
include '../db_connect.php';

// --- Review Submission Handler (Same as quiz.php) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    $action = $_POST['action'] ?? ($jsonInput['action'] ?? '');

    if ($action === 'submit_quiz_review') {
        header('Content-Type: application/json');

        $rating = intval($_POST['rating'] ?? ($jsonInput['rating'] ?? 0));
        $feedback = trim((string)($_POST['feedback'] ?? ($jsonInput['feedback'] ?? '')));

        if ($rating < 1 || $rating > 5) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Please select a valid rating.']);
            exit;
        }

        if ($feedback === '' || strlen($feedback) < 3) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Please share your feedback in the textbox.']);
            exit;
        }

        $feedback = substr($feedback, 0, 1000);
        $isLoggedIn = isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0;
        $isAnonymousRequested = intval($jsonInput['is_anonymous'] ?? 0) === 1;

        $userId = $isLoggedIn ? intval($_SESSION['user_id']) : null;
        $isAnonymous = ($isLoggedIn && !$isAnonymousRequested) ? 0 : 1;
        $reviewerName = ($isLoggedIn && !$isAnonymousRequested) ? trim((string)($_SESSION['name'] ?? 'User')) : 'Anonymous User';
        $reviewerEmail = ($isLoggedIn && !$isAnonymousRequested) ? trim((string)($_SESSION['email'] ?? '')) : null;

        $stmt = $conn->prepare("INSERT INTO user_reviews (user_id, reviewer_name, reviewer_email, rating, feedback, source_page, is_anonymous) VALUES (?, ?, ?, ?, ?, 'take_test', ?)");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Unable to save feedback right now.']);
            exit;
        }

        $stmt->bind_param('issisi', $userId, $reviewerName, $reviewerEmail, $rating, $feedback, $isAnonymous);
        $saved = $stmt->execute();
        $stmt->close();

        if (!$saved) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Unable to save feedback right now.']);
            exit;
        }

        $_SESSION['quiz_review_submitted'] = true;
        $_SESSION['site_review_submitted'] = true;

        echo json_encode(['status' => 'success', 'message' => 'Thank you for your review.']);
        exit;
    }
}

// Check if user has already reviewed
$isLoggedIn = isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0;
$hasAlreadyReviewed = false;
if ($isLoggedIn) {
    if (
        (isset($_SESSION['quiz_review_submitted']) && $_SESSION['quiz_review_submitted'] === true) ||
        (isset($_SESSION['site_review_submitted']) && $_SESSION['site_review_submitted'] === true)
    ) {
        $hasAlreadyReviewed = true;
    } else {
        $stmt = $conn->prepare("SELECT id FROM user_reviews WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $hasAlreadyReviewed = true;
            $_SESSION['quiz_review_submitted'] = true;
            $_SESSION['site_review_submitted'] = true;
        }
        $stmt->close();
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
            box-shadow: 0 20px 50px rgba(0,0,0,0.05);
            border-radius: 20px;
            max-width: 900px;
            margin: 40px auto;
            border: 1px solid #f1f5f9;
        }
        .paper-header {
            border-bottom: 3px solid #0f172a;
            padding-bottom: 25px;
            margin-bottom: 40px;
            text-align: center;
        }
        .paper-header h2 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .section-title {
            background: #f8fafc;
            padding: 12px 20px;
            border-left: 6px solid #4f46e5;
            margin: 40px 0 25px 0;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            color: #1e293b;
            text-transform: uppercase;
        }
        .question-item {
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .question-item:last-child { border-bottom: none; }
        
        .mcq-option {
            padding: 14px 20px;
            border: 2px solid #f1f5f9;
            border-radius: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
        }
        .mcq-option:hover {
            border-color: #e2e8f0;
            background: #f8fafc;
            transform: translateX(5px);
        }
        .mcq-option strong {
            width: 30px;
            height: 30px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .mcq-option.correct {
            background: #ecfdf5;
            border-color: #10b981;
            color: #065f46;
        }
        .mcq-option.correct strong { background: #10b981; color: white; }
        
        .mcq-option.wrong {
            background: #fef2f2;
            border-color: #ef4444;
            color: #991b1b;
        }
        .mcq-option.wrong strong { background: #ef4444; color: white; }

        .answer-box {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: 2px solid #f1f5f9;
            border-radius: 12px;
            margin-top: 15px;
            font-family: 'Inter', sans-serif;
            resize: vertical;
        }
        .answer-box:focus {
            outline: none;
            border-color: #4f46e5;
            background: #fafafa;
        }

        @media print {
            .ALH_nav, .ALH_footer, .no-print { display: none !important; }
            .paper-container { box-shadow: none; margin: 0; padding: 0; max-width: 100%; border: none; }
            body { padding-top: 0 !important; background: white !important; }
            .main-content { padding-top: 0 !important; }
            .mcq-option { border-color: #ddd; }
            .answer-box { border-color: #ddd; min-height: 200px; }
        }

        /* --- Attractive MCQ Notice --- */
        .mcq-notice {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            color: #1e40af;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .mcq-notice i {
            font-size: 1.2rem;
            color: #3b82f6;
            animation: pulse-blue 2s infinite;
        }
        @keyframes pulse-blue {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* --- Review Modal Styles (Same as quiz.php) --- */
        .review-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            padding: 20px;
            animation: alhFadeIn 0.3s ease;
        }
        @keyframes alhFadeIn { from { opacity: 0; } to { opacity: 1; } }
        .review-modal.open { display: flex; }
        .review-modal-card {
            width: 100%;
            max-width: 520px;
            max-height: calc(100vh - 40px);
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: scale(0.95);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .review-modal.open .review-modal-card { transform: scale(1); }
        .review-modal-header {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            color: #ffffff;
            padding: 32px 32px 24px;
            position: relative;
        }
        .review-modal-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 40px;
            background: linear-gradient(to top, #ffffff, transparent);
            opacity: 0.1;
        }
        .review-modal-title { margin: 0 0 8px; font-size: 1.6rem; font-weight: 800; line-height: 1.2; letter-spacing: -0.02em; }
        .review-modal-subtitle { margin: 0; font-size: 0.95rem; color: rgba(255, 255, 255, 0.9); line-height: 1.5; }
        .review-modal-body { padding: 32px; overflow-y: auto; flex: 1; }
        .star-row { display: flex; gap: 12px; margin-bottom: 24px; justify-content: center; }
        .star-btn {
            width: 54px; height: 54px; border-radius: 16px; border: 2px solid #f1f5f9;
            background: #f8fafc; color: #cbd5e1; font-size: 1.5rem; cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center;
        }
        .star-btn:hover { transform: scale(1.1) rotate(5deg); border-color: #fbbf24; color: #fbbf24; background: #fffbeb; box-shadow: 0 10px 15px -3px rgba(251, 191, 36, 0.2); }
        .star-btn.active { border-color: #f59e0b; background: #fff7ed; color: #f59e0b; transform: scale(1.05); box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.1); }
        .review-modal textarea {
            width: 100%; min-height: 140px; border-radius: 16px; border: 2px solid #f1f5f9;
            background: #f8fafc; padding: 16px; font-family: inherit; font-size: 1rem;
            color: #1e293b; resize: none; outline: none; transition: all 0.2s ease;
        }
        .review-modal textarea:focus { border-color: #6366f1; background: #ffffff; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }
        .review-modal-message { margin-top: 16px; font-size: 0.95rem; font-weight: 600; text-align: center; min-height: 24px; }
        .review-modal-message.error { color: #ef4444; }
        .review-modal-message.success { color: #10b981; }
        .review-modal-actions { display: flex; gap: 12px; padding: 0 32px 32px; }
        .review-modal-actions .btn-quiz {
            flex: 1; height: 48px; border-radius: 12px; font-weight: 700; font-size: 0.95rem;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.2s ease; cursor: pointer; border: none;
        }
        .review-modal-actions .btn-quiz.primary { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
        .review-modal-actions .btn-quiz.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(99, 102, 241, 0.4); }
        .review-modal-actions .btn-quiz.outline { background: #ffffff; color: #64748b; border: 2px solid #f1f5f9; }
        .review-modal-actions .btn-quiz.outline:hover { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
        @media (max-width: 640px) {
            .review-modal { padding: 12px; }
            .review-modal-card { border-radius: 20px; max-height: calc(100vh - 24px); }
            .review-modal-header { padding: 20px 24px; }
            .review-modal-title { font-size: 1.3rem; }
            .review-modal-body { padding: 20px 24px; }
            .review-modal-actions { padding: 0 24px 24px; flex-direction: column-reverse; gap: 10px; }
            .review-modal-actions .btn-quiz { width: 100%; height: 44px; }
            .star-btn { width: 44px; height: 44px; font-size: 1.1rem; }
            .review-modal textarea { min-height: 100px; padding: 12px; }
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
                <div class="question-item">
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
                <div class="question-item">
                    <p style="font-size: 1.1rem; font-weight: 600; color: #1e293b;">
                        Q<?= $index + 1 ?>. <?= htmlspecialchars($q['question_text']) ?>
                    </p>
                    <textarea class="answer-box no-print" placeholder="Write your answer here for practice..."></textarea>
                    <div class="print-only" style="display:none; height: 150px; border: 1px solid #eee; margin-top: 10px;"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Subjective Section (Long) -->
        <?php if (!empty($questions_data['long'])): ?>
            <div class="section-title">Section C: Descriptive / Long Questions</div>
            <?php foreach ($questions_data['long'] as $index => $q): ?>
                <div class="question-item">
                    <p style="font-size: 1.1rem; font-weight: 600; color: #1e293b;">
                        Q<?= $index + 1 ?>. <?= htmlspecialchars($q['question_text']) ?>
                    </p>
                    <textarea class="answer-box no-print" placeholder="Write a detailed answer here..."></textarea>
                    <div class="print-only" style="display:none; height: 300px; border: 1px solid #eee; margin-top: 10px;"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="text-center mt-5 pt-4" style="border-top: 1px solid #f1f5f9;">
            <p class="text-muted small">Quality Education by <strong>Ahmad Learning Hub</strong></p>
        </div>
    </div>

    <!-- Review Modal (Same as quiz.php) -->
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
                <a href="../reviews.php" class="btn-quiz outline" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;"><i class="fas fa-comments"></i> All Reviews</a>
                <button type="button" class="btn-quiz outline" onclick="closeReviewModal()">Skip</button>
                <button type="button" class="btn-quiz primary" id="submitReviewBtn" onclick="submitReview()">Submit Review</button>
            </div>
        </div>
    </div>
</div>

<script>
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
        // Find and highlight correct one
        container.querySelectorAll('.mcq-option').forEach(opt => {
            const optKey = opt.getAttribute('data-key');
            const optText = opt.querySelector('span').textContent.trim();
            if (optKey === correctKey || optText === correctKey) {
                opt.classList.add('correct');
            }
        });
    }
}

// --- Review Modal Logic (Same as quiz.php) ---
let selectedReviewRating = 0;
let reviewPopupShown = false;
const hasAlreadyReviewedServer = <?= json_encode($hasAlreadyReviewed) ?>;
const isLoggedIn = <?= json_encode($isLoggedIn) ?>;
const quizApiUrl = window.location.href;

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
        const response = await fetch(quizApiUrl, {
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

        localStorage.setItem('site_review_submitted', 'true');
        localStorage.setItem('quiz_review_submitted', 'true');

        setReviewMessage('Thank you for your feedback!', true);
        setTimeout(() => {
            closeReviewModal();
        }, 900);
    } catch (error) {
        setReviewMessage('Network issue. Please try again.');
        submitBtn.disabled = false;
    }
}

document.querySelectorAll('#starRow .star-btn').forEach(star => {
    star.addEventListener('click', function () {
        selectedReviewRating = Number(this.dataset.rating || 0);
        refreshStars();
        setReviewMessage('');
    });
    star.addEventListener('mouseenter', function() {
        refreshStars(Number(this.dataset.rating));
    });
    star.addEventListener('mouseleave', function() {
        refreshStars();
    });
});

// Show review popup after 30 seconds
const hasReviewedLocally = localStorage.getItem('site_review_submitted') === 'true' || localStorage.getItem('quiz_review_submitted') === 'true';
if (!hasAlreadyReviewedServer && !hasReviewedLocally) {
    setTimeout(() => {
        openReviewModal();
    }, 30000); // 30 seconds
}
</script>

<?php include '../footer.php'; ?>
