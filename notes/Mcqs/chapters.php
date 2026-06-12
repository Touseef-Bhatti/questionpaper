<?php
session_start();
$assetBase = '../../';
include '../../db_connect.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/seo_content.php';
require_once '../../middleware/SubscriptionCheck.php';

$isPremium = false;
if (isset($_SESSION['user_id'])) {
    $subscription = getSubscriptionInfo();
    $isPremium = $subscription ? $subscription['is_premium'] : false;
}

$classId = (int) ($_GET['class_id'] ?? 0);
$bookSlug = (string) ($_GET['book_name'] ?? '');
$chapterSlug = (string) ($_GET['chapter_slug'] ?? '');

$stmt = $conn->prepare("SELECT class_name FROM class WHERE class_id = ? LIMIT 1");
$stmt->bind_param('i', $classId);
$stmt->execute();
$classRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$book = $classRow ? alh_mcqs_find_book($conn, $classId, $bookSlug) : null;
if (!$book && $classRow && $bookSlug !== '') {
    $resolvedPath = $chapterSlug !== '' ? $bookSlug . '-' . $chapterSlug : $bookSlug;
    $resolved = alh_mcqs_split_book_chapter_from_path($conn, $classId, $resolvedPath);
    if ($resolved) {
        $book = $resolved['book'];
        $chapterSlug = (string) $resolved['chapter_slug'];
    }
}
if (!$classRow || !$book) {
    header('Location: /class-9-10-11-12-mcqs-for-board-exams', true, 302);
    exit;
}

$className = $classRow['class_name'];
$bookId = (int) $book['book_id'];
$bookName = $book['book_name'];
$selectedChapter = $chapterSlug !== '' ? alh_mcqs_find_chapter($conn, $classId, $bookId, $chapterSlug) : null;

