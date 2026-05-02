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
$topicLimit = 2; // Default for free
if (isset($_SESSION['user_id'])) {
    $currentSub = $subService->getCurrentSubscription($_SESSION['user_id']);
    $userPlan = $currentSub['plan_name'] ?? 'free';
    
    // Check if limit is explicitly defined in features or standard columns
    if (isset($currentSub['max_topics_per_quiz'])) {
        $topicLimit = intval($currentSub['max_topics_per_quiz']);
    } else {
        $topicLimit = ($userPlan === 'free') ? 2 : -1; // -1 for unlimited
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
                    $sim = calculateSimilarity($searchQuery, $row['topic_name']);
                    if ($sim >= 70) {
                        $existingTopics[] = [
                            'topic' => $row['topic_name'],
                            'keywords' => $row['keywords'] ?? '',
                            'similarity' => $sim,
                            'source' => 'database',
                            'type' => 'topic'
                        ];
                    }
                }
                $stmt->close();
            }

            // 2. Search chapter names
            $chapSql = "SELECT chapter_id, chapter_name, book_name, class_id FROM chapter WHERE chapter_name LIKE ? LIMIT 10";
            $chapStmt = $conn->prepare($chapSql);
            if ($chapStmt) {
                $chapStmt->bind_param('s', $termLike);
                $chapStmt->execute();
                $chapRes = $chapStmt->get_result();
                while ($row = $chapRes->fetch_assoc()) {
                    $sim = calculateSimilarity($searchQuery, $row['chapter_name']);
                    if ($sim >= 70) {
                        $existingTopics[] = [
                            'topic' => $row['chapter_name'] . " (" . $row['book_name'] . " - " . $row['class_id'] . ")",
                            'original_topic' => $row['chapter_name'],
                            'similarity' => $sim,
                            'source' => 'chapter_table',
                            'type' => 'chapter',
                            'chapter_id' => $row['chapter_id']
                        ];
                    }
                }
                $chapStmt->close();
            }

            // 3. AI Search (if needed or to supplement)
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
$studyLevel = $_POST['study_level'] ?? 'medium';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic_search']) && !empty(trim($_POST['topic_search']))) {
    $searchQuery = trim($_POST['topic_search']);
    $studyLevel = $_POST['study_level'] ?? 'medium';

    // Track user topic search history
    try {
        $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $insertHistoryStmt = $conn->prepare("INSERT INTO mcqs_topic_search_history (user_id, query_text) VALUES (?, ?)");
        if ($insertHistoryStmt) {
            $insertHistoryStmt->bind_param('is', $userId, $searchQuery);
            $insertHistoryStmt->execute();
            $insertHistoryStmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to log mcqs_topic search: " . $e->getMessage());
    }
    
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
                if ($similarity >= 70) {
                    $searchResults[] = [
                        'topic' => $topicRow['topic'], 
                        'similarity' => round($similarity, 1),
                        'type' => 'topic'
                    ];
                }
            }

            // Search chapters as well
            $chapStmt = $conn->prepare("SELECT chapter_id, chapter_name, book_name, class_id FROM chapter WHERE chapter_name LIKE ? LIMIT 50");
            if ($chapStmt) {
                $termLike = "%$searchQuery%";
                $chapStmt->bind_param('s', $termLike);
                $chapStmt->execute();
                $chapRes = $chapStmt->get_result();
                while ($row = $chapRes->fetch_assoc()) {
                    $similarity = calculateSimilarity($searchQuery, $row['chapter_name']);
                    if ($similarity >= 70) {
                        $displayName = $row['chapter_name'] . " (" . $row['book_name'] . " - " . $row['class_id'] . ")";
                        $searchResults[] = [
                            'topic' => $displayName,
                            'original_topic' => $row['chapter_name'],
                            'similarity' => round($similarity, 1),
                            'source' => 'chapter_table',
                            'type' => 'chapter',
                            'chapter_id' => $row['chapter_id']
                        ];
                    }
                }
                $chapStmt->close();
            }

            $aiStmt = $conn->prepare("SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic IS NOT NULL AND topic != ''");
            $aiStmt->execute();
            $aiResult = $aiStmt->get_result();
            while ($row = $aiResult->fetch_assoc()) {
                $similarity = calculateSimilarity($searchQuery, $row['topic']);
                if ($similarity >= 70) {
                    $exists = false;
                    foreach ($searchResults as $existing) {
                        if (strcasecmp($existing['topic'], $row['topic']) === 0) { $exists = true; break; }
                    }
                    if (!$exists) $searchResults[] = [
                        'topic' => $row['topic'], 
                        'similarity' => round($similarity, 1), 
                        'source' => 'ai_generated',
                        'type' => 'topic'
                    ];
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
                    if ($maxSim >= 70) {
                        $exists = false;
                        foreach ($searchResults as $existing) {
                            if (strcasecmp($existing['topic'], $row['topic_name']) === 0) { $exists = true; break; }
                        }
                        if (!$exists) $searchResults[] = [
                            'topic' => $row['topic_name'], 
                            'similarity' => round($maxSim, 1), 
                            'source' => 'generated_topics',
                            'type' => 'topic'
                        ];
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
                            // Ensure these related topics are displayed; set a floor of 70 for UI consistency
                            $searchResults[] = [
                                'topic' => $topicName,
                                'similarity' => max(70, round($sim, 1)),
                                'source' => 'related_by_source',
                                'type' => 'topic'
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
        $chapterIdsArray = [];
        foreach ($selectedTopics as $topicJson) {
            $topicData = json_decode($topicJson, true);
            if ($topicData) {
                if (isset($topicData['type']) && $topicData['type'] === 'chapter' && isset($topicData['chapter_id'])) {
                    $chapterIdsArray[] = $topicData['chapter_id'];
                } else if (isset($topicData['topic'])) {
                    $topicsArray[] = $topicData['topic'];
                }
            }
        }
        
        if ((!empty($topicsArray) || !empty($chapterIdsArray)) && $mcqCount > 0) {
            $source = $_POST['source'] ?? '';
            $quizDuration = intval($_POST['quiz_duration'] ?? 10);
            
            if ($source === 'host') {
                $_SESSION['host_quiz_topics'] = $topicsArray;
                $_SESSION['host_quiz_chapters'] = $chapterIdsArray;
                $queryString = http_build_query(['mcq_count' => $mcqCount, 'duration' => $quizDuration]);
                header("Location: online_quiz_host_new.php?" . $queryString);
                exit;
            }

            // Client may pass freshly generated MCQs (e.g. file upload) so quiz.php can load them
            // even if topic string normalization would miss the DB row.
            if (!empty($_POST['prefetch_mcqs_json']) && is_string($_POST['prefetch_mcqs_json'])) {
                $pj = $_POST['prefetch_mcqs_json'];
                if (strlen($pj) < 524288) {
                    $decodedPrefetch = json_decode($pj, true);
                    if (is_array($decodedPrefetch) && count($decodedPrefetch) > 0) {
                        $_SESSION['quiz_prefetch_mcqs'] = $decodedPrefetch;
                    }
                }
            }

            // Build SEO-friendly URL for topics
            $seoTopic = '';
            if (!empty($topicsArray)) {
                $seoTopic = implode('-', $topicsArray);
            }
            if (!empty($chapterIdsArray)) {
                $chapterPrefix = !empty($seoTopic) ? $seoTopic . '-' : '';
                $seoTopic = $chapterPrefix . 'Chapter-' . implode('-', $chapterIdsArray);
            }

            // Sanitize topic for URL
            $seoTopic = preg_replace('/[^a-zA-Z0-9]+/', '-', $seoTopic);
            $seoTopic = trim($seoTopic, '-');

            if (empty($seoTopic)) {
                $seoTopic = 'General';
            }

            $redirectUrl = $seoTopic . '-MCQs-Quiz?mcq_count=' . $mcqCount . '&study_level=' . urlencode($studyLevel);
            
            // If we have actual JSON topics or chapter IDs that might be complex, 
            // we can still pass them in query string for robustness, but the SEO part is in the path.
            if (!empty($topicsArray)) {
                $redirectUrl .= '&topics=' . urlencode(json_encode($topicsArray));
            }
            if (!empty($chapterIdsArray)) {
                $redirectUrl .= '&chapter_ids=' . implode(',', $chapterIdsArray);
            }

            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $error = (empty($topicsArray) && empty($chapterIdsArray)) ? "Please select at least one topic or chapter." : "Please specify the number of MCQs (1-10).";
        }
    } else {
        $error = "Please select at least one topic and specify the number of MCQs (1-10).";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
    <!-- Google tag (gtag.js) -->
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>

      
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Free Online MCQs Test Preparation for All Competitive Exams | Ahmad Learning Hub</title>
    <!-- SEO & AI Optimization Meta Tags -->
    <meta name="description" content="Ahmad Learning Hub is a free online test preparation website. It offers practice tests, MCQs, and resources for competitive exams, job tests, and interviews.">
    <meta name="keywords" content="topic wise MCQs, AI question finder, Ahmad Learning Hub search, board exam topics, science MCQs 2026, chemistry MCQs by topic, physics MCQs by topic">
    <meta name="author" content="Ahmad Learning Hub">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://ahmadlearninghub.com.pk/topic-wise-mcqs-test">
    <meta property="og:title" content="AI-Powered MCQ Search by Topic | Ahmad Learning Hub">
    <meta property="og:description" content="Type any educational topic and let our AI find or generate the best MCQs for your exam practice.">
    <meta property="og:image" content="https://ahmadlearninghub.com.pk/assets/images/topic-search-og.jpg">

    <!-- JSON-LD Structured Data for Search Function -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Search MCQs by Topic",
      "description": "An AI-powered tool to search and generate multiple choice questions based on specific educational topics.",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://ahmadlearninghub.com.pk/topic-wise-mcqs-test?topic_search={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
    </script>

    <link rel="stylesheet" href="<?= ($assetBase ?? '') ?>css/main.css">
    <link rel="stylesheet" href="<?= ($assetBase ?? '') ?>css/mcqs_topic.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
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
            <a href="javascript:void(0)" onclick="ignoreModeAndNavigate('online-mcqs-test-for-9th-and-10th-board-exams')" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Setup
            </a>
            <a href="javascript:void(0)" onclick="ignoreModeAndNavigate('online-mcqs-test-for-9th-and-10th-board-exams')" class="school-mode-btn">
                <i class="fas fa-school"></i> School Mode
            </a>
        </div>
        
       <h2>Search Any Topic & Generate Online MCQs Quiz Instantly</h2>
<p class="desc">
    Create AI-powered MCQs quizzes by searching any topic. Prepare tests like SAT, GRE, GCSE, A-Level, MDCAT, ECAT, and NTS.
</p>

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
                        <i class="fas fa-bolt"></i> Start  Quiz
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
                        <!-- medium by default -->
                        <button type="button" class="level-btn" id="btn-medium" data-level="medium" onclick="selectLevel('medium', this)"><i class="fas fa-balance-scale " ></i> Medium</button>
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
                        placeholder="Search topic (e.g., Organic Chemistry, Wave Optics...)" 
                        value="<?= htmlspecialchars($searchQuery) ?>"
                        autofocus
                    >
                    <button type="button" class="file-upload-btn" title="Upload File" onclick="checkLoginAndOpenUpload()">
                        <i class="fas fa-file-upload"></i>
                    </button>
                    <button type="submit" class="search-btn" title="Initiate AI Search">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- FILE UPLOAD TRIGGER CARD -->
        <div class="text-upload-trigger" id="textUploadTrigger" onclick="checkLoginAndOpenUpload()">
            <div class="text-upload-trigger-icon">
                <i class="fas fa-file-upload"></i>
            </div>
            <div class="text-upload-trigger-content">
                <div class="text-upload-trigger-title">Upload a File & Generate MCQs Quiz</div>
                <div class="text-upload-trigger-desc">PDF, Word, PowerPoint, or an image — AI builds MCQs only from your file (max 10 MB)</div>
            </div>
            <div class="text-upload-trigger-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>

        <!-- FILE UPLOAD MODAL (MCQs only for quiz page) -->
        <div class="text-upload-modal" id="textUploadModal">
            <div class="text-upload-modal-card">
                <div class="text-upload-modal-header">
                    <div>
                        <h3 class="text-upload-modal-title"><i class="fas fa-magic"></i> AI File → MCQs Quiz</h3>
                        <p class="text-upload-modal-subtitle">Upload one file; questions are generated only from its content</p>
                    </div>
                    <button type="button" class="text-upload-close-btn" onclick="closeTextUploadModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="text-upload-modal-body">
                    <div class="text-upload-file-wrapper">
                        <label class="text-upload-file-label" for="documentUploadInput">
                            <i class="fas fa-paperclip"></i> Choose file
                        </label>
                        <input type="file" id="documentUploadInput" class="text-upload-file-input" name="document" accept=".pdf,.doc,.docx,.ppt,.pptx,.png,.jpg,.jpeg,.webp,.gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,image/*">
                        <div class="text-upload-file-meta">
                            <span id="documentUploadFilename" class="text-upload-filename">No file selected</span>
                            <span class="text-upload-hint" title="Legacy .doc/.ppt may need to be saved as PDF or DOCX/PPTX if upload fails">PDF, DOC, DOCX, PPT, PPTX, PNG, JPG, WEBP, GIF · max 10 MB</span>
                        </div>
                    </div>

                    <div class="text-upload-config">
                        <div class="text-upload-config-title">Number of MCQs</div>
                        <div class="text-upload-types">
                            <label class="text-upload-type-checkbox" style="width:100%;">
                                <span class="text-upload-type-label mcq-label"><i class="fas fa-list-ul"></i> MCQs to Generate</span>
                                <div class="text-upload-count-control">
                                    <button type="button" onclick="adjustTextCount('textCountMcqs', -1)">−</button>
                                    <input type="number" id="textCountMcqs" value="10" min="1" max="30">
                                    <button type="button" onclick="adjustTextCount('textCountMcqs', 1)">+</button>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="text-upload-error" id="textUploadError" style="display:none;"></div>

                    <div class="text-upload-progress" id="textUploadProgress" style="display:none;">
                        <div class="text-upload-progress-bar-track">
                            <div class="text-upload-progress-bar" id="textProgressBar"></div>
                        </div>
                        <div class="text-upload-progress-text" id="textProgressText">Reading your file...</div>
                    </div>

                    <div class="text-upload-results" id="textUploadResults" style="display:none;"></div>
                </div>
                <div class="text-upload-modal-footer">
                    <button type="button" class="text-upload-cancel-btn" onclick="closeTextUploadModal()">Cancel</button>
                    <button type="button" class="text-upload-generate-btn" id="textGenerateBtn" onclick="generateMcqsFromFile()">
                        <i class="fas fa-bolt"></i> Generate MCQs
                    </button>
                </div>
            </div>
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
                
                <div class="topics-scroll-container">
                    <div class="topic-list" id="topicList">
                        <?php foreach ($searchResults as $index => $result): 
                            $topicData = [
                                'topic' => $result['topic'], 
                                'similarity' => $result['similarity'],
                                'type' => $result['type'] ?? 'topic'
                            ];
                            if (isset($result['chapter_id'])) $topicData['chapter_id'] = $result['chapter_id'];
                            if (isset($result['original_topic'])) $topicData['original_topic'] = $result['original_topic'];
                            
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
                </div>
                
                <div class="load-more-btn-container">
                     <div id="loadMoreLoader" style="display:none;">
                         <div class="loader-progress">
                             <div class="loader-progress-bar" id="loadMoreProgressBar"></div>
                         </div>
                         <div class="load-more-text">Our AI is exploring the knowledge graph...</div>
                    </div>
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 16px;">
                        <button type="button" id="loadMoreTopicsBtn" class="btn-secondary" style="width: 100%; max-width: 400px;">
                            <i class="fas fa-magic"></i> Explore More Topics (AI)
                        </button>
                        <button type="button" class="start-quiz-btn startQuizBtnResults" style="width: 100%; max-width: 400px;" onclick="document.getElementById('startQuizBtn').click()" disabled>
                            <i class="fas fa-bolt"></i> Start Quiz
                        </button>
                    </div>
                   
                </div>
            </div>
        <?php endif; ?>
        <br>
        <br><br>
        <br><br><br>
        <article class="seo-article-section">
    <h2 class="seo-section-title">AI-Powered Online MCQs Quiz Generator for Global Exams & Subjects</h2>
    
    <div class="seo-intro-text">
        <p>
            <strong>Ahmad Learning Hub</strong> is a smart AI-based online MCQs quiz platform where students can search any topic and instantly generate quizzes. Whether you're preparing for school exams, competitive tests, or international exams like SAT, GRE, GCSE, A-Levels, or IELTS, our AI creates topic-based MCQs quizzes tailored to your needs.
        </p>
    </div>
    
    <div class="seo-grid">
        
        <div class="seo-card">
            <div class="seo-icon"><i class="fas fa-search"></i></div>
            <h3 class="seo-card-title">Search Any Topic – Instant Quiz</h3>
            <p class="seo-card-text">
                Enter any topic like algebra, biology, programming, physics, or English grammar, and our AI will automatically generate related topics and create an online MCQs quiz instantly. Perfect for quick practice and concept revision.
            </p>
        </div>
        
        <div class="seo-card">
            <div class="seo-icon"><i class="fas fa-robot"></i></div>
            <h3 class="seo-card-title">AI Generated MCQs for All Subjects</h3>
            <p class="seo-card-text">
                Our AI generates high-quality MCQs for all subjects including Math, Physics, Chemistry, Biology, Computer Science, and General Knowledge. Practice unlimited online quizzes with accurate and exam-focused questions.
            </p>
        </div>
        
        <div class="seo-card">
            <div class="seo-icon"><i class="fas fa-globe"></i></div>
            <h3 class="seo-card-title">Global Exam Preparation</h3>
            <p class="seo-card-text">
                Prepare for international exams like SAT, GRE, GMAT, GCSE, A-Level, AP Exams, and European entry tests. Also suitable for MDCAT, ECAT, NTS, CSS, and other competitive exams worldwide.
            </p>
        </div>
        
        <div class="seo-card">
            <div class="seo-icon"><i class="fas fa-chart-line"></i></div>
            <h3 class="seo-card-title">Practice, Test & Improve</h3>
            <p class="seo-card-text">
                Take unlimited online MCQs tests, improve your accuracy, and strengthen weak topics. Our AI-driven quiz system helps students from Pakistan, UK, USA, and Europe prepare smarter and faster.
            </p>
        </div>
        
    </div>
    
    <div class="seo-footer">
        <p>
            The ultimate <strong>AI MCQs Quiz Generator</strong> for students worldwide. Practice online tests for <strong>SAT, GRE, GCSE, A-Level, MDCAT, ECAT, NTS, CSS</strong> and more. Search any topic and start your quiz instantly.
        </p>
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
const isLoggedIn = <?= json_encode(isset($_SESSION['user_id'])) ?>;
let isPremium = <?= json_encode($isPremium) ?>;
const topicLimit = <?= json_encode($topicLimit) ?>;

function ignoreModeAndNavigate(url) {
    localStorage.setItem('user_type_preference', 'School');
    window.location.href = url;
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
    const startBtns = document.querySelectorAll('.start-quiz-btn');
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
        startBtns.forEach(btn => btn.disabled = !(mcqCountInput.value > 0));
        
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
        startBtns.forEach(btn => btn.disabled = true);
    }
}

document.getElementById('mcq_count')?.addEventListener('input', function() {
    document.getElementById('hidden_mcq_count').value = this.value;
    const startBtns = document.querySelectorAll('.start-quiz-btn');
    startBtns.forEach(btn => btn.disabled = !(selectedTopics.length > 0 && this.value > 0));
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

    // Build SEO URL
    const topicsArr = [];
    const chapterIds = [];
    selectedTopics.forEach(tJson => {
        const t = JSON.parse(tJson);
        if (t.type === 'chapter' && t.chapter_id) {
            chapterIds.push(t.chapter_id);
        } else if (t.topic) {
            topicsArr.push(t.topic);
        }
    });

    let seoTopic = topicsArr.join('-');
    if (chapterIds.length > 0) {
        seoTopic += (seoTopic ? '-' : '') + 'Chapter-' + chapterIds.join('-');
    }
    seoTopic = seoTopic.replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    if (!seoTopic) seoTopic = 'General';

    const seoUrl = `${seoTopic}-MCQs-Quiz`;
    this.action = seoUrl;

    // We use preventDefault and this.submit() to allow the loader to show and ensure the action is set
    e.preventDefault();

    if (typeof showAILoader === 'function') {
        showAILoader(
            [
                { label: 'Analyzing topics',       duration: 1500 },
                { label: 'Extracting key concepts', duration: 1500 },
                { label: 'Designing MCQs',          duration: 1500 },
                { label: 'Validating difficulty',   duration: 1500 },
                { label: 'Finalizing paper',        duration: 1500 }
            ],
            'Our AI is synthesizing questions based on 2026 board standards\u2026'
        );
        
        setTimeout(() => {
            this.submit();
        }, 1000);
    } else {
        this.submit();
    }
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
            // Prevent rapid double clicks
            if (btn.disabled) return;
            
            // Disable button for 10 seconds to avoid double click
            btn.disabled = true;
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exploring...';
            btn.style.opacity = '0.7';
            btn.style.cursor = 'not-allowed';

            setTimeout(() => { 
                btn.disabled = false;
                btn.innerHTML = originalContent;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            }, 10000);

            const loader = document.getElementById('loadMoreLoader');
            const progressBar = document.getElementById('loadMoreProgressBar');
            const searchQuery = document.getElementById('topic_search').value;
            const topicList = document.getElementById('topicList');
            const resultsHeader = document.getElementById('resultsHeader');
            if (!searchQuery) {
                btn.disabled = false;
                btn.innerHTML = originalContent;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
                return;
            }
            const excludeTopics = [];
            document.querySelectorAll('.topic-item[data-topic-data]').forEach(item => {
                try {
                    const d = JSON.parse(item.getAttribute('data-topic-data'));
                    if (d && d.topic) excludeTopics.push(d.topic);
                } catch(e) {}
            });
            
            if (loader) loader.style.display = 'block';
            let width = 0;
            const interval = setInterval(() => {
                width = Math.min(width + 5, 90);
                if (progressBar) progressBar.style.width = width + '%';
            }, 300);
            fetch(`topic-wise-mcqs-test?ajax=1&q=${encodeURIComponent(searchQuery)}&exclude=${encodeURIComponent(JSON.stringify(excludeTopics))}`)
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
                        
                        // Build comprehensive topic data
                        const tData = {
                            topic: topicName, 
                            similarity: similarity,
                            type: topic.type || 'topic'
                        };
                        if (topic.chapter_id) tData.chapter_id = topic.chapter_id;
                        if (topic.original_topic) tData.original_topic = topic.original_topic;

                        const topicJson = JSON.stringify(tData);
                        const div = document.createElement('div');
                        div.className = 'topic-item' + (isSelected ? ' selected' : '');
                        div.setAttribute('data-topic-data', topicJson);
                        div.onclick = () => toggleTopic(div, topicJson);
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
                }, 500);
            });
        });
    }
});

