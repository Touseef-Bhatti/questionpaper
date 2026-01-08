<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

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
    // Check if email exists in users table
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $userResult = $stmt->get_result();
    $userExists = $userResult->num_rows > 0;
    $stmt->close();
    
    // Check if email exists in pending_users table
    $stmt = $conn->prepare("SELECT id, created_at FROM pending_users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $pendingResult = $stmt->get_result();
    $pendingUser = $pendingResult->fetch_assoc();
    $stmt->close();
    
    if ($userExists) {
        echo json_encode([
            'exists' => true,
            'type' => 'registered',
            'message' => 'This email is already registered. Please use the login page.'
        ]);
    } elseif ($pendingUser) {
        // Check if pending registration is recent (within 24 hours)
        $createdAt = strtotime($pendingUser['created_at']);
        $hoursSince = (time() - $createdAt) / 3600;
        
        if ($hoursSince < 24) {
            echo json_encode([
                'exists' => true,
                'type' => 'pending',
                'message' => 'A verification email was already sent to this address. Please check your email and verify your account.',
                'can_resend' => $hoursSince > 1, // Allow resend after 1 hour
                'hours_since' => round($hoursSince, 1)
            ]);
        } else {
            // Remove old pending registration
            $stmt = $conn->prepare("DELETE FROM pending_users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'exists' => false,
                'message' => 'Email is available for registration'
            ]);
        }
    } else {
        echo json_encode([
            'exists' => false,
            'message' => 'Email is available for registration'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Email check error: " . $e->getMessage());
    echo json_encode(['error' => 'Unable to check email availability']);
}
?>
