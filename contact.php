<?php
// contact.php - Professional Contact page for Ahmad Learning Hub
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/includes/favicons.php'; ?>
    <!-- Google tag (gtag.js) -->
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contact Ahmad Learning Hub. The best Online question paper generator, chapter wise question paper generator for 9 th class and 10th class. Get support for online MCQs test, online quiz hosting, and more. A project by M Arshad Bhatti.">
    <meta name="keywords" content="contact Ahmad Learning Hub, Online question paper generator, 9 th class, 10th class question paper generator, chapter wise question paper generator, online MCQs test, online quiz hosting, question paper generating, M Arshad Bhatti">
    <title>Contact Us | Support for 9th & 10th Class AI Exam Prep | Ahmad Learning Hub</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/contact.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content p-0" style="padding-top: 5%;">
        <!-- Hero Section -->
        <section class="contact-hero">
            <div class="hero-content">
                <h1 class="animate-zoom">We're Here to Support Your Journey</h1>
                <p class="hero-subtitle">Get professional support for your <strong>9 th class</strong> and <strong>10th class</strong> studies. Need help with our <strong>Online question paper generator</strong> or <strong>online quiz hosting</strong>? Our team is always ready to assist you.</p>
            </div>
        </section>

        <!-- Contact Content -->
        <section class="contact-info-section">
            <div class="container">
                <div class="contact-grid">
                    <!-- Info Column -->
                    <div class="contact-info animate-on-scroll">
                        <h2 class="section-title">Reach Out to Experts</h2>
                        <p class="mb-4">Whether you are a teacher looking to access our <strong>Online question paper generator</strong> and <strong>host online quizzes</strong> or a student needing help with an <strong>online MCQs test</strong>, we are just a message away.</p>
                        
                        <div class="info-items">
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-envelope-open-text"></i></div>
                                <div class="info-content">
                                    <h3>Official Email</h3>
                                    <p><a href="mailto:admin@ahmadlearninghub.com.pk">admin@ahmadlearninghub.com.pk</a></p>
                                    <small>Direct support for AI tools & feature requests.</small>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-headset"></i></div>
                                <div class="info-content">
                                    <h3>Quick Response</h3>
                                    <p>Within 24 Business Hours</p>
                                    <small>Monday – Saturday, 9:00 AM – 8:00 PM (PST)</small>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="info-content">
                                    <h3>Service Area</h3>
                                    <p>Nationwide - Pakistan</p>
                                    <small>Proudly serving all Punjab and Federal Boards.</small>
                                </div>
                            </div>
                        </div>

                        <div class="help-topics mt-4">
                            <h3>How We Assist You:</h3>
                            <ul class="styled-list">
                                <li><i class="fas fa-check"></i> <strong>Online question paper generator</strong> for all subjects.</li>
                                <li><i class="fas fa-check"></i> Setting up and <strong>online quiz hosting</strong> sessions.</li>
                                <li><i class="fas fa-check"></i> Comprehensive <strong>10th class question paper generator</strong> & <strong>9 th class</strong> tools.</li>
                                <li><i class="fas fa-check"></i> Detailed <strong>chapter wise question paper generator</strong> and <strong>online MCQs test</strong>.</li>
                            </ul>
                            
                            <div class="founder-contact-card mt-4" style="background: #f9fbfd; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 1.2rem; border-left: 4px solid #2563eb; margin-top: 2rem;">
                                <img src="M-Arshad-Bhatti.jpeg" alt="M Arshad Bhatti" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <div>
                                    <h4 style="margin: 0 0 0.3rem 0; font-size: 1.1rem; color: #2d3748;">M Arshad Bhatti</h4>
                                    <p style="margin: 0 0 0.2rem 0; font-size: 0.95rem; color: #4a5568;">Founder, Ahmad Learning Hub</p>
                                    <p style="margin: 0; font-size: 0.85rem; color: #718096;"><i class="fas fa-map-marker-alt" style="margin-right: 5px;"></i> Sheikhupura Academy Director</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Column -->
                    <div class="contact-form-section animate-on-scroll">
                        <div class="form-card">
                            <h2>Send a Message</h2>
                            <p>Fill out the form for personalized exam preparation assistance.</p>
                            <form class="contact-form" id="contactForm">
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <input type="text" id="name" name="name" required placeholder="Enter your name">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" required placeholder="name@example.com">
                                </div>
                                
                                <div class="form-group">
                                    <label for="message">Your Message</label>
                                    <textarea id="message" name="message" required placeholder="Need help with the 10th class question paper generator or an online MCQs test? Tell us how we can assist..."></textarea>
                                </div>
                                
                                <button type="submit" id="submitBtn" class="btn btn-primary w-100">
                                    <span id="buttonText"><i class="fas fa-paper-plane"></i> Send Inquiry</span>
                                    <span id="buttonLoader" style="display: none;"><i class="fas fa-spinner fa-spin"></i> Processing...</span>
                                </button>
                                
                                <div id="formMessage" class="form-message mt-3" style="display: none;"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const contactForm = document.getElementById('contactForm');
            const submitBtn = document.getElementById('submitBtn');
            const formMessage = document.getElementById('formMessage');
            
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    document.getElementById('buttonText').style.display = 'none';
                    document.getElementById('buttonLoader').style.display = 'inline';
                    submitBtn.disabled = true;

                    const formData = new FormData(this);
                    fetch('contact_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        formMessage.textContent = data.message;
                        formMessage.className = 'form-message ' + (data.success ? 'success' : 'error');
                        formMessage.style.display = 'block';
                        if(data.success) contactForm.reset();
                    })
                    .catch(() => {
                        formMessage.textContent = 'Something went wrong. Please try again.';
                        formMessage.className = 'form-message error';
                        formMessage.style.display = 'block';
                    })
                    .finally(() => {
                        document.getElementById('buttonText').style.display = 'inline';
                        document.getElementById('buttonLoader').style.display = 'none';
                        submitBtn.disabled = false;
                    });
                });
            }
        });
    </script>
</body>
</html>
