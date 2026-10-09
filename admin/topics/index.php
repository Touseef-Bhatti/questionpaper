<?php
/**
 * admin/topics/index.php - Professional Dashboard for Managing Generated Topics
 */
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../db_connect.php';

// Verify admin access
$admin = requireAdminRole('admin');

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filter settings
$filter = $_GET['filter'] ?? 'all'; // all, no_keywords, with_keywords
$search = $_GET['search'] ?? '';

// Build Query
$where = [];
$params = [];
$types = "";

if ($filter === 'no_keywords') {
    $where[] = "(keywords IS NULL OR keywords = '')";
} elseif ($filter === 'with_keywords') {
    $where[] = "(keywords IS NOT NULL AND keywords != '')";
}

if (!empty($search)) {
    $where[] = "(topic_name LIKE ? OR source_term LIKE ? OR keywords LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= "sss";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count Total for Pagination
$countQuery = "SELECT COUNT(*) as total FROM generated_topics $whereSql";
if (!empty($params)) {
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalResult = $stmt->get_result();
} else {
    $totalResult = $conn->query($countQuery);
}
$totalRows = $totalResult->fetch_assoc()['total'] ?? 0;
$totalPages = ceil($totalRows / $limit);

// Fetch Topics
$query = "SELECT * FROM generated_topics $whereSql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$fetchParams = array_merge($params, [$limit, $offset]);
$fetchTypes = $types . "ii";
$stmt->bind_param($fetchTypes, ...$fetchParams);
$stmt->execute();
$topics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if generating key exists
EnvLoader::load();
$hasKey = !empty(getenv('GENERATING_KEYWORDS_KEY')) || !empty($_ENV['GENERATING_KEYWORDS_KEY']);

include __DIR__ . '/../header.php';
?>

<div class="container-fluid topics-admin-page">
    <?php if (!$hasKey): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>AI Key Missing!</strong> The <code>GENERATING_KEYWORDS_KEY</code> is not set in your environment configuration. AI keyword generation will not work.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row mb-4 align-items-center topics-hero">
        <div class="col-md-7 topics-hero-copy">
            <div class="topics-eyebrow"><span class="topics-eyebrow-dot"></span> Content workspace</div>
            <h1 class="h3 mb-0"><i class="fas fa-tags me-2" aria-hidden="true"></i>Topic Management</h1>
            <p>Organize AI-generated topics, keep keyword coverage healthy, and prepare content for search.</p>
        </div>
        <div class="col-md-5 text-md-end topics-hero-actions">
            <div class="btn-group topics-action-group">
                <button type="button" class="btn btn-primary" id="btnBulkGenerate" disabled>
                    <i class="fas fa-robot me-2"></i>Generate Keywords (<span id="selectedCount">0</span>)
                </button>
                <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="bulkAction('generate', 10)">Generate for next 10 (No Keywords)</a></li>
                    <li><a class="dropdown-item" href="#" onclick="bulkAction('generate', 20)">Generate for next 20 (No Keywords)</a></li>
                    <li><a class="dropdown-item" href="#" onclick="bulkAction('generate', 30)">Generate for next 30 (No Keywords)</a></li>
                    <li><a class="dropdown-item" href="#" onclick="bulkAction('generate', 40)">Generate for next 40 (No Keywords)</a></li>
                    <li><a class="dropdown-item" href="#" onclick="bulkAction('generate', 50)">Generate for next 50 (No Keywords)</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="row mb-4 topics-stats">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2 topic-stat-card topic-stat-primary">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Topics</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalRows ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-layer-group fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <?php
            $noKeywordsCount = $conn->query("SELECT COUNT(*) as cnt FROM generated_topics WHERE keywords IS NULL OR keywords = ''")->fetch_assoc()['cnt'];
            ?>
            <div class="card border-left-warning shadow h-100 py-2 topic-stat-card topic-stat-warning">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Missing Keywords</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $noKeywordsCount ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="card shadow mb-4 topics-table-card">
        <div class="card-header py-3">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select name="filter" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Topics</option>
                        <option value="no_keywords" <?= $filter === 'no_keywords' ? 'selected' : '' ?>>Missing Keywords</option>
                        <option value="with_keywords" <?= $filter === 'with_keywords' ? 'selected' : '' ?>>With Keywords</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search topics, source terms or keywords..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                        <?php if (!empty($search) || $filter !== 'all'): ?>
                            <a href="index.php" class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive" role="region" aria-label="Topics table. Scroll horizontally to view all columns." tabindex="0">
                <table class="table table-bordered table-hover" id="topicsTable" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th width="40"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                            <th width="60">ID</th>
                            <th>Topic Name</th>
                            <th>Source Term</th>
                            <th>Keywords</th>
                            <th width="150">Created At</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topics)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">No topics found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topics as $topic): ?>
                                <tr data-id="<?= $topic['id'] ?>">
                                    <td><input type="checkbox" class="topic-checkbox form-check-input" value="<?= $topic['id'] ?>"></td>
                                    <td><?= $topic['id'] ?></td>
                                    <td class="topic-name-cell"><?= htmlspecialchars($topic['topic_name']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($topic['source_term'] ?? 'N/A') ?></span></td>
                                    <td class="keywords-cell">
                                        <?php if (!empty($topic['keywords'])): ?>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach (explode(',', $topic['keywords']) as $kw): ?>
                                                    <span class="badge bg-info text-white"><?= htmlspecialchars(trim($kw)) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted italic small"><i class="fas fa-clock me-1"></i>Pending generation</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= date('M d, Y H:i', strtotime($topic['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary btn-generate-single" title="Generate Keywords">
                                                <i class="fas fa-magic"></i>
                                            </button>
                                            <button class="btn btn-outline-info btn-edit-topic" title="Edit Topic">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-delete-topic" title="Delete Topic">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>">Previous</a>
                        </li>
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Topic Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Topic</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Topic Name</label>
                        <input type="text" name="topic_name" id="editTopicName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keywords (comma-separated)</label>
                        <textarea name="keywords" id="editKeywords" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Generation Progress Modal -->
<div class="modal fade" id="progressModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">AI Keyword Generation</h5>
            </div>
            <div class="modal-body">
                <div id="progressStatus" class="mb-2">Initializing...</div>
                <div class="progress mb-3" style="height: 25px;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                </div>
                <div id="progressDetails" class="small text-muted"></div>
            </div>
            <div class="modal-footer" id="modalFooter" style="display:none;">
                <button type="button" class="btn btn-primary" onclick="location.reload()">Finish & Refresh</button>
            </div>
        </div>
    </div>
</div>

<style>
.topics-admin-page {
    --topics-ink: #17283b;
    --topics-muted: #6b7c90;
    --topics-line: #e4eaf0;
    --topics-surface: #ffffff;
    --topics-canvas: #f4f7fa;
    --topics-navy: #173b63;
    --topics-teal: #168c83;
    --topics-amber: #c97832;
    max-width: 1560px;
    padding: 1.25rem clamp(.75rem, 2vw, 2rem) 4rem;
    color: var(--topics-ink);
    background: var(--topics-canvas);
}

.topics-admin-page .alert {
    border: 1px solid #f3d48a;
    border-radius: 14px;
    box-shadow: 0 8px 20px rgba(138, 96, 24, .06);
}

.topics-hero {
    position: relative;
    overflow: hidden;
    margin-inline: 0;
    padding: clamp(1.45rem, 3vw, 2.5rem);
    border-radius: 22px;
    color: #fff;
    background:
        radial-gradient(circle at 92% 8%, rgba(84, 190, 171, .28), transparent 28%),
        linear-gradient(120deg, #122d4a 0%, #1b4868 58%, #1e6962 100%);
    box-shadow: 0 18px 38px rgba(25, 60, 84, .16);
}

.topics-hero::after {
    content: '';
    position: absolute;
    right: -64px;
    bottom: -120px;
    width: 285px;
    height: 285px;
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 50%;
    box-shadow: 0 0 0 24px rgba(255, 255, 255, .035), 0 0 0 48px rgba(255, 255, 255, .025);
    pointer-events: none;
}

.topics-hero-copy,
.topics-hero-actions { position: relative; z-index: 1; }
.topics-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: .7rem;
    color: #a8ddd3;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .14em;
    text-transform: uppercase;
}
.topics-eyebrow-dot { width: 7px; height: 7px; border-radius: 50%; background: #8ee1ce; box-shadow: 0 0 0 4px rgba(142, 225, 206, .13); }
.topics-hero h1 { color: #fff; font-size: clamp(1.7rem, 3vw, 2.55rem); font-weight: 800; letter-spacing: -.035em; }
.topics-hero h1 i { color: #9ce0d2; }
.topics-hero-copy > p { max-width: 610px; margin: .7rem 0 0; color: #c9dbe5; line-height: 1.7; }
.topics-hero-actions { display: flex; justify-content: flex-end; }
.topics-action-group { box-shadow: 0 10px 20px rgba(9, 32, 51, .16); }
.topics-action-group .btn { min-height: 44px; border-color: rgba(255,255,255,.28); }
.topics-action-group .btn-primary { border-color: #78cfc0; background: #168c83; }
.topics-action-group .btn-primary:hover { background: #11766f; }
.topics-action-group .dropdown-toggle { color: #fff; background: rgba(255,255,255,.1); }

.topics-stats { margin-inline: -.45rem; }
.topics-stats > [class*="col-"] { padding-inline: .45rem; }
.topic-stat-card {
    overflow: hidden;
    border: 1px solid var(--topics-line) !important;
    border-left: 0 !important;
    border-radius: 16px;
    background: var(--topics-surface);
    box-shadow: 0 8px 20px rgba(28, 54, 76, .055) !important;
    transition: transform .2s ease, box-shadow .2s ease;
}
.topic-stat-card:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(28, 54, 76, .09) !important; }
.topic-stat-card::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--topics-teal); }
.topic-stat-warning::before { background: var(--topics-amber); }
.topic-stat-card .card-body { padding: 1.25rem 1.35rem; }
.topic-stat-card .text-xs { color: var(--topics-muted) !important; font-size: .7rem; font-weight: 800; letter-spacing: .1em; }
.topic-stat-card .h5 { color: var(--topics-ink) !important; font-size: 1.8rem; letter-spacing: -.03em; }
.topic-stat-card .col-auto i { color: #b8c8d5 !important; font-size: 1.8rem; }
.topic-stat-warning .col-auto i { color: #e4c596 !important; }

.topics-table-card {
    overflow: hidden;
    border: 1px solid var(--topics-line);
    border-radius: 18px;
    background: var(--topics-surface);
    box-shadow: 0 10px 26px rgba(28, 54, 76, .055) !important;
}
.topics-table-card .card-header { padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--topics-line); background: #fbfcfd; }
.topics-table-card .card-body { padding: 0 1.25rem 1.25rem; }
.topics-table-card .form-select,
.topics-table-card .form-control { min-height: 44px; border-color: #d7e0e8; border-radius: 9px; color: var(--topics-ink); }
.topics-table-card .form-control::placeholder { color: #96a5b4; }
.topics-table-card .form-select:focus,
.topics-table-card .form-control:focus { border-color: var(--topics-teal); box-shadow: 0 0 0 .2rem rgba(22, 140, 131, .13); }
.topics-table-card .input-group .btn { min-width: 46px; }
.topics-table-card .input-group .btn-primary { border-color: var(--topics-teal); background: var(--topics-teal); }
.topics-table-card .input-group .btn-primary:hover { background: #11766f; }
.topics-table-card .table-responsive { overflow-x: auto; border: 1px solid var(--topics-line); border-radius: 12px; }
#topicsTable { min-width: 900px; margin: 0; color: var(--topics-ink); }
#topicsTable thead th { padding: .9rem 1rem; border-top: 0; border-bottom: 1px solid var(--topics-line); color: #718297; background: #f7f9fb; font-size: .68rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; white-space: nowrap; }
#topicsTable tbody td { padding: 1rem; border-color: #edf1f4; vertical-align: middle; }
#topicsTable tbody tr:last-child td { border-bottom: 0; }
#topicsTable tbody tr:hover { background: #fbfdfd; }
#topicsTable .form-check-input { width: 1.05rem; height: 1.05rem; margin-top: 0; border-color: #b7c5d2; }
#topicsTable .form-check-input:checked { border-color: var(--topics-teal); background-color: var(--topics-teal); }
.topic-name-cell { min-width: 220px; color: var(--topics-ink); font-weight: 750; line-height: 1.45; }
.keywords-cell { min-width: 230px; }
.keywords-cell .badge { padding: .38rem .55rem; border: 1px solid #c5e7e2; border-radius: 6px; color: #126b65 !important; background: #e8f7f4 !important; font-size: .72rem; font-weight: 700; }
#topicsTable td > .badge { padding: .4rem .6rem; border-radius: 6px; color: #536579 !important; background: #f1f5f8 !important; font-size: .73rem; font-weight: 700; }
.italic { font-style: italic; }
#topicsTable .text-muted { color: #7f8f9f !important; }
#topicsTable .btn-group { flex-wrap: wrap; }
#topicsTable .btn-group .btn { min-width: 36px; min-height: 36px; border-radius: 7px; }
#topicsTable .btn-outline-primary { color: #197a72; border-color: #9ed7cf; }
#topicsTable .btn-outline-primary:hover { color: #fff; border-color: var(--topics-teal); background: var(--topics-teal); }
#topicsTable .btn-outline-info { color: #31718d; border-color: #a9d3df; }
#topicsTable .btn-outline-danger { color: #a24d57; border-color: #e5b7bd; }
.topics-table-card .pagination { margin-bottom: 0; }
.topics-table-card .page-link { min-width: 40px; min-height: 38px; display: grid; place-items: center; border-color: var(--topics-line); color: var(--topics-navy); }
.topics-table-card .page-item.active .page-link { border-color: var(--topics-teal); background: var(--topics-teal); }

@media (max-width: 767.98px) {
    .topics-admin-page { padding: .75rem .7rem 2.5rem; }
    .topics-hero { padding: 1.25rem; border-radius: 17px; }
    .topics-hero-copy > p { font-size: .9rem; }
    .topics-hero-actions { justify-content: stretch; margin-top: 1.25rem; }
    .topics-action-group { display: flex; width: 100%; }
    .topics-action-group > .btn:first-child { flex: 1; }
    .topics-action-group .btn { min-height: 46px; }
    .topics-stats > [class*="col-"] { margin-bottom: .75rem !important; }
    .topic-stat-card .card-body { padding: 1rem 1.1rem; }
    .topics-table-card .card-header { padding: 1rem; }
    .topics-table-card .card-body { padding: 0 .7rem .8rem; }
    .topics-table-card .card-header form > [class*="col-"] { width: 100%; }
    .topics-table-card .input-group { flex-wrap: wrap; gap: .5rem; }
    .topics-table-card .input-group .form-control { flex: 1 1 100%; width: 100%; border-radius: 9px !important; }
    .topics-table-card .input-group .btn { flex: 1; border-radius: 8px !important; }
    .topics-table-card .table-responsive { overflow: visible; border: 0; }
    #topicsTable { min-width: 0; border-collapse: separate; border-spacing: 0 .75rem; }
    #topicsTable thead { display: none; }
    #topicsTable tbody, #topicsTable tr, #topicsTable td { display: block; width: 100%; }
    #topicsTable tbody tr { padding: .15rem 1rem .25rem; border: 1px solid var(--topics-line); border-radius: 13px; background: #fff; box-shadow: 0 5px 15px rgba(28, 54, 76, .045); }
    #topicsTable tbody tr:hover { background: #fff; }
    #topicsTable tbody td { display: grid; grid-template-columns: 6.25rem minmax(0, 1fr); gap: .75rem; align-items: center; padding: .7rem 0; border-bottom: 1px solid #edf1f4; text-align: left !important; }
    #topicsTable tbody td:last-child { border-bottom: 0; }
    #topicsTable tbody td::before { color: #7b8b9b; content: ''; font-size: .65rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    #topicsTable tbody td:nth-child(1)::before { content: 'Select'; }
    #topicsTable tbody td:nth-child(2)::before { content: 'ID'; }
    #topicsTable tbody td:nth-child(3)::before { content: 'Topic'; }
    #topicsTable tbody td:nth-child(4)::before { content: 'Source'; }
    #topicsTable tbody td:nth-child(5)::before { content: 'Keywords'; }
    #topicsTable tbody td:nth-child(6)::before { content: 'Created'; }
    #topicsTable tbody td:nth-child(7)::before { content: 'Actions'; }
    #topicsTable tbody td[colspan] { display: block; padding: 1.4rem .25rem; text-align: center !important; }
    #topicsTable tbody td[colspan]::before { display: none; }
    #topicsTable .topic-name-cell, #topicsTable .keywords-cell { min-width: 0; }
    #topicsTable .keywords-cell > div { justify-content: flex-start; }
    #topicsTable tbody td:last-child .btn-group { justify-content: flex-start; }
    .topics-table-card .pagination { flex-wrap: wrap; gap: .25rem; }
    .topics-table-card .pagination .page-link { min-width: 38px; }
}

@media (prefers-reduced-motion: reduce) {
    .topic-stat-card { transition: none; }
}
</style>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.topic-checkbox');
    const btnBulkGenerate = document.getElementById('btnBulkGenerate');
    const selectedCount = document.getElementById('selectedCount');
    
    function updateBulkButton() {
        const checked = document.querySelectorAll('.topic-checkbox:checked').length;
        btnBulkGenerate.disabled = checked === 0;
        selectedCount.textContent = checked;
    }

    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateBulkButton();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkButton);
    });

    // Bulk Generate Click
    btnBulkGenerate.addEventListener('click', function() {
        const ids = Array.from(document.querySelectorAll('.topic-checkbox:checked')).map(cb => cb.value);
        if (ids.length > 0) {
            startGeneration(ids);
        }
    });

    // Single Generate Click
    document.querySelectorAll('.btn-generate-single').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.closest('tr').dataset.id;
            startGeneration([id]);
        });
    });

    // Edit Action
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    document.querySelectorAll('.btn-edit-topic').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            document.getElementById('editId').value = row.dataset.id;
            document.getElementById('editTopicName').value = row.querySelector('.topic-name-cell').textContent;
            
            // Get raw keywords (not from badges)
            const kws = Array.from(row.querySelectorAll('.keywords-cell .badge')).map(b => b.textContent.trim()).join(', ');
            document.getElementById('editKeywords').value = kws;
            editModal.show();
        });
    });

    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('ajax_topic_actions.php', {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.error || 'Update failed');
        });
    });

    // Delete Action
    document.querySelectorAll('.btn-delete-topic').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const id = row.dataset.id;
            const name = row.querySelector('.topic-name-cell').textContent;
            
            if (confirm(`Are you sure you want to delete topic "${name}"?`)) {
                fetch('ajax_topic_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&id=${id}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) row.remove();
                    else alert(data.error || 'Delete failed');
                });
            }
        });
    });
});

