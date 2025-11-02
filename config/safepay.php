<?php
/**
 * SafePay Payment Gateway Configuration
 * Uses environment variables for secure credential management
 */

require_once __DIR__ . '/env.php';

return [
    'environment' => EnvLoader::get('SAFEPAY_ENVIRONMENT', 'sandbox'),
    
    // SafePay API Credentials from environment variables
    'apiKey' => EnvLoader::get('SAFEPAY_API_KEY'),
    'v1Secret' => EnvLoader::get('SAFEPAY_V1_SECRET'),
    'webhookSecret' => EnvLoader::get('SAFEPAY_WEBHOOK_SECRET'),
    
    // Application URLs (environment-aware)
    'success_url' => EnvLoader::get('APP_URL', 'https://paper.bhattichemicalsindustry.com.pk') . '/payment/success.php',
    'cancel_url' => EnvLoader::get('APP_URL', 'https://paper.bhattichemicalsindustry.com.pk') . '/payment/cancel.php',
    'webhook_url' => EnvLoader::get('APP_URL', 'https://paper.bhattichemicalsindustry.com.pk') . '/payment/webhook.php',
    
    // Payment settings
    'currency' => EnvLoader::get('DEFAULT_CURRENCY', 'PKR'),
    'order_prefix' => EnvLoader::get('ORDER_ID_PREFIX', 'QPG_'),
    
    // Security settings
    'csrf_token_expiry' => EnvLoader::getInt('CSRF_TOKEN_EXPIRY', 3600),
    'payment_timeout' => EnvLoader::getInt('PAYMENT_TIMEOUT', 1800),
    
    // Feature flags
    'log_payments' => EnvLoader::getBool('LOG_PAYMENTS', true),
    'log_webhooks' => EnvLoader::getBool('LOG_WEBHOOKS', true),
    'send_payment_emails' => EnvLoader::getBool('SEND_PAYMENT_EMAILS', true),
    'enable_payment_retry' => EnvLoader::getBool('ENABLE_PAYMENT_RETRY', true),
    'max_payment_retries' => EnvLoader::getInt('MAX_PAYMENT_RETRIES', 3),
    
    // Contact settings
    'admin_email' => EnvLoader::get('ADMIN_EMAIL', 'admin@questionpaper.com'),
    
    // Rate limiting
    'webhook_rate_limit' => EnvLoader::getInt('WEBHOOK_RATE_LIMIT', 60),
    'api_rate_limit' => EnvLoader::getInt('API_RATE_LIMIT', 100),
    
    // Validation
    'validate_config' => function() {
        $required = [
            'SAFEPAY_API_KEY' => 'SafePay API Key',
            'SAFEPAY_V1_SECRET' => 'SafePay V1 Secret', 
            'SAFEPAY_WEBHOOK_SECRET' => 'SafePay Webhook Secret'
        ];
        $missing = [];
        
        foreach ($required as $var => $description) {
            $value = EnvLoader::get($var);
            if (empty($value) || $value === "your_safepay_{$var}_here") {
                $missing[] = "$description ($var)";
            }
        }
        
        if (!empty($missing)) {
            $errorMsg = "\n❌ Missing or invalid SafePay configuration!\n\n";
            $errorMsg .= "Please update your config/.env file with actual SafePay credentials:\n";
            foreach ($missing as $item) {
                $errorMsg .= "  - $item\n";
            }
            $errorMsg .= "\nGet your credentials from SafePay Dashboard: https://getsafepay.com/\n";
            $errorMsg .= "Then update the corresponding values in config/.env\n\n";
            
            throw new Exception($errorMsg);
        }
        
        return true;
    }
];
