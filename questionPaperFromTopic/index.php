<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../header.php';

$questionTypes = (array)($_GET['type'] ?? []);
$search = $_GET['search'] ?? '';
$topics = [];
$autoLoadAI = false;

// Initialize default values for configuration inputs
$total_mcqs = $_GET['total_mcqs'] ?? 10;
$total_shorts = $_GET['total_shorts'] ?? 5;
$total_longs = $_GET['total_longs'] ?? 3;


if ($search && !empty($questionTypes)) {
    $term = "%$search%";
    
    // Step 1: Search MCQs
    if (in_array('mcqs', $questionTypes) || in_array('all', $questionTypes)) {
        $stmt = $conn->prepare("SELECT DISTINCT topic FROM mcqs WHERE topic LIKE ? LIMIT 50");
        $stmt->bind_param('s', $term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $topics[] = $row['topic'];
        }
        
        // Search AIGeneratedMCQs
        $stmt = $conn->prepare("SELECT DISTINCT topic FROM AIGeneratedMCQs WHERE topic LIKE ? LIMIT 50");
        $stmt->bind_param('s', $term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $topics[] = $row['topic'];
        }
    }
    
    // Step 2: Search other questions (Short/Long)
    $otherSearch = array_filter($questionTypes, function($t) { return $t === 'short' || $t === 'long'; });
    if (in_array('all', $questionTypes)) $otherSearch = ['short', 'long'];
    
    if (!empty($otherSearch)) {
        foreach ($otherSearch as $ot) {
            $stmt = $conn->prepare("SELECT DISTINCT topic FROM questions WHERE question_type = ? AND topic LIKE ? LIMIT 50");
            $stmt->bind_param('ss', $ot, $term);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $topics[] = $row['topic'];
            }
        }
    }
    
    // Step 3: If still no topics found, flag to auto-load via AI
    if (empty($topics)) {
        $autoLoadAI = true;
    }
    
    // Remove duplicates
    $topics = array_values(array_unique($topics));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Question Paper from Topic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
        }

        .page-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px 120px;
        }

        .main-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            padding: 48px;
        }

        .page-title {
            text-align: center;
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .page-subtitle {
            text-align: center;
            color: #64748b;
            font-size: 1.05rem;
            margin-bottom: 40px;
        }

        /* ========== Step Indicator ========== */
        .steps-indicator {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 40px;
        }

        .step-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e2e8f0;
            transition: all 0.3s ease;
        }

        .step-dot.active {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            width: 36px;
            border-radius: 6px;
        }

        .step-dot.completed {
            background: #22c55e;
        }

        /* ========== Question Type Selection ========== */
        .type-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .type-card {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .type-card:hover {
            transform: translateY(-8px);
            border-color: #6366f1;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .card-select-area {
            padding: 24px 20px;
            cursor: pointer;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .type-card.selected {
            border-color: #6366f1;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        }

        .type-card input {
            display: none;
        }

        .type-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 12px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #fff;
        }

        .type-card.selected .type-icon {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        }

        .type-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .type-desc {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 10px;
        }

        .configuration-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
            animation: fadeIn 0.5s ease;
        }

        .config-item {
            display: none; /* Controlled by toggleType */
            background: #ffffff;
            border: 2px solid #f1f5f9;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
        }

        .config-item.active {
            display: block;
            border-color: #6366f1;
            box-shadow: 0 15px 25px -5px rgba(99, 102, 241, 0.12);
            animation: slideUp 0.4s ease;
        }

        .config-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .config-title i {
            width: 32px;
            height: 32px;
            background: #eef2ff;
            color: #6366f1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .config-label-text {
            display: block;
            font-size: 0.75rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .input-group {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-group:focus-within {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .input-group-text {
            background: #f8fafc;
            padding: 8px 12px;
            color: #64748b;
            border-right: 1px solid #e2e8f0;
        }

        .form-control-custom {
            flex: 1;
            border: none;
            padding: 10px 12px;
            font-weight: 700;
            color: #1e293b;
            outline: none;
            width: 100%;
            background: transparent;
            cursor: text;
            -moz-appearance: textfield;
        }

        .form-control-custom::-webkit-outer-spin-button,
        .form-control-custom::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .type-check-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 24px;
            height: 24px;
            background: #6366f1;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .type-card.selected .type-check-badge {
            opacity: 1;
            transform: scale(1);
        }

        /* ========== Search Section ========== */
        .search-section {
            margin-bottom: 32px;
        }

        .search-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 12px;
            display: block;
        }

        .search-box {
            display: flex;
            gap: 12px;
        }

        .search-input {
            flex: 1;
            height: 56px;
            padding: 0 24px;
            font-size: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            outline: none;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .search-btn {
            height: 56px;
            padding: 0 32px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #fff;
            border: none;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            transform: translateY(-2px);
        }

        /* ========== Results Header ========== */
        .results-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }

        .results-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .results-title i {
            color: #6366f1;
        }

        .results-count {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* ========== Topics Grid - NO BOOTSTRAP ========== */
        .topics-container {
            /* No max-height - expands with content */
        }

        .topics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
        }

        .topic-item {
            display: block;
            cursor: pointer;
        }

        .topic-item input {
            display: none;
        }

        .topic-box {
            position: relative;
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 12px;
            text-align: center;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .topic-box:hover {
            border-color: #c7d2fe;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.12);
        }

        .topic-item input:checked + .topic-box {
            border-color: #6366f1;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        }

        .topic-text {
            font-weight: 600;
            color: #334155;
            font-size: 0.85rem;
            line-height: 1.3;
            word-break: break-word;
        }

        .topic-item input:checked + .topic-box .topic-text {
            color: #4338ca;
        }

        .topic-check {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 24px;
            height: 24px;
            background: #6366f1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.75rem;
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .topic-item input:checked + .topic-box .topic-check {
            opacity: 1;
            transform: scale(1);
        }

        /* ========== Load More Button ========== */
        .load-more-wrapper {
            text-align: center;
            margin-top: 24px;
        }

        .load-more-btn {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            color: #6366f1;
            padding: 14px 32px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .load-more-btn:hover {
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            border-color: #6366f1;
        }

        /* ========== Empty State ========== */
        .empty-state {
            background: linear-gradient(135deg, #fefce8, #fef9c3);
            border: 2px solid #fde047;
            border-radius: 20px;
            padding: 48px;
            text-align: center;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            background: #fef08a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: #ca8a04;
        }

        .empty-state h4 {
            color: #854d0e;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #a16207;
            margin-bottom: 20px;
        }

        /* ========== Floating Action Bar ========== */
        .action-bar {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(150%);
            width: calc(100% - 40px);
            max-width: 800px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 16px 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
        }

        .action-bar.visible {
            transform: translateX(-50%) translateY(0);
        }

        .action-bar-inner {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selection-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: #1e293b;
            font-size: 1.1rem;
        }

        .selection-dot {
            width: 12px;
            height: 12px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.3); }
        }

        .continue-btn {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff;
            border: none;
            padding: 14px 40px;
            border-radius: 14px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }

        .continue-btn:hover {
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            transform: translateY(-2px);
        }

        /* ========== Loading State ========== */
        .loading-state {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 20px;
            border: 2px dashed #cbd5e1;
        }

        .loading-icon {
            font-size: 3rem;
            color: #6366f1;
            margin-bottom: 16px;
        }

        .loading-state p {
            color: #64748b;
            font-size: 1.1rem;
        }

        .ai-btn:hover {
            background: #22c55e;
            color: #fff;
        }

        /* ========== Search Hint ========== */
        .search-hint {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 10px;
            text-align: center;
        }

        /* ========== Selected Topics Section ========== */
        .selected-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            border-radius: 12px 12px 0 0;
            border: 2px solid #86efac;
            border-bottom: none;
            color: #166534;
            font-weight: 600;
        }

        .selected-badge {
            background: #22c55e;
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .selected-grid {
            border: 2px solid #86efac;
            border-radius: 0 0 12px 12px;
            padding: 16px;
            background: #f0fdf4;
        }

        .selected-grid .topic-item input:checked + .topic-box {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            border-color: #22c55e;
        }

        .selected-grid .topic-item input:checked + .topic-box .topic-check {
            background: #22c55e;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .type-selection {
                grid-template-columns: 1fr;
            }
            .search-box {
                flex-direction: column;
            }
            .search-btn {
                width: 100%;
            }
            .ai-card {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="page-container">
    <div class="main-card">
        <h1 class="page-title">Generate Question Paper</h1>
        <p class="page-subtitle">Select question type, search topics, and create your paper</p>

        <!-- Step Indicator -->
        <div class="steps-indicator">
            <div class="step-dot <?= !empty($questionTypes) ? 'completed' : 'active' ?>"></div>
            <div class="step-dot <?= (!empty($questionTypes) && $search) ? 'completed' : (!empty($questionTypes) ? 'active' : '') ?>"></div>
            <div class="step-dot <?= (!empty($topics)) ? 'active' : '' ?>"></div>
        </div>

        <form action="configure_paper.php" method="POST" id="topicsForm">
            <input type="hidden" name="source" value="topics">
            <input type="hidden" name="pattern_mode" value="without">
            <input type="hidden" name="class_id" value="0">
            <input type="hidden" name="book_name" value="Custom Topic Paper">
            <input type="hidden" name="question_type" id="hiddenQuestionType" value="<?= (!empty($questionTypes)) ? htmlspecialchars(implode(',', $questionTypes)) : '' ?>">
            
            <!-- Step 1: Question Type Selection (Always Visible) -->
                <div class="type-selection">
                    <!-- MCQ Selection Button -->
                    <div class="type-card <?= in_array('mcqs', $questionTypes) ? 'selected' : '' ?>">
                        <div class="card-select-area" onclick="toggleType('mcqs', this.parentElement)">
                            <input type="checkbox" name="type[]" value="mcqs" <?= in_array('mcqs', $questionTypes) ? 'checked' : '' ?>>
                            <div class="type-check-badge"><i class="fas fa-check"></i></div>
                            <div class="type-icon"><i class="fas fa-list-check"></i></div>
                            <div class="type-title">MCQs</div>
                            <div class="type-desc">Multiple choice questions</div>
                        </div>
                    </div>

                    <!-- Short Questions Selection Button -->
                    <div class="type-card <?= in_array('short', $questionTypes) ? 'selected' : '' ?>">
                        <div class="card-select-area" onclick="toggleType('short', this.parentElement)">
                            <input type="checkbox" name="type[]" value="short" <?= in_array('short', $questionTypes) ? 'checked' : '' ?>>
                            <div class="type-check-badge"><i class="fas fa-check"></i></div>
                            <div class="type-icon"><i class="fas fa-align-left"></i></div>
                            <div class="type-title">Short Questions</div>
                            <div class="type-desc">Brief answer questions</div>
                        </div>
                    </div>

                    <!-- Long Questions Selection Button -->
                    <div class="type-card <?= in_array('long', $questionTypes) ? 'selected' : '' ?>">
                        <div class="card-select-area" onclick="toggleType('long', this.parentElement)">
                            <input type="checkbox" name="type[]" value="long" <?= in_array('long', $questionTypes) ? 'checked' : '' ?>>
                            <div class="type-check-badge"><i class="fas fa-check"></i></div>
                            <div class="type-icon"><i class="fas fa-file-lines"></i></div>
                            <div class="type-title">Long Questions</div>
                            <div class="type-desc">Detailed answer questions</div>
                        </div>
                    </div>
                </div>

                <!-- SEPARATE Configuration Section -->
                <div class="configuration-grid" id="configGrid">
                    <!-- MCQ Quantity Configuration -->
                    <div class="config-item <?= in_array('mcqs', $questionTypes) ? 'active' : '' ?>" id="config-mcqs">
                        <div class="config-title"><i class="fas fa-list-check"></i> MCQs Configuration</div>
                        <label class="config-label-text">How many MCQs to include?</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                            <input type="number" name="total_mcqs" class="form-control-custom" value="<?= htmlspecialchars($total_mcqs) ?>" min="0" max="100" required>
                        </div>
                    </div>

                    <!-- Short Questions Configuration -->
                    <div class="config-item <?= in_array('short', $questionTypes) ? 'active' : '' ?>" id="config-short">
                        <div class="config-title"><i class="fas fa-align-left"></i> Short Questions Configuration</div>
                        <label class="config-label-text">Total number of short questions?</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                            <input type="number" name="total_shorts" class="form-control-custom" value="<?= htmlspecialchars($total_shorts) ?>" min="0" max="100" required>
                        </div>
                    </div>

                    <!-- Long Questions Configuration -->
                    <div class="config-item <?= in_array('long', $questionTypes) ? 'active' : '' ?>" id="config-long">
                        <div class="config-title"><i class="fas fa-file-lines"></i> Long Questions Configuration</div>
                        <label class="config-label-text">Total number of long questions?</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                            <input type="number" name="total_longs" class="form-control-custom" value="<?= htmlspecialchars($total_longs) ?>" min="0" max="100" required>
                        </div>
                    </div>
                </div>

            <!-- Step 2: Search (Hidden until a type is selected) -->
            <div class="search-section" id="searchSection" style="display: <?= (!empty($questionTypes)) ? 'block' : 'none' ?>;">
                <label class="search-label" id="searchLabel">Search Topics</label>
                <div class="search-box">
                    <input type="text" id="topicSearchInput" class="search-input" placeholder="Enter topic name (e.g., Photosynthesis, Cell Biology)..." value="">
                    <button type="button" class="search-btn" onclick="searchTopics()"><i class="fas fa-search"></i> Search</button>
                </div>
                <p class="search-hint">You can search multiple times and select topics from different searches</p>
            </div>

        <!-- Results Section - Always visible -->
        <div id="resultsContainer" style="display: <?= (!empty($questionTypes)) ? 'block' : 'none' ?>;">
            <div class="results-header" id="resultsHeader" style="display: none;">
                <div class="results-title"><i class="fas fa-folder-open"></i> Found Topics</div>
                <div class="results-count" id="resultsCount">0 results</div>
            </div>

                <!-- Step 1 selection was moved up to be always visible -->
                
                <!-- Selected Topics Section -->
                <div id="selectedTopicsSection" style="display: none; margin-bottom: 32px;">
                    <div class="selected-header">
                        <span><i class="fas fa-check-circle"></i> Selected Topics</span>
                        <span id="selectedBadge" class="selected-badge">0</span>
                    </div>
                    <div id="selectedTopicsGrid" class="topics-grid selected-grid"></div>
                </div>
                
                <!-- Search Results Section -->
                <div class="topics-container">
                    <div class="topics-grid" id="topicsGrid"></div>
                </div>

                <div class="load-more-wrapper" id="loadMoreWrapper" style="display: none;">
                    <button type="button" class="load-more-btn" id="loadMoreBtn" onclick="loadMoreTopics(this)">
                        <i class="fas fa-magic"></i> Discover More
                    </button>
                </div>

                <!-- Loading State -->
                <div class="loading-state" id="loadingState" style="display: none;">
                    <div class="loading-icon"><i class="fas fa-spinner fa-spin"></i></div>
                    <p>Searching for topics...</p>
                </div>

                <!-- Floating Action Bar -->
                <div id="actionBar" class="action-bar">
                    <div class="action-bar-inner">
                        <div class="selection-info">
                            <div class="selection-dot"></div>
                            <span id="selectionCount">0 topics selected</span>
                        </div>
                        <button type="submit" class="continue-btn">
                            Generate Paper <i class="fas fa-magic"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    let selectedQuestionTypes = <?= json_encode($questionTypes) ?>;

    function toggleType(type, card) {
        const checkbox = card.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
        
        const configItem = document.getElementById('config-' + type);
        if (checkbox.checked) {
            card.classList.add('selected');
            if (configItem) configItem.classList.add('active');
            if (!selectedQuestionTypes.includes(type)) selectedQuestionTypes.push(type);
        } else {
            card.classList.remove('selected');
            if (configItem) configItem.classList.remove('active');
            selectedQuestionTypes = selectedQuestionTypes.filter(t => t !== type);
        }
        
        const hiddenType = document.getElementById('hiddenQuestionType');
        if (hiddenType) {
            // If multiple types are selected, use 'all', otherwise use the single type
            hiddenType.value = selectedQuestionTypes.length > 1 ? 'all' : (selectedQuestionTypes[0] || '');
        }

        // Show/Hide search section
        const hasSelection = selectedQuestionTypes.length > 0;
        const searchSection = document.getElementById('searchSection');
        const resultsContainer = document.getElementById('resultsContainer');
        if (searchSection) searchSection.style.display = hasSelection ? 'block' : 'none';
        if (resultsContainer) resultsContainer.style.display = hasSelection ? 'block' : 'none';
        
        // Update selection count context
        const searchLabel = document.getElementById('searchLabel');
        if (searchLabel && hasSelection) {
            const labels = { 'mcqs': 'MCQs', 'short': 'Short Questions', 'long': 'Long Questions' };
            searchLabel.textContent = 'Search Topics for ' + selectedQuestionTypes.map(t => labels[t]).join(', ');
        }

        // Update step indicators
        const dots = document.querySelectorAll('.step-dot');
        if (hasSelection) {
            if (dots[0]) dots[0].classList.add('completed');
            if (dots[1]) dots[1].classList.add('active');
        } else {
            if (dots[0]) dots[0].classList.remove('completed');
            if (dots[0]) dots[0].classList.add('active');
            if (dots[1]) dots[1].classList.remove('active');
            if (dots[1]) dots[1].classList.remove('completed');
        }

        // Clear current results
        const grid = document.getElementById('topicsGrid');
        if (grid) grid.innerHTML = '';
        const resultsHeader = document.getElementById('resultsHeader');
        if (resultsHeader) resultsHeader.style.display = 'none';
        const loadMoreWrapper = document.getElementById('loadMoreWrapper');
        if (loadMoreWrapper) loadMoreWrapper.style.display = 'none';
    }

    // Replace original function call with our new toggle logic
    window.selectType = function() { console.log('Legacy selectType called - redirected to toggleType'); };

    // Store selected topics across searches
    let selectedTopics = new Set();
    let currentSearchTerm = '';

    // Search topics via AJAX
    async function searchTopics() {
        const input = document.getElementById('topicSearchInput');
        const searchTerm = input.value.trim();
        if (!searchTerm) {
            alert('Please enter a topic to search');
            return;
        }

        currentSearchTerm = searchTerm;
        
        // Build types query string
        let typeParams = selectedQuestionTypes.map(t => 'type[]=' + encodeURIComponent(t)).join('&');
        
        // Show loading
        document.getElementById('loadingState').style.display = 'block';
        document.getElementById('resultsHeader').style.display = 'none';
        document.getElementById('loadMoreWrapper').style.display = 'none';
        
        // Clear search results grid (but keep selected topics)
        document.getElementById('topicsGrid').innerHTML = '';

        try {
            // First search database
            const response = await fetch('search_topics.php?search=' + encodeURIComponent(searchTerm) + '&' + typeParams);
            const data = await response.json();
            
            document.getElementById('loadingState').style.display = 'none';
            
            if (data.success && data.topics && data.topics.length > 0) {
                displayTopics(data.topics);
                document.getElementById('resultsHeader').style.display = 'flex';
                document.getElementById('resultsCount').textContent = data.topics.length + ' results';
                document.getElementById('loadMoreWrapper').style.display = 'block';
            } else {
                // No DB results - auto search with AI
                await loadMoreTopicsAuto();
            }
        } catch (e) {
            console.error(e);
            document.getElementById('loadingState').style.display = 'none';
            // Try AI fallback
            await loadMoreTopicsAuto();
        }
        
        // Clear input for next search
        input.value = '';
    }

    // Display topics in grid (without duplicates from selected)
    function displayTopics(topics) {
        const grid = document.getElementById('topicsGrid');
        
        topics.forEach(topic => {
            // Skip if already selected
            if (selectedTopics.has(topic)) return;
            
            // Skip if already in grid
            const existing = Array.from(grid.querySelectorAll('.topic-text')).map(el => el.textContent.trim());
            if (existing.includes(topic)) return;
            
            const label = document.createElement('label');
            label.className = 'topic-item';
            label.innerHTML = `
                <input type="checkbox" name="topics[]" value="${topic.replace(/"/g, '&quot;')}" onchange="handleTopicChange(this)">
                <div class="topic-box">
                    <div class="topic-text">${topic}</div>
                    <div class="topic-check"><i class="fas fa-check"></i></div>
                </div>
            `;
            grid.appendChild(label);
        });
        
        document.getElementById('resultsHeader').style.display = 'flex';
        const totalInGrid = grid.querySelectorAll('.topic-item').length;
        document.getElementById('resultsCount').textContent = totalInGrid + ' results';
    }

    // Handle topic selection/deselection
    function handleTopicChange(checkbox) {
        const topicValue = checkbox.value;
        const label = checkbox.closest('.topic-item');
        
        if (checkbox.checked) {
            // Add to selected
            selectedTopics.add(topicValue);
            moveToSelected(label, topicValue);
        } else {
            // Remove from selected
            selectedTopics.delete(topicValue);
            removeFromSelected(topicValue);
        }
        
        updateSelection();
        updateSelectedSection();
    }

    // Move topic to selected section
    function moveToSelected(label, topicValue) {
        const selectedGrid = document.getElementById('selectedTopicsGrid');
        
        // Create new element in selected section
        const newLabel = document.createElement('label');
        newLabel.className = 'topic-item';
        newLabel.dataset.topic = topicValue;
        newLabel.innerHTML = `
            <input type="checkbox" name="topics[]" value="${topicValue.replace(/"/g, '&quot;')}" checked onchange="handleTopicChange(this)">
            <div class="topic-box">
                <div class="topic-text">${topicValue}</div>
                <div class="topic-check"><i class="fas fa-check"></i></div>
            </div>
        `;
        selectedGrid.appendChild(newLabel);
        
        // Remove from search results
        label.remove();
    }

    // Remove from selected section
    function removeFromSelected(topicValue) {
        const selectedGrid = document.getElementById('selectedTopicsGrid');
        const item = selectedGrid.querySelector(`[data-topic="${CSS.escape(topicValue)}"]`);
        if (item) item.remove();
    }

    // Update selected section visibility
    function updateSelectedSection() {
        const section = document.getElementById('selectedTopicsSection');
        const badge = document.getElementById('selectedBadge');
        
        if (selectedTopics.size > 0) {
            section.style.display = 'block';
            badge.textContent = selectedTopics.size;
        } else {
            section.style.display = 'none';
        }
    }

    function updateSelection() {
        const count = selectedTopics.size;
        const actionBar = document.getElementById('actionBar');
        const countSpan = document.getElementById('selectionCount');
        
        if (count > 0) {
            actionBar.classList.add('visible');
            countSpan.textContent = count + ' topic' + (count !== 1 ? 's' : '') + ' selected';
        } else {
            actionBar.classList.remove('visible');
        }
    }

    // Auto load from AI
    async function loadMoreTopicsAuto() {
        const searchTerm = currentSearchTerm;
        if (!searchTerm || selectedQuestionTypes.length === 0) return;
        
        let typeParams = selectedQuestionTypes.map(t => 'type[]=' + encodeURIComponent(t)).join('&');
        document.getElementById('loadingState').style.display = 'block';
        
        try {
            const response = await fetch('fetch_more_topics.php?search=' + encodeURIComponent(searchTerm) + '&' + typeParams);
            const data = await response.json();
            
            document.getElementById('loadingState').style.display = 'none';
            
            if (data.success && data.topics && data.topics.length > 0) {
                displayTopics(data.topics);
                document.getElementById('loadMoreWrapper').style.display = 'block';
            }
        } catch (e) {
            console.error(e);
            document.getElementById('loadingState').style.display = 'none';
        }
    }

    // Allow Enter key to search
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('topicSearchInput');
        if (input) {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchTopics();
                }
            });
        }
    });

    async function loadMoreTopics(btn) {
        const searchTerm = currentSearchTerm;
        if (!searchTerm || selectedQuestionTypes.length === 0) return;
        
        let typeParams = selectedQuestionTypes.map(t => 'type[]=' + encodeURIComponent(t)).join('&');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finding topics...';
        btn.disabled = true;
        
        try {
            const response = await fetch('fetch_more_topics.php?search=' + encodeURIComponent(searchTerm) + '&' + typeParams);
            const data = await response.json();
            
            if (data.success && data.topics && data.topics.length > 0) {
                displayTopics(data.topics);
                
                // Hide the load more button after AI has been called
                btn.style.display = 'none';
            } else {
                // No results from API - hide button silently
                btn.style.display = 'none';
            }
        } catch (e) {
            console.error(e);
            // Silently fail - just hide the button
            btn.style.display = 'none';
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    // Auto-load AI topics if no database topics found
    <?php if ($autoLoadAI): ?>
    document.addEventListener('DOMContentLoaded', function() {
        autoLoadAITopics();
    });
    
    async function autoLoadAITopics() {
        const searchTerm = <?= json_encode($search) ?>;
        currentSearchTerm = searchTerm;
        if (!searchTerm || selectedQuestionTypes.length === 0) return;
        
        let typeParams = selectedQuestionTypes.map(t => 'type[]=' + encodeURIComponent(t)).join('&');
        
        try {
            const response = await fetch('fetch_more_topics.php?search=' + encodeURIComponent(searchTerm) + '&' + typeParams);
            const data = await response.json();
            
            // Hide loading state
            const loadingEl = document.getElementById('loadingState');
            if (loadingEl) loadingEl.style.display = 'none';
            
            if (data.success && data.topics && data.topics.length > 0) {
                displayTopics(data.topics);
            } else {
                // Show error
                if (loadingEl) {
                    loadingEl.innerHTML = `
                        <div class="loading-icon"><i class="fas fa-exclamation-circle" style="color: #f59e0b;"></i></div>
                        <p>No topics found. Try a different search term.</p>
                    `;
                    loadingEl.style.display = 'block';
                }
            }
        } catch (e) {
            console.error(e);
            const loadingEl = document.getElementById('loadingState');
            if (loadingEl) {
                loadingEl.innerHTML = `
                    <div class="loading-icon"><i class="fas fa-times-circle" style="color: #ef4444;"></i></div>
                    <p>Failed to load topics. Please refresh and try again.</p>
                `;
            }
        }
    }
    <?php endif; ?>
</script>

</body>
</html>
