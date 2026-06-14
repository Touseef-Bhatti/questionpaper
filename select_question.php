<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// require_once 'auth/auth_check.php';
include_once 'db_connect.php';
require_once 'middleware/SubscriptionCheck.php';
require_once __DIR__ . '/includes/seo_content_Question_paper.php';

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
$totalSelectedMcqs = array_sum(array_map('intval', $mcqs));
$totalSelectedShorts = array_sum(array_map('intval', $shortQuestions));
$totalSelectedLongs = array_sum(array_map('intval', $longQuestions));
$questionPaperSeo = getQuestionPaperSeoContent($classId, $book_name);

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

$siteBaseUrl = 'https://ahmadlearninghub.com';
$canonicalUrl = $siteBaseUrl . '/' . ltrim($seoBackUrl, '/');
$pageTitle = "{$className} {$book_name} {$chapter_info} Question Paper Review";
$pageDescription = "Review {$totalSelectedMcqs} MCQs, {$totalSelectedShorts} short questions and {$totalSelectedLongs} long questions for {$className} {$book_name} {$chapter_info}, then generate a printable paper.";
$softwareSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => "{$className} {$book_name} Question Paper Generator",
    'url' => $canonicalUrl,
    'description' => $pageDescription,
    'applicationCategory' => 'EducationalApplication',
    'operatingSystem' => 'Any',
    'isAccessibleForFree' => true,
    'inLanguage' => 'en',
    'provider' => [
        '@type' => 'EducationalOrganization',
        'name' => 'Ahmad Learning Hub',
        'url' => $siteBaseUrl,
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>
    <?php include_once __DIR__ . '/includes/monetag_ads.php'; ?>
    <?php include_once __DIR__ . '/includes/favicons.php'; ?>

    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/select_question.css">


    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?> | Ahmad Learning Hub</title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($questionPaperSeo['keywords']) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <script type="application/ld+json"><?= json_encode($softwareSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>

</head>
<body>
    <?php include 'header.php'; ?>




   
<main class="question-page">
<div class="question-container">
    <header class="question-hero">
        <div class="question-hero-icon" aria-hidden="true">
            <i class="fas fa-file-signature"></i>
        </div>
        <div class="question-hero-copy">
            <span class="question-eyebrow">Question Paper Review</span>
            <h1><?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?> Paper</h1>
            <p>Review the chapter-wise distribution for <?= htmlspecialchars($chapter_info) ?> before generating your final question paper.</p>
        </div>
    </header>

    <section class="paper-summary" aria-label="Selected question totals">
        <article class="paper-summary-card paper-summary-card--mcq">
            <i class="fas fa-check-square" aria-hidden="true"></i>
            <span><strong><?= $totalSelectedMcqs ?></strong><small>MCQs</small></span>
        </article>
        <article class="paper-summary-card paper-summary-card--short">
            <i class="fas fa-align-left" aria-hidden="true"></i>
            <span><strong><?= $totalSelectedShorts ?></strong><small>Short Questions</small></span>
        </article>
        <article class="paper-summary-card paper-summary-card--long">
            <i class="fas fa-file-alt" aria-hidden="true"></i>
            <span><strong><?= $totalSelectedLongs ?></strong><small>Long Questions</small></span>
        </article>
        <article class="paper-summary-card paper-summary-card--chapters">
            <i class="fas fa-book-open" aria-hidden="true"></i>
            <span><strong><?= count($selectedChapters) ?></strong><small>Chapters</small></span>
        </article>
    </section>

    <form method="POST" action="select_topics.php">
        <input type="hidden" name="class_id" value="<?= htmlspecialchars($classId) ?>">
        <input type="hidden" name="book_name" value="<?= htmlspecialchars($book_name) ?>">
        <input type="hidden" name="chapters" value="<?= $chaptersSerialized ?>">

        <section class="chapter-review" aria-labelledby="chapter-review-title">
        <div class="section-heading">
            <span class="section-heading-icon"><i class="fas fa-layer-group" aria-hidden="true"></i></span>
            <div>
                <h2 id="chapter-review-title">Chapter Distribution</h2>
                <p>These readonly totals will be used to build your paper.</p>
            </div>
        </div>
        <div class="chapter-review-grid">
        <?php
        foreach ($selectedChapters as $chapter) {
            list($chapterId, $chapterName) = explode('|', $chapter);
            $shortCount = isset($shortQuestions[$chapterId]) ? intval($shortQuestions[$chapterId]) : 0;
            $mcqCount = isset($mcqs[$chapterId]) ? intval($mcqs[$chapterId]) : 0;
            $longCount = isset($longQuestions[$chapterId]) ? intval($longQuestions[$chapterId]) : 0;
            ?>
            <article class="chapter-review-card">
                <h3><i class="fas fa-bookmark" aria-hidden="true"></i><?= htmlspecialchars($chapterName) ?></h3>
                <div class="chapter-counts">
                    <label>
                        <input type="number" name="mcqs[<?= htmlspecialchars($chapterId) ?>]" value="<?= $mcqCount ?>" min="0" readonly>
                        <span>MCQs</span>
                    </label>
                    <label>
                        <input type="number" name="short_questions[<?= htmlspecialchars($chapterId) ?>]" value="<?= $shortCount ?>" min="0" readonly>
                        <span>Short</span>
                    </label>
                    <label>
                        <input type="number" name="long_questions[<?= htmlspecialchars($chapterId) ?>]" value="<?= $longCount ?>" min="0" readonly>
                        <span>Long</span>
                    </label>
                </div>
            </article>
            <?php
        }
        ?>
        </div>
        </section>
    </form>

    <form id="generatePaperForm" method="POST" action="generate_question_paper.php">
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const genForm = document.getElementById('generatePaperForm');
            const generatePopup = document.getElementById('generate-paper-popup');
            const reviewDetailsButton = document.getElementById('review-paper-details');
            const popupGenerateButton = document.getElementById('popup-generate-paper');

            function closeGeneratePopup() {
                if (!generatePopup) return;
                generatePopup.classList.remove('is-visible');
                generatePopup.setAttribute('aria-hidden', 'true');
            }

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

            setTimeout(function() {
                if (!generatePopup) return;
                generatePopup.classList.add('is-visible');
                generatePopup.setAttribute('aria-hidden', 'false');
                popupGenerateButton?.focus();
            }, 3000);

            reviewDetailsButton?.addEventListener('click', closeGeneratePopup);

            popupGenerateButton?.addEventListener('click', function() {
                closeGeneratePopup();
                genForm?.requestSubmit();
            });

            generatePopup?.addEventListener('click', function(event) {
                if (event.target === generatePopup) {
                    closeGeneratePopup();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && generatePopup?.classList.contains('is-visible')) {
                    closeGeneratePopup();
                }
            });
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

       <?php if (false): ?>
       <div class="btn-wrapper legacy-generate-control" aria-hidden="true">
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
       <?php endif; ?>


        <section class="paper-actions" aria-label="Question paper actions">
            <a href="<?= htmlspecialchars($seoBackUrl) ?>" class="paper-action paper-action--back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span><strong>Back to Chapters</strong><small>Change chapter distribution</small></span>
            </a>
            <button type="submit" class="paper-action paper-action--generate">
                <i class="fas fa-magic" aria-hidden="true"></i>
                <span><strong>Generate Question Paper</strong><small>Create the final printable paper</small></span>
            </button>
        </section>

           
    </form>
    
    <?php if (false): ?>
    <a href="<?= htmlspecialchars($seoBackUrl) ?>" class="go-back-btn legacy-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Go Back to Chapters</a>

    <!-- Legacy summary retained temporarily for compatibility; replaced below. -->
    <div class="book-features-seo legacy-seo-content" aria-hidden="true">
        <h2 class="features-title"><i class="fas fa-file-alt" aria-hidden="true"></i> <?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?> Question Paper Maker</h2>
        <p style="text-align: center; color: #64748b; margin-top: -2rem; margin-bottom: 3rem; font-size: 1.1rem;">
            Generate professional exam papers for <strong><?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?></strong> focusing on <strong><?= htmlspecialchars($chapter_info) ?></strong>. Our tool helps teachers create high-quality <strong>mcqs papers</strong>, <strong>short questions</strong>, and <strong>long questions</strong> in minutes.
        </p>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-file-alt icon" aria-hidden="true"></i>
                </div>
                <div class="feature-text">
                    <strong>Board Pattern Papers</strong>
                    <p>Build papers for <strong><?= htmlspecialchars($className) ?> <?= htmlspecialchars($book_name) ?></strong> according to Punjab Board (BISE) patterns.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-check-circle icon" aria-hidden="true"></i>
                </div>
                <div class="feature-text">
                    <strong>Chapter-Wise Selection</strong>
                    <p>Choose specific questions from <strong><?= htmlspecialchars($chapter_info) ?></strong> for a customized assessment experience.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-bolt icon" aria-hidden="true"></i>
                </div>
                <div class="feature-text">
                    <strong>MCQs Paper Generator</strong>
                    <p>Create full-length or chapter-wise <strong>MCQs tests</strong> for <?= htmlspecialchars($className) ?> with instant keys.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-search icon" aria-hidden="true"></i>
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
    <?php endif; ?>
    <?php renderQuestionPaperSeoContent($questionPaperSeo); ?>
</div>
</main>

<div id="generate-paper-popup" class="generate-paper-popup" aria-hidden="true">
    <div class="generate-paper-dialog" role="dialog" aria-modal="true" aria-labelledby="generate-paper-popup-title">
        <div class="generate-paper-icon" aria-hidden="true">
            <i class="fas fa-file-alt"></i>
        </div>
        <span class="generate-paper-kicker">Final Step</span>
        <h2 id="generate-paper-popup-title">Your Paper Is Ready</h2>
        <p>Review your selected questions or continue to generate the complete question paper.</p>
        <div class="generate-paper-actions">
            <button type="button" id="review-paper-details" class="generate-paper-button generate-paper-button--review">
                <i class="fas fa-tasks" aria-hidden="true"></i>
                <span><strong>Review Details</strong><small>Check selected questions again</small></span>
            </button>
            <button type="button" id="popup-generate-paper" class="generate-paper-button generate-paper-button--primary">
                <i class="fas fa-magic" aria-hidden="true"></i>
                <span><strong>Generate Paper</strong><small>Create your question paper now</small></span>
            </button>
        </div>
        <span class="generate-paper-note"><i class="fas fa-shield-alt" aria-hidden="true"></i> Your selected question distribution will be preserved.</span>
    </div>
</div>

<?php include 'footer.php' ?>
</body>
</html>
<?php if (false): ?>
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

    .generate-paper-popup {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: max(18px, env(safe-area-inset-top)) max(18px, env(safe-area-inset-right)) max(18px, env(safe-area-inset-bottom)) max(18px, env(safe-area-inset-left));
        background:
            radial-gradient(circle at 50% 20%, rgba(59, 130, 246, 0.28), transparent 38%),
            rgba(8, 18, 43, 0.72);
        opacity: 0;
        visibility: hidden;
        overflow-x: hidden;
        overflow-y: auto;
        transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    .generate-paper-popup.is-visible {
        opacity: 1;
        visibility: visible;
    }

    .generate-paper-dialog {
        position: relative;
        width: min(100%, 480px);
        margin: auto;
        padding: 34px;
        overflow: hidden;
        text-align: center;
        background: linear-gradient(145deg, #ffffff 0%, #f7faff 100%);
        border: 1px solid rgba(255,255,255,0.75);
        border-radius: 26px;
        box-shadow: 0 22px 48px rgba(2, 12, 34, 0.32);
        transform: translateY(16px);
        transition: transform 0.22s ease;
    }

    .generate-paper-dialog::before {
        content: "";
        position: absolute;
        inset: 0 0 auto;
        height: 6px;
        background: linear-gradient(90deg, #2563eb, #0ea5e9, #7c3aed);
    }

    .generate-paper-popup.is-visible .generate-paper-dialog {
        transform: translateY(0);
    }

    .generate-paper-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 16px;
        display: grid;
        place-items: center;
        color: #fff;
        font-size: 25px;
        background: linear-gradient(135deg, #2563eb, #0ea5e9);
        border-radius: 19px;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.28);
        transform: rotate(-4deg);
    }

    .generate-paper-kicker {
        display: block;
        margin-bottom: 7px;
        color: #2563eb;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.13em;
        text-transform: uppercase;
    }

    .generate-paper-dialog h2 {
        margin: 0 0 10px;
        color: #0f172a;
        font-size: clamp(22px, 5vw, 28px);
        line-height: 1.2;
        letter-spacing: -0.025em;
    }

    .generate-paper-dialog p {
        max-width: 390px;
        margin: 0 auto 24px;
        color: #3f4f65;
        font-size: 15px;
        font-weight: 500;
        line-height: 1.6;
    }

    .generate-paper-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .generate-paper-button {
        width: 100%;
        min-height: 92px;
        margin: 0;
        padding: 15px;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 11px;
        text-align: left;
        border: 1px solid transparent;
        border-radius: 16px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 700;
        box-shadow: none;
        transition: transform 0.18s ease, background-color 0.18s ease;
    }

    .generate-paper-button > i {
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        border-radius: 11px;
    }

    .generate-paper-button span {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .generate-paper-button strong {
        display: block;
        font-size: 14px;
        font-weight: 800;
        line-height: 1.3;
    }

    .generate-paper-button small {
        display: block;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.35;
    }

    .generate-paper-button--review {
        background: #f1f5f9;
        color: #1e293b;
        border-color: #dbe4ef;
    }

    .generate-paper-button--review strong {
        color: #0f172a;
    }

    .generate-paper-button--review small {
        color: #475569;
    }

    .generate-paper-button--review > i {
        color: #2563eb;
        background: #dbeafe;
    }

    .generate-paper-button--review:hover {
        background: #e7eef7;
        transform: translateY(-2px);
    }

    .generate-paper-button--primary {
        background: linear-gradient(135deg, #2563eb, #087bff);
        color: #fff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
    }

    .generate-paper-button--primary strong,
    .generate-paper-button--primary small {
        color: #fff;
    }

    .generate-paper-button--primary > i {
        color: #fff;
        background: rgba(255,255,255,0.17);
    }

    .generate-paper-button--primary:hover {
        background: linear-gradient(135deg, #1d4ed8, #0369d8);
        transform: translateY(-2px);
    }

    .generate-paper-button:focus-visible {
        outline: 3px solid rgba(14, 165, 233, 0.4);
        outline-offset: 3px;
    }

    .generate-paper-note {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        margin-top: 18px;
        color: #475569;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.4;
    }

    .generate-paper-note i {
        color: #10b981;
    }

    @media (max-width: 480px) {
        .generate-paper-popup {
            align-items: flex-end;
            padding: 12px 10px max(10px, env(safe-area-inset-bottom));
        }

        .generate-paper-dialog {
            width: 100%;
            max-height: calc(100dvh - 22px);
            padding: 25px 16px 18px;
            overflow-y: auto;
            border-radius: 22px 22px 16px 16px;
        }

        .generate-paper-icon {
            width: 52px;
            height: 52px;
            margin-bottom: 12px;
            font-size: 21px;
            border-radius: 15px;
        }

        .generate-paper-dialog h2 {
            margin-bottom: 7px;
            font-size: 22px;
        }

        .generate-paper-dialog p {
            margin-bottom: 17px;
            font-size: 14px;
            line-height: 1.5;
        }

        .generate-paper-actions {
            grid-template-columns: 1fr;
            gap: 9px;
        }

        .generate-paper-button {
            min-height: 67px;
            padding: 11px 13px;
            border-radius: 14px;
        }

        .generate-paper-note {
            margin-top: 13px;
            font-size: 11px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .generate-paper-popup,
        .generate-paper-dialog,
        .generate-paper-button {
            transition: none;
        }
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
        .generate-paper-actions {
            flex-direction: column;
        }

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
<?php endif; ?>
