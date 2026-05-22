<?php
session_start();
include '../db_connect.php';

$class_id = intval($_GET['class_id'] ?? 0);
$class_slug = $_GET['class_slug'] ?? '';

// If class_id is missing but class_slug is provided (from SEO URL), fetch class_id
if (!$class_id && !empty($class_slug)) {
    // Try to extract ID if it's class-ID
    if (preg_match('/class-([0-9]+)/i', $class_slug, $matches)) {
        $class_id = intval($matches[1]);
    } else {
        // Search by class name (removing spaces for match)
        $stmt = $conn->prepare("SELECT class_id FROM class WHERE REPLACE(class_name, ' ', '') LIKE ?");
        $searchTerm = "%$class_slug%";
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $class_id = $res['class_id'] ?? 0;
        $stmt->close();
    }
}

if (!$class_id) header("Location: index.php");


$stmt = $conn->prepare("SELECT class_name FROM class WHERE class_id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$className = $res['class_name'] ?? 'Class';
$stmt->close();

$assetBase = '../';
include '../header.php';
$displayClassName = $className;
$pageTitle = $displayClassName . " Board Exam Preparation 2026 - Online Test Papers & Past Papers";
?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Ahmad Learning Hub</title>
    <?php $metaDesc = "Prepare for " . $displayClassName . " board exams with our comprehensive collection of past papers and test papers for all subjects."; ?>
    <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>">
    <link rel="stylesheet" href="../css/exam_prep.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<div class="main-content container">
    <div class="prep-hero shadow-lg">
        <h1>📖 <?= htmlspecialchars($className) ?> Subjects</h1>
        <p>Expertly curated <strong>past papers</strong> and <strong>test papers</strong> for every major subject. Select your book to start practicing now.</p>
    </div>

    <div class="selection-section mb-5">
        <h2 class="text-center section-title mb-5">Step 2: Select a Subject</h2>
        <div class="selection-grid">
            <?php foreach ($booksData as $row) { 
                $icon = 'fa-book';
                $bName = strtolower($row['book_name']);
                if (strpos($bName, 'physics') !== false) $icon = 'fa-atom';
                elseif (strpos($bName, 'chemistry') !== false) $icon = 'fa-flask';
                elseif (strpos($bName, 'biology') !== false) $icon = 'fa-dna';
                elseif (strpos($bName, 'math') !== false) $icon = 'fa-calculator';
                elseif (strpos($bName, 'computer') !== false) $icon = 'fa-laptop-code';
                elseif (strpos($bName, 'english') !== false) $icon = 'fa-language';
            ?>
                <div class="prep-card" onclick="selectBook('<?= htmlspecialchars(urlencode(str_replace(' ', '-', $row['book_name']))); ?>')">
                    <i class="fas <?= $icon ?>"></i>
                    <h3><?= htmlspecialchars($row['book_name']); ?></h3>
                    <p>Start <strong>test paper</strong></p>
                </div>
            <?php } ?>
        </div>

        <!-- SEO Blog Section -->
        <article class="seo-blog-section" style="margin-top: 60px; background: #f8fafc; border-radius: 20px; padding: 40px; border: 1px solid #e2e8f0;">
            <div class="blog-container">
                <h2 class="blog-title" style="font-size: 2.2rem; font-weight: 800; color: #0f172a; margin-bottom: 30px;">Strategic Subject Selection for <?= htmlspecialchars($displayClassName) ?> Board Exam Preparations</h2>
                <div class="blog-content" style="font-size: 1.1rem; line-height: 1.8; color: #475569;">
                    <p>
                        Choosing which subject to focus on is a critical decision in your <strong><?= htmlspecialchars($displayClassName) ?> board exam preparations</strong>. For students in <strong>Class 9, 10, 11, and 12</strong>, each subject carries its own set of challenges and marking criteria. Whether you are in <strong>school</strong> or <strong>college</strong>, a targeted approach to each book is the secret to a high aggregate. Our <strong>online exam preparation</strong> tools are designed to help you master each <strong><?= htmlspecialchars($displayClassName) ?></strong> book individually.
                    </p>
                    
                    <h3 style="color: #1e293b; margin-top: 30px; margin-bottom: 15px;">Identifying High-Yield Topics in <?= htmlspecialchars($displayClassName) ?></h3>
                    <p>
                        Every examiner looks for certain "key concepts" in an <strong>exam test paper</strong>. By focusing on the <strong>important question of that selected book</strong>, you can ensure that you are spending your time efficiently. For <strong>matric</strong> students, subjects like Mathematics and Physics require rigorous practice of theorems and numericals. For <strong>inter</strong> students, especially those in Pre-Medical or Pre-Engineering, the depth of conceptual understanding required in <strong>college</strong> is much higher than in <strong>school</strong>.
                    </p>

                    <h3 style="color: #1e293b; margin-top: 30px; margin-bottom: 15px;">The Benefits of Subject-Wise Testing for <?= htmlspecialchars($displayClassName) ?></h3>
                    <p>
                        Randomly studying different books can lead to confusion. Instead, use our <strong>online exam preparation</strong> platform to dedicate entire days to a single subject. This "subject immersion" technique helps in better retention of complex information. When you generate a <strong>matric exam test paper</strong> or an <strong>inter</strong> mock exam, you are essentially rehearsing for the real board day.
                    </p>

                    <div style="background: white; padding: 25px; border-radius: 12px; margin: 30px 0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        <h4 style="color: #4f46e5; margin-bottom: 10px;"><i class="fas fa-check-circle"></i> Why <?= htmlspecialchars($displayClassName) ?> Prep Matters:</h4>
                        <ul style="margin-bottom: 0; padding-left: 20px;">
                            <li>Focuses on the unique <strong>exam test paper</strong> pattern of each board.</li>
                            <li>Helps in mastering the <strong>important question of that selected book</strong>.</li>
                            <li>Builds confidence for both <strong>school</strong> and <strong>college</strong> board exams.</li>
                            <li>Provides a realistic simulation of <strong>Class 9-10-11-12</strong> board environments.</li>
                        </ul>
                    </div>

                    <p>
                        Ready to begin? Select a subject from the grid above to start your focused <strong>online exam preparation</strong> for <strong><?= htmlspecialchars($displayClassName) ?></strong>. Whether it's the complex reactions of Chemistry or the intricate details of English Literature, we have the right <strong>board exam preparations</strong> resources to help you succeed in <strong>Class 9, 10, 11, and 12</strong>.
                    </p>
                </div>
            </div>
        </article>

        <div class="text-center mt-5">
            <a href="<?= $assetBase ?>online-test-papers-preparation" class="btn btn-outline-secondary" style="border-radius: 12px; padding: 10px 25px;">
                <i class="fas fa-arrow-left"></i> Back to Classes
            </a>
        </div>
    </div>
</div>

<script>
    function selectBook(bookName) {
        // Updated to SEO-friendly URL pattern: class-X-SubjectName-PastPapers
        window.location.href = '<?= $assetBase ?>class-<?= $class_id ?>-' + bookName + '-PastPapers';
    }
</script>

<?php include '../footer.php'; ?>
