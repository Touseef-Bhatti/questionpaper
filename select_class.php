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
    <meta name="description" content="Select your class 9th or 10th to generate question papers, take online MCQs quizzes, and access up-to-date notes. Supported for New Syllabus Punjab Board and Pakistan Board.">
    <meta name="keywords" content="9th class, 10th class, question paper generator, online mcqs, quiz, test paper, new syllabus punjab board, pakistan board up to date papers, online tests, notes">
    <title>9th & 10th Class Question Papers Generator, Online Tests & Notes | Ahmad Learning Hub </title>
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
