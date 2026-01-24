<?php
// about.php - Professional About page for Ahmad Learning Hub
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ahmad Learning Hub is Pakistan's leading AI-powered exam preparation website for 9th and 10th class students. Host online quizzes, access MCQs, solved notes, and smart question papers.">
    <meta name="keywords" content="9th class exam preparation, 10th class exam preparation, AI exam preparation website, online mcqs test, 9th class notes, online quiz hosting, Ahmad Learning Hub, question prep, Pakistan education">
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
                <h1 class="animate-zoom">Revolutionizing Pakistani Education with AI</h1>
                <p class="hero-subtitle">The ultimate AI exam preparation platform for 9th and 10th class students. Prepare smarter, not harder.</p>
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-number">50,000+</span>
                        <span class="stat-label">MCQs Bank</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">98%</span>
                        <span class="stat-label">Success Rate</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">5,000+</span>
                        <span class="stat-label">Daily Quizzes</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mission Section -->
        <section class="mission-section">
            <div class="container text-center">
                <h2 class="section-title">Why Choose Ahmad Learning Hub?</h2>
                <p class="mission-text">
                    Ahmad Learning Hub is not just a portal; it is an integrated <strong>AI exam preparation website</strong> designed to meet the rigorous demands of the modern curriculum. We specialize in <strong>9th and 10th class exam preparation</strong>, providing tools that help students master every subject with precision.
                </p>
            </div>
        </section>

        <!-- Features Grid -->
        <section class="features-section">
            <div class="container">
                <h2 class="text-center mb-4">Our Powerful Features</h2>
                <div class="features-grid">
                    <a href="quiz/online_quiz_host_new.php" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-robot"></i></div>
                        <h3>AI-Powered Questions</h3>
                        <p>Leverage our advanced AI to generate targeted <strong>questions prep</strong> materials, ensuring you cover every important topic for your board exams.</p>
                        <div class="feature-link">Explore AI Tools <i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="quiz/online_quiz_host_new.php" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-desktop"></i></div>
                        <h3>Host Online Quizzes</h3>
                        <p>Teachers can easily <strong>host online quizzes</strong> for their students, making classroom assessment interactive and data-driven.</p>
                        <div class="feature-link">Get Started <i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="select_class.php" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                        <h3>Online MCQs Test</h3>
                        <p>Prepare with our comprehensive <strong>online MCQs test</strong> series, designed to mirror the actual exam patterns of Punjab and Federal Boards.</p>
                        <div class="feature-link">Take a Test <i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="notes/uploaded_notes.php" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-file-alt"></i></div>
                        <h3>Solved Notes</h3>
                        <p>Access high-quality <strong>online mcqs test notes</strong> and solved papers for 9th and 10th class, curated by expert educators.</p>
                        <div class="feature-link">View Notes <i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="select_class.php" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-brain"></i></div>
                        <h3>Smart Paper Generation</h3>
                        <p>Generate professional question papers in seconds. Perfect for schools looking for standardized testing solutions.</p>
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
                    <p>Join the thousands of students already using the best <strong>AI exam preparation website</strong> in Pakistan.</p>
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
