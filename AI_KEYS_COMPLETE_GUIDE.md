# AI Keys Management System - Complete Documentation

## Overview

The AI Keys Management System provides a unified, secure way to manage multiple API keys for various AI services (OpenAI, Anthropic, etc.) with:

- **New simplified naming:** `KEY_1`, `KEY_2` instead of `API_KEY_1_PRIMARY`, etc.
- **Per-key models:** Each key can use a different AI model
- **Automatic encryption:** Keys are encrypted at rest (AES-256-CBC)
- **Smart selection:** Least-used-first + priority-based routing
- **Usage tracking:** Monitor quota and costs per key
- **Health monitoring:** Automatic failure detection and circuit breaker
- **Easy scaling:** Add new keys by editing `.env.local`

---

## Configuration: `.config/.env.local`

### Format

```env
# New format: KEY_N=your_api_key_value
KEY_1=sk-or-v1-...
KEY_1_MODEL=gpt-4-turbo          # Optional: defaults to gpt-4-turbo
KEY_1_PROVIDER=openai             # Optional: defaults to openai

KEY_2=sk-or-v1-...
KEY_2_MODEL=gpt-3.5-turbo
KEY_2_PROVIDER=openai
```

### Account Structure

Keys are automatically split into accounts:
- **Account 1 (Priority 1 - Primary):** KEY_1 to KEY_5 (first half)
- **Account 2 (Priority 2 - Secondary):** KEY_6 onwards (fallback)

### System Settings

```env
# AI System Configuration
AI_ENCRYPTION_KEY=base64_encoded_32_byte_key
AI_DEFAULT_MODEL=gpt-4-turbo
AI_FALLBACK_MODEL=gpt-3.5-turbo
AI_DAILY_QUOTA_PER_KEY=100000
AI_MAX_RETRIES=3
AI_RETRY_DELAY_MS=100
AI_CIRCUIT_BREAKER_THRESHOLD=3
```

---

## Core Classes

### 1. AIKeyConfigManager (`config/AIKeyConfigManager.php`)

**Purpose:** Loads and parses configuration from `.env.local`

**Key Methods:**
```php
$config = new AIKeyConfigManager();

// Get all keys
$keys = $config->getAllKeys();

// Get specific key
$key = $config->getKeyById(1);
$key = $config->getKeyByName('key_1');

// Get accounts
$accounts = $config->getAllAccounts();
$account = $config->getAccountById(1);

// Get system settings
$settings = $config->getSystemConfig();

// Validate encryption
$isValid = $config->validateEncryptionKey();
```

### 2. AIKeysSystem (`services/AIKeysSystem.php`)

**Purpose:** Database operations and key management

**Key Methods:**
```php
$aiKeys = new AIKeysSystem($conn);

// Select best key (priority + least-used-first)
$key = $aiKeys->selectBestKey('openai');

// Get account information
$accounts = $aiKeys->getAllAccounts();
$stats = $aiKeys->getAccountStats(1);
$keys = $aiKeys->getAccountKeys(1);

// Track usage
$aiKeys->updateKeyUsage($keyId, $tokensUsed);

// Manage key status
$aiKeys->blockKey($keyId, 1800);  // Block for 30 minutes
$aiKeys->disableKey($keyId, "Too many failures");
$aiKeys->unblockExpiredKeys();

// Reset counters
$aiKeys->resetDailyCounters();

// Monitor health
$health = $aiKeys->getSystemHealth();
```

---

## Database Schema

### ai_accounts
```
- account_id: INT (Primary Key)
- account_name: VARCHAR(255) - "Primary Account", "Secondary Account (Fallback)"
- provider_name: VARCHAR(100) - "openai", "anthropic", etc.
- priority: INT - 1 (highest), 2 (secondary), etc.
- status: ENUM - active, suspended, disabled
- daily_quota: INT - Total tokens/day for account
- monthly_budget: DECIMAL - Monthly spending limit
- created_at, updated_at: TIMESTAMPS
```

### ai_api_keys
```
- key_id: INT (Primary Key)
- account_id: INT (Foreign Key to ai_accounts)
- api_key_hash: VARCHAR(255) - SHA256 hash (unique, for fast lookups)
- api_key_encrypted: LONGBLOB - AES-256-CBC encrypted key value
- key_name: VARCHAR(100) - "key_1", "key_2", etc.
- model_name: VARCHAR(255) - "gpt-4-turbo", "gpt-3.5-turbo", etc.
- daily_limit: INT - Tokens/day limit for this key
- used_today: INT - Current day usage (reset at midnight)
- status: ENUM - active, temporarily_blocked, exhausted, disabled
- last_used_at: TIMESTAMP - Last successful request
- consecutive_failures: INT - For circuit breaker
- temporary_block_until: TIMESTAMP - Auto-unblock time
- created_at, updated_at: TIMESTAMPS
- disabled_reason: VARCHAR(255) - Why key was disabled
```

---

## Admin Management

### Admin Dashboard: `admin/manage_ai_keys.php`

View and manage all keys from admin panel:

- **System Health:** Overview of keys, accounts, encryption status
- **Accounts Overview:** Keys per account, quota usage, status
- **Keys Table:** Detailed view of all keys with usage stats
- **System Settings:** View all configuration parameters

---

## Usage Examples

### Example 1: Use a Key in Your Code

