<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

// Auto-fix schema for missing columns (Self-healing)
$tablesToFix = ['AIMCQsVerification', 'MCQsVerification'];
foreach ($tablesToFix as $fixTable) {
    // Check if table exists first
    $tblCheck = $conn->query("SHOW TABLES LIKE '$fixTable'");
    if ($tblCheck && $tblCheck->num_rows > 0) {
        $colCheck = $conn->query("SHOW COLUMNS FROM $fixTable LIKE 'original_correct_option'");
        if ($colCheck && $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE $fixTable ADD COLUMN original_correct_option TEXT AFTER suggested_correct_option");
        }
    }
}

// Handle AJAX Request for Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_ai_mcqs') {
    require_once __DIR__ . '/../quiz/mcq_generator.php';
    
    $sourceTable = isset($_POST['source_table']) ? $_POST['source_table'] : 'AIGeneratedMCQs';
    if (!in_array($sourceTable, ['AIGeneratedMCQs', 'mcqs'])) {
        $sourceTable = 'AIGeneratedMCQs';
    }

    // Ensure verification table exists (handled inside checkMCQsWithAI now, but keeping for safety if needed, 
    // actually checkMCQsWithAI handles it, but let's leave it to the function)
    
    set_time_limit(300);
    $limit = intval($_POST['limit'] ?? 10);
    $startId = isset($_POST['start_id']) ? intval($_POST['start_id']) : null;
    $endId = isset($_POST['end_id']) ? intval($_POST['end_id']) : null;
    $ids = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : null;

    $result = checkMCQsWithAI($limit, $startId, $endId, $sourceTable, $ids);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Handle AJAX Request for Fetching Session Results
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_by_ids') {
    $ids = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : [];
    
    if (empty($ids)) {
        echo '<div class="alert alert-warning">No MCQs were processed in this session.</div>';
        exit;
    }

    $sourceTable = isset($_POST['source_table']) ? $_POST['source_table'] : 'AIGeneratedMCQs';
    if (!in_array($sourceTable, ['AIGeneratedMCQs', 'mcqs'])) {
        $sourceTable = 'AIGeneratedMCQs';
    }

    if ($sourceTable === 'mcqs') {
        $mainTable = 'mcqs';
        $verifyTable = 'MCQsVerification';
        $pk = 'mcq_id';
        $fk = 'mcq_id';
    } else {
        $mainTable = 'AIGeneratedMCQs';
        $verifyTable = 'AIMCQsVerification';
        $pk = 'id';
        $fk = 'mcq_id';
    }

    // Sanitize IDs
    $ids = array_map('intval', $ids);
    $idsList = implode(',', $ids);
    
    $sql = "SELECT m.$pk as id, m.topic, m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option as current_correct, 
            v.verification_status, v.suggested_correct_option, v.original_correct_option, v.ai_notes, v.last_checked_at
            FROM $mainTable m
            JOIN $verifyTable v ON m.$pk = v.$fk
            WHERE m.$pk IN ($idsList)
            ORDER BY v.last_checked_at DESC";
            
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo '<div class="table-responsive mt-4"><table class="table table-hover table-bordered align-middle"><thead class="table-light"><tr>
                <th style="width: 5%">ID</th>
                <th style="width: 35%">Question & Options</th>
                <th style="width: 10%">Status</th>
                <th style="width: 25%">AI Notes / Correction</th>
              </tr></thead><tbody>';
              
        while($row = $result->fetch_assoc()) {
            $badgeClass = 'bg-secondary';
            if ($row['verification_status'] === 'verified') $badgeClass = 'badge-verified';
            elseif ($row['verification_status'] === 'corrected') $badgeClass = 'badge-corrected';
            elseif ($row['verification_status'] === 'flagged') $badgeClass = 'badge-flagged';
            
            echo '<tr>';
            echo '<td>#' . $row['id'] . '</td>';
            echo '<td><div class="fw-bold mb-2">' . htmlspecialchars($row['question']) . '</div>
                  <div class="small text-secondary ps-2 border-start border-3 border-light">
                    <div class="mb-1"><span class="fw-bold me-1">A)</span> ' . htmlspecialchars($row['option_a']) . '</div>
                    <div class="mb-1"><span class="fw-bold me-1">B)</span> ' . htmlspecialchars($row['option_b']) . '</div>
                    <div class="mb-1"><span class="fw-bold me-1">C)</span> ' . htmlspecialchars($row['option_c']) . '</div>
                    <div><span class="fw-bold me-1">D)</span> ' . htmlspecialchars($row['option_d']) . '</div>
                  </div></td>';
            echo '<td><span class="badge ' . $badgeClass . '">' . ucfirst($row['verification_status']) . '</span></td>';
            echo '<td>';
            if ($row['verification_status'] === 'corrected') {
                echo '<div class="mb-1"><strong>Corrected to:</strong> <span class="text-success fw-bold">' . htmlspecialchars($row['current_correct']) . '</span></div>';
                if (!empty($row['original_correct_option']) && $row['original_correct_option'] !== $row['current_correct']) {
                    echo '<div class="text-muted small">Previous: ' . htmlspecialchars($row['original_correct_option']) . '</div>';
                }
            }
            if (!empty($row['ai_notes'])) {
                echo '<div class="ai-note mt-1">' . htmlspecialchars($row['ai_notes']) . '</div>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="alert alert-info">No details found for the processed IDs.</div>';
    }
    exit;
}

