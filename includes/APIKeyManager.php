<?php
require_once __DIR__ . '/../db_connect.php';

class APIKeyManager {
    private $conn;

    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->checkDailyReset();
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

    public function ensureTableExists() {
        // Table creation moved to install.php
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
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
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
        $sql = "SELECT key_value FROM api_keys WHERE status = 'active' AND provider = ? ORDER BY last_used ASC";
        
        // Check if table exists first to avoid fatal error if not migrated
        $check = $this->conn->query("SHOW TABLES LIKE 'api_keys'");
        if ($check && $check->num_rows > 0) {
            $stmt = $this->conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s', $provider);
                $stmt->execute();
                $result = $stmt->get_result();
                $keys = [];
                while ($row = $result->fetch_assoc()) {
                    $keys[] = $this->decrypt($row['key_value']);
                }
                $stmt->close();
            }
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
        
        // Import Base Keys (Primary)
        $rawBase = EnvLoader::get('OPENAI_API_KEYS', '');
        if (!empty($rawBase)) {
             $keys = explode(',', $rawBase);
             foreach ($keys as $key) {
                 if ($this->addKey($key, "Primary Account", 'openai')) $imported++;
             }
        }

        // Import Backup Keys (Suffixes 1-10)
        for ($i = 1; $i <= 10; $i++) {
            $raw = EnvLoader::get('OPENAI_API_KEYS_' . $i, '');
            if (!empty($raw)) {
                $keys = explode(',', $raw);
                foreach ($keys as $key) {
                    if ($this->addKey($key, "Account $i", 'openai')) $imported++;
                }
            }
        }
        
        return $imported;
    }
}
?>
