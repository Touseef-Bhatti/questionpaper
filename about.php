<?php
// about.php - Professional About page for Ahmad Learning Hub
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/includes/favicons.php'; ?>
    <!-- Google tag (gtag.js) -->
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ahmad Learning Hub is a top-tier Online question paper generator and exam preparation platform. Host online quizzes, access online MCQs test, solved notes, chapter wise question paper generator for 9 th class and 10th class. A project by M Arshad Bhatti.">
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
                <h1 class="animate-zoom">Advanced Online Question Paper Generator & Learning Hub</h1>
                <p class="hero-subtitle">The ultimate <strong>chapter wise question paper generator</strong> and <strong>online MCQs test</strong> platform for <strong>9 th class</strong> and <strong>10th class</strong> students. Prepare smarter with our seamless <strong>question paper generating</strong> tools.</p>
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
                            With a passion to revolutionize learning, this digital platform was built to expand the reach of quality education. By offering top-tier services like our robust <strong>Online question paper generator</strong>, our interactive <strong>online MCQs test</strong> portal, and seamless <strong>online quiz hosting</strong>, we aim to bridge the gap between traditional academy preparation and modern digital learning for <strong>9 th class</strong> and 10th class students everywhere.
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
                        <p>Leverage our robust <strong>Online question paper generator</strong> and advanced tools to prepare targeted materials covering every important <strong>9 th class</strong> and <strong>10th class</strong> topic for board exams.</p>
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
                        <p>Prepare with our comprehensive <strong>online MCQs test</strong> series, designed to mirror the actual exam patterns of Punjab and Federal Boards.</p>
                        <div class="feature-link">Take a Test <i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="notes/uploaded_notes.php" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-file-alt"></i></div>
                        <h3>Solved Notes</h3>
                        <p>Access high-quality solved notes alongside your <strong>10th class question paper generator</strong> and <strong>9 th class</strong> resources, meticulously curated by expert educators for the best learning experience.</p>
                        <div class="feature-link">View Notes <i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="select_class.php" class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="fas fa-brain"></i></div>
                        <h3>Smart Paper Generation</h3>
                        <p>Use our highly customizable <strong>chapter wise question paper generator</strong> to create professional and accurate tests in seconds. Perfect for schools and academies needing reliable testing solutions.</p>
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
                    Ahmad Learning Hub is an advanced <strong>Online question paper generator</strong> and integrated exam preparation platform designed to meet the rigorous demands of modern education. We offer an unparalleled <strong>chapter wise question paper generator</strong> for comprehensive learning. Whether you are seeking a reliable <strong>10th class question paper generator</strong> or focused <strong>9 th class</strong> practice, our platform provides tools that help students master every subject with precision. Our key functionalities include seamless <strong>question paper generating</strong>, interactive <strong>online MCQs test</strong> practice, and flexible <strong>online quiz hosting</strong>.
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
                    <p>Join the thousands of students and teachers already using the most reliable <strong>Online question paper generator</strong>, thriving <strong>online MCQs test</strong> community, and top-tier <strong>online quiz hosting</strong> platform in Pakistan.</p>
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
