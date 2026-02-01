<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                // Topic update is handled via rename_topic action usually, but here we might want to change the topic association
                // For now, let's just update question and answer
                $question = sanitizeInput($_POST['question'] ?? '');
                $answer = sanitizeInput($_POST['typical_answer'] ?? '');

                if ($question === '' || $answer === '') {
                    $error = 'Question and Typical Answer are required.';
                } else {
                    $stmt = $conn->prepare(
                        "UPDATE AIGeneratedLongQuestions 
                         SET question_text = ?, typical_answer = ?
                         WHERE id = ?"
                    );
                    if ($stmt) {
                        $stmt->bind_param('ssi', $question, $answer, $id);
                        if ($stmt->execute()) {
                            $message = 'Long Question updated successfully.';
                            logAdminAction('update_ai_long', "ID: $id");
                        } else {
                            $error = 'Database error: ' . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
            } else {
                $error = 'Invalid ID.';
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                // Get topic name for count update
                $topicName = '';
                $getTopic = $conn->prepare("SELECT t.topic_name FROM AIGeneratedLongQuestions q JOIN AIQuestionsTopic t ON q.topic_id = t.id WHERE q.id = ?");
                if ($getTopic) {
                    $getTopic->bind_param('i', $id);
                    $getTopic->execute();
                    $res = $getTopic->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $topicName = $row['topic_name'];
                    }
                    $getTopic->close();
                }

                $stmt = $conn->prepare("DELETE FROM AIGeneratedLongQuestions WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    if ($stmt->execute()) {
                        $message = 'Long Question deleted successfully.';
                        logAdminAction('delete_ai_long', "ID: $id");
                        
                        // Update Count
                        if ($topicName) {
                            $upd = $conn->prepare("UPDATE TopicLongQuestionCounts SET question_count = GREATEST(0, question_count - 1) WHERE topic_name = ?");
                            if ($upd) {
                                $upd->bind_param('s', $topicName);
                                $upd->execute();
                                $upd->close();
                            }
                        }
                    } else {
                        $error = 'Database error: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                $error = 'Invalid ID.';
            }
        } elseif ($action === 'rename_topic') {
            // Renaming topic in AIQuestionsTopic affects ALL question types linked to it
            $oldTopic = sanitizeInput($_POST['old_topic'] ?? '');
            $newTopic = sanitizeInput($_POST['new_topic'] ?? '');

            if ($oldTopic === '' || $newTopic === '') {
                $error = 'Both old and new topic names are required.';
            } elseif (strcasecmp($oldTopic, $newTopic) === 0) {
                $error = 'Old and new topic names must be different.';
            } else {
                // Find ID of old topic
                $stmt = $conn->prepare("SELECT id FROM AIQuestionsTopic WHERE topic_name = ?");
                $stmt->bind_param('s', $oldTopic);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $topicId = $row['id'];
                    // Update name
                    $upd = $conn->prepare("UPDATE AIQuestionsTopic SET topic_name = ? WHERE id = ?");
                    $upd->bind_param('si', $newTopic, $topicId);
                    if ($upd->execute()) {
                         $message = "Topic renamed from '$oldTopic' to '$newTopic'. This affects all question types.";
                         logAdminAction('rename_ai_topic_global', "From '$oldTopic' to '$newTopic'");
                    } else {
                        $error = "Error updating topic: " . $upd->error;
                    }
                } else {
                    $error = "Topic '$oldTopic' not found.";
                }
            }
        }
    }
}

$topicFilter = trim($_GET['topic'] ?? '');

// Count per topic
$topicCounts = [];
$topicCountsResult = $conn->query(
    "SELECT t.topic_name as topic, COUNT(q.id) AS total 
     FROM AIGeneratedLongQuestions q
     JOIN AIQuestionsTopic t ON q.topic_id = t.id
     GROUP BY t.topic_name 
     ORDER BY total DESC, t.topic_name ASC"
);
if ($topicCountsResult) {
    while ($row = $topicCountsResult->fetch_assoc()) {
        $topicCounts[] = $row;
    }
}

$overallTotal = 0;
$totalRes = $conn->query("SELECT COUNT(*) AS cnt FROM AIGeneratedLongQuestions");
if ($totalRes && ($row = $totalRes->fetch_assoc())) {
    $overallTotal = (int)$row['cnt'];
}

$whereSql = '';
$whereParams = [];
$whereTypes = '';

if ($topicFilter !== '') {
    $whereSql = 'WHERE t.topic_name LIKE ?';
    $whereParams[] = '%' . $topicFilter . '%';
    $whereTypes .= 's';
}