async function startGeneration(ids) {
    const modal = new bootstrap.Modal(document.getElementById('progressModal'));
    const progressBar = document.getElementById('progressBar');
    const progressStatus = document.getElementById('progressStatus');
    const progressDetails = document.getElementById('progressDetails');
    const footer = document.getElementById('modalFooter');
    
    modal.show();
    footer.style.display = 'none';
    
    let processed = 0;
    const total = ids.length;
    
    // Process in chunks of 5 to avoid timeouts and manage UI updates
    const chunkSize = 5;
    for (let i = 0; i < total; i += chunkSize) {
        const chunk = ids.slice(i, i + chunkSize);
        progressStatus.textContent = `Generating keywords for ${i + 1} to ${Math.min(i + chunkSize, total)} of ${total}...`;
        
        try {
            const response = await fetch('ajax_generate_keywords.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ids=${JSON.stringify(chunk)}`
            });
            
            const result = await response.json();
            if (result.success) {
                processed += chunk.length;
                const percent = Math.round((processed / total) * 100);
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
                progressDetails.innerHTML += result.log || '';
            } else {
                progressDetails.innerHTML += `<div class="text-danger">Error: ${result.error || 'Unknown error'}</div>`;
            }
        } catch (e) {
            progressDetails.innerHTML += `<div class="text-danger">Fetch error: ${e.message}</div>`;
        }
    }
    
    progressStatus.textContent = "Generation complete!";
    progressBar.classList.remove('progress-bar-animated');
    footer.style.display = 'block';
}

