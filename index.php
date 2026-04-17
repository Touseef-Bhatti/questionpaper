<?php
session_start();
include 'db_connect.php';

$latestReviews = [];
$reviewsTableExists = false;
$reviewsTableCheck = $conn->query("SHOW TABLES LIKE 'user_reviews'");
if ($reviewsTableCheck && $reviewsTableCheck->num_rows > 0) {
    $reviewsTableExists = true;
    $latestReviewsResult = $conn->query("SELECT reviewer_name, rating, feedback, created_at, is_anonymous FROM user_reviews WHERE is_approved = 1 ORDER BY created_at DESC LIMIT 3");
    if ($latestReviewsResult) {
        while ($reviewRow = $latestReviewsResult->fetch_assoc()) {
            $latestReviews[] = $reviewRow;
        }
    }
}

function homeReviewStars(int $rating): string {
    $full = max(0, min(5, $rating));
    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
}
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
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="login" class="button accent hero-login-btn"><i class="fas fa-sign-in-alt"></i> Login Now</a>
                        <?php endif; ?>
                        <a href="class-9th-and-10th-online-question-paper-generator" class="button primary bypass-user-type"><i class="fas fa-file-invoice"></i> Generate Paper</a>
                        <a href="quiz_setup" class="button secondary bypass-user-type"><i class="fas fa-laptop-code"></i> Online MCQs Test</a>
                    </div>

            </div>
        </section>

        <br><br>
        
        <?= renderAd('banner', 'Place Top Banner Here') ?>
<br>

        <br>
        <?= renderAd('banner', 'Place Middle Banner Here') ?>
        

        <div class="container">
            <div class="hero-prep-section" role="region" aria-label="Exam preparation categories">
                <h2 class="hero-prep-title">Professional Exam Preparation for School, Board, College & University</h2>
               <br>
                <p class="hero-prep-description">
                    Start focused exam preparation with class-wise papers, board-pattern practice, and chapter-wise MCQs for class 9, class 10, and advanced levels.
                </p>

                <div class="hero-prep-grid">
                    <a href="select_book.php?class_id=9" class="hero-prep-card bypass-user-type">
                        <span class="hero-prep-icon"><i class="fas fa-graduation-cap"></i></span>
                        <h3>Class 9 Exam Preparation</h3>
                        <p>Generate chapter-wise tests and build strong fundamentals with exam-style practice.</p>
                        <span class="prep-card-cta">Explore Now <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="select_book.php?class_id=10" class="hero-prep-card bypass-user-type">
                        <span class="hero-prep-icon"><i class="fas fa-award"></i></span>
                        <h3>Class 10 Exam Preparation</h3>
                        <p>Practice model papers, short questions, long questions, and final revision tests.</p>
                        <span class="prep-card-cta">Explore Now <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="quiz_setup" class="hero-prep-card bypass-user-type">
                        <span class="hero-prep-icon"><i class="fas fa-clipboard-check"></i></span>
                        <h3>MCQs Preparation Class 9 & 10</h3>
                        <p>Take topic-wise MCQs tests with instant scoring and smart performance tracking.</p>
                        <span class="prep-card-cta">Start Test <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="class-9th-and-10th-online-question-paper-generator" class="hero-prep-card bypass-user-type">
                        <span class="hero-prep-icon"><i class="fas fa-file-signature"></i></span>
                        <h3>Board Exam Preparation</h3>
                        <p>Prepare with board-oriented formats, realistic paper structure, and balanced difficulty.</p>
                        <span class="prep-card-cta">Generate Paper <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="online-question-paper-generator" class="hero-prep-card bypass-user-type">
                        <span class="hero-prep-icon"><i class="fas fa-university"></i></span>
                        <h3>College & University Exams</h3>
                        <p>Create professional tests for intermediate, college, and university exam preparation.</p>
                        <span class="prep-card-cta">Get Started <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="topic-wise-mcqs-test" class="hero-prep-card bypass-user-type">
                        <span class="hero-prep-icon"><i class="fas fa-brain"></i></span>
                        <h3>MCQs Practice Hub</h3>
                        <p>Practice objective questions regularly to improve speed, accuracy, and confidence.</p>
                        <span class="prep-card-cta">Practice Now <i class="fas fa-arrow-right"></i></span>
                    </a>
                </div>

                <ul class="hero-keywords" aria-label="Popular exam preparation topics">
                    <li><a href="class-9th-and-10th-online-question-paper-generator" class="bypass-user-type"><i class="fas fa-book-open"></i> Exam Preparation</a></li>
                    <li><a href="select_book.php?class_id=9" class="bypass-user-type"><i class="fas fa-school"></i> Class 9 Exam Preparation</a></li>
                    <li><a href="select_book.php?class_id=10" class="bypass-user-type"><i class="fas fa-graduation-cap"></i> Class 10 Exam Preparation</a></li>
                    <li><a href="quiz_setup" class="bypass-user-type"><i class="fas fa-check-circle"></i> Class 9 & 10 MCQs Preparation</a></li>
                    <li><a href="class-9th-and-10th-online-question-paper-generator" class="bypass-user-type"><i class="fas fa-file-alt"></i> Board Exam Preparation</a></li>
                    <li><a href="online-question-paper-generator" class="bypass-user-type"><i class="fas fa-university"></i> College University Exam Preparation</a></li>
                    <li><a href="topic-wise-mcqs-test" class="bypass-user-type"><i class="fas fa-pencil-alt"></i> MCQs Practice</a></li>
                </ul>
            </div>
        </div>
