<?php
session_start();
// require_once 'auth/auth_check.php';
include 'db_connect.php';
require_once 'middleware/SubscriptionCheck.php';

// Fetch all available classes with their IDs and names using prepared statement (OPTIMIZED)
$classQuery = "SELECT class_id, class_name FROM class ORDER BY class_id ASC";
$classResult = $conn->query($classQuery);

if (!$classResult) {
    die("<h2 style='color:red;'>Database error: " . htmlspecialchars($conn->error) . "</h2>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google tag (gtag.js) -->
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generate 9th & 10th Class Question Papers | Punjab Board MCQs & Tests</title>

<meta name="description" content="Select 9th or 10th class to generate question papers based on Punjab Board patterns. Create MCQs tests, school exams, and practice papers with chapter-wise questions and printable formats.">

<meta name="keywords" content="9th class paper generator, 10th class paper generator, Punjab Board question papers, online MCQs test 9th class, 10th class tests, school exam papers, chapter-wise MCQs, test generator Pakistan">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/select_class.css">
    <link rel="stylesheet" href="css/buttons.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <!-- SIDE SKYSCRAPER ADS (Auto-responsive) -->
    <?= renderAd('skyscraper', 'Place Right Skyscraper Banner Here', 'right', 'margin-top: 20%;') ?>

    <div class="main-content">

<div class="select-class-content">
    <h1>Generate 9th & 10th Class Question Papers – Punjab Board</h1>
    
    <!-- TOP AD BANNER MOVED HERE FROM HEADER -->
    <?= renderAd('banner', 'Place Top Banner Here', 'ad-placement-top') ?>
    
    <div class="description-section">
        <div class="info-banner">
            <h2>📘 Welcome to Ahmad Learning Hub – Smart Paper Generator</h2>
            <p>
              Generate 9th and 10th class question papers online based on Punjab Board exam patterns. 
Create MCQs tests, school exams, and practice papers instantly or attempt online quizzes for better preparation.
            </p>

          
        </div>
        
        <!-- MIDDLE AD BANNER -->
        <?= renderAd('banner', 'Place Middle Banner Here', 'ad-placement-middle') ?>
    
    <div class="classes-container">
        <h2>📋 Select Your Class to Continue</h2>
        <p>
            Choose your class below to start generating <strong>question papers</strong>, taking 
            <strong>online tests</strong>, and accessing <strong>study notes</strong> for your <strong>Punjab Board exams</strong>.
        </p>
        <div class="classes-grid">
            <?php while ($row = $classResult->fetch_assoc()) { 
                $isComingSoon = ($row['class_id'] == 11 || $row['class_id'] == 12);
            ?>
                <div class="class-box <?= $isComingSoon ? 'coming-soon' : '' ?>" onclick="selectClass(<?= htmlspecialchars($row['class_id']); ?>)">
                    <?= htmlspecialchars($row['class_name']); ?>
                </div>
            <?php } ?>
            <div class="class-box other-class-box" onclick="window.location.href = 'questionPaperFromTopic/home.php'">
              College & University Papers
            </div>
        </div>
    </div>
     <div class="features-highlight">
            <h3>✨ Key Features for Students & Teachers</h3>
            <div class="features-list">
                <div class="feature-item">
                    <span class="feature-icon">📚</span>
                    <div>
                        <strong>Updated Syllabus:</strong> Latest <strong>Punjab Board syllabus</strong> for 9th and 10th class.
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">🎯</span>
                    <div>
                        <strong>Smart Question Selection:</strong> System-generated questions follow official exam patterns.
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">⚡</span>
                    <div>
                        <strong>Instant Paper Generation:</strong> Create full-length or chapter-wise papers in seconds.
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">💡</span>
                    <div>
                        <strong>Online Test Practice:</strong> Attempt <strong>MCQs and quizzes</strong> for self-assessment.
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">📱</span>
                    <div>
                        <strong>Mobile Friendly:</strong> Works seamlessly on all smartphones, tablets, and PCs.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <button class="go-back-btn" onclick="window.history.back()">⬅ Go Back</button>
</div>
<div class="seo-content">
    <h2>9th & 10th Class Paper Generator for Punjab Board</h2>
    <p>
        This platform allows students and teachers in Pakistan to generate question papers 
        for 9th and 10th class according to Punjab Board patterns. You can create chapter-wise 
        tests, full-length exam papers, and MCQs quizzes for better exam preparation.
    </p>
</div>
</div> <!-- main-content -->


<?php include 'footer.php'; ?>

<script>
    function selectClass(classId) {
        // Show coming soon message for class_id 11 and 12
        if (classId == 11 || classId == 12) {
            alert('Coming soon!');
            return;
        }
        window.location.href = 'select_book.php?class_id=' + encodeURIComponent(classId);
    }
</script>

</body>
</html>
