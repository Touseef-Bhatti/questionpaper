<?php
if (defined('ALH_GOOGLE_ANALYTICS_RENDERED')) {
    return;
}
define('ALH_GOOGLE_ANALYTICS_RENDERED', true);

$hostPart = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
$isLocalHost = in_array($hostPart, ['', 'localhost', '127.0.0.1'], true);

if (!$isLocalHost):
?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-RNM8N7JBGM"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-RNM8N7JBGM');
</script>
<?php endif; ?>
