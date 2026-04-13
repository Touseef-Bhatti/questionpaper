<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db_connect.php';

$reviews = [];
$totalReviews = 0;
$avgRating = 0;
$perPage = 12;
$currentPage = max(1, intval($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;
$tableExists = false;
$isLoggedIn = isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0;
$formError = '';
$formSuccess = '';
$selectedRating = intval($_POST['rating'] ?? 0);

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

    $stmt = $conn->prepare("SELECT reviewer_name, reviewer_email, rating, feedback, source_page, created_at, is_anonymous FROM user_reviews WHERE is_approved = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?");
    if ($stmt) {
        $stmt->bind_param('ii', $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        $stmt->close();
    }
}

$totalPages = max(1, (int)ceil($totalReviews / $perPage));

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

    
    <!-- ads monetag_ads -->
    <script>(function(s){s.dataset.zone='10835874',s.src='https://n6wxm.com/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
    <!-- end ads monetag_ads -->
     
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
            padding: 2rem 1.5rem 4rem;
            margin-top: 20%;
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
            grid-template-columns: repeat(auto-fit, minmax(70%, 1fr));
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
            /* width: 70%; */
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
        .pagination-wrap {
            margin-top: 1.6rem;
            display: flex;
            justify-content: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .page-link {
            min-width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 1px solid #dbeafe;
            background: #fff;
            color: #1e3a8a;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            padding: 0 0.8rem;
        }
        .page-link.active {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-color: transparent;
            color: #fff;
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
                    <span class="rating-value" id="ratingValue"><?= $selectedRating > 0 ? $selectedRating . ' / 5' : 'Select stars' ?></span>
                    <input type="number" min="1" max="5" class="rating-hidden" id="rating" name="rating" value="<?= $selectedRating > 0 ? $selectedRating : '' ?>" required>
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
        <div class="empty-state">No reviews are available at the moment. Complete a quiz and submit feedback to be featured here.</div>
    <?php else: ?>
        <section class="reviews-grid">
            <?php foreach ($reviews as $review): ?>
                <?php
                    $displayName = trim((string)($review['reviewer_name'] ?? ''));
                    if ($displayName === '') {
                        $displayName = ((int)($review['is_anonymous'] ?? 0) === 1) ? 'Anonymous User' : 'User';
                    }
                    $createdAt = strtotime((string)($review['created_at'] ?? 'now'));
                ?>
                <article class="review-card">
                    <div class="review-top">
                        <div class="reviewer-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="reviewer"><?= htmlspecialchars($displayName) ?></div>
                    </div>
                    <div class="review-stars"><?= htmlspecialchars(renderStars((int)$review['rating'])) ?></div>
                    <div class="review-feedback"><?= htmlspecialchars((string)$review['feedback']) ?></div>
                    <div class="review-date"><?= date('d M Y, h:i A', $createdAt) ?></div>
                </article>
            <?php endforeach; ?>
        </section>

        <?php if ($totalPages > 1): ?>
            <div class="pagination-wrap">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a class="page-link <?= $p === $currentPage ? 'active' : '' ?>" href="reviews.php?page=<?= $p ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
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
</script>

<?php include 'footer.php'; ?>
</body>
</html>
