# ✅ AI Keys Management System - Enhanced & Updated

## 🎯 What Changed

### 1. **New Key Naming Format**
- **Old:** `API_KEY_1_PRIMARY`, `API_KEY_1`, `OPENAI_API_KEY`, `GEMINI_API_KEY`
- **New:** `KEY_1`, `KEY_2`, `KEY_3`, ..., `KEY_N`
- **Simple, clean, consistent!**

### 2. **Removed Unused Code**
- ❌ Deleted old `AIKeyLoader.php` (replaced by `AIKeyConfigManager.php`)
- ❌ Removed `OPENAI_API_KEY`, `OPENAI_API_KEYS`, `GEMINI_API_KEY` from defaults
- ❌ Removed old API_KEY pattern recognition code
- ✅ Kept legacy compatibility in `env.php` (safe)

### 3. **Enhanced Configuration**
- **File:** `config/.env.local`
- **New variables added:**
  - `KEY_N_PROVIDER` - Specify provider (openai, anthropic, etc.)
  - `AI_ENCRYPTION_KEY` - Encryption key for key storage
  - `AI_DEFAULT_MODEL` - System default model
  - `AI_FALLBACK_MODEL` - Fallback when primary fails
  - `AI_DAILY_QUOTA_PER_KEY` - Per-key quota
  - `AI_MAX_RETRIES` - Retry attempts
  - `AI_CIRCUIT_BREAKER_THRESHOLD` - Failure threshold

### 4. **New Core Classes**

#### AIKeyConfigManager (`config/AIKeyConfigManager.php`)
- Loads `.env.local` with new KEY_N format
- Automatically groups keys into accounts (1st half = Account 1, rest = Account 2)
- Provides config validation
- **Methods:** `getAllKeys()`, `getKeyById()`, `getAccountKeys()`, `getSystemConfig()`

#### AIKeysSystem (`services/AIKeysSystem.php`)
- **Complete replacement** for old AIKeyManager
- Database operations (CRUD)
- Key selection logic (priority + least-used)
- Usage tracking
- Health monitoring
- Circuit breaker implementation
- **Methods:** `selectBestKey()`, `updateKeyUsage()`, `blockKey()`, `disableKey()`, `getSystemHealth()`

### 5. **Enhanced Admin Dashboard**
- **File:** `admin/manage_ai_keys.php` (new)
- System health overview cards
- Account statistics and quota usage
- Complete keys table with status
- Key details modals
- Configuration verification
- Help section with setup instructions

### 6. **Database Cleanup Utility**
- **File:** `database/cleanup_legacy_tables.php` (new)
- Safely remove old `api_keys` table
- Backup tables before deletion
- Verify data migration before deleting
- List all remaining tables

---

## 📋 File Structure (Updated)

```
config/
├── .env.local                      ✅ UPDATED - New KEY_N format
├── env.php                         ✅ UPDATED - Removed old defaults
├── AIKeyConfigManager.php          ✅ NEW - Replaces AIKeyLoader
└── AIKeyLoader.php                 ❌ DELETED - Replaced by AIKeyConfigManager

services/
├── AIKeysSystem.php                ✅ NEW - Complete replacement
├── AIKeyManager.php                ℹ️ KEPT - For compatibility
└── AIGateway.php                   ℹ️ KEPT - Works with AIKeysSystem

admin/
├── manage_ai_keys.php              ✅ NEW - Enhanced dashboard
└── api_ai_keys.php                 ℹ️ KEPT - May need update to use AIKeysSystem

database/
├── cleanup_legacy_tables.php        ✅ NEW - Safe cleanup utility
└── fix_ai_keys_schema.sql          ℹ️ REFERENCE - Schema file

root/
├── AI_KEYS_COMPLETE_GUIDE.md       ✅ NEW - Complete documentation
└── migrate_ai_keys_schema.php       ℹ️ REFERENCE - Migration helper
```

---

## 🚀 How to Use

### Step 1: Update `.env.local`
```env
# New format is already updated!
KEY_1=sk-or-v1-your_key_1
KEY_1_MODEL=gpt-4-turbo
KEY_1_PROVIDER=openai

KEY_2=sk-or-v1-your_key_2
KEY_2_MODEL=gpt-3.5-turbo
KEY_2_PROVIDER=openai

# Add AI system settings
AI_ENCRYPTION_KEY=base64_encoded_key_here
AI_DEFAULT_MODEL=gpt-4-turbo
AI_FALLBACK_MODEL=gpt-3.5-turbo
```

### Step 2: Update .env file for production
```bash
# Generate encryption key
php -r "echo base64_encode(random_bytes(32));"

# Update .env with same KEY_N format and settings
```