```php
require_once 'db_connect.php';
require_once 'services/AIKeysSystem.php';

$aiKeys = new AIKeysSystem($conn);

// Get best available key
$key = $aiKeys->selectBestKey('openai');

if (!$key) {
    die('No available API keys');
}

// Use the key
$apiKey = $key['api_key'];
$model = $key['model_name'];

// Make API request
// ... your OpenAI call ...

// Track usage
$tokensUsed = 150;
$aiKeys->updateKeyUsage($key['key_id'], $tokensUsed);
```

### Example 2: Monitor Account Status

```php
$aiKeys = new AIKeysSystem($conn);

// Get all accounts
$accounts = $aiKeys->getAllAccounts();

foreach ($accounts as $account) {
    echo "Account: " . $account['account_name'] . "\n";
    echo "Priority: " . $account['priority'] . "\n";
    echo "Active Keys: " . $account['active_keys'] . "\n";
    echo "Remaining Quota: " . $account['remaining_quota'] . "\n";
}
```

### Example 3: Add New Keys

1. Edit `config/.env.local`:
```env
KEY_10=sk-or-v1-your_new_key_here
KEY_10_MODEL=gpt-4-turbo
KEY_10_PROVIDER=openai
```

2. Run `install.php` to import:
```
http://localhost:8000/install.php
```

3. Keys are automatically encrypted and stored in database

---

## Security Features

### Encryption

- **AES-256-CBC** encryption at rest
- **Random IV** (initialization vector) per key
- **Base64 encoding** for storage
- **SHA256 hashing** for fast lookups without decryption

### Best Practices

1. **Encryption Key:** Set `AI_ENCRYPTION_KEY` environment variable (base64 32-byte key)
2. **Never log keys:** Keys are only decrypted when needed
3. **Restrict access:** Limit admin access to key management pages
4. **Monitor usage:** Check for unusual activity in logs
5. **Rotate keys:** Periodically update API keys in `.env.local`

---

## Monitoring & Health

### Key Health Status

```php
$health = $aiKeys->getSystemHealth();
// Returns: total_keys, active_keys, disabled_keys, healthy, encryption_enabled
```

### Usage Tracking

Track in `ai_request_logs` table:
- `key_id`: Which key was used
- `model`: Model used for request
- `tokens_used`: Tokens consumed
- `estimated_cost`: Cost estimate
- `response_status`: HTTP status code
- `error_message`: If failed

### Daily Reset

Keys' `used_today` counter is reset daily (configure cron job):
```bash
# Add to crontab
0 0 * * * php /path/to/cron/reset_daily_counters.php
```

---

## Migration from Old System

### Old Format (Deprecated)
```
API_KEY_1_PRIMARY=sk-...
API_KEY_2_PRIMARY=sk-...
API_KEY_1=sk-...
OPENAI_API_KEY=sk-...
GEMINI_API_KEY=...
```

### New Format
```
KEY_1=sk-...
KEY_2=sk-...
KEY_3=sk-...
KEY_N=sk-...
```

The system automatically:
1. Detects keys in `.env.local`
2. Groups them into accounts by halves
3. Encrypts and stores in `ai_api_keys` table
4. Tracks usage and status

---

## Troubleshooting

### Issue: "No available API keys"

**Causes:**
- All keys exhausted (quota exceeded)
- All keys disabled
- No keys imported

**Solution:**
1. Check admin dashboard: `admin/manage_ai_keys.php`
2. Verify keys in `.env.local`
3. Run `install.php` to import keys
4. Check quota and reset counters if needed

### Issue: "Encryption key not configured"

**Solution:**
```bash
# Generate encryption key (base64 encoded 32 bytes)
php -r "echo base64_encode(random_bytes(32));"

# Add to .env.local
AI_ENCRYPTION_KEY=generated_key_here
```

### Issue: Keys showing as "disabled"

**Causes:**
- Exceeded `AI_CIRCUIT_BREAKER_THRESHOLD` failures
- Manual disable via admin
- Account suspended

**Solution:**
- Fix the underlying issue (rate limiting, invalid key, etc.)
- Re-enable in database: `UPDATE ai_api_keys SET status='active' WHERE key_id=X`

---

## Performance Tips

1. **Use priority:** Primary account is checked first
2. **Distribute keys:** Spread keys across multiple accounts for load balancing
3. **Monitor failures:** Disabled keys are skipped automatically
4. **Reset quotas:** Ensure daily reset cron is running
5. **Cache configuration:** `AIKeyConfigManager` caches env vars

---

## File Structure

```
config/
├── .env.local                 # Configuration (your API keys)
├── AIKeyConfigManager.php     # Configuration loader
└── env.php                    # Legacy env loader (keep for compatibility)

services/
├── AIKeysSystem.php           # Main database operations class
├── AIKeyManager.php           # Legacy (deprecated)
└── AIGateway.php              # API gateway orchestration

admin/
├── manage_ai_keys.php         # New dashboard
└── api_ai_keys.php            # REST API endpoints (optional)

database/
├── cleanup_legacy_tables.php  # Remove old tables
└── fix_ai_keys_schema.sql     # Schema fixes

cron/
└── reset_daily_counters.php   # Daily quota reset (create if needed)
```

---

## Summary

- **Simple:** Use `KEY_1`, `KEY_2`, etc. naming
- **Flexible:** Each key has its own model assignment
- **Secure:** AES-256 encryption at rest
- **Smart:** Automatic priority + least-used routing
- **Scalable:** Add keys without code changes
- **Monitored:** Full usage tracking and health status

For any questions or issues, check `admin/manage_ai_keys.php` for system status and diagnostics.
