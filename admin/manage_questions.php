<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

$hasBookName = false;
// Detect whether questions.book_name column exists for compatibility with older databases
$colCheck = $conn->query("SHOW COLUMNS FROM questions LIKE 'book_name'");
if ($colCheck && $colCheck->num_rows > 0) { $hasBookName = true; }

// Create mcqs table if it doesn't exist
// Schema creation moved to install.php

// Detect column naming for type/text: either (question_type, question_text) or (type, text)
$hasQuestionType = false;
$hasQuestionText = false;
$colTypeCheck = $conn->query("SHOW COLUMNS FROM questions LIKE 'question_type'");
if ($colTypeCheck && $colTypeCheck->num_rows > 0) { $hasQuestionType = true; }
$colTextCheck = $conn->query("SHOW COLUMNS FROM questions LIKE 'question_text'");
if ($colTextCheck && $colTextCheck->num_rows > 0) { $hasQuestionText = true; }

$typeCol = ($hasQuestionType ? 'question_type' : 'type');
$textCol = ($hasQuestionText ? 'question_text' : 'text');

// Schema creation moved to install.php

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Security token invalid. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $classId = intval($_POST['class_id'] ?? 0);
        $bookId = intval($_POST['book_id'] ?? 0);
        $bookName = trim($_POST['book_name'] ?? '');
        $chapterId = intval($_POST['chapter_id'] ?? 0);
        $type = trim($_POST['type'] ?? 'short'); // mcq | short | long
        $texts = isset($_POST['text']) ? $_POST['text'] : [];
        $topics = isset($_POST['topic']) ? $_POST['topic'] : [];
        $optionAs = isset($_POST['option_a']) ? $_POST['option_a'] : [];
        $optionBs = isset($_POST['option_b']) ? $_POST['option_b'] : [];
        $optionCs = isset($_POST['option_c']) ? $_POST['option_c'] : [];
        $optionDs = isset($_POST['option_d']) ? $_POST['option_d'] : [];
        $correctOptions = isset($_POST['correct_option']) ? $_POST['correct_option'] : [];

        $count = max(count($texts), count($topics));
        $inserted = 0;
        for ($i = 0; $i < $count; $i++) {
            $text = trim($texts[$i] ?? '');
            $topic = trim($topics[$i] ?? '');
            $optionA = trim($optionAs[$i] ?? '');
            $optionB = trim($optionBs[$i] ?? '');
            $optionC = trim($optionCs[$i] ?? '');
            $optionD = trim($optionDs[$i] ?? '');
            $correctOptionLetter = strtoupper(trim($correctOptions[$i] ?? ''));
            $correctOptionText = '';
            switch ($correctOptionLetter) {
                case 'A': $correctOptionText = $optionA; break;
                case 'B': $correctOptionText = $optionB; break;
                case 'C': $correctOptionText = $optionC; break;
                case 'D': $correctOptionText = $optionD; break;
            }
            if ($classId > 0 && $chapterId > 0 && $text !== '') {
                $bookEsc = $conn->real_escape_string($bookName);
                $textEsc = $conn->real_escape_string($text);
                $typeEsc = $conn->real_escape_string($type);
                if ($type === 'mcq' && $optionA !== '' && $optionB !== '' && $optionC !== '' && $optionD !== '') {
                    $optionAEsc = $conn->real_escape_string($optionA);
                    $optionBEsc = $conn->real_escape_string($optionB);
                    $optionCEsc = $conn->real_escape_string($optionC);
                    $optionDEsc = $conn->real_escape_string($optionD);
                    $topicEsc = $conn->real_escape_string($topic);
                    $correctOptionEsc = $conn->real_escape_string($correctOptionText);
                    if ($conn->query("INSERT INTO mcqs (class_id, book_id, chapter_id, topic, question, option_a, option_b, option_c, option_d, correct_option) VALUES ($classId, $bookId, $chapterId, '$topicEsc', '$textEsc', '$optionAEsc', '$optionBEsc', '$optionCEsc', '$optionDEsc', '$correctOptionEsc')")) {
                        $inserted++;
                    }
                } else {
                    try {
                        if ($hasBookName) {
                            if ($bookId > 0) {
                                $bnRes = $conn->query("SELECT book_name FROM book WHERE book_id=$bookId LIMIT 1");
                                if ($bnRes && ($bnRow = $bnRes->fetch_assoc())) { $bookEsc = $conn->real_escape_string($bnRow['book_name']); }
                            }
                            if ($bookEsc === '') {
                                $bnRes = $conn->query("SELECT book_name FROM chapter WHERE chapter_id=$chapterId LIMIT 1");
                                if ($bnRes && ($bnRow = $bnRes->fetch_assoc())) { $bookEsc = $conn->real_escape_string($bnRow['book_name']); }
                            }
                            $topicEsc = $conn->real_escape_string($topic);
                            if ($conn->query("INSERT INTO questions (class_id, book_name, book_id, chapter_id, $typeCol, $textCol, topic) VALUES ($classId, '$bookEsc', $bookId, $chapterId, '$typeEsc', '$textEsc', '$topicEsc')")) {
                                $inserted++;
                            }
                        } else {
                            $topicEsc = $conn->real_escape_string($topic);
                            if ($conn->query("INSERT INTO questions (class_id, book_id, chapter_id, $typeCol, $textCol, topic) VALUES ($classId, $bookId, $chapterId, '$typeEsc', '$textEsc', '$topicEsc')")) {
                                $inserted++;
                            }
                        }
                    } catch (mysqli_sql_exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate question detected in this class and chapter') !== false) {
                            continue;
                        } else {
                            throw $e;
                        }
                    }
                }
            }
        }
        if ($inserted > 0) {
            header('Location: manage_questions.php?msg=created');
            exit;
        }
    }
    elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $classId = intval($_POST['class_id'] ?? 0);
        $chapterId = intval($_POST['chapter_id'] ?? 0);
        $type = trim($_POST['type'] ?? 'short');
        $topic = trim($_POST['topic'] ?? '');
        $text = trim($_POST['text'] ?? '');
        $bookId = intval($_POST['book_id'] ?? 0);
        
        // Handle MCQ options if type is mcq
        $optionA = trim($_POST['option_a'] ?? '');
        $optionB = trim($_POST['option_b'] ?? '');
        $optionC = trim($_POST['option_c'] ?? '');
        $optionD = trim($_POST['option_d'] ?? '');
        // We accept a letter (A/B/C/D) but store the actual option text in DB
        $correctOptionLetter = strtoupper(trim($_POST['correct_option'] ?? ''));
        $correctOptionText = '';
        switch ($correctOptionLetter) {
            case 'A': $correctOptionText = $optionA; break;
            case 'B': $correctOptionText = $optionB; break;
            case 'C': $correctOptionText = $optionC; break;
            case 'D': $correctOptionText = $optionD; break;
        }
        $mcqId = intval($_POST['mcq_id'] ?? 0);
        
        if ($id > 0 && $classId > 0 && $chapterId > 0 && $text !== '') {
            // If type is mcq and we have options
            if ($type === 'mcq' && $optionA !== '' && $optionB !== '' && $optionC !== '' && $optionD !== '') {
                $optionAEsc = $conn->real_escape_string($optionA);
                $optionBEsc = $conn->real_escape_string($optionB);
                $optionCEsc = $conn->real_escape_string($optionC);
                $optionDEsc = $conn->real_escape_string($optionD);
                $textEsc = $conn->real_escape_string($text);
                $topicEsc = $conn->real_escape_string($topic);
                $correctOptionEsc = $conn->real_escape_string($correctOptionText);
                
                // If mcq_id exists, update the mcq record
                if ($mcqId > 0) {
                    if ($conn->query("UPDATE mcqs SET class_id=$classId, book_id=$bookId, chapter_id=$chapterId, topic='$topicEsc', question='$textEsc', option_a='$optionAEsc', option_b='$optionBEsc', option_c='$optionCEsc', option_d='$optionDEsc', correct_option='$correctOptionEsc' WHERE mcq_id=$mcqId")) {
                        header('Location: manage_questions.php?msg=updated');
                        exit;
                    }
                } else {
                    // Insert new MCQ record
                    if ($conn->query("INSERT INTO mcqs (class_id, book_id, chapter_id, topic, question, option_a, option_b, option_c, option_d, correct_option) VALUES ($classId, $bookId, $chapterId, '$topicEsc', '$textEsc', '$optionAEsc', '$optionBEsc', '$optionCEsc', '$optionDEsc', '$correctOptionEsc')")) {
                        // Delete the original question if it exists
                        $conn->query("DELETE FROM questions WHERE id=$id");
                        header('Location: manage_questions.php?msg=updated');
                        exit;
                    }
                }
            } else {
                // Regular question update
                $typeEsc = $conn->real_escape_string($type);
                $textEsc = $conn->real_escape_string($text);
                if ($hasBookName) {
                    $bookNameUpd = '';
                    if ($bookId > 0) {
                        $bnRes = $conn->query("SELECT book_name FROM book WHERE book_id=$bookId LIMIT 1");
                        if ($bnRes && ($bnRow = $bnRes->fetch_assoc())) { $bookNameUpd = $conn->real_escape_string($bnRow['book_name']); }
                    }
                    if ($bookNameUpd === '') {
                        $bnRes = $conn->query("SELECT book_name FROM chapter WHERE chapter_id=$chapterId LIMIT 1");
                        if ($bnRes && ($bnRow = $bnRes->fetch_assoc())) { $bookNameUpd = $conn->real_escape_string($bnRow['book_name']); }
                    }
                    $topicEsc = $conn->real_escape_string($topic);
                    if ($conn->query("UPDATE questions SET class_id=$classId, book_name='$bookNameUpd', book_id=$bookId, chapter_id=$chapterId, $typeCol='$typeEsc', $textCol='$textEsc', topic='$topicEsc' WHERE id=$id")) {
                        header('Location: manage_questions.php?msg=updated');
                        exit;
                    }
                } else {
                    $topicEsc = $conn->real_escape_string($topic);
                    if ($conn->query("UPDATE questions SET class_id=$classId, book_id=$bookId, chapter_id=$chapterId, $typeCol='$typeEsc', $textCol='$textEsc', topic='$topicEsc' WHERE id=$id")) {
                        header('Location: manage_questions.php?msg=updated');
                        exit;
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // archive to deleted_questions
            $bnJoin = $hasBookName ? '' : 'LEFT JOIN chapter c ON c.chapter_id = q.chapter_id';
            $bnExpr = $hasBookName ? 'q.book_name' : 'c.book_name';
            $resQ = $conn->query("SELECT q.id, q.class_id, q.chapter_id, $bnExpr AS book_name, q.$typeCol AS qtype, q.$textCol AS qtext FROM questions q $bnJoin WHERE q.id=$id LIMIT 1");
            if ($resQ && ($rowQ = $resQ->fetch_assoc())) {
                $qid = (int)$rowQ['id'];
                $cid = (int)$rowQ['class_id'];
                $chap = (int)$rowQ['chapter_id'];
                $bname = $conn->real_escape_string($rowQ['book_name'] ?? '');
                $qtype = $conn->real_escape_string($rowQ['qtype']);
                $qtext = $conn->real_escape_string($rowQ['qtext']);
                $conn->query("INSERT INTO deleted_questions (question_id, class_id, book_name, chapter_id, question_type, question_text) VALUES ($qid, $cid, " . ($bname!==''?"'$bname'":"NULL") . ", $chap, '$qtype', '$qtext')");
            }
            // then delete original
            if ($conn->query("DELETE FROM questions WHERE id=$id")) {
                header('Location: manage_questions.php?msg=deleted');
                exit;
            }
        }
    } elseif ($action === 'delete_mcq') {
        $mcqId = intval($_POST['mcq_id'] ?? 0);
        if ($mcqId > 0) {
            if ($conn->query("DELETE FROM mcqs WHERE mcq_id=$mcqId")) {
                header('Location: manage_questions.php?msg=mcq_deleted');
                exit;
            }
        }
    } elseif ($action === 'update_mcq') {
        $mcqId = intval($_POST['mcq_id'] ?? 0);
        $classId = intval($_POST['class_id'] ?? 0);
        $bookId = intval($_POST['book_id'] ?? 0);
        $chapterId = intval($_POST['chapter_id'] ?? 0);
        $topic = trim($_POST['topic'] ?? '');
        $question = trim($_POST['question'] ?? '');
        $optionA = trim($_POST['option_a'] ?? '');
        $optionB = trim($_POST['option_b'] ?? '');
        $optionC = trim($_POST['option_c'] ?? '');
        $optionD = trim($_POST['option_d'] ?? '');
        // We accept a letter (A/B/C/D) but store the actual option text in DB
        $correctOptionLetter = strtoupper(trim($_POST['correct_option'] ?? ''));
        $correctOptionText = '';
        switch ($correctOptionLetter) {
            case 'A': $correctOptionText = $optionA; break;
            case 'B': $correctOptionText = $optionB; break;
            case 'C': $correctOptionText = $optionC; break;
            case 'D': $correctOptionText = $optionD; break;
        }
        
        if ($mcqId > 0 && $classId > 0 && $chapterId > 0 && $question !== '' && $optionA !== '' && $optionB !== '' && $optionC !== '' && $optionD !== '') {
            $optionAEsc = $conn->real_escape_string($optionA);
            $optionBEsc = $conn->real_escape_string($optionB);
            $optionCEsc = $conn->real_escape_string($optionC);
            $optionDEsc = $conn->real_escape_string($optionD);
            $textEsc = $conn->real_escape_string($question);
            $topicEsc = $conn->real_escape_string($topic);
            $correctOptionEsc = $conn->real_escape_string($correctOptionText);
            
            if ($conn->query("UPDATE mcqs SET class_id=$classId, book_id=$bookId, chapter_id=$chapterId, topic='$topicEsc', question='$textEsc', option_a='$optionAEsc', option_b='$optionBEsc', option_c='$optionCEsc', option_d='$optionDEsc', correct_option='$correctOptionEsc' WHERE mcq_id=$mcqId")) {
                header('Location: manage_questions.php?msg=mcq_updated');
                exit;
            }
        }
    }
    }
}

