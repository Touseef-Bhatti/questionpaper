<?php
session_start();
// require_once 'auth/auth_check.php';
include 'db_connect.php';
require_once 'middleware/SubscriptionCheck.php';

include 'includes/adsterra_ads.php';

// Fetch all available classes with their IDs and names using prepared statement (OPTIMIZED)
$classQuery = "SELECT class_id, class_name FROM class ORDER BY class_id ASC";
$classResult = $conn->query($classQuery);

if (!$classResult) {
    die("<h2 style='color:red;'>Error fetching classes: " . htmlspecialchars($conn->error) . "</h2>");
}

$classesData = [];
while ($row = $classResult->fetch_assoc()) {
    $classesData[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google tag (gtag.js) -->
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


<meta name="description" content=" Online Question Paper Generator for Class 9 & 10 (Punjab Board). Create chapter-wise tests, MCQs, short & long questions with answers. Generate and download exam papers instantly for school teachers in Pakistan.">

<meta name="keywords" content="Online question paper generator, 9th class Question paper generator, 10th class Question paper generator, Punjab Board question papers,Chapter Wise Question Paper ,MCQs Paper generator for class 9 and 10, online test maker, online paper Software ,Question paper generatr Tool ,  Board Pattern Question Paper, Matric Exam ,Board Exam paper generator ,Online paper generator , Custom Paper generator , Online Exam ,Board Pattern Paper generator,online MCQs test 9th class, 10th class MCQs tests, school exam papers, chapter-wise MCQs, test generator Pakistan">



    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/select_class.css">
  


    <title>Online Question Paper Generator | Punjab Board  & Others</title>
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
            <h2>📘 Welcome to Ahmad Learning Hub – Online Question Paper Generator For Teachers</h2>
            <p>
              Generate 9th and 10th class Online  question papers based on Punjab Board exam patterns. 
              Create Chapter Wise Exam Question Papers For class 9 and 10, Board Pattern Question Papers For All types Of Exam.

Generator MCQs tests, school exams, and practice papers instantly or attempt online quizzes for better preparation.
            </p>

          
        </div>
        
        <!-- MIDDLE AD BANNER -->
        <?= renderAd('banner', 'Place Middle Banner Here', 'ad-placement-middle') ?>
    
    <div class="classes-container">
        <h2>📋 Select Your Class to Continue</h2>
        <p>
            Choose your class below to start generating <strong>Online question papers Punjab Board Exam Pattern</strong>and 
            <strong>Custom Online Papers</strong>, and Tests  <strong>Generate Chapter Wise Question Paper</strong> for your <strong>Punjab Board exams</strong>.
        </p>
        <div class="classes-grid">
            <?php foreach ($classesData as $row) { 
                $isComingSoon = ($row['class_id'] == 11 || $row['class_id'] == 12);
            ?>
                <div class="class-box <?= $isComingSoon ? 'coming-soon' : '' ?>" onclick="selectClass(<?= htmlspecialchars($row['class_id']); ?>)">
                    <?= htmlspecialchars($row['class_name']); ?>
                </div>
            <?php } ?>
            <div class="class-box other-class-box" onclick="window.location.href = 'questionPaperFromTopic/home.php'">
              College & University
            </div>
        </div>
    </div>
     <div class="features-highlight">
            <h3>Key Features for Students & Teachers</h3>
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
                        <strong>Smart Question Selection:</strong> System-generat question Paper according to official Board exam patterns.
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">⚡</span>
                    <div>
                        <strong>Fast Paper Generation:</strong> Create full chapter-wise Question papers in seconds.
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">💡</span>
                    <div>
                        <strong>Class Test Question Papers</strong>Generate<strong>Online MCQs test and Papers</strong> for any assessment.
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">📱</span>
                    <div>
                        <strong>Mobile Friendly:</strong> Works perfectly on all smartphones, tablets, and PCs.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <a href="index.php" class="go-back-btn" style="text-decoration: none; display: inline-flex; align-items: center;">⬅ Go Back</a>
</div>
<div class="seo-content">
    <h2>9th & 10th Class Paper Generator for Punjab Board</h2>
    <p>
        This platform allows students and teachers in Pakistan to generate Online question papers 
        for 9th and 10th class according to Punjab Board Exam patterns. You can create chapter-wise Question Paper , MCQs Paper and 
        tests, full-length Question papers, and MCQs quizzes for better exam preparation.
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
