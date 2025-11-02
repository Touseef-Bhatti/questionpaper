<?php
/**
 * Payment System Health Check Cron Job
 * Monitors payment system health and creates alerts
 * Run every 5 minutes: */
// 5 * * * * php health_check.php
//  */

// Prevent direct browser access
if (isset($_SERVER['REQUEST_METHOD']) && php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access denied. This script can only be run from command line.');
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../services/PaymentService.php';

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting payment system health check...\n";
    
    $paymentService = new PaymentService();
    $health = $paymentService->getHealthStatus();
    
    echo "[" . date('Y-m-d H:i:s') . "] System status: " . $health['status'] . "\n";
    
    // Create alerts for issues
    if (!empty($health['issues'])) {
        foreach ($health['issues'] as $issue) {
            createAlert('system_health', 'Payment System Health Issue', $issue, 'warning', [
                'health_status' => $health['status'],
                'stats' => $health['last_24h']
            ]);
            echo "[" . date('Y-m-d H:i:s') . "] Alert created: $issue\n";
        }
    }
    
    // Check for critical issues requiring immediate attention
    if ($health['status'] === 'critical') {
        // Send emergency notification (implement your preferred notification method)
        notifyAdmins('CRITICAL', 'Payment system is in critical state', $health['issues']);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Health check completed\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error during health check: " . $e->getMessage() . "\n";
    error_log("Payment health check error: " . $e->getMessage());
    
    // Create critical alert for health check failure
    createAlert('health_check_failed', 'Health Check Failed', $e->getMessage(), 'critical', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    exit(1);
}

function createAlert($type, $title, $message, $severity, $data = null) {
    global $conn;
    
    $sql = "INSERT INTO payment_alerts (alert_type, title, message, severity, data, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $dataJson = $data ? json_encode($data) : null;
    $stmt->bind_param("sssss", $type, $title, $message, $severity, $dataJson);
    $stmt->execute();
}

function notifyAdmins($level, $subject, $details) {
    // Implement your notification system here
    // This could be email, Slack, SMS, etc.
    
    $adminEmail = EnvLoader::get('ADMIN_EMAIL', 'admin@questionpaper.com');
    $message = "[$level] $subject\n\nDetails:\n" . implode("\n", $details);
    
    // Log the notification
    error_log("ADMIN_NOTIFICATION: $subject - " . implode(', ', $details));
    
    // Implement actual email/notification sending here
    // mail($adminEmail, "[$level] QPaperGen Payment Alert: $subject", $message);
}
?>
