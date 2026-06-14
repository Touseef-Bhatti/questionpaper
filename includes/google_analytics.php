<?php
if (defined('ALH_GOOGLE_ANALYTICS_RENDERED')) {
    return;
}
define('ALH_GOOGLE_ANALYTICS_RENDERED', true);

$hostPart = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
$isLocalHost = in_array($hostPart, ['', 'localhost', '127.0.0.1'], true);

if (!$isLocalHost):
?>
<script>
(function () {
    var measurementId = 'G-RNM8N7JBGM';
    var loaded = false;

    function readConsent() {
        var match = document.cookie.match(/(?:^|;\s*)alh_cookie_consent=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    function loadAnalytics() {
        if (loaded || readConsent() !== 'accepted') return;
        loaded = true;

        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () {
            window.dataLayer.push(arguments);
        };
        window.gtag('js', new Date());
        window.gtag('config', measurementId, { anonymize_ip: true });

        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(measurementId);
        document.head.appendChild(script);
    }

    window.addEventListener('alh-consent-updated', loadAnalytics);
    loadAnalytics();
})();
</script>
<?php endif; ?>
