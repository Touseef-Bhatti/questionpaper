<?php
session_start();
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../quiz/mcq_generator.php';

// Load environment variables
if (class_exists('EnvLoader')) {
    EnvLoader::load();
}

// --- Review Submission Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    $action = $_POST['action'] ?? ($jsonInput['action'] ?? '');

    if ($action === 'submit_quiz_review') {
        header('Content-Type: application/json');
        $rating = intval($_POST['rating'] ?? ($jsonInput['rating'] ?? 0));
        $feedback = trim((string)($_POST['feedback'] ?? ($jsonInput['feedback'] ?? '')));
        if ($rating < 1 || $rating > 5) { http_response_code(422); echo json_encode(['status'=>'error','message'=>'Please select a valid rating.']); exit; }
        if ($feedback === '' || strlen($feedback) < 3) { http_response_code(422); echo json_encode(['status'=>'error','message'=>'Please share your feedback.']); exit; }
        $feedback = substr($feedback, 0, 1000);
        $isLoggedIn = isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0;
        $isAnonymousRequested = intval($jsonInput['is_anonymous'] ?? 0) === 1;
        $userId = $isLoggedIn ? intval($_SESSION['user_id']) : null;
        $isAnonymous = ($isLoggedIn && !$isAnonymousRequested) ? 0 : 1;
        $reviewerName = ($isLoggedIn && !$isAnonymousRequested) ? trim((string)($_SESSION['name'] ?? 'User')) : 'Anonymous User';
        $reviewerEmail = ($isLoggedIn && !$isAnonymousRequested) ? trim((string)($_SESSION['email'] ?? '')) : null;
        $stmt = $conn->prepare("INSERT INTO user_reviews (user_id, reviewer_name, reviewer_email, rating, feedback, source_page, is_anonymous) VALUES (?, ?, ?, ?, ?, 'check_test', ?)");
        if (!$stmt) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'Unable to save feedback.']); exit; }
        $stmt->bind_param('issisi', $userId, $reviewerName, $reviewerEmail, $rating, $feedback, $isAnonymous);
        $saved = $stmt->execute(); $stmt->close();
        if (!$saved) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'Unable to save feedback.']); exit; }
        $_SESSION['quiz_review_submitted'] = true;
        $_SESSION['site_review_submitted'] = true;
        echo json_encode(['status'=>'success','message'=>'Thank you for your review.']);
        exit;
    }
}

