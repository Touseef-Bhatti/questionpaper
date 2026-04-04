<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// require_once 'auth/auth_check.php';
include_once 'db_connect.php';
require_once 'middleware/SubscriptionCheck.php';

$classId = intval($_POST['class_id']);
$book_name = trim($_POST['book_name'] ?? '');
$chapter_no = intval($_GET['chapter_no'] ?? 0);

// Get class name for better display
$className = "Class " . $classId;
if ($classId > 0) {
    $stmt = $conn->prepare("SELECT class_name FROM class WHERE class_id = ?");
    $stmt->bind_param("i", $classId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $className = $row['class_name'];
    }
    $stmt->close();
}

if (empty($book_name)) {
    echo("<h2 style='color:red;'>Invalid book name. Please go back and try again.</h2>");
    exit;
}

// Validate and retrieve chapters from POST using prepared statements (OPTIMIZED)
$selectedChapters = $_POST['chapters'] ?? [];
if (empty($selectedChapters)) {
    echo("<h2 style='color:red;'>No chapters selected. Please go back and select chapters.</h2>");
    exit;
}

// Prepare chapter information for meta tags and title
$chapter_info = "";
$chapter_names = [];
if (!empty($selectedChapters)) {
    foreach ($selectedChapters as $ch) {
        $parts = explode('|', $ch);
        if (isset($parts[1])) {
            // Extract chapter number if possible, else use name
            $name = $parts[1];
            if (preg_match('/(?:Chapter|Unit)\s*([0-9]+)/i', $name, $matches)) {
                $chapter_names[] = $matches[1];
            } else {
                $chapter_names[] = $name;
            }
        }
    }
    $chapter_info = "chapter No." . implode(',', $chapter_names);
} else if ($chapter_no > 0) {
    $chapter_info = "chapter No." . $chapter_no;
}

$shortQuestions = $_POST['short_questions'] ?? [];
$mcqs = isset($_POST['mcqs']) ? $_POST['mcqs'] : [];
$longQuestions = $_POST['long_questions'] ?? [];
$chaptersSerialized = htmlspecialchars(json_encode($selectedChapters));

// Define page SEO title and description
$seo_title = "{$className} " . ucfirst($book_name) . " {$chapter_info} MCQs, Short, Long Questions | Question Paper Generator";
$seo_description = "Generate {$className} " . ucfirst($book_name) . " {$chapter_info} Question Paper. Includes MCQs, Short Questions, and Long Questions according to Punjab Board pattern. Best online tool for teachers.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/includes/favicons.php'; ?>

    <link rel="stylesheet" href="css/main.css">
     <link rel="stylesheet" href="css/buttons.css">


    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<meta name="description" content="<?= htmlspecialchars($seo_description) ?>">


<meta name="keywords" content="Question Paper For <?= htmlspecialchars($className) ?> - <?= htmlspecialchars($book_name) ?> <?= htmlspecialchars($chapter_info) ?> MCQs paper Generator, Chapter Wise Paper generator, MCQs, short questions, long questions, exam questions, Punjab Board paper pattern">


<title><?= htmlspecialchars($seo_title) ?></title>

</head>
<body>
    <?php include 'header.php'; ?>

    <!-- SIDE SKYSCRAPER ADS -->
    <?= renderAd('skyscraper', 'Place Right Skyscraper Banner Here', 'right', 'margin-top: 25%;') ?>

