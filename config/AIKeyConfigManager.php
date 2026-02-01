<?php
/**
 * ============================================================================
 * AI Key Configuration Manager
 * ============================================================================
 * 
 * Manages all AI API keys from .env.local with intelligent features:
 * - Load keys using new KEY_N format (replaces old API_KEY_N)
 * - Auto-detect providers and models
 * - Support for multiple accounts with priority ordering
 * - Per-key configuration (model, provider, quota)
 * - Encryption key management
 * - Health status tracking
 * 
 * Usage:
 *   $config = new AIKeyConfigManager();
 *   $allKeys = $config->getAllKeys();
 *   $key1 = $config->getKeyByName('key_1');
 *   $accountKeys = $config->getAccountKeys(1);
 * 
 * ============================================================================
 */

class AIKeyConfigManager {
    
    private $envPath;
    private $envData = [];
    private $keys = [];
    private $accounts = [];
    private $encryptionKey;
    
    public function __construct($envPath = null) {
        if ($envPath === null) {
            $envPath = __DIR__ . '/.env.local';
        }
        $this->envPath = $envPath;
        $this->loadEnvironment();
        $this->parseKeys();
        $this->buildAccounts();
    }
    
    /**
     * Load and parse environment file
     */
    private function loadEnvironment() {
        if (!file_exists($this->envPath)) {
            throw new Exception("Environment file not found: {$this->envPath}");
        }
        
        $lines = file($this->envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                
                $this->envData[$key] = $value;
            }
        }
        
