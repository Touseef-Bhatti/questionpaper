<?php
/**
 * ============================================================================
 * Daily Reset & Maintenance Cron Job
 * ============================================================================
 * 
 * Executes daily maintenance tasks:
 * - Reset used_today counters for all keys
 * - Reset active keys from blocked status (if block expired)
 * - Create daily quota snapshots
 * - Clean up old logs (optional)
 * 
 * CRON SCHEDULE:
 * 0 0 * * * /usr/bin/php /path/to/cron/daily_reset.php
 * (Runs at midnight every day)
 * 
 * ============================================================================
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../services/AIKeyManager.php';
require_once __DIR__ . '/../services/AILoggingService.php';
<?php
/**
 * ============================================================================
 * Daily Reset & Maintenance Cron Job
 * ============================================================================
 * 
 * Executes daily maintenance tasks:
 * - Reset used_today counters for all keys
 * - Reset active keys from blocked status (if block expired)
 * - Display daily quota snapshots
 * - Clean up old logs (optional)
 * 
 * CRON SCHEDULE:
 * 0 0 * * * /usr/bin/php /path/to/cron/ai_daily_reset.php
 * (Runs at midnight every day)
 * 
 * ============================================================================
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../services/AIKeysSystem.php';

try {
    // Ensure we're running in CLI mode
    if (php_sapi_name() !== 'cli') {
        die("This script must be run from the command line\n");
    }
    
    $startTime = microtime(true);
    $timestamp = date('Y-m-d H:i:s');
    
    echo "[$timestamp] Starting daily AI Key reset job...\n";
    
    // ========================================================================
    // 1. RESET DAILY QUOTAS
    // ========================================================================
    
    echo "[$timestamp] Step 1: Resetting daily quotas...\n";
    
    $resetQuery = "
        UPDATE ai_api_keys
        SET 
            used_today = 0,
            last_reset_at = NOW(),
            updated_at = NOW()
        WHERE status != 'disabled'
    ";
    
    if ($conn->query($resetQuery)) {
        $affectedRows = $conn->affected_rows;
        echo "[$timestamp] ✓ Reset used_today for $affectedRows keys\n";
    } else {
        throw new Exception("Failed to reset quotas: " . $conn->error);
    }
    try {
        // Ensure we're running in CLI mode
        if (php_sapi_name() !== 'cli') {
            die("This script must be run from the command line\n");
        }
    
        $startTime = microtime(true);
        $timestamp = date('Y-m-d H:i:s');
    
        echo "[$timestamp] Starting daily AI Key reset job...\n";
    
        $aiKeys = new AIKeysSystem($conn);
    
        // ========================================================================
        // 1. RESET DAILY QUOTAS
        // ========================================================================
    
        echo "[$timestamp] Step 1: Resetting daily quotas...\n";
    
        $aiKeys->resetDailyCounters();
        echo "[$timestamp] ✓ Reset daily counters for all keys\n";
    
    // ========================================================================
    // 2. AUTO-UNBLOCK TEMPORARILY BLOCKED KEYS
    // ========================================================================
    
    echo "[$timestamp] Step 2: Auto-unblocking expired temporary blocks...\n";
    
    $unblockQuery = "
        UPDATE ai_api_keys
        SET 
            status = 'active',
            temporary_block_until = NULL,
            consecutive_failures = 0,
            updated_at = NOW()
        WHERE 
            status = 'temporarily_blocked'
            AND temporary_block_until IS NOT NULL
            AND temporary_block_until < NOW()
    ";
    
    if ($conn->query($unblockQuery)) {
        $unblocked = $conn->affected_rows;
        echo "[$timestamp] ✓ Unblocked $unblocked temporarily blocked keys\n";
    } else {
        throw new Exception("Failed to unblock keys: " . $conn->error->error);
    }
        // ========================================================================
        // 2. AUTO-UNBLOCK TEMPORARILY BLOCKED KEYS
        // ========================================================================
    
        echo "[$timestamp] Step 2: Auto-unblocking expired temporary blocks...\n";
    
        $aiKeys->unblockExpiredKeys();
        echo "[$timestamp] ✓ Unblocked expired temporary blocks\n";
    
    // ========================================================================
    // 3. RESET EXHAUSTED KEYS (IF QUOTA WAS RESET)
    // ========================================================================
    
    echo "[$timestamp] Step 3: Resetting exhausted keys...\n";
    
    $resetExhaustedQuery = "
        UPDATE ai_api_keys
        SET 
            status = 'active',
            used_today = 0,
            updated_at = NOW()
        WHERE status = 'exhausted'
    ";
    
    if ($conn->query($resetExhaustedQuery)) {
        $resetExhausted = $conn->affected_rows;
        echo "[$timestamp] ✓ Reset $resetExhausted exhausted keys to active\n";
    } else {
        throw new Exception("Failed to reset exhausted keys: " . $conn->error);
    }
        // ========================================================================
        // 3. DISPLAY KEY STATUS
        // ========================================================================
    
        echo "[$timestamp] Step 3: Key status report\n";
    
        $healthReport = $aiKeys->getSystemHealth();
        echo "[$timestamp] ✓ Total Keys: {$healthReport['total_keys']}\n";
        echo "[$timestamp] ✓ Active Keys: {$healthReport['active_keys']}\n";
        echo "[$timestamp] ✓ Disabled Keys: {$healthReport['disabled_keys']}\n";
    
    // ========================================================================
    // 4. CREATE DAILY QUOTA SNAPSHOTS FOR ALL ACCOUNTS
    // ========================================================================
    
    echo "[$timestamp] Step 4: Creating daily quota snapshots...\n";
    
    $logger = new AILoggingService($conn);
    
    // Get all active accounts
    $accountsQuery = "SELECT account_id, daily_quota FROM ai_accounts WHERE status = 'active'";
    $result = $conn->query($accountsQuery);
    
    if (!$result) {
        throw new Exception("Failed to fetch accounts: " . $conn->error);
    }
    
    $snapshotCount = 0;
    
    while ($account = $result->fetch_assoc()) {
        $accountId = $account['account_id'];
        $dailyQuota = $account['daily_quota'];
        
        // Get today's usage
        $today = date('Y-m-d');
        $usageQuery = "
            SELECT 
                COUNT(*) as total_requests,
                SUM(tokens_used) as total_tokens,
                SUM(estimated_cost) as total_cost,
                COUNT(DISTINCT key_id) as keys_used
            FROM ai_request_logs
            WHERE account_id = ? AND DATE(created_at) = ?
        ";
        
        $usageStmt = $conn->prepare($usageQuery);
        if (!$usageStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $usageStmt->bind_param('is', $accountId, $today);
        $usageStmt->execute();
        $usageResult = $usageStmt->get_result();
        $usage = $usageResult->fetch_assoc();
        $usageStmt->close();
        
        $totalRequests = (int)($usage['total_requests'] ?? 0);
        $totalTokens = (int)($usage['total_tokens'] ?? 0);
        $totalCost = (float)($usage['total_cost'] ?? 0);
        $remainingQuota = max(0, $dailyQuota - $totalTokens);
        
        // Count active and blocked keys
        $keysQuery = "
            SELECT 
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_keys,
                SUM(CASE WHEN status IN ('temporarily_blocked', 'exhausted') THEN 1 ELSE 0 END) as blocked_keys
            FROM ai_api_keys
            WHERE account_id = ?
        ";
        
        $keysStmt = $conn->prepare($keysQuery);
        if (!$keysStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $keysStmt->bind_param('i', $accountId);
        $keysStmt->execute();
        $keysResult = $keysStmt->get_result();
        $keys = $keysResult->fetch_assoc();
        $keysStmt->close();
        
        $activeKeys = (int)($keys['active_keys'] ?? 0);
        $blockedKeys = (int)($keys['blocked_keys'] ?? 0);
        
        // Create snapshot
        $logger->createDailySnapshot(
            $accountId,
            $totalRequests,
            $totalTokens,
            $totalCost,
            $remainingQuota,
            $activeKeys,
            $blockedKeys
        );
        
        $snapshotCount++;
        echo "[$timestamp] ✓ Account $accountId: $totalRequests requests, $totalTokens tokens, \$$totalCost cost\n";
    }
    
    echo "[$timestamp] ✓ Created $snapshotCount daily snapshots\n";
        // ========================================================================
        // 4. DISPLAY ACCOUNT QUOTAS
        // ========================================================================
    
        echo "[$timestamp] Step 4: Account quota report\n";
    
        $accounts = $aiKeys->getAllAccounts();
        foreach ($accounts as $account) {
            $name = $account['account_name'];
            $active = $account['active_keys'] ?? 0;
            $remaining = $account['remaining_quota'] ?? 0;
            echo "[$timestamp] ✓ {$name}: {$active} active keys, {$remaining} quota remaining\n";
        }
    
    // ========================================================================
    // 5. CLEANUP OLD LOGS (OPTIONAL - Keep last 90 days)
    // ========================================================================
    
    echo "[$timestamp] Step 5: Cleaning up old request logs...\n";
    
    $cleanupQuery = "
        DELETE FROM ai_request_logs
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        LIMIT 1000
    ";
    
    if ($conn->query($cleanupQuery)) {
        $deleted = $conn->affected_rows;
        echo "[$timestamp] ✓ Deleted $deleted old log entries\n";
    } else {
        // Don't fail if cleanup fails
        echo "[$timestamp] ⚠ Cleanup failed (non-critical): " . $conn->error . "\n";
    }
        // ========================================================================
        // 5. CLEANUP OLD LOGS (OPTIONAL - Keep last 90 days)
        // ========================================================================
    
        echo "[$timestamp] Step 5: Cleaning up old logs...\n";
    
        $cleanupQuery = "
            DELETE FROM ai_request_logs
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            LIMIT 1000
        ";
    
        if (@$conn->query($cleanupQuery)) {
            $deleted = $conn->affected_rows;
            echo "[$timestamp] ✓ Deleted $deleted old log entries\n";
        } else {
            // Don't fail if cleanup fails (table may not exist)
            echo "[$timestamp] ℹ Cleanup skipped (non-critical)\n";
        }
    
    // ========================================================================
    // COMPLETION
    // ========================================================================
    
    $duration = round((microtime(true) - $startTime) * 1000);
    echo "[$timestamp] ✓ Daily reset job completed successfully in ${duration}ms\n";
    
    exit(0);
    
} catch (Exception $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] ✗ CRITICAL ERROR: " . $e->getMessage() . "\n";
    error_log("AI Key daily reset failed: " . $e->getMessage());
    exit(1);
}
?>
