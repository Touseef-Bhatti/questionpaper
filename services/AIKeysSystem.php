<?php
/**
 * ============================================================================
 * AI Keys System - Complete Management Class
 * ============================================================================
 * 
 * Master class that handles:
 * - Configuration management (from .env.local)
 * - Database operations for keys
 * - Key encryption/decryption
 * - Account and key selection logic
 * - Usage tracking
 * - Health status monitoring
 * 
 * Usage:
 *   $aiKeys = new AIKeysSystem($conn);
 *   $key = $aiKeys->selectBestKey('openai');
 *   $keys = $aiKeys->getAccountKeys(1);
 *   $stats = $aiKeys->getAccountStats(1);
 * 
 * ============================================================================
 */

class AIKeysSystem {
    
    private $conn;
    private $configManager;
    private $encryptionKey;
    
    public function __construct($conn, $envPath = null) {
        $this->conn = $conn;
        
        if ($envPath === null) {
            $envPath = __DIR__ . '/../config/.env.local';
        }
        
        try {
            $this->configManager = new AIKeyConfigManager($envPath);
            $this->encryptionKey = $this->configManager->getEncryptionKey();
        } catch (Exception $e) {
            throw new Exception("Failed to initialize AIKeysSystem: " . $e->getMessage());
        }
    }
    
    /**
     * Get all accounts with statistics
     */
    public function getAllAccounts() {
        $query = "
            SELECT 
                a.account_id,
                a.account_name,
                a.provider_name,
                a.priority,
                a.status,
                COUNT(k.key_id) as active_keys,
                SUM(k.daily_limit) as total_daily_limit,
                SUM(k.used_today) as total_used_today,
                SUM(k.daily_limit) - SUM(k.used_today) as remaining_quota
            FROM ai_accounts a
            LEFT JOIN ai_api_keys k ON a.account_id = k.account_id AND k.status = 'active'
            GROUP BY a.account_id
            ORDER BY a.priority ASC
        ";
        
        $result = $this->conn->query($query);
        $accounts = [];
        
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        
        return $accounts;
    }
    
    /**
     * Get account statistics
     */
    public function getAccountStats($accountId) {
        $query = "
            SELECT 
                a.account_id,
                a.account_name,
                a.provider_name,
                a.priority,
                a.status,
                COUNT(CASE WHEN k.status = 'active' THEN 1 END) as active_keys,
                COUNT(k.key_id) as total_keys,
                SUM(k.daily_limit) as total_daily_limit,
                SUM(k.used_today) as total_used_today
            FROM ai_accounts a
            LEFT JOIN ai_api_keys k ON a.account_id = k.account_id
            WHERE a.account_id = ?
            GROUP BY a.account_id
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row) {
            $row['remaining_quota'] = intval($row['total_daily_limit']) - intval($row['total_used_today']);
        }
        