// Handle success messages from redirects
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $message = 'Question created successfully.';
            break;
        case 'updated':
            $message = 'Question updated successfully.';
            break;
        case 'deleted':
            $message = 'Question deleted successfully.';
            break;
        case 'mcq_deleted':
            $message = 'MCQ deleted successfully.';
            break;
        case 'mcq_updated':
            $message = 'MCQ updated successfully.';
            break;
    }
}

$classes = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
$chapters = $conn->query("SELECT chapter_id, chapter_name, class_id, book_name FROM chapter ORDER BY chapter_id ASC");
$books = $conn->query("SELECT book_id, book_name, class_id FROM book ORDER BY book_name ASC");
$bookOptions = [];
if ($books) { while ($bk = $books->fetch_assoc()) { $bookOptions[] = $bk; } }

// Filters and sorting - separate for questions and MCQs
$questionSearch = isset($_GET['question_search']) ? trim($_GET['question_search']) : '';
$questionMatch = isset($_GET['question_match']) && strtolower($_GET['question_match']) === 'exact' ? 'exact' : 'contains';
$questionFilterClassId = isset($_GET['question_filter_class_id']) ? intval($_GET['question_filter_class_id']) : 0;
$questionFilterChapterId = isset($_GET['question_filter_chapter_id']) ? intval($_GET['question_filter_chapter_id']) : 0;
$questionFilterBookId = isset($_GET['question_filter_book_id']) ? intval($_GET['question_filter_book_id']) : 0;

