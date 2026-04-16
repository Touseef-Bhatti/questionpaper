<?php
/**
 * Ad Management System
 * Centrally manages ad rendering and subscription-based visibility.
 */

require_once __DIR__ . '/middleware/SubscriptionCheck.php';

// ==========================================
// AD NETWORKS INCLUDES
// ==========================================

// 1. Adsterra (Banner and Skyscraper Ads)
// include_once __DIR__ . '/includes/adsterra_ads.php';

// 2. Monetag (Popunder and Vignette Ads)
// (Controlled in renderMonetagScripts below via include)

// ==========================================

/**
 * Renders an ad unit based on type and placement.
 * Returns empty string if the user has a subscription that hides ads.
 * 
 * @param string $type 'banner', 'mobile_banner', or 'skyscraper'
 * @param string $placement Human readable placement title
 * @param string $class Optional CSS classes
 * @param string $style Optional inline styles
 * @return string HTML content
 */
function renderAd($type, $placement = '', $class = '', $style = '') {
    // Only load ads on full page refresh
    if (isAdRestrictedRequest()) {
        return "<!-- Ads skipped for partial/AJAX request -->";
    }

    // If Adsterra is disabled or missing
    if (!function_exists('renderAdsterraAdUnit')) {
        return "<!-- Adsterra network disabled in ads.php -->";
    }

    return renderAdsterraAdUnit($type, $placement, $class, $style);
}

/**
 * Renders Monetag background/interstitial scripts.
 * Returns empty string if ads are disabled for the current user.
 * 
 * @return string HTML script tags
 */
function renderMonetagScripts() {
    static $monetagRendered = false;
    
    // Prevent repetitive loading of the same scripts
    if ($monetagRendered) {
        return "<!-- Monetag scripts already rendered for this page -->";
    }

    $userId = $_SESSION['user_id'] ?? null;
    $showAds = true;

    // Only load ads on full page refresh
    if (isAdRestrictedRequest()) {
        return "<!-- Monetag skipped for restricted request type -->";
    }

    if ($showAds && $userId) {
        // Logged in: Check subscription status
        if (class_exists('SubscriptionCheck')) {
            $showAds = SubscriptionCheck::shouldShowAds($userId);
        }
    }

    // If ads are disabled for this user, return nothing
    if (!$showAds) {
        return "<!-- Monetag ads disabled for premium user -->";
    }

    $monetagRendered = true;

    // 3. Render Monetag scripts from includes
    ob_start();
    // include __DIR__ . '/includes/monetag_ads.php';
    return ob_get_clean();
}

/**
 * Helper to determine if the current request should avoid loading ads.
 * Prevents ads from loading via AJAX, fragment loads, or certain system calls.
 */
function isAdRestrictedRequest() {
    // 1. Check for AJAX headers
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }

    // 2. Check for common AJAX-only file patterns or query params
    if (isset($_GET['ajax']) || isset($_POST['ajax']) || isset($_GET['modal'])) {
        return true;
    }

    // 3. Check for specific AI loader or fragment scripts
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $forbiddenScripts = ['ai_loader.php', 'download_doc.php', 'download_docx.php', 'generate_sitemap.php'];
    if (in_array($scriptName, $forbiddenScripts)) {
        return true;
    }

    return false;
}