<div class="question-container">
    <h3>Generate Question Paper for Book: <?= htmlspecialchars($book_name) ?> (<?= htmlspecialchars($className) ?>)</h3>
    
    <!-- TOP AD BANNER MOVED HERE FROM HEADER -->
    <?= renderAd('banner', 'Place Top Banner Here', 'ad-placement-top') ?>


    <form method="POST" action="select_topics.php">
        <input type="hidden" name="class_id" value="<?= htmlspecialchars($classId) ?>">
        <input type="hidden" name="book_name" value="<?= htmlspecialchars($book_name) ?>">
        <input type="hidden" name="chapters" value="<?= $chaptersSerialized ?>">

        <!-- MIDDLE AD BANNER -->
        <?= renderAd('banner', 'Place Middle Banner Here', 'ad-placement-top') ?>

        <h4>Specify the total number of questions for each chapter:</h4>
        <?php
        foreach ($selectedChapters as $chapter) {
            list($chapterId, $chapterName) = explode('|', $chapter);
            $shortCount = isset($shortQuestions[$chapterId]) ? intval($shortQuestions[$chapterId]) : 0;
            $mcqCount = isset($mcqs[$chapterId]) ? intval($mcqs[$chapterId]) : 0;
            $longCount = isset($longQuestions[$chapterId]) ? intval($longQuestions[$chapterId]) : 0;
            echo "<div style='margin-bottom: 15px;'>";
            echo "<strong>" . htmlspecialchars($chapterName) . "</strong><br>";
            echo "MCQs: <input type='number' name='mcqs[$chapterId]' value='$mcqCount' min='0' style='margin-right: 10px; padding: 5px; width: 80px;' readonly>";
            echo "Short Questions: <input type='number' name='short_questions[$chapterId]' value='$shortCount' min='0' style='margin-right: 10px; padding: 5px; width: 80px;' readonly>";
            echo "Long Questions: <input type='number' name='long_questions[$chapterId]' value='$longCount' min='0' style='padding: 5px; width: 80px;' readonly>";
            echo "</div>";
        }
        ?>

       
    </form>

    <form method="POST" action="generate_question_paper.php">
        <input type="hidden" name="class_id" value="<?= htmlspecialchars($classId) ?>">
        <input type="hidden" name="book_name" value="<?= htmlspecialchars($book_name) ?>">
        <input type="hidden" name="chapters" value="<?= $chaptersSerialized ?>">
        <?php
        // Pass pattern mode and question count forward
        $patternMode = isset($_POST['pattern_mode']) && $_POST['pattern_mode'] === 'without' ? 'without' : 'with';
        $patternQCount = isset($_POST['pattern_qcount']) ? intval($_POST['pattern_qcount']) : 3;
        echo "<input type='hidden' name='pattern_mode' value='" . htmlspecialchars($patternMode) . "'>";
        echo "<input type='hidden' name='pattern_qcount' value='" . htmlspecialchars($patternQCount) . "'>";

        // Pass per-chapter long placements forward if provided
        if (!empty($_POST['long_qnum']) && is_array($_POST['long_qnum']) && !empty($_POST['long_part']) && is_array($_POST['long_part'])) {
            foreach ($_POST['long_qnum'] as $chapId => $qnums) {
                $chapIdSafe = htmlspecialchars($chapId);
                $parts = isset($_POST['long_part'][$chapId]) ? $_POST['long_part'][$chapId] : [];
                for ($i = 0; $i < count($qnums); $i++) {
                    $qVal = htmlspecialchars($qnums[$i]);
                    $pVal = htmlspecialchars(isset($parts[$i]) ? $parts[$i] : 'a');
                    echo "<input type='hidden' name='long_qnum[$chapIdSafe][]' value='$qVal'>";
                    echo "<input type='hidden' name='long_part[$chapIdSafe][]' value='$pVal'>";
                }
            }
        }
        // Pass all chapter question counts
        foreach ($selectedChapters as $chapter) {
            list($chapterId, $chapterName) = explode('|', $chapter);
            $shortCount = isset($shortQuestions[$chapterId]) ? intval($shortQuestions[$chapterId]) : 0;
            $mcqCount = isset($mcqs[$chapterId]) ? intval($mcqs[$chapterId]) : 0;
            $longCount = isset($longQuestions[$chapterId]) ? intval($longQuestions[$chapterId]) : 0;
            echo "<input type='hidden' name='chapter_ids[]' value='" . htmlspecialchars($chapterId) . "'>";
            echo "<input type='hidden' name='mcqs[$chapterId]' value='$mcqCount'>";
            echo "<input type='hidden' name='short_questions[$chapterId]' value='$shortCount'>";
            echo "<input type='hidden' name='long_questions[$chapterId]' value='$longCount'>";
        }
        ?>

       <div class="btn-wrapper">
  <button type="submit" class="btn">
    <svg class="btn-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"
      ></path>
    </svg>



    <div style="min-width: 16.2em;" class="txt-wrapper">
      <div class="txt-1">
        <span class="btn-letter">G</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">n</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">r</span>
        <span class="btn-letter">a</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">e</span>
        <span style="opacity: 0;" class="btn-letter">-</span>
        <span class="btn-letter">Q</span>
        <span class="btn-letter">u</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">s</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">i</span>
        <span class="btn-letter">o</span>
        <span class="btn-letter">n</span>
        <span style="opacity: 0;" class="btn-letter">-</span>
        <span class="btn-letter">P</span>
        <span class="btn-letter">a</span>
        <span class="btn-letter">p</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">r</span>
      </div>

      <div class="txt-2">
        <span class="btn-letter">G</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">n</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">r</span>
        <span class="btn-letter">a</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">i</span>
        <span class="btn-letter">n</span>
        <span class="btn-letter">g</span>
        <span style="opacity: 0;" class="btn-letter">-</span>
        <span class="btn-letter">Q</span>
        <span class="btn-letter">u</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">s</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">i</span>
        <span class="btn-letter">o</span>
        <span class="btn-letter">n</span>
        <span style="opacity: 0;" class="btn-letter">-</span>
        <span class="btn-letter">P</span>
        <span class="btn-letter">a</span>
        <span class="btn-letter">p</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">r</span>
        <span class="btn-letter">.</span>
        <span class="btn-letter">.</span>
        <span class="btn-letter">.</span>
      </div>
    </div>
  </button>