function bulkAction(action, count) {
    if (action === 'generate') {
        const modal = new bootstrap.Modal(document.getElementById('progressModal'));
        const progressBar = document.getElementById('progressBar');
        const progressStatus = document.getElementById('progressStatus');
        const progressDetails = document.getElementById('progressDetails');
        const footer = document.getElementById('modalFooter');
        
        modal.show();
        footer.style.display = 'none';
        progressStatus.textContent = `Auto-generating keywords for next ${count} topics...`;
        progressBar.style.width = '50%';
        progressBar.textContent = 'Processing...';
        
        fetch('ajax_topic_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=auto_generate&count=${count}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                progressBar.style.width = '100%';
                progressBar.textContent = 'Complete';
                progressStatus.textContent = 'Batch processed successfully!';
                progressDetails.innerHTML = data.log || '';
                footer.style.display = 'block';
            } else {
                alert(data.error || 'Auto-generation failed');
                modal.hide();
            }
        })
        .catch(err => {
            alert('Request failed: ' + err.message);
            modal.hide();
        });
    }
}
</script>

</div> <!-- End of admin-main-content -->
<style id="topics-table-responsive-overrides">
    @media (max-width: 767.98px) {
        .topics-admin-page .topics-table-card,
        .topics-admin-page .table-responsive { overflow: visible; }

        .topics-admin-page #topicsTable {
            display: block;
            width: 100% !important;
            min-width: 0 !important;
            table-layout: auto;
        }

        .topics-admin-page #topicsTable thead { display: none; }

        .topics-admin-page #topicsTable tbody,
        .topics-admin-page #topicsTable tbody tr {
            display: block;
            width: 100%;
        }

        .topics-admin-page #topicsTable tbody tr {
            margin: 0 0 .9rem;
            padding: .8rem;
            border: 1px solid #dbe5ec;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 8px 22px rgba(24, 49, 71, .07);
        }

        .topics-admin-page #topicsTable tbody td {
            display: grid;
            grid-template-columns: minmax(5.5rem, 7.25rem) minmax(0, 1fr);
            align-items: start;
            gap: .65rem;
            width: 100%;
            min-width: 0;
            padding: .65rem .25rem;
            border: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .topics-admin-page #topicsTable tbody td::before {
            min-width: 0;
            color: #617487;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .08em;
            line-height: 1.35;
            text-transform: uppercase;
        }

        .topics-admin-page #topicsTable tbody td:nth-child(1)::before { content: "Select"; }
        .topics-admin-page #topicsTable tbody td:nth-child(2)::before { content: "ID"; }
        .topics-admin-page #topicsTable tbody td:nth-child(3)::before { content: "Topic"; }
        .topics-admin-page #topicsTable tbody td:nth-child(4)::before { content: "Source"; }
        .topics-admin-page #topicsTable tbody td:nth-child(5)::before { content: "Keywords"; }
        .topics-admin-page #topicsTable tbody td:nth-child(6)::before { content: "Created"; }
        .topics-admin-page #topicsTable tbody td:nth-child(7)::before { content: "Actions"; }

        .topics-admin-page #topicsTable tbody td:last-child {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem;
        }

        .topics-admin-page #topicsTable tbody td:last-child::before { flex: 0 0 100%; }

        .topics-admin-page #topicsTable tbody td > *,
        .topics-admin-page #topicsTable tbody td a,
        .topics-admin-page #topicsTable tbody td button,
        .topics-admin-page #topicsTable tbody td .badge {
            max-width: 100%;
        }

        .topics-admin-page #topicsTable tbody td a,
        .topics-admin-page #topicsTable tbody td button {
            white-space: normal;
            overflow-wrap: anywhere;
        }
    }
