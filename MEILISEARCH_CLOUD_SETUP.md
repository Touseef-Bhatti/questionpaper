# Meilisearch Cloud Setup Guide

## 1. Get Your Cloud API Key

1. Go to [Meilisearch Cloud Dashboard](https://cloud.meilisearch.com)
2. Sign up or log in
3. Create a new project or select existing
4. Go to **Settings** → **API Keys**
5. Copy the **Master Key** (for development/admin) or create a custom API key
6. Copy your **API Endpoint URL** (looks like: `https://ms-xxxxx.fra.meilisearch.io`)

## 2. Update Environment Configuration

Update `.env.production` with your actual credentials:

```bash
# Meilisearch Cloud Configuration
MEILISEARCH_HOST=https://ms-49e79ed9cf9b-43096.fra.meilisearch.io
MEILISEARCH_API_KEY=your_actual_api_key_here
MEILISEARCH_TIMEOUT=10
```

**Where to get these:**
- `MEILISEARCH_HOST` → Your API Endpoint from Meilisearch dashboard
- `MEILISEARCH_API_KEY` → Master Key or custom API key from Settings

## 3. Import All Topics to Meilisearch

Run the import script to bulk upload all topics from your database:

```bash
# From your server directory
php scripts/import_topics_to_meilisearch.php
```

This script will:
- Create the `topics` index in Meilisearch Cloud
- Import all topics from:
  - `mcqs` → source: `mcqs`, type: `mcq`
  - `AIGeneratedMCQs` → source: `ai_mcqs`, type: `mcq`
  - `generated_topics` → source: `generated_topics`, type: `short`
  - `questions` → source: `questions`, type: `long`
  - `AIQuestionsTopic` → source: `ai_questions_topic`, type: `long`

## 4. API Example (cURL)

Add a single topic using the API:

```bash
curl -X PUT 'https://ms-49e79ed9cf9b-43096.fra.meilisearch.io/indexes/topics/documents' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer YOUR_API_KEY' \
  --data-binary '[
    {
      "id": "mcqs::mcq::algebra",
      "topic": "Algebra",
      "source": "mcqs",
      "type": "mcq"
    }
  ]'
```

## 5. Search Topics via PHP

In your application, use the search method:

```php
require_once 'services/MeilisearchService.php';

$meilisearch = new MeilisearchService();

// Simple search
$result = $meilisearch->searchTopics('algebra');
echo json_encode($result);

// With options
$result = $meilisearch->searchTopics('algebra', [
    'types' => ['mcq', 'short'],  // Filter by type
    'limit' => 20,                 // Max results
    'with_scores' => true         // Include similarity scores
]);

// Result format:
// {
//   "success": true,
//   "topics": [
//     "Algebra",
//     "Linear Algebra",
//     ...
//   ]
// }
```

## 6. Key Configuration Details

| Setting | Value | Notes |
|---------|-------|-------|
| Index Name | `topics` | Auto-created by script |
| Primary Key | `id` | MD5 hash of topic+source+type |
| Searchable Fields | `topic` | Full-text search |
| Filterable Fields | `source`, `type` | For filtering results |
| API Timeout | 10 seconds | Adjust if needed |

## 7. Meilisearch Cloud Advantages

✅ **No infrastructure to manage**
✅ **Auto-scaling** for traffic spikes  
✅ **Global edge locations** for faster search
✅ **Automatic backups** and disaster recovery
✅ **Real-time indexing** of new topics
✅ **Advanced analytics** in dashboard

## 8. Incremental Updates

After adding new topics to your database, add them to Meilisearch:

```php
// When creating a new MCQ topic
$meilisearch = new MeilisearchService();
$meilisearch->addTopic('Quantum Physics', 'mcqs', 'mcq');

// Or batch add multiple
$meilisearch->addTopicsBatch([
    ['topic' => 'Quantum Physics', 'source' => 'mcqs', 'type' => 'mcq'],
    ['topic' => 'Relativity', 'source' => 'mcqs', 'type' => 'mcq'],
]);
```

## 9. Monitoring & Troubleshooting

### Check Index Status
```bash
curl 'https://ms-49e79ed9cf9b-43096.fra.meilisearch.io/indexes/topics' \
  -H 'Authorization: Bearer YOUR_API_KEY'
```

### View Indexing Stats
```bash
curl 'https://ms-49e79ed9cf9b-43096.fra.meilisearch.io/indexes/topics/stats' \
  -H 'Authorization: Bearer YOUR_API_KEY'
```

### Search Health Check
```php
$service = new MeilisearchService();
echo $service->isAvailable() ? "Connected ✅" : "Not connected ❌";
```

## 10. Security Best Practices

⚠️ **DO NOT** commit `.env.production` with real API keys to Git

✅ **DO:**
- Use environment variables for API keys
- Create separate API keys for different environments
- Rotate keys periodically
- Monitor Meilisearch dashboard for unauthorized access
- Use searchable fields only (restrict secret fields)

---

**Need help?** Visit [Meilisearch Documentation](https://docs.meilisearch.com)