$chapters = [];
$stmt = $conn->prepare("SELECT ch.chapter_id, ch.chapter_no, ch.chapter_name, COUNT(m.mcq_id) AS mcq_count
    FROM chapter ch
    LEFT JOIN mcqs m ON m.chapter_id = ch.chapter_id
    WHERE ch.class_id = ? AND ch.book_id = ?
    GROUP BY ch.chapter_id, ch.chapter_no, ch.chapter_name
    ORDER BY ch.chapter_no ASC, ch.chapter_id ASC");
$stmt->bind_param('ii', $classId, $bookId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $chapters[] = $row;
}
$stmt->close();

$mcqs = [];
if ($selectedChapter) {
    $hasExplanationColumn = false;
    $columnResult = $conn->query("SHOW COLUMNS FROM mcqs LIKE 'explanation'");
    if ($columnResult && $columnResult->num_rows > 0) {
        $hasExplanationColumn = true;
    }
    $explanationSelect = $hasExplanationColumn ? 'explanation' : "'' AS explanation";

    $stmt = $conn->prepare("SELECT mcq_id, topic, question, option_a, option_b, option_c, option_d, correct_option, {$explanationSelect}
        FROM mcqs
        WHERE class_id = ? AND book_id = ? AND chapter_id = ?
        ORDER BY mcq_id DESC
        LIMIT 80");
    $chapterId = (int) $selectedChapter['chapter_id'];
    $stmt->bind_param('iii', $classId, $bookId, $chapterId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $mcqs[] = $row;
    }
    $stmt->close();
}

$chapterTitle = $selectedChapter ? ' ' . $selectedChapter['chapter_name'] : '';
$pageTitle = $selectedChapter
    ? "{$className} {$bookName} {$selectedChapter['chapter_name']} MCQs With Explanations"
    : "{$className} {$bookName} Chapter Wise MCQs With Explanations";
$pageDesc = $selectedChapter
    ? "Practice {$className} {$bookName} {$selectedChapter['chapter_name']} MCQs with explanations for Pakistani board exams. Revise objective questions with correct options for chapter-wise preparation."
    : "Select a chapter for {$className} {$bookName} MCQs with explanations. Chapter-wise objective questions for Pakistani board exam preparation.";
$canonicalPath = $selectedChapter
    ? alh_mcqs_chapter_url($classId, $bookName, $selectedChapter)
    : alh_mcqs_book_url($classId, $bookName);
$canonicalUrl = alh_mcqs_abs_url(ltrim($canonicalPath, '/'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__, 2) . '/includes/google_analytics.php'; ?>
    <?php include_once dirname(__DIR__, 2) . '/includes/monetag_ads.php'; ?>
    <?php include_once dirname(__DIR__, 2) . '/includes/favicons.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Ahmad Learning Hub</title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($className) ?> <?= htmlspecialchars($bookName) ?> MCQs, <?= htmlspecialchars($bookName) ?> chapter wise MCQs, <?= htmlspecialchars($bookName) ?> MCQs with explanations, Pakistan board MCQs">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link rel="stylesheet" href="<?= $assetBase ?>css/buttons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
    <?php if ($selectedChapter): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Quiz",
        "name": "<?= htmlspecialchars($pageTitle) ?>",
        "description": "<?= htmlspecialchars($pageDesc) ?>",
        "numberOfQuestions": <?= count($mcqs) ?>,
        "educationalLevel": "<?= htmlspecialchars($className) ?>",
        "assesses": "<?= htmlspecialchars($bookName . $chapterTitle) ?>",
        "learningResourceType": "Multiple Choice Questions",
        "provider": {"@type": "EducationalOrganization", "name": "Ahmad Learning Hub"}
    }
    </script>
    <?php endif; ?>
</head>
<body>
<?php include '../../header.php'; ?>
<main class="alh-mcq-page">
    <section class="alh-mcq-hero">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p><?= htmlspecialchars($pageDesc) ?></p>
        <div class="alh-crumbs">
            <a href="/class-9-10-11-12-mcqs-for-board-exams">MCQs</a> /
            <a href="<?= htmlspecialchars(alh_mcqs_class_url($classId)) ?>"><?= htmlspecialchars($className) ?></a> /
            <?= htmlspecialchars($bookName) ?>
        </div>
    </section>

    <?php if (!$selectedChapter): ?>
        <section class="alh-mcq-grid" aria-label="Select chapter">
            <?php foreach ($chapters as $chapter): ?>
                <div class="alh-mcq-card" onclick="selectChapter('<?= htmlspecialchars(alh_mcqs_chapter_url($classId, $bookName, $chapter)) ?>')" style="cursor: pointer;">
                    <span class="alh-mcq-badge"><?= (int) $chapter['mcq_count'] ?> MCQs</span>
                    <h2><?= (int) $chapter['chapter_no'] > 0 ? 'Chapter ' . (int) $chapter['chapter_no'] . ': ' : '' ?><?= htmlspecialchars($chapter['chapter_name']) ?></h2>
                    <p>Open MCQs with explanations for this chapter.</p>
                </div>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <?php
        // Online MCQs Test Card
        $isInter = preg_match('/11|12|inter|higher/i', $className);
        $mcqsRoute = $isInter ? 'class-11-and-12-online-mcqs-prepation-test' : 'online-mcqs-test-for-9th-and-10th-board-exams';
        $mcqsUrl = "{$assetBase}{$mcqsRoute}?class_id={$classId}&book_id={$bookId}";
        ?>
        <br><br>
        <a href="<?= $mcqsUrl ?>" class="mcqs-featured-card">
            <div class="mcqs-featured-content">
                <div class="mcqs-featured-title">
                    <i class="fas fa-laptop-code"></i> Live Online MCQs Test
                </div>
                <div class="mcqs-featured-desc">
                    Create a custom, interactive chapter-wise MCQs quiz. Perfect for quick revision and entry test preparation!
                </div>
            </div>
            <div class="mcqs-featured-btn">
                Start Quiz <i class="fas fa-arrow-right"></i>
            </div>
        </a>
        <section class="alh-mcq-section">
            <h2><?= htmlspecialchars($selectedChapter['chapter_name']) ?> MCQs With Explanations</h2>
            <?php if (count($mcqs) === 0): ?>
                <div class="alh-empty">No MCQs are available for this chapter yet.</div>
            <?php else: ?>
                <div class="alh-mcq-list">
                    <?php foreach ($mcqs as $index => $mcq): ?>
                        <?php
                        $options = ['A' => $mcq['option_a'], 'B' => $mcq['option_b'], 'C' => $mcq['option_c'], 'D' => $mcq['option_d']];
                        $correctLetter = alh_mcqs_correct_letter($mcq);
                        $correctAnswerText = $correctLetter !== '' ? (string) ($options[$correctLetter] ?? '') : '';
                        ?>
                        <article class="alh-question">
                            <div class="alh-question-title">Q<?= $index + 1 ?>. <?= htmlspecialchars($mcq['question']) ?></div>
                            <div class="alh-options">
                                <?php foreach ($options as $letter => $option): ?>
                                    <button class="alh-option" type="button" data-correct="<?= $letter === $correctLetter ? '1' : '0' ?>" data-answer="<?= htmlspecialchars($correctAnswerText) ?>" data-answer-letter="<?= htmlspecialchars($correctLetter) ?>" onclick="checkMcqOption(this)">
                                        <strong><?= $letter ?>.</strong>
                                        <span><?= htmlspecialchars((string) $option) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="alh-question-actions">
                                <button class="alh-explain-btn" type="button" onclick="toggleExplanation(this)">Show Explanation</button>
                                <span class="alh-feedback" aria-live="polite"></span>
                            </div>
                            <div class="alh-explanation">
                                <?php if (!empty($mcq['explanation'])): ?>
                                    <?= nl2br(htmlspecialchars((string) $mcq['explanation'])) ?>
                                <?php else: ?>
                                    No explanation is available in the database for this MCQ yet.
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <article class="alh-mcq-section">
        <h2><?= htmlspecialchars($bookName) ?> MCQs Preparation Strategy</h2>
        <p>For board exams, chapter-wise MCQs should be practiced after reading the textbook line by line. Focus on definitions, laws, formulas, examples, diagrams and short conceptual statements because these are common sources of objective questions in Pakistani board papers.</p>
        <p>Revise one chapter, solve its MCQs with explanations, then return to the textbook for every incorrect option. This method is especially useful for Class 9, 10, 11 and 12 students preparing for BISE exams.</p>
    </article>
    <?php alh_mcqs_seo_content($className, $bookName, $selectedChapter['chapter_name'] ?? ''); ?>
</main>
<?php include '../../footer.php'; ?>

<script>
const isPremium = <?= json_encode($isPremium) ?>;

function selectChapter(destinationUrl) {
    window.location.href = destinationUrl;
}

function checkMcqOption(button) {
    const question = button.closest('.alh-question');
    const options = question.querySelectorAll('.alh-option');
    const feedback = question.querySelector('.alh-feedback');
    const isCorrect = button.dataset.correct === '1';

    options.forEach(option => {
        option.disabled = true;
        if (option.dataset.correct === '1') {
            option.classList.add('is-correct');
        }
    });

    if (isCorrect) {
        feedback.textContent = 'Correct answer: ' + formatCorrectAnswer(button);
        feedback.className = 'alh-feedback good';
    } else {
        button.classList.add('is-wrong');
        feedback.textContent = 'Correct answer: ' + formatCorrectAnswer(button);
        feedback.className = 'alh-feedback bad';
    }
}

function formatCorrectAnswer(button) {
    const letter = button.dataset.answerLetter || '';
    const answer = button.dataset.answer || '';
    if (letter && answer) return letter + '. ' + answer;
    if (answer) return answer;
    if (letter) return letter;
    return 'Not available';
}

function toggleExplanation(button) {
    const explanation = button.closest('.alh-question').querySelector('.alh-explanation');
    const isOpen = explanation.classList.toggle('is-open');
    button.textContent = isOpen ? 'Hide Explanation' : 'Show Explanation';
}
</script>
</body>
</html>
