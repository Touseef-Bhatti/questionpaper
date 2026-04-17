<?php
require_once __DIR__ . '/ads.php';
include_once __DIR__ . '/db_connect.php';
// Start session only if not already active and headers are not yet sent.
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        // Headers already sent; avoid starting session to prevent warnings.
        // Consumers of sessions should call session_start() at the top of their scripts
        // if they need session available before output.
    }
}

// Active page detection logic
$current_page = $_SERVER['SCRIPT_NAME'];
if (!function_exists('is_active')) {
    function is_active($page_name) {
        global $current_page;
        // Check if the current page path contains the page name
        // This handles both root-level and subdirectory pages
        return (strpos($current_page, $page_name) !== false) ? 'active' : '';
    }
}

// Special check for Generate Paper dropdown
$gen_paper_pages = ['select_class.php', 'select_book.php', 'select_chapters.php', 'topic-wise-mcqs-test', 'quiz_setup.php', 'online_quiz_host', 'online_quiz_lobby.php', 'quiz.php'];
$is_gen_paper_active = false;
foreach ($gen_paper_pages as $p) {
    if (strpos($current_page, $p) !== false) {
        $is_gen_paper_active = true;
        break;
    }
}

// If only navbar is requested, skip all head logic
if (isset($only_navbar) && $only_navbar) {
    goto navbar_start;
}
?>
<?php if (!isset($skip_shell)): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php endif; ?>

