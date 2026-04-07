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
        background-color: rgba(15, 23, 42, 0.85); /* Slightly darker, removed blur for performance */
        z-index: 10000;
        display: flex;
        justify-content: center;
        align-items: center;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.2s linear, visibility 0.2s linear;
        will-change: opacity;
    }

    .user-type-overlay.show {
        visibility: visible;
        pointer-events: auto;
        opacity: 1;
    }

    /* The Modal Box */
    .user-type-modal {
        background: #ffffff;
        padding: 2.5rem;
        border-radius: 24px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
        text-align: center;
        max-width: 90%;
        width: 400px;
        transform: translateY(10px);
        opacity: 0;
        transition: transform 0.25s ease-out, opacity 0.25s ease-out;
        border: 1px solid rgba(0, 0, 0, 0.05);
        will-change: transform, opacity;
    }

    .user-type-overlay.show .user-type-modal {
        transform: translateY(0);
        opacity: 1;
    }

    .user-type-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.5rem;
        font-family: inherit;
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
        gap: 0.75rem;
    }

    .type-btn {
        padding: 1.1rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        background: #ffffff;
        color: #1e293b;
        font-weight: 700;
        font-size: 1.1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-family: inherit;
        width: 100%;
        transition: background 0.1s;
    }

    .type-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .type-btn.primary {
        background: #6366f1;
        color: #ffffff;
        border: none;
    }

    .type-btn.primary:hover {
        background: #4f46e5;
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
    let pendingNavigationElement = null;

    document.addEventListener('DOMContentLoaded', function() {
        checkUserType();
        setupLinkInterception();
    });

    function checkUserType() {
        const userType = localStorage.getItem('user_type_preference');
        
        if (userType) {
            console.log('User type already selected:', userType);
            applyUserTypeSettings(userType);
        }
    }

    function setupLinkInterception() {
        document.body.addEventListener('click', function(e) {
            const link = e.target.closest('a[href*="select_class"], a[href*="quiz_setup"], a[href*="mcqs_topic"], a[href*="online-question-paper-generator"], a[href*="online-mcqs-test-for-9th-and-10th-board-exams"], a[href*="topic-wise-mcqs-test"]');
            
            if (link) {
                const userType = localStorage.getItem('user_type_preference');
                if (!userType) {
                    e.preventDefault();
                    pendingNavigationElement = link;
                    
                    const overlay = document.getElementById('userTypeOverlay');
                    requestAnimationFrame(() => {
                        overlay.classList.add('show');
                    });
                }
            }
        });
    }

    function selectUserType(type) {
        // Save the choice in the browser
        localStorage.setItem('user_type_preference', type);
        
        // Visual feedback
        const overlay = document.getElementById('userTypeOverlay');
        overlay.classList.remove('show');
        
        // Apply settings immediately but smoothly
        requestAnimationFrame(() => {
            applyUserTypeSettings(type);
            
            // If they were trying to navigate before the popup interrupted them
            if (pendingNavigationElement) {
                // Wait slightly for href updates to process
                setTimeout(() => {
                    window.location.href = pendingNavigationElement.href;
                }, 50);
            }
        });

        console.log('User type saved:', type);
    }

    function applyUserTypeSettings(type) {
        // Add class to body for CSS styling
        document.body.classList.remove('user-type-school', 'user-type-other');
        document.body.classList.add('user-type-' + type.toLowerCase());

        // Update Navigation Links based on user type - targeting only relevant sections to avoid lag
        updateLinks(type);
    }

    function updateLinks(type) {
        // Only target links in nav and footer to minimize iteration
        // Also target links that explicitly point to our target files
        const links = document.querySelectorAll('nav a, footer a, a[href*="select_class"], a[href*="quiz_setup"], a[href*="mcqs_topic"], a[href*="online-question-paper-generator"], a[href*="online-mcqs-test-for-9th-and-10th-board-exams"], a[href*="topic-wise-mcqs-test"]');
        
        // Batch the updates to avoid layout thrashing
        const updates = [];
        
        links.forEach(link => {
            // SKIP links marked as bypass
            if (link.classList.contains('bypass-user-type')) return;

            const href = link.getAttribute('href');
            if (!href) return;

            const targetGeneratePaper = 'select_class.php';
            const targetQuizSetup = 'quiz_setup.php';

            if (type === 'Other') {
                // Change 'Select Class' to 'Online Question Paper Generator' (AI Generator) and 'Quiz Setup' to 'MCQs Topic'
                if (href.includes('class-9th-and-10th-online-question-paper-generator')) {
                    updates.push(() => { link.href = link.href.replace('class-9th-and-10th-online-question-paper-generator', 'online-question-paper-generator'); });
                } else if (href.includes('select_class.php')) {
                    updates.push(() => { link.href = link.href.replace('select_class.php', 'online-question-paper-generator'); });
                } else if (href.includes('select_class')) {
                    updates.push(() => { link.href = link.href.replace('select_class', 'online-question-paper-generator'); });
                }
                
                if (href.includes('quiz_setup')) {
                    updates.push(() => { link.href = link.href.replace('quiz_setup', 'topic-wise-mcqs-test'); });
                } else if (href.includes('online-mcqs-test-for-9th-and-10th-board-exams')) {
                    updates.push(() => { link.href = link.href.replace('online-mcqs-test-for-9th-and-10th-board-exams', 'topic-wise-mcqs-test'); });
                } else if (href.includes('mcqs_topic')) {
                    updates.push(() => { link.href = link.href.replace('mcqs_topic', 'topic-wise-mcqs-test'); });
                }
            } else if (type === 'School') {
                // Change 'Online Question Paper Generator' (AI Generator) back to 'Select Class' and 'MCQs Topic' back to the Quiz SEO slug
                // BUT carefully handle the class-specific SEO URL
                if (href.endsWith('online-question-paper-generator') && !href.includes('class-9th-and-10th-')) {
                    updates.push(() => { link.href = link.href.replace('online-question-paper-generator', 'class-9th-and-10th-online-question-paper-generator'); });
                } else if (href.includes('select_class.php')) {
                    updates.push(() => { link.href = link.href.replace('select_class.php', 'class-9th-and-10th-online-question-paper-generator'); });
                } else if (href.includes('select_class')) {
                    updates.push(() => { link.href = link.href.replace('select_class', 'class-9th-and-10th-online-question-paper-generator'); });
                } else if (href.includes('mcqs_topic')) {
                    updates.push(() => { link.href = link.href.replace('mcqs_topic', 'online-mcqs-test-for-9th-and-10th-board-exams'); });
                } else if (href.includes('topic-wise-mcqs-test')) {
                    updates.push(() => { link.href = link.href.replace('topic-wise-mcqs-test', 'online-mcqs-test-for-9th-and-10th-board-exams'); });
                }
            }
        });

        // Apply all updates in one frame
        if (updates.length > 0) {
            requestAnimationFrame(() => {
                updates.forEach(update => update());
            });
        }
    }
</script>
