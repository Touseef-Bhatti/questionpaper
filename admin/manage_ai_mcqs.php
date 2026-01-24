<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

// Ensure verification table exists (avoids error on first run)
// Moved to install.php
// $createTableSql = ...;
// $conn->query($createTableSql);

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
                $topic = sanitizeInput($_POST['topic'] ?? '');
                $question = sanitizeInput($_POST['question'] ?? '');
                $optionA = sanitizeInput($_POST['option_a'] ?? '');
                $optionB = sanitizeInput($_POST['option_b'] ?? '');
                $optionC = sanitizeInput($_POST['option_c'] ?? '');
                $optionD = sanitizeInput($_POST['option_d'] ?? '');
                $correctLetter = strtoupper(trim($_POST['correct_option'] ?? ''));

                $correctText = '';
                switch ($correctLetter) {
                    case 'A':
                        $correctText = $optionA;
                        break;
                    case 'B':
                        $correctText = $optionB;
                        break;
                    case 'C':
                        $correctText = $optionC;
                        break;
                    case 'D':
                        $correctText = $optionD;
                        break;
                }

                if ($question === '' || $optionA === '' || $optionB === '' || $optionC === '' || $optionD === '' || $correctText === '') {
                    $error = 'All options and correct answer are required for updating an MCQ.';
                } else {
                    $stmt = $conn->prepare(
                        "UPDATE AIGeneratedMCQs 
                         SET topic = ?, question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?
                         WHERE id = ?"
                    );
                    if ($stmt) {
                        $stmt->bind_param(
                            'sssssssi',
                            $topic,
                            $question,
                            $optionA,
                            $optionB,
                            $optionC,
                            $optionD,
                            $correctText,
                            $id
                        );
                        if ($stmt->execute()) {
                            $message = 'AI MCQ updated successfully.';
                            logAdminAction('update_ai_mcq', "AI MCQ ID: $id");
                        } else {
                            $error = 'Database error while updating MCQ: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error = 'Failed to prepare update statement.';
                    }
                }
            } else {
                $error = 'Invalid MCQ ID.';
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM AIGeneratedMCQs WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    if ($stmt->execute()) {
                        $message = 'AI MCQ deleted successfully.';
                        logAdminAction('delete_ai_mcq', "AI MCQ ID: $id");
                    } else {
                        $error = 'Database error while deleting MCQ: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to prepare delete statement.';
                }
            } else {
                $error = 'Invalid MCQ ID.';
            }
        } elseif ($action === 'rename_topic') {
            $oldTopic = sanitizeInput($_POST['old_topic'] ?? '');
            $newTopic = sanitizeInput($_POST['new_topic'] ?? '');

            if ($oldTopic === '' || $newTopic === '') {
                $error = 'Both old and new topic names are required.';
            } elseif (strcasecmp($oldTopic, $newTopic) === 0) {
                $error = 'Old and new topic names must be different.';
            } else {
                $stmt = $conn->prepare(
                    "UPDATE AIGeneratedMCQs 
                     SET topic = ? 
                     WHERE topic = ?"
                );
                if ($stmt) {
                    $stmt->bind_param('ss', $newTopic, $oldTopic);
                    if ($stmt->execute()) {
                        $affected = $stmt->affected_rows;
                        if ($affected > 0) {
                            $message = "Topic name updated in $affected AI MCQs.";
                            logAdminAction('rename_ai_topic', "From '$oldTopic' to '$newTopic' ($affected rows)");
                        } else {
                            $message = 'No AI MCQs found with the specified old topic name.';
                        }
                    } else {
                        $error = 'Database error while renaming topic: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to prepare topic rename statement.';
                }
            }
        }
    }
}

$topicFilter = trim($_GET['topic'] ?? '');

$topicCounts = [];
$topicCountsResult = $conn->query(
    "SELECT topic, COUNT(*) AS total 
     FROM AIGeneratedMCQs 
     WHERE topic IS NOT NULL AND topic <> '' 
     GROUP BY topic 
     ORDER BY total DESC, topic ASC"
);
if ($topicCountsResult) {
    while ($row = $topicCountsResult->fetch_assoc()) {
        $topicCounts[] = $row;
    }
}