### Step 3: Run Install
```bash
# Visit in browser or PHP CLI
http://localhost:8000/install.php
```

### Step 4: Verify in Admin Dashboard
```bash
http://localhost:8000/admin/manage_ai_keys.php
```

### Step 5: Use in Code
```php
require_once 'services/AIKeysSystem.php';

$aiKeys = new AIKeysSystem($conn);
$key = $aiKeys->selectBestKey('openai');

// Use $key['api_key'] and $key['model_name']
```

---

## ✨ Enhanced Features

### 1. **Smart Key Selection**
- Respects account priority (Account 1 before Account 2)
- Least-used-first load balancing
- Skips exhausted and disabled keys
- Auto-unblocks expired blocks

### 2. **Circuit Breaker Pattern**
- Tracks consecutive failures per key
- Auto-disables after threshold
- Prevents cascade failures
- Logs failure reason

### 3. **Usage Monitoring**
- Per-key usage tracking
- Daily quota per key (resettable)
- Cost estimation
- Account-level statistics

### 4. **Security**
- AES-256-CBC encryption at rest
- Random IV per key
- SHA256 hashing for lookups
- Secure decryption on-demand only

### 5. **Account Grouping**
- Automatic split into Primary (KEY_1-5) and Secondary (KEY_6+)
- Override priority for fallback scenarios
- Per-account quota limits
- Account status monitoring

---

## 🧹 Cleanup Tasks

### Remove Old Unused Files (Optional)

If you want to completely remove legacy files (after verifying everything works):

```bash
# Old files that can be safely deleted after testing:
rm config/AIKeyLoader.php           # Replaced by AIKeyConfigManager
rm admin/api_ai_keys.php            # If not using REST API
rm verify_ai_keys.php               # Testing helper
rm migrate_ai_keys_schema.php       # Migration helper
```

### Remove Old Database Table (Safe)

Use the cleanup utility:
```bash
http://localhost:8000/database/cleanup_legacy_tables.php
```

This will:
1. Back up the old `api_keys` table as `api_keys_backup_YYYYMMDDHHMMSS`
2. Rename (not delete) for safety
3. Allow restoration if needed

---

## 🔍 Database Schema Overview

### ai_accounts
- `account_id` - Unique ID
- `account_name` - "Primary Account" or "Secondary Account (Fallback)"
- `provider_name` - "openai"
- `priority` - 1 (primary), 2 (fallback)
- `status` - active, suspended, disabled

### ai_api_keys
- `key_id` - Unique ID
- `account_id` - Which account
- `key_name` - "key_1", "key_2", etc.
- `model_name` - "gpt-4-turbo", "gpt-3.5-turbo"
- `api_key_encrypted` - AES-256 encrypted
- `daily_limit` - 100000 tokens/day
- `used_today` - Current usage (resets daily)
- `status` - active, temporarily_blocked, exhausted, disabled

---

## 📚 Documentation

Complete guide available in:
**`AI_KEYS_COMPLETE_GUIDE.md`**

Topics covered:
- Configuration details
- API usage examples
- Database schema
- Security features
- Troubleshooting
- Monitoring
- Performance tips

---

## ✅ Verification Checklist

After setup, verify:

- [ ] `.env.local` has KEY_N format (not API_KEY_N)
- [ ] `config/AIKeyConfigManager.php` exists
- [ ] `services/AIKeysSystem.php` exists
- [ ] `admin/manage_ai_keys.php` loads successfully
- [ ] `admin/manage_ai_keys.php` shows all keys
- [ ] Database has `ai_api_keys` and `ai_accounts` tables
- [ ] All keys show model_name assigned
- [ ] Encryption status shows ✓ Configured
- [ ] Old `api_keys` table backed up (optional, for cleanup)

---

## 🆘 Support

If issues arise:

1. **Check admin dashboard:** `admin/manage_ai_keys.php`
2. **View system health:** Shows encryption, key count, account status
3. **Verify configuration:** Check that KEY_N format is correct in `.env.local`
4. **Review logs:** Check `ai_request_logs` table for errors
5. **Run install.php:** Re-import keys if needed

---

## 🎉 Summary

**You now have:**
- ✅ **Simplified naming:** KEY_1, KEY_2, etc.
- ✅ **Enhanced features:** Per-key models, smart selection, health monitoring
- ✅ **Better management:** Admin dashboard with full visibility
- ✅ **Cleaner code:** Removed unused legacy code
- ✅ **Complete docs:** Full guide for team reference
- ✅ **Safe cleanup:** Tools to remove old tables when ready

**No code changes needed to add new keys** - just edit `.env.local` and run `install.php`!

---

**Last Updated:** January 29, 2026  
**Status:** ✅ Ready for Production
