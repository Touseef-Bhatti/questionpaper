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

<div class="container-fluid">
    <?php if (!$hasKey): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>AI Key Missing!</strong> The <code>GENERATING_KEYWORDS_KEY</code> is not set in your environment configuration. AI keyword generation will not work.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-tags me-2"></i>Topic Management</h1>
            <p class="text-muted">Manage AI-generated topics and their keywords</p>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="btn-group">
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
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
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
            <div class="card border-left-warning shadow h-100 py-2">
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
    <div class="card shadow mb-4">
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
            <div class="table-responsive">
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
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
.text-xs { font-size: .7rem; }
.italic { font-style: italic; }
.topic-name-cell { font-weight: 600; color: #2c3e50; }
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
</body>
</html>
