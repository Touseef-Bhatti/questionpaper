<?php
require_once __DIR__ . '/ads.php';
include 'db_connect.php';
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

// Dynamic asset base calculation (robust for subdirectory deployments)
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
$scriptDir  = str_replace('\\', '/', dirname($scriptPath));
$docRoot    = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
$appDirFs   = str_replace('\\', '/', __DIR__);
$assetBase  = '';

if ($docRoot !== '' && strpos($appDirFs, $docRoot) === 0) {
    $appBase = substr($appDirFs, strlen($docRoot)); // e.g. /myapp
    if ($appBase === '') { $appBase = '/'; }

    if (strpos($scriptDir, $appBase) === 0) {
        $rel = trim(substr($scriptDir, strlen($appBase)), '/');
        $depth = ($rel === '') ? 0 : (substr_count($rel, '/') + 1);
        $assetBase = str_repeat('../', $depth);
    } else {
        // Fallback if SCRIPT_NAME doesn't start with app base
        $assetBase = (substr_count(trim($scriptDir, '/'), '/') >= 1) ? '../' : '';
    }
} else {
    // Fallback: assume root vs one-level deep
    $isRoot = ($scriptDir === '' || $scriptDir === '/' || $scriptDir === '.');
    $assetBase  = $isRoot ?  '' : '../';
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
$gen_paper_pages = ['select_class.php', 'select_book.php', 'select_chapters.php', 'mcqs_topic.php', 'quiz_setup.php', 'online_quiz_host', 'online_quiz_lobby.php', 'quiz.php'];
$is_gen_paper_active = false;
foreach ($gen_paper_pages as $p) {
    if (strpos($current_page, $p) !== false) {
        $is_gen_paper_active = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'AhmadLearningHub' ?></title>
    <?php if (isset($metaDescription)): ?>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
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
        background: rgba(30, 41, 59, 0.75);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08); /* slight border */
    }
    
    html.dark-mode .nav-menu li a {
        color: var(--text-primary);
    }

    html.dark-mode .dropdown-content {
        background-color: var(--surface);
        border-color: var(--border);
    }

    html.dark-mode .dropdown-content a {
        color: var(--text-primary);
        border-bottom-color: var(--border);
    }

    html.dark-mode .dropdown-content a:hover {
        background-color: rgba(255, 255, 255, 0.05);
        color: var(--primary-dark);
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
    position: sticky;
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
    width: 100%;
    margin: 0;
    min-height: 70px;
    display: flex;
    align-items: center;
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

/* Mobile: collapse to dot circle */
@media (max-width: 768px) {
    .plan-chip .plan-label { display: none; }
    .plan-chip { padding: 9px; border-radius: 50%; gap: 0; }
    .plan-chip .plan-dot { width: 9px; height: 9px; }
}

/* Hamburger Icon */
.nav-toggle {
    display: none; /* Hidden by default on desktop */
    flex-direction: column;
    justify-content: center;
    width: 40px;
    height: 40px;
    cursor: pointer;
    z-index: 1100;
    margin-left: 1rem;
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
    position: fixed;
    width: 100%;
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
        padding: 0.6rem 0;
        min-height: 65px;
    }
    
    .navbar-container {
        width: 95%;
        padding: 0 1rem;
        flex-direction: row;
        align-items: center;
    }
    
    .nav-logo {
        font-size: 1.0rem;
        z-index: 1100;
    }
    
    .nav-menu {
        position: fixed;
        top: 0;
        left: -300px; /* Wider menu, off-screen to the left */
        height: 100vh;
        width: 280px; /* Wider menu */
        background: rgba(255, 255, 255, 0.65); /* Glass effect for mobile menu */
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-right: 1px solid rgba(255, 255, 255, 0.4);
        color: #000; /* Black text for mobile menu */
        flex-direction: column;
        gap: 1.3rem; /* Consistent gap */
        align-items: flex-start;
        padding: 5.5rem 2.5rem 2rem 2.5rem; /* Increased padding */
        box-shadow: 8px 0 20px rgba(0,0,0,0.3); /* Stronger shadow, on the right side */
        overflow-y: auto;
        transition: left 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.6s ease; /* Slower and smoother transition */
        z-index: 1050;
        visibility: hidden; /* Hidden by default on mobile */
        opacity: 0;
        transform: translateX(-100%); /* Slide out of view to the left */
    }
    
    .nav-menu.open {
        left: 0; /* Keep for compatibility if needed, but transform will override */
        visibility: visible; /* Show when open */
        opacity: 1;
        transform: translateX(0); /* Slide into view */
        display: flex; /* Ensure it's flex when open */
        box-shadow: 10px 0 30px rgba(0,0,0,0.4); /* Even stronger shadow */
    }
    
    .nav-menu li {
        opacity: 0; /* Start hidden */
        transform: translateX(-20px); /* Start slightly off-screen to the left */
        transition: opacity 0.3s ease, transform 0.3s ease; /* Slower individual item transition */
        width: 100%;
    }

    .nav-menu.open li {
        opacity: 1; /* Fade in */
        transform: translateX(0); /* Slide into view */
        transition-delay: var(--delay); /* Use CSS variable for staggered delay */
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
        display: block;
        width: 100%;
        padding: 0.8rem 1.2rem; /* Increased padding */
        border-radius: 8px; /* More rounded corners */
        border-bottom: none;
        background: transparent;
        transition: all 0.3s ease;
        font-size: 1.1rem; /* Slightly larger font */
    }
    
    .nav-menu li a:hover {
        background: rgba(0, 0, 0, 0.05); /* Subtle background on hover */
        color: #333; /* Darker hover color */
        border-bottom: none;
        transform: translateX(8px); /* More pronounced slide effect */
    }
    
    .nav-toggle {
        display: flex;
        z-index: 1100;
    }
    
    /* Mobile Join Button Adjustment */
    .nav-menu li a.btn-join {
        background: var(--primary); /* Simpler background on mobile */
        color: white !important;
        text-align: center;
        width: 100%;
        justify-content: center;
        margin-top: 0.5rem;
        box-shadow: none;
        border-radius: 12px;
        padding: 0.9rem;
    }
}


@media (max-width: 480px) {
    .navbar-container {
        width: 98%;
        padding: 0 0.5rem;
    }
    
    .nav-logo {
        font-size: 1.4rem;
    }
    
    .nav-menu {
        width: 260px; /* Adjusted width */
        left: -270px; /* Adjusted left position */
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
        width: 100%; /* Full width for mobile dropdown */
    }

    .dropdown-content {
        position: relative; /* Stacks vertically in mobile menu */
        background-color: rgba(255, 255, 255, 0.9); /* Semi-transparent white for mobile dropdown */
        box-shadow: none; /* No shadow in mobile */
        border-radius: 0; /* No rounded corners in mobile */
        min-width: unset;
        width: 100%;
        top: 0;
        transform: translateY(0);
        padding-left: 1.5rem; /* Indent dropdown items */
        border: none; /* No border in mobile */
    }

    .dropdown-content a {
        padding: 10px 16px;
        font-size: 1.1rem; /* Slightly larger font for mobile dropdown items */
        border-bottom: none; /* No border in mobile */
    }

    .dropdown-content a:hover {
        background-color: rgba(0, 0, 0, 0.05); /* Consistent hover background */
        transform: translateX(0); /* No slide effect in mobile */
    }
}

.user-header-info {
    display: flex;
    flex-direction: column;
    background: #f8fafc;
}

html.dark-mode .user-header-info {
    background: #0f172a;
    border-bottom-color: rgba(255,255,255,0.1) !important;
}

html.dark-mode .user-header-info .fw-bold {
    color: #f8fafc !important;
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
    right: 20px;
    top: 15px;
    font-size: 28px;
    font-weight: bold;
    color: #64748b;
    cursor: pointer;
    transition: color 0.2s;
}

.close-modal:hover {
    color: #1e293b;
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
    font-size: 0.85rem;
    color: #94a3b8;
}
   </style>
   <link rel="stylesheet" href="<?= $assetBase ?>css/ads.css">
</head>
<body>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<link rel="stylesheet" href="../css/index.css">
<link rel="stylesheet" href="../css/main.css">

<nav class="navbar">
  <div class="navbar-container">
    <a href="<?= $assetBase ?>index.php" class="nav-logo">AhmadLearningHub</a>
    <div class="nav-toggle" id="navToggle">
      <span></span>
      <span></span>
      <span></span>
    </div>
    <ul class="nav-menu" id="navMenu">
      <li><a href="<?= $assetBase ?>index.php" class="<?= (basename($current_page) == 'index.php' || basename($current_page) == '') ? 'active' : '' ?>"><i class="fas fa-home"></i> Home</a></li>
      <li class="dropdown">
        <a class="dropbtn <?= $is_gen_paper_active ? 'active' : '' ?>">Generate Paper <i class="fas fa-caret-down"></i></a>
        <div class="dropdown-content">
             <a href="<?= $assetBase ?>select_class.php" class="<?= is_active('select_class.php') ?>"><i class="fas fa-file-alt"></i> Create Question Paper</a>
          <a href="<?= $assetBase ?>quiz/online_quiz_host_new.php" class="<?= is_active('online_quiz_host_new.php') ?>"><i class="fas fa-file-alt"></i> Host Online Quiz</a>
          <a href="<?= $assetBase ?>quiz/quiz_setup.php" class="<?= is_active('quiz_setup.php') ?>"><i class="fas fa-question-circle"></i> MCQs Quiz</a>
          <a href="<?= $assetBase ?>quiz/online_quiz_join.php" class="<?= is_active('online_quiz_join.php') ?>"><i class="fas fa-gamepad"></i> Join Quiz</a>
        </div>
      </li>
      <li><a href="<?= $assetBase ?>notes/notes.php" class="<?= is_active('notes.php') ?>"><i class="fas fa-book"></i> Notes</a></li>
      <li><a href="<?= $assetBase ?>quiz/online_quiz_join.php" class="btn-join"><i class="fas fa-gamepad" ></i> Join</a></li>
      <li><a href="<?= $assetBase ?>about.php" class="<?= is_active('about.php') ?>"><i class="fas fa-info-circle"></i> About</a></li>
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
                            <a href="<?= $subInfo['is_premium'] ? ($assetBase . 'subscription.php') : 'javascript:void(0)' ?>"
                               onclick="<?= $subInfo['is_premium'] ? '' : 'showGlobalUpgradeModal(\'general\')' ?>"
                               class="plan-chip  <?= $subInfo['is_premium'] ? 'premium' : 'basic' ?>"
                               title="<?= htmlspecialchars($subInfo['plan_name']) ?> Plan" style="border-radius: 50px;">
                                <span class="plan-dot"></span>
                                <span class="plan-label"><?= htmlspecialchars($subInfo['plan_name']) ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="dropdown profile-dropdown">
                        <a href="<?= $assetBase ?>profile.php" class="dropbtn <?= is_active('profile.php') ?>"><i class="fas fa-user-circle"></i> Profile <i class="fas fa-caret-down"></i></a>
                        <div class="dropdown-content">
                            <div class="user-header-info px-3 py-2 border-bottom mb-1">
                                <div class="fw-bold text-dark small"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></div>
                                <div class="text-muted small" style="font-size: 0.75rem;"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
                            </div>
                            <a href="<?= $assetBase ?>profile.php" class="<?= is_active('profile.php') ?>"><i class="fas fa-user-cog"></i> My Profile</a>
                            <a href="#" id="headerModeSwitchBtn"><i class="fas fa-exchange-alt"></i> Switch Mode</a>
                            <a href="<?= $assetBase ?>settings.php" class="<?= is_active('settings.php') ?>"><i class="fas fa-cog"></i> Settings</a>
                            <a href="<?= $assetBase ?>auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </li>
                <?php else: ?>
                    <!-- <li><a href="<?= $assetBase ?>subscription.php">Plans</a></li> -->
                    
                    <li><a href="<?= $assetBase ?>auth/login.php" class="<?= is_active('login.php') ?>"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <?php endif; ?>
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
                <a href="<?= $assetBase ?>auth/login.php" class="btn-auth-login">Login to My Account</a>
                <a href="<?= $assetBase ?>auth/register.php" class="btn-auth-register">Create New Account</a>
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
            <a href="<?= $assetBase ?>subscription.php" class="btn-upgrade-now">
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

</body>
</html>

    <script>
    // Responsive Navbar Toggle
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    const navOverlay = document.getElementById('navOverlay');
    
    function closeMenu() {
        navMenu.classList.remove('open');
        navToggle.classList.remove('open');
        navOverlay.classList.remove('open');
        document.body.classList.remove('menu-open');
        // Close all dropdowns when mobile menu is closed
        document.querySelectorAll('.dropdown-content').forEach(content => {
            content.classList.remove('show');
        });
    }
    
    function openMenu() {
        navMenu.classList.add('open');
        navToggle.classList.add('open');
        navOverlay.classList.add('open');
        document.body.classList.add('menu-open');
    }
    
    if (navToggle) {
        navToggle.addEventListener('click', function() {
            if (navMenu.classList.contains('open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });
    }
    
    if (navOverlay) {
        navOverlay.addEventListener('click', closeMenu);
    }

    // Close the mobile menu when clicking outside the menu area (sides of the page)
    document.addEventListener('click', function(e) {
        // Only active when menu open and on small screens
        if (!navMenu.classList.contains('open') || window.innerWidth > 768) return;

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
                if (!link.classList.contains('dropbtn') && window.innerWidth <= 768) {
                    closeMenu();
                }
            });
        });
    }

    // Dropdown functionality (desktop hover, mobile click)
    document.querySelectorAll('.dropdown > .dropbtn').forEach(dropbtn => {
        dropbtn.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) { // Only for mobile
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
        if (window.innerWidth <= 768 && !e.target.matches('.dropbtn')) {
            document.querySelectorAll('.dropdown-content.show').forEach(content => {
                content.classList.remove('show');
            });
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
                setTimeout(showAuthModal, 2000);
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
  