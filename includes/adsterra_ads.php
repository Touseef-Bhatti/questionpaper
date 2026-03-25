<?php
/**
 * Adsterra Ad Unit Renderer
 * Handles banner and skyscraper ad display logic.
 */

function renderAdsterraAdUnit($type, $placement = '', $class = '', $style = '') {
    $userId = $_SESSION['user_id'] ?? null;
    $showAds = true;

    // Only load ads on full page refresh (exclude AJAX requests)
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return "<!-- Ads skipped for AJAX request -->";
    }

    // --- LOCALHOST AD TOGGLE (BANNER ADS) ---
    // Uncomment the block below to DISABLE banner ads on localhost
    /*
    $hostPart = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
    if ($hostPart === 'localhost' || $hostPart === '127.0.0.1') {
        return "<!-- Ads disabled on localhost -->";
    }
    */

    // --- PAGE SPECIFIC ADS (BANNER ADS) ---
    // Add the filenames where you want banner ads to appear (e.g., 'index.php', 'mcqs.php').
    // Leave the array empty to show ads on ALL pages.
    $allowedPages = [
        'index.php',
        'mcqs.php'
    ];
    
    if (!empty($allowedPages)) {
        $currentPage = basename($_SERVER['SCRIPT_NAME']);
        if (!in_array($currentPage, $allowedPages)) {
            return "<!-- Ads disabled on this page -->";
        }
    }

    // Check subscription status
    if ($showAds && $userId) {
        if (class_exists('SubscriptionCheck')) {
            $showAds = SubscriptionCheck::shouldShowAds($userId);
        }
    }

    // If ads are disabled for this user, return nothing
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
              /* margin: 0 !important; */
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