        return $row;
    }
    
    /**
     * Get keys for a specific account
     */
    public function getAccountKeys($accountId, $onlyActive = true) {
        $query = "
            SELECT * FROM ai_api_keys 
            WHERE account_id = ?
        ";
        
        if ($onlyActive) {
            $query .= " AND status = 'active'";
        }
        
        $query .= " ORDER BY last_used_at ASC, key_id ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $keys = [];
        
        while ($row = $result->fetch_assoc()) {
            // Decrypt key if needed
            if (!empty($this->encryptionKey) && !empty($row['api_key_encrypted'])) {
                $row['api_key_decrypted'] = $this->decryptKey($row['api_key_encrypted']);
            }
            $keys[] = $row;
        }
        
        return $keys;
    }
    
    /**
     * Select the best key for a request (priority + least-used-first)
     */
    public function selectBestKey($provider = 'openai') {
        // Get all active accounts ordered by priority
        $accounts = $this->getAllAccounts();
        
        foreach ($accounts as $account) {
            if ($account['status'] !== 'active') continue;
            if ($account['active_keys'] <= 0) continue;
            
            // Check if account has quota
            if ($account['remaining_quota'] <= 0) continue;
            
            // Get keys from this account, least-used first
            $query = "
                SELECT * FROM ai_api_keys 
                WHERE account_id = ? AND status = 'active'
                ORDER BY used_today ASC, last_used_at ASC
                LIMIT 1
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $account['account_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Decrypt key
                if (!empty($this->encryptionKey) && !empty($row['api_key_encrypted'])) {
                    $row['api_key'] = $this->decryptKey($row['api_key_encrypted']);
                }
                
                return $row;
            }
        }
        
        return null; // No available keys
    }
    
    /**
     * Get key by ID
     */
    public function getKeyById($keyId) {
        $query = "SELECT * FROM ai_api_keys WHERE key_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $keyId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Decrypt key
            if (!empty($this->encryptionKey) && !empty($row['api_key_encrypted'])) {
                $row['api_key'] = $this->decryptKey($row['api_key_encrypted']);
            }
            return $row;
        }
        
        return null;
    }
    
    /**
     * Update key usage
     */
    public function updateKeyUsage($keyId, $tokensUsed) {
        $query = "
            UPDATE ai_api_keys 
            SET used_today = used_today + ?,
                last_used_at = NOW()
            WHERE key_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $tokensUsed, $keyId);
        return $stmt->execute();
    }
    
    /**
     * Mark key as temporarily blocked
     */
    public function blockKey($keyId, $duration = 1800) {
        $blockUntil = date('Y-m-d H:i:s', time() + $duration);
        
        $query = "
            UPDATE ai_api_keys 
            SET status = 'temporarily_blocked',
                temporary_block_until = ?
            WHERE key_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $blockUntil, $keyId);
        return $stmt->execute();
    }
    
    /**
     * Unblock key if block expired
     */
    public function unblockExpiredKeys() {
        $query = "
            UPDATE ai_api_keys 
            SET status = 'active',
                temporary_block_until = NULL,
                consecutive_failures = 0
            WHERE status = 'temporarily_blocked'
            AND temporary_block_until < NOW()
        ";
        
        return $this->conn->query($query);
    }
    
    /**
     * Record key failure
     */
    public function recordKeyFailure($keyId) {
        $query = "
            UPDATE ai_api_keys 
            SET consecutive_failures = consecutive_failures + 1
            WHERE key_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $keyId);
        $result = $stmt->execute();
        
        // Check if should disable key due to too many failures
        $key = $this->getKeyById($keyId);
        $threshold = intval(getenv('AI_CIRCUIT_BREAKER_THRESHOLD') ?? 3);
        
        if ($key['consecutive_failures'] >= $threshold) {
            $this->disableKey($keyId, "Exceeded failure threshold ({$key['consecutive_failures']} failures)");
        }
        
        return $result;
    }
    
    /**
     * Disable key permanently
     */
    public function disableKey($keyId, $reason = null) {
        $query = "
            UPDATE ai_api_keys 
            SET status = 'disabled',
                disabled_reason = ?
            WHERE key_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $reason, $keyId);
        return $stmt->execute();
    }
    
    /**
     * Reset daily usage counters
     */
    public function resetDailyCounters() {
        $query = "
            UPDATE ai_api_keys 
            SET used_today = 0,
                last_reset_at = NOW()
            WHERE status = 'active'
        ";
        
        return $this->conn->query($query);
    }
    
    /**
     * Decrypt API key
     */
    private function decryptKey($encryptedBlob) {
        if (empty($this->encryptionKey)) {
            // Return as-is if encrypted blob is base64 and no key
            return base64_decode($encryptedBlob);
        }
        
        $data = base64_decode($encryptedBlob);
        if ($data === false) {
            return null;
        }
        
        // Extract IV (first 16 bytes)
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        try {
            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                base64_decode($this->encryptionKey),
                OPENSSL_RAW_DATA,
                $iv
            );
            
            return $decrypted !== false ? $decrypted : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get configuration manager
     */
    public function getConfigManager() {
        return $this->configManager;
    }
    
    /**
     * Get system health status
     */
    public function getSystemHealth() {
        $accounts = $this->getAllAccounts();
        $totalKeys = 0;
        $activeKeys = 0;
        $disabledKeys = 0;
        
        $keyQuery = "SELECT COUNT(*) as total FROM ai_api_keys";
        $keyResult = $this->conn->query($keyQuery);
        $keyRow = $keyResult->fetch_assoc();
        $totalKeys = intval($keyRow['total']);
        
        $activeQuery = "SELECT COUNT(*) as total FROM ai_api_keys WHERE status = 'active'";
        $activeResult = $this->conn->query($activeQuery);
        $activeRow = $activeResult->fetch_assoc();
        $activeKeys = intval($activeRow['total']);
        
        $disabledQuery = "SELECT COUNT(*) as total FROM ai_api_keys WHERE status = 'disabled'";
        $disabledResult = $this->conn->query($disabledQuery);
        $disabledRow = $disabledResult->fetch_assoc();
        $disabledKeys = intval($disabledRow['total']);
        
        return [
            'total_keys' => $totalKeys,
            'active_keys' => $activeKeys,
            'disabled_keys' => $disabledKeys,
            'accounts' => count($accounts),
            'healthy' => $activeKeys > 0,
            'encryption_enabled' => !empty($this->encryptionKey)
        ];
    }
}

?>
