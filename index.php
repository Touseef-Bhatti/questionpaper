<?php
session_start();
include 'db_connect.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/home_search.php';

$homeSearchItems = homeSearchItems($conn);
$homeSearchQuickLinks = array_slice(array_values(array_filter($homeSearchItems, static fn(array $item): bool => !empty($item['is_quick_link']))), 0, 8);
$homeSearchSettings = homeSearchSettings($conn);
if (empty($_SESSION['home_search_csrf'])) {
    $_SESSION['home_search_csrf'] = bin2hex(random_bytes(24));
}
$homeSearchToken = $_SESSION['home_search_csrf'];

$latestReviews = [];
$reviewsTableExists = false;
$reviewsTableCheck = $conn->query("SHOW TABLES LIKE 'user_reviews'");
if ($reviewsTableCheck && $reviewsTableCheck->num_rows > 0) {
    $reviewsTableExists = true;
    $latestReviewsResult = $conn->query("SELECT reviewer_name, rating, feedback, created_at, is_anonymous, is_pinned FROM user_reviews WHERE is_approved = 1 ORDER BY is_pinned DESC, created_at DESC LIMIT 3");
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

$pageTitle = 'Online Question Paper Generator | Class 9–12 & University';
$metaDescription = 'Create printable question papers for Class 9–12, college and university subjects. Practise MCQs, revise chapters and prepare for Pakistani board exams with Ahmad Learning Hub.';
$metaKeywords = 'online question paper generator Pakistan, exam paper maker, Class 9 question paper generator, Class 10 question paper generator, Class 11 question paper generator, Class 12 question paper generator, college exam maker, university exam maker, board exam preparation, MCQs practice';
?>

<!DOCTYPE html>
<html lang="en-PK">
<head>
    <!-- Google tag (gtag.js) -->
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>
    <?php // AdSense review: third-party ads disabled. include_once __DIR__ . '/includes/monetag_ads.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php alh_render_seo_head([
        'title' => $pageTitle,
        'description' => $metaDescription,
        'keywords' => $metaKeywords,
        'canonical' => alh_seo_absolute_url('/'),
        'page_type' => 'EducationalWebPage',
        'audience_type' => 'Class 9–12 students, college and university students, teachers, tutors and schools in Pakistan',
    ]); ?>

    <link rel="stylesheet" href="css/main.css?v=1.1">
    <link rel="stylesheet" href="css/index.css?v=1.1">
<?php include_once __DIR__ . '/includes/favicons.php'; ?>

</head>
<body>
    <?php include 'header.php'; ?>
    <div class="main-content" style="margin-top: -7%;">

        <!-- HERO: Futuristic & Clean -->
        <section class="hero-section">
            <div class="container hero-grid">
                
               <div class="hero-content">

    <h1 class="hero-title">
        Free Online Question Paper Generator &amp; MCQs Practice
    </h1>
    
    <p class="subtitle">
        Create printable exam papers, practise chapter-wise MCQs, and prepare for board, college, and university exams.
    </p>
