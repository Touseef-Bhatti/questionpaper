<?php
// Enhanced Webhook handler for SafePay payment notifications
// This file processes SafePay webhooks securely with rate limiting

require_once '../db_connect.php';
require_once '../services/PaymentService.php';
require_once '../config/env.php';

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Rate limiting function
function checkRateLimit($ipAddress, $endpoint, $limit = 60, $window = 3600) {
    global $conn;
    
    $sql = "SELECT request_count, window_start FROM rate_limits 
            WHERE ip_address = ? AND endpoint = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $ipAddress, $endpoint);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $windowStart = strtotime($row['window_start']);
        $currentTime = time();
        
        // Check if we're still in the same window
        if (($currentTime - $windowStart) < $window) {
            if ($row['request_count'] >= $limit) {
                return false; // Rate limit exceeded
            }
            
            // Increment request count
            $sql = "UPDATE rate_limits SET request_count = request_count + 1, updated_at = NOW() 
                    WHERE ip_address = ? AND endpoint = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $ipAddress, $endpoint);
            $stmt->execute();
        } else {
            // Reset window
            $sql = "UPDATE rate_limits SET request_count = 1, window_start = NOW(), updated_at = NOW() 
                    WHERE ip_address = ? AND endpoint = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $ipAddress, $endpoint);
            $stmt->execute();
        }
    } else {
        // Create new rate limit record
        $sql = "INSERT INTO rate_limits (ip_address, endpoint, request_count, window_start) 
                VALUES (?, ?, 1, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $ipAddress, $endpoint);
        $stmt->execute();
    }
    
    return true;
}

// Function to log webhook activity
function logWebhookActivity($message, $data = null) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    error_log('WEBHOOK: ' . json_encode($logData));
}

// Function to send response
function sendResponse($status, $message, $data = null) {
    http_response_code($status);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ]);
    exit;
}

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logWebhookActivity('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
        sendResponse(405, 'Method not allowed');
    }
    
    // Rate limiting check
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimit = EnvLoader::getInt('WEBHOOK_RATE_LIMIT', 60);
    
    if (!checkRateLimit($clientIP, 'webhook', $rateLimit, 3600)) {
        logWebhookActivity('Rate limit exceeded', ['ip' => $clientIP, 'limit' => $rateLimit]);
        sendResponse(429, 'Too many requests');
    }
    
    // Get the SafePay signature from headers
    $signature = $_SERVER['HTTP_X_SFPY_SIGNATURE'] ?? '';
    if (empty($signature)) {
        logWebhookActivity('Missing SafePay signature header');
        sendResponse(400, 'Missing signature header');
    }
    
    // Get the raw POST data
    $rawData = file_get_contents('php://input');
    if (empty($rawData)) {
        logWebhookActivity('Empty webhook payload');
        sendResponse(400, 'Empty payload');
    }
    
    // Log the incoming webhook (for debugging)
    logWebhookActivity('Webhook received', [
        'signature' => $signature,
        'payload_length' => strlen($rawData)
    ]);
    
    // Initialize payment service
    $paymentService = new PaymentService();
    
    // Process the webhook
    $result = $paymentService->processWebhook($rawData, $signature);
    
    if (isset($result['success']) && $result['success']) {
        logWebhookActivity('Webhook processed successfully', $result);
        sendResponse(200, 'Webhook processed successfully', $result);
    } else {
        $error = $result['error'] ?? 'Unknown error processing webhook';
        logWebhookActivity('Webhook processing failed', ['error' => $error]);
        sendResponse(400, $error);
    }
    
} catch (Exception $e) {
    // Log the exception
    logWebhookActivity('Webhook exception', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Send error response
    sendResponse(500, 'Internal server error: ' . $e->getMessage());
}

// This should never be reached, but just in case
sendResponse(500, 'Unexpected error');
