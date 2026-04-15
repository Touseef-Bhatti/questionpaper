<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// require_once 'auth/auth_check.php';
include_once 'db_connect.php';
require_once 'middleware/SubscriptionCheck.php';

$classId = intval($_POST['class_id'] ?? $_GET['class_id'] ?? 0);
$book_name = trim($_POST['book_name'] ?? $_GET['book_name'] ?? '');
$chapter_no = intval($_GET['chapter_no'] ?? 0);

// Handle SEO chapters from URL
if (isset($_GET['chapters_from_url']) && empty($_POST['chapters'])) {
    $chaptersFromUrl = $_GET['chapters_from_url'];
    if ($chaptersFromUrl === 'all') {
        $stmt = $conn->prepare("SELECT chapter_id, chapter_name FROM chapter WHERE class_id = ? AND book_name = ?");
        $stmt->bind_param("is", $classId, $book_name);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $_POST['chapters'][] = "{$row['chapter_id']}|{$row['chapter_name']}";
        }
        $stmt->close();
    } else {
        $rawChapters = explode('-', $chaptersFromUrl);
        $numbers = [];
        $names = [];
        foreach ($rawChapters as $ch) {
            $d = urldecode($ch);
            if (preg_match('/^\d+$/', $d)) {
                $numbers[] = intval($d);
            } elseif ($d !== '') {
                $names[] = $d;
            }
        }
        $seen = [];
        if (!empty($numbers)) {
            $placeholders = str_repeat('?,', count($numbers) - 1) . '?';
            $sql = "SELECT chapter_id, chapter_name FROM chapter WHERE class_id = ? AND book_name = ? AND chapter_no IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $types = 'is' . str_repeat('i', count($numbers));
                $stmt->bind_param($types, $classId, $book_name, ...$numbers);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $cid = intval($row['chapter_id']);
                    if (!isset($seen[$cid])) {
                        $seen[$cid] = true;
                        $_POST['chapters'][] = "{$row['chapter_id']}|{$row['chapter_name']}";
                    }
                }
                $stmt->close();
            }
        }
        if (!empty($names)) {
            $placeholders = str_repeat('?,', count($names) - 1) . '?';
            $sql = "SELECT chapter_id, chapter_name FROM chapter WHERE class_id = ? AND book_name = ? AND chapter_name IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $types = 'is' . str_repeat('s', count($names));
                $stmt->bind_param($types, $classId, $book_name, ...$names);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $cid = intval($row['chapter_id']);
                    if (!isset($seen[$cid])) {
                        $seen[$cid] = true;
                        $_POST['chapters'][] = "{$row['chapter_id']}|{$row['chapter_name']}";
                    }
                }
                $stmt->close();
            }
        }
    }
}

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
if (!is_array($selectedChapters)) {
    $decoded = json_decode(is_string($selectedChapters) ? $selectedChapters : '', true);
    if (is_array($decoded)) {
        $selectedChapters = $decoded;
    } elseif (!empty($selectedChapters)) {
        $selectedChapters = [$selectedChapters];
    } else {
        $selectedChapters = [];
    }
}
if (empty($selectedChapters)) {
    echo("<h2 style='color:red;'>No chapters selected. Please go back and select chapters.</h2>");
    exit;
}

// Prepare chapter information for meta tags and title
$chapter_info = "";
$chapter_names = [];

// Fetch total chapter count for this book to check if "All Chapters" should be used
$totalChaptersCount = 0;
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM chapter WHERE class_id = ? AND book_name = ?");
$countStmt->bind_param("is", $classId, $book_name);
$countStmt->execute();
$countRes = $countStmt->get_result();
if ($countRow = $countRes->fetch_assoc()) {
    $totalChaptersCount = $countRow['total'];
}
$countStmt->close();

if (!empty($selectedChapters)) {
    if (count($selectedChapters) >= $totalChaptersCount && $totalChaptersCount > 0) {
        $chapter_info = "All Chapters";
    } else {
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
        $chapter_info = "Chapter " . implode(',', $chapter_names);
    }
} else if ($chapter_no > 0) {
    $chapter_info = "Chapter " . $chapter_no;
}

$shortQuestions = $_POST['short_questions'] ?? [];
$mcqs = isset($_POST['mcqs']) ? $_POST['mcqs'] : [];
$longQuestions = $_POST['long_questions'] ?? [];
$chaptersSerialized = htmlspecialchars(json_encode($selectedChapters));

// Define page SEO title and description
$seo_title = "{$className} " . ucfirst($book_name) . " {$chapter_info} MCQs, Short, Long Questions | Question Paper Generator";
$seo_description = "Generate {$className} " . ucfirst($book_name) . " {$chapter_info} Question Paper. Includes MCQs, Short Questions, and Long Questions according to Punjab Board pattern. Best online tool for teachers.";

// SEO Friendly Back URL
$bookSlug = strtolower(str_replace(' ', '-', $book_name));
$classOrdinal = $classId . ( ($classId % 10 == 1 && $classId % 100 != 11) ? 'st' : (($classId % 10 == 2 && $classId % 100 != 12) ? 'nd' : (($classId % 10 == 3 && $classId % 100 != 13) ? 'rd' : 'th')) );