$totalFiltered = 0;
if ($whereSql === '') {
    $totalFiltered = $overallTotal;
} else {
    $cntStmt = $conn->prepare("
        SELECT COUNT(*) AS cnt 
        FROM AIGeneratedLongQuestions q
        JOIN AIQuestionsTopic t ON q.topic_id = t.id
        $whereSql
    ");
    if ($cntStmt) {
        if ($whereTypes !== '') {
            $cntStmt->bind_param($whereTypes, ...$whereParams);
        }
        $cntStmt->execute();
        $cntResult = $cntStmt->get_result();
        if ($cntResult && ($row = $cntResult->fetch_assoc())) {
            $totalFiltered = (int)$row['cnt'];
        }
        $cntStmt->close();
    }
}

$perPageParam = $_GET['per_page'] ?? '20';
$perPage = 20;
$viewAll = false;
if ($perPageParam === 'all') {
    $viewAll = true;
    $perPage = max(1, $totalFiltered > 0 ? $totalFiltered : 1);
} else {
    $perPageInt = intval($perPageParam);
    if ($perPageInt > 0 && $perPageInt <= 200) {
        $perPage = $perPageInt;
    }
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

$totalPages = $perPage > 0 ? (int)ceil($totalFiltered / $perPage) : 1;
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;
if ($offset < 0) $offset = 0;

$limitSql = $viewAll ? '' : " LIMIT $offset, $perPage";

$questions = [];
$sql = "SELECT q.id, t.topic_name as topic, q.question_text, q.typical_answer, q.generated_at
        FROM AIGeneratedLongQuestions q
        LEFT JOIN AIQuestionsTopic t ON q.topic_id = t.id
        $whereSql
        ORDER BY q.generated_at DESC" . $limitSql;

if ($whereSql === '') {
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
    }
} else {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($whereTypes !== '') {
            $stmt->bind_param($whereTypes, ...$whereParams);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $questions[] = $row;
            }
        }
        $stmt->close();
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage AI Long Questions</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .ai-container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .ai-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .ai-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .ai-card { background: #fff; border-radius: 12px; padding: 1rem 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .data-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .data-table th, .data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #e0e0e0; text-align: left; vertical-align: top; }
        .data-table th { background: #f8f9fa; font-weight: 600; }
        .btn { padding: 0.35rem 0.75rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; }
        .btn-edit { background: #007bff; color: #fff; }
        .btn-delete { background: #dc3545; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .edit-row { background: #fafafa; }
        .topic-item { background: #fff; border-radius: 8px; border: 1px solid #e0e0e0; padding: 0.5rem 0.75rem; }
        .pagination { margin: 1rem 0; text-align: center; }
        .pagination a { display: inline-block; margin: 0 3px; padding: 0.3rem 0.6rem; border: 1px solid #007bff; color: #007bff; text-decoration: none; border-radius: 4px; }
        .pagination a.active, .pagination a:hover { background: #007bff; color: #fff; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>

    <div class="ai-container">
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>

        <div class="ai-header">
            <h1>Manage AI Long Questions</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="ai-summary">
            <div class="ai-card">
                <h3>Summary</h3>
                <p><strong>Total Questions:</strong> <?= (int)$overallTotal ?></p>
                <p><strong>Topics:</strong> <?= count($topicCounts) ?></p>
            </div>
            <?php if ($topicFilter !== ''): ?>
            <div class="ai-card">
                <h3>Current Filter</h3>
                <p><strong>Topic:</strong> <?= htmlspecialchars($topicFilter) ?></p>
                <p><strong>Found:</strong> <?= (int)$totalFiltered ?></p>
                <a href="manage_ai_long.php" style="color:#007bff;">Clear filter</a>
            </div>
            <?php endif; ?>
            <div class="ai-card">
                <h3>Rename Topic</h3>
                <form method="POST" onsubmit="return confirm('Rename topic for ALL question types?');" style="display:flex; flex-direction:column; gap:0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="rename_topic">
                    <input type="text" name="old_topic" placeholder="Old Topic Name" required style="padding:4px;">
                    <input type="text" name="new_topic" placeholder="New Topic Name" required style="padding:4px;">
                    <button type="submit" class="btn btn-edit">Rename</button>
                </form>
            </div>
        </div>

        <h2>Questions List</h2>

        <form method="GET" style="margin-bottom:1rem; display:flex; gap:1rem; align-items:center;">
            <input type="text" name="topic" value="<?= htmlspecialchars($topicFilter) ?>" placeholder="Filter by topic" style="padding:0.4rem; border:1px solid #ccc; border-radius:4px;">
            <button type="submit" class="btn btn-secondary">Search</button>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Topic</th>
                    <th>Question</th>
                    <th>Typical Answer</th>
                    <th>Generated At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($questions)): ?>
                    <tr><td colspan="6">No questions found.</td></tr>
                <?php else: ?>
                    <?php foreach ($questions as $q): ?>
                        <tr>
                            <td><?= (int)$q['id'] ?></td>
                            <td><?= htmlspecialchars($q['topic'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($q['question_text']) ?></td>
                            <td><?= htmlspecialchars(substr($q['typical_answer'] ?? '', 0, 100)) . (strlen($q['typical_answer'] ?? '') > 100 ? '...' : '') ?></td>
                            <td><?= htmlspecialchars($q['generated_at']) ?></td>
                            <td>
                                <button type="button" class="btn btn-edit" onclick="document.getElementById('edit-<?= $q['id'] ?>').style.display='table-row'">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                    <button type="submit" class="btn btn-delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <tr id="edit-<?= $q['id'] ?>" class="edit-row" style="display:none;">
                            <td colspan="6">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                    <div style="display:grid; gap:0.5rem; padding:1rem;">
                                        <label>Question: <textarea name="question" style="width:100%; min-height:60px;"><?= htmlspecialchars($q['question_text']) ?></textarea></label>
                                        <label>Typical Answer: <textarea name="typical_answer" style="width:100%; min-height:80px;"><?= htmlspecialchars($q['typical_answer'] ?? '') ?></textarea></label>
                                        <div>
                                            <button type="submit" class="btn btn-edit">Save</button>
                                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('edit-<?= $q['id'] ?>').style.display='none'">Cancel</button>
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>
