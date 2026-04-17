<?php
/**
 * Monetag Ad Scripts
 * Included dynamically based on subscription status.
 */

// --- LOCALHOST AD TOGGLE (MONETAG ADS) ---
// Uncomment the block below to DISABLE Monetag ads on localhost
/*
$hostPart = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
if ($hostPart === 'localhost' || $hostPart === '127.0.0.1') {
    return;
}
*/

// --- PAGE SPECIFIC ADS (MONETAG ADS) ---
// Add the filenames where you want Monetag ads to appear.
// Leave the array empty to show Monetag ads on ALL pages.
$allowedPages = [
    
    'select_book.php',
    'select_question.php',
    'topic-wise-mcqs-test',
    'home.php',
    'settings.php',
    'online_quiz_join.php',
    'online_quiz_lobby.php',
    'textbooks.php',
    'mcqs.php',
    'reviews.php',
    'about.php',
    'contact.php',
    'privacy-policy.php',
    'terms-and-conditions.php',
];

if (!empty($allowedPages)) {
    $currentPage = basename($_SERVER['SCRIPT_NAME']);
    if (!in_array($currentPage, $allowedPages)) {
        return;
    }
}
?>

<!-- Monetag Centralized Scripts -->
<!-- In-page push -->

<script>(function(s){s.dataset.zone='10846120',s.src='https://nap5k.com/tag.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>

<!-- Monetag Vignettes (Consolidated to one zone to prevent multiple close button clicks) -->
<script>(function(s){s.dataset.zone='10752115',s.src='https://izcle.com/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
<?php /* Removed redundant vignette zones that caused multiple close button issues
<script>(function(s){s.dataset.zone='10846367',s.src='https://n6wxm.com/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
<script>(function(s){s.dataset.zone='10835874',s.src='https://n6wxm.com/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
<script>(function(s){s.dataset.zone='10788340',s.src='https://n6wxm.com/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
*/ ?>
