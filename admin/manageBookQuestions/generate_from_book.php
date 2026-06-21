<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

$classes = $conn->query('SELECT class_id, class_name FROM class ORDER BY class_id ASC');
$books = $conn->query('SELECT book_id, book_name, class_id FROM book ORDER BY book_name ASC');
$chapters = $conn->query('SELECT chapter_id, chapter_no, chapter_name, class_id, book_id FROM chapter ORDER BY chapter_no ASC');
$bookMap = [];
$chapterMap = [];
while ($books && ($row = $books->fetch_assoc())) {
    $bookMap[] = $row;
}
while ($chapters && ($row = $chapters->fetch_assoc())) {
    $chapterMap[] = $row;
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

include_once __DIR__ . '/../header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Generate Questions from Textbook</h1>
            <p class="text-muted mb-0">Upload a complete PDF, enter chapter page ranges, and generate MCQs, short questions, and long questions chapter by chapter.</p>
        </div>
        <a href="../dashboard.php" class="btn btn-outline-secondary">← Dashboard</a>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">How to Use This Page</div>
        <div class="card-body">
            <ol>
                <li><strong>Select Class and Book:</strong> First, choose the class and book you want to generate questions for.</li>
                <li><strong>Upload PDF:</strong> Upload the complete textbook PDF file.</li>
                <li><strong>Set Page Offset:</strong> If the printed page numbers don't match the PDF page numbers, set an offset (e.g., if printed page 1 is PDF page 7, enter 6).</li>
                <li><strong>Add Chapters:</strong> 
                    <ul>
                        <li>Click <strong>"Add All Chapters"</strong> to automatically populate chapters from the selected book, or</li>
                        <li>Click <strong>"+ Add chapter row"</strong> to add chapters manually.</li>
                    </ul>
                </li>
                <li><strong>Fill Chapter Details:</strong> For each chapter, enter the start and end printed page numbers, and the number of MCQs, short questions, and long questions you want to generate.</li>
                <li><strong>Calculate PDF Pages:</strong> Click <strong>"Calculate PDF pages"</strong> to verify that the page ranges map correctly to the PDF pages.</li>
                <li><strong>Generate Questions:</strong> Click <strong>"Start generation"</strong> to begin generating questions.</li>
            </ol>
        </div>
    </div>

    <?php if (!$apiKeyConfigured): ?>
        <div class="alert alert-warning">Gemini API key for book questions is not configured. Add <code>GEMINIAPIKEYFORBOOKQUESTIONS</code> to your <code>.env</code> file.</div>
    <?php endif; ?>

    <div id="alertBox" class="alert d-none" role="alert"></div>

    <form id="bookGenForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars(generateCSRFToken()) ?>">

        <div class="card mb-4">
            <div class="card-header fw-semibold">Book Selection</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <select class="form-select" id="classSelect" name="class_id" required>
                        <option value="">Select class</option>
                        <?php if ($classes) while ($c = $classes->fetch_assoc()): ?>
                            <option value="<?= (int) $c['class_id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Book</label>
                    <select class="form-select" id="bookSelect" name="book_id" required disabled>
                        <option value="">Select book</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Complete Textbook (PDF)</label>
                    <input type="file" class="form-control" id="bookFile" name="book_file" accept=".pdf,application/pdf" required>
                    <div class="form-text" id="pdfPageInfo">PDF recommended for exact chapter page extraction. Current upload limit: <?= (int) $uploadLimitMb ?> MB.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Page offset</label>
                    <input type="number" class="form-control" id="pageOffset" name="page_offset" value="0">
                    <div class="form-text">Example: if printed page 1 is PDF page 7, enter offset 6.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Generation mode</label>
                    <select class="form-select" id="genMode" name="mode">
                        <option value="one">One chapter</option>
                        <option value="all">All chapters</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Chapters</span>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-success me-2" id="addAllChaptersBtn">Add All Chapters</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addChapterRow">+ Add chapter row</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="chaptersTable">
                        <thead class="table-light">
                            <tr>
                                <th>Chapter</th>
                                <th>Start page</th>
                                <th>End page</th>
                                <th>PDF pages</th>
                                <th>MCQs</th>
                                <th>Short</th>
                                <th>Long</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="chapterRows"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="calcPagesBtn">Calculate PDF pages</button>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-4">
            <button type="button" class="btn btn-primary" id="startBtn" <?= $apiKeyConfigured ? '' : 'disabled' ?>>Start generation</button>
            <button type="button" class="btn btn-outline-danger d-none" id="cancelBtn">Cancel generation</button>
            <button type="button" class="btn btn-outline-warning d-none" id="retryBtn">Retry failed batch</button>
            <button type="button" class="btn btn-outline-secondary d-none" id="continueBtn">Continue generation</button>
            <button type="button" class="btn btn-outline-info d-none" id="viewTextBtn">View extracted chapter text</button>
            <button type="button" class="btn btn-outline-success d-none" id="viewSavedBtn">View saved questions</button>
        </div>
    </form>

    <div class="card d-none" id="progressCard">
        <div class="card-header fw-semibold">Progress</div>
        <div class="card-body">
            <div id="progressText" class="mb-2"></div>
            <div id="statusSteps" class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge bg-secondary" id="stepUpload">1. Upload</span>
                <span class="badge bg-secondary" id="stepJob">2. Create job</span>
                <span class="badge bg-secondary" id="stepExtract">3. Extract text</span>
                <span class="badge bg-secondary" id="stepGenerate">4. Generate</span>
                <span class="badge bg-secondary" id="stepSave">5. Save</span>
                <span class="badge bg-secondary" id="stepFinish">6. Finish</span>
            </div>
            <div class="progress mb-2" style="height: 22px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%">0%</div>
            </div>
            <pre class="bg-light p-3 rounded small mb-0" id="progressDetails" style="min-height: 120px; white-space: pre-wrap; overflow-x: hidden;"></pre>
            <div class="mt-3">
                <div class="fw-semibold mb-2">Generation log</div>
                <div id="progressLog" class="bg-dark text-white p-3 rounded small" style="min-height: 120px; max-height: 280px; overflow-y: auto; white-space: pre-wrap;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalTitle">Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"><pre id="previewModalBody" class="mb-0" style="white-space: pre-wrap;"></pre></div>
        </div>
    </div>
</div>

<script>
const bookData = <?= json_encode($bookMap) ?>;
const chapterData = <?= json_encode($chapterMap) ?>;
const canGenerateBookQuestions = <?= $apiKeyConfigured ? 'true' : 'false' ?>;
const maxUploadBytes = <?= (int) $uploadLimitBytes ?>;
const maxUploadMb = <?= (int) $uploadLimitMb ?>;
const apiUrl = 'api.php';
let currentJobId = '';
let pdfPageCount = 0;
let generationRunning = false;
let currentChapterIndex = 0;
let currentBookId = null;
const renderedServerLogs = new Set();

function progressCard() {
    const card = document.getElementById('progressCard');
    card.classList.remove('d-none');
    return card;
}

function showAlert(message, type = 'danger') {
    const box = document.getElementById('alertBox');
    box.className = 'alert alert-' + type;
    box.textContent = message;
    box.classList.remove('d-none');
    logProgress(message, type === 'danger' ? 'error' : (type === 'warning' ? 'warn' : 'info'));
}

function hideAlert() {
    document.getElementById('alertBox').classList.add('d-none');
}

function logProgress(message, level = 'info') {
    progressCard();
    const panel = document.getElementById('progressLog');
    if (!panel) return;
    const prefix = level === 'error' ? '[ERROR]' : level === 'warn' ? '[WARN]' : '[INFO]';
    panel.textContent += prefix + ' ' + message + '\n';
    panel.scrollTop = panel.scrollHeight;
}

function setProgressText(message) {
    progressCard();
    document.getElementById('progressText').textContent = message;
}

function markStep(id, state) {
    const el = document.getElementById(id);
    if (!el) return;
    const classes = {
        waiting: 'bg-secondary',
        active: 'bg-primary',
        success: 'bg-success',
        warn: 'bg-warning text-dark',
        error: 'bg-danger'
    };
    el.className = 'badge ' + (classes[state] || classes.waiting);
}

function resetSteps() {
    ['stepUpload', 'stepJob', 'stepExtract', 'stepGenerate', 'stepSave', 'stepFinish'].forEach(id => markStep(id, 'waiting'));
}

function syncButtons() {
    document.getElementById('startBtn').disabled = generationRunning || !canGenerateBookQuestions;
    document.getElementById('cancelBtn').classList.toggle('d-none', !currentJobId || !generationRunning);
    document.getElementById('retryBtn').classList.toggle('d-none', !currentJobId);
    document.getElementById('continueBtn').classList.toggle('d-none', !currentJobId || generationRunning);
    document.getElementById('viewTextBtn').classList.toggle('d-none', !currentJobId);
    document.getElementById('viewSavedBtn').classList.toggle('d-none', !currentJobId);
}

async function fetchJson(formData) {
    try {
        const res = await fetch(apiUrl, { method: 'POST', body: formData });
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            const errorMessage = 'Invalid JSON response from server.';
            logProgress(errorMessage + ' Response: ' + text.slice(0, 1024), 'error');
            showAlert(errorMessage, 'danger');
            return null;
        }
        if (!res.ok && data.ok !== false) {
            data.ok = false;
            data.error = data.error || ('Server returned HTTP ' + res.status + '.');
        }
        if (!data.ok) {
            const error = data.error || 'Unknown server error.';
            if (data.stored_pdf) {
                logProgress('Uploaded PDF kept as storage/book_uploads/' + data.stored_pdf, 'warn');
            }
            if (data.stored_pdf_note) {
                logProgress(data.stored_pdf_note, 'warn');
            }
            logProgress(error, 'error');
            showAlert(error, 'danger');
        }
        return data;
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Network error';
        logProgress(message, 'error');
        showAlert('Network error: ' + message, 'danger');
        return null;
    }
}

