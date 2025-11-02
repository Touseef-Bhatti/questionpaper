<?php
require_once __DIR__ . '/../db_connect.php';
// Include all Safepay classes
require_once __DIR__ . '/../safepay-php-main/src/Base.php';
require_once __DIR__ . '/../safepay-php-main/src/Lib/Requestor.php';
require_once __DIR__ . '/../safepay-php-main/src/Payments.php';
require_once __DIR__ . '/../safepay-php-main/src/Checkout.php';
require_once __DIR__ . '/../safepay-php-main/src/Verify.php';
require_once __DIR__ . '/../safepay-php-main/src/Safepay.php';
require_once __DIR__ . '/SubscriptionService.php';

use Safepay\Safepay;

class PaymentService 
{
    private $conn;
    private $safepay;
    private $config;
    private $subscriptionService;
    
    public function __construct($connection = null) 
    {
        global $conn;
        $this->conn = $connection ?: $conn;
        $this->config = require __DIR__ . '/../config/safepay.php';
        
        // Validate configuration
        if (isset($this->config['validate_config'])) {
            $this->config['validate_config']();
        }
        
        // Initialize SafePay
        $this->safepay = new Safepay([
            'environment' => $this->config['environment'],
            'apiKey' => $this->config['apiKey'],
            'v1Secret' => $this->config['v1Secret'],
            'webhookSecret' => $this->config['webhookSecret']
        ]);
        
        $this->subscriptionService = new SubscriptionService($this->conn);
    }
    
