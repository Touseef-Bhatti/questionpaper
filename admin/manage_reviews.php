<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);

    if ($action === 'toggle_pin' && $id > 0) {
        $stmt = $conn->prepare("UPDATE user_reviews SET is_pinned = NOT is_pinned WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $message = 'Review pin status updated successfully.';
            } else {
                $message = 'Unable to update review pin status.';
                $messageType = 'error';
            }
            $stmt->close();
        } else {
            $message = 'Unable to update review pin status.';
            $messageType = 'error';
        }
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare("DELETE FROM user_reviews WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $message = 'Review deleted successfully.';
            } else {
                $message = 'Unable to delete review.';
                $messageType = 'error';
            }
            $stmt->close();
        } else {
            $message = 'Unable to delete review.';
            $messageType = 'error';
        }
    }

    if ($action === 'edit' && $id > 0) {
        $reviewerName = trim((string)($_POST['reviewer_name'] ?? ''));
        $reviewerEmail = trim((string)($_POST['reviewer_email'] ?? ''));
        $rating = intval($_POST['rating'] ?? 0);
        $feedback = trim((string)($_POST['feedback'] ?? ''));
        $isAnonymous = intval($_POST['is_anonymous'] ?? 0) === 1 ? 1 : 0;
        $isApproved = intval($_POST['is_approved'] ?? 0) === 1 ? 1 : 0;

        if ($reviewerName === '') {
            $reviewerName = $isAnonymous ? 'Anonymous User' : 'User';
        }
        if ($rating < 1 || $rating > 5) {
            $message = 'Rating must be between 1 and 5.';
            $messageType = 'error';
        } elseif (strlen($feedback) < 3) {
            $message = 'Feedback must be at least 3 characters.';
            $messageType = 'error';
        } elseif ($reviewerEmail !== '' && !filter_var($reviewerEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please provide a valid email.';
            $messageType = 'error';
        } else {
            $feedback = substr($feedback, 0, 1000);
            $reviewerEmail = $reviewerEmail !== '' ? $reviewerEmail : null;
            if ($isAnonymous === 1) {
                $reviewerName = 'Anonymous User';
                $reviewerEmail = null;
            }
            $stmt = $conn->prepare("UPDATE user_reviews SET reviewer_name = ?, reviewer_email = ?, rating = ?, feedback = ?, is_anonymous = ?, is_approved = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('ssisiii', $reviewerName, $reviewerEmail, $rating, $feedback, $isAnonymous, $isApproved, $id);
                if ($stmt->execute()) {
                    $message = 'Review updated successfully.';
                } else {
                    $message = 'Unable to update review.';
                    $messageType = 'error';
                }
                $stmt->close();
            } else {
                $message = 'Unable to update review.';
                $messageType = 'error';
            }
        }
    }
}

$reviews = [];
$query = $conn->query("SELECT id, reviewer_name, reviewer_email, rating, feedback, source_page, is_anonymous, is_approved, is_pinned, created_at FROM user_reviews ORDER BY created_at DESC");
if ($query) {
    while ($row = $query->fetch_assoc()) {
        $reviews[] = $row;
    }
}