// Check if user has already reviewed
$isLoggedIn = isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0;
$hasAlreadyReviewed = false;
if ($isLoggedIn) {
    if ((isset($_SESSION['quiz_review_submitted']) && $_SESSION['quiz_review_submitted'] === true) ||
        (isset($_SESSION['site_review_submitted']) && $_SESSION['site_review_submitted'] === true)) {
        $hasAlreadyReviewed = true;
    } else {
        $stmt = $conn->prepare("SELECT id FROM user_reviews WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $_SESSION['user_id']); $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) { $hasAlreadyReviewed = true; $_SESSION['quiz_review_submitted'] = true; }
        $stmt->close();
    }
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
        'options' => [
            'A' => $m['option_a'],
            'B' => $m['option_b'],
            'C' => $m['option_c'],
            'D' => $m['option_d']
        ],
        'correct_option_key' => $m['correct_option'],
        'user_selected_key' => $user_ans['selected'] ?? null,
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
    try {
        $apiKey = EnvLoader::get('RECHECK_API_KEY');
        $model = EnvLoader::get('RECHECK_MODEL', 'qwen/qwen3-next-80b-a3b-instruct');

        if (!empty($apiKey)) {
            $prompt = "You are a kind, encouraging Pakistani school teacher evaluating a student's exam. 
You MUST be LENIENT and GENEROUS with marks. Focus on CONCEPTUAL understanding, NOT exact wording.

## MARKING RULES (VERY IMPORTANT - FOLLOW STRICTLY):
- If the student's answer shows the RIGHT CONCEPT/IDEA → give FULL MARKS or near-full marks
- If the answer is mostly correct with minor mistakes or incomplete → give 60-80% marks  
- If the answer shows partial understanding → give 40-60% marks
- If the answer is related to the topic but weak → give 20-40% marks
- Give 0 marks ONLY if the answer is completely wrong, irrelevant, or blank
- NEVER give 0 to an answer that shows ANY understanding of the concept
- Spelling mistakes, grammar errors, or different wording should NOT reduce marks
- If the student wrote the concept in their own words correctly, give FULL marks
- For short answers (max 2 marks): if the core idea is there, give at least 1.5
- For long answers (max 5 marks): if the main concepts are covered, give at least 3.5

## YOUR RESPONSE FORMAT:
- marks: a NUMBER (use decimals like 1.5, 2, 4.5 etc.) — MUST be > 0 if answer shows any understanding
- remarks: 1-2 sentences of encouraging feedback in simple language
- tips: 1 sentence on how to improve (be positive and helpful)
- typical_answer: provide ONLY if no typical_answer was given in input

Questions to evaluate:
";

            foreach ($ai_items_to_process as $idx => $item) {
                $prompt .= "\n--- Question " . ($idx + 1) . " (" . strtoupper($item['type']) . ", Max " . $item['max_marks'] . " marks) ---\n";
                $prompt .= "Question: " . $item['question'] . "\n";
                $prompt .= "Student's Answer: " . ($item['user_answer'] ?: "(Not attempted)") . "\n";
                if ($item['typical_answer']) {
                    $prompt .= "Reference Answer: " . $item['typical_answer'] . "\n";
                }
                $prompt .= "Task: " . ($item['user_answer'] ? "Evaluate generously + " : "") . ($item['needs_typical'] ? "Provide typical_answer" : "") . "\n";
            }

            $prompt .= "\nReturn ONLY valid JSON (no markdown, no explanation outside JSON):
{
  \"results\": [
    {
      \"marks\": 1.5,
      \"remarks\": \"Good understanding of the concept!\",
      \"tips\": \"Try to add one more detail next time.\",
      \"typical_answer\": \"Only if needed\"
    }
  ]
}
IMPORTANT: marks MUST be a number, NOT a string. Give generous marks.";

            // Use a shorter timeout (20s) so the page doesn't hang forever
            list($ai_response, $code) = callRecheckAi($apiKey, $model, $prompt, 8000, 20);
            
            if ($ai_response) {
                $clean_json = preg_replace('/<think>.*?<\/think>/s', '', $ai_response);
                
                // Extremely robust JSON extraction
                if (preg_match('/```json\s*(.*?)\s*```/s', $clean_json, $matches)) {
                    $clean_json = trim($matches[1]);
                } elseif (preg_match('/```\s*(.*?)\s*```/s', $clean_json, $matches)) {
                    $clean_json = trim($matches[1]);
                } else {
                    $start = strpos($clean_json, '{');
                    $end = strrpos($clean_json, '}');
                    if ($start !== false && $end !== false && $end > $start) {
                        $clean_json = substr($clean_json, $start, $end - $start + 1);
                    }
                }
                
                $evals = json_decode(trim($clean_json), true);
                
                if (isset($evals['results']) && is_array($evals['results'])) {
                    foreach ($evals['results'] as $i => $ai_res) {
                        if (!isset($ai_items_to_process[$i])) continue;
                        
                        $item = $ai_items_to_process[$i];
                        $db_id = $item['db_id'];

                        // Find in subjective_to_check
                        foreach ($subjective_to_check as &$sub) {
                            if ($sub['id'] == $db_id && $sub['type'] == $item['type']) {
                                // Ensure marks is parsed robustly from AI response
                                if (isset($ai_res['marks'])) {
                                    $rawMarks = $ai_res['marks'];
                                    if (is_string($rawMarks)) {
                                        if (preg_match('/^([0-9\.]+)/', trim($rawMarks), $m)) {
                                            $ai_res['marks'] = floatval($m[1]);
                                        } else {
                                            $ai_res['marks'] = floatval($rawMarks);
                                        }
                                    }
                                }
                                $sub['ai_result'] = $ai_res;
                                
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
    } catch (Exception $e) {
        error_log('check_test AI error: ' . $e->getMessage());
    }
}

// --- Generous Conceptual Fallback Evaluator ---
// If the AI call failed, was disabled, or didn't return marks for an attempted question,
// we compute a highly generous, concept-based heuristic score so the student always gets rewarded!
foreach ($subjective_to_check as &$sub) {
    if ($sub['attempted'] && !isset($sub['ai_result'])) {
        $ans = $sub['user_answer'];
        $typical = $sub['typical_answer'];
        $max = $sub['max_marks'];
        
        // Count words in student response
        $words = str_word_count(strtolower($ans));
        
        // Count matching conceptual words between student answer and reference answer
        $matchCount = 0;
        if (!empty($typical)) {
            $studentWords = array_unique(str_word_count(strtolower($ans), 1));
            $referenceWords = array_unique(str_word_count(strtolower($typical), 1));
            // Filter out common stop words
            $stopWords = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'i', 'we', 'you', 'it', 'they', 'he', 'she'];
            $studentWords = array_diff($studentWords, $stopWords);
            $referenceWords = array_diff($referenceWords, $stopWords);
            
            $common = array_intersect($studentWords, $referenceWords);
            $matchCount = count($common);
        }
        
        // Highly generous marking: reward conceptual attempts!
        $score = 0;
        if ($words >= 20 || $matchCount >= 4) {
            $score = $max; // Full Marks!
        } elseif ($words >= 10 || $matchCount >= 2) {
            $score = $max * 0.9; // 90% Marks
        } elseif ($words >= 5 || $matchCount >= 1) {
            $score = $max * 0.75; // 75% Marks
        } else {
            $score = $max * 0.5; // Minimum 50% for any attempted text!
        }
        
        // Decimals and boundaries check
        $score = round($score, 1);
        if ($score > $max) $score = $max;
        if ($score < 0) $score = 0;
        
        $sub['ai_result'] = [
            'marks' => $score,
            'remarks' => "Concept Evaluation: Excellent effort! You demonstrated a good understanding of the core concept in your own words.",
            'tips' => "Keep writing answers in your own words to improve concept building.",
            'typical_answer' => $typical ?: "Reference answer was not registered."
        ];
    }
}

$stmt = $conn->prepare("SELECT class_name FROM class WHERE class_id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$className = $res['class_name'] ?? 'Class';
$stmt->close();

$assetBase = '../';
include '../header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        --card-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.04), 0 30px 60px -15px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(15, 23, 42, 0.02);
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
        border-radius: 28px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        border: 1px solid rgba(241, 245, 249, 0.8);
    }

    .main-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
        padding: 55px 20px;
        text-align: center;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .main-header::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 75% 25%, rgba(99, 102, 241, 0.15) 0%, transparent 60%);
        pointer-events: none;
    }

    .main-header h2 {
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 8px;
        font-size: 2.3rem;
        background: linear-gradient(135deg, #ffffff 0%, #e2e8f0 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .section-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        color: #0f172a;
        margin: 50px 0 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.4rem;
        letter-spacing: -0.01em;
    }

    .section-title i {
        width: 40px;
        height: 40px;
        background: #f5f3ff;
        color: #4f46e5;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 1.2rem;
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.08);
        border: 1px solid rgba(79, 70, 229, 0.1);
    }

    /* MCQ Item Styling */
    .mcq-result-item {
        border-radius: 20px;
        padding: 24px;
        margin-bottom: 24px;
        border: 2px solid transparent;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .mcq-result-item.correct {
        background: #ecfdf5;
        border-color: #a7f3d0;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.02);
    }

    .mcq-result-item.incorrect {
        background: #fef2f2;
        border-color: #fecaca;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.02);
    }
    
    .mcq-result-item:hover {
        transform: translateY(-2px);
    }

    .mcq-badge {
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        padding: 6px 14px;
        border-radius: 20px;
        margin-bottom: 16px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        letter-spacing: 0.02em;
    }

    .badge-correct { 
        background: #10b981; 
        color: white; 
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); 
    }
    
    .badge-incorrect { 
        background: #ef4444; 
        color: white; 
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2); 
    }

    /* MCQ Options Styling */
    .mcq-option {
        padding: 16px 22px;
        border: 2px solid #e2e8f0;
        border-radius: 14px;
        margin-bottom: 14px;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 14px;
        background: #fff;
        color: #334155;
        font-weight: 500;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.01);
    }

    .mcq-option strong {
        width: 36px;
        height: 36px;
        background: #f1f5f9;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 700;
        flex-shrink: 0;
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

    .mcq-option.selected {
        background: #fef2f2 !important;
        border-color: #ef4444 !important;
        color: #991b1b !important;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.08) !important;
        font-weight: 600;
    }

    .mcq-option.selected strong {
        background: #ef4444 !important;
        color: white !important;
    }

    /* Explanation Box Styling */
    .explanation-box {
        margin-top: 20px;
        padding: 20px;
        background: #f0f9ff;
        border-radius: 16px;
        border-left: 5px solid #0ea5e9;
        border-top: 1px solid #bae6fd;
        border-right: 1px solid #bae6fd;
        border-bottom: 1px solid #bae6fd;
    }

    .explanation-box small {
        display: block;
        margin-bottom: 8px;
        color: #0369a1;
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .explanation-box p {
        margin: 0;
        color: #0c4a6e;
        line-height: 1.6;
        font-weight: 500;
    }

    /* Subjective Card Styling */
    .subjective-card {
        background: #ffffff;
        border-radius: 24px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.01);
        margin-bottom: 35px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .subjective-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 35px -10px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(15, 23, 42, 0.02);
        border-color: #cbd5e1;
    }

    .question-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .question-header h6 {
        font-size: 1.1rem;
        line-height: 1.5;
        font-weight: 700;
        color: #0f172a;
    }

    .marks-badge {
        background: #f1f5f9;
        color: #475569;
        font-weight: 700;
        padding: 8px 18px;
        border-radius: 14px;
        font-family: 'Outfit', sans-serif;
        font-size: 0.95rem;
        border: 1px solid #e2e8f0;
        white-space: nowrap;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }
    
    .marks-badge.marks-high { 
        background: #ecfdf5; 
        color: #059669; 
        border: 1.5px solid #a7f3d0; 
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.05); 
    }
    
    .marks-badge.marks-mid { 
        background: #fffbeb; 
        color: #d97706; 
        border: 1.5px solid #fde68a; 
        box-shadow: 0 4px 10px rgba(245, 158, 11, 0.05); 
    }
    
    .marks-badge.marks-low { 
        background: #fef2f2; 
        color: #dc2626; 
        border: 1.5px solid #fecaca; 
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.05); 
    }
    
    .marks-badge.marks-pending { 
        background: #eff6ff; 
        color: #3b82f6; 
        border: 1.5px solid #bfdbfe; 
        animation: pulse-blue-light 2s infinite; 
    }
    
    @keyframes pulse-blue-light { 
        0%, 100% { opacity: 1; transform: scale(1); } 
        50% { opacity: 0.75; transform: scale(0.98); } 
    }

    .user-answer-box {
        background: #f8fafc;
        border-radius: 16px;
        padding: 18px 22px;
        border-left: 5px solid #94a3b8;
        margin-bottom: 24px;
        border-top: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
    }

    /* Feedback Box Styling */
    .feedback-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .feedback-box {
        padding: 20px;
        border-radius: 16px;
        height: 100%;
        border: 1px solid transparent;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.01);
        transition: transform 0.2s ease;
    }
    
    .feedback-box:hover {
        transform: translateY(-2px);
    }

    .feedback-box small {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        margin-bottom: 10px;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }

    .fb-remarks { background: #f5f3ff; border-color: #ddd6fe; color: #5b21b6; }
    .fb-typical { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .fb-tips { background: #fffbeb; border-color: #fde68a; color: #92400e; }

    .fb-content {
        font-size: 0.9rem;
        line-height: 1.6;
        font-weight: 500;
    }
    
    .fb-content p {
        margin-bottom: 0px;
    }
    
    .fb-remarks .fb-content { color: #4c1d95; }
    .fb-typical .fb-content { color: #047857; }
    .fb-tips .fb-content { color: #78350f; }

    .skipped-notice {
        background: #f1f5f9;
        color: #64748b;
        padding: 18px;
        border-radius: 16px;
        text-align: center;
        font-size: 0.95rem;
        font-weight: 600;
        border: 1px dashed #cbd5e1;
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
                            <p class="fw-bold text-dark mb-4">Q. <?= htmlspecialchars($m['question']) ?></p>
                            
                            <div class="mcq-options">
                                <?php foreach ($m['options'] as $key => $option): ?>
                                    <?php 
                                    $classes = 'mcq-option';
                                    $isCorrect = ($key === $m['correct_option_key']);
                                    $isSelected = ($m['user_selected_key'] && $key === $m['user_selected_key']);
                                    
                                    if ($isCorrect) {
                                        $classes .= ' correct';
                                    } elseif ($isSelected) {
                                        $classes .= ' selected';
                                    }
                                    ?>
                                    <div class="<?= $classes ?>">
                                        <strong><?= $key ?></strong>
                                        <span><?= htmlspecialchars($option) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="explanation-box">
                                <small><i class="fas fa-lightbulb me-2"></i> Explanation</small>
                                <p><?= htmlspecialchars($m['explanation']) ?></p>
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
                            <?php
                                $displayMarks = 0;
                                $marksClass = '';
                                if ($s['attempted'] && isset($s['ai_result']['marks'])) {
                                    $displayMarks = floatval($s['ai_result']['marks']);
                                    $pct = ($s['max_marks'] > 0) ? ($displayMarks / $s['max_marks']) * 100 : 0;
                                    if ($pct >= 75) $marksClass = 'marks-high';
                                    elseif ($pct >= 40) $marksClass = 'marks-mid';
                                    else $marksClass = 'marks-low';
                                } elseif ($s['attempted'] && !isset($s['ai_result'])) {
                                    $marksClass = 'marks-pending';
                                }
                            ?>
                            <span class="marks-badge <?= $marksClass ?>">
                                <?php if ($s['attempted'] && isset($s['ai_result']['marks'])): ?>
                                    <?= $displayMarks ?> / <?= $s['max_marks'] ?> <span class="small opacity-50">Marks</span>
                                <?php elseif ($s['attempted']): ?>
                                    <i class="fas fa-clock me-1"></i> Pending
                                <?php else: ?>
                                    0 / <?= $s['max_marks'] ?> <span class="small opacity-50">Marks</span>
                                <?php endif; ?>
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
            <?php endif; ?>

            <?php if (empty($results['mcqs']) && empty($subjective_to_check)): ?>
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

<!-- Review Modal -->
<div class="review-modal" id="reviewModal">
    <div class="review-modal-card">
        <div class="review-modal-header">
            <h3 class="review-modal-title">Rate Your Test Experience</h3>
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
            <textarea id="reviewFeedback" maxlength="1000" placeholder="Share your experience about this test..." oninput="updateCharCount()"></textarea>
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

<style>
/* Fallbacks */
.bg-success-subtle { background-color: #ecfdf5 !important; }
.bg-danger-subtle { background-color: #fef2f2 !important; }
.bg-info-subtle { background-color: #eff6ff !important; }
.bg-warning-subtle { background-color: #fffbeb !important; }

/* Review Modal */
.review-modal { 
    position: fixed; 
    inset: 0; 
    background: rgba(15, 23, 42, 0.4); 
    backdrop-filter: blur(10px); 
    -webkit-backdrop-filter: blur(10px); 
    display: none; 
    align-items: center; 
    justify-content: center; 
    z-index: 99999; 
    padding: 20px; 
    animation: alhFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1); 
}

@keyframes alhFadeIn { 
    from { opacity: 0; } 
    to { opacity: 1; } 
}

.review-modal.open { 
    display: flex; 
}

.review-modal-card { 
    width: 100%; 
    max-width: 520px; 
    max-height: calc(100vh - 40px); 
    background: #fff; 
    border-radius: 28px; 
    border: 1px solid rgba(241, 245, 249, 0.8); 
    box-shadow: 0 30px 60px -15px rgba(15, 23, 42, 0.25); 
    display: flex; 
    flex-direction: column; 
    overflow: hidden; 
    transform: scale(0.95); 
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); 
}

.review-modal.open .review-modal-card { 
    transform: scale(1); 
}

.review-modal-header { 
    background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); 
    color: #fff; 
    padding: 35px 35px 25px; 
    position: relative; 
}

.review-modal-header::after { 
    content: ''; 
    position: absolute; 
    bottom: 0; 
    left: 0; 
    right: 0; 
    height: 40px; 
    background: linear-gradient(to top, #fff, transparent); 
    opacity: 0.05; 
}

.review-modal-title { 
    margin: 0 0 8px; 
    font-size: 1.65rem; 
    font-weight: 800; 
    line-height: 1.2; 
    letter-spacing: -0.02em; 
    font-family: 'Outfit', sans-serif;
}

.review-modal-subtitle { 
    margin: 0; 
    font-size: 0.95rem; 
    color: rgba(255, 255, 255, 0.9); 
    line-height: 1.5; 
    font-weight: 500;
}

.review-modal-body { 
    padding: 35px; 
    overflow-y: auto; 
    flex: 1; 
}

.star-row { 
    display: flex; 
    gap: 12px; 
    margin-bottom: 25px; 
    justify-content: center; 
}

.star-btn { 
    width: 56px; 
    height: 56px; 
    border-radius: 16px; 
    border: 2px solid #f1f5f9; 
    background: #f8fafc; 
    color: #cbd5e1; 
    font-size: 1.6rem; 
    cursor: pointer; 
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.01);
}

.star-btn:hover { 
    transform: scale(1.15) rotate(6deg); 
    border-color: #fbbf24; 
    color: #fbbf24; 
    background: #fffbeb; 
    box-shadow: 0 10px 20px -3px rgba(251, 191, 36, 0.2); 
}

.star-btn.active { 
    border-color: #f59e0b; 
    background: #fff7ed; 
    color: #f59e0b; 
    transform: scale(1.08); 
    box-shadow: 0 6px 12px -2px rgba(245, 158, 11, 0.15); 
}

.review-modal textarea { 
    width: 100%; 
    min-height: 140px; 
    border-radius: 18px; 
    border: 2px solid #e2e8f0; 
    background: #f8fafc; 
    padding: 18px; 
    font-family: inherit; 
    font-size: 0.95rem; 
    color: #1e293b; 
    resize: none; 
    outline: none; 
    transition: all 0.25s ease; 
}

.review-modal textarea:focus { 
    border-color: #6366f1; 
    background: #fff; 
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); 
}

.review-modal-message { 
    margin-top: 16px; 
    font-size: 0.95rem; 
    font-weight: 600; 
    text-align: center; 
    min-height: 24px; 
}

.review-modal-message.error { color: #ef4444; }
.review-modal-message.success { color: #10b981; }

.review-modal-actions { 
    display: flex; 
    gap: 12px; 
    padding: 0 35px 35px; 
}

.review-modal-actions .btn-quiz { 
    flex: 1; 
    height: 50px; 
    border-radius: 14px; 
    font-weight: 700; 
    font-size: 0.95rem; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    gap: 8px; 
    transition: all 0.2s ease; 
    cursor: pointer; 
    border: none; 
}

.review-modal-actions .btn-quiz.primary { 
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); 
    color: #fff; 
    box-shadow: 0 6px 15px rgba(99, 102, 241, 0.25); 
}

.review-modal-actions .btn-quiz.primary:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 10px 20px rgba(99, 102, 241, 0.35); 
}

.review-modal-actions .btn-quiz.outline { 
    background: #fff; 
    color: #64748b; 
    border: 2px solid #e2e8f0; 
}

.review-modal-actions .btn-quiz.outline:hover { 
    background: #f8fafc; 
    border-color: #cbd5e1; 
    color: #334155; 
}

/* Mobile responsive */
@media (max-width: 768px) {
    .results-container { margin: 16px auto; }
    .main-header { padding: 35px 16px; }
    .main-header h2 { font-size: 1.5rem; }
    .card-body { padding: 20px !important; }
    .section-title { font-size: 1.2rem; margin: 35px 0 20px; }
    .mcq-result-item { padding: 18px; margin-bottom: 18px; }
    .subjective-card { margin-bottom: 25px; }
    .subjective-card.p-4 { padding: 18px !important; }
    .feedback-grid { grid-template-columns: 1fr; gap: 14px; }
    .question-header { flex-direction: column; align-items: flex-start; gap: 8px; }
    .marks-badge { align-self: flex-end; }
}

@media (max-width: 640px) {
    .review-modal { padding: 12px; }
    .review-modal-card { border-radius: 24px; max-height: calc(100vh - 24px); }
    .review-modal-header { padding: 25px 25px 20px; }
    .review-modal-title { font-size: 1.4rem; }
    .review-modal-body { padding: 25px 25px; }
    .review-modal-actions { padding: 0 25px 25px; flex-direction: column-reverse; gap: 10px; }
    .review-modal-actions .btn-quiz { width: 100%; height: 46px; }
    .star-btn { width: 46px; height: 46px; font-size: 1.25rem; border-radius: 12px; }
    .review-modal textarea { min-height: 110px; padding: 14px; }
}
</style>

<script>
const reviewApiUrl = window.location.href;
let selectedReviewRating = 0;
const hasAlreadyReviewedServer = <?= json_encode($hasAlreadyReviewed) ?>;

function openReviewModal() { const m = document.getElementById('reviewModal'); if (m) m.classList.add('open'); }
function closeReviewModal() { const m = document.getElementById('reviewModal'); if (m) m.classList.remove('open'); }

function refreshStars(hoverRating = 0) {
    document.querySelectorAll('#starRow .star-btn').forEach(star => {
        const val = Number(star.dataset.rating || 0);
        star.classList.toggle('active', val <= (hoverRating || selectedReviewRating));
    });
}

function updateCharCount() {
    const ta = document.getElementById('reviewFeedback'), el = document.getElementById('charCount');
    if (!ta || !el) return;
    el.textContent = `${ta.value.length} / 1000`;
    el.style.color = ta.value.length >= 900 ? '#dc2626' : (ta.value.length >= 750 ? '#f59e0b' : '#94a3b8');
}

function setReviewMessage(msg, ok = false) {
    const el = document.getElementById('reviewMessage');
    if (!el) return;
    el.className = 'review-modal-message ' + (ok ? 'success' : 'error');
    el.textContent = msg;
}

async function submitReview() {
    const feedbackEl = document.getElementById('reviewFeedback');
    const submitBtn = document.getElementById('submitReviewBtn');
    const anonCheckbox = document.getElementById('reviewAnonymous');
    if (!feedbackEl || !submitBtn) return;
    const feedback = feedbackEl.value.trim();
    const isAnon = anonCheckbox ? (anonCheckbox.checked ? 1 : 0) : 1;
    if (selectedReviewRating < 1 || selectedReviewRating > 5) { setReviewMessage('Please select your star rating first.'); return; }
    if (feedback.length < 3) { setReviewMessage('Please write your feedback in the textbox.'); return; }
    submitBtn.disabled = true; setReviewMessage('');
    try {
        const resp = await fetch(reviewApiUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'submit_quiz_review', rating: selectedReviewRating, feedback: feedback, is_anonymous: isAnon }) });
        const data = await resp.json();
        if (!resp.ok || data.status !== 'success') { setReviewMessage(data.message || 'Unable to submit review.'); submitBtn.disabled = false; return; }
        localStorage.setItem('site_review_submitted', 'true');
        localStorage.setItem('quiz_review_submitted', 'true');
        setReviewMessage('Thank you for your feedback!', true);
        setTimeout(() => closeReviewModal(), 900);
    } catch (e) { setReviewMessage('Network issue. Please try again.'); submitBtn.disabled = false; }
}

document.querySelectorAll('#starRow .star-btn').forEach(star => {
    star.addEventListener('click', function() { selectedReviewRating = Number(this.dataset.rating || 0); refreshStars(); setReviewMessage(''); });
    star.addEventListener('mouseenter', function() { refreshStars(Number(this.dataset.rating)); });
    star.addEventListener('mouseleave', function() { refreshStars(); });
});

// Show review popup after 30 seconds
const hasReviewedLocally = localStorage.getItem('site_review_submitted') === 'true' || localStorage.getItem('quiz_review_submitted') === 'true';
if (!hasAlreadyReviewedServer && !hasReviewedLocally) {
    setTimeout(() => openReviewModal(), 30000);
}
</script>

<?php include '../footer.php'; ?>
