<?php
/**
 * AI Key Health Check Cron Job
 * Runs from CLI, prints system health and account status.
 * Schedule: Every 15 minutes
 * Example: * / 15 * * * * /usr/bin/php /path/to/cron/ai_health_check.php
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../services/AIKeysSystem.php';

try {
    if (php_sapi_name() !== 'cli') {
        die("This script must be run from the command line\n");
    }

    $startTime = microtime(true);
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] Starting AI Key health check...\n";

    $aiKeys = new AIKeysSystem($conn);

    // System health summary
    echo "[$timestamp] Step 1: Checking system health...\n";
    $health = $aiKeys->getSystemHealth();
    echo "[$timestamp]   Total keys: " . ($health['total_keys'] ?? 0) . "\n";
    echo "[$timestamp]   Active keys: " . ($health['active_keys'] ?? 0) . "\n";
    echo "[$timestamp]   Disabled keys: " . ($health['disabled_keys'] ?? 0) . "\n";
    echo "[$timestamp]   System healthy: " . (!empty($health['healthy']) ? 'Yes' : 'No') . "\n";

    // Accounts overview
    echo "[$timestamp] Step 2: Checking account status...\n";
    $accounts = $aiKeys->getAllAccounts();
    foreach ($accounts as $account) {
        $name = $account['account_name'] ?? ('Account ' . ($account['account_id'] ?? '?'));
        $status = $account['status'] ?? 'unknown';
        $activeKeys = $account['active_keys'] ?? 0;
        $remaining = $account['remaining_quota'] ?? 0;
        echo "[$timestamp] ✓ {$name}: status={$status}, active_keys={$activeKeys}, remaining_quota={$remaining}\n";
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    echo "[$timestamp] Health check complete in {$duration}ms\n";
    exit(0);

} catch (Exception $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] ✗ Health check failed: " . $e->getMessage() . "\n";
    error_log("AI Key health check failed: " . $e->getMessage());
    exit(1);
}
