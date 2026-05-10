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
    private $lastIndexKey = 'ai_last_key_index';

    public function __construct($cacheManager = null) {
        $this->cacheManager = $cacheManager;
        $this->dailyQuota = (int) (EnvLoader::get('AI_DAILY_QUOTA_PER_KEY', 100000));
        $this->loadKeys();
        $this->checkDailyReset();
    }

    /**
     * Load all KEY_N from .env in strict numeric order: KEY_1, KEY_2, ... KEY_N.
     * No account grouping — first request uses KEY_1, next uses KEY_2, etc.
     * To add a key: just add KEY_8=sk-... KEY_8_MODEL=... to .env.
     */
    private function loadKeys() {
        $defaultModel = EnvLoader::get('AI_DEFAULT_MODEL', '');

        for ($i = 1; $i <= 99; $i++) {
            $key = trim((string) EnvLoader::get('KEY_' . $i, ''));
            if ($key === '') continue;

            $model = trim((string) EnvLoader::get('KEY_' . $i . '_MODEL', $defaultModel));
            $this->keys[] = [
                'key'   => $key,
                'model' => $model ?: $defaultModel,
                'id'    => $i,
            ];
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
     * Get next available API key with model (Round-robin rotation)
     * Skips exhausted keys and excludes specific keys if requested
     */
    public function getNextKey($excludeKeys = []) {
        if (empty($this->keys)) return null;

        // 1. Get last used index from cache
        $lastIndex = 0;
        if ($this->cacheManager) {
            try {
                $lastIndex = (int) $this->cacheManager->get($this->lastIndexKey);
            } catch (Exception $e) {
                $lastIndex = 0;
            }
        }

        $totalKeys = count($this->keys);
        
        // 2. Start searching from the next key (Round-robin)
        for ($i = 1; $i <= $totalKeys; $i++) {
            $currentIndex = ($lastIndex + $i) % $totalKeys;
            $item = $this->keys[$currentIndex];

            if (in_array($item['key'], $excludeKeys, true)) continue;
            if ($this->isKeyExhausted($item['key'])) continue;

            // 3. Store the successful index for the next rotation
            if ($this->cacheManager) {
                try {
                    $this->cacheManager->setex($this->lastIndexKey, 86400 * 7, (string) $currentIndex);
                } catch (Exception $e) {}
            }

            return $item;
        }

        // 4. Fallback: If no rotation key found, try the very first available key (standard failover)
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
