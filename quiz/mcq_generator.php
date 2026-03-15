<?php
/**
 * MCQ Generator - AI-based topic search and MCQ generation
 * 1 request = all topics | 1 request = all MCQs (single API key each)
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../services/AIKeyRotator.php';
require_once __DIR__ . '/../services/MeilisearchService.php';

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
        'temperature' => 0.5,
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
    
    // Static cache for the duration of the request
    static $topicIdCache = [];
    if (isset($topicIdCache[$topicName])) {
        return $topicIdCache[$topicName];
    }

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
        $id = $row['id'];
        $stmt->close();
        $topicIdCache[$topicName] = $id;
        return $id;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO AIQuestionsTopic (topic_name) VALUES (?)");
    if (!$stmt) return null;
    $stmt->bind_param("s", $topicName);
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        $topicIdCache[$topicName] = $id;
        try {
            $meili = new MeilisearchService();
            $meili->addTopic(trim($topicName), 'ai_questions_topic', 'mcq');
        } catch (Throwable $e) {
            /* ignore */
        }
        return $id;
    }
    $stmt->close();
    return null;
}

/**
 * Shared helper to rotate AI keys and select model
 */
function getAiKeyAndModel($cacheManager) {
    $rotator = new AIKeyRotator($cacheManager);
    $keyItem = $rotator->getNextKey();
    if (!$keyItem) {
        error_log('AI Generation: No API keys available');
        return [null, null, null];
    }
    $model = $keyItem['model'] ?: EnvLoader::get('AI_DEFAULT_MODEL');
    if (!$model) {
        error_log('AI Generation: No model specified in API key or environment');
        return [null, null, null];
    }
    return [$keyItem, $model, $rotator];
}

/**
 * Shared helper to save generated MCQs to DB
 */
