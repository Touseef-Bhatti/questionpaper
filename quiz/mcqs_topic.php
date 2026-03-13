<?php
// mcqs_topic.php - Topic search page for MCQs
if (session_status() === PHP_SESSION_NONE) session_start();

include '../db_connect.php';
require_once 'mcq_generator.php';
require_once 'MongoSearchLogger.php';
require_once '../services/SubscriptionService.php';

// Check user plan status
$subService = new SubscriptionService($conn);
$userPlan = 'free';
$topicLimit = 3; // Default for free
if (isset($_SESSION['user_id'])) {
    $currentSub = $subService->getCurrentSubscription($_SESSION['user_id']);
    $userPlan = $currentSub['plan_name'] ?? 'free';
    
    // Check if limit is explicitly defined in features or standard columns
    if (isset($currentSub['max_topics_per_quiz'])) {
        $topicLimit = intval($currentSub['max_topics_per_quiz']);
    } else {
        $topicLimit = ($userPlan === 'free') ? 3 : -1; // -1 for unlimited
    }
}
$isPremium = ($userPlan !== 'free');

function calculateSimilarity($str1, $str2) {
    $str1 = mb_strtolower(trim($str1)); $str2 = mb_strtolower(trim($str2));
    if (empty($str1) || empty($str2)) return 0;
    similar_text($str1, $str2, $percent);
    if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false) $percent = max($percent, 70);
    return $percent;
}

// Handle AJAX load more topics
if (isset($_POST['action']) && $_POST['action'] === 'load_more_topics') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $searchQuery = $_POST['search_query'] ?? '';
    
    if (!empty($searchQuery)) {
        try {
            $mongoLogger = new MongoSearchLogger();
            $mongoLogger->logSearch($searchQuery, 'ajax_load_more');

            $existingTopics = [];
            
            // 1. Search in mcqs (Fetch all and filter by similarity)
            $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE topic IS NOT NULL AND topic != ''");
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    if (!empty($row['topic'])) {
                        $sim = calculateSimilarity($searchQuery, $row['topic']);
                        if ($sim >= 50) {
                            $isDuplicate = false;
                            foreach ($existingTopics as $existing) {
                                if (strcasecmp($existing['topic'], $row['topic']) === 0) {
                                    $isDuplicate = true; break;
                                }
                            }
                            if (!$isDuplicate) {
                                $existingTopics[] = ['topic' => $row['topic'], 'similarity' => $sim, 'source' => 'mcqs'];
                            }
                        }
                    }
                }
                $stmt->close();
            }

            // 2. Search in AIGeneratedMCQs
            $aiMcqTopics = [];
            $aiStmt = $conn->prepare("SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic IS NOT NULL AND topic != ''");
            if ($aiStmt) {
                $aiStmt->execute();
                $result = $aiStmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    if (!empty($row['topic'])) {
                        $sim = calculateSimilarity($searchQuery, $row['topic']);
                        if ($sim >= 50) {
                            $exists = false;
                            foreach ($existingTopics as $existing) {
                                if (strcasecmp($existing['topic'], $row['topic']) === 0) {
                                    $exists = true; break;
                                }
                            }
                            if (!$exists && !in_array($row['topic'], array_column($aiMcqTopics, 'topic'))) {
                                $aiMcqTopics[] = ['topic' => $row['topic'], 'similarity' => $sim, 'source' => 'ai_generated_mcqs'];
                            }
                        }
                    }
                }
                $aiStmt->close();
            }

            // 3. Search in generated_topics (by topic_name OR source_term)
            $genTopics = [];
            $termLike = "%$searchQuery%";
            $genStmt = $conn->prepare("SELECT DISTINCT topic_name, source_term FROM generated_topics WHERE topic_name LIKE ? OR source_term LIKE ? LIMIT 100");
            if ($genStmt) {
                $genStmt->bind_param('ss', $termLike, $termLike);
                $genStmt->execute();
                $result = $genStmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    if (!empty($row['topic_name'])) {
                        $simTopic = calculateSimilarity($searchQuery, $row['topic_name']);
                        $simSource = calculateSimilarity($searchQuery, $row['source_term'] ?? '');
                        $maxSim = max($simTopic, $simSource);

                        if ($maxSim >= 50) {
                            $exists = false;
                            foreach (array_merge($existingTopics, $aiMcqTopics) as $existing) {
                                if (strcasecmp($existing['topic'], $row['topic_name']) === 0) {
                                    $exists = true; break;
                                }
                            }
                            if (!$exists && !in_array($row['topic_name'], array_column($genTopics, 'topic'))) {
                                $genTopics[] = ['topic' => $row['topic_name'], 'similarity' => $maxSim, 'source' => 'generated_topics'];
                            }
                        }
                    }
                }
                $genStmt->close();
            }

            // Fetch fresh AI topics (bypass cache) for "load more" - get different topics each time
            $excludeTopics = [];
            if (!empty($_POST['exclude_topics'])) {
                $decoded = json_decode($_POST['exclude_topics'], true);
                if (is_array($decoded)) {
                    $excludeTopics = array_map('trim', array_slice($decoded, 0, 30));
                }
            }
            $aiTopics = searchTopicsWithGemini($searchQuery, 0, 0, [], true, $excludeTopics);
            
            // Store AI topics in generated_topics
            if (!empty($aiTopics)) {
                $insertStmt = $conn->prepare("INSERT IGNORE INTO generated_topics (topic_name, source_term, question_types) VALUES (?, ?, 'mcq')");
                if ($insertStmt) {
                    foreach ($aiTopics as $aiTopic) {
                        $topicName = $aiTopic['topic']; // searchTopicsWithGemini returns array of ['topic' => ..., 'similarity' => ...]
                        $insertStmt->bind_param('ss', $topicName, $searchQuery);
                        $insertStmt->execute();
                    }
                    $insertStmt->close();
                }
            }

            $combinedTopics = array_merge($existingTopics, $aiMcqTopics, $genTopics, $aiTopics);
            $excludeLower = array_map('strtolower', $excludeTopics);
            $uniqueTopics = [];
            $seenTopics = [];
            foreach ($combinedTopics as $topic) {
                $topicLower = strtolower(trim($topic['topic']));
                if (in_array($topicLower, $excludeLower)) continue;
                if (!in_array($topicLower, $seenTopics)) {
                    $uniqueTopics[] = $topic;
                    $seenTopics[] = $topicLower;
                }
            }
            usort($uniqueTopics, function($a, $b) {
                return ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0);
            });
            $uniqueTopics = array_slice($uniqueTopics, 0, 50);
            echo json_encode(['success' => true, 'topics' => $uniqueTopics]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No search query provided']);
    }
    exit;
}

