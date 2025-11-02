<?php
/**
 * Cache Manager
 * Provides unified caching interface with Redis and Memcached fallback
 * Implements caching strategies for optimal performance
 */

class CacheManager
{
    private $redis;
    private $memcached;
    private $useRedis = false;
    private $useMemcached = false;
    private $fallbackToFile = true;
    private $cacheDir;
    
    public function __construct($config = [])
    {
        $this->cacheDir = $config['cache_dir'] ?? __DIR__ . '/../cache/';
        
        // Try to initialize Redis
        if (extension_loaded('redis')) {
            try {
                $this->redis = new Redis();
                $host = $config['redis_host'] ?? '127.0.0.1';
                $port = $config['redis_port'] ?? 6379;
                $this->redis->connect($host, $port);
                $this->useRedis = true;
                error_log("CacheManager: Redis connected successfully");
            } catch (Exception $e) {
                error_log("CacheManager: Redis connection failed - " . $e->getMessage());
            }
        }
        
        // Fallback to Memcached if Redis is not available
        if (!$this->useRedis && extension_loaded('memcached')) {
            try {
                $this->memcached = new Memcached();
                $host = $config['memcached_host'] ?? '127.0.0.1';
                $port = $config['memcached_port'] ?? 11211;
                $this->memcached->addServer($host, $port);
                $this->useMemcached = true;
                error_log("CacheManager: Memcached connected successfully");
            } catch (Exception $e) {
                error_log("CacheManager: Memcached connection failed - " . $e->getMessage());
            }
        }
        
        // Create cache directory for file fallback
        if ($this->fallbackToFile && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        if (!$this->useRedis && !$this->useMemcached && $this->fallbackToFile) {
            error_log("CacheManager: Using file-based caching as fallback");
        }
    }
    
    /**
     * Get cached value
     */
    public function get($key)
    {
        if ($this->useRedis) {
            return $this->redis->get($key);
        }
        
        if ($this->useMemcached) {
            return $this->memcached->get($key);
        }
        
        if ($this->fallbackToFile) {
            return $this->fileGet($key);
        }
        
        return false;
    }
    
    /**
     * Set cache value with expiration
     */
    public function setex($key, $ttl, $value)
    {
        if ($this->useRedis) {
            return $this->redis->setex($key, $ttl, $value);
        }
        
        if ($this->useMemcached) {
            return $this->memcached->set($key, $value, time() + $ttl);
        }
        
        if ($this->fallbackToFile) {
            return $this->fileSetex($key, $ttl, $value);
        }
        
        return false;
    }
    
    /**
     * Set cache value (permanent until deleted)
     */
    public function set($key, $value, $ttl = 0)
    {
        if ($ttl > 0) {
            return $this->setex($key, $ttl, $value);
        }
        
        if ($this->useRedis) {
            return $this->redis->set($key, $value);
        }
        
        if ($this->useMemcached) {
            return $this->memcached->set($key, $value);
        }
        
        if ($this->fallbackToFile) {
            return $this->fileSet($key, $value);
        }
        
        return false;
    }
    
    /**
     * Delete cache key
     */
    public function del($keys)
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        
        if ($this->useRedis) {
            return $this->redis->del($keys);
        }
        
        if ($this->useMemcached) {
            $deleted = 0;
            foreach ($keys as $key) {
                if ($this->memcached->delete($key)) {
                    $deleted++;
                }
            }
            return $deleted;
        }
        
        if ($this->fallbackToFile) {
            return $this->fileDelete($keys);
        }
        
        return 0;
    }
    
    /**
     * Get keys matching pattern (Redis only)
     */
    public function keys($pattern)
    {
        if ($this->useRedis) {
            return $this->redis->keys($pattern);
        }
        
        // Memcached and file cache don't support pattern matching
        return [];
    }
    