$mcqSearch = isset($_GET['mcq_search']) ? trim($_GET['mcq_search']) : '';
$mcqMatch = isset($_GET['mcq_match']) && strtolower($_GET['mcq_match']) === 'exact' ? 'exact' : 'contains';
$mcqFilterClassId = isset($_GET['mcq_filter_class_id']) ? intval($_GET['mcq_filter_class_id']) : 0;
$mcqFilterChapterId = isset($_GET['mcq_filter_chapter_id']) ? intval($_GET['mcq_filter_chapter_id']) : 0;
$mcqFilterBookId = isset($_GET['mcq_filter_book_id']) ? intval($_GET['mcq_filter_book_id']) : 0;

$sortBy = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'id';
$sortDir = strtolower($_GET['sort_dir'] ?? 'desc');
$sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';

// Pagination settings
$questionsPerPage = isset($_GET['questions_per_page']) && $_GET['questions_per_page'] === 'all' ? 'all' : (isset($_GET['questions_per_page']) ? intval($_GET['questions_per_page']) : 10);
$mcqsPerPage = isset($_GET['mcqs_per_page']) ? intval($_GET['mcqs_per_page']) : 10;
$questionsPage = isset($_GET['questions_page']) ? intval($_GET['questions_page']) : 1;
$mcqsPage = isset($_GET['mcqs_page']) ? intval($_GET['mcqs_page']) : 1;

// Validate per page values
$validPerPageValues = [10, 20, 50, 'all'];
if (!in_array($questionsPerPage, $validPerPageValues)) $questionsPerPage = 10;
if (!in_array($mcqsPerPage, $validPerPageValues)) $mcqsPerPage = 10;

// Calculate offsets and limits
$questionsOffset = ($questionsPage - 1) * ($questionsPerPage === 'all' ? 0 : $questionsPerPage);
$mcqsOffset = ($mcqsPage - 1) * ($mcqsPerPage === 'all' ? 0 : $mcqsPerPage);
$questionsLimit = ($questionsPerPage === 'all') ? '' : "LIMIT $questionsPerPage OFFSET $questionsOffset";
$mcqsLimit = ($mcqsPerPage === 'all') ? '' : "LIMIT $mcqsPerPage OFFSET $mcqsOffset";

$qTable = 'q';
$bookExpr = $hasBookName ? "$qTable.book_name" : "c.book_name";
$typeExpr = "$qTable.$typeCol";
$textExpr = "$qTable.$textCol";

$sortMap = [
    'id' => "$qTable.id",
    'class_id' => "$qTable.class_id",
    'book_name' => $bookExpr,
    'chapter_id' => "$qTable.chapter_id",
    'type' => $typeExpr,
    'topic' => "$qTable.topic",
    'text' => $textExpr,
];
$orderExpr = $sortMap[$sortBy] ?? "$qTable.id";

$wheres = [];
if ($questionSearch !== '') {
    $safe = $conn->real_escape_string($questionSearch);
    if ($questionMatch === 'exact') {
        $wheres[] = "(CAST($qTable.id AS CHAR) = '$safe' OR CAST($qTable.class_id AS CHAR) = '$safe' OR CAST($qTable.chapter_id AS CHAR) = '$safe' OR $typeExpr = '$safe' OR $textExpr = '$safe'" . ($hasBookName ? " OR $bookExpr = '$safe'" : " OR c.book_name = '$safe'") . ")";
    } else {
        $like = "%$safe%";
        $wheres[] = "(CAST($qTable.id AS CHAR) LIKE '$like' OR CAST($qTable.class_id AS CHAR) LIKE '$like' OR CAST($qTable.chapter_id AS CHAR) LIKE '$like' OR $typeExpr LIKE '$like' OR $textExpr LIKE '$like'" . ($hasBookName ? " OR $bookExpr LIKE '$like'" : " OR c.book_name LIKE '$like'") . ")";
    }
}
if ($questionFilterClassId > 0) {
    $wheres[] = "$qTable.class_id = $questionFilterClassId";
}
if ($questionFilterChapterId > 0) {
    $wheres[] = "$qTable.chapter_id = $questionFilterChapterId";
}
// Always join book mapping for filtering by book id
$joinChapter = $hasBookName ? '' : " LEFT JOIN chapter c ON c.chapter_id=$qTable.chapter_id";
$joinBook = " LEFT JOIN book b ON b.class_id = $qTable.class_id AND b.book_name = " . ($hasBookName ? "$qTable.book_name" : "c.book_name");
if ($questionFilterBookId > 0) {
    $wheres[] = "b.book_id = $questionFilterBookId";
}
$questionTypeFilter = isset($_GET['question_type_filter']) ? trim($_GET['question_type_filter']) : '';
if ($questionTypeFilter === 'short' || $questionTypeFilter === 'long') {
    $wheres[] = "$typeExpr = '" . $conn->real_escape_string($questionTypeFilter) . "'";
}
$where = count($wheres) ? ('WHERE ' . implode(' AND ', $wheres)) : '';

// Get MCQs with pagination and filtering
$mcqWheres = [];
if ($mcqSearch !== '') {
    $safe = $conn->real_escape_string($mcqSearch);
    if ($mcqMatch === 'exact') {
        $mcqWheres[] = "(CAST(m.mcq_id AS CHAR) = '$safe' OR CAST(m.class_id AS CHAR) = '$safe' OR CAST(m.chapter_id AS CHAR) = '$safe' OR m.question = '$safe' OR m.option_a = '$safe' OR m.option_b = '$safe' OR m.option_c = '$safe' OR m.option_d = '$safe')";
    } else {
        $like = "%$safe%";
        $mcqWheres[] = "(CAST(m.mcq_id AS CHAR) LIKE '$like' OR CAST(m.class_id AS CHAR) LIKE '$like' OR CAST(m.chapter_id AS CHAR) LIKE '$like' OR m.question LIKE '$like' OR m.option_a LIKE '$like' OR m.option_b LIKE '$like' OR m.option_c LIKE '$like' OR m.option_d LIKE '$like')";
    }
}
if ($mcqFilterClassId > 0) {
    $mcqWheres[] = "m.class_id = $mcqFilterClassId";
}
if ($mcqFilterChapterId > 0) {
    $mcqWheres[] = "m.chapter_id = $mcqFilterChapterId";
}
if ($mcqFilterBookId > 0) {
    $mcqWheres[] = "m.book_id = $mcqFilterBookId";
}

$mcqWhere = count($mcqWheres) ? ('WHERE ' . implode(' AND ', $mcqWheres)) : '';