<?php include_once __DIR__ . '/includes/favicons.php'; ?>

    <!-- Google tag (gtag.js) -->
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>

    <!-- Monetag Ads Initialization -->
    <?= renderMonetagScripts() ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'AhmadLearningHub' ?></title>
    <?php if (isset($metaDescription)): ?>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <?php endif; ?>
    <?php if (isset($metaKeywords)): ?>
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
    <?php endif; ?>
    
    <!-- Theme initialization script to prevent flash of unstyled content -->
    <script>
        (function() {
            var isDarkMode = localStorage.getItem('darkMode') === 'enabled';
            var userType = localStorage.getItem('user_type_preference');
            
            if (isDarkMode) {
                document.documentElement.classList.add('dark-mode');
            }
            // Add school-mode indicator to HTML for CSS, but store it as user_type_preference
            if (userType === 'School' || userType === null) {
                document.documentElement.classList.add('school-mode');
            }
        })();
    </script>
    
    <style>
    /* Dark Mode Overrides */
    html.dark-mode {
        --background: #0f172a;
        --surface: #1e293b;
        --text-primary: #f8fafc;
        --text-secondary: #cbd5e1;
        --text-tertiary: #94a3b8;
        --border: #334155;
        --primary-color: #818cf8;
        --primary-dark: #a5b4fc;
        --dark-gray: #f1f5f9;
        --light-gray: #0f172a;
        --white: #1e293b;
        color-scheme: dark;
    }

    html.dark-mode body {
        background-color: var(--background);
        color: var(--text-primary);
    }

    html.dark-mode .navbar {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.8) 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    html.dark-mode .nav-toggle {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.1);
    }

    html.dark-mode .nav-toggle:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    html.dark-mode .nav-menu {
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.98) 0%, rgba(30, 41, 59, 0.95) 100%);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
    }

    html.dark-mode .nav-menu::before {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.15) 0%, rgba(14, 165, 233, 0.15) 100%);
    }

    html.dark-mode .nav-menu li a {
        color: #e2e8f0;
    }

    html.dark-mode .nav-menu li a:hover {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.15) 0%, rgba(14, 165, 233, 0.15) 100%);
        color: #c7d2fe;
        border-color: rgba(79, 70, 229, 0.4);
    }

    html.dark-mode .nav-menu li a.active {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.2) 0%, rgba(14, 165, 233, 0.2) 100%);
        color: #c7d2fe;
        border-color: rgba(79, 70, 229, 0.5);
    }

    html.dark-mode .nav-menu li a i {
        color: #9ca3af;
    }

    html.dark-mode .nav-menu li a:hover i {
        color: #c7d2fe;
    }

    html.dark-mode .nav-toggle span {
        background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    }

    html.dark-mode .dropdown-content {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.95) 100%);
        border-color: rgba(79, 70, 229, 0.2);
        backdrop-filter: blur(15px);
    }

    html.dark-mode .dropdown-content a {
        color: #e2e8f0;
        border-bottom-color: rgba(79, 70, 229, 0.1);
    }

    html.dark-mode .dropdown-content a:hover {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.15) 0%, rgba(14, 165, 233, 0.15) 100%);
        color: #c7d2fe;
        border-left-color: #4f46e5;
    }
    
    html.dark-mode .nav-toggle span {
        background: var(--text-primary);
    }

    html.dark-mode .card, 
    html.dark-mode .profile-content, 
    html.dark-mode .settings-section {
        background-color: var(--surface);
        border-color: var(--border);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
    }

    html.dark-mode h1, 
    html.dark-mode h2, 
    html.dark-mode h3, 
    html.dark-mode h4, 
    html.dark-mode h5, 
    html.dark-mode h6 {
        color: var(--text-primary) !important;
    }

    html.dark-mode p {
        color: var(--text-secondary);
    }

    html.dark-mode .auth-modal-content {
        background: var(--surface);
        border-color: var(--border);
    }

    html.dark-mode .auth-modal-header h2 {
        color: var(--text-primary);
    }
    
    html.dark-mode .close-modal {
        background: var(--border);
        color: var(--text-secondary);
    }
    
    html.dark-mode .close-modal:hover {
        background: rgba(239, 68, 68, 0.2);
        color: #fca5a5;
    }
    
    html.dark-mode .auth-modal-header p {
        color: var(--text-secondary);
    }
    
    html.dark-mode .setting-item label {
        color: var(--text-primary);
    }

    html.dark-mode input.form-control,
    html.dark-mode select.form-control,
    html.dark-mode textarea.form-control {
        background-color: #0f172a;
        color: #f8fafc;
        border-color: #334155;
    }

    /* School Mode Styles */
    html.school-mode .navbar-container {
        /* Subtle visual indicator for school mode */
    }

    /* --- Global Upgrade Plan Modal Styles --- */
    .upgrade-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.9);
        backdrop-filter: blur(12px);
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        animation: fadeInGlobal 0.3s ease;
    }

    .upgrade-modal-card {
        background: white;
        padding: 32px 40px;
        border-radius: 32px;
        max-width: 500px;
        width: 90%;
        text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.1);
        position: relative;
        overflow: hidden;
        animation: modalSlideUpGlobal 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    @keyframes modalSlideUpGlobal {
        from { transform: translateY(50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    @keyframes fadeInGlobal {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .upgrade-modal-icon {
        width: 64px;
        height: 64px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin: 0 auto 16px;
        box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3);
    }

    .upgrade-modal-title {
        font-size: 1.75rem;
        font-weight: 800;
        color: #1e293b !important;
        margin-bottom: 12px;
        letter-spacing: -0.025em;
    }

    .upgrade-modal-text {
        color: #64748b;
        font-size: 1rem;
        line-height: 1.5;
        margin-bottom: 20px;
    }

    .upgrade-features-list {
        text-align: left;
        background: #f8fafc;
        padding: 16px 20px;
        border-radius: 20px;
        margin-bottom: 24px;
    }

    .upgrade-feature-item {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        color: #334155;
        font-weight: 600;
    }

    .upgrade-feature-item i {
        color: #10b981;
        font-size: 1.2rem;
    }

    .upgrade-modal-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .btn-upgrade-now {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        color: white !important;
        padding: 14px;
        border-radius: 16px;
        font-weight: 800;
        font-size: 1.1rem;
        text-decoration: none !important;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.3s;
    }

    .btn-upgrade-now:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
    }

    .btn-maybe-later {
        background: transparent;
        color: #64748b;
        border: none;
        padding: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: color 0.2s;
    }

    .btn-maybe-later:hover {
        color: #1e293b;
    }

    /* Header / Navbar styles appended below */
/* header.css - Responsive, professional navbar (uses design system variables) */

.navbar {
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    color: var(--text-primary);
    padding: 0.7rem 0;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
    position: fixed;
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
    width: 100%;
    margin: 0;
    min-height: 70px;
    display: flex;
    align-items: center;
}

html, body {
    max-width: 100%;
    overflow-x: hidden;
}

.navbar-container {
    min-width: 100%;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    padding: 0 1.25rem;
}

.nav-logo {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--primary);
    text-decoration: none;
    letter-spacing: 0.6px;
    transition: color 0.25s;
}

.nav-menu {
    list-style: none;
    display: flex;
    gap: 1.9rem; /* Slightly increased gap */
    margin: 0;
    padding: 0;
    margin-top: 14px;

    transition: transform 0.4s cubic-bezier(.68,-0.55,.27,1.55), opacity 0.3s;
}

.nav-menu li {
    /* No animation */
}

.nav-menu li a {
    color: var(--text-primary);
    text-decoration: none;
    font-size: 1.05rem;
    font-weight: 500;
    transition: color 0.2s, border-bottom 0.2s, background 0.2s, box-shadow 0.2s, transform 0.2s;
    padding: 8px 14px;
    border-bottom: 3px solid transparent;
    border-radius: 6px;
    position: relative;
}

.nav-menu li a:hover, 
.nav-menu li a.active,
.nav-menu li.dropdown .dropbtn.active {
    color: var(--primary-dark);
    border-bottom: 3px solid var(--primary-dark);
    background: rgba(15, 23, 42, 0.04);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
    transform: translateY(-2px);
}

/* Ensure btn-join active state maintains its premium look */
.nav-menu li a.btn-join.active {
    background: var(--gradient-primary, linear-gradient(135deg, #4F46E5 0%, #0EA5E9 100%));
    color: #fff !important;
    border-bottom: none;
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.35);
    opacity: 1;
}

.nav-menu li a.btn-join {
    background: var(--gradient-primary, linear-gradient(135deg, #4F46E5 0%, #0EA5E9 100%));
    color: #fff !important;
    border: none;
    padding: 0.6rem 1.5rem; /* Polished padding */
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.95rem; /* Match visual weight of other links */
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-bottom: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    top: -4px;
}

.nav-menu li a.btn-join i {
    font-size: 0.9em; /* Icon slightly smaller */
}

.nav-menu li a.btn-join:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.35);
    opacity: 0.95;
    background-size: 110%; /* Subtle texture shift if possible */
}

/* ── Quota Badge ─ Dynamic Quota Status ── */
.quota-badge {
    background: rgba(15, 23, 42, 0.05);
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 30px;
    padding: 6px 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-primary);
    transition: all 0.3s ease;
}

html.dark-mode .quota-badge {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.quota-badge i {
    color: var(--primary);
    font-size: 0.9rem;
}

.quota-badge:hover {
    background: rgba(15, 23, 42, 0.08);
    transform: translateY(-1px);
}

/* ── Plan Status Chip ─ Vibrant & Professional ── */
.plan-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 14px 6px 9px;
    border-radius: 50px;
    font-size: 0.76rem;
    font-weight: 700;
    text-decoration: none;
    letter-spacing: 0.4px;
    white-space: nowrap;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

/* Shimmer sweep on hover */
.plan-chip::before {
    content: '';
    position: absolute;
    top: 0; left: -75%;
    width: 50%; height: 100%;
    background: linear-gradient(120deg, transparent, rgba(255,255,255,0.28), transparent);
    transform: skewX(-20deg);
    transition: left 0.55s ease;
    pointer-events: none;
}
.plan-chip:hover::before { left: 130%; }

/* ─ PREMIUM ─ Royal Indigo-to-Violet gradient */
.plan-chip.premium {
    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
    color: #fff !important;
    border: none;
    box-shadow: 0 3px 12px rgba(79,70,229,0.4), 0 1px 3px rgba(0,0,0,0.12);
}
.plan-chip.premium:hover {
    background: linear-gradient(135deg, #4338CA 0%, #6D28D9 100%);
    box-shadow: 0 6px 20px rgba(109,40,217,0.5), 0 2px 6px rgba(0,0,0,0.15);
    transform: translateY(-2px) scale(1.02);
}

/* ─ BASIC ─ Warm Amber-Gold gradient */
.plan-chip.basic {
    background: linear-gradient(135deg, #F59E0B 0%, #EF4444 100%);
    color: #fff !important;
    border: none;
    box-shadow: 0 3px 12px rgba(245,158,11,0.35), 0 1px 3px rgba(0,0,0,0.1);
}
.plan-chip.basic:hover {
    background: linear-gradient(135deg, #D97706 0%, #DC2626 100%);
    box-shadow: 0 6px 20px rgba(239,68,68,0.4), 0 2px 6px rgba(0,0,0,0.12);
    transform: translateY(-2px) scale(1.02);
}

/* ─ Dot indicator ─ */
.plan-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
    display: inline-block;
}
.plan-chip.premium .plan-dot {
    background: #c4b5fd;
    box-shadow: 0 0 0 0 rgba(196,181,253,0.8);
    animation: plan-pulse 2s infinite;
}
.plan-chip.basic .plan-dot {
    background: #fde68a;
    box-shadow: 0 0 0 0 rgba(253,230,138,0.8);
    animation: plan-pulse-warm 2s infinite;
}

@keyframes plan-pulse {
    0%   { box-shadow: 0 0 0 0   rgba(196,181,253,0.8); }
    65%  { box-shadow: 0 0 0 6px rgba(196,181,253,0);   }
    100% { box-shadow: 0 0 0 0   rgba(196,181,253,0);   }
}

@keyframes plan-pulse-warm {
    0%   { box-shadow: 0 0 0 0   rgba(253,230,138,0.8); }
    65%  { box-shadow: 0 0 0 6px rgba(253,230,138,0);   }
    100% { box-shadow: 0 0 0 0   rgba(253,230,138,0);   }
}

/* Mobile: ensure plan label is visible */
@media (max-width: 768px) {
    .plan-chip .plan-label { display: inline-block; }
    .plan-chip { padding: 6px 14px 6px 9px; border-radius: 50px; gap: 7px; }
    .plan-chip .plan-dot { width: 7px; height: 7px; }
}

/* Hamburger Icon */
.nav-toggle {
    display: none; /* Hidden by default on desktop */
    flex-direction: column;
    justify-content: center;
    width: 44px;
    height: 44px;
    cursor: pointer;
    z-index: 1100;
    margin-left: 1rem;
    touch-action: manipulation;
}

.nav-toggle span {
    height: 4px;
    width: 28px;
    background: var(--text-primary);
    margin: 4px 0;
    border-radius: 2px;
    display: block;
    transition: all 0.3s cubic-bezier(.68,-0.55,.27,1.55);
}

.nav-toggle.open span:nth-child(1) {
    transform: translateY(8px) rotate(45deg);
}

.nav-toggle.open span:nth-child(2) {
    opacity: 0;
}

.nav-toggle.open span:nth-child(3) {
    transform: translateY(-8px) rotate(-45deg);
}

/* Smooth scroll behavior when mobile menu is open */
body.menu-open {
    overflow: hidden;
    height: 100vh;
}

/* Responsive Adjustments */
@media (max-width: 1300px) {
    .nav-menu { gap: 1.2rem; }
    .nav-menu li a { font-size: 0.95rem; }
}

@media (max-width: 1150px) {
    .nav-menu { gap: 0.8rem; }
    .nav-logo { font-size: 1.2rem; }
}

@media (max-width: 1024px) {
    .navbar-container {
        width: 100%;
        padding: 0 1rem;
    }
}

@media (max-width: 768px) {
    
    .navbar {
        width: calc(100% - 1rem);
        max-width: none;
        border-radius: 80px;
        margin: 0 auto;
        padding: 0.8rem 0;
        min-height: 70px;
        -webkit-backdrop-filter: blur(20px);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08);
        border-bottom: 1px solid rgba(255, 255, 255, 0.3);
    }

    .navbar-container {
        width: 100%;
        padding: 0 1rem;
        flex-direction: row;
        align-items: center;
        gap: 0.5rem;
    }

    .nav-logo {
        font-size: clamp(0.95rem, 3.6vw, 1.1rem);
        font-weight: 700;
        z-index: 1100;
        order: 2;
        margin-left: 0.5rem;
        margin-right: auto;
        max-width: calc(100% - 64px);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        letter-spacing: 0.5px;
        color: var(--primary);
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .nav-toggle {
        order: 1;
        margin-left: 0;
        margin-right: 0;
        padding: 0.5rem;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
    }

    .nav-toggle:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }

    .nav-menu {
        position: fixed;
        top: 0;
        left: 0;
        margin-left: 0;
        height: 100vh;
        height: 100dvh;
        width: min(86vw, 360px);
        max-width: calc(100vw - 0.75rem);
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%);
        backdrop-filter: blur(25px);
        -webkit-backdrop-filter: blur(25px);
        border-right: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 0 24px 24px 0;
        color: #1e293b;
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
        padding: calc(5.5rem + env(safe-area-inset-top, 0px)) 1rem calc(1.5rem + env(safe-area-inset-bottom, 0px)) 1rem;
        box-shadow: 12px 0 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.1);
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        z-index: 1050;
        visibility: hidden;
        opacity: 0;
        transform: translateX(calc(-100% - 24px));
        order: 3;
    }

    .nav-menu::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 70px;
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(14, 165, 233, 0.1) 100%);
        border-radius: 0 24px 0 0;
        z-index: -1;
    }
    
    .nav-menu.open {
        visibility: visible;
        opacity: 1;
        transform: translateX(0);
        display: flex;
        box-shadow: 12px 0 40px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.2);
    }

    .nav-menu li {
        opacity: 0;
        transform: translateX(-30px) translateY(10px);
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        width: 100%;
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 0.25rem;
    }

    .nav-menu.open li {
        opacity: 1;
        transform: translateX(0) translateY(0);
        transition-delay: var(--delay);
    }

    .nav-menu.open li:nth-child(1) { --delay: 0.1s; }
    .nav-menu.open li:nth-child(2) { --delay: 0.15s; }
    .nav-menu.open li:nth-child(3) { --delay: 0.2s; }
    .nav-menu.open li:nth-child(4) { --delay: 0.25s; }
    .nav-menu.open li:nth-child(5) { --delay: 0.3s; }
    .nav-menu.open li:nth-child(6) { --delay: 0.35s; }
    .nav-menu.open li:nth-child(7) { --delay: 0.4s; }
    .nav-menu.open li:nth-child(8) { --delay: 0.45s; }

    .nav-menu li a {
        display: flex;
        align-items: center;
        width: 100%;
        padding: 0.9rem 1rem;
        min-height: 46px;
        border-radius: 16px;
        border: none;
        background: transparent;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-size: clamp(0.96rem, 3.8vw, 1.06rem);
        font-weight: 500;
        color: #374151;
        text-decoration: none;
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(10px);
    }

    .nav-menu li a::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(79, 70, 229, 0.1), transparent);
        transition: left 0.5s ease;
    }

    .nav-menu li a:hover {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(14, 165, 233, 0.08) 100%);
        color: #1e40af;
        transform: translateX(4px) scale(1.02);
        box-shadow: 0 4px 20px rgba(79, 70, 229, 0.15);
        border: 1px solid rgba(79, 70, 229, 0.2);
    }

    .nav-menu li a:hover::before {
        left: 100%;
    }

    .nav-menu li a.active {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.15) 0%, rgba(14, 165, 233, 0.15) 100%);
        color: #1e40af;
        font-weight: 600;
        box-shadow: 0 2px 12px rgba(79, 70, 229, 0.2);
        border: 1px solid rgba(79, 70, 229, 0.3);
    }

    .nav-menu li a i {
        margin-right: 1rem;
        font-size: 1.2rem;
        color: #6b7280;
        transition: color 0.3s ease;
    }

    .nav-menu li a:hover i {
        color: #1e40af;
    }

    .nav-toggle {
        display: flex;
        z-index: 1100;
    }

    .nav-toggle span {
        transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        background: linear-gradient(135deg, #374151 0%, #6b7280 100%);
        border-radius: 2px;
    }

    /* Mobile Join Button - Premium Styling */
    .nav-menu li a.btn-join {
        background: linear-gradient(135deg, #4f46e5 0%, #0ea5e9 100%);
        color: white !important;
        text-align: center;
        width: 100%;
        justify-content: center;
        margin-top: 1rem;
        margin-bottom: 0.5rem;
        box-shadow: 0 4px 20px rgba(79, 70, 229, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.2);
        font-weight: 600;
        font-size: clamp(1rem, 4vw, 1.08rem);
        letter-spacing: 0.5px;
        position: relative;
        overflow: hidden;
    }

    .nav-menu li a.btn-join::before {
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    }

    .nav-menu li a.btn-join:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 8px 30px rgba(79, 70, 229, 0.4);
        background: linear-gradient(135deg, #4338ca 0%, #0284c7 100%);
    }

    .nav-menu li a.btn-join i {
        color: white;
        margin-right: 0.5rem;
    }
}


@media (max-width: 480px) {
    .navbar {
        padding: 0.6rem 0;
        min-height: 65px;
    }

    .navbar-container {
        width: 100%;
        padding: 0 0.75rem;
    }

    .nav-logo {
        font-size: 1rem;
    }

    .nav-toggle {
        padding: 0.4rem;
    }

    .nav-menu {
        width: min(92vw, 320px);
        border-radius: 0 20px 20px 0;
        padding: calc(5rem + env(safe-area-inset-top, 0px)) 0.85rem calc(1.4rem + env(safe-area-inset-bottom, 0px)) 0.85rem;
    }

    .nav-menu::before {
        border-radius: 0 20px 0 0;
    }

    .nav-menu li a {
        padding: 0.85rem 0.95rem;
        font-size: 0.96rem;
    }

    .nav-menu li a i {
        font-size: 1.1rem;
        margin-right: 0.8rem;
    }
}

/* Dropdown hover for desktop */
@media (min-width: 769px) {
    .dropdown:hover .dropdown-content {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }
    .profile-dropdown .dropdown-content {
        left: auto;
        right: 0;
    }
}
/* Dropdown Styles */
.dropdown {
    position: relative;
    /* display: inline-block; */ /* Removed to allow full width in mobile */
}

.dropdown-content {
    display: none;
    position: absolute;
    background-color: rgba(255, 255, 255, 0.9); /* Semi-transparent white for dropdown */
    min-width: 200px; /* Wider dropdown */
    box-shadow: 0px 8px 20px 0px rgba(0,0,0,0.5); /* Stronger, more diffused shadow */
    z-index: 1;
    border-radius: 8px; /* Rounded corners */
    overflow: hidden; /* Ensures rounded corners apply to children */
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.3s ease, transform 0.3s ease;
    top: calc(100% + -10px); /* Position below the parent link */
    left: 0;
    border: 1px solid rgba(0, 0, 0, 0.1); /* Subtle black border */
}

.dropdown-content a {
    color: #000; /* Black text for dropdown items */
    padding: 12px 18px; /* Increased padding */
    text-decoration: none;
    display: block;
    text-align: left;
    transition: background-color 0.2s, color 0.2s, transform 0.2s;
    font-size: 1rem; /* Slightly larger font for dropdown items */
    border-bottom: 1px solid rgba(255, 255, 255, 0.05); /* Separator line */
}

.dropdown-content a:last-child {
    border-bottom: none; /* No border for the last item */
}

.dropdown-content a:hover {
    background-color: rgba(0, 0, 0, 0.05); /* Subtle background on hover */
    color: #333; /* Darker hover color */
    transform: translateX(8px); /* More pronounced slide effect on hover */
}

.dropdown-content.show {
    display: block;
    opacity: 1;
    transform: translateY(0);
}

/* Adjust dropdown for mobile */
@media (max-width: 768px) {
    .dropdown {
        width: 100%;
        margin-bottom: 0.5rem;
    }

    .dropdown-content {
        position: relative;
        background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(241, 245, 249, 0.95) 100%);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(79, 70, 229, 0.1);
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        min-width: unset;
        width: calc(100% - 1rem);
        margin-left: 1rem;
        margin-right: 0.5rem;
        padding: 0.5rem 0;
        top: 0;
        transform: translateY(0);
        overflow: hidden;
    }

    .dropdown-content a {
        padding: 0.8rem 1rem;
        min-height: 44px;
        font-size: 1rem;
        font-weight: 500;
        color: #4b5563;
        border-bottom: 1px solid rgba(79, 70, 229, 0.08);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }

    .dropdown-content a:last-child {
        border-bottom: none;
    }

    .dropdown-content a:hover {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(14, 165, 233, 0.08) 100%);
        color: #1e40af;
        transform: translateX(4px);
        border-left: 3px solid #4f46e5;
        padding-left: 1.5rem;
    }

    .dropdown-content a i {
        margin-right: 0.8rem;
        color: #6b7280;
        transition: color 0.3s ease;
    }

    .dropdown-content a:hover i {
        color: #1e40af;
    }
}


