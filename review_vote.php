<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0;
if (!$isLoggedIn) {
    echo json_encode(['success' => false, 'require_login' => true]);
    exit;
}

$userId = intval($_SESSION['user_id']);
$reviewId = intval($_POST['review_id'] ?? 0);
$action = strtolower(trim((string)($_POST['action'] ?? '')));

if ($reviewId <= 0 || !in_array($action, ['like', 'dislike'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
    exit;
}

// 1. Verify review exists
$stmt = $conn->prepare("SELECT id, likes_count, dislikes_count FROM user_reviews WHERE id = ?");
$stmt->bind_param('i', $reviewId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Review not found.']);
    $stmt->close();
    exit;
}
$review = $result->fetch_assoc();
$stmt->close();

$likesCount = intval($review['likes_count']);
$dislikesCount = intval($review['dislikes_count']);

// 2. Check current vote
$stmt = $conn->prepare("SELECT id, vote_type FROM review_votes WHERE review_id = ? AND user_id = ?");
$stmt->bind_param('ii', $reviewId, $userId);
$stmt->execute();
$voteResult = $stmt->get_result();
$currentVote = null;
if ($voteResult->num_rows > 0) {
    $currentVote = $voteResult->fetch_assoc();
}
$stmt->close();

$newVoteState = null;

$conn->begin_transaction();

try {
    if (!$currentVote) {
        // Insert new vote
        $stmt = $conn->prepare("INSERT INTO review_votes (review_id, user_id, vote_type) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $reviewId, $userId, $action);
        $stmt->execute();
        $stmt->close();
        
        if ($action === 'like') {
            $likesCount++;
            $conn->query("UPDATE user_reviews SET likes_count = likes_count + 1 WHERE id = $reviewId");
        } else {
            $dislikesCount++;
            $conn->query("UPDATE user_reviews SET dislikes_count = dislikes_count + 1 WHERE id = $reviewId");
        }
        $newVoteState = $action;
    } else {
        $existingAction = $currentVote['vote_type'];
        $voteId = $currentVote['id'];
        
        if ($existingAction === $action) {
            // Toggle off (delete vote)
            $conn->query("DELETE FROM review_votes WHERE id = $voteId");
            if ($action === 'like') {
                $likesCount = max(0, $likesCount - 1);
                $conn->query("UPDATE user_reviews SET likes_count = GREATEST(0, likes_count - 1) WHERE id = $reviewId");
            } else {
                $dislikesCount = max(0, $dislikesCount - 1);
                $conn->query("UPDATE user_reviews SET dislikes_count = GREATEST(0, dislikes_count - 1) WHERE id = $reviewId");
            }
            $newVoteState = null;
        } else {
            // Switch vote type
            $stmt = $conn->prepare("UPDATE review_votes SET vote_type = ? WHERE id = ?");
            $stmt->bind_param('si', $action, $voteId);
            $stmt->execute();
            $stmt->close();
            
            if ($action === 'like') {
                $likesCount++;
                $dislikesCount = max(0, $dislikesCount - 1);
                $conn->query("UPDATE user_reviews SET likes_count = likes_count + 1, dislikes_count = GREATEST(0, dislikes_count - 1) WHERE id = $reviewId");
            } else {
                $dislikesCount++;
                $likesCount = max(0, $likesCount - 1);
                $conn->query("UPDATE user_reviews SET dislikes_count = dislikes_count + 1, likes_count = GREATEST(0, likes_count - 1) WHERE id = $reviewId");
            }
            $newVoteState = $action;
        }
    }
    
    $conn->commit();
    echo json_encode([
        'success' => true,
        'likes_count' => $likesCount,
        'dislikes_count' => $dislikesCount,
        'current_vote' => $newVoteState
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
