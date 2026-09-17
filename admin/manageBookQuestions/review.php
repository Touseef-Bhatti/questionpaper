<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

$jobId = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($_GET['job_id'] ?? '')));
$csrfToken = generateCSRFToken();

include_once __DIR__ . '/../header.php';
?>

<style>
/* Scoped responsive styling for Review Page */
.review-container {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem 0.75rem 3rem;
}

@media (min-width: 768px) {
    .review-container {
        padding: 1.5rem 1.25rem 3.5rem;
    }
}

/* Override admin.css 24px padding on cards */
.review-container .card {
    padding: 0 !important;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
}

.review-container .card::before {
    display: none !important;
}

.review-container .card-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 0.85rem 1.15rem;
}

@media (min-width: 768px) {
    .review-container .card-header {
        padding: 1rem 1.35rem;
    }
    .review-container .card-body {
        padding: 1.25rem 1.35rem;
    }
}

/* Page Hero */
.review-hero {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    border-radius: 12px;
    color: #ffffff;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 4px 15px rgba(30, 60, 114, 0.15);
}

.review-hero h1 {
    font-size: 1.35rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: #ffffff;
}

@media (min-width: 768px) {
    .review-hero h1 {
        font-size: 1.65rem;
    }
}

.review-hero p {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.85);
    margin-bottom: 0;
}

/* Action & Filter Toolbar */
.toolbar-sticky {
    position: sticky;
    top: 64px;
    z-index: 90;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.25rem;
}

/* Filter pills */
.filter-btn {
    border-radius: 20px;
    font-size: 0.82rem;
    padding: 0.25rem 0.85rem;
    font-weight: 500;
}

/* Draft Item Card */
.draft-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem 1.15rem;
    background: #ffffff;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
    position: relative;
    border-left: 5px solid #94a3b8;
}

.draft-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    border-color: #cbd5e1;
}

.draft-card[data-kind="mcq"] {
    border-left-color: #2563eb;
}

.draft-card[data-kind="short"] {
    border-left-color: #0891b2;
}

.draft-card[data-kind="long"] {
    border-left-color: #d97706;
}

/* Urdu & Multilingual support */
.question-text, .option-input {
    font-size: 0.95rem;
    line-height: 1.6;
    font-family: 'Segoe UI', system-ui, -apple-system, 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', Tahoma, sans-serif;
    border-radius: 7px;
}

