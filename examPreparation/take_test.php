<?php
session_start();
include '../db_connect.php';

$exam_id = intval($_GET['exam_id'] ?? 0);
$is_custom = isset($_GET['custom']);

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
        fetchRandomQuestions($exam['class_id'], $exam['book_id'], $exam['chapter_ids'], $exam['mcq_count'], $exam['short_count'], $exam['long_count']);
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

    fetchRandomQuestions($class_id, $book_id, $chapter_ids, $mcq_count, $short_count, $long_count);
}

function fetchRandomQuestions($class_id, $book_id, $chapter_ids, $mcq_c, $short_c, $long_c) {
    global $conn, $questions_data;
    $where_chapters = $chapter_ids ? "AND chapter_id IN ($chapter_ids)" : "";

    if ($mcq_c > 0) {
        $res = $conn->query("SELECT * FROM mcqs WHERE class_id=$class_id AND book_id=$book_id $where_chapters ORDER BY RAND() LIMIT $mcq_c");
        while ($r = $res->fetch_assoc()) $questions_data['mcqs'][] = $r;
    }
    if ($short_c > 0) {
        $res = $conn->query("SELECT * FROM questions WHERE class_id=$class_id AND book_id=$book_id AND question_type='short' $where_chapters ORDER BY RAND() LIMIT $short_c");
        while ($r = $res->fetch_assoc()) $questions_data['short'][] = $r;
    }
    if ($long_c > 0) {
        $res = $conn->query("SELECT * FROM questions WHERE class_id=$class_id AND book_id=$book_id AND question_type='long' $where_chapters ORDER BY RAND() LIMIT $long_c");
        while ($r = $res->fetch_assoc()) $questions_data['long'][] = $r;
    }
}

// Fetch SEO data
$pageTitle = "Practice-Test-Assessment";
if (isset($exam)) {
    $pageTitle = str_replace(' ', '-', $exam['title']);
} elseif ($is_custom) {
    $info = $conn->query("SELECT b.book_name, c.class_name FROM book b JOIN class c ON b.class_id = c.class_id WHERE b.book_id = $book_id")->fetch_assoc();
    $pageTitle = str_replace(' ', '-', ($info['class_name'] ?? '')) . "-" . str_replace(' ', '-', ($info['book_name'] ?? '')) . "-Practice-Test";
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
            <p class="text-muted mb-4 no-print small"><i class="fas fa-info-circle me-1"></i> Click on an option to check your answer instantly.</p>
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
</script>

<?php include '../footer.php'; ?>
