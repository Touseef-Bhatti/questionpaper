<?php
// Require authentication before accessing this page
// require_once 'auth/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Materials - Ahmad Learning Hub</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/notes.css">
    <link rel="stylesheet" href="../css/buttons.css">
   
</head>
<body>
    <?php include '../header.php'; ?>

    <div class="main-content">
        <div class="study-materials-container">
            <div class="study-materials-header">
                <h1>📚 Study Materials</h1>
                <p>Access comprehensive study resources for your exam preparation</p>
            </div>
            
            <div class="materials-grid">
                <div class="material-card" onclick="navigateToMaterial('textbook')">
                    <span class="material-icon">📖</span>
                    <div class="material-title">Textbooks</div>
                    <div class="material-description">Access digital textbooks and course materials for all subjects</div>
                </div>
                
                <div class="material-card" onclick="navigateToMaterial('notes')">
                    <span class="material-icon">📝</span>
                    <div class="material-title">Notes</div>
                    <div class="material-description">Download comprehensive notes and study guides</div>
                </div>
                <div class="material-card" onclick="navigateToMaterial('mcqs')">
                    <span class="material-icon">🔘</span>
                    <div class="material-title">MCQs</div>
                    <div class="material-description">Multiple choice questions with answers</div>
                </div>
                <div class="material-card" onclick="navigateToMaterial('past-papers')">
                    <span class="material-icon">📄</span>
                    <div class="material-title">Past Papers</div>
                    <div class="material-description">Previous years' exam papers with solutions</div>
                </div>
                
                <div class="material-card" onclick="navigateToMaterial('guess-papers')">
                    <span class="material-icon">🎯</span>
                    <div class="material-title">Guess Papers</div>
                    <div class="material-description">Important questions and expected topics for exams</div>
                </div>
                
                <div class="material-card" onclick="navigateToMaterial('solved-exercises')">
                    <span class="material-icon">✅</span>
                    <div class="material-title">Solved Exercises</div>
                    <div class="material-description">Step-by-step solutions to textbook exercises</div>
                </div>
                
                
                
                
            </div>
            
            <div class="go-back-section">
                <button class="go-back-btn" onclick="window.history.back()">⬅ Go Back</button>
            </div>
        </div>
    </div> <!-- main-content -->

    <?php include '../footer.php'; ?>
    
    <script>
        function navigateToMaterial(type) {
            // Coming soon materials
            const comingSoon = ['past-papers', 'guess-papers', 'solved-exercises'];
            
            if (comingSoon.includes(type)) {
                alert('Coming soon! This feature will be available shortly.');
                return;
            }
            
            // You can customize these URLs based on your routing structure
            const routes = {
                'textbook': 'textbooks.php',
                'notes': 'uploaded_notes.php',
                'past-papers': 'past_papers.php',
                'guess-papers': 'guess_papers.php',
                'solved-exercises': 'solved_exercises.php',
                'mcqs': 'mcqs.php',
                'model-papers': 'model_papers.php'
            };
            
            const url = routes[type] || '#';
            if (url !== '#') {
                window.location.href = url;
            } else {
                alert('This feature is coming soon!');
            }
        }
    </script>
</body>
</html>
