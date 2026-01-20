<?php
// mcqs_topic.php - Topic search page for MCQs
include '../db_connect.php';
require_once 'mcq_generator.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Handle AJAX load more topics
if (isset($_POST['action']) && $_POST['action'] === 'load_more_topics') {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $searchQuery = $_POST['search_query'] ?? '';
    
    if (!empty($searchQuery)) {
        try {
            $relatedTopics = searchTopicsWithGemini($searchQuery);
            echo json_encode(['success' => true, 'topics' => $relatedTopics]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No search query provided']);
    }
    exit;
}

// Function to calculate similarity percentage between two strings
function calculateSimilarity($str1, $str2) {
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));
    
    if (empty($str1) || empty($str2)) {
        return 0;
    }
    
    // Use similar_text for better matching
    similar_text($str1, $str2, $percent);
    
    // Also check if one string contains the other
    if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false) {
        $percent = max($percent, 70); // Boost if one contains the other
    }
    
    return $percent;
}

// Handle search
$searchQuery = '';
$searchResults = [];
$showResults = false;
$studyLevel = $_POST['study_level'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic_search']) && !empty(trim($_POST['topic_search']))) {
    $searchQuery = trim($_POST['topic_search']);
    $studyLevel = $_POST['study_level'] ?? '';
    
    if (!empty($searchQuery)) {
        $cacheKey = null;
        $usedCache = false;
        
        if (isset($cacheManager) && $cacheManager) {
            $cacheKey = "topic_search_" . md5($searchQuery);
            $cached = $cacheManager->get($cacheKey);
            if ($cached !== false) {
                $cachedData = json_decode($cached, true);
                if (is_array($cachedData) && !empty($cachedData)) {
                    $searchResults = $cachedData;
                    $showResults = true;
                    $usedCache = true;
                }
            }
        }
        
        if (!$usedCache) {
            $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE topic IS NOT NULL AND topic != '' ORDER BY topic");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $allTopics = [];
            while ($row = $result->fetch_assoc()) {
                $allTopics[] = $row;
            }
            $stmt->close();
            
            foreach ($allTopics as $topicRow) {
                $similarity = calculateSimilarity($searchQuery, $topicRow['topic']);
                if ($similarity >= 50) {
                    $searchResults[] = [
                        'topic' => $topicRow['topic'],
                        'similarity' => round($similarity, 1)
                    ];
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
                        if (strcasecmp($existing['topic'], $row['topic']) === 0) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        $searchResults[] = [
                            'topic' => $row['topic'],
                            'similarity' => round($similarity, 1),
                            'source' => 'ai_generated'
                        ];
                    }
                }
            }
            $aiStmt->close();
            
            usort($searchResults, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            $showResults = true;
            
            if (empty($searchResults)) {
                $generatedTopics = generateMCQsWithGemini($searchQuery, 10, $studyLevel);
                if (!empty($generatedTopics)) {
                    $searchResults[] = [
                        'topic' => $searchQuery,
                        'similarity' => 100.0,
                        'source' => 'ai_generated'
                    ];
                    $showResults = true;
                }
            }
            
            if (isset($cacheManager) && $cacheManager && $cacheKey && !empty($searchResults)) {
                $cacheManager->setex($cacheKey, 86400, json_encode($searchResults));
            }
        }
    }
}

// Handle saving selected topics to session (via hidden field in search form)
if (isset($_POST['selected_topics_json']) && !empty($_POST['selected_topics_json'])) {
    $_SESSION['selected_topics'] = json_decode($_POST['selected_topics_json'], true) ?? [];
}

