<?php
/**
 * Payment Issue Debugging Script
 * This script helps identify why payments show as successful on SafePay but fail in your application
 */

session_start();
require_once 'db_connect.php';
require_once 'services/PaymentService.php';
require_once 'config/env.php';

// Get recent payments
$sql = "SELECT * FROM payments ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($sql);

echo "🔍 Payment Debugging Report\n";
echo "========================\n\n";

echo "📊 Recent Payment Records:\n";
echo "-------------------------\n";

if ($result->num_rows > 0) {
    while ($payment = $result->fetch_assoc()) {
        echo "Payment ID: " . $payment['id'] . "\n";
        echo "Order ID: " . $payment['order_id'] . "\n";
        echo "Status: " . $payment['status'] . "\n";
        echo "Amount: " . $payment['currency'] . " " . number_format($payment['amount'], 2) . "\n";
        echo "Created: " . $payment['created_at'] . "\n";
        echo "Processed: " . ($payment['processed_at'] ?? 'Not processed') . "\n";
        echo "Failure Reason: " . ($payment['failure_reason'] ?? 'None') . "\n";
        echo "SafePay Token: " . ($payment['safepay_token'] ? 'Present' : 'Missing') . "\n";
        echo "Tracker: " . ($payment['tracker'] ?? 'Missing') . "\n";
        echo "---\n\n";
    }
} else {
    echo "No payment records found.\n\n";
}

// Check webhook logs
$sql = "SELECT * FROM payment_webhooks ORDER BY created_at DESC LIMIT 3";
$webhookResult = $conn->query($sql);

echo "🔗 Recent Webhook Activity:\n";
echo "--------------------------\n";

if ($webhookResult && $webhookResult->num_rows > 0) {
    while ($webhook = $webhookResult->fetch_assoc()) {
        echo "Order ID: " . ($webhook['order_id'] ?? 'Unknown') . "\n";
        echo "Type: " . $webhook['webhook_type'] . "\n";
        echo "Verified: " . ($webhook['verified'] ? 'Yes' : 'No') . "\n";
        echo "Processed: " . ($webhook['processed'] ? 'Yes' : 'No') . "\n";
        echo "Received: " . $webhook['created_at'] . "\n";
        echo "Payload: " . (strlen($webhook['payload']) > 100 ? substr($webhook['payload'], 0, 100) . '...' : $webhook['payload']) . "\n";
        echo "---\n\n";
    }
} else {
    echo "No webhook activity found.\n\n";
}

// Test SafePay configuration
echo "⚙️ SafePay Configuration Test:\n";
echo "-----------------------------\n";

try {
    $config = include 'config/safepay.php';
    
    echo "Environment: " . $config['environment'] . "\n";
    echo "API Key: " . (strlen($config['apiKey']) > 10 ? substr($config['apiKey'], 0, 15) . '...' : 'NOT SET') . "\n";
    echo "V1 Secret: " . (strlen($config['v1Secret']) > 10 ? substr($config['v1Secret'], 0, 15) . '...' : 'NOT SET') . "\n";
    echo "Webhook Secret: " . (strlen($config['webhookSecret']) > 10 ? substr($config['webhookSecret'], 0, 15) . '...' : 'NOT SET') . "\n";
    echo "Success URL: " . $config['success_url'] . "\n";
    echo "Webhook URL: " . $config['webhook_url'] . "\n";
    
    // Validate configuration
    $config['validate_config']();
    echo "✅ Configuration is valid\n\n";
    
} catch (Exception $e) {
    echo "❌ Configuration error: " . $e->getMessage() . "\n\n";
}

// Test SafePay connection
echo "🔗 SafePay Connection Test:\n";
echo "-------------------------\n";

try {
    $paymentService = new PaymentService();
    echo "✅ PaymentService initialized successfully\n";
    
    // Try to get a token for testing (smallest amount)
    $testAmount = 1.0; // PKR 1.00
    
    // Note: We can't actually test token generation without making a real request
    echo "⚠️ Actual SafePay API test would require creating a real payment\n";
    
} catch (Exception $e) {
    echo "❌ PaymentService error: " . $e->getMessage() . "\n";
}

echo "\n🎯 Common Issues and Solutions:\n";
echo "==============================\n";
echo "1. ❌ 'Unable to authorize transaction' usually means:\n";
echo "   - Wrong API credentials\n";
echo "   - Incorrect environment (sandbox vs production)\n";
echo "   - Invalid amount format\n";
echo "   - Missing required fields\n\n";

echo "2. ✅ If payment shows in SafePay but fails here:\n";
echo "   - Webhook not being received\n";
echo "   - Webhook signature verification failing\n";
echo "   - Webhook URL not accessible\n\n";

echo "3. 🔧 To fix the issue:\n";
echo "   - Verify SafePay credentials match your dashboard\n";
echo "   - Check webhook URL is publicly accessible\n";
echo "   - Test webhook signature verification\n";
echo "   - Enable SafePay logs in dashboard\n\n";

echo "📋 Next Steps:\n";
echo "=============\n";
echo "1. Check your SafePay dashboard for API credentials\n";
echo "2. Verify webhook URL in SafePay settings\n";
echo "3. Test a small payment (PKR 1.00)\n";
echo "4. Monitor webhook logs during payment\n";
echo "5. Check SafePay transaction logs\n\n";

echo "🔐 Environment Check:\n";
echo "===================\n";
echo "Current environment: " . (EnvLoader::get('SAFEPAY_ENVIRONMENT', 'not-set')) . "\n";
echo "Expected for testing: sandbox\n";
echo "API Key starts with: " . (EnvLoader::get('SAFEPAY_API_KEY') ? substr(EnvLoader::get('SAFEPAY_API_KEY'), 0, 6) : 'NOT SET') . "\n";

?>
