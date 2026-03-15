<?php
/**
 * Ad Management System
 * Centrally manages ad rendering and subscription-based visibility.
 */

require_once __DIR__ . '/middleware/SubscriptionCheck.php';

/**
 * Renders an ad unit based on type and placement.
 * Returns empty string if the user has a subscription that hides ads.
 * 
 * @param string $type 'banner' or 'skyscraper'
 * @param string $placement Humand readable placement title
 * @param string $class Optional CSS classes
 * @param string $style Optional inline styles
 * @return string HTML content
 */
function renderAd($type, $placement = '', $class = '', $style = '') {
    // 1. Logic: Check if ads should be shown
    $userId = $_SESSION['user_id'] ?? null;
    $showAds = true;

    if ($userId) {
        // Logged in: Check subscription features
        $showAds = SubscriptionCheck::shouldShowAds($userId);
    }

    // 2. If ads are disabled for this user, return nothing
    if (!$showAds) {
        return "<!-- Ads disabled for premium user -->";
    }

    // 3. Render the requested ad type
    if ($type === 'skyscraper') {
        $side = strpos($class, 'right') !== false ? 'right' : 'left';
        return "
        <!-- SIDE SKYSCRAPER AD -->
        <div class=\"ad-skyscraper {$side} {$class}\" style=\"{$style}\" title=\"{$placement}\"></div>";
    }

    // Default: Responsive Banner
    return "
    <!-- AD BANNER: {$placement} -->
    <div class=\"ad-banner-container {$class}\" style=\"{$style}\">
        <div class=\"ad-banner-placeholder\" title=\"{$placement}\"></div>
    </div>";
}
