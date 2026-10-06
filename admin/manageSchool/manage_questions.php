<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
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
            $correctOptionText = in_array($correctOptionLetter, ['A', 'B', 'C', 'D'], true) ? $correctOptionLetter : '';
            if ($classId > 0 && $chapterId > 0 && $text !== '') {
                $bookEsc = $conn->real_escape_string($bookName);
                if ($type === 'mcq' && $optionA !== '' && $optionB !== '' && $optionC !== '' && $optionD !== '' && $correctOptionText !== '') {
                    $mcqStmt = $conn->prepare("INSERT INTO mcqs (class_id, book_id, chapter_id, topic, question, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($mcqStmt) {
                        $mcqStmt->bind_param('iiisssssss', $classId, $bookId, $chapterId, $topic, $text, $optionA, $optionB, $optionC, $optionD, $correctOptionText);
                        if ($mcqStmt->execute()) {
                            $inserted++;
                        }
                        $mcqStmt->close();
                    }
                } else {
                    try {
                        if ($hasBookName) {
                            if ($bookId > 0) {
                                $bnStmt = $conn->prepare("SELECT book_name FROM book WHERE book_id=? LIMIT 1");
                                if ($bnStmt) {
                                    $bnStmt->bind_param('i', $bookId);
                                    $bnStmt->execute();
                                    $bnRes = $bnStmt->get_result();
                                    if ($bnRes && ($bnRow = $bnRes->fetch_assoc())) { $bookEsc = $bnRow['book_name']; }
                                    $bnStmt->close();
                                }
                            }
                            if ($bookEsc === '') {
                                $bnStmt = $conn->prepare("SELECT book_name FROM chapter WHERE chapter_id=? LIMIT 1");
                                if ($bnStmt) {
                                    $bnStmt->bind_param('i', $chapterId);
                                    $bnStmt->execute();
                                    $bnRes = $bnStmt->get_result();
                                    if ($bnRes && ($bnRow = $bnRes->fetch_assoc())) { $bookEsc = $bnRow['book_name']; }
                                    $bnStmt->close();
                                }
                            }
                            $qStmt = $conn->prepare("INSERT INTO questions (class_id, book_name, book_id, chapter_id, $typeCol, $textCol, topic) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            if ($qStmt) {
                                $qStmt->bind_param('isiiiss', $classId, $bookEsc, $bookId, $chapterId, $type, $text, $topic);
                                if ($qStmt->execute()) {
                                    $inserted++;
                                }
                                $qStmt->close();
                            }
                        } else {
                            $qStmt = $conn->prepare("INSERT INTO questions (class_id, book_id, chapter_id, $typeCol, $textCol, topic) VALUES (?, ?, ?, ?, ?, ?)");
                            if ($qStmt) {
                                $qStmt->bind_param('iiisss', $classId, $bookId, $chapterId, $type, $text, $topic);
                                if ($qStmt->execute()) {
                                    $inserted++;
                                }
                                $qStmt->close();
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
        $message = 'No questions were added. Check the selected location and required fields.';
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
        $correctOptionLetter = strtoupper(trim($_POST['correct_option'] ?? ''));
        $correctOptionText = in_array($correctOptionLetter, ['A', 'B', 'C', 'D'], true) ? $correctOptionLetter : '';
        $mcqId = intval($_POST['mcq_id'] ?? 0);
        
        if ($id > 0 && $classId > 0 && $chapterId > 0 && $text !== '') {
            // If type is mcq and we have options
            if ($type === 'mcq' && $optionA !== '' && $optionB !== '' && $optionC !== '' && $optionD !== '' && $correctOptionText !== '') {
                // If mcq_id exists, update the mcq record
                if ($mcqId > 0) {
                    $updateStmt = $conn->prepare("UPDATE mcqs SET class_id=?, book_id=?, chapter_id=?, topic=?, question=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=? WHERE mcq_id=?");
                    if ($updateStmt) {
                        $updateStmt->bind_param('iiisssssssi', $classId, $bookId, $chapterId, $topic, $text, $optionA, $optionB, $optionC, $optionD, $correctOptionText, $mcqId);
                        if ($updateStmt->execute()) {
                            $updateStmt->close();
                            header('Location: manage_questions.php?msg=updated');
                            exit;
                        }
                        $updateStmt->close();
                    }
                } else {
                    // Insert new MCQ record
                    $insertStmt = $conn->prepare("INSERT INTO mcqs (class_id, book_id, chapter_id, topic, question, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($insertStmt) {
                        $insertStmt->bind_param('iiisssssss', $classId, $bookId, $chapterId, $topic, $text, $optionA, $optionB, $optionC, $optionD, $correctOptionText);
                        if ($insertStmt->execute()) {
                            // Delete the original question if it exists
                            $delStmt = $conn->prepare("DELETE FROM questions WHERE id=?");
                            if ($delStmt) {
                                $delStmt->bind_param('i', $id);
                                $delStmt->execute();
                                $delStmt->close();
                            }
                            $insertStmt->close();
                            header('Location: manage_questions.php?msg=updated');
                            exit;
                        }
                        $insertStmt->close();
                    }
                }
            } else {
                // Regular question update
                if ($hasBookName) {
                    $bookNameUpd = '';
                    if ($bookId > 0) {
                        $bnStmt = $conn->prepare("SELECT book_name FROM book WHERE book_id=? LIMIT 1");
                        if ($bnStmt) {
                            $bnStmt->bind_param('i', $bookId);
                            $bnStmt->execute();
                            $bnRes = $bnStmt->get_result();
                            if ($bnRes && ($bnRow = $bnRes->fetch_assoc())) { $bookNameUpd = $bnRow['book_name']; }
                            $bnStmt->close();
                        }
                    }
                    if ($bookNameUpd === '') {
                        $bnStmt = $conn->prepare("SELECT book_name FROM chapter WHERE chapter_id=? LIMIT 1");
                        if ($bnStmt) {
                            $bnStmt->bind_param('i', $chapterId);
                            $bnStmt->execute();
                            $bnRes = $bnStmt->get_result();
                            if ($bnRes && ($bnRow = $bnRes->fetch_assoc())) { $bookNameUpd = $bnRow['book_name']; }
                            $bnStmt->close();
                        }
                    }
                    $updateStmt = $conn->prepare("UPDATE questions SET class_id=?, book_name=?, book_id=?, chapter_id=?, $typeCol=?, $textCol=?, topic=? WHERE id=?");
                    if ($updateStmt) {
                        $updateStmt->bind_param('isiisssi', $classId, $bookNameUpd, $bookId, $chapterId, $type, $text, $topic, $id);
                        if ($updateStmt->execute()) {
                            $updateStmt->close();
                            header('Location: manage_questions.php?msg=updated');
                            exit;
                        }
                        $updateStmt->close();
                    }
                } else {
                    $updateStmt = $conn->prepare("UPDATE questions SET class_id=?, book_id=?, chapter_id=?, $typeCol=?, $textCol=?, topic=? WHERE id=?");
                    if ($updateStmt) {
                        $updateStmt->bind_param('iiisssi', $classId, $bookId, $chapterId, $type, $text, $topic, $id);
                        if ($updateStmt->execute()) {
                            $updateStmt->close();
                            header('Location: manage_questions.php?msg=updated');
                            exit;
                        }
                        $updateStmt->close();
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
                $bname = $rowQ['book_name'] ?? '';
                $qtype = $rowQ['qtype'];
                $qtext = $rowQ['qtext'];
                
                $archStmt = $conn->prepare("INSERT INTO deleted_questions (question_id, class_id, book_name, chapter_id, question_type, question_text) VALUES (?, ?, ?, ?, ?, ?)");
                if ($archStmt) {
                    $archStmt->bind_param('iisiss', $qid, $cid, $bname, $chap, $qtype, $qtext);
                    $archStmt->execute();
                    $archStmt->close();
                }
            }
            // then delete original
            $delStmt = $conn->prepare("DELETE FROM questions WHERE id=?");
            if ($delStmt) {
                $delStmt->bind_param('i', $id);
                if ($delStmt->execute()) {
                    $delStmt->close();
                    header('Location: manage_questions.php?msg=deleted');
                    exit;
                }
                $delStmt->close();
            }
        }
    } elseif ($action === 'delete_mcq') {
        $mcqId = intval($_POST['mcq_id'] ?? 0);
        if ($mcqId > 0) {
            $delStmt = $conn->prepare("DELETE FROM mcqs WHERE mcq_id=?");
            if ($delStmt) {
                $delStmt->bind_param('i', $mcqId);
                if ($delStmt->execute()) {
                    $delStmt->close();
                    header('Location: manage_questions.php?msg=mcq_deleted');
                    exit;
                }
                $delStmt->close();
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
        $explanation = trim($_POST['explanation'] ?? '');
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
            $updateStmt = $conn->prepare("UPDATE mcqs SET class_id=?, book_id=?, chapter_id=?, topic=?, question=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=?, explanation=? WHERE mcq_id=?");
            if ($updateStmt) {
                        $updateStmt->bind_param('iiissssssssi', $classId, $bookId, $chapterId, $topic, $question, $optionA, $optionB, $optionC, $optionD, $correctOptionText, $explanation, $mcqId);
                if ($updateStmt->execute()) {
                    $updateStmt->close();
                    header('Location: manage_questions.php?msg=mcq_updated');
                    exit;
                }
                $updateStmt->close();
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

$classOptions = [];
$classResult = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
if ($classResult) {
    while ($classRow = $classResult->fetch_assoc()) { $classOptions[] = $classRow; }
}
$chapterOptions = [];
$chapterResult = $conn->query("SELECT chapter_id, chapter_name, chapter_no, class_id, book_name FROM chapter ORDER BY chapter_no ASC, chapter_id ASC");
if ($chapterResult) {
    while ($chapterRow = $chapterResult->fetch_assoc()) { $chapterOptions[] = $chapterRow; }
}
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
$mcqs = $conn->query("SELECT m.mcq_id, m.class_id, m.book_id, m.chapter_id, m.topic, m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option, m.explanation, c.chapter_name, b.book_name FROM mcqs m LEFT JOIN chapter c ON c.chapter_id = m.chapter_id LEFT JOIN book b ON b.book_id = m.book_id $mcqWhere ORDER BY m.mcq_id DESC" . ($mcqsLimit ? " $mcqsLimit" : ""));

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
include_once __DIR__ . '/../header.php';
?>
<main class="school-workspace" id="main-content">
    <a class="school-breadcrumb" href="../dashboard.php">â† Back to dashboard</a>

    <section class="school-hero" aria-labelledby="questions-page-title">
        <div>
            <span class="school-kicker">Question bank studio</span>
            <h1 id="questions-page-title">Manage questions</h1>
            <p>Build, review, and update questions with the class â†’ book â†’ chapter path always visible.</p>
        </div>
        <div class="school-hero-mark" aria-hidden="true">Q?</div>
    </section>

    <?php if ($message): ?><div class="school-alert success" role="status">âœ“ <span><?= htmlspecialchars($message) ?></span></div><?php endif; ?>

    <div class="school-stats" aria-label="Question bank summary">
        <div class="school-stat"><span class="school-stat-value"><?= (int) $questionTotalCount ?></span><span class="school-stat-label">Text questions</span></div>
        <div class="school-stat"><span class="school-stat-value"><?= (int) $mcqTotalCount ?></span><span class="school-stat-label">MCQs</span></div>
        <div class="school-stat"><span class="school-stat-value"><?= count($chapterOptions) ?></span><span class="school-stat-label">Chapters to use</span></div>
    </div>

    <nav class="school-tabs" aria-label="Question bank sections" role="tablist">
        <button class="school-tab" type="button" role="tab" aria-selected="true" aria-controls="questions-panel" data-tab-target="questions-panel">Text questions <span>(<?= (int) $questionTotalCount ?>)</span></button>
        <button class="school-tab" type="button" role="tab" aria-selected="false" aria-controls="mcqs-panel" data-tab-target="mcqs-panel">MCQs <span>(<?= (int) $mcqTotalCount ?>)</span></button>
    </nav>

    <section class="school-tab-panel" id="questions-panel" role="tabpanel">
        <div class="school-layout">
            <section class="school-panel" aria-labelledby="question-builder-heading">
                <div class="school-panel-header">
                    <div><h2 id="question-builder-heading">Add questions</h2><p>Set the location once, then add several questions before saving.</p></div>
                    <span class="question-type-pill">Batch ready</span>
                </div>
                <div class="school-panel-body">
                    <form method="POST" id="create-question-form">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="school-grid-3">
                            <div class="school-field"><label for="cq-class">Class</label><select class="school-select" name="class_id" id="cq-class" required><option value="">Choose class</option><?php foreach ($classOptions as $class): ?><option value="<?= (int) $class['class_id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?></select></div>
                            <div class="school-field"><label for="cq-book">Book</label><select class="school-select" name="book_id" id="cq-book" required disabled><option value="">Choose class first</option><?php foreach ($bookOptions as $book): ?><option value="<?= (int) $book['book_id'] ?>" data-class-id="<?= (int) $book['class_id'] ?>" data-book-name="<?= htmlspecialchars($book['book_name']) ?>"><?= htmlspecialchars($book['book_name']) ?></option><?php endforeach; ?></select></div>
                            <div class="school-field"><label for="cq-chapter">Chapter</label><select class="school-select" name="chapter_id" id="cq-chapter" required disabled><option value="">Choose book first</option><?php foreach ($chapterOptions as $chapter): ?><option value="<?= (int) $chapter['chapter_id'] ?>" data-class-id="<?= (int) $chapter['class_id'] ?>" data-book-name="<?= htmlspecialchars($chapter['book_name']) ?>"><?= htmlspecialchars($chapter['chapter_name']) ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="school-grid-2" style="margin-top:12px">
                            <div class="school-field"><label for="question-type">Question type</label><select class="school-select" name="type" id="question-type" required><option value="short" selected>Short answer</option><option value="long">Long answer</option><option value="mcq">MCQ</option></select></div>
                            <div class="school-field"><label>Workflow</label><div class="school-hint" style="margin-top:0">Use â€œAdd questionâ€ to keep building this batch. Save once when the set is complete.</div></div>
                        </div>
                        <div class="question-builder" id="question-sets">
                            <div class="question-set" data-question-set>
                                <div class="question-set-head"><strong class="question-set-title"><span class="question-set-number">Question 01</span></strong><button class="school-button-quiet" type="button" data-remove-question hidden>Remove</button></div>
                                <div class="school-field"><label>Question text</label><textarea class="school-textarea" name="text[]" placeholder="Write the question clearly" required></textarea></div>
                                <div class="school-grid-2"><div class="school-field"><label>Topic</label><input class="school-input" type="text" name="topic[]" placeholder="e.g. Algebra" required></div><div class="school-field"><label>Type reminder</label><div class="school-hint" style="margin-top:0">All cards use the selected question type.</div></div></div>
                                <div class="mcq_options" hidden>
                                    <div class="school-grid-2"><div class="school-field"><label>Option A</label><input class="school-input" type="text" name="option_a[]" placeholder="Option A"></div><div class="school-field"><label>Option B</label><input class="school-input" type="text" name="option_b[]" placeholder="Option B"></div><div class="school-field"><label>Option C</label><input class="school-input" type="text" name="option_c[]" placeholder="Option C"></div><div class="school-field"><label>Option D</label><input class="school-input" type="text" name="option_d[]" placeholder="Option D"></div></div>
                                    <div class="correct-row"><label class="school-filter-label" for="correct-option-0">Correct answer</label><select class="school-select" name="correct_option[]" id="correct-option-0"><option value="">Select option</option><option value="A">Option A</option><option value="B">Option B</option><option value="C">Option C</option><option value="D">Option D</option></select></div>
                                </div>
                            </div>
                        </div>
                        <div class="school-actions"><button class="school-button-secondary" type="button" id="add-next-question">ï¼‹ Add question</button><button class="school-button" type="submit">Save all questions</button></div>
                    </form>
                </div>
            </section>
            <aside class="school-panel">
                <div class="school-panel-header"><div><h2>Fast review</h2><p>Use filters below to narrow the bank before editing.</p></div></div>
                <div class="school-panel-body">
                    <div class="school-sidebar-note"><strong>Deletion is disabled</strong>Question delete buttons are commented out on this page. Existing records remain safe while you clean up wording or move content.</div>
                    <div class="school-hint">â‘  Filter by class, book, chapter, or type.</div>
                    <div class="school-hint">â‘¡ Open Edit to update the question in context.</div>
                    <div class="school-hint">â‘¢ MCQs have their own workspace tab so long option sets stay readable.</div>
                    <a class="school-button-quiet" style="margin-top:14px" href="../deleted_questions.php">View archived questions</a>
                </div>
            </aside>
        </div>

        <section class="school-panel" style="margin-top:22px" aria-labelledby="latest-questions-heading">
            <div class="school-panel-header"><div><h2 id="latest-questions-heading">Text question library</h2><p><?= (int) $questionTotalCount ?> result<?= $questionTotalCount === 1 ? '' : 's' ?> Â· Latest content is listed first.</p></div></div>
            <form method="GET" class="school-filter" id="question-filter-form" aria-label="Filter text questions">
                <div class="school-filter-row"><div><label class="school-filter-label" for="question-search">Search</label><input id="question-search" type="search" name="question_search" placeholder="ID, chapter, topic, or question text" value="<?= htmlspecialchars($questionSearch) ?>"></div><div><label class="school-filter-label" for="question-match">Match</label><select id="question-match" name="question_match"><option value="contains" <?= $questionMatch === 'contains' ? 'selected' : '' ?>>Contains</option><option value="exact" <?= $questionMatch === 'exact' ? 'selected' : '' ?>>Exact</option></select></div><div><label class="school-filter-label" for="question-class-filter">Class</label><select id="question-class-filter" name="question_filter_class_id"><option value="0">All classes</option><?php foreach ($classOptions as $class): ?><option value="<?= (int) $class['class_id'] ?>" <?= $questionFilterClassId === (int) $class['class_id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?></select></div></div>
                <div class="school-filter-row"><div><label class="school-filter-label" for="question-book-filter">Book</label><select id="question-book-filter" name="question_filter_book_id"><option value="0">All books</option><?php foreach ($bookOptions as $book): ?><option value="<?= (int) $book['book_id'] ?>" data-class-id="<?= (int) $book['class_id'] ?>" <?= $questionFilterBookId === (int) $book['book_id'] ? 'selected' : '' ?>><?= htmlspecialchars($book['book_name']) ?></option><?php endforeach; ?></select></div><div><label class="school-filter-label" for="question-chapter-filter">Chapter</label><select id="question-chapter-filter" name="question_filter_chapter_id"><option value="0">All chapters</option><?php foreach ($chapterOptions as $chapter): if ($questionFilterClassId > 0 && $questionFilterClassId !== (int) $chapter['class_id']) continue; ?><option value="<?= (int) $chapter['chapter_id'] ?>" data-class-id="<?= (int) $chapter['class_id'] ?>" data-book-name="<?= htmlspecialchars($chapter['book_name']) ?>" <?= $questionFilterChapterId === (int) $chapter['chapter_id'] ? 'selected' : '' ?>><?= htmlspecialchars($chapter['chapter_name']) ?></option><?php endforeach; ?></select></div><div><label class="school-filter-label" for="question-type-filter">Type</label><select id="question-type-filter" name="question_type_filter"><option value="">All types</option><option value="short" <?= $questionTypeFilter === 'short' ? 'selected' : '' ?>>Short</option><option value="long" <?= $questionTypeFilter === 'long' ? 'selected' : '' ?>>Long</option></select></div><div class="school-filter-actions" style="align-items:end"><button class="school-button" type="submit">Apply filters</button><a class="school-button-quiet" href="manage_questions.php">Reset</a></div></div>
            </form>
            <div class="school-question-list">
                <?php if (!$questions || $questionTotalCount === 0): ?><div class="school-empty">No text questions match these filters yet.</div><?php endif; ?>
                <?php while ($questions && ($row = $questions->fetch_assoc())): ?>
                    <?php $mcqData = null; if (strcasecmp($row['question_type'], 'mcq') === 0) { $mcqKey = $row['class_id'] . '-' . $row['chapter_id'] . '-' . $row['question_text']; $mcqData = $mcqsData[$mcqKey] ?? null; } ?>
                    <article class="question-card">
                        <div class="question-card-id">#<?= (int) $row['id'] ?></div>
                        <div><h3><?= htmlspecialchars($row['question_text']) ?></h3><p><?= $row['topic'] ? htmlspecialchars($row['topic']) : 'No topic added yet.' ?></p><div class="question-card-meta"><span><?= htmlspecialchars(strtoupper($row['question_type'])) ?></span><span><?= htmlspecialchars($row['book_name']) ?></span><span><?= htmlspecialchars($row['chapter_name'] ?? 'Chapter') ?></span></div><?php if ($mcqData): ?><div class="mcq-options-display"><div><strong>A</strong> <?= htmlspecialchars($mcqData['option_a'] ?? '') ?></div><div><strong>B</strong> <?= htmlspecialchars($mcqData['option_b'] ?? '') ?></div><div><strong>C</strong> <?= htmlspecialchars($mcqData['option_c'] ?? '') ?></div><div><strong>D</strong> <?= htmlspecialchars($mcqData['option_d'] ?? '') ?></div></div><?php endif; ?></div>
                        <div class="question-card-actions"><button class="school-button-quiet" type="button" data-edit-question="<?= (int) $row['id'] ?>">Edit</button><!-- <button type="submit" class="school-button-quiet">Delete</button> --><span class="school-disabled-action" title="Delete is disabled">Delete disabled</span></div>
                        <div class="edit-row" id="edit-question-<?= (int) $row['id'] ?>">
                            <form method="POST" class="edit-form"><input type="hidden" name="action" value="update"><input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <div class="school-grid-3"><div class="school-field"><label>Class</label><select class="school-select edit-class" name="class_id" required><?php foreach ($classOptions as $class): ?><option value="<?= (int) $class['class_id'] ?>" <?= (int) $class['class_id'] === (int) $row['class_id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?></select></div><div class="school-field"><label>Book</label><select class="school-select edit-book" name="book_id" required><?php foreach ($bookOptions as $book): ?><option value="<?= (int) $book['book_id'] ?>" data-class-id="<?= (int) $book['class_id'] ?>" data-book-name="<?= htmlspecialchars($book['book_name']) ?>" <?= (int) $book['book_id'] === (int) $row['book_id'] ? 'selected' : '' ?>><?= htmlspecialchars($book['book_name']) ?></option><?php endforeach; ?></select></div><div class="school-field"><label>Chapter</label><select class="school-select edit-chapter" name="chapter_id" required><?php foreach ($chapterOptions as $chapter): ?><option value="<?= (int) $chapter['chapter_id'] ?>" data-class-id="<?= (int) $chapter['class_id'] ?>" data-book-name="<?= htmlspecialchars($chapter['book_name']) ?>" <?= (int) $chapter['chapter_id'] === (int) $row['chapter_id'] ? 'selected' : '' ?>><?= htmlspecialchars($chapter['chapter_name']) ?></option><?php endforeach; ?></select></div></div>
                                <div class="school-grid-2"><div class="school-field"><label>Type</label><select class="school-select edit-question-type" name="type" required><?php foreach (['short','long','mcq'] as $type): ?><option value="<?= $type ?>" <?= strcasecmp($row['question_type'], $type) === 0 ? 'selected' : '' ?>><?= strtoupper($type) ?></option><?php endforeach; ?></select></div><div class="school-field"><label>Topic</label><input class="school-input" type="text" name="topic" value="<?= htmlspecialchars($row['topic'] ?? '') ?>"></div></div>
                                <div class="school-field"><label>Question text</label><textarea class="school-textarea" name="text" required><?= htmlspecialchars($row['question_text']) ?></textarea></div>
                                <?php if ($mcqData): ?><input type="hidden" name="mcq_id" value="<?= (int) $mcqData['mcq_id'] ?>"><?php endif; ?>
                                <?php if ($mcqData): ?><div class="mcq-options-edit"><div class="school-grid-2"><div class="school-field"><label>Option A</label><input class="school-input" type="text" name="option_a" value="<?= htmlspecialchars($mcqData['option_a'] ?? '') ?>"></div><div class="school-field"><label>Option B</label><input class="school-input" type="text" name="option_b" value="<?= htmlspecialchars($mcqData['option_b'] ?? '') ?>"></div><div class="school-field"><label>Option C</label><input class="school-input" type="text" name="option_c" value="<?= htmlspecialchars($mcqData['option_c'] ?? '') ?>"></div><div class="school-field"><label>Option D</label><input class="school-input" type="text" name="option_d" value="<?= htmlspecialchars($mcqData['option_d'] ?? '') ?>"></div></div><div class="school-field" style="margin-top:10px"><label>Correct option</label><select class="school-select" name="correct_option"><option value="">Select option</option><?php $coText = trim($mcqData['correct_option'] ?? ''); foreach (['A','B','C','D'] as $letter): $optionKey = 'option_' . strtolower($letter); ?><option value="<?= $letter ?>" <?= $coText === $letter || strcasecmp($coText, $mcqData[$optionKey] ?? '') === 0 ? 'selected' : '' ?>>Option <?= $letter ?></option><?php endforeach; ?></select></div></div><?php endif; ?>
                                <div class="form-actions"><button class="school-button" type="submit">Save changes</button><button class="school-button-quiet" type="button" data-close-question="<?= (int) $row['id'] ?>">Cancel</button></div>
                            </form>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
            <?php if ($questionsPerPage === 'all'): ?><div class="school-pagination"><span>Viewing all <?= (int) $questionTotalCount ?> questions</span></div><?php elseif ($questionTotalPages > 1): ?><div class="school-pagination"><span>Showing <?= $questionsOffset + 1 ?>â€“<?= min($questionsOffset + $questionsPerPage, $questionTotalCount) ?> of <?= (int) $questionTotalCount ?></span><div class="school-pagination-links"><?php if ($questionsPage > 1): ?><a class="school-button-quiet" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['questions_page' => $questionsPage - 1]))) ?>">â† Prev</a><?php endif; ?><?php for ($i = max(1, $questionsPage - 2); $i <= min($questionTotalPages, $questionsPage + 2); $i++): ?><a class="school-button-quiet <?= $i === $questionsPage ? 'active' : '' ?>" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['questions_page' => $i]))) ?>"><?= $i ?></a><?php endfor; ?><?php if ($questionsPage < $questionTotalPages): ?><a class="school-button-quiet" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['questions_page' => $questionsPage + 1]))) ?>">Next â†’</a><?php endif; ?></div></div><?php endif; ?>
        </section>
    </section>

    <section class="school-tab-panel" id="mcqs-panel" role="tabpanel" hidden>
        <section class="school-panel" aria-labelledby="mcq-library-heading">
            <div class="school-panel-header"><div><h2 id="mcq-library-heading">MCQ library</h2><p>Keep options and correct answers visible while reviewing each item.</p></div><span class="question-type-pill">MCQ</span></div>
            <form method="GET" class="school-filter" id="mcq-filter-form" aria-label="Filter MCQs">
                <div class="school-filter-row"><div><label class="school-filter-label" for="mcq-search">Search</label><input id="mcq-search" type="search" name="mcq_search" placeholder="Question, option, topic, or ID" value="<?= htmlspecialchars($mcqSearch) ?>"></div><div><label class="school-filter-label" for="mcq-match">Match</label><select id="mcq-match" name="mcq_match"><option value="contains" <?= $mcqMatch === 'contains' ? 'selected' : '' ?>>Contains</option><option value="exact" <?= $mcqMatch === 'exact' ? 'selected' : '' ?>>Exact</option></select></div><div><label class="school-filter-label" for="mcq-class-filter">Class</label><select id="mcq-class-filter" name="mcq_filter_class_id"><option value="0">All classes</option><?php foreach ($classOptions as $class): ?><option value="<?= (int) $class['class_id'] ?>" <?= $mcqFilterClassId === (int) $class['class_id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?></select></div></div>
                <div class="school-filter-row"><div><label class="school-filter-label" for="mcq-book-filter">Book</label><select id="mcq-book-filter" name="mcq_filter_book_id"><option value="0">All books</option><?php foreach ($bookOptions as $book): ?><option value="<?= (int) $book['book_id'] ?>" data-class-id="<?= (int) $book['class_id'] ?>" <?= $mcqFilterBookId === (int) $book['book_id'] ? 'selected' : '' ?>><?= htmlspecialchars($book['book_name']) ?></option><?php endforeach; ?></select></div><div><label class="school-filter-label" for="mcq-chapter-filter">Chapter</label><select class="school-select" id="mcq-chapter-filter" name="mcq_filter_chapter_id"><option value="0">All chapters</option><?php foreach ($chapterOptions as $chapter): if ($mcqFilterClassId > 0 && $mcqFilterClassId !== (int) $chapter['class_id']) continue; ?><option value="<?= (int) $chapter['chapter_id'] ?>" data-class-id="<?= (int) $chapter['class_id'] ?>" data-book-name="<?= htmlspecialchars($chapter['book_name']) ?>" <?= $mcqFilterChapterId === (int) $chapter['chapter_id'] ? 'selected' : '' ?>><?= htmlspecialchars($chapter['chapter_name']) ?></option><?php endforeach; ?></select></div><div><label class="school-filter-label" for="mcqs-per-page">Per page</label><select id="mcqs-per-page" name="mcqs_per_page"><option value="10" <?= $mcqsPerPage === 10 ? 'selected' : '' ?>>10</option><option value="20" <?= $mcqsPerPage === 20 ? 'selected' : '' ?>>20</option><option value="50" <?= $mcqsPerPage === 50 ? 'selected' : '' ?>>50</option><option value="all" <?= $mcqsPerPage === 'all' ? 'selected' : '' ?>>All</option></select></div><div class="school-filter-actions" style="align-items:end"><button class="school-button" type="submit">Apply filters</button><a class="school-button-quiet" href="manage_questions.php">Reset</a></div></div>
            </form>
            <div class="school-question-list">
                <?php if (!$mcqs || $mcqTotalCount === 0): ?><div class="school-empty">No MCQs match these filters yet.</div><?php endif; ?>
                <?php if ($mcqs) { $mcqs->data_seek(0); while ($mcq = $mcqs->fetch_assoc()): ?>
                    <article class="question-card">
                        <div class="question-card-id">#<?= (int) $mcq['mcq_id'] ?></div>
                        <div><h3><?= htmlspecialchars($mcq['question']) ?></h3><p><?= $mcq['topic'] ? htmlspecialchars($mcq['topic']) : 'No topic added yet.' ?></p><div class="question-card-meta"><span><?= htmlspecialchars($mcq['book_name'] ?? 'Book') ?></span><span><?= htmlspecialchars($mcq['chapter_name'] ?? 'Chapter') ?></span><span>Correct: <?= htmlspecialchars($mcq['correct_option'] ?? 'Not set') ?></span></div><div class="mcq-options-display"><div><strong>A</strong> <?= htmlspecialchars($mcq['option_a'] ?? '') ?></div><div><strong>B</strong> <?= htmlspecialchars($mcq['option_b'] ?? '') ?></div><div><strong>C</strong> <?= htmlspecialchars($mcq['option_c'] ?? '') ?></div><div><strong>D</strong> <?= htmlspecialchars($mcq['option_d'] ?? '') ?></div><?php if (!empty($mcq['explanation'])): ?><div style="grid-column:1/-1"><strong>Why</strong> <?= htmlspecialchars($mcq['explanation']) ?></div><?php endif; ?></div></div>
                        <div class="question-card-actions"><button class="school-button-quiet" type="button" data-edit-mcq="<?= (int) $mcq['mcq_id'] ?>">Edit</button><!-- <button type="submit" class="school-button-quiet">Delete</button> --><span class="school-disabled-action" title="Delete is disabled">Delete disabled</span></div>
                        <div class="edit-row" id="edit-mcq-<?= (int) $mcq['mcq_id'] ?>"><form method="POST" class="edit-form"><input type="hidden" name="action" value="update_mcq"><input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"><input type="hidden" name="mcq_id" value="<?= (int) $mcq['mcq_id'] ?>"><div class="school-grid-3"><div class="school-field"><label>Class</label><select class="school-select edit-class" name="class_id" required><?php foreach ($classOptions as $class): ?><option value="<?= (int) $class['class_id'] ?>" <?= (int) $class['class_id'] === (int) $mcq['class_id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?></select></div><div class="school-field"><label>Book</label><select class="school-select edit-book" name="book_id" required><?php foreach ($bookOptions as $book): ?><option value="<?= (int) $book['book_id'] ?>" data-class-id="<?= (int) $book['class_id'] ?>" <?= (int) $book['book_id'] === (int) $mcq['book_id'] ? 'selected' : '' ?>><?= htmlspecialchars($book['book_name']) ?></option><?php endforeach; ?></select></div><div class="school-field"><label>Chapter</label><select class="school-select edit-chapter" name="chapter_id" required><?php foreach ($chapterOptions as $chapter): ?><option value="<?= (int) $chapter['chapter_id'] ?>" data-class-id="<?= (int) $chapter['class_id'] ?>" data-book-name="<?= htmlspecialchars($chapter['book_name']) ?>" <?= (int) $chapter['chapter_id'] === (int) $mcq['chapter_id'] ? 'selected' : '' ?>><?= htmlspecialchars($chapter['chapter_name']) ?></option><?php endforeach; ?></select></div></div><div class="school-field"><label>Topic</label><input class="school-input" type="text" name="topic" value="<?= htmlspecialchars($mcq['topic'] ?? '') ?>"></div><div class="school-field"><label>Question</label><textarea class="school-textarea" name="question" required><?= htmlspecialchars($mcq['question']) ?></textarea></div><div class="school-grid-2"><div class="school-field"><label>Option A</label><input class="school-input" type="text" name="option_a" value="<?= htmlspecialchars($mcq['option_a'] ?? '') ?>" required></div><div class="school-field"><label>Option B</label><input class="school-input" type="text" name="option_b" value="<?= htmlspecialchars($mcq['option_b'] ?? '') ?>" required></div><div class="school-field"><label>Option C</label><input class="school-input" type="text" name="option_c" value="<?= htmlspecialchars($mcq['option_c'] ?? '') ?>" required></div><div class="school-field"><label>Option D</label><input class="school-input" type="text" name="option_d" value="<?= htmlspecialchars($mcq['option_d'] ?? '') ?>" required></div></div><div class="school-grid-2"><div class="school-field"><label>Correct option</label><select class="school-select" name="correct_option" required><?php $coText = trim($mcq['correct_option'] ?? ''); foreach (['A','B','C','D'] as $letter): $optionKey = 'option_' . strtolower($letter); ?><option value="<?= $letter ?>" <?= $coText === $letter || strcasecmp($coText, $mcq[$optionKey] ?? '') === 0 ? 'selected' : '' ?>>Option <?= $letter ?></option><?php endforeach; ?></select></div><div class="school-field"><label>Explanation</label><textarea class="school-textarea" name="explanation"><?= htmlspecialchars($mcq['explanation'] ?? '') ?></textarea></div></div><div class="form-actions"><button class="school-button" type="submit">Save changes</button><button class="school-button-quiet" type="button" data-close-mcq="<?= (int) $mcq['mcq_id'] ?>">Cancel</button></div></form></div>
                    </article>
                <?php endwhile; } ?>
            </div>
            <?php if ($mcqsPerPage === 'all'): ?><div class="school-pagination"><span>Viewing all <?= (int) $mcqTotalCount ?> MCQs</span></div><?php elseif ($mcqTotalPages > 1): ?><div class="school-pagination"><span>Showing <?= $mcqsOffset + 1 ?>â€“<?= min($mcqsOffset + $mcqsPerPage, $mcqTotalCount) ?> of <?= (int) $mcqTotalCount ?></span><div class="school-pagination-links"><?php if ($mcqsPage > 1): ?><a class="school-button-quiet" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['mcqs_page' => $mcqsPage - 1]))) ?>">â† Prev</a><?php endif; ?><?php for ($i = max(1, $mcqsPage - 2); $i <= min($mcqTotalPages, $mcqsPage + 2); $i++): ?><a class="school-button-quiet <?= $i === $mcqsPage ? 'active' : '' ?>" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['mcqs_page' => $i]))) ?>"><?= $i ?></a><?php endfor; ?><?php if ($mcqsPage < $mcqTotalPages): ?><a class="school-button-quiet" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['mcqs_page' => $mcqsPage + 1]))) ?>">Next â†’</a><?php endif; ?></div></div><?php endif; ?>
        </section>
    </section>
</main>

<script>
(function () {
    const books = <?= json_encode($bookOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const chapters = <?= json_encode($chapterOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function filterLocation(form, preserveChapter) {
        const classSelect = form.querySelector('.edit-class, #cq-class');
        const bookSelect = form.querySelector('.edit-book, #cq-book');
        const chapterSelect = form.querySelector('.edit-chapter, #cq-chapter');
        if (!classSelect || !bookSelect || !chapterSelect) return;
        const classId = classSelect.value;
        const currentBook = bookSelect.value;
        const currentChapter = chapterSelect.value;
        bookSelect.innerHTML = '<option value="">Choose book</option>';
        books.filter(book => !classId || String(book.class_id) === String(classId)).forEach(book => {
            const option = new Option(book.book_name, book.book_id);
            option.dataset.classId = book.class_id;
            option.dataset.bookName = book.book_name;
            option.selected = String(book.book_id) === String(currentBook);
            bookSelect.add(option);
        });
        bookSelect.disabled = !classId;
        const bookName = bookSelect.selectedOptions[0]?.dataset.bookName || '';
        chapterSelect.innerHTML = '<option value="">Choose chapter</option>';
        chapters.filter(chapter => (!classId || String(chapter.class_id) === String(classId)) && (!bookName || chapter.book_name === bookName)).forEach(chapter => {
            const option = new Option(chapter.chapter_name, chapter.chapter_id);
            option.dataset.classId = chapter.class_id;
            option.dataset.bookName = chapter.book_name;
            option.selected = String(chapter.chapter_id) === String(currentChapter) || String(chapter.chapter_id) === String(preserveChapter || '');
            chapterSelect.add(option);
        });
        chapterSelect.disabled = !bookSelect.value;
    }

    const createForm = document.getElementById('create-question-form');
    createForm?.querySelector('#cq-class')?.addEventListener('change', () => filterLocation(createForm));
    createForm?.querySelector('#cq-book')?.addEventListener('change', () => filterLocation(createForm));
    if (createForm) filterLocation(createForm);

    function toggleMcq(set, isMcq) {
        const options = set.querySelector('.mcq_options, .mcq-options-edit');
        if (!options) return;
        options.hidden = !isMcq;
        options.querySelectorAll('input, select').forEach(input => {
            input.required = isMcq && input.name !== 'explanation';
        });
    }
    const typeSelect = document.getElementById('question-type');
    function updateQuestionSets() { document.querySelectorAll('#question-sets [data-question-set]').forEach(set => toggleMcq(set, typeSelect?.value === 'mcq')); }
    typeSelect?.addEventListener('change', updateQuestionSets);
    const questionSets = document.getElementById('question-sets');
    function updateSetLabels() { questionSets?.querySelectorAll('[data-question-set]').forEach((set, index) => { set.querySelector('.question-set-number').textContent = 'Question ' + String(index + 1).padStart(2, '0'); const remove = set.querySelector('[data-remove-question]'); if (remove) remove.hidden = questionSets.children.length === 1; }); }
    document.getElementById('add-next-question')?.addEventListener('click', () => { const clone = questionSets.firstElementChild.cloneNode(true); clone.querySelectorAll('textarea, input').forEach(input => { input.value = ''; }); clone.querySelectorAll('select').forEach(select => { select.selectedIndex = 0; }); questionSets.appendChild(clone); updateSetLabels(); updateQuestionSets(); clone.querySelector('textarea').focus(); });
    questionSets?.addEventListener('click', event => { const remove = event.target.closest('[data-remove-question]'); if (remove && questionSets.children.length > 1) { remove.closest('[data-question-set]').remove(); updateSetLabels(); } });
    updateSetLabels(); updateQuestionSets();

    document.querySelectorAll('[data-edit-question]').forEach(button => { button.addEventListener('click', () => { const row = document.getElementById('edit-question-' + button.dataset.editQuestion); row?.classList.toggle('is-open'); if (row?.classList.contains('is-open')) { const form = row.querySelector('form'); filterLocation(form); const type = form.querySelector('.edit-question-type'); toggleMcq(form, type?.value === 'mcq'); type?.addEventListener('change', () => toggleMcq(form, type.value === 'mcq')); form.querySelector('.edit-class')?.addEventListener('change', () => filterLocation(form)); form.querySelector('.edit-book')?.addEventListener('change', () => filterLocation(form)); } }); });
    document.querySelectorAll('[data-close-question]').forEach(button => button.addEventListener('click', () => document.getElementById('edit-question-' + button.dataset.closeQuestion)?.classList.remove('is-open')));
    document.querySelectorAll('[data-edit-mcq]').forEach(button => { button.addEventListener('click', () => { const row = document.getElementById('edit-mcq-' + button.dataset.editMcq); row?.classList.toggle('is-open'); if (row?.classList.contains('is-open')) { const form = row.querySelector('form'); filterLocation(form); form.querySelector('.edit-class')?.addEventListener('change', () => filterLocation(form)); form.querySelector('.edit-book')?.addEventListener('change', () => filterLocation(form)); } }); });
    document.querySelectorAll('[data-close-mcq]').forEach(button => button.addEventListener('click', () => document.getElementById('edit-mcq-' + button.dataset.closeMcq)?.classList.remove('is-open')));

    document.querySelectorAll('[data-tab-target]').forEach(tab => tab.addEventListener('click', () => { document.querySelectorAll('[data-tab-target]').forEach(item => item.setAttribute('aria-selected', String(item === tab))); document.querySelectorAll('.school-tab-panel').forEach(panel => { panel.hidden = panel.id !== tab.dataset.tabTarget; }); }));
    const query = new URLSearchParams(window.location.search);
    if (query.has('mcq_search') || query.has('mcq_filter_class_id') || query.has('mcq_filter_book_id') || query.has('mcq_filter_chapter_id')) {
        document.querySelector('[data-tab-target="mcqs-panel"]')?.click();
    }

    [['question-class-filter', 'question-book-filter'], ['mcq-class-filter', 'mcq-book-filter']].forEach(([classId, bookId]) => { const classSelect = document.getElementById(classId); const bookSelect = document.getElementById(bookId); classSelect?.addEventListener('change', () => { if (!bookSelect) return; Array.from(bookSelect.options).forEach(option => { option.hidden = option.value !== '0' && classSelect.value !== '0' && option.dataset.classId !== classSelect.value; }); if (bookSelect.selectedOptions[0]?.hidden) bookSelect.value = '0'; }); classSelect?.dispatchEvent(new Event('change')); });
})();
</script>

<?php include_once __DIR__ . '/../footer.php'; ?>
