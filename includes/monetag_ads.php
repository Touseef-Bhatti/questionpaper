<?php
/**
 * Monetag Ad Scripts
 * Included dynamically based on subscription status.
 */

if (defined('ALH_MONETAG_ADS_RENDERED')) {
    return;
}
define('ALH_MONETAG_ADS_RENDERED', true);

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
        'select_book_for_test.php',
        'select_chapters_for_test.php',
        // Merged from removed In-page push Ads 2 script.
        'quiz_setup_inter.php',
        'select_book.php',
        'select_question.php',
        'mcqs_topic.php',
        'finalize_paper.php',
        'online_quiz_join.php',
        'select_class_for_test.php',
    ];
    
    if (!shouldShowAdsOnCurrentPage($allowedPages)) {
        return '';
    }
    
    return '
    <!-- In-page push Ads 1; removed Ads 2 zone 10846120 -->
    <script async data-zone="10752105" src="https://nap5k.com/tag.min.js"></script>
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
        'select_book_for_test.php',
        // Merged from removed Vignette Banner 2 script.
        'textbooks.php',
        'mcqs.php',
        'reviews.php',
        'about.php',
        'contact.php',
        'online_quiz_join.php',
        'online_quiz_lobby.php',
        'select_chapters_for_test.php',
    ];
    
    if (!shouldShowAdsOnCurrentPage($allowedPages)) {
        return '';
    }
    
    return '
    <!-- Vignette Banner 1; removed Banner 2 zone 10846367 -->
    <script async data-zone="10752115" src="https://izcle.com/vignette.min.js"></script>
    ';
}

function shouldShowAdsOnCurrentPage($allowedPages) {
    if (isMonetagRestrictedRequest()) {
        return false;
    }

    // --- LOCALHOST AD TOGGLE ---
    $hostPart = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    if (in_array($hostPart, ['', 'localhost', '127.0.0.1', '::1'], true)) {
        return false;
    }
    
    // --- PAGE SPECIFIC ADS ---
    if (!empty($allowedPages)) {
        $currentPage = basename($_SERVER['SCRIPT_NAME']);
        if (!in_array($currentPage, $allowedPages)) {
            return false;
        }
    }
    
    return true;
}

function isMonetagRestrictedRequest() {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }

    if (isset($_GET['ajax']) || isset($_POST['ajax']) || isset($_GET['modal'])) {
        return true;
    }

    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $forbiddenScripts = ['ai_loader.php', 'download_doc.php', 'download_docx.php', 'generate_sitemap.php'];

    return in_array($scriptName, $forbiddenScripts, true);
}

// Render all ads by default when file is included
echo inPagePushAds1();
echo vignetteBanner1();
?>
