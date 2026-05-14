<?php
require_once __DIR__ . '/../db_connect.php';

echo "<h1>Creating Exam Preparation Tables...</h1>";
echo "<pre>";

function runQuery($conn, $sql, $message) {
    echo "Processing: $message... ";
    try {
        if ($conn->query($sql) === TRUE) {
            echo "<span style='color:green;'>OK</span>\n";
        } else {
            echo "<span style='color:red;'>Error: " . $conn->error . "</span>\n";
        }
    } catch (Exception $e) {
        echo "<span style='color:red;'>Exception: " . $e->getMessage() . "</span>\n";
    }
}

$sql = "CREATE TABLE IF NOT EXISTS exam_preparations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    class_id INT NOT NULL,
    book_id INT NOT NULL,
    chapter_ids TEXT NOT NULL,
    mcq_count INT DEFAULT 0,
    short_count INT DEFAULT 0,
    long_count INT DEFAULT 0,
    selection_type ENUM('manual', 'random') NOT NULL DEFAULT 'random',
    question_ids TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES class(class_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES book(book_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

runQuery($conn, $sql, "Table: exam_preparations");

echo "</pre>";
?>