.option-badge {
    min-width: 32px;
    font-weight: 700;
    text-align: center;
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    border-right: none;
    border-top-left-radius: 7px;
    border-bottom-left-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.option-input {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

/* Floating mobile bottom actions when selected */
.mobile-bulk-bar {
    position: fixed;
    bottom: 16px;
    left: 12px;
    right: 12px;
    z-index: 1000;
    background: #1e293b;
    color: #ffffff;
    border-radius: 10px;
    padding: 0.65rem 1rem;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
}

/* Touch friendly buttons */
.btn {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
}

@media (max-width: 575.98px) {
    .btn-mobile-grow {
        flex: 1 1 auto;
    }
}
</style>

<div class="review-container">
    <!-- Header Hero -->
    <div class="review-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <h1><i class="fa-solid fa-clipboard-check me-2"></i>Review Generated Questions</h1>
                <?php if ($jobId !== ''): ?>
                    <span class="badge bg-light text-dark py-1 px-2" style="font-size: 0.75rem;">Job: <?= htmlspecialchars(substr($jobId, 0, 12)) ?>...</span>
                <?php endif; ?>
            </div>
            <p>Inspect, edit, or reject questions before publishing them directly into the official question banks.</p>
        </div>
        <div>
            <a href="generate_from_book.php" class="btn btn-light w-100" title="Back to Book Generator">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Generator
            </a>
        </div>
    </div>

    <div id="alertBox" class="alert d-none mb-3" role="alert"></div>

    <?php if ($jobId === ''): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
            <i class="fa-solid fa-circle-exclamation fs-4"></i>
            <div><strong>Error:</strong> Missing generation job ID. Please select a job from the <a href="generate_from_book.php" class="alert-link">generator page</a>.</div>
        </div>
    <?php else: ?>
        <!-- Main Review Wrapper -->
        <div class="card mb-4">
            <!-- Filter & Bulk Actions Sticky Toolbar -->
            <div class="card-header border-bottom-0 pb-2">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <!-- Left: Count & Filter Pills -->
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-bold text-dark fs-6">Questions for Review</span>
                            <span class="badge bg-primary rounded-pill px-2 py-1" id="draftCount">0 pending</span>
                        </div>
                        <div class="d-flex gap-1 flex-wrap mt-2" id="filterPills">
                            <button type="button" class="btn btn-sm btn-primary filter-btn" data-filter="all" onclick="setFilter('all')">
                                All (<span id="countFilterAll">0</span>)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary filter-btn" data-filter="mcq" onclick="setFilter('mcq')">
                                MCQs (<span id="countFilterMcq">0</span>)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary filter-btn" data-filter="short" onclick="setFilter('short')">
                                Short (<span id="countFilterShort">0</span>)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary filter-btn" data-filter="long" onclick="setFilter('long')">
                                Long (<span id="countFilterLong">0</span>)
                            </button>
                        </div>
                    </div>

                    <!-- Right: Bulk Actions Buttons -->
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <button type="button" class="btn btn-success btn-sm btn-mobile-grow" id="approveSelectedBtn" disabled>
                            <i class="fa-solid fa-check-double me-1"></i>Approve Selected
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm btn-mobile-grow" id="approveAllBtn">
                            <i class="fa-solid fa-circle-check me-1"></i>Approve All
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-mobile-grow" id="discardBtn">
                            <i class="fa-solid fa-trash-can me-1"></i>Discard All
                        </button>
                    </div>
                </div>

                <!-- Select All Bar -->
                <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2 flex-wrap gap-2">
                    <div class="form-check d-flex align-items-center">
                        <input class="form-check-input me-2" type="checkbox" id="selectAllDrafts" style="width: 1.15rem; height: 1.15rem; cursor: pointer;">
                        <label class="form-check-label small fw-semibold text-secondary" for="selectAllDrafts" style="cursor: pointer;">
                            Select all visible questions
                        </label>
                    </div>
                    <div class="small text-muted" id="selectionStatus">
                        0 selected
                    </div>
                </div>
            </div>

            <!-- List of Question Cards -->
            <div class="card-body pt-1">
                <div id="draftList" class="d-flex flex-column gap-3">
                    <div class="text-center text-muted py-5">
                        <i class="fa-solid fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                        <div>Loading generated questions...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Floating Sticky Selection Bar (shows when items are checked on mobile) -->
        <div class="mobile-bulk-bar d-none" id="mobileFloatingBar">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-check-circle text-success"></i>
                <span class="small fw-bold" id="mobileSelectedCount">0 selected</span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-success" onclick="approveDrafts(selectedDrafts())">
                    Approve
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const jobId = <?= json_encode($jobId) ?>;
const csrfToken = <?= json_encode($csrfToken) ?>;
const apiUrl = 'api.php';
let drafts = [];
let activeFilter = 'all';

function showAlert(message, type = 'danger') {
    const box = document.getElementById('alertBox');
    const icons = {
        success: 'fa-check-circle',
        warning: 'fa-triangle-exclamation',
        danger: 'fa-circle-xmark',
        info: 'fa-circle-info'
    };
    const icon = icons[type] || 'fa-circle-info';
    box.className = `alert alert-${type} d-flex align-items-center gap-2`;
    box.innerHTML = `<i class="fa-solid ${icon} fs-5 flex-shrink-0"></i><div>${escapeHtml(message)}</div>`;
    box.classList.remove('d-none');
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function postForm(fd) {
    fd.append('csrf_token', csrfToken);
    if (jobId) fd.append('job_id', jobId);
    try {
        const res = await fetch(apiUrl, { method: 'POST', body: fd });
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            showAlert('Invalid JSON response: ' + text.slice(0, 500), 'danger');
            return null;
        }
        if (!data.ok) {
            showAlert(data.error || 'Request failed.', 'danger');
        }
        return data;
    } catch (err) {
        showAlert('Network error: ' + err.message, 'danger');
        return null;
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
}

function setFilter(kind) {
    activeFilter = kind;
    document.querySelectorAll('#filterPills button').forEach(btn => {
        if (btn.dataset.filter === kind) {
            btn.className = 'btn btn-sm btn-primary filter-btn';
        } else {
            btn.className = 'btn btn-sm btn-outline-secondary filter-btn';
        }
    });
    renderDrafts();
}

function updateCounts() {
    const total = drafts.length;
    const mcqs = drafts.filter(d => d.question_kind === 'mcq').length;
    const shorts = drafts.filter(d => d.question_kind === 'short').length;
    const longs = drafts.filter(d => d.question_kind === 'long').length;

    document.getElementById('draftCount').textContent = `${total} pending`;
    document.getElementById('countFilterAll').textContent = total;
    document.getElementById('countFilterMcq').textContent = mcqs;
    document.getElementById('countFilterShort').textContent = shorts;
    document.getElementById('countFilterLong').textContent = longs;
}

function renderDrafts() {
    const list = document.getElementById('draftList');
    updateCounts();

    const filtered = activeFilter === 'all' 
        ? drafts 
        : drafts.filter(d => d.question_kind === activeFilter);

    if (!drafts.length) {
        list.innerHTML = `
            <div class="alert alert-success d-flex align-items-center gap-3 p-4 my-3">
                <i class="fa-solid fa-circle-check fs-2 text-success"></i>
                <div>
                    <h5 class="alert-heading mb-1">All Questions Approved!</h5>
                    <p class="mb-0 text-muted">No pending draft questions remain for this generation job. You can safely return to the generator.</p>
                </div>
            </div>`;
        updateApprovalControls();
        return;
    }

    if (!filtered.length) {
        list.innerHTML = `
            <div class="alert alert-light text-center py-4 border text-muted">
                <i class="fa-solid fa-filter me-1"></i>No ${escapeHtml(activeFilter.toUpperCase())} questions found in this job.
            </div>`;
        updateApprovalControls();
        return;
    }

    list.innerHTML = filtered.map(item => {
        const isMcq = item.question_kind === 'mcq';
        const badgeClass = isMcq ? 'bg-primary' : (item.question_kind === 'short' ? 'bg-info text-dark' : 'bg-warning text-dark');
        
        return `
            <div class="draft-card" data-id="${item.id}" data-kind="${escapeHtml(item.question_kind)}" data-draft-type="${escapeHtml(item.draft_type || 'question')}">
                <!-- Card Header -->
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start gap-2 mb-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="form-check m-0">
                            <input class="form-check-input draft-checkbox" type="checkbox" aria-label="Select question for approval" style="width: 1.15rem; height: 1.15rem; cursor: pointer;">
                        </div>
                        <span class="badge ${badgeClass} fw-bold text-uppercase px-2 py-1">${escapeHtml(item.question_kind)}</span>
                        <span class="text-secondary small fw-medium">
                            ${escapeHtml(item.class_name)} &bull; ${escapeHtml(item.book_name)} &bull; Ch ${escapeHtml(item.chapter_no)}: ${escapeHtml(item.chapter_name)}
                        </span>
                    </div>
                    <div class="d-flex gap-2 align-self-end align-self-sm-start mt-1 mt-sm-0">
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="approveDraft(${item.id}, '${escapeHtml(item.draft_type || 'question')}')" title="Approve this question">
                            <i class="fa-solid fa-check me-1"></i>Approve
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteDraft(${item.id})" title="Delete this question">
                            <i class="fa-solid fa-trash me-1"></i>Delete
                        </button>
                    </div>
                </div>

                <!-- Question Textarea -->
                <div class="mb-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Question Statement</label>
                    <textarea class="form-control question-text" rows="2" dir="auto">${escapeHtml(item.question_text)}</textarea>
                </div>

                ${isMcq ? `
                    <!-- MCQ Options in a clean responsive grid -->
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="option-badge px-2">A</span>
                                <input class="form-control option-input option-a" value="${escapeHtml(item.option_a)}" dir="auto">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="option-badge px-2">B</span>
                                <input class="form-control option-input option-b" value="${escapeHtml(item.option_b)}" dir="auto">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="option-badge px-2">C</span>
                                <input class="form-control option-input option-c" value="${escapeHtml(item.option_c)}" dir="auto">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="option-badge px-2">D</span>
                                <input class="form-control option-input option-d" value="${escapeHtml(item.option_d)}" dir="auto">
                            </div>
                        </div>
                        
                        <!-- Correct Answer and Difficulty side-by-side -->
                        <div class="col-6 col-sm-4 col-md-3 mt-2">
                            <label class="form-label small text-muted mb-1">Correct Answer</label>
                            <select class="form-select form-select-sm correct-option">
                                ${['A','B','C','D'].map(letter => `<option value="${letter}" ${item.correct_option === letter ? 'selected' : ''}>Option ${letter}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-6 col-sm-4 col-md-3 mt-2">
                            <label class="form-label small text-muted mb-1">Difficulty</label>
                            <select class="form-select form-select-sm difficulty-level">
                                ${['Easy','Medium','Hard'].map(level => `<option value="${level}" ${item.difficulty_level === level ? 'selected' : ''}>${level}</option>`).join('')}
                            </select>
                        </div>
                    </div>` : ''}

                <!-- Footer Save Action -->
                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="saveDraft(${item.id})">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Save Edit
                    </button>
                    <span class="small text-muted">ID: #${item.id}</span>
                </div>
            </div>`;
    }).join('');

    updateApprovalControls();
}

async function loadDrafts() {
    if (!jobId) return;
    const fd = new FormData();
    fd.append('action', 'get_drafts');
    const data = await postForm(fd);
    if (data?.ok) {
        drafts = data.items || [];
        renderDrafts();
    }
}

async function saveDraft(id) {
    const el = document.querySelector(`.draft-item[data-id="${id}"], .draft-card[data-id="${id}"]`);
    if (!el) return;
    const fd = new FormData();
    const kind = el.dataset.kind;
    const draftType = el.dataset.draftType || 'question';
    fd.append('action', 'update_draft');
    fd.append('draft_id', id);
    fd.append('question_kind', kind);
    fd.append('draft_type', draftType);
    fd.append('question_text', el.querySelector('.question-text').value);
    if (kind === 'mcq') {
        fd.append('option_a', el.querySelector('.option-a').value);
        fd.append('option_b', el.querySelector('.option-b').value);
        fd.append('option_c', el.querySelector('.option-c').value);
        fd.append('option_d', el.querySelector('.option-d').value);
        fd.append('correct_option', el.querySelector('.correct-option').value);
        fd.append('difficulty_level', el.querySelector('.difficulty-level').value);
    }
    const saveBtn = el.querySelector('button[onclick*="saveDraft"]');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Saving...';
    }
    const data = await postForm(fd);
    if (data?.ok) {
        showAlert('Draft updated successfully.', 'success');
        loadDrafts();
    } else if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i>Save Edit';
    }
}

async function deleteDraft(id) {
    const el = document.querySelector(`.draft-item[data-id="${id}"], .draft-card[data-id="${id}"]`);
    const fd = new FormData();
    fd.append('action', 'delete_draft');
    fd.append('draft_id', id);
    fd.append('draft_type', el?.dataset.draftType || 'question');
    const data = await postForm(fd);
    if (data?.ok) {
        showAlert('Question deleted.', 'info');
        loadDrafts();
    }
}

function selectedDrafts() {
    return Array.from(document.querySelectorAll('.draft-card, .draft-item'))
        .filter(item => item.querySelector('.draft-checkbox')?.checked)
        .map(item => ({
            id: Number(item.dataset.id),
            draft_type: item.dataset.draftType || 'question'
        }));
}

function updateApprovalControls() {
    const selected = selectedDrafts().length;
    const selectAll = document.getElementById('selectAllDrafts');
    const checkboxes = document.querySelectorAll('.draft-checkbox');
    const approveBtn = document.getElementById('approveSelectedBtn');
    
    if (approveBtn) {
        approveBtn.disabled = selected === 0;
        approveBtn.innerHTML = `<i class="fa-solid fa-check-double me-1"></i>Approve Selected (${selected})`;
    }
    
    if (selectAll) {
        selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
        selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
    }

    const statusEl = document.getElementById('selectionStatus');
    if (statusEl) {
        statusEl.textContent = `${selected} of ${checkboxes.length} selected`;
    }

    // Mobile floating bar visibility
    const mobileBar = document.getElementById('mobileFloatingBar');
    if (mobileBar) {
        if (selected > 0 && window.innerWidth < 768) {
            mobileBar.classList.remove('d-none');
            document.getElementById('mobileSelectedCount').textContent = `${selected} selected`;
        } else {
            mobileBar.classList.add('d-none');
        }
    }
}

async function approveDrafts(selection = null) {
    if (!drafts.length || (Array.isArray(selection) && selection.length === 0)) return;
    const fd = new FormData();
    fd.append('action', 'approve_drafts');
    if (selection !== null) {
        fd.append('drafts', JSON.stringify(selection));
    }
    const data = await postForm(fd);
    if (data?.ok) {
        showAlert(`Successfully approved ${data.approved || 0} question(s) to the question bank!`, 'success');
        loadDrafts();
    }
}

async function approveDraft(id, draftType) {
    await approveDrafts([{ id: Number(id), draft_type: draftType }]);
}

document.getElementById('approveSelectedBtn')?.addEventListener('click', () => {
    approveDrafts(selectedDrafts());
});

document.getElementById('approveAllBtn')?.addEventListener('click', () => {
    if (confirm('Approve all pending questions in this job?')) {
        approveDrafts();
    }
});

document.getElementById('selectAllDrafts')?.addEventListener('change', event => {
    document.querySelectorAll('.draft-checkbox').forEach(checkbox => {
        checkbox.checked = event.target.checked;
    });
    updateApprovalControls();
});

document.addEventListener('change', event => {
    if (event.target.matches('.draft-checkbox')) {
        updateApprovalControls();
    }
});

document.getElementById('discardBtn')?.addEventListener('click', async () => {
    if (!drafts.length || !confirm('Are you sure you want to discard all pending generated questions for this job? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('action', 'discard_drafts');
    const data = await postForm(fd);
    if (data?.ok) {
        showAlert('All draft questions discarded.', 'warning');
        loadDrafts();
    }
});

document.addEventListener('DOMContentLoaded', loadDrafts);
</script>

<?php include_once __DIR__ . '/../footer.php'; ?>