function getChaptersForBook(bookId) {
    if (!bookId) return [];
    return chapterData.filter(ch => parseInt(ch.book_id, 10) === parseInt(bookId, 10)).sort((a, b) => parseInt(a.chapter_no, 10) - parseInt(b.chapter_no, 10));
}

function updateBookOptions() {
    const classId = parseInt(document.getElementById('classSelect').value, 10);
    const bookSelect = document.getElementById('bookSelect');
    bookSelect.innerHTML = '<option value="">Select book</option>';
    if (!classId) {
        bookSelect.disabled = true;
        currentBookId = null;
        return;
    }
    bookData.filter(b => parseInt(b.class_id, 10) === classId).forEach(book => {
        const opt = document.createElement('option');
        opt.value = book.book_id;
        opt.textContent = book.book_name;
        bookSelect.appendChild(opt);
    });
    bookSelect.disabled = false;
    currentBookId = null;
}

function chapterRowTemplate(data = {}) {
    const chapters = getChaptersForBook(currentBookId);
    let chapterOptions = '<option value="">Select chapter</option>';
    chapters.forEach(ch => {
        const selected = (data.chapter_no == ch.chapter_no && data.chapter_name == ch.chapter_name) ? 'selected' : '';
        chapterOptions += `<option value="${encodeURIComponent(JSON.stringify({ chapter_no: ch.chapter_no, chapter_name: ch.chapter_name }))}" ${selected}>${ch.chapter_no}: ${ch.chapter_name}</option>`;
    });
    
    return `
        <tr class="chapter-row">
            <td>
                <select class="form-select form-select-sm chapter-select" required>
                    ${chapterOptions}
                </select>
                <input type="hidden" class="chapter-no" value="${data.chapter_no || ''}">
                <input type="hidden" class="chapter-name" value="${data.chapter_name || ''}">
            </td>
            <td><input type="number" class="form-control form-control-sm start-page" min="1" value="${data.start_page || ''}" required></td>
            <td><input type="number" class="form-control form-control-sm end-page" min="1" value="${data.end_page || ''}" required></td>
            <td class="pdf-pages text-muted small">—</td>
            <td><input type="number" class="form-control form-control-sm mcq-count" min="0" max="200" value="${data.mcq_count ?? 10}"></td>
            <td><input type="number" class="form-control form-control-sm short-count" min="0" max="100" value="${data.short_count ?? 5}"></td>
            <td><input type="number" class="form-control form-control-sm long-count" min="0" max="50" value="${data.long_count ?? 3}"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">×</button></td>
        </tr>`;
}

