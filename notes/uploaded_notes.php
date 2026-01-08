<?php
include '../db_connect.php';

// Get filter parameters
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$bookId = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
$chapterId = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$fileType = isset($_GET['file_type']) ? trim($_GET['file_type']) : '';

// Fetch classes for filter
$classesQuery = "SELECT class_id, class_name FROM class ORDER BY class_id ASC";
$classesResult = $conn->query($classesQuery);
$classes = [];
while ($row = $classesResult->fetch_assoc()) {
    $classes[] = $row;
}

// Fetch books for selected class
$books = [];
if ($classId > 0) {
    $booksQuery = "SELECT book_id, book_name FROM book WHERE class_id = ? ORDER BY book_name ASC";
    $stmt = $conn->prepare($booksQuery);
    $stmt->bind_param('i', $classId);
    $stmt->execute();
    $booksResult = $stmt->get_result();
    while ($row = $booksResult->fetch_assoc()) {
        $books[] = $row;
    }
    $stmt->close();
}

// Fetch chapters for selected class and book
$chapters = [];
if ($classId > 0 && $bookId > 0) {
    $chaptersQuery = "SELECT chapter_id, chapter_name FROM chapter WHERE class_id = ? AND book_id = ? ORDER BY chapter_no ASC, chapter_id ASC";
    $stmt = $conn->prepare($chaptersQuery);
    $stmt->bind_param('ii', $classId, $bookId);
    $stmt->execute();
    $chaptersResult = $stmt->get_result();
    while ($row = $chaptersResult->fetch_assoc()) {
        $chapters[] = $row;
    }
    $stmt->close();
}

// Build query for notes
$whereConditions = ['n.is_deleted = 0'];
$params = [];
$types = '';

if ($classId > 0) {
    $whereConditions[] = "n.class_id = ?";
    $params[] = $classId;
    $types .= 'i';
}

if ($bookId > 0) {
    $whereConditions[] = "n.book_id = ?";
    $params[] = $bookId;
    $types .= 'i';
}

if ($chapterId > 0) {
    $whereConditions[] = "n.chapter_id = ?";
    $params[] = $chapterId;
    $types .= 'i';
}

