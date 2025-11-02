<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Please enter a valid email address']);
    exit;
}

try {
    // Create newsletter table if it doesn't exist
    $conn->query("
        CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            status ENUM('subscribed', 'unsubscribed') DEFAULT 'subscribed',
            source VARCHAR(100) DEFAULT 'website',
            subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            unsubscribed_at TIMESTAMP NULL,
            INDEX idx_email (email),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id, status FROM newsletter_subscribers WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        if ($existing['status'] === 'subscribed') {
            echo json_encode(['message' => 'You are already subscribed to our newsletter!']);
        } else {
            // Resubscribe
            $stmt = $conn->prepare("UPDATE newsletter_subscribers SET status = 'subscribed', subscribed_at = NOW(), unsubscribed_at = NULL WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Welcome back! You have been resubscribed to our newsletter.']);
        }
    } else {
        // New subscription
        $stmt = $conn->prepare("INSERT INTO newsletter_subscribers (email, source) VALUES (?, 'website')");
        $stmt->bind_param('s', $email);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Send welcome email (optional)
            // require_once 'phpmailer_mailer.php';
            // sendWelcomeNewsletterEmail($email);
            
            echo json_encode(['success' => true, 'message' => 'Thank you for subscribing! You will receive our latest educational content and updates.']);
        } else {
            echo json_encode(['error' => 'Failed to subscribe. Please try again later.']);
        }
    }
    
} catch (Exception $e) {
    error_log("Newsletter signup error: " . $e->getMessage());
    echo json_encode(['error' => 'Something went wrong. Please try again later.']);
}
?>