<br><br><br><br>
<?= renderAd('banner', 'Place Bottom Banner Here') ?>

<br><br>

        <!-- Host Online Quiz Showcase Section -->
        <section class="quiz-showcase-section" id="host-quiz-section">
            <div class="container">
                <!-- Section Header -->
                <div class="quiz-showcase-header">
                    <div class="quiz-showcase-badge"><i class="fas fa-bolt"></i> Live Quiz Platform</div>
                    <h2>Host <span class="gradient-text">Online Quizzes</span> in Real-Time</h2>
                    <p>Create interactive quiz rooms, invite students with a unique code, and watch them compete live. Perfect for classrooms, exams, and fun learning sessions.</p>
                </div>

                <!-- Main Content Grid -->
                <div class="quiz-showcase-grid">

                    <!-- Left: Features List -->
                    <div class="quiz-showcase-features">
                        <a href="online_quiz_join" class="qf-card">
                            <div class="qf-icon"><i class="fas fa-link"></i></div>
                            <div class="qf-content">
                                <h4>Shareable Room Code</h4>
                                <p>Students join instantly with a unique quiz code — no signup required.</p>
                            </div>
                        </a>
                        <a href="online_quiz_host_new" class="qf-card">
                            <div class="qf-icon"><i class="fas fa-chart-bar"></i></div>
                            <div class="qf-content">
                                <h4>Real-Time Leaderboard</h4>
                                <p>Live rank tracking keeps engagement high and learning competitive.</p>
                            </div>
                        </a>
                        <a href="online_quiz_host_new" class="qf-card">
                            <div class="qf-icon"><i class="fas fa-stopwatch"></i></div>
                            <div class="qf-content">
                                <h4>Timed Questions</h4>
                                <p>Set per-question timers for a real exam feel with auto-submit.</p>
                            </div>
                        </a>
                        <a href="online_quiz_host_new" class="qf-card">
                            <div class="qf-icon"><i class="fas fa-trophy"></i></div>
                            <div class="qf-content">
                                <h4>Instant Results</h4>
                                <p>Scores, rankings, and detailed analytics — available the moment the quiz ends.</p>
                            </div>
                        </a>
                    </div>

                    <!-- Right: Visual Quiz Mockup -->
                    <div class="quiz-showcase-visual">
                        <div class="quiz-mockup">
                            <div class="quiz-mockup-header">
                                <div class="mockup-dot red"></div>
                                <div class="mockup-dot yellow"></div>
                                <div class="mockup-dot green"></div>
                                <span class="mockup-title">Live Quiz Room</span>
                            </div>
                            <div class="quiz-mockup-body">
                                <div class="mockup-question-badge">Question 3 of 10</div>
                                <div class="mockup-question">What is the SI unit of force?</div>
                                <div class="mockup-options" id="mockup-quiz-options">
                                    <div class="mockup-option" onclick="handleMockupClick(this, false)">A. Joule</div>
                                    <div class="mockup-option" onclick="handleMockupClick(this, true)">B. Newton</div>
                                    <div class="mockup-option" onclick="handleMockupClick(this, false)">C. Watt</div>
                                    <div class="mockup-option" onclick="handleMockupClick(this, false)">D. Pascal</div>
                                </div>
                                <div class="mockup-timer">
                                    <div class="timer-bar"><div class="timer-fill"></div></div>
                                    <span>18s remaining</span>
                                </div>
                                <div class="mockup-participants">
                                    <div class="participant-avatar"><i class="fas fa-user"></i></div>
                                    <div class="participant-avatar"><i class="fas fa-user"></i></div>
                                    <div class="participant-avatar"><i class="fas fa-user"></i></div>
                                    <div class="participant-avatar more">+12</div>
                                    <span class="participant-label">15 students competing</span>
                                </div>
                            </div>
                        </div>
                        <!-- Floating badges -->
                        <div class="quiz-float-badge badge-top"><i class="fas fa-users"></i> 15 Online</div>
                        <div class="quiz-float-badge badge-bottom"><i class="fas fa-star"></i> 98% Accuracy</div>
                    </div>

                </div>

                <!-- Bottom CTA -->
                <div class="quiz-showcase-cta">
                    <a href="online_quiz_host_new" class="button primary large quiz-host-btn"><i class="fas fa-play-circle"></i> Host a Quiz Now</a>
                    <a href="online_quiz_join" class="button secondary large quiz-join-btn"><i class="fas fa-gamepad"></i> Join a Quiz</a>
                </div>
            </div>
        </section>

