<?php
/**
 * MCQ Generator Helper
 * Automatically generates MCQs using OpenAI API (via OpenRouter)
 * Stores generated MCQs in cache and MySQL
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/APIKeyManager.php';

// Initialize APIKeyManager
$keyManager = new APIKeyManager();

// Try to load CacheManager if available
$cacheManager = null;
if (file_exists(__DIR__ . '/../services/CacheManager.php')) {
    require_once __DIR__ . '/../services/CacheManager.php';
    try {
        $cacheManager = new CacheManager();
    } catch (Exception $e) {
        error_log("CacheManager initialization failed: " . $e->getMessage());
    }
}


/**
 * Get an available API key using Round Robin and Locking
 * @param array $apiKeys List of API keys
 * @param CacheManager|null $cacheManager
 * @param array $excludedKeys Keys to skip
 * @return array|null ['key' => string, 'lockKey' => string] or null
 */
function getAvailableApiKey($apiKeys, $cacheManager, $excludedKeys = []) {
    if (empty($apiKeys)) return null;
    
    // If no cache manager, just pick the first non-excluded key
    if (!$cacheManager) {
        foreach ($apiKeys as $key) {
            if (!in_array($key, $excludedKeys)) return ['key' => $key, 'lockKey' => null];
        }
        return null;
    }

    $lockDuration = 30; // 30 seconds lock
    $maxRetries = 3;    // Try 3 times to find an unlocked key
    $retryDelay = 1;    // 1 second

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        // Get the last used index
        $lastIndex = (int)$cacheManager->get('openai_api_key_last_index');
        $count = count($apiKeys);
        
        // Iterate through all keys starting from next index
        for ($i = 0; $i < $count; $i++) {
            $currentIndex = ($lastIndex + 1 + $i) % $count;
            $key = $apiKeys[$currentIndex];
            
            if (in_array($key, $excludedKeys)) continue;
            
            $keyHash = substr(md5($key), 0, 10);
            $lockKey = "lock_apikey_" . $keyHash;
            
            // Check if locked
            if ($cacheManager->get($lockKey)) {
                continue; // Key is busy
            }
            
            // Lock it
            $cacheManager->setex($lockKey, $lockDuration, '1');
            $cacheManager->set('openai_api_key_last_index', $currentIndex);
            
            return [
                'key' => $key,
                'lockKey' => $lockKey
            ];
        }
        
        // If all keys are locked, wait a bit
        sleep($retryDelay);
    }
    
    // If we're here, all keys are busy. 
    // Find the next one in rotation that is not excluded and force use it
    $lastIndex = (int)$cacheManager->get('openai_api_key_last_index');
    $count = count($apiKeys);
    
    for ($i = 0; $i < $count; $i++) {
        $nextIndex = ($lastIndex + 1 + $i) % $count;
        $key = $apiKeys[$nextIndex];
        
        if (!in_array($key, $excludedKeys)) {
            $keyHash = substr(md5($key), 0, 10);
            $lockKey = "lock_apikey_" . $keyHash;
            $cacheManager->setex($lockKey, $lockDuration, '1');
            $cacheManager->set('openai_api_key_last_index', $nextIndex);
            
            return [
                'key' => $key,
                'lockKey' => $lockKey
            ];
        }
    }
    
    return null;
}

/**
 * Generate MCQs using OpenAI API (via OpenRouter)
 * @param string $topic Topic name
 * @param int $count Number of MCQs to generate
 * @return array Generated MCQs
 */
