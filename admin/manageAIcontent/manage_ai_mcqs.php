<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../quiz/mcq_generator.php';
require_once __DIR__ . '/../../includes/ai_mcq_recommendations.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

if (isset($conn) && function_exists('ensureMcqVerificationTable')) {
    ensureMcqVerificationTable($conn);
}
$recommendationsReady = isset($conn) && ensureAiTopicRecommendationsTable($conn);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'recommendations') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false];

    try {
        if (!$recommendationsReady) {
            throw new RuntimeException('Recommendation storage is not available.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
                http_response_code(403);
                throw new RuntimeException('Security token invalid. Please refresh the page.');
            }

            if (($_POST['action'] ?? '') !== 'remove_recommended_topic') {
                http_response_code(400);
                throw new RuntimeException('Invalid recommendation action.');
            }

            $topicToRemove = trim((string)($_POST['topic'] ?? ''));
            if ($topicToRemove === '') {
                http_response_code(422);
                throw new RuntimeException('A topic is required.');
            }

            $removeStmt = $conn->prepare('DELETE FROM AIRecommendedTopics WHERE topic_name = ?');
            if (!$removeStmt) {
                throw new RuntimeException('Unable to prepare the removal request.');
            }
            $removeStmt->bind_param('s', $topicToRemove);
            $removeStmt->execute();
            $removed = $removeStmt->affected_rows > 0;
            $removeStmt->close();

            if ($removed) {
                logAdminAction('remove_ai_topic_recommendation', "Topic: '$topicToRemove'");
            }

            $response = [
                'success' => true,
                'removed' => $removed,
                'message' => $removed ? 'Topic removed from recommendations.' : 'Topic was already removed.'
            ];
        } else {
            $selectedTopic = trim((string)($_GET['topic'] ?? ''));

            if ($selectedTopic !== '') {
                $selectedStmt = $conn->prepare(
                    'SELECT topic_name FROM AIRecommendedTopics WHERE topic_name = ? LIMIT 1'
                );
                $selectedStmt->bind_param('s', $selectedTopic);
                $selectedStmt->execute();
                $isSelected = $selectedStmt->get_result()->num_rows > 0;
                $selectedStmt->close();

                if (!$isSelected) {
                    http_response_code(404);
                    throw new RuntimeException('This topic is not in the recommendation pool.');
                }

                $detailsStmt = $conn->prepare(
                    'SELECT id, question_text AS question, option_a, option_b, option_c, option_d,
                            correct_option, explanation, generated_at
                     FROM AIGeneratedMCQs
                     WHERE topic = ?
                     ORDER BY generated_at DESC, id DESC'
                );
                $detailsStmt->bind_param('s', $selectedTopic);
                $detailsStmt->execute();
                $detailsResult = $detailsStmt->get_result();
                $mcqDetails = [];
                while ($detail = $detailsResult->fetch_assoc()) {
                    $mcqDetails[] = $detail;
                }
                $detailsStmt->close();

                $response = [
                    'success' => true,
                    'topic' => $selectedTopic,
                    'mcqs' => $mcqDetails
                ];
            } else {
                $selectedResult = $conn->query(
                    "SELECT r.topic_name, r.selected_at, r.selected_by, COUNT(m.id) AS mcq_count
                     FROM AIRecommendedTopics r
                     LEFT JOIN AIGeneratedMCQs m ON BINARY m.topic = BINARY r.topic_name
                     GROUP BY r.topic_name, r.selected_at, r.selected_by
                     ORDER BY r.selected_at DESC, r.topic_name ASC"
                );
                $selectedTopics = [];
                while ($selectedResult && ($selected = $selectedResult->fetch_assoc())) {
                    $selected['mcq_count'] = (int)$selected['mcq_count'];
                    $selectedTopics[] = $selected;
                }

                $response = ['success' => true, 'topics' => $selectedTopics];
            }
        }
    } catch (Throwable $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_topic_recommendations') {
            if (!$recommendationsReady) {
                $error = 'Recommendation storage is not available. Please check the database setup.';
            } else {
                $postedTopics = is_array($_POST['recommended_topics'] ?? null)
                    ? $_POST['recommended_topics']
                    : [];
                $postedTopics = array_values(array_unique(array_filter(array_map(
                    static function ($topic) {
                        return mb_substr(trim((string)$topic), 0, 255);
                    },
                    $postedTopics
                ), static function ($topic) { return $topic !== ''; })));

                $availableTopics = [];
                $availableResult = $conn->query(
                    "SELECT DISTINCT topic FROM AIGeneratedMCQs
                     WHERE topic IS NOT NULL AND TRIM(topic) != ''"
                );
                while ($availableResult && ($available = $availableResult->fetch_assoc())) {
                    $availableTopics[$available['topic']] = true;
                }
                $selectedTopics = array_values(array_filter(
                    $postedTopics,
                    static function ($topic) use ($availableTopics) {
                        return isset($availableTopics[$topic]);
                    }
                ));

                $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
                $insertRecommendation = $conn->prepare(
                    'INSERT INTO AIRecommendedTopics (topic_name, selected_by) VALUES (?, ?)'
                );

                if (!$insertRecommendation) {
                    $error = 'Failed to prepare recommendation update.';
                } else {
                    try {
                        $conn->begin_transaction();
                        if (!$conn->query('DELETE FROM AIRecommendedTopics')) {
                            throw new RuntimeException('Unable to clear the previous recommendation pool.');
                        }

                        foreach ($selectedTopics as $selectedTopic) {
                            $insertRecommendation->bind_param('si', $selectedTopic, $adminId);
                            if (!$insertRecommendation->execute()) {
                                throw new RuntimeException('Unable to save a selected topic.');
                            }
                        }

                        $conn->commit();
                        $message = count($selectedTopics) . ' recommended topic(s) saved.';
                        logAdminAction(
                            'save_ai_topic_recommendations',
                            'Selected topics: ' . count($selectedTopics)
                        );
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $error = 'Database error while saving topic recommendations.';
                    }
                    $insertRecommendation->close();
                }
            }
        } elseif ($action === 'update') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $topic = sanitizeInput($_POST['topic'] ?? '');
                $question = sanitizeInput($_POST['question'] ?? '');
                $optionA = sanitizeInput($_POST['option_a'] ?? '');
                $optionB = sanitizeInput($_POST['option_b'] ?? '');
                $optionC = sanitizeInput($_POST['option_c'] ?? '');
                $optionD = sanitizeInput($_POST['option_d'] ?? '');
                $explanation = sanitizeInput($_POST['explanation'] ?? '');
                $correctLetter = strtoupper(trim($_POST['correct_option'] ?? ''));

                $correctText = '';
                switch ($correctLetter) {
                    case 'A':
                        $correctText = $optionA;
                        break;
                    case 'B':
                        $correctText = $optionB;
                        break;
                    case 'C':
                        $correctText = $optionC;
                        break;
                    case 'D':
                        $correctText = $optionD;
                        break;
                }

                if ($question === '' || $optionA === '' || $optionB === '' || $optionC === '' || $optionD === '' || $correctText === '') {
                    $error = 'All options and correct answer are required for updating an MCQ.';
                } else {
                    $stmt = $conn->prepare(
                        "UPDATE AIGeneratedMCQs 
                         SET topic = ?, question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, explanation = ?
                         WHERE id = ?"
                    );
                    if ($stmt) {
                        $stmt->bind_param(
                            'ssssssssi',
                            $topic,
                            $question,
                            $optionA,
                            $optionB,
                            $optionC,
                            $optionD,
                            $correctText,
                            $explanation,
                            $id
                        );
                        if ($stmt->execute()) {
                            $message = 'AI MCQ updated successfully.';
                            logAdminAction('update_ai_mcq', "AI MCQ ID: $id");
                        } else {
                            $error = 'Database error while updating MCQ: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error = 'Failed to prepare update statement.';
                    }
                }
            } else {
                $error = 'Invalid MCQ ID.';
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $src = 'AIGeneratedMCQs';
                $delV = $conn->prepare('DELETE FROM MCQVerification WHERE source = ? AND mcq_id = ?');
                if ($delV) {
                    $delV->bind_param('si', $src, $id);
                    $delV->execute();
                    $delV->close();
                }
                $stmt = $conn->prepare("DELETE FROM AIGeneratedMCQs WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    if ($stmt->execute()) {
                        $message = 'AI MCQ deleted successfully.';
                        logAdminAction('delete_ai_mcq', "AI MCQ ID: $id");
                    } else {
                        $error = 'Database error while deleting MCQ: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to prepare delete statement.';
                }
            } else {
                $error = 'Invalid MCQ ID.';
            }
        } elseif ($action === 'rename_topic') {
            $oldTopic = sanitizeInput($_POST['old_topic'] ?? '');
            $newTopic = sanitizeInput($_POST['new_topic'] ?? '');

            if ($oldTopic === '' || $newTopic === '') {
                $error = 'Both old and new topic names are required.';
            } elseif (strcasecmp($oldTopic, $newTopic) === 0) {
                $error = 'Old and new topic names must be different.';
            } else {
                $stmt = $conn->prepare(
                    "UPDATE AIGeneratedMCQs 
                     SET topic = ? 
                     WHERE topic = ?"
                );
                if ($stmt) {
                    $stmt->bind_param('ss', $newTopic, $oldTopic);
                    if ($stmt->execute()) {
                        $affected = $stmt->affected_rows;
                        if ($affected > 0) {
                            if ($recommendationsReady) {
                                $copyRecommendation = $conn->prepare(
                                    'INSERT IGNORE INTO AIRecommendedTopics (topic_name, selected_by, selected_at)
                                     SELECT ?, selected_by, selected_at
                                     FROM AIRecommendedTopics WHERE topic_name = ?'
                                );
                                if ($copyRecommendation) {
                                    $copyRecommendation->bind_param('ss', $newTopic, $oldTopic);
                                    $copyRecommendation->execute();
                                    $copyRecommendation->close();
                                }
                                $removeOldRecommendation = $conn->prepare(
                                    'DELETE FROM AIRecommendedTopics WHERE topic_name = ?'
                                );
                                if ($removeOldRecommendation) {
                                    $removeOldRecommendation->bind_param('s', $oldTopic);
                                    $removeOldRecommendation->execute();
                                    $removeOldRecommendation->close();
                                }
                            }
                            $message = "Topic name updated in $affected AI MCQs.";
                            logAdminAction('rename_ai_topic', "From '$oldTopic' to '$newTopic' ($affected rows)");
                        } else {
                            $message = 'No AI MCQs found with the specified old topic name.';
                        }
                    } else {
                        $error = 'Database error while renaming topic: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to prepare topic rename statement.';
                }
            }
        }
    }
}

