<?php
/**
 * Super-admin cleanup for content belonging to one class/book pair.
 *
 * This page deliberately keeps the class and book rows. It removes only
 * records with a trustworthy class_id + book_id relationship (plus class
 * notes whose legacy schema stores the book as an exact subject value).
 */
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';

requireAdminAuth();

if (!in_array((string) ($_SESSION['role'] ?? ''), ['superadmin', 'super_admin'], true)) {
    http_response_code(403);
    exit('Forbidden: only a super admin can run book cleanup.');
}

/** @var mysqli $conn */

function cleanupTableExists(mysqli $conn, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $cache[$table] = (bool) ($result && $result->num_rows > 0);
}

function cleanupColumnExists(mysqli $conn, string $table, string $column): bool
{
    if (!cleanupTableExists($conn, $table)) {
        return false;
    }

    $safeTable = str_replace('`', '``', $table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return (bool) ($result && $result->num_rows > 0);
}

/** Bind a dynamic list of scalar parameters to a mysqli statement. */
function cleanupBind(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '') {
        return;
    }

    $bind = [$types];
    foreach ($params as $key => &$value) {
        $bind[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

/** @return array<int, array<string, mixed>> */
function cleanupRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare cleanup lookup.');
    }

    cleanupBind($stmt, $types, $params);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to execute cleanup lookup.');
    }

    $result = $stmt->get_result();
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function cleanupCount(mysqli $conn, string $table, string $where, string $types, array $params): int
{
    if (!cleanupTableExists($conn, $table)) {
        return 0;
    }

    $safeTable = str_replace('`', '``', $table);
    $rows = cleanupRows($conn, "SELECT COUNT(*) AS total FROM `{$safeTable}` WHERE {$where}", $types, $params);
    return (int) ($rows[0]['total'] ?? 0);
}

function cleanupDelete(mysqli $conn, string $table, string $where, string $types, array $params): int
{
    if (!cleanupTableExists($conn, $table)) {
        return 0;
    }

    $safeTable = str_replace('`', '``', $table);
    $stmt = $conn->prepare("DELETE FROM `{$safeTable}` WHERE {$where}");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare cleanup deletion.');
    }

    cleanupBind($stmt, $types, $params);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to execute cleanup deletion.');
    }

    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

/** @return array<int> */
function cleanupIds(mysqli $conn, string $table, string $idColumn, string $where, string $types, array $params): array
{
    if (!cleanupTableExists($conn, $table)) {
        return [];
    }

    $safeTable = str_replace('`', '``', $table);
    $safeColumn = str_replace('`', '``', $idColumn);
    $rows = cleanupRows($conn, "SELECT `{$safeColumn}` AS id FROM `{$safeTable}` WHERE {$where}", $types, $params);
    return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
}

/** Delete rows whose integer key is in a validated, server-derived ID list. */
function cleanupDeleteIds(mysqli $conn, string $table, string $column, array $ids): int
{
    if (!$ids || !cleanupTableExists($conn, $table)) {
        return 0;
    }

    $safeColumn = str_replace('`', '``', $column);
    $total = 0;
    foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $total += cleanupDelete($conn, $table, "`{$safeColumn}` IN ({$placeholders})", str_repeat('i', count($chunk)), $chunk);
    }
    return $total;
}

/** @return string[] */
function cleanupClassNoteValues(string $className): array
{
    $values = [trim($className)];
    if (preg_match('/(?:class\s*)?(9|10|11|12)(?:th|st|nd|rd)?\b/i', $className, $matches)) {
        $values[] = $matches[1];
    }
    return array_values(array_unique(array_filter($values, static fn (string $value): bool => $value !== '')));
}

function cleanupCountByIds(mysqli $conn, string $table, string $column, array $ids): int
{
    if (!$ids || !cleanupTableExists($conn, $table)) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return cleanupCount($conn, $table, "`{$column}` IN ({$placeholders})", str_repeat('i', count($ids)), $ids);
}

