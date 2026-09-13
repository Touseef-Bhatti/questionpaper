<?php
/**
 * Admin panel for managing class notes — approval workflow, upload, delete, and edit.
 * Dynamic Class, Book (Subject), and Chapter dropdowns populated from DB tables: class, book, chapter.
 */
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

// Ensure class_notes table exists
$conn->query("CREATE TABLE IF NOT EXISTS class_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    subject VARCHAR(100),
    class ENUM('9','10','11','12') NOT NULL,
    chapter VARCHAR(255),
    drive_file_id VARCHAR(255) NOT NULL,
    drive_url VARCHAR(500) NOT NULL,
    original_filename VARCHAR(255),
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT DEFAULT 0,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    uploaded_by INT DEFAULT NULL,
    uploader_name VARCHAR(255),
    uploader_email VARCHAR(255) DEFAULT NULL,
    uploader_type ENUM('admin','user') DEFAULT 'user',
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    INDEX idx_class (class),
    INDEX idx_status (status),
    INDEX idx_subject (subject),
    INDEX idx_class_status (class, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$emailColumnCheck = $conn->query("SHOW COLUMNS FROM class_notes LIKE 'uploader_email'");
if (!$emailColumnCheck || $emailColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE class_notes ADD COLUMN uploader_email VARCHAR(255) DEFAULT NULL AFTER uploader_name");
}

// Handle session messages
$message = $_SESSION['cn_message'] ?? '';
$error = $_SESSION['cn_error'] ?? '';
unset($_SESSION['cn_message'], $_SESSION['cn_error']);

// Load classes from DB `class` table
$classesQuery = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
$dbClasses = [];
while ($classesQuery && $cRow = $classesQuery->fetch_assoc()) {
    $dbClasses[] = $cRow;
}

// Load books from DB `book` table grouped by class_id
$booksQuery = $conn->query("SELECT book_id, book_name, class_id FROM book ORDER BY class_id ASC, book_name ASC");
$dbBooksByClass = [];
$allUniqueSubjects = [];
while ($booksQuery && $bRow = $booksQuery->fetch_assoc()) {
    $cid = (string)$bRow['class_id'];
    $dbBooksByClass[$cid][] = [
        'book_id' => $bRow['book_id'],
        'book_name' => $bRow['book_name']
    ];
    if (!in_array($bRow['book_name'], $allUniqueSubjects, true)) {
        $allUniqueSubjects[] = $bRow['book_name'];
    }
}
sort($allUniqueSubjects);

// Current filter parameters
$statusFilter = $_GET['status'] ?? 'pending';
$classFilter = $_GET['class'] ?? '';
$subjectFilter = $_GET['subject'] ?? '';
$chapterFilter = $_GET['chapter'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];
$types = '';

if (in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $where[] = "n.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if (!empty($classFilter) && in_array($classFilter, ['9','10','11','12'])) {
    $where[] = "n.class = ?";
    $params[] = $classFilter;
    $types .= 's';
}

if (!empty($subjectFilter)) {
    $where[] = "n.subject = ?";
    $params[] = $subjectFilter;
    $types .= 's';
}

if (!empty($chapterFilter)) {
    $where[] = "n.chapter = ?";
    $params[] = $chapterFilter;
    $types .= 's';
}

if (!empty($searchFilter)) {
    $where[] = "(n.title LIKE ? OR n.subject LIKE ? OR n.chapter LIKE ? OR n.uploader_name LIKE ?)";
    $sp = "%{$searchFilter}%";
    $params[] = $sp;
    $params[] = $sp;
    $params[] = $sp;
    $params[] = $sp;
    $types .= 'ssss';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$query = "SELECT n.* FROM class_notes n $whereClause ORDER BY n.created_at DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$notes = [];
while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}
$stmt->close();

// Count by status
$countQuery = $conn->query("SELECT status, COUNT(*) as cnt FROM class_notes GROUP BY status");
$statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
while ($row = $countQuery->fetch_assoc()) {
    $statusCounts[$row['status']] = $row['cnt'];
}
$totalCount = array_sum($statusCounts);

$sourceCounts = ['user' => 0, 'admin' => 0];
$sourceCountQuery = $conn->query("SELECT uploader_type, COUNT(*) as cnt FROM class_notes GROUP BY uploader_type");
while ($sourceCountQuery && ($sRow = $sourceCountQuery->fetch_assoc())) {
    $type = $sRow['uploader_type'] ?: 'user';
    if (isset($sourceCounts[$type])) {
        $sourceCounts[$type] = (int)$sRow['cnt'];
    }
}

$pendingReviewNotes = [];
$userUploadedNotes = [];
$adminUploadedNotes = [];
foreach ($notes as $row) {
    if ($row['status'] === 'pending') {
        $pendingReviewNotes[] = $row;
    }
    if ($row['uploader_type'] === 'admin') {
        $adminUploadedNotes[] = $row;
    } else {
        $userUploadedNotes[] = $row;
    }
}

// Available filter chapters if class or subject is chosen
$filterChapters = [];
if (!empty($subjectFilter)) {
    if (!empty($classFilter)) {
        $cStmt = $conn->prepare("SELECT DISTINCT chapter_name, chapter_no FROM chapter WHERE class_id = ? AND book_name = ? ORDER BY chapter_no ASC, chapter_name ASC");
        $cStmt->bind_param("is", $classFilter, $subjectFilter);
    } else {
        $cStmt = $conn->prepare("SELECT DISTINCT chapter_name, chapter_no FROM chapter WHERE book_name = ? ORDER BY chapter_no ASC, chapter_name ASC");
        $cStmt->bind_param("s", $subjectFilter);
    }
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    while ($row = $cRes->fetch_assoc()) {
        $filterChapters[] = $row;
    }
    $cStmt->close();
}

$csrfToken = generateCSRFToken();

function formatBytes($bytes) {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function adminNotePublicUrl($assetBase, $note) {
    $subject = strtolower(trim((string)($note['subject'] ?? 'general')));
    $subject = trim(preg_replace('/[^a-z0-9]+/i', '-', $subject), '-');
    $title = strtolower(trim((string)($note['title'] ?? 'study-notes')));
    $title = trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-');
    if ($subject === '') $subject = 'general';
    if ($title === '') $title = 'notes';
    return $assetBase . 'class-notes/class-' . rawurlencode((string)$note['class']) . '-' . $subject . '-' . $title . '-' . (int)$note['id'];
}
?>
<?php include '../header.php'; ?>

<style>
.notes-admin-hero {
    background: linear-gradient(135deg, #0f172a, #1e3a8a);
    color: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.25rem;
}
.notes-admin-metric {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 1rem;
    min-height: 100%;
}
.notes-admin-metric span {
    color: #64748b;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.notes-admin-metric strong {
    display: block;
    color: #0f172a;
    font-size: 1.7rem;
    line-height: 1.1;
}
.notes-admin-section {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    height: 100%;
}
.notes-admin-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    padding: .95rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
}
.notes-admin-section-header h3 {
    font-size: .98rem;
    margin: 0;
    color: #0f172a;
    font-weight: 800;
}
.notes-admin-list {
    display: flex;
    flex-direction: column;
}
.notes-admin-item {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: .75rem;
    padding: .9rem 1rem;
    border-bottom: 1px solid #eef2f7;
}
.notes-admin-item:last-child { border-bottom: 0; }
.notes-admin-item-title {
    font-weight: 800;
    color: #172033;
    text-decoration: none;
}
.notes-admin-item-title:hover { color: #2563eb; }
.notes-admin-item-meta {
    color: #64748b;
    font-size: .8rem;
    margin-top: .25rem;
}
.notes-admin-empty {
    padding: 1.25rem;
    color: #64748b;
    font-size: .9rem;
}
@media (max-width: 768px) {
    .notes-admin-item { grid-template-columns: 1fr; }
    .notes-admin-section-header { align-items: flex-start; flex-direction: column; }
}
</style>

<div class="container-fluid py-4">
    <div class="notes-admin-hero">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="mb-1"><i class="fas fa-file-alt text-info"></i> Class Notes Management</h2>
                <p class="mb-0 text-white-50">Review community submissions, manage admin uploads, and keep public study notes organized for students.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-light btn-sm" onclick="testDriveConnection()" id="testDriveBtn">
                    <i class="fab fa-google-drive"></i> Test Drive
                </button>
                <a href="../../google_drive_callback.php" class="btn btn-outline-light btn-sm">
                    <i class="fab fa-google"></i> Connect Drive OAuth
                </a>
                <a href="../../class-notes" target="_blank" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-external-link-alt"></i> Public Hub
                </a>
                <button class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-plus"></i> Admin Upload
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2"><div class="notes-admin-metric"><span>Total Notes</span><strong><?= $totalCount ?></strong></div></div>
        <div class="col-6 col-lg-2"><div class="notes-admin-metric"><span>Pending</span><strong><?= $statusCounts['pending'] ?></strong></div></div>
        <div class="col-6 col-lg-2"><div class="notes-admin-metric"><span>Approved</span><strong><?= $statusCounts['approved'] ?></strong></div></div>
        <div class="col-6 col-lg-2"><div class="notes-admin-metric"><span>Rejected</span><strong><?= $statusCounts['rejected'] ?></strong></div></div>
        <div class="col-6 col-lg-2"><div class="notes-admin-metric"><span>User Uploaded</span><strong><?= $sourceCounts['user'] ?></strong></div></div>
        <div class="col-6 col-lg-2"><div class="notes-admin-metric"><span>Admin Uploaded</span><strong><?= $sourceCounts['admin'] ?></strong></div></div>
    </div>

    <div class="d-none">
        <div>
            <h2 class="mb-1"><i class="fas fa-file-alt text-primary"></i> Class Notes Management</h2>
            <p class="text-muted mb-0">Manage user-uploaded notes with approval workflow & database-driven class/book/chapter taxonomy</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-info" onclick="testDriveConnection()" id="testDriveBtn">
                <i class="fab fa-google-drive"></i> Test Drive
            </button>
            <a href="../../google_drive_callback.php" class="btn btn-outline-success">
                <i class="fab fa-google"></i> Connect Drive OAuth
            </a>
            <a href="../../class-notes" target="_blank" class="btn btn-outline-primary">
                <i class="fas fa-external-link-alt"></i> Public Hub
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fas fa-plus"></i> Upload Notes (Admin)
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <?php
        $sections = [
            ['title' => 'Pending Reviews', 'notes' => $pendingReviewNotes, 'badge' => 'warning text-dark', 'empty' => 'No pending notes need review.'],
            ['title' => 'User Uploaded Notes', 'notes' => $userUploadedNotes, 'badge' => 'primary', 'empty' => 'No user uploaded notes match these filters.'],
            ['title' => 'Admin Uploaded Notes', 'notes' => $adminUploadedNotes, 'badge' => 'info text-dark', 'empty' => 'No admin uploaded notes match these filters.'],
        ];
        ?>
        <?php foreach ($sections as $section): ?>
            <div class="col-lg-4">
                <section class="notes-admin-section">
                    <div class="notes-admin-section-header">
                        <h3><?= htmlspecialchars($section['title']) ?></h3>
                        <span class="badge bg-<?= $section['badge'] ?>"><?= count($section['notes']) ?></span>
                    </div>
                    <div class="notes-admin-list">
                        <?php if (empty($section['notes'])): ?>
                            <div class="notes-admin-empty"><?= htmlspecialchars($section['empty']) ?></div>
                        <?php endif; ?>
                        <?php foreach (array_slice($section['notes'], 0, 5) as $note): ?>
                            <div class="notes-admin-item">
                                <div>
                                    <a class="notes-admin-item-title" href="<?= htmlspecialchars(adminNotePublicUrl('../../', $note)) ?>" target="_blank">
                                        <?= htmlspecialchars($note['title']) ?>
                                    </a>
                                    <div class="notes-admin-item-meta">
                                        Class <?= htmlspecialchars($note['class']) ?> · <?= htmlspecialchars($note['subject'] ?? 'General') ?>
                                        <?= !empty($note['chapter']) ? ' · ' . htmlspecialchars($note['chapter']) : '' ?>
                                        <br><?= htmlspecialchars($note['uploader_name'] ?? 'User') ?>
                                        <?= !empty($note['uploader_email']) ? ' · ' . htmlspecialchars($note['uploader_email']) : '' ?>
                                        · <?= date('M d, Y', strtotime($note['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($note['status'] !== 'approved'): ?>
                                        <button class="btn btn-outline-success" onclick="adminAction(<?= (int)$note['id'] ?>, 'approve')" title="Approve"><i class="fas fa-check"></i></button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-info" onclick="editNote(<?= (int)$note['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                    <?php if ($note['status'] !== 'rejected'): ?>
                                        <button class="btn btn-outline-warning" onclick="rejectNote(<?= (int)$note['id'] ?>)" title="Reject"><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Status Tabs -->
    <div class="card mb-4">
        <div class="card-body p-2">
            <div class="d-flex gap-2 flex-wrap">
                <a href="?status=pending<?= !empty($classFilter) ? '&class='.urlencode($classFilter) : '' ?>" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">
                    ⏳ Pending <span class="badge bg-dark ms-1"><?= $statusCounts['pending'] ?></span>
                </a>
                <a href="?status=approved<?= !empty($classFilter) ? '&class='.urlencode($classFilter) : '' ?>" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-success' : 'btn-outline-success' ?>">
                    ✅ Approved <span class="badge bg-dark ms-1"><?= $statusCounts['approved'] ?></span>
                </a>
                <a href="?status=rejected<?= !empty($classFilter) ? '&class='.urlencode($classFilter) : '' ?>" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-danger' : 'btn-outline-danger' ?>">
                    ❌ Rejected <span class="badge bg-dark ms-1"><?= $statusCounts['rejected'] ?></span>
                </a>
                <a href="?status=all<?= !empty($classFilter) ? '&class='.urlencode($classFilter) : '' ?>" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                    📋 All <span class="badge bg-dark ms-1"><?= $totalCount ?></span>
                </a>
            </div>
        </div>
    </div>

    <!-- Filters Bar (Dependent Dropdowns) -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end" id="filterForm">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                
                <!-- Class Dropdown (from DB class table) -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label fw-bold small">Class</label>
                    <select name="class" id="filterClass" class="form-select form-select-sm">
                        <option value="">All Classes</option>
                        <?php foreach ($dbClasses as $cls): ?>
                            <option value="<?= htmlspecialchars($cls['class_id']) ?>" <?= (string)$classFilter === (string)$cls['class_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Subject (Book) Dropdown (from DB book table) -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-bold small">Subject (Book)</label>
                    <select name="subject" id="filterSubject" class="form-select form-select-sm">
                        <option value="">All Subjects</option>
                        <?php
                        $activeSubjectList = (!empty($classFilter) && isset($dbBooksByClass[$classFilter])) 
                            ? array_column($dbBooksByClass[$classFilter], 'book_name') 
                            : $allUniqueSubjects;
                        ?>
                        <?php foreach ($activeSubjectList as $subj): ?>
                            <option value="<?= htmlspecialchars($subj) ?>" <?= $subjectFilter === $subj ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subj) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Chapter Dropdown (from DB chapter table) -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-bold small">Chapter</label>
                    <select name="chapter" id="filterChapter" class="form-select form-select-sm" <?= empty($filterChapters) && empty($chapterFilter) ? 'disabled' : '' ?>>
                        <option value="">All Chapters</option>
                        <?php foreach ($filterChapters as $chap): ?>
                            <?php 
                            $disp = !empty($chap['chapter_no']) ? "Ch {$chap['chapter_no']}: {$chap['chapter_name']}" : $chap['chapter_name']; 
                            ?>
                            <option value="<?= htmlspecialchars($chap['chapter_name']) ?>" <?= $chapterFilter === $chap['chapter_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($disp) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label fw-bold small">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Title, uploader..." value="<?= htmlspecialchars($searchFilter) ?>">
                </div>

                <!-- Actions -->
                <div class="col-md-2 col-sm-12 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fas fa-filter"></i> Filter</button>
                    <?php if (!empty($classFilter) || !empty($subjectFilter) || !empty($chapterFilter) || !empty($searchFilter)): ?>
                        <a href="?status=<?= urlencode($statusFilter) ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="fas fa-undo"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Notes Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (count($notes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="30"><input type="checkbox" id="selectAll"></th>
                            <th>Title &amp; Chapter</th>
                            <th>Class</th>
                            <th>Subject (Book)</th>
                            <th>Uploader</th>
                            <th>File</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th width="170" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notes as $note): ?>
                        <tr id="note-row-<?= $note['id'] ?>">
                            <td><input type="checkbox" class="note-checkbox" value="<?= $note['id'] ?>"></td>
                            <td>
                                <strong><?= htmlspecialchars($note['title']) ?></strong>
                                <?php if (!empty($note['chapter'])): ?>
                                    <br><small class="text-primary"><i class="fas fa-bookmark me-1"></i><?= htmlspecialchars($note['chapter']) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($note['description'])): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(substr($note['description'], 0, 80)) ?><?= strlen($note['description']) > 80 ? '...' : '' ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-primary">Class <?= htmlspecialchars($note['class']) ?></span></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($note['subject'] ?? '—') ?></span></td>
                            <td>
                                <small>
                                    <strong><?= htmlspecialchars($note['uploader_name'] ?? 'User') ?></strong>
                                    <?php if (!empty($note['uploader_email'])): ?>
                                        <br><a href="mailto:<?= htmlspecialchars($note['uploader_email']) ?>"><?= htmlspecialchars($note['uploader_email']) ?></a>
                                    <?php endif; ?>
                                    <br><span class="badge bg-<?= $note['uploader_type'] === 'admin' ? 'info text-dark' : 'light text-muted border' ?>"><?= ucfirst($note['uploader_type']) ?></span>
                                </small>
                            </td>
                            <td>
                                <small>
                                    <span class="badge bg-light text-dark border"><?= strtoupper($note['original_filename'] ? pathinfo($note['original_filename'], PATHINFO_EXTENSION) : 'FILE') ?></span>
                                    <br><span class="text-muted"><?= formatBytes($note['file_size']) ?></span>
                                </small>
                            </td>
                            <td>
                                <?php
                                $statusBadges = [
                                    'pending' => 'warning text-dark',
                                    'approved' => 'success',
                                    'rejected' => 'danger'
                                ];
                                ?>
                                <span class="badge bg-<?= $statusBadges[$note['status']] ?? 'secondary' ?>"><?= ucfirst($note['status']) ?></span>
                                <?php if ($note['status'] === 'rejected' && !empty($note['rejection_reason'])): ?>
                                    <br><small class="text-danger" title="<?= htmlspecialchars($note['rejection_reason']) ?>">
                                        <i class="fas fa-info-circle"></i> <?= htmlspecialchars(substr($note['rejection_reason'], 0, 25)) ?>...
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= date('M d, Y', strtotime($note['created_at'])) ?></small></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= htmlspecialchars($note['drive_url']) ?>" target="_blank" class="btn btn-outline-primary" title="Preview on Google Drive">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn btn-outline-info" onclick="editNote(<?= $note['id'] ?>)" title="Edit Note Details">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($note['status'] !== 'approved'): ?>
                                    <button class="btn btn-outline-success" onclick="adminAction(<?= $note['id'] ?>, 'approve')" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($note['status'] !== 'rejected'): ?>
                                    <button class="btn btn-outline-warning" onclick="rejectNote(<?= $note['id'] ?>)" title="Reject">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-danger" onclick="adminAction(<?= $note['id'] ?>, 'delete')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bulk Actions -->
            <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex gap-2 align-items-center">
                    <span class="text-muted small">Bulk Actions:</span>
                    <button class="btn btn-sm btn-success" onclick="bulkAction('approve')"><i class="fas fa-check"></i> Approve Selected</button>
                    <button class="btn btn-sm btn-warning" onclick="bulkAction('reject')"><i class="fas fa-times"></i> Reject Selected</button>
                    <button class="btn btn-sm btn-danger" onclick="bulkAction('delete')"><i class="fas fa-trash"></i> Delete Selected</button>
                </div>
                <div class="text-muted small">
                    Showing <?= count($notes) ?> note(s)
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <div style="font-size: 3rem;">📭</div>
                <h4 class="text-muted mt-2">No notes found</h4>
                <p class="text-muted">No class notes match the current filter selection.</p>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-upload"></i> Upload Notes Now
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Admin Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-cloud-upload-alt text-primary me-2"></i> Upload Notes (Admin)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="api.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="admin_upload">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Note Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="255" placeholder="e.g. Complete Solved Numericals & Notes">
                    </div>

                    <div class="row mb-3">
                        <!-- Class Select (from DB class table) -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Class <span class="text-danger">*</span></label>
                            <select name="class" id="modalClass" class="form-select" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($dbClasses as $cls): ?>
                                    <option value="<?= htmlspecialchars($cls['class_id']) ?>">
                                        <?= htmlspecialchars($cls['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Subject (Book) Select (from DB book table) -->
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Subject (Book) <span class="text-danger">*</span></label>
                            <select name="subject" id="modalSubject" class="form-select" required disabled>
                                <option value="">-- Select Class First --</option>
                            </select>
                            <div id="modalCustomSubjectWrap" class="mt-2" style="display:none;">
                                <input type="text" name="custom_subject" id="modalCustomSubject" class="form-control form-control-sm" placeholder="Type custom subject name...">
                            </div>
                        </div>
                    </div>

                    <!-- Chapter Select (from DB chapter table) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Chapter (Optional)</label>
                        <select name="chapter" id="modalChapter" class="form-select" disabled>
                            <option value="">-- Select Subject First --</option>
                        </select>
                        <div id="modalCustomChapterWrap" class="mt-2" style="display:none;">
                            <input type="text" name="custom_chapter" id="modalCustomChapter" class="form-control form-control-sm" placeholder="Type custom chapter name or notes topic...">
                        </div>
                        <small class="text-muted">Select an individual chapter or keep as "All Chapters / General Notes".</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Key topics covered, highlights, author notes..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">File <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" required
                               accept=".pdf,.ppt,.pptx,.doc,.docx,.png,.jpg,.jpeg,.gif,.webp">
                        <small class="text-muted">Supported formats: PDF, Word (DOC/DOCX), PowerPoint (PPT/PPTX), Images (PNG/JPG/WEBP) — Max 50MB</small>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-info" onclick="testDriveConnection()">
                        <i class="fab fa-google-drive"></i> Test Drive Connection
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload &amp; Auto-Approve</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Note Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit text-info me-2"></i> Edit Note Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editNoteForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_note">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="note_id" id="editNoteId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Note Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="editTitle" class="form-control" required maxlength="255">
                    </div>

                    <div class="row mb-3">
                        <!-- Class Select -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Class <span class="text-danger">*</span></label>
                            <select name="class" id="editClass" class="form-select" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($dbClasses as $cls): ?>
                                    <option value="<?= htmlspecialchars($cls['class_id']) ?>">
                                        <?= htmlspecialchars($cls['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Subject (Book) Select -->
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Subject (Book) <span class="text-danger">*</span></label>
                            <select name="subject" id="editSubject" class="form-select" required>
                                <option value="">-- Select Class First --</option>
                            </select>
                            <div id="editCustomSubjectWrap" class="mt-2" style="display:none;">
                                <input type="text" name="custom_subject" id="editCustomSubject" class="form-control form-control-sm" placeholder="Type custom subject name...">
                            </div>
                        </div>
                    </div>

                    <!-- Chapter Select -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Chapter</label>
                        <select name="chapter" id="editChapter" class="form-select">
                            <option value="">-- Select Subject First --</option>
                        </select>
                        <div id="editCustomChapterWrap" class="mt-2" style="display:none;">
                            <input type="text" name="custom_chapter" id="editCustomChapter" class="form-control form-control-sm" placeholder="Type custom chapter name...">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" id="editDescription" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Reason Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-bold">Reason (optional)</label>
                <textarea id="rejectReason" class="form-control" rows="3" placeholder="Why is this note being rejected?"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRejectBtn">Reject</button>
            </div>
        </div>
    </div>
</div>

<!-- Google Drive Diagnostics Modal -->
<div class="modal fade" id="driveDiagModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fab fa-google-drive text-info me-2"></i> Google Drive Storage &amp; Connection Diagnostics</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="driveDiagModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Checking Google Drive API connection and folder permissions...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="testDriveConnection()"><i class="fas fa-redo"></i> Re-test Connection</button>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// DB Books lookup grouped by class_id
const dbBooksByClass = <?= json_encode($dbBooksByClass) ?>;
const allUniqueSubjects = <?= json_encode($allUniqueSubjects) ?>;
const csrfToken = '<?= $csrfToken ?>';
let rejectNoteId = null;

// Populate subjects dropdown based on chosen class
function populateSubjects(classSelectElem, subjectSelectElem, selectedSubject = '', includeAllOption = false) {
    const classId = classSelectElem.value;
    subjectSelectElem.innerHTML = '';
    
    if (includeAllOption) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'All Subjects';
        subjectSelectElem.appendChild(opt);
    } else {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '-- Select Subject (Book) --';
        subjectSelectElem.appendChild(opt);
    }
    
    let books = [];
    if (classId && dbBooksByClass[classId]) {
        books = dbBooksByClass[classId].map(b => b.book_name);
    } else if (!classId && includeAllOption) {
        books = allUniqueSubjects;
    }
    
    books.forEach(bookName => {
        const opt = document.createElement('option');
        opt.value = bookName;
        opt.textContent = bookName;
        if (bookName === selectedSubject) {
            opt.selected = true;
        }
        subjectSelectElem.appendChild(opt);
    });
    
    // Add "Other" option for modals
    if (!includeAllOption) {
        const otherOpt = document.createElement('option');
        otherOpt.value = 'Other';
        otherOpt.textContent = '➕ Other / Custom Subject...';
        if (selectedSubject && !books.includes(selectedSubject)) {
            otherOpt.selected = true;
        }
        subjectSelectElem.appendChild(otherOpt);
    }
    
    subjectSelectElem.disabled = false;
}

// Fetch and populate chapters from DB chapter table via AJAX
async function populateChapters(classSelectElem, subjectSelectElem, chapterSelectElem, selectedChapter = '', includeAllOption = false) {
    const classId = classSelectElem.value;
    const subject = subjectSelectElem.value;
    
    chapterSelectElem.innerHTML = '';
    
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = includeAllOption ? 'All Chapters' : '-- All Chapters / Complete Book / General Notes --';
    chapterSelectElem.appendChild(defaultOpt);
    
    if (!classId || !subject || subject === 'Other') {
        chapterSelectElem.disabled = (subject !== 'Other' && includeAllOption);
        return;
    }
    
    try {
        chapterSelectElem.disabled = true;
        const resp = await fetch(`api.php?action=get_chapters&class_id=${encodeURIComponent(classId)}&book_name=${encodeURIComponent(subject)}`);
        const chapters = await resp.json();
        
        let foundSelected = false;
        chapters.forEach(ch => {
            const opt = document.createElement('option');
            opt.value = ch.chapter_name;
            opt.textContent = ch.display_name;
            if (ch.chapter_name === selectedChapter || ch.display_name === selectedChapter) {
                opt.selected = true;
                foundSelected = true;
            }
            chapterSelectElem.appendChild(opt);
        });
        
        // Add custom chapter option for modals
        if (!includeAllOption) {
            const customOpt = document.createElement('option');
            customOpt.value = '__custom__';
            customOpt.textContent = '✏️ Custom / Other Chapter...';
            if (selectedChapter && !foundSelected) {
                customOpt.selected = true;
            }
            chapterSelectElem.appendChild(customOpt);
        }
    } catch (err) {
        console.error('Failed to load chapters:', err);
    } finally {
        chapterSelectElem.disabled = false;
    }
}

// Setup dependent dropdown handlers for a form group (Class -> Subject -> Chapter)
function setupDependentDropdowns(prefix, isFilter = false) {
    const classEl = document.getElementById(prefix + 'Class');
    const subjectEl = document.getElementById(prefix + 'Subject');
    const chapterEl = document.getElementById(prefix + 'Chapter');
    const customSubjWrap = document.getElementById(prefix + 'CustomSubjectWrap');
    const customChapWrap = document.getElementById(prefix + 'CustomChapterWrap');
    
    if (!classEl || !subjectEl) return;
    
    classEl.addEventListener('change', function() {
        populateSubjects(classEl, subjectEl, '', isFilter);
        if (chapterEl) {
            chapterEl.innerHTML = `<option value="">${isFilter ? 'All Chapters' : '-- Select Subject First --'}</option>`;
            chapterEl.disabled = isFilter;
        }
        if (customSubjWrap) customSubjWrap.style.display = 'none';
        if (customChapWrap) customChapWrap.style.display = 'none';
    });
    
    subjectEl.addEventListener('change', function() {
        if (customSubjWrap) {
            customSubjWrap.style.display = (this.value === 'Other') ? 'block' : 'none';
        }
        if (chapterEl) {
            populateChapters(classEl, subjectEl, chapterEl, '', isFilter);
        }
    });
    
    if (chapterEl && customChapWrap) {
        chapterEl.addEventListener('change', function() {
            customChapWrap.style.display = (this.value === '__custom__') ? 'block' : 'none';
        });
    }
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    // Setup Filter Bar
    setupDependentDropdowns('filter', true);
    
    // Setup Upload Modal
    setupDependentDropdowns('modal', false);
    
    // Setup Edit Modal
    setupDependentDropdowns('edit', false);
});

// Select All Checkbox
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.note-checkbox').forEach(cb => cb.checked = this.checked);
});

// Admin Note Actions (approve, reject, delete)
function adminAction(noteId, action, extra = {}) {
    if (action === 'delete' && !confirm('Are you sure you want to permanently delete this note (from DB and Google Drive)?')) return;
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('note_id', noteId);
    formData.append('csrf_token', csrfToken);
    Object.entries(extra).forEach(([k, v]) => formData.append(k, v));
    
    fetch('api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Action failed');
            }
        })
        .catch(() => alert('Network error. Please try again.'));
}

// Reject Modal
function rejectNote(noteId) {
    rejectNoteId = noteId;
    document.getElementById('rejectReason').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

document.getElementById('confirmRejectBtn')?.addEventListener('click', function() {
    if (rejectNoteId) {
        adminAction(rejectNoteId, 'reject', { reason: document.getElementById('rejectReason').value });
    }
});

// Edit Note Modal Handler
async function editNote(noteId) {
    try {
        const resp = await fetch(`api.php?action=get_note&note_id=${noteId}`);
        const data = await resp.json();
        if (!data.success || !data.note) {
            alert('Failed to load note details');
            return;
        }
        
        const note = data.note;
        document.getElementById('editNoteId').value = note.id;
        document.getElementById('editTitle').value = note.title;
        document.getElementById('editDescription').value = note.description || '';
        
        const classEl = document.getElementById('editClass');
        const subjectEl = document.getElementById('editSubject');
        const chapterEl = document.getElementById('editChapter');
        
        classEl.value = note.class;
        populateSubjects(classEl, subjectEl, note.subject, false);
        
        if (subjectEl.value === 'Other') {
            document.getElementById('editCustomSubjectWrap').style.display = 'block';
            document.getElementById('editCustomSubject').value = note.subject;
        } else {
            document.getElementById('editCustomSubjectWrap').style.display = 'none';
        }
        
        await populateChapters(classEl, subjectEl, chapterEl, note.chapter || '', false);
        
        if (chapterEl.value === '__custom__') {
            document.getElementById('editCustomChapterWrap').style.display = 'block';
            document.getElementById('editCustomChapter').value = note.chapter;
        } else {
            document.getElementById('editCustomChapterWrap').style.display = 'none';
        }
        
        new bootstrap.Modal(document.getElementById('editModal')).show();
    } catch (err) {
        alert('Error loading note details.');
    }
}

// Edit Form Submit
document.getElementById('editNoteForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const resp = await fetch('api.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
            location.reload();
        } else {
            alert(res.error || 'Failed to update note.');
        }
    } catch (err) {
        alert('Network error while saving note.');
    }
});

// Bulk Action Handler
function bulkAction(action) {
    const selected = [...document.querySelectorAll('.note-checkbox:checked')].map(cb => cb.value);
    if (selected.length === 0) { alert('Please select at least one note.'); return; }
    if (action === 'delete' && !confirm(`Delete ${selected.length} note(s) permanently?`)) return;
    
    const formData = new FormData();
    formData.append('action', 'bulk_' + action);
    formData.append('note_ids', JSON.stringify(selected));
    formData.append('csrf_token', csrfToken);
    
    fetch('api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Bulk action failed');
            }
        })
        .catch(() => alert('Network error during bulk action.'));
}