</style>
<style id="topics-theme-overrides">
    .topics-admin-page {
        --topics-ink: #2d2033;
        --topics-muted: #705f6d;
        --topics-canvas: #faf6f4;
        --topics-panel: #ffffff;
        --topics-border: #eadbdf;
        --topics-accent: #c45168;
        --topics-accent-dark: #93364d;
        --topics-accent-soft: #f8e5e9;
        --topics-warm: #c58a35;
        color: var(--topics-ink);
        background: var(--topics-canvas);
    }

    .topics-admin-page .topics-hero,
    .topics-admin-page .topics-table-card,
    .topics-admin-page .topic-stat-card {
        border-color: var(--topics-border);
    }

    .topics-admin-page .topics-hero {
        background: linear-gradient(135deg, #3b263d 0%, #6e3048 55%, #a8455c 100%);
    }

    .topics-admin-page .topics-eyebrow-dot,
    .topics-admin-page .topic-stat-primary::before {
        background: var(--topics-accent);
    }

    .topics-admin-page .topics-hero h1,
    .topics-admin-page .topics-hero p,
    .topics-admin-page .topics-hero .topics-eyebrow {
        color: #fff;
    }

    .topics-admin-page .btn-primary,
    .topics-admin-page .topics-action-group .btn-primary {
        background: var(--topics-accent);
        border-color: var(--topics-accent);
    }

    .topics-admin-page .btn-primary:hover,
    .topics-admin-page .btn-primary:focus-visible {
        background: var(--topics-accent-dark);
        border-color: var(--topics-accent-dark);
    }

    .topics-admin-page .btn-info,
    .topics-admin-page .btn-success,
    .topics-admin-page button.btn-primary,
    .topics-admin-page a.btn-primary {
        color: #fff !important;
        background: var(--topics-accent) !important;
        border-color: var(--topics-accent) !important;
    }

    .topics-admin-page .btn-info:hover,
    .topics-admin-page .btn-info:focus-visible,
    .topics-admin-page .btn-success:hover,
    .topics-admin-page .btn-success:focus-visible,
    .topics-admin-page button.btn-primary:hover,
    .topics-admin-page button.btn-primary:focus-visible,
    .topics-admin-page a.btn-primary:hover,
    .topics-admin-page a.btn-primary:focus-visible {
        color: #fff !important;
        background: var(--topics-accent-dark) !important;
        border-color: var(--topics-accent-dark) !important;
    }

    .topics-admin-page .btn-outline-primary {
        color: var(--topics-accent-dark) !important;
        background: #fff !important;
        border-color: var(--topics-accent) !important;
    }

    .topics-admin-page .btn-outline-primary:hover,
    .topics-admin-page .btn-outline-primary:focus-visible {
        color: #fff !important;
        background: var(--topics-accent) !important;
        border-color: var(--topics-accent) !important;
    }

    .topics-admin-page .btn-secondary,
    .topics-admin-page .btn-outline-secondary {
        color: var(--topics-accent-dark);
        border-color: #d9aab5;
        background: #fff;
    }

    .topics-admin-page .btn-secondary:hover,
    .topics-admin-page .btn-outline-secondary:hover {
        color: #fff;
        background: var(--topics-accent-dark);
        border-color: var(--topics-accent-dark);
    }

    .topics-admin-page .topic-stat-warning,
    .topics-admin-page .badge-warning,
    .topics-admin-page .badge.bg-warning {
        color: #684518;
        background: #f7e8c9;
        border-color: #e6c889;
    }

    .topics-admin-page .badge-primary,
    .topics-admin-page .badge.bg-primary,
    .topics-admin-page .badge-info,
    .topics-admin-page .badge.bg-info {
        color: var(--topics-accent-dark);
        background: var(--topics-accent-soft);
        border-color: #e6b6c1;
    }

    .topics-admin-page #topicsTable thead th {
        color: #674153;
        background: #fbf0f1;
        border-color: var(--topics-border);
    }

    .topics-admin-page #topicsTable tbody tr:hover {
        background: #fff7f7;
    }

    .topics-admin-page #topicsTable a,
    .topics-admin-page .pagination a {
        color: var(--topics-accent-dark);
    }

    .topics-admin-page #topicsTable a:hover,
    .topics-admin-page .pagination a:hover {
        color: #c45168;
    }
