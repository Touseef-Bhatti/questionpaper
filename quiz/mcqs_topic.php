<?php
// mcqs_topic.php - Topic search page for MCQs
include '../db_connect.php';
require_once 'mcq_generator.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Handle AJAX load more topics
if (isset($_POST['action']) && $_POST['action'] === 'load_more_topics') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $searchQuery = $_POST['search_query'] ?? '';
    
    if (!empty($searchQuery)) {
        try {
            $aiTopics = searchTopicsWithGemini($searchQuery);
            $existingTopics = [];
            $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE topic LIKE ? LIMIT 10");
            if ($stmt) {
                $searchPattern = '%' . $searchQuery . '%';
                $stmt->bind_param('s', $searchPattern);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    if (!empty($row['topic']) && !in_array($row['topic'], array_column($existingTopics, 'topic'))) {
                        $existingTopics[] = ['topic' => $row['topic'], 'similarity' => 80.0, 'source' => 'mcqs'];
                    }
                }
                $stmt->close();
            }
            $aiMcqTopics = [];
            $aiStmt = $conn->prepare("SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic LIKE ? LIMIT 10");
            if ($aiStmt) {
                $searchPattern = '%' . $searchQuery . '%';
                $aiStmt->bind_param('s', $searchPattern);
                $aiStmt->execute();
                $result = $aiStmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    if (!empty($row['topic'])) {
                        $exists = false;
                        foreach ($existingTopics as $existing) {
                            if (strcasecmp($existing['topic'], $row['topic']) === 0) {
                                $exists = true; break;
                            }
                        }
                        if (!$exists && !in_array($row['topic'], array_column($aiMcqTopics, 'topic'))) {
                            $aiMcqTopics[] = ['topic' => $row['topic'], 'similarity' => 80.0, 'source' => 'ai_generated_mcqs'];
                        }
                    }
                }
                $aiStmt->close();
            }
            $combinedTopics = array_merge($aiTopics, $existingTopics, $aiMcqTopics);
            $uniqueTopics = [];
            $seenTopics = [];
            foreach ($combinedTopics as $topic) {
                $topicLower = strtolower(trim($topic['topic']));
                if (!in_array($topicLower, $seenTopics)) {
                    $uniqueTopics[] = $topic;
                    $seenTopics[] = $topicLower;
                }
            }
            usort($uniqueTopics, function($a, $b) {
                return ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0);
            });
            echo json_encode(['success' => true, 'topics' => $uniqueTopics]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No search query provided']);
    }
    exit;
}

function calculateSimilarity($str1, $str2) {
    $str1 = strtolower(trim($str1)); $str2 = strtolower(trim($str2));
    if (empty($str1) || empty($str2)) return 0;
    similar_text($str1, $str2, $percent);
    if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false) $percent = max($percent, 70);
    return $percent;
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
            usort($searchResults, function($a, $b) { return $b['similarity'] <=> $a['similarity']; });
            $showResults = true;
            
            if (empty($searchResults)) {
                $generatedTopics = generateMCQsWithGemini($searchQuery, 10, $studyLevel);
                if (!empty($generatedTopics)) {
                    $searchResults[] = ['topic' => $searchQuery, 'similarity' => 100.0, 'source' => 'ai_generated'];
                    $showResults = true;
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
        <a href="<?= $backLink ?>" class="back-btn"><?= $backText ?></a>
        
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

        <!-- Inline Loader -->
        <div id="inlineLoader" style="display:none; padding: 40px 0;">
            <div class="honeycomb"> 
               <div></div><div></div><div></div><div></div><div></div><div></div><div></div> 
            </div>
            <div class="loader-progress">
                <div class="loader-progress-bar" id="loaderProgressBar"></div>
            </div>
            <div id="loaderText" style="text-align: center; color: var(--text-muted); font-weight: 700; font-size: 1.1rem;">Searching Topics...</div>
        </div>

        <?php if ($showResults && !empty($searchResults)): ?>
            <div class="results-section">
                <div class="results-header">
                    ✨ Found <?= count($searchResults) ?> search results
                </div>
                
                <div class="topic-list">
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
                </div>
                
                <div style="text-align: center; margin-top: 32px;">
                    <button type="button" id="loadMoreTopicsBtn" class="btn btn-secondary" style="min-width: 280px; border-width: 2px;color:var(--primary-dark);border-color:var(--primary-dark); ">
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
        
        <!-- Additional SEO Content for Topic Search -->
        <article style="margin-top: 60px; border-top: 1px solid #e2e8f0; padding-top: 40px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; text-align: left;">
                <div style="background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #f1f5f9;">
                    <h3 style="color: var(--primary); margin-top: 0; font-size: 1.15rem; font-weight: 800;">🧬 Topic-Wise Precision</h3>
                    <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6; margin-bottom: 0;">Generic tests can be overwhelming. Our AI helps you break down your syllabus into bite-sized topics like 'Quantum Mechanics', 'Organic nomenclature', or 'Cellular Respiration' for focused revision.</p>
                </div>
                <div style="background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #f1f5f9;">
                    <h3 style="color: var(--primary); margin-top: 0; font-size: 1.15rem; font-weight: 800;">🤖 AI-Generated Variation</h3>
                    <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6; margin-bottom: 0;">If a topic doesn't exist in our massive database, our Gemini AI engine generates high-quality, syllabus-compliant MCQs on the fly to fulfill your request.</p>
                </div>
                <div style="background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #f1f5f9;">
                    <h3 style="color: var(--primary); margin-top: 0; font-size: 1.15rem; font-weight: 800;">📈 Exam Compliance</h3>
                    <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6; margin-bottom: 0;">Every question generated follows the 2026 paper pattern for Federal Board, BISE, and CSS standard competitive exams across Pakistan.</p>
                </div>
            </div>
            
            <div style="margin-top: 40px; text-align: center; color: #475569; font-size: 0.95rem;">
                <p>Trusted by students appearing in <strong>MDCAT 2026</strong> and <strong>FSc Part 1 & 2</strong> Board Exams.</p>
            </div>
        </article>
    </div>
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
    showLoader('Generating Quiz...', 'AI is crafting your unique MCQs. Please wait 10-30 seconds.');
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
    if (!searchQuery) return;
    
    btn.style.display = 'none';
    loader.style.display = 'block';
    
    let width = 0;
    const interval = setInterval(() => {
        width = Math.min(width + 5, 90);
        progressBar.style.width = width + '%';
    }, 300);
    
    fetch('mcqs_topic.php', {
        method: 'POST',
        body: new URLSearchParams({'action': 'load_more_topics', 'search_query': searchQuery})
    })
    .then(r => r.json())
    .then(data => {
        progressBar.style.width = '100%';
        if (data.success && data.topics) {
            const list = document.querySelector('.topic-list');
            data.topics.forEach(topic => {
                const isSelected = selectedTopics.some(t => JSON.parse(t).topic === topic.topic);
                const div = document.createElement('div');
                div.className = 'topic-item' + (isSelected ? ' selected' : '');
                const tData = {topic: topic.topic, similarity: topic.similarity || 85.0};
                div.setAttribute('data-topic-data', JSON.stringify(tData));
                div.onclick = () => toggleTopic(div, JSON.stringify(tData));
                div.innerHTML = `<div class="topic-info"><div class="topic-name">${topic.topic}</div></div><div class="topic-similarity">${tData.similarity}% match</div>`;
                list.appendChild(div);
            });
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
