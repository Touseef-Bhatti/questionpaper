<?php
// admin/security.php - Security helper functions for admin panel

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_only_cookies' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

/**
 * Check if user is authenticated as admin
 */
if (!function_exists('requireAdminAuth')) {
    function requireAdminAuth() {
        if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
            header('Location: login.php');
            exit;
        }
    }
}

/**
 * Check if user is super admin
 */
if (!function_exists('requireSuperAdmin')) {
    function requireSuperAdmin() {
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
            header('Location: dashboard.php');
            exit;
        }
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
 * Get client IP address reliably with proxy validation
 */
function getAdminClientIP(): string {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    foreach ($candidates as $candidate) {
        if (!empty($candidate)) {
            // If comma-separated (e.g. proxies), take first IP
            $ips = explode(',', $candidate);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '127.0.0.1';
}

/**
 * Ensures the persistent rate limit table exists in the database
 */
function ensureRateLimitSchema(?mysqli $conn = null): void {
    if (!$conn) {
        global $conn;
    }
    if (!$conn) return;

    static $schemaEnsured = false;
    if ($schemaEnsured) return;

    $sql = "CREATE TABLE IF NOT EXISTS admin_rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action_key VARCHAR(64) NOT NULL,
        identifier VARCHAR(128) NOT NULL,
        attempts INT NOT NULL DEFAULT 1,
        first_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        locked_until TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY uniq_action_ident (action_key, identifier),
        INDEX idx_locked (locked_until),
        INDEX idx_last_attempt (last_attempt_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
    $schemaEnsured = true;
}

/**
 * Robust database-backed rate limiter for admin operations
 * Returns: ['allowed' => bool, 'remaining_seconds' => int, 'attempts' => int]
 */
function checkDbRateLimit(mysqli $conn, string $action, string $identifier, int $maxAttempts = 5, int $windowSeconds = 900, int $lockoutSeconds = 900): array {
    ensureRateLimitSchema($conn);
    $action = substr(trim($action), 0, 64);
    $identifier = substr(trim($identifier), 0, 128);
    $now = time();

    // Query existing rate limit record
    $stmt = $conn->prepare("SELECT id, attempts, UNIX_TIMESTAMP(first_attempt_at) as first_ts, UNIX_TIMESTAMP(last_attempt_at) as last_ts, UNIX_TIMESTAMP(locked_until) as locked_ts FROM admin_rate_limits WHERE action_key = ? AND identifier = ? LIMIT 1");
    if (!$stmt) {
        // Fallback to session rate limiting if DB query fails
        $allowed = checkRateLimit($action . '_' . $identifier, $maxAttempts, $windowSeconds);
        return ['allowed' => $allowed, 'remaining_seconds' => $allowed ? 0 : $windowSeconds, 'attempts' => 1];
    }

    $stmt->bind_param("ss", $action, $identifier);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        $lockedTs = (int)($row['locked_ts'] ?? 0);
        if ($lockedTs > $now) {
            // Currently locked out
            return [
                'allowed' => false,
                'remaining_seconds' => ($lockedTs - $now),
                'attempts' => (int)$row['attempts']
            ];
        }

        $firstTs = (int)($row['first_ts'] ?? 0);
        if (($now - $firstTs) > $windowSeconds) {
            // Window expired, reset counter
            $resetStmt = $conn->prepare("UPDATE admin_rate_limits SET attempts = 1, first_attempt_at = NOW(), last_attempt_at = NOW(), locked_until = NULL WHERE id = ?");
            if ($resetStmt) {
                $resetStmt->bind_param("i", $row['id']);
                $resetStmt->execute();
                $resetStmt->close();
            }
            return ['allowed' => true, 'remaining_seconds' => 0, 'attempts' => 1];
        }

        // Within active window
        $newAttempts = (int)$row['attempts'] + 1;
        if ($newAttempts > $maxAttempts) {
            // Threshold exceeded -> enforce lockout
            $lockUntil = $now + $lockoutSeconds;
            $lockStmt = $conn->prepare("UPDATE admin_rate_limits SET locked_until = FROM_UNIXTIME(?), attempts = ?, last_attempt_at = NOW() WHERE id = ?");
            if ($lockStmt) {
                $lockStmt->bind_param("iii", $lockUntil, $newAttempts, $row['id']);
                $lockStmt->execute();
                $lockStmt->close();
            }
            return [
                'allowed' => false,
                'remaining_seconds' => $lockoutSeconds,
                'attempts' => $newAttempts
            ];
        }

        // Increment attempts count
        $incStmt = $conn->prepare("UPDATE admin_rate_limits SET attempts = ?, last_attempt_at = NOW() WHERE id = ?");
        if ($incStmt) {
            $incStmt->bind_param("ii", $newAttempts, $row['id']);
            $incStmt->execute();
            $incStmt->close();
        }
        return ['allowed' => true, 'remaining_seconds' => 0, 'attempts' => $newAttempts];
    }

    // First attempt: insert new record
    $insStmt = $conn->prepare("INSERT INTO admin_rate_limits (action_key, identifier, attempts, first_attempt_at, last_attempt_at) VALUES (?, ?, 1, NOW(), NOW())");
    if ($insStmt) {
        $insStmt->bind_param("ss", $action, $identifier);
        $insStmt->execute();
        $insStmt->close();
    }
    return ['allowed' => true, 'remaining_seconds' => 0, 'attempts' => 1];
}

/**
 * Clears rate limit record upon successful operation (e.g. valid login)
 */
function clearDbRateLimit(mysqli $conn, string $action, string $identifier): void {
    ensureRateLimitSchema($conn);
    $action = substr(trim($action), 0, 64);
    $identifier = substr(trim($identifier), 0, 128);
    $stmt = $conn->prepare("DELETE FROM admin_rate_limits WHERE action_key = ? AND identifier = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $action, $identifier);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Rate limiting for admin actions (Session fallback)
 */
function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
    $key = "rate_limit_{$action}_" . ($_SESSION['admin_id'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'guest'));
    
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
        $_SERVER['HTTP_HOST'] ?? 'localhost',
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
    $isLoginPage = in_array(basename($_SERVER['PHP_SELF'] ?? ''), ['login.php']);
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        if (!$isLoginPage) {
            header('Location: login.php');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

// Auto-cleanup old sessions
cleanupOldSessions();
