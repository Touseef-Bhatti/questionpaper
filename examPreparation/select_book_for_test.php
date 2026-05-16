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

// --- Caching Logic ---
require_once '../services/CacheManager.php';
$cacheManager = new CacheManager();
$cacheKey = "select_book_list_" . $class_id;
$cachedData = $cacheManager->get($cacheKey);

if ($cachedData && is_array($cachedData)) {
    $className = $cachedData['className'];
    $booksData = $cachedData['booksData'];
} else {
    // Fetch class name for SEO
    $classStmt = $conn->prepare("SELECT class_name FROM class WHERE class_id = ?");
    $classStmt->bind_param("i", $class_id);
    $classStmt->execute();
    $className = $classStmt->get_result()->fetch_assoc()['class_name'] ?? 'Class';
    $classStmt->close();

    $stmt = $conn->prepare("SELECT * FROM book WHERE class_id = ? ORDER BY book_name ASC");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $booksResult = $stmt->get_result();
    $booksData = [];
    while ($row = $booksResult->fetch_assoc()) {
        $booksData[] = $row;
    }
    $stmt->close();

    // Store in cache for 24 hours
    $cacheManager->setex($cacheKey, 86400, [
        'className' => $className,
        'booksData' => $booksData
    ]);
}

$assetBase = '../';
include '../header.php';
$displayClassName = $className;
$className = str_replace(' ', '-', $className);
$pageTitle = $className . "-PastPapers";
?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Ahmad Learning Hub</title>
    <meta name="description" content="Prepare for <?= htmlspecialchars($displayClassName) ?> board exams with our comprehensive collection of past papers and test papers for all subjects.">
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
        <article class="seo-blog-section">
            <div class="blog-container">
                <h2 class="blog-title">Subject-Wise Preparation with Authentic Past Papers</h2>
                <div class="blog-content">
                    <p>
                        Selecting the right subject is the first step towards a targeted study plan. Our <strong><?= htmlspecialchars($className) ?> past papers</strong> collection is designed to give you a competitive edge. By focusing on subject-specific <strong>test papers</strong>, you can master complex concepts in Physics, Mathematics, and Biology with ease.
                    </p>
                    <p>
                        Why choose our <strong>past papers</strong>? We provide updated 2026 content that follows the latest board guidelines. Each <strong>test paper</strong> is generated to test your critical thinking and time management skills.
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
