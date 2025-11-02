<?php
// Diagnostic: Test why deleting a question triggers HTTP 500
// Usage: visit /test_delete_question.php
// This script mimics admin/manage_questions.php delete logic and prints detailed diagnostics.

// Enable verbose error reporting for this test only
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db_connect.php';

echo '<h2>Delete Question Diagnostic</h2>';

function p($msg) { echo '<p>' . htmlspecialchars($msg) . '</p>'; }
function ok($msg) { echo '<p style="color:green;">' . htmlspecialchars('✓ ' . $msg) . '</p>'; }
function warn($msg) { echo '<p style="color:orange;">' . htmlspecialchars('! ' . $msg) . '</p>'; }
function err($msg) { echo '<p style="color:red;">' . htmlspecialchars('✗ ' . $msg) . '</p>'; }

// 0) Server info
$pinfo = mysqli_get_server_info($conn);
p("MySQL server info: $pinfo");

// 1) Detect questions schema (book_name presence, and column aliases)
$hasBookName = false;
$res = $conn->query("SHOW COLUMNS FROM questions LIKE 'book_name'");
if ($res && $res->num_rows > 0) { $hasBookName = true; }
$hasQuestionType = $conn->query("SHOW COLUMNS FROM questions LIKE 'question_type'");
$hasQuestionText = $conn->query("SHOW COLUMNS FROM questions LIKE 'question_text'");
$typeCol = ($hasQuestionType && $hasQuestionType->num_rows > 0) ? 'question_type' : 'type';
$textCol = ($hasQuestionText && $hasQuestionText->num_rows > 0) ? 'question_text' : 'text';

p('Detected columns: ' . ($hasBookName ? 'questions.book_name present' : 'questions.book_name absent') . ", typeCol=$typeCol, textCol=$textCol");

// 2) Ensure deleted_questions table exists (as manage_questions.php expects)
$sqlCreate = "CREATE TABLE IF NOT EXISTS deleted_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    class_id INT NOT NULL,
    book_name VARCHAR(191) NULL,
    chapter_id INT NOT NULL,
    question_type ENUM('mcq','short','long') NOT NULL,
    question_text TEXT NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sqlCreate)) {
    ok('deleted_questions table verified/created');
} else {
    err('Error creating deleted_questions: ' . $conn->error);
}

// 3) Pick a question candidate to delete (or create a disposable one)
$pick = $conn->query("SELECT id FROM questions ORDER BY id DESC LIMIT 1");
$deleteId = 0;
if ($pick && ($row = $pick->fetch_assoc())) {
    $deleteId = (int)$row['id'];
    p("Selected existing question id=$deleteId for delete test");
} else {
    warn('No existing question found; creating a test one');
    $stmt = $conn->prepare("INSERT INTO questions (class_id, chapter_id, $typeCol, $textCol" . ($hasBookName? ', book_name':'') . ") VALUES (?, ?, ?, ?" . ($hasBookName? ', ?':'') . ")");
    if ($stmt) {
        $classId = 1; $chapterId = 1; $qtype = 'short'; $qtext = 'TEMP DELETE DIAG'; $bname = 'TEMP';
        if ($hasBookName) {
            $stmt->bind_param('iisss', $classId, $chapterId, $qtype, $qtext, $bname);
        } else {
            $stmt->bind_param('iiss', $classId, $chapterId, $qtype, $qtext);
        }
        if ($stmt->execute()) {
            $deleteId = $conn->insert_id;
            ok("Created test question id=$deleteId");
        } else {
            err('Failed to create test question: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        err('Prepare failed when inserting test question: ' . $conn->error);
    }
}

if ($deleteId <= 0) {
    err('Could not obtain a question id to delete. Aborting.');
    exit;
}

// 4) Reproduce admin delete flow: archive then delete
p('Archiving question to deleted_questions...');
$bnJoin = $hasBookName ? '' : 'LEFT JOIN chapter c ON c.chapter_id = q.chapter_id';
$bnExpr = $hasBookName ? 'q.book_name' : 'c.book_name';
$q = $conn->query("SELECT q.id, q.class_id, q.chapter_id, $bnExpr AS book_name, q.$typeCol AS qtype, q.$textCol AS qtext FROM questions q $bnJoin WHERE q.id=$deleteId LIMIT 1");
if (!$q) {
    err('Fetch question failed: ' . $conn->error);
} else if (!($qr = $q->fetch_assoc())) {
    err('Question not found just before archive; possibly already deleted.');
} else {
    $qid = (int)$qr['id'];
    $cid = (int)$qr['class_id'];
    $chap = (int)$qr['chapter_id'];
    $bname = $conn->real_escape_string($qr['book_name'] ?? '');
    $qtype = $conn->real_escape_string($qr['qtype']);
    $qtext = $conn->real_escape_string($qr['qtext']);
    $ins = $conn->query("INSERT INTO deleted_questions (question_id, class_id, book_name, chapter_id, question_type, question_text) VALUES ($qid, $cid, " . ($bname!==''?"'$bname'":"NULL") . ", $chap, '$qtype', '$qtext')");
    if ($ins) {
        ok('Archived successfully into deleted_questions');
    } else {
        err('Archive insert failed: ' . $conn->error);
    }
}

// 5) Try delete and capture errors
p('Deleting from questions...');
$del = $conn->query("DELETE FROM questions WHERE id=$deleteId");
if ($del) {
    ok('Delete successful');
} else {
    err('Delete failed: ' . $conn->error);
}

// 6) Check FK references to questions that may block delete
p('Checking foreign key references to questions...');
$fk = $conn->query("SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME='questions' AND TABLE_SCHEMA=DATABASE()");
if ($fk && $fk->num_rows > 0) {
    echo '<table border="1" style="border-collapse:collapse"><tr><th>Table</th><th>Constraint</th><th>Column</th></tr>';
    while ($r = $fk->fetch_assoc()) {
        echo '<tr><td>' . htmlspecialchars($r['TABLE_NAME']) . '</td><td>' . htmlspecialchars($r['CONSTRAINT_NAME']) . '</td><td>' . htmlspecialchars($r['COLUMN_NAME']) . '</td></tr>';
    }
    echo '</table>';
} else {
    ok('No foreign key references found (or none detected)');
}

// 7) Show recent errors/messages if any
if ($conn->error) {
    warn('Final mysqli error: ' . $conn->error);
}

// Done
echo '<hr><p><a href="admin/manage_questions.php">Back to Manage Questions</a></p>';
?>