$topicFilter = trim($_GET['topic'] ?? '');

$topicCounts = [];
$topicCountsSql = $recommendationsReady
    ? "SELECT m.topic, COUNT(*) AS total,
              CASE WHEN r.topic_name IS NULL THEN 0 ELSE 1 END AS is_recommended
       FROM AIGeneratedMCQs m
       LEFT JOIN AIRecommendedTopics r ON BINARY r.topic_name = BINARY m.topic
       WHERE m.topic IS NOT NULL AND m.topic <> ''
       GROUP BY m.topic, r.topic_name
       ORDER BY total DESC, m.topic ASC"
    : "SELECT topic, COUNT(*) AS total, 0 AS is_recommended
       FROM AIGeneratedMCQs
       WHERE topic IS NOT NULL AND topic <> ''
       GROUP BY topic
       ORDER BY total DESC, topic ASC";
$topicCountsResult = $conn->query($topicCountsSql);
if ($topicCountsResult) {
    while ($row = $topicCountsResult->fetch_assoc()) {
        $topicCounts[] = $row;
    }
}

$overallTotal = 0;
$totalRes = $conn->query("SELECT COUNT(*) AS cnt FROM AIGeneratedMCQs");
if ($totalRes && ($row = $totalRes->fetch_assoc())) {
    $overallTotal = (int)$row['cnt'];
}

$recommendedTotal = 0;
if ($recommendationsReady) {
    $recommendedTotalRes = $conn->query("SELECT COUNT(*) AS cnt FROM AIRecommendedTopics");
    if ($recommendedTotalRes && ($row = $recommendedTotalRes->fetch_assoc())) {
        $recommendedTotal = (int)$row['cnt'];
    }
}

$whereSql = '';
$whereParams = [];
$whereTypes = '';

if ($topicFilter !== '') {
    $whereSql = 'WHERE topic LIKE ?';
    $whereParams[] = '%' . $topicFilter . '%';
    $whereTypes .= 's';
}

$totalFiltered = 0;
if ($whereSql === '') {
    $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM AIGeneratedMCQs");
    if ($cntRes && ($row = $cntRes->fetch_assoc())) {
        $totalFiltered = (int)$row['cnt'];
    }
} else {
    $cntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM AIGeneratedMCQs $whereSql");
    if ($cntStmt) {
        if ($whereTypes !== '') {
            $cntStmt->bind_param($whereTypes, ...$whereParams);
        }
        $cntStmt->execute();
        $cntResult = $cntStmt->get_result();
        if ($cntResult && ($row = $cntResult->fetch_assoc())) {
            $totalFiltered = (int)$row['cnt'];
        }
        $cntStmt->close();
    }
}

$perPageParam = $_GET['per_page'] ?? '20';
$perPage = 20;
$viewAll = false;
if ($perPageParam === 'all') {
    $viewAll = true;
    $perPage = max(1, $totalFiltered > 0 ? $totalFiltered : 1);
} else {
    $perPageInt = intval($perPageParam);
    if ($perPageInt > 0 && $perPageInt <= 200) {
        $perPage = $perPageInt;
    }
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

$totalPages = $perPage > 0 ? (int)ceil($totalFiltered / $perPage) : 1;
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;
if ($offset < 0) $offset = 0;

$limitSql = $viewAll ? '' : " LIMIT $offset, $perPage";

$mcqs = [];
if ($whereSql === '') {
    $sql = "SELECT m.id, m.topic, m.question_text AS question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option, m.explanation, m.generated_at, v.verification_status, v.last_checked_at
            FROM AIGeneratedMCQs m
            LEFT JOIN MCQVerification v ON v.source = 'AIGeneratedMCQs' AND v.mcq_id = m.id
            ORDER BY m.generated_at DESC" . $limitSql;
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $mcqs[] = $row;
        }
    }
} else {
    $sql = "SELECT m.id, m.topic, m.question_text AS question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option, m.explanation, m.generated_at, v.verification_status, v.last_checked_at
            FROM AIGeneratedMCQs m
            LEFT JOIN MCQVerification v ON v.source = 'AIGeneratedMCQs' AND v.mcq_id = m.id
            $whereSql 
            ORDER BY m.generated_at DESC" . $limitSql;
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($whereTypes !== '') {
            $stmt->bind_param($whereTypes, ...$whereParams);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $mcqs[] = $row;
            }
        }
        $stmt->close();
    }
}

