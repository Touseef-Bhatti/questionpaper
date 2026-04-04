<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

// Fetch latest search queries from both tables
$mcqsSearchResults = [];
$paperSearchResults = [];

$mcqsStmt = $conn->prepare("SELECT h.id, h.user_id, u.email AS user_email, h.query_text, h.created_at FROM mcqs_topic_search_history h LEFT JOIN `users` u ON h.user_id = u.id ORDER BY h.created_at DESC LIMIT 200");
if ($mcqsStmt) {
    $mcqsStmt->execute();
    $mcqsSearchResults = $mcqsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $mcqsStmt->close();
}

$paperStmt = $conn->prepare("SELECT h.id, h.user_id, u.email AS user_email, h.query_text, h.created_at FROM question_paper_topic_search_history h LEFT JOIN `users` u ON h.user_id = u.id ORDER BY h.created_at DESC LIMIT 200");
if ($paperStmt) {
    $paperStmt->execute();
    $paperSearchResults = $paperStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $paperStmt->close();
}

?>
<?php include __DIR__ . '/header.php'; ?>
<div class="admin-container">
    <div class="top">
        <h1>Search Query Activity</h1>
        <div>
            <a class="btn btn-secondary" href="promotional_emails.php">Send Promotional Email</a>
        </div>
    </div>

    <div class="overview-section">
        <h2>MCQs Topic Search History</h2>
        <p>Total tracked queries: <?= count($mcqsSearchResults) ?></p>
        <div class="data-table-wrap">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Search Term</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mcqsSearchResults as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['user_email'] ?: 'guest') ?></td>
                        <td><?= htmlspecialchars($row['query_text']) ?></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="overview-section mt-4">
        <h2>Question Paper Topic Search History</h2>
        <p>Total tracked queries: <?= count($paperSearchResults) ?></p>
        <div class="data-table-wrap">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Search Term</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($paperSearchResults as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['user_email'] ?: 'guest') ?></td>
                        <td><?= htmlspecialchars($row['query_text']) ?></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
