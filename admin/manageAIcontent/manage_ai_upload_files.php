<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

include_once __DIR__ . '/../header.php';

$message = '';
$error = '';

/**
 * Manual retention cleanup for files older than 48 hours.
 */
function adminCleanupExpiredUploadFiles(mysqli $conn, int $maxRows = 500): int
{
    $projectRoot = dirname(__DIR__, 2);
    $deleted = 0;
    $sql = "SELECT id, relative_path
            FROM AIDocumentUploads
            WHERE created_at < (NOW() - INTERVAL 48 HOUR)
              AND relative_path IS NOT NULL
              AND relative_path <> ''
            ORDER BY id ASC
            LIMIT ?";
    $st = $conn->prepare($sql);
    if (!$st) {
        return 0;
    }
    $st->bind_param('i', $maxRows);
    $st->execute();
    $res = $st->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rid = intval($row['id'] ?? 0);
            $rel = str_replace('\\', '/', (string) ($row['relative_path'] ?? ''));
            if ($rel === '') {
                continue;
            }
            $abs = rtrim($projectRoot, '/\\') . '/' . ltrim($rel, '/');
            $deletedThis = false;
            if (is_file($abs) && @unlink($abs)) {
                $deleted++;
                $deletedThis = true;
            }
            if ($rid > 0 && ($deletedThis || !is_file($abs))) {
                $up = $conn->prepare("UPDATE AIDocumentUploads SET relative_path = '' WHERE id = ?");
                if ($up) {
                    $up->bind_param('i', $rid);
                    $up->execute();
                    $up->close();
                }
            }
        }
    }
    $st->close();
    return $deleted;
}

$tableExists = false;
$chk = $conn->query("SHOW TABLES LIKE 'AIDocumentUploads'");
if ($chk && $chk->num_rows > 0) {
    $tableExists = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'cleanup_expired') {
            $deleted = adminCleanupExpiredUploadFiles($conn, 1000);
            $message = "Cleanup complete. Deleted {$deleted} expired file(s) older than 48 hours.";
            logAdminAction('cleanup_ai_upload_files', "Deleted expired upload files: {$deleted}");
        }
    }
}

$stats = [
    'total_uploads' => 0,
    'active_files' => 0,
    'expired_files' => 0,
    'pending_recheck' => 0,
];

$rows = [];
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;
$totalRows = 0;

if ($tableExists) {
    $totalRes = $conn->query("SELECT COUNT(*) AS cnt FROM AIDocumentUploads");
    if ($totalRes && ($r = $totalRes->fetch_assoc())) {
        $totalRows = intval($r['cnt'] ?? 0);
    }

    $statsSql = "SELECT
                    COUNT(*) AS total_uploads,
                    SUM(CASE WHEN created_at >= (NOW() - INTERVAL 48 HOUR) THEN 1 ELSE 0 END) AS active_files,
                    SUM(CASE WHEN created_at < (NOW() - INTERVAL 48 HOUR) THEN 1 ELSE 0 END) AS expired_files,
                    SUM(CASE WHEN recheck_status IN ('pending','processing') THEN 1 ELSE 0 END) AS pending_recheck
                 FROM AIDocumentUploads";
    $statsRes = $conn->query($statsSql);
    if ($statsRes && ($s = $statsRes->fetch_assoc())) {
        $stats['total_uploads'] = intval($s['total_uploads'] ?? 0);
        $stats['active_files'] = intval($s['active_files'] ?? 0);
        $stats['expired_files'] = intval($s['expired_files'] ?? 0);
        $stats['pending_recheck'] = intval($s['pending_recheck'] ?? 0);
    }

    $sql = "SELECT id, user_id, original_filename, stored_filename, relative_path, mime_type, file_size, file_sha256, ext, detected_topic, recheck_status, created_at
            FROM AIDocumentUploads
            ORDER BY id DESC
            LIMIT ? OFFSET ?";
    $st = $conn->prepare($sql);
    if ($st) {
        $st->bind_param('ii', $perPage, $offset);
        $st->execute();
        $res = $st->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $st->close();
    }
}

$totalPages = max(1, (int) ceil($totalRows / $perPage));
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">AI Upload Files Review</h2>
        <?php if ($tableExists): ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                <input type="hidden" name="action" value="cleanup_expired">
                <button type="submit" class="btn btn-outline-danger btn-sm">Delete Expired (48h+) Files</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning">`AIDocumentUploads` table is not available. Run `install.php` first.</div>
    <?php else: ?>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Total Upload Records</div><div class="h4 mb-0"><?= (int) $stats['total_uploads'] ?></div></div></div>
            <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Active Files (&lt;=48h)</div><div class="h4 mb-0 text-success"><?= (int) $stats['active_files'] ?></div></div></div>
            <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Expired Files (&gt;48h)</div><div class="h4 mb-0 text-danger"><?= (int) $stats['expired_files'] ?></div></div></div>
            <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Pending Recheck</div><div class="h4 mb-0 text-warning"><?= (int) $stats['pending_recheck'] ?></div></div></div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Original File</th>
                            <th>Stored Path</th>
                            <th>Topic</th>
                            <th>Recheck</th>
                            <th>Size</th>
                            <th>Age</th>
                            <th>SHA256</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No upload records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                $created = strtotime((string) ($r['created_at'] ?? ''));
                                $hours = $created ? floor((time() - $created) / 3600) : null;
                                $isExpired = $hours !== null && $hours > 48;
                                $relPath = (string) ($r['relative_path'] ?? '');
                                $fileUrl = $relPath !== '' ? ($baseUrl . ltrim(str_replace('\\', '/', $relPath), '/')) : '';
                                $sha = (string) ($r['file_sha256'] ?? '');
                                ?>
                                <tr>
                                    <td><?= (int) ($r['id'] ?? 0) ?></td>
                                    <td><?= (int) ($r['user_id'] ?? 0) ?></td>
                                    <td title="<?= htmlspecialchars((string) ($r['original_filename'] ?? '')) ?>">
                                        <?= htmlspecialchars(mb_strimwidth((string) ($r['original_filename'] ?? ''), 0, 40, '...')) ?>
                                    </td>
                                    <td>
                                        <?php if ($fileUrl !== ''): ?>
                                            <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" rel="noopener">Open file</a>
                                        <?php else: ?>
                                            <span class="text-muted">No path</span>
                                        <?php endif; ?>
                                        <div class="small text-muted"><?= htmlspecialchars(mb_strimwidth($relPath, 0, 40, '...')) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($r['detected_topic'] ?? '')) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars((string) ($r['recheck_status'] ?? '')) ?></span></td>
                                    <td><?= number_format(((int) ($r['file_size'] ?? 0)) / 1024, 1) ?> KB</td>
                                    <td>
                                        <?php if ($hours === null): ?>
                                            <span class="text-muted">Unknown</span>
                                        <?php else: ?>
                                            <span class="<?= $isExpired ? 'text-danger' : 'text-success' ?>"><?= (int) $hours ?>h</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-monospace"><?= htmlspecialchars($sha !== '' ? substr($sha, 0, 14) . '...' : '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../footer.php'; ?>