</div>

                    <button class="home-smart-search-trigger" type="button" data-home-search-open aria-haspopup="dialog" aria-controls="homeSmartSearch" aria-label="Open smart website search">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($homeSearchSettings['placeholder'], ENT_QUOTES, 'UTF-8') ?></span>
                        <kbd>Ctrl K</kbd>
                    </button>

                    <div class="hero-actions">
                        <br>
                        <a href="class-9th-and-10th-online-question-paper-generator" class="button primary bypass-user-type ALH_cct" data-action="generate_paper"><i class="fas fa-file-invoice"></i> Generate Paper</a>
                        <a href="online-mcqs-test-for-9th-and-10th-board-exams" class="button secondary bypass-user-type ALH_cct" data-action="online_mcqs"><i class="fas fa-laptop-code"></i> Online MCQs Test</a>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="login" class="button accent hero-login-btn"><i class="fas fa-sign-in-alt"></i> Login Now</a>
                        <?php endif; ?>
                    </div>

            </div>
        </section>

        <div class="home-search-overlay" id="homeSmartSearch" data-search-token="<?= htmlspecialchars($homeSearchToken, ENT_QUOTES, 'UTF-8') ?>" hidden>
            <div class="home-search-backdrop" data-home-search-close></div>
            <section class="home-search-dialog" role="dialog" aria-modal="true" aria-labelledby="homeSearchTitle">
                <h2 id="homeSearchTitle" class="sr-only">Search Ahmad Learning Hub</h2>
                <div class="home-search-input-row">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input id="homeSearchInput" type="search" autocomplete="off" maxlength="120" spellcheck="true" placeholder="<?= htmlspecialchars($homeSearchSettings['placeholder'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Search website features" aria-controls="homeSearchResults">
                    <span class="home-search-esc" aria-hidden="true">ESC</span>
                    <button type="button" class="home-search-close" data-home-search-close aria-label="Close search"><i class="fas fa-times"></i></button>
                </div>
                <div class="home-search-content">
                    <div class="home-search-heading-row">
                        <p class="home-search-eyebrow" id="homeSearchSectionTitle">Quick links</p>
                        <span class="home-search-status" id="homeSearchStatus" role="status" aria-live="polite"></span>
                    </div>
                    <div class="home-search-results" id="homeSearchResults" role="listbox">
                        <?php foreach ($homeSearchQuickLinks as $item): ?>
                            <?php $publicItem = homeSearchPublicItem($item); ?>
                            <a class="home-search-result" href="<?= htmlspecialchars($publicItem['url'], ENT_QUOTES, 'UTF-8') ?>" role="option" data-search-item-id="<?= $publicItem['id'] ?>" data-search-kind="quick_link">
                                <span class="home-search-result-icon"><i class="<?= htmlspecialchars($publicItem['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                <span><strong><?= htmlspecialchars($publicItem['title'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($publicItem['description'], ENT_QUOTES, 'UTF-8') ?></small></span>
                                <i class="fas fa-arrow-right home-search-result-arrow" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="home-search-empty" id="homeSearchEmpty" hidden>
                        <strong><?= htmlspecialchars($homeSearchSettings['no_results_title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($homeSearchSettings['no_results_message'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <footer class="home-search-footer">
                    <span><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
                    <span><kbd>Enter</kbd> Open</span>
                    <span><kbd>Esc</kbd> Close</span>
                    <span class="home-search-smart-note"><i class="fas fa-magic"></i> Understands related words & typos</span>
                </footer>
            </section>
        </div>

      

        <div class="container">
            <div class="hero-prep-section" role="region" aria-label="Exam preparation categories">
                <h2 class="hero-prep-title">Question Paper Maker and Exam Preparation for Every Level</h2>
               <br>
                <p class="hero-prep-description">
                    Choose a class or study level to generate a question paper, practise a test, or prepare for board, college, and university exams. Ahmad Learning Hub supports Class 9 and 10, Intermediate Class 11 and 12, and higher-education assessment workflows.
                </p>

                <div class="hero-prep-grid">
                    <a href="class-9th-and-10th-online-question-paper-generator" class="hero-prep-card bypass-user-type" aria-label="Open the Class 9 and 10 online question paper generator">
                        <span class="hero-prep-icon"><i class="fas fa-graduation-cap"></i></span>
                        <h3>Class 9 & 10 Exam Preparation</h3>
                        <p>Generate Class 9 and 10 question papers, build strong fundamentals with chapter-wise tests, model papers, and comprehensive board practice.</p>
                        <span class="prep-card-cta">Open Class 9 & 10 Paper Generator <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="class-11-and-12-online-question-paper-generator" class="hero-prep-card bypass-user-type" aria-label="Open the Class 11 and 12 online question paper generator">
                        <span class="hero-prep-icon"><i class="fas fa-university"></i></span>
                        <h3>Class 11 & 12 Exam Preparation</h3>
                        <p>Create Intermediate and HSSC question papers while mastering subjects with chapter tests, short questions, and final revision papers.</p>
                        <span class="prep-card-cta">Open Class 11 & 12 Paper Generator <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="quiz_setup" class="hero-prep-card bypass-user-type ALH_cct" data-action="online_mcqs">
                        <span class="hero-prep-icon"><i class="fas fa-clipboard-check"></i></span>
                        <h3>MCQs Preparation Class 9 & 10</h3>
                        <p>Take topic-wise MCQs tests with instant scoring and smart performance tracking.</p>
                        <span class="prep-card-cta">Start Test <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="class-9-10-11-12-test-series-for-board-exams" class="hero-prep-card bypass-user-type" aria-label="Explore Class 9 to 12 board exam test series">
                        <span class="hero-prep-icon"><i class="fas fa-file-signature"></i></span>
                        <h3>Board Exam Preparation</h3>
                        <p>Prepare with board-oriented formats, realistic paper structure, and balanced difficulty.</p>
                        <span class="prep-card-cta">Generate Paper <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="online-question-paper-generator" class="hero-prep-card bypass-user-type" aria-label="Open the college and university online question paper generator">
                        <span class="hero-prep-icon"><i class="fas fa-university"></i></span>
                        <h3>College & University Exams</h3>
                        <p>Create professional question papers and exams for college, university, intermediate, and higher-education subjects.</p>
                        <span class="prep-card-cta">Open College & University Exam Maker <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="topic-wise-mcqs-test" class="hero-prep-card bypass-user-type ALH_cct" data-action="online_mcqs">
                        <span class="hero-prep-icon"><i class="fas fa-brain"></i></span>
                        <h3>MCQs Practice Hub</h3>
                        <p>Practice objective questions regularly to improve speed, accuracy, and confidence.</p>
                        <span class="prep-card-cta">Practice Now <i class="fas fa-arrow-right"></i></span>
                    </a>
                </div>

                <ul class="hero-keywords" aria-label="Popular exam preparation topics">
                    <li><a href="class-9th-and-10th-online-question-paper-generator" class="bypass-user-type"><i class="fas fa-book-open"></i> Class 9 & 10 Question Paper Generator</a></li>
                    <li><a href="select_book.php?class_id=9" class="bypass-user-type"><i class="fas fa-school"></i> Class 9 Exam Preparation</a></li>
                    <li><a href="select_book.php?class_id=10" class="bypass-user-type"><i class="fas fa-graduation-cap"></i> Class 10 Exam Preparation</a></li>
                    <li><a href="class-11-and-12-online-question-paper-generator" class="bypass-user-type"><i class="fas fa-university"></i> Class 11 & 12 Question Paper Generator</a></li>
                    <li><a href="quiz_setup" class="bypass-user-type ALH_cct" data-action="online_mcqs"><i class="fas fa-check-circle"></i> Class 9 & 10 MCQs Preparation</a></li>
                    <li><a href="class-9-10-11-12-test-series-for-board-exams" class="bypass-user-type"><i class="fas fa-file-alt"></i> Board Exam Test Series</a></li>
                    <li><a href="online-question-paper-generator" class="bypass-user-type"><i class="fas fa-university"></i> College & University Exam Maker</a></li>
                    <li><a href="topic-wise-mcqs-test" class="bypass-user-type ALH_cct" data-action="online_mcqs"><i class="fas fa-pencil-alt"></i> MCQs Practice</a></li>
                </ul>
            </div>
        </div>
<br><br><br><br>

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
                        <a href="online-quiz-hosting" class="qf-card">
                            <div class="qf-icon"><i class="fas fa-chart-bar"></i></div>
                            <div class="qf-content">
                                <h4>Real-Time Leaderboard</h4>
                                <p>Live rank tracking keeps engagement high and learning competitive.</p>
                            </div>
                        </a>
                        <a href="online-quiz-hosting" class="qf-card">
                            <div class="qf-icon"><i class="fas fa-stopwatch"></i></div>
                            <div class="qf-content">
                                <h4>Timed Questions</h4>
                                <p>Set per-question timers for a real exam feel with auto-submit.</p>
                            </div>
                        </a>
                        <a href="online-quiz-hosting" class="qf-card">
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
                        <div class="quiz-float-badge badge-bottom"><i class="fas fa-star"></i> Instant Results</div>
                    </div>

                </div>

                <!-- Bottom CTA -->
                <div class="quiz-showcase-cta">
                    <a href="online-quiz-hosting" class="button primary large quiz-host-btn"><i class="fas fa-play-circle"></i> Start Hosting a Quiz</a>
                    <a href="online_quiz_join" class="button secondary large quiz-join-btn"><i class="fas fa-gamepad"></i> Join a Quiz</a>
                </div>
            </div>
        </section>

<br><br>
        <!-- ROLES SECTION: Teachers & Students -->
        <section class="alh-roles-section">
            <div class="container">
                <div class="roles-grid">
                    
                    <!-- Teachers Card -->
                    <div class="role-card teacher-card">
                        <div class="role-header">
                            <div class="role-icon-box">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div class="role-title-box">
                                <h3>Teacher Tools</h3>
                                <span>For Educators & Institutions</span>
                            </div>
                        </div>
                        <div class="role-body">
                            <p>Streamline your teaching with automated paper generation and interactive live quizzes.</p>
                            <ul class="role-features">
                                <li><i class="fas fa-check"></i> Custom Board Patterns</li>
                                <li><i class="fas fa-check"></i> Real-time Hosting</li>
                            </ul>
                            <div class="role-actions">
                                <a href="class-9th-and-10th-online-question-paper-generator" class="role-btn primary bypass-user-type ALH_cct" data-action="generate_paper">
                                    <i class="fas fa-file-invoice"></i> Generate Paper
                                </a>
                                <div class="role-sub-actions">
                                    <a href="online-quiz-hosting" class="role-btn secondary">Host a Live Quiz</a>
                                    <a href="note" class="role-btn ghost">View Notes</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Students Card -->
                    <div class="role-card student-card">
                        <div class="role-header">
                            <div class="role-icon-box">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="role-title-box">
                                <h3>For Students</h3>
                                <span>For Independent Learners</span>
                            </div>
                        </div>
                        <div class="role-body">
                            <p>Boost your grades with exam-style MCQs, professional notes, and competitive live quizzes.</p>
                            <ul class="role-features">
                                <li><i class="fas fa-check"></i> Chapter-wise MCQs</li>
                                <li><i class="fas fa-check"></i> Interactive Leaderboards</li>
                            </ul>
                            <div class="role-actions">
                                <a href="examPreparation/select_class_for_test.php" class="role-btn primary">
                                    <i class="fas fa-graduation-cap"></i> Board Exam Preparations
                                </a>
                                <div class="role-sub-actions">
                                    <a href="online-mcqs-test-for-9th-and-10th-board-exams" class="role-btn secondary bypass-user-type ALH_cct" data-action="online_mcqs">Take Online Test</a>
                                    <a href="note" class="role-btn ghost">View Notes</a>
                                </div>
                            </div>
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
                            <?php $isPinned = (int)($review['is_pinned'] ?? 0) === 1; ?>
                            <article class="home-review-card" style="<?= $isPinned ? 'border: 2px solid #f59e0b; box-shadow: 0 10px 25px rgba(245, 158, 11, 0.15); position: relative;' : '' ?>">
                                <?php if ($isPinned): ?>
                                    <div style="position: absolute; top: -12px; right: 20px; background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 4px; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); z-index: 10;">
                                        <i class="fas fa-star"></i> Featured
                                    </div>
                                <?php endif; ?>
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


      <!-- CTA -->
<br>
<section class="cta-section">
    <div class="container">
        <div class="cta-card">
            <h2>Ready to excel in your <span class="highlight">exam preparation</span>?</h2>
            
            <p>
                Use <strong>Ahmad Learning Hub</strong> to create question papers, practise chapter-wise MCQs, take board-oriented tests, and host live educational quizzes.
                The strongest coverage is for school and intermediate subjects available in the platform's current class, book, and chapter database.
            </p>
            
            <p>
                Unlock adaptive tests, generate personalized <span class="keyword">study materials</span>, and host engaging quizzes with ease. 
                Whether you’re a student aiming for top scores or an educator streamlining assessments, our platform provides all the tools you need in one comprehensive <span class="keyword">exam preparation platform</span>.
            </p>
            
            <div class="cta-actions">
                <a href="class-9th-and-10th-online-question-paper-generator" class="button primary bypass-user-type ALH_cct" data-action="generate_paper"><i class="fas fa-file-invoice"></i> Generate Paper</a>
                <a href="online-mcqs-test-for-9th-and-10th-board-exams" class="button ghost bypass-user-type ALH_cct" data-action="online_mcqs"><i class="fas fa-laptop-code"></i> Start Test</a>
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
