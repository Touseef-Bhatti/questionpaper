<?php
declare(strict_types=1);

// Ping endpoint for WAF testing.
// Returns 200 immediately and does not talk to Google.

header('Content-Type: text/plain; charset=utf-8');
http_response_code(200);

$hasCode = isset($_GET['code']) ? 'yes' : 'no';
$codeSample = isset($_GET['code']) ? substr((string)$_GET['code'], 0, 18) : '';

echo "OK\n";
echo "has_code={$hasCode}\n";
echo "code_sample={$codeSample}\n";
?>

