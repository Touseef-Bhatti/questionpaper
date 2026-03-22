<?php
/**
 * Monetag Ad Scripts
 * Included dynamically based on subscription status.
 */

// Do not load ads on localhost (handles ports like localhost:8000)
// $hostPart = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
// if ($hostPart === 'localhost' || $hostPart === '127.0.0.1') {
//     return;
// }
?>
<!-- Monetag MultiTag & Vignette - Only load on refresh if detected by JS -->
<script>
(function() {
    // Check if the page is being reloaded (refreshed)
    // type === 1 for older performance API (legacy)
    // type === 'reload' for newer performance API
    const navEntries = performance.getEntriesByType('navigation');
    const isReload = (navEntries.length > 0 && navEntries[0].type === 'reload') || (window.performance.navigation && window.performance.navigation.type === 1);
    
    if (isReload) {
        // Run Monetag scripts only on refresh
        
        // Monetag MultiTag
        (function(s){s.dataset.zone='10752105',s.src='https://nap5k.com/tag.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')));
        
        // Monetag Vignette
        (function(s){s.dataset.zone='10752115',s.src='https://izcle.com/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')));
        
        console.log('Monetag ads loaded (Page Refresh detected)');
    } else {
        console.log('Monetag ads skipped (Standard Navigation)');
    }
})();
</script>
