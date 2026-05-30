<?php
$assetBase = '../../';
include '../../db_connect.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/seo_content.php';

$classes = [];
$result = $conn->query("SELECT c.class_id, c.class_name, COUNT(m.mcq_id) AS mcq_count
    FROM class c
    LEFT JOIN mcqs m ON m.class_id = c.class_id
    WHERE c.class_id IN (9, 10, 11, 12)
    GROUP BY c.class_id, c.class_name
    ORDER BY c.class_id ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}

$pageTitle = 'Class 9, 10, 11, 12 MCQs With Explanations For Pakistani Board Exams';
$pageDesc = 'Practice Class 9, 10, 11 and 12 chapter-wise MCQs with explanations for Pakistani board exams. Select your class, subject and chapter for Punjab Board, Federal Board and BISE exam preparation.';
$canonicalUrl = alh_mcqs_abs_url('class-9-10-11-12-mcqs-for-board-exams');
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
    <meta name="keywords" content="class 9 MCQs, class 10 MCQs, class 11 MCQs, class 12 MCQs, chapter wise MCQs with explanations, Pakistan board MCQs, Punjab Board MCQs, Federal Board MCQs">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link rel="stylesheet" href="<?= $assetBase ?>css/buttons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "CollectionPage",
        "name": "<?= htmlspecialchars($pageTitle) ?>",
        "description": "<?= htmlspecialchars($pageDesc) ?>",
        "educationalLevel": "Secondary and Higher Secondary Education",
        "audience": {"@type": "EducationalAudience", "educationalRole": "student"},
        "provider": {"@type": "EducationalOrganization", "name": "Ahmad Learning Hub"}
    }
    </script>
</head>
<body>
<?php include '../../header.php'; ?>
<main class="alh-mcq-page">
    <section class="alh-mcq-hero">
        <h1>Class 9, 10, 11, 12 MCQs With Explanations For Board Exams</h1>
        <p>Choose your class to practice chapter-wise MCQs prepared for Pakistani students following board exam patterns. These objective questions help with daily revision, concept checking and quick board preparation.</p>
    </section>

    <section class="alh-mcq-grid" aria-label="Select class">
        <?php foreach ($classes as $class): ?>
            <a class="alh-mcq-card" href="<?= htmlspecialchars(alh_mcqs_class_url((int) $class['class_id'])) ?>">
                <span class="alh-mcq-badge"><?= (int) $class['mcq_count'] ?> MCQs</span>
                <h2><?= htmlspecialchars($class['class_name']) ?> MCQs</h2>
                <p>Open all subjects and start chapter-wise MCQs with explanations for board exam preparation.</p>
            </a>
        <?php endforeach; ?>
    </section>

    <article class="alh-mcq-section">
        <h2>MCQs Practice For Pakistani Board Students</h2>
        <p>Objective questions are a scoring part of board exams because they test definitions, formulas, concepts, diagrams and textbook facts in a direct way. Ahmad Learning Hub organizes MCQs by class, subject and chapter so students from Class 9, Class 10, Class 11 and Class 12 can revise exactly the topic they are studying.</p>
        <p>Use these MCQs after reading a chapter, before school tests, and during final board exam revision. Each page is designed for fast scanning, answer checking, explanations and repeated practice.</p>
    </article>
    <?php alh_mcqs_seo_content(); ?>
</main>
<?php include '../../footer.php'; ?>
</body>
</html>
