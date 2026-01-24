# API Key Management Strategy

This system uses a robust **Multi-Account Failover Strategy** for managing OpenAI/OpenRouter API keys. This ensures high availability and prevents service interruptions due to rate limits or quota exhaustion on a single account.

## 1. Structure

API keys are organized into **groups** within the `.env` (or `.env.local`) configuration file. Each group typically represents a distinct OpenRouter/OpenAI account.

*   **Primary Group (`OPENAI_API_KEYS`)**: The first set of keys the system attempts to use.
*   **Secondary Groups (`OPENAI_API_KEYS_1`, `OPENAI_API_KEYS_2`, ...)**: Backup pools of keys used automatically if the primary group is exhausted or rate-limited.

### Configuration Example (`.env`)

```ini
# --- Account 1 (Primary) ---
# Newest keys with fresh quota
OPENAI_API_KEYS=sk-or-v1-keyA1,sk-or-v1-keyA2

# --- Account 2 (Backup 1) ---
# Older keys or different account
OPENAI_API_KEYS_1=sk-or-v1-keyB1,sk-or-v1-keyB2,sk-or-v1-keyB3

# --- Account 3 (Backup 2) ---
# Emergency backup keys
OPENAI_API_KEYS_2=sk-or-v1-keyC1
```

## 2. How It Works

### A. Loading (`EnvLoader`)
The `EnvLoader::getList('OPENAI_API_KEYS')` method in `config/env.php` is responsible for aggregating these keys.
1.  It reads the base `OPENAI_API_KEYS` variable.
2.  It automatically scans for suffixes `_1` through `_10` (e.g., `OPENAI_API_KEYS_1`, `OPENAI_API_KEYS_2`).
3.  It merges all found keys into a **single, unified list**.

### B. Rotation & Locking (`mcq_generator.php`)
The system doesn't just pick a random key; it uses a smart rotation mechanism:

1.  **Round Robin**: It iterates through the unified list of keys one by one.
2.  **Locking**: When a key is used, it is temporarily "locked" (via `CacheManager` or DB) for ~30 seconds. This prevents multiple concurrent requests from hitting the exact same key simultaneously, reducing `429 Too Many Requests` errors.
3.  **Failover**: If a key fails (e.g., due to quota limits), the system catches the error and immediately retries with the *next* available key in the list.

## 3. Adding New Keys
To add keys from a new account without disturbing the existing setup:

1.  Open your `.env` or `.env.local` file.
2.  Create a new variable incrementing the number suffix (e.g., `OPENAI_API_KEYS_3`).
3.  Paste your comma-separated keys there.

```ini
OPENAI_API_KEYS_3=new_key_1,new_key_2
```

The system will automatically detect and include these new keys in the rotation cycle.
