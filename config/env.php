<?php
/**
 * Environment Configuration Loader
 * Loads environment variables from .env file
 */

class EnvLoader 
{
    private static $loaded = false;
    
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
            // Detect environment
            $serverName = $_SERVER['SERVER_NAME'] ?? 'production';
            $isLocal = in_array($serverName, ['localhost', '127.0.0.1', '::1']);

            // Choose the right env file
            $envFile = $isLocal ? __DIR__ . '/.env.local' : __DIR__ . '/.env.production';
        }
        
        if (!file_exists($envFile)) {
            // Try to use environment variables directly if .env doesn't exist
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
        
        // Return default if not found
        return $default;
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
