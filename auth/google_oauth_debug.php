<?php
declare(strict_types=1);

// Debug endpoint:
// - Shows what redirect URI and auth URL this app generates.
// - Does not perform any token exchange (safe for repeated checks).

require_once __DIR__ . '/../config/google_oauth.php';

header('Content-Type: text/plain; charset=utf-8');

$host = $_SERVER['HTTP_HOST'] ?? 'unknown';
$https = (isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) === 'on') ? 'on' : 'off';

$redirectUri = GoogleOAuthConfig::getRedirectUri();
$authUrl = GoogleOAuthConfig::getAuthUrl('login');
$clientId = GoogleOAuthConfig::getClientId();

echo "host={$host}\n";
echo "https={$https}\n";
echo "computed_redirect_uri={$redirectUri}\n";
echo "oauth_client_id={$clientId}\n";
echo "computed_auth_url={$authUrl}\n";

?>

