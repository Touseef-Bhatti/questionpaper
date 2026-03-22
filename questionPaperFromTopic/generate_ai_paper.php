<?php
session_start();
$pageTitle = "Generated Paper | Intelligent Paper Builder";
$metaDescription = "View and print your AI-generated assessment paper.";
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/../quiz/mcq_generator.php';
require_once __DIR__ . '/../includes/APIKeyManager.php';
?>
<link rel="stylesheet" href="<?= $assetBase ?>css/paper-builder.css?v=<?= time() . rand(6000, 7000) ?>">
<?php

// Get inputs from configure_paper.php or topic search
$topics = $_POST['topics'] ?? (isset($_GET['topic']) ? [$_GET['topic']] : []);
$topicsMcqs = $_POST['topics_mcqs'] ?? [];
$topicsShort = $_POST['topics_short'] ?? [];
$topicsLong = $_POST['topics_long'] ?? [];

$totalMcqs = intval($_POST['total_mcqs'] ?? 0);
$totalShorts = intval($_POST['total_shorts'] ?? 0);
$totalLongs = intval($_POST['total_longs'] ?? 0);

$generatedContent = [
    'mcqs' => [],
    'short' => [],
    'long' => []
];
$isProcessing = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($topics)) {
    $isProcessing = true;
    
    // Generate each type if count > 0
    if ($totalMcqs > 0) {
        $useTopics = !empty($topicsMcqs) ? $topicsMcqs : $topics;
        $generatedContent['mcqs'] = generateQuestionsByTopicAI('mcqs', $useTopics, $totalMcqs);
    }
    if ($totalShorts > 0) {
        $useTopics = !empty($topicsShort) ? $topicsShort : $topics;
        $generatedContent['short'] = generateQuestionsByTopicAI('short', $useTopics, $totalShorts);
    }
    if ($totalLongs > 0) {
        $useTopics = !empty($topicsLong) ? $topicsLong : $topics;
        $generatedContent['long'] = generateQuestionsByTopicAI('long', $useTopics, $totalLongs);
    }

    if (empty($generatedContent['mcqs']) && empty($generatedContent['short']) && empty($generatedContent['long'])) {
        $error = "Failed to generate your professional paper. Please try again with fewer questions or check your AI connection.";
    }
    $isProcessing = false;
}


/**
 * Helper to get an available API key with locking
 */
function getAvailableApiKey($apiKeys, $cacheManager, $attemptedKeys, $lockDuration) {
    foreach ($apiKeys as $keyItem) {
        $apiKey = is_array($keyItem) ? ($keyItem['key'] ?? '') : $keyItem;
        if (empty($apiKey)) continue;

        if (in_array($apiKey, $attemptedKeys)) continue;
        
        $lockKey = null;
        if ($cacheManager) {
            $lockKey = 'ai_key_lock_' . md5($apiKey);
            try {
                // Check if locked
                if ($cacheManager->get($lockKey)) continue;
                // Acquire lock
                $cacheManager->setex($lockKey, $lockDuration, '1');
            } catch (Exception $e) {
                // If cache fails, just proceed without locking
                $lockKey = null;
            }
        }
        
        return ['key' => $apiKey, 'lockKey' => $lockKey];
    }
    return null;
}

/**
 * AI Generation Helper
 */
