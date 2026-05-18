<?php
session_start();
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../quiz/mcq_generator.php';

// Load environment variables
if (class_exists('EnvLoader')) {
    EnvLoader::load();
}

$exam_id = intval($_GET['exam_id'] ?? 0);
$is_custom = isset($_GET['custom']);
$user_answers = $_SESSION['test_answers'] ?? ['mcqs' => [], 'short' => [], 'long' => []];

// Fetch questions - prioritize session questions for accuracy
$questions_data = $_SESSION['current_test_questions'] ?? [
    'mcqs' => [],
    'short' => [],
    'long' => []
];

// Fallback to cache/DB if session is empty or doesn't match current request
$current_cache_key = "take_test_" . md5(serialize($_GET));
if (empty($questions_data['mcqs']) && empty($questions_data['short']) && empty($questions_data['long']) || ($_SESSION['current_test_key'] ?? '') !== $current_cache_key) {
    // If session doesn't match, try to load from cache
    require_once '../services/CacheManager.php';
    $cacheManager = new CacheManager();
    $cachedData = $cacheManager->get($current_cache_key);
    if ($cachedData && is_array($cachedData)) {
        $questions_data = $cachedData['questions_data'];
    }
}

// Still empty? Try to reconstruct (simplified fallback)
if (empty($questions_data['mcqs']) && empty($questions_data['short']) && empty($questions_data['long'])) {
    if ($exam_id) {
        $stmt = $conn->prepare("SELECT * FROM exam_preparations WHERE id = ?");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $exam = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exam && $exam['selection_type'] === 'manual') {
            $qids = json_decode($exam['question_ids'], true);
            // ... (rest of manual fetch logic)
        }
    }
}

$results = [
    'mcqs' => [],
    'subjective' => []
];

$ai_items_to_process = [];

// 1. Process MCQs (Auto-check - No AI for MCQs as per latest instructions)
foreach ($questions_data['mcqs'] as $m) {
    $id = $m['mcq_id'];
    $user_ans = $user_answers['mcqs'][$id] ?? null;
    $is_correct = false;
    if ($user_ans) {
        $is_correct = $user_ans['isCorrect'];
    }
    
    $explanation = $m['explanation'] ?? '';
    if (trim($explanation) === '' || $explanation === 'No explanation available.') {
        $explanation = 'No explanation provided for this question.';
    }

    $results['mcqs'][$id] = [
        'mcq_id' => $id,
        'question' => $m['question'],
        'correct_option' => $m['correct_option'],
        'user_selected' => $user_ans['selected'] ?? 'Not attempted',
        'is_correct' => $is_correct,
        'explanation' => $explanation
    ];
}

// 2. Process Subjective (AI-check only for ATTEMPTED questions)
$subjective_to_check = [];
foreach (['short', 'long'] as $type) {
    foreach ($questions_data[$type] as $q) {
        $id = $q['id'];
        $ans = trim($user_answers[$type][$id] ?? '');
        $typical = $q['typical_answer'] ?? '';
        
        $item = [
            'id' => $id,
            'type' => $type,
            'question' => $q['question_text'],
            'user_answer' => $ans,
            'typical_answer' => $typical,
            'max_marks' => ($type === 'short' ? 2 : 5),
            'attempted' => ($ans !== ''),
            'needs_typical' => (trim($typical) === '')
        ];

        // Store all questions to display in results
        $subjective_to_check[] = $item;

        // Only send to AI if attempted
        if ($item['attempted']) {
            $ai_items_to_process[] = array_merge($item, ['db_id' => $id]);
        }
    }
}