// Test Google Drive Connection & Diagnostics Modal
let driveDiagModalInstance = null;
async function testDriveConnection() {
    const modalElem = document.getElementById('driveDiagModal');
    const modalBody = document.getElementById('driveDiagModalBody');
    if (!driveDiagModalInstance) {
        driveDiagModalInstance = new bootstrap.Modal(modalElem);
    }
    
    modalBody.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted fw-bold">Querying Google Drive API &amp; inspecting folder permissions...</p>
        </div>
    `;
    driveDiagModalInstance.show();
    
    try {
        const resp = await fetch('api.php?action=test_drive');
        const data = await resp.json();
        
        if (data.success || data.status === 'ok') {
            let subfoldersHtml = '';
            if (data.subfolders && data.subfolders.length > 0) {
                subfoldersHtml = `
                    <div class="mt-3 p-2 bg-light rounded border">
                        <strong class="text-dark small d-block mb-1">📁 Existing Subfolders in Drive:</strong>
                        <div>
                            ${data.subfolders.map(s => `<span class="badge bg-success bg-opacity-25 text-success border border-success me-1 mb-1 p-2">📁 ${s}</span>`).join('')}
                        </div>
                    </div>
                `;
            } else {
                subfoldersHtml = `<div class="alert alert-info py-2 px-3 small mt-3 mb-0">✨ Folder is ready. Class &amp; Subject subfolders will be auto-created on first upload.</div>`;
            }

            modalBody.innerHTML = `
                <div class="alert alert-success d-flex align-items-center mb-3">
                    <i class="fas fa-check-circle fa-2x me-3 text-success"></i>
                    <div>
                        <h6 class="mb-0 fw-bold">Google Drive Connected &amp; Ready for Uploads!</h6>
                        <small>${data.auth_mode === 'oauth' ? 'OAuth credential can create folders and upload files.' : 'Service account has permissions to create folders and upload files.'}</small>
                    </div>
                </div>
                
                <table class="table table-bordered table-sm small mb-2">
                    <tbody>
                        <tr>
                            <td class="fw-bold bg-light" style="width:30%;">Target Root Folder</td>
                            <td>
                                <strong>${data.root_folder_name || 'AhmadLearningHub'}</strong>
                                ${data.root_folder_url ? `<a href="${data.root_folder_url}" target="_blank" class="btn btn-xs btn-outline-primary ms-2 py-0 px-2 small"><i class="fas fa-external-link-alt"></i> Open in Drive</a>` : ''}
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Folder ID</td>
                            <td><code>${data.root_folder_id}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Active Credential</td>
                            <td><code>${data.auth_mode === 'oauth' ? 'Google Drive OAuth account' : data.service_email}</code> <span class="badge bg-success ms-1">Verified</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Auto Organization</td>
                            <td><span class="badge bg-primary text-white">Active</span> <code>AhmadLearningHub / Class {X} / {Subject}</code></td>
                        </tr>
                    </tbody>
                </table>
                ${subfoldersHtml}
            `;
        } else {
            const email = data.service_email || 'drive-for-uploads@ahmad-learning-hub.iam.gserviceaccount.com';
            const errors = data.errors || [data.error || 'Connection check failed'];

            modalBody.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center mb-3">
                    <i class="fas fa-exclamation-triangle fa-2x me-3 text-danger"></i>
                    <div>
                        <h6 class="mb-0 fw-bold">Google Drive Storage Not Connected</h6>
                        <small>The connected Google Drive credential cannot access the target folder.</small>
                    </div>
                </div>

                <div class="card border-danger mb-3">
                    <div class="card-header bg-danger text-white py-1 small fw-bold">Reported Error Details</div>
                    <div class="card-body py-2 px-3 small text-danger bg-light">
                        ${errors.map(e => `<div>• <strong>${e}</strong></div>`).join('')}
                    </div>
                </div>

                <div class="card border-primary">
                    <div class="card-header bg-primary text-white py-1 small fw-bold"><i class="fas fa-wrench me-1"></i> Quick Google Drive OAuth Fix</div>
                    <div class="card-body py-2 px-3 small">
                        <ol class="mb-0 ps-3">
                            <li class="mb-2">Open <a href="../../google_drive_callback.php" class="fw-bold text-primary">Google Drive OAuth setup</a> as an admin.</li>
                            <li class="mb-2">Connect the Google account that should own uploaded class notes.</li>
                            <li class="mb-2">Locate or create your Drive folder named <strong>AhmadLearningHub</strong>.</li>
                            <li class="mb-2">Copy the folder URL from your browser bar (e.g. <code>https://drive.google.com/drive/folders/1ABC...</code>) and paste into <code>config/.env</code> as:<br>
                                <code>GOOGLE_DRIVE_FOLDER_ID=your_copied_id_or_url</code>
                            </li>
                            <li>Click the <strong>Re-test Connection</strong> button below to confirm!</li>
                        </ol>
                    </div>
                </div>
            `;
        }
    } catch (err) {
        modalBody.innerHTML = `
            <div class="alert alert-danger">
                <h6><i class="fas fa-times-circle"></i> Network Request Failed</h6>
                <p class="mb-0 small">${err.message}</p>
            </div>
        `;
    }
}
</script>