<br><br>
        <section class="teachers-section">
            <div class="container">
                <div class="split-grid">
                    <div>
                        <div class="card">
                            <h3>Teacher tools</h3>
                            <p>Create Online Question papers and Host Online Quizez .</p>
                            <a class="btn btn-primary bypass-user-type" href="class-9th-and-10th-online-question-paper-generator">Generate Question Paper</a>
                            <a class="btn btn-outline" href="online_quiz_host_new">Host a Quiz</a>
                            
                            <a class="btn btn-ghost" href="note">View Notes</a>
                        </div>
                    </div>
                    <div>
                        <div class="card">
                            <h3>For students</h3>
                            <p>Prepare Exam with Chapter-wise real Exam-style Questions , MCQs And have Access to all updated Notes </p>
                            <a class="btn btn-primary" href="online_quiz_join">Join Quiz</a>
                            <a class="btn btn-outline bypass-user-type" href="quiz_setup">Take Online Test</a>
                            <a class="btn btn-ghost" href="note">View Notes</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="home-reviews-section">
            <div class="container">
                <div class="home-reviews-header">
                    <h2>What Learners Say About Our Platform</h2>
                    <p>Real feedback from students and teachers after using our quiz system, MCQs tools, and question paper generator.</p>
                </div>

                <?php if ($reviewsTableExists && !empty($latestReviews)): ?>
                    <div class="home-reviews-grid">
                        <?php foreach ($latestReviews as $review): ?>
                            <?php
                                $name = trim((string)($review['reviewer_name'] ?? ''));
                                if ($name === '') {
                                    $name = ((int)($review['is_anonymous'] ?? 0) === 1) ? 'Anonymous User' : 'User';
                                }
                                $feedback = trim((string)($review['feedback'] ?? ''));
                                $snippet = strlen($feedback) > 180 ? substr($feedback, 0, 180) . '...' : $feedback;
                                $reviewTime = strtotime((string)($review['created_at'] ?? 'now'));
                            ?>
                            <article class="home-review-card">
                                <div class="home-review-stars"><?= htmlspecialchars(homeReviewStars((int)$review['rating'])) ?></div>
                                <p class="home-review-feedback"><?= htmlspecialchars($snippet) ?></p>
                                <div class="home-review-footer">
                                    <div class="home-reviewer-info">
                                        <div class="home-reviewer-avatar">
                                            <i class="fas fa-user-circle"></i>
                                        </div>
                                        <strong><?= htmlspecialchars($name) ?></strong>
                                    </div>
                                    <span><?= date('d M Y', $reviewTime) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="home-review-empty">
                        Reviews will appear here after students submit feedback at the end of quizzes.
                    </div>
                <?php endif; ?>

                <div class="home-reviews-actions" style="gap: 1.5rem; flex-wrap: wrap;">
                    <a class="button ghost" href="reviews.php" style="color: var(--primary); border-color: var(--primary);"><i class="fas fa-star"></i> View All Reviews</a>
                    <a class="button accent" href="reviews.php#write-review"><i class="fas fa-pen"></i> Write a Review</a>
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
                <a href="class-9th-and-10th-online-question-paper-generator" class="button primary bypass-user-type"><i class="fas fa-file-invoice"></i> Generate Paper</a>
                <a href="quiz_setup" class="button ghost bypass-user-type"><i class="fas fa-laptop-code"></i> Start Test</a>
            </div>
        </div>
    </div>
</section>

    </div>


    <script>
    function handleMockupClick(element, isCorrect) {
        // Find the container
        const optionsContainer = document.getElementById('mockup-quiz-options');
        const allOptions = optionsContainer.querySelectorAll('.mockup-option');
        
        // Remove existing result classes and icons from all options
        allOptions.forEach(opt => {
            opt.classList.remove('correct', 'incorrect');
            const icon = opt.querySelector('i');
            if (icon) icon.remove();
        });
        
        // Add the appropriate class to the clicked element
        if (isCorrect) {
            element.classList.add('correct');
            element.innerHTML += ' <i class="fas fa-check-circle"></i>';
        } else {
            element.classList.add('incorrect');
            element.innerHTML += ' <i class="fas fa-times-circle"></i>';
        }
    }
    </script>

    <?php include 'footer.php'; ?>
    </body>
    </html>
