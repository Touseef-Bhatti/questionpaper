<?php
/**
 * ============================================================================
 * AI Key Loader - Dynamic Configuration from .env.local
 * ============================================================================
 * 
 * Loads API keys dynamically from .env.local with support for:
 * - Multiple accounts (Account 1 Primary, Account 2, etc.)
 * - Multiple keys per account (API_KEY_1, API_KEY_2, etc.)
 * - Per-key model configuration (each key can use different model)
 * - Easy addition of new keys without code changes
 * 
 * Usage:
 *   $loader = new AIKeyLoader();
 *   $accounts = $loader->loadAllAccounts();
 *   foreach ($accounts as $account) {
 *       foreach ($account['keys'] as $key) {
 *           echo $key['value'] . ' uses model: ' . $key['model'];
 *       }
 *   }
 * 
 * ============================================================================
 */

class AIKeyLoader {
    
    private $envPath;
    private $envData = [];
    
    /**
     * Constructor
     * 
     * @param string $envPath Path to .env.local or .env.production file (optional)
     */
    public function __construct($envPath = null) {
        if ($envPath === null) {
            // Determine which environment file to use
            $envPath = __DIR__ . '/.env.local';
            
            // Check if in production environment
            if ((defined('ENVIRONMENT') && ENVIRONMENT === 'production') || getenv('APP_ENV') === 'production') {
                $envPath = __DIR__ . '/.env.production';
            }
            
            // Use .env.production if it exists and .env.local doesn't
            if (!file_exists($envPath)) {
                $alternativePath = (strpos($envPath, '.env.production') !== false) ? 
                    __DIR__ . '/.env.local' : 
                    __DIR__ . '/.env.production';
                if (file_exists($alternativePath)) {
                    $envPath = $alternativePath;
                }
            }
        }
        $this->envPath = $envPath;
        $this->loadEnvFile();
    }
    
    /**
     * Load and parse .env.local file
     */
    private function loadEnvFile() {
        if (!file_exists($this->envPath)) {
            throw new Exception("Environment file not found: {$this->envPath}");
        }
        
        $content = file_get_contents($this->envPath);
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE format
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }
                
                $this->envData[$key] = $value;
            }
        }
    }
    
    /**
     * Get all accounts with their keys
     * Scans .env for API_KEY_* patterns and groups them by account
     * 
     * @return array Structured account/key data
     */
    public function loadAllAccounts() {
        $accounts = [];
        $processedKeys = [];
        
        // Pattern 1: API_KEY_N_PRIMARY (Account 1 Primary)
        $primaryKeys = $this->extractKeysByPattern('API_KEY_(\d+)_PRIMARY');
        if (!empty($primaryKeys)) {
            $accounts['account_1_primary'] = [
                'account_id' => 1,
                'account_name' => 'OpenAI Account 1 (Primary)',
                'provider' => 'OpenAI',
                'priority' => 1,
                'keys' => $primaryKeys
            ];
            $processedKeys = array_merge($processedKeys, array_keys($primaryKeys));
        }
        
        // Pattern 2: API_KEY_N (Account 2 Secondary)
        // Only match if not _PRIMARY (to avoid duplicates)
        $secondaryKeys = [];
        foreach ($this->envData as $key => $value) {
            // Match API_KEY_N but NOT API_KEY_N_PRIMARY
            if (preg_match('/^API_KEY_(\d+)$/', $key, $matches) && !in_array($key, $processedKeys)) {
                $keyNum = intval($matches[1]);
                $secondaryKeys[$keyNum] = [
                    'name' => $key,
                    'value' => $this->parseKeyValue($value),
                    'model' => $this->getKeyModel($key) ?: 'gpt-4-turbo',
                    'index' => $keyNum
                ];
            }
        }
        
        // Sort by key number
        ksort($secondaryKeys);
        
        if (!empty($secondaryKeys)) {
            // Reindex to sequential
            $secondaryKeys = array_values($secondaryKeys);
            
            $accounts['account_2_secondary'] = [
                'account_id' => 2,
                'account_name' => 'OpenAI Account 2 (Secondary)',
                'provider' => 'OpenAI',
                'priority' => 2,
                'keys' => $secondaryKeys
            ];
        }
        
        return $accounts;
    }
    
    /**
     * Extract keys matching a regex pattern
     * 
     * @param string $pattern Regex pattern (without delimiters)
     * @return array Extracted keys with models
     */
    private function extractKeysByPattern($pattern) {
        $keys = [];
        $matches = [];
        
        foreach ($this->envData as $key => $value) {
            if (preg_match("/^{$pattern}$/", $key, $m)) {
                $keyNum = intval($m[1]);
                $keys[$keyNum] = [
                    'name' => $key,
                    'value' => $this->parseKeyValue($value),
                    'model' => $this->getKeyModel($key) ?: 'gpt-4-turbo',
                    'index' => $keyNum
                ];
            }
        }
        
        // Sort by key number
        ksort($keys);
        
        // Reindex to sequential
        return array_values($keys);
    }
    
    /**
     * Parse API key value (handles comma-separated or single values)
     * 
     * @param string $value Raw value from .env
     * @return string Clean API key
     */
    private function parseKeyValue($value) {
        // Remove trailing comma if present
        $value = rtrim($value, ',');
        return trim($value);
    }
    
    /**
     * Get model for a specific key from .env
     * Format: API_KEY_1_MODEL=gpt-4
     * 
     * @param string $keyName Key name (e.g., 'API_KEY_1')
     * @return string|null Model name or null if not set
     */
    private function getKeyModel($keyName) {
        // Try to find corresponding _MODEL variable
        $modelKey = $keyName . '_MODEL';
        return $this->envData[$modelKey] ?? null;
    }
    
    /**
     * Get a specific account by name
     * 
     * @param string $accountName Key from loadAllAccounts() result
     * @return array|null Account data or null
     */
    public function getAccount($accountName) {
        $accounts = $this->loadAllAccounts();
        return $accounts[$accountName] ?? null;
    }
    
    /**
     * Get all keys from a specific account
     * 
     * @param string $accountName Account key
     * @return array Keys array or empty
     */
    public function getAccountKeys($accountName) {
        $account = $this->getAccount($accountName);
        return $account['keys'] ?? [];
    }
    
    /**
     * Get total key count across all accounts
     * 
     * @return int Total number of API keys
     */
    public function getTotalKeyCount() {
        $count = 0;
        $accounts = $this->loadAllAccounts();
        foreach ($accounts as $account) {
            $count += count($account['keys']);
        }
        return $count;
    }
    
    /**
     * Get environment variable
     * 
     * @param string $key Variable name
     * @param string|null $default Default value if not found
     * @return string|null Value
     */
    public function getEnv($key, $default = null) {
        return $this->envData[$key] ?? $default;
    }
}
?>