</div>


        <br><br>

           
    </form>
    
    <a href="select_chapters.php?class_id=<?= $classId ?>&book_name=<?= urlencode($book_name) ?>" class="go-back-btn" style="text-decoration: none; display: inline-flex; align-items: center;">⬅ Go Back to Chapters</a>

    <!-- SEO ARTICLE SECTION -->
    <article class="seo-article-section">
        <div class="seo-container">
            <div class="seo-info-bar">
                <i class="fas fa-info-circle"></i>
                <span>Online Paper Setter Tool For Teachers of class <strong><?= htmlspecialchars($className) ?>  <?= htmlspecialchars($book_name) ?>Book </strong> Chapter Wise Question Selection from Chapter Number (<?= htmlspecialchars($chapter_info) ?>).</span>
            </div>

            <div class="seo-header">
                <h2>The Best Paper Generator Tool for <?= htmlspecialchars($book_name) ?> Paper of class <?= htmlspecialchars($className) ?></h2>
                <p>Best Available tool to Generate Question paper With Full Controlled Chapter Wise Selection. Tailor your assessment with precision. Our automated Advanced system helps you pick the right balance of questions according to the official Punjab Board scheme.</p>
            </div>

            <div class="seo-grid">
                <div class="seo-card">
                    <div class="seo-card-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Board Exam Patterns</h3>
                    <p>Every question in our database is mapped to the latest 2026 syllabus and patterns for 9th, 10th and FSc classes across all Punjab Boards including Lahore, Faisalabad, and Rawalpindi.</p>
                </div>

                <div class="seo-card">
                    <div class="seo-card-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h3>Comprehensive Selection</h3>
                    <p>Mix and match MCQs, short questions, and long questions from multiple chapters of <strong><?= htmlspecialchars($book_name) ?></strong> to create a truly representative exam for your students.</p>
                </div>

                <div class="seo-card">
                    <div class="seo-card-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <h3>Ready to Print</h3>
                    <p>Once you generate your paper, it is formatted to be professional, clean, and ready for immediate printing. Perfect for mid-terms, final exams, or weekly school tests.</p>
                </div>

                <div class="seo-card">
                    <div class="seo-card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Track Success</h3>
                    <p>Join the thousands of teachers and educational institutes in Pakistan who trust Ahmad Learning Hub for fast, reliable, and high-quality assessment tools.</p>
                </div>
            </div>
        </div>
    </article>
</div>

<?php include 'footer.php' ?>
</body>
</html>
<style>
    .question-container {
        max-width: 65%;
        width: 95%;
        margin: 5% auto 5% 5%;
        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    @media (max-width: 768px) {
        .question-container {
            width: 95%;
            min-width: 95%;
            padding: 15px;
            margin: 5% auto;
        }
    }
    .topic_btn {
        display: inline-block;
    }
    h3, h4 {
        color: #333;
        margin-bottom: 15px;
    }

    input[type="number"] {
        width: 80px;
        padding: 5px;
        margin: 10px 0;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    /* SEO Article Styling */
    .seo-article-section {
        margin-top: 50px;
        padding-top: 40px;
        border-top: 2px solid #f1f3f6;
    }

    .seo-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .seo-info-bar {
        background: #e7f3ff;
        color: #2b6cb0;
        padding: 12px 20px;
        border-radius: 10px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
    }

    .seo-header {
        text-align: center;
        margin-bottom: 40px;
    }

    .seo-header h2 {
        font-size: 1.8rem;
        color: #2c3e50;
        margin-bottom: 15px;
    }

    .seo-header p {
        color: #64748b;
        max-width: 700px;
        margin: 0 auto;
        line-height: 1.6;
    }

    .seo-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .seo-card {
        background: #f8fafc;
        padding: 25px;
        border-radius: 15px;
        transition: all 0.3s ease;
        border: 1px solid #edf2f7;
    }

    .seo-card:hover {
        transform: translateY(-5px);
        background: #ffffff;
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        border-color: #3182ce;
    }

    .seo-card-icon {
        width: 45px;
        height: 45px;
        background: #3182ce;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        margin-bottom: 15px;
        font-size: 1.2rem;
    }

    .seo-card h3 {
        font-size: 1.2rem;
        margin-bottom: 10px;
        color: #2d3748;
    }

    .seo-card p {
        color: #718096;
        font-size: 0.9rem;
        line-height: 1.6;
        margin: 0;
    }

    @media (max-width: 768px) {
        .question-container {
            width: 95%;
            min-width: 95%;
            padding: 15px;
            margin: 5% auto;
        }

        .seo-grid {
            grid-template-columns: 1fr;
        }

        .seo-header h2 {
            font-size: 1.5rem;
        }
    }
</style>
