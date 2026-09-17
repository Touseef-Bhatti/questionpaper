<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

$classes = [];
$res = $conn->query('SELECT class_id, class_name FROM class ORDER BY class_id ASC');
while ($res && ($row = $res->fetch_assoc())) {
    $classes[] = $row;
}

$books = [];
$res = $conn->query('SELECT book_id, book_name, class_id FROM book ORDER BY class_id ASC, book_name ASC');
while ($res && ($row = $res->fetch_assoc())) {
    $books[] = $row;
}

$apiKeyConfigured = trim((string) EnvLoader::get('GEMINIAPIKEYFORBOOKQUESTIONS', '')) !== '';

function bookQuestionsIniBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    if ($unit === 'g') {
        $number *= 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $number *= 1024 * 1024;
    } elseif ($unit === 'k') {
        $number *= 1024;
    }
    return (int) $number;
}

$uploadLimitBytes = min(
    bookQuestionsIniBytes((string) ini_get('upload_max_filesize')),
    bookQuestionsIniBytes((string) ini_get('post_max_size'))
);
$uploadLimitMb = $uploadLimitBytes > 0 ? round($uploadLimitBytes / 1024 / 1024) : 0;
$csrfToken = generateCSRFToken();

include_once __DIR__ . '/../header.php';
?>

<style>
/* Scoped responsive styles for Textbook Question Generator */
.generator-container {
    width: 100%;
    max-width: 1440px;
    margin: 0 auto;
    padding: 1rem 0.75rem 2.5rem;
}

@media (min-width: 768px) {
    .generator-container {
        padding: 1.5rem 1.25rem 3rem;
    }
}

/* Reset admin.css 24px padding on bootstrap cards */
.generator-container .card {
    padding: 0 !important;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.generator-container .card::before {
    display: none !important;
}

.generator-container .card-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 0.85rem 1.15rem;
    font-weight: 600;
    color: #1e293b;
}

.generator-container .card-body {
    padding: 1rem 1.15rem;
}

@media (min-width: 768px) {
    .generator-container .card-header {
        padding: 1rem 1.35rem;
    }
    .generator-container .card-body {
        padding: 1.25rem 1.35rem;
    }
}

/* Header & Banner */
.generator-hero {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    border-radius: 12px;
    color: #ffffff;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 15px rgba(30, 60, 114, 0.15);
}

.generator-hero h1 {
    font-size: 1.35rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: #ffffff;
}

@media (min-width: 768px) {
    .generator-hero h1 {
        font-size: 1.65rem;
    }
}

.generator-hero p {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.85);
    margin-bottom: 0;
}

/* Saved Drafts List */
.draft-job-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.draft-job-card:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