    /**
     * Create a payment order
     */
    public function createPaymentOrder($userId, $planId) 
    {
        $userId = intval($userId);
        $planId = intval($planId);
        
        // Get plan details
        $plan = $this->subscriptionService->getPlanById($planId);
        if (!$plan) {
            return ['error' => 'Invalid subscription plan'];
        }
        
        // Generate unique order ID
        $orderId = $this->config['order_prefix'] . time() . '_' . $userId . '_' . $planId;
        
        // Create payment record in database
        $paymentId = $this->createPaymentRecord($userId, $planId, $orderId, $plan['price']);
        if (!$paymentId) {
            return ['error' => 'Failed to create payment record'];
        }
        
        try {
            // Get SafePay token
            $tokenResponse = $this->safepay->payments->getToken([
                'amount' => floatval($plan['price']), // Use amount as-is (Safepay expects PKR, not paisa)
                'currency' => $this->config['currency']
            ]);
            
            if (!isset($tokenResponse['token'])) {
                $this->updatePaymentStatus($paymentId, 'failed', 'Failed to get SafePay token');
                return ['error' => 'Payment gateway error'];
            }
            
            $token = $tokenResponse['token'];
            
            // Update payment record with token
            $this->updatePaymentToken($paymentId, $token);
            
            // Create checkout link
            $checkoutResponse = $this->safepay->checkout->create([
                'token' => $token,
                'order_id' => $orderId,
                'source' => 'custom',
                'webhooks' => 'true',
                'success_url' => $this->config['success_url'] . '?order_id=' . urlencode($orderId),
                'cancel_url' => $this->config['cancel_url'] . '?order_id=' . urlencode($orderId)
            ]);
            
            if ($checkoutResponse['result'] === 'success') {
                // Update payment status to processing
                $this->updatePaymentStatus($paymentId, 'processing');
                
                return [
                    'success' => true,
                    'payment_id' => $paymentId,
                    'order_id' => $orderId,
                    'checkout_url' => $checkoutResponse['redirect'],
                    'plan' => $plan
                ];
            } else {
                $this->updatePaymentStatus($paymentId, 'failed', 'Checkout creation failed');
                return ['error' => 'Failed to create checkout'];
            }
            
        } catch (Exception $e) {
            $this->updatePaymentStatus($paymentId, 'failed', $e->getMessage());
            return ['error' => 'Payment processing error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Create payment record in database
     */
    private function createPaymentRecord($userId, $planId, $orderId, $amount) 
    {
        $sql = "INSERT INTO payments (user_id, plan_id, order_id, amount, currency, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iisds", $userId, $planId, $orderId, $amount, $this->config['currency']);
        
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update payment token
     */
    private function updatePaymentToken($paymentId, $token) 
    {
        $sql = "UPDATE payments SET safepay_token = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $token, $paymentId);
        $stmt->execute();
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus($paymentId, $status, $failureReason = null) 
    {
        $processedAt = in_array($status, ['completed', 'failed', 'cancelled']) ? date('Y-m-d H:i:s') : null;
        
        $sql = "UPDATE payments SET status = ?, failure_reason = ?, processed_at = ?, updated_at = NOW() 
                WHERE id = ?";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssi", $status, $failureReason, $processedAt, $paymentId);
        $stmt->execute();
    }
    
    /**
     * Check payment status directly from Safepay
     */
    public function checkPaymentStatusFromGateway($orderId) 
    {
        $payment = $this->getPaymentByOrderId($orderId);
        if (!$payment) {
            return ['error' => 'Payment record not found'];
        }
        
        // If payment is already completed, return success
        if ($payment['status'] === 'completed') {
            return ['success' => true, 'status' => 'completed', 'payment' => $payment];
        }
        
        // Check if payment was made but not yet processed via webhook
        // This helps handle cases where webhook might be delayed
        return ['success' => false, 'status' => $payment['status'], 'payment' => $payment];
    }
    
    /**
     * Process successful payment
     */
    public function processSuccessfulPayment($orderId, $tracker = null, $signature = null) 
    {
        // Get payment record first
        $payment = $this->getPaymentByOrderId($orderId);
        if (!$payment) {
            return ['error' => 'Payment record not found'];
        }
        
        // If signature verification is required and provided, verify it
        if ($tracker && $signature) {
            if (!$this->verifyPaymentSignature($tracker, $signature)) {
                return ['error' => 'Invalid payment signature'];
            }
        }
        
        // Check if already processed
        if ($payment['status'] === 'completed') {
            return [
                'success' => true, 
                'message' => 'Payment already processed', 
                'payment' => $payment,
                'subscription_id' => $payment['subscription_id'] ?? null
            ];
        }
        
        // Update payment as completed
        $this->updatePaymentStatus($payment['id'], 'completed');
        if ($tracker) {
            $this->updatePaymentTracker($payment['id'], $tracker);
        }
        
        // Create subscription
        $subscriptionId = $this->subscriptionService->createSubscription(
            $payment['user_id'], 
            $payment['plan_id'], 
            $payment['id']
        );
        
        if ($subscriptionId) {
            // Link subscription to payment
            $this->linkSubscriptionToPayment($payment['id'], $subscriptionId);
            
            // Send confirmation email
            $this->sendPaymentConfirmationEmail($payment);
            
            return [
                'success' => true, 
                'subscription_id' => $subscriptionId,
                'payment' => $payment
            ];
        } else {
            // Revert payment status if subscription creation fails
            $this->updatePaymentStatus($payment['id'], 'failed', 'Failed to create subscription');
            return ['error' => 'Failed to create subscription'];
        }
    }
    
    /**
     * Link subscription to payment record
     */
    private function linkSubscriptionToPayment($paymentId, $subscriptionId) 
    {
        $sql = "UPDATE payments SET subscription_id = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $subscriptionId, $paymentId);
        $stmt->execute();
    }
    
    /**
     * Verify payment signature
     */
    private function verifyPaymentSignature($tracker, $signature) 
    {
        try {
            return $this->safepay->verify->signature($tracker, $signature);
        } catch (Exception $e) {
            error_log("Payment signature verification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get payment by order ID
     */
    public function getPaymentByOrderId($orderId) 
    {
        $sql = "SELECT p.*, sp.name as plan_name, sp.display_name as plan_display_name, u.name as user_name, u.email as user_email
                FROM payments p 
                JOIN subscription_plans sp ON p.plan_id = sp.id 
                JOIN users u ON p.user_id = u.id 
                WHERE p.order_id = ?";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }
    
    /**
     * Update payment tracker
     */
    private function updatePaymentTracker($paymentId, $tracker) 
    {
        $sql = "UPDATE payments SET tracker = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $tracker, $paymentId);
        $stmt->execute();
    }
    
    /**
     * Update payment with webhook data
     */
    private function updatePaymentWebhookData($paymentId, $rawPayload, $webhookData) 
    {
        // Extract payment method from webhook data
        $paymentMethod = $webhookData['payment_method'] ?? $webhookData['method'] ?? 'unknown';
        
        $sql = "UPDATE payments SET 
                webhook_data = ?, 
                payment_method = ?,
                updated_at = NOW()
                WHERE id = ?";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssi", $rawPayload, $paymentMethod, $paymentId);
        $stmt->execute();
    }
    
    /**
     * Update payment with SafePay response data
     */
    public function updatePaymentSafepayResponse($paymentId, $responseData) 
    {
        $responseJson = is_array($responseData) ? json_encode($responseData) : $responseData;
        
        $sql = "UPDATE payments SET safepay_response = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $responseJson, $paymentId);
        $stmt->execute();
    }
    
    /**
     * Process webhook
     */
    public function processWebhook($payload, $signature) 
    {
        // Log webhook
        $this->logWebhook($payload, $signature);
        
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($payload, $signature)) {
            return ['error' => 'Invalid webhook signature'];
        }
        
        $data = json_decode($payload, true);
        if (!$data || !isset($data['data'])) {
            return ['error' => 'Invalid webhook payload'];
        }
        
        $webhookData = $data['data'];
        $orderId = $webhookData['order_id'] ?? null;
        $status = $webhookData['status'] ?? null;
        
        if (!$orderId) {
            return ['error' => 'Missing order ID in webhook'];
        }
        
        // Get payment record
        $payment = $this->getPaymentByOrderId($orderId);
        if (!$payment) {
            return ['error' => 'Payment record not found for webhook'];
        }
        
        // Update payment with webhook data
        $this->updatePaymentWebhookData($payment['id'], $payload, $webhookData);
        
        // Update webhook as processed
        $this->updateWebhookProcessed($orderId, true);
        
        // Process based on status
        switch (strtolower($status)) {
            case 'completed':
            case 'success':
                return $this->processSuccessfulPayment(
                    $orderId, 
                    $webhookData['tracker'] ?? '', 
                    $webhookData['signature'] ?? ''
                );
                
            case 'failed':
            case 'error':
                $this->updatePaymentStatus($payment['id'], 'failed', $webhookData['error'] ?? 'Payment failed');
                return ['success' => true, 'message' => 'Payment marked as failed'];
                
            case 'cancelled':
                $this->updatePaymentStatus($payment['id'], 'cancelled');
                return ['success' => true, 'message' => 'Payment marked as cancelled'];
                
            default:
                return ['error' => 'Unknown webhook status: ' . $status];
        }
    }
    
    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature($payload, $signature) 
    {
        try {
            return $this->safepay->verify->webhook($payload, $signature);
        } catch (Exception $e) {
            error_log("Webhook signature verification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log webhook
     */
    private function logWebhook($payload, $signature) 
    {
        $data = json_decode($payload, true);
        $orderId = isset($data['data']['order_id']) ? $data['data']['order_id'] : null;
        $webhookType = $data['type'] ?? 'unknown';
        
        $verified = $this->verifyWebhookSignature($payload, $signature) ? 1 : 0;
        
        $sql = "INSERT INTO payment_webhooks (order_id, webhook_type, payload, signature, verified, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssssi", $orderId, $webhookType, $payload, $signature, $verified);
        $stmt->execute();
    }
    
    /**
     * Update webhook as processed
     */
    private function updateWebhookProcessed($orderId, $processed = true) 
    {
        $sql = "UPDATE payment_webhooks SET processed = ? WHERE order_id = ?";
        $stmt = $this->conn->prepare($sql);
        $processedInt = $processed ? 1 : 0;
        $stmt->bind_param("is", $processedInt, $orderId);
        $stmt->execute();
    }
    
    /**
     * Check if user has pending payment for a plan
     */
    public function getUserPendingPayment($userId, $planId) 
    {
        $sql = "SELECT * FROM payments 
                WHERE user_id = ? AND plan_id = ? 
                AND status IN ('pending', 'processing') 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY created_at DESC LIMIT 1";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $planId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }
    
    /**
     * Get user's payment history
     */
    public function getUserPaymentHistory($userId, $limit = 10) 
    {
        $sql = "SELECT p.*, sp.display_name as plan_name 
                FROM payments p 
                JOIN subscription_plans sp ON p.plan_id = sp.id 
                WHERE p.user_id = ? 
                ORDER BY p.created_at DESC 
                LIMIT ?";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        
        return $payments;
    }
    
    /**
     * Send payment confirmation email
     */
    private function sendPaymentConfirmationEmail($payment) 
    {
        if (!$this->config['send_payment_emails']) return;
        
        // Implement email sending logic here
        // You can use PHPMailer or your existing email system
        
        $to = $payment['user_email'];
        $subject = "Payment Confirmation - " . $payment['plan_display_name'];
        
        $message = "
        <html>
        <body>
        <h2>Payment Confirmation</h2>
        <p>Dear " . htmlspecialchars($payment['user_name']) . ",</p>
        <p>Your payment has been successfully processed!</p>
        <p><strong>Plan:</strong> " . htmlspecialchars($payment['plan_display_name']) . "</p>
        <p><strong>Amount:</strong> " . $payment['currency'] . " " . number_format($payment['amount'], 2) . "</p>
        <p><strong>Order ID:</strong> " . htmlspecialchars($payment['order_id']) . "</p>
        <p><strong>Date:</strong> " . date('M d, Y H:i', strtotime($payment['created_at'])) . "</p>
        <p>Thank you for your subscription!</p>
        </body>
        </html>";
        
        // Add email sending code here
        // mail($to, $subject, $message, $headers);
    }
    
    /**
     * Cancel payment
     */
    public function cancelPayment($orderId, $reason = null) 
    {
        $payment = $this->getPaymentByOrderId($orderId);
        if (!$payment) {
            return ['error' => 'Payment not found'];
        }
        
        if (in_array($payment['status'], ['completed', 'cancelled'])) {
            return ['error' => 'Payment cannot be cancelled'];
        }
        
        $this->updatePaymentStatus($payment['id'], 'cancelled', $reason);
        return ['success' => true];
    }
    
    /**
     * Cleanup expired payments
     */
    public function cleanupExpiredPayments() 
    {
        // Mark payments as expired if they're pending/processing for more than payment_timeout
        $timeoutMinutes = $this->config['payment_timeout'] / 60; // Convert to minutes
        
        $sql = "UPDATE payments 
                SET status = 'expired', 
                    failure_reason = 'Payment timeout - not completed within allowed time',
                    processed_at = NOW(),
                    updated_at = NOW()
                WHERE status IN ('pending', 'processing') 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $timeoutMinutes);
        $result = $stmt->execute();
        
        return $stmt->affected_rows;
    }
    
    /**
     * Get payment statistics for admin
     */
    public function getPaymentStatistics($days = 30) 
    {
        $sql = "SELECT 
                    COUNT(*) as total_payments,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
                    COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_payments,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_payments,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue
                FROM payments 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Manual payment verification (for admin use)
     */
    public function manualVerifyPayment($orderId, $adminUserId) 
    {
        $payment = $this->getPaymentByOrderId($orderId);
        if (!$payment) {
            return ['error' => 'Payment not found'];
        }
        
        if ($payment['status'] === 'completed') {
            return ['error' => 'Payment already completed'];
        }
        
        // Manual verification - mark as completed
        $result = $this->processSuccessfulPayment($orderId);
        
        if ($result['success']) {
            // Log manual verification
            $this->logPaymentEvent('manual_verification', $payment['user_id'], $orderId, [
                'admin_id' => $adminUserId,
                'previous_status' => $payment['status'],
                'plan_id' => $payment['plan_id']
            ]);
        }
        
        return $result;
    }
    
    /**
     * Retry failed payment
     */
    public function retryPayment($orderId, $adminUserId = null) 
    {
        if (!$this->config['enable_payment_retry']) {
            return ['error' => 'Payment retry is disabled'];
        }
        
        $payment = $this->getPaymentByOrderId($orderId);
        if (!$payment) {
            return ['error' => 'Payment not found'];
        }
        
        if (!in_array($payment['status'], ['failed', 'expired'])) {
            return ['error' => 'Payment cannot be retried'];
        }
        
        // Check retry count
        $retryCount = $this->getPaymentRetryCount($payment['id']);
        if ($retryCount >= $this->config['max_payment_retries']) {
            return ['error' => 'Maximum retry attempts exceeded'];
        }
        
        // Reset payment status to pending
        $this->updatePaymentStatus($payment['id'], 'pending', null);
        $this->incrementPaymentRetryCount($payment['id']);
        
        // Log retry attempt
        $this->logPaymentEvent('payment_retry', $payment['user_id'], $orderId, [
            'retry_count' => $retryCount + 1,
            'admin_id' => $adminUserId,
            'previous_status' => $payment['status']
        ]);
        
        return ['success' => true, 'message' => 'Payment retry initiated'];
    }
    
    /**
     * Process refund
     */
    public function processRefund($orderId, $amount = null, $reason = null, $adminUserId = null) 
    {
        $payment = $this->getPaymentByOrderId($orderId);
        if (!$payment) {
            return ['error' => 'Payment not found'];
        }
        
        if ($payment['status'] !== 'completed') {
            return ['error' => 'Only completed payments can be refunded'];
        }
        
        $refundAmount = $amount ?: $payment['amount'];
        if ($refundAmount > $payment['amount']) {
            return ['error' => 'Refund amount cannot exceed payment amount'];
        }
        
        try {
            // Create refund record
            $refundId = $this->createRefundRecord($payment['id'], $refundAmount, $reason, $adminUserId);
            
            if ($refundId) {
                // Update payment status
                $this->updatePaymentStatus($payment['id'], 'refunded', $reason);
                
                // Deactivate associated subscription
                if ($payment['subscription_id']) {
                    $this->subscriptionService->cancelSubscription($payment['user_id']);
                }
                
                // Log refund
                $this->logPaymentEvent('payment_refunded', $payment['user_id'], $orderId, [
                    'refund_id' => $refundId,
                    'refund_amount' => $refundAmount,
                    'reason' => $reason,
                    'admin_id' => $adminUserId
                ]);
                
                return [
                    'success' => true, 
                    'refund_id' => $refundId,
                    'message' => 'Refund processed successfully'
                ];
            }
            
        } catch (Exception $e) {
            $this->logPaymentEvent('refund_failed', $payment['user_id'], $orderId, [
                'error' => $e->getMessage(),
                'admin_id' => $adminUserId
            ]);
            
            return ['error' => 'Refund processing failed: ' . $e->getMessage()];
        }
        
        return ['error' => 'Failed to create refund record'];
    }
    
    /**
     * Get advanced payment analytics
     */
    public function getAdvancedAnalytics($dateFrom = null, $dateTo = null) 
    {
        $dateFrom = $dateFrom ?: date('Y-m-01'); // Start of current month
        $dateTo = $dateTo ?: date('Y-m-d'); // Today
        
        // Revenue analytics
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_payments,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as daily_revenue,
                    AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_transaction_value
                FROM payments 
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $dailyStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Plan popularity
        $sql = "SELECT 
                    sp.display_name,
                    COUNT(*) as total_purchases,
                    COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as successful_purchases,
                    SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as plan_revenue
                FROM payments p
                JOIN subscription_plans sp ON p.plan_id = sp.id
                WHERE DATE(p.created_at) BETWEEN ? AND ?
                GROUP BY p.plan_id, sp.display_name
                ORDER BY successful_purchases DESC";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $planStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Conversion rates
        $sql = "SELECT 
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*) as conversion_rate,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) * 100.0 / COUNT(*) as failure_rate,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) * 100.0 / COUNT(*) as cancellation_rate
                FROM payments 
                WHERE DATE(created_at) BETWEEN ? AND ?";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $conversionStats = $stmt->get_result()->fetch_assoc();
        
        return [
            'daily_stats' => $dailyStats,
            'plan_stats' => $planStats,
            'conversion_stats' => $conversionStats,
            'period' => ['from' => $dateFrom, 'to' => $dateTo]
        ];
    }
    
    /**
     * Enhanced payment logging
     */
    private function logPaymentEvent($event, $userId, $orderId, $details = []) 
    {
        if (!$this->config['log_payments']) return;
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'user_id' => $userId,
            'order_id' => $orderId,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ];
        
        // Log to file
        error_log('PAYMENT_EVENT: ' . json_encode($logData));
        
        // Store in database for analytics
        $this->storePaymentLog($event, $userId, $orderId, $details);
    }
    
    /**
     * Store payment log in database
     */
    private function storePaymentLog($event, $userId, $orderId, $details) 
    {
        $sql = "INSERT INTO payment_logs (event, user_id, order_id, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
                
        $stmt = $this->conn->prepare($sql);
        $detailsJson = json_encode($details);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        
        $stmt->bind_param("sisss", $event, $userId, $orderId, $detailsJson, $ipAddress, $userAgent);
        $stmt->execute();
    }
    
    /**
     * Create refund record
     */
    private function createRefundRecord($paymentId, $amount, $reason, $adminUserId) 
    {
        $sql = "INSERT INTO payment_refunds (payment_id, amount, reason, admin_user_id, status, created_at) 
                VALUES (?, ?, ?, ?, 'processed', NOW())";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("idsi", $paymentId, $amount, $reason, $adminUserId);
        
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get payment retry count
     */
    private function getPaymentRetryCount($paymentId) 
    {
        $sql = "SELECT retry_count FROM payments WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['retry_count'] ?? 0;
    }
    
    /**
     * Increment payment retry count
     */
    private function incrementPaymentRetryCount($paymentId) 
    {
        $sql = "UPDATE payments SET retry_count = COALESCE(retry_count, 0) + 1 WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $paymentId);
        $stmt->execute();
    }
    
    /**
     * Get payment health status
     */
    public function getHealthStatus() 
    {
        $stats = $this->getPaymentStatistics(1); // Last 24 hours
        
        $health = [
            'status' => 'healthy',
            'last_24h' => $stats,
            'issues' => []
        ];
        
        // Check for issues
        $totalPayments = $stats['total_payments'] ?? 0;
        if ($totalPayments > 0) {
            $failureRate = ($stats['failed_payments'] ?? 0) / $totalPayments * 100;
            
            if ($failureRate > 20) {
                $health['status'] = 'warning';
                $health['issues'][] = 'High failure rate: ' . number_format($failureRate, 1) . '%';
            }
            
            if ($failureRate > 50) {
                $health['status'] = 'critical';
            }
        }
        
        // Check for stuck payments
        $sql = "SELECT COUNT(*) as stuck_payments FROM payments 
                WHERE status IN ('pending', 'processing') 
                AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)";
        $result = $this->conn->query($sql);
        $stuckCount = $result->fetch_assoc()['stuck_payments'] ?? 0;
        
        if ($stuckCount > 0) {
            $health['status'] = $stuckCount > 5 ? 'warning' : $health['status'];
            $health['issues'][] = "$stuckCount stuck payments detected";
        }
        
        return $health;
    }
    
    /**
     * Get revenue trends
     */
    public function getRevenueTrends($months = 12) 
    {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as revenue,
                    COUNT(*) as total_attempts
                FROM payments 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $months);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