function refreshAllChapterSelects() {
    document.querySelectorAll('#chapterRows .chapter-row').forEach(row => {
        const currentNo = row.querySelector('.chapter-no').value;
        const currentName = row.querySelector('.chapter-name').value;
        const chapters = getChaptersForBook(currentBookId);
        
        let chapterOptions = '<option value="">Select chapter</option>';
        chapters.forEach(ch => {
            const selected = (currentNo == ch.chapter_no && currentName == ch.chapter_name) ? 'selected' : '';
            chapterOptions += `<option value="${encodeURIComponent(JSON.stringify({ chapter_no: ch.chapter_no, chapter_name: ch.chapter_name }))}" ${selected}>${ch.chapter_no}: ${ch.chapter_name}</option>`;
        });
        
        row.querySelector('.chapter-select').innerHTML = chapterOptions;
    });
}

function ensureAtLeastOneRow() {
    const tbody = document.getElementById('chapterRows');
    if (!tbody.children.length) {
        tbody.insertAdjacentHTML('beforeend', chapterRowTemplate());
    }
}

function collectChapters() {
    const rows = [];
    document.querySelectorAll('#chapterRows .chapter-row').forEach(row => {
        rows.push({
            chapter_no: parseInt(row.querySelector('.chapter-no').value, 10) || 0,
            chapter_name: row.querySelector('.chapter-name').value.trim(),
            start_page: parseInt(row.querySelector('.start-page').value, 10) || 0,
            end_page: parseInt(row.querySelector('.end-page').value, 10) || 0,
            mcq_count: parseInt(row.querySelector('.mcq-count').value, 10) || 0,
            short_count: parseInt(row.querySelector('.short-count').value, 10) || 0,
            long_count: parseInt(row.querySelector('.long-count').value, 10) || 0,
        });
    });
    return rows;
}

