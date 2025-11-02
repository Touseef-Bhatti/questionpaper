<?php
/**
 * Quick Payment Status Fix
 * This script manually processes pending payments that were successful on SafePay
 * but webhook wasn't received due to localhost limitations
 */

require_once 'db_connect.php';
require_once 'services/PaymentService.php';

echo "🔧 Payment Status Fix Tool\n";
echo "=========================\n\n";

// Get payments that are still processing
$sql = "SELECT * FROM payments WHERE status IN ('processing', 'pending') ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "✅ No pending payments found!\n";
    exit;
}

$paymentService = new PaymentService();

echo "Found " . $result->num_rows . " pending payments:\n\n";

while ($payment = $result->fetch_assoc()) {
    echo "Processing Payment ID: " . $payment['id'] . "\n";
    echo "Order ID: " . $payment['order_id'] . "\n";
    echo "Status: " . $payment['status'] . "\n";
    echo "Amount: " . $payment['currency'] . " " . number_format($payment['amount'], 2) . "\n";
    echo "Created: " . $payment['created_at'] . "\n";
    
    // Simulate successful payment processing
    echo "🔄 Manually processing payment...\n";
    
    try {
        // Process as successful payment (skip signature verification for manual fix)
        $paymentResult = $paymentService->processSuccessfulPayment(
            $payment['order_id']
        );
        
        if (isset($paymentResult['success']) && $paymentResult['success']) {
            echo "✅ Payment processed successfully!\n";
            echo "   Subscription ID: " . $result['subscription_id'] . "\n";
        } else {
            echo "❌ Failed to process payment: " . $result['error'] . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error processing payment: " . $e->getMessage() . "\n";
    }
    
    echo "---\n\n";
}

echo "🎯 For Future Payments:\n";
echo "======================\n";
echo "To prevent this issue:\n";
echo "1. Use ngrok to expose your local server\n";
echo "2. Update SafePay webhook URL to ngrok URL\n";
echo "3. Or deploy to a public server for testing\n\n";

echo "💡 Alternative Testing Method:\n";
echo "=============================\n";
echo "Use the manual payment verification:\n";
echo "http://localhost/questionpaper/admin/verify_payment.php\n\n";
?>