// Handle start quiz with selected topics
if (isset($_POST['start_quiz'])) {
    $selectedTopics = $_POST['selected_topics'] ?? [];
    $mcqCount = intval($_POST['mcq_count'] ?? 10);
    
    // Limit max MCQs to 50
    if ($mcqCount > 50) {
        $mcqCount = 50;
    }
    
    // Clear session after starting quiz
    unset($_SESSION['selected_topics']);
    
    if (!empty($selectedTopics) && $mcqCount > 0) {
        $topicsArray = [];

        foreach ($selectedTopics as $topicJson) {
            $topicData = json_decode($topicJson, true);
            if ($topicData && isset($topicData['topic'])) {
                $topicsArray[] = $topicData['topic'];
            }
        }
        
        if (!empty($topicsArray) && $mcqCount > 0) {
            // Check how many MCQs exist for these topics in mcqs table
            $placeholders = str_repeat('?,', count($topicsArray) - 1) . '?';
            $existingCount = 0;
            
            // Check if this is a hosting request
            $source = $_POST['source'] ?? '';
            $quizDuration = intval($_POST['quiz_duration'] ?? 10);
            
            if ($source === 'host') {
                // Store topics in session and redirect back to host page
                $_SESSION['host_quiz_topics'] = $topicsArray;
                
                // Build query params for other settings
                $redirectParams = [
                    'mcq_count' => $mcqCount,
                    'duration' => $quizDuration
                ];
                $queryString = http_build_query($redirectParams);
                
                header("Location: online_quiz_host_new.php?" . $queryString);
                exit;
            }
            
            // 1. Check mcqs table (any class/book)
            $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM mcqs WHERE topic IN ($placeholders)");
            $types = str_repeat('s', count($topicsArray));
            $params = $topicsArray;
            $checkStmt->bind_param($types, ...$params);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($row = $checkResult->fetch_assoc()) {
                $existingCount += intval($row['cnt']);
            }
            $checkStmt->close();
            
            // 2. Check AIGeneratedMCQs table
            $aiCheckStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM AIGeneratedMCQs WHERE topic IN ($placeholders)");
            $types = str_repeat('s', count($topicsArray));
            $params = $topicsArray;
            $aiCheckStmt->bind_param($types, ...$params);
            $aiCheckStmt->execute();
            $aiCheckResult = $aiCheckStmt->get_result();
            if ($row = $aiCheckResult->fetch_assoc()) {
                $existingCount += intval($row['cnt']);
            }
            $aiCheckStmt->close();
            
            // Generate MCQs if needed
            $neededCount = max(0, $mcqCount - $existingCount);
            
            if ($neededCount > 0) {
                // Distribute MCQs across topics
                $topicsCount = count($topicsArray);
                $mcqsPerTopic = ceil($neededCount / $topicsCount);
                
                $generatedTotal = 0;
                foreach ($topicsArray as $topic) {
                    if ($generatedTotal >= $neededCount) break;
                    
                    $toGenerate = min($mcqsPerTopic, $neededCount - $generatedTotal);
                    if ($toGenerate > 0) {
                        $generatedMCQs = generateMCQsWithGemini($topic, $toGenerate, $studyLevel);
                        $generatedTotal += count($generatedMCQs);
                    }
                }
            }
            
            // Redirect to quiz.php with multiple topics (topics-only)
            $topicsParam = urlencode(json_encode($topicsArray));
            header('Location: quiz.php?class_id=0&book_id=0&topics=' . $topicsParam . '&mcq_count=' . $mcqCount . '&study_level=' . urlencode($studyLevel));
            exit;
        } else {
            if (empty($topicsArray)) {
                $error = "Please select at least one topic.";
            } else {
                $error = "Please specify the number of MCQs (1-50).";
            }
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
    <title>Search MCQs by Topic - Ahmad Learning Hub</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/quiz_setup.css">
    <style>
        .topic-search-container {
            max-width: 60%;
            margin: 0 auto;
            background: var(--white);
            padding: 40px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }
        @media (max-width: 768px) {
            .topic-search-container {
                max-width: 90%;
                padding: 20px;
            }
        }
        .search-section {
            margin-bottom: 32px;
        }
        
        .search-input-wrapper {
            position: relative;
            margin-bottom: 24px;
        }
        
        .search-input {
            width: 100%;
            padding: 16px 20px;
            padding-right: 60px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }
        
        .search-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            background: var(--primary-hover);
        }
        
        .results-section {
            margin-top: 32px;
        }
        
        .results-header {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 20px;
        }
        
        .topic-list {
            display: grid;
            gap: 12px;
        }
        
        .topic-item {
            background: #f8fafc;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .topic-item:hover {
            border-color: var(--primary-color);
            background: #f5f3ff;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .topic-info {
            flex: 1;
        }
        
        .topic-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        
        .topic-meta {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .topic-similarity {
            background: var(--primary-color);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        
        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 16px;
        }
        
        .back-btn {
            margin-bottom: 24px;
            display: inline-block;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            color: var(--primary-hover);
        }
        
        .selected-topics-section {
            margin-bottom: 32px;
            padding: 24px;
            background: #f0f9ff;
            border: 2px solid #0ea5e9;
            border-radius: var(--radius-md);
            display: block;
        }
        
        .selected-topics-section.empty .selected-topics-list {
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-style: italic;
        }
        
        .selected-topics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .selected-topics-info {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 12px;
            padding: 12px;
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }
        
        .selected-topics-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-main);
        }
        
        .selected-topics-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .loader-progress {
            width: 260px;
            max-width: 80%;
            height: 8px;
            border-radius: 999px;
            background: #e5e7eb;
            margin: 24px auto 0 auto;
            overflow: hidden;
        }
        
        .loader-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #6366f1, #22c55e);
            transition: width 0.2s ease-out;
        }
        
        .selected-topic-badge {
            background: var(--primary-color);
            color: white;
            padding: 10px 16px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .remove-topic-btn {
            background: rgba(255, 255, 255, 0.3);
            border: none;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.2s ease;
        }
        
        .remove-topic-btn:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        
        .quiz-config-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 2px solid var(--border-color);
        }
        
        .mcq-count-input {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .mcq-count-input input {
            width: 120px;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1rem;
        }
        
        .start-quiz-btn {
            width: 100%;
            padding: 16px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .start-quiz-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.25);
        }
        
        .start-quiz-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        
        .add-topic-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            margin-left: 12px;
        }
        
        .add-topic-btn:hover {
            background: #059669;
        }
        
        .topic-item {
            position: relative;
        }
        
        .topic-item.selected {
            border-color: #10b981;
            background: #dcfce7;
        }
        
        .topic-item.selected::after {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #10b981;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }

        /* Honeycomb Loader Styles */
        @-webkit-keyframes honeycomb { 
            0%, 20%, 80%, 100% { opacity: 0; -webkit-transform: scale(0); transform: scale(0); } 
            30%, 70% { opacity: 1; -webkit-transform: scale(1); transform: scale(1); } 
        } 
        @keyframes honeycomb { 
            0%, 20%, 80%, 100% { opacity: 0; -webkit-transform: scale(0); transform: scale(0); } 
            30%, 70% { opacity: 1; -webkit-transform: scale(1); transform: scale(1); } 
        } 
        
        .honeycomb { 
            height: 24px; 
            position: relative; 
            width: 24px; 
            margin: 0 auto;
        } 
        
        .honeycomb div { 
            -webkit-animation: honeycomb 2.1s infinite backwards; 
            animation: honeycomb 2.1s infinite backwards; 
            background: var(--primary-color); 
            height: 12px; 
            margin-top: 6px; 
            position: absolute; 
            width: 24px; 
        } 
        
        .honeycomb div:after, .honeycomb div:before { 
            content: ''; 
            border-left: 12px solid transparent; 
            border-right: 12px solid transparent; 
            position: absolute; 
            left: 0; 
            right: 0; 
        } 
        
        .honeycomb div:after { 
            top: -6px; 
            border-bottom: 6px solid var(--primary-color); 
        } 
        
        .honeycomb div:before { 
            bottom: -6px; 
            border-top: 6px solid var(--primary-color); 
        } 
        
        .honeycomb div:nth-child(1) { -webkit-animation-delay: 0s; animation-delay: 0s; left: -28px; top: 0; } 
        .honeycomb div:nth-child(2) { -webkit-animation-delay: 0.1s; animation-delay: 0.1s; left: -14px; top: 22px; } 
        .honeycomb div:nth-child(3) { -webkit-animation-delay: 0.2s; animation-delay: 0.2s; left: 14px; top: 22px; } 
        .honeycomb div:nth-child(4) { -webkit-animation-delay: 0.3s; animation-delay: 0.3s; left: 28px; top: 0; } 
        .honeycomb div:nth-child(5) { -webkit-animation-delay: 0.4s; animation-delay: 0.4s; left: 14px; top: -22px; } 
        .honeycomb div:nth-child(6) { -webkit-animation-delay: 0.5s; animation-delay: 0.5s; left: -14px; top: -22px; } 
        .honeycomb div:nth-child(7) { -webkit-animation-delay: 0.6s; animation-delay: 0.6s; left: 0; top: 0; }
    </style>
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
        <a href="<?= $backLink ?>" class="back-btn"><?= $backText ?></a>
        
        <h1>Search MCQs by Topic</h1>
        <p class="desc">Search and add multiple topics to create your quiz. You can add as many topics as you want!</p>
        
        <?php if (isset($error)): ?>
            <div style="background: #fee2e2; border: 2px solid #ef4444; color: #991b1b; padding: 16px; border-radius: var(--radius-md); margin-bottom: 24px;">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Selected Topics Section (Moved above search) -->
        <div class="selected-topics-section empty" id="selectedTopicsSection">
            <div class="selected-topics-header">
                <div class="selected-topics-title">Selected Topics (<span id="selectedCount">0</span>)</div>
                <button type="button" class="btn secondary" onclick="clearAllTopics()" style="padding: 8px 16px; font-size: 0.9rem;">Clear All</button>
            </div>
            <div class="selected-topics-list" id="selectedTopicsList">
                <!-- Selected topics will be added here -->
            </div>
            
            <div class="quiz-config-section">
                <div class="mcq-count-input">
                    <label for="mcq_count" style="font-weight: 600;">Number of MCQs:</label>
                    <input 
                        type="number" 
                        id="mcq_count" 
                        name="mcq_count" 
                        min="1" 
                        max="50" 
                        value="<?= htmlspecialchars($_REQUEST['mcq_count'] ?? $_POST['mcq_count'] ?? 10) ?>" 
                        required
                        oninput="if(parseInt(this.value) > 50) this.value = 10; if(parseInt(this.value) < 1 && this.value !== '') this.value = 1;"
                    >
                    <div class="hint" style="margin: 0;">MCQs will be randomly selected from all your selected topics (Max: 50)</div>
                </div>
                
            <form method="POST" action="" id="startQuizForm">
                    <input type="hidden" name="mcq_count" id="hidden_mcq_count">
                    <input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>">
                    <input type="hidden" name="quiz_duration" value="<?= htmlspecialchars($quizDuration) ?>">
                    <input type="hidden" name="study_level" value="<?= htmlspecialchars($studyLevel) ?>">
                    <div id="hidden_topics_inputs"></div>
                    <button type="submit" name="start_quiz" class="start-quiz-btn" id="startQuizBtn" disabled>
                        Start Quiz with Selected Topics
                    </button>
                </form>
            </div>
        </div>

        <div class="search-section">
            <form method="POST" action="" id="searchForm">
                <!-- Hidden input to persist selected topics across searches -->
                <input type="hidden" name="selected_topics_json" id="selected_topics_json" value="<?= htmlspecialchars(isset($_SESSION['selected_topics']) ? json_encode($_SESSION['selected_topics']) : '') ?>">
                <input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>">
                <input type="hidden" name="quiz_duration" value="<?= htmlspecialchars($quizDuration) ?>">
                <input type="hidden" name="mcq_count" value="<?= htmlspecialchars($_REQUEST['mcq_count'] ?? $_POST['mcq_count'] ?? 10) ?>">
                
                <div style="margin-bottom: 12px; display: flex; align-items: center; gap: 12px;">
                    <label for="study_level" style="font-weight: 600;">Level:</label>
                    <select name="study_level" id="study_level" class="input inline" style="max-width: 260px;">
                        <option value="">Select level (optional)</option>
                        <option value="school" <?= $studyLevel === 'school' ? 'selected' : '' ?>>School</option>
                        <option value="college" <?= $studyLevel === 'college' ? 'selected' : '' ?>>College</option>
                        <option value="university" <?= $studyLevel === 'university' ? 'selected' : '' ?>>University</option>
                    </select>
                </div>
                
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        name="topic_search" 
                        id="topic_search"
                        class="search-input" 
                        placeholder="Enter topic name (e.g., 'Algebra', 'Photosynthesis', 'Newton Laws')" 
                        value="<?= htmlspecialchars($searchQuery) ?>"
                        autofocus
                    >
                    <button type="submit" class="search-btn">Search</button>
                </div>
            </form>
        </div>

        <!-- Inline Loader (Hidden by default) -->
        <div id="inlineLoader" style="display:none; padding: 40px 0; text-align: center;">
            <div class="honeycomb"> 
               <div></div> 
               <div></div> 
               <div></div> 
               <div></div> 
               <div></div> 
               <div></div> 
               <div></div> 
            </div>
            <div class="loader-progress">
                <div class="loader-progress-bar" id="loaderProgressBar"></div>
            </div>
            <div id="loaderText" style="margin-top: 16px; color: var(--text-muted); font-weight: 500; font-size: 1.1rem;">Searching...</div>
        </div>
        
        <?php if ($showResults && !empty($searchResults)): ?>
            <div class="results-section">
                <div class="results-header">
                    Found <?= count($searchResults) ?> topic(s)
                </div>
                
                <?php if (!empty($searchResults)): ?>
                    <div class="topic-list">
                        <?php foreach ($searchResults as $index => $result): 
                            $topicData = [
                                'topic' => $result['topic'],
                                'similarity' => $result['similarity']
                            ];
                            $topicJson = htmlspecialchars(json_encode($topicData), ENT_QUOTES);
                        ?>
                            <div class="topic-item" data-topic-data="<?= $topicJson ?>" onclick="toggleTopic(this, '<?= $topicJson ?>')">
                                <div class="topic-info">
                                    <div class="topic-name"><?= htmlspecialchars($result['topic']) ?></div>
                                </div>
                                <div class="topic-similarity"><?= $result['similarity'] ?>% match</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="text-align: center; margin-top: 24px;">
                        <button type="button" id="loadMoreTopicsBtn" class="search-btn" style="position: static; transform: none; min-width: 200px;">
                            Load More Topics (AI)
                        </button>
                        <div id="loadMoreLoader" style="display:none; width: 100%; max-width: 300px; margin: 10px auto;">
                             <div class="loader-progress" style="margin: 0 auto;">
                                 <div class="loader-progress-bar" id="loadMoreProgressBar" style="width:0%"></div>
                             </div>
                             <div style="text-align:center; margin-top:8px; color:var(--text-muted); font-size:0.9rem;">Searching for related topics...</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Loader Overlay Removed -->

<script>
let loaderProgressInterval;

function showLoader(title = 'Processing...', subtitle = '') {
    const loader = document.getElementById('inlineLoader');
    const titleEl = document.getElementById('loaderText');
    const progressBar = document.getElementById('loaderProgressBar');
    const resultsSection = document.querySelector('.results-section');
    const noResults = document.querySelector('.no-results');
    
    if (loader) {
        if (titleEl) titleEl.textContent = title;
        loader.style.display = 'block';
        
        if (resultsSection) resultsSection.style.display = 'none';
        if (noResults) noResults.style.display = 'none';
        
        if (progressBar) {
            progressBar.style.width = '0%';
            let progress = 0;
            if (loaderProgressInterval) clearInterval(loaderProgressInterval);
            loaderProgressInterval = setInterval(function() {
                progress += 5;
                if (progress >= 95) {
                    progress = 95;
                    clearInterval(loaderProgressInterval);
                }
                progressBar.style.width = progress + '%';
            }, 200);
        }
    }
}

// Initialize selected topics from PHP session
let selectedTopics = [];
<?php
if (isset($_SESSION['selected_topics']) && is_array($_SESSION['selected_topics'])) {
    echo "selectedTopics = " . json_encode($_SESSION['selected_topics']) . ";\n";
}
?>

function toggleTopic(element, topicJson) {
    const topicData = JSON.parse(topicJson);
    
    // Check if topic is already selected (topic-only)
    const index = selectedTopics.findIndex(t => {
        const tData = JSON.parse(t);
        return tData.topic === topicData.topic;
    });
    
    if (index > -1) {
        // Remove topic
        selectedTopics.splice(index, 1);
        element.classList.remove('selected');
    } else {
        // Add topic (allow mixed classes/books)
        selectedTopics.push(topicJson);
        element.classList.add('selected');
    }
    
    // Save to hidden field (will be saved to session on next form submit)
    saveSelectedTopicsToSession();
    updateSelectedTopicsUI();
}

function removeTopic(topicJson) {
    try {
        const topicData = JSON.parse(topicJson);
        selectedTopics = selectedTopics.filter(t => {
            try {
                const tData = JSON.parse(t);
                return !(tData.topic === topicData.topic);
            } catch(e) {
                return true;
            }
        });
        
        // Update visual state of topic items
        document.querySelectorAll('.topic-item').forEach(item => {
            const itemData = item.getAttribute('data-topic-data');
            if (itemData) {
                try {
                    const itemTopicData = JSON.parse(itemData);
                    if (itemTopicData.topic === topicData.topic) {
                        item.classList.remove('selected');
                    }
                } catch(e) {
                    // Skip invalid data
                }
            }
        });
        
        saveSelectedTopicsToSession();
        updateSelectedTopicsUI();
    } catch(e) {
        console.error('Error removing topic:', e);
    }
}

function clearAllTopics() {
    selectedTopics = [];
    document.querySelectorAll('.topic-item').forEach(item => {
        item.classList.remove('selected');
    });
    saveSelectedTopicsToSession();
    updateSelectedTopicsUI();
}

// Save topics before form submission
document.getElementById('searchForm')?.addEventListener('submit', function() {
    saveSelectedTopicsToSession();
    showLoader('Searching Topics...', 'We are searching for relevant topics. This might involve an AI lookup.');
});

function saveSelectedTopicsToSession() {
    // Update hidden field in search form
    const hiddenInput = document.getElementById('selected_topics_json');
    if (hiddenInput) {
        hiddenInput.value = JSON.stringify(selectedTopics);
    }
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
        section.classList.add('active');
        
        // Update hidden inputs for form submission
        hiddenInputs.innerHTML = '';
        selectedTopics.forEach((topicJson, index) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_topics[]';
            input.value = topicJson;
            hiddenInputs.appendChild(input);
        });
        
        hiddenMcqCount.value = mcqCountInput.value;
        
        // Update button state
        if (mcqCountInput.value > 0) {
            startBtn.disabled = false;
        } else {
            startBtn.disabled = true;
        }
        
        // Render selected topics
        list.innerHTML = '';
        selectedTopics.forEach((topicJson, index) => {
            try {
                const topicData = JSON.parse(topicJson);
                const badge = document.createElement('div');
                badge.className = 'selected-topic-badge';
                
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-topic-btn';
                removeBtn.title = 'Remove';
                removeBtn.textContent = '×';
                removeBtn.onclick = function() {
                    removeTopic(topicJson);
                };
                
                const span = document.createElement('span');
                span.textContent = topicData.topic;
                
                badge.appendChild(span);
                badge.appendChild(removeBtn);
                list.appendChild(badge);
            } catch(e) {
                console.error('Error rendering topic:', e);
            }
        });
    } else {
        section.classList.remove('active');
        startBtn.disabled = true;
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Update MCQ count input handler
const mcqCountInput = document.getElementById('mcq_count');
if (mcqCountInput) {
    mcqCountInput.addEventListener('input', function() {
        const startBtn = document.getElementById('startQuizBtn');
        const hiddenMcqCount = document.getElementById('hidden_mcq_count');
        
        if (hiddenMcqCount) {
            hiddenMcqCount.value = this.value;
        }
        
        if (selectedTopics.length > 0 && this.value > 0) {
            if (startBtn) startBtn.disabled = false;
        } else {
            if (startBtn) startBtn.disabled = true;
        }
    });
}

// Handle form submission to ensure data is set
document.getElementById('startQuizForm')?.addEventListener('submit', function(e) {
    // Ensure hidden inputs are populated
    const hiddenInputs = document.getElementById('hidden_topics_inputs');
    const hiddenMcqCount = document.getElementById('hidden_mcq_count');
    const mcqCountInput = document.getElementById('mcq_count');
    
    if (!hiddenInputs || !hiddenMcqCount || !mcqCountInput) {
        e.preventDefault();
        alert('Error: Form elements not found. Please refresh the page.');
        return false;
    }
    
    // Ensure topics are set
    if (selectedTopics.length === 0) {
        e.preventDefault();
        alert('Please select at least one topic.');
        return false;
    }
    
    // Ensure MCQ count is set
    if (!mcqCountInput.value || parseInt(mcqCountInput.value) <= 0) {
        e.preventDefault();
        alert('Please enter a valid number of MCQs.');
        return false;
    }
    
    // Update hidden inputs one more time before submit
    hiddenInputs.innerHTML = '';
    selectedTopics.forEach((topicJson) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_topics[]';
        input.value = topicJson;
        hiddenInputs.appendChild(input);
    });
    
    hiddenMcqCount.value = mcqCountInput.value;

    // Show loader
    showLoader('Generating Quiz...', 'We are creating unique MCQs for you. This usually takes 10-30 seconds depending on complexity.');
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedTopicsUI();
    
    // If we have selected topics from PHP (persistence), mark them as selected in the UI if they appear in search results
    selectedTopics.forEach(topicJson => {
        try {
            const topicData = JSON.parse(topicJson);
            document.querySelectorAll('.topic-item').forEach(item => {
                const itemData = item.getAttribute('data-topic-data');
                if (itemData) {
                    try {
                        const itemTopicData = JSON.parse(itemData);
                        if (itemTopicData.topic === topicData.topic) {
                            item.classList.add('selected');
                        }
                    } catch(e) {}
                }
            });
        } catch(e) {}
    });

    updateSelectedTopicsUI();
});