        // Load encryption key
        $this->encryptionKey = $this->envData['AI_ENCRYPTION_KEY'] ?? null;
    }
    
    /**
     * Parse all KEY_N format keys from environment
     */
    private function parseKeys() {
        $keyPattern = '/^KEY_(\d+)$/';
        
        foreach ($this->envData as $key => $value) {
            if (preg_match($keyPattern, $key, $matches)) {
                $keyNum = intval($matches[1]);
                
                // Get model and provider
                $modelKey = "KEY_{$keyNum}_MODEL";
                $providerKey = "KEY_{$keyNum}_PROVIDER";
                
                $model = $this->envData[$modelKey] ?? getenv('AI_DEFAULT_MODEL') ?? 'gpt-4-turbo';
                $provider = $this->envData[$providerKey] ?? 'openai';
                
                $this->keys[$keyNum] = [
                    'id' => $keyNum,
                    'name' => "key_$keyNum",
                    'value' => $value,
                    'model' => $model,
                    'provider' => $provider,
                    'status' => 'active',
                    'daily_limit' => getenv('AI_DAILY_QUOTA_PER_KEY') ?? 100000,
                    'used_today' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Sort keys by ID
        ksort($this->keys);
    }
    
    /**
     * Build account structure based on key distribution
     * Account 1 (Priority 1): KEY_1 to KEY_X (first half or marked primary)
     * Account 2 (Priority 2): Remaining keys (fallback/secondary)
     */
    private function buildAccounts() {
        if (empty($this->keys)) {
            return;
        }
        
        $keyIds = array_keys($this->keys);
        $totalKeys = count($keyIds);
        $midpoint = ceil($totalKeys / 2);
        
        // Account 1: First half of keys
        $account1Keys = [];
        for ($i = 0; $i < $midpoint && isset($keyIds[$i]); $i++) {
            $account1Keys[] = $this->keys[$keyIds[$i]];
        }
        
        if (!empty($account1Keys)) {
            $this->accounts[1] = [
                'id' => 1,
                'name' => 'Primary Account',
                'provider' => $account1Keys[0]['provider'] ?? 'openai',
                'priority' => 1,
                'status' => 'active',
                'keys' => $account1Keys,
                'key_count' => count($account1Keys)
            ];
        }
        
        // Account 2: Second half of keys
        $account2Keys = [];
        for ($i = $midpoint; isset($keyIds[$i]); $i++) {
            $account2Keys[] = $this->keys[$keyIds[$i]];
        }
        
        if (!empty($account2Keys)) {
            $this->accounts[2] = [
                'id' => 2,
                'name' => 'Secondary Account (Fallback)',
                'provider' => $account2Keys[0]['provider'] ?? 'openai',
                'priority' => 2,
                'status' => 'active',
                'keys' => $account2Keys,
                'key_count' => count($account2Keys)
            ];
        }
    }
    
    /**
     * Get all keys
     */
    public function getAllKeys() {
        return array_values($this->keys);
    }
    
    /**
     * Get key by ID
     */
    public function getKeyById($id) {
        return $this->keys[$id] ?? null;
    }
    
    /**
     * Get key by name (e.g., 'key_1')
     */
    public function getKeyByName($name) {
        foreach ($this->keys as $key) {
            if ($key['name'] === $name) {
                return $key;
            }
        }
        return null;
    }
    
    /**
     * Get all accounts
     */
    public function getAllAccounts() {
        return array_values($this->accounts);
    }
    
    /**
     * Get account by ID
     */
    public function getAccountById($id) {
        return $this->accounts[$id] ?? null;
    }
    
    /**
     * Get keys for specific account
     */
    public function getAccountKeys($accountId) {
        return $this->accounts[$accountId]['keys'] ?? [];
    }
    
    /**
     * Get account statistics
     */
    public function getAccountStats($accountId) {
        $account = $this->accounts[$accountId] ?? null;
        if (!$account) {
            return null;
        }
        
        return [
            'account_id' => $account['id'],
            'account_name' => $account['name'],
            'provider' => $account['provider'],
            'priority' => $account['priority'],
            'total_keys' => count($account['keys']),
            'active_keys' => count(array_filter($account['keys'], fn($k) => $k['status'] === 'active')),
            'daily_limit' => array_sum(array_column($account['keys'], 'daily_limit')),
            'used_today' => array_sum(array_column($account['keys'], 'used_today')),
            'remaining_quota' => array_sum(array_column($account['keys'], 'daily_limit')) - 
                                array_sum(array_column($account['keys'], 'used_today'))
        ];
    }
    
    /**
     * Get encryption key for key storage
     */
    public function getEncryptionKey() {
        return $this->encryptionKey;
    }
    
    /**
     * Validate encryption key format
     */
    public function validateEncryptionKey() {
        if (empty($this->encryptionKey)) {
            return false;
        }
        
        // Should be base64 encoded 32 bytes
        $decoded = base64_decode($this->encryptionKey, true);
        return $decoded !== false && strlen($decoded) === 32;
    }
    
    /**
     * Get total key count
     */
    public function getTotalKeyCount() {
        return count($this->keys);
    }
    
    /**
     * Get system configuration
     */
    public function getSystemConfig() {
        return [
            'default_model' => getenv('AI_DEFAULT_MODEL') ?? 'gpt-4-turbo',
            'fallback_model' => getenv('AI_FALLBACK_MODEL') ?? 'gpt-3.5-turbo',
            'daily_quota_per_key' => intval(getenv('AI_DAILY_QUOTA_PER_KEY') ?? 100000),
            'max_retries' => intval(getenv('AI_MAX_RETRIES') ?? 3),
            'retry_delay_ms' => intval(getenv('AI_RETRY_DELAY_MS') ?? 100),
            'circuit_breaker_threshold' => intval(getenv('AI_CIRCUIT_BREAKER_THRESHOLD') ?? 3),
            'encryption_configured' => $this->validateEncryptionKey(),
            'total_keys_loaded' => $this->getTotalKeyCount(),
            'total_accounts' => count($this->accounts)
        ];
    }
    
    /**
     * Get all configuration as array (useful for debugging)
     */
    public function getFullConfig() {
        return [
            'system' => $this->getSystemConfig(),
            'accounts' => $this->getAllAccounts(),
            'keys' => $this->getAllKeys()
        ];
    }
    
    /**
     * Get environment variable
     */
    public function getEnv($key, $default = null) {
        return $this->envData[$key] ?? $default;
    }
}

?>
