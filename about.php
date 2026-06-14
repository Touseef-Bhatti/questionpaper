<?php
// about.php - Professional About page for Ahmad Learning Hub
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db_connect.php';

function alhPublicCount(mysqli $conn, string $sql): int
{
    try {
        $result = $conn->query($sql);
        if (!$result) {
            return 0;
        }
        $row = $result->fetch_row();
        return max(0, (int) ($row[0] ?? 0));
    } catch (Throwable $e) {
        error_log('About page statistic query failed: ' . $e->getMessage());
        return 0;
    }
}

function alhFormatPublicCount(int $count): string
{
    if ($count >= 10000) {
        return number_format((int) floor($count / 1000) * 1000) . '+';
    }
    if ($count >= 1000) {
        return number_format((int) floor($count / 100) * 100) . '+';
    }
    if ($count >= 100) {
        return number_format((int) floor($count / 10) * 10) . '+';
    }
    return number_format($count);
}

$totalMcqs =
    alhPublicCount($conn, 'SELECT COUNT(*) FROM mcqs') +
    alhPublicCount($conn, 'SELECT COUNT(*) FROM AIGeneratedMCQs');
$totalTheoreticalQuestions =
    alhPublicCount($conn, "SELECT COUNT(*) FROM questions WHERE question_type IN ('short', 'long')") +
    alhPublicCount($conn, 'SELECT COUNT(*) FROM AIGeneratedShortQuestions') +
    alhPublicCount($conn, 'SELECT COUNT(*) FROM AIGeneratedLongQuestions');