// Determine SEO back URL and chapter slug for generation
if (count($selectedChapters) >= $totalChaptersCount && $totalChaptersCount > 0) {
    $chapterSlugPart = "all";
    $seoBackUrl = "{$classOrdinal}-class-{$bookSlug}-all-chapter-question-paper-generator";
} else {
    $ids = [];
    foreach ($selectedChapters as $ch) {
        $parts = explode('|', $ch);
        $cid = isset($parts[0]) ? intval($parts[0]) : 0;
        if ($cid > 0) $ids[] = $cid;
    }
    $chapterSlugPart = $chapter_no > 0 ? $chapter_no : "";
    if (!empty($ids)) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "SELECT COALESCE(chapter_no, chapter_id) AS num FROM chapter WHERE chapter_id IN ($placeholders) ORDER BY num ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $types = str_repeat('i', count($ids));
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $res = $stmt->get_result();
            $nums = [];
            while ($row = $res->fetch_assoc()) {
                $nums[] = (string)intval($row['num']);
            }
            $stmt->close();
            if (!empty($nums)) {
                $chapterSlugPart = implode('-', $nums);
            }
        }
    }
    $seoBackUrl = "{$classOrdinal}-class-{$bookSlug}-chapter-{$chapterSlugPart}-question-paper-generator";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/includes/favicons.php'; ?>

    <link rel="stylesheet" href="css/main.css">
     <link rel="stylesheet" href="css/buttons.css">


    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<meta name="description" content="Generate <?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?> question paper for <?= htmlspecialchars($chapter_info) ?>. Custom MCQs, short and long questions based on Punjab Board pattern for teachers.">


<meta name="keywords" content="<?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?> <?= htmlspecialchars($chapter_info) ?> paper generator, mcqs paper maker, chapter wise paper generator, short questions long questions generator, Punjab Board test setter">



<title><?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?> <?= htmlspecialchars($chapter_info) ?> Paper Generator | MCQs & Questions</title>

<!-- Schema.org Markup for SEO -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "<?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?> Paper Generator",
  "operatingSystem": "Web",
  "applicationCategory": "EducationalApplication",
  "description": "Professional question selection for <?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?> <?= htmlspecialchars($chapter_info) ?> based on Punjab Board patterns.",
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.9",
    "ratingCount": "850"
  }
}
</script>

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

    <form id="generatePaperForm" method="POST" action="generate_question_paper.php">
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const genForm = document.getElementById('generatePaperForm');
            if (genForm) {
                genForm.addEventListener('submit', function(e) {
                    // Prevent default to ensure we can build the URL and redirect manually if needed
                    // but for SEO, we want the browser to navigate to the new URL.
                    // We'll update the action and let it submit normally.
                    
                    const classId = "<?= $classId ?>";
                    const classOrdinal = "<?= $classOrdinal ?>";
                    const bookSlug = "<?= $bookSlug ?>";
                    const chapterSlug = "<?= $chapterSlugPart ?>";
                    
                    // Calculate totals from the HIDDEN inputs in THIS form
                    let totalMcqs = 0;
                    let totalShorts = 0;
                    let totalLongs = 0;
                    
                    // Note: We need to target the inputs within THIS form specifically
                    genForm.querySelectorAll('input[name^="mcqs["]').forEach(i => totalMcqs += parseInt(i.value) || 0);
                    genForm.querySelectorAll('input[name^="short_questions["]').forEach(i => totalShorts += parseInt(i.value) || 0);
                    genForm.querySelectorAll('input[name^="long_questions["]').forEach(i => totalLongs += parseInt(i.value) || 0);
                    
                    // Build SEO URL
                    const chapterPart = chapterSlug ? `chapter-${chapterSlug}-` : "";
                    const seoAction = `/${classOrdinal}-class-${bookSlug}-${chapterPart}mcqs-${totalMcqs}-short-${totalShorts}-long-${totalLongs}-question-paper-generator`;
                    
                    // Update form action and submit
                    genForm.action = seoAction;
                    
                    // Explicitly submit if needed, or let the event finish.
                    // To be safe and professional, we'll let the submit proceed after updating action.
                });
            }
        });
        </script>
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
    
    <a href="<?= $seoBackUrl ?>" class="go-back-btn" style="text-decoration: none; display: inline-flex; align-items: center;">⬅ Go Back to Chapters</a>

    <!-- SEO Content Section -->
    <div class="book-features-seo">
        <h2 class="features-title">🚀 <?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?> Question Paper Maker</h2>
        <p style="text-align: center; color: #64748b; margin-top: -2rem; margin-bottom: 3rem; font-size: 1.1rem;">
            Generate professional exam papers for <strong><?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?></strong> focusing on <strong><?= htmlspecialchars($chapter_info) ?></strong>. Our tool helps teachers create high-quality <strong>mcqs papers</strong>, <strong>short questions</strong>, and <strong>long questions</strong> in minutes.
        </p>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <span class="icon">📄</span>
                </div>
                <div class="feature-text">
                    <strong>Board Pattern Papers</strong>
                    <p>Build papers for <strong><?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?></strong> according to Punjab Board (BISE) patterns.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <span class="icon">✅</span>
                </div>
                <div class="feature-text">
                    <strong>Chapter-Wise Selection</strong>
                    <p>Choose specific questions from <strong><?= htmlspecialchars($chapter_info) ?></strong> for a customized assessment experience.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <span class="icon">⚡</span>
                </div>
                <div class="feature-text">
                    <strong>MCQs Paper Generator</strong>
                    <p>Create full-length or chapter-wise <strong>MCQs tests</strong> for <?= htmlspecialchars($className) ?> with instant keys.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <span class="icon">🔍</span>
                </div>
                <div class="feature-text">
                    <strong>Print-Ready Format</strong>
                    <p>Download your <strong>question papers</strong> in professional PDF or editable Word formats ready for school exams.</p>
                </div>
            </div>
        </div>

        <!-- SEO FAQ Section -->
        <div class="seo-faq-section" style="margin-top: 4rem; border-top: 1px solid #e2e8f0; padding-top: 3rem;">
            <h3 style="text-align: center; color: #0f172a; margin-bottom: 2rem;">Frequently Asked Questions (FAQs)</h3>
            <div class="faq-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; text-align: left;">
                <div class="faq-item">
                    <h4 style="color: #1e293b; margin-bottom: 0.5rem;">How to select questions for <?= htmlspecialchars($book_name) ?> paper?</h4>
                    <p style="color: #475569; font-size: 0.95rem;">Simply browse the list of available questions for your selected chapters and click on the checkboxes to include them in your final paper.</p>
                </div>
                <div class="faq-item">
                    <h4 style="color: #1e293b; margin-bottom: 0.5rem;">Can I add my own questions?</h4>
                    <p style="color: #475569; font-size: 0.95rem;">Currently, our system provides a massive database of verified questions from Punjab Board patterns, which covers almost everything you need.</p>
                </div>
                <div class="faq-item">
                    <h4 style="color: #1e293b; margin-bottom: 0.5rem;">Is it free to generate papers?</h4>
                    <p style="color: #475569; font-size: 0.95rem;">Yes, you can generate and preview papers. For advanced features and PDF downloads, check out our premium subscription plans.</p>
                </div>
                <div class="faq-item">
                    <h4 style="color: #1e293b; margin-bottom: 0.5rem;">Which boards are supported?</h4>
                    <p style="color: #475569; font-size: 0.95rem;">We support all Punjab Boards including BISE Lahore, Multan, Faisalabad, Gujranwala, and Rawalpindi.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php' ?>
