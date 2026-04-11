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
            'HTTP-Referer: https://ahmadlearninghub.com.pk',
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
 * True if the key is an NVIDIA API key (same family as GENERATING_KEYWORDS_KEY).
 */
function isNvidiaApiKey($key) {
    return is_string($key) && strncmp($key, 'nvapi-', 6) === 0;
}

/**
 * NVIDIA integrate.api.nvidia.com chat completions — OpenAI-compatible (matches TopicAIService / keyword generation).
 * Returns [response_text|null, http_code].
 */
function callNvidiaChatCompletions($apiKey, $model, $prompt, $maxTokens = 4096, $timeout = 120) {
    $url = 'https://integrate.api.nvidia.com/v1/chat/completions';
    $payload = [
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.3,
        'top_p' => 0.7,
        'max_tokens' => $maxTokens,
        'stream' => false,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
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
 * MCQ recheck: OpenRouter keys → OpenRouter; nvapi- keys → NVIDIA (same as GENERATING_KEYWORDS_KEY).
 */
function callRecheckAi($apiKey, $model, $prompt, $maxTokens = 12000, $timeout = 120) {
    if (isNvidiaApiKey($apiKey)) {
        $cap = min((int) $maxTokens, 8192);
        return callNvidiaChatCompletions($apiKey, $model, $prompt, $cap, $timeout);
    }
    return callOpenRouter($apiKey, $model, $prompt, $maxTokens, $timeout);
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
 * API key used only for MCQ verification (reads .env.local / .env.production via EnvLoader).
 */
function getRecheckApiKey() {
    $k = EnvLoader::get('RECHECK_API_KEY', '');
    $k = is_string($k) ? trim($k) : '';
    if ($k !== '') {
        return $k;
    }
    $fallback = EnvLoader::get('GENERATING_KEYWORDS_KEY', '');
    return is_string($fallback) ? trim($fallback) : '';
}

/**
 * Model for recheck: RECHECK_MODEL if set; else NVIDIA default for nvapi keys (keyword stack), else AI_DEFAULT_MODEL.
 */
function getRecheckModel() {
    $m = EnvLoader::get('RECHECK_MODEL', '');
    $m = is_string($m) ? trim($m) : '';
    if ($m !== '') {
        return $m;
    }
    $key = getRecheckApiKey();
    if (isNvidiaApiKey($key)) {
        return 'qwen/qwen3-next-80b-a3b-instruct';
    }
    return EnvLoader::get('AI_DEFAULT_MODEL', 'gpt-4-turbo');
}

/** AI MCQs use unified MCQVerification; manual mcqs table uses MCQsVerification (never dropped by migrate). */
function mcqVerificationTableName() {
    return 'MCQVerification';
}

/**
 * Source value stored in MCQVerification.source (AI rows only).
 */
function mcqVerificationSourceValue($sourceTable) {
    return $sourceTable === 'mcqs' ? 'mcqs' : 'AIGeneratedMCQs';
}

/**
 * Manual MCQs verification table — preserved on install; not migrated away.
 */
function ensureMcqsVerificationTable($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->query("CREATE TABLE IF NOT EXISTS MCQsVerification (
        mcq_id INT PRIMARY KEY,
        verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
        last_checked_at DATETIME NULL,
        suggested_correct_option TEXT,
        original_correct_option TEXT,
        ai_notes TEXT,
        explanation TEXT,
        FOREIGN KEY (mcq_id) REFERENCES mcqs(mcq_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Migrate legacy AI-only tables into MCQVerification, then drop them.
 * MCQsVerification is never migrated or dropped (manual MCQ verification records stay here).
 */
function migrateLegacyMcqVerificationTables($conn) {
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $pairs = [
        ['AIMCQsVerification', 'AIGeneratedMCQs'],
        ['AIGeneratedMCQsVerification', 'AIGeneratedMCQs'],
    ];

    foreach ($pairs as $pair) {
        $legacy = $pair[0];
        $srcEnum = $pair[1];
        if ($srcEnum !== 'mcqs' && $srcEnum !== 'AIGeneratedMCQs') {
            continue;
        }

        $legSafe = preg_replace('/[^A-Za-z0-9_]/', '', $legacy);
        if ($legSafe === '') {
            continue;
        }

        $chk = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($legSafe) . "'");
        if (!$chk || $chk->num_rows === 0) {
            continue;
        }

        $hasExplanation = false;
        $colChk = $conn->query("SHOW COLUMNS FROM `$legSafe` LIKE 'explanation'");
        if ($colChk && $colChk->num_rows > 0) {
            $hasExplanation = true;
        }

        if ($hasExplanation) {
            $sql = "INSERT IGNORE INTO MCQVerification (source, mcq_id, verification_status, last_checked_at, suggested_correct_option, original_correct_option, ai_notes, explanation)
                    SELECT '$srcEnum', mcq_id, verification_status, last_checked_at, suggested_correct_option, original_correct_option, ai_notes, explanation FROM `$legSafe`";
        } else {
            $sql = "INSERT IGNORE INTO MCQVerification (source, mcq_id, verification_status, last_checked_at, suggested_correct_option, original_correct_option, ai_notes)
                    SELECT '$srcEnum', mcq_id, verification_status, last_checked_at, suggested_correct_option, original_correct_option, ai_notes FROM `$legSafe`";
        }

        if (!$conn->query($sql)) {
            error_log('migrateLegacyMcqVerificationTables from ' . $legSafe . ': ' . $conn->error);
        }
        $conn->query("DROP TABLE IF EXISTS `$legSafe`");
    }
}

/**
 * Create MCQVerification and migrate old verification tables (idempotent).
 */
function ensureMcqVerificationTable($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    ensureMcqsVerificationTable($conn);

    $conn->query("CREATE TABLE IF NOT EXISTS MCQVerification (
        source ENUM('AIGeneratedMCQs', 'mcqs') NOT NULL,
        mcq_id INT NOT NULL,
        verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
        last_checked_at DATETIME NULL,
        suggested_correct_option TEXT,
        original_correct_option TEXT,
        ai_notes TEXT,
        explanation TEXT,
        PRIMARY KEY (source, mcq_id),
        KEY idx_mv_status (source, verification_status),
        KEY idx_mv_checked (source, last_checked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    migrateLegacyMcqVerificationTables($conn);
}

/**
 * Add explanation column on AIGeneratedMCQs / MCQVerification when missing (idempotent).
 */
function ensureMcqExplanationColumns($conn) {
    ensureMcqVerificationTable($conn);

    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $targets = [
        ['AIGeneratedMCQs', 'ADD COLUMN explanation TEXT NULL AFTER correct_option'],
        ['MCQVerification', 'ADD COLUMN explanation TEXT NULL AFTER ai_notes'],
        ['MCQsVerification', 'ADD COLUMN explanation TEXT NULL AFTER ai_notes'],
    ];
    foreach ($targets as $t) {
        list($table, $fragment) = $t;
        $esc = $conn->real_escape_string($table);
        $chk = $conn->query("SHOW TABLES LIKE '$esc'");
        if (!$chk || $chk->num_rows === 0) {
            continue;
        }
        $col = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'explanation'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE `$table` $fragment");
        }
    }
}

/**
 * Verify MCQ rows using only RECHECK_API_KEY (OpenRouter-compatible endpoint).
 *
 * @param array $mcqs Rows from DB (or synthetic rows for freshly inserted AI MCQs)
 * @return array { success, message?, stats }
 */
function verifyMcqsWithRecheckApi($conn, array $mcqs, $sourceTable = 'AIGeneratedMCQs') {
    ensureMcqExplanationColumns($conn);

    $apiKey = getRecheckApiKey();
    if ($apiKey === '') {
        return ['success' => false, 'message' => 'No recheck key: set RECHECK_API_KEY or GENERATING_KEYWORDS_KEY in .env.local / .env.production.'];
    }

    if ($sourceTable === 'mcqs') {
        $mainTable = 'mcqs';
        $pk = 'mcq_id';
        $qCol = 'question';
    } else {
        $mainTable = 'AIGeneratedMCQs';
        $pk = 'id';
        $qCol = 'question_text';
    }

    $useManualVerifyTable = ($sourceTable === 'mcqs');
    $verifyTable = $useManualVerifyTable ? 'MCQsVerification' : mcqVerificationTableName();
    $verifySource = mcqVerificationSourceValue($sourceTable);

    $model = getRecheckModel();

    $prompt = "You are an expert educator and examiner. Verify each MCQ for factual accuracy.\n"
        . "For each item, determine if the marked correct answer is accurate.\n"
        . "- If correct: status \"verified\".\n"
        . "- If another option (A, B, C, or D) is correct: status \"corrected\". Identify the correct letter.\n"
        . "- If NO option is correct: status \"corrected\". Rewrite the text of the option currently marked as correct (the \"Marked correct\" column) to be factually accurate.\n"
        . "Explanation: Write only 2-3 lines explaining ONLY why the correct option is right. Do NOT explain why others are wrong.\n"
        . "Return ONLY a JSON array. If status is \"corrected\", return the correct letter and the texts for ALL options (option_a, option_b, option_c, option_d).\n"
        . "Format: [{\"id\": <number>, \"status\": \"verified\"|\"corrected\"|\"flagged\", \"correct_option\": \"A\"|\"B\"|\"C\"|\"D\", \"option_a\": \"...\", \"option_b\": \"...\", \"option_c\": \"...\", \"option_d\": \"...\", \"explanation\": \"<2-3 lines explanation>\"}]\n\n"
        . "MCQs:\n";

    foreach ($mcqs as $m) {
        $correctText = $m['correct_option'];
        if ($sourceTable === 'mcqs') {
            $letter = strtoupper((string) $m['correct_option']);
            switch ($letter) {
                case 'A':
                    $correctText = $m['option_a'];
                    break;
                case 'B':
                    $correctText = $m['option_b'];
                    break;
                case 'C':
                    $correctText = $m['option_c'];
                    break;
                case 'D':
                    $correctText = $m['option_d'];
                    break;
            }
        }

        $topic = isset($m['topic']) ? $m['topic'] : 'General MCQ';
        if ($sourceTable === 'mcqs' && (!isset($m['topic']) || $m['topic'] === '')) {
            $topic = 'Class ' . ($m['class_id'] ?? 'Unknown') . ' - Book ' . ($m['book_id'] ?? 'Unknown') . ' - Chapter ' . ($m['chapter_id'] ?? 'Unknown');
        }

        $prompt .= "ID: {$m[$pk]}, Topic: {$topic}, Q: {$m[$qCol]}, A: {$m['option_a']}, B: {$m['option_b']}, C: {$m['option_c']}, D: {$m['option_d']}, Marked correct: {$correctText}\n";
    }

    list($resp, $code) = callRecheckAi($apiKey, $model, $prompt, 12000, 120);

    if ($code !== 200 || !$resp) {
        $hint = ($code === 401 && isNvidiaApiKey($apiKey))
            ? ' (check NVIDIA key and RECHECK_MODEL; nvapi keys use integrate.api.nvidia.com, not OpenRouter)'
            : '';
        return ['success' => false, 'message' => 'Recheck API call failed with HTTP code ' . $code . $hint];
    }

    $results = parseMcqJson($resp);
    if (!is_array($results)) {
        return ['success' => false, 'message' => 'Failed to parse recheck API response'];
    }

    $stats = ['checked' => 0, 'verified' => 0, 'corrected' => 0, 'flagged' => 0, 'processed_ids' => []];
    $now = date('Y-m-d H:i:s');

    foreach ($results as $res) {
        $id = intval($res['id'] ?? 0);
        $status = $res['status'] ?? 'pending';
        $correctOptionLetter = $res['correct_option'] ?? '';
        $notes = $res['notes'] ?? '';
        $explanation = $res['explanation'] ?? '';

        // If status is corrected, handle option updates
        $newOptions = [
            'option_a' => $res['option_a'] ?? null,
            'option_b' => $res['option_b'] ?? null,
            'option_c' => $res['option_c'] ?? null,
            'option_d' => $res['option_d'] ?? null,
        ];

        if ($id <= 0) {
            continue;
        }

        $original = null;
        foreach ($mcqs as $row) {
            if (intval($row[$pk]) === $id) {
                $original = $row;
                break;
            }
        }
        if (!$original) {
            continue;
        }

        $stats['checked']++;
        if ($status === 'verified') {
            $stats['verified']++;
        } elseif ($status === 'corrected') {
            $stats['corrected']++;
        } elseif ($status === 'flagged') {
            $stats['flagged']++;
        }
        $stats['processed_ids'][] = $id;

        // Suggested text for backward compatibility in verification table
        $suggestedText = '';
        if ($status === 'corrected' && !empty($correctOptionLetter)) {
            $letter = strtoupper($correctOptionLetter);
            if ($letter === 'A') $suggestedText = $newOptions['option_a'] ?? $original['option_a'];
            elseif ($letter === 'B') $suggestedText = $newOptions['option_b'] ?? $original['option_b'];
            elseif ($letter === 'C') $suggestedText = $newOptions['option_c'] ?? $original['option_c'];
            elseif ($letter === 'D') $suggestedText = $newOptions['option_d'] ?? $original['option_d'];
        }

        if ($useManualVerifyTable) {
            $upsertSql = "INSERT INTO MCQsVerification (mcq_id, verification_status, last_checked_at, suggested_correct_option, original_correct_option, ai_notes, explanation) 
                          VALUES (?, ?, ?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          verification_status = VALUES(verification_status), 
                          last_checked_at = VALUES(last_checked_at), 
                          suggested_correct_option = VALUES(suggested_correct_option), 
                          original_correct_option = VALUES(original_correct_option), 
                          ai_notes = VALUES(ai_notes),
                          explanation = VALUES(explanation)";
        } else {
            $upsertSql = "INSERT INTO MCQVerification (source, mcq_id, verification_status, last_checked_at, suggested_correct_option, original_correct_option, ai_notes, explanation) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          verification_status = VALUES(verification_status), 
                          last_checked_at = VALUES(last_checked_at), 
                          suggested_correct_option = VALUES(suggested_correct_option), 
                          original_correct_option = VALUES(original_correct_option), 
                          ai_notes = VALUES(ai_notes),
                          explanation = VALUES(explanation)";
        }

        $originalCorrect = $original['correct_option'];
        $stmt = $conn->prepare($upsertSql);
        if (!$stmt) {
            continue;
        }
        if ($useManualVerifyTable) {
            $stmt->bind_param('issssss', $id, $status, $now, $suggestedText, $originalCorrect, $notes, $explanation);
        } else {
            $stmt->bind_param('sissssss', $verifySource, $id, $status, $now, $suggestedText, $originalCorrect, $notes, $explanation);
        }
        $stmt->execute();
        $stmt->close();

        if ($sourceTable === 'AIGeneratedMCQs' && ($status === 'verified' || $status === 'corrected' || $status === 'flagged') && $explanation !== '') {
            $updEx = $conn->prepare("UPDATE AIGeneratedMCQs SET explanation = ? WHERE id = ?");
            if ($updEx) {
                $updEx->bind_param('si', $explanation, $id);
                $updEx->execute();
                $updEx->close();
            }
        }

        if ($status === 'corrected') {
            // Update correct_option letter/text
            if ($sourceTable === 'mcqs') {
                $letter = !empty($correctOptionLetter) ? strtoupper($correctOptionLetter) : '';
                if (!empty($letter)) {
                    $updateMainSql = "UPDATE $mainTable SET correct_option = ? WHERE $pk = ?";
                    $stmt = $conn->prepare($updateMainSql);
                    $stmt->bind_param('si', $letter, $id);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                // For AIGeneratedMCQs, correct_option is text
                if (!empty($suggestedText)) {
                    $updateMainSql = "UPDATE $mainTable SET correct_option = ? WHERE $pk = ?";
                    $stmt = $conn->prepare($updateMainSql);
                    $stmt->bind_param('si', $suggestedText, $id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Update option texts if returned
            foreach ($newOptions as $col => $text) {
                if ($text !== null && !empty($text)) {
                    $updateOptSql = "UPDATE $mainTable SET $col = ? WHERE $pk = ?";
                    $stmt = $conn->prepare($updateOptSql);
                    $stmt->bind_param('si', $text, $id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    return ['success' => true, 'stats' => $stats];
}

/**
 * After new AI MCQs are inserted, verify them with RECHECK_API_KEY.
 *
 * @param array $insertedRows Output rows from saveGeneratedMcqs (must include id, topic, question, options, correct_option)
 */
function verifyInsertedAIGeneratedMcqs($conn, array $insertedRows) {
    if (empty($insertedRows)) {
        return ['success' => true, 'stats' => ['checked' => 0, 'verified' => 0, 'corrected' => 0, 'flagged' => 0, 'processed_ids' => []]];
    }
    $mcqs = [];
    foreach ($insertedRows as $item) {
        if (empty($item['id'])) {
            continue;
        }
        $mcqs[] = [
            'id' => $item['id'],
            'topic' => $item['topic'] ?? '',
            'question_text' => $item['question'] ?? '',
            'option_a' => $item['option_a'] ?? '',
            'option_b' => $item['option_b'] ?? '',
            'option_c' => $item['option_c'] ?? '',
            'option_d' => $item['option_d'] ?? '',
            'correct_option' => $item['correct_option'] ?? '',
        ];
    }
    if (empty($mcqs)) {
        return ['success' => true, 'stats' => ['checked' => 0, 'verified' => 0, 'corrected' => 0, 'flagged' => 0, 'processed_ids' => []]];
    }
    $chunkSize = 12;
    $merged = [
        'success' => true,
        'stats' => ['checked' => 0, 'verified' => 0, 'corrected' => 0, 'flagged' => 0, 'processed_ids' => []],
    ];
    for ($off = 0; $off < count($mcqs); $off += $chunkSize) {
        $chunk = array_slice($mcqs, $off, $chunkSize);
        $r = verifyMcqsWithRecheckApi($conn, $chunk, 'AIGeneratedMCQs');
        if (empty($r['success'])) {
            return $r;
        }
        foreach (['checked', 'verified', 'corrected', 'flagged'] as $k) {
            $merged['stats'][$k] += (int) ($r['stats'][$k] ?? 0);
        }
        if (!empty($r['stats']['processed_ids'])) {
            $merged['stats']['processed_ids'] = array_merge($merged['stats']['processed_ids'], $r['stats']['processed_ids']);
        }
    }
    return $merged;
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

    // AIQuestionsTopic table is created in install.php - with safety fallback for runtime
    static $checked = false;
    if (!$checked) {
        $conn->query("CREATE TABLE IF NOT EXISTS AIQuestionsTopic (
            id INT AUTO_INCREMENT PRIMARY KEY,
            topic_name VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
function saveGeneratedMcqs($conn, $mcqs, $defaultTopic, $cacheManager = null, $cacheKey = null, $skipVerify = false) {
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

    if (!empty($inserted) && !$skipVerify) {
        $recheck = verifyInsertedAIGeneratedMcqs($conn, $inserted);
        if (empty($recheck['success'])) {
            error_log('verifyInsertedAIGeneratedMcqs: ' . ($recheck['message'] ?? 'failed'));
        }
        $ref = $conn->prepare('SELECT correct_option, explanation FROM AIGeneratedMCQs WHERE id = ?');
        if ($ref) {
            foreach ($inserted as &$insRow) {
                $rid = (int) ($insRow['id'] ?? 0);
                if ($rid <= 0) {
                    continue;
                }
                $ref->bind_param('i', $rid);
                $ref->execute();
                $upd = $ref->get_result();
                if ($upd && ($u = $upd->fetch_assoc())) {
                    $insRow['correct_option'] = $u['correct_option'];
                    if (!empty($u['explanation'])) {
                        $insRow['explanation'] = $u['explanation'];
                    }
                }
            }
            unset($insRow);
            $ref->close();
        }
    }

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
 * @param bool $skipVerify If true, skip sync verification (background verification should follow)
 * @return array Generated MCQs
 */
function generateMCQsBulkWithGemini($topicOrTopics, $count = 10, $level = '', $skipVerify = false) {
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
    $distHint = count($topics) > 1
        ? "Distribute across topics: " . $topicsStr . ". Each MCQ must include \"topic\" field with the topic name."
        : "Topic: {$topicsStr}.";

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
    
    return saveGeneratedMcqs($conn, $allMcqs, $topics[0], $cacheManager, $cacheKey, $skipVerify);
}

/**
 * Generate MCQs - 1 request, 1 API key, generates all MCQs (single topic).
 * For multiple topics, use generateMCQsBulkWithGemini() to get 1 request total.
 * @param bool $skipVerify If true, skip sync verification
 */
function generateMCQsWithGemini($topic, $count = 10, $level = '', $skipVerify = false) {
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
    
    return saveGeneratedMcqs($conn, $allMcqs, $topic, $cacheManager, $cacheKey, $skipVerify);
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
 $prompt = "i have an exam and it's Topic: \"{$searchQuery}\". Return EXACTLY 4 topics related to \"{$searchQuery}\" that can come in the exam as a JSON array of objects. 
Each object must have: 
- \"topic\": (string) The topic name. 2-3 topics must closely match the text of \"{$searchQuery}\". {$excludeHint}. 
- \"keywords\": (string) A comma-separated list of 3 highly relevant, specific, and searchable phrases that capture the core meaning, concepts, and real-world use of the topic. Avoid vague words. Use clear terms that help in search and filtering. 

Return ONLY the JSON array. No extra text.";

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
        $topicName = is_array($t) ? ($t['topic'] ?? '') : (is_string($t) ? $t : '');
        $keywords = is_array($t) ? ($t['keywords'] ?? '') : '';
        
        if (!empty(trim($topicName))) {
            $out[] = [
                'topic' => trim($topicName),
                'keywords' => trim($keywords),
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
    global $conn;

    ensureMcqExplanationColumns($conn);

    if ($sourceTable === 'mcqs') {
        $mainTable = 'mcqs';
        $pk = 'mcq_id';
        $qCol = 'question';
    } else {
        $mainTable = 'AIGeneratedMCQs';
        $pk = 'id';
        $qCol = 'question_text';
    }

    $joinVerify = ($sourceTable === 'mcqs')
        ? "LEFT JOIN MCQsVerification v ON m.$pk = v.mcq_id"
        : "LEFT JOIN MCQVerification v ON v.source = 'AIGeneratedMCQs' AND v.mcq_id = m.$pk";

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
            $joinVerify 
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

    return verifyMcqsWithRecheckApi($conn, $mcqs, $sourceTable);
}