// ================================================================
// TEXT UPLOAD MODAL (MCQs Quiz)
// ================================================================
function checkLoginAndOpenUpload() {
    if (!isLoggedIn) {
        if (typeof showLoginModal === 'function') {
            showLoginModal();
        } else {
            window.location.href = '../login.php';
        }
        return;
    }
    openTextUploadModal();
}

function openTextUploadModal() {
    const modal = document.getElementById('textUploadModal');
    const fin = document.getElementById('documentUploadInput');
    const fn = document.getElementById('documentUploadFilename');
    if(fin) fin.value = '';
    if(fn) fn.textContent = 'No file selected';
    if(modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => modal.classList.add('active'), 10);
    }
}

function closeTextUploadModal() {
    const modal = document.getElementById('textUploadModal');
    if(modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }, 300);
    }
}

function adjustTextCount(inputId, delta) {
    const inp = document.getElementById(inputId);
    if(!inp) return;
    let val = parseInt(inp.value) || 1;
    val = Math.max(parseInt(inp.min)||1, Math.min(val + delta, parseInt(inp.max)||30));
    inp.value = val;
}

document.getElementById('documentUploadInput')?.addEventListener('change', function() {
    const fn = document.getElementById('documentUploadFilename');
    if(fn) fn.textContent = (this.files && this.files[0]) ? this.files[0].name : 'No file selected';
});

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

