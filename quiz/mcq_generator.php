<?php
/**
 * MCQ Generator - AI-based topic search and MCQ generation
 * 1 request = all topics | 1 request = all MCQs (single API key each)
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../services/AIKeyRotator.php';

$cacheManager = null;
if (file_exists(__DIR__ . '/../services/CacheManager.php')) {
    require_once __DIR__ . '/../services/CacheManager.php';
    try {
        $cacheManager = new CacheManager();
    } catch (Exception $e) {
        $cacheManager = null;
    }
}

/**
 * Make OpenRouter API call - returns [response_text, http_code] or [null, code]
 */
function callOpenRouter($apiKey, $model, $prompt, $maxTokens = 18000, $timeout = 60) {
    $payload = [
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.7,
        'max_tokens' => $maxTokens,
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: https://paper.bhattichemicalsindustry.com.pk',
            'X-Title: Ahmad Learning Hub',
        ],
        CURLOPT_TIMEOUT => $timeout,
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $resp) {
        $dec = json_decode($resp, true);
        if (isset($dec['choices'][0]['message']['content'])) {
            return [$dec['choices'][0]['message']['content'], $code];
        }
    }
    return [null, $code];
}

/**
 * Parse JSON array from AI response
 */
function parseMcqJson($text) {
    $txt = preg_replace('/<think>.*?<\/think>/s', '', $text);
    if (preg_match('/```json(.*?)```/s', $txt, $m)) {
        $jsonText = trim($m[1]);
    } elseif (preg_match('/```(.*?)```/s', $txt, $m)) {
        $jsonText = trim($m[1]);
    } else {
        $s = strpos($txt, '[');
        $e = strrpos($txt, ']');
        $jsonText = ($s !== false && $e !== false && $e >= $s) ? substr($txt, $s, $e - $s + 1) : $txt;
    }
    $arr = json_decode($jsonText, true);
    return is_array($arr) ? $arr : [];
}

/**
 * Get or create topic ID from AIQuestionsTopic table
 */
function getOrCreateTopicId($conn, $topicName) {
    if (empty($topicName)) return null;
    
    // Ensure table exists (just in case)
    static $checked = false;
    if (!$checked) {
        $conn->query("CREATE TABLE IF NOT EXISTS AIQuestionsTopic (
            id INT AUTO_INCREMENT PRIMARY KEY,
            topic_name VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $checked = true;
    }

    $stmt = $conn->prepare("SELECT id FROM AIQuestionsTopic WHERE topic_name = ?");
    if (!$stmt) return null;
    $stmt->bind_param("s", $topicName);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        return $row['id'];
    }
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO AIQuestionsTopic (topic_name) VALUES (?)");
    if (!$stmt) return null;
    $stmt->bind_param("s", $topicName);
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }
    $stmt->close();
    return null;
}

/**
 * Generate ALL MCQs in 1 request - for single topic or multiple topics.
 * Use this when you need many MCQs: 1 API call generates everything.
 *
 * @param string|array $topicOrTopics Single topic string, or array of topics
 * @param int $count Total MCQs to generate
 * @param string $level easy|medium|hard
 * @return array Generated MCQs
 */