$overallTotal = 0;
$totalRes = $conn->query("SELECT COUNT(*) AS cnt FROM AIGeneratedMCQs");
if ($totalRes && ($row = $totalRes->fetch_assoc())) {
    $overallTotal = (int)$row['cnt'];
}

$whereSql = '';
$whereParams = [];
$whereTypes = '';

if ($topicFilter !== '') {
    $whereSql = 'WHERE topic LIKE ?';
    $whereParams[] = '%' . $topicFilter . '%';
    $whereTypes .= 's';
}

$totalFiltered = 0;
if ($whereSql === '') {
    $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM AIGeneratedMCQs");
    if ($cntRes && ($row = $cntRes->fetch_assoc())) {
        $totalFiltered = (int)$row['cnt'];
    }
} else {
    $cntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM AIGeneratedMCQs $whereSql");
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

$mcqs = [];
if ($whereSql === '') {
    $sql = "SELECT m.id, m.topic, m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option, m.generated_at, v.verification_status, v.last_checked_at 
            FROM AIGeneratedMCQs m
            LEFT JOIN AIMCQsVerification v ON m.id = v.mcq_id
            ORDER BY m.generated_at DESC" . $limitSql;
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $mcqs[] = $row;
        }
    }
} else {
    $sql = "SELECT m.id, m.topic, m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option, m.generated_at, v.verification_status, v.last_checked_at 
            FROM AIGeneratedMCQs m
            LEFT JOIN AIMCQsVerification v ON m.id = v.mcq_id
            $whereSql 
            ORDER BY m.generated_at DESC" . $limitSql;
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($whereTypes !== '') {
            $stmt->bind_param($whereTypes, ...$whereParams);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $mcqs[] = $row;
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
    <title>Manage AI Generated MCQs</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .ai-mcqs-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        .ai-mcqs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .ai-mcqs-header h1 {
            margin: 0;
        }
        .ai-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .ai-card {
            background: #fff;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .ai-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
        }
        .ai-card p {
            margin: 0.15rem 0;
            font-size: 0.9rem;
            color: #555;
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .topic-table-wrapper {
            max-height: 260px;
            overflow-y: auto;
            margin-bottom: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            background: #fff;
        }
        .topic-table {
            width: 100%;
            border-collapse: collapse;
        }
        .topic-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: top;
            width: 33.33%;
        }
        .topic-item {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 0.5rem 0.75rem;
        }
        .topic-item-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .topic-item-count {
            font-size: 0.85rem;
            color: #555;
        }
        .topic-search-bar {
            margin-bottom: 0.5rem;
        }
        .topic-search-bar input {
            width: 100%;
            max-width: 320px;
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
        }
        .topic-link {
            color: #007bff;
            text-decoration: none;
        }
        .topic-link:hover {
            text-decoration: underline;
        }
        .mcq-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .mcq-table th,
        .mcq-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e0e0e0;
            text-align: left;
            vertical-align: top;
            font-size: 0.9rem;
        }
        .mcq-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .mcq-options-display {
            margin-top: 4px;
            padding: 6px 8px;
            background-color: #f5f5f5;
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }
        .mcq-options-display div {
            margin-bottom: 2px;
        }
        .mcq-options-display strong {
            display: inline-block;
            width: 20px;
            color: #007bff;
        }
        .mcq-correct {
            font-weight: bold;
            color: #28a745;
            text-align: center;
        }
        .ai-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .ai-actions form {
            display: inline-block;
        }
        .btn {
            padding: 0.35rem 0.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .btn-edit {
            background: #007bff;
            color: #fff;
        }
        .btn-delete {
            background: #dc3545;
            color: #fff;
        }
        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }
        .filter-form {
            margin-bottom: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .filter-form select,
        .filter-form input {
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
        }
        .pagination {
            margin: 1rem 0;
            text-align: center;
        }
        .pagination a {
            display: inline-block;
            margin: 0 3px;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            border: 1px solid #007bff;
            color: #007bff;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .pagination a.active,
        .pagination a:hover {
            background: #007bff;
            color: #fff;
        }
        .edit-row {
            background: #fafafa;
        }
        .edit-row form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
        }
        .edit-row textarea {
            min-height: 80px;
            resize: vertical;
        }
        .topic-rename-card {
            background: #fff;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .topic-rename-card h3 {
            margin-top: 0;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }
        .topic-rename-form {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .topic-rename-form input {
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
            min-width: 160px;
        }
        .btn-primary {
            background: #007bff;
            color: #fff;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pending { background: #e9ecef; color: #495057; }
        .status-verified { background: #d4edda; color: #155724; }
        .status-corrected { background: #cce5ff; color: #004085; }
        .status-flagged { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>

    <div class="ai-mcqs-container">
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>

        <div class="ai-mcqs-header">
            <h1>Manage AI Generated MCQs</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="ai-summary">
            <div class="ai-card">
                <h3>AI Generated MCQs</h3>
                <p><strong>Total:</strong> <?= (int)$overallTotal ?></p>
                <p><strong>Topics:</strong> <?= count($topicCounts) ?></p>
            </div>
            <?php if ($topicFilter !== ''): ?>
            <div class="ai-card">
                <h3>Current Topic</h3>
                <p><strong>Topic:</strong> <?= htmlspecialchars($topicFilter) ?></p>
                <p><strong>MCQs:</strong> <?= (int)$totalFiltered ?></p>
                <p>
                    <a href="manage_ai_mcqs.php" class="topic-link">Clear topic filter</a>
                </p>
            </div>
            <?php endif; ?>
            <div class="ai-card">
                <h3>AI Verification</h3>
                <div class="d-grid gap-2">
                    <a href="verify_ai_mcqs.php" class="btn btn-primary"><i class="fas fa-robot me-2"></i>Open Verification Center</a>
                    <a href="verify_ai_mcqs.php?tab=report" class="btn btn-outline-info"><i class="fas fa-list-check me-2"></i>View Verified/Corrected MCQs</a>
                </div>
                
                <hr style="margin: 15px 0;">
                
                <form action="verify_ai_mcqs.php" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="mode" value="range">
                    <div class="col-auto">
                        <input type="number" name="start" class="form-control form-control-sm" placeholder="Start ID" required>
                    </div>
                    <div class="col-auto">
                        <span class="text-muted">-</span>
                    </div>
                    <div class="col-auto">
                        <input type="number" name="end" class="form-control form-control-sm" placeholder="End ID" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Check Range</button>
                    </div>
                </form>
            </div>
            <div class="topic-rename-card">
                <h3>Search & Replace Topic Name</h3>
                <form method="POST" class="topic-rename-form" onsubmit="return confirm('Replace topic name for all matching AI MCQs?');">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="rename_topic">
                    <label>
                        Old Topic:
                        <input type="text" name="old_topic" required placeholder="Exact old topic">
                    </label>
                    <label>
                        New Topic:
                        <input type="text" name="new_topic" required placeholder="New topic name">
                    </label>
                    <button type="submit" class="btn btn-primary">Replace</button>
                </form>
            </div>
        </div>

        <h2>Topics Overview</h2>
        <?php if (empty($topicCounts)): ?>
            <p>No AI generated MCQs found.</p>
        <?php else: ?>
            <div class="topic-search-bar">
                <input type="text" id="topicSearchInput" placeholder="Search topics...">
            </div>
            <div class="topic-table-wrapper">
                <table class="topic-table" id="topicTable">
                    <tbody>
                        <?php
                            $colsPerRow = 3;
                            $totalTopics = count($topicCounts);
                            for ($i = 0; $i < $totalTopics; $i += $colsPerRow):
                        ?>
                            <tr>
                                <?php for ($j = 0; $j < $colsPerRow; $j++):
                                    $index = $i + $j;
                                ?>
                                    <td>
                                        <?php if ($index < $totalTopics):
                                            $row = $topicCounts[$index];
                                        ?>
                                            <div class="topic-item">
                                                <a class="topic-link" href="?<?= http_build_query(array_merge($_GET, ['topic' => $row['topic'], 'page' => 1])) ?>">
                                                    <div class="topic-item-name"><?= htmlspecialchars($row['topic']) ?></div>
                                                </a>
                                                <div class="topic-item-count">MCQs: <?= (int)$row['total'] ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h2>AI MCQs List</h2>

        <form method="GET" class="filter-form">
            <label>
                Topic:
                <input type="text" name="topic" value="<?= htmlspecialchars($topicFilter) ?>" placeholder="Filter by topic (supports partial match)">
            </label>
            <label>
                Per page:
                <select name="per_page">
                    <option value="10" <?= $perPageParam == '10' ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $perPageParam == '20' ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $perPageParam == '50' ? 'selected' : '' ?>>50</option>
                    <option value="all" <?= $perPageParam === 'all' ? 'selected' : '' ?>>View All</option>
                </select>
            </label>
            <button type="submit" class="btn btn-secondary">Apply</button>
            <?php if ($topicFilter !== '' || $perPageParam !== '20' || $page !== 1): ?>
                <a href="manage_ai_mcqs.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>

        <table class="mcq-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Topic</th>
                    <th>Question</th>
                    <th>Options</th>
                    <th>Correct</th>
                    <th>Status</th>
                    <th>Last Checked</th>
                    <th>Generated At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mcqs)): ?>
                    <tr>
                        <td colspan="9">No AI MCQs found for the selected filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($mcqs as $mcq): ?>
                        <?php
                            $coText = trim($mcq['correct_option'] ?? '');
                            $label = '';
                            if ($coText !== '') {
                                if (strcasecmp($coText, $mcq['option_a'] ?? '') === 0) $label = 'A';
                                elseif (strcasecmp($coText, $mcq['option_b'] ?? '') === 0) $label = 'B';
                                elseif (strcasecmp($coText, $mcq['option_c'] ?? '') === 0) $label = 'C';
                                elseif (strcasecmp($coText, $mcq['option_d'] ?? '') === 0) $label = 'D';
                            }
                        ?>
                        <tr>
                            <td><?= (int)$mcq['id'] ?></td>
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
                            <td class="mcq-correct">
                                <?php if ($label !== ''): ?>
                                    <?= $label ?>
                                <?php else: ?>
                                    Not Set
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $status = $mcq['verification_status'] ?? 'pending';
                                    $badgeClass = 'status-pending';
                                    if ($status === 'verified') $badgeClass = 'status-verified';
                                    elseif ($status === 'corrected') $badgeClass = 'status-corrected';
                                    elseif ($status === 'flagged') $badgeClass = 'status-flagged';
                                ?>
                                <span class="status-badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span>
                            </td>
                            <td>
                                <?= $mcq['last_checked_at'] ? htmlspecialchars($mcq['last_checked_at']) : '-' ?>
                            </td>
                            <td><?= htmlspecialchars($mcq['generated_at']) ?></td>
                            <td>
                                <div class="ai-actions">
                                    <form method="POST" onsubmit="return confirm('Delete this AI MCQ?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$mcq['id'] ?>">
                                        <button type="submit" class="btn btn-delete">Delete</button>
                                    </form>
                                    <button type="button" class="btn btn-edit" onclick="document.getElementById('edit-ai-<?= (int)$mcq['id'] ?>').style.display='table-row'">
                                        Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr id="edit-ai-<?= (int)$mcq['id'] ?>" class="edit-row" style="display:none;">
                            <td colspan="9">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int)$mcq['id'] ?>">

                                    <div>
                                        <label>Topics (comma-separated)</label>
                                        <input type="text" name="topic" value="<?= htmlspecialchars($mcq['topic'] ?? '') ?>" style="width:100%;">
                                    </div>
                                    <div>
                                        <label>Question</label>
                                        <textarea name="question" style="width:100%;"><?= htmlspecialchars($mcq['question']) ?></textarea>
                                    </div>
                                    <div>
                                        <label>Option A</label>
                                        <input type="text" name="option_a" value="<?= htmlspecialchars($mcq['option_a'] ?? '') ?>" style="width:100%;">
                                    </div>
                                    <div>
                                        <label>Option B</label>
                                        <input type="text" name="option_b" value="<?= htmlspecialchars($mcq['option_b'] ?? '') ?>" style="width:100%;">
                                    </div>
                                    <div>
                                        <label>Option C</label>
                                        <input type="text" name="option_c" value="<?= htmlspecialchars($mcq['option_c'] ?? '') ?>" style="width:100%;">
                                    </div>
                                    <div>
                                        <label>Option D</label>
                                        <input type="text" name="option_d" value="<?= htmlspecialchars($mcq['option_d'] ?? '') ?>" style="width:100%;">
                                    </div>
                                    <?php
                                        $selA = ($label === 'A') ? 'selected' : '';
                                        $selB = ($label === 'B') ? 'selected' : '';
                                        $selC = ($label === 'C') ? 'selected' : '';
                                        $selD = ($label === 'D') ? 'selected' : '';
                                    ?>
                                    <div>
                                        <label>Correct Option</label>
                                        <select name="correct_option" style="width:100%;">
                                            <option value="">Select Correct Option</option>
                                            <option value="A" <?= $selA ?>>Option A</option>
                                            <option value="B" <?= $selB ?>>Option B</option>
                                            <option value="C" <?= $selC ?>>Option C</option>
                                            <option value="D" <?= $selD ?>>Option D</option>
                                        </select>
                                    </div>
                                    <div style="grid-column: 1 / -1; margin-top: 0.5rem;">
                                        <button type="submit" class="btn btn-edit">Save</button>
                                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('edit-ai-<?= (int)$mcq['id'] ?>').style.display='none'">Cancel</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (!$viewAll && $totalPages > 1): ?>
            <div class="pagination">
                <p>Showing <?= $totalFiltered ? ($offset + 1) : 0 ?>-<?= min($offset + $perPage, $totalFiltered) ?> of <?= $totalFiltered ?> MCQs</p>
                <div>
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Previous</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next →</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($viewAll): ?>
            <div class="pagination">
                <p><strong>Viewing all <?= $totalFiltered ?> MCQs</strong></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="ai-loader-overlay" id="aiLoader">
        <div class="ai-loader-box">
            <div class="ai-loader-spinner"></div>
            <div class="ai-loader-title">Verifying MCQs with AI</div>
            <div class="ai-loader-status" id="aiLoaderStatus">Initializing check process...</div>
            
            <div class="ai-progress-container">
                <div class="ai-progress-bar" id="aiProgressBar"></div>
            </div>
            
            <div class="ai-loader-details">
                <div class="ai-stat-item">
                    <strong id="statChecked">0</strong>
                    Checked
                </div>
                <div class="ai-stat-item">
                    <strong id="statVerified" style="color:#28a745">0</strong>
                    Verified
                </div>
                <div class="ai-stat-item">
                    <strong id="statCorrected" style="color:#007bff">0</strong>
                    Corrected
                </div>
                <div class="ai-stat-item">
                    <strong id="statFlagged" style="color:#dc3545">0</strong>
                    Flagged
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../footer.php'; ?>
    <script>
    (function() {
        var input = document.getElementById('topicSearchInput');
        if (input) {
            input.addEventListener('input', function() {
                var q = input.value.toLowerCase();
                var rows = document.querySelectorAll('#topicTable tbody tr');
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    var names = row.querySelectorAll('.topic-item-name');
                    var match = false;
                    for (var j = 0; j < names.length; j++) {
                        var text = names[j].textContent || names[j].innerText || '';
                        if (text.toLowerCase().indexOf(q) !== -1) {
                            match = true;
                            break;
                        }
                    }
                    row.style.display = (q === '' || match) ? '' : 'none';
                }
            });
        }
    })();
    </script>
</body>
</html>
