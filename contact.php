<?php
// Require authentication before accessing this page
require_once 'auth/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contact QPaperGen for support, feedback, or inquiries about 9th and 10th class past papers, online tests, guess papers, and notes for Punjab Board exams. We are here to assist teachers and students in Pakistan.">
    <meta name="keywords" content="QPaperGen, contact, support, feedback, inquiry, 9th class, 10th class, past papers, online tests, guess papers, notes, Punjab Board, exam preparation, Pakistan">
    <title>Contact QPaperGen - Support for 9th & 10th Class Exam Prep</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/contact.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="contact-content">
    <!-- Hero Section -->
    <section class="contact-hero">
        <div class="hero-content">
            <h1>📧 Get in Touch</h1>
            <p class="hero-subtitle">We're here to help with any questions or feedback about QPaperGen</p>
        </div>
    </section>

    <!-- Contact Information -->
    <section class="contact-info-section">
        <div class="container">
            <div class="contact-grid">
                <div class="contact-info">
                    <h2>📞 Contact Information</h2>
                    <div class="info-items">
                        <div class="info-item">
                            <div class="info-icon">📧</div>
                            <div class="info-content">
                                <h3>Email Support</h3>
                                <p><a href="mailto:touseef12345bhatti@gmail.com">touseef12345bhatti@gmail.com</a></p>
                                <small>We respond within 24 hours</small>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">⏰</div>
                            <div class="info-content">
                                <h3>Response Time</h3>
                                <p>24-48 hours</p>
                                <small>Monday to Friday, 9 AM - 6 PM</small>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">🌐</div>
                            <div class="info-content">
                                <h3>Website</h3>
                                <p>paper.bhattichemicalsindustry.com.pk</p>
                                <small>Available 24/7</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="help-topics">
                        <h3>📝 What can we help you with?</h3>
                        <ul>
                            <li>❓ Questions about using QPaperGen</li>
                            <li>📚 Subject-specific question requests</li>
                            <li>🐛 Bug reports and technical issues</li>
                            <li>💡 Feature suggestions and feedback</li>
                            <li>🏫 Partnership and collaboration inquiries</li>
                            <li>📊 Usage statistics and analytics</li>
                        </ul>
                    </div>
                </div>
                
                <div class="contact-form-section">
                    <h2>📝 Send us a Message</h2>
                    <p>Fill out the form below and we'll get back to you as soon as possible.</p>
                    <form class="contact-form" id="contactForm">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required>
        
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        
        <label for="message">Message:</label>
        <textarea id="message" name="message" required placeholder="Please describe your query or suggestion..."></textarea>
        
        <button type="submit" id="submitBtn">
            <span id="buttonText">Send Message</span>
            <span id="buttonLoader" style="display: none;">📧 Sending...</span>
        </button>
        
        <div id="formMessage" class="form-message" style="display: none;"></div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
</div> <!-- main-content -->

<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submitBtn');
    const buttonText = document.getElementById('buttonText');
    const buttonLoader = document.getElementById('buttonLoader');
    const formMessage = document.getElementById('formMessage');
    
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(contactForm);
            const name = formData.get('name').trim();
            const email = formData.get('email').trim();
            const message = formData.get('message').trim();
            
            // Basic validation
            if (!name || !email || !message) {
                showMessage('Please fill in all fields.', 'error');
                return;
            }
            
            if (!isValidEmail(email)) {
                showMessage('Please enter a valid email address.', 'error');
                return;
            }
            
            // Show loading state
            setLoadingState(true);
            
            // Send form data to server
            fetch('contact_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                setLoadingState(false);
                if (data.success) {
                    showMessage(data.message, 'success');
                    contactForm.reset();
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                setLoadingState(false);
                console.error('Error:', error);
                showMessage('Sorry, there was an error sending your message. Please try again.', 'error');
            });
        });
    }
    
    function setLoadingState(loading) {
        if (loading) {
            submitBtn.disabled = true;
            buttonText.style.display = 'none';
            buttonLoader.style.display = 'inline';
            submitBtn.style.opacity = '0.7';
        } else {
            submitBtn.disabled = false;
            buttonText.style.display = 'inline';
            buttonLoader.style.display = 'none';
            submitBtn.style.opacity = '1';
        }
    }
    
    function showMessage(text, type) {
        formMessage.textContent = text;
        formMessage.className = 'form-message ' + type;
        formMessage.style.display = 'block';
        
        // Hide message after 5 seconds
        setTimeout(() => {
            formMessage.style.display = 'none';
        }, 5000);
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
</script>

</body>
</html>