function generateMCQsBulkWithGemini($topicOrTopics, $count = 10, $level = '') {
    global $conn, $cacheManager;

    $topics = is_array($topicOrTopics) ? $topicOrTopics : [$topicOrTopics];
    $topics = array_values(array_filter(array_map('trim', $topics)));
    if (empty($topics)) return [];

    $lvl = is_string($level) ? strtolower(trim($level)) : '';
    $lvl = in_array($lvl, ['easy', 'medium', 'hard']) ? $lvl : '';
    $cacheKey = 'ai_mcqs_bulk_' . md5(implode(',', $topics) . '_' . $count . ($lvl ? '_' . $lvl : ''));

    if ($cacheManager) {
        try {
            $cached = $cacheManager->get($cacheKey);
            if ($cached !== false && $cached !== null) {
                $data = json_decode($cached, true);
                if (is_array($data) && count($data) >= $count) {
                    return array_slice($data, 0, $count);
                }
            }
        } catch (Exception $e) {}
    }

    $rotator = new AIKeyRotator($cacheManager);
    $keyItem = $rotator->getNextKey();
    if (!$keyItem) {
        error_log('generateMCQsBulkWithGemini: No API keys available');
        return [];
    }

    $model = $keyItem['model'] ?: EnvLoader::get('AI_DEFAULT_MODEL', 'liquid/lfm-2.5-1.2b-thinking:free');
    $levelHint = $lvl ? "Difficulty: {$lvl}. " : '';
    $topicsStr = implode(', ', $topics);
    $distHint = count($topics) > 1
        ? "Distribute across topics: " . $topicsStr . ". Each MCQ must include \"topic\" field with the topic name."
        : "Topic: {$topicsStr}.";

    $prompt = "Generate exactly {$count} MCQs. {$distHint} {$levelHint}Return ONLY a JSON array. Each item: {\"topic\":\"...\", \"question\":\"...\", \"option_a\":\"...\", \"option_b\":\"...\", \"option_c\":\"...\", \"option_d\":\"...\", \"correct_option\":\"a\" or \"b\" or \"c\" or \"d\"}. No extra text.";
    $maxTokens = min(16000, 500 + $count * 400);

    list($resp, $code) = callOpenRouter($keyItem['key'], $model, $prompt, $maxTokens, 120);

    if ($code === 429 || $code === 402 || $code === 503) {
        $rotator->markExhausted($keyItem['key']);
        return [];
    }
    if (!$resp) return [];

    $rotator->logSuccess($keyItem['key']);
    $allMcqs = parseMcqJson($resp);
    $allMcqs = array_filter($allMcqs, function ($m) { return isset($m['question']); });
    $allMcqs = array_slice($allMcqs, 0, $count);

    if (empty($allMcqs)) return [];

    $now = date('Y-m-d H:i:s');
    // Updated to include topic_id
    $stmt = $conn->prepare('INSERT INTO AIGeneratedMCQs (topic_id, topic, question_text, option_a, option_b, option_c, option_d, correct_option, generated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) return [];

    $inserted = [];
    $firstTopic = $topics[0];
    $topicIdCache = [];

    foreach ($allMcqs as $mcq) {
        $topicVal = isset($mcq['topic']) ? (string) trim($mcq['topic']) : $firstTopic;
        
        // Resolve topic_id
        if (!isset($topicIdCache[$topicVal])) {
            $topicIdCache[$topicVal] = getOrCreateTopicId($conn, $topicVal);
        }
        $topicId = $topicIdCache[$topicVal];

        $q = (string) ($mcq['question'] ?? '');
        $optA = $mcq['option_a'] ?? '';
        $optB = $mcq['option_b'] ?? '';
        $optC = $mcq['option_c'] ?? '';
        $optD = $mcq['option_d'] ?? '';
        $corr = $mcq['correct_option'] ?? '';
        
        $stmt->bind_param('issssssss', $topicId, $topicVal, $q, $optA, $optB, $optC, $optD, $corr, $now);
        if ($stmt->execute()) {
            $inserted[] = [
                'id' => $stmt->insert_id,
                'topic_id' => $topicId,
                'topic' => $topicVal,
                'question' => $q,
                'option_a' => $optA,
                'option_b' => $optB,
                'option_c' => $optC,
                'option_d' => $optD,
                'correct_option' => $corr,
            ];
        }
    }
    $stmt->close();

    if ($cacheManager && !empty($inserted)) {
        try {
            $cacheManager->setex($cacheKey, 86400, json_encode($inserted));
        } catch (Exception $e) {}
    }

    return $inserted;
}

/**
 * Generate MCQs - 1 request, 1 API key, generates all MCQs (single topic).
 * For multiple topics, use generateMCQsBulkWithGemini() to get 1 request total.
 */
function generateMCQsWithGemini($topic, $count = 10, $level = '') {
    global $conn, $cacheManager;

    $lvl = is_string($level) ? strtolower(trim($level)) : '';
    $lvl = in_array($lvl, ['easy', 'medium', 'hard']) ? $lvl : '';
    $cacheKey = 'ai_mcqs_' . md5($topic . '_' . $count . ($lvl ? '_' . $lvl : ''));

    if ($cacheManager) {
        try {
            $cached = $cacheManager->get($cacheKey);
            if ($cached !== false && $cached !== null) {
                $data = json_decode($cached, true);
                if (is_array($data) && count($data) >= $count) {
                    return array_slice($data, 0, $count);
                }
            }
        } catch (Exception $e) {}
    }

    $rotator = new AIKeyRotator($cacheManager);
    $keyItem = $rotator->getNextKey();
    if (!$keyItem) {
        error_log('generateMCQsWithGemini: No API keys available');
        return [];
    }

    $model = $keyItem['model'] ?: EnvLoader::get('AI_DEFAULT_MODEL', 'liquid/lfm-2.5-1.2b-thinking:free');
    $levelHint = $lvl ? "Difficulty: {$lvl}. " : '';
    $prompt = "Generate exactly {$count} MCQs on topic: {$topic}. {$levelHint}Return ONLY a JSON array. Each item: {\"question\":\"...\", \"option_a\":\"...\", \"option_b\":\"...\", \"option_c\":\"...\", \"option_d\":\"...\", \"correct_option\":\"a\" or \"b\" or \"c\" or \"d\"}. No extra text.";
    $maxTokens = min(18000, 500 + $count * 400);

    list($resp, $code) = callOpenRouter($keyItem['key'], $model, $prompt, $maxTokens, 60);

    if ($code === 429 || $code === 402 || $code === 503) {
        $rotator->markExhausted($keyItem['key']);
        return [];
    }
    if (!$resp) return [];

    $rotator->logSuccess($keyItem['key']);
    $allMcqs = parseMcqJson($resp);
    $allMcqs = array_slice(array_filter($allMcqs, function ($m) { return isset($m['question']); }), 0, $count);

    if (empty($allMcqs)) return [];

    $now = date('Y-m-d H:i:s');
    // Updated to include topic_id
    $stmt = $conn->prepare('INSERT INTO AIGeneratedMCQs (topic_id, topic, question_text, option_a, option_b, option_c, option_d, correct_option, generated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) return [];

    $inserted = [];
    $topicVal = (string) $topic;
    $topicId = getOrCreateTopicId($conn, $topicVal);

    foreach ($allMcqs as $mcq) {
        $q = (string) ($mcq['question'] ?? '');
        $optA = $mcq['option_a'] ?? '';
        $optB = $mcq['option_b'] ?? '';
        $optC = $mcq['option_c'] ?? '';
        $optD = $mcq['option_d'] ?? '';
        $corr = $mcq['correct_option'] ?? '';
        
        $stmt->bind_param('issssssss', $topicId, $topicVal, $q, $optA, $optB, $optC, $optD, $corr, $now);
        if ($stmt->execute()) {
            $inserted[] = [
                'id' => $stmt->insert_id,
                'topic_id' => $topicId,
                'topic' => $topicVal,
                'question' => $q,
                'option_a' => $optA,
                'option_b' => $optB,
                'option_c' => $optC,
                'option_d' => $optD,
                'correct_option' => $corr,
            ];
        }
    }
    $stmt->close();

    if ($cacheManager && !empty($inserted)) {
        try {
            $cacheManager->setex($cacheKey, 86400, json_encode($inserted));
        } catch (Exception $e) {}
    }

    return $inserted;
}

/**
 * Search for topics using AI
 * @param bool $forceRefresh Skip cache and fetch fresh AI results (for Load More)
 * @param array $excludeTopics Topic names to exclude from results (for Load More to get different topics)
 */
function searchTopicsWithGemini($searchQuery, $classId = 0, $bookId = 0, $questionTypes = [], $forceRefresh = false, $excludeTopics = []) {
    global $cacheManager;

    if (!$forceRefresh && $cacheManager) {
        try {
            $cached = $cacheManager->get('ai_topic_' . sha1($searchQuery));
            if ($cached !== false && $cached !== null) {
                $data = json_decode($cached, true);
                if (is_array($data) && !empty($data)) {
                    return $data;
                }
            }
        } catch (Exception $e) {}
    }

    $rotator = new AIKeyRotator($cacheManager);
    $keyItem = $rotator->getNextKey();
    if (!$keyItem) {
        error_log('searchTopicsWithGemini: No API keys available');
        return [];
    }

    $model = $keyItem['model'] ?: EnvLoader::get('AI_DEFAULT_MODEL', 'liquid/lfm-2.5-1.2b-thinking:free');
    $excludeHint = '';
    if (!empty($excludeTopics)) {
        $excludeList = implode(', ', array_slice(array_map('trim', $excludeTopics), 0, 20));
        $excludeHint = " Do NOT include these (already shown): {$excludeList}. Return DIFFERENT topics.";
    }
    $prompt = "Topic: \"{$searchQuery}\". Return EXACTLY 8 educational subtopics as a JSON array of strings.{$excludeHint} No numbering, no explanations.";

    list($respBody, $code) = callOpenRouter($keyItem['key'], $model, $prompt, 2800, 25);

    if ($code === 429 || $code === 402) {
        $rotator->markExhausted($keyItem['key']);
    } elseif ($respBody) {
        $rotator->logSuccess($keyItem['key']);
    }

    if (empty($respBody)) return [];

    $text = preg_replace('/<think>.*?<\/think>/s', '', $respBody);
    preg_match('/\[.*?\]/s', $text, $m);
    $jsonText = $m[0] ?? $text;
    $topics = json_decode($jsonText, true);

    if (!is_array($topics)) return [];

    $out = [];
    foreach ($topics as $t) {
        if (is_string($t) && !empty(trim($t))) {
            $out[] = [
                'topic' => trim($t),
                'class_id' => $classId,
                'book_id' => $bookId,
                'similarity' => 85.0,
                'web_searched' => true,
            ];
        }
    }

    if ($cacheManager && !empty($out) && !$forceRefresh && empty($excludeTopics)) {
        try {
            $cacheManager->setex('ai_topic_' . sha1($searchQuery), 86400, json_encode($out));
        } catch (Exception $e) {}
    }

    return $out;
}

/**
 * Check MCQs with AI - stub
 */
function checkMCQsWithAI($limit = 50, $startId = null, $endId = null, $sourceTable = 'AIGeneratedMCQs', $specificIds = null) {
    return ['success' => false, 'message' => 'AI verification currently disabled'];
}
