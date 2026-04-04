<?php
/**
 * Common Favicon Include
 * This file provides a centralized way to include favicons across the entire site.
 */

// Determine the base path to the root directory
if (!isset($faviconBaseUrl)) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the script path and calculate relative distance to root
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $pathParts = explode('/', trim($scriptPath, '/'));
    
    // The project is assumed to be in the root of the domain or a specific folder.
    // If it's in a subdirectory like /auth/ or /admin/, we need to go up.
    $depth = 0;
    if (strpos($scriptPath, '/auth/') !== false) $depth = 1;
    if (strpos($scriptPath, '/admin/') !== false) {
        $depth = 1;
        // Check if we are deeper in admin (e.g. /admin/manageSchool/)
        if (preg_match('/\/admin\/[^\/]+\//', $scriptPath)) $depth = 2;
    }
    if (strpos($scriptPath, '/quiz/') !== false) $depth = 1;
    if (strpos($scriptPath, '/notes/') !== false) $depth = 1;
    if (strpos($scriptPath, '/email/') !== false) $depth = 1;
    if (strpos($scriptPath, '/payment/') !== false) $depth = 1;
    if (strpos($scriptPath, '/tests/') !== false) $depth = 1;
    if (strpos($scriptPath, '/migrations/') !== false) $depth = 1;
    if (strpos($scriptPath, '/cron/') !== false) $depth = 1;
    if (strpos($scriptPath, '/questionPaperFromTopic/') !== false) $depth = 1;

    $rootPath = str_repeat('../', $depth);
    $faviconBaseUrl = $rootPath;
}
?>
<!-- Favicons -->
<link rel="icon" type="image/png" href="<?= $faviconBaseUrl ?>favicon/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="<?= $faviconBaseUrl ?>favicon/favicon.svg" />
<link rel="shortcut icon" href="<?= $faviconBaseUrl ?>favicon/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="<?= $faviconBaseUrl ?>favicon/apple-touch-icon.png" />
<link rel="manifest" href="<?= $faviconBaseUrl ?>favicon/site.webmanifest" />
<meta name="apple-mobile-web-app-title" content="AhmadHub" />
<link rel="icon" type="image/png" href="<?= $faviconBaseUrl ?>favicon/web-app-manifest-192x192.png" sizes="192x192" />
<link rel="icon" type="image/png" href="<?= $faviconBaseUrl ?>favicon/web-app-manifest-512x512.png" sizes="512x512" />