document.getElementById('loadMoreTopicsBtn')?.addEventListener('click', function() {
    const btn = this;
    const loader = document.getElementById('loadMoreLoader');
    const progressBar = document.getElementById('loadMoreProgressBar');
    const searchQuery = document.getElementById('topic_search').value;
    
    if (!searchQuery) return;
    
    // UI Loading State
    btn.style.display = 'none';
    if (loader) {
        loader.style.display = 'block';
        if (progressBar) {
            progressBar.style.width = '0%';
            let width = 0;
            const interval = setInterval(() => {
                if (width >= 90) clearInterval(interval);
                else {
                    width += 5;
                    progressBar.style.width = width + '%';
                }
            }, 300);
            
            // Store interval to clear it later
            btn.dataset.intervalId = interval;
        }
    }
    
    const formData = new FormData();
    formData.append('action', 'load_more_topics');
    formData.append('search_query', searchQuery);
    
    fetch('mcqs_topic.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (progressBar) progressBar.style.width = '100%';
        
        if (data.success && data.topics && data.topics.length > 0) {
            const list = document.querySelector('.topic-list');
            let addedCount = 0;
            
            data.topics.forEach(topic => {
                // Check if topic already exists in list (by text content)
                let exists = false;
                document.querySelectorAll('.topic-name').forEach(el => {
                    if (el.textContent.trim().toLowerCase() === topic.topic.trim().toLowerCase()) {
                        exists = true;
                    }
                });
                
                if (!exists) {
                    const topicData = {
                        topic: topic.topic,
                        similarity: topic.similarity || 85.0
                    };
                    const topicJson = JSON.stringify(topicData).replace(/"/g, '&quot;');
                    
                    const div = document.createElement('div');
                    div.className = 'topic-item';
                    div.setAttribute('data-topic-data', JSON.stringify(topicData));
                    div.onclick = function() { toggleTopic(this, JSON.stringify(topicData)); };
                    
                    div.innerHTML = `
                        <div class="topic-info">
                            <div class="topic-name">${topic.topic}</div>
                        </div>
                    `;
                    list.appendChild(div);
                    addedCount++;
                }
            });
            
            if (addedCount === 0) {
                alert('No new unique topics found.');
            }
            
            // Check if selected topics match any new ones
            if (typeof selectedTopics !== 'undefined') {
                selectedTopics.forEach(topicJson => {
                    try {
                        const topicData = JSON.parse(topicJson);
                        document.querySelectorAll('.topic-item').forEach(item => {
                            const itemData = item.getAttribute('data-topic-data');
                            if (itemData) {
                                try {
                                    const itemTopicData = JSON.parse(itemData);
                                    if (itemTopicData.topic === topicData.topic) {
                                        item.classList.add('selected');
                                    }
                                } catch(e) {}
                            }
                        });
                    } catch(e) {}
                });
            }
            
        } else {
            alert(data.error || 'No more topics found.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('An error occurred while fetching more topics.');
    })
    .finally(() => {
        setTimeout(() => {
            if (loader) loader.style.display = 'none';
            // Button stays hidden after search
            // btn.style.display = 'inline-block';
            if (btn.dataset.intervalId) clearInterval(Number(btn.dataset.intervalId));
            if (progressBar) progressBar.style.width = '0%';
        }, 500);
    });
});
</script>
<?php include '../footer.php'; ?>
</body>
</html>
