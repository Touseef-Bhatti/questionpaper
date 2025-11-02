<?php
require_once __DIR__ . '/../db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    header('Location: login.php');
    exit;
}

$hasBookName = false;
// Detect whether questions.book_name column exists for compatibility with older databases
$colCheck = $conn->query("SHOW COLUMNS FROM questions LIKE 'book_name'");
if ($colCheck && $colCheck->num_rows > 0) { $hasBookName = true; }

// Detect column naming for type/text: either (question_type, question_text) or (type, text)
$hasQuestionType = false;
$hasQuestionText = false;
$colTypeCheck = $conn->query("SHOW COLUMNS FROM questions LIKE 'question_type'");
if ($colTypeCheck && $colTypeCheck->num_rows > 0) { $hasQuestionType = true; }
$colTextCheck = $conn->query("SHOW COLUMNS FROM questions LIKE 'question_text'");
if ($colTextCheck && $colTextCheck->num_rows > 0) { $hasQuestionText = true; }

$typeCol = ($hasQuestionType ? 'question_type' : 'type');
$textCol = ($hasQuestionText ? 'question_text' : 'text');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'restore') {
        $trashId = intval($_POST['trash_id'] ?? 0);
        if ($trashId > 0) {
            $resDel = $conn->query("SELECT id, question_id, class_id, book_name, chapter_id, question_type, question_text FROM deleted_questions WHERE id=$trashId LIMIT 1");
            if ($resDel && ($dq = $resDel->fetch_assoc())) {
                $cid = (int)$dq['class_id'];
                $chap = (int)$dq['chapter_id'];
                $qtype = $conn->real_escape_string($dq['question_type']);
                $qtext = $conn->real_escape_string($dq['question_text']);
                if ($hasBookName) {
                    $bname = $conn->real_escape_string($dq['book_name'] ?? '');
                    $conn->query("INSERT INTO questions (class_id, book_name, chapter_id, $typeCol, $textCol) VALUES ($cid, " . ($bname!==''?"'$bname'":"NULL") . ", $chap, '$qtype', '$qtext')");
                } else {
                    $conn->query("INSERT INTO questions (class_id, chapter_id, $typeCol, $textCol) VALUES ($cid, $chap, '$qtype', '$qtext')");
                }
                $conn->query("DELETE FROM deleted_questions WHERE id=$trashId");
                $message = 'Question restored successfully.';
            }
        }
    } elseif ($action === 'purge') {
        $trashId = intval($_POST['trash_id'] ?? 0);
        if ($trashId > 0) {
            $conn->query("DELETE FROM deleted_questions WHERE id=$trashId");
            $message = 'Deleted entry removed permanently.';
        }
    } elseif ($action === 'purge_all') {
        $conn->query("DELETE FROM deleted_questions");
        $message = 'All deleted entries removed permanently.';
    }
}

// Get deleted questions with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM deleted_questions");
$total = $totalQuery ? $totalQuery->fetch_assoc()['total'] : 0;
$totalPages = ceil($total / $perPage);

$deletedQuestions = $conn->query("SELECT id, question_id, class_id, book_name, chapter_id, question_type, question_text, deleted_at FROM deleted_questions ORDER BY deleted_at DESC LIMIT $perPage OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recently Deleted Questions</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/footer.css">
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="wrap">
        <div class="nav">
            <a href="dashboard.php" class="btn">← Back to Dashboard</a>
            <a href="manage_questions.php" class="btn">← Back to Manage Questions</a>
        </div>
        <h1>Recently Deleted Questions</h1>
        <?php if ($message): ?><p class="msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <?php if ($total > 0): ?>
            <div class="actions" style="margin-bottom: 20px;">
                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to permanently delete ALL deleted questions? This action cannot be undone.');">
                    <input type="hidden" name="action" value="purge_all">
                    <button type="submit" class="danger">Purge All Deleted Questions</button>
                </form>
                <span style="margin-left: 20px; color: #666;">Total: <?= $total ?> deleted questions</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Original QID</th>
                        <th>Class</th>
                        <th>Book</th>
                        <th>Chapter</th>
                        <th>Type</th>
                        <th>Text</th>
                        <th>Deleted At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($deletedQuestions) while ($tr = $deletedQuestions->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$tr['id'] ?></td>
                        <td><?= (int)$tr['question_id'] ?></td>
                        <td><?= (int)$tr['class_id'] ?></td>
                        <td><?= htmlspecialchars($tr['book_name'] ?? '') ?></td>
                        <td><?= (int)$tr['chapter_id'] ?></td>
                        <td><?= htmlspecialchars($tr['question_type']) ?></td>
                        <td>
                            <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($tr['question_text']) ?>">
                                <?= htmlspecialchars(substr($tr['question_text'], 0, 100)) ?><?= strlen($tr['question_text']) > 100 ? '...' : '' ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($tr['deleted_at']) ?></td>
                        <td>
                            <form method="POST" class="inline" onsubmit="return confirm('Restore this question?');">
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="trash_id" value="<?= (int)$tr['id'] ?>">
                                <button type="submit" class="btn">Restore</button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Permanently delete this entry?');">
                                <input type="hidden" name="action" value="purge">
                                <input type="hidden" name="trash_id" value="<?= (int)$tr['id'] ?>">
                                <button type="submit" class="danger">Purge</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div class="pagination" style="margin-top: 20px; text-align: center;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="btn">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="btn" style="background: #007cba; color: white;"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>" class="btn"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="btn">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p style="text-align: center; color: #666; margin: 40px 0;">No deleted questions found.</p>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>
