<?php
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
function is_active($page_name) {
    global $current_page;
    // Check if the current page path contains the page name
    // This handles both root-level and subdirectory pages
    return (strpos($current_page, $page_name) !== false) ? 'active' : '';
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
     <style>
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

/* Mobile Styles */
@media (max-width: 1024px) {
    .navbar-container {
        width: 95%;
        padding: 0 1rem;
    }
    .nav-menu {
        gap: 1.8rem;
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
   </style>
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
      <li><a href="<?= $assetBase ?>contact.php" class="<?= is_active('contact.php') ?>"><i class="fas fa-envelope"></i> Contact</a></li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                    // Include subscription middleware for header info
                    if (file_exists(__DIR__ . '/middleware/SubscriptionCheck.php')) {
                        require_once __DIR__ . '/middleware/SubscriptionCheck.php';
                        $subInfo = getSubscriptionInfo($_SESSION['user_id']);
                    }
                    ?>
                    <?php if (isset($subInfo) && $subInfo): ?>
                        <!-- Subscription plan button hidden -->
                        <?php /* <li><a href="<?= $assetBase ?>subscription.php" style="background: <?= $subInfo['is_premium'] ? '#28a745' : '#ffc107' ?>; color: <?= $subInfo['is_premium'] ? 'white' : '#856404' ?>; padding: 5px 10px; border-radius: 15px; font-size: 0.9rem; margin-right: 10px;"><?= htmlspecialchars($subInfo['plan_name']) ?></a></li> */ ?>
                    <?php endif; ?>
                    <li class="dropdown">
                        <a href="<?= $assetBase ?>profile.php" class="dropbtn <?= is_active('profile.php') ?>"><i class="fas fa-user-circle"></i> Profile <i class="fas fa-caret-down"></i></a>
                        <div class="dropdown-content">
                            <a href="<?= $assetBase ?>profile.php" class="<?= is_active('profile.php') ?>"><i class="fas fa-user-cog"></i> My Profile</a>
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

    </script>
  