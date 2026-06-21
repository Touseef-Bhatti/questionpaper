<?php
/**
 * Sync book-generated questions into the normal mcqs/questions tables.
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts\book_questions\sync_book_tables_to_main.php
 *   C:\xampp\php\php.exe scripts\book_questions\sync_book_tables_to_main.php 500
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only." . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/book_questions/BookQuestionGenerator.php';

$limit = isset($argv[1]) ? max(0, (int) $argv[1]) : 0;

$generator = new BookQuestionGenerator($conn);
$stats = $generator->syncBookTablesToMainTables($limit);

echo "Book question sync complete." . PHP_EOL;
echo "MCQs processed: " . $stats['mcqs_processed'] . PHP_EOL;
echo "MCQs failed: " . $stats['mcqs_failed'] . PHP_EOL;
echo "Questions processed: " . $stats['questions_processed'] . PHP_EOL;
echo "Questions failed: " . $stats['questions_failed'] . PHP_EOL;

exit(($stats['mcqs_failed'] + $stats['questions_failed']) > 0 ? 1 : 0);
