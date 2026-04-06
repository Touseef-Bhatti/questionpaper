<?php
session_start();
include 'db_connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google tag (gtag.js) -->
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ahmad Learning Hub is an all-in-one question paper generator and exam preparation platform for students and teachers in Pakistan and India. Create school, college, and university tests with board exam patterns, MCQs, subjective questions, past papers, and online quizzes.">

<meta name="keywords" content="Online question paper generator, question paper generator,Online Paper genertor, online test , online quiz , online exam paper generator , AI paper generator ,Online Question paper generator for 9th class, online question paper for 10th class , Online exam , online test maker, exam paper creator, MCQs test online, board exam preparation, school test generator, college exam papers, university test maker, online quiz maker, Punjab Board, CBSE, ICSE, Pakistan exams, India exams, past papers, solved notes, guess papers, Ahmad Learning Hub  ">


    <title>Ahmad Learning Hub – Online Question Paper Generator for All Classes | School, College & University</title>
    
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">


    <?php include_once __DIR__ . '/includes/favicons.php'; ?>

</head>
<body>
    <?php include 'header.php'; ?>



    <div class="main-content" style="margin-top: -10%;">

        <!-- HERO: Futuristic & Clean -->
        <section class="hero-section">
            <div class="container hero-grid">
                
               <div class="hero-content">
    <div class="eyebrow" style="color: aliceblue;">Question Paper Generator & MCQs Practice</div>
    
    <h1 class="hero-title">
        Generate Papers & Practice MCQs for Any Topic
    </h1>
    
    <p class="subtitle">
        Create 9th & 10th class question papers or search any topic to start MCQs tests instantly — with real exam patterns, short and long questions, and downloadable papers.
    </p>
</div>

                    <div class="hero-actions">
                        <a href="login" class="button accent hero-login-btn"><i class="fas fa-sign-in-alt"></i> Login Now</a>
                        <a href="class-9th-and-10th-online-question-paper-generator" class="button primary"><i class="fas fa-file-invoice"></i> Generate Paper</a>
                        <a href="quiz_setup" class="button secondary"><i class="fas fa-laptop-code"></i> Online MCQs Test</a>
                    </div>

            </div>
        </section>

        <br><br>
        
        <?= renderAd('banner', 'Place Top Banner Here') ?>
