<?php
/**
 * AI Key Rotator - Intelligent API key rotation with account prioritization
 *
 * Rotation order: Account 2 → Primary → Account 3 → Additional (auto-detected)
 * Auto-detects all KEY_N from .env - just add KEY_10=... and it works.
 */

class AIKeyRotator {
    private $cacheManager;
    private $keys = [];
    private $dailyQuota;
    private $exhaustedPrefix = 'ai_key_exhausted_';
    private $usagePrefix = 'ai_key_usage_';
    private $dateKey = 'ai_key_rotation_date';

    public function __construct($cacheManager = null) {
        $this->cacheManager = $cacheManager;
        $this->dailyQuota = (int) (EnvLoader::get('AI_DAILY_QUOTA_PER_KEY', 100000));
        $this->loadKeys();
        $this->checkDailyReset();
    }

    /**
     * Auto-detect all KEY_N from env and load in rotation order.
     * Just add KEY_N=sk-... to .env - no extra config needed.
     */
    private function loadKeys() {
        $defaultModel = EnvLoader::get('AI_DEFAULT_MODEL', 'liquid/lfm-2.5-1.2b-thinking:free');

        // 1. Scan KEY_1..KEY_99 - any key that exists is loaded
        $found = [];
        for ($i = 1; $i <= 99; $i++) {
            $key = EnvLoader::get('KEY_' . $i, '');
            if (!empty(trim($key))) {
                $model = EnvLoader::get('KEY_' . $i . '_MODEL', $defaultModel);
                $found[$i] = [
                    'key' => trim($key),
                    'model' => trim($model) ?: $defaultModel,
                    'id' => $i,
                ];
            }
        }
        if (empty($found)) return;

        // 2. Ranges for ordering (optional)
        $account2Start = (int) EnvLoader::get('ACCOUNT_2_KEYS_START', 3);
        $account2End = (int) EnvLoader::get('ACCOUNT_2_KEYS_END', 9);
        $primaryStart = (int) EnvLoader::get('PRIMARY_KEYS_START', 1);
        $primaryEnd = (int) EnvLoader::get('PRIMARY_KEYS_END', 2);
        $account3Start = (int) EnvLoader::get('ACCOUNT_3_KEYS_START', 0);
        $account3End = (int) EnvLoader::get('ACCOUNT_3_KEYS_END', 0);

        // Auto: if Account3 start set but end not, include all keys from start to max
        if ($account3Start > 0 && $account3End < $account3Start) {
            $account3End = max(array_keys($found));
        }

        // 3. Build order: Account2 → Primary → Account3 → remaining
        $order = [];
        $used = [];
        $ranges = [
            [$account2Start, $account2End, 'Account2'],
            [$primaryStart, $primaryEnd, 'Primary'],
            [$account3Start, $account3End, 'Account3'],
        ];
        foreach ($ranges as $r) {
            list($start, $end, $account) = $r;
            if ($start > 0 && $end >= $start) {
                for ($i = $start; $i <= $end; $i++) {
                    if (isset($found[$i]) && !in_array($i, $used)) {
                        $order[] = $i;
                        $used[] = $i;
                    }
                }
            }
        }
        foreach (array_keys($found) as $num) {
            if (!in_array($num, $used)) {
                $order[] = $num;
            }
        }

        foreach ($order as $num) {
            $account = ($num >= $account2Start && $num <= $account2End) ? 'Account2'
                : (($num >= $primaryStart && $num <= $primaryEnd) ? 'Primary' : 'Account3');
            $this->keys[] = array_merge($found[$num], ['account' => $account]);
        }
    }

    private function checkDailyReset() {
        if (!$this->cacheManager) return;
        try {
            $stored = $this->cacheManager->get($this->dateKey);
            $today = date('Y-m-d');
            if ($stored !== $today) {
                $pattern = $this->exhaustedPrefix . '*';
                if (method_exists($this->cacheManager, 'keys')) {
                    $keys = $this->cacheManager->keys($pattern);
                    if (!empty($keys)) {
                        $this->cacheManager->del($keys);
                    }
                }
                $this->cacheManager->setex($this->dateKey, 86400 * 2, $today);
            }
        } catch (Exception $e) {}
    }

    /**
     * Check if key is exhausted (rate limited or quota exceeded)
     */
    public function isKeyExhausted($apiKey) {
        if (!$this->cacheManager) return false;
        $hash = substr(hash('sha256', $apiKey), 0, 16);
        $cacheKey = $this->exhaustedPrefix . $hash;
        try {
            return $this->cacheManager->get($cacheKey) === '1';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Mark key as exhausted (until daily reset)
     */
    public function markExhausted($apiKey, $ttl = 86400) {
        if (!$this->cacheManager) return;
        $hash = substr(hash('sha256', $apiKey), 0, 16);
        try {
            $this->cacheManager->setex($this->exhaustedPrefix . $hash, $ttl, '1');
        } catch (Exception $e) {}
    }

    /**
     * Get next available API key with model (skips exhausted keys)
     */
    public function getNextKey($excludeKeys = []) {
        foreach ($this->keys as $item) {
            if (in_array($item['key'], $excludeKeys, true)) continue;
            if ($this->isKeyExhausted($item['key'])) continue;
            return $item;
        }
        return null;
    }

    /**
     * Get all non-exhausted keys (for parallel requests)
     */
    public function getAvailableKeys($maxCount = 5) {
        $available = [];
        foreach ($this->keys as $item) {
            if ($this->isKeyExhausted($item['key'])) continue;
            $available[] = $item;
            if (count($available) >= $maxCount) break;
        }
        return $available;
    }

    /**
     * Get keys in rotation order (raw list)
     */
    public function getAllKeys() {
        return $this->keys;
    }

    /**
     * Log successful use (optional - for tracking)
     */
    public function logSuccess($apiKey) {
        if (!$this->cacheManager) return;
        $hash = substr(hash('sha256', $apiKey), 0, 16);
        $key = $this->usagePrefix . $hash;
        try {
            $val = (int) $this->cacheManager->get($key);
            $this->cacheManager->setex($key, 86400 * 2, (string) ($val + 1));
        } catch (Exception $e) {}
    }
}