function validateSelectedPdfSize() {
    const fileInput = document.getElementById('bookFile');
    if (!fileInput.files.length || !maxUploadBytes) return true;
    const file = fileInput.files[0];
    if (file.size <= maxUploadBytes) return true;
    const fileMb = (file.size / 1024 / 1024).toFixed(1);
    const message = 'This PDF is ' + fileMb + ' MB, but the current PHP upload limit is ' + maxUploadMb + ' MB. Increase upload_max_filesize and post_max_size, then restart the server if needed.';
    document.getElementById('pdfPageInfo').textContent = message;
    showAlert(message, 'warning');
    markStep('stepUpload', 'error');
    return false;
}

function applyModeVisibility() {
    const mode = document.getElementById('genMode').value;
    const addBtn = document.getElementById('addChapterRow');
    const addAllBtn = document.getElementById('addAllChaptersBtn');
    const rows = document.querySelectorAll('#chapterRows .chapter-row');
    addBtn.classList.toggle('d-none', mode === 'one');
    addAllBtn.classList.toggle('d-none', mode === 'one');
    if (mode === 'one' && rows.length > 1) {
        rows.forEach((row, idx) => { if (idx > 0) row.remove(); });
    }
}

function addAllChapters() {
    const chapters = getChaptersForBook(currentBookId);
    if (!chapters.length) {
        showAlert('No chapters found for the selected book.', 'warning');
        return;
    }
    const tbody = document.getElementById('chapterRows');
    tbody.innerHTML = '';
    chapters.forEach(ch => {
        tbody.insertAdjacentHTML('beforeend', chapterRowTemplate({
            chapter_no: ch.chapter_no,
            chapter_name: ch.chapter_name
        }));
    });
}

async function detectPdfPageCount() {
    const fileInput = document.getElementById('bookFile');
    if (!fileInput.files.length) return;
    if (!validateSelectedPdfSize()) return;
    hideAlert();
    resetSteps();
    markStep('stepUpload', 'active');
    setProgressText('Checking uploaded PDF page count...');
    logProgress('Checking PDF page count for ' + fileInput.files[0].name + '.');
    document.getElementById('pdfPageInfo').textContent = 'Checking PDF page count...';
    const fd = new FormData();
    fd.append('action', 'pdf_page_count');
    fd.append('csrf_token', document.getElementById('csrfToken').value);
    fd.append('book_file', fileInput.files[0]);
    const data = await fetchJson(fd);
    if (!data || !data.ok) {
        markStep('stepUpload', 'error');
        document.getElementById('pdfPageInfo').textContent = 'Could not detect PDF page count. Start generation will show the exact error.';
        return;
    }
    if (data.ok) {
        pdfPageCount = data.pdf_page_count;
        document.getElementById('pdfPageInfo').textContent = 'PDF has ' + pdfPageCount + ' pages.';
        markStep('stepUpload', 'success');
        logProgress('Detected PDF page count: ' + pdfPageCount);
    }
}