if (!empty($search)) {
    $whereConditions[] = "(n.title LIKE ? OR n.description LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

if (!empty($fileType)) {
    if ($fileType === 'images') {
        $whereConditions[] = "n.file_type IN ('png', 'jpg', 'jpeg', 'gif', 'webp')";
    } else {
        $whereConditions[] = "n.file_type = ?";
        $params[] = $fileType;
        $types .= 's';
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Fetch notes
$query = "SELECT n.*, c.class_name, b.book_name, ch.chapter_name 
          FROM uploaded_notes n 
          LEFT JOIN class c ON n.class_id = c.class_id 
          LEFT JOIN book b ON n.book_id = b.book_id 
          LEFT JOIN chapter ch ON n.chapter_id = ch.chapter_id 
          $whereClause 
          ORDER BY n.created_at DESC";

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

// Dynamic SEO content
$pageTitle = "Free Study Notes & Materials - Punjab Board 9th & 10th Class | Ahmad Learning Hub";
$pageDescription = "Download free study materials, PDF notes, presentations, and educational resources for 9th and 10th class Punjab Board students. Access high-quality notes for Physics, Chemistry, Biology, Math, Computer Science, and more subjects. Prepare for your board exams with comprehensive study materials.";
$pageKeywords = "free study notes, Punjab Board notes, 9th class notes PDF, 10th class notes download, matric notes, SSC notes, physics notes, chemistry notes, biology notes, math notes, computer science notes, free educational resources, board exam preparation, study materials download";

$className = '';
$bookName = '';
$chapterName = '';

if ($classId > 0) {
    foreach ($classes as $class) {
        if ($class['class_id'] == $classId) {
            $className = $class['class_name'];
            $pageTitle = htmlspecialchars($className) . " Notes - Free Study Materials | Ahmad Learning Hub";
            $pageDescription = "Download free " . htmlspecialchars($className) . " study notes and materials for Punjab Board. Access PDF notes, presentations, and resources for all subjects including Physics, Chemistry, Biology, Math, and Computer Science.";
            $pageKeywords = htmlspecialchars($className) . " notes, " . htmlspecialchars($className) . " PDF, " . htmlspecialchars($className) . " study materials, " . $pageKeywords;
            break;
        }
    }
}

if ($bookId > 0) {
    foreach ($books as $book) {
        if ($book['book_id'] == $bookId) {
            $bookName = $book['book_name'];
            $pageTitle = htmlspecialchars($bookName) . " Notes - " . htmlspecialchars($className) . " | Ahmad Learning Hub";
            $pageDescription = "Download free " . htmlspecialchars($bookName) . " notes for " . htmlspecialchars($className) . " Punjab Board. Complete chapter-wise study materials, solved exercises, and exam preparation resources.";
            $pageKeywords = htmlspecialchars($bookName) . " notes, " . htmlspecialchars($bookName) . " PDF, " . $pageKeywords;
            break;
        }
    }
}

if ($chapterId > 0) {
    foreach ($chapters as $chapter) {
        if ($chapter['chapter_id'] == $chapterId) {
            $chapterName = $chapter['chapter_name'];
            $pageTitle = htmlspecialchars($chapterName) . " - " . htmlspecialchars($bookName) . " Notes | Ahmad Learning Hub";
            $pageDescription = "Download " . htmlspecialchars($chapterName) . " notes for " . htmlspecialchars($bookName) . " " . htmlspecialchars($className) . ". Complete solved notes, important questions, and study materials for Punjab Board exams.";
            break;
        }
    }
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$currentUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$canonicalUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
$siteUrl = $protocol . "://" . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Primary Meta Tags -->
    <title><?= $pageTitle ?></title>
    <meta name="title" content="<?= $pageTitle ?>">
    <meta name="description" content="<?= $pageDescription ?>">
    <meta name="keywords" content="<?= $pageKeywords ?>">
    <meta name="author" content="Ahmad Learning Hub">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="googlebot" content="index, follow">
    <meta name="language" content="English">
    <meta name="revisit-after" content="3 days">
    <meta name="rating" content="General">
    <meta name="distribution" content="global">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($currentUrl) ?>">
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:description" content="<?= $pageDescription ?>">
    <meta property="og:site_name" content="Ahmad Learning Hub">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= htmlspecialchars($currentUrl) ?>">
    <meta name="twitter:title" content="<?= $pageTitle ?>">
    <meta name="twitter:description" content="<?= $pageDescription ?>">
    
    <!-- Additional SEO -->
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- Structured Data - Educational Organization -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "Ahmad Learning Hub",
        "description": "<?= addslashes($pageDescription) ?>",
        "url": "<?= $siteUrl ?>",
        "educationalLevel": "Secondary Education",
        "audience": {
            "@type": "EducationalAudience",
            "educationalRole": "student"
        }
    }
    </script>
    
    <!-- Structured Data - ItemList for Notes -->
    <?php if (count($notes) > 0): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ItemList",
        "name": "Study Notes and Materials",
        "description": "<?= addslashes($pageDescription) ?>",
        "numberOfItems": <?= count($notes) ?>,
        "itemListElement": [
            <?php foreach (array_slice($notes, 0, 10) as $index => $note): ?>
            {
                "@type": "ListItem",
                "position": <?= $index + 1 ?>,
                "item": {
                    "@type": "DigitalDocument",
                    "name": "<?= addslashes($note['title']) ?>",
                    "description": "<?= addslashes($note['description'] ?? 'Study material for Punjab Board students') ?>",
                    "encodingFormat": "<?= htmlspecialchars($note['mime_type']) ?>",
                    "educationalLevel": "<?= addslashes($note['class_name'] ?? 'Secondary') ?>",
                    "isAccessibleForFree": true
                }
            }<?= $index < min(count($notes), 10) - 1 ? ',' : '' ?>
            <?php endforeach; ?>
        ]
    }
    </script>
    <?php endif; ?>
    
    <!-- Breadcrumb Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "<?= $siteUrl ?>/"
            },
            {
                "@type": "ListItem",
                "position": 2,
                "name": "Study Notes",
                "item": "<?= htmlspecialchars($canonicalUrl) ?>"
            }
            <?php if (!empty($className)): ?>
            ,{
                "@type": "ListItem",
                "position": 3,
                "name": "<?= addslashes($className) ?>",
                "item": "<?= htmlspecialchars($currentUrl) ?>"
            }
            <?php endif; ?>
        ]
    }
    </script>
    
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/notes.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --accent: #f093fb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-accent: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
        }

        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .notes-page {
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .notes-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 1.5rem;
        }

        /* Hero Section */
        .notes-hero {
            text-align: center;
            margin-bottom: 3rem;
            padding: 3rem 2rem;
            background: var(--gradient-primary);
            border-radius: var(--radius-xl);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .notes-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: shimmer 15s linear infinite;
        }

        @keyframes shimmer {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .notes-hero h1 {
            font-size: 2.75rem;
            font-weight: 800;
            margin-bottom: 1rem;
            position: relative;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .notes-hero p {
            font-size: 1.2rem;
            opacity: 0.95;
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            line-height: 1.7;
            color: white;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 2rem;
            flex-wrap: wrap;
            position: relative;
        }

        .hero-stat {
            text-align: center;
        }

        .hero-stat-number {
            font-size: 2.5rem;
            font-weight: 800;
        }

        .hero-stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Filters Section */
        .filters-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2.5rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-200);
        }

        .filters-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .filters-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-600);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--gray-100);
            color: var(--dark);
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }

        /* File Type Pills */
        .file-type-filter {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            align-items: center;
        }

        .file-type-label {
            font-weight: 600;
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .file-type-btn {
            padding: 0.625rem 1.25rem;
            background: var(--gray-100);
            border: 2px solid var(--gray-200);
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .file-type-btn:hover {
            background: var(--gray-200);
            transform: translateY(-2px);
        }

        .file-type-btn.active {
            background: var(--gradient-primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.35);
        }

        /* Action Buttons */
        .filter-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-filter {
            padding: 0.875rem 2rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-clear {
            background: var(--gray-500);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.25);
        }

        .btn-clear:hover {
            background: var(--gray-600);
            box-shadow: 0 6px 20px rgba(100, 116, 139, 0.4);
        }

        /* Results Info */
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem 1.5rem;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .results-count {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .results-count span {
            color: var(--primary);
        }

        /* Notes Grid */
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.75rem;
        }

        /* Note Card */
        .note-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            border: 1px solid var(--gray-200);
            position: relative;
        }

        .note-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }

        .note-card-header {
            padding: 1.5rem;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }

        .note-card-header::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1));
        }

        .note-icon {
            font-size: 3rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .note-type-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(10px);
        }

        .note-card-body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .note-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }

        .note-description {
            color: var(--gray-500);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
            flex-grow: 1;
        }

        .note-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .note-tag {
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .note-tag.class-tag {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .note-tag.book-tag {
            background: #dcfce7;
            color: #15803d;
        }

        .note-tag.chapter-tag {
            background: #fef3c7;
            color: #b45309;
        }

        .note-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        .note-size {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 500;
        }

        .note-date {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* Action Buttons */
        .note-actions {
            display: flex;
            gap: 0.75rem;
            padding: 1.25rem 1.5rem;
            background: var(--gray-100);
            border-top: 1px solid var(--gray-200);
        }

        .note-btn {
            flex: 1;
            padding: 0.875rem 1rem;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .note-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .note-btn:hover::before {
            left: 100%;
        }

        .btn-download {
            background: var(--gradient-success);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        /* No Notes */
        .no-notes {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
        }

        .no-notes-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.8;
        }

        .no-notes h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.75rem;
        }

        .no-notes p {
            color: var(--gray-500);
            font-size: 1.1rem;
        }

        /* SEO Content Section */
        .seo-content {
            margin-top: 4rem;
            padding: 3rem;
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
        }

        .seo-content h2 {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .seo-content p {
            color: var(--gray-600);
            line-height: 1.8;
            margin-bottom: 1.5rem;
            font-size: 1.05rem;
        }

        .seo-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .seo-feature {
            padding: 1.5rem;
            background: var(--gray-100);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--primary);
        }

        .seo-feature h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .seo-feature p {
            font-size: 0.95rem;
            color: var(--gray-500);
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .notes-hero h1 {
                font-size: 2rem;
            }

            .notes-hero p {
                font-size: 1rem;
            }

            .hero-stats {
                gap: 1.5rem;
            }

            .hero-stat-number {
                font-size: 2rem;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .notes-grid {
                grid-template-columns: 1fr;
            }

            .results-info {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .note-actions {
                flex-direction: column;
            }

            .seo-content {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body class="notes-page">
    <?php include '../header.php'; ?>
    
    <div class="main-content">
        <div class="notes-container">
            <!-- Hero Section -->
            <div class="notes-hero">
                <h1>📚 Free Study Notes & Materials</h1>
                <p>Download high-quality study notes, PDF resources, presentations, and educational materials to ace your Punjab Board exams. All materials are free!</p>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <div class="hero-stat-number"><?= count($notes) ?>+</div>
                        <div class="hero-stat-label">Study Materials</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-number"><?= count($classes) ?></div>
                        <div class="hero-stat-label">Classes</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-number">100%</div>
                        <div class="hero-stat-label">Free Access</div>
                    </div>
                </div>
            </div>
            
            <!-- Filters Section -->
            <div class="filters-section">
                <div class="filters-header">
                    <span style="font-size: 1.5rem;">🔍</span>
                    <h2>Find Your Study Materials</h2>
                </div>
                
                <form method="GET" action="" id="filterForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="class_id">Class</label>
                            <select name="class_id" id="class_id" onchange="updateBooks()">
                                <option value="0">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['class_id'] ?>" <?= $classId == $class['class_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="book_id">Subject / Book</label>
                            <select name="book_id" id="book_id" onchange="updateChapters()">
                                <option value="0">All Books</option>
                                <?php foreach ($books as $book): ?>
                                    <option value="<?= $book['book_id'] ?>" <?= $bookId == $book['book_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($book['book_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="chapter_id">Chapter</label>
                            <select name="chapter_id" id="chapter_id">
                                <option value="0">All Chapters</option>
                                <?php foreach ($chapters as $chapter): ?>
                                    <option value="<?= $chapter['chapter_id'] ?>" <?= $chapterId == $chapter['chapter_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($chapter['chapter_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search">Search Notes</label>
                            <input type="text" name="search" id="search" placeholder="Search by title or topic..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    
                    <div class="file-type-filter">
                        <span class="file-type-label">📁 File Type:</span>
                        <button type="button" class="file-type-btn <?= empty($fileType) ? 'active' : '' ?>" onclick="filterByType('')">All</button>
                        <button type="button" class="file-type-btn <?= $fileType === 'pdf' ? 'active' : '' ?>" onclick="filterByType('pdf')">📄 PDF</button>
                        <button type="button" class="file-type-btn <?= $fileType === 'pptx' ? 'active' : '' ?>" onclick="filterByType('pptx')">📊 PPT</button>
                        <button type="button" class="file-type-btn <?= $fileType === 'docx' ? 'active' : '' ?>" onclick="filterByType('docx')">📝 Word</button>
                        <button type="button" class="file-type-btn <?= $fileType === 'images' ? 'active' : '' ?>" onclick="filterByType('images')">🖼️ Images</button>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">🔍 Search Notes</button>
                        <button type="button" class="btn-filter btn-clear" onclick="clearFilters()">🔄 Clear Filters</button>
                    </div>
                </form>
            </div>
            
            <!-- Results Info -->
            <div class="results-info">
                <div class="results-count">
                    📚 Found <span><?= count($notes) ?></span> study materials
                    <?php if (!empty($className)): ?>
                        for <?= htmlspecialchars($className) ?>
                    <?php endif; ?>
                </div>
                <div style="color: var(--success); font-weight: 600;">
                    ✓ All materials are free to download
                </div>
            </div>
            
            <!-- Notes Grid -->
            <?php if (count($notes) > 0): ?>
                <div class="notes-grid">
                    <?php foreach ($notes as $note): ?>
                        <article class="note-card">
                            <div class="note-card-header">
                                <span class="note-icon"><?= getFileIcon($note['file_type']) ?></span>
                                <span class="note-type-badge"><?= strtoupper(htmlspecialchars($note['file_type'])) ?></span>
                            </div>
                            
                            <div class="note-card-body">
                                <h3 class="note-title"><?= htmlspecialchars($note['title']) ?></h3>
                                
                                <?php if ($note['description']): ?>
                                    <p class="note-description"><?= htmlspecialchars(substr($note['description'], 0, 120)) ?><?= strlen($note['description']) > 120 ? '...' : '' ?></p>
                                <?php else: ?>
                                    <p class="note-description">Study notes and materials for Punjab Board exam preparation.</p>
                                <?php endif; ?>
                                
                                <div class="note-meta">
                                    <?php if ($note['class_name']): ?>
                                        <span class="note-tag class-tag">📖 <?= htmlspecialchars($note['class_name']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($note['book_name']): ?>
                                        <span class="note-tag book-tag">📚 <?= htmlspecialchars($note['book_name']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($note['chapter_name']): ?>
                                        <span class="note-tag chapter-tag">📑 <?= htmlspecialchars($note['chapter_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="note-info">
                                    <span class="note-size">📦 <?= formatFileSize($note['file_size']) ?></span>
                                    <span class="note-date">📅 <?= date('M d, Y', strtotime($note['created_at'])) ?></span>
                                </div>
                            </div>
                            
                            <div class="note-actions">
                                <?php 
                                // Create clean filename from title
                                $cleanTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $note['title']);
                                $downloadFilename = $cleanTitle . '.' . $note['file_type'];
                                ?>
                                <a href="../<?= htmlspecialchars($note['file_path']) ?>" download="<?= htmlspecialchars($downloadFilename) ?>" class="note-btn btn-download" style="width: 100%;">
                                    <span>📥</span> Download
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-notes">
                    <div class="no-notes-icon">📭</div>
                    <h3>No Study Materials Found</h3>
                    <p>Try adjusting your filters to find the notes you're looking for.</p>
                </div>
            <?php endif; ?>
            
            <!-- SEO Content Section -->
            <section class="seo-content">
                <h2>📖 Free Study Notes for Punjab Board Exams</h2>
                <p>
                    Welcome to Ahmad Learning Hub's comprehensive collection of free study materials and notes for 9th and 10th class Punjab Board students. 
                    Our platform offers high-quality educational resources including PDF notes, PowerPoint presentations, Word documents, and image-based study materials 
                    to help you excel in your board examinations.
                </p>
                <p>
                    Whether you're looking for Physics notes, Chemistry formulas, Biology diagrams, Mathematics solutions, or Computer Science concepts, 
                    we have everything you need to prepare effectively for your SSC/Matric exams. All materials are carefully curated by experienced teachers 
                    and aligned with the latest Punjab Board curriculum.
                </p>
                
                <div class="seo-features">
                    <div class="seo-feature">
                        <h3>📄 PDF Notes</h3>
                        <p>Download comprehensive PDF notes for all subjects with detailed explanations and solved examples.</p>
                    </div>
                
                    <div class="seo-feature">
                        <h3>📝 Chapter Notes</h3>
                        <p>Chapter-wise organized notes covering all important topics from the Punjab Board syllabus.</p>
                    </div>
                    <div class="seo-feature">
                        <h3>✅ Free Access</h3>
                        <p>All study materials are completely free to download. No registration or payment required.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
    
    <?php include '../footer.php'; ?>
    
    <script>
        function updateBooks() {
            const classId = document.getElementById('class_id').value;
            const bookSelect = document.getElementById('book_id');
            const chapterSelect = document.getElementById('chapter_id');
            
            bookSelect.innerHTML = '<option value="0">Loading...</option>';
            chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
            
            if (classId == 0) {
                bookSelect.innerHTML = '<option value="0">All Books</option>';
                return;
            }
            
            fetch(`../quiz/quiz_data.php?type=books&class_id=${classId}`)
                .then(response => response.json())
                .then(books => {
                    bookSelect.innerHTML = '<option value="0">All Books</option>';
                    books.forEach(book => {
                        const option = document.createElement('option');
                        option.value = book.book_id;
                        option.textContent = book.book_name;
                        bookSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading books:', error);
                    bookSelect.innerHTML = '<option value="0">Error loading</option>';
                });
        }
        
        function updateChapters() {
            const classId = document.getElementById('class_id').value;
            const bookId = document.getElementById('book_id').value;
            const chapterSelect = document.getElementById('chapter_id');
            
            chapterSelect.innerHTML = '<option value="0">Loading...</option>';
            
            if (bookId == 0 || classId == 0) {
                chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
                return;
            }
            
            fetch(`../quiz/quiz_data.php?type=chapters&class_id=${classId}&book_id=${bookId}`)
                .then(response => response.json())
                .then(chapters => {
                    chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
                    chapters.forEach(chapter => {
                        const option = document.createElement('option');
                        option.value = chapter.chapter_id;
                        option.textContent = chapter.chapter_name;
                        chapterSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading chapters:', error);
                    chapterSelect.innerHTML = '<option value="0">Error loading</option>';
                });
        }
        
        function clearFilters() {
            window.location.href = 'uploaded_notes.php';
        }
        
        function filterByType(type) {
            const url = new URL(window.location.href);
            
            if (type) {
                url.searchParams.set('file_type', type);
            } else {
                url.searchParams.delete('file_type');
            }
            
            window.location.href = url.toString();
        }
    </script>
</body>
</html>

<?php
function getFileIcon($fileType) {
    $icons = [
        'pdf' => '📄',
        'ppt' => '📊',
        'pptx' => '📊',
        'doc' => '📝',
        'docx' => '📝',
        'txt' => '📃',
        'png' => '🖼️',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'gif' => '🖼️',
        'webp' => '🖼️'
    ];
    return $icons[$fileType] ?? '📎';
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