// Generate CSRF Token for the page
$csrfToken = generateCSRFToken();

// --- REPORT LOGIC ---

$sourceTable = isset($_GET['source_table']) ? $_GET['source_table'] : 'AIGeneratedMCQs';
if (!in_array($sourceTable, ['AIGeneratedMCQs', 'mcqs'])) {
    $sourceTable = 'AIGeneratedMCQs';
}

if ($sourceTable === 'mcqs') {
    $mainTable = 'mcqs';
    $verifyTable = 'MCQsVerification';
    $pk = 'mcq_id';
    $fk = 'mcq_id';
} else {
    $mainTable = 'AIGeneratedMCQs';
    $verifyTable = 'AIMCQsVerification';
    $pk = 'id';
    $fk = 'mcq_id';
}

// Ensure table exists for report view as well
$createTableSql = "";
if ($sourceTable === 'mcqs') {
    $createTableSql = "CREATE TABLE IF NOT EXISTS MCQsVerification (
        mcq_id INT PRIMARY KEY,
        verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
        last_checked_at DATETIME,
        suggested_correct_option TEXT,
        ai_notes TEXT,
        FOREIGN KEY (mcq_id) REFERENCES mcqs(mcq_id) ON DELETE CASCADE
    )";
} else {
    $createTableSql = "CREATE TABLE IF NOT EXISTS AIMCQsVerification (
        mcq_id INT PRIMARY KEY,
        verification_status ENUM('pending', 'verified', 'corrected', 'flagged') DEFAULT 'pending',
        last_checked_at DATETIME,
        suggested_correct_option TEXT,
        ai_notes TEXT,
        FOREIGN KEY (mcq_id) REFERENCES AIGeneratedMCQs(id) ON DELETE CASCADE
    )";
}
$conn->query($createTableSql);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'corrected';
$allowedFilters = ['verified', 'corrected', 'flagged', 'all'];
if (!in_array($filter, $allowedFilters)) $filter = 'corrected';

$whereClause = "WHERE v.verification_status = '$filter'";
if ($filter === 'all') $whereClause = "";

// Count for Pagination
$countSql = "SELECT COUNT(*) as cnt FROM $verifyTable v $whereClause";
$countRes = $conn->query($countSql);
$totalRows = $countRes ? $countRes->fetch_assoc()['cnt'] : 0;
$totalPages = ceil($totalRows / $perPage);

// Fetch Report Data
$reportSql = "SELECT m.$pk as id, m.topic, m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option as current_correct, 
        v.verification_status, v.suggested_correct_option, v.original_correct_option, v.ai_notes, v.last_checked_at
        FROM $mainTable m
        JOIN $verifyTable v ON m.$pk = v.$fk
        $whereClause
        ORDER BY v.last_checked_at DESC
        LIMIT $offset, $perPage";

$reportResult = $conn->query($reportSql);

// --- GLOBAL STATS LOGIC ---
$globalStats = [
    'total' => 0,
    'verified' => 0,
    'corrected' => 0,
    'flagged' => 0,
    'pending' => 0
];