async function calculatePages() {
    hideAlert();
    if (!pdfPageCount) {
        await detectPdfPageCount();
    }
    if (!pdfPageCount) return;

    const fd = new FormData();
    fd.append('action', 'calculate_pages');
    fd.append('csrf_token', document.getElementById('csrfToken').value);
    fd.append('page_offset', document.getElementById('pageOffset').value || '0');
    fd.append('pdf_page_count', String(pdfPageCount));
    fd.append('chapters', JSON.stringify(collectChapters()));

    const data = await fetchJson(fd);
    if (!data || !data.ok) return;
    const rows = document.querySelectorAll('#chapterRows .chapter-row');
    data.rows.forEach((item, idx) => {
        const cell = rows[idx]?.querySelector('.pdf-pages');
        if (!cell) return;
        if (item.ok) {
            cell.textContent = item.pdf_start + ' – ' + item.pdf_end;
            cell.classList.remove('text-danger');
            cell.classList.add('text-success');
        } else {
            cell.textContent = item.error || 'Invalid';
            cell.classList.add('text-danger');
        }
    });
    logProgress('PDF page mapping calculated.');
}

function renderProgress(progress) {
    if (!progress) return;
    progressCard();
    renderServerLogs(progress.logs || []);
    const ch = progress.chapter;
    let html = '';
    if (currentJobId) html += `Job ID: ${currentJobId}\n`;
    if (progress.stored_pdf) html += `Stored PDF: storage/book_uploads/${progress.stored_pdf}\n`;
    html += `Job status: ${progress.job_status || 'ready'}\n`;
    html += `Chapter: ${(progress.current_chapter_index || 0) + 1} / ${progress.total_chapters || 1}\n`;
    if (ch) {
        html += `Current chapter: ${ch.chapter_no}: ${ch.chapter_name}\n`;
        html += `Chapter status: ${ch.status || 'pending'}\n`;
        if (ch.current_type) html += `Current type: ${ch.current_type}, batch ${(ch.current_batch || 0) + 1}\n`;
        html += `MCQs: ${ch.saved?.mcq || 0} / ${ch.targets?.mcq || 0}\n`;
        html += `Short questions: ${ch.saved?.short || 0} / ${ch.targets?.short || 0}\n`;
        html += `Long questions: ${ch.saved?.long || 0} / ${ch.targets?.long || 0}\n`;
        html += `Printed pages: ${ch.printed_start}-${ch.printed_end} | PDF pages: ${ch.pdf_start}-${ch.pdf_end}\n`;
        html += `Duplicates skipped: ${ch.skipped?.duplicates || 0}\n`;
        html += `Invalid questions skipped: ${ch.skipped?.invalid || 0}\n`;
        html += `Replacement attempts: ${ch.replacement_attempts || 0}\n`;
        if (ch.error) html += `Error: ${ch.error}\n`;
        if (ch.warning) html += `Notice: ${ch.warning}\n`;
    }
    document.getElementById('progressDetails').textContent = html;

    const totalTargets = (ch?.targets?.mcq || 0) + (ch?.targets?.short || 0) + (ch?.targets?.long || 0);
    const totalSaved = (ch?.saved?.mcq || 0) + (ch?.saved?.short || 0) + (ch?.saved?.long || 0);
    const pct = totalTargets > 0 ? Math.min(100, Math.round((totalSaved / totalTargets) * 100)) : 0;
    const bar = document.getElementById('progressBar');
    bar.style.width = pct + '%';
    bar.textContent = pct + '%';
    const failed = ch?.status === 'failed' || !!ch?.error;
    document.getElementById('progressText').textContent = progress.job_status === 'completed'
        ? 'Generation completed.'
        : (progress.cancelled ? 'Generation cancelled.' : (failed ? 'Generation stopped with an error.' : 'Generating questions...'));
    currentChapterIndex = progress.current_chapter_index || 0;

    markStep('stepUpload', currentJobId ? 'success' : 'active');
    markStep('stepJob', currentJobId ? 'success' : 'waiting');
    markStep('stepExtract', ch && ['generating', 'done'].includes(ch.status) ? 'success' : (ch?.status === 'pending' ? 'active' : 'waiting'));
    markStep('stepGenerate', failed ? 'error' : (ch?.status === 'generating' ? 'active' : (ch?.status === 'done' ? 'success' : 'waiting')));
    const savedAny = (ch?.saved?.mcq || 0) + (ch?.saved?.short || 0) + (ch?.saved?.long || 0) > 0;
    markStep('stepSave', savedAny ? 'success' : (ch?.status === 'generating' ? 'active' : 'waiting'));
    markStep('stepFinish', progress.job_status === 'completed' ? 'success' : (failed ? 'error' : 'waiting'));
    syncButtons();
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
}

