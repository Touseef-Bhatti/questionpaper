<?php

class MongoSearchLogger {
    private $manager;
    private $database;
    private $collection;

    public function __construct() {
        // Determine which environment file to use
        $envPath = __DIR__ . '/../config/.env.local';
        
        // Check if in production environment
        if ((defined('ENVIRONMENT') && ENVIRONMENT === 'production') || getenv('APP_ENV') === 'production') {
            $envPath = __DIR__ . '/../config/.env.production';
        }
        
        // Use .env.production if it exists and .env.local doesn't
        if (!file_exists($envPath)) {
            $alternativePath = (strpos($envPath, '.env.production') !== false) ? 
                __DIR__ . '/../config/.env.local' : 
                __DIR__ . '/../config/.env.production';
            if (file_exists($alternativePath)) {
                $envPath = $alternativePath;
            }
        }
        
        $mongoUri = 'mongodb://localhost:27017'; // Default fallback
        $this->database = 'paper_bhatti'; // Default fallback
        $this->collection = 'search_logs';

        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);
                    if ($name === 'MONGODB_URI') {
                        $mongoUri = $value;
                    }
                    if ($name === 'MONGODB_DB') {
                        $this->database = $value;
                    }
                    if ($name === 'MONGODB_COLLECTION') {
                        $this->collection = $value;
                    }
                }
            }
        }

        try {
            $managerClass = '\MongoDB\Driver\Manager';
            if (class_exists($managerClass)) {
                $this->manager = new $managerClass($mongoUri);
            } else {
                error_log("MongoDB extension not loaded");
                $this->manager = null;
            }
        } catch (Exception $e) {
            error_log("MongoDB Connection Error: " . $e->getMessage());
            $this->manager = null;
        }
    }

    public function logSearch($query, $source = 'web') {
        if (!$this->manager) return;

        try {
            $bulkClass = '\MongoDB\Driver\BulkWrite';
            $bsonDateClass = '\MongoDB\BSON\UTCDateTime';
            
            if (!class_exists($bulkClass) || !class_exists($bsonDateClass)) return;

            $bulk = new $bulkClass;
            $doc = [
                'query' => $query,
                'source' => $source,
                'timestamp' => new $bsonDateClass((new DateTime())->getTimestamp() * 1000),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'session_id' => session_id()
            ];

            $bulk->insert($doc);

            $this->manager->executeBulkWrite("{$this->database}.{$this->collection}", $bulk);
        } catch (Exception $e) {
            error_log("MongoDB Write Error: " . $e->getMessage());
        }
    }
}
