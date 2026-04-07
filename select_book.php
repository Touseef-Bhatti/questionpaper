<?php
session_start();
// require_once 'auth/auth_check.php';
include 'db_connect.php';
require_once 'middleware/SubscriptionCheck.php';

// Ensure class_id is provided and is valid integer
if (!isset($_GET['class_id']) || empty($_GET['class_id']) || !is_numeric($_GET['class_id'])) {
    header('Location: select_class.php');
    exit;
}

$classId = intval($_GET['class_id']);

// Select all books for this class using prepared statement (OPTIMIZED)
$bookQuery = "SELECT book_id, book_name FROM book WHERE class_id = ? ORDER BY book_id ASC";
$stmt = $conn->prepare($bookQuery);

if (!$stmt) {
    die("<h2 style='color:red;'>Database error: " . htmlspecialchars($conn->error) . "</h2>");
}

$stmt->bind_param('i', $classId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("<h2 style='color:red;'>Query error: " . htmlspecialchars($conn->error) . "</h2>");
}

$booksData = [];
while ($row = $result->fetch_assoc()) {
    $booksData[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/includes/favicons.php'; ?>
    <!-- Google tag (gtag.js) -->
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>
    
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/select_book.css">
    <link rel="stylesheet" href="css/buttons.css">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />



<meta name="description" content="Select your <?= htmlspecialchars($classId) ?>th class subject to generate chapter-wise question papers, MCQs tests, and board pattern exams for Punjab Board (BISE Lahore, Multan, Faisalabad, etc.).">

<meta name="keywords" content="<?= htmlspecialchars($classId) ?>th class paper generator, <?= htmlspecialchars($classId) ?> class subjects Punjab Board, online MCQs test <?= htmlspecialchars($classId) ?>, BISE Punjab subject-wise question papers, test generator Pakistan">
<!-- monetag vegenate Banner -->
<script>(function(s){s.dataset.zone='10788340',s.src='https://n6wxm.com/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
   <!-- monetag vegenate Banner -->


<title><?= htmlspecialchars($classId) ?>th Class Online Question Paper Generator | All Subjects Punjab Board</title>

    <!-- Schema.org Markup for SEO -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "<?= htmlspecialchars($classId) ?>th Class Online Paper Generator",
      "operatingSystem": "Web",
      "applicationCategory": "EducationalApplication",
      "description": "Professional online question paper generator for <?= htmlspecialchars($classId) ?>th class subjects including Physics, Chemistry, Biology, and Math for Punjab Boards.",
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.8",
        "ratingCount": "2100"
      }
    }
    </script>
  
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- SIDE SKYSCRAPER ADS (Auto-responsive) -->
    <?= renderAd('skyscraper', 'Place Right Skyscraper Banner Here', 'right', 'margin-top: 25%;') ?>

    
    <div class="main-container">

    <h1>Select Book to Generate <?= htmlspecialchars($classId) ?>th Class Question Paper</h1>

    <!-- TOP AD BANNER MOVED HERE FROM HEADER -->
    <?= renderAd('banner', 'Place Top Banner Here', 'ad-placement-top') ?>

    <!-- MIDDLE AD BANNER -->
    <?= renderAd('banner', 'Place Middle Banner Here', 'ad-placement-top') ?>

    <div class="classes-container" id="book-box-container">
        <div class="book-selection-header">
            <h2>📚 Choose a Subject for <?= htmlspecialchars($classId) ?>th Class</h2>
            <p>Select your desired book to start generating <strong>Online Question Papers</strong> and <strong>MCQs Tests</strong> according to the <strong>Punjab Board Exam Pattern</strong>. We provide comprehensive coverage for all major subjects including Science and Arts groups.</p>
        </div>

        <div class="classes-grid" id="books-grid">
        <?php if (!empty($booksData)): ?>
            <?php foreach ($booksData as $row): ?>
                <?php
                    // Example: Make some  "coming soon" book

                    $isComingSoon = in_array($row['book_id'], []);

                ?>
                <div 
                    class="class-box <?= $isComingSoon ? 'coming-soon' : '' ?>" 
                    data-book-id="<?= htmlspecialchars($row['book_id']) ?>" 
                    data-book-name="<?= htmlspecialchars($row['book_name']) ?>"
                    onclick="<?= $isComingSoon ? 'showComingSoon()' : 'navigateToChapters(this)' ?>"
                >
                    <?= htmlspecialchars($row['book_name']) ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <h3 style="color:red;">No books found for this class.</h3>
        <?php endif; ?>
    </div>
    <br><br><br>
    <div class="book-features-seo">
        <h2 class="features-title">🚀 Professional <?= htmlspecialchars($classId) ?>th Class Online Question Paper Generator</h2>
        <p style="text-align: center; color: #64748b; margin-top: -2rem; margin-bottom: 3rem; font-size: 1.1rem;">
            Select your subject to generate <strong>chapter-wise question papers</strong> and <strong>online MCQs tests</strong> for <?= htmlspecialchars($classId) ?>th class. Our platform supports all subjects including Physics, Chemistry, Biology, and Mathematics according to <strong>Punjab Board (BISE)</strong> standards.
        </p>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <span class="icon">📄</span>
                </div>
                <div class="feature-text">
                    <strong>Board Pattern Exams</strong>
                    <p>Generate full-length <strong>Punjab Board Question Papers</strong> for any <?= htmlspecialchars($classId) ?>th class subject instantly.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <span class="icon">✅</span>
                </div>
                <div class="feature-text">
                    <strong>Chapter-Wise Selection</strong>
                    <p>Create <strong>Custom Online Tests</strong> by selecting specific chapters for focused classroom assessments.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <span class="icon">⚡</span>
                </div>
                <div class="feature-text">
                    <strong>Fast MCQs Generator</strong>
                    <p>Build <strong>MCQs papers for <?= htmlspecialchars($classId) ?> class</strong> with automatic answer key generation in seconds.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <span class="icon">🔍</span>
                </div>
                <div class="feature-text">
                    <strong>Editable Downloads</strong>
                    <p>Download your generated <strong>exam papers</strong> in editable Word (DOCX) or professional PDF formats.</p>
                </div>
            </div>
        </div>

        <!-- SEO Content Section for Punjab Boards -->
        <div class="bise-boards-section" style="margin-top: 4rem; border-top: 1px solid #e2e8f0; padding-top: 3rem; text-align: center;">
            <h3 style="color: #0f172a; margin-bottom: 1.5rem;">Supported Punjab Education Boards</h3>
            <p style="color: #475569; max-width: 800px; margin: 0 auto 2rem auto; line-height: 1.6;">
                Our <?= htmlspecialchars($classId) ?>th class paper generator is fully compatible with the syllabus and paper patterns of all BISE boards in Punjab, including:
            </p>
            <div class="boards-list" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem; color: #1e40af; font-weight: 600;">
                <span>BISE Lahore</span> • <span>BISE Multan</span> • <span>BISE Faisalabad</span> • <span>BISE Gujranwala</span> • <span>BISE Rawalpindi</span> • <span>BISE Sahiwal</span> • <span>BISE Sargodha</span> • <span>BISE Bahawalpur</span> • <span>BISE DG Khan</span>
            </div>
        </div>
    </div>
    </div>
    <a href="select_class.php" class="go-back-btn" style="text-decoration: none; display: inline-flex; align-items: center;">⬅ Go Back</a>


<p class="seo-subject-info">
    Fast Question paper generator for <?= htmlspecialchars($classId) ?> class for  Punjab Board  and School Exams.Fast MCQs Paper generator for <?= htmlspecialchars($classId) ?> class Exams. Create custom
    question papers, MCQs tests, and chapter-wise for <?= htmlspecialchars($classId) ?> exam papers in seconds.
</p>
    </div>
<?php include 'footer.php'; ?>

    <script>
        const classId = '<?= urlencode($classId) ?>';
        
        function getOrdinalSuffix(i) {
            var j = i % 10, k = i % 100;
            if (j == 1 && k != 11) return i + "st";
            if (j == 2 && k != 12) return i + "nd";
            if (j == 3 && k != 13) return i + "rd";
            return i + "th";
        }

        function navigateToChapters(el) {
            const bookName = el.getAttribute('data-book-name');
            if (!bookName) {
                alert('Please select a valid book.');
                return;
            }
            
            // Generate SEO-friendly slug
            const bookSlug = bookName.toLowerCase().trim().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
            const classOrdinal = getOrdinalSuffix(parseInt(classId));
            
            // Construct the SEO-optimized URL
            window.location.href = `${classOrdinal}-class-${bookSlug}-question-paper-generator`;
        }
        
        function showComingSoon() {
            alert('coming soon!');
        }
    </script>

    
    
</body>
</html>