function renderServerLogs(logs) {
    logs.forEach(item => {
        const key = [item.time || '', item.level || '', item.message || ''].join('|');
        if (renderedServerLogs.has(key)) return;
        renderedServerLogs.add(key);
        logProgress((item.time ? item.time + ' ' : '') + (item.message || ''), item.level || 'info');
    });
}

async function initJob() {
    hideAlert();
    const form = document.getElementById('bookGenForm');
    if (!form.reportValidity()) {
        showAlert('Complete the required class, book, PDF, chapter, page range, and question counts first.', 'warning');
        return false;
    }
    const fileInput = document.getElementById('bookFile');
    if (!fileInput.files.length) {
        showAlert('Upload the complete textbook PDF.');
        return false;
    }
    if (!validateSelectedPdfSize()) {
        return false;
    }
    resetSteps();
    markStep('stepUpload', 'active');
    setProgressText('Uploading PDF and creating generation job...');
    logProgress('Starting generation for ' + fileInput.files[0].name + '.');

    const fd = new FormData();
    fd.append('action', 'init_job');
    fd.append('csrf_token', document.getElementById('csrfToken').value);
    fd.append('class_id', document.getElementById('classSelect').value);
    fd.append('book_id', document.getElementById('bookSelect').value);
    fd.append('page_offset', document.getElementById('pageOffset').value || '0');
    fd.append('mode', document.getElementById('genMode').value);
    fd.append('chapters', JSON.stringify(collectChapters()));
    fd.append('book_file', fileInput.files[0]);

    const data = await fetchJson(fd);
    if (!data || !data.ok) {
        markStep('stepUpload', 'error');
        return false;
    }
    currentJobId = data.job_id;
    pdfPageCount = data.pdf_page_count || pdfPageCount;
    markStep('stepUpload', 'success');
    markStep('stepJob', 'success');
    logProgress('Generation job created: ' + currentJobId + '.');
    renderProgress(data.progress);
    syncButtons();
    return true;
}

async function processBatchLoop() {
    if (!currentJobId || !generationRunning) return;

    const fd = new FormData();
    fd.append('action', 'process_batch');
    fd.append('csrf_token', document.getElementById('csrfToken').value);
    fd.append('job_id', currentJobId);

    try {
        const data = await fetchJson(fd);
        if (data?.progress) renderProgress(data.progress);
        if (!data || !data.ok) {
            generationRunning = false;
            syncButtons();
            return;
        }
        if (data.batch) {
            logProgress('Batch result: saved ' + data.batch.saved + ', duplicates ' + data.batch.duplicates + ', invalid ' + data.batch.invalid + '.');
        }
        if (data.done) {
            generationRunning = false;
            markStep('stepFinish', 'success');
            showAlert('Generation finished.', 'success');
            syncButtons();
            return;
        }
        setTimeout(processBatchLoop, 300);
    } catch (e) {
        generationRunning = false;
        syncButtons();
        showAlert('Network error during generation. You can continue generation safely.');
    }
}

document.getElementById('classSelect').addEventListener('change', () => {
    updateBookOptions();
    currentBookId = null;
    refreshAllChapterSelects();
});
document.getElementById('bookSelect').addEventListener('change', () => {
    currentBookId = document.getElementById('bookSelect').value ? parseInt(document.getElementById('bookSelect').value, 10) : null;
    refreshAllChapterSelects();
});
document.getElementById('bookFile').addEventListener('change', detectPdfPageCount);
document.getElementById('addChapterRow').addEventListener('click', () => {
    document.getElementById('chapterRows').insertAdjacentHTML('beforeend', chapterRowTemplate());
});
document.getElementById('addAllChaptersBtn').addEventListener('click', addAllChapters);
document.getElementById('chapterRows').addEventListener('click', e => {
    if (e.target.classList.contains('remove-row')) {
        e.target.closest('tr').remove();
        ensureAtLeastOneRow();
    }
});
document.getElementById('chapterRows').addEventListener('change', e => {
    if (e.target.classList.contains('chapter-select')) {
        const row = e.target.closest('tr');
        const selectedValue = e.target.value;
        if (selectedValue) {
            try {
                const data = JSON.parse(decodeURIComponent(selectedValue));
                row.querySelector('.chapter-no').value = data.chapter_no;
                row.querySelector('.chapter-name').value = data.chapter_name;
            } catch (err) {
                console.error(err);
            }
        }
    }
});
document.getElementById('genMode').addEventListener('change', applyModeVisibility);
document.getElementById('calcPagesBtn').addEventListener('click', calculatePages);
document.getElementById('pageOffset').addEventListener('change', () => {
    document.querySelectorAll('.pdf-pages').forEach(cell => {
        cell.textContent = '—';
        cell.classList.remove('text-success', 'text-danger');
    });
});

