<?php
session_start();
include '../db_connect.php';

$class_id = intval($_GET['class_id'] ?? 0);
$book_id = intval($_GET['book_id'] ?? 0);
$book_name_slug = $_GET['book_name'] ?? '';

// If book_id is missing but book_name is provided (from SEO URL), fetch book_id
if (!$book_id && !empty($book_name_slug)) {
    $book_name = str_replace('-', ' ', $book_name_slug);
    $stmt = $conn->prepare("SELECT book_id FROM book WHERE class_id = ? AND book_name LIKE ?");
    $searchTerm = "%$book_name%";
    $stmt->bind_param("is", $class_id, $searchTerm);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $book_id = $res['book_id'] ?? 0;
    $stmt->close();
}

if (!$class_id || !$book_id) header("Location: index.php");

// Fetch class and book names for SEO
$infoStmt = $conn->prepare("
    SELECT c.class_name, b.book_name 
    FROM class c 
    JOIN book b ON c.class_id = b.class_id 
    WHERE c.class_id = ? AND b.book_id = ?
");
$infoStmt->bind_param("ii", $class_id, $book_id);
$infoStmt->execute();
$info = $infoStmt->get_result()->fetch_assoc();
$className = $info['class_name'] ?? 'Class';
$bookName = $info['book_name'] ?? 'Subject';
$infoStmt->close();

// Fetch chapters
$stmt = $conn->prepare("SELECT * FROM chapter WHERE class_id = ? AND book_id = ? ORDER BY chapter_no ASC");
$stmt->bind_param("ii", $class_id, $book_id);
$stmt->execute();
$chaptersResult = $stmt->get_result();
$chaptersData = [];
while ($row = $chaptersResult->fetch_assoc()) {
    $chaptersData[] = $row;
}
$stmt->close();

// Fetch available pre-created exams for this book
$stmt = $conn->prepare("SELECT * FROM exam_preparations WHERE class_id = ? AND book_id = ? ORDER BY created_at DESC");
$stmt->bind_param("ii", $class_id, $book_id);
$stmt->execute();
$examsResult = $stmt->get_result();
$examsData = [];
while ($row = $examsResult->fetch_assoc()) {
    $examsData[] = $row;
}
$stmt->close();

$assetBase = '../';
include '../header.php';
$pageTitle = str_replace(' ', '-', $className) . "-" . str_replace(' ', '-', $bookName) . "-PastPapers-Online-Test";
?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Ahmad Learning Hub</title>
    <meta name="description" content="Practice chapter-wise test papers and solved past papers for <?= htmlspecialchars($className) ?> <?= htmlspecialchars($bookName) ?>. Custom generate your board exam preparation tests.">
    <link rel="stylesheet" href="../css/exam_prep.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<div class="main-content container">
    <div class="prep-hero shadow-lg">
        <h1>🎯 <?= htmlspecialchars($className) ?> <?= htmlspecialchars($bookName) ?> Prep</h1>
        <p>Access <strong>past papers</strong>, model tests, and chapter-wise <strong>test papers</strong> for <?= htmlspecialchars($className) ?> <?= htmlspecialchars($bookName) ?>. Custom generate your success.</p>
    </div>

    <style>
    .mcqs-featured-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, #4f46e5 0%, #0ea5e9 100%);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 50px;
        box-shadow: 0 15px 35px rgba(79, 70, 229, 0.3);
        text-decoration: none;
        color: white;
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        overflow: hidden;
    }

    .mcqs-featured-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
        border-radius: 50%;
    }

    .mcqs-featured-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(79, 70, 229, 0.4);
        color: white;
    }

    .mcqs-featured-content {
        flex: 1;
        position: relative;
        z-index: 2;
    }

    .mcqs-featured-title {
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 10px;
        font-family: 'Outfit', sans-serif;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .mcqs-featured-title i {
        background: rgba(255,255,255,0.2);
        padding: 10px;
        border-radius: 12px;
    }

    .mcqs-featured-desc {
        font-size: 1.05rem;
        opacity: 0.9;
        max-width: 85%;
        line-height: 1.5;
    }

    .mcqs-featured-btn {
        background: white;
        color: #4f46e5;
        padding: 14px 30px;
        border-radius: 50px;
        font-weight: 800;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        transition: transform 0.2s;
        position: relative;
        z-index: 2;
        white-space: nowrap;
    }

    .mcqs-featured-card:hover .mcqs-featured-btn {
        transform: scale(1.05);
    }

    @media (max-width: 768px) {
        .mcqs-featured-card {
            flex-direction: column;
            text-align: center;
            align-items: center;
            gap: 15px;
            padding: 20px;
            margin-bottom: 35px;
            border-radius: 18px;
        }
        .mcqs-featured-title {
            font-size: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .mcqs-featured-desc {
            max-width: 100%;
            font-size: 0.95rem;
        }
        .mcqs-featured-btn {
            width: 100%;
            justify-content: center;
            padding: 12px 20px;
            font-size: 1rem;
        }
    }
    </style>

    <div class="row">
        <!-- Pre-created Exams -->
        <div class="col-lg-8 mx-auto mb-5">
            <?php
            // Online MCQs Test Card
            $isInter = preg_match('/11|12|inter|higher/i', $className);
            $mcqsRoute = $isInter ? 'class-11-and-12-online-mcqs-prepation-test' : 'online-mcqs-test-for-9th-and-10th-board-exams';
            $mcqsUrl = "{$assetBase}{$mcqsRoute}?class_id={$class_id}&book_id={$book_id}";
            ?>
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

            <h2 class="mb-4 section-title text-center d-block">Practice Tests</h2>
            <div class="practice-tests-grid mb-4">
                <?php foreach ($examsData as $exam): 
                    $bookSlug = urlencode(str_replace(' ', '-', $bookName));
                    $seoExamUrl = "{$assetBase}class-{$class_id}-{$bookSlug}-PastPapers-Online-Test-{$exam['id']}";
                ?>
                    <a href="<?= $seoExamUrl ?>" class="test-square-card">
                        <div class="test-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="test-title"><?= htmlspecialchars($exam['title']) ?></div>
                        <div class="test-stats">
                            <span class="stat-badge mcq-badge"><i class="fas fa-check-circle"></i> <?= $exam['mcq_count'] ?> MCQs</span>
                            <span class="stat-badge short-badge"><i class="fas fa-pen-alt"></i> <?= $exam['short_count'] ?> Short</span>
                            <span class="stat-badge long-badge"><i class="fas fa-file-alt"></i> <?= $exam['long_count'] ?> Long</span>
                        </div>
                        <div class="btn start-test-btn">
                            Start Test <i class="fas fa-play-circle"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($examsData) == 0): ?>
                <div class="prep-card mb-4" style="min-height: 150px; cursor: default;">
                    <i class="fas fa-info-circle" style="color: #6366f1;"></i>
                    <p>No pre-created full <strong>test papers</strong> available for this book yet. Use the Online MCQs Test above to practice!</p>
                </div>
            <?php endif; ?>
 <a href="<?= $assetBase ?>class-<?= $class_id ?>-PastPapers" class="back-grid-button">
                        <i class="fas fa-arrow-left"></i> Back to Subjects
                    </a>
            <!-- SEO Blog Section -->
            <article class="seo-blog-section" style="margin-top: 20px;">
                <h3 class="blog-title">How to Use <?= htmlspecialchars($bookName) ?> Past Papers for Maximum Marks</h3>
                <div class="blog-content">
                    <p>
                        Preparing for <strong><?= htmlspecialchars($className) ?> <?= htmlspecialchars($bookName) ?></strong> requires a strategy. Simply reading the textbook isn't enough. By solving <strong>past papers</strong>, you familiarize yourself with the question wording and the marks distribution.
                    </p>
                    <p>
                        Regularly practicing with <strong>test papers</strong> helps reduce exam anxiety and improves time management. This targeted approach is recommended by toppers for acing <strong>board exams</strong>.
                    </p>
                </div>
            </article>
        </div>
    </div>

</div>

<script>
    // Navigation and interactive logic
</script>

<?php include '../footer.php'; ?>
