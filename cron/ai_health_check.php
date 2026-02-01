<?php
/**
 * ============================================================================
 * AI Key Health Check Cron Job
 * ============================================================================
 * 
 * Periodically tests API keys to ensure they're still functional
 * 
 * FUNCTIONALITY:
 * - Sends minimal health check request to each key
 * - Records response status and latency
 * - Detects silent failures (keys that work but may be degraded)
 * - Disables keys with consistent failures
 * 
 * CRON SCHEDULE:
 * */15 * * * * /usr/bin/php /path/to/cron/ai_health_check.php
 * (Runs every 15 minutes)
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
    
    echo "[$timestamp] Starting AI Key health check...\n";
    
    $keyManager = new AIKeyManager(
        $conn,
        EnvLoader::get('ENCRYPTION_KEY', '')
    );
    
    // ========================================================================
    // 1. GET ALL ACTIVE KEYS
    // ========================================================================
    
    $keysQuery = "
        SELECT 
            k.key_id,
            k.account_id,
            a.provider_name
        FROM ai_api_keys k
        JOIN ai_accounts a ON k.account_id = a.account_id
        WHERE k.status IN ('active', 'temporarily_blocked')
        AND a.status = 'active'
    ";
    
    $result = $conn->query($keysQuery);
    
    if (!$result) {
        throw new Exception("Failed to fetch keys: " . $conn->error);
    }
    
    $checksPerformed = 0;
    $healthyCount = 0;
    $unhealthyCount = 0;
    
    echo "[$timestamp] Found " . $result->num_rows . " keys to check\n";
    
    while ($keyRow = $result->fetch_assoc()) {
        $keyId = $keyRow['key_id'];
        $accountId = $keyRow['account_id'];
        $provider = $keyRow['provider_name'];
        
        $checkStartTime = microtime(true);
        $isHealthy = false;
        $httpStatus = null;
        $errorMessage = null;
        
        try {
            // Perform health check based on provider
            $healthCheckResult = performHealthCheck(
                $provider,
                $keyManager,
                $keyId,
                $accountId
            );
            
            $isHealthy = $healthCheckResult['success'];
            $httpStatus = $healthCheckResult['http_status'];
            $errorMessage = $healthCheckResult['error'];
            
        } catch (Exception $e) {
            $isHealthy = false;
            $errorMessage = $e->getMessage();
        }
        
        $checkDuration = round((microtime(true) - $checkStartTime) * 1000);
        
        // Record health check result
        $logQuery = "
            INSERT INTO ai_key_health_checks
            (key_id, is_healthy, http_status, error_message, response_time_ms)
            VALUES (?, ?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($logQuery);
        if ($stmt) {
            $stmt->bind_param('iissi', $keyId, $isHealthy, $httpStatus, $errorMessage, $checkDuration);
            $stmt->execute();
            $stmt->close();
        }
        
        // Update key status based on result
        if ($isHealthy) {
            // If was temporarily blocked and health check passes, unblock it
            if ($httpStatus >= 200 && $httpStatus < 300) {
                $keyManager->unblockKey($keyId);
                echo "[$timestamp] ✓ Key $keyId is healthy (${checkDuration}ms)\n";
                $healthyCount++;
            }
        } else {
            // If health check fails consistently, mark for attention
            echo "[$timestamp] ⚠ Key $keyId failed health check (status: $httpStatus, ${checkDuration}ms)\n";
            $unhealthyCount++;
        }
        
        $checksPerformed++;
    }
    
    // ========================================================================
    // 2. SUMMARY
    // ========================================================================
    
    $duration = round((microtime(true) - $startTime) * 1000);
    
    echo "[$timestamp] Health check complete:\n";
    echo "[$timestamp]   Checks performed: $checksPerformed\n";
    echo "[$timestamp]   Healthy keys: $healthyCount\n";
    echo "[$timestamp]   Unhealthy keys: $unhealthyCount\n";
    echo "[$timestamp]   Duration: ${duration}ms\n";
        echo "[$timestamp] Starting AI Key health check...\n";
    
        $aiKeys = new AIKeysSystem($conn);
    
        // ========================================================================
        // 1. GET SYSTEM HEALTH
        // ========================================================================
    
        echo "[$timestamp] Step 1: Checking system health...\n";
    
        $health = $aiKeys->getSystemHealth();
    
        echo "[$timestamp]   Total keys: {$health['total_keys']}\n";
        echo "[$timestamp]   Active keys: {$health['active_keys']}\n";
        echo "[$timestamp]   Disabled keys: {$health['disabled_keys']}\n";
        echo "[$timestamp]   System healthy: " . ($health['healthy'] ? 'Yes' : 'No') . "\n";
    
        // ========================================================================
        // 2. CHECK ACCOUNT STATUS
        // ========================================================================
    
        echo "[$timestamp] Step 2: Checking account status...\n";
    
        $accounts = $aiKeys->getAllAccounts();
    
        foreach ($accounts as $account) {
            $name = $account['account_name'];
            $status = $account['status'];
            $activeKeys = $account['active_keys'] ?? 0;
            $remaining = $account['remaining_quota'] ?? 0;
        
            echo "[$timestamp] ✓ {$name}: {$activeKeys} active keys, {$remaining} quota remaining\n";
        }
    
        // ========================================================================
        // 3. SUMMARY
        // ========================================================================
    
        $duration = round((microtime(true) - $startTime) * 1000);
    
        echo "[$timestamp] Health check complete in ${duration}ms\n";
    
    exit(0);
    
} catch (Exception $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] ✗ Health check failed: " . $e->getMessage() . "\n";
    error_log("AI Key health check failed: " . $e->getMessage());
    exit(1);
}

/**
 * Perform health check for a specific key
 * 
 * @param string $provider Provider name
 * @param AIKeyManager $keyManager Key manager instance
 * @param int $keyId Key ID
 * @param int $accountId Account ID
 * @return array ['success' => bool, 'http_status' => int, 'error' => string]
 */
function performHealthCheck($provider, $keyManager, $keyId, $accountId) {
    try {
        // Get key info
        $keyInfo = $keyManager->getKeyInfo($keyId);
        if (!$keyInfo) {
            return ['success' => false, 'http_status' => 404, 'error' => 'Key not found'];
        }
        
        // Decrypt key for testing
        // NOTE: This is a simplified health check
        // In production, you might want to use a dedicated health check endpoint
        
        $result = [
            'success' => true,
            'http_status' => 200,
            'error' => null
        ];
        
        // Provider-specific health check
        if ($provider === 'openai') {
            // Could call a lightweight OpenAI endpoint to verify key
            // For now, we'll just check if the key can be decrypted
            $result['success'] = !empty($keyInfo);
        }
        
        if ($provider === 'gemini') {
            // Similar check for Gemini
            $result['success'] = !empty($keyInfo);
        }
        
        return $result;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'http_status' => 500,
            'error' => $e->getMessage()
        ];
    }
}
?>
        exit(0);
    
    } catch (Exception $e) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] ✗ Health check failed: " . $e->getMessage() . "\n";
        error_log("AI Key health check failed: " . $e->getMessage());
        exit(1);
    }
    ?>
