<?php
// Google Analytics Integration
// Replace 'GA_TRACKING_ID' with your actual Google Analytics Tracking ID

$ga_tracking_id = 'GA_TRACKING_ID'; // Replace with your GA tracking ID

if ($ga_tracking_id !== 'GA_TRACKING_ID'): ?>
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $ga_tracking_id ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?= $ga_tracking_id ?>', {
    anonymize_ip: true,
    cookie_expires: 63072000, // 2 years
    allow_google_signals: false // Set to true if you want enhanced conversions
  });
</script>
<?php endif; ?>

<?php
// Google AdSense Integration
// Replace 'ca-pub-XXXXXXXXXXXXXXXX' with your actual AdSense Publisher ID

$adsense_pub_id = 'ca-pub-XXXXXXXXXXXXXXXX'; // Replace with your AdSense Publisher ID

if ($adsense_pub_id !== 'ca-pub-XXXXXXXXXXXXXXXX'): ?>
<!-- Google AdSense -->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= $adsense_pub_id ?>" 
        crossorigin="anonymous"></script>
<?php endif; ?>