    /**
     * Clear all cache
     */
    public function flushAll()
    {
        if ($this->useRedis) {
            return $this->redis->flushAll();
        }
        
        if ($this->useMemcached) {
            return $this->memcached->flush();
        }
        
        if ($this->fallbackToFile) {
            return $this->fileFlushAll();
        }
        
        return false;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $stats = [
            'type' => 'unknown',
            'hits' => 0,
            'misses' => 0,
            'memory_usage' => 0
        ];
        
        if ($this->useRedis) {
            $info = $this->redis->info();
            $stats = [
                'type' => 'redis',
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
                'memory_usage' => $info['used_memory'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0
            ];
        } elseif ($this->useMemcached) {
            $memStats = $this->memcached->getStats();
            $serverStats = reset($memStats);
            $stats = [
                'type' => 'memcached',
                'hits' => $serverStats['get_hits'] ?? 0,
                'misses' => $serverStats['get_misses'] ?? 0,
                'memory_usage' => $serverStats['bytes'] ?? 0,
                'current_connections' => $serverStats['curr_connections'] ?? 0
            ];
        } elseif ($this->fallbackToFile) {
            $stats['type'] = 'file';
            $stats['memory_usage'] = $this->getFileCacheSize();
        }
        
        return $stats;
    }
    
    /**
     * File-based cache get
     */
    private function fileGet($key)
    {
        $filename = $this->getCacheFilename($key);
        
        if (!file_exists($filename)) {
            return false;
        }
        
        $data = file_get_contents($filename);
        if ($data === false) {
            return false;
        }
        
        $decoded = json_decode($data, true);
        if (!$decoded || !isset($decoded['expires'], $decoded['value'])) {
            return false;
        }
        
        // Check expiration
        if ($decoded['expires'] > 0 && time() > $decoded['expires']) {
            unlink($filename);
            return false;
        }
        
        return $decoded['value'];
    }
    
    /**
     * File-based cache setex
     */
    private function fileSetex($key, $ttl, $value)
    {
        $filename = $this->getCacheFilename($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($filename, json_encode($data), LOCK_EX) !== false;
    }
    
    /**
     * File-based cache set (no expiration)
     */
    private function fileSet($key, $value)
    {
        $filename = $this->getCacheFilename($key);
        $data = [
            'value' => $value,
            'expires' => 0,
            'created' => time()
        ];
        
        return file_put_contents($filename, json_encode($data), LOCK_EX) !== false;
    }
    
    /**
     * File-based cache delete
     */
    private function fileDelete($keys)
    {
        $deleted = 0;
        foreach ($keys as $key) {
            $filename = $this->getCacheFilename($key);
            if (file_exists($filename) && unlink($filename)) {
                $deleted++;
            }
        }
        return $deleted;
    }
    
    /**
     * File-based cache flush all
     */
    private function fileFlushAll()
    {
        if (!is_dir($this->cacheDir)) {
            return true;
        }
        
        $files = glob($this->cacheDir . '*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted > 0;
    }
    
    /**
     * Get file cache total size
     */
    private function getFileCacheSize()
    {
        if (!is_dir($this->cacheDir)) {
            return 0;
        }
        
        $size = 0;
        $files = glob($this->cacheDir . '*.cache');
        
        foreach ($files as $file) {
            $size += filesize($file);
        }
        
        return $size;
    }
    
    /**
     * Get cache filename for key
     */
    private function getCacheFilename($key)
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cacheDir . $safeKey . '.cache';
    }
    
    /**
     * Clean up expired file cache entries
     */
    public function cleanupExpired()
    {
        if (!$this->fallbackToFile || !is_dir($this->cacheDir)) {
            return 0;
        }
        
        $files = glob($this->cacheDir . '*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $data = file_get_contents($file);
            if ($data) {
                $decoded = json_decode($data, true);
                if ($decoded && isset($decoded['expires']) && 
                    $decoded['expires'] > 0 && time() > $decoded['expires']) {
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Get cache hit ratio
     */
    public function getHitRatio()
    {
        $stats = $this->getStats();
        $total = $stats['hits'] + $stats['misses'];
        
        if ($total === 0) {
            return 0;
        }
        
        return round(($stats['hits'] / $total) * 100, 2);
    }
    
    /**
     * Check if caching is available
     */
    public function isAvailable()
    {
        return $this->useRedis || $this->useMemcached || $this->fallbackToFile;
    }
    
    /**
     * Get cache type being used
     */
    public function getCacheType()
    {
        if ($this->useRedis) return 'redis';
        if ($this->useMemcached) return 'memcached';
        if ($this->fallbackToFile) return 'file';
        return 'none';
    }
}
?>
