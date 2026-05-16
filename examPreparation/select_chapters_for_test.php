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

// --- Caching Logic ---
require_once '../services/CacheManager.php';
$cacheManager = new CacheManager();
$cacheKey = "select_chapters_" . $class_id . "_" . $book_id;
$cachedData = $cacheManager->get($cacheKey);

if ($cachedData && is_array($cachedData)) {
    $className = $cachedData['className'];
    $bookName = $cachedData['bookName'];
    $chaptersData = $cachedData['chaptersData'];
    $examsData = $cachedData['examsData'];
} else {
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

    // Store in cache for 24 hours
    $cacheManager->setex($cacheKey, 86400, [
        'className' => $className,
        'bookName' => $bookName,
        'chaptersData' => $chaptersData,
        'examsData' => $examsData
    ]);
}

$assetBase = '../';
include '../header.php';
$pageTitle = $className . " " . $bookName . " Online Exam Preparation & Test Papers 2026";
?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Ahmad Learning Hub</title>
    <?php $metaDesc = "Boost your " . $className . " " . $bookName . " board exam score. Take chapter-wise online tests and access important question papers for Class 9-12."; ?>
    <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>">
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

    /* Custom Test Banner */
    .custom-test-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        border-radius: 20px;
        padding: 25px 35px;
        margin-bottom: 40px;
        box-shadow: 0 12px 25px rgba(5, 150, 105, 0.2);
        text-decoration: none !important;
        color: white !important;
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .custom-test-banner::before {
        content: '';
        position: absolute;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        top: -100px;
        right: -50px;
        border-radius: 50%;
    }

    .custom-test-banner:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(5, 150, 105, 0.3);
    }

    .custom-test-content {
        flex: 1;
        z-index: 1;
    }

    .custom-test-title {
        font-size: 1.6rem;
        font-weight: 800;
        margin-bottom: 8px;
        font-family: 'Outfit', sans-serif;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .custom-test-desc {
        font-size: 1rem;
        opacity: 0.9;
        max-width: 85%;
        line-height: 1.4;
    }

    .custom-test-btn {
        background: white;
        color: #059669;
        padding: 14px 28px;
        border-radius: 50px;
        font-weight: 800;
        font-size: 1.05rem;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        transition: transform 0.2s;
        z-index: 1;
        white-space: nowrap;
    }

    .custom-test-banner:hover .custom-test-btn {
        transform: scale(1.05);
    }

    @media (max-width: 768px) {
        .custom-test-banner {
            flex-direction: column;
            text-align: center;
            padding: 25px 20px;
            gap: 20px;
        }
        .custom-test-title {
            font-size: 1.4rem;
            justify-content: center;
        }
        .custom-test-desc {
            max-width: 100%;
            font-size: 0.9rem;
        }
        .custom-test-btn {
            width: 100%;
            justify-content: center;
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

            // Custom Test Redirect URL based on Class Level
            $customTestFile = $isInter ? 'Class-11-and-12-Online-Question-Paper-generator' : 'Class-9-and-10-Online-Question-Paper-generator';
            $customTestUrl = "{$assetBase}{$customTestFile}";
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

            <a href="<?= $customTestUrl ?>" class="custom-test-banner">
                <div class="custom-test-content">
                    <div class="custom-test-title">
                        <i class="fas fa-edit"></i> Create Custom Test
                    </div>
                    <div class="custom-test-desc">
                        Generate a professional question paper with your own choice of chapters and questions.
                    </div>
                </div>
                <div class="custom-test-btn">
                    Create Now <i class="fas fa-plus-circle"></i>
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
            <article class="seo-blog-section" style="margin-top: 40px; background: linear-gradient(to bottom, #ffffff, #f1f5f9); border-radius: 24px; padding: 45px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div class="blog-container">
                    <h3 class="blog-title" style="font-size: 2.2rem; font-weight: 800; color: #0f172a; margin-bottom: 30px; border-bottom: 3px solid #4f46e5; display: inline-block; padding-bottom: 10px;">Mastering <?= htmlspecialchars($className) ?> <?= htmlspecialchars($bookName) ?>: Chapter-Wise Exam Strategies</h3>
                    <div class="blog-content" style="font-size: 1.1rem; line-height: 1.8; color: #334155;">
                        <p>
                            The final stage of your <strong><?= htmlspecialchars($className) ?> <?= htmlspecialchars($bookName) ?> board exam preparations</strong> is chapter-wise mastery. For students in <strong>Class 9, 10, 11, and 12</strong>, the ability to break down a vast syllabus into manageable chapters is the key to reducing stress and increasing retention. Whether you are a <strong>matric</strong> student in <strong>school</strong> or an <strong>inter</strong> student in <strong>college</strong>, our <strong>online exam preparation</strong> tools are here to simplify your <strong><?= htmlspecialchars($bookName) ?></strong> revision.
                        </p>
                        
                        <h4 style="color: #1e293b; margin-top: 30px; margin-bottom: 15px; font-weight: 700;">Why Focus on <?= htmlspecialchars($bookName) ?> Chapter-Wise Test Papers?</h4>
                        <p>
                            A comprehensive <strong>exam test paper</strong> can often feel overwhelming if you haven't mastered the individual building blocks. By taking chapter-specific quizzes, you can pinpoint exactly where you need more work in <strong><?= htmlspecialchars($bookName) ?></strong>. Is it the first chapter's definitions or the third chapter's numericals? Our <strong>online exam preparation</strong> platform gives you that granular insight. For <strong>inter</strong> board exams, where the competition in <strong>college</strong> is fierce, this level of detail can give you the edge you need.
                        </p>

                        <h4 style="color: #1e293b; margin-top: 30px; margin-bottom: 15px; font-weight: 700;">Finding the Important Question of <?= htmlspecialchars($bookName) ?></h4>
                        <p>
                            In every chapter of <strong><?= htmlspecialchars($bookName) ?></strong>, there are certain topics that appear in the <strong>exam test paper</strong> year after year. We have curated these high-priority areas to help you focus your efforts. For <strong>Class 9-10-11-12</strong>, knowing which diagrams to practice and which derivations to memorize for <strong><?= htmlspecialchars($className) ?></strong> is a fundamental part of smart <strong>board exam preparations</strong>.
                        </p>

                        <div style="background: rgba(79, 70, 229, 0.05); border-radius: 16px; padding: 30px; margin: 35px 0; border: 1px dashed #4f46e5;">
                            <h5 style="color: #4f46e5; margin-bottom: 15px; font-weight: 800;"><i class="fas fa-rocket"></i> Your <?= htmlspecialchars($bookName) ?> Revision Checklist:</h5>
                            <ul style="margin-bottom: 0; padding-left: 20px; color: #1e293b; font-weight: 500;">
                                <li>Review the <strong>important question of <?= htmlspecialchars($bookName) ?></strong> for each chapter.</li>
                                <li>Take a timed <strong>online exam preparation</strong> quiz for every topic in <strong><?= htmlspecialchars($className) ?></strong>.</li>
                                <li>Solve at least three <strong>matric</strong> or <strong>inter</strong> level practice tests.</li>
                                <li>Focus on neat paper presentation—use headings and diagrams effectively.</li>
                            </ul>
                        </div>

                        <p>
                            As you prepare for your <strong><?= htmlspecialchars($className) ?> school</strong> or <strong>college</strong> finals, remember that every chapter of <strong><?= htmlspecialchars($bookName) ?></strong> you master brings you one step closer to your goal. Stay consistent with your <strong>board exam preparations</strong> and use our <strong>exam test paper</strong> generator to evaluate your progress daily. Good luck to all students in <strong>Class 9, 10, 11, and 12</strong>!
                        </p>
                    </div>
                </div>
            </article>
        </div>
    </div>

</div>

<script>
    // Navigation and interactive logic
</script>

<?php include '../footer.php'; ?>