// Get total count for MCQs
$mcqCountResult = $conn->query("SELECT COUNT(*) as total FROM mcqs m $mcqWhere");
$mcqTotalCount = $mcqCountResult ? $mcqCountResult->fetch_assoc()['total'] : 0;
$mcqTotalPages = ($mcqsPerPage === 'all') ? 1 : ceil($mcqTotalCount / $mcqsPerPage);

// Get MCQs with pagination
$mcqs = $conn->query("SELECT m.mcq_id, m.class_id, m.book_id, m.chapter_id, m.topic, m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option, c.chapter_name, b.book_name FROM mcqs m LEFT JOIN chapter c ON c.chapter_id = m.chapter_id LEFT JOIN book b ON b.book_id = m.book_id $mcqWhere ORDER BY m.mcq_id DESC" . ($mcqsLimit ? " $mcqsLimit" : ""));

// Keep the old mcqsData for backward compatibility
$mcqsData = [];
if ($mcqs) {
    while ($mcq = $mcqs->fetch_assoc()) {
        $key = $mcq['class_id'] . '-' . $mcq['chapter_id'] . '-' . $mcq['question'];
        $mcqsData[$key] = $mcq;
    }
}

// Get total count for questions
$questionCountResult = $conn->query("SELECT COUNT(*) as total FROM questions $qTable $joinChapter $joinBook $where");
$questionTotalCount = $questionCountResult ? $questionCountResult->fetch_assoc()['total'] : 0;
$questionTotalPages = ($questionsPerPage === 'all') ? 1 : ceil($questionTotalCount / $questionsPerPage);

