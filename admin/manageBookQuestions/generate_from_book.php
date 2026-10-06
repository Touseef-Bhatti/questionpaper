<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

$driveSyncResult = ['books_missing' => 0, 'notes_missing' => 0];
// Drive reconciliation is intentionally manual. Only read the locally stored
// missing count here so an unavailable Drive cannot delay the page response.
$bookUploadsTableCheck = $conn->query("SHOW TABLES LIKE 'book_uploads'");
if ($bookUploadsTableCheck && $bookUploadsTableCheck->num_rows > 0) {
    $driveStatusColumnCheck = $conn->query("SHOW COLUMNS FROM book_uploads LIKE 'drive_status'");
    if ($driveStatusColumnCheck && $driveStatusColumnCheck->num_rows > 0) {
        $missingBooksQuery = $conn->query("SELECT COUNT(*) AS cnt FROM book_uploads WHERE drive_status = 'missing'");
        if ($missingBooksQuery && ($missingBooksRow = $missingBooksQuery->fetch_assoc())) {
            $driveSyncResult['books_missing'] = (int) $missingBooksRow['cnt'];
        }
    }
}

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
/* Scoped responsive styles for the textbook content pipeline. */
.generator-container {
    width: 100%;
    max-width: 1480px;
    margin: 0 auto;
    padding: 1.25rem 0.75rem 3rem;
}

@media (min-width: 768px) {
    .generator-container {
        padding: 1.75rem 1.5rem 4rem;
    }
}

/* Editorial operations-console direction: ink, paper, and a single mint accent. */
.generator-container .card {
    padding: 0 !important;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: #fffdfa;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.generator-container .card::before {
    display: none !important;
}

.generator-container .card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 16px 36px rgba(15, 23, 42, 0.09);
}

.generator-container .card-header {
    background: #fffdfa;
    border-bottom: 1px solid #e2e8f0;
    padding: 1rem 1.15rem;
    font-weight: 750;
    color: #172033;
    letter-spacing: -0.01em;
}

.generator-container .card-body {
    padding: 1.15rem;
}

@media (min-width: 768px) {
    .generator-container .card-header {
        padding: 1.1rem 1.35rem;
    }
    .generator-container .card-body {
        padding: 1.35rem;
    }
}

.generator-hero {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 85% 10%, rgba(62, 207, 142, 0.2), transparent 28%),
        linear-gradient(135deg, #111827 0%, #1e293b 58%, #263449 100%);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 18px;
    color: #ffffff;
    padding: 1.5rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
}

.generator-hero::after {
    content: "";
    position: absolute;
    width: 240px;
    height: 240px;
    right: -90px;
    bottom: -130px;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 50%;
    pointer-events: none;
}

.hero-copy,
.hero-actions {
    position: relative;
    z-index: 1;
}

.hero-kicker,
.card-kicker {
    color: #6ee7b7;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.generator-hero h1 {
    font-size: clamp(1.45rem, 2.5vw, 2.2rem);
    font-weight: 800;
    letter-spacing: -0.04em;
    margin: 0;
    color: #ffffff;
}

.hero-title-row {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    flex-wrap: wrap;
    margin: 0.35rem 0 0.45rem;
}

.generator-hero p {
    max-width: 720px;
    font-size: 0.92rem;
    line-height: 1.65;
    color: rgba(255, 255, 255, 0.85);
    margin-bottom: 0;
}

.hero-route {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 1rem;
    color: rgba(255,255,255,0.72);
    font-size: 0.76rem;
}

.hero-route code,
.drive-route-preview code {
    color: #d1fae5;
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(110, 231, 183, 0.28);
    border-radius: 999px;
    padding: 0.28rem 0.6rem;
    font-size: 0.74rem;
}

.hero-route-label {
    font-weight: 800;
    letter-spacing: 0.08em;
    color: rgba(255,255,255,0.55);
}

