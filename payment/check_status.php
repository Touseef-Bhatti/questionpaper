<?php
// check_status.php - API endpoint to check payment status
session_start();
require_once '../db_connect.php';
require_once '../services/PaymentService.php';

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if order_id is provided
$orderId = $_GET['order_id'] ?? $_POST['order_id'] ?? null;
if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id parameter']);
    exit;
}

try {
    $paymentService = new PaymentService();
    
    // Get payment record
    $payment = $paymentService->getPaymentByOrderId($orderId);
    if (!$payment) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment record not found']);
        exit;
    }
    
    // Verify the payment belongs to the current user
    if ($payment['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    // Return payment status
    $response = [
        'status' => $payment['status'],
        'order_id' => $payment['order_id'],
        'amount' => $payment['amount'],
        'currency' => $payment['currency'],
        'plan_name' => $payment['plan_display_name'],
        'created_at' => $payment['created_at'],
        'processed_at' => $payment['processed_at'],
        'failure_reason' => $payment['failure_reason']
    ];
    
    // If payment is completed, include subscription info
    if ($payment['status'] === 'completed' && $payment['subscription_id']) {
        $response['subscription_id'] = $payment['subscription_id'];
    }
    
    echo json_encode(['success' => true, 'payment' => $response]);
    
} catch (Exception $e) {
    error_log("Payment status check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
