<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db_connect.php';

$reviews = [];
$totalReviews = 0;
$avgRating = 0;
$ratingCounts = array_fill(1, 5, 0);
$tableExists = false;
$isLoggedIn = isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0;
$formError = '';
$formSuccess = '';
$selectedFormRating = intval($_POST['rating'] ?? 0);
$ratingFilter = intval($_GET['rating'] ?? 0);
if ($ratingFilter < 1 || $ratingFilter > 5) {
    $ratingFilter = 0;
}

$tableCheck = $conn->query("SHOW TABLES LIKE 'user_reviews'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $tableExists = true;
}

if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_public_review') {
    $rating = intval($_POST['rating'] ?? 0);
    $feedback = trim((string)($_POST['feedback'] ?? ''));
    $isAnonymousRequested = intval($_POST['is_anonymous'] ?? 0) === 1;

    if ($rating < 1 || $rating > 5) {
        $formError = 'Please select a valid rating.';
    } elseif ($feedback === '' || strlen($feedback) < 3) {
        $formError = 'Please write your feedback in the textbox.';
    } else {
        $feedback = substr($feedback, 0, 1000);
        $userId = $isLoggedIn ? intval($_SESSION['user_id']) : null;

        if ($isLoggedIn) {
            $isAnonymous = $isAnonymousRequested ? 1 : 0;
            $reviewerName = $isAnonymous ? 'Anonymous User' : trim((string)($_SESSION['name'] ?? 'User'));
            $reviewerEmail = $isAnonymous ? null : trim((string)($_SESSION['email'] ?? ''));
        } else {
            $guestName = trim((string)($_POST['reviewer_name'] ?? ''));
            $guestEmail = trim((string)($_POST['reviewer_email'] ?? ''));
            $isAnonymous = $isAnonymousRequested ? 1 : 0;
            if ($isAnonymous) {
                $reviewerName = 'Anonymous User';
                $reviewerEmail = null;
            } else {
                if (strlen($guestName) < 2) {
                    $formError = 'Please enter your name.';
                } elseif ($guestEmail !== '' && !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                    $formError = 'Please enter a valid email address.';
                }
                $reviewerName = $guestName;
                $reviewerEmail = $guestEmail !== '' ? $guestEmail : null;
            }
        }

        if ($formError === '') {
            $stmt = $conn->prepare("INSERT INTO user_reviews (user_id, reviewer_name, reviewer_email, rating, feedback, source_page, is_anonymous) VALUES (?, ?, ?, ?, ?, 'reviews_page', ?)");
            if ($stmt) {
                $stmt->bind_param('issisi', $userId, $reviewerName, $reviewerEmail, $rating, $feedback, $isAnonymous);
                if ($stmt->execute()) {
                    $_SESSION['site_review_submitted'] = true;
                    header('Location: reviews.php?submitted=1#write-review');
                    exit;
                }
                $stmt->close();
            }
            $formError = 'Unable to submit review right now. Please try again.';
        }
    }
}

if (isset($_GET['submitted']) && $_GET['submitted'] === '1') {
    $formSuccess = 'Thank you! Your review has been submitted.';
}

