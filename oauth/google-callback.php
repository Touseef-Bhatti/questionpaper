<?php
/**
 * Google OAuth Callback Handler
 * Processes the OAuth callback from Google and handles user login/registration
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/google_oauth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if we have an authorization code
if (!isset($_GET['code'])) {
    $error = $_GET['error'] ?? 'No authorization code received';
    error_log('Google OAuth Error: ' . $error);
header('Location: /auth/login.php?error=' . urlencode('Google authentication failed'));
    exit;
}

try {
    // Exchange authorization code for access token
    $tokenData = GoogleOAuthConfig::exchangeCodeForToken($_GET['code']);
    
    if (!$tokenData || !isset($tokenData['access_token'])) {
        throw new Exception('Failed to get access token from Google');
    }
    
    // Get user information from Google
    $userInfo = GoogleOAuthConfig::getUserInfo($tokenData['access_token']);
    
    if (!$userInfo || !isset($userInfo['email'])) {
        throw new Exception('Failed to get user information from Google');
    }
    
    // Extract user data
    $googleId = $userInfo['id'];
    $email = $userInfo['email'];
    $name = $userInfo['name'] ?? $email;
    $picture = $userInfo['picture'] ?? null;
    
    // Check if user already exists with this Google ID
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE google_id = ? LIMIT 1");
    $stmt->bind_param('s', $googleId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        // User exists, log them in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = 'user';
        
        header('Location: /index.php');
        exit;
    }
    
    // Check if user exists with this email but no Google ID (linking accounts)
    $stmt = $conn->prepare("SELECT id, name, email, oauth_provider FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $existingUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existingUser) {
        // Link Google account to existing user
        $stmt = $conn->prepare("UPDATE users SET google_id = ?, oauth_provider = 'google' WHERE id = ?");
        $stmt->bind_param('si', $googleId, $existingUser['id']);
        $stmt->execute();
        $stmt->close();
        
        // Log them in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $existingUser['id'];
        $_SESSION['name'] = $existingUser['name'];
        $_SESSION['email'] = $existingUser['email'];
        $_SESSION['role'] = 'user';
        
        header('Location: /index.php');
        exit;
    }
    
    // Create new user with Google account
    $stmt = $conn->prepare("INSERT INTO users (name, email, google_id, oauth_provider, password) VALUES (?, ?, ?, 'google', NULL)");
    $stmt->bind_param('sss', $name, $email, $googleId);
    
    if ($stmt->execute()) {
        $newUserId = $conn->insert_id;
        $stmt->close();
        
        // Log them in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'user';
        
        header('Location: /index.php');
        exit;
    } else {
        throw new Exception('Failed to create user account');
    }
    
} catch (Exception $e) {
    error_log('Google OAuth Callback Error: ' . $e->getMessage());
header('Location: /auth/login.php?error=' . urlencode('Authentication failed. Please try again.'));
    exit;
}
?>
