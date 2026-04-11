<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../quiz/mcq_generator.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

if (isset($conn) && function_exists('ensureMcqExplanationColumns')) {
    ensureMcqExplanationColumns($conn);
}

// Auto-fix schema for missing columns (Self-healing)
// Moved to install.php

// Handle individual MCQ actions (Approve, Delete, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve', 'delete_mcq', 'flag'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die(json_encode(['success' => false, 'message' => 'CSRF validation failed']));
    }

    $id = intval($_POST['id']);
    $sourceTable = $_POST['source_table'] ?? 'AIGeneratedMCQs';
    
    if ($sourceTable === 'mcqs') {
        $mainTable = 'mcqs';
        $pk = 'mcq_id';
    } else {
        $mainTable = 'AIGeneratedMCQs';
        $pk = 'id';
    }

    $vSrc = mcqVerificationSourceValue($sourceTable);

    if ($_POST['action'] === 'approve') {
        if ($sourceTable === 'mcqs') {
            $sql = "INSERT INTO MCQsVerification (mcq_id, verification_status, last_checked_at) 
                    VALUES (?, 'verified', NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    verification_status = 'verified', last_checked_at = NOW()";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
        } else {
            $sql = "INSERT INTO MCQVerification (source, mcq_id, verification_status, last_checked_at) 
                    VALUES (?, ?, 'verified', NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    verification_status = 'verified', last_checked_at = NOW()";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $vSrc, $id);
        }
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
    } elseif ($_POST['action'] === 'flag') {
        if ($sourceTable === 'mcqs') {
            $sql = "INSERT INTO MCQsVerification (mcq_id, verification_status, last_checked_at) 
                    VALUES (?, 'flagged', NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    verification_status = 'flagged', last_checked_at = NOW()";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
        } else {
            $sql = "INSERT INTO MCQVerification (source, mcq_id, verification_status, last_checked_at) 
                    VALUES (?, ?, 'flagged', NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    verification_status = 'flagged', last_checked_at = NOW()";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $vSrc, $id);
        }
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
    } elseif ($_POST['action'] === 'delete_mcq') {
        $conn->begin_transaction();
        try {
            if ($sourceTable === 'mcqs') {
                $stmt1 = $conn->prepare('DELETE FROM MCQsVerification WHERE mcq_id = ?');
                $stmt1->bind_param('i', $id);
            } else {
                $stmt1 = $conn->prepare('DELETE FROM MCQVerification WHERE source = ? AND mcq_id = ?');
                $stmt1->bind_param('si', $vSrc, $id);
            }
            $stmt1->execute();
            
            $stmt2 = $conn->prepare("DELETE FROM $mainTable WHERE $pk = ?");
            $stmt2->bind_param('i', $id);
            $stmt2->execute();
            
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    exit;
}

// Handle AJAX Request for Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_ai_mcqs') {
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
        $pk = 'mcq_id';
    } else {
        $mainTable = 'AIGeneratedMCQs';
        $pk = 'id';
    }

    $verifyJoinFetch = ($sourceTable === 'mcqs')
        ? "JOIN MCQsVerification v ON m.$pk = v.mcq_id"
        : "JOIN MCQVerification v ON v.source = 'AIGeneratedMCQs' AND v.mcq_id = m.$pk";

    // Sanitize IDs
    $ids = array_map('intval', $ids);
    $idsList = implode(',', $ids);
    
    $questionColumn = ($sourceTable === 'mcqs') ? 'question' : 'question_text';
    $topicColumn = ($sourceTable === 'mcqs') ? "CONCAT('Class ', COALESCE(m.class_id, 'Unknown'), ' - Book ', COALESCE(m.book_id, 'Unknown'), ' - Chapter ', COALESCE(m.chapter_id, 'Unknown'))" : 'm.topic';
    $explanationSelect = ($sourceTable === 'mcqs')
        ? 'v.explanation AS explanation'
        : 'COALESCE(NULLIF(TRIM(v.explanation), ""), NULLIF(TRIM(m.explanation), "")) AS explanation';
    $sql = "SELECT m.$pk as id, $topicColumn as topic, m.$questionColumn as question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option as current_correct, 
            v.verification_status, v.suggested_correct_option, v.original_correct_option, v.ai_notes, v.last_checked_at, $explanationSelect
            FROM $mainTable m
            $verifyJoinFetch
            WHERE m.$pk IN ($idsList)
            ORDER BY v.last_checked_at DESC";
            
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo '<div class="table-responsive mt-4"><table class="table table-hover table-bordered align-middle"><thead class="table-light"><tr>
                <th style="width: 5%">ID</th>
                <th style="width: 32%">Question & Options</th>
                <th style="width: 8%">Status</th>
                <th style="width: 20%">AI Notes / Correction</th>
                <th style="width: 35%">Explanation (learning)</th>
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
                $correctedText = '';
                $originalText = '';
                
                if ($sourceTable === 'mcqs') {
                    // For mcqs table, current_correct is letter, suggested_correct_option is text
                    $correctedText = htmlspecialchars($row['suggested_correct_option'] ?: $row['current_correct']);
                    
                    // Convert original letter to text
                    $origLetter = strtoupper($row['original_correct_option'] ?: '');
                    switch ($origLetter) {
                        case 'A': $originalText = htmlspecialchars($row['option_a']); break;
                        case 'B': $originalText = htmlspecialchars($row['option_b']); break;
                        case 'C': $originalText = htmlspecialchars($row['option_c']); break;
                        case 'D': $originalText = htmlspecialchars($row['option_d']); break;
                        default: $originalText = htmlspecialchars($row['original_correct_option'] ?: 'Unknown');
                    }
                } else {
                    // For AIGeneratedMCQs, both are text
                    $correctedText = htmlspecialchars($row['current_correct']);
                    $originalText = htmlspecialchars($row['original_correct_option'] ?: 'Unknown');
                }
                
                echo '<div class="mb-1"><strong>Corrected to:</strong> <span class="text-success fw-bold">' . $correctedText . '</span></div>';
                if (!empty($row['original_correct_option'])) {
                    echo '<div class="text-muted small">Previous: ' . $originalText . '</div>';
                }
            }
            if (!empty($row['ai_notes'])) {
                echo '<div class="ai-note mt-1">' . htmlspecialchars($row['ai_notes']) . '</div>';
            }
            echo '</td>';
            echo '<td class="small">';
            if (!empty($row['explanation'])) {
                echo '<div class="ai-note-box">' . nl2br(htmlspecialchars($row['explanation'])) . '</div>';
            } else {
                echo '<span class="text-muted">—</span>';
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
    $pk = 'mcq_id';
} else {
    $mainTable = 'AIGeneratedMCQs';
    $pk = 'id';
}

$vSrcSql = mcqVerificationSourceValue($sourceTable);
ensureMcqVerificationTable($conn);

$verifyJoinReport = ($sourceTable === 'mcqs')
    ? "LEFT JOIN MCQsVerification v ON m.$pk = v.mcq_id"
    : "LEFT JOIN MCQVerification v ON v.source = 'AIGeneratedMCQs' AND v.mcq_id = m.$pk";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'corrected';
$allowedFilters = ['verified', 'corrected', 'flagged', 'pending', 'all'];
if (!in_array($filter, $allowedFilters)) $filter = 'corrected';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = "";

if ($filter === 'all') {
    $whereClause = "WHERE 1=1";
} elseif ($filter === 'pending') {
    $whereClause = "WHERE (v.verification_status IS NULL OR v.verification_status = 'pending')";
} else {
    $whereClause = "WHERE v.verification_status = '$filter'";
}

if (!empty($search)) {
    $searchEscaped = $conn->real_escape_string($search);
    $questionColumn = ($sourceTable === 'mcqs') ? 'm.question' : 'm.question_text';
    $topicSearch = ($sourceTable === 'mcqs') ? 
        "(CONCAT('Class ', COALESCE(m.class_id, ''), ' - Book ', COALESCE(m.book_id, ''), ' - Chapter ', COALESCE(m.chapter_id, '')) LIKE '%$searchEscaped%')" : 
        "m.topic LIKE '%$searchEscaped%'";
    $whereClause .= " AND ($questionColumn LIKE '%$searchEscaped%' OR $topicSearch)";
}

// Count for Pagination
$countSql = "SELECT COUNT(*) as cnt FROM $mainTable m $verifyJoinReport $whereClause";
$countRes = $conn->query($countSql);
$totalRows = $countRes ? $countRes->fetch_assoc()['cnt'] : 0;
$totalPages = ceil($totalRows / $perPage);

    // Fetch Report Data
    $questionColumn = ($sourceTable === 'mcqs') ? 'question' : 'question_text';
    $topicColumn = ($sourceTable === 'mcqs') ? "CONCAT('Class ', COALESCE(m.class_id, 'Unknown'), ' - Book ', COALESCE(m.book_id, 'Unknown'), ' - Chapter ', COALESCE(m.chapter_id, 'Unknown'))" : 'm.topic';
    $explanationSelect = ($sourceTable === 'mcqs')
        ? 'v.explanation AS explanation'
        : 'COALESCE(NULLIF(TRIM(v.explanation), ""), NULLIF(TRIM(m.explanation), "")) AS explanation';
    $reportSql = "SELECT m.$pk as id, $topicColumn as topic, m.$questionColumn as question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option as current_correct, 
            v.verification_status, v.suggested_correct_option, v.original_correct_option, v.ai_notes, v.last_checked_at, $explanationSelect
            FROM $mainTable m
            $verifyJoinReport
            $whereClause
            ORDER BY COALESCE(v.last_checked_at, '1000-01-01') DESC, m.$pk DESC
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

// Verification Stats (per selected source)
if ($sourceTable === 'mcqs') {
    $res = $conn->query('SELECT verification_status, COUNT(*) as cnt FROM MCQsVerification GROUP BY verification_status');
} else {
    $res = $conn->query("SELECT verification_status, COUNT(*) as cnt FROM MCQVerification WHERE source = '" . $conn->real_escape_string($vSrcSql) . "' GROUP BY verification_status");
}
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
<?php include_once __DIR__ . '/../header.php'; ?>
<style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --secondary-color: #858796;
            --light-bg: #f8f9fc;
            --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        body { background-color: var(--light-bg); font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .main-container {
            max-width: 1400px;
            margin: 20px auto;
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
        }
        
        /* Stats Dashboard */
        .stat-card {
            background: #fff;
            border: none;
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.05);
        }
        .stat-icon {
            font-size: 2rem;
            opacity: 0.3;
            position: absolute;
            right: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            display: block;
            margin-bottom: 0.25rem;
        }
        .stat-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--secondary-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Verification Styles */
        .verification-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }
        .ai-loader-spinner {
            width: 70px;
            height: 70px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 2rem;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .progress-wrapper {
            background: #eaecf4;
            border-radius: 20px;
            height: 25px;
            width: 100%;
            overflow: hidden;
            margin-bottom: 2.5rem;
            position: relative;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        .progress-bar-custom {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: 700;
        }

        /* Report Table Improvements */
        .table thead th {
            background-color: #f8f9fc;
            text-transform: uppercase;
            font-size: 0.75rem;
            font-weight: 700;
            color: #4e73df;
            border-top: none;
            padding: 1rem;
        }
        .table tbody td {
            padding: 1.25rem 1rem;
            vertical-align: middle;
        }
        .mcq-preview {
            max-width: 450px;
        }
        .option-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .option-item.is-correct {
            background-color: #d4edda;
            color: #155724;
            font-weight: 600;
        }
        .option-letter {
            font-weight: 800;
            width: 25px;
            flex-shrink: 0;
        }
        
        .badge-corrected { background-color: var(--primary-color); color: white; }
        .badge-verified { background-color: var(--success-color); color: white; }
        .badge-flagged { background-color: var(--danger-color); color: white; }
        .badge-pending { background-color: var(--secondary-color); color: white; }

        .correction-highlight {
            border: 1px solid #ffeeba;
            background-color: #fffcf0;
            padding: 10px;
            border-radius: 8px;
            margin-top: 8px;
        }
        .ai-note-box {
            font-size: 0.85rem;
            color: #5a5c69;
            background: #f8f9fc;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .action-btns {
            display: flex;
            gap: 5px;
        }
        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .btn-action:hover { transform: scale(1.1); }

        /* Search Bar */
        .search-container {
            background: #f8f9fc;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border: 1px solid #eaecf4;
        }

        .log-container {
            margin-top: 2rem;
            background: #2c3e50;
            color: #ecf0f1;
            padding: 1.25rem;
            border-radius: 10px;
            height: 250px;
            overflow-y: auto;
            font-family: 'Fira Code', 'Consolas', monospace;
            font-size: 0.85rem;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);
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
                <div class="row mb-5 g-4">
                    <div class="col-md">
                        <a href="verify_ai_mcqs.php?source_table=<?= $sourceTable ?>&tab=report&filter=all" class="text-decoration-none">
                            <div class="stat-card border-start border-4 border-info shadow-sm">
                                <i class="fas fa-layer-group stat-icon text-info"></i>
                                <span class="stat-value text-info"><?= number_format($globalStats['total']) ?></span>
                                <span class="stat-label">Total MCQs</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md">
                        <a href="verify_ai_mcqs.php?source_table=<?= $sourceTable ?>&tab=report&filter=pending" class="text-decoration-none">
                            <div class="stat-card border-start border-4 border-secondary shadow-sm">
                                <i class="fas fa-hourglass-half stat-icon text-secondary"></i>
                                <span class="stat-value text-secondary"><?= number_format($globalStats['pending']) ?></span>
                                <span class="stat-label">Pending Review</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md">
                        <a href="verify_ai_mcqs.php?source_table=<?= $sourceTable ?>&tab=report&filter=verified" class="text-decoration-none">
                            <div class="stat-card border-start border-4 border-success shadow-sm">
                                <i class="fas fa-check-circle stat-icon text-success"></i>
                                <span class="stat-value text-success"><?= number_format($globalStats['verified']) ?></span>
                                <span class="stat-label">Verified Correct</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md">
                        <a href="verify_ai_mcqs.php?source_table=<?= $sourceTable ?>&tab=report&filter=corrected" class="text-decoration-none">
                            <div class="stat-card border-start border-4 border-primary shadow-sm">
                                <i class="fas fa-magic stat-icon text-primary"></i>
                                <span class="stat-value text-primary"><?= number_format($globalStats['corrected']) ?></span>
                                <span class="stat-label">Auto-Corrected</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md">
                        <a href="verify_ai_mcqs.php?source_table=<?= $sourceTable ?>&tab=report&filter=flagged" class="text-decoration-none">
                            <div class="stat-card border-start border-4 border-danger shadow-sm">
                                <i class="fas fa-flag stat-icon text-danger"></i>
                                <span class="stat-value text-danger"><?= number_format($globalStats['flagged']) ?></span>
                                <span class="stat-label">Flagged/Broken</span>
                            </div>
                        </a>
                    </div>
                </div>

                <div id="setup-phase" class="card shadow-sm border-0 p-4">
                    <h5 class="card-title border-bottom pb-3 mb-4"><i class="fas fa-cog me-2"></i>Verification Settings</h5>
                    <p class="text-muted small mb-4">
                        Batch verification uses <code>RECHECK_API_KEY</code> (or <code>GENERATING_KEYWORDS_KEY</code> if empty) from
                        <code>.env.local</code> / <code>.env.production</code>.
                        <code>nvapi-</code> keys use NVIDIA <code>integrate.api.nvidia.com</code> (same as keyword generation); other keys use OpenRouter.
                        Optional <code>RECHECK_MODEL</code>: if unset, NVIDIA defaults to <code>qwen/qwen3-next-80b-a3b-instruct</code>, OpenRouter to <code>AI_DEFAULT_MODEL</code>.
                    </p>
                    
                    <div class="row g-3 align-items-center justify-content-center mb-4">
                        <div class="col-auto">
                            <label class="form-label text-muted mb-0">Select Data Source:</label>
                        </div>
                        <div class="col-auto" style="width: 300px;">
                            <select class="form-select shadow-none" id="source-table">
                                <option value="AIGeneratedMCQs" <?= $sourceTable === 'AIGeneratedMCQs' ? 'selected' : '' ?>>AI Generated MCQs</option>
                                <option value="mcqs" <?= $sourceTable === 'mcqs' ? 'selected' : '' ?>>Manual MCQs (mcqs table)</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center gap-3">
                        <button onclick="startVerification('pending')" class="btn btn-primary btn-lg px-5 rounded-pill shadow-sm">
                            <i class="fas fa-play me-2"></i>Run Smart Batch (50)
                        </button>
                        <button onclick="toggleRangeInputs()" class="btn btn-outline-secondary btn-lg px-4 rounded-pill">
                            <i class="fas fa-sliders-h me-2"></i>Custom Range
                        </button>
                        <button onclick="switchToReportTab()" class="btn btn-outline-info btn-lg px-4 rounded-pill">
                            <i class="fas fa-list-ul me-2"></i>View All Records
                        </button>
                    </div>
                    
                    <div id="range-inputs" class="mt-4 p-3 bg-light rounded shadow-sm" style="display:none; max-width: 400px; margin: 20px auto;">
                        <div class="row g-2">
                            <div class="col">
                                <input type="number" id="start-id" class="form-control" placeholder="Start ID">
                            </div>
                            <div class="col">
                                <input type="number" id="end-id" class="form-control" placeholder="End ID">
                            </div>
                        </div>
                        <button onclick="startVerification('range')" class="btn btn-success w-100 mt-2">Start Custom Check</button>
                    </div>
                </div>

                <div id="process-phase" style="display:none;">
                    <div class="card shadow-sm border-0 p-4 text-center">
                        <div class="ai-loader-spinner" id="spinner"></div>
                        <div class="status-text h5 text-primary mb-4" id="status-text">Initializing AI analysis...</div>
                        
                        <div class="progress-wrapper">
                            <div class="progress-bar-custom" id="progress-bar">0%</div>
                        </div>

                        <div class="row g-3 justify-content-center mb-4">
                            <div class="col-md-2">
                                <div class="p-2 border rounded">
                                    <div class="small text-muted">Checked</div>
                                    <div class="h5 mb-0 fw-bold" id="stat-checked">0</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="p-2 border rounded">
                                    <div class="small text-success">Verified</div>
                                    <div class="h5 mb-0 fw-bold text-success" id="stat-verified">0</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="p-2 border rounded">
                                    <div class="small text-primary">Fixed</div>
                                    <div class="h5 mb-0 fw-bold text-primary" id="stat-corrected">0</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="p-2 border rounded">
                                    <div class="small text-danger">Flagged</div>
                                    <div class="h5 mb-0 fw-bold text-danger" id="stat-flagged">0</div>
                                </div>
                            </div>
                        </div>

                        <div class="log-container" id="log-console"></div>
                        
                        <div id="session-results-container" style="display:none; margin-top: 2rem;">
                            <h5 class="text-start mb-3 border-bottom pb-2">Batch Report</h5>
                            <div id="session-results-content"></div>
                        </div>

                        <div class="mt-4">
                            <button onclick="resetProcess()" class="btn btn-secondary btn-lg px-4 rounded-pill" id="back-btn" style="display:none;">
                                <i class="fas fa-redo me-2"></i>New Session
                            </button>
                            <button onclick="switchToReportTab()" class="btn btn-primary btn-lg px-4 rounded-pill ms-2" id="view-res-btn" style="display:none;">
                                <i class="fas fa-external-link-alt me-2"></i>View History
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Tab -->
        <div class="tab-pane fade <?= $activeTab === 'report' ? 'show active' : '' ?>" id="report" role="tabpanel" aria-labelledby="report-tab">
            <div class="search-container shadow-sm">
                <form action="verify_ai_mcqs.php" method="GET" class="row g-3">
                    <input type="hidden" name="tab" value="report">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-uppercase">Data Source</label>
                        <select class="form-select" name="source_table" onchange="this.form.submit()">
                            <option value="AIGeneratedMCQs" <?= $sourceTable === 'AIGeneratedMCQs' ? 'selected' : '' ?>>AI Generated MCQs</option>
                            <option value="mcqs" <?= $sourceTable === 'mcqs' ? 'selected' : '' ?>>Manual MCQs (mcqs)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-uppercase">Status Filter</label>
                        <select class="form-select" name="filter" onchange="this.form.submit()">
                            <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="verified" <?= $filter === 'verified' ? 'selected' : '' ?>>Verified Correct</option>
                            <option value="corrected" <?= $filter === 'corrected' ? 'selected' : '' ?>>Auto-Corrected</option>
                            <option value="flagged" <?= $filter === 'flagged' ? 'selected' : '' ?>>Flagged/Broken</option>
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Results</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Search Keyword</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search topic or question..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <div id="bulk-action-container" style="display: none;">
                        <button onclick="checkSelectedMCQs()" class="btn btn-primary rounded-pill shadow-sm">
                            <i class="fas fa-robot me-2"></i>Verify Selected (<span id="selected-count">0</span>)
                        </button>
                    </div>
                </div>
                <table class="table table-hover align-middle border shadow-sm rounded">
                    <thead>
                        <tr>
                            <th style="width: 3%"><input type="checkbox" id="select-all-mcqs" class="form-check-input"></th>
                            <th style="width: 5%">ID</th>
                            <th style="width: 12%">Topic</th>
                            <th style="width: 35%">Question & Options</th>
                            <th style="width: 10%">Status</th>
                            <th style="width: 18%">AI Findings</th>
                            <th style="width: 20%">Explanation</th>
                            <th style="width: 12%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reportResult && $reportResult->num_rows > 0): ?>
                            <?php while($row = $reportResult->fetch_assoc()): ?>
                                <tr id="mcq-row-<?= $row['id'] ?>">
                                    <td><input type="checkbox" class="form-check-input mcq-checkbox" value="<?= $row['id'] ?>" onchange="updateSelectedCount()"></td>
                                    <td class="fw-bold text-muted small">#<?= $row['id'] ?></td>
                                    <td><span class="badge bg-light text-dark border p-2"><?= htmlspecialchars($row['topic']) ?></span></td>
                                    <td>
                                        <div class="mcq-preview">
                                            <div class="fw-bold mb-2"><?= htmlspecialchars($row['question']) ?></div>
                                            <div class="options-list ps-2 border-start border-3 border-light">
                                                <?php 
                                                $options = ['a' => $row['option_a'], 'b' => $row['option_b'], 'c' => $row['option_c'], 'd' => $row['option_d']];
                                                foreach ($options as $key => $val):
                                                    $isCorrect = false;
                                                    if ($sourceTable === 'mcqs') {
                                                        // For mcqs table, correct_option is a letter
                                                        $isCorrect = (strcasecmp($row['current_correct'], strtoupper($key)) === 0);
                                                    } else {
                                                        // For AIGeneratedMCQs, correct_option is the text
                                                        $isCorrect = (strcasecmp($val, $row['current_correct']) === 0);
                                                    }
                                                ?>
                                                    <div class="option-item <?= $isCorrect ? 'is-correct' : '' ?>">
                                                        <span class="option-letter"><?= strtoupper($key) ?>)</span>
                                                        <span class="option-text"><?= htmlspecialchars($val) ?></span>
                                                        <?php if ($isCorrect): ?> <i class="fas fa-check-circle ms-2 small"></i> <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $row['verification_status'] ?: 'pending';
                                        $badgeClass = 'badge-pending';
                                        if ($status === 'verified') $badgeClass = 'badge-verified';
                                        elseif ($status === 'corrected') $badgeClass = 'badge-corrected';
                                        elseif ($status === 'flagged') $badgeClass = 'badge-flagged';
                                        ?>
                                        <span class="badge <?= $badgeClass ?> p-2 px-3 rounded-pill"><?= ucfirst($status) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($row['verification_status'] === 'corrected'): ?>
                                            <div class="correction-highlight shadow-sm">
                                                <div class="small text-muted mb-1"><i class="fas fa-magic me-1"></i> Corrected from:</div>
                                                <div class="text-danger text-decoration-line-through small mb-2">
                                                    <?php
                                                    if ($sourceTable === 'mcqs') {
                                                        // For mcqs table, original_correct_option is a letter, convert to text
                                                        $origLetter = strtoupper($row['original_correct_option'] ?: '');
                                                        $origText = 'Unknown';
                                                        switch ($origLetter) {
                                                            case 'A': $origText = $row['option_a']; break;
                                                            case 'B': $origText = $row['option_b']; break;
                                                            case 'C': $origText = $row['option_c']; break;
                                                            case 'D': $origText = $row['option_d']; break;
                                                        }
                                                        echo htmlspecialchars($origText);
                                                    } else {
                                                        echo htmlspecialchars($row['original_correct_option'] ?: 'Unknown');
                                                    }
                                                    ?>
                                                </div>
                                                <div class="small text-muted mb-1">To:</div>
                                                <div class="text-success fw-bold">
                                                    <?php
                                                    if ($sourceTable === 'mcqs') {
                                                        // For mcqs table, suggested_correct_option is already text
                                                        echo htmlspecialchars($row['suggested_correct_option'] ?: $row['current_correct']);
                                                    } else {
                                                        echo htmlspecialchars($row['current_correct']);
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['ai_notes'])): ?>
                                            <div class="ai-note-box mt-2">
                                                <i class="fas fa-comment-dots me-1 opacity-50"></i>
                                                <?= htmlspecialchars($row['ai_notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($row['explanation'])): ?>
                                            <div class="ai-note-box" style="max-height: 200px; overflow-y: auto;">
                                                <?= nl2br(htmlspecialchars($row['explanation'])) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button onclick="reverifySingle(<?= $row['id'] ?>)" class="btn btn-action btn-outline-info" title="Recheck with AI">
                                                <i class="fas fa-sync"></i>
                                            </button>
                                            <?php if ($row['verification_status'] !== 'verified'): ?>
                                                <button onclick="mcqAction(<?= $row['id'] ?>, 'approve')" class="btn btn-action btn-outline-success" title="Approve Current">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="mcqAction(<?= $row['id'] ?>, 'flag')" class="btn btn-action btn-outline-warning" title="Flag Problem">
                                                <i class="fas fa-flag"></i>
                                            </button>
                                            <button onclick="mcqAction(<?= $row['id'] ?>, 'delete_mcq')" class="btn btn-action btn-outline-danger" title="Delete MCQ">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <a href="manage_ai_mcqs.php?topic=<?= urlencode($row['topic']) ?>" class="btn btn-action btn-outline-primary" title="Edit in Manage">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                        <div class="small text-muted mt-2" style="font-size: 0.7rem;">
                                            <?php if ($row['last_checked_at']): ?>
                                                Checked: <?= date('M d, H:i', strtotime($row['last_checked_at'])) ?>
                                            <?php else: ?>
                                                Not Checked
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
                                    <p>No records found matching your criteria.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php 
                        $base_url = "verify_ai_mcqs.php?source_table=$sourceTable&tab=report&filter=$filter&search=" . urlencode($search);
                        ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base_url ?>&page=<?= $page - 1 ?>">Previous</a>
                        </li>
                        <?php for($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $page || $i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $base_url ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base_url ?>&page=<?= $page + 1 ?>">Next</a>
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

        // Bulk Selection Logic
        const selectAll = document.getElementById('select-all-mcqs');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.mcq-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateSelectedCount();
            });
        }
    });

    function updateSelectedCount() {
        const selected = document.querySelectorAll('.mcq-checkbox:checked');
        const count = selected.length;
        const selectedCountEl = document.getElementById('selected-count');
        const bulkActionContainer = document.getElementById('bulk-action-container');
        
        if (selectedCountEl) selectedCountEl.textContent = count;
        if (bulkActionContainer) bulkActionContainer.style.display = count > 0 ? 'block' : 'none';
        
        // Update Select All checkbox state
        const selectAll = document.getElementById('select-all-mcqs');
        if (selectAll) {
            const total = document.querySelectorAll('.mcq-checkbox').length;
            selectAll.checked = count === total && total > 0;
            selectAll.indeterminate = count > 0 && count < total;
        }
    }

    function checkSelectedMCQs() {
        const selected = Array.from(document.querySelectorAll('.mcq-checkbox:checked')).map(cb => parseInt(cb.value));
        if (selected.length === 0) return;
        
        if (!confirm(`Run AI verification for ${selected.length} selected MCQs?`)) return;

        // Show process phase and hide setup
        document.getElementById('setup-phase').style.display = 'none';
        document.getElementById('process-phase').style.display = 'block';
        document.getElementById('spinner').style.display = 'block';
        document.getElementById('log-console').style.display = 'block';
        
        // Switch to verify tab
        const verifyTabBtn = document.getElementById('verify-tab');
        const verifyTab = new bootstrap.Tab(verifyTabBtn);
        verifyTab.show();

        // Reset stats
        stats = { checked: 0, verified: 0, corrected: 0, flagged: 0 };
        currentProcessed = 0;
        totalToCheck = selected.length;
        allProcessedIds = [];
        mode = 'ids';
        idsToCheck = selected;
        
        updateUI();
        log(`Starting bulk verification for ${totalToCheck} selected items...`);
        runBatch();
    }

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
    
    function reverifySingle(id) {
        if (!confirm('Re-verify this MCQ with AI?')) return;
        
        // Show process phase and hide setup
        document.getElementById('setup-phase').style.display = 'none';
        document.getElementById('process-phase').style.display = 'block';
        document.getElementById('spinner').style.display = 'block';
        document.getElementById('log-console').style.display = 'block';
        
        // Switch to verify tab if not already there
        const verifyTabBtn = document.getElementById('verify-tab');
        const verifyTab = new bootstrap.Tab(verifyTabBtn);
        verifyTab.show();

        // Reset stats for this single check
        stats = { checked: 0, verified: 0, corrected: 0, flagged: 0 };
        currentProcessed = 0;
        totalToCheck = 1;
        allProcessedIds = [];
        mode = 'ids';
        idsToCheck = [id];
        
        updateUI();
        log(`Starting re-verification for MCQ #${id}...`);
        runBatch();
    }

    function mcqAction(id, action) {
        let confirmMsg = 'Are you sure?';
        if (action === 'delete_mcq') confirmMsg = 'This will permanently delete the MCQ. Proceed?';
        
        if (!confirm(confirmMsg)) return;
        
        const formData = new FormData();
        formData.append('action', action);
        formData.append('id', id);
        formData.append('source_table', sourceTable);
        formData.append('csrf_token', csrfToken);
        
        fetch('verify_ai_mcqs.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById(`mcq-row-${id}`);
                if (action === 'delete_mcq') {
                    row.style.transition = 'all 0.5s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 500);
                } else {
                    location.reload(); // Refresh to update status badges
                }
            } else {
                alert('Error: ' + (data.message || 'Action failed'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Network error');
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
