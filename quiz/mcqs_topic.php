<?php
// mcqs_topic.php - Topic search page for MCQs
if (session_status() === PHP_SESSION_NONE) session_start();

include_once '../db_connect.php';
require_once 'mcq_generator.php';
// require_once 'MongoSearchLogger.php';
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
    // Remove spaces and convert to lowercase for comparison "ignore spaces" requirement
    $s1 = str_replace(' ', '', mb_strtolower(trim($str1)));
    $s2 = str_replace(' ', '', mb_strtolower(trim($str2)));
    if (empty($s1) || empty($s2)) return 0;
    
    similar_text($s1, $s2, $percent);
    
    // Check if one contains the other (ignoring spaces)
    if (strpos($s1, $s2) !== false || strpos($s2, $s1) !== false) {
        $percent = max($percent, 80); // Increased from 70
        
        // If the query perfectly matches the START of the topic, give it very high score
        if (strpos($s2, $s1) === 0) {
            $percent = max($percent, 90);
        }
    }
    return $percent;
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'topics' => []];
    try {
        if (isset($_GET['q']) && !empty(trim($_GET['q']))) {
            $searchQuery = trim($_GET['q']);
            $excludeTopics = [];
            if (isset($_GET['exclude'])) {
                if (is_array($_GET['exclude'])) {
                    $excludeTopics = $_GET['exclude'];
                } elseif (is_string($_GET['exclude'])) {
                    $tmp = json_decode($_GET['exclude'], true);
                    if (is_array($tmp)) $excludeTopics = $tmp;
                }
            }
            
            $uniqueTopics = [];
            $existingTopics = [];
            
            // 1. Search existing topics in the database (including keywords)
            $termLike = "%$searchQuery%";
            $sql = "SELECT DISTINCT topic_name, keywords FROM generated_topics 
                    WHERE topic_name LIKE ? OR source_term LIKE ? OR keywords LIKE ? 
                    LIMIT 20";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('sss', $termLike, $termLike, $termLike);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $existingTopics[] = [
                        'topic' => $row['topic_name'],
                        'keywords' => $row['keywords'] ?? '',
                        'similarity' => calculateSimilarity($searchQuery, $row['topic_name']),
                        'source' => 'database'
                    ];
                }
                $stmt->close();
            }

            // 2. AI Search (if needed or to supplement)
            $aiTopics = searchTopicsWithGemini($searchQuery, 0, 0, [], true, $excludeTopics);
            if (!empty($aiTopics)) {
                $insertStmt = $conn->prepare("INSERT INTO generated_topics (topic_name, source_term, question_types, keywords) VALUES (?, ?, 'mcq', ?) ON DUPLICATE KEY UPDATE keywords = VALUES(keywords)");
                if ($insertStmt) {
                    foreach ($aiTopics as $aiTopic) {
                        $topicName = $aiTopic['topic'];
                        $keywords = $aiTopic['keywords'] ?? '';
                        $insertStmt->bind_param('sss', $topicName, $searchQuery, $keywords);
                        $insertStmt->execute();
                    }
                    $insertStmt->close();
                }
            }

            $combinedTopics = array_merge($existingTopics, $aiTopics);
            $excludeLower = array_map('strtolower', $excludeTopics);
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
            $response['topics'] = array_slice($uniqueTopics, 0, 50);
            $response['success'] = true;
        }
    } catch (Exception $e) {
        error_log("AJAX Topic Search Error: " . $e->getMessage());
        $response['error'] = 'An unexpected error occurred.';
    }
    echo json_encode($response);
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
        $cacheKey = null; $usedCache = false;
        if (isset($cacheManager) && $cacheManager) {
            $cacheKey = "topic_search_v2_" . md5($searchQuery);
            $cached = $cacheManager->get($cacheKey);
            if ($cached !== false) {
                $cachedData = json_decode($cached, true);
                if (is_array($cachedData) && !empty($cachedData)) {
                    $searchResults = $cachedData; $showResults = true; $usedCache = true;
                }
            }
        }
        
        if (!$usedCache) {
            // SQL search for topics
            $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE topic IS NOT NULL AND topic != '' ORDER BY topic");
            $stmt->execute();
            $result = $stmt->get_result();
            $allTopics = [];
            while ($row = $result->fetch_assoc()) $allTopics[] = $row;
            $stmt->close();

            foreach ($allTopics as $topicRow) {
                $similarity = calculateSimilarity($searchQuery, $topicRow['topic']);
                if ($similarity >= 60) {
                    $searchResults[] = ['topic' => $topicRow['topic'], 'similarity' => round($similarity, 1)];
                }
            }

            $aiStmt = $conn->prepare("SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic IS NOT NULL AND topic != ''");
            $aiStmt->execute();
            $aiResult = $aiStmt->get_result();
            while ($row = $aiResult->fetch_assoc()) {
                $similarity = calculateSimilarity($searchQuery, $row['topic']);
                if ($similarity >= 60) {
                    $exists = false;
                    foreach ($searchResults as $existing) {
                        if (strcasecmp($existing['topic'], $row['topic']) === 0) { $exists = true; break; }
                    }
                    if (!$exists) $searchResults[] = ['topic' => $row['topic'], 'similarity' => round($similarity, 1), 'source' => 'ai_generated'];
                }
            }
            $aiStmt->close();

            $termLike = "%$searchQuery%";
            $genStmt = $conn->prepare("SELECT DISTINCT topic_name, source_term, keywords FROM generated_topics WHERE topic_name LIKE ? OR source_term LIKE ? OR keywords LIKE ? LIMIT 100");
            if ($genStmt) {
                $genStmt->bind_param('sss', $termLike, $termLike, $termLike);
                $genStmt->execute();
                $genResult = $genStmt->get_result();
                while ($row = $genResult->fetch_assoc()) {
                    $simTopic = calculateSimilarity($searchQuery, $row['topic_name']);
                    $simSource = calculateSimilarity($searchQuery, $row['source_term'] ?? '');
                    $maxSim = max($simTopic, $simSource);
                    if ($maxSim >= 60) {
                        $exists = false;
                        foreach ($searchResults as $existing) {
                            if (strcasecmp($existing['topic'], $row['topic_name']) === 0) { $exists = true; break; }
                        }
                        if (!$exists) $searchResults[] = ['topic' => $row['topic_name'], 'similarity' => round($maxSim, 1), 'source' => 'generated_topics'];
                    }
                }
                $genStmt->close();
            }

            // If the query matches any keyword, include all topics from its source_term
            $kwLike = "%$searchQuery%";
            $kwStmt = $conn->prepare("SELECT DISTINCT source_term FROM generated_topics WHERE keywords IS NOT NULL AND keywords != '' AND keywords LIKE ? LIMIT 5");
            if ($kwStmt) {
                $kwStmt->bind_param('s', $kwLike);
                $kwStmt->execute();
                $kwRes = $kwStmt->get_result();
                while ($kw = $kwRes->fetch_assoc()) {
                    $src = trim((string)($kw['source_term'] ?? ''));
                    if ($src === '') continue;
                    $rtStmt = $conn->prepare("SELECT DISTINCT topic_name FROM generated_topics WHERE source_term = ? ORDER BY topic_name");
                    if ($rtStmt) {
                        $rtStmt->bind_param('s', $src);
                        $rtStmt->execute();
                        $rtRes = $rtStmt->get_result();
                        while ($t = $rtRes->fetch_assoc()) {
                            $topicName = $t['topic_name'] ?? '';
                            if ($topicName === '') continue;
                            $exists = false;
                            foreach ($searchResults as $existing) {
                                if (strcasecmp($existing['topic'], $topicName) === 0) { $exists = true; break; }
                            }
                            if ($exists) continue;
                            $sim = calculateSimilarity($searchQuery, $topicName);
                            // Ensure these related topics are displayed; set a floor of 60 for UI consistency
                            $searchResults[] = [
                                'topic' => $topicName,
                                'similarity' => max(60, round($sim, 1)),
                                'source' => 'related_by_source'
                            ];
                        }
                        $rtStmt->close();
                    }
                }
                $kwStmt->close();
            }

            usort($searchResults, function($a, $b) { return $b['similarity'] <=> $a['similarity']; });
            $showResults = true;
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
    if ($mcqCount > 10) $mcqCount = 10;
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
            $error = empty($topicsArray) ? "Please select at least one topic." : "Please specify the number of MCQs (1-10).";
        }
    } else {
        $error = "Please select at least one topic and specify the number of MCQs (1-10).";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google tag (gtag.js) -->
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Free Online MCQs Test Preparation for Competitive Exams | Ahmad Learning Hub</title>
    <!-- SEO & AI Optimization Meta Tags -->
    <meta name="description" content="Ahmad Learning Hub is a free online test preparation website. It offers practice tests, MCQs, and resources for competitive exams, job tests, and interviews.">
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

    <link rel="stylesheet" href="<?= ($assetBase ?? '') ?>css/main.css">
    <link rel="stylesheet" href="<?= ($assetBase ?? '') ?>css/mcqs_topic.css">
    <link rel="stylesheet" href="<?= ($assetBase ?? '') ?>css/ai_loader.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="<?= ($assetBase ?? '') ?>js/ai_loader.js" defer></script>
</head>
<body>
<?php include_once '../header.php'; ?>

<!-- SIDE SKYSCRAPER ADS (Right Only) -->
<?= renderAd('skyscraper', 'Place Right Skyscraper Banner Here', 'right', 'margin-top: 30%;') ?>

<div class="main-content">
    <div class="topic-search-container">
        <!-- TOP AD BANNER MOVED HERE FROM HEADER -->
        <?= renderAd('banner', 'Place Top Banner Here', 'ad-placement-top') ?>

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
                            max="10" 
                            value="<?= htmlspecialchars($_REQUEST['mcq_count'] ?? $_POST['mcq_count'] ?? 10) ?>" 
                            required
                            oninput="if(parseInt(this.value) > 10) this.value = 10; if(parseInt(this.value) < 1 && this.value !== '') this.value = 1;"
                        >
                        <button type="button" onclick="const input = document.getElementById('mcq_count'); input.value = Math.min(10, parseInt(input.value) + 1); input.dispatchEvent(new Event('input'));" class="mcq-count-btn"><i class="fas fa-plus"></i></button>
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
                        placeholder="Select your target topic (e.g., Organic Chemistry, Wave Optics...)" 
                        value="<?= htmlspecialchars($searchQuery) ?>"
                        autofocus
                    >
                    <button type="submit" class="search-btn" title="Initiate AI Search">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <?php include __DIR__ . '/../includes/ai_loader.php'; ?>

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
                    <?php endif; ?>
                </div>
                
                <div class="load-more-btn-container">
                    <button type="button" id="loadMoreTopicsBtn" class="btn-secondary">
                        <i class="fas fa-magic"></i> Explore Related Topics (AI)
                    </button>
                    <div id="loadMoreLoader" style="display:none;">
                         <div class="loader-progress">
                             <div class="loader-progress-bar" id="loadMoreProgressBar"></div>
                         </div>
                         <div class="load-more-text">Our AI is exploring the knowledge graph...</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <article class="seo-article-section">
            <h2 class="seo-section-title">Free Online MCQs Test Preparation for Competitive Exams</h2>
            <div class="seo-intro-text">
                <p><strong>Ahmad Learning Hub</strong> is a premier free online test preparation ecosystem. We provide comprehensive practice tests, expert-verified MCQs, and strategic resources designed for competitive exams, career job tests, and high-stakes interviews.</p>
            </div>
            
            <div class="seo-grid">
                <div class="seo-card">
                    <div class="seo-icon"><i class="fas fa-microscope"></i></div>
                    <h3 class="seo-card-title">Competitive Exam Focus</h3>
                    <p class="seo-card-text">Tailored resources for MDCAT, ECAT, NTS, and CSS exams. Our platform ensures you are prepared for the specific patterns and challenges of top-tier competitive tests.</p>
                </div>
                <div class="seo-card">
                    <div class="seo-icon"><i class="fas fa-shield-check"></i></div>
                    <h3 class="seo-card-title">Job Test Readiness</h3>
                    <p class="seo-card-text">Comprehensive question banks for government and private sector job recruitment tests. Practice with real-world scenarios to secure your dream career.</p>
                </div>
                <div class="seo-card">
                    <div class="seo-icon"><i class="fas fa-bolt"></i></div>
                    <h3 class="seo-card-title">Interview Mastery</h3>
                    <p class="seo-card-text">Access curated MCQ sets and resources specifically designed for technical and conceptual interview rounds across all major academic fields.</p>
                </div>
                <div class="seo-card">
                    <div class="seo-icon"><i class="fas fa-trophy"></i></div>
                    <h3 class="seo-card-title">Free Expert Resources</h3>
                    <p class="seo-card-text">High-quality educational content provided entirely for free. Join thousands of successful candidates who use Ahmad Learning Hub for daily study.</p>
                </div>
            </div>
            
            <div class="seo-footer">
                <p>The definitive <strong>MCQ Test Preparation Platform</strong>. Free practice tests and resources for <strong>MDCAT, ECAT, NTS,</strong> and National Board excellence.</p>
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
    showAILoader(
        [
            { label: 'Analyzing topics',        duration: 3500 },
            { label: 'Extracting key concepts',  duration: 3500 },
            { label: 'Designing MCQs',           duration: 3500 },
            { label: 'Validating difficulty',    duration: 3500 },
            { label: 'Finalizing paper',         duration: 3500 }
        ],
        'Our AI is synthesizing questions based on 2026 board standards…'
    );
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
    showAILoader(
        [
            { label: 'Analyzing topics',       duration: 3500 },
            { label: 'Extracting key concepts', duration: 3500 },
            { label: 'Designing MCQs',          duration: 3500 },
            { label: 'Validating difficulty',   duration: 3500 },
            { label: 'Finalizing paper',        duration: 3500 }
        ],
        'Our AI is synthesizing questions based on 2026 board standards\u2026'
    );
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
    
    const loadBtn = document.getElementById('loadMoreTopicsBtn');
    if (loadBtn) {
        loadBtn.addEventListener('click', function() {
            const btn = this;
            const loader = document.getElementById('loadMoreLoader');
            const progressBar = document.getElementById('loadMoreProgressBar');
            const searchQuery = document.getElementById('topic_search').value;
            const topicList = document.getElementById('topicList');
            const resultsHeader = document.getElementById('resultsHeader');
            if (!searchQuery) return;
            const excludeTopics = [];
            document.querySelectorAll('.topic-item[data-topic-data]').forEach(item => {
                try {
                    const d = JSON.parse(item.getAttribute('data-topic-data'));
                    if (d && d.topic) excludeTopics.push(d.topic);
                } catch(e) {}
            });
            btn.style.display = 'none';
            if (loader) loader.style.display = 'block';
            let width = 0;
            const interval = setInterval(() => {
                width = Math.min(width + 5, 90);
                if (progressBar) progressBar.style.width = width + '%';
            }, 300);
            fetch(`mcqs_topic.php?ajax=1&q=${encodeURIComponent(searchQuery)}&exclude=${encodeURIComponent(JSON.stringify(excludeTopics))}`)
            .then(r => r.json())
            .then(data => {
                if (progressBar) progressBar.style.width = '100%';
                const arr = (data && Array.isArray(data.topics)) ? data.topics : [];
                if (arr.length > 0) {
                    let added = 0;
                    arr.forEach(topic => {
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
                    if (loader) loader.style.display = 'none'; 
                    btn.style.display = 'inline-block';
                }, 500);
            });
        });
    }
});
</script>
<?php include '../footer.php'; ?>
</body>
</html>
