<?php
/**
 * Cleanup Payments Cron Job
 * This script should be run regularly (every 15-30 minutes) to clean up expired payments
 * 
 * Usage: php cleanup_payments.php
 * Or set up as a cron job: */
// 15 * * * /usr/bin/php /path/to/cleanup_payments.php
//  */

// Prevent direct browser access
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'CLI') {
    http_response_code(403);
    die('Access denied. This script can only be run from command line.');
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../services/PaymentService.php';

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting payment cleanup...\n";
    
    $paymentService = new PaymentService();
    
    // Clean up expired payments
    $expiredCount = $paymentService->cleanupExpiredPayments();
    
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup completed. $expiredCount payments marked as expired.\n";
    
    // Optional: Get and log statistics
    $stats = $paymentService->getPaymentStatistics(1); // Last 24 hours
    echo "[" . date('Y-m-d H:i:s') . "] Payment statistics (last 24h): \n";
    echo "  - Total: " . ($stats['total_payments'] ?? 0) . "\n";
    echo "  - Successful: " . ($stats['successful_payments'] ?? 0) . "\n";
    echo "  - Failed: " . ($stats['failed_payments'] ?? 0) . "\n";
    echo "  - Expired: " . ($stats['expired_payments'] ?? 0) . "\n";
    echo "  - Cancelled: " . ($stats['cancelled_payments'] ?? 0) . "\n";
    echo "  - Revenue: PKR " . number_format($stats['total_revenue'] ?? 0, 2) . "\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error during cleanup: " . $e->getMessage() . "\n";
    error_log("Payment cleanup error: " . $e->getMessage());
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Payment cleanup finished successfully.\n";
?>
