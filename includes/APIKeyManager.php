<?php
require_once __DIR__ . '/../db_connect.php';

class APIKeyManager {
    private $conn;

    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->ensureTableExists(); // Ensure table exists before doing anything
        $this->checkDailyReset();
    }

    public function ensureTableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            key_value TEXT NOT NULL,
            key_hash VARCHAR(64) NOT NULL,
            provider VARCHAR(50) DEFAULT 'openai',
            account_name VARCHAR(100) DEFAULT 'Primary Account',
            usage_count INT DEFAULT 0,
            error_count INT DEFAULT 0,
            status ENUM('active', 'exhausted', 'rate_limited', 'quota_exceeded') DEFAULT 'active',
            last_used DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (key_hash),
            INDEX (provider),
            INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $this->conn->query($sql);
        
        // Add error_count column if it doesn't exist (for existing tables)
        $check = $this->conn->query("SHOW COLUMNS FROM api_keys LIKE 'error_count'");
        if ($check && $check->num_rows == 0) {
            $this->conn->query("ALTER TABLE api_keys ADD COLUMN error_count INT DEFAULT 0 AFTER usage_count");
        }
    }

    private function checkDailyReset() {
        // Set timezone for this check
        $tz = new DateTimeZone('Asia/Karachi');
        $now = new DateTime('now', $tz);
        $today = $now->format('Y-m-d');
        
        // Use cache directory for tracking
        $trackerFile = __DIR__ . '/../cache/daily_reset_tracker.txt';
        
        $lastReset = file_exists($trackerFile) ? trim(file_get_contents($trackerFile)) : '';
        
        if ($lastReset !== $today) {
            // Reset usage for all keys
            // We check if it's past 12:00 AM PST (which is implied by the date change)
            $this->conn->query("UPDATE api_keys SET usage_count = 0");
            
            // Update tracker
            if (!is_dir(dirname($trackerFile))) {
                mkdir(dirname($trackerFile), 0755, true);
            }
            file_put_contents($trackerFile, $today);
            
            // Log reset if needed (optional)
            error_log("API Usage Reset for date: $today");
        }
    }



    private function getAppKey() {
        $key = EnvLoader::get('APP_KEY');
        if (empty($key)) {
            // Fallback for dev or error
            error_log("APP_KEY missing for encryption");
            return 'default_insecure_key_please_change'; 
        }
        return $key;
    }

    private function encrypt($value) {
        $key = $this->getAppKey();
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decrypt($value) {
        $key = $this->getAppKey();
        $data = base64_decode($value);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        if (strlen($data) < $ivLength) return $value; // Not encrypted or invalid
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
        
        // If decryption fails (e.g. wrong key), return empty string or handle error
        if ($decrypted === false) {
             error_log("APIKeyManager: Decryption failed for key value.");
             // Try to return original value if it looks like a key (starts with sk-)
             // But usually it's garbage if decrypted with wrong key.
             return ''; 
        }
        return $decrypted;
    }

    public function getAllKeys() {
        $sql = "SELECT * FROM api_keys ORDER BY account_name, created_at DESC";
        $result = $this->conn->query($sql);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        
        foreach ($rows as &$row) {
            $row['key_value'] = $this->decrypt($row['key_value']);
        }
        return $rows;
    }

    public function getActiveKeys($provider = 'openai') {
        $keys = [];
        
        // 1. First, check if we need to sync with EnvLoader
        // This ensures new keys added to .env are immediately available
        if (class_exists('EnvLoader')) {
            $envKeys = EnvLoader::getList('OPENAI_API_KEYS');
            
            // If Env has more keys than DB (or DB is empty), trigger import
            // Optimization: Just check counts roughly or just run import (it handles duplicates)
            // For now, we'll just run import if Env has keys. It's safe due to ignore on duplicates.
            if (!empty($envKeys)) {
                $this->importFromEnv(); 
            }
        }

        $sql = "SELECT key_value, account_name, last_used FROM api_keys WHERE status = 'active' AND provider = ? ORDER BY last_used ASC";
        
        // Check if table exists first to avoid fatal error if not migrated
        $check = $this->conn->query("SHOW TABLES LIKE 'api_keys'");
        if ($check && $check->num_rows > 0) {
            $stmt = $this->conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s', $provider);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $keys[] = [
                        'key' => $this->decrypt($row['key_value']),
                        'account' => $row['account_name'],
                        'last_used' => $row['last_used']
                    ];
                }
                $stmt->close();
            }
        }
        
        // If DB returned keys, we need to sort them to respect user priority (Account 2 first)
        // EnvLoader::getList() returns keys in the desired order: [Account 2 keys, Primary keys, ...]
        // We should try to match that order if the keys have never been used (last_used IS NULL)
        
        if (!empty($keys)) {
            // Extract just the key strings for final return
            $finalKeys = [];
            
            // Separate unused keys (likely new ones from Account 2) and used keys
            $unusedKeys = [];
            $usedKeys = [];
            
            foreach ($keys as $k) {
                if (is_null($k['last_used'])) {
                    $unusedKeys[] = $k;
                } else {
                    $usedKeys[] = $k;
                }
            }
            
            // For unused keys, we want to respect EnvLoader order if possible
            // But since we just want Account 2 (Account 1 in DB?) to be first...
            // Account 2 in DB is stored as "Account 1" (from suffix _1)
            
            usort($unusedKeys, function($a, $b) {
                // Prioritize "Account 2" specifically as requested
                if ($a['account'] === 'Account 2' && $b['account'] !== 'Account 2') return -1;
                if ($b['account'] === 'Account 2' && $a['account'] !== 'Account 2') return 1;
                
                // Then Prioritize "Account 1"
                if ($a['account'] === 'Account 1' && $b['account'] !== 'Account 1') return -1;
                if ($b['account'] === 'Account 1' && $a['account'] !== 'Account 1') return 1;
                
                // General: Numbered accounts before Primary
                $aIsNumbered = strpos($a['account'], 'Account') === 0;
                $bIsNumbered = strpos($b['account'], 'Account') === 0;
                if ($aIsNumbered && !$bIsNumbered) return -1;
                if (!$aIsNumbered && $bIsNumbered) return 1;
                
                return 0;
            });
            
            // Merge: Unused (New/Priority) -> Used (Old)
            // This ensures fresh keys from Account 2 get used first
            foreach (array_merge($unusedKeys, $usedKeys) as $k) {
                $finalKeys[] = $k['key'];
            }
            
            return $finalKeys;
        }
        
        // Fallback to EnvLoader if no keys in DB or table missing
        if (empty($keys) && class_exists('EnvLoader')) {
            $keys = EnvLoader::getList('OPENAI_API_KEYS');
        }
        
        return $keys;
    }

    public function addKey($key, $account_name, $provider = 'openai') {
        $key = trim($key);
        if (empty($key)) return false;

        $key_hash = hash('sha256', $key);
        $encrypted_key = $this->encrypt($key);

        try {
            $stmt = $this->conn->prepare("INSERT INTO api_keys (key_value, key_hash, account_name, provider) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $encrypted_key, $key_hash, $account_name, $provider);
            return $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            // Check for duplicate entry error code (1062)
            if ($e->getCode() === 1062) {
                // Key already exists, just ignore
                return false;
            }
            // Throw other errors
            throw $e;
        }
    }


    public function updateStatus($id, $status) {
        $stmt = $this->conn->prepare("UPDATE api_keys SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        return $stmt->execute();
    }

    public function deleteKey($id) {
        $stmt = $this->conn->prepare("DELETE FROM api_keys WHERE id = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    public function logUsage($key_value) {
        // Need hash to find record
        $key_hash = hash('sha256', $key_value);
        $stmt = $this->conn->prepare("UPDATE api_keys SET usage_count = usage_count + 1, last_used = NOW() WHERE key_hash = ?");
        $stmt->bind_param('s', $key_hash);
        $stmt->execute();
    }

    public function logError($key_value) {
        // Need hash to find record
        $key_hash = hash('sha256', $key_value);
        $stmt = $this->conn->prepare("UPDATE api_keys SET error_count = error_count + 1 WHERE key_hash = ?");
        $stmt->bind_param('s', $key_hash);
        $stmt->execute();
    }
    
    public function getStats() {
        $sql = "SELECT 
                    account_name, 
                    COUNT(*) as total_keys, 
                    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_keys,
                    SUM(usage_count) as total_usage,
                    SUM(error_count) as total_errors
                FROM api_keys 
                GROUP BY account_name";
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function importFromEnv() {
        // Assume EnvLoader is already loaded via db_connect -> env.php
        if (!class_exists('EnvLoader')) return false;

        $imported = 0;
        
        // 1. Import Backup Keys (Suffixes 1-10) FIRST to ensure they get attributed to specific accounts
        // This is important for prioritization logic in getActiveKeys
        for ($i = 1; $i <= 10; $i++) {
            $raw = EnvLoader::get('OPENAI_API_KEYS_' . $i, '');
            if (!empty($raw)) {
                $keys = explode(',', $raw);
                foreach ($keys as $key) {
                    if ($this->addKey($key, "Account $i", 'openai')) $imported++;
                }
            }
        }
        
        // 2. Import Base Keys (Primary)
        $rawBase = EnvLoader::get('OPENAI_API_KEYS', '');
        if (!empty($rawBase)) {
             $keys = explode(',', $rawBase);
             foreach ($keys as $key) {
                 if ($this->addKey($key, "Primary Account", 'openai')) $imported++;
             }
        }
        
        return $imported;
    }
}
?>