$csrfToken = generateCSRFToken();
include_once __DIR__ . '/../header.php';
?>
<style>
        .ai-mcqs-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        .ai-mcqs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .ai-mcqs-header h1 {
            margin: 0;
        }
        .ai-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .ai-card {
            background: #fff;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .ai-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
        }
        .ai-card p {
            margin: 0.15rem 0;
            font-size: 0.9rem;
            color: #555;
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .topic-table-wrapper {
            max-height: 260px;
            overflow: auto;
            margin-bottom: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            background: #fff;
        }
        .topic-table {
            width: 100%;
            border-collapse: collapse;
        }
        .topic-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: top;
            width: 33.33%;
        }
        .topic-item {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 0.5rem 0.75rem;
            transition: border-color 0.2s, background-color 0.2s;
        }
        .topic-item.is-recommended {
            border-color: #e0a800;
            background: #fffdf2;
        }
        .topic-item-header {
            min-height: 48px;
        }
        .topic-item-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .topic-item-count {
            font-size: 0.85rem;
            color: #555;
        }
        .topic-recommendation-option {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin-top: 0.55rem;
            padding-top: 0.5rem;
            border-top: 1px solid #ececec;
            font-size: 0.84rem;
            font-weight: 600;
            color: #5f4b00;
            cursor: pointer;
        }
        .topic-search-bar {
            margin-bottom: 0.5rem;
        }
        .topic-search-bar input {
            width: 100%;
            max-width: 320px;
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
        }
        .topic-link {
            color: #007bff;
            text-decoration: none;
        }
        .topic-link:hover {
            text-decoration: underline;
        }
        .mcq-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .mcq-table th,
        .mcq-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e0e0e0;
            text-align: left;
            vertical-align: top;
            font-size: 0.9rem;
        }
        .mcq-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .mcq-options-display {
            margin-top: 4px;
            padding: 6px 8px;
            background-color: #f5f5f5;
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }
        .mcq-options-display div {
            margin-bottom: 2px;
        }
        .mcq-options-display strong {
            display: inline-block;
            width: 20px;
            color: #007bff;
        }
        .mcq-correct {
            font-weight: bold;
            color: #28a745;
            text-align: center;
        }
        .ai-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .ai-actions form {
            display: inline-block;
        }
        .btn {
            padding: 0.35rem 0.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .btn-edit {
            background: #007bff;
            color: #fff;
        }
        .btn-delete {
            background: #dc3545;
            color: #fff;
        }
        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }
        .filter-form {
            margin-bottom: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .filter-form select,
        .filter-form input {
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
        }
        .recommendation-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 1rem;
            padding: 0.75rem 1rem;
            background: #fff8e1;
            border: 1px solid #ffe08a;
            border-radius: 8px;
        }
        .recommendation-toolbar p {
            flex: 1 1 360px;
            margin: 0;
            color: #5f4b00;
            font-size: 0.9rem;
        }
        .recommendation-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #e0a800;
        }
        .btn-recommendation {
            background: #e0a800;
            color: #212529;
            font-weight: 600;
        }
        .recommendation-manager {
            position: fixed;
            inset: 0;
            z-index: 11000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.68);
        }
        .recommendation-manager.is-open {
            display: flex;
        }
        .recommendation-manager-dialog {
            width: min(1100px, 96vw);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 24px 70px rgba(0,0,0,0.28);
            overflow: hidden;
        }
        .recommendation-manager-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .recommendation-manager-header h2 {
            margin: 0;
            font-size: 1.25rem;
        }
        .recommendation-manager-body {
            padding: 1rem 1.25rem 1.25rem;
            overflow-y: auto;
        }
        .recommendation-manager-close {
            border: 0;
            background: transparent;
            font-size: 1.75rem;
            line-height: 1;
            cursor: pointer;
            color: #4b5563;
        }
        .selected-topic-card {
            border: 1px solid #dfe3e8;
            border-radius: 10px;
            margin-bottom: 0.8rem;
            overflow: hidden;
        }
        .selected-topic-summary {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            padding: 0.8rem 1rem;
            background: #f8fafc;
        }
        .selected-topic-info {
            flex: 1 1 300px;
        }
        .selected-topic-name {
            font-weight: 700;
            color: #1f2937;
        }
        .selected-topic-meta {
            margin-top: 0.2rem;
            font-size: 0.82rem;
            color: #6b7280;
        }
        .selected-topic-details {
            display: none;
            padding: 0.85rem 1rem;
            border-top: 1px solid #e5e7eb;
        }
        .selected-topic-details.is-open {
            display: block;
        }
        .recommendation-mcq {
            margin-bottom: 0.8rem;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
        }
        .recommendation-mcq:last-child {
            margin-bottom: 0;
        }
        .recommendation-mcq-question {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .recommendation-mcq-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.3rem 1rem;
            font-size: 0.86rem;
        }
        .recommendation-mcq-answer,
        .recommendation-mcq-explanation {
            margin-top: 0.5rem;
            font-size: 0.86rem;
        }
        .recommendation-empty,
        .recommendation-loading,
        .recommendation-error {
            padding: 2rem 1rem;
            text-align: center;
            color: #6b7280;
        }
        .recommendation-error {
            color: #b91c1c;
        }
        .pagination {
            margin: 1rem 0;
            text-align: center;
        }
        .pagination a {
            display: inline-block;
            margin: 0 3px;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            border: 1px solid #007bff;
            color: #007bff;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .pagination a.active,
        .pagination a:hover {
            background: #007bff;
            color: #fff;
        }
        .edit-row {
            background: #fafafa;
        }
        .edit-row form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
        }
        .edit-row textarea {
            min-height: 80px;
            resize: vertical;
        }
        .topic-rename-card {
            background: #fff;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .topic-rename-card h3 {
            margin-top: 0;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }
        .topic-rename-form {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .topic-rename-form input {
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
            min-width: 160px;
        }
        .btn-primary {
            background: #007bff;
            color: #fff;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pending { background: #e9ecef; color: #495057; }
        .status-verified { background: #d4edda; color: #155724; }
        .status-corrected { background: #cce5ff; color: #004085; }
        .status-flagged { background: #f8d7da; color: #721c24; }
 
        .ai-loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }
        .ai-loader-box {
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .ai-loader-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .ai-progress-container {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin: 1rem 0;
            overflow: hidden;
        }
        .ai-progress-bar {
            height: 100%;
            background: #007bff;
            width: 0%;
            transition: width 0.3s;
        }
        .ai-loader-details {
            display: flex;
            justify-content: space-around;
            margin-top: 1rem;
        }
        .ai-stat-item {
            font-size: 0.8rem;
        }
        .ai-stat-item strong {
            display: block;
            font-size: 1.1rem;
        }
        .btn-check-ai {
            background: #28a745;
            color: #fff;
            padding: 4px 10px;
            font-size: 0.8rem;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-check-ai:hover {
            background: #218838;
            color: #fff;
        }
        .btn-check-ai.loading {
            background: #6c757d;
            cursor: not-allowed;
            pointer-events: none;
        }
        .spinner-sm {
            width: 12px;
            height: 12px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: none;
        }
        .btn-check-ai.loading .spinner-sm {
            display: inline-block;
        }

        @media (max-width: 768px) {
            .ai-mcqs-container {
                padding: 1rem;
            }

            .ai-mcqs-header,
            .topic-rename-form,
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .ai-mcqs-header h1 {
                font-size: 1.6rem;
                line-height: 1.2;
            }

            .ai-summary {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .topic-search-bar input,
            .topic-rename-form input,
            .filter-form select,
            .filter-form input,
            .filter-form button,
            .btn {
                width: 100%;
            }

            .recommendation-toolbar {
                align-items: stretch;
            }

            .recommendation-mcq-options {
                grid-template-columns: 1fr;
            }

            .topic-table-wrapper {
                overflow: auto;
                -webkit-overflow-scrolling: touch;
            }

            .topic-table,
            .mcq-table {
                display: block;
                max-width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .mcq-table th,
            .mcq-table td {
                padding: 0.65rem;
                font-size: 0.85rem;
            }

            .ai-actions {
                flex-direction: column;
            }

            .ai-actions form {
                width: 100%;
            }

            .edit-row form {
                grid-template-columns: 1fr;
            }

            .ai-loader-box {
                padding: 1.25rem;
            }
        }
        /* Responsive admin workspace refresh */
        .ai-mcqs-container {
            --ai-ink: #17283b;
            --ai-muted: #6b7c90;
            --ai-line: #e3eaf0;
            --ai-canvas: #f4f7fa;
            --ai-navy: #173b63;
            --ai-teal: #168c83;
            --ai-amber: #c97832;
            max-width: 1560px;
            padding: 1rem clamp(.75rem, 2vw, 2rem) 4rem;
            color: var(--ai-ink);
            background: var(--ai-canvas);
        }
        .ai-mcqs-container .nav { margin-bottom: 1rem; }
        .ai-mcqs-container .nav a {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            min-height: 38px;
            padding: .45rem .75rem;
            border: 1px solid #d8e3eb;
            border-radius: 9px;
            color: var(--ai-navy);
            background: #fff;
            font-size: .86rem;
            font-weight: 700;
            text-decoration: none;
        }
        .ai-mcqs-container .nav a:hover { border-color: #9ccfc6; color: #116e67; background: #f4fbf9; }
        .ai-mcqs-header {
            position: relative;
            overflow: hidden;
            margin-bottom: 1.35rem;
            padding: clamp(1.4rem, 3vw, 2.3rem);
            border-radius: 22px;
            color: #fff;
            background:
                radial-gradient(circle at 91% 8%, rgba(84, 190, 171, .28), transparent 28%),
                linear-gradient(120deg, #122d4a 0%, #1b4868 58%, #1e6962 100%);
            box-shadow: 0 18px 38px rgba(25, 60, 84, .16);
        }
        .ai-mcqs-header::after {
            content: '';
            position: absolute;
            right: -55px;
            bottom: -125px;
            width: 280px;
            height: 280px;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 50%;
            box-shadow: 0 0 0 24px rgba(255,255,255,.035), 0 0 0 48px rgba(255,255,255,.025);
            pointer-events: none;
        }
        .ai-mcqs-header h1 { position: relative; z-index: 1; color: #fff; font-size: clamp(1.65rem, 3vw, 2.55rem); font-weight: 800; letter-spacing: -.035em; }
        .ai-summary { grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: .9rem; margin-bottom: 1.5rem; }
        .ai-card, .topic-rename-card {
            position: relative;
            border: 1px solid var(--ai-line);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 20px rgba(28,54,76,.055);
        }
        .ai-card { padding: 1.15rem 1.25rem; }
        .ai-card::before, .topic-rename-card::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; border-radius: 16px 0 0 16px; background: var(--ai-teal); }
        .ai-card h3, .topic-rename-card h3 { color: var(--ai-ink); font-size: 1rem; font-weight: 800; }
        .ai-card p { color: var(--ai-muted); }
        .ai-card strong { color: var(--ai-ink); }
        .topic-rename-card { padding: 1.15rem 1.25rem; }
        .topic-rename-form { align-items: stretch; }
        .topic-rename-form label { display: grid; gap: .35rem; min-width: 0; color: var(--ai-muted); font-size: .75rem; font-weight: 800; }
        .topic-rename-form input, .filter-form input, .filter-form select, .topic-search-bar input {
            min-height: 42px;
            border: 1px solid #d5e0e8;
            border-radius: 8px;
            color: var(--ai-ink);
            background: #fff;
        }
        .topic-rename-form input:focus, .filter-form input:focus, .filter-form select:focus, .topic-search-bar input:focus { outline: 0; border-color: var(--ai-teal); box-shadow: 0 0 0 .2rem rgba(22,140,131,.13); }
        .recommendation-toolbar { margin-bottom: .8rem; padding: .9rem 1rem; border-color: #f0d28f; border-radius: 12px; background: #fff9e9; }
        .recommendation-toolbar p { color: #695300; line-height: 1.55; }
        .recommendation-toolbar .btn { min-height: 40px; }
        .btn-primary { border-color: var(--ai-teal); background: var(--ai-teal); }
        .btn-primary:hover { border-color: #11766f; background: #11766f; }
        .btn-secondary { border-color: #74869a; background: #61758a; }
        .btn-secondary:hover { background: #506477; }
        .topic-table-wrapper { max-height: 330px; margin-bottom: 2.25rem; border: 1px solid var(--ai-line); border-radius: 13px; box-shadow: 0 8px 20px rgba(28,54,76,.045); }
        .topic-table td { padding: .75rem; border-color: #edf1f4; }
        .topic-item { min-height: 92px; padding: .75rem .85rem; border-color: #dfe8ee; border-radius: 10px; background: #fff; }
        .topic-item:hover { border-color: #9ed7cf; background: #f8fdfc; }
        .topic-item.is-recommended { border-color: #e2b94d; background: #fffaf0; }
        .topic-item-name { color: var(--ai-ink); line-height: 1.4; }
        .topic-item-count { color: var(--ai-muted); }
        .topic-recommendation-option { color: #6f5a0a; }
        .topic-search-bar input { max-width: 420px; padding: .5rem .75rem; }
        .ai-mcqs-container > h2 { margin: 2rem 0 .85rem; color: var(--ai-ink); font-size: 1.45rem; font-weight: 800; letter-spacing: -.02em; }
        .filter-form { margin-bottom: 1rem; padding: .9rem 1rem; border: 1px solid var(--ai-line); border-radius: 13px; background: #fff; box-shadow: 0 8px 20px rgba(28,54,76,.045); }
        .filter-form label { display: grid; gap: .35rem; color: var(--ai-muted); font-size: .75rem; font-weight: 800; }
        .filter-form .btn { min-height: 42px; }
        .mcq-table { min-width: 1180px; border: 1px solid var(--ai-line); border-radius: 14px; box-shadow: 0 10px 24px rgba(28,54,76,.06); }
        .mcq-table th { padding: .9rem 1rem; border-color: var(--ai-line); color: #718297; background: #f7f9fb; font-size: .68rem; letter-spacing: .1em; text-transform: uppercase; white-space: nowrap; }
        .mcq-table td { padding: 1rem; border-color: #edf1f4; color: var(--ai-ink); line-height: 1.55; }
        .mcq-table tbody tr:hover:not(.edit-row) { background: #fbfdfd; }
        .mcq-options-display { padding: .65rem .75rem; border-left: 3px solid var(--ai-teal); border-radius: 8px; background: #f1f7f7; }
        .mcq-options-display strong { color: var(--ai-teal); }
        .mcq-correct { color: #16816e; font-size: 1rem; font-weight: 800; }
        .status-badge { padding: .35rem .55rem; border-radius: 999px; font-size: .7rem; letter-spacing: .02em; }
        .status-pending { color: #705b16; background: #fff2c7; }
        .status-verified { color: #146c4b; background: #dff6e9; }
        .status-corrected { color: #245d86; background: #e0f0fb; }
        .status-flagged { color: #963f49; background: #fde5e7; }
        .btn-check-ai { min-height: 34px; border-radius: 7px; background: #19866e; }
        .btn-check-ai:hover { background: #126a59; }
        .ai-actions { align-items: stretch; }
        .ai-actions .btn { min-height: 36px; }
        .edit-row { background: #f7fafb; }
        .edit-row form { gap: .85rem; padding: .75rem; }
        .edit-row form label { display: block; margin-bottom: .35rem; color: var(--ai-muted); font-size: .75rem; font-weight: 800; }
        .edit-row form input, .edit-row form textarea { box-sizing: border-box; width: 100% !important; min-height: 42px; padding: .55rem .7rem; border: 1px solid #d5e0e8; border-radius: 8px; }
        .edit-row form textarea { min-height: 92px; }
        .recommendation-manager-dialog { width: min(1100px, calc(100vw - 2rem)); border-radius: 16px; }
        .recommendation-manager-header, .recommendation-manager-body { padding-inline: clamp(1rem, 3vw, 1.5rem); }
        .ai-mcqs-container .alert-error { border: 1px solid #f0b9c0; border-radius: 10px; }

        @media (max-width: 800px) {
            .ai-mcqs-container { padding: .75rem .7rem 2.5rem; }
            .ai-mcqs-header { padding: 1.3rem; border-radius: 17px; }
            .ai-summary { grid-template-columns: 1fr; gap: .75rem; }
            .recommendation-toolbar, .topic-rename-form, .filter-form { align-items: stretch; }
            .recommendation-toolbar .btn, .topic-rename-form .btn, .filter-form .btn { width: 100%; }
            .topic-rename-form label { width: 100%; }
            .topic-rename-form input, .filter-form input, .filter-form select { width: 100%; }
            .topic-table-wrapper { max-height: none; overflow: visible; border: 0; box-shadow: none; }
            .topic-table { display: block; }
            .topic-table tbody, .topic-table tr, .topic-table td { display: block; width: 100%; }
            .topic-table tr { margin-bottom: .7rem; padding: .35rem .4rem; border: 1px solid var(--ai-line); border-radius: 12px; background: #fff; }
            .topic-table td { width: 100%; padding: .35rem; border: 0; }
            .topic-table td:empty { display: none; }
            .topic-item { min-height: 0; }
            .recommendation-mcq-options { grid-template-columns: 1fr; }
            .mcq-table { display: block; min-width: 0; overflow: visible; border: 0; box-shadow: none; background: transparent; }
            .mcq-table thead { display: none; }
            .mcq-table tbody, .mcq-table tbody tr:not(.edit-row), .mcq-table tbody tr:not(.edit-row) td { display: block; width: 100%; }
            .mcq-table tbody tr:not(.edit-row) { margin-bottom: .8rem; padding: .2rem 1rem; border: 1px solid var(--ai-line); border-radius: 13px; background: #fff; box-shadow: 0 6px 16px rgba(28,54,76,.045); }
            .mcq-table tbody tr:not(.edit-row) td { display: grid; grid-template-columns: 6.7rem minmax(0, 1fr); gap: .75rem; align-items: start; padding: .75rem 0; border-bottom: 1px solid #edf1f4; text-align: left !important; }
            .mcq-table tbody tr:not(.edit-row) td:last-child { border-bottom: 0; }
            .mcq-table tbody tr:not(.edit-row) td::before { color: #7b8b9b; content: ''; font-size: .64rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
            .mcq-table tbody tr:not(.edit-row) td:nth-child(1)::before { content: 'ID'; }
            .mcq-table tbody tr:not(.edit-row) td:nth-child(2)::before { content: 'Topic'; }
            .mcq-table tbody tr:not(.edit-row) td:nth-child(3)::before { content: 'Question'; }
            .mcq-table tbody tr:not(.edit-row) td:nth-child(4)::before { content: 'Options'; }
            .mcq-table tbody tr:not(.edit-row) td:nth-child(5)::before { content: 'Explanation'; }
            .mcq-table tbody tr:not(.edit-row) td:nth-child(6)::before { content: 'Correct'; }
            .mcq-table tbody tr:not(.edit-row) td:nth-child(7)::before { content: 'Status'; }
            .mcq-table tbody tr:not(.edit-row) td:nth-child(8)::before { content: 'Checked'; }
            .mcq-table tbody tr:not(.edit-row) td:nth-child(9)::before { content: 'Generated'; }
            .mcq-table tbody tr:not(.edit-row) td:nth-child(10)::before { content: 'Actions'; }
            .mcq-table tbody tr:not(.edit-row) td[colspan] { display: block; padding: 1.25rem .25rem; text-align: center !important; }
            .mcq-table tbody tr:not(.edit-row) td[colspan]::before { display: none; }
            .mcq-table tbody tr.edit-row { display: none !important; margin-bottom: .8rem; border: 1px solid var(--ai-line); border-radius: 13px; background: #fff; }
            .mcq-table tbody tr.edit-row[style*="table-row"] { display: block !important; }
            .mcq-table tbody tr.edit-row td { display: block; padding: .75rem; }
            .mcq-table tbody tr.edit-row form { grid-template-columns: 1fr; }
            .mcq-options-display { min-width: 0; }
            .ai-actions { flex-direction: row; flex-wrap: wrap; }
            .ai-actions form { width: auto; }
            .ai-actions .btn { width: auto; }
            .recommendation-manager { padding: .6rem; }
            .recommendation-manager-dialog { width: 100%; max-height: 94vh; }
            .recommendation-manager-header { align-items: flex-start; }
            .recommendation-mcq-options { grid-template-columns: 1fr; }
        }

        @media (prefers-reduced-motion: reduce) {
            .ai-mcqs-container *, .ai-mcqs-container *::before, .ai-mcqs-container *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; }
        }
    </style>


<style id="ai-mcqs-table-responsive-overrides">
    .ai-mcqs-container .table-responsive { width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .ai-mcqs-container table { width: 100%; max-width: 100%; table-layout: fixed; }
    .ai-mcqs-container table th,
    .ai-mcqs-container table td { min-width: 0; max-width: 100%; white-space: normal; overflow-wrap: anywhere; word-break: break-word; vertical-align: top; }
    .ai-mcqs-container table td > *,
    .ai-mcqs-container table td a,
    .ai-mcqs-container table td button,
    .ai-mcqs-container table td input,
    .ai-mcqs-container table td select,
    .ai-mcqs-container table td textarea { min-width: 0; max-width: 100%; }
    .ai-mcqs-container table td textarea,
    .ai-mcqs-container table td input,
    .ai-mcqs-container table td select { width: 100%; }
    .ai-mcqs-container table td pre,
    .ai-mcqs-container table td code { max-width: 100%; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; }

    @media (max-width: 800px) {
        .ai-mcqs-container .table-responsive { overflow-x: visible; }
        .ai-mcqs-container table { display: block; min-width: 0 !important; }
        .ai-mcqs-container table thead { display: none; }
        .ai-mcqs-container table tbody,
        .ai-mcqs-container table tbody tr { display: block; width: 100%; }
        .ai-mcqs-container table tbody tr { margin: 0 0 .9rem; padding: .8rem; border: 1px solid #dbe5ec; border-radius: 1rem; background: #fff; box-shadow: 0 8px 22px rgba(24, 49, 71, .07); }
        .ai-mcqs-container table tbody td { display: grid; grid-template-columns: minmax(6.25rem, 8.25rem) minmax(0, 1fr); align-items: start; gap: .65rem; width: 100%; min-width: 0; padding: .65rem .25rem; border: 0; overflow: visible; }
        .ai-mcqs-container table tbody td::before { min-width: 0; color: #617487; font-size: .68rem; font-weight: 800; letter-spacing: .08em; line-height: 1.35; text-transform: uppercase; }
        .ai-mcqs-container table tbody td:last-child { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .ai-mcqs-container table tbody td:last-child::before { flex: 0 0 100%; }
        .ai-mcqs-container table tbody td a,
        .ai-mcqs-container table tbody td button { white-space: normal; overflow-wrap: anywhere; }
        .ai-mcqs-container table tbody td .btn,
        .ai-mcqs-container table tbody td .action-btn,
        .ai-mcqs-container table tbody td [class*="action"] { max-width: 100%; min-height: 2.25rem; }
        .ai-mcqs-container table tbody tr[style*="table-row"] { display: block !important; }
    }
<style id="ai-mcqs-theme-overrides">
    .ai-mcqs-container {
        --ai-ink: #2d2033;
        --ai-muted: #705f6d;
        --ai-canvas: #faf6f4;
        --ai-panel: #ffffff;
        --ai-border: #eadbdf;
        --ai-accent: #c45168;
        --ai-accent-dark: #93364d;
        --ai-accent-soft: #f8e5e9;
        --ai-warm: #c58a35;
        color: var(--ai-ink);
        background: var(--ai-canvas);
    }

    .ai-mcqs-container > h1,
    .ai-mcqs-container > h2,
    .ai-mcqs-container > h3,
    .ai-mcqs-container .section-title,
    .ai-mcqs-container .card-title {
        color: var(--ai-ink) !important;
    }

    .ai-mcqs-container .ai-mcqs-hero,
    .ai-mcqs-container .page-hero,
    .ai-mcqs-container .hero-section {
        background: linear-gradient(135deg, #3b263d 0%, #6e3048 55%, #a8455c 100%) !important;
        border-color: #6e3048 !important;
    }

    .ai-mcqs-container .btn-primary,
    .ai-mcqs-container .btn-success,
    .ai-mcqs-container button[type="submit"] {
        color: #fff !important;
        background: var(--ai-accent) !important;
        border-color: var(--ai-accent) !important;
    }

    .ai-mcqs-container .btn-primary:hover,
    .ai-mcqs-container .btn-primary:focus-visible,
    .ai-mcqs-container .btn-success:hover,
    .ai-mcqs-container button[type="submit"]:hover {
        background: var(--ai-accent-dark) !important;
        border-color: var(--ai-accent-dark) !important;
    }

    .ai-mcqs-container .btn-info,
    .ai-mcqs-container .btn-success,
    .ai-mcqs-container button.btn-primary,
    .ai-mcqs-container a.btn-primary,
    .ai-mcqs-container button[type="submit"] {
        color: #fff !important;
        background: var(--ai-accent) !important;
        border-color: var(--ai-accent) !important;
    }

    .ai-mcqs-container .btn-info:hover,
    .ai-mcqs-container .btn-info:focus-visible,
    .ai-mcqs-container .btn-success:hover,
    .ai-mcqs-container .btn-success:focus-visible,
    .ai-mcqs-container button.btn-primary:hover,
    .ai-mcqs-container button.btn-primary:focus-visible,
    .ai-mcqs-container a.btn-primary:hover,
    .ai-mcqs-container a.btn-primary:focus-visible,
    .ai-mcqs-container button[type="submit"]:hover,
    .ai-mcqs-container button[type="submit"]:focus-visible {
        color: #fff !important;
        background: var(--ai-accent-dark) !important;
        border-color: var(--ai-accent-dark) !important;
    }

    .ai-mcqs-container .btn-outline-primary {
        color: var(--ai-accent-dark) !important;
        background: #fff !important;
        border-color: var(--ai-accent) !important;
    }

    .ai-mcqs-container .btn-outline-primary:hover,
    .ai-mcqs-container .btn-outline-primary:focus-visible {
        color: #fff !important;
        background: var(--ai-accent) !important;
        border-color: var(--ai-accent) !important;
    }

    .ai-mcqs-container .btn-secondary,
    .ai-mcqs-container .btn-outline-secondary,
    .ai-mcqs-container .btn-outline-primary {
        color: var(--ai-accent-dark) !important;
        background: #fff !important;
        border-color: #d9aab5 !important;
    }

    .ai-mcqs-container .btn-secondary:hover,
    .ai-mcqs-container .btn-outline-secondary:hover,
    .ai-mcqs-container .btn-outline-primary:hover {
        color: #fff !important;
        background: var(--ai-accent-dark) !important;
        border-color: var(--ai-accent-dark) !important;
    }

    .ai-mcqs-container .badge-primary,
    .ai-mcqs-container .badge-info,
    .ai-mcqs-container .badge.bg-primary,
    .ai-mcqs-container .badge.bg-info,
    .ai-mcqs-container .status-badge {
        color: var(--ai-accent-dark) !important;
        background: var(--ai-accent-soft) !important;
        border-color: #e6b6c1 !important;
    }

    .ai-mcqs-container .badge-warning,
    .ai-mcqs-container .badge.bg-warning {
        color: #684518 !important;
        background: #f7e8c9 !important;
        border-color: #e6c889 !important;
    }

    .ai-mcqs-container input:focus,
    .ai-mcqs-container select:focus,
    .ai-mcqs-container textarea:focus,
    .ai-mcqs-container .form-control:focus {
        border-color: var(--ai-accent) !important;
        box-shadow: 0 0 0 .2rem rgba(196, 81, 104, .18) !important;
    }

    .ai-mcqs-container table thead th {
        color: #674153 !important;
        background: #fbf0f1 !important;
        border-color: var(--ai-border) !important;
    }

    .ai-mcqs-container table tbody tr:hover {
        background: #fff7f7 !important;
    }

    .ai-mcqs-container a {
        color: var(--ai-accent-dark);
    }

    .ai-mcqs-container a:hover {
        color: var(--ai-accent);
    }
</style>
<style id="ai-mcqs-horizontal-table-overrides">
    .ai-mcqs-container .table-responsive {
        width: 100%;
        max-width: 100%;
        overflow-x: auto !important;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-color: #b8c8d5 #f3f6f8;
        scrollbar-width: thin;
    }

    .ai-mcqs-container .table-responsive:focus-visible,
    .ai-mcqs-container .topic-table-wrapper:focus-visible {
        outline: 3px solid rgba(22, 140, 131, .28);
        outline-offset: 3px;
    }

    .ai-mcqs-container .mcq-table-scroll {
        border: 1px solid var(--ai-line);
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 10px 24px rgba(28, 54, 76, .06);
    }

    .ai-mcqs-container .mcq-table-scroll > .mcq-table {
        width: 100%;
        min-width: 1180px;
        max-width: none;
        margin: 0;
        table-layout: fixed;
    }

    .ai-mcqs-container .mcq-table th,
    .ai-mcqs-container .mcq-table td {
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: normal;
    }

    .ai-mcqs-container .mcq-table th:nth-child(1),
    .ai-mcqs-container .mcq-table td:nth-child(1) { width: 64px; }
    .ai-mcqs-container .mcq-table th:nth-child(2),
    .ai-mcqs-container .mcq-table td:nth-child(2) { width: 180px; }
    .ai-mcqs-container .mcq-table th:nth-child(3),
    .ai-mcqs-container .mcq-table td:nth-child(3) { width: 280px; }
    .ai-mcqs-container .mcq-table th:nth-child(4),
    .ai-mcqs-container .mcq-table td:nth-child(4) { width: 300px; }
    .ai-mcqs-container .mcq-table th:nth-child(5),
    .ai-mcqs-container .mcq-table td:nth-child(5) { width: 220px; }
    .ai-mcqs-container .mcq-table th:nth-child(6),
    .ai-mcqs-container .mcq-table td:nth-child(6) { width: 86px; }
    .ai-mcqs-container .mcq-table th:nth-child(7),
    .ai-mcqs-container .mcq-table td:nth-child(7) { width: 112px; }
    .ai-mcqs-container .mcq-table th:nth-child(8),
    .ai-mcqs-container .mcq-table td:nth-child(8) { width: 170px; }
    .ai-mcqs-container .mcq-table th:nth-child(9),
    .ai-mcqs-container .mcq-table td:nth-child(9) { width: 170px; }
    .ai-mcqs-container .mcq-table th:nth-child(10),
    .ai-mcqs-container .mcq-table td:nth-child(10) { width: 150px; }

    .ai-mcqs-container .mcq-table td:nth-child(1),
    .ai-mcqs-container .mcq-table td:nth-child(6),
    .ai-mcqs-container .mcq-table td:nth-child(7),
    .ai-mcqs-container .mcq-table td:nth-child(9) {
        font-variant-numeric: tabular-nums;
    }

    .ai-mcqs-container .topic-table-wrapper {
        overflow-x: auto;
        overflow-y: auto;
        scrollbar-color: #b8c8d5 #f3f6f8;
        scrollbar-width: thin;
    }

    @media (max-width: 800px) {
        .ai-mcqs-container table {
            display: table !important;
            width: max-content !important;
            max-width: none !important;
            min-width: 980px !important;
            table-layout: auto !important;
        }

        .ai-mcqs-container table thead {
            display: table-header-group !important;
        }

        .ai-mcqs-container table tbody {
            display: table-row-group !important;
        }

        .ai-mcqs-container table tbody tr {
            display: table-row !important;
            width: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .ai-mcqs-container table th,
        .ai-mcqs-container table td {
            display: table-cell !important;
            width: auto !important;
            min-width: 0;
            padding: .75rem .8rem !important;
            white-space: normal;
            vertical-align: top;
        }

        .ai-mcqs-container table td::before {
            content: none !important;
            display: none !important;
        }

        .ai-mcqs-container table td:last-child {
            display: table-cell !important;
            white-space: nowrap;
        }

        .ai-mcqs-container table td a,
        .ai-mcqs-container table td button {
            white-space: nowrap;
        }

        .ai-mcqs-container .topic-table-wrapper {
            max-height: 330px;
            overflow-x: auto;
            overflow-y: auto;
            border: 1px solid var(--ai-line);
            box-shadow: 0 8px 20px rgba(28, 54, 76, .045);
        }

        .ai-mcqs-container .topic-table {
            display: table !important;
            width: max-content !important;
            max-width: none !important;
            min-width: 720px !important;
            table-layout: fixed;
        }

        .ai-mcqs-container .topic-table tbody,
        .ai-mcqs-container .topic-table tr {
            display: table-row-group !important;
        }

        .ai-mcqs-container .topic-table tr {
            display: table-row !important;
        }

        .ai-mcqs-container .topic-table td {
            display: table-cell !important;
            width: 240px !important;
            min-width: 240px;
            padding: .75rem !important;
            border: 0;
            vertical-align: top;
        }

        .ai-mcqs-container .topic-table td:empty {
            display: table-cell !important;
        }
    }
</style>
<div class="ai-mcqs-container">
        <div class="nav">
            <a href="../dashboard.php">← Back to Dashboard</a>
        </div>

        <div class="ai-mcqs-header">
            <h1>Manage AI Generated MCQs</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="ai-summary">
            <div class="ai-card">
                <h3>AI Generated MCQs</h3>
                <p><strong>Total:</strong> <?= (int)$overallTotal ?></p>
                <p><strong>Topics:</strong> <?= count($topicCounts) ?></p>
                <p><strong>Recommended pool:</strong> <?= (int)$recommendedTotal ?></p>
            </div>
            <?php if ($topicFilter !== ''): ?>
            <div class="ai-card">
                <h3>Current Topic</h3>
                <p><strong>Topic:</strong> <?= htmlspecialchars($topicFilter) ?></p>
                <p><strong>MCQs:</strong> <?= (int)$totalFiltered ?></p>
                <p>
                    <a href="manage_ai_mcqs.php" class="topic-link">Clear topic filter</a>
                </p>
            </div>
            <?php endif; ?>
            <div class="ai-card">
                <h3>AI Verification</h3>
                <div class="d-grid gap-2">
                    <a href="verify_ai_mcqs.php" class="btn btn-primary"><i class="fas fa-robot me-2"></i>Open Verification Center</a>
                    <a href="verify_ai_mcqs.php?tab=report" class="btn btn-outline-info"><i class="fas fa-list-check me-2"></i>View Verified/Corrected MCQs</a>
                </div>
                
                <hr style="margin: 15px 0;">
                
                <form action="verify_ai_mcqs.php" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="mode" value="range">
                    <div class="col-auto">
                        <input type="number" name="start" class="form-control form-control-sm" placeholder="Start ID" required>
                    </div>
                    <div class="col-auto">
                        <span class="text-muted">-</span>
                    </div>
                    <div class="col-auto">
                        <input type="number" name="end" class="form-control form-control-sm" placeholder="End ID" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Check Range</button>
                    </div>
                </form>
            </div>
            <div class="topic-rename-card">
                <h3>Search & Replace Topic Name</h3>
                <form method="POST" class="topic-rename-form" onsubmit="return confirm('Replace topic name for all matching AI MCQs?');">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="rename_topic">
                    <label>
                        Old Topic:
                        <input type="text" name="old_topic" required placeholder="Exact old topic">
                    </label>
                    <label>
                        New Topic:
                        <input type="text" name="new_topic" required placeholder="New topic name">
                    </label>
                    <button type="submit" class="btn btn-primary">Replace</button>
                </form>
            </div>
        </div>

        <h2>Topics Overview</h2>
        <?php if (empty($topicCounts)): ?>
            <p>No AI generated MCQs found.</p>
        <?php else: ?>
            <form method="POST" id="topicRecommendationsForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="save_topic_recommendations">

                <div class="recommendation-toolbar">
                    <p>
                        Choose any topics for the public recommendation pool. Three are selected
                        randomly whenever the topic-wise MCQs page loads.
                    </p>
                    <button type="button" class="btn btn-secondary" onclick="setTopicRecommendations(true)">Select all</button>
                    <button type="button" class="btn btn-secondary" onclick="setTopicRecommendations(false)">Clear all</button>
                    <button type="submit" class="btn btn-recommendation">Save recommendations</button>
                    <button type="button" class="btn btn-primary" onclick="openRecommendationManager()">
                        View selected (<span id="recommendedTopicCount"><?= (int)$recommendedTotal ?></span>)
                    </button>
                </div>

                <div class="topic-search-bar">
                    <input type="text" id="topicSearchInput" placeholder="Search topics...">
                </div>
                <div class="topic-table-wrapper">
                    <table class="topic-table" id="topicTable">
                        <tbody>
                            <?php
                                $colsPerRow = 3;
                                $totalTopics = count($topicCounts);
                                for ($i = 0; $i < $totalTopics; $i += $colsPerRow):
                            ?>
                                <tr>
                                    <?php for ($j = 0; $j < $colsPerRow; $j++):
                                        $index = $i + $j;
                                    ?>
                                        <td>
                                            <?php if ($index < $totalTopics):
                                                $row = $topicCounts[$index];
                                            ?>
                                                <div class="topic-item <?= !empty($row['is_recommended']) ? 'is-recommended' : '' ?>">
                                                    <div class="topic-item-header">
                                                        <a class="topic-link" href="?<?= http_build_query(array_merge($_GET, ['topic' => $row['topic'], 'page' => 1])) ?>">
                                                            <div class="topic-item-name"><?= htmlspecialchars($row['topic']) ?></div>
                                                        </a>
                                                        <div class="topic-item-count">MCQs: <?= (int)$row['total'] ?></div>
                                                    </div>
                                                    <label class="topic-recommendation-option">
                                                        <input
                                                            type="checkbox"
                                                            class="recommendation-checkbox"
                                                            name="recommended_topics[]"
                                                            value="<?= htmlspecialchars($row['topic']) ?>"
                                                            <?= !empty($row['is_recommended']) ? 'checked' : '' ?>
                                                            <?= !$recommendationsReady ? 'disabled' : '' ?>
                                                        >
                                                        Add to recommendations
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php if (!$recommendationsReady): ?>
                <div class="alert alert-error">Recommendation selection is unavailable because its database table could not be initialized.</div>
            <?php endif; ?>
        <?php endif; ?>

        <h2>AI MCQs List</h2>

        <form method="GET" class="filter-form">
            <label>
                Topic:
                <input type="text" name="topic" value="<?= htmlspecialchars($topicFilter) ?>" placeholder="Filter by topic (supports partial match)">
            </label>
            <label>
                Per page:
                <select name="per_page">
                    <option value="10" <?= $perPageParam == '10' ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $perPageParam == '20' ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $perPageParam == '50' ? 'selected' : '' ?>>50</option>
                    <option value="all" <?= $perPageParam === 'all' ? 'selected' : '' ?>>View All</option>
                </select>
            </label>
            <button type="submit" class="btn btn-secondary">Apply</button>
            <?php if ($topicFilter !== '' || $perPageParam !== '20' || $page !== 1): ?>
                <a href="manage_ai_mcqs.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>

        <div class="table-responsive mcq-table-scroll" role="region" aria-label="AI MCQs table. Scroll horizontally to view all columns." tabindex="0">
        <table class="mcq-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Topic</th>
                    <th>Question</th>
                    <th>Options</th>
                    <th>Explanation</th>
                    <th>Correct</th>
                    <th>Status</th>
                    <th>Last Checked</th>
                    <th>Generated At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mcqs)): ?>
                    <tr>
                        <td colspan="10">No AI MCQs found for the selected filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($mcqs as $mcq): ?>
                        <?php
                            $coText = trim($mcq['correct_option'] ?? '');
                            $label = '';
                            if ($coText !== '') {
                                if (strcasecmp($coText, $mcq['option_a'] ?? '') === 0) $label = 'A';
                                elseif (strcasecmp($coText, $mcq['option_b'] ?? '') === 0) $label = 'B';
                                elseif (strcasecmp($coText, $mcq['option_c'] ?? '') === 0) $label = 'C';
                                elseif (strcasecmp($coText, $mcq['option_d'] ?? '') === 0) $label = 'D';
                                // Handle cases where the text might be just 'A', 'B', 'C', or 'D'
                                if ($label === '' && strlen($coText) === 1) {
                                    $possibleLabel = strtoupper($coText);
                                    if (in_array($possibleLabel, ['A', 'B', 'C', 'D'])) $label = $possibleLabel;
                                }
                            }
                        ?>
                        <tr>
                            <td><?= (int)$mcq['id'] ?></td>
                            <td><?= htmlspecialchars($mcq['topic'] ?? '') ?></td>
                            <td><?= htmlspecialchars($mcq['question']) ?></td>
                            <td>
                                <div class="mcq-options-display">
                                    <div><strong>A:</strong> <?= htmlspecialchars($mcq['option_a'] ?? '') ?></div>
                                    <div><strong>B:</strong> <?= htmlspecialchars($mcq['option_b'] ?? '') ?></div>
                                    <div><strong>C:</strong> <?= htmlspecialchars($mcq['option_c'] ?? '') ?></div>
                                    <div><strong>D:</strong> <?= htmlspecialchars($mcq['option_d'] ?? '') ?></div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($mcq['explanation'])): ?>
                                    <div style="font-style: italic; color: #555; font-size: 0.85rem; max-width: 250px;">
                                        <?= htmlspecialchars($mcq['explanation']) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.8rem;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="mcq-correct">
                                <?php if ($label !== ''): ?>
                                    <?= $label ?>
                                <?php else: ?>
                                    Not Set
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $status = $mcq['verification_status'] ?? 'pending';
                                    $badgeClass = 'status-pending';
                                    if ($status === 'verified') $badgeClass = 'status-verified';
                                    elseif ($status === 'corrected') $badgeClass = 'status-corrected';
                                    elseif ($status === 'flagged') $badgeClass = 'status-flagged';
                                ?>
                                <span class="status-badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span>
                            </td>
                            <td>
                                <?= $mcq['last_checked_at'] ? htmlspecialchars($mcq['last_checked_at']) : '-' ?>
                                <div style="margin-top: 8px;">
                                    <button onclick="checkSingleMCQ(<?= (int)$mcq['id'] ?>, this)" class="btn-check-ai" id="check-btn-<?= (int)$mcq['id'] ?>">
                                        <span class="spinner-sm"></span>
                                        <span class="btn-text"><?= $mcq['last_checked_at'] ? 'Recheck' : 'Check' ?></span>
                                    </button>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($mcq['generated_at']) ?></td>
                            <td>
                                <div class="ai-actions">
                                    <form method="POST" onsubmit="return confirm('Delete this AI MCQ?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$mcq['id'] ?>">
                                        <button type="submit" class="btn btn-delete">Delete</button>
                                    </form>
                                    <button type="button" class="btn btn-edit" onclick="document.getElementById('edit-ai-<?= (int)$mcq['id'] ?>').style.display='table-row'">
                                        Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr id="edit-ai-<?= (int)$mcq['id'] ?>" class="edit-row" style="display:none;">
                            <td colspan="10">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int)$mcq['id'] ?>">

                                    <div>
                                        <label>Topics (comma-separated)</label>
                                        <input type="text" name="topic" value="<?= htmlspecialchars($mcq['topic'] ?? '') ?>" style="width:100%;">
                                    </div>
                                    <div>
                                        <label>Question</label>
                                        <textarea name="question" style="width:100%;"><?= htmlspecialchars($mcq['question']) ?></textarea>
                                    </div>
                                    <div>
                                        <label>Option A</label>
                                        <input type="text" name="option_a" value="<?= htmlspecialchars($mcq['option_a'] ?? '') ?>" style="width:100%;">
                                    </div>
                                    <div>
                                        <label>Option B</label>
                                        <input type="text" name="option_b" value="<?= htmlspecialchars($mcq['option_b'] ?? '') ?>" style="width:100%;">
                                    </div>
                                    <div>
                                        <label>Option C</label>
                                        <input type="text" name="option_c" value="<?= htmlspecialchars($mcq['option_c'] ?? '') ?>" style="width:100%;">
                                    </div>
                                    <div>
                                        <label>Option D</label>
                                        <input type="text" name="option_d" value="<?= htmlspecialchars($mcq['option_d'] ?? '') ?>" style="width:100%;">
                                    </div>
                                    <?php
                                        $selA = ($label === 'A') ? 'selected' : '';
                                        $selB = ($label === 'B') ? 'selected' : '';
                                        $selC = ($label === 'C') ? 'selected' : '';
                                        $selD = ($label === 'D') ? 'selected' : '';
                                    ?>
                                    <div>
                                        <label>Correct Option</label>
                                        <select name="correct_option" style="width:100%;">
                                            <option value="">Select Correct Option</option>
                                            <option value="A" <?= $selA ?>>Option A</option>
                                            <option value="B" <?= $selB ?>>Option B</option>
                                            <option value="C" <?= $selC ?>>Option C</option>
                                            <option value="D" <?= $selD ?>>Option D</option>
                                        </select>
                                    </div>
                                    <div style="grid-column: 1 / -1;">
                                        <label>Explanation (Why this is correct)</label>
                                        <textarea name="explanation" style="width:100%; min-height: 80px;"><?= htmlspecialchars($mcq['explanation'] ?? '') ?></textarea>
                                    </div>
                                    <div style="grid-column: 1 / -1; margin-top: 0.5rem;">
                                        <button type="submit" class="btn btn-edit">Save</button>
                                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('edit-ai-<?= (int)$mcq['id'] ?>').style.display='none'">Cancel</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if (!$viewAll && $totalPages > 1): ?>
            <div class="pagination">
                <p>Showing <?= $totalFiltered ? ($offset + 1) : 0 ?>-<?= min($offset + $perPage, $totalFiltered) ?> of <?= $totalFiltered ?> MCQs</p>
                <div>
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Previous</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next →</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($viewAll): ?>
            <div class="pagination">
                <p><strong>Viewing all <?= $totalFiltered ?> MCQs</strong></p>
            </div>
        <?php endif; ?>
    </div>

    <div
        class="recommendation-manager"
        id="recommendationManager"
        role="dialog"
        aria-modal="true"
        aria-labelledby="recommendationManagerTitle"
        aria-hidden="true"
    >
        <div class="recommendation-manager-dialog">
            <div class="recommendation-manager-header">
                <div>
                    <h2 id="recommendationManagerTitle">Selected recommendation topics</h2>
                    <div class="selected-topic-meta">View topic details or remove topics from the public recommendation pool.</div>
                </div>
                <button type="button" class="recommendation-manager-close" onclick="closeRecommendationManager()" aria-label="Close">&times;</button>
            </div>
            <div class="recommendation-manager-body" id="recommendationManagerBody">
                <div class="recommendation-loading">Loading selected topics...</div>
            </div>
        </div>
    </div>

    <div class="ai-loader-overlay" id="aiLoader">
        <div class="ai-loader-box">
            <div class="ai-loader-spinner"></div>
            <div class="ai-loader-title">Verifying MCQs with AI</div>
            <div class="ai-loader-status" id="aiLoaderStatus">Initializing check process...</div>
            
            <div class="ai-progress-container">
                <div class="ai-progress-bar" id="aiProgressBar"></div>
            </div>
            
            <div class="ai-loader-details">
                <div class="ai-stat-item">
                    <strong id="statChecked">0</strong>
                    Checked
                </div>
                <div class="ai-stat-item">
                    <strong id="statVerified" style="color:#28a745">0</strong>
                    Verified
                </div>
                <div class="ai-stat-item">
                    <strong id="statCorrected" style="color:#007bff">0</strong>
                    Corrected
                </div>
                <div class="ai-stat-item">
                    <strong id="statFlagged" style="color:#dc3545">0</strong>
                    Flagged
                </div>
            </div>
        </div>
    </div>

    
    <script>
    (function() {
        var input = document.getElementById('topicSearchInput');
        if (input) {
            input.addEventListener('input', function() {
                var q = input.value.toLowerCase();
                var rows = document.querySelectorAll('#topicTable tbody tr');
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    var cells = row.querySelectorAll('td');
                    var visibleCells = 0;
                    for (var j = 0; j < cells.length; j++) {
                        var name = cells[j].querySelector('.topic-item-name');
                        if (!name) {
                            cells[j].style.display = q === '' ? '' : 'none';
                            continue;
                        }
                        var text = name.textContent || name.innerText || '';
                        var matches = q === '' || text.toLowerCase().indexOf(q) !== -1;
                        cells[j].style.display = matches ? '' : 'none';
                        if (matches) visibleCells++;
                    }
                    row.style.display = visibleCells > 0 ? '' : 'none';
                }
            });
        }

        var recommendationCheckboxes = document.querySelectorAll('.recommendation-checkbox');
        for (var i = 0; i < recommendationCheckboxes.length; i++) {
            recommendationCheckboxes[i].addEventListener('change', function() {
                var item = this.closest('.topic-item');
                if (item) item.classList.toggle('is-recommended', this.checked);
            });
        }

        var manager = document.getElementById('recommendationManager');
        if (manager) {
            manager.addEventListener('click', function(event) {
                if (event.target === manager) closeRecommendationManager();
            });
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') closeRecommendationManager();
        });
    })();

    const recommendationEndpoint = 'manage_ai_mcqs.php?ajax=recommendations';
    const recommendationCsrfToken = <?= json_encode($csrfToken) ?>;

    function escapeRecommendationHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setTopicRecommendations(checked) {
        var checkboxes = document.querySelectorAll('.recommendation-checkbox:not(:disabled)');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = checked;
            var item = checkboxes[i].closest('.topic-item');
            if (item) item.classList.toggle('is-recommended', checked);
        }
    }

    function openRecommendationManager() {
        var manager = document.getElementById('recommendationManager');
        if (!manager) return;
        manager.classList.add('is-open');
        manager.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        loadSelectedRecommendations();
    }

    function closeRecommendationManager() {
        var manager = document.getElementById('recommendationManager');
        if (!manager || !manager.classList.contains('is-open')) return;
        manager.classList.remove('is-open');
        manager.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    async function requestRecommendationData(url, options) {
        var response = await fetch(url, options || {});
        var data;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('The server returned an invalid response.');
        }
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to load recommendation data.');
        }
        return data;
    }

    async function loadSelectedRecommendations() {
        var body = document.getElementById('recommendationManagerBody');
        if (!body) return;
        body.innerHTML = '<div class="recommendation-loading">Loading selected topics...</div>';

        try {
            var data = await requestRecommendationData(recommendationEndpoint);
            renderSelectedRecommendations(data.topics || []);
        } catch (error) {
            body.innerHTML = '<div class="recommendation-error">' + escapeRecommendationHtml(error.message) + '</div>';
        }
    }

    function renderSelectedRecommendations(topics) {
        var body = document.getElementById('recommendationManagerBody');
        var count = document.getElementById('recommendedTopicCount');
        if (count) count.textContent = topics.length;

        if (!topics.length) {
            body.innerHTML = '<div class="recommendation-empty">No topics are currently selected for recommendation.</div>';
            return;
        }

        body.innerHTML = topics.map(function(topic, index) {
            var topicName = escapeRecommendationHtml(topic.topic_name);
            var selectedAt = topic.selected_at ? 'Selected ' + escapeRecommendationHtml(topic.selected_at) : 'Selection date unavailable';
            var selectedBy = topic.selected_by ? ' &bull; Admin #' + Number(topic.selected_by) : '';
            return '<div class="selected-topic-card">' +
                '<div class="selected-topic-summary">' +
                    '<div class="selected-topic-info">' +
                        '<div class="selected-topic-name">' + topicName + '</div>' +
                        '<div class="selected-topic-meta">' + Number(topic.mcq_count || 0) + ' MCQs &bull; ' + selectedAt + selectedBy + '</div>' +
                    '</div>' +
                    '<button type="button" class="btn btn-primary view-recommendation-details" data-topic="' + topicName + '" data-target="recommendationDetails' + index + '">View MCQ details</button>' +
                    '<button type="button" class="btn btn-delete remove-recommended-topic" data-topic="' + topicName + '">Remove</button>' +
                '</div>' +
                '<div class="selected-topic-details" id="recommendationDetails' + index + '"></div>' +
            '</div>';
        }).join('');

        body.querySelectorAll('.view-recommendation-details').forEach(function(button) {
            button.addEventListener('click', function() {
                loadRecommendedTopicDetails(this.dataset.topic, this.dataset.target, this);
            });
        });
        body.querySelectorAll('.remove-recommended-topic').forEach(function(button) {
            button.addEventListener('click', function() {
                removeRecommendedTopic(this.dataset.topic, this);
            });
        });
    }

    async function loadRecommendedTopicDetails(topic, targetId, button) {
        var details = document.getElementById(targetId);
        if (!details) return;

        if (button.dataset.loaded === 'true') {
            details.classList.toggle('is-open');
            button.textContent = details.classList.contains('is-open') ? 'Hide MCQ details' : 'View MCQ details';
            return;
        }

        details.classList.add('is-open');
        details.innerHTML = '<div class="recommendation-loading">Loading MCQ details...</div>';
        button.disabled = true;

        try {
            var data = await requestRecommendationData(recommendationEndpoint + '&topic=' + encodeURIComponent(topic));
            var mcqs = data.mcqs || [];
            if (!mcqs.length) {
                details.innerHTML = '<div class="recommendation-empty">No AI MCQs currently use this topic.</div>';
            } else {
                details.innerHTML = mcqs.map(function(mcq) {
                    return '<div class="recommendation-mcq">' +
                        '<div class="recommendation-mcq-question">#' + Number(mcq.id) + ' &mdash; ' + escapeRecommendationHtml(mcq.question) + '</div>' +
                        '<div class="recommendation-mcq-options">' +
                            '<div><strong>A:</strong> ' + escapeRecommendationHtml(mcq.option_a) + '</div>' +
                            '<div><strong>B:</strong> ' + escapeRecommendationHtml(mcq.option_b) + '</div>' +
                            '<div><strong>C:</strong> ' + escapeRecommendationHtml(mcq.option_c) + '</div>' +
                            '<div><strong>D:</strong> ' + escapeRecommendationHtml(mcq.option_d) + '</div>' +
                        '</div>' +
                        '<div class="recommendation-mcq-answer"><strong>Correct:</strong> ' + escapeRecommendationHtml(mcq.correct_option || 'Not set') + '</div>' +
                        '<div class="recommendation-mcq-explanation"><strong>Explanation:</strong> ' + escapeRecommendationHtml(mcq.explanation || 'Not provided') + '</div>' +
                        '<div class="selected-topic-meta">Generated: ' + escapeRecommendationHtml(mcq.generated_at || '-') + '</div>' +
                    '</div>';
                }).join('');
            }
            button.dataset.loaded = 'true';
            button.textContent = 'Hide MCQ details';
        } catch (error) {
            details.innerHTML = '<div class="recommendation-error">' + escapeRecommendationHtml(error.message) + '</div>';
        } finally {
            button.disabled = false;
        }
    }

    async function removeRecommendedTopic(topic, button) {
        if (!confirm('Remove "' + topic + '" from recommendations?')) return;

        button.disabled = true;
        var formData = new FormData();
        formData.append('action', 'remove_recommended_topic');
        formData.append('topic', topic);
        formData.append('csrf_token', recommendationCsrfToken);

        try {
            await requestRecommendationData(recommendationEndpoint, {
                method: 'POST',
                body: formData
            });
            document.querySelectorAll('.recommendation-checkbox').forEach(function(checkbox) {
                if (checkbox.value === topic) {
                    checkbox.checked = false;
                    var item = checkbox.closest('.topic-item');
                    if (item) item.classList.remove('is-recommended');
                }
            });
            await loadSelectedRecommendations();
        } catch (error) {
            alert(error.message);
            button.disabled = false;
        }
    }

    function checkSingleMCQ(id, btn) {
        if (!confirm('Run AI check for this MCQ?')) return;

        const loader = document.getElementById('aiLoader');
        const status = document.getElementById('aiLoaderStatus');
        const bar = document.getElementById('aiProgressBar');
        
        // Disable button and show inner loader
        btn.classList.add('loading');
        btn.querySelector('.btn-text').textContent = 'Checking...';

        loader.style.display = 'flex';
        status.textContent = 'Preparing check for MCQ #' + id + '...';
        bar.style.width = '20%';

        const formData = new FormData();
        formData.append('action', 'check_ai_mcqs');
        formData.append('source_table', 'AIGeneratedMCQs');
        formData.append('ids', JSON.stringify([id]));
        formData.append('csrf_token', '<?= $csrfToken ?>');

        fetch('verify_ai_mcqs.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.stats && data.stats.checked > 0) {
                bar.style.width = '100%';
                status.textContent = 'Check completed successfully!';
                
                // Update stats
                document.getElementById('statChecked').textContent = data.stats.checked;
                document.getElementById('statVerified').textContent = data.stats.verified;
                document.getElementById('statCorrected').textContent = data.stats.corrected;
                document.getElementById('statFlagged').textContent = data.stats.flagged;
                
                setTimeout(() => {
                    loader.style.display = 'none';
                    location.reload(); 
                }, 800);
            } else {
                loader.style.display = 'none';
                btn.classList.remove('loading');
                btn.querySelector('.btn-text').textContent = 'Retry';
                alert('Error: ' + (data.message || 'Unknown error occurred.'));
            }
        })
        .catch(err => {
            console.error(err);
            loader.style.display = 'none';
            btn.classList.remove('loading');
            btn.querySelector('.btn-text').textContent = 'Retry';
            alert('Network error occurred.');
        });
    }
    </script>