if ($tableExists) {
    $countResult = $conn->query("SELECT COUNT(*) AS total_reviews, ROUND(AVG(rating), 1) AS avg_rating FROM user_reviews WHERE is_approved = 1");
    if ($countResult) {
        $meta = $countResult->fetch_assoc();
        $totalReviews = intval($meta['total_reviews'] ?? 0);
        $avgRating = floatval($meta['avg_rating'] ?? 0);
    }

    $ratingCountResult = $conn->query("SELECT rating, COUNT(*) AS review_count FROM user_reviews WHERE is_approved = 1 GROUP BY rating");
    if ($ratingCountResult) {
        while ($ratingRow = $ratingCountResult->fetch_assoc()) {
            $ratingValue = intval($ratingRow['rating'] ?? 0);
            if ($ratingValue >= 1 && $ratingValue <= 5) {
                $ratingCounts[$ratingValue] = intval($ratingRow['review_count'] ?? 0);
            }
        }
    }

    $userVotes = [];
    $reviewSql = "SELECT id, reviewer_name, reviewer_email, rating, feedback, source_page, created_at, is_anonymous, likes_count, dislikes_count, is_pinned FROM user_reviews WHERE is_approved = 1";
    if ($ratingFilter > 0) {
        $reviewSql .= " AND rating = ?";
    }
    $reviewSql .= " ORDER BY is_pinned DESC, created_at DESC";

    $stmt = $conn->prepare($reviewSql);
    if ($stmt) {
        if ($ratingFilter > 0) {
            $stmt->bind_param('i', $ratingFilter);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $reviewIds = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
            $reviewIds[] = $row['id'];
        }
        $stmt->close();
        
        // Fetch user votes if logged in
        if ($isLoggedIn && !empty($reviewIds)) {
            $userId = intval($_SESSION['user_id']);
            $idList = implode(',', $reviewIds);
            $voteStmt = $conn->prepare("SELECT review_id, vote_type FROM review_votes WHERE user_id = ? AND review_id IN ($idList)");
            if ($voteStmt) {
                $voteStmt->bind_param('i', $userId);
                $voteStmt->execute();
                $voteResult = $voteStmt->get_result();
                while ($vRow = $voteResult->fetch_assoc()) {
                    $userVotes[$vRow['review_id']] = $vRow['vote_type'];
                }
                $voteStmt->close();
            }
        }
    }
}

