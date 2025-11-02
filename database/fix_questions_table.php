<?php
// This script inspects and fixes the `chapter` table schema to match app expectations.
// Usage: visit /database/fix_chapter_table.php once, then remove the file for security.
require_once __DIR__ . '/../db_connect.php';

function out($msg) { echo '<p>' . htmlspecialchars($msg) . '</p>'; }

echo '<h2>Fix chapter table schema</h2>';

$exists = false;
$res = $conn->query("SHOW TABLES LIKE 'chapter'");
if ($res && $res->num_rows > 0) { $exists = true; }

if (!$exists) {
    out('Table `chapter` does not exist. Creating...');
    $sql = "CREATE TABLE chapter (
        chapter_id INT NOT NULL AUTO_INCREMENT,
        chapter_name VARCHAR(191) NOT NULL,
        class_id INT NOT NULL,
        book_name VARCHAR(191) NOT NULL,
        PRIMARY KEY (chapter_id),
        INDEX idx_class_book (class_id, book_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql)) {
        out('ERROR creating table: ' . $conn->error);
        exit;
    }
    out('Table `chapter` created.');
} else {
    out('Table `chapter` exists. Inspecting columns...');

    $columns = [];
    $res = $conn->query("DESCRIBE chapter");
    while ($res && $row = $res->fetch_assoc()) { $columns[$row['Field']] = $row; }

    // If there's an `id` column but no `chapter_id`, rename it.
    if (!isset($columns['chapter_id']) && isset($columns['id'])) {
        out('Renaming `id` to `chapter_id`...');
        if (!$conn->query("ALTER TABLE chapter CHANGE COLUMN id chapter_id INT")) {
            out('ERROR renaming column: ' . $conn->error);
            exit;
        }
        // Refresh DESCRIBE
        $columns = [];
        $res = $conn->query("DESCRIBE chapter");
        while ($res && $row = $res->fetch_assoc()) { $columns[$row['Field']] = $row; }
    }

    // Ensure chapter_id exists
    if (!isset($columns['chapter_id'])) {
        out('Adding `chapter_id` column...');
        if (!$conn->query("ALTER TABLE chapter ADD COLUMN chapter_id INT FIRST")) {
            out('ERROR adding chapter_id: ' . $conn->error);
            exit;
        }
    }

    // Ensure chapter_id is primary key and auto_increment
    // Drop existing primary key if different
    $hasPkOnChapterId = false;
    $res = $conn->query("SHOW KEYS FROM chapter WHERE Key_name = 'PRIMARY'");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if ($row['Column_name'] === 'chapter_id') { $hasPkOnChapterId = true; }
        }
    }
    if (!$hasPkOnChapterId) {
        out('Adjusting PRIMARY KEY to be on `chapter_id`...');
        
        // First, try to drop existing primary key (ignore errors if none exists)
        $dropResult = $conn->query("ALTER TABLE chapter DROP PRIMARY KEY");
        if (!$dropResult && !strpos($conn->error, "check that column/key exists")) {
            out('Warning when dropping primary key: ' . $conn->error);
        }
        
        // Add new primary key on chapter_id
        if (!$conn->query("ALTER TABLE chapter ADD PRIMARY KEY (chapter_id)")) {
            out('ERROR setting primary key: ' . $conn->error);
            // Try alternative approach - modify the column directly
            out('Trying alternative approach...');
            if (!$conn->query("ALTER TABLE chapter MODIFY COLUMN chapter_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY")) {
                out('FATAL ERROR: Could not set chapter_id as primary key: ' . $conn->error);
                exit;
            } else {
                out('Successfully set chapter_id as AUTO_INCREMENT PRIMARY KEY');
            }
        } else {
            out('Primary key set successfully');
        }
    }

    // Make chapter_id INT NOT NULL AUTO_INCREMENT
    out('Ensuring `chapter_id` is INT AUTO_INCREMENT...');
    if (!$conn->query("ALTER TABLE chapter MODIFY COLUMN chapter_id INT NOT NULL AUTO_INCREMENT")) {
        out('ERROR making chapter_id AUTO_INCREMENT: ' . $conn->error);
        exit;
    }

    // Ensure other required columns
    $required = [
        'chapter_name' => "ALTER TABLE chapter ADD COLUMN chapter_name VARCHAR(191) NOT NULL AFTER chapter_id",
        'class_id' => "ALTER TABLE chapter ADD COLUMN class_id INT NOT NULL AFTER chapter_name",
        'book_name' => "ALTER TABLE chapter ADD COLUMN book_name VARCHAR(191) NOT NULL AFTER class_id",
    ];
    foreach ($required as $col => $ddl) {
        if (!isset($columns[$col])) {
            out("Adding `$col` column...");
            if (!$conn->query($ddl)) { out('ERROR adding ' . $col . ': ' . $conn->error); exit; }
        }
    }

    // Create index for (class_id, book_name)
    $idxRes = $conn->query("SHOW INDEX FROM chapter WHERE Key_name = 'idx_class_book'");
    if (!$idxRes || $idxRes->num_rows === 0) {
        out('Creating index idx_class_book...');
        if (!$conn->query("ALTER TABLE chapter ADD INDEX idx_class_book (class_id, book_name)")) {
            out('ERROR creating index: ' . $conn->error);
        }
    }

    out('Schema inspection and adjustments complete.');
}

echo '<hr><p><a href="../test_chapter.php">Run test_chapter.php</a> | <a href="../admin/manage_chapters.php">Back to Manage Chapters</a></p>';