function generateQuestionsByTopicAI($type, $topics, $count) {
    global $conn, $cacheManager, $keyManager;
    
    // Check DB for existing questions first
    $existingQuestions = [];

    // Check manual MCQs table if type is mcqs
    if ($type === 'mcqs' && !empty($topics)) {
        $likes = [];
        $params = [];
        $typesStr = "";
        foreach ($topics as $t) {
            $likes[] = "topic LIKE ?";
            $params[] = "%" . trim($t) . "%";
            $typesStr .= "s";
        }
        
        if (!empty($likes)) {
            $sql = "SELECT question, option_a, option_b, option_c, option_d, correct_option FROM mcqs WHERE " . implode(" OR ", $likes) . " ORDER BY RAND() LIMIT " . ($count * 2);
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($typesStr, ...$params);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $existingQuestions[md5($row['question'])] = $row;
                }
                $stmt->close();
            }
        }
    }

    $tableMap = [
        'mcqs' => ['table' => 'AIGeneratedMCQs', 'cols' => 'question_text as question, option_a, option_b, option_c, option_d, correct_option'],
        'short' => ['table' => 'AIGeneratedShortQuestions', 'cols' => 'question_text as question, typical_answer'],
        'long' => ['table' => 'AIGeneratedLongQuestions', 'cols' => 'question_text as question, typical_answer']
    ];

    if (isset($tableMap[$type]) && !empty($topics)) {
        $tb = $tableMap[$type];
        $tIds = [];
        $likes = [];
        $params = [];
        $typesStr = "";
        foreach ($topics as $t) {
            $likes[] = "topic_name LIKE ?";
            $params[] = "%" . trim($t) . "%";
            $typesStr .= "s";
        }
        
        if (!empty($likes)) {
            $sql = "SELECT id FROM AIQuestionsTopic WHERE " . implode(" OR ", $likes);
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($typesStr, ...$params);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) $tIds[] = $r['id'];
                $stmt->close();
            }
        }

        if (!empty($tIds)) {
            $idStr = implode(',', array_map('intval', $tIds));
            $qSql = "SELECT {$tb['cols']} FROM {$tb['table']} WHERE topic_id IN ($idStr) ORDER BY RAND() LIMIT " . ($count * 2);
            $qRes = $conn->query($qSql);
            if ($qRes) {
                while ($row = $qRes->fetch_assoc()) {
                    $existingQuestions[md5($row['question'])] = $row;
                }
            }
        }
    }
    
    $existingQuestions = array_values($existingQuestions);
    if (count($existingQuestions) >= $count) {
        shuffle($existingQuestions);
        return array_slice($existingQuestions, 0, $count);
    }
    
    // Only generate what's needed
    $preservedQuestions = $existingQuestions;
    $count = $count - count($existingQuestions);
    
    // Ensure table schema is up to date (Fix for missing typical_answer column)
    static $schemaChecked = false;
    if (!$schemaChecked && $conn) {
        $check = $conn->query("SHOW COLUMNS FROM questions LIKE 'typical_answer'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE questions ADD COLUMN typical_answer TEXT DEFAULT NULL");
        }
        $schemaChecked = true;
    }

    if (!isset($keyManager)) $keyManager = new APIKeyManager();
    
    $apiKeys = $keyManager->getActiveKeys();
    $openaiModel = EnvLoader::get('OPENAI_MODEL', 'liquid/lfm-2.5-1.2b-thinking:free');
    if (empty($apiKeys)) return $preservedQuestions;

    $topicsList = implode(', ', $topics);
    
    if ($type === 'mcqs') {
        $prompt = "Generate exactly {$count} multiple choice questions covering topics: \"{$topicsList}\".
        Format: JSON Array of objects with keys: topic, question, option_a, option_b, option_c, option_d, correct_option.
        \"topic\" MUST be one of the searched topics: \"{$topicsList}\".
        correct_option MUST be just the character A, B, C, or D.
        Strictly JSON only.";
    } else {
        $mode = ($type === 'short') ? 'short answer questions (brief)' : 'long detailed answer questions';
        $prompt = "Generate exactly {$count} {$mode} covering topics: \"{$topicsList}\".
        Format: JSON Array of objects with keys: topic, question, typical_answer.
        \"topic\" MUST be one of the searched topics: \"{$topicsList}\".
        Strictly JSON only.";
    }

    $attemptedKeys = [];
    $response = null;
    $success = false;

    // Retry logic across multiple keys - hold locks until processing complete
    $acquiredLocks = [];
    $curlTimeout = 120;
    $lockDuration = $curlTimeout + 30;

    for ($i = 0; $i < count($apiKeys); $i++) {
            $keyData = getAvailableApiKey($apiKeys, $cacheManager, $attemptedKeys, $lockDuration);
        if (!$keyData) break;
        $apiKey = $keyData['key'];
        $lockKey = $keyData['lockKey'];
        $attemptedKeys[] = $apiKey;

        if ($lockKey && $cacheManager) $acquiredLocks[$apiKey] = $lockKey;

        $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $openaiModel,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.7,
            'max_tokens' => 16000
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: https://paper.bhattichemicalsindustry.com.pk',
            'X-Title: Ahmad Learning Hub AI Generation'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $curlTimeout);
        
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Do not release lock immediately; release after we parse/store the response

        if ($code == 200) {
            $json = json_decode($raw, true);
            if (isset($json['choices'][0]['message']['content'])) {
                $response = $json['choices'][0]['message']['content'];
                $success = true;
                $keyManager->logUsage($apiKey);
                $successfulKey = $apiKey;
                break;
            }
        }
        $keyManager->logError($apiKey);
        // release lock on failure so others can try
        if ($lockKey && $cacheManager) {
            $cacheManager->del($lockKey);
            unset($acquiredLocks[$apiKey]);
        }
    }

    if (!$success) {
        // release any remaining locks
        if ($cacheManager && !empty($acquiredLocks)) {
            foreach ($acquiredLocks as $lk) {
                try { $cacheManager->del($lk); } catch (Exception $e) {}
            }
        }
    }

    if (!$success || !$response) return $preservedQuestions;

    // Parse JSON from response
    if (preg_match('/\[.*\]/s', $response, $matches)) {
        $jsonStr = $matches[0];
    } else {
        $jsonStr = $response;
    }
    
    $data = json_decode($jsonStr, true);
    if (!is_array($data)) return $preservedQuestions;

    // Persist to Database for future searchability
    $today = date('Y-m-d H:i:s');
    $firstTopicInList = !empty($topics) ? trim($topics[0]) : 'General';
    $topicIdCache = [];

    foreach ($data as $q) {
        $topicVal = isset($q['topic']) ? (string) trim($q['topic']) : $firstTopicInList;
        
        // Resolve topic_id (using helper from mcq_generator.php)
        if (!isset($topicIdCache[$topicVal])) {
            $topicIdCache[$topicVal] = getOrCreateTopicId($conn, $topicVal);
        }
        $topicId = $topicIdCache[$topicVal];

        if ($type === 'mcqs') {
            $stmt = $conn->prepare("INSERT INTO AIGeneratedMCQs (topic_id, topic, question_text, option_a, option_b, option_c, option_d, correct_option, generated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssssss', $topicId, $topicVal, $q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_option'], $today);
            $stmt->execute();

            // Update MCQ Count
            $countStmt = $conn->prepare("INSERT INTO TopicQuestionCounts (topic_name, question_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE question_count = question_count + 1");
            $countStmt->bind_param("s", $topicVal);
            $countStmt->execute();
            
        } elseif ($type === 'short') {
            $stmt = $conn->prepare("INSERT INTO AIGeneratedShortQuestions (topic_id, question_text, typical_answer, generated_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('isss', $topicId, $q['question'], $q['typical_answer'], $today);
            $stmt->execute();
            
            // Update Short Question Count
            $countStmt = $conn->prepare("INSERT INTO TopicShortQuestionCounts (topic_name, question_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE question_count = question_count + 1");
            $countStmt->bind_param("s", $topicVal);
            $countStmt->execute();
            
        } elseif ($type === 'long') {
            $stmt = $conn->prepare("INSERT INTO AIGeneratedLongQuestions (topic_id, question_text, typical_answer, generated_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('isss', $topicId, $q['question'], $q['typical_answer'], $today);
            $stmt->execute();
            
            // Update Long Question Count
            $countStmt = $conn->prepare("INSERT INTO TopicLongQuestionCounts (topic_name, question_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE question_count = question_count + 1");
            $countStmt->bind_param("s", $topicVal);
            $countStmt->execute();
        }
    }
    
    return array_merge($preservedQuestions, $data);
}
?>

<style>
    :root {
        --paper-bg: #ffffff;
        --paper-text: #1e293b;
        --accent-color: #4f46e5;
        --border-color: #e2e8f0;
    }

    body {
        margin: 0 !important;
        padding: 0 !important;
        background-color: #f8fafc;
        width: 100%;
        overflow-x: hidden;
    }

    .navbar {
        width: 100% !important;
        margin: 0 !important;
        left: 0 !important;
        right: 0 !important;
        position: sticky !important;
        top: 0 !important;
        z-index: 2000 !important;
    }

    .footer {
        width: 100% !important;
        margin: 0 !important;
        box-sizing: border-box;
    }

    .main-content {
        padding-bottom: 100px;
    }

    /* Paper Container - A4 mimic */
    .paper-preview {
        background: var(--paper-bg);
        width: 100%;
        max-width: 210mm; /* A4 width */
        min-height: 297mm; /* A4 height */
        padding: 20mm;
        margin: 40px auto;
        border-radius: 2px;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.08);
        position: relative;
        font-family: 'Times New Roman', Times, serif; /* Classic paper font */
        color: #000;
    }

    /* Paper Header */
    .paper-header {
        text-align: center;
        border-bottom: 3px double #000;
        padding-bottom: 20px;
        margin-bottom: 30px;
    }

    .institute-name {
        font-size: 28px;
        font-weight: 900;
        text-transform: uppercase;
        margin-bottom: 5px;
        letter-spacing: 1px;
    }

    .paper-meta {
        display: flex;
        justify-content: space-between;
        margin-top: 15px;
        font-family: 'Arial', sans-serif;
        font-size: 14px;
        border-top: 1px solid #000;
        padding-top: 8px;
    }

    /* Sections */
    .section-title {
        font-family: 'Arial', sans-serif;
        font-size: 16px;
        font-weight: 800;
        text-transform: uppercase;
        margin-top: 25px;
        margin-bottom: 15px;
        background: #f1f5f9;
        padding: 8px 12px;
        border-left: 4px solid #000;
        -webkit-print-color-adjust: exact;
    }

    /* Questions */
    .q-item {
        margin-bottom: 15px;
        page-break-inside: avoid;
    }

    .q-text {
        font-size: 16px;
        font-weight: bold;
        line-height: 1.4;
    }

    /* MCQ Table Grid */
    .options-table {
        width: 100%;
        margin-left: 20px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .options-table td {
        width: 50%;
        padding: 4px 5px;
        font-size: 15px;
        vertical-align: top;
    }

    .option {
        font-size: 15px;
    }

    /* Loading State */
    .processing-card {
        background: white;
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.05);
        padding: 60px 40px;
        text-align: center;
        max-width: 500px;
        margin: 80px auto;
        border: 1px solid rgba(0,0,0,0.02);
    }

    /* Action Bar */
    .action-bar {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(15px);
        padding: 10px 20px;
        border-radius: 50px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.18);
        display: flex;
        gap: 12px;
        z-index: 1000;
        border: 1px solid rgba(255,255,255,0.8);
        transition: all 0.3s ease;
        width: auto;
        max-width: 95vw;
    }

    .btn-float {
        border-radius: 30px;
        padding: 12px 24px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        white-space: nowrap;
    }

    .btn-float:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }

    @media (max-width: 768px) {
        .action-bar {
            bottom: 20px;
            padding: 8px 12px;
            gap: 8px;
        }
        .btn-float {
            padding: 12px;
            min-width: 48px;
            height: 48px;
        }
        .btn-float span {
            display: none;
        }
        .btn-float i {
            margin: 0;
            font-size: 1.2rem;
        }
    }

    [contenteditable="true"]:hover {
        background-color: rgba(255, 255, 0, 0.1);
        cursor: text;
        outline: 1px dashed #ccc;
    }
    
    [contenteditable="true"]:focus {
        background-color: #fff;
        outline: 2px solid var(--accent-color);
        padding: 2px;
        border-radius: 2px;
    }

    /* Design Selector Styles */
    .design-selector {
        position: fixed;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        background: white;
        padding: 15px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        z-index: 1300;
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: 150px;
        font-family: 'Inter', sans-serif;
    }
    .design-selector h4 {
        font-size: 14px;
        margin: 0 0 10px 0;
        text-align: center;
        color: #333;
    }
    .design-option {
        cursor: pointer;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 8px;
        text-align: center;
        font-size: 11px;
        font-weight: 600;
        transition: all 0.2s;
        background: #f9fafb;
        color: #666;
    }
    .design-option:hover {
        border-color: #4f46e5;
        background: #f3f4f6;
    }
    .design-option.active {
        border-color: #4f46e5;
        background: #eef2ff;
        color: #4f46e5;
    }

    .marks-badge {
        font-family: 'Arial', sans-serif;
        font-size: 13px;
        font-weight: bold;
        border: 1px solid #000;
        padding: 2px 6px;
        border-radius: 4px;
        min-width: 40px;
        text-align: center;
        background: #fff;
        cursor: pointer;
        display: inline-block;
    }

    .marks-badge::before {
        content: '[';
    }
    .marks-badge::after {
        content: ']';
    }

    .marks-val {
        outline: none;
    }

    /* Print Optimizations */
    @media print {
        @page { size: A4; margin: 0; }
        body { background: white; -webkit-print-color-adjust: exact; }
        .main-content { padding: 0; margin: 0; width: 100%; max-width: none; }
        .paper-preview { 
            box-shadow: none; 
            margin: 0; 
            padding: 20mm; 
            width: 100%;
            max-width: none;
            min-height: auto;
        }
        .action-bar, .navbar, footer, .alert { display: none !important; }
        .section-title { background-color: #eee !important; }
        .marks-badge { border: none; padding: 0; }
    }
</style>



<div class="container main-content paper-builder-main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-4 p-4 mt-5 d-flex align-items-center gap-3">
            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
            <div>
                <h5 class="mb-1 fw-bold">Generation Failed</h5>
                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
            </div>
            <a href="index.php" class="btn btn-danger ms-auto rounded-pill px-4">Retry</a>
        </div>

    <?php elseif (empty($generatedContent['mcqs']) && empty($generatedContent['short']) && empty($generatedContent['long'])): ?>
        
        <!-- Modern Processing State -->
        <div class="processing-card">
            <div class="spinner-border text-primary mb-4" style="width: 4rem; height: 4rem;" role="status"></div>
            <h3 class="fw-bold text-dark mb-3">Crafting Your Paper</h3>
            <p class="text-muted mb-4">Our AI is generating professional quality questions based on your topics.</p>
            
            <div class="progress" style="height: 6px; border-radius: 10px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%"></div>
            </div>
            <div class="mt-3 text-muted small">Estimated time: 15-30 seconds</div>
            
            <!-- Hidden auto-submit form -->
            <form id="autoSubmitForm" method="POST">
                <?php foreach($topics as $t): ?>
                    <input type="hidden" name="topics[]" value="<?= htmlspecialchars($t) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="total_mcqs" value="<?= $totalMcqs ?>">
                <input type="hidden" name="total_shorts" value="<?= $totalShorts ?>">
                <input type="hidden" name="total_longs" value="<?= $totalLongs ?>">
            </form>
            <script>
                setTimeout(() => document.getElementById('autoSubmitForm').submit(), 2000);
            </script>
        </div>

    <?php else: ?>
        <?php $selectedDesign = intval($_POST['header_design'] ?? 1); ?>
        <!-- Design Selector UI -->
        <div class="design-selector">
            <h4>Header Design</h4>
            <div class="design-option <?= $selectedDesign == 1 ? 'active' : '' ?>" onclick="changeHeader(1, this)">Design 1 (Formal)</div>
            <div class="design-option <?= $selectedDesign == 2 ? 'active' : '' ?>" onclick="changeHeader(2, this)">Design 2 (Modern)</div>
            <div class="design-option <?= $selectedDesign == 3 ? 'active' : '' ?>" onclick="changeHeader(3, this)">Design 3 (Board)</div>
            <div class="design-option <?= $selectedDesign == 4 ? 'active' : '' ?>" onclick="changeHeader(4, this)">Design 4 (Elegant)</div>
            <div class="design-option <?= $selectedDesign == 5 ? 'active' : '' ?>" onclick="changeHeader(5, this)">Design 5 (Boxed)</div>
            <div class="design-option <?= $selectedDesign == 6 ? 'active' : '' ?>" onclick="changeHeader(6, this)">Design 6 (AI Style)</div>
        </div>

        <?php
        // Prepare variables for headers
        $instituteName = "Ahmad Learning Hub";
        $bookName = htmlspecialchars(implode(', ', array_slice($topics, 0, 3))) . (count($topics) > 3 ? '...' : '');
        $chapterHeaderLabel = $bookName;
        $totalMarks = 0;
        $classNameHeader = "Custom Class";
        ?>

        <!-- Professional Paper Preview -->
        <div class="paper-preview">
            <div class="header" id="dynamic-header">
                <div id="header-container-1" style="<?= $selectedDesign == 1 ? '' : 'display:none;' ?>"><?php include '../questionPapersHeaders/header1.php'; ?></div>
                <div id="header-container-2" style="<?= $selectedDesign == 2 ? '' : 'display:none;' ?>"><?php include '../questionPapersHeaders/header2.php'; ?></div>
                <div id="header-container-3" style="<?= $selectedDesign == 3 ? '' : 'display:none;' ?>"><?php include '../questionPapersHeaders/header3.php'; ?></div>
                <div id="header-container-4" style="<?= $selectedDesign == 4 ? '' : 'display:none;' ?>"><?php include '../questionPapersHeaders/header4.php'; ?></div>
                <div id="header-container-5" style="<?= $selectedDesign == 5 ? '' : 'display:none;' ?>"><?php include '../questionPapersHeaders/header5.php'; ?></div>
                <div id="header-container-6" style="<?= $selectedDesign == 6 ? '' : 'display:none;' ?>"><?php include '../questionPapersHeaders/header6.php'; ?></div>
            </div>

            <script>
            function changeHeader(designId, element) {
                document.querySelectorAll('.design-option').forEach(opt => opt.classList.remove('active'));
                element.classList.add('active');
                for (let i = 1; i <= 6; i++) {
                    const el = document.getElementById('header-container-' + i);
                    if (el) el.style.display = 'none';
                }
                const target = document.getElementById('header-container-' + designId);
                if (target) target.style.display = 'block';
            }
            </script>



            <?php if (!empty($generatedContent['mcqs'])): ?>
                <div class="section-title" contenteditable="true">Section A: Multiple Choice Questions</div>
                <?php foreach ($generatedContent['mcqs'] as $i => $q): ?>
                    <div class="q-item">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                            <tr>
                                <td style="vertical-align: top;">
                                    <span class="q-text" contenteditable="true">Q.<?= $i+1 ?>: <?= htmlspecialchars($q['question']) ?></span>
                                </td>
                                <td style="width: 60px; vertical-align: top; text-align: right;">
                                    <div class="marks-badge"><span class="marks-val" contenteditable="true" oninput="calculateTotalMarks()">1</span></div>
                                </td>
                            </tr>
                        </table>
                        <table class="options-table">
                            <tr>
                                <td class="option" contenteditable="true">(A) <?= htmlspecialchars($q['option_a']) ?></td>
                                <td class="option" contenteditable="true">(B) <?= htmlspecialchars($q['option_b']) ?></td>
                            </tr>
                            <tr>
                                <td class="option" contenteditable="true">(C) <?= htmlspecialchars($q['option_c']) ?></td>
                                <td class="option" contenteditable="true">(D) <?= htmlspecialchars($q['option_d']) ?></td>
                            </tr>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($generatedContent['short'])): ?>
                <div class="section-title" contenteditable="true">Section B: Short Questions</div>
                <?php foreach ($generatedContent['short'] as $i => $q): ?>
                    <div class="q-item">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                            <tr>
                                <td style="vertical-align: top;">
                                    <span class="q-text" contenteditable="true">Q.<?= $i+1 ?>: <?= htmlspecialchars($q['question']) ?></span>
                                </td>
                                <td style="width: 60px; vertical-align: top; text-align: right;">
                                    <div class="marks-badge"><span class="marks-val" contenteditable="true" oninput="calculateTotalMarks()">2</span></div>
                                </td>
                            </tr>
                        </table>
                        <div style="height: 60px;"></div> <!-- Space for answer -->
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($generatedContent['long'])): ?>
                <div class="section-title" contenteditable="true">Section C: Detailed Questions</div>
                <?php foreach ($generatedContent['long'] as $i => $q): ?>
                    <div class="q-item">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                            <tr>
                                <td style="vertical-align: top;">
                                    <span class="q-text" contenteditable="true">Q.<?= $i+1 ?>: <?= htmlspecialchars($q['question']) ?></span>
                                </td>
                                <td style="width: 60px; vertical-align: top; text-align: right;">
                                    <div class="marks-badge"><span class="marks-val" contenteditable="true" oninput="calculateTotalMarks()">5</span></div>
                                </td>
                            </tr>
                        </table>
                        <div style="height: 120px;"></div> <!-- Space for answer -->
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="text-center mt-5 pt-4 border-top">
                <?php
                $quotes = [
                    "Education is the most powerful weapon which you can use to change the world.",
                    "The beautiful thing about learning is that no one can take it away from you.",
                    "Education is not the filling of a pail, but the lighting of a fire.",
                    "Live as if you were to die tomorrow. Learn as if you were to live forever.",
                    "The roots of education are bitter, but the fruit is sweet.",
                    "Education is the passport to the future, for tomorrow belongs to those who prepare for it today.",
                    "Investment in knowledge pays the best interest.",
                    "Education is what remains after one has forgotten what one has learned in school.",
                    "The more that you read, the more things you will know. The more that you learn, the more places you'll go.",
                    "Teachers can open the door, but you must enter it yourself.",
                    "Develop a passion for learning. If you do, you will never cease to grow."
                ];
                $randomQuote = $quotes[array_rand($quotes)];
                ?>
                <p class="fst-italic text-muted small">"<?= htmlspecialchars($randomQuote) ?>"</p>
            </div>
        </div>

        <!-- Hidden Answer Key Container -->
        <div id="answer-key-content" style="display:none;">
            <div class="paper-header">
                <div class="institute-name">Ahmad Learning Hub</div>
                <div class="text-uppercase fw-bold mb-2">ANSWER KEY</div>
                <div class="paper-meta">
                    <span><strong>Subject:</strong> <?= htmlspecialchars(implode(', ', array_slice($topics, 0, 3))) ?></span>
                    <span><strong>Date:</strong> <?= date('d M, Y') ?></span>
                </div>
            </div>

            <?php if (!empty($generatedContent['mcqs'])): ?>
                <div class="section-title">Section A: MCQs Key</div>
                <table style="width: 100%; border-collapse: collapse;">
                <?php foreach ($generatedContent['mcqs'] as $i => $q): ?>
                    <tr>
                        <td style="padding: 5px; border-bottom: 1px solid #eee;"><strong>Q.<?= $i+1 ?></strong></td>
                        <td style="padding: 5px; border-bottom: 1px solid #eee;">Correct Option: <strong><?= htmlspecialchars($q['correct_option']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <?php if (!empty($generatedContent['short'])): ?>
                <div class="section-title">Section B: Short Answers</div>
                <?php foreach ($generatedContent['short'] as $i => $q): ?>
                    <div class="q-item">
                        <div class="q-text"><strong>Q.<?= $i+1 ?>:</strong> <?= htmlspecialchars($q['question']) ?></div>
                        <div style="margin-top: 5px; padding: 10px; background: #f9f9f9; border-left: 3px solid #000;">
                            <strong>Answer:</strong> <?= nl2br(htmlspecialchars($q['typical_answer'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($generatedContent['long'])): ?>
                <div class="section-title">Section C: Detailed Answers</div>
                <?php foreach ($generatedContent['long'] as $i => $q): ?>
                    <div class="q-item">
                        <div class="q-text"><strong>Q.<?= $i+1 ?>:</strong> <?= htmlspecialchars($q['question']) ?></div>
                        <div style="margin-top: 5px; padding: 10px; background: #f9f9f9; border-left: 3px solid #000;">
                            <strong>Answer Outline:</strong> <?= nl2br(htmlspecialchars($q['typical_answer'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Floating Action Bar -->
        <div class="action-bar">
            <button onclick="downloadDocx('paper')" class="btn btn-success btn-float shadow-sm">
                <i class="fas fa-download"></i> <span>Download Paper</span>
            </button>
            <button onclick="downloadDocx('key')" class="btn btn-info btn-float shadow-sm text-white">
                <i class="fas fa-key"></i> <span>Download Key</span>
            </button>
            <button onclick="window.print()" class="btn btn-dark btn-float shadow-sm">
                <i class="fas fa-print"></i> <span>Print</span>
            </button>
            <a href="index.php" class="btn btn-primary btn-float shadow-sm">
                <i class="fas fa-plus-circle"></i> <span>New</span>
            </a>
        </div>

        <form id="downloadForm" action="download_docx.php" method="POST" target="_blank" style="display:none;">
            <input type="hidden" name="content" id="downloadContent">
            <input type="hidden" name="filename" id="downloadFilename">
        </form>

        <script>
        function downloadDocx(type) {
            const form = document.getElementById('downloadForm');
            const contentInput = document.getElementById('downloadContent');
            const filenameInput = document.getElementById('downloadFilename');
            
            if (type === 'paper') {
                // Clone the paper preview to manipulate it for export without changing view
                const paper = document.querySelector('.paper-preview').cloneNode(true);
                
                // Remove contenteditable attributes for the static file
                const editables = paper.querySelectorAll('[contenteditable]');
                editables.forEach(el => el.removeAttribute('contenteditable'));
                
                // Remove hidden headers and other invisible elements
                paper.querySelectorAll('[style*="display:none"], [style*="display: none"]').forEach(el => el.remove());
                
                contentInput.value = paper.innerHTML;
                filenameInput.value = 'Assessment_Paper_' + new Date().toISOString().slice(0,10);
            } else if (type === 'key') {
                const keyContent = document.getElementById('answer-key-content').innerHTML;
                contentInput.value = keyContent;
                filenameInput.value = 'Answer_Key_' + new Date().toISOString().slice(0,10);
            }
            
            form.submit();
        }

        function calculateTotalMarks() {
            const displays = document.querySelectorAll('.marks-val');
            let total = 0;
            displays.forEach(d => {
                const val = parseInt(d.innerText) || 0;
                total += val;
            });
            document.getElementById('total-marks-display').innerText = total;
        }

        // Initialize total marks on load
        window.addEventListener('load', calculateTotalMarks);
        </script>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
