<?php
include '../db_connect.php';

// Start session for user upload button
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function cnSlugify($text) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'notes';
}

function cnListingUrl($assetBase, $class = '', $subject = '') {
    if ($class !== '' && $subject !== '') {
        return ($assetBase ?? '') . 'class-notes/class-' . rawurlencode((string)$class) . '-' . cnSlugify($subject) . '-notes';
    }
    if ($class !== '') {
        return ($assetBase ?? '') . 'class-notes/class-' . rawurlencode((string)$class) . '-notes';
    }
    return ($assetBase ?? '') . 'class-notes';
}

// Get filter parameters
$classFilter = isset($_GET['class']) ? trim($_GET['class']) : '';
$subjectFilter = isset($_GET['subject']) ? trim($_GET['subject']) : '';
$subjectSlugFilter = isset($_GET['subject_slug']) ? trim($_GET['subject_slug']) : '';
$chapterFilter = isset($_GET['chapter']) ? trim($_GET['chapter']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$fileType = isset($_GET['file_type']) ? trim($_GET['file_type']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

if ($subjectFilter === '' && $subjectSlugFilter !== '') {
    if (!empty($classFilter) && in_array($classFilter, ['9','10','11','12'])) {
        $subjectResolveStmt = $conn->prepare("SELECT DISTINCT subject FROM class_notes WHERE class = ? AND subject IS NOT NULL AND subject != ''");
        $subjectResolveStmt->bind_param('s', $classFilter);
    } else {
        $subjectResolveStmt = $conn->prepare("SELECT DISTINCT subject FROM class_notes WHERE subject IS NOT NULL AND subject != ''");
    }
    $subjectResolveStmt->execute();
    $subjectResolveResult = $subjectResolveStmt->get_result();
    while ($subjectResolveRow = $subjectResolveResult->fetch_assoc()) {
        if (cnSlugify($subjectResolveRow['subject']) === $subjectSlugFilter) {
            $subjectFilter = $subjectResolveRow['subject'];
            break;
        }
    }
    $subjectResolveStmt->close();
}

// Build query for approved notes only
$whereConditions = ["n.status = 'approved'"];
$params = [];
$types = '';

if (!empty($classFilter) && in_array($classFilter, ['9','10','11','12'])) {
    $whereConditions[] = "n.class = ?";
    $params[] = $classFilter;
    $types .= 's';
}

if (!empty($subjectFilter)) {
    $whereConditions[] = "n.subject = ?";
    $params[] = $subjectFilter;
    $types .= 's';
}

if (!empty($chapterFilter)) {
    $whereConditions[] = "n.chapter = ?";
    $params[] = $chapterFilter;
    $types .= 's';
}

if (!empty($search)) {
    $whereConditions[] = "(n.title LIKE ? OR n.description LIKE ? OR n.subject LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

if (!empty($fileType)) {
    if ($fileType === 'images') {
        $whereConditions[] = "n.mime_type LIKE 'image/%'";
    } elseif ($fileType === 'pdf') {
        $whereConditions[] = "n.mime_type = 'application/pdf'";
    } elseif ($fileType === 'ppt') {
        $whereConditions[] = "(n.mime_type LIKE '%presentation%' OR n.mime_type LIKE '%powerpoint%')";
    } elseif ($fileType === 'doc') {
        $whereConditions[] = "(n.mime_type LIKE '%word%' OR n.mime_type LIKE '%document%')";
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM class_notes n $whereClause";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalNotes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$totalPages = max(1, ceil($totalNotes / $perPage));

// Fetch notes with pagination
$query = "SELECT n.* FROM class_notes n $whereClause ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

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

// Get distinct subjects for filter
$subjectsQuery = "SELECT DISTINCT subject FROM class_notes WHERE status = 'approved' AND subject IS NOT NULL AND subject != '' ORDER BY subject ASC";
$subjectsResult = $conn->query($subjectsQuery);
$subjects = [];
while ($row = $subjectsResult->fetch_assoc()) {
    $subjects[] = $row['subject'];
}

// Get distinct chapters for selected subject
$chapters = [];
if (!empty($subjectFilter)) {
    $chapQuery = "SELECT DISTINCT chapter FROM class_notes WHERE status = 'approved' AND subject = ? AND chapter IS NOT NULL AND chapter != '' ORDER BY chapter ASC";
    $chapStmt = $conn->prepare($chapQuery);
    $chapStmt->bind_param('s', $subjectFilter);
    $chapStmt->execute();
    $chapResult = $chapStmt->get_result();
    while ($row = $chapResult->fetch_assoc()) {
        $chapters[] = $row['chapter'];
    }
    $chapStmt->close();
}

// Class labels
$classLabels = [
    '9' => 'Class 9 (Matric Part 1)',
    '10' => 'Class 10 (Matric Part 2)',
    '11' => 'Class 11 (Intermediate Part 1)',
    '12' => 'Class 12 (Intermediate Part 2)'
];

// SEO
$classLabel = !empty($classFilter) ? ($classLabels[$classFilter] ?? "Class $classFilter") : '';
$classShort = !empty($classFilter) ? "Class $classFilter" : '';

$pageTitle = "Free Class Notes & Study Materials for Board Exam Preparation | Ahmad Learning Hub";
$pageDescription = "Download free study notes, PDF materials, and presentations for Class 9, 10, 11, and 12. Community-uploaded study resources for Punjab Board matric and intermediate exam preparation 2026.";
$metaKeywords = "class notes, free study notes, class 9 notes, class 10 notes, class 11 notes, class 12 notes, matric notes, intermediate notes, board exam preparation, Punjab board notes, BISE exam notes, free PDF notes, study materials Pakistan, SSC notes, HSSC notes";

if (!empty($classLabel)) {
    $pageTitle = "{$classLabel} Notes - Free Study Materials & Board Exam Notes | Ahmad Learning Hub";
    $pageDescription = "Download free {$classLabel} study notes, PDF materials, and presentations. Community-uploaded and admin-curated resources for Punjab Board exam preparation 2026.";
    $metaKeywords = "{$classShort} notes, {$classLabel} study material, free {$classShort} PDF notes, {$classShort} board exam preparation, Punjab board {$classShort}, BISE notes {$classShort}, {$metaKeywords}";
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$currentUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$siteUrl = $protocol . "://" . $_SERVER['HTTP_HOST'];
$canonicalPath = '/' . ltrim(cnListingUrl('', $classFilter, $subjectFilter), '/');
if ($page > 1) {
    $canonicalPath .= '?page=' . $page;
}
$canonicalUrl = $siteUrl . $canonicalPath;
$currentPathWithQuery = ltrim($_SERVER['REQUEST_URI'] ?? '', '/');
$targetPath = ltrim($canonicalPath, '/');
$hasNonSeoFilters = $chapterFilter !== '' || $search !== '' || $fileType !== '';
if (!$hasNonSeoFilters && $page === 1 && ($classFilter !== '' || $subjectFilter !== '') && $currentPathWithQuery !== $targetPath) {
    header('Location: ' . $canonicalPath, true, 301);
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);

// Helper: file type SVG icons
function cnGetFileIcon($mime) {
    if (str_contains($mime, 'pdf'))
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
    if (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint'))
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>';
    if (str_contains($mime, 'word') || str_contains($mime, 'document'))
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>';
    if (str_contains($mime, 'image'))
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
}

function cnGetFileTypeLabel($mime) {
    if (str_contains($mime, 'pdf')) return 'PDF';
    if (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint')) return 'PPT';
    if (str_contains($mime, 'word') || str_contains($mime, 'document')) return 'DOCX';
    if (str_contains($mime, 'image/png')) return 'PNG';
    if (str_contains($mime, 'image/jpeg')) return 'JPG';
    if (str_contains($mime, 'image/webp')) return 'WEBP';
    if (str_contains($mime, 'image')) return 'IMG';
    return 'FILE';
}

function cnFormatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function cnNoteUrl($assetBase, $note) {
    return ($assetBase ?? '') . 'class-notes/class-' . rawurlencode((string)$note['class']) . '-' . cnSlugify($note['subject'] ?? 'general') . '-' . cnSlugify($note['title'] ?? 'study-notes') . '-' . (int)$note['id'];
}

// File type color mapping
function cnGetTypeColor($mime) {
    if (str_contains($mime, 'pdf')) return '#ef4444';
    if (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint')) return '#f97316';
    if (str_contains($mime, 'word') || str_contains($mime, 'document')) return '#3b82f6';
    if (str_contains($mime, 'image')) return '#8b5cf6';
    return '#6366f1';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">

    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($currentUrl) ?>">

    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link rel="stylesheet" href="<?= $assetBase ?>css/notes.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "CollectionPage",
        "name": "<?= addslashes($pageTitle) ?>",
        "description": "<?= addslashes($pageDescription) ?>",
        "url": "<?= $canonicalUrl ?>",
        "isPartOf": { "@type": "WebSite", "name": "Ahmad Learning Hub", "url": "<?= $siteUrl ?>" },
        "about": {
            "@type": "EducationalOrganization",
            "name": "Ahmad Learning Hub"
        },
        "numberOfItems": <?= $totalNotes ?>,
        "mainEntity": {
            "@type": "ItemList",
            "numberOfItems": <?= $totalNotes ?>,
            "itemListElement": [
                <?php foreach (array_slice($notes, 0, 6) as $idx => $n): ?>
                {
                    "@type": "ListItem",
                    "position": <?= $idx + 1 ?>,
                    "item": {
                        "@type": "DigitalDocument",
                        "name": "<?= addslashes(htmlspecialchars($n['title'])) ?>",
                        "url": "<?= $siteUrl ?>/<?= ltrim(cnNoteUrl($assetBase, $n), '/') ?>",
                        "encodingFormat": "<?= htmlspecialchars($n['mime_type']) ?>",
                        "isAccessibleForFree": true
                    }
                }<?= $idx < min(count($notes), 6) - 1 ? ',' : '' ?>
                <?php endforeach; ?>
            ]
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            { "@type": "ListItem", "position": 1, "name": "Home", "item": "<?= $siteUrl ?>" },
            { "@type": "ListItem", "position": 2, "name": "Study Materials", "item": "<?= $siteUrl ?>/study-material-for-board-exam-preparations" },
            { "@type": "ListItem", "position": 3, "name": "<?= !empty($classLabel) ? addslashes($classLabel) . ' Notes' : 'Class Notes' ?>" }
        ]
    }
    </script>

    <style>
        /* ============================================================
           Class Notes Page — Premium Redesign
           ============================================================ */
        :root {
            --cn-primary: #6366f1;
            --cn-primary-light: #818cf8;
            --cn-primary-dark: #4f46e5;
            --cn-primary-glow: rgba(99, 102, 241, 0.12);
            --cn-accent: #38bdf8;
            --cn-accent-glow: rgba(56, 189, 248, 0.1);
            --cn-bg: #f1f3f6;
            --cn-bg-card: #ffffff;
            --cn-bg-elevated: #f9fafc;
            --cn-surface: #f1f3f6;
            --cn-border: rgba(0, 0, 0, 0.08);
            --cn-border-hover: rgba(99, 102, 241, 0.3);
            --cn-text: #4a5568;
            --cn-text-muted: #6c757d;
            --cn-text-heading: #2c3e50;
            --cn-success: #27ae60;
            --cn-warning: #f39c12;
            --cn-danger: #e74c3c;
            --cn-radius-lg: 20px;
            --cn-radius: 14px;
            --cn-radius-sm: 10px;
            --cn-radius-xs: 6px;
            --cn-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            --cn-shadow-lg: 0 12px 36px rgba(0, 0, 0, 0.1);
            --cn-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .cn-page {
            background: var(--cn-bg);
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        .cn-container {
            max-width: 1340px;
            margin: 0 auto;
            padding: 0 1.5rem 4rem;
        }

        /* ---- Hero Section ---- */
        .cn-hero {
            position: relative;
            text-align: center;
            padding: 4rem 2rem 3.5rem;
            background: linear-gradient(160deg, #050810 0%, #0f172a 40%, #1a1040 70%, #0c1425 100%);
            overflow: hidden;
            border-bottom: 1px solid var(--cn-border);
        }
        .cn-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 600px 500px at 25% 30%, rgba(99,102,241,0.18) 0%, transparent 70%),
                radial-gradient(ellipse 500px 400px at 75% 70%, rgba(56,189,248,0.12) 0%, transparent 70%),
                radial-gradient(ellipse 300px 300px at 50% 50%, rgba(139,92,246,0.1) 0%, transparent 60%);
            pointer-events: none;
            animation: cnHeroGlow 8s ease-in-out infinite alternate;
        }
        @keyframes cnHeroGlow {
            0% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .cn-hero::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 120px;
            background: linear-gradient(to top, #f1f3f6, transparent);
            pointer-events: none;
        }
        .cn-hero-content { position: relative; z-index: 2; max-width: 780px; margin: 0 auto; }
        .cn-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 1.1rem;
            border-radius: 50px;
            background: rgba(99,102,241,0.12);
            border: 1px solid rgba(99,102,241,0.2);
            color: var(--cn-primary-light);
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin-bottom: 1.5rem;
        }
        .cn-hero-badge svg { width: 14px; height: 14px; }
        .cn-hero h1 {
            font-size: clamp(2rem, 5vw, 3.2rem);
            font-weight: 900;
            line-height: 1.15;
            margin-bottom: 1rem;
            color: #ffffff;
            letter-spacing: -0.02em;
        }
        .cn-hero h1 .cn-gradient-text {
            background: linear-gradient(135deg, #818cf8 0%, #38bdf8 50%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .cn-hero-desc {
            color: var(--cn-text-muted);
            font-size: 1.05rem;
            line-height: 1.75;
            max-width: 640px;
            margin: 0 auto 2rem;
        }

        /* Class Tabs */
        .cn-class-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .cn-class-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.6rem 1.4rem;
            border-radius: 50px;
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(10px);
            color: var(--cn-text-muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid rgba(255,255,255,0.06);
            transition: var(--cn-transition);
        }
        .cn-class-tab svg { width: 15px; height: 15px; }
        .cn-class-tab:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.12);
            color: var(--cn-text);
        }
        .cn-class-tab.active {
            background: linear-gradient(135deg, rgba(99,102,241,0.25), rgba(139,92,246,0.2));
            border-color: rgba(99,102,241,0.4);
            color: #fff;
            box-shadow: 0 0 20px rgba(99,102,241,0.15);
        }

        /* ---- Stats Strip ---- */
        .cn-stats-strip {
            display: flex;
            justify-content: center;
            gap: 2.5rem;
            padding: 1.5rem 0;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }
        .cn-stat {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: var(--cn-text-muted);
            font-size: 0.88rem;
        }
        .cn-stat-icon {
            width: 38px;
            height: 38px;
            border-radius: var(--cn-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cn-stat-icon svg { width: 18px; height: 18px; }
        .cn-stat-icon.purple { background: rgba(99,102,241,0.1); color: #6366f1; }
        .cn-stat-icon.green { background: rgba(39,174,96,0.1); color: #27ae60; }
        .cn-stat-icon.blue { background: rgba(52,152,219,0.1); color: #3498db; }
        .cn-stat-number { font-weight: 800; color: var(--cn-text-heading); font-size: 1.1rem; }

        /* ---- Actions Bar ---- */
        .cn-actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .cn-section-heading {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--cn-text-heading);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0;
        }
        .cn-section-heading svg { width: 22px; height: 22px; color: var(--cn-primary); }
        .cn-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.75rem 1.6rem;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            border: none;
            border-radius: var(--cn-radius);
            font-weight: 700;
            font-size: 0.88rem;
            text-decoration: none;
            cursor: pointer;
            transition: var(--cn-transition);
            box-shadow: 0 4px 20px rgba(99,102,241,0.25);
            position: relative;
            overflow: hidden;
        }
        .cn-upload-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 40%, rgba(255,255,255,0.1) 50%, transparent 60%);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }
        .cn-upload-btn:hover::before { transform: translateX(100%); }
        .cn-upload-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(99,102,241,0.35); }
        .cn-upload-btn svg { width: 18px; height: 18px; }

        /* ---- Filters Panel ---- */
        .cn-filters {
            background: var(--cn-bg-card);
            border: 1px solid var(--cn-border);
            border-radius: var(--cn-radius-lg);
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .cn-filters::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--cn-primary), var(--cn-accent), var(--cn-primary));
            background-size: 200% 100%;
            animation: cnGradientSlide 4s linear infinite;
        }
        @keyframes cnGradientSlide {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .cn-filters-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1.25rem;
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--cn-text-heading);
        }
        .cn-filters-header svg { width: 18px; height: 18px; color: var(--cn-primary); }
        .cn-filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .cn-filter-group label {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--cn-text-muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .cn-filter-group label svg { width: 14px; height: 14px; }
        .cn-filter-group select,
        .cn-filter-group input {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: var(--cn-radius-sm);
            font-size: 0.92rem;
            background: #fff;
            color: var(--cn-text-heading);
            transition: var(--cn-transition);
            font-family: 'Inter', sans-serif;
        }
        .cn-filter-group select:focus,
        .cn-filter-group input:focus {
            outline: none;
            border-color: var(--cn-primary);
            box-shadow: 0 0 0 3px var(--cn-primary-glow);
        }
        .cn-filter-group select option { background: #fff; }

        /* File Type Pills */
        .cn-type-pills {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1.25rem;
        }
        .cn-type-label {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 600;
            color: var(--cn-text-muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-right: 0.25rem;
        }
        .cn-type-label svg { width: 14px; height: 14px; }
        .cn-type-pill {
            padding: 0.4rem 1rem;
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: var(--cn-transition);
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-family: 'Inter', sans-serif;
        }
        .cn-type-pill svg { width: 13px; height: 13px; }
        .cn-type-pill:hover {
            background: #f8fafc;
            border-color: #c7d2fe;
            color: #4f46e5;
        }
        .cn-type-pill.active {
            background: linear-gradient(135deg, var(--cn-primary), #8b5cf6);
            color: #fff;
            border-color: var(--cn-primary);
            box-shadow: 0 2px 12px rgba(99,102,241,0.25);
        }

        /* Filter Buttons */
        .cn-filter-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .cn-filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.65rem 1.4rem;
            border: none;
            border-radius: var(--cn-radius-sm);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--cn-transition);
            font-family: 'Inter', sans-serif;
        }
        .cn-filter-btn svg { width: 15px; height: 15px; }
        .cn-filter-btn.search {
            background: linear-gradient(135deg, var(--cn-primary), #8b5cf6);
            color: #fff;
            box-shadow: 0 2px 10px rgba(99,102,241,0.2);
        }
        .cn-filter-btn.search:hover { box-shadow: 0 4px 16px rgba(99,102,241,0.35); }
        .cn-filter-btn.clear {
            background: #fff;
            color: #64748b;
            text-decoration: none;
            border: 2px solid #e2e8f0;
        }
        .cn-filter-btn.clear:hover { border-color: #c7d2fe; color: #4f46e5; }

        /* ---- Results Bar ---- */
        .cn-results-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 1.2rem;
            background: var(--cn-bg-card);
            border-radius: var(--cn-radius);
            margin-bottom: 1.75rem;
            border: 1px solid var(--cn-border);
            font-size: 0.88rem;
            color: var(--cn-text-muted);
        }
        .cn-results-bar .cn-results-count {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .cn-results-bar .cn-results-count svg { width: 16px; height: 16px; color: var(--cn-primary); }
        .cn-results-bar .cn-results-count strong { color: var(--cn-primary-light); }
        .cn-results-bar .cn-results-free {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--cn-success);
            font-weight: 600;
        }
        .cn-results-bar .cn-results-free svg { width: 15px; height: 15px; }

        /* ---- Notes Grid ---- */
        .cn-notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
            gap: 1.5rem;
        }

        /* ---- Note Card ---- */
        .cn-card {
            background: var(--cn-bg-card);
            border-radius: var(--cn-radius-lg);
            overflow: hidden;
            border: 1px solid var(--cn-border);
            display: flex;
            flex-direction: column;
            transition: var(--cn-transition);
            position: relative;
        }
        .cn-card::before {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: var(--cn-radius-lg);
            padding: 1px;
            background: linear-gradient(135deg, transparent 40%, var(--cn-primary) 100%);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.4s;
            pointer-events: none;
        }
        .cn-card:hover::before { opacity: 1; }
        .cn-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--cn-shadow-lg);
        }
        .cn-card-header {
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
        }
        .cn-card-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .cn-card-icon-wrap svg { width: 24px; height: 24px; color: #fff; }
        .cn-card-badge {
            padding: 0.25rem 0.65rem;
            border-radius: 50px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .cn-card-body { padding: 0 1.5rem 1.25rem; flex: 1; display: flex; flex-direction: column; }
        .cn-card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--cn-text-heading);
            margin-bottom: 0.4rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .cn-card-desc {
            color: var(--cn-text-muted);
            font-size: 0.85rem;
            line-height: 1.55;
            margin-bottom: 0.85rem;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .cn-card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 0.85rem;
        }
        .cn-tag {
            padding: 0.2rem 0.6rem;
            border-radius: var(--cn-radius-xs);
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .cn-tag svg { width: 11px; height: 11px; }
        .cn-tag.class-tag { background: #dbeafe; color: #1d4ed8; }
        .cn-tag.subject-tag { background: #dcfce7; color: #15803d; }
        .cn-tag.chapter-tag { background: #fef3c7; color: #b45309; }
        .cn-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.75rem;
            border-top: 1px solid var(--cn-border);
            font-size: 0.76rem;
            color: var(--cn-text-muted);
        }
        .cn-card-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .cn-card-meta svg { width: 13px; height: 13px; }

        /* Card Action */
        .cn-card-actions {
            padding: 0.85rem 1.5rem;
            border-top: 1px solid var(--cn-border);
        }
        .cn-view-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.7rem;
            border: none;
            border-radius: var(--cn-radius-sm);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            transition: var(--cn-transition);
            width: 100%;
            background: rgba(99,102,241,0.1);
            color: var(--cn-primary-light);
            border: 1px solid rgba(99,102,241,0.15);
        }
        .cn-view-btn svg { width: 16px; height: 16px; }
        .cn-view-btn:hover {
            background: linear-gradient(135deg, var(--cn-primary), #8b5cf6);
            color: #fff;
            border-color: var(--cn-primary);
            box-shadow: 0 4px 16px rgba(99,102,241,0.3);
            transform: translateY(-1px);
        }

        /* ---- Empty State ---- */
        .cn-empty {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--cn-bg-card);
            border-radius: var(--cn-radius-lg);
            border: 1px solid var(--cn-border);
        }
        .cn-empty-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 1.25rem;
            border-radius: 50%;
            background: rgba(99,102,241,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cn-empty-icon svg { width: 32px; height: 32px; color: var(--cn-primary); }
        .cn-empty h3 { font-size: 1.3rem; font-weight: 700; color: var(--cn-text-heading); margin-bottom: 0.5rem; }
        .cn-empty p { color: var(--cn-text-muted); font-size: 0.95rem; max-width: 420px; margin: 0 auto; }

        /* ---- Pagination ---- */
        .cn-pagination {
            display: flex;
            justify-content: center;
            gap: 0.4rem;
            margin-top: 2.5rem;
            flex-wrap: wrap;
        }
        .cn-page-btn {
            padding: 0.55rem 1rem;
            border-radius: var(--cn-radius-sm);
            background: #fff;
            border: 2px solid #e2e8f0;
            color: #475569;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: var(--cn-transition);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .cn-page-btn svg { width: 14px; height: 14px; }
        .cn-page-btn:hover { border-color: #6366f1; color: #6366f1; }
        .cn-page-btn.active {
            background: linear-gradient(135deg, var(--cn-primary), #8b5cf6);
            color: #fff;
            border-color: var(--cn-primary);
        }
        .cn-page-btn.disabled { opacity: 0.3; pointer-events: none; }

        /* ---- Upload CTA ---- */
        .cn-cta {
            margin-top: 3rem;
            padding: 3rem 2rem;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            border: none;
            border-radius: var(--cn-radius-lg);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cn-cta::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 500px 300px at 30% 50%, rgba(99,102,241,0.1) 0%, transparent 70%),
                radial-gradient(ellipse 500px 300px at 70% 50%, rgba(56,189,248,0.07) 0%, transparent 70%);
            pointer-events: none;
        }
        .cn-cta-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 1.25rem;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--cn-primary), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
        }
        .cn-cta-icon svg { width: 24px; height: 24px; color: #fff; }
        .cn-cta h2 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.6rem;
            color: #fff;
            position: relative;
            z-index: 1;
        }
        .cn-cta p {
            color: #94a3b8;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.7;
        }

        /* ---- SEO Content Section ---- */
        .cn-seo-content {
            margin-top: 3rem;
            padding: 2.5rem;
            background: var(--cn-bg-card);
            border: 1px solid var(--cn-border);
            border-radius: var(--cn-radius-lg);
        }
        .cn-seo-content h2 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--cn-text-heading);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .cn-seo-content h2 svg { width: 22px; height: 22px; color: var(--cn-primary); }
        .cn-seo-content p {
            color: var(--cn-text-muted);
            line-height: 1.8;
            font-size: 0.92rem;
            margin-bottom: 0.75rem;
        }
        .cn-seo-content a { color: var(--cn-primary-light); }

        /* ---- Back Link ---- */
        .cn-back-section {
            display: flex;
            justify-content: center;
            margin-top: 2.5rem;
            margin-bottom: 1rem;
        }
        .cn-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: var(--cn-radius-sm);
            color: var(--cn-text);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            transition: var(--cn-transition);
        }
        .cn-back-btn svg { width: 16px; height: 16px; }
        .cn-back-btn:hover {
            background: rgba(99,102,241,0.1);
            border-color: var(--cn-primary);
            color: var(--cn-primary-light);
        }

        /* ---- Animations ---- */
        .cn-fade-up {
            animation: cnFadeUp 0.5s ease-out both;
        }
        @keyframes cnFadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .cn-card { animation: cnFadeUp 0.5s ease-out both; }
        .cn-card:nth-child(1) { animation-delay: 0.05s; }
        .cn-card:nth-child(2) { animation-delay: 0.10s; }
        .cn-card:nth-child(3) { animation-delay: 0.15s; }
        .cn-card:nth-child(4) { animation-delay: 0.20s; }
        .cn-card:nth-child(5) { animation-delay: 0.25s; }
        .cn-card:nth-child(6) { animation-delay: 0.30s; }
        .cn-card:nth-child(7) { animation-delay: 0.35s; }
        .cn-card:nth-child(8) { animation-delay: 0.40s; }
        .cn-card:nth-child(9) { animation-delay: 0.45s; }
        .cn-card:nth-child(10) { animation-delay: 0.50s; }
        .cn-card:nth-child(11) { animation-delay: 0.55s; }
        .cn-card:nth-child(12) { animation-delay: 0.60s; }

        /* ---- Responsive ---- */
        @media (max-width: 768px) {
            .cn-hero { padding: 2.5rem 1.2rem 2rem; }
            .cn-container { padding: 0 1rem 3rem; }
            .cn-notes-grid { grid-template-columns: 1fr; }
            .cn-filters-grid { grid-template-columns: 1fr; }
            .cn-actions-bar { flex-direction: column; align-items: stretch; }
            .cn-results-bar { flex-direction: column; gap: 0.5rem; text-align: center; }
            .cn-stats-strip { gap: 1.5rem; }
            .cn-seo-content { padding: 1.5rem; }
        }
    </style>
</head>
<body class="cn-page">
    <?php include '../header.php'; ?>

    <div class="main-content">
        <!-- Hero Section -->
        <header class="cn-hero">
            <div class="cn-hero-content">
                <div class="cn-hero-badge cn-fade-up">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 12 3 12 0v-5"/></svg>
                    Free Educational Resources
                </div>
                <h1 class="cn-fade-up" style="animation-delay: 0.1s">
                    <span class="cn-gradient-text">Class Notes</span> &amp; Study Materials
                    <?php if (!empty($classLabel)): ?>
                        <br><span style="font-size: 0.55em; opacity: 0.7; font-weight: 600;"><?= htmlspecialchars($classLabel) ?></span>
                    <?php endif; ?>
                </h1>
                <p class="cn-hero-desc cn-fade-up" style="animation-delay: 0.2s">
                    Access free, high-quality study notes for Class 9, 10, 11, and 12 board exam preparation.
                    PDF notes, presentations, and study materials curated by teachers and top students across Punjab.
                </p>

                <nav class="cn-class-tabs cn-fade-up" style="animation-delay: 0.3s" aria-label="Filter notes by class">
                    <a href="<?= htmlspecialchars(cnListingUrl($assetBase)) ?>" class="cn-class-tab <?= empty($classFilter) ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        All Classes
                    </a>
                    <a href="<?= htmlspecialchars(cnListingUrl($assetBase, '9')) ?>" class="cn-class-tab <?= $classFilter === '9' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                        Class 9
                    </a>
                    <a href="<?= htmlspecialchars(cnListingUrl($assetBase, '10')) ?>" class="cn-class-tab <?= $classFilter === '10' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                        Class 10
                    </a>
                    <a href="<?= htmlspecialchars(cnListingUrl($assetBase, '11')) ?>" class="cn-class-tab <?= $classFilter === '11' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                        Class 11
                    </a>
                    <a href="<?= htmlspecialchars(cnListingUrl($assetBase, '12')) ?>" class="cn-class-tab <?= $classFilter === '12' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                        Class 12
                    </a>
                </nav>
            </div>
        </header>

        <div class="cn-container">
            <!-- Stats Strip -->
            <div class="cn-stats-strip cn-fade-up" style="animation-delay: 0.1s">
                <div class="cn-stat">
                    <div class="cn-stat-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div>
                        <div class="cn-stat-number"><?= $totalNotes ?></div>
                        <div>Study Materials</div>
                    </div>
                </div>
                <div class="cn-stat">
                    <div class="cn-stat-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div>
                        <div class="cn-stat-number">100%</div>
                        <div>Free Access</div>
                    </div>
                </div>
                <div class="cn-stat">
                    <div class="cn-stat-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                    </div>
                    <div>
                        <div class="cn-stat-number">Community</div>
                        <div>Contributed</div>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="cn-actions-bar cn-fade-up" style="animation-delay: 0.15s">
                <h2 class="cn-section-heading">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                    <?= !empty($classLabel) ? htmlspecialchars($classLabel) . ' Notes' : 'Browse Study Notes' ?>
                </h2>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= $assetBase ?>upload-notes" class="cn-upload-btn" id="cnUploadBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload Your Notes
                    </a>
                <?php else: ?>
                    <a href="<?= $assetBase ?>auth/login.php" class="cn-upload-btn" id="cnLoginUploadBtn" onclick="sessionStorage.setItem('redirect_after_login', '<?= $assetBase ?>upload-notes');">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Login to Upload Notes
                    </a>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <div class="cn-filters cn-fade-up" style="animation-delay: 0.2s">
                <div class="cn-filters-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filter &amp; Search Notes
                </div>
                <form method="GET" action="" id="cnFilterForm">
                    <?php if (!empty($classFilter)): ?>
                        <input type="hidden" name="class" value="<?= htmlspecialchars($classFilter) ?>">
                    <?php endif; ?>

                    <div class="cn-filters-grid">
                        <?php if (empty($classFilter)): ?>
                        <div class="cn-filter-group">
                            <label for="cn_class">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 12 3 12 0v-5"/></svg>
                                Class
                            </label>
                            <select name="class" id="cn_class">
                                <option value="">All Classes</option>
                                <option value="9" <?= $classFilter === '9' ? 'selected' : '' ?>>Class 9</option>
                                <option value="10" <?= $classFilter === '10' ? 'selected' : '' ?>>Class 10</option>
                                <option value="11" <?= $classFilter === '11' ? 'selected' : '' ?>>Class 11</option>
                                <option value="12" <?= $classFilter === '12' ? 'selected' : '' ?>>Class 12</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="cn-filter-group">
                            <label for="cn_subject">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                                Subject
                            </label>
                            <select name="subject" id="cn_subject" onchange="this.form.submit()">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $subj): ?>
                                    <option value="<?= htmlspecialchars($subj) ?>" <?= $subjectFilter === $subj ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subj) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if (!empty($chapters)): ?>
                        <div class="cn-filter-group">
                            <label for="cn_chapter">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                Chapter
                            </label>
                            <select name="chapter" id="cn_chapter">
                                <option value="">All Chapters</option>
                                <?php foreach ($chapters as $chap): ?>
                                    <option value="<?= htmlspecialchars($chap) ?>" <?= $chapterFilter === $chap ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($chap) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="cn-filter-group">
                            <label for="cn_search">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                Search
                            </label>
                            <input type="text" name="search" id="cn_search" placeholder="Search notes by title, subject..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="cn-type-pills">
                        <span class="cn-type-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                            Type:
                        </span>
                        <button type="submit" name="file_type" value="" class="cn-type-pill <?= empty($fileType) ? 'active' : '' ?>">All</button>
                        <button type="submit" name="file_type" value="pdf" class="cn-type-pill <?= $fileType === 'pdf' ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            PDF
                        </button>
                        <button type="submit" name="file_type" value="ppt" class="cn-type-pill <?= $fileType === 'ppt' ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            PPT
                        </button>
                        <button type="submit" name="file_type" value="doc" class="cn-type-pill <?= $fileType === 'doc' ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            Word
                        </button>
                        <button type="submit" name="file_type" value="images" class="cn-type-pill <?= $fileType === 'images' ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            Images
                        </button>
                    </div>

                    <div class="cn-filter-actions">
                        <button type="submit" class="cn-filter-btn search">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            Search
                        </button>
                        <a href="<?= htmlspecialchars(cnListingUrl($assetBase, $classFilter)) ?>" class="cn-filter-btn clear">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                            Clear Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Results Bar -->
            <div class="cn-results-bar cn-fade-up" style="animation-delay: 0.25s">
                <div class="cn-results-count">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                    Found <strong><?= $totalNotes ?></strong> study materials<?= !empty($classLabel) ? ' for ' . htmlspecialchars($classLabel) : '' ?>
                </div>
                <div class="cn-results-free">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    All materials are free
                </div>
            </div>

            <!-- Notes Grid -->
            <?php if (count($notes) > 0): ?>
                <div class="cn-notes-grid">
                    <?php foreach ($notes as $note): ?>
                        <?php $typeColor = cnGetTypeColor($note['mime_type']); ?>
                        <article class="cn-card" id="note-<?= $note['id'] ?>">
                            <div class="cn-card-header">
                                <div class="cn-card-icon-wrap" style="background: <?= $typeColor ?>;">
                                    <?= cnGetFileIcon($note['mime_type']) ?>
                                </div>
                                <span class="cn-card-badge" style="background: <?= $typeColor ?>15; color: <?= $typeColor ?>;">
                                    <?= cnGetFileTypeLabel($note['mime_type']) ?>
                                </span>
                            </div>
                            <div class="cn-card-body">
                                <h3 class="cn-card-title"><?= htmlspecialchars($note['title']) ?></h3>
                                <?php if (!empty($note['description'])): ?>
                                    <p class="cn-card-desc"><?= htmlspecialchars($note['description']) ?></p>
                                <?php else: ?>
                                    <p class="cn-card-desc">Free study material for board exam preparation.</p>
                                <?php endif; ?>

                                <div class="cn-card-tags">
                                    <span class="cn-tag class-tag">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 12 3 12 0v-5"/></svg>
                                        Class <?= htmlspecialchars($note['class']) ?>
                                    </span>
                                    <?php if (!empty($note['subject'])): ?>
                                        <span class="cn-tag subject-tag">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                                            <?= htmlspecialchars($note['subject']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($note['chapter'])): ?>
                                        <span class="cn-tag chapter-tag">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            <?= htmlspecialchars($note['chapter']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="cn-card-meta">
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
                                        <?= cnFormatSize($note['file_size']) ?>
                                    </span>
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                        <?= date('M d, Y', strtotime($note['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="cn-card-actions">
                                <a href="<?= htmlspecialchars(cnNoteUrl($assetBase, $note)) ?>" class="cn-view-btn" id="view-note-<?= $note['id'] ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    View Study Material
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="cn-pagination" aria-label="Notes pagination">
                    <?php
                    $queryParams = $_GET;
                    unset($queryParams['page']);
                    $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');

                    // Previous
                    $queryParams['page'] = $page - 1;
                    echo '<a href="' . $baseUrl . '?' . http_build_query($queryParams) . '" class="cn-page-btn ' . ($page <= 1 ? 'disabled' : '') . '">';
                    echo '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
                    echo 'Prev</a>';

                    // Page numbers
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);

                    if ($start > 1) {
                        $queryParams['page'] = 1;
                        echo '<a href="' . $baseUrl . '?' . http_build_query($queryParams) . '" class="cn-page-btn">1</a>';
                        if ($start > 2) echo '<span class="cn-page-btn disabled">...</span>';
                    }

                    for ($i = $start; $i <= $end; $i++) {
                        $queryParams['page'] = $i;
                        echo '<a href="' . $baseUrl . '?' . http_build_query($queryParams) . '" class="cn-page-btn ' . ($i === $page ? 'active' : '') . '">' . $i . '</a>';
                    }

                    if ($end < $totalPages) {
                        if ($end < $totalPages - 1) echo '<span class="cn-page-btn disabled">...</span>';
                        $queryParams['page'] = $totalPages;
                        echo '<a href="' . $baseUrl . '?' . http_build_query($queryParams) . '" class="cn-page-btn">' . $totalPages . '</a>';
                    }

                    // Next
                    $queryParams['page'] = $page + 1;
                    echo '<a href="' . $baseUrl . '?' . http_build_query($queryParams) . '" class="cn-page-btn ' . ($page >= $totalPages ? 'disabled' : '') . '">';
                    echo 'Next';
                    echo '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>';
                    echo '</a>';
                    ?>
                </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="cn-empty cn-fade-up">
                    <div class="cn-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                    </div>
                    <h3>No Study Notes Found</h3>
                    <p>No study materials match your current filters. Try adjusting your search criteria or be the first to upload notes for your class.</p>
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= $assetBase ?>upload-notes" class="cn-upload-btn" style="margin-top: 1.5rem; display: inline-flex;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Upload Notes
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Upload CTA -->
            <div class="cn-cta cn-fade-up">
                <div class="cn-cta-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <h2>Share Your Knowledge with Students Across Pakistan</h2>
                <p>Help fellow students prepare for board exams by uploading your study notes. All submissions are carefully reviewed before publishing to ensure quality.</p>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= $assetBase ?>upload-notes" class="cn-upload-btn" style="position: relative; z-index: 1;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload Your Notes
                    </a>
                <?php else: ?>
                    <a href="<?= $assetBase ?>auth/login.php" class="cn-upload-btn" style="position: relative; z-index: 1;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Login to Upload
                    </a>
                <?php endif; ?>
            </div>

            <!-- SEO Content Section -->
            <section class="cn-seo-content cn-fade-up">
                <h2>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                    Free Class Notes for Punjab Board Exam Preparation 2026
                </h2>
                <p>
                    Ahmad Learning Hub provides a comprehensive collection of free class notes and study materials for students preparing for Punjab Board (BISE) examinations.
                    Our community-driven platform offers study resources for Class 9, Class 10 (Matric), Class 11, and Class 12 (Intermediate) covering all major subjects including
                    Physics, Chemistry, Biology, Mathematics, English, Urdu, Pakistan Studies, Islamiyat, and Computer Science.
                </p>
                <p>
                    All study materials are uploaded by verified teachers and students, reviewed for quality, and made available completely free of charge.
                    Whether you need PDF notes for last-minute revision, detailed chapter summaries, or presentation slides for visual learning,
                    our growing library of <?= $totalNotes ?> study materials has resources to support your board exam preparation.
                </p>
                <p>
                    Looking for more ways to prepare? Try our
                    <a href="<?= $assetBase ?>class-9-and-10-online-mcqs-prepation-test">free online MCQ practice tests</a>,
                    <a href="<?= $assetBase ?>Class-9-and-10-Online-Question-Paper-generator">question paper generator</a>, or
                    <a href="<?= $assetBase ?>class-9-10-11-12-test-series-for-board-exams">board exam test series</a>
                    to complement your studies.
                </p>
            </section>

            <!-- Back Button -->
            <div class="cn-back-section">
                <a href="<?= $assetBase ?>study-material-for-board-exam-preparations" class="cn-back-btn">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
                    Back to Study Materials
                </a>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>
