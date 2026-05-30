<?php
$assetBase = '../../';
include '../../db_connect.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/seo_content.php';

$classId = (int) ($_GET['class_id'] ?? 0);
$stmt = $conn->prepare("SELECT class_name FROM class WHERE class_id = ? LIMIT 1");
$stmt->bind_param('i', $classId);
$stmt->execute();
$classRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$classRow) {
    header('Location: /class-9-10-11-12-mcqs-for-board-exams', true, 302);
    exit;
}
$className = $classRow['class_name'];

$books = [];
$stmt = $conn->prepare("SELECT b.book_id, b.book_name, COUNT(m.mcq_id) AS mcq_count
    FROM book b
    LEFT JOIN mcqs m ON m.book_id = b.book_id AND m.class_id = b.class_id
    WHERE b.class_id = ?
    GROUP BY b.book_id, b.book_name
    ORDER BY b.book_id ASC");
$stmt->bind_param('i', $classId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}
$stmt->close();

$pageTitle = "{$className} All Subjects MCQs With Explanations";
$pageDesc = "Practice {$className} all subjects MCQs with explanations for Pakistani board exams. Select Physics, Chemistry, Biology, Mathematics, Computer Science, English or your board subject for chapter-wise preparation.";
$canonicalUrl = alh_mcqs_abs_url(ltrim(alh_mcqs_class_url($classId), '/'));
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
    <meta name="keywords" content="<?= htmlspecialchars($className) ?> MCQs, <?= htmlspecialchars($className) ?> all subjects MCQs, board exam MCQs with explanations, chapter wise MCQs Pakistan">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link rel="stylesheet" href="<?= $assetBase ?>css/buttons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php include '../../header.php'; ?>
<main class="alh-mcq-page">
    <section class="alh-mcq-hero">
        <h1><?= htmlspecialchars($className) ?> All Subjects MCQs With Explanations</h1>
        <p>Select your subject to open chapter-wise MCQs for board preparation. This section is built for Pakistani students who need quick, focused and answer-supported objective practice.</p>
        <div class="alh-crumbs"><a href="/class-9-10-11-12-mcqs-for-board-exams">MCQs</a> / <?= htmlspecialchars($className) ?></div>
    </section>

    <section class="alh-mcq-grid" aria-label="Select subject">
        <?php foreach ($books as $book): ?>
            <a class="alh-mcq-card" href="<?= htmlspecialchars(alh_mcqs_book_url($classId, (string) $book['book_name'])) ?>">
                <span class="alh-mcq-badge"><?= (int) $book['mcq_count'] ?> MCQs</span>
                <h2><?= htmlspecialchars($book['book_name']) ?></h2>
                <p>Practice chapter-wise <?= htmlspecialchars($book['book_name']) ?> MCQs with explanations.</p>
            </a>
        <?php endforeach; ?>
    </section>

    <article class="alh-mcq-section">
        <h2>How To Prepare <?= htmlspecialchars($className) ?> Objective Questions</h2>
        <p>Start with one subject, complete its chapter MCQs, and mark the questions you answer incorrectly. For board exams, repeated practice is useful because MCQs often come from definitions, textbook lines, numerical concepts, diagrams, grammar rules and key formulas.</p>
        <p>Students should revise MCQs subject-wise before attempting full-length tests. This approach builds confidence and improves speed in the objective section of Pakistani board papers.</p>
    </article>
    <?php alh_mcqs_seo_content($className); ?>
</main>
<?php include '../../footer.php'; ?>
</body>
</html>
