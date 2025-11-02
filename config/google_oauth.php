<?php
/**
 * Google OAuth Configuration
 * Configure your Google OAuth settings here
 */

require_once __DIR__ . '/env.php';

class GoogleOAuthConfig 
{
    public static function getClientId() 
    {
        // Add your Google OAuth Client ID to .env file or return directly
        return EnvLoader::get('GOOGLE_CLIENT_ID', 'your-google-client-id.apps.googleusercontent.com');
    }
    
    public static function getClientSecret() 
    {
        // Add your Google OAuth Client Secret to .env file or return directly
        return EnvLoader::get('GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
    }
    
    public static function getRedirectUri() 
    {
        // Construct redirect URI based on current domain
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/oauth/google-callback.php';
    }
    
    public static function getAuthUrl($state = null) 
    {
        $params = [
            'client_id' => self::getClientId(),
            'redirect_uri' => self::getRedirectUri(),
            'scope' => 'email profile',
            'response_type' => 'code',
            'access_type' => 'online',
            'prompt' => 'select_account'
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    public static function exchangeCodeForToken($code) 
    {
        $data = [
            'client_id' => self::getClientId(),
            'client_secret' => self::getClientSecret(),
            'redirect_uri' => self::getRedirectUri(),
            'grant_type' => 'authorization_code',
            'code' => $code
        ];
        
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    public static function getUserInfo($accessToken) 
    {
        $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
}
?>