$searchQuery = '';
$searchResults = [];
$showResults = false;
$studyLevel = $_POST['study_level'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic_search']) && !empty(trim($_POST['topic_search']))) {
    $searchQuery = trim($_POST['topic_search']);
    $studyLevel = $_POST['study_level'] ?? '';
    
    if (!empty($searchQuery)) {
        $mongoLogger = new MongoSearchLogger();
        $mongoLogger->logSearch($searchQuery, 'post_search');

        $cacheKey = null; $usedCache = false;
        if (isset($cacheManager) && $cacheManager) {
            $cacheKey = "topic_search_" . md5($searchQuery);
            $cached = $cacheManager->get($cacheKey);
            if ($cached !== false) {
                $cachedData = json_decode($cached, true);
                if (is_array($cachedData) && !empty($cachedData)) {
                    $searchResults = $cachedData; $showResults = true; $usedCache = true;
                }
            }
        }
        
        if (!$usedCache) {
            $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE topic IS NOT NULL AND topic != '' ORDER BY topic");
            $stmt->execute();
            $result = $stmt->get_result();
            $allTopics = [];
            while ($row = $result->fetch_assoc()) $allTopics[] = $row;
            $stmt->close();
            
            foreach ($allTopics as $topicRow) {
                $similarity = calculateSimilarity($searchQuery, $topicRow['topic']);
                if ($similarity >= 50) {
                    $searchResults[] = ['topic' => $topicRow['topic'], 'similarity' => round($similarity, 1)];
                }
            }
            
            $aiStmt = $conn->prepare("SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic IS NOT NULL AND topic != ''");
            $aiStmt->execute();
            $aiResult = $aiStmt->get_result();
            while ($row = $aiResult->fetch_assoc()) {
                $similarity = calculateSimilarity($searchQuery, $row['topic']);
                if ($similarity >= 50) {
                    $exists = false;
                    foreach ($searchResults as $existing) {
                        if (strcasecmp($existing['topic'], $row['topic']) === 0) { $exists = true; break; }
                    }
                    if (!$exists) $searchResults[] = ['topic' => $row['topic'], 'similarity' => round($similarity, 1), 'source' => 'ai_generated'];
                }
            }
            $aiStmt->close();

            // Search in generated_topics (by topic_name OR source_term)
            $termLike = "%$searchQuery%";
            $genStmt = $conn->prepare("SELECT DISTINCT topic_name, source_term FROM generated_topics WHERE topic_name LIKE ? OR source_term LIKE ? LIMIT 100");
            if ($genStmt) {
                $genStmt->bind_param('ss', $termLike, $termLike);
                $genStmt->execute();
                $genResult = $genStmt->get_result();
                while ($row = $genResult->fetch_assoc()) {
                    $simTopic = calculateSimilarity($searchQuery, $row['topic_name']);
                    $simSource = calculateSimilarity($searchQuery, $row['source_term'] ?? '');
                    $maxSim = max($simTopic, $simSource);

                    if ($maxSim >= 50) {
                        $exists = false;
                        foreach ($searchResults as $existing) {
                            if (strcasecmp($existing['topic'], $row['topic_name']) === 0) { $exists = true; break; }
                        }
                        if (!$exists) $searchResults[] = ['topic' => $row['topic_name'], 'similarity' => round($maxSim, 1), 'source' => 'generated_topics'];
                    }
                }
                $genStmt->close();
            }

            usort($searchResults, function($a, $b) { return $b['similarity'] <=> $a['similarity']; });
            $showResults = true;
            
            if (empty($searchResults)) {
                // Use AI to search for topics
                $aiTopics = searchTopicsWithGemini($searchQuery);
                
                if (!empty($aiTopics)) {
                    // Store AI topics
                    $insertStmt = $conn->prepare("INSERT IGNORE INTO generated_topics (topic_name, source_term, question_types) VALUES (?, ?, 'mcq')");
                    if ($insertStmt) {
                        foreach ($aiTopics as $aiTopic) {
                            $topicName = $aiTopic['topic'];
                            $insertStmt->bind_param('ss', $topicName, $searchQuery);
                            $insertStmt->execute();
                        }
                        $insertStmt->close();
                    }
                    
                    // Add to results
                    foreach ($aiTopics as $aiTopic) {
                        $searchResults[] = $aiTopic;
                    }
                    $showResults = true;
                } else {
                    // Fallback to generating MCQs directly for the term
                    $generatedTopics = generateMCQsWithGemini($searchQuery, 10, $studyLevel);
                    if (!empty($generatedTopics)) {
                        $searchResults[] = ['topic' => $searchQuery, 'similarity' => 100.0, 'source' => 'ai_generated'];
                        $showResults = true;
                    }
                }
            }
            if (isset($cacheManager) && $cacheManager && $cacheKey && !empty($searchResults)) {
                $cacheManager->setex($cacheKey, 86400, json_encode($searchResults));
            }
        }
    }
}

