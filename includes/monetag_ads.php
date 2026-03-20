<?php
/**
 * Monetag Ad Scripts
 * Included dynamically based on subscription status.
 */

// Do not load ads on localhost (handles ports like localhost:8000)
$hostPart = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
if ($hostPart === 'localhost' || $hostPart === '127.0.0.1') {
    return;
}
?>
<!-- Monetag MultiTag -->
<script>(function(s){s.dataset.zone='10752105',s.src='https://nap5k.com/tag.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>

<!-- Monetag Vignette -->
<script>(function(s){s.dataset.zone='10752115',s.src='https://izcle.com/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