<br>

        <br>
        <?= renderAd('banner', 'Place Middle Banner Here') ?>
        <br>

        <div class="container">
            <div class="hero-prep-section" role="region" aria-label="Exam preparation categories">
                <h2 class="hero-prep-title">Professional Exam Preparation for School, Board, College & University</h2>
               <br>
                <p class="hero-prep-description">
                    Start focused exam preparation with class-wise papers, board-pattern practice, and chapter-wise MCQs for class 9, class 10, and advanced levels.
                </p>

                <div class="hero-prep-grid">
                    <a href="class-9-online-question-paper-generator" class="hero-prep-card">
                        <span class="hero-prep-icon"><i class="fas fa-graduation-cap"></i></span>
                        <h3>Class 9 Exam Preparation</h3>
                        <p>Generate chapter-wise tests and build strong fundamentals with exam-style practice.</p>
                        <span class="prep-card-cta">Explore Now <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="class-10-online-question-paper-generator" class="hero-prep-card">
                        <span class="hero-prep-icon"><i class="fas fa-award"></i></span>
                        <h3>Class 10 Exam Preparation</h3>
                        <p>Practice model papers, short questions, long questions, and final revision tests.</p>
                        <span class="prep-card-cta">Explore Now <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="quiz_setup" class="hero-prep-card">
                        <span class="hero-prep-icon"><i class="fas fa-clipboard-check"></i></span>
                        <h3>MCQs Preparation Class 9 & 10</h3>
                        <p>Take topic-wise MCQs tests with instant scoring and smart performance tracking.</p>
                        <span class="prep-card-cta">Start Test <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="class-9th-and-10th-online-question-paper-generator" class="hero-prep-card">
                        <span class="hero-prep-icon"><i class="fas fa-file-signature"></i></span>
                        <h3>Board Exam Preparation</h3>
                        <p>Prepare with board-oriented formats, realistic paper structure, and balanced difficulty.</p>
                        <span class="prep-card-cta">Generate Paper <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="home" class="hero-prep-card bypass-user-type">
                        <span class="hero-prep-icon"><i class="fas fa-university"></i></span>
                        <h3>College & University Exams</h3>
                        <p>Create professional tests for intermediate, college, and university exam preparation.</p>
                        <span class="prep-card-cta">Get Started <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="mcqs_topic" class="hero-prep-card bypass-user-type">
                        <span class="hero-prep-icon"><i class="fas fa-brain"></i></span>
                        <h3>MCQs Practice Hub</h3>
                        <p>Practice objective questions regularly to improve speed, accuracy, and confidence.</p>
                        <span class="prep-card-cta">Practice Now <i class="fas fa-arrow-right"></i></span>
                    </a>
                </div>

                <ul class="hero-keywords" aria-label="Popular exam preparation topics">
                    <li><a href="class-9th-and-10th-online-question-paper-generator"><i class="fas fa-book-open"></i> Exam Preparation</a></li>
                    <li><a href="class-9-online-question-paper-generator"><i class="fas fa-school"></i> Class 9 Exam Preparation</a></li>
                    <li><a href="class-10-online-question-paper-generator"><i class="fas fa-graduation-cap"></i> Class 10 Exam Preparation</a></li>
                    <li><a href="quiz_setup"><i class="fas fa-check-circle"></i> Class 9 & 10 MCQs Preparation</a></li>
                    <li><a href="class-9th-and-10th-online-question-paper-generator"><i class="fas fa-file-alt"></i> Board Exam Preparation</a></li>
                    <li><a href="home" class="bypass-user-type"><i class="fas fa-university"></i> College University Exam Preparation</a></li>
                    <li><a href="mcqs_topic" class="bypass-user-type"><i class="fas fa-pencil-alt"></i> MCQs Practice</a></li>
                </ul>
            </div>
        </div>

        <section class="teachers-section">
            <div class="container">
                <div class="split-grid">
                    <div>
                        <div class="card">
                            <h3>Teacher tools</h3>
                            <p>Create Online Question papers and Host Online Quizez .</p>
                            <a class="btn btn-primary" href="class-9th-and-10th-online-question-paper-generator">Generate Question Paper</a>
                            <a class="btn btn-outline" href="online_quiz_host_new">Host a Quiz</a>
                            
                            <a class="btn btn-ghost" href="note">View Notes</a>
                        </div>
                    </div>
                    <div>
                        <div class="card">
                            <h3>For students</h3>
                            <p>Prepare Exam with Chapter-wise real Exam-style Questions , MCQs And have Access to all updated Notes </p>
                            <a class="btn btn-primary" href="online_quiz_join">Join Quiz</a>
                            <a class="btn btn-outline" href="quiz_setup">Take Online Test</a>
                            <a class="btn btn-ghost" href="note">View Notes</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <br>

        <?= renderAd('banner', 'Place Bottom Banner Here') ?>

      <!-- CTA -->
<br>
<section class="cta-section">
    <div class="container">
        <div class="cta-card">
            <h2>Ready to excel in your <span class="highlight">exam preparation</span>?</h2>
            
            <p>
                Join thousands of learners and educators worldwide who trust <strong>Ahmad Learning Hub</strong> for smarter, faster, and future-ready <span class="keyword">online quizzes</span>, <span class="keyword">practice tests</span>, and <span class="keyword">Online question papers generator </span>. 
                Prepare for a wide range of exams including <strong>board exams</strong> (Punjab Board, CBSE, ICSE), <strong>college & university exams</strong>, <strong>medical entrance exams</strong> (NEET, MCAT), <strong>engineering entrance exams</strong> (JEE, EAMCET), <strong>law exams</strong> (CLAT, LSAT), and international tests like <strong>GRE, GMAT, SAT, IELTS, TOEFL</strong>.
            </p>
            
            <p>
                Unlock adaptive tests, generate personalized <span class="keyword">study materials</span>, and host engaging quizzes with ease. 
                Whether you’re a student aiming for top scores or an educator streamlining assessments, our platform provides all the tools you need in one comprehensive <span class="keyword">exam preparation platform</span>.
            </p>
            
            <div class="cta-actions">
                <a href="class-9th-and-10th-online-question-paper-generator" class="button primary"><i class="fas fa-file-invoice"></i> Generate Paper</a>
                <a href="quiz_setup" class="button ghost"><i class="fas fa-laptop-code"></i> Start Test</a>
            </div>
        </div>
    </div>
</section>

    </div>


    <?php include 'footer.php'; ?>
    </body>
    </html>
