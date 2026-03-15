#!/usr/bin/env php
<?php
/**
 * Rebuild Meilisearch "topics" index from MySQL.
 * Run from project root: php scripts/meilisearch_index_topics.php [--fresh]
 * --fresh: delete all documents before reindexing.
 *
 * Requires: db_connect.php, services/MeilisearchService.php
 * Environment: MEILISEARCH_HOST, optional MEILISEARCH_MASTER_KEY
 */

$projectRoot = dirname(__DIR__);
// When run from CLI: load .env.production on server (no .env.local), .env.local when present (e.g. Docker)
if (php_sapi_name() === 'cli') {
    if (file_exists($projectRoot . '/config/.env.production') && !file_exists($projectRoot . '/config/.env.local')) {
        $_SERVER['SERVER_NAME'] = 'production';
    } elseif (file_exists($projectRoot . '/config/.env.local')) {
        $_SERVER['SERVER_NAME'] = 'localhost';
    }
}
require_once $projectRoot . '/config/env.php';
require_once $projectRoot . '/db_connect.php';
require_once $projectRoot . '/services/MeilisearchService.php';

$fresh = in_array('--fresh', $argv ?? [], true);

$meili = new MeilisearchService();
if (!$meili->isAvailable()) {
    fwrite(STDERR, "Meilisearch is not configured (MEILISEARCH_HOST empty). Set it in .env and retry.\n");
    exit(1);
}

echo "Meilisearch host: " . (EnvLoader::get('MEILISEARCH_HOST', '')) . "\n";

if (!$meili->ensureIndex()) {
    fwrite(STDERR, "Failed to ensure index.\n");
    exit(1);
}

if ($fresh) {
    echo "Deleting existing documents...\n";
    $meili->deleteAllDocuments();
    sleep(1);
}

$documents = [];

// 1. mcqs — distinct topic
try {
    $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE topic IS NOT NULL AND topic != ''");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $documents[] = ['topic' => trim($row['topic']), 'source' => 'mcqs', 'type' => 'mcq'];
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    // Table might not exist
}
echo "mcqs: " . count(array_filter($documents, fn($d) => $d['source'] === 'mcqs')) . " topics\n";

$mcqCount = count($documents);

// 2. AIGeneratedMCQs
try {
    $stmt = $conn->prepare("SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic IS NOT NULL AND topic != ''");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $documents[] = ['topic' => trim($row['topic']), 'source' => 'ai_mcqs', 'type' => 'mcq'];
        }
        $stmt->close();
    }
} catch (Throwable $e) {}
echo "AIGeneratedMCQs: " . (count($documents) - $mcqCount) . " topics\n";
$aiMcqCount = count($documents) - $mcqCount;

// 3. generated_topics
try {
    $stmt = $conn->prepare("SELECT DISTINCT topic_name FROM generated_topics WHERE topic_name IS NOT NULL AND topic_name != ''");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $documents[] = ['topic' => trim($row['topic_name']), 'source' => 'generated_topics', 'type' => 'mcq'];
        }
        $stmt->close();
    }
} catch (Throwable $e) {}
$genCount = count($documents) - $mcqCount - $aiMcqCount;
echo "generated_topics: " . $genCount . " topics\n";

// 4. questions (short/long) — need column topic and question_type
try {
    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM questions LIKE 'topic'");
    if ($r && $r->num_rows > 0) {
        $cols['topic'] = true;
    }
    $r = $conn->query("SHOW COLUMNS FROM questions LIKE 'question_type'");
    if ($r && $r->num_rows > 0) {
        $cols['question_type'] = true;
    } else {
        $r = $conn->query("SHOW COLUMNS FROM questions LIKE 'type'");
        if ($r && $r->num_rows > 0) {
            $cols['type'] = true;
        }
    }
    if (!empty($cols) && isset($cols['topic'])) {
        $typeCol = isset($cols['question_type']) ? 'question_type' : 'type';
        $stmt = $conn->prepare("SELECT DISTINCT topic, $typeCol AS qtype FROM questions WHERE topic IS NOT NULL AND topic != ''");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $t = trim($row['topic']);
                $qt = strtolower($row['qtype'] ?? 'short');
                if ($qt === 'long' || $qt === 'short') {
                    $documents[] = ['topic' => $t, 'source' => 'questions', 'type' => $qt];
                }
            }
            $stmt->close();
        }
    }
} catch (Throwable $e) {}
echo "questions: " . count(array_filter($documents, fn($d) => $d['source'] === 'questions')) . " topics\n";

// 5. AIQuestionsTopic (topic_name)
try {
    $stmt = $conn->prepare("SELECT DISTINCT topic_name FROM AIQuestionsTopic WHERE topic_name IS NOT NULL AND topic_name != ''");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $documents[] = ['topic' => trim($row['topic_name']), 'source' => 'ai_questions_topic', 'type' => 'mcq'];
        }
        $stmt->close();
    }
} catch (Throwable $e) {}
echo "AIQuestionsTopic: " . count(array_filter($documents, fn($d) => $d['source'] === 'ai_questions_topic')) . " topics\n";

$total = count($documents);
echo "Total documents to index: $total\n";

$batchSize = 500;
$offset = 0;
$failed = false;
while ($offset < $total) {
    $batch = array_slice($documents, $offset, $batchSize);
    $offset += count($batch);
    if (!$meili->addTopicsBatch($batch)) {
        $failed = true;
        break;
    }
    echo "Sent batch " . (int)(($offset - 1) / $batchSize + 1) . "\n";
}

if (!$failed) {
    echo "Indexing requests sent successfully. Meilisearch will process asynchronously.\n";
    exit(0);
}

fwrite(STDERR, "Indexing request failed.\n");
exit(1);
