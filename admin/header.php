<?php
// admin/header.php - Dedicated admin header
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Question Paper Generator</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="../css/admin-header.css">
</head>
<body>
    <nav class="admin-navbar">
        <div class="admin-navbar-container">
            <div class="admin-nav-left">
                <a href="../index.php" class="admin-nav-logo">
                    <span class="logo-icon">⚙️</span>
                    <span class="logo-text">Admin Panel</span>
                </a>
                <div class="admin-nav-divider"></div>
                <a href="dashboard.php" class="admin-nav-brand">QPaperGen</a>
            </div>
            
            <div class="admin-nav-toggle" id="adminNavToggle" aria-label="Open admin navigation" tabindex="0">
                <span></span>
                <span></span>
                <span></span>
            </div>
            
            <ul class="admin-nav-menu" id="adminNavMenu">
                <li><a href="dashboard.php" class="nav-link-dashboard">📊 Dashboard</a></li>
                <li class="nav-dropdown">
                    <a href="#" class="nav-link-dropdown">📚 Manage Content</a>
                    <ul class="dropdown-menu">
                        <li><a href="manage_classes.php">🏫 Classes</a></li>
                        <li><a href="manage_books.php">📖 Books</a></li>
                        <li><a href="manage_chapters.php">📝 Chapters</a></li>
                        <li><a href="manage_questions.php">❓ Questions</a></li>
                    </ul>
                </li>
                <li><a href="deleted_questions.php" class="nav-link-deleted">🗑️ Deleted</a></li>
                <li><a href="contact_messages.php" class="nav-link-contact">💌 Contact Messages</a></li>
                <li><a href="users.php" class="nav-link-users">👥 Admins</a></li>
                <li><a href="settings.php" class="nav-link-settings">⚙️ Settings</a></li>
                <li class="admin-user-info">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></span>
                    <a href="logout.php" class="nav-link-logout">🚪 Logout</a>
                </li>
            </ul>
        </div>
        <div class="admin-nav-overlay" id="adminNavOverlay"></div>
    </nav>
    
    <div class="admin-main-content">
    
    <script>
    // Admin Navbar Toggle
    const adminNavToggle = document.getElementById('adminNavToggle');
    const adminNavMenu = document.getElementById('adminNavMenu');
    const adminNavOverlay = document.getElementById('adminNavOverlay');
    
    function closeAdminMenu() {
        adminNavMenu.classList.remove('open');
        adminNavToggle.classList.remove('open');
        adminNavOverlay.classList.remove('open');
        document.body.style.overflow = '';
    }
    
    function openAdminMenu() {
        adminNavMenu.classList.add('open');
        adminNavToggle.classList.add('open');
        adminNavOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    
    adminNavToggle.addEventListener('click', function() {
        if (adminNavMenu.classList.contains('open')) {
            closeAdminMenu();
        } else {
            openAdminMenu();
        }
    });
    
    adminNavOverlay.addEventListener('click', closeAdminMenu);
    
    // Close on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAdminMenu();
    });
    
    // Close on menu link click (mobile)
    adminNavMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) closeAdminMenu();
        });
    });
    
    // Dropdown functionality
    document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
        const link = dropdown.querySelector('.nav-link-dropdown');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        link.addEventListener('click', function(e) {
            e.preventDefault();
            dropdown.classList.toggle('open');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });
    });
    
    // Highlight current page
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.admin-nav-menu a').forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });
    </script>
