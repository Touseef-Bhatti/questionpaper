# Meilisearch Topic Search Integration

This document describes how topic search uses Meilisearch and how to rebuild the index from MySQL.

## Overview

- **Goal:** Replace SQL `LIKE`-based topic search with full-text ranked search via Meilisearch so results are more relevant.
- **Flow:** User searches for a topic → PHP sends the query to Meilisearch → Meilisearch returns ranked results → results are displayed. When new topics or questions are added (MySQL), they are also indexed in Meilisearch so they appear in search immediately (or after running the rebuild script).

## Where Meilisearch Is Used

| Page / Action | File | Behavior |
|---------------|------|----------|
| Topic search (MCQ) | `quiz/mcqs_topic.php` | POST search and "Load More" use Meilisearch when configured; fallback to SQL + AI when not. |
| Topic search (Paper builder) | `questionPaperFromTopic/search_topics.php` | AJAX search uses Meilisearch when configured; fallback to SQL. |
| New topic / question added | Multiple | New topics are indexed automatically when inserted into `mcqs`, `AIGeneratedMCQs`, `generated_topics`, `questions`, or `AIQuestionsTopic`. |

## Docker Configuration

Meilisearch is defined in `docker-compose.yml`:

```yaml
meilisearch:
  image: getmeili/meilisearch:v1.11
  container_name: paper_meilisearch
  environment:
    MEILI_MASTER_KEY: ${MEILISEARCH_MASTER_KEY:-}
    MEILI_ENV: production
  ports:
    - "7700:7700"
  volumes:
    - meilisearch_data:/meili_data
  restart: unless-stopped
```

- **Port:** `7700` (host). From the app container use `http://meilisearch:7700`.
- **Data:** Persisted in Docker volume `meilisearch_data`.
- **Master key:** Optional. Set `MEILISEARCH_MASTER_KEY` in your `.env` (same file used by `docker-compose`) if you want to protect the instance.

### Environment Variables (for the PHP app)

In `config/.env` (or the env file your app loads). When using Docker, the app often uses the same `.env` as docker-compose (project root); if so, set these there.

- **MEILISEARCH_HOST**  
  - With Docker: `http://meilisearch:7700`  
  - Local (e.g. XAMPP) with Meilisearch on host: `http://localhost:7700`  
  - If empty or not set, the app falls back to SQL search and does not use Meilisearch.

- **MEILISEARCH_MASTER_KEY** (optional)  
  - Same value as `MEILISEARCH_MASTER_KEY` in docker-compose so PHP can call the API. Leave empty if you did not set a key.

Example for Docker:

```env
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_MASTER_KEY=your-secret-key-if-you-set-one
```

### Production server (no Docker)

When you deploy by updating files on the server, the app uses **`config/.env.production`**. Topic search uses Meilisearch if it is configured there.

1. **Install Meilisearch on the server** (or use a separate Meilisearch host). Example (Linux):  
   `curl -L https://install.meilisearch.com | sh` then run with `MEILI_MASTER_KEY="your_16_char_secret" ./meilisearch` (or run as a service on port 7700).

2. **Configure `config/.env.production`** (already present; adjust if needed):
   - `MEILISEARCH_HOST=http://127.0.0.1:7700` (same server) or the URL of your Meilisearch instance.
   - `MEILISEARCH_MASTER_KEY=` a secret **at least 16 characters** (replace the default in .env.production).

3. **Build the index after deploy** (once, or via cron):  
   `php scripts/meilisearch_index_topics.php --fresh` from the project root. The script loads `.env.production` when run from CLI on the server (when `.env.local` is not present).

4. **Deploy**: upload files as usual; the web app reads `MEILISEARCH_*` from `.env.production` automatically.

## Index: `topics`

- **Index name:** `topics`
- **Primary key:** `id` (string, generated as `md5(source . '::' . type . '::' . normalized_topic)`).
- **Fields:**
  - `topic` (string) – searchable topic name
  - `source` (string) – origin: `mcqs`, `ai_mcqs`, `generated_topics`, `questions`, `ai_questions_topic`
  - `type` (string) – `mcq`, `short`, or `long` (for filtering)
- **Searchable:** `topic`
- **Filterable:** `source`, `type`

## Rebuilding the Index from MySQL

Use the CLI script to refill the `topics` index from the database. Run from the **project root** (directory containing `db_connect.php`, `scripts/`, etc.):

```bash
# Full rebuild (clears index then reindexes)
php scripts/meilisearch_index_topics.php --fresh

# Incremental (only adds/updates; does not delete existing documents)
php scripts/meilisearch_index_topics.php
```