</body>
</html>
<style>
    .question-container {
        max-width: 80%;
        
        margin: auto;

        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        margin-top: 10%;
    }

    @media (max-width: 768px) {
        .question-container {
            width: 95%;
            min-width: 95%;
            padding: 15px;
            margin: 5% auto;
            margin-top: 50%;
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

    /* SEO Content Styles Refined - Same as Book Selection Page */
    .book-features-seo {
        margin: 4rem auto 2rem auto;
        padding: 3rem 2rem;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 24px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 20px 50px -12px rgba(0, 0, 0, 0.05);
    }

    .features-title {
        color: #0f172a;
        font-size: clamp(1.3rem, 4vw, 1.75rem);
        font-weight: 800;
        margin-bottom: 3rem;
        text-align: center;
        letter-spacing: -0.01em;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 2rem;
    }

    .feature-card {
        display: flex;
        align-items: flex-start;
        gap: 1.5rem;
        padding: 1.75rem;
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #f1f5f9;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
    }

    .feature-card:hover {
        transform: translateY(-8px);
        border-color: #3b82f6;
        box-shadow: 0 20px 25px -5px rgba(59, 130, 246, 0.1), 0 10px 10px -5px rgba(59, 130, 246, 0.04);
    }

    .feature-icon-wrapper {
        flex-shrink: 0;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #eff6ff;
        border-radius: 14px;
        font-size: 1.75rem;
        transition: all 0.3s ease;
    }

    .feature-card:hover .feature-icon-wrapper {
        background: #3b82f6;
        transform: rotate(8deg) scale(1.1);
    }

    .feature-card:hover .icon {
        filter: brightness(0) invert(1);
    }

    .feature-text {
        text-align: left;
    }

    .feature-text strong {
        display: block;
        color: #0f172a;
        font-size: 1.15rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .feature-text p {
        color: #475569;
        font-size: 0.95rem;
        line-height: 1.5;
        margin: 0;
    }

    @media (max-width: 768px) {
        .book-features-seo {
            padding: 2rem 1.25rem;
            margin-top: 3rem;
        }
        
        .feature-card {
            padding: 1.5rem;
            gap: 1.25rem;
        }

        .feature-icon-wrapper {
            width: 48px;
            height: 48px;
            font-size: 1.5rem;
        }
    }

    @media (max-width: 480px) {
        .features-grid {
            grid-template-columns: 1fr;
        }
        
        .feature-card {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .feature-text {
            text-align: center;
        }
    }
</style>
