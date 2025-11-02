<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

try {
    // Check if email exists in pending_users table
    $stmt = $conn->prepare("SELECT id, token, created_at FROM pending_users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $pendingUser = $result->fetch_assoc();
    $stmt->close();
    
    if (!$pendingUser) {
        echo json_encode(['error' => 'No pending registration found for this email']);
        exit;
    }
    
    // Check if we can resend (minimum 1 hour gap)
    $createdAt = strtotime($pendingUser['created_at']);
    $hoursSince = (time() - $createdAt) / 3600;
    
    if ($hoursSince < 1) {
        $minutesLeft = round((1 - $hoursSince) * 60);
        echo json_encode([
            'error' => "Please wait $minutesLeft more minutes before requesting another verification email"
        ]);
        exit;
    }
    
    // Generate new token and update
    $newToken = bin2hex(random_bytes(32));
    $stmt = $conn->prepare("UPDATE pending_users SET token = ?, created_at = NOW() WHERE email = ?");
    $stmt->bind_param('ss', $newToken, $email);
    $stmt->execute();
    $stmt->close();
    
    // Send verification email
    require_once 'phpmailer_mailer.php';
    if (sendVerificationEmail($email, $newToken)) {
        echo json_encode([
            'success' => true,
            'message' => 'Verification email sent successfully! Please check your inbox.'
        ]);
    } else {
        echo json_encode(['error' => 'Failed to send verification email. Please try again later.']);
    }
    
} catch (Exception $e) {
    error_log("Resend verification error: " . $e->getMessage());
    echo json_encode(['error' => 'Unable to resend verification email']);
}
?>
