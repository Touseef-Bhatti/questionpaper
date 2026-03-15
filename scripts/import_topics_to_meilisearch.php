<?php
/**
 * Bulk import all topics from database to Meilisearch Cloud
 * 
 * Usage: php scripts/import_topics_to_meilisearch.php
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../services/MeilisearchService.php';

$meilisearch = new MeilisearchService();

// Check if Meilisearch is configured
if (!$meilisearch->isAvailable()) {
    echo "❌ Meilisearch is not configured.\n";
    echo "Set MEILISEARCH_HOST and MEILISEARCH_API_KEY in your .env file\n";
    exit(1);
}

echo "🚀 Starting Meilisearch import...\n\n";

// Step 1: Create/ensure index exists
echo "📝 Ensuring index exists...\n";
if (!$meilisearch->ensureIndex()) {
    echo "❌ Failed to create/ensure index\n";
    exit(1);
}
echo "✅ Index ready\n\n";

// Step 2: Clear existing documents (optional - comment out if you want to keep them)
echo "🧹 Clearing existing documents...\n";
$meilisearch->deleteAllDocuments();
echo "✅ Documents cleared\n\n";

// Step 3: Import topics from all sources
$totalImported = 0;
$batchSize = 500; // Meilisearch limit is 1000 per batch

// ============ Source 1: MCQs ============
echo "📚 Importing topics from MCQs...\n";
try {
    $query = "SELECT DISTINCT topic FROM mcqs WHERE topic IS NOT NULL AND topic != '' ORDER BY topic";
    $result = $conn->query($query);
    
    if (!$result) {
        echo "⚠️  Query error: " . $conn->error . "\n";
    } else {
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = [
                'topic' => $row['topic'],
                'source' => 'mcqs',
                'type' => 'mcq'
            ];
            
            if (count($documents) >= $batchSize) {
                $meilisearch->addTopicsBatch($documents);
                $totalImported += count($documents);
                echo "  ➜ Imported " . count($documents) . " from MCQs (Total: $totalImported)\n";
                $documents = [];
            }
        }
        
        if (!empty($documents)) {
            $meilisearch->addTopicsBatch($documents);
            $totalImported += count($documents);
            echo "  ➜ Imported " . count($documents) . " from MCQs (Total: $totalImported)\n";
        }
    }
} catch (Exception $e) {
    echo "⚠️  Error importing MCQs: " . $e->getMessage() . "\n";
}

// ============ Source 2: AI Generated MCQs ============
echo "\n📚 Importing topics from AI Generated MCQs...\n";
try {
    $query = "SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic IS NOT NULL AND topic != '' ORDER BY topic";
    $result = $conn->query($query);
    
    if (!$result) {
        echo "⚠️  Query error: " . $conn->error . "\n";
    } else {
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = [
                'topic' => $row['topic'],
                'source' => 'ai_mcqs',
                'type' => 'mcq'
            ];
            
            if (count($documents) >= $batchSize) {
                $meilisearch->addTopicsBatch($documents);
                $totalImported += count($documents);
                echo "  ➜ Imported " . count($documents) . " from AI MCQs (Total: $totalImported)\n";
                $documents = [];
            }
        }
        
        if (!empty($documents)) {
            $meilisearch->addTopicsBatch($documents);
            $totalImported += count($documents);
            echo "  ➜ Imported " . count($documents) . " from AI MCQs (Total: $totalImported)\n";
        }
    }
} catch (Exception $e) {
    echo "⚠️  Error importing AI MCQs: " . $e->getMessage() . "\n";
}

// ============ Source 3: Generated Topics ============
echo "\n📚 Importing topics from Generated Topics...\n";
try {
    $query = "SELECT DISTINCT topic FROM generated_topics WHERE topic IS NOT NULL AND topic != '' ORDER BY topic";
    $result = $conn->query($query);
    
    if (!$result) {
        echo "⚠️  Query error: " . $conn->error . "\n";
    } else {
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = [
                'topic' => $row['topic'],
                'source' => 'generated_topics',
                'type' => 'short'
            ];
            
            if (count($documents) >= $batchSize) {
                $meilisearch->addTopicsBatch($documents);
                $totalImported += count($documents);
                echo "  ➜ Imported " . count($documents) . " from Generated Topics (Total: $totalImported)\n";
                $documents = [];
            }
        }
        
        if (!empty($documents)) {
            $meilisearch->addTopicsBatch($documents);
            $totalImported += count($documents);
            echo "  ➜ Imported " . count($documents) . " from Generated Topics (Total: $totalImported)\n";
        }
    }
} catch (Exception $e) {
    echo "⚠️  Error importing Generated Topics: " . $e->getMessage() . "\n";
}

// ============ Source 4: Questions ============
echo "\n📚 Importing topics from Questions...\n";
try {
    $query = "SELECT DISTINCT topic FROM questions WHERE topic IS NOT NULL AND topic != '' ORDER BY topic";
    $result = $conn->query($query);
    
    if (!$result) {
        echo "⚠️  Query error: " . $conn->error . "\n";
    } else {
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = [
                'topic' => $row['topic'],
                'source' => 'questions',
                'type' => 'long'
            ];
            
            if (count($documents) >= $batchSize) {
                $meilisearch->addTopicsBatch($documents);
                $totalImported += count($documents);
                echo "  ➜ Imported " . count($documents) . " from Questions (Total: $totalImported)\n";
                $documents = [];
            }
        }
        
        if (!empty($documents)) {
            $meilisearch->addTopicsBatch($documents);
            $totalImported += count($documents);
            echo "  ➜ Imported " . count($documents) . " from Questions (Total: $totalImported)\n";
        }
    }
} catch (Exception $e) {
    echo "⚠️  Error importing Questions: " . $e->getMessage() . "\n";
}

// ============ Source 5: AI Questions Topic ============
echo "\n📚 Importing topics from AI Questions Topic...\n";
try {
    $query = "SELECT DISTINCT topic FROM AIQuestionsTopic WHERE topic IS NOT NULL AND topic != '' ORDER BY topic";
    $result = $conn->query($query);
    
    if (!$result) {
        echo "⚠️  Query error: " . $conn->error . "\n";
    } else {
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = [
                'topic' => $row['topic'],
                'source' => 'ai_questions_topic',
                'type' => 'long'
            ];
            
            if (count($documents) >= $batchSize) {
                $meilisearch->addTopicsBatch($documents);
                $totalImported += count($documents);
                echo "  ➜ Imported " . count($documents) . " from AI Questions Topic (Total: $totalImported)\n";
                $documents = [];
            }
        }
        
        if (!empty($documents)) {
            $meilisearch->addTopicsBatch($documents);
            $totalImported += count($documents);
            echo "  ➜ Imported " . count($documents) . " from AI Questions Topic (Total: $totalImported)\n";
        }
    }
} catch (Exception $e) {
    echo "⚠️  Error importing AI Questions Topic: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Import Complete!\n";
echo "📊 Total topics imported: $totalImported\n";
echo "🔗 Meilisearch Host: " . EnvLoader::get('MEILISEARCH_HOST') . "\n";
echo "📑 Index Name: topics\n";
echo "\n💡 Next steps:\n";
echo "   1. Test search: Visit your search page to verify\n";
echo "   2. Monitor: Check Meilisearch dashboard for indexing status\n";
echo "   3. Setup: Run this script again after adding new topics\n";
echo str_repeat("=", 60) . "\n";

$conn->close();
?>
