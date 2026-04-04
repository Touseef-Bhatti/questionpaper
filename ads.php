<?php




/**
 * Ad Management System
 * Centrally manages ad rendering and subscription-based visibility.
 */

require_once __DIR__ . '/middleware/SubscriptionCheck.php';



// ==========================================
// AD NETWORKS INCLUDES
// Comment out any include below to completely disable that ad network
// ==========================================

// 1. Adsterra (Banner and Skyscraper Ads)
include_once __DIR__ . '/includes/adsterra_ads.php';

// 2. Monetag (Popunder and Vignette Ads)
// (Controlled in renderMonetagScripts below)

// ==========================================

/**
 * Renders an ad unit based on type and placement.
 * Returns empty string if the user has a subscription that hides ads.
 * 
 * @param string $type 'banner', 'mobile_banner', or 'skyscraper'
 * @param string $placement Humand readable placement title
 * @param string $class Optional CSS classes
 * @param string $style Optional inline styles
 * @return string HTML content
 */
function renderAd($type, $placement = '', $class = '', $style = '') {
    // If Adsterra is commented out, function won't exist
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
    $userId = $_SESSION['user_id'] ?? null;
    $showAds = true;

    // Only load ads on full page refresh (exclude AJAX requests)
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return "<!-- Monetag skipped for AJAX request -->";
    }

    if ($showAds && $userId) {
        // Logged in: Check subscription features
        require_once __DIR__ . '/middleware/SubscriptionCheck.php';
        $showAds = SubscriptionCheck::shouldShowAds($userId);
    }

    // If ads are disabled for this user, return nothing
    if (!$showAds) {
        return "<!-- Monetag ads disabled for premium user -->";
    }

    // 3. Render Monetag scripts from includes
    // To disable Monetag completely, you can comment out the include below
    ob_start();
    // include __DIR__ . '/includes/monetag_ads.php';
    return ob_get_clean();
}