.pipeline-steps {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.45rem;
    margin-top: 1.35rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255,255,255,0.14);
}

.pipeline-step {
    color: rgba(255,255,255,0.58);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
}

.pipeline-step span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.55rem;
    height: 1.55rem;
    margin-right: 0.35rem;
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 50%;
    color: rgba(255,255,255,0.72);
    font-size: 0.65rem;
}

.pipeline-step.active { color: #d1fae5; }
.pipeline-step.active span { background: #34d399; border-color: #34d399; color: #10231d; }

.step-marker {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.8rem;
    height: 1.8rem;
    margin-right: 0.55rem;
    border-radius: 8px;
    background: #ecfdf5;
    color: #047857;
    font-size: 0.68rem;
    font-weight: 850;
    letter-spacing: 0.04em;
}

.upload-card { border-top: 3px solid #34d399 !important; }

.drive-route-preview {
    padding: 0.85rem;
    margin-top: 1rem;
    border: 1px solid #d1fae5;
    border-radius: 12px;
    background: #f0fdf4;
}

.drive-route-preview .route-heading {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: #065f46;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.drive-route-preview code {
    display: block;
    overflow-wrap: anywhere;
    margin-top: 0.55rem;
    color: #065f46;
    background: #dcfce7;
    border-color: #bbf7d0;
    border-radius: 8px;
}

.drive-route-preview small { display: block; margin-top: 0.5rem; color: #3f6656; line-height: 1.45; }

.section-note {
    color: #64748b;
    font-size: 0.78rem;
    line-height: 1.5;
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

/* Section 04: focused generation control room. */
.generator-container .generation-panel {
    position: relative;
    overflow: hidden;
    border-color: #b7e4d0;
    background: #fbfffd;
    box-shadow: 0 16px 34px rgba(15, 118, 110, 0.1);
}

.generator-container .generation-panel::after {
    content: "";
    position: absolute;
    width: 220px;
    height: 220px;
    right: -100px;
    top: -120px;
    border: 1px solid rgba(15, 118, 110, 0.13);
    border-radius: 50%;
    pointer-events: none;
}

.generator-container .generation-panel .card-header {
    position: relative;
    z-index: 1;
    padding: 1.25rem 1.35rem 1rem;
    border-bottom: 0;
    background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
    color: #ffffff;
}

.generation-panel-header,
.generation-heading,
.generation-source-badge {
    display: flex;
    align-items: center;
}

.generation-panel-header {
    justify-content: space-between;
    gap: 1rem;
}

.generation-heading {
    gap: 0.75rem;
}

.generation-step-marker {
    flex: 0 0 auto;
    margin-right: 0;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.32);
    color: #ffffff;
}

.generation-panel .card-kicker {
    color: #a7f3d0;
}

.generation-panel h2 {
    margin: 0.12rem 0 0;
    color: #ffffff;
    font-size: clamp(1.1rem, 2vw, 1.35rem);
    font-weight: 800;
    letter-spacing: -0.025em;
}

.generation-panel .generation-subtitle {
    max-width: 580px;
    margin: 0.32rem 0 0;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.78rem;
    line-height: 1.45;
}

.generation-source-badge {
    flex: 0 0 auto;
    gap: 0.4rem;
    min-height: 34px;
    padding: 0.42rem 0.7rem;
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    color: #ecfdf5;
    font-size: 0.7rem;
    font-weight: 750;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.generation-source-badge.is-ready {
    background: #d1fae5;
    border-color: #a7f3d0;
    color: #065f46;
}

.generation-source-badge.is-warning {
    background: #fef3c7;
    border-color: #fde68a;
    color: #92400e;
}

.generator-container .generation-panel .card-body {
    position: relative;
    z-index: 1;
    padding: 1.2rem 1.35rem 1.35rem;
}

.generation-controls {
    display: grid;
    grid-template-columns: minmax(250px, 1.35fr) minmax(280px, 1fr) minmax(190px, 0.7fr);
    gap: 1rem;
    align-items: end;
}

.generation-field,
.generation-targets,
.generation-action {
    min-width: 0;
}

.generation-label {
    display: block;
    margin-bottom: 0.45rem;
    color: #334155;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.generation-field .form-select {
    min-height: 48px;
    border-color: #b7e4d0;
    background-color: #ffffff;
    font-weight: 650;
}

.generation-field-note,
.generation-action-note {
    display: block;
    margin-top: 0.42rem;
    color: #64748b;
    font-size: 0.72rem;
    line-height: 1.4;
}

.target-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.55rem;
}

.target-input-card {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    padding: 0.6rem 0.65rem 0.55rem;
    border: 1px solid #dbe7e1;
    border-radius: 11px;
    background: #ffffff;
    cursor: text;
    transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.target-input-card:hover,
.target-input-card:focus-within {
    border-color: #34d399;
    box-shadow: 0 5px 14px rgba(15, 118, 110, 0.1);
    transform: translateY(-1px);
}

.target-input-card > span {
    color: #0f766e;
    font-size: 0.7rem;
    font-weight: 850;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.target-input-card > small {
    min-height: 1.8em;
    color: #64748b;
    font-size: 0.65rem;
    line-height: 1.25;
}

.target-input-card input {
    width: 100%;
    min-height: 34px;
    margin-top: 0.22rem;
    border: 0;
    border-top: 1px solid #e2e8f0;
    border-radius: 0;
    color: #172033;
    font-size: 1.05rem;
    font-weight: 800;
    outline: 0;
}

.generation-action .btn {
    min-height: 48px;
    font-weight: 800;
    letter-spacing: 0.01em;
}

.generation-action-note i {
    color: #0f766e;
}

@media (max-width: 991.98px) {
    .generation-controls {
        grid-template-columns: 1fr 1fr;
    }
    .generation-field {
        grid-column: 1 / -1;
    }
}

@media (max-width: 575.98px) {
    .generator-container .generation-panel .card-header,
    .generator-container .generation-panel .card-body {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    .generation-panel-header {
        align-items: flex-start;
        flex-direction: column;
    }
    .generation-source-badge {
        margin-left: 3rem;
    }
    .generation-controls {
        grid-template-columns: 1fr;
    }
    .generation-field {
        grid-column: auto;
    }
}

/* Touch targets and form controls */
.form-control, .form-select, .btn {
    min-height: 42px;
    border-radius: 9px;
}

.form-control:focus, .form-select:focus {
    border-color: #34d399;
    box-shadow: 0 0 0 0.2rem rgba(52, 211, 153, 0.16);
}

.generator-container .btn-primary {
    background: #0f766e;
    border-color: #0f766e;
}

.generator-container .btn-primary:hover,
.generator-container .btn-primary:focus {
    background: #115e59;
    border-color: #115e59;
}

.generator-container .card-header > span,
.generator-container .card-header > div {
    display: inline-flex;
    align-items: center;
}

@media (max-width: 575.98px) {
    .btn-mobile-full {
        width: 100% !important;
    }
    .pipeline-steps {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        row-gap: 0.8rem;
    }
}
</style>

<div class="generator-container">
    <div class="generator-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="hero-copy">
            <div class="hero-kicker">Admin workspace / Book intelligence</div>
            <div class="hero-title-row">
                <h1><i class="fa-solid fa-book-bookmark me-2"></i>Textbook Question Generator</h1>
                <?php if ($apiKeyConfigured): ?>
                    <span class="badge bg-success text-white py-1 px-2"><i class="fa-solid fa-check-circle me-1"></i>AI key active</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark py-1 px-2"><i class="fa-solid fa-triangle-exclamation me-1"></i>AI key missing</span>
                <?php endif; ?>
            </div>
            <p>Build a reliable question source from a textbook: upload once, map printed pages, generate a focused chapter draft, and send it to review.</p>
            <div class="hero-route" aria-label="Google Drive storage routing">
                <span class="hero-route-label">Books</span>
                <code>AhmadLearningHub / Books / Class X / BookName</code>
                <span class="hero-route-label">Notes</span>
                <code>AhmadLearningHub / Notes / Class X / Book / Chapter / Admin|User</code>
            </div>
            <div class="pipeline-steps" aria-label="Textbook workflow steps">
                <div class="pipeline-step active"><span>01</span>Upload</div>
                <div class="pipeline-step"><span>02</span>Map pages</div>
                <div class="pipeline-step"><span>03</span>Generate</div>
                <div class="pipeline-step"><span>04</span>Review</div>
            </div>
        </div>
        <div class="hero-actions d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-success" id="syncDriveBtn" title="Import Drive files into the database">
                <i class="fa-solid fa-arrows-rotate me-1"></i>Sync Drive
            </button>
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
            <div class="flex-grow-1">
                <strong>Gemini API Key Required:</strong> Add <code>GEMINIAPIKEYFORBOOKQUESTIONS</code> to your environment file to enable question generation.
            </div>
        </div>
    <?php endif; ?>
    <?php if (($driveSyncResult['books_missing'] ?? 0) > 0): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 mb-3">
            <i class="fa-solid fa-cloud-slash fs-5 flex-shrink-0"></i>
            <div>
                <strong><?= (int) $driveSyncResult['books_missing'] ?> textbook record(s) are marked “Deleted from Google Drive”.</strong>
                <div class="small mt-1">Select a marked book below to re-upload a PDF and preserve its record and chapter mappings.</div>
                <button type="button" class="btn btn-sm btn-dark mt-2" id="purgeMissingBooksBtn">
                    <i class="fa-solid fa-trash-can me-1"></i>Remove missing textbook records from database
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div id="alertBox" class="alert d-none mb-3" role="alert"></div>

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

    <div class="row g-3 g-xl-4">
        <div class="col-12 col-xl-4">
            <div class="card h-100 upload-card">
                <div class="card-header">
                    <span class="step-marker">01</span>
                    <div><span class="card-kicker d-block">Storage</span><strong><i class="fa-solid fa-cloud-arrow-up me-2 text-primary"></i>Upload textbook</strong></div>
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
                                <span>Google Drive only</span>
                                <span class="badge bg-light text-dark border">Limit: <?= (int) $uploadLimitMb ?> MB</span>
                            </div>
                        </div>

                        <div class="drive-route-preview" aria-live="polite">
                            <div class="route-heading"><i class="fa-brands fa-google-drive"></i>Book destination</div>
                            <code id="bookDrivePath">AhmadLearningHub / Books / Class X / Select book</code>
                            <small>Textbooks use Books; class notes use the separate Notes root with Chapter/Admin or User folders.</small>
                        </div>

                        <button class="btn btn-primary w-100 mt-2" type="submit" id="uploadSubmitBtn">
                            <i class="fa-solid fa-upload me-1"></i>Upload &amp; Store Book
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8 d-flex flex-column gap-3 gap-xl-4">
            <div class="card">
                <div class="card-header">
                    <span class="step-marker">02</span>
                    <div><span class="card-kicker d-block">Library</span><strong><i class="fa-solid fa-folder-open me-2 text-primary"></i>Uploaded books</strong></div>
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
                    <div class="d-none mt-3" id="replaceBookWrap">
                        <div class="alert alert-danger d-flex align-items-start gap-2 mb-2">
                            <i class="fa-solid fa-cloud-slash mt-1"></i>
                            <div><strong>This textbook was deleted from Google Drive.</strong><div class="small">Re-upload a PDF to keep this book record and its chapter mappings.</div></div>
                        </div>
                        <div class="input-group">
                            <input type="file" class="form-control" id="replaceBookFile" accept=".pdf,application/pdf">
                            <button class="btn btn-outline-success" type="button" id="replaceBookBtn"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Re-upload</button>
                            <button class="btn btn-outline-danger" type="button" id="deleteMissingBookBtn"><i class="fa-solid fa-trash me-1"></i>Delete record</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="step-marker">03</span>
                        <span><span class="card-kicker d-block">Mapping</span><strong><i class="fa-solid fa-list-ol me-2 text-primary"></i>Chapter page ranges</strong></span>
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

            <div class="card generation-panel">
                <div class="card-header generation-panel-header">
                    <div class="generation-heading">
                        <span class="step-marker generation-step-marker">04</span>
                        <div>
                            <span class="card-kicker d-block">AI draft</span>
                            <h2><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Generate for review</h2>
                            <p class="generation-subtitle">Choose one mapped chapter, set the question mix, and create an editable draft for approval.</p>
                        </div>
                    </div>
                    <span class="generation-source-badge" id="generationSourceBadge" aria-live="polite">
                        <i class="fa-solid fa-circle-question"></i> Select a stored book
                    </span>
                </div>
                <div class="card-body">
                    <div class="generation-controls">
                        <div class="generation-field">
                            <label class="generation-label" for="generateChapter">Chapter to generate</label>
                            <select class="form-select" id="generateChapter">
                                <option value="">Save chapter ranges first</option>
                            </select>
                            <span class="generation-field-note"><i class="fa-solid fa-circle-info me-1"></i>Only chapters with saved PDF ranges appear here.</span>
                        </div>

                        <div class="generation-targets">
                            <span class="generation-label">Question targets</span>
                            <div class="target-grid">
                                <label class="target-input-card" for="mcqCount">
                                    <span>MCQ</span>
                                    <small>Multiple choice</small>
                                    <input type="number" id="mcqCount" min="0" max="200" value="10" aria-label="MCQ target count">
                                </label>
                                <label class="target-input-card" for="shortCount">
                                    <span>Short</span>
                                    <small>Short questions</small>
                                    <input type="number" id="shortCount" min="0" max="100" value="5" aria-label="Short question target count">
                                </label>
                                <label class="target-input-card" for="longCount">
                                    <span>Long</span>
                                    <small>Long questions</small>
                                    <input type="number" id="longCount" min="0" max="50" value="3" aria-label="Long question target count">
                                </label>
                            </div>
                        </div>

                        <div class="generation-action">
                            <button type="button" class="btn btn-primary w-100" id="startGenerateBtn" disabled>
                                <i class="fa-solid fa-play me-1"></i>Generate draft
                            </button>
                            <span class="generation-action-note"><i class="fa-solid fa-shield-halved me-1"></i>Drafts stay pending until you approve them.</span>
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
const driveSyncUrl = '../google_drive_sync.php';
const bookData = <?= json_encode($books, JSON_UNESCAPED_UNICODE) ?>;
const maxUploadBytes = <?= (int) $uploadLimitBytes ?>;
const aiKeyConfigured = <?= $apiKeyConfigured ? 'true' : 'false' ?>;
let uploads = [];
let currentUpload = null;
let currentChapters = [];
let currentRanges = {};
let currentJobId = '';
let generationRunning = false;

function updateGenerationControls() {
    const button = document.getElementById('startGenerateBtn');
    const chapterSelect = document.getElementById('generateChapter');
    const badge = document.getElementById('generationSourceBadge');
    if (!button || !chapterSelect || !badge) return;

    const sourceAvailable = !!currentUpload && currentUpload.source_available !== false;
    const chapterSelected = chapterSelect.value !== '';
    button.disabled = !aiKeyConfigured || !sourceAvailable || !chapterSelected || generationRunning;

    badge.classList.remove('is-ready', 'is-warning');
    if (!currentUpload) {
        badge.innerHTML = '<i class="fa-solid fa-circle-question"></i> Select a stored book';
        return;
    }
    if (!sourceAvailable) {
        badge.classList.add('is-warning');
        badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Source unavailable';
        return;
    }
    if (!aiKeyConfigured) {
        badge.classList.add('is-warning');
        badge.innerHTML = '<i class="fa-solid fa-key"></i> AI key required';
        return;
    }

    badge.classList.add('is-ready');
    const sourceLabel = currentUpload.source_type === 'local' ? 'Local copy ready' : 'Stored book ready';
    badge.innerHTML = `<i class="fa-solid fa-circle-check"></i> ${sourceLabel}`;
}

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
    if (!fd.has('csrf_token')) {
        fd.append('csrf_token', csrfToken);
    }
    try {
        const res = await fetch(apiUrl, { method: 'POST', body: fd });
        const text = await res.text();
        const contentType = (res.headers.get('content-type') || '').toLowerCase();
        const trimmedText = text.trimStart();
        if (res.redirected || contentType.includes('text/html')) {
            showAlert('Your admin session may have expired. Reload the page and sign in again.', 'warning');
            return null;
        }
        if (contentType.includes('application/pdf') || trimmedText.startsWith('%PDF-')) {
            showAlert('The server returned a PDF instead of a JSON result. The textbook was not processed; reload the page and try again.', 'danger');
            return null;
        }
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            const detail = text.replace(/\s+/g, ' ').trim().slice(0, 240);
            showAlert(`The server returned an invalid response${detail ? `: ${detail}` : '.'}`, 'danger');
            return null;
        }
        if (!res.ok && data?.ok !== false) {
            data.ok = false;
            data.error = data.error || `Request failed with HTTP ${res.status}.`;
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
        updateBookDrivePath();
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
    updateBookDrivePath();
}

function updateBookDrivePath() {
    const classSelect = document.getElementById('uploadClass');
    const bookSelect = document.getElementById('uploadBook');
    const path = document.getElementById('bookDrivePath');
    if (!classSelect || !bookSelect || !path) return;

    const classOption = classSelect.options[classSelect.selectedIndex];
    const classValue = (classOption?.textContent || '').trim().replace(/^class\s*/i, '');
    const classFolder = classValue ? `Class ${classValue.replace(/[^0-9A-Za-z_-]/g, '')}` : 'Class X';
    const bookName = bookSelect.options[bookSelect.selectedIndex]?.textContent?.trim() || 'Select book';
    path.textContent = `AhmadLearningHub / Books / ${classFolder} / ${bookName}`;
}

function renderUploads() {
    const select = document.getElementById('storedBookSelect');
    const selected = select.value;
    select.innerHTML = '<option value="">Select uploaded book</option>';
    uploads.forEach(upload => {
        const opt = document.createElement('option');
        opt.value = upload.id;
        const sourceState = upload.source_type === 'local'
            ? ' · local copy'
            : upload.source_available === false
                ? ' · source unavailable'
                : ' · Drive ready';
        const pageState = Number(upload.pdf_page_count || 0) > 0
            ? `${upload.pdf_page_count} pages`
            : 'PDF pages pending';
        opt.textContent = `${upload.class_name} - ${upload.book_name} (${upload.mapped_chapters || 0} mapped, ${pageState}${sourceState})`;
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
    updateGenerationControls();
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
    driveLink.classList.toggle('disabled', !currentUpload.drive_url || currentUpload.drive_status === 'missing');
    document.getElementById('replaceBookWrap').classList.toggle('d-none', currentUpload.drive_status !== 'missing' || currentUpload.source_type === 'local');
    
    const metaContainer = document.getElementById('storedBookMeta');
    metaContainer.innerHTML = `
        <div class="d-flex flex-wrap gap-2 align-items-center small text-muted">
            <span class="badge bg-light text-dark border"><i class="fa-solid fa-file-pdf text-danger me-1"></i>${escapeHtml(currentUpload.original_filename)}</span>
            <span class="badge bg-light text-dark border"><i class="fa-solid fa-file-lines me-1"></i>${Number(currentUpload.pdf_page_count || 0) > 0 ? currentUpload.pdf_page_count + ' PDF Pages' : 'PDF pages pending'}</span>
            ${currentUpload.page_offset ? `<span class="badge bg-light text-dark border">Offset: ${currentUpload.page_offset}</span>` : ''}
        </div>`;
    const sourceBadge = currentUpload.source_type === 'local'
        ? '<span class="badge bg-info-subtle text-info-emphasis border ms-1"><i class="fa-solid fa-hard-drive me-1"></i>Local copy ready</span>'
        : currentUpload.source_available === false
            ? '<span class="badge bg-danger-subtle text-danger-emphasis border ms-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>Source unavailable</span>'
            : '<span class="badge bg-success-subtle text-success-emphasis border ms-1"><i class="fa-brands fa-google-drive me-1"></i>Stored book ready</span>';
    metaContainer.insertAdjacentHTML('beforeend', sourceBadge);
    updateGenerationControls();
}

async function replaceBookFile() {
    if (!currentUpload) {
        showAlert('Select a stored book first.', 'warning');
        return;
    }
    const input = document.getElementById('replaceBookFile');
    if (!input.files || !input.files[0]) {
        showAlert('Choose a replacement PDF first.', 'warning');
        return;
    }
    const btn = document.getElementById('replaceBookBtn');
    btn.disabled = true;
    const oldText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Uploading...';
    const fd = new FormData();
    fd.append('action', 'replace_book');
    fd.append('upload_id', currentUpload.id);
    fd.append('book_file', input.files[0]);
    fd.append('csrf_token', csrfToken);
    try {
        const data = await postForm(fd);
        if (data?.ok) {
            uploads = data.uploads || uploads;
            renderUploads();
            document.getElementById('storedBookSelect').value = currentUpload.id;
            await loadUploadDetails(currentUpload.id);
            showAlert('Textbook re-uploaded to Google Drive. The existing book record was preserved.', 'success');
        }
    } finally {
        input.value = '';
        btn.disabled = false;
        btn.innerHTML = oldText;
    }
}

async function deleteMissingBookRecord() {
    if (!currentUpload || currentUpload.drive_status !== 'missing') return;
    if (!confirm('This textbook is already deleted from Google Drive. Delete its database record and chapter mappings?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_book_record');
    fd.append('upload_id', currentUpload.id);
    fd.append('csrf_token', csrfToken);
    const data = await postForm(fd);
    if (data?.ok) {
        currentUpload = null;
        uploads = data.uploads || [];
        renderUploads();
        document.getElementById('storedBookSelect').value = '';
        document.getElementById('storedBookMeta').innerHTML = '';
        document.getElementById('replaceBookWrap').classList.add('d-none');
        showAlert('Deleted textbook record and its chapter mappings.', 'success');
    }
}

async function purgeMissingBookRecords() {
    if (!confirm('Permanently remove every textbook record marked Deleted from Google Drive, including chapter mappings and generated drafts?')) return;
    const btn = document.getElementById('purgeMissingBooksBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Removing...';
    }
    const fd = new FormData();
    fd.append('action', 'purge_missing_records');
    try {
        const data = await postForm(fd);
        if (data?.ok) {
            currentUpload = null;
            uploads = data.uploads || [];
            renderUploads();
            document.getElementById('storedBookSelect').value = '';
            document.getElementById('storedBookMeta').innerHTML = '';
            document.getElementById('replaceBookWrap').classList.add('d-none');
            showAlert(`Removed ${data.deleted_count || 0} missing textbook record(s) from the database.`, 'success');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-trash-can me-1"></i>Remove missing textbook records from database';
        }
    }
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
document.getElementById('generateChapter').addEventListener('change', updateGenerationControls);

function renderGenerateChapters() {
    const select = document.getElementById('generateChapter');
    const previousChapterId = select.value;
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
    } else if (previousChapterId && [...select.options].some(option => option.value === previousChapterId)) {
        select.value = previousChapterId;
    } else {
        select.selectedIndex = 1;
    }
    updateGenerationControls();
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
        updateGenerationControls();
        return;
    }
    if (data.done) {
        generationRunning = false;
        showAlert('Draft generation finished! Click "Review generated questions" to inspect and approve them.', 'success');
        document.getElementById('stopGenerateBtn').classList.add('d-none');
        updateGenerationControls();
        loadReviewJobs();
        return;
    }
    setTimeout(processBatchLoop, 300);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
}

document.getElementById('uploadClass').addEventListener('change', () => populateBooks(document.getElementById('uploadClass'), document.getElementById('uploadBook')));
document.getElementById('uploadBook').addEventListener('change', updateBookDrivePath);

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
        return;
    }
    currentUpload = null;
    currentChapters = [];
    currentRanges = {};
    document.getElementById('rangeRows').innerHTML = '<tr><td colspan="4" class="text-muted text-center py-4">Select an uploaded book to view and configure chapters.</td></tr>';
    document.getElementById('chapterCountBadge').textContent = '0 chapters';
    document.getElementById('storedBookMeta').innerHTML = '';
    document.getElementById('replaceBookWrap').classList.add('d-none');
    document.getElementById('driveLink').href = '#';
    document.getElementById('driveLink').classList.add('disabled');
    renderGenerateChapters();
});
document.getElementById('replaceBookBtn').addEventListener('click', replaceBookFile);
document.getElementById('deleteMissingBookBtn').addEventListener('click', deleteMissingBookRecord);
document.getElementById('purgeMissingBooksBtn')?.addEventListener('click', purgeMissingBookRecords);

document.getElementById('saveRangesBtn').addEventListener('click', async () => {
    if (!currentUpload) {
        showAlert('Select an uploaded book first.', 'warning');
        return;
    }
    if (currentUpload.source_available === false) {
        showAlert('This stored book has no usable source. Upload or restore the book first.', 'warning');
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
    if (currentUpload.source_available === false) {
        showAlert('This stored book has no usable source. Upload or restore the book first.', 'warning');
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
    updateGenerationControls();
    processBatchLoop();
});

document.getElementById('stopGenerateBtn').addEventListener('click', () => {
    generationRunning = false;
    document.getElementById('stopGenerateBtn').classList.add('d-none');
    updateGenerationControls();
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

document.getElementById('syncDriveBtn').addEventListener('click', async () => {
    const btn = document.getElementById('syncDriveBtn');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Syncing...';

    const fd = new FormData();
    fd.append('action', 'sync_drive');
    fd.append('csrf_token', csrfToken);
    try {
        const response = await fetch(driveSyncUrl, { method: 'POST', body: fd });
        const text = await response.text();
        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        if (response.redirected || contentType.includes('text/html')) {
            throw new Error('Your admin session may have expired. Reload the page and sign in again.');
        }
        if (contentType.includes('application/pdf') || text.trimStart().startsWith('%PDF-')) {
            throw new Error('The Drive sync endpoint returned a PDF instead of JSON.');
        }
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            throw new Error('The Drive sync endpoint returned an invalid response.');
        }
        if (!data.ok) {
            showAlert(data.error || 'Google Drive sync failed.', 'danger');
        } else {
            showAlert('Drive sync complete: ' + (data.books_imported || 0) + ' book file(s), ' + (data.notes_imported || 0) + ' note file(s) imported; marked ' + (data.books_missing || 0) + ' book record(s) and ' + (data.notes_missing || 0) + ' note record(s) as deleted from Drive.', 'success');
            window.location.reload();
        }
    } catch (error) {
        showAlert('Google Drive sync failed. Check the Drive connection.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = oldHtml;
    }
});

document.addEventListener('DOMContentLoaded', refreshData);
</script>

<?php include_once __DIR__ . '/../footer.php'; ?>