function renderStars(int $rating): string {
    $full = max(0, min(5, $rating));
    $empty = 5 - $full;
    return str_repeat('★', $full) . str_repeat('☆', $empty);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/includes/favicons.php'; ?>
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>
    <?php include_once __DIR__ . '/includes/monetag_ads.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Read verified student and teacher reviews for Ahmad Learning Hub. See real feedback about online quizzes, MCQs practice, and question paper generation tools.">
    <title>User Reviews | Ahmad Learning Hub</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        .reviews-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 1.5rem 4rem;
        }
        .reviews-hero {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #fff;
            border-radius: 24px;
            padding: 2.5rem 2rem;
            box-shadow: 0 16px 40px rgba(79, 70, 229, 0.3);
            margin-bottom: 2rem;
        }
        .reviews-hero h1 {
            margin: 0 0 0.6rem;
            font-size: clamp(1.7rem, 3vw, 2.3rem);
            font-weight: 900;
        }
        .reviews-hero p {
            margin: 0;
            color: rgba(255, 255, 255, 0.92);
            max-width: 860px;
            line-height: 1.7;
        }
        .reviews-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.6rem;
        }
        .metric-card {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 14px;
            padding: 0.95rem 1rem;
        }
        .metric-value {
            font-size: 1.7rem;
            font-weight: 900;
            line-height: 1.1;
        }
        .metric-label {
            font-size: 0.85rem;
            opacity: 0.92;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .reviews-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.2rem;
            margin: auto;
        }
        .review-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 1.25rem;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .review-card:hover {
            transform: translateY(-2px);
            border-color: #c7d2fe;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.09);
        }
        .review-top {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 1rem;
        }
        .reviewer {
            font-weight: 800;
            color: #0f172a;
            font-size: 1.05rem;
        }
        .reviewer-avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        .review-form-wrap {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 1.2rem;
            margin-bottom: 1.4rem;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
        }
        .review-form-title {
            margin: 0 0 0.2rem;
            color: #0f172a;
            font-size: 1.2rem;
            font-weight: 800;
        }
        .review-form-subtitle {
            margin: 0 0 1rem;
            color: #475569;
            font-size: 0.92rem;
        }
        .review-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.9rem;
            margin-bottom: 0.9rem;
        }
        .review-input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0.65rem 0.8rem;
            color: #0f172a;
            font-size: 0.94rem;
            outline: none;
            background: #fff;
        }
        .review-input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.14);
        }
        .review-textarea {
            min-height: 120px;
            resize: vertical;
            margin-top: 0.5rem;
        }
        .review-stars-row {
            display: flex;
            gap: 0.8rem;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 0.5rem;
        }
        .star-input-row {
            display: flex;
            gap: 0.55rem;
            align-items: center;
        }
        .rating-hidden {
            display: none;
        }
        .star-input-btn {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #94a3b8;
            font-size: 1.2rem;
            cursor: pointer;
            transition: transform 0.18s ease, background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
        }
        .star-input-btn:hover {
            transform: translateY(-1px);
            color: #f59e0b;
            border-color: #f59e0b;
            background: #fff7ed;
        }
        .star-input-btn.active {
            color: #f59e0b;
            border-color: #f59e0b;
            background: #fff7ed;
        }
        .rating-value {
            font-size: 0.86rem;
            font-weight: 700;
            color: #334155;
            min-width: 95px;
        }
        .review-anon {
            margin: 0.7rem 0 0.2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #334155;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .review-submit-btn {
            margin-top: 0.9rem;
            border: none;
            border-radius: 10px;
            padding: 0.7rem 1.2rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            cursor: pointer;
        }
        .review-form-message {
            margin-top: 0.75rem;
            font-size: 0.9rem;
            font-weight: 700;
        }
        .review-form-message.error {
            color: #b91c1c;
        }
        .review-form-message.success {
            color: #166534;
        }
        .review-stars {
            color: #f59e0b;
            font-size: 1rem;
            letter-spacing: 0.08em;
            font-weight: 800;
        }
        .review-feedback {
            color: #334155;
            line-height: 1.7;
            font-size: 0.96rem;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .review-date {
            color: #64748b;
            font-size: 0.83rem;
            font-weight: 600;
            margin-top: auto;
        }
        .empty-state {
            background: #fff;
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            padding: 2rem;
            text-align: center;
            color: #475569;
        }
        .review-filter-section {
            position: relative;
            overflow: hidden;
            background: linear-gradient(145deg, #ffffff 0%, #f8faff 100%);
            border: 1px solid #dbe3f0;
            border-radius: 24px;
            padding: 1.6rem;
            margin-bottom: 1.4rem;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }
        .review-filter-section::after {
            content: '';
            position: absolute;
            width: 180px;
            height: 180px;
            right: -75px;
            top: -95px;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.08);
            pointer-events: none;
        }
        .review-filter-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.25rem;
            position: relative;
            z-index: 1;
        }
        .review-filter-heading {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }
        .review-filter-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            box-shadow: 0 8px 18px rgba(79, 70, 229, 0.25);
            flex-shrink: 0;
        }
        .review-filter-title {
            margin: 0;
            color: #0f172a;
            font-size: 1.25rem;
            font-weight: 900;
        }
        .review-filter-description {
            margin: 0.2rem 0 0;
            color: #64748b;
            font-size: 0.9rem;
        }
        .review-filter-status {
            color: #475569;
            font-size: 0.88rem;
            font-weight: 700;
            text-align: right;
            padding-top: 0.25rem;
        }
        .rating-summary-layout {
            display: grid;
            grid-template-columns: 190px minmax(0, 1fr);
            gap: 1.25rem;
            align-items: stretch;
            position: relative;
            z-index: 1;
        }
        .rating-average-card {
            border-radius: 18px;
            padding: 1.25rem;
            color: #fff;
            background: linear-gradient(145deg, #312e81, #6d28d9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-shadow: 0 12px 28px rgba(76, 29, 149, 0.2);
        }
        .rating-average-value {
            font-size: 2.7rem;
            line-height: 1;
            font-weight: 900;
        }
        .rating-average-stars {
            margin: 0.55rem 0 0.35rem;
            color: #fbbf24;
            letter-spacing: 0.1em;
            font-size: 1rem;
        }
        .rating-average-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.82);
        }
        .review-filter-buttons {
            display: grid;
            grid-template-columns: repeat(5, minmax(105px, 1fr));
            gap: 0.7rem;
        }
        .review-filter-btn {
            min-height: 92px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            color: #334155;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            font-weight: 800;
            box-shadow: 0 5px 14px rgba(15, 23, 42, 0.04);
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, border-color 0.18s ease;
        }
        .review-filter-btn:hover {
            transform: translateY(-2px);
            border-color: #fbbf24;
            box-shadow: 0 10px 24px rgba(245, 158, 11, 0.14);
        }
        .review-filter-btn.active {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(145deg, #f59e0b, #ea580c);
            box-shadow: 0 12px 24px rgba(234, 88, 12, 0.22);
        }
        .filter-rating {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 1.05rem;
        }
        .review-filter-btn .filter-star {
            color: #f59e0b;
            font-size: 1.1rem;
        }
        .review-filter-btn.active .filter-star {
            color: #fff7cc;
        }
        .filter-review-count {
            color: #64748b;
            font-size: 0.76rem;
            font-weight: 700;
        }
        .review-filter-btn.active .filter-review-count {
            color: rgba(255, 255, 255, 0.86);
        }
        .filter-progress {
            width: 70%;
            height: 4px;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
            margin-top: 0.15rem;
        }
        .filter-progress-fill {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #fbbf24, #f97316);
        }
        .review-filter-btn.active .filter-progress {
            background: rgba(255, 255, 255, 0.25);
        }
        .review-filter-btn.active .filter-progress-fill {
            background: #fff;
        }
        .clear-filter-link {
            color: #4f46e5;
            font-weight: 700;
            text-decoration: none;
        }
        @media (max-width: 720px) {
            .reviews-main {
                padding: 68px 0.85rem 2.5rem;
            }
            .reviews-hero {
                border-radius: 20px;
                padding: 1.7rem 1.2rem;
            }
            .reviews-metrics {
                grid-template-columns: 1fr;
            }
            .review-filter-section {
                padding: 1.15rem;
                border-radius: 20px;
            }
            .review-filter-header {
                align-items: flex-start;
                flex-direction: column;
            }
            .review-filter-status {
                text-align: left;
                padding: 0;
            }
            .rating-summary-layout {
                grid-template-columns: 1fr;
            }
            .rating-average-card {
                min-height: 150px;
            }
            .review-filter-buttons {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .review-filter-btn:last-child {
                grid-column: 1 / -1;
            }
            .review-form-wrap,
            .review-card {
                padding: 1rem;
                border-radius: 16px;
            }
            .review-stars-row {
                align-items: flex-start;
                flex-direction: column;
            }
            .star-input-row {
                width: 100%;
                justify-content: space-between;
                gap: 0.35rem;
            }
            .star-input-btn {
                flex: 1;
                max-width: 52px;
            }
            .review-footer {
                align-items: flex-start;
                flex-direction: column;
                gap: 0.8rem;
            }
            .review-actions {
                width: 100%;
            }
            .vote-btn {
                flex: 1;
                justify-content: center;
            }
            .featured-badge {
                right: 12px;
            }
        }
        @media (max-width: 390px) {
            .review-filter-buttons {
                grid-template-columns: 1fr;
            }
            .review-filter-btn:last-child {
                grid-column: auto;
            }
            .review-filter-heading {
                align-items: flex-start;
            }
        }
        .review-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 0.8rem;
            border-top: 1px solid #f1f5f9;
        }
        .review-actions {
            display: flex;
            gap: 0.6rem;
        }
        .vote-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .vote-btn:hover {
            background: #f1f5f9;
            color: #334155;
        }
        .vote-btn.active.like-btn {
            background: #ecfdf5;
            color: #10b981;
            border-color: #a7f3d0;
        }
        .vote-btn.active.dislike-btn {
            background: #fef2f2;
            color: #ef4444;
            border-color: #fecaca;
        }
        .vote-btn i {
            font-size: 0.9rem;
        }
        .featured-review {
            border: 2px solid #f59e0b;
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.15);
            position: relative;
        }
        .featured-badge {
            position: absolute;
            top: -12px;
            right: 20px;
            background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
            z-index: 10;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<main class="reviews-main">
    <section class="reviews-hero">
        <h1>Student & Teacher Reviews</h1>
        <p>These reviews come directly from users after quiz sessions and platform usage. We keep this section transparent to highlight real experiences and continuously improve your learning journey.</p>
        <div class="reviews-metrics">
            <div class="metric-card">
                <div class="metric-value"><?= number_format($totalReviews) ?></div>
                <div class="metric-label">Total Reviews</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= $avgRating > 0 ? number_format($avgRating, 1) : '0.0' ?>/5</div>
                <div class="metric-label">Average Rating</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">Verified</div>
                <div class="metric-label">Public Feedback</div>
            </div>
        </div>
    </section>

    <?php if ($tableExists): ?>
        <section class="review-filter-section" aria-labelledby="review-filter-title">
            <div class="review-filter-header">
                <div class="review-filter-heading">
                    <span class="review-filter-icon" aria-hidden="true"><i class="fas fa-sliders"></i></span>
                    <div>
                        <h2 class="review-filter-title" id="review-filter-title">Explore Reviews by Rating</h2>
                        <p class="review-filter-description">Select a star rating to view feedback from that group.</p>
                    </div>
                </div>
                <div class="review-filter-status">
                    <?php if ($ratingFilter > 0): ?>
                        Showing <?= number_format($ratingCounts[$ratingFilter]) ?> <?= $ratingFilter ?>-star <?= $ratingCounts[$ratingFilter] === 1 ? 'review' : 'reviews' ?>
                        &nbsp;|&nbsp;
                        <a class="clear-filter-link" href="reviews.php#reviews-list">Show all reviews</a>
                    <?php else: ?>
                        Showing all <?= number_format($totalReviews) ?> reviews
                    <?php endif; ?>
                </div>
            </div>
            <div class="rating-summary-layout">
                <div class="rating-average-card">
                    <div class="rating-average-value"><?= $avgRating > 0 ? number_format($avgRating, 1) : '0.0' ?></div>
                    <div class="rating-average-stars" aria-label="<?= htmlspecialchars(number_format($avgRating, 1)) ?> out of 5 stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                    <div class="rating-average-label">Average from <?= number_format($totalReviews) ?> <?= $totalReviews === 1 ? 'review' : 'reviews' ?></div>
                </div>
                <div class="review-filter-buttons">
                    <?php for ($filterStar = 1; $filterStar <= 5; $filterStar++): ?>
                        <?php
                            $filterCount = $ratingCounts[$filterStar];
                            $filterPercent = $totalReviews > 0 ? round(($filterCount / $totalReviews) * 100) : 0;
                        ?>
                        <a
                            class="review-filter-btn <?= $ratingFilter === $filterStar ? 'active' : '' ?>"
                            href="<?= $ratingFilter === $filterStar ? 'reviews.php#reviews-list' : 'reviews.php?rating=' . $filterStar . '#reviews-list' ?>"
                            aria-pressed="<?= $ratingFilter === $filterStar ? 'true' : 'false' ?>"
                            aria-label="<?= $filterStar ?> stars, <?= $filterCount ?> <?= $filterCount === 1 ? 'review' : 'reviews' ?>"
                        >
                            <span class="filter-rating">
                                <span><?= $filterStar ?></span>
                                <span class="filter-star" aria-hidden="true">&#9733;</span>
                            </span>
                            <span class="filter-review-count"><?= number_format($filterCount) ?> <?= $filterCount === 1 ? 'review' : 'reviews' ?></span>
                            <span class="filter-progress" aria-hidden="true">
                                <span class="filter-progress-fill" style="width: <?= $filterPercent ?>%"></span>
                            </span>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($tableExists): ?>
        <section class="review-form-wrap" id="write-review">
            <h2 class="review-form-title">Write a Review</h2>
            <p class="review-form-subtitle">Share your feedback about your learning experience.</p>
            <form method="POST" action="reviews.php#write-review">
                <input type="hidden" name="action" value="submit_public_review">
                <?php if (!$isLoggedIn): ?>
                    <div class="review-form-grid">
                        <input class="review-input" type="text" name="reviewer_name" placeholder="Your name" value="<?= htmlspecialchars((string)($_POST['reviewer_name'] ?? '')) ?>">
                        <input class="review-input" type="email" name="reviewer_email" placeholder="Your email (optional)" value="<?= htmlspecialchars((string)($_POST['reviewer_email'] ?? '')) ?>">
                    </div>
                <?php endif; ?>
                <div class="review-stars-row">
                    <label for="rating" style="color:#334155; font-weight:700;">Rating</label>
                    <div class="star-input-row" id="starInputRow">
                        <button type="button" class="star-input-btn" data-rating="1" aria-label="1 star">★</button>
                        <button type="button" class="star-input-btn" data-rating="2" aria-label="2 stars">★</button>
                        <button type="button" class="star-input-btn" data-rating="3" aria-label="3 stars">★</button>
                        <button type="button" class="star-input-btn" data-rating="4" aria-label="4 stars">★</button>
                        <button type="button" class="star-input-btn" data-rating="5" aria-label="5 stars">★</button>
                    </div>
                    <span class="rating-value" id="ratingValue"><?= $selectedFormRating > 0 ? $selectedFormRating . ' / 5' : 'Select stars' ?></span>
                    <input type="number" min="1" max="5" class="rating-hidden" id="rating" name="rating" value="<?= $selectedFormRating > 0 ? $selectedFormRating : '' ?>" required>
                </div>
                <textarea class="review-input review-textarea" name="feedback" maxlength="1000" placeholder="Write your review..."><?= htmlspecialchars((string)($_POST['feedback'] ?? '')) ?></textarea>
                <label class="review-anon">
                    <input type="checkbox" name="is_anonymous" value="1" <?= !empty($_POST['is_anonymous']) ? 'checked' : '' ?>>
                    Post review anonymously
                </label>
                <div>
                    <button type="submit" class="review-submit-btn">Submit Review</button>
                </div>
                <?php if ($formError !== ''): ?>
                    <div class="review-form-message error"><?= htmlspecialchars($formError) ?></div>
                <?php elseif ($formSuccess !== ''): ?>
                    <div class="review-form-message success"><?= htmlspecialchars($formSuccess) ?></div>
                <?php endif; ?>
            </form>
        </section>
    <?php endif; ?>

    <?php if (!$tableExists): ?>
        <div class="empty-state">Reviews table is not available yet. Please run <strong>install.php</strong> once, then revisit this page.</div>
    <?php elseif (empty($reviews)): ?>
        <div class="empty-state" id="reviews-list">
            <?php if ($ratingFilter > 0): ?>
                No <?= $ratingFilter ?>-star reviews are available. <a class="clear-filter-link" href="reviews.php#reviews-list">Show all reviews</a>.
            <?php else: ?>
                No reviews are available at the moment. Complete a quiz and submit feedback to be featured here.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <section class="reviews-grid" id="reviews-list">
            <?php foreach ($reviews as $review): ?>
                <?php
                    $displayName = trim((string)($review['reviewer_name'] ?? ''));
                    if ($displayName === '') {
                        $displayName = ((int)($review['is_anonymous'] ?? 0) === 1) ? 'Anonymous User' : 'User';
                    }
                    $createdAt = strtotime((string)($review['created_at'] ?? 'now'));
                    $isPinned = (int)($review['is_pinned'] ?? 0) === 1;
                ?>
                <article class="review-card <?= $isPinned ? 'featured-review' : '' ?>">
                    <?php if ($isPinned): ?>
                        <div class="featured-badge"><i class="fas fa-star"></i> Featured</div>
                    <?php endif; ?>
                    <div class="review-top">
                        <div class="reviewer-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="reviewer"><?= htmlspecialchars($displayName) ?></div>
                    </div>
                    <div class="review-stars"><?= htmlspecialchars(renderStars((int)$review['rating'])) ?></div>
                    <div class="review-feedback"><?= htmlspecialchars((string)$review['feedback']) ?></div>
                    <?php 
                        $rId = $review['id'] ?? 0;
                        $myVote = $userVotes[$rId] ?? null;
                    ?>
                    <div class="review-footer">
                        <div class="review-date"><?= date('d M Y, h:i A', $createdAt) ?></div>
                        <div class="review-actions">
                            <button class="vote-btn like-btn <?= $myVote === 'like' ? 'active' : '' ?>" data-review-id="<?= $rId ?>" data-action="like">
                                <i class="fas fa-thumbs-up"></i> <span class="count"><?= intval($review['likes_count'] ?? 0) ?></span>
                            </button>
                            <button class="vote-btn dislike-btn <?= $myVote === 'dislike' ? 'active' : '' ?>" data-review-id="<?= $rId ?>" data-action="dislike">
                                <i class="fas fa-thumbs-down"></i> <span class="count"><?= intval($review['dislikes_count'] ?? 0) ?></span>
                            </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>

<script>
const reviewStarButtons = document.querySelectorAll('#starInputRow .star-input-btn');
const reviewRatingInput = document.getElementById('rating');
const reviewRatingValue = document.getElementById('ratingValue');
let selectedReviewRating = Number(reviewRatingInput ? reviewRatingInput.value : 0);

function paintReviewStars(hoverRating = 0) {
    const activeRating = hoverRating || selectedReviewRating;
    reviewStarButtons.forEach(btn => {
        const val = Number(btn.dataset.rating || 0);
        btn.classList.toggle('active', val <= activeRating);
    });
}

reviewStarButtons.forEach(btn => {
    btn.addEventListener('click', function () {
        selectedReviewRating = Number(this.dataset.rating || 0);
        if (reviewRatingInput) reviewRatingInput.value = selectedReviewRating;
        if (reviewRatingValue) reviewRatingValue.textContent = `${selectedReviewRating} / 5`;
        paintReviewStars();
    });
    btn.addEventListener('mouseenter', function () {
        paintReviewStars(Number(this.dataset.rating || 0));
    });
    btn.addEventListener('mouseleave', function () {
        paintReviewStars();
    });
});

paintReviewStars();

// Handle Like/Dislike voting
document.querySelectorAll('.vote-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const reviewId = this.dataset.reviewId;
        const action = this.dataset.action;
        const btnContainer = this.closest('.review-actions');
        
        try {
            const formData = new FormData();
            formData.append('review_id', reviewId);
            formData.append('action', action);
            
            const response = await fetch('review_vote.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.require_login) {
                if (typeof showAuthModal === 'function') {
                    showAuthModal();
                } else {
                    alert('You need to be logged in to vote.');
                }
                return;
            }
            
            if (data.success) {
                // Update counts
                const likeBtn = btnContainer.querySelector('.like-btn');
                const dislikeBtn = btnContainer.querySelector('.dislike-btn');
                
                likeBtn.querySelector('.count').textContent = data.likes_count;
                dislikeBtn.querySelector('.count').textContent = data.dislikes_count;
                
                // Update active states
                likeBtn.classList.remove('active');
                dislikeBtn.classList.remove('active');
                
                if (data.current_vote === 'like') {
                    likeBtn.classList.add('active');
                } else if (data.current_vote === 'dislike') {
                    dislikeBtn.classList.add('active');
                }
            } else {
                console.error(data.error || 'Failed to process vote');
            }
        } catch (error) {
            console.error('Error submitting vote:', error);
        }
    });
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>
