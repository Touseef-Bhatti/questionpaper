<?php
// user_type_selection.php
// This file handles the "School or Other" selection popup without backend storage.
?>
<style>
    /* Overlay for the modal */
    .user-type-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(15, 23, 42, 0.8); /* Dark blur background */
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 10000; /* Very high to sit on top of everything */
        display: none; /* Hidden by default */
        justify-content: center;
        align-items: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .user-type-overlay.show {
        display: flex;
        opacity: 1;
    }

    /* The Modal Box */
    .user-type-modal {
        background: rgba(255, 255, 255, 0.95);
        padding: 2.5rem;
        border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        text-align: center;
        max-width: 90%;
        width: 400px;
        transform: scale(0.9);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        border: 1px solid rgba(255, 255, 255, 0.5);
    }

    .user-type-overlay.show .user-type-modal {
        transform: scale(1);
    }

    .user-type-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.5rem;
        font-family: 'Inter', system-ui, sans-serif;
    }

    .user-type-subtitle {
        color: #64748b;
        margin-bottom: 2rem;
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .user-type-buttons {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .type-btn {
        padding: 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        background: white;
        color: #1e293b;
        font-weight: 700;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-family: 'Inter', system-ui, sans-serif;
    }

    .type-btn:hover {
        border-color: #6366f1;
        color: #6366f1;
        background: #f8fafc;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .type-btn.primary {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 14px 0 rgba(99, 102, 241, 0.39);
    }

    .type-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.23);
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    }

    /* Icon styling placeholder */
    .btn-icon {
        font-size: 1.2rem;
    }
</style>

<div id="userTypeOverlay" class="user-type-overlay">
    <div class="user-type-modal">
        <h2 class="user-type-title">Welcome! 👋</h2>
        <p class="user-type-subtitle">Please select your role to personalize your experience:</p>
        
        <div class="user-type-buttons">
            <button class="type-btn primary" onclick="selectUserType('School')">
                <span class="btn-icon">🏫</span> School
            </button>
            <button class="type-btn" onclick="selectUserType('Other')">
                <span class="btn-icon">👤</span> Other
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        checkUserType();
    });

    function checkUserType() {
        const userType = localStorage.getItem('user_type_preference');
        
        if (!userType) {
            // No choice made yet, show popup after 5 seconds
            setTimeout(() => {
                const overlay = document.getElementById('userTypeOverlay');
                overlay.style.display = 'flex';
                // Small timeout for animation
                setTimeout(() => {
                    overlay.classList.add('show');
                }, 50);
            }, 5000);
        } else {
            console.log('User type already selected:', userType);
            applyUserTypeSettings(userType);
        }
    }

    function selectUserType(type) {
        // Save the choice in the browser
        localStorage.setItem('user_type_preference', type);
        
        // Visual feedback
        const overlay = document.getElementById('userTypeOverlay');
        overlay.classList.remove('show');
        
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 300);

        applyUserTypeSettings(type);
        
        console.log('User type saved:', type);
    }

    function applyUserTypeSettings(type) {
        // Add class to body for CSS styling
        document.body.classList.remove('user-type-school', 'user-type-other');
        document.body.classList.add('user-type-' + type.toLowerCase());

        // Update Navigation Links based on user type
        updateLinks(type);
    }

    function updateLinks(type) {
        const links = document.querySelectorAll('a');
        
        links.forEach(link => {
            const href = link.getAttribute('href');
            if (!href) return;

            // Define targets
            const targetGeneratePaper = 'select_class.php';
            const targetQuizSetup = 'quiz_setup.php'; // Matches quiz/quiz_setup.php or just quiz_setup.php

            // Logic for "Other" type
            if (type === 'Other') {
                if (href.indexOf(targetGeneratePaper) !== -1) {
                    // Check if we need to adjust path (e.g. if we are in a subdir)
                    // We can just replace the filename part if we assume structure
                    // But simpler: if it contains select_class.php, replace it with questionPaperFromTopic/index.php
                    // We need to handle relative paths carefully.
                    // If href is "select_class.php", new is "questionPaperFromTopic/index.php"
                    // If href is "../select_class.php", new is "../questionPaperFromTopic/index.php"
                    link.href = link.href.replace('select_class.php', 'questionPaperFromTopic/index.php');
                }
                if (href.indexOf(targetQuizSetup) !== -1) {
                    // Replace quiz_setup.php with mcqs_topic.php
                    // Note: mcqs_topic.php is usually in the same directory (quiz/) as quiz_setup.php
                    // So we just replace the filename.
                    // However, in header.php it might be "quiz/quiz_setup.php".
                    // If we replace "quiz_setup.php" with "mcqs_topic.php", "quiz/quiz_setup.php" becomes "quiz/mcqs_topic.php". Correct.
                    link.href = link.href.replace('quiz_setup.php', 'mcqs_topic.php');
                }
            } 
            // Logic for "School" type (Revert to defaults if needed, though usually default HTML is School)
            else if (type === 'School') {
                // If the link was previously changed to the "Other" version, revert it.
                // This handles the case where user switches types without reloading (if we allowed that)
                // or if we just want to be safe.
                if (href.indexOf('questionPaperFromTopic/index.php') !== -1) {
                    link.href = link.href.replace('questionPaperFromTopic/index.php', 'select_class.php');
                }
                if (href.indexOf('mcqs_topic.php') !== -1) {
                    // Be careful not to replace legitimate links to mcqs_topic.php if any exist that SHOULD remain mcqs_topic.php
                    // But based on user request, for School, "Take a Test" should be quiz_setup.php.
                    // If there was a link explicitly to mcqs_topic.php that shouldn't change, this might be an issue.
                    // However, standard nav usually points to quiz_setup.php.
                    // Let's assume we are reverting our own changes.
                    link.href = link.href.replace('mcqs_topic.php', 'quiz_setup.php');
                }
            }
        });
    }
</script>
