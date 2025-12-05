<?php
include 'db_connect.php';

// Fetch all available classes with their IDs and names
$classQuery = "SELECT class_id, class_name FROM class";
$classResult = $conn->query($classQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Select your class to generate question papers, take online tests, and download notes for 9th and 10th class Punjab Board exams. Access past papers, MCQs, and guess papers for smarter exam preparation.">
    <meta name="keywords" content="Ahmad Learning Hub, select class, 9th class, 10th class, Punjab Board, online test, MCQs, past papers, solved notes, guess papers, question paper generator, exam preparation Pakistan, chapter-wise tests">
    <title>Select Class | Ahmad Learning Hub - 9th & 10th Class Online Tests, Notes & Question Papers</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/select_class.css">
    <link rel="stylesheet" href="css/buttons.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="main-content">

<div class="select-class-content">
    <h1>🎓 Select Your Class for Exam Preparation</h1>
    
    <div class="description-section">
        <div class="info-banner">
            <h2>📘 Welcome to Ahmad Learning Hub – Smart Paper Generator</h2>
            <p>
                Ahmad Learning Hub is your all-in-one platform for <strong>online exam preparation</strong> for 
                <strong>9th and 10th class Punjab Board students</strong>. Whether you’re a teacher creating 
                <strong>custom question papers</strong> or a student attempting <strong>online MCQ tests</strong>, 
                everything starts here by selecting your class.
            </p>

          
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
    
    <div class="classes-container">
        <h2>📋 Select Your Class to Continue</h2>
        <p>
            Choose your class below to start generating <strong>question papers</strong>, taking 
            <strong>online tests</strong>, and accessing <strong>study notes</strong> for your <strong>Punjab Board exams</strong>.
        </p>
        <div class="classes-grid">
            <?php while ($row = $classResult->fetch_assoc()) { ?>
                <div class="class-box" onclick="selectClass(<?= htmlspecialchars($row['class_id']); ?>)">
                    <?= htmlspecialchars($row['class_name']); ?>
                </div>
            <?php } ?>
        </div>
    </div>
    
    <button class="go-back-btn" onclick="window.history.back()">⬅ Go Back</button>
</div>
</div> <!-- main-content -->

<?php include 'footer.php'; ?>

<script>
    function selectClass(classId) {
        window.location.href = 'select_book.php?class_id=' + encodeURIComponent(classId);
    }
</script>

</body>
</html>