if (!empty($ai_items_to_process)) {
    $apiKey = EnvLoader::get('RECHECK_API_KEY');
    $model = EnvLoader::get('RECHECK_MODEL', 'qwen/qwen3-next-80b-a3b-instruct');

    $prompt = "You are an expert teacher. Evaluate a student's test and provide reference material. 
Focus on conceptual understanding. Be student-friendly. Use simple, easy language, avoid hard vocabulary.

For Subjective Questions (short/long):
1. Marks obtained (out of max marks).
2. Brief remarks.
3. Improvement tips.
4. A 'typical_answer' (simple, easy to understand version of the correct answer) - ONLY if typical_answer was not provided in the input.

For MCQs:
1. A brief 'explanation' of why the correct option is right - ONLY if it was requested.

Questions to process:
";

    foreach ($ai_items_to_process as $idx => $item) {
        $prompt .= "\n--- Item " . ($idx + 1) . " (" . strtoupper($item['type']) . ") ---\n";
        $prompt .= "Question: " . $item['question'] . "\n";
        
        if ($item['type'] === 'mcq') {
            $prompt .= "Options: A: {$item['options']['A']}, B: {$item['options']['B']}, C: {$item['options']['C']}, D: {$item['options']['D']}\n";
            $prompt .= "Correct Option: " . $item['correct_option'] . "\n";
            $prompt .= "Task: Provide a simple explanation.\n";
        } else {
            $prompt .= "Student Answer: " . ($item['user_answer'] ?: "(Not attempted)") . "\n";
            $prompt .= "Max Marks: " . $item['max_marks'] . "\n";
            if ($item['typical_answer']) {
                $prompt .= "Existing Typical Answer: " . $item['typical_answer'] . "\n";
            }
            $prompt .= "Task: " . ($item['user_answer'] ? "Evaluate + " : "") . ($item['needs_typical'] ? "Provide Typical Answer" : "") . "\n";
        }
    }

    $prompt .= "\nReturn a JSON object:
{
  \"results\": [
    {
      \"marks\": 1.5,
      \"remarks\": \"...\",
      \"tips\": \"...\",
      \"typical_answer\": \"... (simple version)\",
      \"explanation\": \"... (for mcqs)\"
    },
    ...
  ]
}";

    list($ai_response, $code) = callRecheckAi($apiKey, $model, $prompt);
    
    if ($ai_response) {
        $clean_json = preg_replace('/<think>.*?<\/think>/s', '', $ai_response);
        if (preg_match('/```json(.*?)```/s', $clean_json, $matches)) {
            $clean_json = trim($matches[1]);
        }
        $evals = json_decode($clean_json, true);
        
        if (isset($evals['results']) && is_array($evals['results'])) {
            foreach ($evals['results'] as $i => $ai_res) {
                if (!isset($ai_items_to_process[$i])) continue;
                
                $item = $ai_items_to_process[$i];
                $db_id = $item['db_id'];

                if ($item['type'] === 'mcq') {
                    if (isset($ai_res['explanation']) && trim($ai_res['explanation']) !== '') {
                        $results['mcqs'][$db_id]['explanation'] = $ai_res['explanation'];
                        // Save to DB
                        $stmt = $conn->prepare("UPDATE mcqs SET explanation = ? WHERE mcq_id = ? AND (explanation IS NULL OR explanation = '' OR explanation = 'No explanation available.')");
                        if ($stmt) {
                            $stmt->bind_param("si", $ai_res['explanation'], $db_id);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                } else {
                    // Find in subjective_to_check
                    foreach ($subjective_to_check as &$sub) {
                        if ($sub['id'] == $db_id && $sub['type'] == $item['type']) {
                            $sub['ai_result'] = $ai_res;
                            
                            // If AI provided a typical answer and we needed one, save it
                            if ($item['needs_typical'] && isset($ai_res['typical_answer']) && trim($ai_res['typical_answer']) !== '') {
                                $sub['typical_answer'] = $ai_res['typical_answer'];
                                $stmt = $conn->prepare("UPDATE questions SET typical_answer = ? WHERE id = ? AND (typical_answer IS NULL OR typical_answer = '')");
                                if ($stmt) {
                                    $stmt->bind_param("si", $ai_res['typical_answer'], $db_id);
                                    $stmt->execute();
                                    $stmt->close();
                                }
                            }
                            break;
                        }
                    }
                }
            }
        }
    }
}

$assetBase = '../';
include '../header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        --accent-shadow: 0 20px 50px rgba(0,0,0,0.05);
    }

    body {
        background: #f8fafc;
        font-family: 'Inter', sans-serif;
    }

    .results-container {
        max-width: 1000px;
        margin: 40px auto;
    }

    .main-card {
        background: white;
        border-radius: 24px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        border: 1px solid #f1f5f9;
    }

    .main-header {
        background: var(--primary-gradient);
        padding: 40px 20px;
        text-align: center;
        color: white;
    }

    .main-header h2 {
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 8px;
    }

    .section-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        color: #1e293b;
        margin: 40px 0 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.4rem;
    }

    .section-title i {
        width: 36px;
        height: 36px;
        background: #eef2ff;
        color: #4f46e5;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-size: 1.1rem;
    }

    /* MCQ Item Styling */
    .mcq-result-item {
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 20px;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }

    .mcq-result-item.correct {
        background: #f0fdf4;
        border-color: #dcfce7;
    }

    .mcq-result-item.incorrect {
        background: #fef2f2;
        border-color: #fee2e2;
    }

    .mcq-badge {
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        padding: 4px 12px;
        border-radius: 20px;
        margin-bottom: 12px;
        display: inline-block;
    }

    .badge-correct { background: #dcfce7; color: #166534; }
    .badge-incorrect { background: #fee2e2; color: #991b1b; }

    /* Subjective Card Styling */
    .subjective-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        margin-bottom: 30px;
        transition: transform 0.2s ease;
    }

    .subjective-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--card-shadow);
    }

    .question-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .marks-badge {
        background: #f1f5f9;
        color: #475569;
        font-weight: 700;
        padding: 6px 16px;
        border-radius: 12px;
        font-family: 'Outfit', sans-serif;
    }

    .user-answer-box {
        background: #f8fafc;
        border-radius: 12px;
        padding: 15px;
        border-left: 4px solid #cbd5e1;
        margin-bottom: 20px;
    }

    /* Feedback Box Styling */
    .feedback-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 15px;
    }

    .feedback-box {
        padding: 16px;
        border-radius: 14px;
        height: 100%;
        border: 1px solid transparent;
    }

    .feedback-box small {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 700;
        margin-bottom: 8px;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
    }

    .fb-remarks { background: #eff6ff; border-color: #dbeafe; color: #1e40af; }
    .fb-typical { background: #f0fdf4; border-color: #dcfce7; color: #166534; }
    .fb-tips { background: #fffbeb; border-color: #fef3c7; color: #854d0e; }

    .fb-content {
        font-size: 0.9rem;
        line-height: 1.6;
        color: #334155;
    }

    .skipped-notice {
        background: #f1f5f9;
        color: #64748b;
        padding: 15px;
        border-radius: 12px;
        text-align: center;
        font-size: 0.9rem;
    }

    @media print {
        body { background: white; }
        .results-container { margin: 0; max-width: 100%; }
        .main-card { box-shadow: none; border: none; }
        .no-print { display: none !important; }
    }
</style>

<div class="container py-4 results-container">
    <div class="main-card">
        <div class="main-header">
            <h2><i class="fas fa-award me-2"></i> Performance Analysis</h2>
            <p class="mb-0 opacity-75">Personalized Feedback & Conceptual Evaluation</p>
        </div>
        
        <div class="card-body p-4 p-md-5">
            
            <!-- MCQ Results -->
            <?php if (!empty($results['mcqs'])): ?>
                <h4 class="section-title"><i><i class="fas fa-tasks"></i></i> Section A: Objective (MCQs)</h4>
                <div class="row">
                <?php foreach ($results['mcqs'] as $m): ?>
                    <div class="col-12">
                        <div class="mcq-result-item <?= $m['is_correct'] ? 'correct' : 'incorrect' ?>">
                            <span class="mcq-badge <?= $m['is_correct'] ? 'badge-correct' : 'badge-incorrect' ?>">
                                <?= $m['is_correct'] ? '<i class="fas fa-check-circle me-1"></i> Correct' : '<i class="fas fa-times-circle me-1"></i> Incorrect' ?>
                            </span>
                            <p class="fw-bold text-dark mb-3">Q. <?= htmlspecialchars($m['question']) ?></p>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6 col-md-4">
                                    <div class="small text-muted mb-1">Your Selection</div>
                                    <div class="fw-bold"><?= htmlspecialchars($m['user_selected']) ?></div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="small text-muted mb-1">Correct Option</div>
                                    <div class="fw-bold text-success"><?= htmlspecialchars($m['correct_option']) ?></div>
                                </div>
                            </div>
                            
                            <div class="mt-3 pt-3 border-top border-2 border-white opacity-75">
                                <small class="fw-bold d-block mb-1"><i class="fas fa-info-circle me-1"></i> Explanation:</small>
                                <p class="mb-0 small"><?= htmlspecialchars($m['explanation']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Subjective Results -->
            <?php if (!empty($subjective_to_check)): ?>
                <h4 class="section-title"><i><i class="fas fa-pen-fancy"></i></i> Section B & C: Subjective Analysis</h4>
                <?php foreach ($subjective_to_check as $s): ?>
                    <div class="subjective-card p-4">
                        <div class="question-header">
                            <h6 class="fw-bold text-dark mb-0 pe-3">Q. <?= htmlspecialchars($s['question']) ?></h6>
                            <span class="marks-badge">
                                <?= $s['attempted'] ? ($s['ai_result']['marks'] ?? 0) : 0 ?> / <?= $s['max_marks'] ?> <span class="small opacity-50">Marks</span>
                            </span>
                        </div>
                        
                        <div class="user-answer-box mt-3">
                            <small class="text-muted d-block mb-2 uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">Your Response</small>
                            <?php if ($s['attempted']): ?>
                                <div class="text-dark"><?= nl2br(htmlspecialchars($s['user_answer'])) ?></div>
                            <?php else: ?>
                                <span class="text-danger fw-bold small"><i class="fas fa-exclamation-triangle me-1"></i> Question was skipped.</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($s['attempted']): ?>
                        <div class="feedback-grid">
                            <?php if (!empty($s['ai_result']['remarks'])): ?>
                            <div class="feedback-box fb-remarks">
                                <small><i class="fas fa-comment-dots"></i> Teacher's Remarks</small>
                                <div class="fb-content"><?= htmlspecialchars($s['ai_result']['remarks']) ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="feedback-box fb-typical">
                                <small><i class="fas fa-check-circle"></i> Typical Answer</small>
                                <div class="fb-content"><?= nl2br(htmlspecialchars($s['typical_answer'] ?? 'Preparing reference...')) ?></div>
                            </div>

                            <div class="feedback-box fb-tips">
                                <small><i class="fas fa-lightbulb"></i> Improvement Tips</small>
                                <div class="fb-content"><?= htmlspecialchars($s['ai_result']['tips'] ?? 'Keep focusing on the core concepts!') ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="skipped-notice">
                            <i class="fas fa-robot me-2 opacity-50"></i> AI Analysis is only available for attempted questions.
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3 text-muted opacity-20"><i class="fas fa-clipboard-list fa-4x"></i></div>
                    <h5 class="text-muted">No answers were submitted.</h5>
                    <p class="small text-secondary">Complete the test to see your personalized analysis here.</p>
                </div>
            <?php endif; ?>

            <div class="text-center mt-5 no-print pt-4 border-top">
                <button onclick="window.print()" class="btn btn-dark btn-lg px-5 shadow-sm me-3" style="border-radius: 14px; font-weight: 700;">
                    <i class="fas fa-print me-2"></i> Print Analysis
                </button>
                <a href="take_test.php<?= $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" class="btn btn-outline-primary btn-lg px-5" style="border-radius: 14px; font-weight: 700;">
                    <i class="fas fa-redo me-2"></i> Re-take Test
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Keeping these as fallbacks for legacy browsers */
.bg-success-subtle { background-color: #f0fff4 !important; }
.bg-danger-subtle { background-color: #fff5f5 !important; }
.bg-info-subtle { background-color: #e3f2fd !important; }
.bg-warning-subtle { background-color: #fffde7 !important; }
</style>

<?php include '../footer.php'; ?>
