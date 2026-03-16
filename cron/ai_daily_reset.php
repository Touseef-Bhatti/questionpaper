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
        throw new Exception("Failed to unblock keys: " . $conn->error);
    }
    
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
    // 4. CREATE DAILY QUOTA SNAPSHOTS FOR ALL ACCOUNTS
    // ========================================================================
    
    echo "[$timestamp] Step 4: Creating daily quota snapshots...\n";
    
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
        
        // Create snapshot via direct SQL (AILoggingService not available)
        $snapshotQuery = "
            INSERT INTO ai_daily_snapshots 
            (account_id, snapshot_date, total_requests, total_tokens, total_cost, remaining_quota, active_keys, blocked_keys, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            total_requests = VALUES(total_requests),
            total_tokens = VALUES(total_tokens),
            total_cost = VALUES(total_cost),
            remaining_quota = VALUES(remaining_quota),
            active_keys = VALUES(active_keys),
            blocked_keys = VALUES(blocked_keys)
        ";
        
        $snapshotStmt = $conn->prepare($snapshotQuery);
        if (!$snapshotStmt) {
            echo "[$timestamp] ⚠ Snapshot table not configured (non-critical): " . $conn->error . "\n";
        } else {
            $snapshotStmt->bind_param('isiiidii', $accountId, $today, $totalRequests, $totalTokens, $totalCost, $remainingQuota, $activeKeys, $blockedKeys);
            $snapshotStmt->execute();
            $snapshotStmt->close();
            $snapshotCount++;
        }
        
        echo "[$timestamp] ✓ Account $accountId: $totalRequests requests, $totalTokens tokens, \$$totalCost cost\n";
    }
    
    echo "[$timestamp] ✓ Created $snapshotCount daily snapshots\n";

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