function saveGeneratedMcqs($conn, $mcqs, $defaultTopic, $cacheManager = null, $cacheKey = null) {
    if (empty($mcqs)) return [];

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('INSERT INTO AIGeneratedMCQs (topic_id, topic, question_text, option_a, option_b, option_c, option_d, correct_option, generated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) return [];

    $inserted = [];
    foreach ($mcqs as $mcq) {
        if (!isset($mcq['question'])) continue;

        $topicVal = isset($mcq['topic']) ? (string) trim($mcq['topic']) : $defaultTopic;
        $topicId = getOrCreateTopicId($conn, $topicVal);
        
        $q = (string) $mcq['question'];
        $optA = $mcq['option_a'] ?? '';
        $optB = $mcq['option_b'] ?? '';
        $optC = $mcq['option_c'] ?? '';
        $optD = $mcq['option_d'] ?? '';
        $corr = $mcq['correct_option'] ?? '';
        
        $stmt->bind_param('issssssss', $topicId, $topicVal, $q, $optA, $optB, $optC, $optD, $corr, $now);
        if ($stmt->execute()) {
            try {
                $meili = new MeilisearchService();
                $meili->addTopic($topicVal, 'ai_mcqs', 'mcq');
            } catch (Throwable $e) {
                /* ignore */
            }
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

    if ($cacheManager && $cacheKey && !empty($inserted)) {
        try {
            $cacheManager->setex($cacheKey, 86400, json_encode($inserted));
        } catch (Exception $e) {}
    }

    return $inserted;
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

    $lvl = in_array(strtolower($level), ['easy', 'medium', 'hard']) ? strtolower($level) : '';
    $cacheKey = 'ai_mcqs_bulk_' . md5(implode(',', $topics) . '_' . $count . ($lvl ? '_' . $lvl : ''));

    if ($cacheManager) {
        $cached = $cacheManager->get($cacheKey);
        if ($cached) {
            $data = json_decode($cached, true);
            if (is_array($data) && count($data) >= $count) return array_slice($data, 0, $count);
        }
    }

    list($keyItem, $model, $rotator) = getAiKeyAndModel($cacheManager);
    if (!$keyItem) return [];

    $levelHint = $lvl ? "Difficulty: {$lvl}. " : '';
    $topicsStr = implode(', ', $topics);
    // $distHint = count($topics) > 1
    //     ? "Distribute across topics: " . $topicsStr . ". Each MCQ must include \"topic\" field with the topic name."
    //     : "Topic: {$topicsStr}.";

    $prompt = "i have an exam of topics {$topicsStr} and you have to Generate exactly {$count} MCQs.  {$levelHint}Return ONLY a JSON array.generate correct answer for each MCQs.Make sure correct_option exactly matches the correct option.Each item: {\"topic\":\"...\", \"question\":\"...\", \"option_a\":\"...\", \"option_b\":\"...\", \"option_c\":\"...\", \"option_d\":\"...\", \"correct_option\":\"a\" or \"b\" or \"c\" or \"d\"}. No extra text.";
    $maxTokens = min(16000, 500 + $count * 400);

    list($resp, $code) = callOpenRouter($keyItem['key'], $model, $prompt, $maxTokens, 120);

    if ($code === 429 || $code === 402 || $code === 503) {
        $rotator->markExhausted($keyItem['key']);
        return [];
    }
    if (!$resp) return [];

    $rotator->logSuccess($keyItem['key']);
    $allMcqs = array_slice(parseMcqJson($resp), 0, $count);
    
    return saveGeneratedMcqs($conn, $allMcqs, $topics[0], $cacheManager, $cacheKey);
}

/**
 * Generate MCQs - 1 request, 1 API key, generates all MCQs (single topic).
 * For multiple topics, use generateMCQsBulkWithGemini() to get 1 request total.
 */
function generateMCQsWithGemini($topic, $count = 10, $level = '') {
    global $conn, $cacheManager;

    $lvl = in_array(strtolower($level), ['easy', 'medium', 'hard']) ? strtolower($level) : '';
    $cacheKey = 'ai_mcqs_' . md5($topic . '_' . $count . ($lvl ? '_' . $lvl : ''));

    if ($cacheManager) {
        $cached = $cacheManager->get($cacheKey);
        if ($cached) {
            $data = json_decode($cached, true);
            if (is_array($data) && count($data) >= $count) return array_slice($data, 0, $count);
        }
    }

    list($keyItem, $model, $rotator) = getAiKeyAndModel($cacheManager);
    if (!$keyItem) return [];

    $levelHint = $lvl ? "Difficulty: {$lvl}. " : '';
    $prompt = "Generate exactly {$count} MCQs on topic: {$topic}.{$levelHint}Return ONLY a JSON array. Each item: {\"question\":\"...\", \"option_a\":\"...\", \"option_b\":\"...\", \"option_c\":\"...\", \"option_d\":\"...\", \"correct_option\":\"a\" or \"b\" or \"c\" or \"d\"}. Also recheck the correct answer. No extra text.";
    $maxTokens = min(18000, 500 + $count * 400);

    list($resp, $code) = callOpenRouter($keyItem['key'], $model, $prompt, $maxTokens, 60);

    if ($code === 429 || $code === 402 || $code === 503) {
        $rotator->markExhausted($keyItem['key']);
        return [];
    }
    if (!$resp) return [];

    $rotator->logSuccess($keyItem['key']);
    $allMcqs = array_slice(parseMcqJson($resp), 0, $count);
    
    return saveGeneratedMcqs($conn, $allMcqs, $topic, $cacheManager, $cacheKey);
}

/**
 * Search for topics using AI
 * @param bool $forceRefresh Skip cache and fetch fresh AI results (for Load More)
 * @param array $excludeTopics Topic names to exclude from results (for Load More to get different topics)
 */
function searchTopicsWithGemini($searchQuery, $classId = 0, $bookId = 0, $questionTypes = [], $forceRefresh = false, $excludeTopics = []) {
    global $cacheManager;

    $cacheKey = 'ai_topic_' . sha1($searchQuery);
    if (!$forceRefresh && $cacheManager) {
        $cached = $cacheManager->get($cacheKey);
        if ($cached) {
            $data = json_decode($cached, true);
            if (is_array($data) && !empty($data)) return $data;
        }
    }

    list($keyItem, $model, $rotator) = getAiKeyAndModel($cacheManager);
    if (!$keyItem) return [];

    $excludeHint = '';
    if (!empty($excludeTopics)) {
        $excludeList = implode(', ', array_slice(array_map('trim', $excludeTopics), 0, 20));
        $excludeHint = " Do NOT include these (already shown): {$excludeList}. Return DIFFERENT topics.";
    }
    $prompt = " i have an exam and it's Topic: \"{$searchQuery}\". Return EXACTLY 8 topics according to \"{$searchQuery}\ that can come in exam  as a JSON array of strings.2-3 topics must closely match the text of {$searchQuery}. {$excludeHint}.";

    list($respBody, $code) = callOpenRouter($keyItem['key'], $model, $prompt, 2800, 25);

    if ($code === 429 || $code === 402) {
        $rotator->markExhausted($keyItem['key']);
    } elseif ($respBody) {
        $rotator->logSuccess($keyItem['key']);
    }

    if (empty($respBody)) return [];

    $topics = parseMcqJson($respBody);
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
            $cacheManager->setex($cacheKey, 86400, json_encode($out));
        } catch (Exception $e) {}
    }

    return $out;
}

/**
 * Check MCQs with AI - implementation
 */
function checkMCQsWithAI($limit = 50, $startId = null, $endId = null, $sourceTable = 'AIGeneratedMCQs', $specificIds = null) {
    global $conn, $cacheManager;

    if ($sourceTable === 'mcqs') {
        $mainTable = 'mcqs';
        $verifyTable = 'MCQsVerification';
        $pk = 'mcq_id';
        $fk = 'mcq_id';
        $qCol = 'question';
    } else {
        $mainTable = 'AIGeneratedMCQs';
        $verifyTable = 'AIMCQsVerification';
        $pk = 'id';
        $fk = 'mcq_id';
        $qCol = 'question_text';
    }

    // Ensure verification table exists
    if ($sourceTable === 'mcqs') {
        $conn->query("CREATE TABLE IF NOT EXISTS MCQsVerification (
            mcq_id INT PRIMARY KEY,
            verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
            last_checked_at DATETIME,
            suggested_correct_option TEXT,
            original_correct_option TEXT,
            ai_notes TEXT,
            FOREIGN KEY (mcq_id) REFERENCES mcqs(mcq_id) ON DELETE CASCADE
        )");
    } else {
        $conn->query("CREATE TABLE IF NOT EXISTS AIMCQsVerification (
            mcq_id INT PRIMARY KEY,
            verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
            last_checked_at DATETIME,
            suggested_correct_option TEXT,
            original_correct_option TEXT,
            ai_notes TEXT,
            FOREIGN KEY (mcq_id) REFERENCES AIGeneratedMCQs(id) ON DELETE CASCADE
        )");
    }

    // Fetch MCQs to check
    $where = [];
    $params = [];
    $types = "";

    if ($specificIds !== null && is_array($specificIds) && !empty($specificIds)) {
        $placeholders = implode(',', array_fill(0, count($specificIds), '?'));
        $where[] = "m.$pk IN ($placeholders)";
        foreach ($specificIds as $id) {
            $params[] = intval($id);
            $types .= "i";
        }
    } elseif ($startId !== null && $endId !== null) {
        $where[] = "m.$pk BETWEEN ? AND ?";
        $params[] = intval($startId);
        $params[] = intval($endId);
        $types .= "ii";
    } else {
        // Pending only
        $where[] = "(v.verification_status IS NULL OR v.verification_status = 'pending')";
    }

    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    $sql = "SELECT m.*, v.verification_status 
            FROM $mainTable m 
            LEFT JOIN $verifyTable v ON m.$pk = v.$fk 
            $whereSql 
            ORDER BY m.$pk ASC 
            LIMIT ?";
    
    $params[] = intval($limit);
    $types .= "i";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return ['success' => false, 'message' => 'Prepare failed: ' . $conn->error];
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $mcqs = [];
    while ($row = $res->fetch_assoc()) {
        $mcqs[] = $row;
    }
    $stmt->close();

    if (empty($mcqs)) {
        return ['success' => true, 'stats' => ['checked' => 0, 'verified' => 0, 'corrected' => 0, 'flagged' => 0, 'processed_ids' => []]];
    }

    list($keyItem, $model, $rotator) = getAiKeyAndModel($cacheManager);
    if (!$keyItem) return ['success' => false, 'message' => 'No AI keys available'];

    $prompt = "You are an expert examiner. Verify the following MCQs for factual accuracy and correct answers. 
    For each MCQ, determine if the 'correct_option' (which contains the text of the correct answer) is actually correct.
    If it's correct, status is 'verified'.
    If another option is correct, status is 'corrected' and provide the correct option text in 'suggested_correct_option'.
    If the question or options are nonsensical or broken, status is 'flagged'.
    Return ONLY a JSON array of objects, one for each ID.
    Format: [{\"id\": 1, \"status\": \"verified\"|\"corrected\"|\"flagged\", \"suggested\": \"text of correct option if corrected\", \"notes\": \"brief reason if corrected or flagged\"}]
    
    MCQs to verify:\n";

    foreach ($mcqs as $m) {
        $prompt .= "ID: {$m[$pk]}, Topic: {$m['topic']}, Q: {$m[$qCol]}, A: {$m['option_a']}, B: {$m['option_b']}, C: {$m['option_c']}, D: {$m['option_d']}, Correct: {$m['correct_option']}\n";
    }

    list($resp, $code) = callOpenRouter($keyItem['key'], $model, $prompt, 10000, 120);

    if ($code !== 200 || !$resp) {
        return ['success' => false, 'message' => 'AI call failed with code ' . $code];
    }

    $results = parseMcqJson($resp);
    if (!is_array($results)) return ['success' => false, 'message' => 'Failed to parse AI response'];

    $stats = ['checked' => 0, 'verified' => 0, 'corrected' => 0, 'flagged' => 0, 'processed_ids' => []];
    $now = date('Y-m-d H:i:s');

    foreach ($results as $res) {
        $id = intval($res['id'] ?? 0);
        $status = $res['status'] ?? 'pending';
        $suggested = $res['suggested'] ?? '';
        $notes = $res['notes'] ?? '';

        if ($id <= 0) continue;

        // Find original MCQ data
        $original = null;
        foreach ($mcqs as $m) {
            if (intval($m[$pk]) === $id) {
                $original = $m;
                break;
            }
        }
        if (!$original) continue;

        $stats['checked']++;
        if ($status === 'verified') $stats['verified']++;
        elseif ($status === 'corrected') $stats['corrected']++;
        elseif ($status === 'flagged') $stats['flagged']++;
        $stats['processed_ids'][] = $id;

        // Update verification table
        $upsertSql = "INSERT INTO $verifyTable (mcq_id, verification_status, last_checked_at, suggested_correct_option, original_correct_option, ai_notes) 
                      VALUES (?, ?, ?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE 
                      verification_status = VALUES(verification_status), 
                      last_checked_at = VALUES(last_checked_at), 
                      suggested_correct_option = VALUES(suggested_correct_option), 
                      original_correct_option = VALUES(original_correct_option), 
                      ai_notes = VALUES(ai_notes)";
        
        $originalCorrect = $original['correct_option'];
        $stmt = $conn->prepare($upsertSql);
        $stmt->bind_param('isssss', $id, $status, $now, $suggested, $originalCorrect, $notes);
        $stmt->execute();
        $stmt->close();

        // If corrected, also update the main table
        if ($status === 'corrected' && !empty($suggested)) {
            $updateMainSql = "UPDATE $mainTable SET correct_option = ? WHERE $pk = ?";
            $stmt = $conn->prepare($updateMainSql);
            $stmt->bind_param('si', $suggested, $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    return ['success' => true, 'stats' => $stats];
}
