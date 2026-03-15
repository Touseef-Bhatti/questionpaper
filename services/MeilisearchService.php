<?php
/**
 * Meilisearch integration for topic search.
 * Provides full-text ranked search over topics from mcqs, AIGeneratedMCQs,
 * generated_topics, questions, and AIQuestionsTopic.
 */

require_once __DIR__ . '/../config/env.php';

class MeilisearchService
{
    public const INDEX_TOPICS = 'topics';

    /** @var string|null */
    private $host;

    /** @var string */
    private $apiKey;

    /** @var int */
    private $timeout;

    public function __construct()
    {
        $this->host = rtrim(EnvLoader::get('MEILISEARCH_HOST', ''), '/');
        $this->apiKey = (string) EnvLoader::get('MEILISEARCH_API_KEY', '');
        $this->timeout = (int) EnvLoader::get('MEILISEARCH_TIMEOUT', 10);
    }

    /**
     * Whether Meilisearch is configured and should be used.
     */
    public function isAvailable(): bool
    {
        return $this->host !== '' && strlen($this->host) > 0;
    }

    /**
     * Search topics by query. Returns array of unique topic strings with optional similarity score.
     *
     * @param string $query Search query
     * @param array $options ['types' => ['mcq','short','long'], 'limit' => 50]
     * @return array{topics: array, success: bool, error?: string} topics are array of strings or array of {topic, similarity}
     */
    public function searchTopics(string $query, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return ['success' => false, 'topics' => []];
        }

        $limit = (int) ($options['limit'] ?? 50);
        $types = $options['types'] ?? null; // null = all, or ['mcq'], ['short'], ['long']
        $withScores = !empty($options['with_scores']);

        $body = [
            'q' => $query,
            'limit' => min(100, max(1, $limit)),
        ];

        if ($types !== null && is_array($types) && !empty($types)) {
            $normalized = array_map(function ($t) {
                return '"' . preg_replace('/[^a-z]/', '', strtolower((string) $t)) . '"';
            }, $types);
            $body['filter'] = 'type IN [' . implode(', ', $normalized) . ']';
        }

        $response = $this->request('POST', '/indexes/' . self::INDEX_TOPICS . '/search', $body);
        if ($response['code'] !== 200 || !isset($response['body']['hits'])) {
            if ($response['code'] > 0) {
                error_log('Meilisearch search error: ' . ($response['body']['message'] ?? $response['raw'] ?? 'Unknown'));
            }
            return ['success' => false, 'topics' => []];
        }

        $hits = $response['body']['hits'];
        $seen = [];
        $topics = [];
        foreach ($hits as $hit) {
            $topic = $hit['topic'] ?? '';
            if ($topic === '' || isset($seen[$topic])) {
                continue;
            }
            $seen[$topic] = true;
            if ($withScores) {
                $topics[] = [
                    'topic' => $topic,
                    'similarity' => isset($hit['_rankingScore']) ? round((float) $hit['_rankingScore'] * 100, 1) : 100,
                    'source' => $hit['source'] ?? null,
                ];
            } else {
                $topics[] = $topic;
            }
        }

        return ['success' => true, 'topics' => $topics];
    }

    /**
     * Add or update a single topic document. Call after inserting into MySQL.
     *
     * @param string $topic Topic name
     * @param string $source e.g. 'mcqs', 'ai_mcqs', 'generated_topics', 'questions', 'ai_questions_topic'
     * @param string $type 'mcq', 'short', or 'long'
     */
    public function addTopic(string $topic, string $source, string $type): bool
    {
        if (!$this->isAvailable() || $topic === '') {
            return false;
        }
        $topic = trim($topic);
        $id = $this->documentId($source, $type, $topic);
        $doc = [
            'id' => $id,
            'topic' => $topic,
            'source' => $source,
            'type' => $type,
        ];
        $response = $this->request('PUT', '/indexes/' . self::INDEX_TOPICS . '/documents', [$doc]);
        return $response['code'] === 202 || $response['code'] === 200;
    }

    /**
     * Add multiple topic documents in one batch (max 1000 per batch).
     *
     * @param array $documents Array of [topic, source, type]
     */
    public function addTopicsBatch(array $documents): bool
    {
        if (!$this->isAvailable() || empty($documents)) {
            return true;
        }
        $docs = [];
        foreach ($documents as $d) {
            $topic = trim($d['topic'] ?? $d[0] ?? '');
            if ($topic === '') continue;
            $source = $d['source'] ?? $d[1] ?? 'unknown';
            $type = $d['type'] ?? $d[2] ?? 'mcq';
            $docs[] = [
                'id' => $this->documentId($source, $type, $topic),
                'topic' => $topic,
                'source' => $source,
                'type' => $type,
            ];
        }
        if (empty($docs)) return true;
        $response = $this->request('PUT', '/indexes/' . self::INDEX_TOPICS . '/documents', $docs);
        return $response['code'] === 202 || $response['code'] === 200;
    }

    /**
     * Ensure the index exists and has the right settings.
     */
    public function ensureIndex(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        $indexName = self::INDEX_TOPICS;
        $create = $this->request('POST', '/indexes', ['uid' => $indexName, 'primaryKey' => 'id']);
        if ($create['code'] !== 201 && $create['code'] !== 202 && $create['code'] !== 200) {
            if ($create['code'] !== 409) { // already exists
                error_log('Meilisearch create index error: code=' . $create['code'] . ' body=' . ($create['raw'] ?? ''));
                return false;
            }
        }

        $settings = [
            'searchableAttributes' => ['topic'],
            'filterableAttributes' => ['source', 'type'],
            'sortableAttributes' => [],
        ];
        $settingsRes = $this->request('PATCH', '/indexes/' . $indexName . '/settings', $settings);
        return $settingsRes['code'] === 200 || $settingsRes['code'] === 202;
    }

    /**
     * Delete all documents in the index (for full reindex).
     */
    public function deleteAllDocuments(): bool
    {
        if (!$this->isAvailable()) return false;
        $task = $this->request('DELETE', '/indexes/' . self::INDEX_TOPICS . '/documents');
        return $task['code'] === 202 || $task['code'] === 200;
    }

    private function documentId(string $source, string $type, string $topic): string
    {
        $normalized = mb_strtolower(trim($topic));
        return md5($source . '::' . $type . '::' . $normalized);
    }

    /**
     * @return array{code: int, body: array, raw: string}
     */
    private function request(string $method, string $path, $body = null): array
    {
        $url = $this->host . $path;
        $headers = ['Content-Type: application/json'];
        if ($this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true) ?? [];
        }

        return ['code' => $code, 'body' => $decoded, 'raw' => (string) $raw];
    }
}
