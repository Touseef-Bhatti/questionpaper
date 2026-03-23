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
 * @param string $type 'banner', 'mobile_banner', or 'skyscraper'
 * @param string $placement Humand readable placement title
 * @param string $class Optional CSS classes
 * @param string $style Optional inline styles
 * @return string HTML content
 */
function renderAd($type, $placement = '', $class = '', $style = '') {
    // 1. Logic: Check if ads should be shown
    $userId = $_SESSION['user_id'] ?? null;
    $showAds = true;

    // Only load ads on full page refresh (exclude AJAX requests)
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return "<!-- Ads skipped for AJAX request -->";
    }

    // Do not show ads on localhost handling
    if ($showAds && $userId) {
        $showAds = SubscriptionCheck::shouldShowAds($userId);
    }

    // 2. If ads are disabled for this user, return nothing
    if (!$showAds) {
        return "<!-- Ads disabled for premium user -->";
    }

    // 3. Render the requested ad type
    // Side Rectangle 300x250
    if ($type === 'skyscraper') {
        return "
    <!-- AD RECTANGLE 300x250: {$placement} -->
    <style>
      .ad-pc-right-mobile-top {
          width: 300px;
          height: 250px;
          margin: 0 auto 20px auto;
          text-align: center;
          display: block;
      }
      @media (min-width: 992px) {
          .ad-pc-right-mobile-top {
              position: absolute;
              top: 250px;
              right: 20px;
              z-index: 1000;
              // margin: 0 !important;
          }
      }
    </style>
    <div class=\"ad-pc-right-mobile-top {$class}\" style=\"{$style}\">
        <script>
          atOptions = {
            'key' : 'c54ab34f07f33632a986cb4782570f9b',
            'format' : 'iframe',
            'height' : 250,
            'width' : 300,
            'params' : {}
          };
        </script>
        <script src=\"https://www.highperformanceformat.com/c54ab34f07f33632a986cb4782570f9b/invoke.js\"></script>
    </div>";
    }

    // Mobile Banner 320x50
    if ($type === 'mobile_banner') {
        return "
    <!-- AD MOBILE BANNER: {$placement} -->
    <div class=\"ad-banner-container {$class}\" style=\"{$style}\">
        <script>
          atOptions = {
            'key' : '43642832886698adfde3a7506c418808',
            'format' : 'iframe',
            'height' : 50,
            'width' : 320,
            'params' : {}
          };
        </script>
        <script src=\"https://www.highperformanceformat.com/43642832886698adfde3a7506c418808/invoke.js\"></script>
    </div>";
    }

    // Responsive Banner: 728x90 on desktop, 320x50 on mobile
    return "
    <!-- AD BANNER (RESPONSIVE): {$placement} -->
    <style>
      .ad-responsive-banner .ad-desktop-728 { display:block; }
      .ad-responsive-banner .ad-mobile-320  { display:none;  }
      @media (max-width: 767px) {
        .ad-responsive-banner .ad-desktop-728 { display:none;  }
        .ad-responsive-banner .ad-mobile-320  { display:block; }
      }
    </style>
    <div class=\"ad-responsive-banner {$class}\" style=\"text-align:center;overflow:hidden;{$style}\">

        <!-- Desktop: 728x90 -->
        <div class=\"ad-desktop-728\">
            <script>
              atOptions = {
                'key' : 'e29fb48aaf844c9c145f7894f28f01b2',
                'format' : 'iframe',
                'height' : 90,
                'width' : 728,
                'params' : {}
              };
            </script>
            <script src=\"https://www.highperformanceformat.com/e29fb48aaf844c9c145f7894f28f01b2/invoke.js\"></script>
        </div>

        <!-- Mobile: 320x50 -->
        <div class=\"ad-mobile-320\">
            <script>
              atOptions = {
                'key' : '43642832886698adfde3a7506c418808',
                'format' : 'iframe',
                'height' : 50,
                'width' : 320,
                'params' : {}
              };
            </script>
            <script src=\"https://www.highperformanceformat.com/43642832886698adfde3a7506c418808/invoke.js\"></script>
        </div>

    </div>";
}

/**
 * Renders Monetag background/interstitial scripts.
 * Returns empty string if ads are disabled for the current user.
 * 
 * @return string HTML script tags
 */
function renderMonetagScripts() {
    // 1. Logic: Check if ads should be shown
    $userId = $_SESSION['user_id'] ?? null;
    $showAds = true;

    // Only load ads on full page refresh (exclude AJAX requests)
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return "<!-- Monetag skipped for AJAX request -->";
    }

    // Do not show ads on localhost (handles ports like localhost:8000)
    // $hostPart = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
    // if ($hostPart === 'localhost' || $hostPart === '127.0.0.1') {
    //     $showAds = false;
    // }

    if ($showAds && $userId) {
        // Logged in: Check subscription features
        require_once __DIR__ . '/middleware/SubscriptionCheck.php';
        $showAds = SubscriptionCheck::shouldShowAds($userId);
    }

    // 2. If ads are disabled for this user, return nothing
    if (!$showAds) {
        return "<!-- Monetag ads disabled for premium user -->";
    }

    // 3. Render Monetag scripts from includes
    ob_start();
    include __DIR__ . '/includes/monetag_ads.php';
    return ob_get_clean();
}
