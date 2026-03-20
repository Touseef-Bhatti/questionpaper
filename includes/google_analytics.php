<?php 
$hostPart = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
if ($hostPart !== 'localhost' && $hostPart !== '127.0.0.1'): 
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
