<?php
/**
 * MCQ Generator Helper
 * Automatically generates MCQs using OpenAI API (via OpenRouter)
 * Stores in AIGeneratedMCQs table and cache
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';

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
 * Generate MCQs using OpenAI API (via OpenRouter)
 * @param string $topic Topic name
 * @param int $count Number of MCQs to generate
 * @return array Generated MCQs
 */
function generateMCQsWithGemini($topic, $count) {
    global $conn, $cacheManager;
    
    // Check cache first
    if ($cacheManager) {
        $cacheKey = "ai_mcqs_" . md5($topic . "_" . $count);
        $cached = $cacheManager->get($cacheKey);
        if ($cached !== false) {
            $cachedData = json_decode($cached, true);
            if (is_array($cachedData) && count($cachedData) >= $count) {
                return array_slice($cachedData, 0, $count);
            }
        }
    }
    
    // Get OpenAI API keys (OpenRouter)
    $openaiApiKeys = EnvLoader::get('OPENAI_API_KEYS', '');
    if (empty($openaiApiKeys)) {
        // Fallback to single key if list not present
        $openaiApiKeys = EnvLoader::get('OPENAI_API_KEY', '');
    }
    
    $apiKeys = [];
    if (!empty($openaiApiKeys)) {
        $apiKeys = array_map('trim', explode(',', $openaiApiKeys));
    }
    
    $openaiModel = EnvLoader::get('OPENAI_MODEL', 'nvidia/nemotron-3-nano-30b-a3b:free');
    
    if (empty($apiKeys)) {
        error_log("OpenAI API keys not found");
        return [];
    }
    
    // Prepare prompt for OpenAI
    $prompt = "Generate {$count} multiple choice questions (MCQs) on the topic: {$topic}. 
    
For each MCQ, provide:
1. A clear and concise question
2. Four options labeled A, B, C, and D
3. The correct answer (specify which option is correct)

Format the response as JSON array with this structure:
[
  {
    \"question\": \"Question text here\",
    \"option_a\": \"Option A text\",
    \"option_b\": \"Option B text\",
    \"option_c\": \"Option C text\",
    \"option_d\": \"Option D text\",
    \"correct_option\": \"The full text of the correct answer\"
  }
]

Make sure the questions are educational, clear, and appropriate for students. Return ONLY the JSON array, no additional text.";

    try {
        $lastError = '';
        $response = '';
        $httpCode = 0;
        $success = false;

        foreach ($apiKeys as $apiKey) {
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
                'max_tokens' => 4000
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
            
            if ($httpCode === 200) {
                // Verify response content
                $responseData = json_decode($response, true);
                if (isset($responseData['choices'][0]['message']['content'])) {
                    $success = true;
                    break; // Success!
                } else {
                    $lastError = "Invalid response structure with key " . substr($apiKey, 0, 8) . "...: " . substr(json_encode($responseData), 0, 200);
                }
            } else {
                $lastError = "HTTP $httpCode with key " . substr($apiKey, 0, 8) . "...: " . substr($response, 0, 200);
                if ($curlError) $lastError .= " Curl error: $curlError";
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
        
        // Store in AIGeneratedMCQs table and regular mcqs table
        $insertedMCQs = [];
        $topicEsc = $conn->real_escape_string($topic);
        
        // Default values for mcqs table since we removed class/book dependency
        $classId = 0;
        $bookId = 0;
        $chapterId = 0;
        
        foreach ($mcqs as $mcq) {
            if (!isset($mcq['question']) || !isset($mcq['correct_option'])) {
                continue; // Skip invalid MCQs
            }
            
            $question = $conn->real_escape_string($mcq['question']);
            $optionA = $conn->real_escape_string($mcq['option_a'] ?? '');
            $optionB = $conn->real_escape_string($mcq['option_b'] ?? '');
            $optionC = $conn->real_escape_string($mcq['option_c'] ?? '');
            $optionD = $conn->real_escape_string($mcq['option_d'] ?? '');
            $correctOption = $conn->real_escape_string($mcq['correct_option']);
            
            // Insert into AIGeneratedMCQs table only
            $aiSql = "INSERT INTO AIGeneratedMCQs (topic, question, option_a, option_b, option_c, option_d, correct_option, generated_at) 
                     VALUES ('$topicEsc', '$question', '$optionA', '$optionB', '$optionC', '$optionD', '$correctOption', NOW())";
            
            if ($conn->query($aiSql)) {
                $generatedId = $conn->insert_id;
                
                $insertedMCQs[] = [
                    'id' => $generatedId,
                    'question' => $mcq['question'],
                    'option_a' => $mcq['option_a'] ?? '',
                    'option_b' => $mcq['option_b'] ?? '',
                    'option_c' => $mcq['option_c'] ?? '',
                    'option_d' => $mcq['option_d'] ?? '',
                    'correct_option' => $mcq['correct_option']
                ];
            }
        }
        
        // Cache the generated MCQs
        if ($cacheManager && !empty($insertedMCQs)) {
            $cacheKey = "ai_mcqs_" . md5($topic . "_" . $count);
            $cacheManager->setex($cacheKey, 86400, json_encode($insertedMCQs)); // Cache for 24 hours
        }
        
        return $insertedMCQs;
        
    } catch (Exception $e) {
        error_log("Error generating MCQs with OpenAI: " . $e->getMessage());
        return [];
    }
}

/**
 * Ensure AIGeneratedMCQs table exists
 */
function ensureAIGeneratedMCQsTable() {
    global $conn;
    
    $conn->query("CREATE TABLE IF NOT EXISTS `AIGeneratedMCQs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `topic` varchar(255) NOT NULL,
        `question` text NOT NULL,
        `option_a` varchar(255) NOT NULL,
        `option_b` varchar(255) NOT NULL,
        `option_c` varchar(255) NOT NULL,
        `option_d` varchar(255) NOT NULL,
        `correct_option` varchar(255) DEFAULT NULL,
        `generated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `topic` (`topic`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Remove class_id, book_id, chapter_id, mcq_id columns if they exist
    $columnsToRemove = ['class_id', 'book_id', 'chapter_id', 'mcq_id'];
    foreach ($columnsToRemove as $col) {
        $check = $conn->query("SHOW COLUMNS FROM AIGeneratedMCQs LIKE '$col'");
        if ($check && $check->num_rows > 0) {
            // Drop foreign key if it exists for mcq_id
            if ($col === 'mcq_id') {
                // Get constraint name
                $fkCheck = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'AIGeneratedMCQs' AND COLUMN_NAME = 'mcq_id' AND REFERENCED_TABLE_NAME = 'mcqs' LIMIT 1");
                if ($fkCheck && $row = $fkCheck->fetch_assoc()) {
                    $fkName = $row['CONSTRAINT_NAME'];
                    $conn->query("ALTER TABLE AIGeneratedMCQs DROP FOREIGN KEY `$fkName`");
                }
            }
            $conn->query("ALTER TABLE AIGeneratedMCQs DROP COLUMN $col");
        }
    }

    // Check for missing columns (migration for existing table)
    $columns = ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option'];
    foreach ($columns as $col) {
        $check = $conn->query("SHOW COLUMNS FROM AIGeneratedMCQs LIKE '$col'");
        if ($check && $check->num_rows == 0) {
            $type = ($col === 'question') ? 'TEXT NOT NULL' : 'VARCHAR(255) NOT NULL';
            if ($col === 'correct_option') $type = 'VARCHAR(255) DEFAULT NULL';
            $conn->query("ALTER TABLE AIGeneratedMCQs ADD COLUMN $col $type");
        }
    }
}

/**
 * Search for related topics using OpenAI API
 * @param string $searchQuery User's search query
 * @param int $classId Class ID (optional)
 * @param int $bookId Book ID (optional)
 * @return array Array of related topics found
 */
function searchTopicsWithGemini($searchQuery, $classId = 0, $bookId = 0) {
    global $conn, $cacheManager;
    
    // Check cache first
    if ($cacheManager) {
        $cacheKey = "topic_search_" . md5($searchQuery);
        $cached = $cacheManager->get($cacheKey);
        if ($cached !== false) {
            $cachedData = json_decode($cached, true);
            if (is_array($cachedData) && !empty($cachedData)) {
                return $cachedData;
            }
        }
    }
    
    // Get OpenAI API keys (OpenRouter)
    $openaiApiKeys = EnvLoader::get('OPENAI_API_KEYS', '');
    if (empty($openaiApiKeys)) {
        // Fallback to single key if list not present
        $openaiApiKeys = EnvLoader::get('OPENAI_API_KEY', '');
    }
    
    $apiKeys = [];
    if (!empty($openaiApiKeys)) {
        $apiKeys = array_map('trim', explode(',', $openaiApiKeys));
    }
    
    $openaiModel = EnvLoader::get('OPENAI_MODEL', 'nvidia/nemotron-3-nano-30b-a3b:free');
    
    if (empty($apiKeys)) {
        error_log("OpenAI API keys not found for topic search");
        return [];
    }
    
    // Prepare prompt for OpenAI to search and suggest related educational topics
    $prompt = "Based on current educational standards and curriculum, find related educational topics similar to: \"{$searchQuery}\"
    
Please provide a list of 5-10 related educational topics that students might study. These should be:
- Educational/academic topics
- Related to the search query
- Commonly taught in schools/colleges
- Suitable for creating multiple choice questions
- Real and valid educational topics

Return ONLY a JSON array of topic names, like this:
[\"Topic 1\", \"Topic 2\", \"Topic 3\"]

Do not include any explanation, just the JSON array. Make sure the topics are real educational subjects.";

    try {
        $lastError = '';
        $response = '';
        $httpCode = 0;
        $success = false;

        foreach ($apiKeys as $apiKey) {
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
                'max_tokens' => 2000
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
            
            if ($httpCode === 200) {
                // Verify response content
                $responseData = json_decode($response, true);
                if (isset($responseData['choices'][0]['message']['content'])) {
                    $success = true;
                    break; // Success!
                } else {
                    $lastError = "Invalid response structure with key " . substr($apiKey, 0, 8) . "...: " . substr(json_encode($responseData), 0, 200);
                }
            } else {
                $lastError = "HTTP $httpCode with key " . substr($apiKey, 0, 8) . "...: " . substr($response, 0, 200);
                if ($curlError) $lastError .= " Curl error: $curlError";
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
            $cacheKey = "topic_search_" . md5($searchQuery);
            $cacheManager->setex($cacheKey, 86400, json_encode($results)); // Cache for 24 hours
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Error searching topics with OpenAI: " . $e->getMessage());
        // Fallback: return the search query itself
        return [['topic' => $searchQuery, 'class_id' => $classId, 'book_id' => $bookId, 'similarity' => 100.0]];
    }
}

// Ensure table exists when this file is included
ensureAIGeneratedMCQsTable();