- **`--fresh`:** Deletes all documents in the `topics` index, then indexes all topics from MySQL. Use after schema/data fixes or to start clean.
- **Without `--fresh`:** Sends all current topics to Meilisearch; documents are added or updated by `id`. Does not remove old documents that no longer exist in MySQL (for a fully exact sync, use `--fresh` periodically).

### Data sources (MySQL → Meilisearch)

The script reads from:

1. **mcqs** – `DISTINCT topic` → source `mcqs`, type `mcq`
2. **AIGeneratedMCQs** – `DISTINCT topic` → source `ai_mcqs`, type `mcq`
3. **generated_topics** – `DISTINCT topic_name` → source `generated_topics`, type `mcq`
4. **questions** – `DISTINCT topic` (+ `question_type` or `type`) → source `questions`, type `short` or `long`
5. **AIQuestionsTopic** – `DISTINCT topic_name` → source `ai_questions_topic`, type `mcq`

Tables that might not exist (e.g. old schema) are skipped without failing the script.

### When to run

- After first-time Meilisearch setup.
- After restoring a MySQL backup (so search reflects the restored data).
- Periodically if you want a full resync (e.g. `php scripts/meilisearch_index_topics.php --fresh` from cron).

## PHP API (internal)

### MeilisearchService

- **Location:** `services/MeilisearchService.php`
- **Constructor:** Reads `MEILISEARCH_HOST` and `MEILISEARCH_MASTER_KEY` from env.
- **isAvailable():** Returns true if `MEILISEARCH_HOST` is set.
- **searchTopics(string $query, array $options):**  
  - Options: `limit`, `types` (array of `mcq`/`short`/`long`), `with_scores` (bool).  
  - Returns `['success' => bool, 'topics' => array]`. With `with_scores`, each topic is `['topic' => ..., 'similarity' => ..., 'source' => ...]`; otherwise a list of topic strings.
- **addTopic(string $topic, string $source, string $type):** Indexes one topic (add or update by id).
- **addTopicsBatch(array $documents):** Indexes many documents; each element is `['topic' => ..., 'source' => ..., 'type' => ...]`.
- **ensureIndex():** Creates the `topics` index and sets searchable/filterable attributes if needed.
- **deleteAllDocuments():** Deletes all documents in `topics` (for full rebuild).

### Example: search (conceptual)

```php
$meili = new MeilisearchService();
if ($meili->isAvailable()) {
    $res = $meili->searchTopics('kinetic energy', ['limit' => 20, 'with_scores' => true]);
    if ($res['success']) {
        foreach ($res['topics'] as $t) {
            echo $t['topic'] . ' (' . $t['similarity'] . "%)\n";
        }
    }
}
```

### Example: index one topic after insert

```php
$meili = new MeilisearchService();
$meili->addTopic('Newton\'s Laws', 'mcqs', 'mcq');
```

## Example API Calls (Meilisearch HTTP)

- **Health:**  
  `GET http://localhost:7700/health`  
  (If using a master key, add header: `Authorization: Bearer YOUR_MASTER_KEY`.)

- **Search:**  
  `POST http://localhost:7700/indexes/topics/search`  
  Body: `{"q": "kinetic energy", "limit": 10}`  
  Returns ranked hits with `_rankingScore` and document fields.

- **Index settings (e.g. after create):**  
  `PATCH http://localhost:7700/indexes/topics/settings`  
  Body: `{"searchableAttributes": ["topic"], "filterableAttributes": ["source", "type"]}`  

See [Meilisearch API docs](https://www.meilisearch.com/docs/reference/api/overview) for full reference.

## Troubleshooting

- **No results / search still uses SQL**  
  - Ensure `MEILISEARCH_HOST` is set in the env file the app loads (e.g. `config/.env`).  
  - Ensure Meilisearch is running (`docker compose ps` or hit `/health`).  
  - Run a full reindex: `php scripts/meilisearch_index_topics.php --fresh`.

- **Index missing or wrong**  
  - Run `php scripts/meilisearch_index_topics.php --fresh` from project root.  
  - Ensure DB credentials in `config/.env` (or `db_connect.php`) are correct so the script can read the tables.

- **New topics not appearing**  
  - New inserts trigger indexing automatically. If they still don’t appear, run the rebuild script with `--fresh` once to sync, then check that `MeilisearchService::addTopic()` is called after your insert path (see “Where Meilisearch Is Used” and “Index new topics when added” above).
