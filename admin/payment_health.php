<?php
// Payment System Health Check API
session_start();
require_once '../db_connect.php';
require_once '../services/PaymentService.php';

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $paymentService = new PaymentService();
    
    // Get comprehensive health status
    $health = $paymentService->getHealthStatus();
    
    // Add additional system checks
    $systemChecks = [];
    
    // Database connectivity check
    try {
        $conn->ping();
        $systemChecks['database'] = ['status' => 'healthy', 'message' => 'Database connection active'];
    } catch (Exception $e) {
        $systemChecks['database'] = ['status' => 'critical', 'message' => 'Database connection failed'];
        $health['status'] = 'critical';
    }
    
    // SafePay configuration check
    try {
        $config = require __DIR__ . '/../config/safepay.php';
        if (empty($config['apiKey']) || empty($config['v1Secret'])) {
            $systemChecks['safepay'] = ['status' => 'critical', 'message' => 'SafePay credentials missing'];
            $health['status'] = 'critical';
        } else {
            $systemChecks['safepay'] = ['status' => 'healthy', 'message' => 'SafePay configured'];
        }
    } catch (Exception $e) {
        $systemChecks['safepay'] = ['status' => 'critical', 'message' => 'SafePay config error'];
        $health['status'] = 'critical';
    }
    
    // Webhook processing check
    $sql = "SELECT COUNT(*) as unprocessed_webhooks FROM payment_webhooks 
            WHERE processed = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $result = $conn->query($sql);
    $unprocessedWebhooks = $result->fetch_assoc()['unprocessed_webhooks'] ?? 0;
    
    if ($unprocessedWebhooks > 10) {
        $systemChecks['webhooks'] = ['status' => 'warning', 'message' => "$unprocessedWebhooks unprocessed webhooks"];
        if ($health['status'] === 'healthy') $health['status'] = 'warning';
    } else {
        $systemChecks['webhooks'] = ['status' => 'healthy', 'message' => 'Webhook processing normal'];
    }
    
    // Disk space check (if possible)
    $diskFree = disk_free_space('.');
    $diskTotal = disk_total_space('.');
    if ($diskFree && $diskTotal) {
        $diskUsagePercent = (1 - $diskFree / $diskTotal) * 100;
        if ($diskUsagePercent > 90) {
            $systemChecks['disk'] = ['status' => 'critical', 'message' => 'Low disk space: ' . number_format($diskUsagePercent, 1) . '% used'];
            $health['status'] = 'critical';
        } elseif ($diskUsagePercent > 80) {
            $systemChecks['disk'] = ['status' => 'warning', 'message' => 'Disk space warning: ' . number_format($diskUsagePercent, 1) . '% used'];
            if ($health['status'] === 'healthy') $health['status'] = 'warning';
        } else {
            $systemChecks['disk'] = ['status' => 'healthy', 'message' => 'Disk space normal: ' . number_format($diskUsagePercent, 1) . '% used'];
        }
    }
    
    $health['system_checks'] = $systemChecks;
    $health['timestamp'] = date('Y-m-d H:i:s');
    
    echo json_encode($health);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'critical',
        'error' => 'Health check failed',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