/** @return int[] */
function cleanupIdsByIds(mysqli $conn, string $table, string $idColumn, string $filterColumn, array $ids): array
{
    if (!$ids || !cleanupTableExists($conn, $table)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return cleanupIds($conn, $table, $idColumn, "`{$filterColumn}` IN ({$placeholders})", str_repeat('i', count($ids)), $ids);
}

function cleanupCountByTwoIdSets(mysqli $conn, string $table, string $columnA, array $idsA, string $columnB, array $idsB): int
{
    if (!cleanupTableExists($conn, $table) || (!$idsA && !$idsB)) {
        return 0;
    }

    $clauses = [];
    $params = [];
    $types = '';
    if ($idsA) {
        $clauses[] = "`{$columnA}` IN (" . implode(',', array_fill(0, count($idsA), '?')) . ')';
        $params = array_merge($params, $idsA);
        $types .= str_repeat('i', count($idsA));
    }
    if ($idsB) {
        $clauses[] = "`{$columnB}` IN (" . implode(',', array_fill(0, count($idsB), '?')) . ')';
        $params = array_merge($params, $idsB);
        $types .= str_repeat('i', count($idsB));
    }
    return cleanupCount($conn, $table, '(' . implode(' OR ', $clauses) . ')', $types, $params);
}

/** @return array<string, int> */
function cleanupBuildPreview(mysqli $conn, int $classId, int $bookId, array $book): array
{
    $preview = [];
    $directTargets = [
        'questions' => ['table' => 'questions'],
        'mcqs' => ['table' => 'mcqs'],
        'questions_from_book' => ['table' => 'questions_from_book'],
        'mcqs_from_book' => ['table' => 'mcqs_from_book'],
        'book_question_drafts' => ['table' => 'book_question_drafts'],
        'book_mcq_drafts' => ['table' => 'book_mcq_drafts'],
        'book_chapter_page_ranges' => ['table' => 'book_chapter_page_ranges'],
        'book_uploads' => ['table' => 'book_uploads'],
        'uploaded_notes' => ['table' => 'uploaded_notes'],
        'exam_preparations' => ['table' => 'exam_preparations'],
        'quiz_rooms' => ['table' => 'quiz_rooms'],
        'chapter' => ['table' => 'chapter'],
    ];

    foreach ($directTargets as $key => $target) {
        $preview[$key] = cleanupCount($conn, $target['table'], 'class_id = ? AND book_id = ?', 'ii', [$classId, $bookId]);
    }

    $preview['manual_mcq_verification'] = 0;
    if (cleanupTableExists($conn, 'MCQsVerification') && cleanupTableExists($conn, 'mcqs')) {
        $preview['manual_mcq_verification'] = (int) (cleanupRows(
            $conn,
            'SELECT COUNT(*) AS total FROM MCQsVerification v INNER JOIN mcqs m ON m.mcq_id = v.mcq_id WHERE m.class_id = ? AND m.book_id = ?',
            'ii',
            [$classId, $bookId]
        )[0]['total'] ?? 0);
    }

    $preview['mcq_verification'] = 0;
    if (cleanupTableExists($conn, 'MCQVerification') && cleanupTableExists($conn, 'mcqs') && cleanupColumnExists($conn, 'MCQVerification', 'source')) {
        $preview['mcq_verification'] = (int) (cleanupRows(
            $conn,
            "SELECT COUNT(*) AS total FROM MCQVerification v INNER JOIN mcqs m ON m.mcq_id = v.mcq_id WHERE v.source = 'mcqs' AND m.class_id = ? AND m.book_id = ?",
            'ii',
            [$classId, $bookId]
        )[0]['total'] ?? 0);
    }

    $preview['class_notes'] = 0;
    $preview['class_notes_safe'] = 0;
    $classNoteValues = cleanupClassNoteValues($book['class_name']);
    if ($classNoteValues && cleanupTableExists($conn, 'class_notes') && cleanupColumnExists($conn, 'class_notes', 'subject') && cleanupColumnExists($conn, 'class_notes', 'class')) {
        $placeholders = implode(',', array_fill(0, count($classNoteValues), '?'));
        $params = array_merge([$book['book_name']], $classNoteValues);
        $types = 's' . str_repeat('s', count($classNoteValues));
        $preview['class_notes'] = cleanupCount($conn, 'class_notes', "subject = ? AND `class` IN ({$placeholders})", $types, $params);
        $preview['class_notes_safe'] = 1;
    }

    $roomIds = cleanupIds($conn, 'quiz_rooms', 'id', 'class_id = ? AND book_id = ?', 'ii', [$classId, $bookId]);
    $preview['quiz_room_questions'] = cleanupCountByIds($conn, 'quiz_room_questions', 'room_id', $roomIds);
    $preview['quiz_participants'] = cleanupCountByIds($conn, 'quiz_participants', 'room_id', $roomIds);
    $preview['live_quiz_events'] = cleanupCountByIds($conn, 'live_quiz_events', 'room_id', $roomIds);

    $participantIds = cleanupIdsByIds($conn, 'quiz_participants', 'id', 'room_id', $roomIds);
    $questionIds = cleanupIdsByIds($conn, 'quiz_room_questions', 'id', 'room_id', $roomIds);
    $preview['quiz_responses'] = cleanupCountByTwoIdSets($conn, 'quiz_responses', 'participant_id', $participantIds, 'question_id', $questionIds);

    return $preview;
}

/** @return array<string, int> */
function cleanupBookData(mysqli $conn, int $classId, int $bookId, array $book): array
{
    $deleted = [];
    $roomIds = cleanupIds($conn, 'quiz_rooms', 'id', 'class_id = ? AND book_id = ?', 'ii', [$classId, $bookId]);
    $participantIds = cleanupIdsByIds($conn, 'quiz_participants', 'id', 'room_id', $roomIds);
    $questionIds = cleanupIdsByIds($conn, 'quiz_room_questions', 'id', 'room_id', $roomIds);

    if ($participantIds || $questionIds) {
        $clauses = [];
        $params = [];
        $types = '';
        if ($participantIds) {
            $clauses[] = 'participant_id IN (' . implode(',', array_fill(0, count($participantIds), '?')) . ')';
            $params = array_merge($params, $participantIds);
            $types .= str_repeat('i', count($participantIds));
        }
        if ($questionIds) {
            $clauses[] = 'question_id IN (' . implode(',', array_fill(0, count($questionIds), '?')) . ')';
            $params = array_merge($params, $questionIds);
            $types .= str_repeat('i', count($questionIds));
        }
        $deleted['quiz_responses'] = cleanupDelete($conn, 'quiz_responses', '(' . implode(' OR ', $clauses) . ')', $types, $params);
    } else {
        $deleted['quiz_responses'] = 0;
    }
    $deleted['live_quiz_events'] = cleanupDeleteIds($conn, 'live_quiz_events', 'room_id', $roomIds);
    $deleted['quiz_participants'] = cleanupDeleteIds($conn, 'quiz_participants', 'room_id', $roomIds);
    $deleted['quiz_room_questions'] = cleanupDeleteIds($conn, 'quiz_room_questions', 'room_id', $roomIds);
    $deleted['quiz_rooms'] = cleanupDelete($conn, 'quiz_rooms', 'class_id = ? AND book_id = ?', 'ii', [$classId, $bookId]);

    if (cleanupTableExists($conn, 'MCQsVerification') && cleanupTableExists($conn, 'mcqs')) {
        $deleted['manual_mcq_verification'] = cleanupDelete($conn, 'MCQsVerification', 'mcq_id IN (SELECT mcq_id FROM mcqs WHERE class_id = ? AND book_id = ?)', 'ii', [$classId, $bookId]);
    } else {
        $deleted['manual_mcq_verification'] = 0;
    }
    if (cleanupTableExists($conn, 'MCQVerification') && cleanupTableExists($conn, 'mcqs') && cleanupColumnExists($conn, 'MCQVerification', 'source')) {
        $deleted['mcq_verification'] = cleanupDelete($conn, 'MCQVerification', "source = 'mcqs' AND mcq_id IN (SELECT mcq_id FROM mcqs WHERE class_id = ? AND book_id = ?)", 'ii', [$classId, $bookId]);
    } else {
        $deleted['mcq_verification'] = 0;
    }

    foreach (['uploaded_notes', 'exam_preparations', 'book_chapter_page_ranges', 'book_mcq_drafts', 'book_question_drafts', 'book_uploads', 'mcqs_from_book', 'questions_from_book', 'mcqs', 'questions', 'chapter'] as $table) {
        $deleted[$table] = cleanupDelete($conn, $table, 'class_id = ? AND book_id = ?', 'ii', [$classId, $bookId]);
    }

    $deleted['class_notes'] = 0;
    $classNoteValues = cleanupClassNoteValues($book['class_name']);
    if ($classNoteValues && cleanupTableExists($conn, 'class_notes') && cleanupColumnExists($conn, 'class_notes', 'subject') && cleanupColumnExists($conn, 'class_notes', 'class')) {
        $placeholders = implode(',', array_fill(0, count($classNoteValues), '?'));
        $params = array_merge([$book['book_name']], $classNoteValues);
        $types = 's' . str_repeat('s', count($classNoteValues));
        $noteIds = cleanupIds($conn, 'class_notes', 'id', "subject = ? AND `class` IN ({$placeholders})", $types, $params);
        $deleted['class_note_comments'] = cleanupDeleteIds($conn, 'class_note_comments', 'note_id', $noteIds);
        $deleted['class_note_likes'] = cleanupDeleteIds($conn, 'class_note_likes', 'note_id', $noteIds);
        $deleted['class_notes'] = cleanupDeleteIds($conn, 'class_notes', 'id', $noteIds);
    }

    return $deleted;
}

$classId = filter_var($_REQUEST['class_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$bookId = filter_var($_REQUEST['book_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$message = '';
$error = '';
$preview = [];
$selectedBook = null;

if ($classId > 0 && $bookId > 0) {
    $selectedRows = cleanupRows($conn, 'SELECT b.book_id, b.book_name, b.class_id, c.class_name FROM book b INNER JOIN class c ON c.class_id = b.class_id WHERE b.book_id = ? AND b.class_id = ? LIMIT 1', 'ii', [$bookId, $classId]);
    $selectedBook = $selectedRows[0] ?? null;
    if ($selectedBook) {
        $preview = cleanupBuildPreview($conn, $classId, $bookId, $selectedBook);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $error = 'The selected book does not belong to the selected class.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && $selectedBook) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please reload the page.';
    } elseif (!hash_equals(strtoupper('DELETE ' . trim((string) $selectedBook['book_name'])), strtoupper(trim((string) ($_POST['confirmation'] ?? ''))))) {
        $error = 'Confirmation text did not match. No data was changed.';
    } else {
        $rateIdentifier = (string) ($_SESSION['admin_id'] ?? getAdminClientIP());
        $rate = checkDbRateLimit($conn, 'book_cleanup', $rateIdentifier, 3, 900, 900);
        if (!$rate['allowed']) {
            $error = 'Cleanup is temporarily locked after repeated attempts. Try again later.';
        } else {
            try {
                $conn->begin_transaction();
                $deleted = cleanupBookData($conn, $classId, $bookId, $selectedBook);
                $conn->commit();
                logAdminAction('cleanup_book_data', json_encode(['class_id' => $classId, 'book_id' => $bookId, 'book_name' => $selectedBook['book_name'], 'deleted' => $deleted], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $_SESSION['book_cleanup_result'] = $deleted;
                header('Location: cleanup_book_data.php?class_id=' . $classId . '&book_id=' . $bookId . '&msg=deleted');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Book cleanup failed: ' . $e->getMessage());
                $error = 'Cleanup failed and was rolled back. No partial deletion was kept.';
            }
        }
    }
}

if (($_GET['msg'] ?? '') === 'deleted' && isset($_SESSION['book_cleanup_result'])) {
    $result = $_SESSION['book_cleanup_result'];
    unset($_SESSION['book_cleanup_result']);
    $message = 'Cleanup completed. ' . array_sum(array_map('intval', $result)) . ' related database row(s) were removed. The class and book records were preserved.';
}

$classes = [];
$classResult = $conn->query('SELECT class_id, class_name FROM class ORDER BY class_id ASC');
while ($classResult && ($row = $classResult->fetch_assoc())) {
    $classes[] = $row;
}
$books = [];
$bookResult = $conn->query('SELECT book_id, book_name, class_id FROM book ORDER BY class_id ASC, book_name ASC');
while ($bookResult && ($row = $bookResult->fetch_assoc())) {
    $books[] = $row;
}

$previewLabels = [
    'chapter' => 'Chapters', 'questions' => 'Questions', 'mcqs' => 'MCQs',
    'questions_from_book' => 'Book short/long questions', 'mcqs_from_book' => 'Book MCQs',
    'book_question_drafts' => 'Book question drafts', 'book_mcq_drafts' => 'Book MCQ drafts',
    'book_chapter_page_ranges' => 'Book chapter page mappings', 'book_uploads' => 'Book uploads',
    'uploaded_notes' => 'Book-linked notes', 'class_notes' => 'Legacy class notes matched by exact book subject',
    'exam_preparations' => 'Exam preparation tests', 'quiz_rooms' => 'Live quiz rooms',
    'quiz_room_questions' => 'Live quiz questions', 'quiz_participants' => 'Live quiz participants',
    'live_quiz_events' => 'Live quiz events', 'quiz_responses' => 'Live quiz responses',
    'manual_mcq_verification' => 'Manual MCQ verification rows', 'mcq_verification' => 'MCQ verification rows',
];
$previewTotal = 0;
foreach ($preview as $previewKey => $previewValue) {
    if ($previewKey !== 'class_notes_safe') {
        $previewTotal += (int) $previewValue;
    }
}

include_once __DIR__ . '/header.php';
?>
<style>
    .cleanup-page { max-width: 1180px; margin: 0 auto; padding: 32px 20px 60px; }
    .cleanup-card { background: #fff; border-radius: 14px; box-shadow: 0 8px 28px rgba(15, 23, 42, .08); padding: 24px; margin-bottom: 22px; }
    .cleanup-warning { border-left: 5px solid #dc3545; background: #fff1f2; color: #7f1d1d; }
    .cleanup-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; align-items: end; }
    .cleanup-form label { display: block; font-weight: 700; margin-bottom: 6px; color: #25324b; }
    .cleanup-form select, .cleanup-form input { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
    .cleanup-form .full { grid-column: 1 / -1; }
    .cleanup-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .cleanup-button { border: 0; border-radius: 8px; padding: 11px 18px; cursor: pointer; font-weight: 700; }
    .cleanup-button.preview { background: #2563eb; color: #fff; }
    .cleanup-button.danger { background: #b91c1c; color: #fff; }
    .cleanup-button:disabled { opacity: .5; cursor: not-allowed; }
    .cleanup-alert { border-radius: 8px; padding: 13px 16px; margin-bottom: 18px; }
    .cleanup-alert.success { background: #ecfdf5; color: #166534; border: 1px solid #bbf7d0; }
    .cleanup-alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .cleanup-table { width: 100%; border-collapse: collapse; }
    .cleanup-table th, .cleanup-table td { padding: 11px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
    .cleanup-table th:last-child, .cleanup-table td:last-child { text-align: right; }
    .cleanup-total { font-size: 1.15rem; font-weight: 800; color: #991b1b; }
    .cleanup-muted { color: #64748b; }
    @media (max-width: 700px) { .cleanup-form { grid-template-columns: 1fr; } .cleanup-form .full { grid-column: auto; } }
</style>
<main class="cleanup-page">
    <div class="cleanup-card"><p><a href="dashboard.php">← Back to dashboard</a></p><h1>Remove Old Book Data</h1><p class="cleanup-muted">Select one class and book to inspect all book-scoped content before removing it.</p></div>
    <div class="cleanup-card cleanup-warning"><strong>Destructive action — super admin only.</strong><p>This removes matching chapters, questions, MCQs, book-generated records, notes, exam-preparation tests, and live quiz records from the database. The selected class and book rows are preserved. Google Drive files and generic AI/topic content are not deleted.</p></div>
    <?php if ($message): ?><div class="cleanup-alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="cleanup-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="cleanup-card"><form method="GET" class="cleanup-form">
        <div><label for="class_id">Class</label><select id="class_id" name="class_id" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['class_id'] ?>" <?= $classId === (int) $class['class_id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?></select></div>
        <div><label for="book_id">Book</label><select id="book_id" name="book_id" required><option value="">Select book</option><?php foreach ($books as $book): ?><option value="<?= (int) $book['book_id'] ?>" data-class-id="<?= (int) $book['class_id'] ?>" <?= $bookId === (int) $book['book_id'] ? 'selected' : '' ?>><?= htmlspecialchars($book['book_name']) ?></option><?php endforeach; ?></select></div>
        <div class="full cleanup-actions"><button class="cleanup-button preview" type="submit">Preview rows to remove</button></div>
    </form></div>
    <?php if ($selectedBook): ?>
        <div class="cleanup-card"><h2><?= htmlspecialchars($selectedBook['class_name']) ?> — <?= htmlspecialchars($selectedBook['book_name']) ?></h2><p class="cleanup-total">Total preview rows: <?= $previewTotal ?></p><table class="cleanup-table"><thead><tr><th>Data type</th><th>Rows</th></tr></thead><tbody><?php foreach ($previewLabels as $key => $label): ?><?php if (!array_key_exists($key, $preview)) continue; ?><tr><td><?= htmlspecialchars($label) ?></td><td><?= (int) $preview[$key] ?></td></tr><?php endforeach; ?></tbody></table><?php if (($preview['class_notes_safe'] ?? 0) === 0): ?><p class="cleanup-muted">Legacy class notes were not counted because their schema does not provide a safe exact class/subject mapping.</p><?php endif; ?></div>
        <div class="cleanup-card"><h2>Confirm permanent database cleanup</h2><p>Type <code>DELETE <?= htmlspecialchars($selectedBook['book_name']) ?></code> exactly to enable deletion. This action cannot be undone from the admin panel.</p><form method="POST" class="cleanup-form" onsubmit="return confirm('Delete all previewed rows for this class and book?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>"><input type="hidden" name="class_id" value="<?= (int) $classId ?>"><input type="hidden" name="book_id" value="<?= (int) $bookId ?>"><div class="full"><label for="confirmation">Confirmation text</label><input id="confirmation" name="confirmation" type="text" autocomplete="off" placeholder="DELETE <?= htmlspecialchars($selectedBook['book_name']) ?>" required></div><div class="full cleanup-actions"><button id="delete-button" class="cleanup-button danger" type="submit" <?= $previewTotal > 0 ? '' : 'disabled' ?>>Delete previewed database rows</button></div></form></div>
    <?php endif; ?>
</main>
<script>
(function () { const classSelect = document.getElementById('class_id'); const bookSelect = document.getElementById('book_id'); if (!classSelect || !bookSelect) return; function filterBooks() { const classId = classSelect.value; Array.from(bookSelect.options).forEach(function (option, index) { if (index === 0) return; const visible = !classId || option.dataset.classId === classId; option.hidden = !visible; if (!visible && option.selected) bookSelect.value = ''; }); } classSelect.addEventListener('change', filterBooks); filterBooks(); })();
</script>
