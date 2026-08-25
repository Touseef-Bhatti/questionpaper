<?php

/**
 * Ensure the table used to store admin-selected topic recommendations exists.
 */
function ensureAiTopicRecommendationsTable($conn) {
    static $result = null;

    if ($result !== null) {
        return $result;
    }

    $result = (bool)$conn->query("CREATE TABLE IF NOT EXISTS AIRecommendedTopics (
        topic_name VARCHAR(255) NOT NULL PRIMARY KEY,
        selected_by INT NULL,
        selected_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ai_recommended_topic_selected_at (selected_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!$result) {
        error_log('Unable to create AIRecommendedTopics: ' . $conn->error);
        return false;
    }

    // Carry forward any selections made by the earlier MCQ-level implementation.
    $legacyTable = $conn->query("SHOW TABLES LIKE 'AIRecommendedMCQs'");
    if ($legacyTable && $legacyTable->num_rows > 0) {
        $migrationSucceeded = $conn->query("INSERT IGNORE INTO AIRecommendedTopics (topic_name, selected_by, selected_at)
            SELECT m.topic, MIN(r.selected_by), MIN(r.selected_at)
            FROM AIRecommendedMCQs r
            INNER JOIN AIGeneratedMCQs m ON m.id = r.mcq_id
            WHERE m.topic IS NOT NULL AND TRIM(m.topic) != ''
            GROUP BY m.topic");
        if ($migrationSucceeded) {
            $conn->query('DROP TABLE AIRecommendedMCQs');
        }
    }

    return $result;
}