/* Range table responsive card styling for mobile */
@media (max-width: 767.98px) {
    .ranges-table-wrapper {
        border: none;
    }
    .ranges-table-wrapper table,
    .ranges-table-wrapper thead,
    .ranges-table-wrapper tbody,
    .ranges-table-wrapper tr,
    .ranges-table-wrapper td,
    .ranges-table-wrapper th {
        display: block;
        width: 100% !important;
    }
    .ranges-table-wrapper thead {
        display: none;
    }
    .ranges-table-wrapper tr {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.85rem;
        margin-bottom: 0.75rem;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .ranges-table-wrapper td {
        padding: 0.35rem 0 !important;
        border: none !important;
    }
    .ranges-table-wrapper .mobile-row-inputs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-top: 0.4rem;
    }
    .ranges-table-wrapper .mobile-pdf-info {
        margin-top: 0.4rem;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
}

/* Generation stats grid */
.stat-pill {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    text-align: center;
    font-size: 0.85rem;
}

.stat-pill .stat-val {
    font-weight: 700;
    font-size: 1.05rem;
    color: #1e293b;
    display: block;
}

.stat-pill .stat-lbl {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
}

/* Touch targets and form controls */
.form-control, .form-select, .btn {
    min-height: 40px;
    border-radius: 7px;
}

@media (max-width: 575.98px) {
    .btn-mobile-full {
        width: 100% !important;
    }
}
</style>

<div class="generator-container">
    <!-- Hero Banner with responsive actions -->
    <div class="generator-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <h1><i class="fa-solid fa-book-bookmark me-2"></i>Textbook Question Generator</h1>
                <?php if ($apiKeyConfigured): ?>
                    <span class="badge bg-success text-white py-1 px-2" style="font-size: 0.75rem;"><i class="fa-solid fa-check-circle me-1"></i>AI Key Active</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark py-1 px-2" style="font-size: 0.75rem;"><i class="fa-solid fa-triangle-exclamation me-1"></i>AI Key Missing</span>
                <?php endif; ?>
            </div>
            <p>Store textbooks in Google Drive, map chapter page ranges, and generate AI questions for instant review.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-info text-white" id="testDriveBtn" title="Test Google Drive connection">
                <i class="fa-brands fa-google-drive me-1"></i>Test Drive
            </button>
            <a href="../dashboard.php" class="btn btn-light" title="Return to Admin Dashboard">
                <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
            </a>
        </div>
    </div>

    <?php if (!$apiKeyConfigured): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-triangle-exclamation fs-5 flex-shrink-0"></i>
            <div>
                <strong>Gemini API Key Required:</strong> Add <code>GEMINIAPIKEYFORBOOKQUESTIONS</code> to your environment file to enable question generation.
            </div>
        </div>
    <?php endif; ?>

    <div id="alertBox" class="alert d-none mb-3" role="alert"></div>

    <!-- Saved Draft Reviews Section -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Saved Draft Reviews</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadReviewJobs()" title="Refresh draft list">
                <i class="fa-solid fa-rotate me-1"></i>Refresh
            </button>
        </div>
        <div class="card-body">
            <div id="savedDraftJobs" class="d-flex flex-column gap-2">
                <div class="text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading saved drafts...</div>
            </div>
        </div>
    </div>

    <!-- Two-column responsive layout: Left = Upload, Right = Manage & Generate -->
    <div class="row g-3 g-xl-4">
        <!-- Step 1: Upload Book -->
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fa-solid fa-cloud-arrow-up me-2 text-primary"></i>1. Store Book PDF
                </div>
                <div class="card-body">
                    <form id="uploadBookForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="upload_book">

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary" for="uploadClass">Class</label>
                            <select class="form-select" id="uploadClass" name="class_id" required>
                                <option value="">Select class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= (int) $class['class_id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary" for="uploadBook">Book</label>
                            <select class="form-select" id="uploadBook" name="book_id" required disabled>
                                <option value="">Select class first</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary" for="bookFile">Textbook PDF</label>
                            <input type="file" class="form-control" id="bookFile" name="book_file" accept=".pdf,application/pdf" required>
                            <div class="form-text small d-flex justify-content-between align-items-center mt-1">
                                <span>Drive + local storage</span>
                                <span class="badge bg-light text-dark border">Limit: <?= (int) $uploadLimitMb ?> MB</span>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100 mt-2" type="submit" id="uploadSubmitBtn">
                            <i class="fa-solid fa-upload me-1"></i>Upload &amp; Store Book
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Step 2, 3, 4 -->
        <div class="col-12 col-xl-8 d-flex flex-column gap-3 gap-xl-4">
            <!-- Step 2: Uploaded Books Selection -->
            <div class="card">
                <div class="card-header">
                    <i class="fa-solid fa-folder-open me-2 text-primary"></i>2. Uploaded Books
                </div>
                <div class="card-body">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold small text-secondary" for="storedBookSelect">Select Stored Book</label>
                            <select class="form-select" id="storedBookSelect">
                                <option value="">Select an uploaded book</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 align-self-end mt-2 mt-md-0">
                            <a href="#" target="_blank" class="btn btn-outline-primary w-100 disabled" id="driveLink">
                                <i class="fa-brands fa-google-drive me-1"></i>Open in Drive
                            </a>
                        </div>
                    </div>
                    <div class="mt-2" id="storedBookMeta"></div>
                </div>
            </div>

            <!-- Step 3: Chapter Page Ranges -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span><i class="fa-solid fa-list-ol me-2 text-primary"></i>3. Chapter Page Ranges</span>
                        <span class="badge bg-light text-secondary border d-none d-sm-inline" id="chapterCountBadge">0 chapters</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-success" id="saveRangesBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Save Page Ranges
                    </button>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                        <div class="text-muted small">
                            <i class="fa-solid fa-circle-info text-info me-1"></i>Enter printed textbook pages. PDF page offsets are calculated automatically.
                        </div>
                        <div class="w-100 w-sm-auto" style="min-width: 200px; max-width: 320px;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" class="form-control" id="chapterFilterInput" placeholder="Filter chapters...">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive ranges-table-wrapper">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Chapter</th>
                                    <th scope="col" style="width: 140px;">Printed Start</th>
                                    <th scope="col" style="width: 140px;">Printed End</th>
                                    <th scope="col" style="width: 150px;">PDF Pages</th>
                                </tr>
                            </thead>
                            <tbody id="rangeRows">
                                <tr><td colspan="4" class="text-muted text-center py-4">Select an uploaded book to view and configure chapters.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Step 4: Generate One Chapter for Review -->
            <div class="card">
                <div class="card-header">
                    <i class="fa-solid fa-wand-magic-sparkles me-2 text-primary"></i>4. Generate One Chapter for Review
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Chapter Selector -->
                        <div class="col-12 col-lg-5">
                            <label class="form-label fw-semibold small text-secondary" for="generateChapter">Chapter to Generate</label>
                            <select class="form-select" id="generateChapter">
                                <option value="">Save chapter ranges first</option>
                            </select>
                        </div>

                        <!-- Target Counts: MCQs, Short, Long in 3 neat columns on all screens -->
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-semibold small text-secondary d-block">Question Targets</label>
                            <div class="row g-2">
                                <div class="col-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text px-1 px-sm-2 small" title="Multiple Choice Questions">MCQ</span>
                                        <input type="number" class="form-control text-center px-1" id="mcqCount" min="0" max="200" value="10">
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text px-1 px-sm-2 small" title="Short Questions">Short</span>
                                        <input type="number" class="form-control text-center px-1" id="shortCount" min="0" max="100" value="5">
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text px-1 px-sm-2 small" title="Long Questions">Long</span>
                                        <input type="number" class="form-control text-center px-1" id="longCount" min="0" max="50" value="3">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="col-12 col-lg-3 d-flex align-items-end">
                            <button type="button" class="btn btn-primary w-100" id="startGenerateBtn" <?= $apiKeyConfigured ? '' : 'disabled' ?>>
                                <i class="fa-solid fa-play me-1"></i>Start Generation
                            </button>
                        </div>
                    </div>

                    <!-- Progress Section -->
                    <div class="progress mt-3 d-none" id="progressWrap" style="height: 24px; border-radius: 6px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary fw-bold" id="progressBar" role="progressbar" style="width: 0%">0%</div>
                    </div>

                    <!-- Stats Grid for Progress -->
                    <div class="row g-2 mt-2 d-none" id="progressStatsGrid">
                        <div class="col-6 col-sm-3">
                            <div class="stat-pill">
                                <span class="stat-val text-primary" id="statMcqs">0 / 0</span>
                                <span class="stat-lbl">MCQs</span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="stat-pill">
                                <span class="stat-val text-info" id="statShort">0 / 0</span>
                                <span class="stat-lbl">Short</span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="stat-pill">
                                <span class="stat-val text-warning" id="statLong">0 / 0</span>
                                <span class="stat-lbl">Long</span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="stat-pill">
                                <span class="stat-val text-secondary" id="statSkipped">0</span>
                                <span class="stat-lbl">Skipped</span>
                            </div>
                        </div>
                    </div>

                    <pre class="bg-light p-3 rounded small mt-3 mb-0 d-none" id="progressDetails" style="white-space: pre-wrap; max-height: 180px; overflow-y: auto;"></pre>

                    <!-- Review Actions -->
                    <div class="mt-3 d-none d-flex gap-2 flex-wrap align-items-center" id="reviewLinkWrap">
                        <a href="#" class="btn btn-success" id="reviewLink">
                            <i class="fa-solid fa-clipboard-check me-1"></i>Review Generated Questions
                        </a>
                        <button type="button" class="btn btn-outline-danger d-none" id="stopGenerateBtn">
                            <i class="fa-solid fa-stop me-1"></i>Stop Generation
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = <?= json_encode($csrfToken) ?>;
const apiUrl = 'api.php';
const bookData = <?= json_encode($books, JSON_UNESCAPED_UNICODE) ?>;
const maxUploadBytes = <?= (int) $uploadLimitBytes ?>;
let uploads = [];
let currentUpload = null;
let currentChapters = [];
let currentRanges = {};
let currentJobId = '';
let generationRunning = false;

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

function hideAlert() {
    const box = document.getElementById('alertBox');
    box.classList.add('d-none');
    box.innerHTML = '';
}

async function postForm(fd) {
    fd.append('csrf_token', csrfToken);
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
        if (!data.ok && !data.success) {
            showAlert(data.error || 'Request failed.', 'danger');
        }
        return data;
    } catch (err) {
        showAlert('Network error: ' + err.message, 'danger');
        return null;
    }
}

function populateBooks(classSelect, bookSelect) {
    const classId = parseInt(classSelect.value, 10);
    bookSelect.innerHTML = '<option value="">Select book</option>';
    if (!classId) {
        bookSelect.disabled = true;
        return;
    }
    const filtered = bookData.filter(b => parseInt(b.class_id, 10) === classId);
    filtered.forEach(book => {
        const opt = document.createElement('option');
        opt.value = book.book_id;
        opt.textContent = book.book_name;
        bookSelect.appendChild(opt);
    });
    bookSelect.disabled = filtered.length === 0;
}

function renderUploads() {
    const select = document.getElementById('storedBookSelect');
    const selected = select.value;
    select.innerHTML = '<option value="">Select uploaded book</option>';
    uploads.forEach(upload => {
        const opt = document.createElement('option');
        opt.value = upload.id;
        opt.textContent = `${upload.class_name} - ${upload.book_name} (${upload.mapped_chapters || 0} mapped, ${upload.pdf_page_count} pages)`;
        select.appendChild(opt);
    });
    if (selected) select.value = selected;
}

async function refreshData() {
    const fd = new FormData();
    fd.append('action', 'list_data');
    const data = await postForm(fd);
    if (data?.ok) {
        uploads = data.uploads || [];
        renderUploads();
    }
    await loadReviewJobs();
}

function renderReviewJobs(jobs) {
    const container = document.getElementById('savedDraftJobs');
    if (!jobs.length) {
        container.innerHTML = '<div class="text-muted small py-2"><i class="fa-solid fa-info-circle me-1"></i>No pending draft jobs found.</div>';
        return;
    }
    container.innerHTML = jobs.map(job => `
        <div class="draft-job-card d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
            <div>
                <div class="fw-semibold text-dark">
                    <span class="badge bg-primary me-1">${escapeHtml(job.class_name)}</span>
                    ${escapeHtml(job.book_name)} &bull; Ch ${escapeHtml(job.chapter_no)}: ${escapeHtml(job.chapter_name)}
                </div>
                <div class="small text-muted mt-1">
                    <span class="badge bg-warning text-dark me-1"><i class="fa-solid fa-hourglass-half me-1"></i>${escapeHtml(job.pending_count)} pending</span>
                    <span>${escapeHtml(job.last_created)}</span>
                </div>
            </div>
            <div class="mt-2 mt-sm-0">
                <a class="btn btn-sm btn-outline-success w-100" href="review.php?job_id=${encodeURIComponent(job.job_id)}">
                    <i class="fa-solid fa-arrow-right me-1"></i>Continue Review
                </a>
            </div>
        </div>`).join('');
}

async function loadReviewJobs() {
    const fd = new FormData();
    fd.append('action', 'list_review_jobs');
    const data = await postForm(fd);
    if (data?.ok) {
        renderReviewJobs(data.jobs || []);
    }
}

async function loadUploadDetails(uploadId) {
    currentUpload = null;
    currentChapters = [];
    currentRanges = {};
    document.getElementById('rangeRows').innerHTML = '<tr><td colspan="4" class="text-muted text-center py-4"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading chapters...</td></tr>';
    document.getElementById('chapterCountBadge').textContent = '0 chapters';
    
    const fd = new FormData();
    fd.append('action', 'get_upload_details');
    fd.append('upload_id', uploadId);
    const data = await postForm(fd);
    if (!data?.ok) return;
    
    currentUpload = data.upload;
    currentChapters = data.chapters || [];
    currentRanges = data.ranges || {};
    
    document.getElementById('chapterCountBadge').textContent = `${currentChapters.length} chapters`;
    renderRangeRows();
    renderGenerateChapters();

    const driveLink = document.getElementById('driveLink');
    driveLink.href = currentUpload.drive_url || '#';
    driveLink.classList.toggle('disabled', !currentUpload.drive_url);
    
    const metaContainer = document.getElementById('storedBookMeta');
    metaContainer.innerHTML = `
        <div class="d-flex flex-wrap gap-2 align-items-center small text-muted">
            <span class="badge bg-light text-dark border"><i class="fa-solid fa-file-pdf text-danger me-1"></i>${escapeHtml(currentUpload.original_filename)}</span>
            <span class="badge bg-light text-dark border"><i class="fa-solid fa-file-lines me-1"></i>${currentUpload.pdf_page_count} PDF Pages</span>
            ${currentUpload.page_offset ? `<span class="badge bg-light text-dark border">Offset: ${currentUpload.page_offset}</span>` : ''}
        </div>`;
}

function renderRangeRows() {
    const tbody = document.getElementById('rangeRows');
    if (!currentChapters.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-4">No chapters found for this book in the chapter database.</td></tr>';
        return;
    }
    tbody.innerHTML = '';
    currentChapters.forEach(ch => {
        const range = currentRanges[ch.chapter_id] || {};
        const pdfText = range.pdf_start_page ? `${range.pdf_start_page} - ${range.pdf_end_page}` : '-';
        const tr = document.createElement('tr');
        tr.dataset.chapterId = ch.chapter_id;
        tr.dataset.chapterSearch = `${ch.chapter_no} ${ch.chapter_name}`.toLowerCase();
        
        // Responsive hybrid row: table on desktop, cards on mobile via CSS
        tr.innerHTML = `
            <td>
                <div class="fw-semibold"><span class="badge bg-secondary me-1">Ch ${escapeHtml(ch.chapter_no)}</span>${escapeHtml(ch.chapter_name)}</div>
                <!-- Mobile Only Inputs Container -->
                <div class="d-md-none mobile-row-inputs">
                    <div>
                        <label class="form-label small text-muted mb-1">Start Page</label>
                        <input type="number" class="form-control form-control-sm printed-start" min="1" placeholder="Start" value="${range.printed_start_page || ''}" oninput="syncInputs(this, 'start')">
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-1">End Page</label>
                        <input type="number" class="form-control form-control-sm printed-end" min="1" placeholder="End" value="${range.printed_end_page || ''}" oninput="syncInputs(this, 'end')">
                    </div>
                </div>
                <div class="d-md-none mobile-pdf-info text-muted">
                    <span>PDF Range:</span>
                    <strong class="pdf-range-badge text-primary">${pdfText}</strong>
                </div>
            </td>
            <td class="d-none d-md-table-cell">
                <input type="number" class="form-control form-control-sm printed-start" min="1" placeholder="Start" value="${range.printed_start_page || ''}" oninput="syncInputs(this, 'start')">
            </td>
            <td class="d-none d-md-table-cell">
                <input type="number" class="form-control form-control-sm printed-end" min="1" placeholder="End" value="${range.printed_end_page || ''}" oninput="syncInputs(this, 'end')">
            </td>
            <td class="d-none d-md-table-cell text-muted pdf-range">
                <span class="badge bg-light text-dark border pdf-range-badge">${pdfText}</span>
            </td>`;
        tbody.appendChild(tr);
    });
}

function syncInputs(el, type) {
    const tr = el.closest('tr');
    if (!tr) return;
    const targets = tr.querySelectorAll(type === 'start' ? '.printed-start' : '.printed-end');
    targets.forEach(input => {
        if (input !== el) input.value = el.value;
    });
}

function filterChapters(keyword) {
    const query = keyword.trim().toLowerCase();
    const rows = document.querySelectorAll('#rangeRows tr[data-chapter-id]');
    rows.forEach(row => {
        const search = row.dataset.chapterSearch || '';
        row.style.display = search.includes(query) ? '' : 'none';
    });
}

document.getElementById('chapterFilterInput').addEventListener('input', e => filterChapters(e.target.value));

function renderGenerateChapters() {
    const select = document.getElementById('generateChapter');
    select.innerHTML = '<option value="">Select mapped chapter</option>';
    let mappedCount = 0;
    currentChapters.forEach(ch => {
        const range = currentRanges[ch.chapter_id];
        if (!range || !range.pdf_start_page) return;
        mappedCount++;
        const opt = document.createElement('option');
        opt.value = ch.chapter_id;
        opt.textContent = `Ch ${ch.chapter_no}. ${ch.chapter_name} (PDF: ${range.pdf_start_page}-${range.pdf_end_page})`;
        select.appendChild(opt);
    });
    if (mappedCount === 0) {
        select.innerHTML = '<option value="">No mapped chapters yet. Save ranges above.</option>';
    }
}

function collectRanges() {
    return [...document.querySelectorAll('#rangeRows tr[data-chapter-id]')].map(row => {
        const startInput = row.querySelector('.printed-start');
        const endInput = row.querySelector('.printed-end');
        return {
            chapter_id: parseInt(row.dataset.chapterId, 10),
            printed_start_page: parseInt(startInput ? startInput.value : 0, 10) || 0,
            printed_end_page: parseInt(endInput ? endInput.value : 0, 10) || 0
        };
    }).filter(row => row.printed_start_page > 0 || row.printed_end_page > 0);
}

function validatePdfSize() {
    const input = document.getElementById('bookFile');
    if (!input.files.length || !maxUploadBytes) return true;
    if (input.files[0].size <= maxUploadBytes) return true;
    showAlert(`This PDF exceeds the server upload limit (${Math.round(maxUploadBytes / 1024 / 1024)} MB).`, 'warning');
    return false;
}

function renderProgress(progress) {
    if (!progress) return;
    document.getElementById('progressWrap').classList.remove('d-none');
    document.getElementById('progressStatsGrid').classList.remove('d-none');
    document.getElementById('progressDetails').classList.remove('d-none');
    
    const ch = progress.chapter || {};
    const target = (ch.targets?.mcq || 0) + (ch.targets?.short || 0) + (ch.targets?.long || 0);
    const saved = (ch.saved?.mcq || 0) + (ch.saved?.short || 0) + (ch.saved?.long || 0);
    const pct = target > 0 ? Math.min(100, Math.round(saved / target * 100)) : 0;
    
    const bar = document.getElementById('progressBar');
    bar.style.width = pct + '%';
    bar.textContent = pct + '%';

    // Update Stat pills
    document.getElementById('statMcqs').textContent = `${ch.saved?.mcq || 0} / ${ch.targets?.mcq || 0}`;
    document.getElementById('statShort').textContent = `${ch.saved?.short || 0} / ${ch.targets?.short || 0}`;
    document.getElementById('statLong').textContent = `${ch.saved?.long || 0} / ${ch.targets?.long || 0}`;
    document.getElementById('statSkipped').textContent = (ch.skipped?.duplicates || 0) + (ch.skipped?.invalid || 0);

    // Update Details log
    document.getElementById('progressDetails').textContent =
        `Job ID: ${currentJobId}\nStatus: ${progress.job_status}\nChapter: ${ch.chapter_no || ''} ${ch.chapter_name || ''}\n` +
        `MCQs: ${ch.saved?.mcq || 0}/${ch.targets?.mcq || 0} | Short: ${ch.saved?.short || 0}/${ch.targets?.short || 0} | Long: ${ch.saved?.long || 0}/${ch.targets?.long || 0}\n` +
        `Duplicates skipped: ${ch.skipped?.duplicates || 0} | Invalid skipped: ${ch.skipped?.invalid || 0}\n${ch.error ? 'Error: ' + ch.error : ''}`;
}

async function processBatchLoop() {
    if (!generationRunning || !currentJobId) return;
    const fd = new FormData();
    fd.append('action', 'process_batch');
    fd.append('job_id', currentJobId);
    const data = await postForm(fd);
    if (data?.progress) renderProgress(data.progress);
    if (!generationRunning) {
        return;
    }
    if (!data || !data.ok) {
        generationRunning = false;
        document.getElementById('stopGenerateBtn').classList.add('d-none');
        return;
    }
    if (data.done) {
        generationRunning = false;
        showAlert('Draft generation finished! Click "Review generated questions" to inspect and approve them.', 'success');
        document.getElementById('stopGenerateBtn').classList.add('d-none');
        loadReviewJobs();
        return;
    }
    setTimeout(processBatchLoop, 300);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
}

document.getElementById('uploadClass').addEventListener('change', () => populateBooks(document.getElementById('uploadClass'), document.getElementById('uploadBook')));

document.getElementById('uploadBookForm').addEventListener('submit', async event => {
    event.preventDefault();
    hideAlert();
    if (!validatePdfSize() || !event.currentTarget.reportValidity()) return;
    const submit = document.getElementById('uploadSubmitBtn');
    submit.disabled = true;
    submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Uploading to Drive...';
    
    const fd = new FormData(event.currentTarget);
    const data = await postForm(fd);
    submit.disabled = false;
    submit.innerHTML = '<i class="fa-solid fa-upload me-1"></i>Upload &amp; Store Book';
    
    if (data?.ok) {
        uploads = data.uploads || [];
        renderUploads();
        document.getElementById('storedBookSelect').value = data.upload_id;
        await loadUploadDetails(data.upload_id);
        showAlert('Book uploaded successfully and stored in Google Drive. Now set chapter page ranges below.', 'success');
    }
});

document.getElementById('storedBookSelect').addEventListener('change', event => {
    if (event.target.value) {
        loadUploadDetails(event.target.value);
    }
});

document.getElementById('saveRangesBtn').addEventListener('click', async () => {
    if (!currentUpload) {
        showAlert('Select an uploaded book first.', 'warning');
        return;
    }
    const fd = new FormData();
    fd.append('action', 'save_chapter_ranges');
    fd.append('upload_id', currentUpload.id);
    fd.append('ranges', JSON.stringify(collectRanges()));
    const data = await postForm(fd);
    if (data?.ok) {
        showAlert(`Saved ${data.saved} chapter page range(s) successfully.`, 'success');
        await loadUploadDetails(currentUpload.id);
        await refreshData();
    }
});

document.getElementById('startGenerateBtn').addEventListener('click', async () => {
    if (!currentUpload) {
        showAlert('Select an uploaded book first.', 'warning');
        return;
    }
    const chapterId = document.getElementById('generateChapter').value;
    if (!chapterId) {
        showAlert('Select a mapped chapter to generate questions.', 'warning');
        return;
    }
    hideAlert();
    document.getElementById('reviewLinkWrap').classList.add('d-none');
    document.getElementById('stopGenerateBtn').classList.add('d-none');
    
    const fd = new FormData();
    fd.append('action', 'init_chapter_job');
    fd.append('upload_id', currentUpload.id);
    fd.append('chapter_id', chapterId);
    fd.append('mcq_count', document.getElementById('mcqCount').value);
    fd.append('short_count', document.getElementById('shortCount').value);
    fd.append('long_count', document.getElementById('longCount').value);
    
    const data = await postForm(fd);
    if (!data?.ok) return;
    
    currentJobId = data.job_id;
    const link = document.getElementById('reviewLink');
    link.href = 'review.php?job_id=' + encodeURIComponent(currentJobId);
    document.getElementById('reviewLinkWrap').classList.remove('d-none');
    document.getElementById('stopGenerateBtn').classList.remove('d-none');
    renderProgress(data.progress);
    generationRunning = true;
    processBatchLoop();
});

document.getElementById('stopGenerateBtn').addEventListener('click', () => {
    generationRunning = false;
    document.getElementById('stopGenerateBtn').classList.add('d-none');
    showAlert('Generation paused. You can review and approve drafts generated so far.', 'warning');
    loadReviewJobs();
});

document.getElementById('testDriveBtn').addEventListener('click', async () => {
    const btn = document.getElementById('testDriveBtn');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Testing...';
    
    const fd = new FormData();
    fd.append('action', 'test_drive');
    const data = await postForm(fd);
    btn.disabled = false;
    btn.innerHTML = oldHtml;
    
    if (data?.success || data?.status === 'ok') {
        showAlert('Google Drive connection is verified and operational.', 'success');
    }
});

document.addEventListener('DOMContentLoaded', refreshData);
</script>

<?php include_once __DIR__ . '/../footer.php'; ?>
