<?php
/**
 * Manual Payment Verification Script
 * Use this script carefully for emergency payment verification
 * Usage: php manual_verify_payment.php --order-id=QPG_123456789 --admin-id=1
 */

session_start();
require_once 'db_connect.php';
require_once 'services/PaymentService.php';

// Security check - only allow admin access or CLI
if (php_sapi_name() !== 'cli') {
    // Web access - check admin authentication
    if (!isset($_SESSION['admin_id'])) {
        http_response_code(403);
        die('Access denied. Admin authentication required.');
    }
    $adminUserId = $_SESSION['admin_id'];
    $orderId = $_GET['order_id'] ?? $_POST['order_id'] ?? null;
} else {
    // CLI access - parse command line arguments
    $options = getopt('', ['order-id:', 'admin-id:']);
    $orderId = $options['order-id'] ?? null;
    $adminUserId = $options['admin-id'] ?? 1;
}

if (empty($orderId)) {
    if (php_sapi_name() === 'cli') {
        echo "Usage: php manual_verify_payment.php --order-id=QPG_123456789 --admin-id=1\n";
        exit(1);
    } else {
        echo "<p style='color: red;'>❌ Order ID is required!</p>";
        exit;
    }
}

try {
    echo "<h2>Manual Payment Verification</h2>";
    echo "<p>Verifying payment: <strong>$orderId</strong></p>";
    
    $paymentService = new PaymentService();
    
    // Get payment details first
    $payment = $paymentService->getPaymentByOrderId($orderId);
    if (!$payment) {
        echo "<p style='color: red;'>❌ Payment not found!</p>";
        exit;
    }
    
    echo "<h3>Payment Details:</h3>";
    echo "<ul>";
    echo "<li><strong>Order ID:</strong> " . htmlspecialchars($payment['order_id']) . "</li>";
    echo "<li><strong>User:</strong> " . htmlspecialchars($payment['user_name']) . " (" . htmlspecialchars($payment['user_email']) . ")</li>";
    echo "<li><strong>Plan:</strong> " . htmlspecialchars($payment['plan_display_name']) . "</li>";
    echo "<li><strong>Amount:</strong> " . $payment['currency'] . " " . number_format($payment['amount'], 2) . "</li>";
    echo "<li><strong>Current Status:</strong> " . htmlspecialchars($payment['status']) . "</li>";
    echo "<li><strong>Created:</strong> " . $payment['created_at'] . "</li>";
    echo "</ul>";
    
    if ($payment['status'] === 'completed') {
        echo "<p style='color: green;'>✅ Payment is already completed!</p>";
    } else {
        echo "<h3>Processing Payment Verification...</h3>";
        
        // Manually verify the payment
        $result = $paymentService->processSuccessfulPayment($orderId);
        
        if ($result['success']) {
            echo "<p style='color: green;'><strong>✅ Payment verified successfully!</strong></p>";
            echo "<p>Subscription ID: " . ($result['subscription_id'] ?? 'N/A') . "</p>";
            echo "<p>User should now have access to the " . htmlspecialchars($payment['plan_display_name']) . " plan.</p>";
        } else {
            echo "<p style='color: red;'><strong>❌ Payment verification failed:</strong></p>";
            echo "<p>" . htmlspecialchars($result['error'] ?? 'Unknown error') . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
}

echo "<br><br><a href='subscription.php'>← Back to Subscription Page</a>";
?>