if (isset($_POST['selected_topics_json']) && !empty($_POST['selected_topics_json'])) {
    $_SESSION['selected_topics'] = json_decode($_POST['selected_topics_json'], true) ?? [];
}

if (isset($_POST['start_quiz'])) {
    $selectedTopics = $_POST['selected_topics'] ?? [];
    $mcqCount = intval($_POST['mcq_count'] ?? 10);
    if ($mcqCount > 50) $mcqCount = 50;
    unset($_SESSION['selected_topics']);
    
    if (!empty($selectedTopics) && $mcqCount > 0) {
        $topicsArray = [];
        foreach ($selectedTopics as $topicJson) {
            $topicData = json_decode($topicJson, true);
            if ($topicData && isset($topicData['topic'])) $topicsArray[] = $topicData['topic'];
        }
        
        if (!empty($topicsArray) && $mcqCount > 0) {
            $placeholders = str_repeat('?,', count($topicsArray) - 1) . '?';
            $existingCount = 0;
            $source = $_POST['source'] ?? '';
            $quizDuration = intval($_POST['quiz_duration'] ?? 10);
            
            if ($source === 'host') {
                $_SESSION['host_quiz_topics'] = $topicsArray;
                $queryString = http_build_query(['mcq_count' => $mcqCount, 'duration' => $quizDuration]);
                header("Location: online_quiz_host_new.php?" . $queryString);
                exit;
            }
            
            $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM mcqs WHERE topic IN ($placeholders)");
            $types = str_repeat('s', count($topicsArray));
            $params = $topicsArray;
            $checkStmt->bind_param($types, ...$params);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($row = $checkResult->fetch_assoc()) $existingCount += intval($row['cnt']);
            $checkStmt->close();
            
            $aiCheckStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM AIGeneratedMCQs WHERE topic IN ($placeholders)");
            $aiCheckStmt->bind_param($types, ...$params);
            $aiCheckStmt->execute();
            $aiCheckResult = $aiCheckStmt->get_result();
            if ($row = $aiCheckResult->fetch_assoc()) $existingCount += intval($row['cnt']);
            $aiCheckStmt->close();
            
            $neededCount = max(0, $mcqCount - $existingCount);
            if ($neededCount > 0) {
                generateMCQsBulkWithGemini($topicsArray, $neededCount, $studyLevel);
            }
            
            $topicsParam = urlencode(json_encode($topicsArray));
            header('Location: quiz.php?class_id=0&book_id=0&topics=' . $topicsParam . '&mcq_count=' . $mcqCount . '&study_level=' . urlencode($studyLevel));
            exit;
        } else {
            $error = empty($topicsArray) ? "Please select at least one topic." : "Please specify the number of MCQs (1-50).";
        }
    } else {
        $error = "Please select at least one topic and specify the number of MCQs (1-50).";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onliine MCQs Test School and College - AI MCQs Generator - Ahmad Learning Hub</title>
    <!-- SEO & AI Optimization Meta Tags -->
    <meta name="description" content="Discover thousands of MCQs by any topic using our advanced AI-driven search. Perfect for 2026 Board Exam preparation, MDCAT, ECAT, and GRE topic-wise study. 100% accurate syllabus-based questions.">
    <meta name="keywords" content="topic wise MCQs, AI question finder, Ahmad Learning Hub search, board exam topics, science MCQs 2026, chemistry MCQs by topic, physics MCQs by topic">
    <meta name="author" content="Ahmad Learning Hub">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://paper.bhattichemicalsindustry.com.pk/quiz/mcqs_topic.php">
    <meta property="og:title" content="AI-Powered MCQ Search by Topic | Ahmad Learning Hub">
    <meta property="og:description" content="Type any educational topic and let our AI find or generate the best MCQs for your exam practice.">
    <meta property="og:image" content="https://paper.bhattichemicalsindustry.com.pk/assets/images/topic-search-og.jpg">

    <!-- JSON-LD Structured Data for Search Function -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Search MCQs by Topic",
      "description": "An AI-powered tool to search and generate multiple choice questions based on specific educational topics.",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://paper.bhattichemicalsindustry.com.pk/quiz/mcqs_topic.php?topic_search={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
    </script>

    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/mcqs_topic.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php include_once '../header.php'; ?>
<div class="main-content">
    <div class="topic-search-container">
        <?php
        $source = $_REQUEST['source'] ?? '';
        $quizDuration = $_REQUEST['quiz_duration'] ?? 10;
        $backLink = ($source === 'host') ? 'online_quiz_host_new.php' : 'quiz_setup.php';
        $backText = ($source === 'host') ? '← Back to Host Quiz' : '← Back to Quiz Setup';
        ?>
        <div class="top-nav">
            <a href="javascript:void(0)" onclick="ignoreModeAndNavigate('quiz_setup.php')" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Setup
            </a>
            <a href="javascript:void(0)" onclick="ignoreModeAndNavigate('quiz_setup.php')" class="school-mode-btn">
                <i class="fas fa-school"></i> School Mode
            </a>
        </div>
        
        <h1>Expert MCQ Search</h1>
        <p class="desc">Discover thousands of expert-verified questions instantly. Add multiple topics to create your perfect personalized quiz.</p>
        
        <?php if (isset($error)): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 2px solid var(--danger); color: var(--danger); padding: 20px; border-radius: 16px; margin-bottom: 32px; text-align: center; font-weight: 800; animation: shake 0.5s ease-in-out;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="selected-topics-section empty" id="selectedTopicsSection">
            <div class="selected-topics-header">
                <div class="selected-topics-title">Your Selection (<span id="selectedCount">0</span>)</div>
                <button type="button" class="btn-secondary btn-clear-all" onclick="clearAllTopics()"><i class="fas fa-trash-alt"></i> Clear All</button>
            </div>
            
            <div class="selected-topics-list" id="selectedTopicsList">
                 <div class="no-selection-hint">
                    Your list is currently empty. Start by searching modules below.
                </div>
            </div>
            
            <div class="quiz-config-section">
                <div style="margin-bottom: 32px; text-align: center;">
                    <label for="mcq_count" class="quiz-config-header">Number of Questions</label>
                    <div class="mcq-count-controls">
                        <button type="button" onclick="const input = document.getElementById('mcq_count'); input.value = Math.max(1, parseInt(input.value) - 1); input.dispatchEvent(new Event('input'));" class="mcq-count-btn"><i class="fas fa-minus"></i></button>
                        <input 
                            type="number" 
                            id="mcq_count" 
                            class="mcq-count-input"
                            min="1" 
                            max="50" 
                            value="<?= htmlspecialchars($_REQUEST['mcq_count'] ?? $_POST['mcq_count'] ?? 10) ?>" 
                            required
                            oninput="if(parseInt(this.value) > 50) this.value = 50; if(parseInt(this.value) < 1 && this.value !== '') this.value = 1;"
                        >
                        <button type="button" onclick="const input = document.getElementById('mcq_count'); input.value = Math.min(50, parseInt(input.value) + 5); input.dispatchEvent(new Event('input'));" class="mcq-count-btn"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                
                <form method="POST" action="" id="startQuizForm">
                    <input type="hidden" name="mcq_count" id="hidden_mcq_count">
                    <input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>">
                    <input type="hidden" name="quiz_duration" value="<?= htmlspecialchars($quizDuration) ?>">
                    <input type="hidden" name="study_level" value="<?= htmlspecialchars($studyLevel) ?>">
                    <div id="hidden_topics_inputs"></div>
                    <button type="submit" name="start_quiz" class="start-quiz-btn" id="startQuizBtn" disabled>
                        <i class="fas fa-bolt"></i> Start Intelligent Quiz
                    </button>
                </form>
            </div>
        </div>

        <div class="search-section">
            <form method="POST" action="" id="searchForm">
                <input type="hidden" name="selected_topics_json" id="selected_topics_json" value="<?= htmlspecialchars(isset($_SESSION['selected_topics']) ? json_encode($_SESSION['selected_topics']) : '') ?>">
                <input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>">
                <input type="hidden" name="quiz_duration" value="<?= htmlspecialchars($quizDuration) ?>">
                <input type="hidden" name="mcq_count" value="<?= htmlspecialchars($_REQUEST['mcq_count'] ?? $_POST['mcq_count'] ?? 10) ?>">
                
                <div style="margin-bottom: 32px;">
                    <label class="level-label">Challenge Level <span style="color: var(--danger);">*</span></label>
                    <div class="level-container">
                        <button type="button" class="level-btn" id="btn-easy" data-level="easy" onclick="selectLevel('easy', this)"><i class="fas fa-leaf"></i> Easy</button>
                        <button type="button" class="level-btn" id="btn-medium" data-level="medium" onclick="selectLevel('medium', this)"><i class="fas fa-balance-scale"></i> Medium</button>
                        <button type="button" class="level-btn" id="btn-hard" data-level="hard" onclick="selectLevel('hard', this)"><i class="fas fa-fire"></i> Hard</button>
                    </div>
                    <input type="hidden" name="study_level" id="study_level" value="<?= htmlspecialchars($studyLevel) ?>">
                    <div id="levelError" class="level-error"><i class="fas fa-info-circle"></i> Please select a difficulty level to continue</div>
                </div>
                
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        name="topic_search" 
                        id="topic_search"
                        class="search-input" 
                        placeholder="Type any topic (e.g. Molecular Biology, Calculus...)" 
                        value="<?= htmlspecialchars($searchQuery) ?>"
                        autofocus
                    >
                    <button type="submit" class="search-btn">Find Items</button>
                </div>
            </form>
        </div>

        <!-- Professional AI Loader Modal -->
        <div id="aiLoaderModal" class="ai-loader-overlay">
            <div class="ai-loader-card">
                <div class="ai-icon-container">
                    <div class="ai-icon-glow"></div>
                    <i class="fas fa-robot" style="color: white; z-index: 2; position: relative;"></i>
                </div>
                <h2 class="ai-loader-title">Neural Engine Processing</h2>
                
                <div class="ai-steps-list">
                    <div class="ai-step" id="step-1">
                        <div class="ai-step-icon" id="icon-1"><i class="fas fa-circle-notch"></i></div>
                        <div class="ai-step-text">Analyzing topics</div>
                    </div>
                    <div class="ai-step" id="step-2">
                        <div class="ai-step-icon" id="icon-2"><i class="fas fa-circle-notch"></i></div>
                        <div class="ai-step-text">Extracting key concepts</div>
                    </div>
                    <div class="ai-step" id="step-3">
                        <div class="ai-step-icon" id="icon-3"><i class="fas fa-circle-notch"></i></div>
                        <div class="ai-step-text">Designing MCQs</div>
                    </div>
                    <div class="ai-step" id="step-4">
                        <div class="ai-step-icon" id="icon-4"><i class="fas fa-circle-notch"></i></div>
                        <div class="ai-step-text">Validating difficulty</div>
                    </div>
                    <div class="ai-step" id="step-5">
                        <div class="ai-step-icon" id="icon-5"><i class="fas fa-circle-notch"></i></div>
                        <div class="ai-step-text">Finalizing paper</div>
                    </div>
                </div>

                <div class="ai-progress-container">
                    <div class="ai-progress-bar" id="aiProgressBar"></div>
                </div>
                
                <div style="margin-top: 32px; color: rgba(255,255,255,0.4); font-size: 0.9rem; font-style: italic;">
                    <i class="fas fa-info-circle"></i> Our AI is synthesizing questions based on 2026 board standards...
                </div>
            </div>
        </div>

        <!-- Simple Search Loader -->
        <div id="inlineLoader">
            <div class="honeycomb"> 
               <div></div><div></div><div></div><div></div><div></div><div></div><div></div> 
            </div>
            <div class="loader-progress">
                <div class="loader-progress-bar" id="loaderProgressBar"></div>
            </div>
            <div id="loaderText">Scanning Database...</div>
        </div>

        <?php if ($showResults): ?>
            <div class="results-section" id="resultsSection">
                <div class="results-header" id="resultsHeader">
                    <?php if (!empty($searchResults)): ?>
                        <i class="fas fa-poll-h" style="color: var(--primary);"></i> Top Matches (<?= count($searchResults) ?>)
                    <?php else: ?>
                        <i class="fas fa-search-minus" style="color: var(--secondary);"></i> No local matches found
                    <?php endif; ?>
                </div>
                
                <div class="topic-list" id="topicList">
                    <?php foreach ($searchResults as $index => $result): 
                        $topicData = ['topic' => $result['topic'], 'similarity' => $result['similarity']];
                        $topicJson = htmlspecialchars(json_encode($topicData), ENT_QUOTES);
                    ?>
                        <div class="topic-item" data-topic-data="<?= $topicJson ?>" onclick="toggleTopic(this, '<?= $topicJson ?>')">
                            <div class="topic-name"><?= htmlspecialchars($result['topic']) ?></div>
                            <div class="topic-similarity"><?= $result['similarity'] ?>% match</div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($searchResults)): ?>
                        <div id="noTopicsHint" class="no-topics-hint">
                            <p style="font-size: 1.1rem; margin-bottom: 15px;">We couldn't find matches in our local library for "<?= htmlspecialchars($searchQuery) ?>".</p>
                            <p>Try triggering our <strong>AI Expand</strong> feature below to generate relevant topics.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="load-more-btn-container">
                    <button type="button" id="loadMoreTopicsBtn" class="btn-secondary">
                        <i class="fas fa-magic"></i> Explore Related Topics (AI)
                    </button>
                    <div id="loadMoreLoader">
                         <div class="loader-progress">
                             <div class="loader-progress-bar" id="loadMoreProgressBar"></div>
                         </div>
                         <div class="load-more-text">Our AI is exploring the knowledge graph...</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <article class="seo-article-section">
            <div class="seo-grid">
                <div class="seo-card">
                    <div class="seo-icon"><i class="fas fa-microscope" style="color: var(--primary);"></i></div>
                    <h2 class="seo-card-title">Micro-Topic Precision</h2>
                    <p class="seo-card-text">Our proprietary AI engine breaks down curricula into atomic topics. Search specifically for "Electron Transport Chain" or "Kinetic Theory" to get laser-focused practice.</p>
                </div>
                <div class="seo-card">
                    <div class="seo-icon"><i class="fas fa-brain" style="color: #a855f7;"></i></div>
                    <h2 class="seo-card-title">Cognitive AI Synthesis</h2>
                    <p class="seo-card-text">Powered by Google Gemini Pro, we don't just find questions—we verify their conceptual integrity against current 2026 board standards across Pakistan.</p>
                </div>
                <div class="seo-card">
                    <div class="seo-icon"><i class="fas fa-chart-line" style="color: var(--success);"></i></div>
                    <h2 class="seo-card-title">Adaptive Difficulty</h2>
                    <p class="seo-card-text">Choose your challenge level. From foundational Easy mode to board-crushing Hard mode, tailor your preparation to your current mastery level.</p>
                </div>
                <div class="seo-card">
                    <div class="seo-icon"><i class="fas fa-users" style="color: var(--accent);"></i></div>
                    <h2 class="seo-card-title">Multi-Platform Ready</h2>
                    <p class="seo-card-text">Perfect for individual study or classroom hosting. Select your modules and instantly broadcast a live interactive competition to any device.</p>
                </div>
            </div>
            
            <div class="seo-footer">
                <p>Pakistan's most sophisticated <strong>Topic-Wise MCQ Generator</strong>. Designed for excellence in <strong>MDCAT, ECAT, NTS,</strong> and High School Board Examinations.</p>
            </div>
        </article>
    </div>
</div>

<!-- Upgrade Plan Modal -->
<div id="upgradeModal" class="upgrade-modal-overlay">
    <div class="upgrade-modal-card">
        <div class="upgrade-modal-icon">
            <i class="fas fa-crown"></i>
        </div>
        <h2 class="upgrade-modal-title">Unlock Unlimited Topics</h2>
        <p class="upgrade-modal-text"><?= ($userPlan === 'free') ? "Free" : ucfirst($userPlan) ?> users can select up to <strong><?= $topicLimit ?> topics</strong> per quiz. Upgrade to a higher plan to select more topics and unlock all advanced AI features.</p>
        
        <div class="upgrade-features-list">
            <div class="upgrade-feature-item">
                <i class="fas fa-check-circle"></i>
                <span>Unlimited topic selection</span>
            </div>
            <div class="upgrade-feature-item">
                <i class="fas fa-check-circle"></i>
                <span>Advanced AI discovery depth</span>
            </div>
            <div class="upgrade-feature-item">
                <i class="fas fa-check-circle"></i>
                <span>Priority question generation</span>
            </div>
        </div>
        
        <div class="upgrade-modal-actions">
            <a href="../subscription.php" class="btn-upgrade-now">
                <i class="fas fa-rocket"></i> Upgrade Now
            </a>
            <button type="button" class="btn-maybe-later" onclick="closeUpgradeModal()">
                Maybe Later
            </button>
        </div>
    </div>
</div>

<script>
let loaderProgressInterval;
let selectedTopics = [];
const isPremium = <?= json_encode($isPremium) ?>;
const topicLimit = <?= json_encode($topicLimit) ?>;

function ignoreModeAndNavigate(url) {
    window.location.href = '../reset_and_go.php?redirect=' + encodeURIComponent('quiz/' + url);
}

function showUpgradeModal() {
    const modal = document.getElementById('upgradeModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeUpgradeModal() {
    const modal = document.getElementById('upgradeModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

<?php if (isset($_SESSION['selected_topics']) && is_array($_SESSION['selected_topics'])) {
    echo "selectedTopics = " . json_encode($_SESSION['selected_topics']) . ";\n";
} ?>

function showLoader(title = 'Processing...', subtitle = '') {
    const loader = document.getElementById('inlineLoader');
    const titleEl = document.getElementById('loaderText');
    const progressBar = document.getElementById('loaderProgressBar');
    const resultsSection = document.querySelector('.results-section');
    
    if (loader) {
        if (titleEl) titleEl.textContent = title;
        loader.style.display = 'block';
        if (resultsSection) resultsSection.style.display = 'none';
        
        if (progressBar) {
            progressBar.style.width = '0%';
            let progress = 0;
            if (loaderProgressInterval) clearInterval(loaderProgressInterval);
            loaderProgressInterval = setInterval(function() {
                progress += 5;
                if (progress >= 95) { progress = 95; clearInterval(loaderProgressInterval); }
                progressBar.style.width = progress + '%';
            }, 200);
        }
    }
}

function showAILoader() {
    const modal = document.getElementById('aiLoaderModal');
    const progressBar = document.getElementById('aiProgressBar');
    
    // Disable scrolling while loader is active
    document.body.style.overflow = 'hidden';
    
    // Ensure modal occupies full screen and is visible
    modal.style.display = 'flex';
    
    const steps = [
        { id: 1, text: 'Analyzing topics', duration: 3500 },
        { id: 2, text: 'Extracting key concepts', duration: 3500 },
        { id: 3, text: 'Designing MCQs', duration: 3500 },
        { id: 4, text: 'Validating difficulty', duration: 3500 },
        { id: 5, text: 'Finalizing paper', duration: 3500 }
    ];
    
    let currentStepIndex = 0;
    let totalDuration = steps.reduce((acc, s) => acc + s.duration, 0);
    let elapsed = 0;
    
    // Initialize all steps to pending state
    steps.forEach(step => {
        const stepEl = document.getElementById(`step-${step.id}`);
        const iconEl = document.getElementById(`icon-${step.id}`);
        if (stepEl) {
            stepEl.classList.remove('active', 'completed');
            iconEl.innerHTML = '<i class="fas fa-circle-notch"></i>';
        }
    });
    
    function updateStep() {
        if (currentStepIndex >= steps.length) return;
        
        const step = steps[currentStepIndex];
        const stepEl = document.getElementById(`step-${step.id}`);
        const iconEl = document.getElementById(`icon-${step.id}`);
        
        // Mark previous steps as completed
        for (let i = 0; i < currentStepIndex; i++) {
            const prevStep = steps[i];
            const prevStepEl = document.getElementById(`step-${prevStep.id}`);
            const prevIconEl = document.getElementById(`icon-${prevStep.id}`);
            if (prevStepEl) {
                prevStepEl.classList.add('completed');
                prevStepEl.classList.remove('active');
                prevIconEl.innerHTML = '<i class="fas fa-check"></i>';
            }
        }
        
        // Mark current step as active
        if (stepEl) {
            stepEl.classList.add('active');
            iconEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
        
        // Move to next step after duration
        setTimeout(() => {
            currentStepIndex++;
            updateStep();
        }, step.duration);
    }
    
    // Higher interval for fewer DOM updates, CSS transition handles the smoothness
    const progressUpdateFreq = 250; 
    const progressInterval = setInterval(() => {
        elapsed += progressUpdateFreq;
        let progress = (elapsed / totalDuration) * 100;
        if (progress >= 99) {
            progress = 99;
            clearInterval(progressInterval);
        }
        if (progressBar) progressBar.style.width = progress + '%';
    }, progressUpdateFreq);
    
    updateStep();
}

function selectLevel(level, button) {
    event.preventDefault();
    document.getElementById('study_level').value = level;
    document.getElementById('levelError').style.display = 'none';
    
    document.querySelectorAll('.level-btn').forEach(btn => {
        btn.classList.remove('selected-easy', 'selected-medium', 'selected-hard');
    });
    
    button.classList.add('selected-' + level);
}

function toggleTopic(element, topicJson) {
    const topicData = JSON.parse(topicJson);
    const index = selectedTopics.findIndex(t => JSON.parse(t).topic === topicData.topic);
    
    if (index > -1) {
        selectedTopics.splice(index, 1);
        element.classList.remove('selected');
    } else {
        // Restriction check based on user plan limit
        if (topicLimit !== -1 && selectedTopics.length >= topicLimit) {
            showUpgradeModal();
            return;
        }
        selectedTopics.push(topicJson);
        element.classList.add('selected');
    }
    saveSelectedTopicsToSession();
    updateSelectedTopicsUI();
}

function removeTopic(topicJson) {
    const topicData = JSON.parse(topicJson);
    selectedTopics = selectedTopics.filter(t => JSON.parse(t).topic !== topicData.topic);
    
    document.querySelectorAll('.topic-item').forEach(item => {
        const itemData = item.getAttribute('data-topic-data');
        if (itemData && JSON.parse(itemData).topic === topicData.topic) item.classList.remove('selected');
    });
    saveSelectedTopicsToSession();
    updateSelectedTopicsUI();
}

function clearAllTopics() {
    selectedTopics = [];
    document.querySelectorAll('.topic-item').forEach(item => item.classList.remove('selected'));
    saveSelectedTopicsToSession();
    updateSelectedTopicsUI();
}

function saveSelectedTopicsToSession() {
    const hiddenInput = document.getElementById('selected_topics_json');
    if (hiddenInput) hiddenInput.value = JSON.stringify(selectedTopics);
}

function updateSelectedTopicsUI() {
    const section = document.getElementById('selectedTopicsSection');
    const list = document.getElementById('selectedTopicsList');
    const count = document.getElementById('selectedCount');
    const startBtn = document.getElementById('startQuizBtn');
    const hiddenInputs = document.getElementById('hidden_topics_inputs');
    const hiddenMcqCount = document.getElementById('hidden_mcq_count');
    const mcqCountInput = document.getElementById('mcq_count');
    
    count.textContent = selectedTopics.length;
    
    if (selectedTopics.length > 0) {
        section.classList.remove('empty');
        hiddenInputs.innerHTML = '';
        selectedTopics.forEach((topicJson) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_topics[]';
            input.value = topicJson;
            hiddenInputs.appendChild(input);
        });
        hiddenMcqCount.value = mcqCountInput.value;
        startBtn.disabled = !(mcqCountInput.value > 0);
        
        list.innerHTML = '';
        selectedTopics.forEach((topicJson) => {
            const topicData = JSON.parse(topicJson);
            const badge = document.createElement('div');
            badge.className = 'selected-topic-badge';
            badge.innerHTML = `<span>${topicData.topic}</span>`;
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-topic-btn';
            removeBtn.textContent = '×';
            removeBtn.onclick = () => removeTopic(topicJson);
            
            badge.appendChild(removeBtn);
            list.appendChild(badge);
        });
    } else {
        section.classList.add('empty');
        list.innerHTML = '<div class="no-selection-hint" style="width: 100%; text-align: center; color: var(--text-muted); padding: 20px; font-weight: 600;">Your list is currently empty. Start by searching modules below.</div>';
        startBtn.disabled = true;
    }
}

document.getElementById('mcq_count')?.addEventListener('input', function() {
    document.getElementById('hidden_mcq_count').value = this.value;
    document.getElementById('startQuizBtn').disabled = !(selectedTopics.length > 0 && this.value > 0);
});

document.getElementById('searchForm')?.addEventListener('submit', function(e) {
    const level = document.getElementById('study_level').value;
    if (!level) {
        e.preventDefault();
        const err = document.getElementById('levelError');
        err.style.display = 'block';
        err.scrollIntoView({behavior: 'smooth', block: 'center'});
        return false;
    }
    saveSelectedTopicsToSession();
    showLoader('Searching Topics...', 'Deep search in progress.');
});

document.getElementById('startQuizForm')?.addEventListener('submit', function(e) {
    if (selectedTopics.length === 0 || !document.getElementById('mcq_count').value) {
        e.preventDefault();
        alert('Selection internal error.');
        return;
    }
    showAILoader();
});

document.addEventListener('DOMContentLoaded', () => {
    // Restore persistent level selection
    const currentLevel = document.getElementById('study_level').value;
    if (currentLevel) {
        const btn = document.getElementById('btn-' + currentLevel);
        if (btn) btn.classList.add('selected-' + currentLevel);
    }
    
    updateSelectedTopicsUI();
    selectedTopics.forEach(topicJson => {
        const topicData = JSON.parse(topicJson);
        document.querySelectorAll('.topic-item').forEach(item => {
            const itemData = item.getAttribute('data-topic-data');
            if (itemData && JSON.parse(itemData).topic === topicData.topic) item.classList.add('selected');
        });
    });
});

document.getElementById('loadMoreTopicsBtn')?.addEventListener('click', function() {
    const btn = this;
    const loader = document.getElementById('loadMoreLoader');
    const progressBar = document.getElementById('loadMoreProgressBar');
    const searchQuery = document.getElementById('topic_search').value;
    const topicList = document.getElementById('topicList');
    const resultsHeader = document.getElementById('resultsHeader');
    const noTopicsHint = document.getElementById('noTopicsHint');
    
    if (!searchQuery) return;
    
    // Collect currently displayed topic names to exclude (get different topics)
    const excludeTopics = [];
    document.querySelectorAll('.topic-item[data-topic-data]').forEach(item => {
        try {
            const d = JSON.parse(item.getAttribute('data-topic-data'));
            if (d && d.topic) excludeTopics.push(d.topic);
        } catch(e) {}
    });
    
    btn.style.display = 'none';
    loader.style.display = 'block';
    
    let width = 0;
    const interval = setInterval(() => {
        width = Math.min(width + 5, 90);
        progressBar.style.width = width + '%';
    }, 300);
    
    const params = {
        action: 'load_more_topics',
        search_query: searchQuery,
        exclude_topics: JSON.stringify(excludeTopics)
    };
    
    fetch('mcqs_topic.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(params)
    })
    .then(r => r.json())
    .then(data => {
        progressBar.style.width = '100%';
        if (data.success && data.topics && data.topics.length > 0) {
            if (noTopicsHint) noTopicsHint.remove();
            let added = 0;
            data.topics.forEach(topic => {
                const topicName = typeof topic === 'string' ? topic : topic.topic;
                const similarity = (topic.similarity !== undefined) ? topic.similarity : 85.0;
                const isSelected = selectedTopics.some(t => {
                    try { return JSON.parse(t).topic === topicName; } catch(e) { return false; }
                });
                const tData = {topic: topicName, similarity: similarity};
                const div = document.createElement('div');
                div.className = 'topic-item' + (isSelected ? ' selected' : '');
                div.setAttribute('data-topic-data', JSON.stringify(tData));
                div.onclick = () => toggleTopic(div, JSON.stringify(tData));
                div.innerHTML = '<div class="topic-name">' + topicName + '</div><div class="topic-similarity">' + similarity + '% match</div>';
                topicList.appendChild(div);
                added++;
            });
            if (resultsHeader) {
                const total = document.querySelectorAll('.topic-item[data-topic-data]').length;
                resultsHeader.innerHTML = '<i class="fas fa-magic" style="color: var(--primary);"></i> Found ' + total + ' search results';
            }
        }
    })
    .finally(() => {
        clearInterval(interval);
        setTimeout(() => { 
            loader.style.display = 'none'; 
            btn.style.display = 'inline-block';
        }, 500);
    });
});
</script>
<?php include '../footer.php'; ?>
</body>
</html>
