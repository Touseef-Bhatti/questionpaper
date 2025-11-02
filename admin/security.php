<?php
// admin/security.php - Security helper functions for admin panel

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Check if user is authenticated as admin
 */
function requireAdminAuth() {
    if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Check if user is super admin
 */
function requireSuperAdmin() {
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate and sanitize integer input
 */
function validateInt($value, $min = null, $max = null) {
    $int = filter_var($value, FILTER_VALIDATE_INT);
    if ($int === false) return false;
    
    if ($min !== null && $int < $min) return false;
    if ($max !== null && $int > $max) return false;
    
    return $int;
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Log admin actions for audit trail
 */
function logAdminAction($action, $details = '') {
    global $conn;
    
    if (!isset($_SESSION['admin_id'])) return;
    
    $adminId = (int)$_SESSION['admin_id'];
    $action = $conn->real_escape_string($action);
    $details = $conn->real_escape_string($details);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $sql = "INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent, created_at) 
            VALUES ($adminId, '$action', '$details', '$ip', '$userAgent', NOW())";
    
    $conn->query($sql);
}

/**
 * Rate limiting for admin actions
 */
function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
    $key = "rate_limit_{$action}_" . ($_SESSION['admin_id'] ?? $_SERVER['REMOTE_ADDR']);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + $timeWindow];
    }
    
    if (time() > $_SESSION[$key]['reset_time']) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + $timeWindow];
    }
    
    if ($_SESSION[$key]['count'] >= $maxAttempts) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * Secure redirect with validation
 */
function secureRedirect($url) {
    // Only allow redirects to admin pages or main site
    $allowedDomains = [
        $_SERVER['HTTP_HOST'],
        'localhost',
        '127.0.0.1'
    ];
    
    $parsedUrl = parse_url($url);
    if (isset($parsedUrl['host']) && !in_array($parsedUrl['host'], $allowedDomains)) {
        $url = 'dashboard.php';
    }
    
    header("Location: $url");
    exit;
}

/**
 * Clean up old session data
 */
function cleanupOldSessions() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// Auto-cleanup old sessions
cleanupOldSessions();
