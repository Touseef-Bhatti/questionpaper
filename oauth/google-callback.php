<?php
/**
 * Google OAuth Callback Handler
 * Processes the OAuth callback from Google and handles user login/registration
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/google_oauth.php';
require_once __DIR__ . '/../services/SubscriptionService.php';

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
    
    // Better name extraction: try 'name' first, then 'given_name', finally fallback to email prefix
    $name = $userInfo['name'] ?? null;
    
    // If name is missing or looks like an email, try alternatives
    if (!$name || filter_var($name, FILTER_VALIDATE_EMAIL)) {
        if (isset($userInfo['given_name']) && !empty($userInfo['given_name'])) {
            $name = $userInfo['given_name'] . (isset($userInfo['family_name']) ? ' ' . $userInfo['family_name'] : '');
        } else {
            // Fallback: extract name from email (e.g., "john.doe" from "john.doe@gmail.com")
            $name = explode('@', $email)[0];
            $name = ucwords(str_replace(['.', '_', '-'], ' ', $name));
        }
    }
    
    $picture = $userInfo['picture'] ?? null;
    
    // Check if user already exists with this Google ID
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE google_id = ? LIMIT 1");
    $stmt->bind_param('s', $googleId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        // If existing user has an email as their name, update it to the better name we found
        if (filter_var($user['name'], FILTER_VALIDATE_EMAIL)) {
            $updateStmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
            $updateStmt->bind_param('si', $name, $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            $user['name'] = $name; // Update local variable for session
        }

        // User exists, log them in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = 'user';
        
        // Auto-cleanup expired subscriptions on login
        $subService = new SubscriptionService($conn);
        $subService->cleanupExpiredSubscriptions($user['id']);
        
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
        // Also update name if it's currently an email
        if (filter_var($existingUser['name'], FILTER_VALIDATE_EMAIL)) {
            $stmt = $conn->prepare("UPDATE users SET google_id = ?, oauth_provider = 'google', name = ? WHERE id = ?");
            $stmt->bind_param('ssi', $googleId, $name, $existingUser['id']);
            $existingUser['name'] = $name; // Update for session
        } else {
            $stmt = $conn->prepare("UPDATE users SET google_id = ?, oauth_provider = 'google' WHERE id = ?");
            $stmt->bind_param('si', $googleId, $existingUser['id']);
        }
        $stmt->execute();
        $stmt->close();
        
        // Log them in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $existingUser['id'];
        $_SESSION['name'] = $existingUser['name'];
        $_SESSION['email'] = $existingUser['email'];
        $_SESSION['role'] = 'user';
        
        // Auto-cleanup expired subscriptions on login
        $subService = new SubscriptionService($conn);
        $subService->cleanupExpiredSubscriptions($existingUser['id']);
        
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
