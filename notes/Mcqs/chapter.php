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
    $resolved = alh_mcqs_split_book_chapter_from_path($conn, $classId, $bookSlug . '-' . $chapterSlug);
    if ($resolved) {
        $book = $resolved['book'];
        $chapterSlug = (string) $resolved['chapter_slug'];
    }
}

if (!$classRow || !$book || $chapterSlug === '') {
    header('Location: /class-9-10-11-12-mcqs-for-board-exams', true, 302);
    exit;
}

$className = (string) $classRow['class_name'];
$bookId = (int) $book['book_id'];
$bookName = (string) $book['book_name'];
$selectedChapter = alh_mcqs_find_chapter($conn, $classId, $bookId, $chapterSlug);

if (!$selectedChapter) {
    header('Location: ' . alh_mcqs_book_url($classId, $bookName), true, 302);
    exit;
}

$chapterId = (int) $selectedChapter['chapter_id'];
$chapterNo = (int) ($selectedChapter['chapter_no'] ?? 0);
$chapterName = (string) $selectedChapter['chapter_name'];
$chapterLabel = $chapterNo > 0 ? 'Chapter ' . $chapterNo . ': ' . $chapterName : $chapterName;

$hasExplanationColumn = false;
$columnResult = $conn->query("SHOW COLUMNS FROM mcqs LIKE 'explanation'");
if ($columnResult && $columnResult->num_rows > 0) {
    $hasExplanationColumn = true;
}
$explanationSelect = $hasExplanationColumn ? 'explanation' : "'' AS explanation";

$mcqs = [];
$stmt = $conn->prepare("SELECT mcq_id, topic, question, option_a, option_b, option_c, option_d, correct_option, {$explanationSelect}
    FROM mcqs
    WHERE class_id = ? AND book_id = ? AND chapter_id = ?
    ORDER BY mcq_id DESC
    LIMIT 120");
$stmt->bind_param('iii', $classId, $bookId, $chapterId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $mcqs[] = $row;
}
$stmt->close();

$explanationCount = 0;
$topics = [];
foreach ($mcqs as $mcq) {
    if (!empty($mcq['explanation'])) {
        $explanationCount++;
    }
    $topic = trim((string) ($mcq['topic'] ?? ''));
    if ($topic !== '') {
        $topics[$topic] = true;
    }
}
$topicList = array_slice(array_keys($topics), 0, 8);

$chapterSuggestions = [];
$stmt = $conn->prepare("SELECT ch.chapter_id, ch.chapter_no, ch.chapter_name, COUNT(m.mcq_id) AS mcq_count
    FROM chapter ch
    LEFT JOIN mcqs m ON m.chapter_id = ch.chapter_id
    WHERE ch.class_id = ? AND ch.book_id = ? AND ch.chapter_id <> ?
    GROUP BY ch.chapter_id, ch.chapter_no, ch.chapter_name
    ORDER BY
        CASE WHEN ch.chapter_no > ? THEN 0 ELSE 1 END,
        ABS(ch.chapter_no - ?),
        ch.chapter_no ASC,
        ch.chapter_id ASC
    LIMIT 6");
$stmt->bind_param('iiiii', $classId, $bookId, $chapterId, $chapterNo, $chapterNo);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $chapterSuggestions[] = $row;
}
$stmt->close();

$isInter = preg_match('/11|12|inter|higher/i', $className);
$mcqsRoute = $isInter ? 'class-11-and-12-online-mcqs-prepation-test' : 'online-mcqs-test-for-9th-and-10th-board-exams';
$mcqsUrl = "{$assetBase}{$mcqsRoute}?class_id={$classId}&book_id={$bookId}";

$pageTitle = "{$className} {$bookName} {$chapterLabel} MCQs With Explanations";
$pageDesc = "Practice {$className} {$bookName} {$chapterLabel} MCQs with explanations for Pakistani board exams. Includes solved objective questions, answer checking, chapter revision guidance and next chapter suggestions.";
$canonicalPath = alh_mcqs_chapter_url($classId, $bookName, $selectedChapter);
$canonicalUrl = alh_mcqs_abs_url(ltrim($canonicalPath, '/'));
$bookUrl = alh_mcqs_book_url($classId, $bookName);
$classUrl = alh_mcqs_class_url($classId);

$schemaQuestions = [];
foreach (array_slice($mcqs, 0, 20) as $mcq) {
    $options = ['A' => $mcq['option_a'], 'B' => $mcq['option_b'], 'C' => $mcq['option_c'], 'D' => $mcq['option_d']];
    $correctLetter = alh_mcqs_correct_letter($mcq);
    $acceptedAnswer = $correctLetter !== '' ? (string) ($options[$correctLetter] ?? '') : '';
    $schemaQuestions[] = [
        '@type' => 'Question',
        'name' => (string) $mcq['question'],
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text' => $acceptedAnswer !== '' ? $acceptedAnswer : 'See the marked correct option on the page.'
        ]
    ];
}

$quizSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Quiz',
    'name' => $pageTitle,
    'description' => $pageDesc,
    'url' => $canonicalUrl,
    'numberOfQuestions' => count($mcqs),
    'educationalLevel' => $className,
    'assesses' => $bookName . ' ' . $chapterLabel,
    'learningResourceType' => 'Multiple Choice Questions',
    'provider' => ['@type' => 'EducationalOrganization', 'name' => 'Ahmad Learning Hub'],
];
if (!empty($schemaQuestions)) {
    $quizSchema['hasPart'] = $schemaQuestions;
}

$breadcrumbSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'MCQs', 'item' => alh_mcqs_abs_url('class-9-10-11-12-mcqs-for-board-exams')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $className, 'item' => alh_mcqs_abs_url(ltrim($classUrl, '/'))],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $bookName, 'item' => alh_mcqs_abs_url(ltrim($bookUrl, '/'))],
        ['@type' => 'ListItem', 'position' => 4, 'name' => $chapterLabel, 'item' => $canonicalUrl],
    ],
];

$faqSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name' => "How should I revise {$chapterLabel} MCQs?",
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Read the textbook section first, solve the available MCQs without notes, then use the explanations and correct options to identify weak concepts.']
        ],
        [
            '@type' => 'Question',
            'name' => "Are these {$bookName} MCQs official board questions?",
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'They are practice questions for board exam preparation. Students should verify important answers with their current textbook, teacher or board guidance.']
        ],
        [
            '@type' => 'Question',
            'name' => 'What should I study after this chapter?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'After completing this chapter, continue with the suggested next chapters shown near the end of the page.']
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__, 2) . '/includes/google_analytics.php'; ?>
<?php // AdSense review: third-party ads disabled. include_once dirname(__DIR__, 2) . '/includes/monetag_ads.php'; ?>
    <?php include_once dirname(__DIR__, 2) . '/includes/favicons.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Ahmad Learning Hub</title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($className) ?> <?= htmlspecialchars($bookName) ?> <?= htmlspecialchars($chapterLabel) ?> MCQs, <?= htmlspecialchars($bookName) ?> chapter <?= $chapterNo ?> MCQs, MCQs with explanations, Pakistan board MCQs">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link rel="stylesheet" href="<?= $assetBase ?>css/buttons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
    <script type="application/ld+json"><?= json_encode($quizSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <script type="application/ld+json"><?= json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
</head>
<body>
<?php include '../../header.php'; ?>
<main class="alh-mcq-page">
    <section class="alh-mcq-hero">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p><?= htmlspecialchars($pageDesc) ?></p>
        <div class="alh-crumbs">
            <a href="/class-9-10-11-12-mcqs-for-board-exams">MCQs</a> /
            <a href="<?= htmlspecialchars($classUrl) ?>"><?= htmlspecialchars($className) ?></a> /
            <a href="<?= htmlspecialchars($bookUrl) ?>"><?= htmlspecialchars($bookName) ?></a> /
            <?= htmlspecialchars($chapterLabel) ?>
        </div>
    </section>

    <section class="alh-mcq-section alh-chapter-summary" aria-label="Chapter MCQs summary">
        <div>
            <span class="alh-mcq-badge"><?= count($mcqs) ?> MCQs</span>
            <h2><?= htmlspecialchars($chapterLabel) ?> Objective Preparation</h2>
            <p>Use this page for direct chapter practice, answer checking and explanation review. It is available through the clean URL shown in the browser, so students can bookmark and share this exact chapter.</p>
        </div>
        <div class="alh-summary-grid">
            <div><strong><?= count($mcqs) ?></strong><span>Total MCQs</span></div>
            <div><strong><?= $explanationCount ?></strong><span>With explanations</span></div>
            <div><strong><?= count($topicList) ?></strong><span>Topics represented</span></div>
        </div>
        <?php if (!empty($topicList)): ?>
            <p class="alh-topic-line"><strong>Topics found:</strong> <?= htmlspecialchars(implode(', ', $topicList)) ?></p>
        <?php endif; ?>
    </section>

    <a href="<?= htmlspecialchars($mcqsUrl) ?>" class="mcqs-featured-card">
        <div class="mcqs-featured-content">
            <div class="mcqs-featured-title">
                <i class="fas fa-laptop-code"></i> Live Online MCQs Test
            </div>
            <div class="mcqs-featured-desc">
                Create a custom, interactive chapter-wise MCQs quiz for quick revision and test practice.
            </div>
        </div>
        <div class="mcqs-featured-btn">
            Start Quiz <i class="fas fa-arrow-right"></i>
        </div>
    </a>

    <section class="alh-mcq-section">
        <h2><?= htmlspecialchars($chapterName) ?> MCQs With Explanations</h2>
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

    <?php if (!empty($chapterSuggestions)): ?>
        <section class="alh-mcq-section alh-next-chapters" aria-label="Suggested next chapters">
            <h2>After <?= htmlspecialchars($chapterLabel) ?>, Practice Next Chapters</h2>
            <p>Once you finish these MCQs, continue with the next available chapters from <?= htmlspecialchars($bookName) ?> so your revision stays chapter-wise and complete.</p>
            <div class="alh-mcq-grid">
                <?php foreach ($chapterSuggestions as $chapter): ?>
                    <a class="alh-mcq-card" href="<?= htmlspecialchars(alh_mcqs_chapter_url($classId, $bookName, $chapter)) ?>">
                        <span class="alh-mcq-badge"><?= (int) $chapter['mcq_count'] ?> MCQs</span>
                        <h3><?= (int) $chapter['chapter_no'] > 0 ? 'Chapter ' . (int) $chapter['chapter_no'] . ': ' : '' ?><?= htmlspecialchars($chapter['chapter_name']) ?></h3>
                        <p>Open the next chapter MCQs with explanations.</p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <article class="alh-mcq-section">
        <h2><?= htmlspecialchars($chapterLabel) ?> Revision Guide</h2>
        <p>Complete the MCQs first, then review every incorrect answer against the textbook heading where that concept appears. For biology and other science subjects, pay attention to terminology, diagrams, sequence of processes, examples and differences between similar structures or functions.</p>
        <p>This chapter page keeps the exact class, subject, chapter, question count, answer checking, explanations and follow-up chapters together, so students can revise from one focused URL instead of searching through the full book again.</p>
    </article>

    <?php alh_mcqs_seo_content($className, $bookName, $chapterName); ?>
</main>
<?php include '../../footer.php'; ?>

<script>
const isPremium = <?= json_encode($isPremium) ?>;
let mcqAudioContext = null;

function playMcqNotes(notes, waveType = 'sine', volume = 0.3) {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) return;

    if (!mcqAudioContext) {
        mcqAudioContext = new AudioContextClass();
    }

    if (mcqAudioContext.state === 'suspended') {
        mcqAudioContext.resume();
    }

    notes.forEach(({ frequency, delay, duration }) => {
        const oscillator = mcqAudioContext.createOscillator();
        const gain = mcqAudioContext.createGain();
        const startTime = mcqAudioContext.currentTime + delay;

        oscillator.connect(gain);
        gain.connect(mcqAudioContext.destination);
        oscillator.frequency.value = frequency;
        oscillator.type = waveType;
        gain.gain.setValueAtTime(volume, startTime);
        gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
        oscillator.start(startTime);
        oscillator.stop(startTime + duration + 0.05);
    });
}

function playCorrectMcqSound() {
    playMcqNotes([
        { frequency: 523, delay: 0, duration: 0.12 },
        { frequency: 659, delay: 0.12, duration: 0.12 },
        { frequency: 784, delay: 0.24, duration: 0.22 }
    ], 'sine', 0.3);
}

function playIncorrectMcqSound() {
    playMcqNotes([
        { frequency: 311, delay: 0, duration: 0.15 },
        { frequency: 261, delay: 0.15, duration: 0.28 }
    ], 'sawtooth', 0.28);
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
        playCorrectMcqSound();
        feedback.textContent = 'Correct answer: ' + formatCorrectAnswer(button);
        feedback.className = 'alh-feedback good';
    } else {
        playIncorrectMcqSound();
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
