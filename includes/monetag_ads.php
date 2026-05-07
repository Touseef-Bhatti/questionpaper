<?php
/**
 * Monetag Ad Scripts
 * Included dynamically based on subscription status.
 */

function inPagePushAds1() {
    $allowedPages = [
        
        'select_class.php',
        'quiz_setup.php',
        'textbooks.php',
        'mcqs.php',
        'reviews.php',
        'about.php',
        'contact.php',
        'online_quiz_lobby.php',
    ];
    
    if (!shouldShowAdsOnCurrentPage($allowedPages)) {
        return '';
    }
    
    return '
    <!-- In-page push Ads 1 -->
    <script>(function(s){s.dataset.zone="10752105",s.src="https://nap5k.com/tag.min.js"})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement("script")))</script>
    ';
}

function inPagePushAds2() {
    $allowedPages = [
        'select_book.php',
        'select_question.php',
        'mcqs_topic.php',
        'home.php',
        'finalize_paper.php',
        'online_quiz_join.php',
        
    ];
    
    if (!shouldShowAdsOnCurrentPage($allowedPages)) {
        return '';
    }
    
    return '
    <!-- In-page push Ads 2 -->
    <script>(function(s){s.dataset.zone="10846120",s.src="https://nap5k.com/tag.min.js"})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement("script")))</script>
    ';
}

function vignetteBanner1() {
    $allowedPages = [
        'select_chapters.php',
        'select_book.php',
        'mcqs_topic.php',
        'privacy-policy.php',
        'terms-and-conditions.php',
        'select_question.php',
    ];
    
    if (!shouldShowAdsOnCurrentPage($allowedPages)) {
        return '';
    }
    
    return '
    <!-- Vignette Banner 1 -->
    <script>(function(s){s.dataset.zone="10752115",s.src="https://izcle.com/vignette.min.js"})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement("script")))</script>
    ';
}

function vignetteBanner2() {
    $allowedPages = [
        'textbooks.php',
        'mcqs.php',
        'reviews.php',
        'about.php',
        'contact.php',
        'online_quiz_join.php',
        'online_quiz_lobby.php',
    ];
    
    if (!shouldShowAdsOnCurrentPage($allowedPages)) {
        return '';
    }
    
    return '
    <!-- Vignette Banner 2 -->
    <script>(function(s){s.dataset.zone="10846367",s.src="https://n6wxm.com/vignette.min.js"})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement("script")))</script>
    ';
}

function onClickAd() {
    $allowedPages = [
        // 'index.php',
        // 'select_class.php',
        // 'select_book.php',
        // 'select_chapters.php',
        // 'select_question.php',
        // 'quiz_setup.php',
        // 'mcqs_topic.php',
        // 'quiz.php',
        // 'home.php',
        // 'finalize_paper.php',
        // 'settings.php',
        // 'online_quiz_join.php',
        // 'online_quiz_lobby.php',
        // 'textbooks.php',
        // 'mcqs.php',
        // 'reviews.php',
        // 'about.php',
        // 'contact.php',
        // 'privacy-policy.php',
        // 'terms-and-conditions.php',
    ];
    
    if (!shouldShowAdsOnCurrentPage($allowedPages)) {
        return '';
    }
    
    return '
    <!-- OnClick Ad -->
    
    ';
}

function shouldShowAdsOnCurrentPage($allowedPages) {
    // // --- LOCALHOST AD TOGGLE ---
    // $hostPart = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
    // if ($hostPart === 'localhost' || $hostPart === '127.0.0.1') {
    //     return false;
    // }
    
    // --- PAGE SPECIFIC ADS ---
    if (!empty($allowedPages)) {
        $currentPage = basename($_SERVER['SCRIPT_NAME']);
        if (!in_array($currentPage, $allowedPages)) {
            return false;
        }
    }
    
    return true;
}

// Render all ads by default when file is included
echo inPagePushAds1();
echo inPagePushAds2();
echo vignetteBanner1();
echo vignetteBanner2();
echo onClickAd();
?>