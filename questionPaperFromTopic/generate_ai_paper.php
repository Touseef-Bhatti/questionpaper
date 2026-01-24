<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/../quiz/mcq_generator.php'; 

// Get inputs from configure_paper.php or topic search
$topics = $_POST['topics'] ?? (isset($_GET['topic']) ? [$_GET['topic']] : []);
$totalMcqs = intval($_POST['total_mcqs'] ?? 10);
$totalShorts = intval($_POST['total_shorts'] ?? 5);
$totalLongs = intval($_POST['total_longs'] ?? 3);

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
        $generatedContent['mcqs'] = generateQuestionsByTopicAI('mcqs', $topics, $totalMcqs);
    }
    if ($totalShorts > 0) {
        $generatedContent['short'] = generateQuestionsByTopicAI('short', $topics, $totalShorts);
    }
    if ($totalLongs > 0) {
        $generatedContent['long'] = generateQuestionsByTopicAI('long', $topics, $totalLongs);
    }

    if (empty($generatedContent['mcqs']) && empty($generatedContent['short']) && empty($generatedContent['long'])) {
        $error = "Failed to generate your professional paper. Please try again with fewer questions or check your AI connection.";
    }
    $isProcessing = false;
}

/**
 * AI Generation Helper
 */
function generateQuestionsByTopicAI($type, $topics, $count) {
    global $conn, $cacheManager, $keyManager;
    if (!isset($keyManager)) $keyManager = new APIKeyManager();
    
    $apiKeys = $keyManager->getActiveKeys();
    $openaiModel = EnvLoader::get('OPENAI_MODEL', 'nvidia/nemotron-3-nano-30b-a3b:free');
    if (empty($apiKeys)) return [];

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

    // Retry logic across multiple keys
    for ($i = 0; $i < count($apiKeys); $i++) {
        $keyData = getAvailableApiKey($apiKeys, $cacheManager, $attemptedKeys);
        if (!$keyData) break;
        $apiKey = $keyData['key'];
        $lockKey = $keyData['lockKey'];
        $attemptedKeys[] = $apiKey;

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
        
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($lockKey && $cacheManager) $cacheManager->del($lockKey);

        if ($code == 200) {
            $json = json_decode($raw, true);
            if (isset($json['choices'][0]['message']['content'])) {
                $response = $json['choices'][0]['message']['content'];
                $success = true;
                $keyManager->logUsage($apiKey);
                break;
            }
        }
        $keyManager->logError($apiKey);
    }

    if (!$success || !$response) return [];

    // Parse JSON from response
    if (preg_match('/\[.*\]/s', $response, $matches)) {
        $jsonStr = $matches[0];
    } else {
        $jsonStr = $response;
    }
    
    $data = json_decode($jsonStr, true);
    if (!is_array($data)) return [];

    // Persist to Database for future searchability
    $today = date('Y-m-d H:i:s');
    foreach ($data as $q) {
        if ($type === 'mcqs') {
            $stmt = $conn->prepare("INSERT INTO AIGeneratedMCQs (topic, question_text, option_a, option_b, option_c, option_d, correct_option, generated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $topicStr = implode(',', $topics);
            $stmt->bind_param('ssssssss', $topicStr, $q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_option'], $today);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO questions (topic, question_type, question_text, marks, typical_answer) VALUES (?, ?, ?, ?, ?)");
            $topicStr = implode(',', $topics);
            $marks = ($type === 'short') ? 2 : 5;
            $stmt->bind_param('sssis', $topicStr, $type, $q['question'], $marks, $q['typical_answer']);
            $stmt->execute();
        }
    }
    
    return $data;
}
?>

<style>
    .paper-preview {
        background: #fff;
        padding: 60px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        max-width: 900px;
        margin: 40px auto;
        font-family: 'Times New Roman', serif;
        position: relative;
    }
    
    .paper-header {
        text-align: center;
        border-bottom: 2px solid #334155;
        padding-bottom: 20px;
        margin-bottom: 30px;
    }

    .paper-header h1 {
        font-size: 24px;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .paper-header p {
        font-size: 16px;
        color: #64748b;
        margin: 0;
    }

    .section-title {
        font-size: 18px;
        font-weight: 700;
        margin-top: 30px;
        margin-bottom: 15px;
        border-left: 4px solid #6366f1;
        padding-left: 12px;
        text-transform: uppercase;
    }

    .q-item {
        margin-bottom: 20px;
        line-height: 1.6;
    }

    .q-text {
        font-weight: 600;
        font-size: 16px;
        display: block;
        margin-bottom: 8px;
    }

    .options-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 15px;
    }

    .option {
        font-size: 15px;
    }

    .footer-actions {
        position: fixed;
        bottom: 20px;
        right: 20px;
        display: flex;
        gap: 10px;
        z-index: 1000;
    }

    .btn-action {
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    @media print {
        .footer-actions, .navbar { display: none !important; }
        .paper-preview { margin: 0; padding: 20px; box-shadow: none; max-width: 100%; }
        body { background: white; }
    }
</style>

<div class="container main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-4 p-4 mt-5">
            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
            <div class="mt-3">
                <a href="index.php" class="btn btn-outline-danger">Try Different Topics</a>
            </div>
        </div>
    <?php elseif (empty($generatedContent['mcqs']) && empty($generatedContent['short']) && empty($generatedContent['long'])): ?>
        <!-- Full Screen Processing State -->
        <div class="text-center py-5 mt-5">
            <div class="spinner-grow text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
            <h2 class="mt-4 fw-bold">AI is Crafting Your Paper...</h2>
            <p class="text-muted">This takes around 20-30 seconds to ensure high-quality content.</p>
            <div class="progress mx-auto mt-4" style="max-width: 400px; height: 8px; border-radius: 10px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-indigo" style="width: 100%"></div>
            </div>
            
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
                setTimeout(() => document.getElementById('autoSubmitForm').submit(), 1000);
            </script>
        </div>
    <?php else: ?>
        <!-- Generated Paper Preview -->
        <div class="paper-preview">
            <div class="paper-header">
                <h1>Ahmad Learning Hub</h1>
                <p>AI Generated Professional Assessment Paper</p>
                <div class="d-flex justify-content-between mt-3 small text-muted">
                    <span>Topics: <?= htmlspecialchars(implode(', ', $topics)) ?></span>
                    <span>Date: <?= date('d M, Y') ?></span>
                </div>
            </div>

            <?php if (!empty($generatedContent['mcqs'])): ?>
                <div class="section-title">Section A: Multiple Choice Questions</div>
                <div class="row">
                <?php foreach ($generatedContent['mcqs'] as $i => $q): ?>
                    <div class="col-md-12 q-item">
                        <span class="q-text">Q<?= $i+1 ?>. <?= htmlspecialchars($q['question']) ?></span>
                        <div class="options-grid">
                            <span class="option">A) <?= htmlspecialchars($q['option_a']) ?></span>
                            <span class="option">B) <?= htmlspecialchars($q['option_b']) ?></span>
                            <span class="option">C) <?= htmlspecialchars($q['option_c']) ?></span>
                            <span class="option">D) <?= htmlspecialchars($q['option_d']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($generatedContent['short'])): ?>
                <div class="section-title">Section B: Short Answer Questions</div>
                <?php foreach ($generatedContent['short'] as $i => $q): ?>
                    <div class="q-item">
                        <span class="q-text">Q<?= $i+1 ?>. <?= htmlspecialchars($q['question']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($generatedContent['long'])): ?>
                <div class="section-title">Section C: Long Answer Questions</div>
                <?php foreach ($generatedContent['long'] as $i => $q): ?>
                    <div class="q-item">
                        <span class="q-text">Q<?= $i+1 ?>. <?= htmlspecialchars($q['question']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="mt-5 text-center text-muted small" style="border-top: 1px dashed #ccc; padding-top: 20px;">
                --- End of Question Paper ---
            </div>
        </div>

        <div class="footer-actions">
            <button onclick="window.print()" class="btn btn-dark btn-action">
                <i class="fas fa-print"></i> Print Paper
            </button>
            <a href="index.php" class="btn btn-primary btn-action">
                <i class="fas fa-redo"></i> Create New
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