function generateMCQsWithGemini($topic, $count, $level = null) {
    global $conn, $cacheManager, $keyManager;
    
    $lvl = is_string($level) ? strtolower(trim($level)) : '';
    $lvl = in_array($lvl, ['school','college','university']) ? $lvl : '';
    $cacheKey = "ai_mcqs_" . md5($topic . "_" . $count . ($lvl ? "_" . $lvl : ""));
    
    // Check cache first
    if ($cacheManager) {
        $cached = $cacheManager->get($cacheKey);
        if ($cached !== false) {
            $cachedData = json_decode($cached, true);
            if (is_array($cachedData) && count($cachedData) >= $count) {
                return array_slice($cachedData, 0, $count);
            }
        }
    }
    
    // Get OpenAI API keys (OpenRouter)
    $apiKeys = $keyManager->getActiveKeys();
    
    $openaiModel = EnvLoader::get('OPENAI_MODEL', 'nvidia/nemotron-3-nano-30b-a3b:free');
    
    if (empty($apiKeys)) {
        error_log("OpenAI API keys not found");
        return [];
    }
    
    // Prepare prompt for OpenAI
    $levelInstruction = $lvl ? "STRICT LEVEL: {$lvl}\n- Tailor difficulty, vocabulary, and context to {$lvl}." : "Automatically determine the appropriate academic level:\n  - School \n  - College (Grades 11 to 12)\n  - University (Bachelor level)\n- Generate questions appropriate to the detected academic level.";
  
$prompt = "You are an academic question-paper generator.

Generate exactly {$count} high-quality Multiple Choice Questions (MCQs) strictly on the topic: {$topic}.

IMPORTANT RULES:
- Treat the {$topic} as a complete word or sentence. Do NOT change its meaning.
{$levelInstruction}
- Questions must be factual, conceptual, or application-based (NO opinions).
- Avoid repeated concepts or similar questions.
- Each question must have ONE and ONLY ONE correct answer.
- Check the correct answer carefully also do recheck.
- Language must be clear, academic, and student-friendly.

MCQ STRUCTURE RULES:
- Exactly 4 options per question labeled A, B, C, and D.
- Options must be concise and similar in length.


OUTPUT FORMAT (STRICT):
- Return ONLY a valid JSON array.
- No explanations, no markdown, no extra text.
- JSON must be machine-parseable without errors.

JSON STRUCTURE:
[
  {
    \"question\": \"Question text\",
    \"option_a\": \"Option A text\",
    \"option_b\": \"Option B text\",
    \"option_c\": \"Option C text\",
    \"option_d\": \"Option D text\",
    \"correct_option\": \"Exact text of the correct option\"
  }
]

FINAL CHECK BEFORE OUTPUT:
- Exactly {$count} MCQs.
- Academic tone maintained.
- All Options present.
- Output is pure JSON only.";

    try {
        $lastError = '';
        $response = '';
        $httpCode = 0;
        $success = false;

        $attemptedKeys = [];
        $maxAttempts = count($apiKeys);
        
        for ($k = 0; $k < $maxAttempts; $k++) {
            $keyData = getAvailableApiKey($apiKeys, $cacheManager, $attemptedKeys);
            if (!$keyData) break;
            
            $apiKey = $keyData['key'];
            $lockKey = $keyData['lockKey'];
            $attemptedKeys[] = $apiKey;

            if (empty($apiKey)) continue;
            
            // Call OpenAI API via OpenRouter
            $url = "https://openrouter.ai/api/v1/chat/completions";
            
            $data = [
                'model' => $openaiModel,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 7000
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'HTTP-Referer: https://paper.bhattichemicalsindustry.com.pk',
                'X-Title: Ahmad Learning Hub'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Release lock immediately after request is done
            if ($lockKey && $cacheManager) {
                $cacheManager->del($lockKey);
            }
            
            if ($httpCode === 200) {
                // Verify response content
                $responseData = json_decode($response, true);
                if (isset($responseData['choices'][0]['message']['content'])) {
                    $success = true;
                    $keyManager->logUsage($apiKey);
                    break; // Success!
                } else {
                    $lastError = "Invalid response structure with key " . substr($apiKey, 0, 8) . "...: " . substr(json_encode($responseData), 0, 200);
                    $keyManager->logError($apiKey);
                }
            } else {
                $lastError = "HTTP $httpCode with key " . substr($apiKey, 0, 8) . "...: " . substr($response, 0, 200);
                if ($curlError) $lastError .= " Curl error: $curlError";
                $keyManager->logError($apiKey);
            }
        }
        
        if (!$success) {
            error_log("All OpenAI API keys failed. Last error: " . $lastError);
            return [];
        }
        
        $responseData = json_decode($response, true);
        
        if (!isset($responseData['choices'][0]['message']['content'])) {
            error_log("Invalid response from OpenAI API: " . substr(json_encode($responseData), 0, 500));
            return [];
        }
        
        $generatedText = $responseData['choices'][0]['message']['content'];
        
        // Extract JSON from response (in case there's extra text)
        if (preg_match('/\[.*\]/s', $generatedText, $matches)) {
            $jsonText = $matches[0];
        } else {
            $jsonText = $generatedText;
        }
        
        $mcqs = json_decode($jsonText, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($mcqs)) {
            error_log("Failed to parse JSON from OpenAI response. Error: " . json_last_error_msg() . ". Response: " . substr($generatedText, 0, 500));
            return [];
        }
        
        // Schema check moved to install.php
        // ensureAIGeneratedMCQsTable();
        
        $insertedMCQs = [];
        $now = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("INSERT INTO AIGeneratedMCQs (topic, question, option_a, option_b, option_c, option_d, correct_option, generated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            error_log("Failed to prepare insert statement for AIGeneratedMCQs: " . $conn->error);
            return [];
        }
        
        foreach ($mcqs as $mcq) {
            if (!isset($mcq['question']) || !isset($mcq['correct_option'])) {
                continue;
            }
            
            $question = (string)$mcq['question'];
            $optionA = isset($mcq['option_a']) ? (string)$mcq['option_a'] : '';
            $optionB = isset($mcq['option_b']) ? (string)$mcq['option_b'] : '';
            $optionC = isset($mcq['option_c']) ? (string)$mcq['option_c'] : '';
            $optionD = isset($mcq['option_d']) ? (string)$mcq['option_d'] : '';
            $correctText = (string)$mcq['correct_option'];
            $topicValue = (string)$topic;
            
            $stmt->bind_param(
                'ssssssss',
                $topicValue,
                $question,
                $optionA,
                $optionB,
                $optionC,
                $optionD,
                $correctText,
                $now
            );
            
            if ($stmt->execute()) {
                $insertedMCQs[] = [
                    'id' => $stmt->insert_id,
                    'topic' => $topicValue,
                    'question' => $question,
                    'option_a' => $optionA,
                    'option_b' => $optionB,
                    'option_c' => $optionC,
                    'option_d' => $optionD,
                    'correct_option' => $correctText
                ];
            }
        }
        
        $stmt->close();
        
        if ($cacheManager && !empty($insertedMCQs)) {
            $cacheManager->setex($cacheKey, 86400, json_encode($insertedMCQs));
        }
        
        return $insertedMCQs;
        
    } catch (Exception $e) {
        error_log("Error generating MCQs with OpenAI: " . $e->getMessage());
        return [];
    }
}

/**
 * Schema management moved to install.php
 */
/* function ensureAIGeneratedMCQsTable() { ... } */

/**
 * Search for related topics using OpenAI API
 * @param string $searchQuery User's search query
 * @param int $classId Class ID (optional)
 * @param int $bookId Book ID (optional)
 * @return array Array of related topics found
 */
function searchTopicsWithGemini($searchQuery, $classId = 0, $bookId = 0, $questionTypes = []) {
    global $conn, $cacheManager, $keyManager;
    
    // Check cache first (Include types in hash to avoid collision)
    if ($cacheManager) {
        $cacheKey = "ai_topic_suggestions_" . md5($searchQuery . serialize($questionTypes));
        $cached = $cacheManager->get($cacheKey);
        if ($cached !== false) {
            $cachedData = json_decode($cached, true);
            if (is_array($cachedData) && !empty($cachedData)) {
                return $cachedData;
            }
        }
    }
    
    // Get OpenAI API keys (OpenRouter)
    $apiKeys = $keyManager->getActiveKeys();
    
    $openaiModel = EnvLoader::get('OPENAI_MODEL', 'nvidia/nemotron-3-nano-30b-a3b:free');
    
    if (empty($apiKeys)) {
        error_log("OpenAI API keys not found for topic search");
        return [];
    }

    $typeContext = !empty($questionTypes) ? "specifically for questions of type: " . implode(', ', $questionTypes) : "";
    
    // Prepare prompt for OpenAI to search and suggest related educational topics
    $prompt = "Based on current educational standards and curriculum, find exactly 6 related educational topics similar to: \"{$searchQuery}\" {$typeContext}
    
    Please provide a list of exactly 6 related educational topics that students might study. These should be:
    - Educational/academic topics
    - Related to the search query
    - Commonly taught in schools/colleges
    - Suitable for creating various types of questions (MCQs, Short/Long answers)
    - Real and valid educational topics

    Return ONLY a JSON array of topic names, like this:
    [\"Topic 1\", \"Topic 2\", \"Topic 3\", \"Topic 4\", \"Topic 5\", \"Topic 6\"]

    Do not include any explanation, just the JSON array. Make sure the topics are real educational subjects.";

    try {
        $lastError = '';
        $response = '';
        $httpCode = 0;
        $success = false;

        $attemptedKeys = [];
        $maxAttempts = count($apiKeys);
        
        for ($k = 0; $k < $maxAttempts; $k++) {
            $keyData = getAvailableApiKey($apiKeys, $cacheManager, $attemptedKeys);
            if (!$keyData) break;
            
            $apiKey = $keyData['key'];
            $lockKey = $keyData['lockKey'];
            $attemptedKeys[] = $apiKey;

            if (empty($apiKey)) continue;
            
            // Call OpenAI API via OpenRouter
            $url = "https://openrouter.ai/api/v1/chat/completions";
            
            $data = [
                'model' => $openaiModel,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 7000
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'HTTP-Referer: https://paper.bhattichemicalsindustry.com.pk',
                'X-Title: Ahmad Learning Hub'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Release lock immediately after request is done
            if ($lockKey && $cacheManager) {
                $cacheManager->del($lockKey);
            }
            
            if ($httpCode === 200) {
                // Verify response content
                $responseData = json_decode($response, true);
                if (isset($responseData['choices'][0]['message']['content'])) {
                    $success = true;
                    $keyManager->logUsage($apiKey);
                    break; // Success!
                } else {
                    $lastError = "Invalid response structure with key " . substr($apiKey, 0, 8) . "...: " . substr(json_encode($responseData), 0, 200);
                    $keyManager->logError($apiKey);
                }
            } else {
                $lastError = "HTTP $httpCode with key " . substr($apiKey, 0, 8) . "...: " . substr($response, 0, 200);
                if ($curlError) $lastError .= " Curl error: $curlError";
                $keyManager->logError($apiKey);
            }
        }
        
        if (!$success) {
            error_log("All OpenAI API keys failed for topic search. Last error: " . $lastError);
            // Fallback: return the search query itself as a topic
            return [['topic' => $searchQuery, 'class_id' => $classId, 'book_id' => $bookId, 'similarity' => 100.0]];
        }
        
        $responseData = json_decode($response, true);
        
        if (!isset($responseData['choices'][0]['message']['content'])) {
            error_log("Invalid response from OpenAI API for topic search: " . substr(json_encode($responseData), 0, 500));
            // Fallback: return the search query itself
            return [['topic' => $searchQuery, 'class_id' => $classId, 'book_id' => $bookId, 'similarity' => 100.0]];
        }
        
        $generatedText = $responseData['choices'][0]['message']['content'];
        
        // Extract JSON array from response
        if (preg_match('/\[.*\]/s', $generatedText, $matches)) {
            $jsonText = $matches[0];
        } else {
            $jsonText = $generatedText;
        }
        
        $topics = json_decode($jsonText, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($topics)) {
            error_log("Failed to parse topics from OpenAI response. Error: " . json_last_error_msg() . ". Response: " . substr($generatedText, 0, 500));
            // Fallback: return the search query itself
            return [['topic' => $searchQuery, 'class_id' => $classId, 'book_id' => $bookId, 'similarity' => 100.0]];
        }
        
        // Convert to our format
        $results = [];
        foreach ($topics as $topicName) {
            if (is_string($topicName) && !empty(trim($topicName))) {
                $results[] = [
                    'topic' => trim($topicName),
                    'class_id' => $classId > 0 ? $classId : 0,
                    'book_id' => $bookId > 0 ? $bookId : 0,
                    'chapter_id' => 1,
                    'similarity' => 85.0, // High similarity since it's web-searched
                    'web_searched' => true
                ];
            }
        }
        
        // If no valid topics found, return the search query itself
        if (empty($results)) {
            $results[] = [
                'topic' => $searchQuery,
                'class_id' => $classId,
                'book_id' => $bookId,
                'chapter_id' => 1,
                'similarity' => 100.0,
                'web_searched' => true
            ];
        }
        
        // Cache the results
        if ($cacheManager && !empty($results)) {
            $cacheKey = "ai_topic_suggestions_" . md5($searchQuery);
            $cacheManager->setex($cacheKey, 86400, json_encode($results)); // Cache for 24 hours
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Error searching topics with OpenAI: " . $e->getMessage());
        // Fallback: return the search query itself
        return [['topic' => $searchQuery, 'class_id' => $classId, 'book_id' => $bookId, 'similarity' => 100.0]];
    }
}

/**
 * Check and Verify MCQs using OpenAI
 * @param int $limit Number of MCQs to check
 * @param int|null $startId Start ID for range
 * @param int|null $endId End ID for range
 * @param string $sourceTable 'AIGeneratedMCQs' or 'mcqs'
 * @param array|null $specificIds Array of IDs to check specifically (overrides other filters)
 * @return array Result stats
 */
function checkMCQsWithAI($limit = 50, $startId = null, $endId = null, $sourceTable = 'AIGeneratedMCQs', $specificIds = null) {
    global $conn, $cacheManager;

    // Define table-specific configurations
    if ($sourceTable === 'mcqs') {
        $pk = 'mcq_id';
        $verifyTable = 'MCQsVerification';
        $fk = 'mcq_id';
        
        // Ensure Verification Table Exists for standard MCQs
        $createTableSql = "CREATE TABLE IF NOT EXISTS MCQsVerification (
            mcq_id INT PRIMARY KEY,
            verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
            last_checked_at DATETIME,
            suggested_correct_option TEXT,
            original_correct_option TEXT,
            ai_notes TEXT,
            FOREIGN KEY (mcq_id) REFERENCES mcqs(mcq_id) ON DELETE CASCADE
        )";
        
        // Add original_correct_option if not exists
        $colCheck = $conn->query("SHOW COLUMNS FROM MCQsVerification LIKE 'original_correct_option'");
        if ($colCheck && $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE MCQsVerification ADD COLUMN original_correct_option TEXT AFTER suggested_correct_option");
        }

    } else {
        // Default: AIGeneratedMCQs
        $pk = 'id';
        $verifyTable = 'AIMCQsVerification';
        $fk = 'mcq_id';
        
        // Ensure Verification Table Exists for AI MCQs
        $createTableSql = "CREATE TABLE IF NOT EXISTS AIMCQsVerification (
            mcq_id INT PRIMARY KEY,
            verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
            last_checked_at DATETIME,
            suggested_correct_option TEXT,
            original_correct_option TEXT,
            ai_notes TEXT,
            FOREIGN KEY (mcq_id) REFERENCES AIGeneratedMCQs(id) ON DELETE CASCADE
        )";

        // Add original_correct_option if not exists
        $colCheck = $conn->query("SHOW COLUMNS FROM AIMCQsVerification LIKE 'original_correct_option'");
        if ($colCheck && $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE AIMCQsVerification ADD COLUMN original_correct_option TEXT AFTER suggested_correct_option");
        }
    }
    
    $conn->query($createTableSql);

    // 2. Select MCQs
    // Join with verification table to find those that are NOT checked (or pending)
    $sql = "SELECT m.$pk as id, m.topic, m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option 
            FROM $sourceTable m 
            LEFT JOIN $verifyTable v ON m.$pk = v.$fk
            WHERE ";
    
    $params = [];
    $types = "";

    if (!empty($specificIds) && is_array($specificIds)) {
        // Specific IDs check
        $idsStr = implode(',', array_map('intval', $specificIds));
        $sql .= "m.$pk IN ($idsStr)";
        // No bind params needed for IN clause with intval sanitization, but let's be consistent if we want prepared
        // For simplicity with variable list length, direct injection of intval'd IDs is safe and easier
    } elseif ($startId !== null && $endId !== null) {
        $sql .= "m.$pk BETWEEN ? AND ?";
        $params[] = $startId;
        $params[] = $endId;
        $types .= "ii";
    } else {
        // Check if v.mcq_id IS NULL (never checked) OR v.verification_status = 'pending'
        $sql .= "(v.$fk IS NULL OR v.verification_status = 'pending') LIMIT ?";
        $params[] = $limit;
        $types .= "i";
    }

    $stmt = $conn->prepare($sql);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $mcqs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($mcqs)) {
        return ['success' => false, 'message' => 'No MCQs found to check.'];
    }

    // 3. Process in batches
    $batchSize = 10; // Send 10 at a time to AI
    $batches = array_chunk($mcqs, $batchSize);
    
    $stats = [
        'checked' => 0,
        'verified' => 0,
        'corrected' => 0,
        'flagged' => 0,
        'errors' => 0,
        'processed_ids' => []
    ];

    // Get API Keys
    $openaiApiKeys = EnvLoader::get('OPENAI_API_KEYS', '');
    if (empty($openaiApiKeys)) $openaiApiKeys = EnvLoader::get('OPENAI_API_KEY', '');
    $apiKeys = !empty($openaiApiKeys) ? array_map('trim', explode(',', $openaiApiKeys)) : [];
    $openaiModel = EnvLoader::get('OPENAI_MODEL', 'nvidia/nemotron-3-nano-30b-a3b:free');

    if (empty($apiKeys)) {
        return ['success' => false, 'message' => 'No API keys available.'];
    }

    foreach ($batches as $batch) {
        $mcqData = [];
        foreach ($batch as $m) {
            $mcqData[] = [
                'id' => $m['id'],
                'question' => $m['question'],
                'options' => [
                    'A' => $m['option_a'],
                    'B' => $m['option_b'],
                    'C' => $m['option_c'],
                    'D' => $m['option_d']
                ],
                'current_correct' => $m['correct_option']
            ];
        }

        $prompt = "You are a strict academic quality assurance system.
Verify the following Multiple Choice Questions.

For each MCQ:
1. Check if the question and options are valid/make sense. If not, status = 'flagged'.
2. Identify the correct option (A, B, C, or D).
3. Compare with 'current_correct'. 
   - If your identified correct option matches 'current_correct' (by text content), status = 'verified'.
   - If different, status = 'corrected' and provide the EXACT text of the correct option.

Input JSON:
" . json_encode($mcqData) . "

Output JSON format (Array of objects):
[
    {
        \"id\": 123,
        \"status\": \"verified\" | \"corrected\" | \"flagged\",
        \"correct_option\": \"Option Text\" (ONLY if corrected),
        \"reason\": \"Brief reason\"
    }
]
Return ONLY the JSON array.";

        // --- Call API (Embedded Logic) ---
        $success = false;
        $responseContent = '';
        $attemptedKeys = [];
        
        for ($k = 0; $k < count($apiKeys); $k++) {
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
                'temperature' => 0.1 // Low temp for deterministic verification
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'HTTP-Referer: https://paper.bhattichemicalsindustry.com.pk',
                'X-Title: Ahmad Learning Hub'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($lockKey && $cacheManager) $cacheManager->del($lockKey);

            if ($httpCode === 200) {
                $decoded = json_decode($response, true);
                if (isset($decoded['choices'][0]['message']['content'])) {
                    $responseContent = $decoded['choices'][0]['message']['content'];
                    $success = true;
                    break;
                }
            }
        }
        // ---------------------------------

        if ($success) {
            // Parse JSON
            if (preg_match('/\\[.*\\]/s', $responseContent, $matches)) {
                $jsonText = $matches[0];
            } else {
                $jsonText = $responseContent;
            }
            $results = json_decode($jsonText, true);

            if (is_array($results)) {
                foreach ($results as $res) {
                    if (!isset($res['id']) || !isset($res['status'])) continue;
                    
                    $id = intval($res['id']);
                    $status = $res['status'];
                    $now = date('Y-m-d H:i:s');
                    $reason = $conn->real_escape_string($res['reason'] ?? '');
                    $suggestedCorrect = '';
                    
                    if ($status === 'verified') {
                        $sql = "INSERT INTO $verifyTable ($fk, verification_status, last_checked_at, ai_notes) 
                                VALUES ($id, 'verified', '$now', '$reason')
                                ON DUPLICATE KEY UPDATE verification_status='verified', last_checked_at='$now', ai_notes='$reason'";
                        $conn->query($sql);
                        $stats['verified']++;
                    } elseif ($status === 'corrected' && !empty($res['correct_option'])) {
                        $suggestedCorrect = $conn->real_escape_string($res['correct_option']);
                        
                        // Capture original correct option before update
                        $originalCorrect = '';
                        $origQ = $conn->query("SELECT correct_option FROM $sourceTable WHERE $pk = $id");
                        if ($origQ && $row = $origQ->fetch_assoc()) {
                            $originalCorrect = $conn->real_escape_string($row['correct_option']);
                        }

                        // Update the main table with the correction
                        $conn->query("UPDATE $sourceTable SET correct_option = '$suggestedCorrect' WHERE $pk = $id");
                        
                        // Log in verification table
                        $sql = "INSERT INTO $verifyTable ($fk, verification_status, last_checked_at, suggested_correct_option, original_correct_option, ai_notes) 
                                VALUES ($id, 'corrected', '$now', '$suggestedCorrect', '$originalCorrect', '$reason')
                                ON DUPLICATE KEY UPDATE verification_status='corrected', last_checked_at='$now', suggested_correct_option='$suggestedCorrect', original_correct_option='$originalCorrect', ai_notes='$reason'";
                        $conn->query($sql);
                        
                        $stats['corrected']++;
                    } elseif ($status === 'flagged') {
                        $sql = "INSERT INTO $verifyTable ($fk, verification_status, last_checked_at, ai_notes) 
                                VALUES ($id, 'flagged', '$now', '$reason')
                                ON DUPLICATE KEY UPDATE verification_status='flagged', last_checked_at='$now', ai_notes='$reason'";
                        $conn->query($sql);
                        $stats['flagged']++;
                    }
                    $stats['checked']++;
                    $stats['processed_ids'][] = $id;
                }
            }
        } else {
            $stats['errors']++;
        }
        
        // Small delay between batches
        sleep(1);
    }

    return ['success' => true, 'stats' => $stats];
}

