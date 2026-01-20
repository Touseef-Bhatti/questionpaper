<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../header.php';

// Include memory cache logic or simplified version
// We will try to rely on common logic if possible, but for now let's reproduce the API Key logic 
// or simpler: just use one key for this feature to minimize complexity or copy the rotation logic.
// Efficient way: include the generator file to get getAvailableApiKey if it doesn't cause side effects.
// quiz/mcq_generator.php only defines functions, shouldn't output anything.
require_once __DIR__ . '/../quiz/mcq_generator.php'; 

$topic = $_GET['topic'] ?? '';
$count = $_POST['count'] ?? 10;
$generated = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $topic) {
    // Generate Logic
    $generated = generateAndSaveQuestions($topic, $count);
    if (empty($generated)) {
        $error = "Failed to generate questions. Please try again or check API keys.";
    }
}

function generateAndSaveQuestions($topic, $count) {
    global $conn, $cacheManager;
    
    // Using OpenRouter/OpenAI logic similar to mcq_generator.php
    $openaiApiKeys = EnvLoader::get('OPENAI_API_KEYS', EnvLoader::get('OPENAI_API_KEY', ''));
    $apiKeys = !empty($openaiApiKeys) ? array_map('trim', explode(',', $openaiApiKeys)) : [];
    $openaiModel = EnvLoader::get('OPENAI_MODEL', 'nvidia/nemotron-3-nano-30b-a3b:free');

    if (empty($apiKeys)) return [];

    $prompt = "Generate exactly {$count} multiple choice questions on the topic: \"{$topic}\".
    Format: JSON Array of objects with keys: question, option_a, option_b, option_c, option_d, correct_option.
    correct_option should be the text of the correct answer.
    Level: School/High School.
    Language: English.
    Strictly JSON only.";

    $attemptedKeys = [];
    $response = null;
    $success = false;

    // Retry logic
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
            'temperature' => 0.7
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: https://paper.bhattichemicalsindustry.com.pk',
            'X-Title: Ahmad Learning Hub'
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
                break;
            }
        }
    }

    if (!$success || !$response) return [];

    // Parse JSON
    if (preg_match('/\[.*\]/s', $response, $matches)) {
        $jsonStr = $matches[0];
    } else {
        $jsonStr = $response;
    }
    
    $questions = json_decode($jsonStr, true);
    if (!is_array($questions)) return [];

    $inserted = [];
    $today = date('Y-m-d H:i:s');
    
    // Insert into AIGeneratedQuestion
    $stmt = $conn->prepare("INSERT INTO AIGeneratedQuestion (topic_id, question_text, option_a, option_b, option_c, option_d, correct_option, generated_at) VALUES (0, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($questions as $q) {
        if (!isset($q['question']) || !isset($q['correct_option'])) continue;
        
        // Find simpler char for correct option if text provided
        // Logic: if correct_option is one of A,B,C,D text, map to A,B,C,D char
        // But table def has correct_option as CHAR(1) or VARCHAR?
        // Step 168: correct_option CHAR(1) NOT NULL
        // Note: The prompt asked for "text of correct answer". We need to map it back or change prompt.
        // Let's assume prompt returns text. We need to find which option it matches.
        
        $qText = $q['question'];
        $optA = $q['option_a'];
        $optB = $q['option_b'];
        $optC = $q['option_c'];
        $optD = $q['option_d'];
        $ansText = $q['correct_option'];
        
        $correctChar = 'A'; // default
        if (trim($ansText) == trim($optA)) $correctChar = 'A';
        elseif (trim($ansText) == trim($optB)) $correctChar = 'B';
        elseif (trim($ansText) == trim($optC)) $correctChar = 'C';
        elseif (trim($ansText) == trim($optD)) $correctChar = 'D';
        else {
            // If it's just 'A' or 'Option A'
            $upper = strtoupper(trim($ansText));
            if (strpos($upper, 'A') === 0) $correctChar = 'A';
            if (strpos($upper, 'B') === 0) $correctChar = 'B';
            if (strpos($upper, 'C') === 0) $correctChar = 'C';
            if (strpos($upper, 'D') === 0) $correctChar = 'D';
        }

        $stmt->bind_param('sssssss', $qText, $optA, $optB, $optC, $optD, $correctChar, $today);
        if ($stmt->execute()) {
            $inserted[] = $q;
        }
    }
    
    return $inserted;
}

?>

<div class="container main-content pt-5">
    <div class="card shadow" style="max-width: 800px; margin: auto;">
        <div class="card-header bg-success text-white">
            <h3><i class="fas fa-robot"></i> Generative AI Question Paper</h3>
        </div>
        <div class="card-body">
            <?php if (empty($generated)): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label>Topic</label>
                        <input type="text" name="topic" value="<?= htmlspecialchars($topic) ?>" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Number of Questions</label>
                        <select name="count" class="form-control">
                            <option value="5">5 Questions</option>
                            <option value="10" selected>10 Questions</option>
                            <option value="20">20 Questions</option>
                        </select>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-magic"></i> Generate Questions
                    </button>
                    <div class="text-muted mt-2 small text-center">This may take up to 30 seconds.</div>
                </form>
            <?php else: ?>
                <div class="alert alert-success">
                    Successfully generated <?= count($generated) ?> questions!
                </div>
                <h5>Preview:</h5>
                <ul class="list-group mb-4">
                    <?php foreach ($generated as $i => $q): ?>
                        <li class="list-group-item">
                            <strong>Q<?= $i+1 ?>:</strong> <?= htmlspecialchars($q['question']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="../select_class.php" class="btn btn-primary">Go to Paper Setup</a>
                <a href="index.php" class="btn btn-secondary">Back to Topic Search</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