// Total MCQs
$res = $conn->query("SELECT COUNT(*) as cnt FROM $mainTable");
if ($res && $row = $res->fetch_assoc()) {
    $globalStats['total'] = intval($row['cnt']);
}

// Verification Stats
$res = $conn->query("SELECT verification_status, COUNT(*) as cnt FROM $verifyTable GROUP BY verification_status");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $status = $row['verification_status'];
        if (isset($globalStats[$status])) {
             $globalStats[$status] = intval($row['cnt']);
        }
    }
}

$checkedCount = $globalStats['verified'] + $globalStats['corrected'] + $globalStats['flagged'];
$globalStats['pending'] = max(0, $globalStats['total'] - $checkedCount);

// Determine active tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'verify';
if (isset($_GET['filter']) || isset($_GET['page'])) {
    $activeTab = 'report';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI MCQ Verification Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .main-container {
            max-width: 1200px;
            margin: 30px auto;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        /* Verification Styles */
        .verification-wrapper {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }
        .ai-loader-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .status-text {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 1.5rem;
            min-height: 1.5em;
        }
        .progress-wrapper {
            background: #e9ecef;
            border-radius: 10px;
            height: 20px;
            width: 100%;
            overflow: hidden;
            margin-bottom: 2rem;
            position: relative;
        }
        .progress-bar-custom {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #0d6efd, #0dcaf0);
            transition: width 0.3s ease;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 2rem;
            border-top: 1px solid #eee;
            padding-top: 2rem;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            display: block;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .log-container {
            margin-top: 2rem;
            text-align: left;
            background: #1e1e1e;
            color: #00ff00;
            padding: 1rem;
            border-radius: 8px;
            height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.9rem;
            display: none;
        }
        .log-entry { margin-bottom: 4px; }
        .log-info { color: #0dcaf0; }
        .log-success { color: #198754; }
        .log-warning { color: #ffc107; }
        .log-error { color: #dc3545; }

        /* Report Styles */
        .badge-corrected { background-color: #0d6efd; }
        .badge-verified { background-color: #198754; }
        .badge-flagged { background-color: #dc3545; }
        .ai-note {
            font-size: 0.9rem;
            color: #555;
            background: #f1f3f5;
            padding: 8px;
            border-radius: 6px;
            border-left: 3px solid #6c757d;
        }
    </style>
</head>
<body>

<div class="container main-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-robot text-primary me-2"></i>AI Verification Center</h2>
        <a href="manage_ai_mcqs.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Manage
        </a>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'verify' ? 'active' : '' ?>" id="verify-tab" data-bs-toggle="tab" data-bs-target="#verify" type="button" role="tab" aria-controls="verify" aria-selected="<?= $activeTab === 'verify' ? 'true' : 'false' ?>">
                <i class="fas fa-play-circle me-2"></i>Run Verification
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'report' ? 'active' : '' ?>" id="report-tab" data-bs-toggle="tab" data-bs-target="#report" type="button" role="tab" aria-controls="report" aria-selected="<?= $activeTab === 'report' ? 'true' : 'false' ?>">
                <i class="fas fa-list-alt me-2"></i>View Results
            </button>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        
        <!-- Verification Tab -->
        <div class="tab-pane fade <?= $activeTab === 'verify' ? 'show active' : '' ?>" id="verify" role="tabpanel" aria-labelledby="verify-tab">
            <div class="verification-wrapper">
                <!-- Global Stats Dashboard -->
                <div class="row mb-5 text-center">
                    <div class="col-md-3">
                        <div class="stat-card border-start border-4 border-secondary">
                            <span class="stat-value text-secondary"><?= $globalStats['pending'] ?></span>
                            <span class="stat-label">Remaining (Pending)</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card border-start border-4 border-success">
                            <span class="stat-value text-success"><?= $globalStats['verified'] ?></span>
                            <span class="stat-label">Verified</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card border-start border-4 border-primary">
                            <span class="stat-value text-primary"><?= $globalStats['corrected'] ?></span>
                            <span class="stat-label">Corrected</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card border-start border-4 border-danger">
                            <span class="stat-value text-danger"><?= $globalStats['flagged'] ?></span>
                            <span class="stat-label">Flagged</span>
                        </div>
                    </div>
                </div>

                <div id="setup-phase">
                    <p class="text-muted mb-4">Select verification mode to begin the AI checking process.</p>
                    
                    <div class="mb-4 text-center">
                        <label class="form-label text-muted me-2">Source Table:</label>
                        <div class="d-inline-block" style="width: 250px;">
                            <select class="form-select" id="source-table">
                                <option value="AIGeneratedMCQs" <?= $sourceTable === 'AIGeneratedMCQs' ? 'selected' : '' ?>>AI Generated MCQs</option>
                                <option value="mcqs" <?= $sourceTable === 'mcqs' ? 'selected' : '' ?>>Manual MCQs (mcqs)</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center gap-3">
                        <button onclick="startVerification('pending')" class="btn btn-primary btn-lg px-4">
                            <i class="fas fa-play me-2"></i>Check Next 50 Pending
                        </button>
                        <button onclick="toggleRangeInputs()" class="btn btn-outline-secondary btn-lg px-4">
                            <i class="fas fa-sync me-2"></i>Recheck Range
                        </button>
                        <button onclick="switchToReportTab()" class="btn btn-info btn-lg px-4 text-white">
                            <i class="fas fa-list-alt me-2"></i>View Results
                        </button>
                    </div>
                    
                    <div id="range-inputs" class="mt-4" style="display:none; max-width: 300px; margin: 20px auto;">
                        <input type="number" id="start-id" class="form-control mb-2" placeholder="Start ID">
                        <input type="number" id="end-id" class="form-control mb-2" placeholder="End ID">
                        <button onclick="startVerification('range')" class="btn btn-success w-100">Start Range Check</button>
                    </div>
                </div>

                <div id="process-phase" style="display:none;">
                    <div class="ai-loader-spinner" id="spinner"></div>
                    <div class="status-text" id="status-text">Initializing AI agents...</div>
                    
                    <div class="progress-wrapper">
                        <div class="progress-bar-custom" id="progress-bar"></div>
                    </div>

                    <h5 class="text-muted mb-3">Current Session Results</h5>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <span class="stat-value" id="stat-checked">0</span>
                            <span class="stat-label">Checked</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value text-success" id="stat-verified">0</span>
                            <span class="stat-label">Verified</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value text-primary" id="stat-corrected">0</span>
                            <span class="stat-label">Corrected</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value text-danger" id="stat-flagged">0</span>
                            <span class="stat-label">Flagged</span>
                        </div>
                    </div>

                    <div class="log-container" id="log-console"></div>
                    
                    <div id="session-results-container" style="display:none; margin-top: 2rem;">
                        <h4 class="mb-3 border-bottom pb-2">Session Results</h4>
                        <div id="session-results-content"></div>
                    </div>

                    <button onclick="resetProcess()" class="btn btn-secondary mt-4" id="back-btn" style="display:none;">
                        <i class="fas fa-redo me-2"></i>Start New Check
                    </button>
                    <button onclick="switchToReportTab()" class="btn btn-info mt-4 text-white ms-2" id="view-res-btn" style="display:none;">
                        <i class="fas fa-list-alt me-2"></i>View Results
                    </button>
                </div>
            </div>
        </div>

        <!-- Report Tab -->
        <div class="tab-pane fade <?= $activeTab === 'report' ? 'show active' : '' ?>" id="report" role="tabpanel" aria-labelledby="report-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Detailed Report</h4>
                <div style="width: 250px;">
                    <select class="form-select form-select-sm" onchange="window.location.href='verify_ai_mcqs.php?tab=report&source_table='+this.value">
                        <option value="AIGeneratedMCQs" <?= $sourceTable === 'AIGeneratedMCQs' ? 'selected' : '' ?>>Source: AI Generated MCQs</option>
                        <option value="mcqs" <?= $sourceTable === 'mcqs' ? 'selected' : '' ?>>Source: Manual MCQs (mcqs)</option>
                    </select>
                </div>
            </div>

            <!-- Filters -->
            <ul class="nav nav-pills mb-4 justify-content-center">
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'corrected' ? 'active' : '' ?>" href="?source_table=<?= $sourceTable ?>&tab=report&filter=corrected">Corrected</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'flagged' ? 'active' : '' ?>" href="?source_table=<?= $sourceTable ?>&tab=report&filter=flagged">Flagged</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'verified' ? 'active' : '' ?>" href="?source_table=<?= $sourceTable ?>&tab=report&filter=verified">Verified</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="?source_table=<?= $sourceTable ?>&tab=report&filter=all">All</a>
                </li>
            </ul>

            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%">ID</th>
                            <th style="width: 15%">Topic</th>
                            <th style="width: 35%">Question & Options</th>
                            <th style="width: 10%">Status</th>
                            <th style="width: 25%">AI Notes / Correction</th>
                            <th style="width: 15%">Checked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reportResult && $reportResult->num_rows > 0): ?>
                            <?php while($row = $reportResult->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= $row['id'] ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['topic']) ?></span></td>
                                    <td>
                                        <div class="fw-bold mb-2"><?= htmlspecialchars($row['question']) ?></div>
                                        <div class="small text-secondary ps-2 border-start border-3 border-light">
                                            <div class="mb-1"><span class="fw-bold me-1">A)</span> <?= htmlspecialchars($row['option_a']) ?></div>
                                            <div class="mb-1"><span class="fw-bold me-1">B)</span> <?= htmlspecialchars($row['option_b']) ?></div>
                                            <div class="mb-1"><span class="fw-bold me-1">C)</span> <?= htmlspecialchars($row['option_c']) ?></div>
                                            <div><span class="fw-bold me-1">D)</span> <?= htmlspecialchars($row['option_d']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = 'bg-secondary';
                                        if ($row['verification_status'] === 'verified') $badgeClass = 'badge-verified';
                                        elseif ($row['verification_status'] === 'corrected') $badgeClass = 'badge-corrected';
                                        elseif ($row['verification_status'] === 'flagged') $badgeClass = 'badge-flagged';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= ucfirst($row['verification_status']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($row['verification_status'] === 'corrected'): ?>
                                            <div class="mb-1"><strong>Corrected to:</strong> <span class="text-success fw-bold"><?= htmlspecialchars($row['current_correct']) ?></span></div>
                                            <?php if (!empty($row['original_correct_option']) && $row['original_correct_option'] !== $row['current_correct']): ?>
                                                <div class="text-muted small">Previous: <?= htmlspecialchars($row['original_correct_option']) ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($row['ai_notes'])): ?>
                                            <div class="ai-note mt-1"><?= htmlspecialchars($row['ai_notes']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('M d, Y h:i A', strtotime($row['last_checked_at'])) ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No records found for this filter.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?source_table=<?= $sourceTable ?>&tab=report&filter=<?= $filter ?>&page=<?= $page - 1 ?>">Previous</a>
                        </li>
                        <?php for($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $page || $i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?source_table=<?= $sourceTable ?>&tab=report&filter=<?= $filter ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?source_table=<?= $sourceTable ?>&tab=report&filter=<?= $filter ?>&page=<?= $page + 1 ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const csrfToken = '<?= $csrfToken ?>';
    let totalToCheck = 50;
    let batchSize = 10;
    let currentProcessed = 0;
    let stats = { checked: 0, verified: 0, corrected: 0, flagged: 0 };
    let mode = 'pending';
    let rangeStart = 0;
    let rangeEnd = 0;
    let allProcessedIds = [];
    let sourceTable = 'AIGeneratedMCQs';

    // Check URL params for auto-start
    window.addEventListener('load', () => {
        const urlParams = new URLSearchParams(window.location.search);
        // Set source table from URL if present
        const tableParam = urlParams.get('source_table');
        if (tableParam) {
            const select = document.getElementById('source-table');
            if (select) {
                select.value = tableParam;
                sourceTable = tableParam;
            }
        }

        // Add change listener to selector
        const sourceTableSelect = document.getElementById('source-table');
        if (sourceTableSelect) {
            sourceTableSelect.addEventListener('change', function() {
                const selected = this.value;
                const url = new URL(window.location.href);
                url.searchParams.set('source_table', selected);
                // Reset page if on report tab
                if (url.searchParams.get('tab') === 'report') {
                    url.searchParams.set('page', 1);
                }
                window.location.href = url.toString();
            });
        }

        if (urlParams.get('mode') === 'range') {
            const start = urlParams.get('start');
            const end = urlParams.get('end');
            if (start && end) {
                document.getElementById('start-id').value = start;
                document.getElementById('end-id').value = end;
                
                // Switch to verify tab if not already
                const verifyTabBtn = document.getElementById('verify-tab');
                const verifyTab = new bootstrap.Tab(verifyTabBtn);
                verifyTab.show();

                toggleRangeInputs(); // Show inputs
            }
        }
    });

    function switchToReportTab() {
        window.location.href = `verify_ai_mcqs.php?source_table=${sourceTable}&tab=report`;
    }

    function toggleRangeInputs() {
        const el = document.getElementById('range-inputs');
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }

    function log(msg, type = 'info') {
        const consoleEl = document.getElementById('log-console');
        const entry = document.createElement('div');
        entry.className = `log-entry log-${type}`;
        const time = new Date().toLocaleTimeString();
        entry.textContent = `[${time}] ${msg}`;
        consoleEl.appendChild(entry);
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }

    function startVerification(selectedMode) {
        mode = selectedMode;
        sourceTable = document.getElementById('source-table').value;

        if (mode === 'range') {
            rangeStart = parseInt(document.getElementById('start-id').value);
            rangeEnd = parseInt(document.getElementById('end-id').value);
            if (!rangeStart || !rangeEnd || rangeEnd < rangeStart) {
                alert('Please enter a valid ID range.');
                return;
            }
            totalToCheck = rangeEnd - rangeStart + 1;
            if (totalToCheck > 100) {
                if(!confirm(`You are about to check ${totalToCheck} MCQs. This might take a while. Continue?`)) return;
            }
        } else {
            totalToCheck = 50;
        }

        document.getElementById('setup-phase').style.display = 'none';
        document.getElementById('process-phase').style.display = 'block';
        document.getElementById('spinner').style.display = 'block';
        document.getElementById('log-console').style.display = 'block';
        
        // Reset stats
        stats = { checked: 0, verified: 0, corrected: 0, flagged: 0 };
        currentProcessed = 0;
        allProcessedIds = [];
        updateUI();
        
        log(`Starting verification in ${mode} mode...`);
        runBatch();
    }

    function updateUI() {
        const pct = totalToCheck > 0 ? Math.min(100, Math.round((currentProcessed / totalToCheck) * 100)) : 0;
        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('stat-checked').textContent = stats.checked;
        document.getElementById('stat-verified').textContent = stats.verified;
        document.getElementById('stat-corrected').textContent = stats.corrected;
        document.getElementById('stat-flagged').textContent = stats.flagged;
    }

    let idsToCheck = [];

    function runBatch() {
        if (currentProcessed >= totalToCheck) {
            finishProcess();
            return;
        }

        const currentBatchSize = Math.min(batchSize, totalToCheck - currentProcessed);
        
        const formData = new FormData();
        formData.append('action', 'check_ai_mcqs');
        formData.append('source_table', sourceTable);
        formData.append('limit', currentBatchSize);
        formData.append('csrf_token', csrfToken);
        
        if (mode === 'range') {
            const batchStart = rangeStart + currentProcessed;
            const batchEnd = Math.min(rangeEnd, batchStart + currentBatchSize - 1);
            formData.append('start_id', batchStart);
            formData.append('end_id', batchEnd);
            document.getElementById('status-text').textContent = `Processing batch... (IDs: ${batchStart} - ${batchEnd})`;
        } else if (mode === 'ids') {
            const batchIds = idsToCheck.slice(currentProcessed, currentProcessed + currentBatchSize);
            formData.append('ids', JSON.stringify(batchIds));
            document.getElementById('status-text').textContent = `Processing batch... (${currentProcessed}/${totalToCheck} Items)`;
        } else {
             document.getElementById('status-text').textContent = `Processing batch... (${currentProcessed}/${totalToCheck})`;
        }

        fetch('verify_ai_mcqs.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const s = data.stats;
                stats.checked += s.checked;
                stats.verified += s.verified;
                stats.corrected += s.corrected;
                stats.flagged += s.flagged;
                
                let processedIdsMsg = '';
                if (s.processed_ids && s.processed_ids.length > 0) {
                    allProcessedIds = allProcessedIds.concat(s.processed_ids);
                    const minId = Math.min(...s.processed_ids);
                    const maxId = Math.max(...s.processed_ids);
                    processedIdsMsg = ` [IDs: ${minId}-${maxId}]`;
                    document.getElementById('status-text').textContent = `Checked IDs: ${minId} - ${maxId}`;
                }

                log(`Batch completed: ${s.checked} checked (${s.verified} OK, ${s.corrected} Fixed, ${s.flagged} Flagged)${processedIdsMsg}`, 'success');
                
                currentProcessed += currentBatchSize;
                updateUI();
                
                if (mode === 'pending' && s.checked === 0) {
                    log('No more pending MCQs found.', 'warning');
                    finishProcess();
                } else {
                    runBatch();
                }
            } else {
                log(`Error: ${data.message}`, 'error');
                finishProcess();
            }
        })
        .catch(err => {
            console.error(err);
            log('Network error occurred.', 'error');
            finishProcess();
        });
    }

    function finishProcess() {
        document.getElementById('spinner').style.display = 'none';
        document.getElementById('status-text').textContent = 'Verification process completed.';
        document.getElementById('progress-bar').style.width = '100%';
        document.getElementById('back-btn').style.display = 'inline-block';
        document.getElementById('view-res-btn').style.display = 'inline-block';

        // Add Recheck Button
        let recheckBtn = document.getElementById('recheck-btn');
        if (!recheckBtn) {
            recheckBtn = document.createElement('button');
            recheckBtn.id = 'recheck-btn';
            recheckBtn.className = 'btn btn-warning mt-4 ms-2';
            recheckBtn.innerHTML = '<i class="fas fa-sync me-2"></i>Recheck These';
            recheckBtn.onclick = recheckSession;
            const viewBtn = document.getElementById('view-res-btn');
            if (viewBtn && viewBtn.parentNode) {
                viewBtn.parentNode.insertBefore(recheckBtn, viewBtn.nextSibling);
            }
        }
        recheckBtn.style.display = 'inline-block';
        
        log('Process finished.', 'info');

        // Fetch and display session results
        if (allProcessedIds.length > 0) {
            fetchSessionResults(allProcessedIds);
        }
    }

    function recheckSession() {
        if (allProcessedIds.length === 0) return;
        if (!confirm(`Recheck ${allProcessedIds.length} MCQs?`)) return;

        idsToCheck = [...allProcessedIds];
        totalToCheck = idsToCheck.length;
        mode = 'ids';
        
        // Reset UI
        document.getElementById('setup-phase').style.display = 'none';
        document.getElementById('process-phase').style.display = 'block';
        document.getElementById('spinner').style.display = 'block';
        document.getElementById('back-btn').style.display = 'none';
        document.getElementById('view-res-btn').style.display = 'none';
        if(document.getElementById('recheck-btn')) document.getElementById('recheck-btn').style.display = 'none';
        document.getElementById('session-results-container').style.display = 'none';
        document.getElementById('session-results-content').innerHTML = '';
        document.getElementById('log-console').innerHTML = '';
        
        // Reset stats
        stats = { checked: 0, verified: 0, corrected: 0, flagged: 0 };
        currentProcessed = 0;
        allProcessedIds = [];
        updateUI();
        
        log(`Starting recheck of ${totalToCheck} MCQs...`);
        runBatch();
    }
    
    function fetchSessionResults(ids) {
        document.getElementById('session-results-container').style.display = 'block';
        document.getElementById('session-results-content').innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading results...</div></div>';

        const formData = new FormData();
        formData.append('action', 'fetch_by_ids');
        formData.append('ids', JSON.stringify(ids));
        formData.append('source_table', sourceTable);
        formData.append('csrf_token', csrfToken);

        fetch('verify_ai_mcqs.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(html => {
            document.getElementById('session-results-content').innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            document.getElementById('session-results-content').innerHTML = '<div class="alert alert-danger">Failed to load results.</div>';
        });
    }
    
    function resetProcess() {
        document.getElementById('process-phase').style.display = 'none';
        document.getElementById('setup-phase').style.display = 'block';
        document.getElementById('back-btn').style.display = 'none';
        document.getElementById('view-res-btn').style.display = 'none';
        document.getElementById('session-results-container').style.display = 'none';
        document.getElementById('session-results-content').innerHTML = '';
        document.getElementById('log-console').innerHTML = ''; // Clear logs
        document.getElementById('status-text').textContent = 'Initializing AI agents...';
        document.getElementById('progress-bar').style.width = '0%';
    }
</script>
</body>
</html>
