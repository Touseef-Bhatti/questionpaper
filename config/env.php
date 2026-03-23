<?php
/**
 * Environment Configuration Loader
 * Loads environment variables from .env file
 */

class EnvLoader
{
    private static $loaded = false;
    
    // Define default values if not present in .env
    private static $defaults = [
        'BASE_URL' => 'https://ahmadlearninghub.com.pk/',
        'AI_DEFAULT_MODEL' => 'gpt-4-turbo',
        'AI_FALLBACK_MODEL' => 'gpt-3.5-turbo',
        'AI_DAILY_QUOTA_PER_KEY' => '100000',
        'AI_MAX_RETRIES' => '3',
        'AI_RETRY_DELAY_MS' => '100',
        'GENERATING_KEYWORDS_KEY' => '',
    ];

    /**
     * Reset the loader to allow reloading
     */
    public static function reset()
    {
        self::$loaded = false;
    }
    
    public static function load($envFile = null)
    {
        if (self::$loaded) return;
        
        // If no specific env file provided, detect environment automatically
        if ($envFile === null) {
            // Strategy 1: Check APP_ENV environment variable first (for Docker/CI-CD)
            $appEnv = getenv('APP_ENV');
            if (!$appEnv && isset($_ENV['APP_ENV'])) {
                $appEnv = $_ENV['APP_ENV'];
            }
            
            // Strategy 2: Check server hostname
            $serverName = strtolower($_SERVER['SERVER_NAME'] ?? getenv('HOSTNAME') ?? 'production');
            $isDocker = !empty(getenv('DOCKER_ENV')) || getenv('HOSTNAME') === 'docker-host' || file_exists('/.dockerenv');
            $isLocal = in_array($serverName, ['localhost', '127.0.0.1', '::1', 'db', 'docker']);
            
            // Strategy 3: Build priority list
            $candidates = [];
            
            // If APP_ENV is explicitly set, prefer matching file
            if ($appEnv && strtolower($appEnv) === 'production') {
                $candidates[] = __DIR__ . '/.env.production';
            } elseif ($appEnv && strtolower($appEnv) === 'development') {
                $candidates[] = __DIR__ . '/.env.local';
            }
            
            // If local/docker in early stage, prefer local
            if ($isLocal && !$appEnv) {
                $candidates[] = __DIR__ . '/.env.local';
            }
            
            // Add standard files
            $candidates[] = __DIR__ . '/.env.production';
            $candidates[] = __DIR__ . '/.env.local';
            $candidates[] = __DIR__ . '/.env';
            $candidates[] = __DIR__ . '/../.env.production';
            $candidates[] = __DIR__ . '/../.env.local';
            $candidates[] = __DIR__ . '/../.env';
            
            // Use the first existing file
            $envFile = null;
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $envFile = $candidate;
                    break;
                }
            }
            
            // If absolutely nothing found, default to .env
            if (!$envFile) {
                $envFile = __DIR__ . '/.env';
            }
        }
        
        if (!file_exists($envFile)) {
            // Try to use environment variables directly if .env doesn't exist
            // And set defaults for missing env vars
            foreach (self::$defaults as $key => $value) {
                if (!getenv($key) && !isset($_ENV[$key])) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
            self::$loaded = true;
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Remove quotes if present
                $value = trim($value, '"\'');
                
                // Convert string booleans to actual booleans
                if (strtolower($value) === 'true') $value = true;
                if (strtolower($value) === 'false') $value = false;
                
                // Handle empty values explicitly
                if ($value === '') $value = '';
                
                // Set environment variables
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }

        // Set defaults for any variables not explicitly set in the .env file
        foreach (self::$defaults as $key => $value) {
            if (!isset($_ENV[$key]) && getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable with fallback
     */
    public static function get($key, $default = null)
    {
        self::load();
        
        // Check $_ENV first
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        
        // Then check getenv
        $envValue = getenv($key);
        if ($envValue !== false) {
            return $envValue;
        }

        // Check defaults
        if (array_key_exists($key, self::$defaults)) {
            return self::$defaults[$key];
        }
        
        // Return default if not found
        return $default;
    }
    
    /**
     * Get list of values from environment variable (comma separated)
     * Modified to prioritize Account 2 (suffix _1) over Primary (base)
     */
    public static function getList($key)
    {
        $values = [];
        
        // 1. Account 2 (Suffix _1) - Prioritized per user request
        $account2 = self::get($key . '_1', '');
        if (!empty($account2)) {
            $values = array_merge($values, array_map('trim', explode(',', $account2)));
        }
        
        // 2. Primary Account (Base key)
        $base = self::get($key, '');
        if (!empty($base)) {
            $values = array_merge($values, array_map('trim', explode(',', $base)));
        }
        
        // 3. Other Backup Accounts (Suffix _2 to _10)
        for ($i = 2; $i <= 10; $i++) {
            $suffixVal = self::get($key . '_' . $i, '');
            if (!empty($suffixVal)) {
                $values = array_merge($values, array_map('trim', explode(',', $suffixVal)));
            }
        }
        
        // Filter empty values and remove duplicates
        return array_values(array_unique(array_filter($values)));
    }

    /**
     * Get boolean environment variable
     */
    public static function getBool($key, $default = false)
    {
        $value = self::get($key, $default);
        
        if (is_bool($value)) return $value;
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
        }
        
        return (bool)$value;
    }
    
    /**
     * Get integer environment variable
     */
    public static function getInt($key, $default = 0)
    {
        return (int)self::get($key, $default);
    }
    
    /**
     * Check if we're in production
     */
    public static function isProduction()
    {
        return strtolower(self::get('APP_ENV', 'development')) === 'production';
    }
    
    /**
     * Check if we're in development
     */
    public static function isDevelopment()
    {
        return !self::isProduction();
    }
}

// Auto-load environment variables
EnvLoader::load();
?>
