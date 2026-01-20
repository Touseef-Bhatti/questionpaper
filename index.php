<?php
session_start();
include 'db_connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ahmad Learning Hub helps 9th and 10th class students prepare for Punjab Board exams with free online tests, chapter-wise MCQs, past papers, notes, and guess papers. Teachers can generate question papers and host online quizzes easily.">
    <meta name="keywords" content="online exam preparation, 9th class, 10th class, Punjab Board, online test, MCQs, past papers, solved notes, guess papers, chapter-wise test, paper generator, Ahmad Learning Hub, quiz for students, Pakistan exams, test preparation, online learning, board exams">
    <title>Ahmad Learning Hub - Online Exam Preparation, Past Papers & Notes for 9th & 10th Class Punjab Board</title>
    
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<body>
    <?php include 'header.php'; ?>

    <div class="main-content" style="margin-top: -10%;">

        <!-- HERO: Futuristic & Clean -->
        <section class="hero-section" style="background: url(8617761.jpg) no-repeat center center fixed; background-size: cover;">
            <div class="container hero-grid">
                <div class="hero-content">
                    <div class="eyebrow">Intelligent Exam Preparation</div>
                    <h1 class="hero-title">Ahmad Learning Hub — Smarter, Faster, Future-Ready</h1>
                    <p class="subtitle">Prepare for 9th & 10th Punjab Board exams with adaptive tests, chapter-wise practice, instant analytics and printable papers — all in one modern platform.</p>

                    <div class="hero-actions">
                        <a href="#" onclick="showTypeSelection(event)" class="button primary">Generate Paper</a>
                        <a href="quiz/quiz_setup.php" class="button secondary">Take a Test</a>
                        <a href="notes/notes.php" class="button ghost" style="color: white;">Notes & Guides</a>
                    </div>

                    <div class="hero-stats">
                        <div class="stat-item"><strong>10k+</strong><span>Questions</span></div>
                        <div class="stat-item"><strong>100+</strong><span>Schools</span></div>
                        <div class="stat-item"><strong>99.9%</strong><span>Uptime</span></div>
                    </div>
                </div>

                <!-- Hero visual cards removed for a cleaner, more professional hero -->
            </div>
        </section>

       

        <!-- HOW IT WORKS -->
        <section class="how-section">
            <div class="container">
                <h2 class="section-title">How it works</h2>
                <div class="how-grid">
                    <div class="how-card">
                        <div class="step">1</div>
                        <h4>Select class & chapters</h4>
                        <p>Choose the class and chapters to target specific topics.</p>
                    </div>
                    <div class="how-card">
                        <div class="step">2</div>
                        <h4>Pick question types</h4>
                        <p>Set counts for MCQs, short and long questions or use built-in patterns.</p>
                    </div>
                    <div class="how-card">
                        <div class="step">3</div>
                        <h4>Generate & review</h4>
                        <p>Preview, edit and download or print the final paper. Use analytics to refine practice.</p>
                    </div>
                </div>
            </div>
        </section>
    <br><br>
        <!-- TEACHERS & TOOLS -->
        <section class="teachers-section">
            <div class="container">
                <div class="split-grid">
                    <div>
                        <div class="card">
                            <h3>Teacher tools</h3>
                            <p>Create quizzes, generate papers, and manage questions.</p>
                            <a class="btn btn-primary" href="#" onclick="showTypeSelection(event)">Generate Question Paper</a>
                            <a class="btn btn-outline" href="quiz/online_quiz_host_new.php">Host a Quiz</a>
                            
                            <a class="btn btn-ghost" href="notes/notes.php">View Notes</a>
                        </div>
                    </div>
                    <div>
                        <div class="card">
                            <h3>For students</h3>
                            <p>Join quizzes, take tests, and access study materials.</p>
                            <a class="btn btn-primary" href="quiz/online_quiz_join.php">Join Quiz</a>
                            <a class="btn btn-outline" href="quiz/quiz_setup.php">Take Online Test</a>
                            <a class="btn btn-ghost" href="notes/notes.php">View Notes</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
<br><br>
        <!-- CTA -->
        <section class="cta-section">
            <div class="container">
                <div class="cta-card">
                    <h2>Ready to elevate your <span class="highlight">exam preparation</span>?</h2>
                    <p>Join thousands of students and educators who trust Ahmad Learning Hub for smarter, faster, and future-ready <span class="keyword">online quizzes</span>, <span class="keyword">practice tests</span>, and <span class="keyword">question paper generation</span>. Unlock adaptive tests, generate custom <span class="keyword">study materials</span>, and host engaging quizzes with ease.</p>
                    <p>Whether you're a student aiming for top grades or a teacher looking to streamline assessment, Ahmad Learning Hub provides the tools you need to succeed. Get started today and transform your learning and teaching experience with our comprehensive <span class="keyword">exam preparation platform</span>!</p>
                    <div class="cta-actions">
                        <a href="#" onclick="showTypeSelection(event)" class="button primary">Generate Paper</a>
                        <a href="quiz/quiz_setup.php" class="button ghost">Start Test</a>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- TYPE SELECTION MODAL -->
    <div id="typeSelectionModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <h3 style="margin-top: 0; color: #333;">Select Mode</h3>
            <p>Please select your organization type:</p>
            <div class="modal-actions" style="display: flex; gap: 15px; margin-top: 20px; justify-content: center;">
                <a href="select_class.php" class="modal-btn school-btn">
                    <span class="text">School</span>
                </a>
                <a href="questionPaperFromTopic/index.php" class="modal-btn other-btn">
                    <span class="text">Other</span>
                </a>
            </div>
            <button onclick="closeTypeSelection()" class="close-modal-btn">&times;</button>
        </div>
    </div>

    <style>
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            position: relative;
            text-align: center;
            max-width: 90%;
            width: 400px;
        }
        .modal-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border-radius: 12px;
            text-decoration: none;
            color: #333;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            width: 120px;
        }
        .modal-btn:hover {
            background: #eef2ff;
            border-color: #4f46e5;
        }
        .modal-btn .icon {
            font-size: 32px;
            margin-bottom: 8px;
        }
        .modal-btn .text {
            font-weight: 600;
        }
        .close-modal-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        .close-modal-btn:hover {
            color: #333;
        }
    </style>

    <script>
    function showTypeSelection(e) {
        if (e) e.preventDefault();
        document.getElementById('typeSelectionModal').style.display = 'flex';
    }
    function closeTypeSelection() {
        document.getElementById('typeSelectionModal').style.display = 'none';
    }
    // Close on click outside
    document.getElementById('typeSelectionModal').addEventListener('click', function(e) {
        if (e.target === this) closeTypeSelection();
    });
    </script>

    <?php include 'footer.php'; ?>
    </body>
    </html>
