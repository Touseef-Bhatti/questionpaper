<?php
session_start();
include '../db_connect.php';

$level = $_GET['level'] ?? '';

// SEO Titles based on level
$pageTitle = "Exam Preparation Portal - Solved Past Papers & Test Papers 2026";
$metaDesc = "Access the ultimate Exam Preparation Portal for Class 9, 10, 11, and 12. Download past papers, take online test papers, and prepare for board exams.";

if ($level === 'School') {
    $pageTitle = "Class-9-10-pastPaper-&-Test-Papers";
    $metaDesc = "Download solved past papers and take online test papers for Class 9 and 10. Best resource for Matric board exam preparation in 2026.";
} elseif ($level === 'College') {
    $pageTitle = "Class-11-12-pastPaper-&-Test-Papers";
    $metaDesc = "Get access to Class 11 and 12 past papers and chapter-wise test papers. Prepare for FSc, ICS, and I.Com board exams with our expert practice tests.";
} else {
    $pageTitle = "Class-9-10-11-12-pastPaper-&-Test-Papers";
    $metaDesc = "Access the ultimate Exam Preparation Portal for Class 9, 10, 11, and 12. Download past papers, take online test papers, and prepare for board exams.";
}

// --- Caching Logic ---
require_once '../services/CacheManager.php';
$cacheManager = new CacheManager();
$cacheKey = "select_class_list";
$cachedData = $cacheManager->get($cacheKey);

if ($cachedData && is_array($cachedData)) {
    $classesData = $cachedData;
} else {
    $classQuery = "SELECT class_id, class_name FROM class ORDER BY class_id ASC";
    $classResult = $conn->query($classQuery);
    $classesData = [];

    while ($row = $classResult->fetch_assoc()) {
        $classesData[] = $row;
    }
    
    // Store in cache for 24 hours
    $cacheManager->setex($cacheKey, 86400, $classesData);
}

$assetBase = '../';
include '../header.php';
?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Ahmad Learning Hub</title>
    <meta name="description" content="<?= $metaDesc ?>">
    <link rel="stylesheet" href="../css/exam_prep.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<div class="main-content container">
    <div class="prep-hero shadow-lg">
        <h1>🎓 Exam Preparation Portal</h1>
        <p>Your journey to academic excellence starts here. Select your class to access curated practice tests, past papers, and AI-generated assessments.</p>
    </div>

    <div class="selection-section mb-5">
        <h2 class="text-center mb-5" style="font-family: 'Outfit', sans-serif; font-weight: 800; color: #0f172a;">Step 1: Choose Your Class</h2>
        <div class="selection-grid">
            <?php foreach ($classesData as $row) { 
                $icon = 'fa-user-graduate';
                if (strpos($row['class_name'], '9') !== false) $icon = 'fa-school';
                if (strpos($row['class_name'], '10') !== false) $icon = 'fa-school';
                if (strpos($row['class_name'], '11') !== false) $icon = 'fa-university';
                if (strpos($row['class_name'], '12') !== false) $icon = 'fa-university';
            ?>
                <div class="prep-card" onclick="selectClass(<?= $row['class_id']; ?>, '<?= htmlspecialchars(strtolower(str_replace(' ', '', $row['class_name']))); ?>')">
                    <i class="fas <?= $icon ?>"></i>
                    <h3><?= htmlspecialchars($row['class_name']); ?></h3>
                    <p>Access subject tests</p>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Premium Blog Layout SEO Section -->
    <article class="seo-blog-section blog-layout" style="margin-top: 100px;">
        <div class="blog-container">
            <header class="blog-header">
                <h2 class="blog-title">Master Your Exams with Professional Past Papers and Practice Tests</h2>
                <div class="blog-meta">
                    <span class="category">Exam Prep Guide 2026</span>
                    <span class="read-time">6 min read</span>
                </div>
            </header>

            <section class="blog-content">
                <p class="lead">
                    Success in board exams isn't just about reading; it's about practicing. Our platform provides high-quality <strong>past papers</strong> and <strong>test papers</strong> for students from Class 9 to Bachelor's level, helping you understand the exam pattern and score higher.
                </p>

                <h2>The Power of Solved Past Papers</h2>
                <p>
                    Studying <strong>past papers</strong> is one of the most effective ways to prepare for exams like Matric, Inter, or MDCAT. By reviewing previous year questions, you can identify recurring themes and important topics that are likely to appear in your upcoming exams.
                </p>
                
                <h2>Comprehensive Test Papers for Every Subject</h2>
                <p>
                    Our <strong>test papers</strong> are curated by experts to match the latest 2026 syllabus. Whether you need 9th class physics <strong>practice tests</strong> or 12th class mathematics <strong>model papers</strong>, we have you covered.
                </p>

                <ul>
                    <li><strong>Identify Knowledge Gaps:</strong> Instantly see which chapters need more focus through our <strong>chapter-wise tests</strong>.</li>
                    <li><strong>Improve Time Management:</strong> Practice answering under the pressure of a ticking clock.</li>
                    <li><strong>Reduce Exam Anxiety:</strong> Familiarize yourself with the board exam pattern (MCQs, Short, and Long questions).</li>
                </ul>

                <div class="blog-featured-box">
                    <h4>💡 Pro Tip for Students</h4>
                    <p>Don't just take one test. Re-take the same chapter <strong>test paper</strong> after 2 days to ensure the concepts are locked into your long-term memory. This "spaced repetition" is the secret of top-performing students using our <strong>past papers</strong> portal.</p>
                </div>

                <h3>What We Offer for All Classes</h3>
                <p>
                    From 9th Class Matric to FSc and MDCAT/ECAT levels, our platform covers all core subjects. Every <strong>past paper</strong> and <strong>test paper</strong> is updated according to the latest 2026 board patterns.
                </p>

                <div class="blog-cta-box">
                    <h3>Ready to Ace Your Exams?</h3>
                    <p>Pick your class above and start your first <strong>practice test</strong> today. It's free, fast, and 100% focused on your success with <strong>past papers</strong> and <strong>test papers</strong>!</p>
                </div>
            </section>
        </div>
    </article>
</div>

<script>
    function selectClass(classId, classSlug) {
        // Use class name slug for SEO if available, otherwise fallback to class-ID
        // Pattern: /class9-PastPapers or /class-9-PastPapers
        const slug = classSlug ? classSlug : 'class-' + classId;
        window.location.href = '<?= $assetBase ?>' + slug + '-PastPapers?class_id=' + classId;
    }
</script>

<?php include '../footer.php'; ?>