async function generateMcqsFromFile() {
    const fileInput = document.getElementById('documentUploadInput');
    const file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    const errorEl = document.getElementById('textUploadError');
    const progressEl = document.getElementById('textUploadProgress');
    const progressBar = document.getElementById('textProgressBar');
    const progressText = document.getElementById('textProgressText');
    const resultsEl = document.getElementById('textUploadResults');
    const genBtn = document.getElementById('textGenerateBtn');

    errorEl.style.display = 'none';
    resultsEl.style.display = 'none';

    if(!file) {
        errorEl.textContent = 'Please choose a file to upload.';
        errorEl.style.display = 'block';
        return;
    }
    if(file.size > 10 * 1024 * 1024) {
        errorEl.textContent = 'File is too large. Maximum size is 10 MB.';
        errorEl.style.display = 'block';
        return;
    }

    progressEl.style.display = 'block';
    genBtn.disabled = true;
    genBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

    const progressSteps = [
        'Reading your file...',
        'Sending to AI...',
        'Crafting MCQs...',
        'Validating answers...',
        'Finalizing quiz...'
    ];
    let stepIdx = 0;
    let pVal = 0;
    const pInterval = setInterval(() => {
        pVal = Math.min(pVal + 3, 92);
        if(progressBar) progressBar.style.width = pVal + '%';
        if(pVal % 18 === 0 && stepIdx < progressSteps.length - 1) {
            stepIdx++;
            if(progressText) progressText.textContent = progressSteps[stepIdx];
        }
    }, 400);

    try {
        const formData = new FormData();
        formData.append('document', file);
        formData.append('question_types[]', 'mcqs');
        formData.append('count_mcqs', document.getElementById('textCountMcqs')?.value || 10);

        const res = await fetch('../questionPaperFromTopic/generate_from_upload.php', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();
        clearInterval(pInterval);
        if(progressBar) progressBar.style.width = '100%';

        if(data.success && data.mcqs?.length > 0) {
            progressEl.style.display = 'none';
            window._generatedMcqs = data.mcqs;
            window._generatedMcqTopic = data.detected_topic || '';
            startTextQuiz();
        } else {
            progressEl.style.display = 'none';
            errorEl.textContent = data.error || 'Failed to generate MCQs. Please try again.';
            errorEl.style.display = 'block';
        }
    } catch(e) {
        clearInterval(pInterval);
        progressEl.style.display = 'none';
        errorEl.textContent = 'Network error. Please check your connection and try again.';
        errorEl.style.display = 'block';
        console.error('Generate MCQs from file error:', e);
    } finally {
        genBtn.disabled = false;
        genBtn.innerHTML = '<i class="fas fa-bolt"></i> Generate MCQs';
    }
}

function displayMcqResults(data) {
    const resultsEl = document.getElementById('textUploadResults');
    if(!resultsEl) return;

    let html = '<div class="text-results-header"><i class="fas fa-check-circle"></i> ' + data.mcqs.length + ' MCQs Generated!</div>';
    if(data.detected_topic) {
        html += '<div class="text-preview-section" style="margin-bottom:12px;color:var(--text-muted);"><strong>Topic:</strong> ' + escapeHtml(data.detected_topic) + '</div>';
    }
    if(data.recheck_status === 'pending') {
        html += '<div class="text-preview-section" style="margin-bottom:10px;font-size:0.9rem;color:var(--text-muted);"><i class="fas fa-sync-alt fa-spin" style="margin-right:6px;"></i> Verifying MCQs and adding explanations in the background — you can start the quiz now.</div>';
    }
    html += '<div class="text-results-preview">';
    data.mcqs.slice(0, 3).forEach((q, i) => {
        html += '<div class="text-preview-section"><strong>Q' + (i+1) + ':</strong> ' + escapeHtml(String(q.question || '')) + '</div>';
    });
    if(data.mcqs.length > 3) html += '<div class="text-preview-section" style="color:var(--text-muted);">...and ' + (data.mcqs.length - 3) + ' more</div>';
    html += '</div>';
    html += '<button type="button" class="text-upload-submit-btn" onclick="startTextQuiz()"><i class="fas fa-bolt"></i> Start Quiz Now</button>';

    resultsEl.innerHTML = html;
    resultsEl.style.display = 'block';
    window._generatedMcqs = data.mcqs;
    window._generatedMcqTopic = data.detected_topic || '';
}

function startTextQuiz() {
    const mcqs = window._generatedMcqs;
    if(!mcqs || mcqs.length === 0) return;

    sessionStorage.setItem('text_generated_mcqs', JSON.stringify(mcqs));

    const topicName = window._generatedMcqTopic || 'AI Generated from Upload';
    const topicData = JSON.stringify({topic: topicName, similarity: 100, type: 'topic'});

    // Clear and add this topic
    selectedTopics = [topicData];
    updateSelectedTopicsUI();

    // Show AI loader and submit
    if(typeof showAILoader === 'function') {
        showAILoader([
            { label: 'Preparing quiz', duration: 2000 },
            { label: 'Loading questions', duration: 2000 }
        ], 'Setting up your quiz...');
    }

    const mcqCount = mcqs.length > 10 ? 10 : mcqs.length;
    const form = document.getElementById('startQuizForm');
    document.getElementById('hidden_mcq_count').value = mcqCount;

    const hiddenInputs = document.getElementById('hidden_topics_inputs');
    hiddenInputs.innerHTML = '';
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'selected_topics[]'; inp.value = topicData;
    hiddenInputs.appendChild(inp);
    
    const startInp = document.createElement('input');
    startInp.type = 'hidden'; startInp.name = 'start_quiz'; startInp.value = '1';
    form.appendChild(startInp);

    let prefetchInp = document.getElementById('prefetch_mcqs_json');
    if (!prefetchInp) {
        prefetchInp = document.createElement('input');
        prefetchInp.type = 'hidden';
        prefetchInp.name = 'prefetch_mcqs_json';
        prefetchInp.id = 'prefetch_mcqs_json';
        form.appendChild(prefetchInp);
    }
    prefetchInp.value = JSON.stringify(mcqs);

    closeTextUploadModal();

    // Set SEO action
    let seoTopic = topicName.replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    if (!seoTopic) seoTopic = 'General';
    form.action = `${seoTopic}-MCQs-Quiz`;

    setTimeout(() => form.submit(), 800);
}
</script>

</body>
<?php include '../footer.php'; ?>
</html>
