<?php
include '../db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function vnSlugify($text) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'notes';
}

function vnNoteUrl($assetBase, $note) {
    $subjectSlug = vnSlugify($note['subject'] ?? 'general');
    $titleSlug = vnSlugify($note['title'] ?? 'study-notes');
    return ($assetBase ?? '') . 'class-notes/class-' . rawurlencode((string)$note['class']) . '-' . $subjectSlug . '-' . $titleSlug . '-' . (int)$note['id'];
}

function vnEnsureEngagementTables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS class_note_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        note_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_note_like (note_id, user_id),
        INDEX idx_note_likes_note (note_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->query("CREATE TABLE IF NOT EXISTS class_note_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        note_id INT NOT NULL,
        user_id INT NOT NULL,
        parent_id INT DEFAULT NULL,
        comment_text TEXT NOT NULL,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        INDEX idx_note_comments_note (note_id, created_at),
        INDEX idx_note_comments_parent (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function vnSendReplyEmail($conn, $parentCommentId, $replyText, $replyUserName, $note, $noteAbsoluteUrl) {
    $stmt = $conn->prepare("SELECT u.email, COALESCE(NULLIF(u.name, ''), 'Student') AS name
                            FROM class_note_comments c
                            JOIN users u ON u.id = c.user_id
                            WHERE c.id = ? LIMIT 1");
    $stmt->bind_param('i', $parentCommentId);
    $stmt->execute();
    $recipient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$recipient || empty($recipient['email']) || !filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    require_once __DIR__ . '/../email/phpmailer_mailer.php';
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        configureMailerSmtp($mail);
        $mail->setFrom(getMailerFromAddress(), getMailerFromName());
        $mail->addAddress($recipient['email'], $recipient['name']);
        $mail->isHTML(true);
        $mail->Subject = 'Someone replied to your comment - Ahmad Learning Hub';
        $safeName = htmlspecialchars($recipient['name'], ENT_QUOTES, 'UTF-8');
        $safeReplyUser = htmlspecialchars($replyUserName, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($note['title'], ENT_QUOTES, 'UTF-8');
        $safeReply = nl2br(htmlspecialchars($replyText, ENT_QUOTES, 'UTF-8'));
        $safeUrl = htmlspecialchars($noteAbsoluteUrl, ENT_QUOTES, 'UTF-8');
        $mail->Body = '<!doctype html><html><body style="margin:0;background:#f6f8fb;font-family:Arial,sans-serif;color:#172033;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:28px 12px;background:#f6f8fb;"><tr><td align="center">
            <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
            <tr><td style="background:#1e3a8a;color:#ffffff;padding:24px 28px;"><h1 style="font-size:22px;margin:0;">New reply to your comment</h1></td></tr>
            <tr><td style="padding:26px 28px;">
            <p style="font-size:16px;line-height:1.6;margin:0 0 14px;">Hi ' . $safeName . ',</p>
            <p style="font-size:15px;line-height:1.7;margin:0 0 18px;"><strong>' . $safeReplyUser . '</strong> replied to your comment on <strong>' . $safeTitle . '</strong>.</p>
            <div style="border-left:4px solid #2563eb;background:#eff6ff;padding:14px 16px;border-radius:8px;margin:18px 0;color:#1e3a8a;line-height:1.6;">' . $safeReply . '</div>
            <p style="margin:24px 0;"><a href="' . $safeUrl . '#comments" style="background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;display:inline-block;">View reply</a></p>
            <p style="font-size:12px;color:#64748b;line-height:1.6;margin:0;">This is an automated notification from Ahmad Learning Hub.</p>
            </td></tr></table></td></tr></table></body></html>';
        $mail->AltBody = "{$replyUserName} replied to your comment on {$note['title']}: {$replyText}\n\nOpen: {$noteAbsoluteUrl}#comments";
        return $mail->send();
    } catch (Throwable $e) {
        error_log('Note reply email error: ' . $e->getMessage());
        return false;
    }
}

vnEnsureEngagementTables($conn);

$noteId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($noteId <= 0) {
    http_response_code(404);
    header('Location: ' . ($assetBase ?? '/') . 'class-notes');
    exit;
}

// Fetch approved note
$stmt = $conn->prepare("SELECT * FROM class_notes WHERE id = ? AND status = 'approved'");
$stmt->bind_param('i', $noteId);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();
$stmt->close();

if (!$note) {
    http_response_code(404);
    header('Location: ' . ($assetBase ?? '/') . 'class-notes');
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);
$currentUserId = $isLoggedIn ? (int)$_SESSION['user_id'] : 0;
if (empty($_SESSION['note_csrf_token'])) {
    $_SESSION['note_csrf_token'] = bin2hex(random_bytes(32));
}
$noteCsrfToken = $_SESSION['note_csrf_token'];

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$siteUrl = $protocol . "://" . $_SERVER['HTTP_HOST'];
$canonicalPath = vnNoteUrl($assetBase ?? '', $note);
$canonicalUrl = $siteUrl . '/' . ltrim($canonicalPath, '/');
$currentUrl = $siteUrl . $_SERVER['REQUEST_URI'];
$loginUrl = ($assetBase ?? '') . 'auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);
$engagementMessage = '';
$engagementError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['note_csrf_token'] ?? '';
    $action = $_POST['note_action'] ?? '';

    if (!$isLoggedIn) {
        header('Location: ' . $loginUrl);
        exit;
    }

    if (!hash_equals($noteCsrfToken, $postedToken)) {
        $engagementError = 'Security token expired. Please reload the page and try again.';
    } elseif ($action === 'toggle_like') {
        $likeStmt = $conn->prepare("SELECT id FROM class_note_likes WHERE note_id = ? AND user_id = ? LIMIT 1");
        $likeStmt->bind_param('ii', $noteId, $currentUserId);
        $likeStmt->execute();
        $existingLike = $likeStmt->get_result()->fetch_assoc();
        $likeStmt->close();

        if ($existingLike) {
            $delStmt = $conn->prepare("DELETE FROM class_note_likes WHERE id = ?");
            $delStmt->bind_param('i', $existingLike['id']);
            $delStmt->execute();
            $delStmt->close();
        } else {
            $insStmt = $conn->prepare("INSERT IGNORE INTO class_note_likes (note_id, user_id) VALUES (?, ?)");
            $insStmt->bind_param('ii', $noteId, $currentUserId);
            $insStmt->execute();
            $insStmt->close();
        }
        header('Location: ' . $canonicalPath . '#engagement');
        exit;
    } elseif ($action === 'comment' || $action === 'reply') {
        $commentText = trim($_POST['comment_text'] ?? '');
        $parentId = $action === 'reply' ? max(0, intval($_POST['parent_id'] ?? 0)) : 0;

        if ($commentText === '' || strlen($commentText) > 2000) {
            $engagementError = 'Please write a comment between 1 and 2000 characters.';
        } else {
            if ($parentId > 0) {
                $parentStmt = $conn->prepare("SELECT id FROM class_note_comments WHERE id = ? AND note_id = ? AND parent_id IS NULL AND is_deleted = 0 LIMIT 1");
                $parentStmt->bind_param('ii', $parentId, $noteId);
                $parentStmt->execute();
                $validParent = $parentStmt->get_result()->fetch_assoc();
                $parentStmt->close();
                if (!$validParent) {
                    $parentId = 0;
                }
            }

            if ($parentId > 0) {
                $commentStmt = $conn->prepare("INSERT INTO class_note_comments (note_id, user_id, parent_id, comment_text) VALUES (?, ?, ?, ?)");
                $commentStmt->bind_param('iiis', $noteId, $currentUserId, $parentId, $commentText);
            } else {
                $nullParent = null;
                $commentStmt = $conn->prepare("INSERT INTO class_note_comments (note_id, user_id, parent_id, comment_text) VALUES (?, ?, ?, ?)");
                $commentStmt->bind_param('iiis', $noteId, $currentUserId, $nullParent, $commentText);
            }
            $commentStmt->execute();
            $commentStmt->close();

            if ($parentId > 0) {
                $replyUserName = $_SESSION['name'] ?? $_SESSION['username'] ?? 'A student';
                vnSendReplyEmail($conn, $parentId, $commentText, $replyUserName, $note, $canonicalUrl);
            }

            header('Location: ' . $canonicalPath . '#comments');
            exit;
        }
    }
}

// Fetch related notes (same class & subject, excluding current)
$relatedNotes = [];
$relStmt = $conn->prepare("SELECT id, title, subject, chapter, mime_type FROM class_notes WHERE status = 'approved' AND class = ? AND subject = ? AND id != ? ORDER BY created_at DESC LIMIT 4");
$relStmt->bind_param('ssi', $note['class'], $note['subject'], $noteId);
$relStmt->execute();
$relResult = $relStmt->get_result();
while ($row = $relResult->fetch_assoc()) {
    $relatedNotes[] = $row;
}
$relStmt->close();

// Class labels
$classLabels = [
    '9'  => 'Class 9 (Matric Part 1)',
    '10' => 'Class 10 (Matric Part 2)',
    '11' => 'Class 11 (Intermediate Part 1)',
    '12' => 'Class 12 (Intermediate Part 2)'
];
$classLabel = $classLabels[$note['class']] ?? "Class {$note['class']}";
$classShort = "Class {$note['class']}";

// File type helpers
function vnGetFileTypeLabel($mime) {
    if (str_contains($mime, 'pdf')) return 'PDF Document';
    if (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint')) return 'PowerPoint Presentation';
    if (str_contains($mime, 'word') || str_contains($mime, 'document')) return 'Word Document';
    if (str_contains($mime, 'image/png')) return 'PNG Image';
    if (str_contains($mime, 'image/jpeg')) return 'JPEG Image';
    if (str_contains($mime, 'image/webp')) return 'WebP Image';
    if (str_contains($mime, 'image')) return 'Image File';
    return 'Document';
}

function vnGetFileTypeBadge($mime) {
    if (str_contains($mime, 'pdf')) return 'PDF';
    if (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint')) return 'PPT';
    if (str_contains($mime, 'word') || str_contains($mime, 'document')) return 'DOCX';
    if (str_contains($mime, 'image/png')) return 'PNG';
    if (str_contains($mime, 'image/jpeg')) return 'JPG';
    if (str_contains($mime, 'image/webp')) return 'WEBP';
    if (str_contains($mime, 'image')) return 'IMG';
    return 'FILE';
}

function vnFormatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

$fileTypeLabel = vnGetFileTypeLabel($note['mime_type']);
$fileTypeBadge = vnGetFileTypeBadge($note['mime_type']);
$fileSize = vnFormatSize($note['file_size']);
$uploadDate = date('F j, Y', strtotime($note['created_at']));
$subjectName = !empty($note['subject']) ? htmlspecialchars($note['subject']) : 'General';
$chapterName = !empty($note['chapter']) ? htmlspecialchars($note['chapter']) : '';
$noteTitle = htmlspecialchars($note['title']);
$noteDesc = !empty($note['description']) ? htmlspecialchars($note['description']) : "Free {$classShort} {$subjectName} study notes for board exam preparation.";

// SEO
$pageTitle = "{$noteTitle} - {$classShort} {$subjectName} Notes | Ahmad Learning Hub";
$pageDescription = "View and study {$noteTitle} for {$classLabel}. Free {$subjectName}" . ($chapterName ? " {$chapterName}" : "") . " {$fileTypeLabel} notes for Punjab board exam preparation 2026. Download free study materials.";
$metaKeywords = "{$classShort} notes, {$subjectName} notes, {$classLabel} study material, " . ($chapterName ? "{$chapterName} notes, " : "") . "board exam preparation, Punjab board notes, free study material, {$classShort} {$subjectName} {$fileTypeBadge}, matric notes, intermediate notes, online notes Pakistan";

// Google Drive embed URL
$embedUrl = "https://drive.google.com/file/d/" . urlencode($note['drive_file_id']) . "/preview";

$likeCount = 0;
$commentCount = 0;
$userLiked = false;
$likeCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM class_note_likes WHERE note_id = ?");
$likeCountStmt->bind_param('i', $noteId);
$likeCountStmt->execute();
$likeCount = (int)($likeCountStmt->get_result()->fetch_assoc()['total'] ?? 0);
$likeCountStmt->close();

if ($isLoggedIn) {
    $userLikeStmt = $conn->prepare("SELECT id FROM class_note_likes WHERE note_id = ? AND user_id = ? LIMIT 1");
    $userLikeStmt->bind_param('ii', $noteId, $currentUserId);
    $userLikeStmt->execute();
    $userLiked = (bool)$userLikeStmt->get_result()->fetch_assoc();
    $userLikeStmt->close();
}

$comments = [];
$commentStmt = $conn->prepare("SELECT c.*, COALESCE(NULLIF(u.name, ''), NULLIF(u.email, ''), 'Student') AS user_name
                               FROM class_note_comments c
                               JOIN users u ON u.id = c.user_id
                               WHERE c.note_id = ? AND c.is_deleted = 0
                               ORDER BY COALESCE(c.parent_id, c.id) ASC, c.parent_id ASC, c.created_at ASC");
$commentStmt->bind_param('i', $noteId);
$commentStmt->execute();
$commentResult = $commentStmt->get_result();
while ($row = $commentResult->fetch_assoc()) {
    $commentCount++;
    if (empty($row['parent_id'])) {
        $row['replies'] = [];
        $comments[(int)$row['id']] = $row;
    } elseif (isset($comments[(int)$row['parent_id']])) {
        $comments[(int)$row['parent_id']]['replies'][] = $row;
    }
}
$commentStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">

    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">

    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link rel="stylesheet" href="<?= $assetBase ?>css/notes.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "DigitalDocument",
        "name": "<?= addslashes($noteTitle) ?>",
        "description": "<?= addslashes($noteDesc) ?>",
        "encodingFormat": "<?= htmlspecialchars($note['mime_type']) ?>",
        "datePublished": "<?= date('Y-m-d', strtotime($note['created_at'])) ?>",
        "inLanguage": "en",
        "isAccessibleForFree": true,
        "provider": {
            "@type": "Organization",
            "name": "Ahmad Learning Hub",
            "url": "<?= $siteUrl ?>"
        },
        "about": {
            "@type": "Course",
            "name": "<?= addslashes($classLabel) ?> <?= addslashes($subjectName) ?>"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            { "@type": "ListItem", "position": 1, "name": "Home", "item": "<?= $siteUrl ?>" },
            { "@type": "ListItem", "position": 2, "name": "Class Notes", "item": "<?= $siteUrl ?>/class-notes" },
            { "@type": "ListItem", "position": 3, "name": "<?= addslashes($classShort) ?>", "item": "<?= $siteUrl ?>/class-notes?class=<?= $note['class'] ?>" },
            { "@type": "ListItem", "position": 4, "name": "<?= addslashes($noteTitle) ?>" }
        ]
    }
    </script>

    <style>
        :root {
            --vn-primary: #6366f1;
            --vn-primary-dark: #4f46e5;
            --vn-primary-glow: rgba(99, 102, 241, 0.12);
            --vn-accent: #38bdf8;
            --vn-bg: #f1f3f6;
            --vn-bg-card: #ffffff;
            --vn-bg-card-solid: #ffffff;
            --vn-surface: #f9fafc;
            --vn-border: rgba(0, 0, 0, 0.08);
            --vn-text: #4a5568;
            --vn-text-muted: #6c757d;
            --vn-text-heading: #2c3e50;
            --vn-success: #27ae60;
            --vn-radius: 16px;
            --vn-radius-sm: 10px;
        }

        .vn-page {
            background: var(--vn-bg);
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--vn-text);
        }

        .vn-container {
            max-width: 1360px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Breadcrumbs */
        .vn-breadcrumbs {
            padding: 1.25rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            font-size: 0.85rem;
            color: var(--vn-text-muted);
        }
        .vn-breadcrumbs a {
            color: var(--vn-text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .vn-breadcrumbs a:hover { color: var(--vn-primary); }
        .vn-breadcrumbs .vn-bc-sep {
            display: flex;
            align-items: center;
            opacity: 0.4;
        }
        .vn-breadcrumbs .vn-bc-current {
            color: var(--vn-text-heading);
            font-weight: 600;
        }

        /* Layout */
        .vn-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 2rem;
            padding-bottom: 3rem;
        }

        /* Iframe Viewer */
        .vn-viewer {
            background: var(--vn-bg-card-solid);
            border-radius: var(--vn-radius);
            border: 1px solid var(--vn-border);
            overflow: hidden;
            position: relative;
        }
        .vn-viewer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            background: #f9fafc;
            border-bottom: 1px solid var(--vn-border);
        }
        .vn-viewer-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1rem;
            color: var(--vn-text-heading);
        }
        .vn-viewer-title svg {
            width: 20px;
            height: 20px;
            color: var(--vn-primary);
            flex-shrink: 0;
        }
        .vn-file-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.75rem;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--vn-primary-glow);
            color: var(--vn-primary);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        .vn-iframe-wrap {
            position: relative;
            width: 100%;
            padding-bottom: 75%;
            background: #000;
        }
        .vn-iframe-wrap iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        .vn-iframe-loading {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #fff;
            z-index: 2;
            transition: opacity 0.5s;
        }
        .vn-iframe-loading.loaded { opacity: 0; pointer-events: none; }
        .vn-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: var(--vn-primary);
            border-radius: 50%;
            animation: vnSpin 0.8s linear infinite;
            margin-bottom: 1rem;
        }
        @keyframes vnSpin { to { transform: rotate(360deg); } }
        .vn-iframe-loading span {
            font-size: 0.85rem;
            color: var(--vn-text-muted);
        }

        /* Sidebar */
        .vn-sidebar { display: flex; flex-direction: column; gap: 1.5rem; }

        .vn-info-card {
            background: var(--vn-bg-card-solid);
            border: 1px solid var(--vn-border);
            border-radius: var(--vn-radius);
            overflow: hidden;
        }
        .vn-info-card-header {
            padding: 1.25rem 1.5rem;
            background: linear-gradient(135deg, rgba(99,102,241,0.12) 0%, rgba(56,189,248,0.08) 100%);
            border-bottom: 1px solid var(--vn-border);
        }
        .vn-info-card-header h2 {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--vn-text-heading);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        .vn-info-card-header h2 svg {
            width: 18px;
            height: 18px;
            color: var(--vn-primary);
        }
        .vn-info-card-body { padding: 1.25rem 1.5rem; }

        .vn-meta-row {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.85rem 0;
            border-bottom: 1px solid var(--vn-border);
        }
        .vn-meta-row:last-child { border-bottom: none; }
        .vn-meta-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--vn-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .vn-meta-icon svg { width: 18px; height: 18px; }
        .vn-meta-icon.class { background: #dbeafe; color: #2563eb; }
        .vn-meta-icon.subject { background: #dcfce7; color: #16a34a; }
        .vn-meta-icon.chapter { background: #fef3c7; color: #d97706; }
        .vn-meta-icon.type { background: #f3e8ff; color: #9333ea; }
        .vn-meta-icon.size { background: #fce7f3; color: #db2777; }
        .vn-meta-icon.date { background: #e0e7ff; color: #4f46e5; }
        .vn-meta-icon.user { background: #ccfbf1; color: #0d9488; }
        .vn-meta-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--vn-text-muted);
            margin-bottom: 0.15rem;
        }
        .vn-meta-value {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--vn-text-heading);
        }

        /* Tags */
        .vn-tags { display: flex; flex-wrap: wrap; gap: 0.4rem; padding: 1rem 1.5rem; }
        .vn-tag {
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            background: rgba(99, 102, 241, 0.1);
            color: var(--vn-primary);
            border: 1px solid rgba(99, 102, 241, 0.15);
        }

        /* Related Notes */
        .vn-related-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .vn-related-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            background: #f9fafc;
            border-radius: var(--vn-radius-sm);
            text-decoration: none;
            color: var(--vn-text);
            transition: all 0.25s;
            border: 1px solid transparent;
        }
        .vn-related-item:hover {
            background: rgba(99, 102, 241, 0.06);
            border-color: rgba(99, 102, 241, 0.15);
        }
        .vn-related-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--vn-primary), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .vn-related-icon svg { width: 16px; height: 16px; color: #fff; }
        .vn-related-title {
            font-size: 0.85rem;
            font-weight: 600;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .vn-related-sub {
            font-size: 0.72rem;
            color: var(--vn-text-muted);
            margin-top: 0.15rem;
        }

        /* SEO Content Section */
        .vn-seo-section {
            margin-top: 2rem;
            padding: 2.5rem;
            background: var(--vn-bg-card-solid);
            border: 1px solid var(--vn-border);
            border-radius: var(--vn-radius);
        }
        .vn-seo-section h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--vn-text-heading);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .vn-seo-section h2 svg { width: 24px; height: 24px; color: var(--vn-primary); }
        .vn-seo-section h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--vn-text-heading);
            margin: 1.5rem 0 0.75rem;
        }
        .vn-seo-section p {
            color: var(--vn-text-muted);
            line-height: 1.8;
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
        }
        .vn-seo-section ul {
            list-style: none;
            padding: 0;
            margin: 0.75rem 0;
        }
        .vn-seo-section ul li {
            position: relative;
            padding: 0.4rem 0 0.4rem 1.5rem;
            color: var(--vn-text-muted);
            font-size: 0.92rem;
            line-height: 1.6;
        }
        .vn-seo-section ul li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.75rem;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--vn-primary);
        }

        .vn-engagement {
            margin-top: 1.5rem;
            background: var(--vn-bg-card-solid);
            border: 1px solid var(--vn-border);
            border-radius: var(--vn-radius);
            padding: 1.35rem;
        }
        .vn-engagement-head {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1rem;
        }
        .vn-engagement-title {
            margin: 0;
            color: var(--vn-text-heading);
            font-size: 1.05rem;
            font-weight: 800;
        }
        .vn-engagement-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .vn-action-btn {
            border: 1px solid #dbe1ea;
            background: #fff;
            color: #344256;
            border-radius: 8px;
            padding: 0.58rem 0.85rem;
            font-weight: 700;
            font-size: 0.84rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            text-decoration: none;
        }
        .vn-action-btn.primary,
        .vn-action-btn.liked {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        .vn-share-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.55rem;
            margin-bottom: 1rem;
        }
        .vn-share-row input {
            border: 1px solid #dbe1ea;
            border-radius: 8px;
            padding: 0.65rem 0.75rem;
            min-width: 0;
            color: #475569;
        }
        .vn-comment-form textarea {
            width: 100%;
            min-height: 90px;
            resize: vertical;
            border: 1px solid #dbe1ea;
            border-radius: 10px;
            padding: 0.8rem;
            font-family: inherit;
            color: #172033;
            box-sizing: border-box;
        }
        .vn-comment-list {
            margin-top: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }
        .vn-comment {
            border: 1px solid #e6ebf2;
            border-radius: 10px;
            padding: 0.95rem;
            background: #fbfcfe;
        }
        .vn-comment.reply {
            margin-left: 1.25rem;
            background: #fff;
            border-left: 3px solid #2563eb;
        }
        .vn-comment-meta {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
            color: #64748b;
            font-size: 0.78rem;
            margin-bottom: 0.4rem;
        }
        .vn-comment-meta strong {
            color: #172033;
            font-size: 0.86rem;
        }
        .vn-comment-text {
            white-space: pre-wrap;
            line-height: 1.65;
            color: #334155;
            font-size: 0.92rem;
            margin: 0 0 0.65rem;
        }
        .vn-reply-form {
            display: none;
            margin-top: 0.65rem;
        }
        .vn-reply-form.open {
            display: block;
        }
        .vn-reply-form textarea {
            min-height: 70px;
        }
        .vn-login-note {
            background: #eff6ff;
            color: #1e3a8a;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 0.9rem 1rem;
            line-height: 1.55;
            margin: 1rem 0;
        }
        .vn-flash {
            padding: 0.8rem 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        .vn-flash.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        /* Back link */
        .vn-back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: var(--vn-radius-sm);
            color: var(--vn-text);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            transition: all 0.25s;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        .vn-back-link:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--vn-primary);
            color: var(--vn-primary);
        }
        .vn-back-link svg { width: 16px; height: 16px; }

        /* Responsive */
        @media (max-width: 960px) {
            .vn-layout {
                grid-template-columns: 1fr;
            }
            .vn-sidebar { order: -1; }
            .vn-iframe-wrap { padding-bottom: 80%; }
        }
        @media (max-width: 640px) {
            .vn-container { padding: 0 1rem; }
            .vn-layout { gap: 1rem; }
            .vn-iframe-wrap { padding-bottom: 135%; min-height: 520px; }
            .vn-seo-section { padding: 1.5rem; }
            .vn-viewer-header { padding: 0.85rem 1rem; align-items: flex-start; gap: 0.75rem; }
            .vn-viewer-title { font-size: 0.9rem; line-height: 1.35; }
            .vn-share-row { grid-template-columns: 1fr; }
            .vn-engagement { padding: 1rem; }
            .vn-engagement-actions { width: 100%; }
            .vn-action-btn { justify-content: center; flex: 1 1 auto; }
            .vn-comment.reply { margin-left: 0.55rem; }
            .vn-breadcrumbs { font-size: 0.78rem; }
        }

        /* Animation */
        .vn-fade-in {
            animation: vnFadeIn 0.5s ease-out both;
        }
        @keyframes vnFadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="vn-page">
    <?php include '../header.php'; ?>

    <div class="main-content">
        <div class="vn-container">
            <!-- Breadcrumbs -->
            <nav class="vn-breadcrumbs vn-fade-in" aria-label="Breadcrumb navigation">
                <a href="<?= $assetBase ?>">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                    Home
                </a>
                <span class="vn-bc-sep"><svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></span>
                <a href="<?= $assetBase ?>class-notes">Class Notes</a>
                <span class="vn-bc-sep"><svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></span>
                <a href="<?= $assetBase ?>class-notes?class=<?= $note['class'] ?>"><?= $classShort ?></a>
                <span class="vn-bc-sep"><svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></span>
                <span class="vn-bc-current"><?= $noteTitle ?></span>
            </nav>

            <!-- Main Layout -->
            <div class="vn-layout">
                <!-- Viewer Column -->
                <div class="vn-fade-in" style="animation-delay: 0.1s">
                    <div class="vn-viewer">
                        <div class="vn-viewer-header">
                            <div class="vn-viewer-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                <?= $noteTitle ?>
                            </div>
                            <span class="vn-file-badge">
                                <svg viewBox="0 0 16 16" fill="currentColor" width="12" height="12"><path d="M4 0a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V4.5L9.5 0H4zm5.5 1.5v3h3"/></svg>
                                <?= $fileTypeBadge ?>
                            </span>
                        </div>
                        <div class="vn-iframe-wrap">
                            <div class="vn-iframe-loading" id="vnLoading">
                                <div class="vn-spinner"></div>
                                <span>Loading document preview...</span>
                            </div>
                            <iframe
                                src="<?= htmlspecialchars($embedUrl) ?>"
                                allowfullscreen
                                title="<?= $noteTitle ?> - <?= $classShort ?> <?= $subjectName ?> Study Material Preview"
                                onload="document.getElementById('vnLoading').classList.add('loaded')"
                            ></iframe>
                        </div>
                    </div>

                    <section class="vn-engagement" id="engagement">
                        <div class="vn-engagement-head">
                            <h2 class="vn-engagement-title" id="comments">Student Discussion (<?= $commentCount ?>)</h2>
                            <div class="vn-engagement-actions">
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="note_action" value="toggle_like">
                                    <input type="hidden" name="note_csrf_token" value="<?= htmlspecialchars($noteCsrfToken) ?>">
                                    <button type="submit" class="vn-action-btn <?= $userLiked ? 'liked' : '' ?>">
                                        <span><?= $userLiked ? 'Liked' : 'Like' ?></span>
                                        <strong><?= $likeCount ?></strong>
                                    </button>
                                </form>
                                <a class="vn-action-btn" href="<?= htmlspecialchars($note['drive_url']) ?>" target="_blank" rel="noopener">Open File</a>
                            </div>
                        </div>

                        <?php if ($engagementError): ?>
                            <div class="vn-flash error"><?= htmlspecialchars($engagementError) ?></div>
                        <?php endif; ?>

                        <div class="vn-share-row">
                            <input type="text" id="shareLink" value="<?= htmlspecialchars($canonicalUrl) ?>" readonly aria-label="Share link">
                            <button type="button" class="vn-action-btn primary" onclick="copyShareLink()">Copy Share Link</button>
                        </div>

                        <?php if ($isLoggedIn): ?>
                            <form method="POST" class="vn-comment-form">
                                <input type="hidden" name="note_action" value="comment">
                                <input type="hidden" name="note_csrf_token" value="<?= htmlspecialchars($noteCsrfToken) ?>">
                                <textarea name="comment_text" maxlength="2000" required placeholder="Ask a question or share a useful study tip..."></textarea>
                                <div style="display:flex;justify-content:flex-end;margin-top:0.6rem;">
                                    <button type="submit" class="vn-action-btn primary">Post Comment</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="vn-login-note">
                                <a href="<?= htmlspecialchars($loginUrl) ?>">Log in</a> to like this note, ask a question, or reply to another student.
                            </div>
                        <?php endif; ?>

                        <div class="vn-comment-list">
                            <?php if (empty($comments)): ?>
                                <p class="vn-comment-text" style="margin:0;color:#64748b;">No comments yet. Start the discussion for this study material.</p>
                            <?php endif; ?>
                            <?php foreach ($comments as $comment): ?>
                                <article class="vn-comment">
                                    <div class="vn-comment-meta">
                                        <strong><?= htmlspecialchars($comment['user_name']) ?></strong>
                                        <span><?= date('M d, Y h:i A', strtotime($comment['created_at'])) ?></span>
                                    </div>
                                    <p class="vn-comment-text"><?= htmlspecialchars($comment['comment_text']) ?></p>
                                    <?php if ($isLoggedIn): ?>
                                        <button type="button" class="vn-action-btn" onclick="toggleReplyForm(<?= (int)$comment['id'] ?>)">Reply</button>
                                        <form method="POST" class="vn-comment-form vn-reply-form" id="reply-form-<?= (int)$comment['id'] ?>">
                                            <input type="hidden" name="note_action" value="reply">
                                            <input type="hidden" name="parent_id" value="<?= (int)$comment['id'] ?>">
                                            <input type="hidden" name="note_csrf_token" value="<?= htmlspecialchars($noteCsrfToken) ?>">
                                            <textarea name="comment_text" maxlength="2000" required placeholder="Write a professional reply..."></textarea>
                                            <div style="display:flex;justify-content:flex-end;margin-top:0.6rem;">
                                                <button type="submit" class="vn-action-btn primary">Send Reply</button>
                                            </div>
                                        </form>
                                    <?php endif; ?>

                                    <?php foreach ($comment['replies'] as $reply): ?>
                                        <article class="vn-comment reply">
                                            <div class="vn-comment-meta">
                                                <strong><?= htmlspecialchars($reply['user_name']) ?></strong>
                                                <span><?= date('M d, Y h:i A', strtotime($reply['created_at'])) ?></span>
                                            </div>
                                            <p class="vn-comment-text"><?= htmlspecialchars($reply['comment_text']) ?></p>
                                        </article>
                                    <?php endforeach; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <!-- SEO Content Section -->
                    <section class="vn-seo-section vn-fade-in" style="animation-delay: 0.3s">
                        <h2>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                            <?= $classShort ?> <?= $subjectName ?> Study Notes for Board Exam Preparation
                        </h2>
                        <p>
                            These comprehensive <?= $classShort ?> <?= $subjectName ?> study notes are designed to help students prepare effectively for Punjab Board examinations. 
                            Whether you are studying for your matric or intermediate exams, these free <?= strtolower($fileTypeLabel) ?> resources provide detailed explanations, 
                            key concepts, and important topics covered in the <?= $classLabel ?> curriculum.
                        </p>

                        <h3>Key Features of This Study Material</h3>
                        <ul>
                            <li>Comprehensive coverage of <?= $classShort ?> <?= $subjectName ?> syllabus topics<?= $chapterName ? " including {$chapterName}" : "" ?></li>
                            <li>Aligned with Punjab Board (BISE) examination pattern and marking scheme</li>
                            <li>Free to access study material in <?= $fileTypeLabel ?> format for easy reading</li>
                            <li>Suitable for <?= str_contains($note['class'], '9') || str_contains($note['class'], '10') ? 'Matric (SSC)' : 'Intermediate (HSSC)' ?> board exam preparation 2026</li>
                            <li>Uploaded by verified community members and reviewed for quality</li>
                        </ul>

                        <h3>How to Use These <?= $subjectName ?> Notes Effectively</h3>
                        <p>
                            Start by reading through the material once to understand the overall concepts. Then, focus on important definitions, 
                            formulas, and key points. Practice writing short and long answers based on these notes. For MCQ preparation, 
                            you can also use our <a href="<?= $assetBase ?>class-<?= $note['class'] ?>-all-subjects-mcqs-with-explanations" style="color: var(--vn-primary);">free online MCQ practice tests</a> 
                            for <?= $classShort ?>.
                        </p>

                        <?php if (!empty($chapterName)): ?>
                        <h3>About <?= $chapterName ?></h3>
                        <p>
                            This chapter is an important part of the <?= $classShort ?> <?= $subjectName ?> curriculum. 
                            Understanding the concepts covered in <?= $chapterName ?> is essential for scoring well in both objective and subjective portions of the board examination.
                            These notes cover all the important topics, definitions, and solved exercises from this chapter.
                        </p>
                        <?php endif; ?>
                    </section>

                    <a href="<?= $assetBase ?>class-notes<?= !empty($note['class']) ? '?class=' . $note['class'] : '' ?>" class="vn-back-link">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
                        Back to <?= $classShort ?> Notes
                    </a>
                </div>

                <!-- Sidebar -->
                <aside class="vn-sidebar vn-fade-in" style="animation-delay: 0.2s">
                    <!-- Note Details -->
                    <div class="vn-info-card">
                        <div class="vn-info-card-header">
                            <h2>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                Document Details
                            </h2>
                        </div>
                        <div class="vn-info-card-body">
                            <div class="vn-meta-row">
                                <div class="vn-meta-icon class">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 12 3 12 0v-5"/></svg>
                                </div>
                                <div>
                                    <div class="vn-meta-label">Class</div>
                                    <div class="vn-meta-value"><?= htmlspecialchars($classLabel) ?></div>
                                </div>
                            </div>
                            <div class="vn-meta-row">
                                <div class="vn-meta-icon subject">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                                </div>
                                <div>
                                    <div class="vn-meta-label">Subject</div>
                                    <div class="vn-meta-value"><?= $subjectName ?></div>
                                </div>
                            </div>
                            <?php if (!empty($chapterName)): ?>
                            <div class="vn-meta-row">
                                <div class="vn-meta-icon chapter">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                                <div>
                                    <div class="vn-meta-label">Chapter</div>
                                    <div class="vn-meta-value"><?= $chapterName ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="vn-meta-row">
                                <div class="vn-meta-icon type">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><line x1="7" y1="2" x2="7" y2="22"/><line x1="17" y1="2" x2="17" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="7" x2="7" y2="7"/><line x1="2" y1="17" x2="7" y2="17"/><line x1="17" y1="7" x2="22" y2="7"/><line x1="17" y1="17" x2="22" y2="17"/></svg>
                                </div>
                                <div>
                                    <div class="vn-meta-label">File Type</div>
                                    <div class="vn-meta-value"><?= $fileTypeLabel ?></div>
                                </div>
                            </div>
                            <div class="vn-meta-row">
                                <div class="vn-meta-icon size">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                </div>
                                <div>
                                    <div class="vn-meta-label">File Size</div>
                                    <div class="vn-meta-value"><?= $fileSize ?></div>
                                </div>
                            </div>
                            <div class="vn-meta-row">
                                <div class="vn-meta-icon date">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </div>
                                <div>
                                    <div class="vn-meta-label">Published</div>
                                    <div class="vn-meta-value"><?= $uploadDate ?></div>
                                </div>
                            </div>
                            <?php if (!empty($note['uploader_name'])): ?>
                            <div class="vn-meta-row">
                                <div class="vn-meta-icon user">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </div>
                                <div>
                                    <div class="vn-meta-label">Uploaded By</div>
                                    <div class="vn-meta-value"><?= htmlspecialchars($note['uploader_name']) ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="vn-tags">
                            <span class="vn-tag"><?= $classShort ?></span>
                            <span class="vn-tag"><?= $subjectName ?></span>
                            <?php if ($chapterName): ?>
                                <span class="vn-tag"><?= $chapterName ?></span>
                            <?php endif; ?>
                            <span class="vn-tag"><?= $fileTypeBadge ?></span>
                            <span class="vn-tag">Free</span>
                            <span class="vn-tag">Board Exam</span>
                        </div>
                    </div>

                    <div class="vn-info-card">
                        <div class="vn-info-card-header">
                            <h2>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                                Notes Description
                            </h2>
                        </div>
                        <div class="vn-info-card-body">
                            <p style="margin:0;color:#475569;line-height:1.75;font-size:.92rem;">
                                <?= !empty($note['description']) ? nl2br(htmlspecialchars($note['description'])) : "Free {$classShort} {$subjectName} study material for board exam preparation. Use this preview to revise important concepts, chapter notes, and classroom learning points." ?>
                            </p>
                        </div>
                    </div>

                    <!-- Related Notes -->
                    <?php if (count($relatedNotes) > 0): ?>
                    <div class="vn-info-card">
                        <div class="vn-info-card-header">
                            <h2>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                                Related Study Notes
                            </h2>
                        </div>
                        <div class="vn-info-card-body" style="padding: 0.75rem;">
                            <div class="vn-related-list">
                                <?php foreach ($relatedNotes as $rel): ?>
                                <a href="<?= htmlspecialchars(vnNoteUrl($assetBase, $rel)) ?>" class="vn-related-item">
                                    <div class="vn-related-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    </div>
                                    <div>
                                        <div class="vn-related-title"><?= htmlspecialchars($rel['title']) ?></div>
                                        <div class="vn-related-sub"><?= htmlspecialchars($rel['subject'] ?? '') ?><?= !empty($rel['chapter']) ? ' &middot; ' . htmlspecialchars($rel['chapter']) : '' ?></div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>
    <script>
    function copyShareLink() {
        const input = document.getElementById('shareLink');
        input.select();
        input.setSelectionRange(0, 99999);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value);
        } else {
            document.execCommand('copy');
        }
    }

    function toggleReplyForm(commentId) {
        const form = document.getElementById('reply-form-' + commentId);
        if (form) {
            form.classList.toggle('open');
            const textarea = form.querySelector('textarea');
            if (form.classList.contains('open') && textarea) {
                textarea.focus();
            }
        }
    }
    </script>
</body>
</html>