.user-header-info {
    display: flex;
    flex-direction: column;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.05) 0%, rgba(14, 165, 233, 0.05) 100%);
    border-radius: 12px;
    margin: 0.5rem 0;
    padding: 1rem;
    border: 1px solid rgba(79, 70, 229, 0.1);
    backdrop-filter: blur(10px);
}

.user-header-info .fw-bold {
    color: #1e40af !important;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
}

.user-header-info .text-muted {
    color: #6b7280 !important;
    font-size: 0.9rem;
    font-weight: 500;
}

html.dark-mode .user-header-info {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(14, 165, 233, 0.1) 100%);
    border-color: rgba(79, 70, 229, 0.3);
}

html.dark-mode .user-header-info .fw-bold {
    color: #c7d2fe !important;
}

html.dark-mode .user-header-info .text-muted {
    color: #9ca3af !important;
}

/* Nav Overlay for mobile */
.nav-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5); /* Semi-transparent black */
    z-index: 1040; /* Below nav-menu but above content */
    opacity: 0; /* Hidden by default */
    pointer-events: none; /* Not clickable when hidden */
    transition: opacity 0.05s ease-out; /* Faster fade out */
}

.nav-overlay.open {
    opacity: 1;
    pointer-events: auto; /* Clickable when open */
    transition: opacity 0.3s ease-in; /* Slower fade in */
}
/* Auth Modal Styles */
.auth-modal {
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(15, 23, 42, 0.85); /* Slightly darker, removed blur for performance */
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.2s linear, visibility 0.2s linear;
    will-change: opacity;
}

