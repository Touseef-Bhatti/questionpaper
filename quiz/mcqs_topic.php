<?php
// mcqs_topic.php - Topic search page for MCQs
include '../db_connect.php';
require_once 'mcq_generator.php';
require_once 'MongoSearchLogger.php';
if (session_status() === PHP_SESSION_NONE) session_start();

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

            // 3. Search in generated_topics
            $genTopics = [];
            $genStmt = $conn->prepare("SELECT DISTINCT topic_name FROM generated_topics WHERE topic_name IS NOT NULL AND topic_name != ''");
            if ($genStmt) {
                $genStmt->execute();
                $result = $genStmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    if (!empty($row['topic_name'])) {
                        $sim = calculateSimilarity($searchQuery, $row['topic_name']);
                        if ($sim >= 50) {
                            $exists = false;
                            foreach (array_merge($existingTopics, $aiMcqTopics) as $existing) {
                                if (strcasecmp($existing['topic'], $row['topic_name']) === 0) {
                                    $exists = true; break;
                                }
                            }
                            if (!$exists && !in_array($row['topic_name'], array_column($genTopics, 'topic'))) {
                                $genTopics[] = ['topic' => $row['topic_name'], 'similarity' => $sim, 'source' => 'generated_topics'];
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

            // Search in generated_topics
            $genStmt = $conn->prepare("SELECT DISTINCT topic_name FROM generated_topics WHERE topic_name IS NOT NULL AND topic_name != '' ORDER BY topic_name");
            if ($genStmt) {
                $genStmt->execute();
                $genResult = $genStmt->get_result();
                while ($row = $genResult->fetch_assoc()) {
                    $similarity = calculateSimilarity($searchQuery, $row['topic_name']);
                    if ($similarity >= 50) {
                        $exists = false;
                        foreach ($searchResults as $existing) {
                            if (strcasecmp($existing['topic'], $row['topic_name']) === 0) { $exists = true; break; }
                        }
                        if (!$exists) $searchResults[] = ['topic' => $row['topic_name'], 'similarity' => round($similarity, 1), 'source' => 'generated_topics'];
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
    <title>Search MCQs by Topic - AI Powered discovery - Ahmad Learning Hub</title>
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
<?php include '../header.php'; ?>
<div class="main-content">
    <div class="topic-search-container">
        <?php
        $source = $_REQUEST['source'] ?? '';
        $quizDuration = $_REQUEST['quiz_duration'] ?? 10;
        $backLink = ($source === 'host') ? 'online_quiz_host_new.php' : 'quiz_setup.php';
        $backText = ($source === 'host') ? '← Back to Host Quiz' : '← Back to Quiz Setup';
        ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <a href="<?= $backLink ?>" class="back-btn"><?= $backText ?></a>
            <a href="quiz_setup.php" class="school-mode-btn">🏫 School Mode (Class/Book)</a>
        </div>
        
        <h1>Search MCQs by Topic</h1>
        <p class="desc">Create custom quizzes by searching and adding multiple topics. Powered by AI topic generation.</p>
        
        <?php if (isset($error)): ?>
            <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 16px; border-radius: 12px; margin-bottom: 24px; text-align: center; font-weight: 600;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="selected-topics-section empty" id="selectedTopicsSection">
            <div class="selected-topics-header">
                <div class="selected-topics-title">Selected Topics (<span id="selectedCount">0</span>)</div>
                <button type="button" class="btn btn-secondary" onclick="clearAllTopics()" style="padding: 8px 16px; font-size: 0.875rem;color:var(--primary-dark);border-color:var(--primary-dark);">Clear All</button>
            </div>
            
            <div class="selected-topics-list" id="selectedTopicsList">
                 <div class="no-selection-hint" style="width: 100%; text-align: center; color: var(--text-muted); font-style: italic; padding: 20px;">
                    No topics selected yet. Search and add topics below.
                </div>
            </div>
            
            <div class="quiz-config-section">
                <div class="form-grid" style="margin-bottom: 24px; display: grid; grid-template-columns: 1fr; max-width: 300px; margin-left: auto; margin-right: auto;">
                    <div class="form-group" style="text-align: center;">
                        <label for="mcq_count" class="form-label">Number of MCQs (1-50)</label>
                        <input 
                            type="number" 
                            id="mcq_count" 
                            class="form-input"
                            min="1" 
                            max="50" 
                            value="<?= htmlspecialchars($_REQUEST['mcq_count'] ?? $_POST['mcq_count'] ?? 10) ?>" 
                            required
                            style="text-align: center; font-size: 1.25rem; font-weight: 800;"
                            oninput="if(parseInt(this.value) > 50) this.value = 50; if(parseInt(this.value) < 1 && this.value !== '') this.value = 1;"
                        >
                    </div>
                </div>
                
                <form method="POST" action="" id="startQuizForm">
                    <input type="hidden" name="mcq_count" id="hidden_mcq_count">
                    <input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>">
                    <input type="hidden" name="quiz_duration" value="<?= htmlspecialchars($quizDuration) ?>">
                    <input type="hidden" name="study_level" value="<?= htmlspecialchars($studyLevel) ?>">
                    <div id="hidden_topics_inputs"></div>
                    <button type="submit" name="start_quiz" class="start-quiz-btn" id="startQuizBtn" disabled>
                        🚀 Start Quiz with Selected Topics
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
                
                <div style="margin-bottom: 24px;">
                    <label class="form-label">Difficulty Level <span style="color: var(--danger);">*</span></label>
                    <div style="display: flex; gap: 12px;">
                        <button type="button" class="level-btn" id="btn-easy" data-level="easy" onclick="selectLevel('easy', this)">Easy</button>
                        <button type="button" class="level-btn" id="btn-medium" data-level="medium" onclick="selectLevel('medium', this)">Medium</button>
                        <button type="button" class="level-btn" id="btn-hard" data-level="hard" onclick="selectLevel('hard', this)">Hard</button>
                    </div>
                    <input type="hidden" name="study_level" id="study_level" value="<?= htmlspecialchars($studyLevel) ?>">
                    <span id="levelError" style="color: var(--danger); font-size: 0.875rem; display: none; margin-top: 8px; font-weight: 600;">Please select a difficulty level</span>
                </div>
                
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        name="topic_search" 
                        id="topic_search"
                        class="search-input" 
                        placeholder="Search topics (e.g., Algebra, Atoms, Biology...)" 
                        value="<?= htmlspecialchars($searchQuery) ?>"
                        autofocus
                    >
                    <button type="submit" class="search-btn">🔍 Search</button>
                </div>
            </form>
        </div>

        <!-- Advanced AI Loader Modal -->
        <div id="aiLoaderModal" class="ai-loader-overlay">
            <div class="ai-loader-card">
                <div class="ai-icon-container">
                    <div class="ai-icon-glow"></div>
                    🤖
                </div>
                <h2 class="ai-loader-title">Crafting Your Quiz</h2>
                
                <div class="ai-steps-list">
                    <div class="ai-step" id="step-1">
                        <div class="ai-step-icon" id="icon-1">⏳</div>
                        <div class="ai-step-text">Analyzing topics</div>
                    </div>
                    <div class="ai-step" id="step-2">
                        <div class="ai-step-icon" id="icon-2">⏳</div>
                        <div class="ai-step-text">Extracting key concepts</div>
                    </div>
                    <div class="ai-step" id="step-3">
                        <div class="ai-step-icon" id="icon-3">⏳</div>
                        <div class="ai-step-text">Designing MCQs</div>
                    </div>
                    <div class="ai-step" id="step-4">
                        <div class="ai-step-icon" id="icon-4">⏳</div>
                        <div class="ai-step-text">Validating difficulty</div>
                    </div>
                    <div class="ai-step" id="step-5">
                        <div class="ai-step-icon" id="icon-5">⏳</div>
                        <div class="ai-step-text">Finalizing paper</div>
                    </div>
                </div>

                <div class="ai-progress-container">
                    <div class="ai-progress-bar" id="aiProgressBar"></div>
                </div>
            </div>
        </div>

        <!-- Keep inline loader for simple searches -->
        <div id="inlineLoader" style="display:none; padding: 40px 0;">
            <div class="honeycomb"> 
               <div></div><div></div><div></div><div></div><div></div><div></div><div></div> 
            </div>
            <div class="loader-progress">
                <div class="loader-progress-bar" id="loaderProgressBar"></div>
            </div>
            <div id="loaderText" style="text-align: center; color: var(--text-muted); font-weight: 700; font-size: 1.1rem;">Searching Topics...</div>
        </div>

        <?php if ($showResults): ?>
            <div class="results-section" id="resultsSection">
                <div class="results-header" id="resultsHeader">
                    <?php if (!empty($searchResults)): ?>
                        ✨ Found <?= count($searchResults) ?> search results
                    <?php else: ?>
                        No topics found in database for "<?= htmlspecialchars($searchQuery) ?>"
                    <?php endif; ?>
                </div>
                
                <div class="topic-list" id="topicList">
                    <?php foreach ($searchResults as $index => $result): 
                        $topicData = ['topic' => $result['topic'], 'similarity' => $result['similarity']];
                        $topicJson = htmlspecialchars(json_encode($topicData), ENT_QUOTES);
                    ?>
                        <div class="topic-item" data-topic-data="<?= $topicJson ?>" onclick="toggleTopic(this, '<?= $topicJson ?>')">
                            <div class="topic-info">
                                <div class="topic-name"><?= htmlspecialchars($result['topic']) ?></div>
                            </div>
                            <div class="topic-similarity"><?= $result['similarity'] ?>% match</div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($searchResults)): ?>
                        <div id="noTopicsHint" style="text-align: center; color: var(--text-muted); font-style: italic; padding: 24px;">
                            Click the button below to load AI-generated related topics.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div style="text-align: center; margin-top: 32px;">
                    <button type="button" id="loadMoreTopicsBtn" class="btn btn-secondary" style="min-width: 280px; border-width: 2px;color:var(--primary-dark);border-color:var(--primary-dark);">
                        ✨ Load Related Topics (AI)
                    </button>
                    <div id="loadMoreLoader" style="display:none; width: 100%; max-width: 300px; margin: 20px auto;">
                         <div class="loader-progress" style="margin: 0 auto;">
                             <div class="loader-progress-bar" id="loadMoreProgressBar" style="width:0%"></div>
                         </div>
                         <div style="text-align:center; margin-top:12px; color:var(--text-muted); font-size:0.95rem; font-weight: 600;">AI is exploring related topics...</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Additional SEO Content for Topic Search -->
    <article class="seo-article-section">
        <div class="seo-grid">
            <div class="seo-card">
                <div class="seo-icon">🧬</div>
                <h3 class="seo-card-title">Topic-Wise Precision</h3>
                <p class="seo-card-text">Our AI-driven engine specializes in granular topic extraction. Whether you're searching for "Mitosis in Biology" or "Projectiles in Physics", get questions that hit the mark for <strong>BISE Punjab, Federal Board,</strong> and <strong>KPK Boards</strong>.</p>
            </div>
            <div class="seo-card">
                <div class="seo-icon">🤖</div>
                <h3 class="seo-card-title">AI-Powered Generation</h3>
                <p class="seo-card-text">Leveraging <strong>Google Gemini AI</strong>, we provide dynamic MCQ generation for niche topics. If our database of 50,000+ questions doesn't have it, our AI creates high-standard, conceptually sound questions instantly.</p>
            </div>
            <div class="seo-card">
                <div class="seo-icon">📈</div>
                <h3 class="seo-card-title">Exam-Ready Standards</h3>
                <p class="seo-card-text">Stay ahead with questions modeled after the <strong>2026 Paper Pattern</strong>. Perfect for <strong>MDCAT, ECAT, NTS,</strong> and board exam preparation for Classes 9, 10, 11, and 12.</p>
            </div>
            <div class="seo-card">
                <div class="seo-icon">⚡</div>
                <h3 class="seo-card-title">Instant Quiz Hosting</h3>
                <p class="seo-card-text">Once you've selected your topics, host a live quiz for your students or peers in seconds. Real-time leaderboards and instant results tracking make learning interactive and fun.</p>
            </div>
        </div>
        
        <div class="seo-footer">
            <p>Empowering students across Pakistan with the most advanced <strong>AI MCQ Generator</strong> and <strong>Online Quiz Platform</strong>. Trusted for Matric, Intermediate, and Entrance Test success.</p>
        </div>
    </article>
</div>

<script>
let loaderProgressInterval;
let selectedTopics = [];
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
        { id: 1, text: 'Analyzing topics', duration: 5500 },
        { id: 2, text: 'Extracting key concepts', duration: 5500 },
        { id: 3, text: 'Designing MCQs', duration: 6000 },
        { id: 4, text: 'Validating difficulty', duration: 5000 },
        { id: 5, text: 'Finalizing paper', duration: 15000 }
    ];
    
    let currentStepIndex = 0;
    let totalDuration = steps.reduce((acc, s) => acc + s.duration, 0);
    let elapsed = 0;
    
    // Initialize all steps to pending state (hourglass)
    steps.forEach(step => {
        const stepEl = document.getElementById(`step-${step.id}`);
        const iconEl = document.getElementById(`icon-${step.id}`);
        if (stepEl) {
            stepEl.classList.remove('active', 'completed');
            iconEl.textContent = '⏳';
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
                prevIconEl.textContent = '✔';
            }
        }
        
        // Mark current step as active
        if (stepEl) {
            stepEl.classList.add('active');
            iconEl.textContent = '⏳';
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
        list.innerHTML = '<div style="width: 100%; text-align: center; color: var(--text-muted); font-style: italic; padding: 20px;">No topics selected yet. Search and add topics below.</div>';
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
                div.innerHTML = '<div class="topic-info"><div class="topic-name">' + topicName + '</div></div><div class="topic-similarity">' + similarity + '% match</div>';
                topicList.appendChild(div);
                added++;
            });
            if (resultsHeader) {
                const total = document.querySelectorAll('.topic-item[data-topic-data]').length;
                resultsHeader.textContent = '\u2728 Found ' + total + ' search results';
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