document.getElementById('startBtn').addEventListener('click', async () => {
    generationRunning = false;
    syncButtons();
    const started = await initJob();
    if (started) {
        generationRunning = true;
        syncButtons();
        processBatchLoop();
    }
    syncButtons();
});

document.getElementById('cancelBtn').addEventListener('click', async () => {
    if (!currentJobId) return;
    generationRunning = false;
    syncButtons();
    const fd = new FormData();
    fd.append('action', 'cancel');
    fd.append('csrf_token', document.getElementById('csrfToken').value);
    fd.append('job_id', currentJobId);
    const data = await fetchJson(fd);
    if (data?.progress) renderProgress(data.progress);
    if (data?.ok) showAlert('Generation cancelled.', 'warning');
});

document.getElementById('retryBtn').addEventListener('click', async () => {
    if (!currentJobId) return;
    const fd = new FormData();
    fd.append('action', 'retry_failed');
    fd.append('csrf_token', document.getElementById('csrfToken').value);
    fd.append('job_id', currentJobId);
    const data = await fetchJson(fd);
    if (!data || !data.ok) {
        return;
    }
    if (data.progress) renderProgress(data.progress);
    generationRunning = true;
    syncButtons();
    processBatchLoop();
});

document.getElementById('continueBtn').addEventListener('click', async () => {
    if (!currentJobId) {
        showAlert('Start a generation job first.');
        return;
    }
    hideAlert();
    const fd = new FormData();
    fd.append('action', 'continue_job');
    fd.append('csrf_token', document.getElementById('csrfToken').value);
    fd.append('job_id', currentJobId);
    const data = await fetchJson(fd);
    if (!data || !data.ok) {
        return;
    }
    if (data.progress) renderProgress(data.progress);
    generationRunning = true;
    syncButtons();
    processBatchLoop();
});

document.getElementById('viewTextBtn').addEventListener('click', async () => {
    if (!currentJobId) return;
    const fd = new FormData();
    fd.append('action', 'get_extracted_text');
    fd.append('csrf_token', document.getElementById('csrfToken').value);
    fd.append('job_id', currentJobId);
    fd.append('chapter_index', String(currentChapterIndex));
    const data = await fetchJson(fd);
    if (!data || !data.ok) {
        return;
    }
    document.getElementById('previewModalTitle').textContent = 'Extracted chapter text';
    document.getElementById('previewModalBody').textContent = data.text || '';
    new bootstrap.Modal(document.getElementById('previewModal')).show();
});

document.getElementById('viewSavedBtn').addEventListener('click', async () => {
    if (!currentJobId) return;
    const fd = new FormData();
    fd.append('action', 'get_saved_questions');
    fd.append('csrf_token', document.getElementById('csrfToken').value);
    fd.append('job_id', currentJobId);
    fd.append('chapter_index', String(currentChapterIndex));
    const data = await fetchJson(fd);
    if (!data || !data.ok) {
        return;
    }
    const lines = (data.items || []).map(item => {
        if (item.type === 'mcq') {
            return `[MCQ #${item.id}] ${item.question}\nA) ${item.option_a}\nB) ${item.option_b}\nC) ${item.option_c}\nD) ${item.option_d}\nAnswer: ${item.correct_option}\n`;
        }
        return `[${String(item.type).toUpperCase()} #${item.id}] ${item.question}\n`;
    });
    document.getElementById('previewModalTitle').textContent = 'Saved questions';
    document.getElementById('previewModalBody').textContent = lines.join('\n') || 'No saved questions yet.';
    new bootstrap.Modal(document.getElementById('previewModal')).show();
});

document.addEventListener('DOMContentLoaded', () => {
    ensureAtLeastOneRow();
    applyModeVisibility();
    resetSteps();
    syncButtons();
});
</script>

<?php include_once __DIR__ . '/../footer.php'; ?>
