<?php
/**
 * Test File for MCQ Generation with OpenAI API (via OpenRouter)
 * This file tests if the OpenAI API is working correctly
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../services/AIKeyRotator.php';
require_once __DIR__ . '/mcq_generator.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$cacheManager = null;
if (file_exists(__DIR__ . '/../services/CacheManager.php')) {
    require_once __DIR__ . '/../services/CacheManager.php';
    try { $cacheManager = new CacheManager(); } catch (Exception $e) {}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCQ Generation Test - Ahmad Learning Hub</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #6366f1;
            padding-bottom: 10px;
        }
        h2 {
            color: #6366f1;
            margin-top: 30px;
        }
        .test-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #6366f1;
        }
        .success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        .mcq-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .mcq-question {
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .mcq-option {
            padding: 5px 10px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 3px;
        }
        .mcq-correct {
            background: #d4edda;
            border-left: 3px solid #28a745;
        }
        button {
            background: #6366f1;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover {
            background: #4f46e5;
        }
        .form-group {
            margin: 15px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🧪 MCQ Generation Test</h1>
        <p>This page tests if the OpenAI API (via OpenRouter) is working correctly for generating MCQs.</p>

        <?php
        // Test 1: Check API Key
        echo '<div class="test-section">';
        echo '<h2>Test 1: API Key Configuration</h2>';
        
        $rotator = new AIKeyRotator($cacheManager);
        $allKeys = $rotator->getAllKeys();
        $nextKey = $rotator->getNextKey();
        $model = EnvLoader::get('AI_DEFAULT_MODEL', 'liquid/lfm-2.5-1.2b-thinking:free');
        
        if (!empty($allKeys)) {
             echo '<div class="success">✓ Found ' . count($allKeys) . ' API Keys in rotation (AIKeyRotator - Account 2 → Primary → Account 3)</div>';
             
             foreach ($allKeys as $i => $item) {
                 $k = $item['key'] ?? '';
                 if (empty($k)) continue;
                 $account = $item['account'] ?? 'Unknown';
                 $isNext = ($nextKey && ($nextKey['key'] ?? '') === $k);
                 $exhausted = $rotator->isKeyExhausted($k);
                 $status = $exhausted ? ' <span style="color: #dc3545;">(Exhausted)</span>' : ($isNext ? ' <span style="color: #28a745; font-weight: bold;">(Next Request)</span>' : '');
                 $style = $exhausted ? 'opacity: 0.7;' : ($isNext ? 'border: 2px solid #28a745; background-color: #f0fff4;' : '');
                 
                 echo '<div class="info" style="' . $style . '">Key ' . ($i+1) . ' [' . htmlspecialchars($account) . ']: ' . substr($k, 0, 10) . '...' . substr($k, -5) . $status . '</div>';
             }
        } else {
            $hasKey1 = EnvLoader::get('KEY_1', '');
            $hasKey2 = EnvLoader::get('KEY_2', '');
            if (!empty($hasKey1) || !empty($hasKey2)) {
                echo '<div class="warning">⚠ AIKeyRotator found no keys. Check ACCOUNT_2_KEYS_START/END and PRIMARY_KEYS_START/END in .env.local.</div>';
            } else {
                echo '<div class="error">✗ API Key not found! Add KEY_1, KEY_2, etc. to config/.env.local</div>';
            }
        }
        echo '<div class="info">Model: <strong>' . htmlspecialchars($model) . '</strong></div>';
        echo '</div>';

        // Test 2: Database Connection
        echo '<div class="test-section">';
        echo '<h2>Test 2: Database Connection</h2>';
        
        if ($conn && !$conn->connect_error) {
            echo '<div class="success">✓ Database connected successfully</div>';
            
            // Check if AIGeneratedMCQs table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'AIGeneratedMCQs'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                echo '<div class="success">✓ AIGeneratedMCQs table exists</div>';
            } else {
                echo '<div class="warning">⚠ AIGeneratedMCQs table does not exist (will be created automatically)</div>';
            }
            
            // Check if mcqs table exists
            $mcqsTableCheck = $conn->query("SHOW TABLES LIKE 'mcqs'");
            if ($mcqsTableCheck && $mcqsTableCheck->num_rows > 0) {
                echo '<div class="success">✓ mcqs table exists</div>';
            } else {
                echo '<div class="error">✗ mcqs table does not exist!</div>';
            }
        } else {
            echo '<div class="error">✗ Database connection failed: ' . ($conn->connect_error ?? 'Unknown error') . '</div>';
        }
        echo '</div>';

        // Test 3: Test MCQ Generation
        echo '<div class="test-section">';
        echo '<h2>Test 3: MCQ Generation Test</h2>';
        
        // Enable debug for MCQ generation
        if (!defined('DEBUG_MCQ_GEN')) define('DEBUG_MCQ_GEN', true);
        
        if (isset($_POST['test_generate'])) {
            $testTopic = $_POST['test_topic'] ?? 'Mathematics';
            $testCount = intval($_POST['test_count'] ?? 3);
            
            echo '<div class="info">Testing generation for topic: <strong>' . htmlspecialchars($testTopic) . '</strong></div>';
            echo '<div class="info">Requested count: <strong>' . $testCount . '</strong></div>';
            
            echo '<div class="info">⏳ Generating MCQs... This may take a few seconds...</div>';
            
            $startTime = microtime(true);
            $generatedMCQs = generateMCQsWithGemini($testTopic, $testCount);
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            if (!empty($generatedMCQs)) {
                echo '<div class="success">✓ Successfully generated ' . count($generatedMCQs) . ' MCQs in ' . $duration . ' seconds</div>';
                
                // Display generated MCQs
                echo '<h3>Generated MCQs:</h3>';
                    foreach ($generatedMCQs as $index => $mcq) {
                        echo '<div class="mcq-item">';
                        echo '<div class="mcq-question">Q' . ($index + 1) . ': ' . htmlspecialchars($mcq['question']) . '</div>';
                        echo '<div class="mcq-option">A) ' . htmlspecialchars($mcq['option_a'] ?? '') . '</div>';
                        echo '<div class="mcq-option">B) ' . htmlspecialchars($mcq['option_b'] ?? '') . '</div>';
                        echo '<div class="mcq-option">C) ' . htmlspecialchars($mcq['option_c'] ?? '') . '</div>';
                        echo '<div class="mcq-option">D) ' . htmlspecialchars($mcq['option_d'] ?? '') . '</div>';
                        echo '<div class="mcq-option mcq-correct">✓ Correct Answer: ' . htmlspecialchars($mcq['correct_option'] ?? '') . '</div>';
                        echo '<div style="margin-top: 10px; font-size: 12px; color: #666;">ID: ' . ($mcq['id'] ?? $mcq['mcq_id'] ?? 'N/A') . '</div>';
                        echo '</div>';
                    }
                    
                    // Check if stored in AIGeneratedMCQs table
                    $firstId = $generatedMCQs[0]['id'] ?? $generatedMCQs[0]['mcq_id'] ?? null;
                    
                    if ($firstId) {
                        // Check both id and mcq_id columns just in case
                        $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM AIGeneratedMCQs WHERE id = ?");
                        if (!$checkStmt) {
                             // Fallback if id column doesn't exist (unlikely with new schema)
                             $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM AIGeneratedMCQs WHERE mcq_id = ?");
                        }
                        
                        if ($checkStmt) {
                            $checkStmt->bind_param('i', $firstId);
                            $checkStmt->execute();
                            $checkResult = $checkStmt->get_result();
                            if ($checkRow = $checkResult->fetch_assoc()) {
                                if ($checkRow['cnt'] > 0) {
                                    echo '<div class="success">✓ MCQs stored in AIGeneratedMCQs table (ID: ' . $firstId . ')</div>';
                                } else {
                                    echo '<div class="warning">⚠ MCQs not found in AIGeneratedMCQs table (ID: ' . $firstId . ')</div>';
                                }
                            }
                            $checkStmt->close();
                        }
                    }
                    
                    // Show raw JSON for debugging
                    echo '<details style="margin-top: 20px;">';
                    echo '<summary style="cursor: pointer; font-weight: bold; color: #6366f1;">View Raw JSON Response</summary>';
                    echo '<pre>' . htmlspecialchars(json_encode($generatedMCQs, JSON_PRETTY_PRINT)) . '</pre>';
                    echo '</details>';
                    
                } else {
                    echo '<div class="error">✗ Failed to generate MCQs. Check error logs for details.</div>';
                    echo '<div class="info">Common issues:</div>';
                    echo '<ul>';
                    echo '<li>API key is invalid or expired</li>';
                    echo '<li>API quota exceeded</li>';
                    echo '<li>Network connectivity issues</li>';
                    echo '<li>Invalid topic or parameters</li>';
                    echo '</ul>';
                }
            }
         else {
            // Get available classes and books for testing
            $classes = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC LIMIT 10");
            $books = $conn->query("SELECT book_id, book_name, class_id FROM book ORDER BY book_id ASC LIMIT 10");
            
            echo '<form method="POST" action="">';
            echo '<div class="form-group">';
            echo '<label for="test_topic">Test Topic:</label>';
            echo '<input type="text" id="test_topic" name="test_topic" value="Mathematics" required>';
            echo '</div>';
            
            echo '<div class="form-group">';
            echo '<label for="test_count">Number of MCQs to Generate:</label>';
            echo '<input type="number" id="test_count" name="test_count" value="3" min="1" max="10" required>';
            echo '</div>';
            
            echo '<button type="submit" name="test_generate">🚀 Test MCQ Generation</button>';
            echo '</form>';
        }
        echo '</div>';

        // Test 4: Check Cache
        echo '<div class="test-section">';
        echo '<h2>Test 4: Cache System</h2>';
        
        if (file_exists(__DIR__ . '/../services/CacheManager.php')) {
            echo '<div class="success">✓ CacheManager.php exists</div>';
            try {
                require_once __DIR__ . '/../services/CacheManager.php';
                $cacheManager = new CacheManager();
                echo '<div class="success">✓ CacheManager initialized successfully</div>';
            } catch (Exception $e) {
                echo '<div class="warning">⚠ CacheManager initialization failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            echo '<div class="info">ℹ CacheManager.php not found (using file-based fallback)</div>';
        }
        echo '</div>';

        // Test 5: API Connection Test
        echo '<div class="test-section">';
        echo '<h2>Test 5: Direct API Connection Test</h2>';
        
        if (isset($_POST['test_api_connection'])) {
            $rotator = new AIKeyRotator($cacheManager);
            $keyItem = $rotator->getNextKey();
            
            $apiKey = '';
            if ($keyItem && !empty($keyItem['key'])) {
                $apiKey = $keyItem['key'];
            }
            
            // Check manual input
            if (empty($apiKey) && !empty($_POST['manual_api_key'])) {
                $apiKey = trim($_POST['manual_api_key']);
                echo '<div class="info">Using manually provided API Key</div>';
            }
            
            $model = EnvLoader::get('AI_DEFAULT_MODEL', 'liquid/lfm-2.5-1.2b-thinking:free');
            
            if (!empty($apiKey)) {
                echo '<div class="info">Testing API connection...</div>';
                echo '<div class="info">Using model: <strong>' . htmlspecialchars($model) . '</strong></div>';
                
                $url = "https://openrouter.ai/api/v1/chat/completions";
                
                $data = [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'Say "Hello, API is working!" if you can read this.'
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                    // 'provider' => ['order' => ['Hypbolic'], 'allow_fallbacks' => false] // Optional: Force provider if needed
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
                    $responseData = json_decode($response, true);
                    if (isset($responseData['choices'][0]['message']['content'])) {
                        $apiResponse = $responseData['choices'][0]['message']['content'] ?? '';
                        
                        if (!empty($apiResponse)) {
                             echo '<div class="success">✓ API Connection Successful!</div>';
                             echo '<div class="info">API Response: ' . htmlspecialchars($apiResponse) . '</div>';
                        } else {
                             echo '<div class="warning">⚠ API Connection Successful (200 OK), but returned empty content. This might be due to the model not producing output.</div>';
                        }

                        // Always show raw response for debugging
                        echo '<details open>';
                        echo '<summary style="cursor: pointer; color: #6366f1; font-weight: bold;">View Raw API Response (Debug)</summary>';
                        echo '<pre>' . htmlspecialchars($response) . '</pre>';
                        echo '</details>';

                    } else {
                        echo '<div class="error">✗ API returned unexpected response format</div>';
                        echo '<pre>' . htmlspecialchars(json_encode($responseData, JSON_PRETTY_PRINT)) . '</pre>';
                        
                        echo '<details open>';
                        echo '<summary style="cursor: pointer; color: #6366f1; font-weight: bold;">View Raw API Response (Debug)</summary>';
                        echo '<pre>' . htmlspecialchars($response) . '</pre>';
                        echo '</details>';
                    }
                } else {
                    echo '<div class="error">✗ API Connection Failed</div>';
                    echo '<div class="error">HTTP Code: ' . $httpCode . '</div>';
                    if ($curlError) {
                        echo '<div class="error">cURL Error: ' . htmlspecialchars($curlError) . '</div>';
                    }
                    echo '<div class="info">Response: ' . htmlspecialchars(substr($response, 0, 500)) . '</div>';
                }
            } else {
                echo '<div class="error">✗ API Key not configured</div>';
            }
        } else {
            echo '<form method="POST" action="">';
            echo '<div class="form-group">';
            echo '<label for="manual_api_key">Optional: Manually enter API Key (if env fails)</label>';
            echo '<input type="password" id="manual_api_key" name="manual_api_key" placeholder="sk-or-v1-...">';
            echo '</div>';
            echo '<button type="submit" name="test_api_connection">🔌 Test API Connection</button>';
            echo '</form>';
        }
        echo '</div>';

        // Test 6: Database Statistics
        echo '<div class="test-section">';
        echo '<h2>Test 6: Database Statistics</h2>';
        
        // Count total MCQs
        $totalMcqs = $conn->query("SELECT COUNT(*) as cnt FROM mcqs")->fetch_assoc()['cnt'];
        echo '<div class="info">Total MCQs in database: <strong>' . $totalMcqs . '</strong></div>';
        
        // Count AI-generated MCQs
        $aiMcqs = $conn->query("SELECT COUNT(*) as cnt FROM AIGeneratedMCQs")->fetch_assoc()['cnt'];
        echo '<div class="info">AI-Generated MCQs: <strong>' . $aiMcqs . '</strong></div>';
        
        // Count distinct topics
        $topics = $conn->query("SELECT COUNT(DISTINCT topic) as cnt FROM mcqs WHERE topic IS NOT NULL AND topic != ''")->fetch_assoc()['cnt'];
        echo '<div class="info">Distinct Topics: <strong>' . $topics . '</strong></div>';
        
        // Recent AI-generated MCQs
        $recentAi = $conn->query("SELECT COUNT(*) as cnt FROM AIGeneratedMCQs WHERE generated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetch_assoc()['cnt'];
        echo '<div class="info">AI-Generated MCQs (Last 24 hours): <strong>' . $recentAi . '</strong></div>';
        
        echo '</div>';
        ?>

        <div class="test-section">
            <h2>📝 Notes</h2>
            <ul>
                <li>This test page helps verify that the OpenAI API (via OpenRouter) integration is working correctly</li>
                <li>If generation fails, check the error logs in your server</li>
                <li>Make sure your API key has sufficient quota/credits</li>
                <li>Generated MCQs are stored in both <code>mcqs</code> and <code>AIGeneratedMCQs</code> tables</li>
                <li>You can delete this test file after verification</li>
            </ul>
        </div>
    </div>
</body>
</html>
