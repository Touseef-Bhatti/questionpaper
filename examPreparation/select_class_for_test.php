<?php
session_start();
include '../db_connect.php';

$level = $_GET['level'] ?? '';

// SEO Titles based on level
$pageTitle = "Board Exam Preparation Portal 2026 - Ahmad Learning Hub";
$metaDesc = "The ultimate Exam Preparation Portal for Class 9, 10, 11, and 12. Get solved past papers, important questions, and take online test papers for all subjects.";

if ($level === 'School') {
    $pageTitle = "Class 9 & 10 Matric Board Exam Preparation 2026 - Online Test Papers";
    $metaDesc = "Prepare for Class 9 and 10 Matric board exams with Ahmad Learning Hub. Access solved past papers, important questions, and online test papers for all school subjects.";
} elseif ($level === 'College') {
    $pageTitle = "Class 11 & 12 Intermediate Board Exam Preparation 2026 - Online Test Papers";
    $metaDesc = "Achieve top marks in Class 11 and 12 Intermediate exams. Get the best online exam preparation tools, past papers, and important questions for all college subjects.";
} else {
    $pageTitle = "Class 9-10-11-12 Board Exam Preparation & Online Test Papers 2026";
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

    <?php
    // Dynamic variables for SEO content customization
    $eduLevelTitle = ($level === 'School') ? 'Matric (Class 9 & 10)' : (($level === 'College') ? 'Intermediate (Class 11 & 12)' : 'Matric & Inter (Class 9-12)');
    $eduType = ($level === 'School') ? 'school' : 'college';
    $targetClasses = ($level === 'School') ? 'Class 9 and 10' : (($level === 'College') ? 'Class 11 and 12' : 'Class 9, 10, 11, and 12');
    ?>
    <!-- Premium Long-Form SEO Content Section -->
    <article class="seo-blog-section blog-layout" style="margin-top: 80px;">
        <div class="blog-container" style="max-width: 1000px; margin: 0 auto; padding: 0 20px;">
            <header class="blog-header" style="text-align: center; margin-bottom: 50px;">
                <h2 class="blog-title" style="font-size: 2.8rem; font-weight: 800; background: linear-gradient(135deg, #4f46e5, #0ea5e9); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Ultimate 2026 Guide: <?= $eduLevelTitle ?> Board Exam Preparations</h2>
                <div class="blog-meta" style="margin-top: 15px; color: #64748b; font-weight: 600;">
                    <span class="category"><i class="fas fa-bookmark"></i> Specialized <?= $eduType ?> Guide</span>
                    <span class="read-time" style="margin-left: 20px;"><i class="fas fa-clock"></i> 15 min read</span>
                </div>
            </header>

            <section class="blog-content" style="font-size: 1.15rem; line-height: 1.8; color: #334155;">
                <p class="lead" style="font-size: 1.3rem; font-weight: 500; color: #1e293b; margin-bottom: 30px;">
                    Achieving top marks in your <strong><?= $eduLevelTitle ?> board exam preparations</strong> is more than just a goal—it's a milestone for your academic future. Whether you are currently in <strong><?= $targetClasses ?></strong>, our platform offers the most advanced <strong>online exam preparation</strong> tools. For every student in <strong><?= $eduType ?></strong>, practicing with a realistic <strong>exam test paper</strong> is the definitive path to excellence.
                </p>

                <h2 style="font-size: 2rem; color: #0f172a; margin-top: 50px; margin-bottom: 25px;">1. Strategic Planning for <?= $eduLevelTitle ?> Students</h2>
                <p>
                    Success for <strong><?= $eduType ?></strong> students starts with a solid plan. For <strong><?= $targetClasses ?></strong>, understanding the specific board patterns and pairing schemes is essential. Our <strong>board exam preparations</strong> resources are tailored to match the latest 2026 requirements for all major boards.
                </p>
                <p>
                    If you are a <strong>matric</strong> student, your focus should be on building strong foundations. If you are an <strong>inter</strong> student, conceptual depth in <strong>college</strong> subjects is what will set you apart. Use our <strong>online exam preparation</strong> system to create a timetable that prioritizes your weakest areas.
                </p>

                <h2 style="font-size: 2rem; color: #0f172a; margin-top: 50px; margin-bottom: 25px;">2. Maximizing Scores with Online Exam Preparation</h2>
                <p>
                    Traditional studying is no longer enough. To truly excel, students in <strong><?= $targetClasses ?></strong> must engage with interactive <strong>exam test papers</strong>. Our <strong>online exam preparation</strong> platform mimics the actual board environment, helping you build the stamina needed for the final day.
                </p>

                <div class="blog-featured-box" style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border-left: 6px solid #0ea5e9; padding: 30px; border-radius: 12px; margin: 40px 0;">
                    <h4 style="color: #0369a1; font-size: 1.4rem; margin-bottom: 15px;"><i class="fas fa-lightbulb"></i> Pro Tip for <?= $eduLevelTitle ?></h4>
                    <p style="margin-bottom: 0;">Did you know that 80% of board questions often repeat from the <strong>important question of that selected book</strong>? By using our <strong>exam test paper</strong> generator, you can specifically target these high-frequency topics and secure your A+ grade in <strong>board exam preparations</strong>.</p>
                </div>

                <h2 style="font-size: 2rem; color: #0f172a; margin-top: 50px; margin-bottom: 25px;">3. Subject-Specific Focus for <?= $eduLevelTitle ?></h2>
                <p>
                    Whether it's the complex derivations in Physics or the detailed essays in English, each <strong><?= $eduType ?></strong> subject requires a unique strategy. For <strong>Class 9-10-11-12</strong>, we provide subject-wise <strong>online exam preparation</strong> modules. Make sure to identify the <strong>important question of that selected book</strong> for every subject you study.
                </p>

                <h2 style="font-size: 2rem; color: #0f172a; margin-top: 50px; margin-bottom: 25px;">4. The Value of Continuous Testing</h2>
                <p>
                    Testing is not just an evaluation; it's a learning tool. For <strong><?= $targetClasses ?></strong>, solving an <strong>exam test paper</strong> daily reduces anxiety and sharpens memory. Our <strong>board exam preparations</strong> portal is designed to be your constant companion in your <strong>school</strong> and <strong>college</strong> exams.
                </p>

                <div class="blog-cta-box" style="background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; padding: 40px; border-radius: 20px; text-align: center; margin-top: 60px; box-shadow: 0 20px 40px rgba(79, 70, 229, 0.2);">
                    <h3 style="font-size: 2.2rem; margin-bottom: 15px; font-weight: 800; color: white;">Ace Your <?= $eduLevelTitle ?> Exams!</h3>
                    <p style="font-size: 1.2rem; margin-bottom: 25px; opacity: 0.9;">Start your specialized <strong>online exam preparation</strong> now and dominate your <strong>board exam preparations</strong>.</p>
                    <a href="#selection-grid" class="ALH_join" style="background: white; color: #4f46e5 !important; padding: 15px 40px; border-radius: 50px; font-size: 1.2rem; font-weight: 800; text-decoration: none; display: inline-block;">Select Your Class Below <i class="fas fa-arrow-right"></i></a>
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