if ($hasBookName) {
    $questions = $conn->query("SELECT $qTable.id, $qTable.class_id, $qTable.book_name, $qTable.book_id, $qTable.chapter_id, c.chapter_name, $typeExpr AS question_type, $textExpr AS question_text, $qTable.topic FROM questions $qTable LEFT JOIN chapter c ON c.chapter_id = $qTable.chapter_id $joinBook $where ORDER BY $orderExpr $sortDir" . ($questionsLimit ? " $questionsLimit" : ""));
} else {
    $questions = $conn->query("SELECT $qTable.id, $qTable.class_id, IFNULL(c.book_name,'') AS book_name, $qTable.book_id, $qTable.chapter_id, c.chapter_name, $typeExpr AS question_type, $textExpr AS question_text, $qTable.topic FROM questions $qTable $joinChapter $joinBook $where ORDER BY $orderExpr $sortDir" . ($questionsLimit ? " $questionsLimit" : ""));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/footer.css">
    <style>
        .mcq-options-display {
            margin-top: 8px;
            padding: 8px;
            background-color: #f5f5f5;
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }
        .mcq-options-display div {
            margin-bottom: 4px;
        }
        .mcq-options-display strong {
            display: inline-block;
            width: 20px;
            color: #007bff;
        }
    </style>
    
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="wrap">
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        <h1>Manage Questions</h1>
        <?php if ($message): ?><p class="msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>

            <div style="margin-top:24px; text-align: center;">
            <a href="deleted_questions.php" class="btn" style="display: inline-block; padding: 12px 24px; font-size: 16px; text-decoration: none; background: #28a745; color: white; border-radius: 8px; transition: background 0.3s;">
                🗑️ View Recently Deleted Questions
            </a>
        </div>
        <h3>Create New Question</h3>
        <form method="POST" class="row" id="create-question-form">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <select name="class_id" id="cq_class" required>
                <option value="">Select class</option>
                <?php if ($classes) while ($c = $classes->fetch_assoc()): ?>
                    <option value="<?= (int)$c['class_id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <?php if ($hasBookName): ?>
                <select name="book_id" id="cq_book">
                    <option value="">Select book</option>
                    <?php foreach ($bookOptions as $bk): ?>
                        <option value="<?= (int)$bk['book_id'] ?>" data-class="<?= (int)$bk['class_id'] ?>" data-book-name="<?= htmlspecialchars($bk['book_name']) ?>"><?= htmlspecialchars($bk['book_name']) ?> (Class <?= (int)$bk['class_id'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <select name="chapter_id" id="cq_chapter" required>
                <option value="">Select chapter</option>
                <?php if ($chapters) while ($ch = $chapters->fetch_assoc()): ?>
                    <option value="<?= (int)$ch['chapter_id'] ?>" data-class="<?= (int)$ch['class_id'] ?>" data-book-name="<?= htmlspecialchars($ch['book_name']) ?>"><?= htmlspecialchars($ch['chapter_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <select name="type" id="question_type" required>
                <option value="mcq">MCQ</option>
                <option value="short" selected>Short</option>
                <option value="long">Long</option>
            </select>
            
            <br><br>
            <div id="question-sets">
                <div class="question-set">
                    <textarea name="text[]" placeholder="Question text" required></textarea>
                    <input type="text" name="topic[]" placeholder="Topic" required>
                    <div class="mcq_options" style="display: none;">
                        <input type="text" name="option_a[]" placeholder="Option A">
                        <input type="text" name="option_b[]" placeholder="Option B">
                        <input type="text" name="option_c[]" placeholder="Option C">
                        <input type="text" name="option_d[]" placeholder="Option D">
                        <select name="correct_option[]">
                            <option value="">Select Correct Option</option>
                            <option value="A">Option A</option>
                            <option value="B">Option B</option>
                            <option value="C">Option C</option>
                            <option value="D">Option D</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="button" id="add-next-question" style="margin:10px 0;">Add Next Question</button>
            <button type="submit">Add All</button>
            <script>
            (function(){
                const questionType = document.getElementById('question_type');
                function toggleMcqOptions(set) {
                    const typeSel = questionType;
                    const mcqDiv = set.querySelector('.mcq_options');
                    if (!typeSel || !mcqDiv) return;
                    mcqDiv.style.display = typeSel.value === 'mcq' ? 'block' : 'none';
                }
                function addQuestionSet() {
                    const sets = document.getElementById('question-sets');
                    const first = sets.querySelector('.question-set');
                    const clone = first.cloneNode(true);
                    // Clear values
                    clone.querySelectorAll('textarea, input, select').forEach(el => {
                        if (el.tagName === 'SELECT') el.selectedIndex = 0;
                        else el.value = '';
                    });
                    sets.appendChild(clone);
                    toggleMcqOptions(clone);
                }
                document.getElementById('add-next-question').addEventListener('click', addQuestionSet);
                // Toggle MCQ options for all sets on type change
                questionType.addEventListener('change', function(){
                    document.querySelectorAll('.question-set').forEach(set => toggleMcqOptions(set));
                });
                // Initial toggle
                document.querySelectorAll('.question-set').forEach(set => toggleMcqOptions(set));
            })();
            </script>
        </form>
        <script>
        (function(){
            const classSel = document.getElementById('cq_class');
            const bookSel = document.getElementById('cq_book');
            const chapterSel = document.getElementById('cq_chapter');
            const questionType = document.getElementById('question_type');
            const mcqOptions = document.getElementById('mcq_options');
            
            function filterBooks() {
                if (!bookSel || !classSel) return;
                const cls = classSel.value;
                Array.from(bookSel.options).forEach(opt => {
                    if (!opt.value) { opt.hidden = false; return; }
                    const c = opt.getAttribute('data-class');
                    opt.hidden = (cls && c !== cls);
                });
            }
            
            function filterChapters() {
                if (!chapterSel || !classSel) return;
                const cls = classSel.value;
                const selectedBookName = bookSel ? (bookSel.options[bookSel.selectedIndex]?.getAttribute('data-book-name') || '') : '';
                Array.from(chapterSel.options).forEach(opt => {
                    if (!opt.value) { opt.hidden = false; return; }
                    const c = opt.getAttribute('data-class');
                    const bn = opt.getAttribute('data-book-name') || '';
                    const classMismatch = (cls && c !== cls);
                    const bookMismatch = (bookSel && selectedBookName) ? (bn !== selectedBookName) : false;
                    opt.hidden = classMismatch || bookMismatch;
                });
            }
            
            function toggleMcqOptions() {
                if (!questionType || !mcqOptions) return;
                mcqOptions.style.display = questionType.value === 'mcq' ? 'block' : 'none';
            }
            
            classSel?.addEventListener('change', () => { filterBooks(); filterChapters(); });
            bookSel?.addEventListener('change', () => { filterChapters(); });
            questionType?.addEventListener('change', toggleMcqOptions);
            
            // initialize
            filterBooks(); filterChapters();
            toggleMcqOptions();
            
            // Handle edit form MCQ options toggle
            document.querySelectorAll('.edit-question-type').forEach(select => {
                select.addEventListener('change', function() {
                    const mcqOptionsDiv = this.closest('form').querySelector('.mcq-options-edit');
                    if (mcqOptionsDiv) {
                        mcqOptionsDiv.style.display = this.value === 'mcq' ? 'block' : 'none';
                    }
                });
            });
        })();
        </script>

        <script>
        // MCQ filter form auto-submit
        (function(){
            const mcqForm = document.getElementById('mcq-filter-form');
            if (!mcqForm) return;
            // auto submit on change for selects
            mcqForm.querySelectorAll('select').forEach(sel => {
                sel.addEventListener('change', () => mcqForm.submit());
            });
            // submit on Enter in search
            const search = mcqForm.querySelector('input[name="search"]');
            if (search) {
                search.addEventListener('keydown', (e) => { if (e.key === 'Enter') mcqForm.submit(); });
            }
        })();
        </script>

        <h3>Latest Questions
        <?php if ($questionSearch !== ''): ?>
            <span style="font-size: 14px; color: #6c757d; font-weight: normal;">
                - Search results for "<?= htmlspecialchars($questionSearch) ?>" (<?= $questionTotalCount ?> found)
            </span>
        <?php endif; ?>
        </h3>
        <form method="GET" class="row" id="question-filter-form" style="margin-bottom:8px; background: #f8f9fa; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                <input type="text" name="question_search" placeholder="🔍 Search questions by ID, class, chapter, book, type, or text..." value="<?= htmlspecialchars($questionSearch) ?>" style="flex: 1; padding: 10px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 14px;">
                <select name="question_match" style="padding: 10px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 14px;">
                    <option value="contains" <?= $questionMatch==='contains'?'selected':'' ?>>Contains</option>
                    <option value="exact" <?= $questionMatch==='exact'?'selected':'' ?>>Exact Match</option>
                </select>
            </div>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <select name="question_filter_class_id">
                <option value="0">All classes</option>
                <?php
                $clsRes2 = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
                while ($cc = $clsRes2->fetch_assoc()): ?>
                    <option value="<?= (int)$cc['class_id'] ?>" <?= $questionFilterClassId===(int)$cc['class_id']?'selected':'' ?>><?= htmlspecialchars($cc['class_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <select name="question_filter_book_id" id="qf_book">
                <option value="0">All books</option>
                <?php foreach ($bookOptions as $bk): ?>
                    <?php if ($questionFilterClassId > 0 && (int)$bk['class_id'] !== (int)$questionFilterClassId) continue; ?>
                    <option value="<?= (int)$bk['book_id'] ?>" <?= $questionFilterBookId===(int)$bk['book_id']?'selected':'' ?>><?= htmlspecialchars($bk['book_name']) ?> (Class <?= (int)$bk['class_id'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <select name="question_filter_chapter_id">
                <option value="0">All chapters</option>
                <?php
                // Build chapter list filtered by selected class and book
                $chWhere = [];
                if ($questionFilterClassId > 0) { $chWhere[] = 'class_id='.(int)$questionFilterClassId; }
                if ($questionFilterBookId > 0) {
                    $bnResSel = $conn->query('SELECT book_name FROM book WHERE book_id='.(int)$questionFilterBookId.' LIMIT 1');
                    if ($bnResSel && ($bnRowSel = $bnResSel->fetch_assoc())) {
                        $bnSafe = $conn->real_escape_string($bnRowSel['book_name']);
                        $chWhere[] = "book_name='$bnSafe'";
                    }
                }
                $chapQuery2 = 'SELECT chapter_id, chapter_name FROM chapter' . (count($chWhere)? (' WHERE '.implode(' AND ', $chWhere)) : '') . ' ORDER BY chapter_id ASC';
                $chapRes2 = $conn->query($chapQuery2);
                while ($ch2 = $chapRes2->fetch_assoc()): ?>
                    <option value="<?= (int)$ch2['chapter_id'] ?>" <?= $questionFilterChapterId===(int)$ch2['chapter_id']?'selected':'' ?>><?= htmlspecialchars($ch2['chapter_name']) ?></option>
                <?php endwhile; ?>
            </select>
           
            <select name="question_type_filter" style="margin-left:10px;">
                <option value="">All Types</option>
                <option value="short" <?= (isset($_GET['question_type_filter']) && $_GET['question_type_filter']==='short')?'selected':'' ?>>Short</option>
                <option value="long" <?= (isset($_GET['question_type_filter']) && $_GET['question_type_filter']==='long')?'selected':'' ?>>Long</option>
            </select>
            </select>
            <select name="sort_dir">
                <option value="asc" <?= strtolower($sortDir)==='asc'?'selected':'' ?>>ASC</option>
                <option value="desc" <?= strtolower($sortDir)==='desc'?'selected':'' ?>>DESC</option>
            </select>
            <select name="questions_per_page">
                <option value="10" <?= $questionsPerPage==10?'selected':'' ?>>10 per page</option>
                <option value="20" <?= $questionsPerPage==20?'selected':'' ?>>20 per page</option>
                <option value="50" <?= $questionsPerPage==50?'selected':'' ?>>50 per page</option>
                <option value="all" <?= $questionsPerPage==='all'?'selected':'' ?>>View All</option>
            </select>
            <button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">Apply Filters</button>
            <?php if ($questionSearch !== '' || $questionFilterClassId > 0 || $questionFilterChapterId > 0 || $questionFilterBookId > 0): ?>
                <a href="?" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 14px;">Clear Search</a>
            <?php endif; ?>
        </form>
        <script>
        (function(){
            const form = document.getElementById('question-filter-form');
            if (!form) return;
            // auto submit on change for selects
            form.querySelectorAll('select').forEach(sel => {
                sel.addEventListener('change', () => form.submit());
            });
            // submit on Enter in search
            const search = form.querySelector('input[name="search"]');
            if (search) {
                search.addEventListener('keydown', (e) => { if (e.key === 'Enter') form.submit(); });
            }
        })();
        </script>

        <script>
        // Dynamic filtering for edit forms
        function setupEditFormFiltering(questionId) {
            const classSel = document.getElementById('edit-class-' + questionId);
            const bookSel = document.getElementById('edit-book-' + questionId);
            const chapterSel = document.getElementById('edit-chapter-' + questionId);
            
            if (!classSel || !chapterSel) return;
            
            const allChapters = Array.from(chapterSel.options).map(opt => ({
                value: opt.value,
                text: opt.textContent,
                classId: opt.getAttribute('data-class'),
                bookName: opt.getAttribute('data-book-name')
            }));

            function filterEditOptions() {
                const selectedClassId = classSel.value;
                const selectedBookName = bookSel ? (bookSel.options[bookSel.selectedIndex]?.getAttribute('data-book-name') || '') : '';

                // Filter Books
                if (bookSel) {
                    Array.from(bookSel.options).forEach(opt => {
                        if (!opt.value) { opt.hidden = false; return; }
                        const classId = opt.getAttribute('data-class');
                        opt.hidden = (selectedClassId && classId !== selectedClassId);
                    });
                    if (bookSel.selectedOptions[0] && bookSel.selectedOptions[0].hidden) {
                        bookSel.value = '';
                    }
                }

                // Filter Chapters
                chapterSel.innerHTML = '<option value="">Select chapter</option>';
                allChapters.forEach(chap => {
                    if (!chap.value) return;
                    const matchesClass = !selectedClassId || (chap.classId === selectedClassId);
                    const matchesBook = !selectedBookName || (chap.bookName === selectedBookName);

                    if (matchesClass && matchesBook) {
                        const opt = document.createElement('option');
                        opt.value = chap.value;
                        opt.textContent = chap.text;
                        opt.setAttribute('data-class', chap.classId);
                        opt.setAttribute('data-book-name', chap.bookName);
                        if (chap.value === chapterSel.getAttribute('data-original-value')) {
                            opt.selected = true;
                        }
                        chapterSel.appendChild(opt);
                    }
                });
            }

            classSel.addEventListener('change', filterEditOptions);
            if (bookSel) bookSel.addEventListener('change', filterEditOptions);
            
            // Store original chapter value for restoration
            chapterSel.setAttribute('data-original-value', chapterSel.value);
            filterEditOptions();
        }

        // Setup filtering for all edit forms when they're shown
        document.addEventListener('click', function(e) {
            if (e.target.matches('button[onclick*="edit-"]')) {
                const questionId = e.target.onclick.toString().match(/edit-(\d+)/)[1];
                setTimeout(() => setupEditFormFiltering(questionId), 100);
            }
        });
        </script>

        <table>
            <thead><tr>
                <th><a href="?<?= http_build_query(['search'=>$search,'sort_by'=>'id','sort_dir'=> strtolower($sortDir)==='asc'?'desc':'asc']) ?>">ID</a></th>
                <th><a href="?<?= http_build_query(['search'=>$search,'sort_by'=>'class_id','sort_dir'=> strtolower($sortDir)==='asc'?'desc':'asc']) ?>">Class</a></th>
                <th><a href="?<?= http_build_query(['search'=>$search,'sort_by'=>'book_name','sort_dir'=> strtolower($sortDir)==='asc'?'desc':'asc']) ?>">Book</a></th>
                <th><a href="?<?= http_build_query(['search'=>$search,'sort_by'=>'chapter_id','sort_dir'=> strtolower($sortDir)==='asc'?'desc':'asc']) ?>">Chapter</a></th>
                <th><a href="?<?= http_build_query(['search'=>$search,'sort_by'=>'type','sort_dir'=> strtolower($sortDir)==='asc'?'desc':'asc']) ?>">Type</a></th>
                <th><a href="?<?= http_build_query(['search'=>$search,'sort_by'=>'topic','sort_dir'=> strtolower($sortDir)==='asc'?'desc':'asc']) ?>">Topic</a></th>
                <th><a href="?<?= http_build_query(['search'=>$search,'sort_by'=>'text','sort_dir'=> strtolower($sortDir)==='asc'?'desc':'asc']) ?>">Text</a></th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php while ($row = $questions->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= (int)$row['class_id'] ?></td>
                    <td><?= htmlspecialchars($row['book_name']) ?></td>
                    <td><?= htmlspecialchars($row['chapter_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['question_type']) ?></td>
                    <td><?= htmlspecialchars($row['topic'] ?? '') ?></td>
                    <td>
                        <?= htmlspecialchars($row['question_text']) ?>
                        <?php if (strcasecmp($row['question_type'], 'mcq') === 0): 
                            $mcqKey = $row['class_id'] . '-' . $row['chapter_id'] . '-' . $row['question_text'];
                            if (isset($mcqsData[$mcqKey])):
                                $mcqData = $mcqsData[$mcqKey];
                        ?>
                            <div class="mcq-options-display">
                                <div><strong>A:</strong> <?= htmlspecialchars($mcqData['option_a'] ?? '') ?></div>
                                <div><strong>B:</strong> <?= htmlspecialchars($mcqData['option_b'] ?? '') ?></div>
                                <div><strong>C:</strong> <?= htmlspecialchars($mcqData['option_c'] ?? '') ?></div>
                                <div><strong>D:</strong> <?= htmlspecialchars($mcqData['option_d'] ?? '') ?></div>
                            </div>
                        <?php endif; endif; ?>
                    </td>
                    <td>
                        <!-- Delete button commented out -->
                         
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this question?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <button type="submit">Delete</button>
                        </form>
                       
                        <button type="button" onclick="document.getElementById('edit-<?= (int)$row['id'] ?>').style.display='table-row'">Edit</button>
                    </td>
                </tr>
                <tr id="edit-<?= (int)$row['id'] ?>" style="display:none; background:#fafafa;">
                    <td colspan="7">
                        <form method="POST" class="row">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <select name="class_id" required id="edit-class-<?= (int)$row['id'] ?>">
                                <?php $clsRes3 = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC"); while ($cc = $clsRes3->fetch_assoc()): ?>
                                    <option value="<?= (int)$cc['class_id'] ?>" <?= ((int)$cc['class_id']===(int)$row['class_id'])?'selected':'' ?>><?= htmlspecialchars($cc['class_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                                                         <?php if ($hasBookName): ?>
                                 <select name="book_id" id="edit-book-<?= (int)$row['id'] ?>">
                                     <option value="">Select book</option>
                                     <?php foreach ($bookOptions as $bk): ?>
                                         <option value="<?= (int)$bk['book_id'] ?>" data-class="<?= (int)$bk['class_id'] ?>" <?= ((int)$bk['book_id']===(int)$row['book_id']) ? 'selected' : '' ?>><?= htmlspecialchars($bk['book_name']) ?> (Class <?= (int)$bk['class_id'] ?>)</option>
                                     <?php endforeach; ?>
                                 </select>
                             <?php else: ?>
                                 <select name="book_id" id="edit-book-<?= (int)$row['id'] ?>">
                                     <option value="">Select book</option>
                                     <?php foreach ($bookOptions as $bk): ?>
                                         <option value="<?= (int)$bk['book_id'] ?>" data-class="<?= (int)$bk['class_id'] ?>" <?= ((int)$bk['book_id']===(int)$row['book_id']) ? 'selected' : '' ?>><?= htmlspecialchars($bk['book_name']) ?> (Class <?= (int)$bk['class_id'] ?>)</option>
                                     <?php endforeach; ?>
                                 </select>
                             <?php endif; ?>
                             <input type="hidden" name="chapter_id" value="<?= (int)$row['chapter_id'] ?>">
                            <select name="type" class="edit-question-type" required>
                                <?php $types = ['mcq','short','long']; foreach ($types as $t): ?>
                                    <option value="<?= $t ?>" <?= (strcasecmp($row['question_type'], $t)===0 ? 'selected' : '') ?>><?= strtoupper($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="topic" value="<?= htmlspecialchars($row['topic'] ?? '') ?>" placeholder="Topic" />
                            <textarea name="text" required><?= htmlspecialchars($row['question_text']) ?></textarea>
                            
                            <?php 
                            // Check if this is an MCQ in the mcqs table
                            $mcqData = null;
                            if (strcasecmp($row['question_type'], 'mcq') === 0) {
                                $mcqKey = $row['class_id'] . '-' . $row['chapter_id'] . '-' . $row['question_text'];
                                if (isset($mcqsData[$mcqKey])) {
                                    $mcqData = $mcqsData[$mcqKey];
                                }
                            }
                            ?>
                            
                            <?php if ($mcqData): ?>
                                <input type="hidden" name="mcq_id" value="<?= (int)$mcqData['mcq_id'] ?>">
                            <?php endif; ?>
                            
                            <div class="mcq-options-edit" style="display: <?= strcasecmp($row['question_type'], 'mcq')===0 ? 'block' : 'none' ?>">
                                <input type="text" name="option_a" placeholder="Option A" value="<?= htmlspecialchars($mcqData['option_a'] ?? '') ?>">
                                <input type="text" name="option_b" placeholder="Option B" value="<?= htmlspecialchars($mcqData['option_b'] ?? '') ?>">
                                <input type="text" name="option_c" placeholder="Option C" value="<?= htmlspecialchars($mcqData['option_c'] ?? '') ?>">
                                <input type="text" name="option_d" placeholder="Option D" value="<?= htmlspecialchars($mcqData['option_d'] ?? '') ?>">
                                <?php 
                                    $coText = trim($mcqData['correct_option'] ?? '');
                                    $selA = (strcasecmp($coText, $mcqData['option_a'] ?? '') === 0) ? 'selected' : '';
                                    $selB = (strcasecmp($coText, $mcqData['option_b'] ?? '') === 0) ? 'selected' : '';
                                    $selC = (strcasecmp($coText, $mcqData['option_c'] ?? '') === 0) ? 'selected' : '';
                                    $selD = (strcasecmp($coText, $mcqData['option_d'] ?? '') === 0) ? 'selected' : '';
                                ?>
                                <select name="correct_option">
                                    <option value="">Select Correct Option</option>
                                    <option value="A" <?= $selA ?>>Option A</option>
                                    <option value="B" <?= $selB ?>>Option B</option>
                                    <option value="C" <?= $selC ?>>Option C</option>
                                    <option value="D" <?= $selD ?>>Option D</option>
                                </select>
                            </div>
                            <button type="submit">Save</button>
                            <button type="button" onclick="document.getElementById('edit-<?= (int)$row['id'] ?>').style.display='none'">Cancel</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Questions Pagination -->
        <?php if ($questionsPerPage === 'all'): ?>
        <div class="pagination" style="margin: 20px 0; text-align: center;">
            <p><strong>Viewing all <?= $questionTotalCount ?> questions</strong></p>
        </div>
        <?php elseif ($questionTotalPages > 1): ?>
        <div class="pagination" style="margin: 20px 0; text-align: center;">
            <p>Showing <?= $questionsOffset + 1 ?>-<?= min($questionsOffset + $questionsPerPage, $questionTotalCount) ?> of <?= $questionTotalCount ?> questions</p>
            <div style="margin: 10px 0;">
                <?php if ($questionsPage > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['questions_page' => $questionsPage - 1])) ?>" class="btn" style="margin: 0 5px;">← Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $questionsPage - 2); $i <= min($questionTotalPages, $questionsPage + 2); $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['questions_page' => $i])) ?>" 
                       class="btn <?= $i == $questionsPage ? 'active' : '' ?>" 
                       style="margin: 0 2px; <?= $i == $questionsPage ? 'background: #007bff; color: white;' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($questionsPage < $questionTotalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['questions_page' => $questionsPage + 1])) ?>" class="btn" style="margin: 0 5px;">Next →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- MCQ Section -->
        <h3>MCQ Questions
        <?php if ($mcqSearch !== ''): ?>
            <span style="font-size: 14px; color: #6c757d; font-weight: normal;">
                - Search results for "<?= htmlspecialchars($mcqSearch) ?>" (<?= $mcqTotalCount ?> found)
            </span>
        <?php endif; ?>
        </h3>
        <form method="GET" class="row" id="mcq-filter-form" style="margin-bottom:8px; background: #f8f9fa; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                <input type="text" name="mcq_search" placeholder="🔍 Search MCQs by ID, class, chapter, question, or options..." value="<?= htmlspecialchars($mcqSearch) ?>" style="flex: 1; padding: 10px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 14px;">
                <select name="mcq_match" style="padding: 10px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 14px;">
                    <option value="contains" <?= $mcqMatch==='contains'?'selected':'' ?>>Contains</option>
                    <option value="exact" <?= $mcqMatch==='exact'?'selected':'' ?>>Exact Match</option>
                </select>
            </div>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <select name="mcq_filter_class_id">
                <option value="0">All classes</option>
                <?php
                $clsRes3 = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
                while ($cc = $clsRes3->fetch_assoc()): ?>
                    <option value="<?= (int)$cc['class_id'] ?>" <?= $mcqFilterClassId===(int)$cc['class_id']?'selected':'' ?>><?= htmlspecialchars($cc['class_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <select name="mcq_filter_book_id" id="mcq_qf_book">
                <option value="0">All books</option>
                <?php foreach ($bookOptions as $bk): ?>
                    <?php if ($mcqFilterClassId > 0 && (int)$bk['class_id'] !== (int)$mcqFilterClassId) continue; ?>
                    <option value="<?= (int)$bk['book_id'] ?>" <?= $mcqFilterBookId===(int)$bk['book_id']?'selected':'' ?>><?= htmlspecialchars($bk['book_name']) ?> (Class <?= (int)$bk['class_id'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <select name="mcq_filter_chapter_id">
                <option value="0">All chapters</option>
                <?php
                // Build chapter list filtered by selected class and book
                $chWhere = [];
                if ($mcqFilterClassId > 0) { $chWhere[] = 'class_id='.(int)$mcqFilterClassId; }
                if ($mcqFilterBookId > 0) {
                    $bnResSel = $conn->query('SELECT book_name FROM book WHERE book_id='.(int)$mcqFilterBookId.' LIMIT 1');
                    if ($bnResSel && ($bnRowSel = $bnResSel->fetch_assoc())) {
                        $bnSafe = $conn->real_escape_string($bnRowSel['book_name']);
                        $chWhere[] = "book_name='$bnSafe'";
                    }
                }
                $chapQuery3 = 'SELECT chapter_id, chapter_name FROM chapter' . (count($chWhere)? (' WHERE '.implode(' AND ', $chWhere)) : '') . ' ORDER BY chapter_id ASC';
                $chapRes3 = $conn->query($chapQuery3);
                while ($ch3 = $chapRes3->fetch_assoc()): ?>
                    <option value="<?= (int)$ch3['chapter_id'] ?>" <?= $mcqFilterChapterId===(int)$ch3['chapter_id']?'selected':'' ?>><?= htmlspecialchars($ch3['chapter_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <select name="mcqs_per_page">
                <option value="10" <?= $mcqsPerPage==10?'selected':'' ?>>10 per page</option>
                <option value="20" <?= $mcqsPerPage==20?'selected':'' ?>>20 per page</option>
                <option value="50" <?= $mcqsPerPage==50?'selected':'' ?>>50 per page</option>
                <option value="all" <?= $mcqsPerPage==='all'?'selected':'' ?>>View All</option>
            </select>
            <button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">Apply Filters</button>
            <?php if ($mcqSearch !== '' || $mcqFilterClassId > 0 || $mcqFilterChapterId > 0 || $mcqFilterBookId > 0): ?>
                <a href="?" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 14px;">Clear Search</a>
            <?php endif; ?>
        </form>

        <table>
            <thead><tr>
                <th>MCQ ID</th>
                <th>Class</th>
                <th>Book</th>
                <th>Chapter</th>
                <th>Topic</th>
                <th>Question</th>
                <th>Options</th>
                <th>Correct Answer</th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php 
            // Use the already fetched MCQs data
            $mcqs->data_seek(0); // Reset the result pointer to the beginning
            while ($mcq = $mcqs->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$mcq['mcq_id'] ?></td>
                    <td><?= (int)$mcq['class_id'] ?></td>
                    <td><?= htmlspecialchars($mcq['book_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($mcq['chapter_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($mcq['topic'] ?? '') ?></td>
                    <td><?= htmlspecialchars($mcq['question']) ?></td>
                    <td>
                        <div class="mcq-options-display">
                            <div><strong>A:</strong> <?= htmlspecialchars($mcq['option_a'] ?? '') ?></div>
                            <div><strong>B:</strong> <?= htmlspecialchars($mcq['option_b'] ?? '') ?></div>
                            <div><strong>C:</strong> <?= htmlspecialchars($mcq['option_c'] ?? '') ?></div>
                            <div><strong>D:</strong> <?= htmlspecialchars($mcq['option_d'] ?? '') ?></div>
                        </div>
                    </td>
                    <td style="text-align: center; font-weight: bold; color: #28a745;"><?= htmlspecialchars($mcq['correct_option'] ?? 'Not Set') ?></td>
                    <td>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this MCQ?');">
                            <input type="hidden" name="action" value="delete_mcq">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="mcq_id" value="<?= (int)$mcq['mcq_id'] ?>">
                            <button type="submit">Delete</button>
                        </form>
                        <button type="button" onclick="document.getElementById('edit-mcq-<?= (int)$mcq['mcq_id'] ?>').style.display='table-row'">Edit</button>
                    </td>
                </tr>
                <tr id="edit-mcq-<?= (int)$mcq['mcq_id'] ?>" style="display:none; background:#fafafa;">
                    <td colspan="9">
                        <form method="POST" class="row">
                            <input type="hidden" name="action" value="update_mcq">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="mcq_id" value="<?= (int)$mcq['mcq_id'] ?>">
                            <select name="class_id" required>
                                <?php $clsRes4 = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC"); while ($cc = $clsRes4->fetch_assoc()): ?>
                                    <option value="<?= (int)$cc['class_id'] ?>" <?= ((int)$cc['class_id']===(int)$mcq['class_id'])?'selected':'' ?>><?= htmlspecialchars($cc['class_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <select name="book_id">
                                <option value="">Select book</option>
                                <?php foreach ($bookOptions as $bk): ?>
                                    <option value="<?= (int)$bk['book_id'] ?>" data-class="<?= (int)$bk['class_id'] ?>" <?= ((int)$bk['book_id']===(int)$mcq['book_id'])?'selected':'' ?>><?= htmlspecialchars($bk['book_name']) ?> (Class <?= (int)$bk['class_id'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <select name="chapter_id" required>
                                <option value="">Select chapter</option>
                                <?php $chapRes4 = $conn->query("SELECT chapter_id, chapter_name, class_id FROM chapter ORDER BY chapter_id ASC"); while ($ch4 = $chapRes4->fetch_assoc()): ?>
                                    <option value="<?= (int)$ch4['chapter_id'] ?>" data-class="<?= (int)$ch4['class_id'] ?>" <?= ((int)$ch4['chapter_id']===(int)$mcq['chapter_id'])?'selected':'' ?>><?= htmlspecialchars($ch4['chapter_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <input type="text" name="topic" value="<?= htmlspecialchars($mcq['topic'] ?? '') ?>" placeholder="Topic" />
                            <textarea name="question" required><?= htmlspecialchars($mcq['question']) ?></textarea>
                            <input type="text" name="option_a" placeholder="Option A" value="<?= htmlspecialchars($mcq['option_a'] ?? '') ?>">
                            <input type="text" name="option_b" placeholder="Option B" value="<?= htmlspecialchars($mcq['option_b'] ?? '') ?>">
                            <input type="text" name="option_c" placeholder="Option C" value="<?= htmlspecialchars($mcq['option_c'] ?? '') ?>">
                            <input type="text" name="option_d" placeholder="Option D" value="<?= htmlspecialchars($mcq['option_d'] ?? '') ?>">
                            <?php 
                                $coText = trim($mcq['correct_option'] ?? '');
                                $selA = (strcasecmp($coText, $mcq['option_a'] ?? '') === 0) ? 'selected' : '';
                                $selB = (strcasecmp($coText, $mcq['option_b'] ?? '') === 0) ? 'selected' : '';
                                $selC = (strcasecmp($coText, $mcq['option_c'] ?? '') === 0) ? 'selected' : '';
                                $selD = (strcasecmp($coText, $mcq['option_d'] ?? '') === 0) ? 'selected' : '';
                            ?>
                            <select name="correct_option">
                                <option value="">Select Correct Option</option>
                                <option value="A" <?= $selA ?>>Option A</option>
                                <option value="B" <?= $selB ?>>Option B</option>
                                <option value="C" <?= $selC ?>>Option C</option>
                                <option value="D" <?= $selD ?>>Option D</option>
                            </select>
                            <button type="submit">Save</button>
                            <button type="button" onclick="document.getElementById('edit-mcq-<?= (int)$mcq['mcq_id'] ?>').style.display='none'">Cancel</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

        <!-- MCQ Pagination -->
        <?php if ($mcqsPerPage === 'all'): ?>
        <div class="pagination" style="margin: 20px 0; text-align: center;">
            <p><strong>Viewing all <?= $mcqTotalCount ?> MCQs</strong></p>
        </div>
        <?php elseif ($mcqTotalPages > 1): ?>
        <div class="pagination" style="margin: 20px 0; text-align: center;">
            <p>Showing <?= $mcqsOffset + 1 ?>-<?= min($mcqsOffset + $mcqsPerPage, $mcqTotalCount) ?> of <?= $mcqTotalCount ?> MCQs</p>
            <div style="margin: 10px 0;">
                <?php if ($mcqsPage > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['mcqs_page' => $mcqsPage - 1])) ?>" class="btn" style="margin: 0 5px;">← Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $mcqsPage - 2); $i <= min($mcqTotalPages, $mcqsPage + 2); $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['mcqs_page' => $i])) ?>" 
                       class="btn <?= $i == $mcqsPage ? 'active' : '' ?>" 
                       style="margin: 0 2px; <?= $i == $mcqsPage ? 'background: #007bff; color: white;' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($mcqsPage < $mcqTotalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['mcqs_page' => $mcqsPage + 1])) ?>" class="btn" style="margin: 0 5px;">Next →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>



