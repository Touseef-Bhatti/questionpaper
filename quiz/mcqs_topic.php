<?php
// mcqs_topic.php - Topic search page for MCQs
include '../db_connect.php';
require_once 'mcq_generator.php';
if (session_status() === PHP_SESSION_NONE) session_start();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic_search']) && !empty(trim($_POST['topic_search']))) {
    $searchQuery = trim($_POST['topic_search']);
    
    if (!empty($searchQuery)) {
        // Get all distinct topics from mcqs table
        $stmt = $conn->prepare("SELECT DISTINCT topic, class_id, book_id, chapter_id FROM mcqs WHERE topic IS NOT NULL AND topic != '' ORDER BY topic");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $allTopics = [];
        while ($row = $result->fetch_assoc()) {
            $allTopics[] = $row;
        }
        $stmt->close();
        
        // Filter topics with 50% or more similarity
        foreach ($allTopics as $topicRow) {
            $similarity = calculateSimilarity($searchQuery, $topicRow['topic']);
            if ($similarity >= 50) {
                $topicRow['similarity'] = round($similarity, 1);
                $searchResults[] = $topicRow;
            }
        }
        
        // Search AIGeneratedMCQs table
        $aiStmt = $conn->prepare("SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic IS NOT NULL AND topic != ''");
        $aiStmt->execute();
        $aiResult = $aiStmt->get_result();
        
        while ($row = $aiResult->fetch_assoc()) {
            $similarity = calculateSimilarity($searchQuery, $row['topic']);
            if ($similarity >= 50) {
                // Check if topic already exists in results
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
                        'class_id' => 0,
                        'book_id' => 0,
                        'chapter_id' => 0,
                        'similarity' => round($similarity, 1),
                        'source' => 'ai_generated'
                    ];
                }
            }
        }
        $aiStmt->close();
        
        // Sort by similarity (highest first)
        usort($searchResults, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        $showResults = true;
        
        // If few results found or Load More requested, search web using Gemini API
        if (count($searchResults) < 10 || isset($_POST['load_more'])) {
            // Try to get class_id and book_id from first selected topic (if any)
            $classId = 0;
            $bookId = 0;
            
            // Check if we have selected topics to get class/book info
            if (isset($_SESSION['selected_topics']) && !empty($_SESSION['selected_topics'])) {
                $firstSelected = json_decode($_SESSION['selected_topics'][0], true);
                if ($firstSelected && isset($firstSelected['class_id']) && isset($firstSelected['book_id'])) {
                    $classId = intval($firstSelected['class_id']);
                    $bookId = intval($firstSelected['book_id']);
                }
            }
            
            // If no class/book from selected topics, try to get from most common in database
            if ($classId == 0 || $bookId == 0) {
                $commonStmt = $conn->prepare("SELECT class_id, book_id, COUNT(*) as cnt FROM mcqs WHERE topic IS NOT NULL GROUP BY class_id, book_id ORDER BY cnt DESC LIMIT 1");
                $commonStmt->execute();
                $commonResult = $commonStmt->get_result();
                if ($commonRow = $commonResult->fetch_assoc()) {
                    $classId = intval($commonRow['class_id']);
                    $bookId = intval($commonRow['book_id']);
                }
                $commonStmt->close();
            }
            
            // If we still don't have class/book, get first available
            if ($classId == 0 || $bookId == 0) {
                $firstStmt = $conn->prepare("SELECT class_id, book_id FROM mcqs LIMIT 1");
                $firstStmt->execute();
                $firstResult = $firstStmt->get_result();
                if ($firstRow = $firstResult->fetch_assoc()) {
                    $classId = intval($firstRow['class_id']);
                    $bookId = intval($firstRow['book_id']);
                }
                $firstStmt->close();
            }
            
            // Search web for related topics using Gemini API
            $webSearchedTopics = searchTopicsWithGemini($searchQuery, $classId, $bookId);
            
            if (!empty($webSearchedTopics)) {
                // Add web-searched topics to search results (deduplicate)
                $existingTopics = array_column($searchResults, 'topic');
                foreach ($webSearchedTopics as $webTopic) {
                     $isDuplicate = false;
                     foreach ($existingTopics as $existing) {
                         if (strcasecmp($existing, $webTopic['topic']) === 0) {
                             $isDuplicate = true;
                             break;
                         }
                     }
                     if (!$isDuplicate) {
                         $searchResults[] = $webTopic;
                         $existingTopics[] = $webTopic['topic'];
                     }
                }
                $showResults = true;
                
                // Also auto-generate MCQs for the main search query topic
                // Removed class/book dependency check
                generateMCQsWithGemini($searchQuery, 10);
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
        $topicsData = []; // Store full topic data
        
        $classId = 0;
        $bookId = 0;
        $first = true;
        $mixed = false;

        foreach ($selectedTopics as $topicJson) {
            $topicData = json_decode($topicJson, true);
            if ($topicData && isset($topicData['topic'])) {
                $topicsArray[] = $topicData['topic'];
                $topicsData[] = $topicData;
                
                // Track if we have a consistent class/book
                if ($first) {
                    $classId = intval($topicData['class_id'] ?? 0);
                    $bookId = intval($topicData['book_id'] ?? 0);
                    $first = false;
                } else {
                    if ($classId !== intval($topicData['class_id'] ?? 0) || $bookId !== intval($topicData['book_id'] ?? 0)) {
                        $mixed = true;
                    }
                }
            }
        }
        
        if ($mixed) {
            // Use first topic's class/book for generation
            if (!empty($topicsData)) {
                $classId = intval($topicsData[0]['class_id'] ?? 0);
                $bookId = intval($topicsData[0]['book_id'] ?? 0);
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
            
            // 1. Check mcqs table
            if ($classId > 0 && $bookId > 0) {
                $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM mcqs WHERE topic IN ($placeholders) AND class_id = ? AND book_id = ?");
                $types = str_repeat('s', count($topicsArray)) . 'ii';
                $params = array_merge($topicsArray, [$classId, $bookId]);
                $checkStmt->bind_param($types, ...$params);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                if ($row = $checkResult->fetch_assoc()) {
                    $existingCount += intval($row['cnt']);
                }
                $checkStmt->close();
            }
            
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
                        $generatedMCQs = generateMCQsWithGemini($topic, $toGenerate);
                        $generatedTotal += count($generatedMCQs);
                    }
                }
            }
            
            // Redirect to quiz.php with multiple topics
            $topicsParam = urlencode(json_encode($topicsArray));
            header('Location: quiz.php?class_id=' . $classId . '&book_id=' . $bookId . '&topics=' . $topicsParam . '&mcq_count=' . $mcqCount);
            exit;
        } else {
            if (empty($topicsArray)) {
                $error = "Please select at least one topic.";
            } elseif ($classId == 0 || $bookId == 0) {
                $error = "Unable to determine class and book. Please ensure topics have class and book information.";
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
            max-width: 900px;
            margin: 0 auto;
            background: var(--white);
            padding: 40px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
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
                                'class_id' => $result['class_id'],
                                'book_id' => $result['book_id'],
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
                        <button type="button" onclick="triggerLoadMore()" class="btn secondary" style="padding: 10px 24px; cursor: pointer; background: #6366f1; color: white; border: none; border-radius: 8px; font-weight: 600;">
                            Load More Topics 
                        </button>
                    </div>
                    
                    <script>
                    function triggerLoadMore() {
                        const form = document.getElementById('searchForm');
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'load_more';
                        input.value = '1';
                        form.appendChild(input);
                        // Update selected topics before submit
                        if (typeof saveSelectedTopicsToSession === 'function') {
                            saveSelectedTopicsToSession();
                        }
                        form.submit();
                    }
                    </script>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Initialize selected topics from PHP session
let selectedTopics = [];
<?php
if (isset($_SESSION['selected_topics']) && is_array($_SESSION['selected_topics'])) {
    echo "selectedTopics = " . json_encode($_SESSION['selected_topics']) . ";\n";
}
?>

function toggleTopic(element, topicJson) {
    const topicData = JSON.parse(topicJson);
    const topicKey = topicData.topic + '_' + topicData.class_id + '_' + topicData.book_id;
    
    // Check if topic is already selected
    const index = selectedTopics.findIndex(t => {
        const tData = JSON.parse(t);
        return tData.topic === topicData.topic && tData.class_id === topicData.class_id && tData.book_id === topicData.book_id;
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
                return !(tData.topic === topicData.topic && tData.class_id === topicData.class_id && tData.book_id === topicData.book_id);
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
                    if (itemTopicData.topic === topicData.topic && itemTopicData.class_id === topicData.class_id && itemTopicData.book_id === topicData.book_id) {
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
                        if (itemTopicData.topic === topicData.topic && itemTopicData.class_id === topicData.class_id && itemTopicData.book_id === topicData.book_id) {
                            item.classList.add('selected');
                        }
                    } catch(e) {}
                }
            });
        } catch(e) {}
    });

    updateSelectedTopicsUI();
});
</script>
<?php include '../footer.php'; ?>
</body>
</html>
