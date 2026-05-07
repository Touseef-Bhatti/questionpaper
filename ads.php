<?php
/**
 * Ad Management System
 * Centrally manages ad rendering and subscription-based visibility.
 */

require_once __DIR__ . '/middleware/SubscriptionCheck.php';

/**
 * Renders an ad unit based on type and placement.
 * Currently disabled - returns empty string.
 * 
 * @param string $type 'banner', 'mobile_banner', or 'skyscraper'
 * @param string $placement Human readable placement title
 * @param string $class Optional CSS classes
 * @param string $style Optional inline styles
 * @return string HTML content
 */
function renderAd($type, $placement = '', $class = '', $style = '') {
    return "<!-- Ads disabled -->";
}

/**
 * Renders Monetag background/interstitial scripts.
 * 
 * @return string HTML script tags
 */
function renderMonetagScripts() {
    static $monetagRendered = false;
    
    if ($monetagRendered) {
        return "<!-- Monetag scripts already rendered for this page -->";
    }
    
    $monetagRendered = true;
    
    ob_start();
    include __DIR__ . '/includes/monetag_ads.php';
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