.auth-modal.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.auth-modal-content {
    background: #ffffff;
    padding: 2.5rem;
    border-radius: 24px;
    width: 90%;
    max-width: 450px;
    text-align: center;
    position: relative;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
    transform: translateY(10px);
    transition: transform 0.25s ease-out, opacity 0.25s ease-out;
    border: 1px solid rgba(0, 0, 0, 0.05);
    will-change: transform, opacity;
}

.auth-modal.show .auth-modal-content {
    transform: translateY(0);
}

.close-modal {
    position: absolute;
    right: 16px;
    top: 16px;
    font-size: 22px;
    font-weight: bold;
    color: #64748b;
    background: #f1f5f9;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.close-modal:hover {
    color: #ef4444;
    background: #fee2e2;
    transform: rotate(90deg) scale(1.1);
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2);
}

.auth-modal-header h2 {
    font-size: 1.8rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
    font-weight: 800;
}

.auth-modal-header p {
    color: #64748b;
    font-size: 1rem;
    margin-bottom: 2rem;
}

.auth-options {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.btn-auth-login, .btn-auth-register {
    padding: 0.8rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    font-size: 1rem;
    transition: background 0.1s;
    display: block;
}

.btn-auth-login {
    background: #6366f1;
    color: white;
}

.btn-auth-login:hover {
    background: #4f46e5;
}

.btn-auth-register {
    background: #f1f5f9;
    color: #1e293b;
    border: 1px solid #e2e8f0;
}

.btn-auth-register:hover {
        background: #e2e8f0;
    }

    .auth-modal-footer {
        margin-top: 2rem;
        color: #94a3b8;
    }

    .mobile-only-nav {
        display: none;
    }

    @media (max-width: 768px) {
        .mobile-only-nav {
            display: block;
        }
    }
   </style>

    <?php if (isset($extraHead)) echo $extraHead; ?>

<?php if (isset($only_head) && $only_head): ?>
<?php if (!isset($skip_shell)): ?>
</head>
<?php endif; ?>
<?php return; // Stop here if only head was requested ?>
<?php endif; ?>

<?php if (!isset($skip_shell) && !isset($only_navbar)): ?>
</head>
<body>
<?php endif; ?>

<?php 
navbar_start: 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">


<nav class="navbar">
  <div class="navbar-container">
    <a href="<?= $assetBase ?>index.php" class="nav-logo">AhmadLearningHub</a>
    <div class="nav-toggle" id="navToggle" role="button" tabindex="0" aria-label="Toggle navigation menu" aria-controls="navMenu" aria-expanded="false">
      <span></span>
      <span></span>
      <span></span>
    </div>
    <ul class="nav-menu" id="navMenu">
      <li><a href="<?= $assetBase ?>index" class="<?= (basename($current_page) == 'index.php' || basename($current_page) == '') ? 'active' : '' ?>"><i class="fas fa-home"></i> Home</a></li>
      <li class="dropdown">
        <a class="dropbtn <?= $is_gen_paper_active ? 'active' : '' ?>">Generate Paper <i class="fas fa-caret-down"></i></a>
        <div class="dropdown-content">
             <a href="<?= $assetBase ?>class-9th-and-10th-online-question-paper-generator" class="<?= is_active('select_class.php') ?>"><i class="fas fa-file-alt"></i> Create Question Paper</a>
          <a href="<?= $assetBase ?>online_quiz_host_new" class="<?= is_active('online_quiz_host_new.php') ?>"><i class="fas fa-file-alt"></i> Host Online Quiz</a>
          <a href="<?= $assetBase ?>online-mcqs-test-for-9th-and-10th-board-exams" class="<?= is_active('quiz_setup.php') ?>"><i class="fas fa-question-circle"></i> MCQs Quiz</a>
          <a href="<?= $assetBase ?>online_quiz_join" class="<?= is_active('online_quiz_join.php') ?>"><i class="fas fa-gamepad"></i> Join Quiz</a>
        </div>
      </li>
      <li><a href="<?= $assetBase ?>note" class="<?= is_active('note.php') ?>"><i class="fas fa-book"></i> Notes</a></li>
      <li><a href="<?= $assetBase ?>online_quiz_join" class="btn-join"><i class="fas fa-gamepad" ></i> Join</a></li>
      <li class="dropdown">
        <a class="dropbtn <?= is_active('about.php') || is_active('reviews.php') ? 'active' : '' ?>"><i class="fas fa-info-circle"></i> About <i class="fas fa-caret-down"></i></a>
        <div class="dropdown-content">
          <a href="<?= $assetBase ?>about" class="<?= is_active('about.php') ?>"><i class="fas fa-info-circle"></i> About Us</a>
          <a href="<?= $assetBase ?>reviews" class="<?= is_active('reviews.php') ?>"><i class="fas fa-star"></i> Reviews</a>
        </div>
      </li>
      <!-- <li><a href="<?= $assetBase ?>contact.php" class="<?= is_active('contact.php') ?>"><i class="fas fa-envelope"></i> Contact</a></li> -->

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                    // Include subscription middleware for header info
                    if (file_exists(__DIR__ . '/middleware/SubscriptionCheck.php')) {
                        require_once __DIR__ . '/middleware/SubscriptionCheck.php';
                        $subInfo = getSubscriptionInfo($_SESSION['user_id']);
                    }
                    ?>
                    <?php if (isset($subInfo) && $subInfo): ?>
                        <li>
                            <a href="<?= $subInfo['is_premium'] ? ($assetBase . 'subscription') : 'javascript:void(0)' ?>"
                               onclick="<?= $subInfo['is_premium'] ? '' : 'showGlobalUpgradeModal(\'general\')' ?>"
                               class="plan-chip  <?= $subInfo['is_premium'] ? 'premium' : 'basic' ?>"
                               title="<?= htmlspecialchars($subInfo['plan_name']) ?> Plan" style="border-radius: 50px;">
                                <span class="plan-dot"></span>
                                <span class="plan-label"><?= htmlspecialchars($subInfo['plan_name']) ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="dropdown profile-dropdown">
                        <a href="<?= $assetBase ?>profile" class="dropbtn <?= is_active('profile.php') ?>"><i class="fas fa-user-circle"></i> Profile <i class="fas fa-caret-down"></i></a>
                        <div class="dropdown-content">
                            <div class="user-header-info px-3 py-2 border-bottom mb-1">
                                <div class="fw-bold text-dark small"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></div>
                                <div class="text-muted small" style="font-size: 0.75rem;"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
                            </div>
                            <a href="<?= $assetBase ?>profile" class="<?= is_active('profile.php') ?>"><i class="fas fa-user-cog"></i> My Profile</a>
                            <a href="#" id="headerModeSwitchBtn"><i class="fas fa-exchange-alt"></i> Switch Mode</a>
                            <a href="<?= $assetBase ?>settings" class="<?= is_active('settings.php') ?>"><i class="fas fa-cog"></i> Settings</a>
                            <a href="<?= $assetBase ?>logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </li>
                <?php else: ?>
                    <!-- <li><a href="<?= $assetBase ?>subscription.php">Plans</a></li> -->
                    
                    <li><a href="<?= $assetBase ?>login" class="<?= is_active('login.php') ?>"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <?php endif; ?>
                <li class="mobile-only-nav"><a href="<?= $assetBase ?>settings" class="<?= is_active('settings.php') ?>"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </div>
        <div class="nav-overlay" id="navOverlay"></div>
    </nav>

<!-- Auth Modal -->
<div id="authModal" class="auth-modal">
    <div class="auth-modal-content">
        <span class="close-modal" id="closeAuthModal">&times;</span>
        <div class="auth-modal-header">
            <h2 id="modalTitle">Welcome to AhmadLearningHub</h2>
            <p id="modalSubtitle">Please login or create an account to access premium features.</p>
        </div>
        <div class="auth-modal-body">
            <div class="auth-options">
                <a href="<?= $assetBase ?>login" class="btn-auth-login">Login to My Account</a>
                <a href="<?= $assetBase ?>register" class="btn-auth-register">Create New Account</a>
            </div>
            <div class="auth-modal-footer">
                <p>Unlock smart paper generation, online quizzes, and expert notes!</p>
            </div>
        </div>
    </div>
</div>

<!-- Global Upgrade Plan Modal -->
<div id="globalUpgradeModal" class="upgrade-modal-overlay">
    <div class="upgrade-modal-card">
        <div class="upgrade-modal-icon">
            <i class="fas fa-crown"></i>
        </div>
        <h2 class="upgrade-modal-title" id="globalUpgradeTitle">Unlock Full Access</h2>
        <p class="upgrade-modal-text" id="globalUpgradeText">Upgrade to a premium plan to unlock unlimited features and removal of all ads.</p>
        
        <div class="upgrade-features-list">
            <div class="upgrade-feature-item">
                <i class="fas fa-check-circle"></i>
                <span>Unlimited paper generation</span>
            </div>
            <div class="upgrade-feature-item">
                <i class="fas fa-check-circle"></i>
                <span>No platform-wide advertisements</span>
            </div>
            <div class="upgrade-feature-item">
                <i class="fas fa-check-circle"></i>
                <span>Advanced AI paper builder</span>
            </div>
        </div>
        
        <div class="upgrade-modal-actions">
            <a href="<?= $assetBase ?>subscription" class="btn-upgrade-now">
                <i class="fas fa-rocket"></i> Upgrade Now
            </a>
            <button type="button" class="btn-maybe-later" onclick="closeGlobalUpgradeModal()">
                Maybe Later
            </button>
        </div>
    </div>
</div>

<script>
    function showGlobalUpgradeModal(type = 'general') {
        const modal = document.getElementById('globalUpgradeModal');
        const title = document.getElementById('globalUpgradeTitle');
        const text = document.getElementById('globalUpgradeText');
        
        if (type === 'topics') {
            title.textContent = 'Unlock Unlimited Topics';
            text.innerHTML = 'Free users have a limit on topic selection. Upgrade to select <strong>unlimited topics</strong> per assessment.';
        } else if (type === 'questions') {
            title.textContent = 'Maximum Questions Reached';
            text.innerHTML = 'Free users are limited to 10 MCQs, 10 Shorts, and 3 Long questions. <strong>Upgrade for unlimited counts.</strong>';
        } else {
            title.textContent = 'Unlock Premium Features';
            text.textContent = 'Experience the full power of Ahmad Learning Hub with unlimited paper generation and ad-free browsing.';
        }

        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeGlobalUpgradeModal() {
        const modal = document.getElementById('globalUpgradeModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    // Close on click outside
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('globalUpgradeModal');
        if (e.target === modal) {
            closeGlobalUpgradeModal();
        }
    });
</script>

    <script>
    // Responsive Navbar Toggle
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    const navOverlay = document.getElementById('navOverlay');
    const isMobileViewport = () => window.matchMedia('(max-width: 768px)').matches;
    
    function closeMenu() {
        if (!navMenu || !navToggle || !navOverlay) return;
        navMenu.classList.remove('open');
        navToggle.classList.remove('open');
        navOverlay.classList.remove('open');
        document.body.classList.remove('menu-open');
        navToggle.setAttribute('aria-expanded', 'false');
        // Close all dropdowns when mobile menu is closed
        document.querySelectorAll('.dropdown-content').forEach(content => {
            content.classList.remove('show');
        });
    }
    
    function openMenu() {
        if (!navMenu || !navToggle || !navOverlay) return;
        navMenu.classList.add('open');
        navToggle.classList.add('open');
        navOverlay.classList.add('open');
        document.body.classList.add('menu-open');
        navToggle.setAttribute('aria-expanded', 'true');
    }
    
    if (navToggle) {
        navToggle.addEventListener('click', function() {
            if (navMenu.classList.contains('open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });
        navToggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (navMenu.classList.contains('open')) {
                    closeMenu();
                } else {
                    openMenu();
                }
            }
        });
    }
    
    if (navOverlay) {
        navOverlay.addEventListener('click', closeMenu);
    }

    // Close the mobile menu when clicking outside the menu area (sides of the page)
    document.addEventListener('click', function(e) {
        // Only active when menu open and on small screens
        if (!navMenu.classList.contains('open') || !isMobileViewport()) return;

        // If click inside the menu or on the toggle, ignore
        if (navMenu.contains(e.target) || (navToggle && navToggle.contains(e.target))) return;

        closeMenu();
    }, true);
    
    // Close on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMenu();
    });
    
    // Close on menu link click (mobile)
    if (navMenu) {
        navMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                // Only close if it's not a dropdown toggle
                if (!link.classList.contains('dropbtn') && isMobileViewport()) {
                    closeMenu();
                }
            });
        });
    }

    // Dropdown functionality (desktop hover, mobile click)
    document.querySelectorAll('.dropdown > .dropbtn').forEach(dropbtn => {
        dropbtn.addEventListener('click', function(e) {
            if (isMobileViewport()) { // Only for mobile
                e.preventDefault();
                e.stopPropagation(); // Prevent closing the mobile menu if it's open
                const dropdownContent = this.nextElementSibling;
                
                // Close other open dropdowns
                document.querySelectorAll('.dropdown-content.show').forEach(content => {
                    if (content !== dropdownContent) {
                        content.classList.remove('show');
                    }
                });
                
                dropdownContent.classList.toggle('show');
            }
        });
    });

    // Close dropdowns when clicking outside (only for mobile click dropdowns)
    window.addEventListener('click', function(e) {
        if (isMobileViewport() && !e.target.matches('.dropbtn')) {
            document.querySelectorAll('.dropdown-content.show').forEach(content => {
                content.classList.remove('show');
            });
        }
    });

    window.addEventListener('resize', function() {
        if (!isMobileViewport()) {
            closeMenu();
        }
    });

    // Auth Modal Logic
    const authModal = document.getElementById('authModal');
    const closeAuthBtn = document.getElementById('closeAuthModal');
    const isUserLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;

    function showAuthModal() {
        if (!authModal) return;
        
        // Check if previously logged in
        const wasLoggedIn = localStorage.getItem('alh_was_logged_in');
        if (wasLoggedIn === 'true') {
            document.getElementById('modalTitle').textContent = 'Welcome Back!';
            document.getElementById('modalSubtitle').textContent = 'Your session has expired. Please login again to continue.';
        }

        requestAnimationFrame(() => {
            authModal.classList.add('show');
        });
    }

    function hideAuthModal() {
        if (!authModal) return;
        authModal.classList.remove('show');
        // Mark as seen for this session
        sessionStorage.setItem('alh_auth_modal_seen', 'true');
    }

    if (closeAuthBtn) {
        closeAuthBtn.addEventListener('click', hideAuthModal);
    }

    // Auto-show logic
    window.addEventListener('load', function() {
        const path = window.location.pathname;
        const isAuthPage = path.includes('login.php') || path.includes('register.php') || path.includes('forgot_password.php') || path.includes('reset_password.php');
        
        if (!isUserLoggedIn && !isAuthPage) {
            const hasSeenRecently = sessionStorage.getItem('alh_auth_modal_seen');
            if (!hasSeenRecently) {
                // Mark as seen for this session immediately (this prevents it from showing on refresh or other pages)
                sessionStorage.setItem('alh_auth_modal_seen', 'true');
                
                // Show modal after a short delay
                setTimeout(showAuthModal, 20000);
            }
        } else if (isUserLoggedIn) {
            // Store login status for future reference if they are currently logged in
            localStorage.setItem('alh_was_logged_in', 'true');
            localStorage.setItem('alh_last_active', Date.now());
        }
    });

    // Close on click outside
    window.addEventListener('click', function(e) {
        if (e.target === authModal) {
            hideAuthModal();
        }
    });

    // Mode Selectors Logic
    function updateModeUI() {
        const userType = localStorage.getItem('user_type_preference') || 'School';
        const isSchoolMode = userType === 'School';
        
        // Update document classes based on mode natively
        if (isSchoolMode) {
             document.documentElement.classList.add('school-mode');
        } else {
             document.documentElement.classList.remove('school-mode');
        }

        // Update header dynamic mode button text/icon
        const headerModeBtn = document.getElementById('headerModeSwitchBtn');
        if (headerModeBtn) {
            if (isSchoolMode) {
                headerModeBtn.innerHTML = '<i class="fas fa-laptop-code"></i>  Advance Mode';
            } else {
                headerModeBtn.innerHTML = '<i class="fas fa-school"></i> School Mode';
            }
        }
        
        // Update settings page buttons if it exists
        const btnSchool = document.getElementById('btnSchoolMode');
        const btnAdvance = document.getElementById('btnAdvanceMode');
        if (btnSchool && btnAdvance) {
            if (isSchoolMode) {
                btnSchool.classList.add('active');
                btnAdvance.classList.remove('active');
            } else {
                btnSchool.classList.remove('active');
                btnAdvance.classList.add('active');
            }
        }
    }

    const headerModeSwitchBtn = document.getElementById('headerModeSwitchBtn');
    if (headerModeSwitchBtn) {
        headerModeSwitchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.innerWidth > 768) {
                e.stopPropagation(); // On desktop, keep dropdown open to see change
            }
            const currentUserType = localStorage.getItem('user_type_preference') || 'School';
            const newMode = (currentUserType === 'School') ? 'Other' : 'School';
            
            if (typeof selectUserType === 'function') {
                selectUserType(newMode);
            } else {
                localStorage.setItem('user_type_preference', newMode);
            }
            updateModeUI();
        });
    }
    
    // Listen for custom external updates if they happen (e.g. from popup window)
    window.addEventListener('storage', function(e) {
        if(e.key === 'user_type_preference') {
             updateModeUI();
        }
    });
                
    // Initial UI update
    updateModeUI();
    </script>
  
</body>
</html>