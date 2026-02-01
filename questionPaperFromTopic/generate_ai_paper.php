<?php
$pageTitle = "Generated Paper | Intelligent Paper Builder";
$metaDescription = "View and print your AI-generated assessment paper.";
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/../quiz/mcq_generator.php'; 
require_once __DIR__ . '/../includes/APIKeyManager.php';
?>
<link rel="stylesheet" href="../css/paper-builder.css">
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
        Format: JSON Array of objects with keys: question, option_a, option_b, option_c, option_d, correct_option.
        correct_option MUST be just the character A, B, C, or D.
        Strictly JSON only.";
    } else {
        $mode = ($type === 'short') ? 'short answer questions (brief)' : 'long detailed answer questions';
        $prompt = "Generate exactly {$count} {$mode} covering topics: \"{$topicsList}\".
        Format: JSON Array of objects with keys: question, typical_answer.
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
            'max_tokens' => 4000
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
    
    // Create/Get Topic ID
    $topicStr = implode(',', $topics);
    $topicId = null;
    
    // Check if topic exists
    $stmt = $conn->prepare("SELECT id FROM AIQuestionsTopic WHERE topic_name = ?");
    $stmt->bind_param("s", $topicStr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $topicId = $row['id'];
    } else {
        // Insert new topic
        $stmt = $conn->prepare("INSERT INTO AIQuestionsTopic (topic_name) VALUES (?)");
        $stmt->bind_param("s", $topicStr);
        if ($stmt->execute()) {
            $topicId = $stmt->insert_id;
        }
    }

    foreach ($data as $q) {
        if ($type === 'mcqs') {
            $stmt = $conn->prepare("INSERT INTO AIGeneratedMCQs (topic_id, question_text, option_a, option_b, option_c, option_d, correct_option, generated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isssssss', $topicId, $q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_option'], $today);
            $stmt->execute();
            
            // Update MCQ Count
            $countStmt = $conn->prepare("INSERT INTO TopicQuestionCounts (topic_name, question_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE question_count = question_count + 1");
            $countStmt->bind_param("s", $topicStr);
            $countStmt->execute();
            
        } elseif ($type === 'short') {
            $stmt = $conn->prepare("INSERT INTO AIGeneratedShortQuestions (topic_id, question_text, typical_answer, generated_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('isss', $topicId, $q['question'], $q['typical_answer'], $today);
            $stmt->execute();
            
            // Update Short Question Count
            $countStmt = $conn->prepare("INSERT INTO TopicShortQuestionCounts (topic_name, question_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE question_count = question_count + 1");
            $countStmt->bind_param("s", $topicStr);
            $countStmt->execute();
            
        } elseif ($type === 'long') {
            $stmt = $conn->prepare("INSERT INTO AIGeneratedLongQuestions (topic_id, question_text, typical_answer, generated_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('isss', $topicId, $q['question'], $q['typical_answer'], $today);
            $stmt->execute();
            
            // Update Long Question Count
            $countStmt = $conn->prepare("INSERT INTO TopicLongQuestionCounts (topic_name, question_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE question_count = question_count + 1");
            $countStmt->bind_param("s", $topicStr);
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
        background-color: #f8fafc;
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
        display: block;
        margin-bottom: 8px;
        line-height: 1.4;
    }

    /* MCQ Grid */
    .options-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px 20px;
        padding-left: 20px;
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
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        padding: 12px 24px;
        border-radius: 50px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        display: flex;
        gap: 15px;
        z-index: 1000;
        border: 1px solid rgba(255,255,255,0.5);
    }

    .btn-float {
        border-radius: 30px;
        padding: 10px 24px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .btn-float:hover {
        transform: translateY(-2px);
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
    }
</style>

<div class="container main-content">
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
        
        <!-- Professional Paper Preview -->
        <div class="paper-preview">
            <div class="paper-header">
                <div class="institute-name">Ahmad Learning Hub</div>
                <div class="text-uppercase fw-bold mb-2">Professional Assessment Test</div>
                <div class="paper-meta">
                    <span><strong>Subject:</strong> <?= htmlspecialchars(implode(', ', array_slice($topics, 0, 3))) . (count($topics) > 3 ? '...' : '') ?></span>
                    <span><strong>Date:</strong> <?= date('d M, Y') ?></span>
                    <span><strong>Total Marks:</strong> Auto</span>
                </div>
            </div>

            <?php if (!empty($generatedContent['mcqs'])): ?>
                <div class="section-title">Section A: Multiple Choice Questions</div>
                <?php foreach ($generatedContent['mcqs'] as $i => $q): ?>
                    <div class="q-item">
                        <span class="q-text">Q.<?= $i+1 ?>: <?= htmlspecialchars($q['question']) ?></span>
                        <div class="options-grid">
                            <span class="option">(A) <?= htmlspecialchars($q['option_a']) ?></span>
                            <span class="option">(B) <?= htmlspecialchars($q['option_b']) ?></span>
                            <span class="option">(C) <?= htmlspecialchars($q['option_c']) ?></span>
                            <span class="option">(D) <?= htmlspecialchars($q['option_d']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($generatedContent['short'])): ?>
                <div class="section-title">Section B: Short Questions</div>
                <?php foreach ($generatedContent['short'] as $i => $q): ?>
                    <div class="q-item">
                        <span class="q-text">Q.<?= $i+1 ?>: <?= htmlspecialchars($q['question']) ?></span>
                        <div style="height: 60px;"></div> <!-- Space for answer -->
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($generatedContent['long'])): ?>
                <div class="section-title">Section C: Detailed Questions</div>
                <?php foreach ($generatedContent['long'] as $i => $q): ?>
                    <div class="q-item">
                        <span class="q-text">Q.<?= $i+1 ?>: <?= htmlspecialchars($q['question']) ?></span>
                        <div style="height: 120px;"></div> <!-- Space for answer -->
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="text-center mt-5 pt-4 border-top">
                <p class="fst-italic text-muted small">"Education is the most powerful weapon which you can use to change the world."</p>
            </div>
        </div>

        <!-- Floating Action Bar -->
        <div class="action-bar">
            <button onclick="window.print()" class="btn btn-dark btn-float shadow-sm">
                <i class="fas fa-print"></i> <span>Print Paper</span>
            </button>
            <a href="index.php" class="btn btn-primary btn-float shadow-sm">
                <i class="fas fa-plus-circle"></i> <span>Create New</span>
            </a>
            <!-- Optional: Save as PDF button if we had jsPDF, but Print to PDF is standard -->
        </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