include_once __DIR__ . '/header.php';
?>
<style>
    .admin-container { max-width: 1260px; margin: 0 auto; padding: 20px; }
    .top-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
    .top-row h1 { margin: 0; font-size: 1.7rem; color: #0f172a; }
    .top-row a { text-decoration: none; font-weight: 600; color: #1d4ed8; }
    .msg { padding: 12px 14px; border-radius: 8px; margin-bottom: 14px; font-weight: 600; }
    .msg.success { background: #dcfce7; color: #166534; }
    .msg.error { background: #fee2e2; color: #b91c1c; }
    .table-wrap { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06); }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; border-bottom: 1px solid #eef2f7; vertical-align: top; text-align: left; }
    th { background: #f8fafc; color: #334155; font-size: 0.86rem; text-transform: uppercase; letter-spacing: 0.04em; }
    tr:last-child td { border-bottom: none; }
    .rating { color: #f59e0b; font-weight: 800; letter-spacing: 0.05em; }
    .source { display: inline-block; padding: 2px 8px; border-radius: 14px; background: #eff6ff; color: #1d4ed8; font-size: 0.74rem; font-weight: 700; text-transform: uppercase; }
    .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 0.74rem; font-weight: 700; }
    .badge.anon { background: #ede9fe; color: #5b21b6; }
    .badge.named { background: #e2e8f0; color: #334155; }
    .badge.approved { background: #dcfce7; color: #166534; }
    .badge.pending { background: #fee2e2; color: #b91c1c; }
    .feedback { max-width: 420px; white-space: pre-wrap; color: #334155; line-height: 1.5; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-start; }
    .btn { border: none; border-radius: 8px; padding: 7px 10px; font-size: 0.84rem; font-weight: 700; cursor: pointer; }
    .btn.edit { background: #2563eb; color: #fff; }
    .btn.delete { background: #dc2626; color: #fff; }
    details.edit-panel { width: 100%; margin-top: 8px; }
    details.edit-panel summary { list-style: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
    details.edit-panel summary::-webkit-details-marker { display: none; }
    .edit-form { margin-top: 10px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
    .edit-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-bottom: 10px; }
    .edit-input, .edit-textarea, .edit-select { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; font-size: 0.9rem; color: #0f172a; background: #fff; }
    .edit-textarea { min-height: 90px; resize: vertical; margin-bottom: 10px; }
    .edit-submit { background: #16a34a; color: #fff; }
    .empty { padding: 40px 24px; text-align: center; color: #64748b; font-weight: 600; }
    @media (max-width: 900px) { .feedback { max-width: 260px; } }
</style>

<div class="admin-container">
    <div class="top-row">
        <h1>Manage Reviews</h1>
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="msg <?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="table-wrap">
        <?php if (empty($reviews)): ?>
            <div class="empty">No reviews available.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reviewer</th>
                        <th>Rating</th>
                        <th>Feedback</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $review): ?>
                        <tr>
                            <td>#<?= (int)$review['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars((string)$review['reviewer_name']) ?></strong><br>
                                <span style="color:#64748b; font-size:0.84rem;"><?= htmlspecialchars((string)($review['reviewer_email'] ?? '')) ?></span><br>
                                <span class="badge <?= (int)$review['is_anonymous'] === 1 ? 'anon' : 'named' ?>"><?= (int)$review['is_anonymous'] === 1 ? 'Anonymous' : 'Named' ?></span>
                            </td>
                            <td><span class="rating"><?= str_repeat('★', (int)$review['rating']) . str_repeat('☆', max(0, 5 - (int)$review['rating'])) ?></span></td>
                            <td><div class="feedback"><?= htmlspecialchars((string)$review['feedback']) ?></div></td>
                            <td><span class="source"><?= htmlspecialchars((string)$review['source_page']) ?></span></td>
                            <td>
                                <span class="badge <?= (int)$review['is_approved'] === 1 ? 'approved' : 'pending' ?>"><?= (int)$review['is_approved'] === 1 ? 'Approved' : 'Hidden' ?></span>
                                <?php if ((int)($review['is_pinned'] ?? 0) === 1): ?>
                                    <span class="badge" style="background:#fef3c7; color:#b45309; margin-top:4px; display:block; width:fit-content;">📌 Pinned</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d M Y, h:i A', strtotime((string)$review['created_at'])) ?></td>
                            <td class="actions">
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="action" value="toggle_pin">
                                    <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
                                    <button type="submit" class="btn" style="background:#f59e0b; color:#fff;"><?= (int)($review['is_pinned'] ?? 0) === 1 ? 'Unpin' : 'Pin' ?></button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Delete this review?');" style="display:inline-block;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
                                    <button type="submit" class="btn delete">Delete</button>
                                </form>
                                <details class="edit-panel">
                                    <summary><button type="button" class="btn edit">Edit</button></summary>
                                    <form method="POST" class="edit-form">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
                                        <div class="edit-grid">
                                            <input class="edit-input" type="text" name="reviewer_name" value="<?= htmlspecialchars((string)$review['reviewer_name']) ?>" placeholder="Reviewer name">
                                            <input class="edit-input" type="email" name="reviewer_email" value="<?= htmlspecialchars((string)($review['reviewer_email'] ?? '')) ?>" placeholder="Reviewer email">
                                            <select class="edit-select" name="rating" required>
                                                <?php for ($r = 1; $r <= 5; $r++): ?>
                                                    <option value="<?= $r ?>" <?= (int)$review['rating'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <label><input type="checkbox" name="is_anonymous" value="1" <?= (int)$review['is_anonymous'] === 1 ? 'checked' : '' ?>> Anonymous</label>
                                            <label><input type="checkbox" name="is_approved" value="1" <?= (int)$review['is_approved'] === 1 ? 'checked' : '' ?>> Approved</label>
                                        </div>
                                        <textarea class="edit-textarea" name="feedback" required placeholder="Review feedback..."><?= htmlspecialchars((string)$review['feedback']) ?></textarea>
                                        <button type="submit" class="btn edit-submit">Save Changes</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