</style>
<style id="topics-horizontal-table-overrides">
    .topics-admin-page .topics-table-card {
        min-width: 0;
        overflow: hidden;
    }

    .topics-admin-page .table-responsive {
        width: 100%;
        max-width: 100%;
        overflow-x: auto !important;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-color: #b8c8d5 #f3f6f8;
        scrollbar-width: thin;
    }

    .topics-admin-page .table-responsive:focus-visible {
        outline: 3px solid rgba(196, 81, 104, .25);
        outline-offset: 3px;
    }

    .topics-admin-page #topicsTable {
        width: 100%;
        min-width: 900px;
        margin: 0;
        table-layout: fixed;
        font-size: .9rem;
        line-height: 1.5;
    }

    .topics-admin-page #topicsTable th,
    .topics-admin-page #topicsTable td {
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: normal;
    }

    .topics-admin-page #topicsTable th:nth-child(1),
    .topics-admin-page #topicsTable td:nth-child(1) { width: 64px; }
    .topics-admin-page #topicsTable th:nth-child(2),
    .topics-admin-page #topicsTable td:nth-child(2) { width: 70px; }
    .topics-admin-page #topicsTable th:nth-child(3),
    .topics-admin-page #topicsTable td:nth-child(3) { width: 220px; }
    .topics-admin-page #topicsTable th:nth-child(4),
    .topics-admin-page #topicsTable td:nth-child(4) { width: 180px; }
    .topics-admin-page #topicsTable th:nth-child(5),
    .topics-admin-page #topicsTable td:nth-child(5) { width: 260px; }
    .topics-admin-page #topicsTable th:nth-child(6),
    .topics-admin-page #topicsTable td:nth-child(6) { width: 150px; }
    .topics-admin-page #topicsTable th:nth-child(7),
    .topics-admin-page #topicsTable td:nth-child(7) { width: 140px; }

    .topics-admin-page #topicsTable td:nth-child(1),
    .topics-admin-page #topicsTable td:nth-child(2),
    .topics-admin-page #topicsTable td:nth-child(6) {
        font-variant-numeric: tabular-nums;
    }

    @media (max-width: 767.98px) {
        .topics-admin-page #topicsTable {
            display: table !important;
            width: max-content !important;
            max-width: none !important;
            min-width: 760px !important;
            table-layout: auto !important;
        }

        .topics-admin-page #topicsTable thead {
            display: table-header-group !important;
        }

        .topics-admin-page #topicsTable tbody {
            display: table-row-group !important;
        }

        .topics-admin-page #topicsTable tbody tr {
            display: table-row !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .topics-admin-page #topicsTable th,
        .topics-admin-page #topicsTable td {
            display: table-cell !important;
            width: auto !important;
            min-width: 0;
            padding: .75rem .8rem !important;
            white-space: normal;
            vertical-align: top;
        }

        .topics-admin-page #topicsTable td::before {
            content: none !important;
            display: none !important;
        }

        .topics-admin-page #topicsTable td:last-child {
            display: table-cell !important;
            white-space: nowrap;
        }

        .topics-admin-page #topicsTable td a,
        .topics-admin-page #topicsTable td button {
            white-space: nowrap;
        }
    }
</style>
</body>
</html>
