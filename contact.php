<?php
// contact.php - Professional Contact page for Ahmad Learning Hub
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Have questions about 9th or 10th class exam preparation? Contact Ahmad Learning Hub, the best AI exam preparation website in Pakistan for MCQs, online tests, and notes.">
    <meta name="keywords" content="contact Ahmad Learning Hub, 9th class help, 10th class support, AI exam prep assistance, hosting online quiz support, questions prep feedback">
    <title>Contact Us | Support for 9th & 10th Class AI Exam Prep | Ahmad Learning Hub</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/contact.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content p-0">
        <!-- Hero Section -->
        <section class="contact-hero">
            <div class="hero-content">
                <h1 class="animate-zoom">We're Here for Your Success</h1>
                <p class="hero-subtitle">Get professional support for your <strong>9th and 10th class exam preparation</strong>. Our team is ready to assist you with AI tools and study material.</p>
            </div>
        </section>

        <!-- Contact Content -->
        <section class="contact-info-section">
            <div class="container">
                <div class="contact-grid">
                    <!-- Info Column -->
                    <div class="contact-info animate-on-scroll">
                        <h2 class="section-title">Reach Out to Experts</h2>
                        <p class="mb-4">Whether you are a teacher looking to <strong>host online quizzes</strong> or a student needing help with <strong>mcqs</strong> and <strong>notes</strong>, we are just a message away.</p>
                        
                        <div class="info-items">
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-envelope-open-text"></i></div>
                                <div class="info-content">
                                    <h3>Official Email</h3>
                                    <p><a href="mailto:touseef12345bhatti@gmail.com">touseef12345bhatti@gmail.com</a></p>
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
                                <li><i class="fas fa-check"></i> <strong>AI Exam Preparation Website</strong> guidance.</li>
                                <li><i class="fas fa-check"></i> Setting up <strong>hosting online quiz</strong> sessions.</li>
                                <li><i class="fas fa-check"></i> High-quality <strong>online mcqs test notes</strong> inquiries.</li>
                                <li><i class="fas fa-check"></i> Board-specific <strong>questions prep</strong> requests.</li>
                            </ul>
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
                                    <textarea id="message" name="message" required placeholder="Tell us how we can help with your 9th or 10th class studies..."></textarea>
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