$dailyQuizSessions =
    alhPublicCount($conn, 'SELECT COUNT(*) FROM quiz_rooms WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY') +
    alhPublicCount($conn, 'SELECT COUNT(*) FROM quiz_participants WHERE started_at >= CURDATE() AND started_at < CURDATE() + INTERVAL 1 DAY');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/includes/favicons.php'; ?>
    <!-- Google tag (gtag.js) -->
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>
    <?php // AdSense review: third-party ads disabled. include_once __DIR__ . '/includes/monetag_ads.php'; ?>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Learn about Ahmad Learning Hub, its founder, and its educational tools for question-paper generation, MCQs practice, notes, tests, and live quizzes.">
    <link rel="canonical" href="https://ahmadlearninghub.com.pk/about">
    <meta name="keywords" content="Online question paper generator, 9 th class, 10th class question paper generator, chapter wise question paper generator, online MCQs test, online quiz hosting, question paper generating, Ahmad Learning Hub, M Arshad Bhatti, Sheikhupura academy">
    <title>About Us | AI-Powered 9th & 10th Class Exam Preparation | Ahmad Learning Hub</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/about.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content p-0">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-content">
                <h1 class="animate-zoom">Online Question Paper Generator & Learning Hub</h1>
                <p class="hero-subtitle">Create chapter-wise question papers, practise MCQs, review theoretical questions, and host live quizzes using the classes and subjects available on Ahmad Learning Hub.</p>
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?= htmlspecialchars(alhFormatPublicCount($totalMcqs)) ?></span>
                        <span class="stat-label">MCQs in Database</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= htmlspecialchars(alhFormatPublicCount($totalTheoreticalQuestions)) ?></span>
                        <span class="stat-label">Short &amp; Long Questions</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= htmlspecialchars(alhFormatPublicCount($dailyQuizSessions)) ?></span>
                        <span class="stat-label">Quiz Sessions Today</span>
                    </div>
                </div>
            </div>
        </section>

      

        <!-- Founder Section -->
        <section class="founder-section" style="padding: 4rem 1rem; background-color: #f9fbfd;">
            <div class="container">
                <div class="founder-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem; align-items: center;">
                    <div style="text-align: center;">
                        <div style="position: relative; display: inline-block; padding-bottom: 25px;">
                            <img src="M-Arshad-Bhatti.jpeg" alt="M Arshad Bhatti - Founder of Ahmad Learning Hub" style="max-width: 100%; width: 350px; border-radius: 15px; box-shadow: 0 15px 30px rgba(0,0,0,0.15); display: block;">
                            
                            <!-- Professional Floating Contact Card -->
                            <div style="background: white; padding: 0.6rem 1.5rem; border-radius: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.12); position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); display: flex; gap: 1.2rem; width: max-content; align-items: center; z-index: 2; border: 1px solid #f1f5f9; backdrop-filter: blur(10px);">
                                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=zouraize@gmail.com" target="_blank" rel="noopener noreferrer" style="color: #334155; text-decoration: none; display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 0.95rem; transition: all 0.3s;" onmouseover="this.querySelector('.icon-bg').style.background='#3b82f6'; this.querySelector('i').style.color='white'" onmouseout="this.querySelector('.icon-bg').style.background='#eff6ff'; this.querySelector('i').style.color='#3b82f6'">
                                    <div class="icon-bg" style="width: 34px; height: 34px; border-radius: 50%; background: #eff6ff; display: flex; align-items: center; justify-content: center; transition: all 0.3s;">
                                        <i class="fas fa-envelope" style="color: #3b82f6; font-size: 0.9rem; transition: all 0.3s;"></i>
                                    </div>
                                    Email
                                </a>
                                
                                <div style="width: 2px; height: 20px; background: #e2e8f0; border-radius: 2px;"></div>
                                
                                <a href="https://wa.me/923006480410" target="_blank" rel="noopener noreferrer" style="color: #334155; text-decoration: none; display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 0.95rem; transition: all 0.3s;" onmouseover="this.querySelector('.icon-bg-wa').style.background='#22c55e'; this.querySelector('i').style.color='white'" onmouseout="this.querySelector('.icon-bg-wa').style.background='#dcfce7'; this.querySelector('i').style.color='#22c55e'">
                                    <div class="icon-bg-wa" style="width: 34px; height: 34px; border-radius: 50%; background: #dcfce7; display: flex; align-items: center; justify-content: center; transition: all 0.3s;">
                                        <i class="fab fa-whatsapp" style="color: #22c55e; font-size: 1.1rem; transition: all 0.3s;"></i>
                                    </div>
                                    WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h2 style="font-size: 2.5rem; margin-bottom: 1rem; color: #333;">Our Founder & Vision</h2>
                        <p style="font-size: 1.15rem; line-height: 1.8; color: #555; margin-bottom: 1.5rem;">
                            Ahmad Learning Hub is the visionary project of <strong>M Arshad Bhatti</strong>. Under his dedicated supervision, they successfully run a physical academy, <strong>Ahmad Learning Hub</strong>, located in <strong>Sheikhupura</strong>.
                        </p>
                        <p style="font-size: 1.15rem; line-height: 1.8; color: #555;">
                            This digital platform was built to extend classroom and academy support through a question-paper generator, MCQs practice, theoretical questions, study resources, and live quiz hosting for the classes and subjects currently available.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        
        <!-- Features Grid -->
        <section class="features-section">
            <div class="container">
                <h2 class="text-center mb-4">Our Powerful Features</h2>
                <div class="features-grid">
                    <a href="online-question-paper-generator" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-robot"></i></div>
                        <h3>AI-Powered Questions</h3>
                        <p>Use AI-assisted tools to create practice material from selected topics or uploaded educational content, then review the result before classroom or exam use.</p>
                        <div class="feature-link">Explore AI Tools <i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="quiz/online_quiz_host_new.php" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-desktop"></i></div>
                        <h3>Host Online Quizzes</h3>
                        <p>Teachers can easily <strong>host online quizzes</strong> for their students, making classroom assessment interactive and data-driven.</p>
                        <div class="feature-link">Get Started <i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="topic-wise-mcqs-test" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                        <h3>Online MCQs Test</h3>
                        <p>Practise MCQs from the available class, book, chapter, and topic collections with instant results.</p>
                        <div class="feature-link">Take a Test <i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="notes/uploaded_notes.php" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-file-alt"></i></div>
                        <h3>Solved Notes</h3>
                        <p>Access uploaded notes and study resources organized by the classes, books, and chapters available on the platform.</p>
                        <div class="feature-link">View Notes <i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="select_class.php" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-brain"></i></div>
                        <h3>Smart Paper Generation</h3>
                        <p>Select chapters and question types to create a printable practice paper for school, academy, or independent revision.</p>
                        <div class="feature-link">Generate Paper <i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="profile.php" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                        <h3>Progress Analytics</h3>
                        <p>Track your preparation levels with detailed performance reports and AI-driven insights into your weak areas.</p>
                        <div class="feature-link">View Stats <i class="fas fa-arrow-right"></i></div>
                    </a>
                </div>
            </div>
        </section>
  <!-- Mission Section -->
        <section class="mission-section">
            <div class="container text-center">
                <h2 class="section-title">Why Choose Ahmad Learning Hub?</h2>
                <p class="mission-text">
                    Ahmad Learning Hub brings question-paper creation, theoretical questions, MCQs practice, study resources, exam tests, and live quiz hosting into one educational platform. Students and teachers can choose available classes, books, chapters, or topics and should compare generated material with the latest official textbook and instructions.
                </p>
            </div>
        </section>
        <!-- Subjects Summary -->
        <section class="subjects-section">
            <div class="container">
                <h2 class="text-center mb-4">Comprehensive Coverage</h2>
                <div class="subjects-grid">
                    <div class="subject-category">
                        <h3><i class="fas fa-microscope"></i> Science Group</h3>
                        <ul>
                            <li>9th & 10th Physics</li>
                            <li>Applied Mathematics</li>
                            <li>Advanced Chemistry</li>
                            <li>Biology & Life Sciences</li>
                            <li>Computer Science</li>
                        </ul>
                    </div>
                    <div class="subject-category">
                        <h3><i class="fas fa-pen-nib"></i> Humanities & Arts</h3>
                        <ul>
                            <li>English Grammar & Lit</li>
                            <li>Urdu Complete Notes</li>
                            <li>Pakistan Studies</li>
                            <li>Islamic Studies</li>
                            <li>General Science</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <div class="container text-center">
                <div class="cta-content">
                    <h2>Ready to Ace Your Exams?</h2>
                    <p>Create board-oriented question papers, practise chapter-wise MCQs, and host live educational quizzes from one platform. Always verify generated material against the current official syllabus.</p>
                    <div class="cta-buttons">
                        <a href="quiz/online_quiz_join.php" class="btn btn-primary">Join a Live Quiz</a>
                        <a href="auth/register.php" class="btn btn-secondary">Create Free Account</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        function initScrollAnimations() {
            const animatedElements = document.querySelectorAll('.animate-on-scroll, .animate-zoom');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate');
                    }
                });
            }, { threshold: 0.1 });
            animatedElements.forEach(el => observer.observe(el));
        }
        document.addEventListener('DOMContentLoaded', initScrollAnimations);
    </script>
</body>
</html>
