<?php
// auth_check.php - Authentication middleware
// Include this file on any page that requires user authentication

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Get the current page URL for redirect after login
    $current_page = $_SERVER['REQUEST_URI'] ?? '/';

    // Store the intended destination in session
    $_SESSION['redirect_after_login'] = $current_page;

    // Compute relative path to auth/login.php from the current URL
    // This mirrors the logic used in header.php for robust subdirectory support
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '/');
    $scriptDir  = str_replace('\\', '/', dirname($scriptPath));
    $docRoot    = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    $appRootFs  = str_replace('\\', '/', dirname(__DIR__)); // filesystem path to project root

    $loginHref  = 'auth/login.php'; // sensible default for root
    if ($docRoot !== '' && strpos($appRootFs, $docRoot) === 0) {
        $appBase = substr($appRootFs, strlen($docRoot)); // e.g. /paper.myapp
        if ($appBase === '') { $appBase = '/'; }

        if (strpos($scriptDir, $appBase) === 0) {
            $rel = trim(substr($scriptDir, strlen($appBase)), '/');
            $depth = ($rel === '') ? 0 : (substr_count($rel, '/') + 1);
            $prefix = str_repeat('../', $depth);
            $loginHref = $prefix . 'auth/login.php';
        } else {
            // Fallback: if under at least one segment, go up one level
            $loginHref = (substr_count(trim($scriptDir, '/'), '/') >= 1) ? '../auth/login.php' : 'auth/login.php';
        }
    }

    // Redirect to login page
    header('Location: ' . $loginHref);
    exit;
}

// Optional: Additional checks
// - Account status verification
// - Session timeout checks
// - Role-based access control
?>
