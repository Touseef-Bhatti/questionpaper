<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/seo.php';

$noteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
if ($noteId < 1) {
    http_response_code(404);
    exit('Note not found.');
}

$stmt = $conn->prepare("SELECT id, title, description, class, subject, chapter, mime_type, file_size, created_at, status FROM class_notes WHERE id = ? AND status = 'approved' LIMIT 1");
if (!$stmt) {
    http_response_code(503);
    exit('Notes are temporarily unavailable. Please try again shortly.');
}
$stmt->bind_param('i', $noteId);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$note) {
    http_response_code(404);
    exit('This study material is unavailable.');
}

function downloadNoteSlug(string $value, string $fallback): string {
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : $fallback;
}

function downloadNotePath(array $note): string {
    $parts = ['class-' . downloadNoteSlug((string)$note['class'], '9'), downloadNoteSlug((string)($note['subject'] ?? ''), 'general')];
    if (trim((string)($note['chapter'] ?? '')) !== '') $parts[] = downloadNoteSlug((string)$note['chapter'], 'chapter');
    $parts[] = downloadNoteSlug((string)($note['title'] ?? ''), 'study-notes');
    return '/download-notes/' . implode('-', $parts) . '-' . (int)$note['id'];
}

$canonicalPath = downloadNotePath($note);
$requestPath = '/' . ltrim((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'), '/');
if (rtrim($requestPath, '/') !== rtrim($canonicalPath, '/')) {
    header('Location: ' . $canonicalPath, true, 301);
    exit;
}

$siteUrl = alh_seo_site_url();
$canonicalUrl = $siteUrl . $canonicalPath;
$title = trim((string)$note['title']) ?: 'Study Notes';
$subject = trim((string)($note['subject'] ?? '')) ?: 'General';
$chapter = trim((string)($note['chapter'] ?? ''));
$class = trim((string)$note['class']);
$classNames = ['9'=>'Class 9 (Matric Part 1)','10'=>'Class 10 (Matric Part 2)','11'=>'Class 11 (Intermediate Part 1)','12'=>'Class 12 (Intermediate Part 2)'];
$classLabel = $classNames[$class] ?? 'Class ' . $class;
$description = trim((string)($note['description'] ?? ''));
$summary = $description !== '' ? $description : "Download {$title}, free {$classLabel} {$subject} study notes" . ($chapter !== '' ? " for {$chapter}" : '') . ' from Ahmad Learning Hub.';
$fileType = str_contains(strtolower((string)$note['mime_type']), 'pdf') ? 'PDF' : 'study material';
$pageTitle = "Download {$title} - {$classLabel} {$subject} Notes | Ahmad Learning Hub";
$pageDescription = "Download {$title} for {$classLabel}" . ($chapter !== '' ? " {$chapter}" : '') . " {$subject} notes. Free {$fileType} study material for exam preparation from Ahmad Learning Hub.";
$downloadUrl = '/download-note?id=' . (int)$note['id'];
$chapterLabel = $chapter !== '' ? $chapter : 'All chapters';
$documentLd = [
    '@context' => 'https://schema.org',
    '@type' => ['DigitalDocument', 'LearningResource'],
    'name' => $title,
    'headline' => $pageTitle,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'encodingFormat' => (string)$note['mime_type'],
    'datePublished' => date('Y-m-d', strtotime((string)$note['created_at'])),
    'inLanguage' => 'en',
    'isAccessibleForFree' => true,
    'learningResourceType' => 'Study notes',
    'educationalLevel' => $classLabel,
    'keywords' => implode(', ', [$title, $classLabel, $subject, $chapter, 'free notes', 'download study material', 'board exam preparation']),
    'audience' => ['@type'=>'EducationalAudience','educationalRole'=>'student'],
    'provider' => ['@type'=>'Organization','name'=>'Ahmad Learning Hub','url'=>$siteUrl],
    'mainEntityOfPage' => ['@type'=>'WebPage','@id'=>$canonicalUrl],
];
$breadcrumbLd = [
    '@context'=>'https://schema.org',
    '@type'=>'BreadcrumbList',
    'itemListElement'=>[
        ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>$siteUrl],
        ['@type'=>'ListItem','position'=>2,'name'=>'Class Notes','item'=>$siteUrl . '/class-notes'],
        ['@type'=>'ListItem','position'=>3,'name'=>$classLabel,'item'=>$siteUrl . '/class-' . rawurlencode($class) . '-notes'],
        ['@type'=>'ListItem','position'=>4,'name'=>$title,'item'=>$canonicalUrl],
    ],
];

// The clean URL is rewritten to a nested controller; keep shared links at the site root.
$assetBase = '/';
$faviconBaseUrl = $assetBase;
?>
<!doctype html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:site_name" content="Ahmad Learning Hub">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>css/main.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>css/notes.css">
    <script type="application/ld+json"><?= json_encode($documentLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
    <script type="application/ld+json"><?= json_encode($breadcrumbLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
    <style>
        .dn-page{min-height:100vh;background:#f1f5f9;color:#172033;font-family:system-ui,-apple-system,'Segoe UI',sans-serif}
        .dn-wrap{width:min(900px,calc(100% - 32px));margin:0 auto;padding:28px 0 64px}
        .dn-crumbs{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 24px;color:#64748b;font-size:13px}
        .dn-crumbs a{color:#4f46e5;text-decoration:none}
        .dn-card{padding:clamp(22px,5vw,52px);border:1px solid #dbe3ef;border-radius:24px;background:#fff;box-shadow:0 20px 55px rgba(15,23,42,.08)}
        .dn-kicker{display:inline-flex;padding:7px 11px;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
        .dn-card h1{margin:18px 0 12px;color:#172554;font-size:clamp(30px,5vw,46px);line-height:1.12;letter-spacing:-.03em}
        .dn-summary{max-width:680px;margin:0;color:#526078;font-size:17px;line-height:1.75}
        .dn-meta{display:flex;flex-wrap:wrap;gap:9px;margin:22px 0 28px}
        .dn-meta span{padding:8px 11px;border:1px solid #e2e8f0;border-radius:9px;background:#f8fafc;color:#334155;font-size:13px;font-weight:700}
        .dn-download{display:flex;justify-content:center;align-items:center;gap:11px;width:100%;min-height:66px;padding:14px 20px;border-radius:14px;background:linear-gradient(110deg,#4338ca,#2563eb);color:white;text-align:center;text-decoration:none;font-size:18px;font-weight:800;box-shadow:0 12px 26px rgba(67,56,202,.2);transition:transform .18s,box-shadow .18s}
        .dn-download:hover{transform:translateY(-2px);box-shadow:0 16px 30px rgba(67,56,202,.25)}.dn-download svg{width:23px;height:23px;flex:none}
        .dn-copy{margin-top:32px;padding-top:24px;border-top:1px solid #e2e8f0;color:#475569;line-height:1.75}
        .dn-copy h2{margin:0 0 10px;color:#172554;font-size:21px}.dn-copy h2:not(:first-child){margin-top:24px}
        .dn-steps{padding-left:22px}.dn-steps li{margin:7px 0}
        .dn-note-link{display:inline-block;margin-top:24px;color:#4338ca;font-weight:700;text-decoration:none}
        @media(max-width:560px){.dn-wrap{width:min(100% - 22px,900px);padding-top:16px}.dn-card{border-radius:17px}.dn-summary{font-size:15px}.dn-download{font-size:16px;min-height:60px}}
    </style>
</head>
<body class="dn-page">
    <?php include dirname(__DIR__) . '/header.php'; ?>
    <main class="dn-wrap">
        <nav class="dn-crumbs" aria-label="Breadcrumb"><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>">Home</a><span aria-hidden="true">&rsaquo;</span><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>class-notes">Class Notes</a><span aria-hidden="true">&rsaquo;</span><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>class-<?= rawurlencode($class) ?>-notes"><?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></a><span aria-hidden="true">&rsaquo;</span><span><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span></nav>
        <article class="dn-card">
            <span class="dn-kicker">Free study resource &middot; <?= htmlspecialchars($fileType, ENT_QUOTES, 'UTF-8') ?></span>
            <h1>Download <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="dn-summary"><?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="dn-meta" aria-label="Study material details">
                <span><?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <span><?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?></span>
                <span><?= htmlspecialchars($chapterLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <span><?= htmlspecialchars($fileType, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <a class="dn-download" id="download-note-cta" href="<?= htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') ?>" rel="nofollow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                Download Notes &mdash; Free
            </a>
            <section class="dn-copy" aria-labelledby="dn-details-title">
                <h2 id="dn-details-title">About this <?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?> resource</h2>
                <p><?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?> This file is provided as a free study resource for students reviewing <?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?><?= $chapter !== '' ? ' and ' . htmlspecialchars($chapter, ENT_QUOTES, 'UTF-8') : '' ?>. Check the file type above to confirm it works with your device before downloading.</p>
                <h2>How to download these notes</h2>
                <ol class="dn-steps"><li>Select the download button above.</li><li>Your browser will open the available file from its source.</li><li>Save the file to your device for offline revision.</li></ol>
            </section>
            <a class="dn-note-link" href="<?= htmlspecialchars($assetBase . ltrim('class-' . downloadNoteSlug($class, '9') . '-' . downloadNoteSlug($subject, 'general') . '-' . downloadNoteSlug($title, 'study-notes') . '-' . (int)$note['id'], '/'), ENT_QUOTES, 'UTF-8') ?>">&larr; View the note page and discussion</a>
        </article>
    </main>
    <?php include dirname(__DIR__) . '/footer.php'; ?>
    <script>
    (function () {
        const downloadButton = document.getElementById('download-note-cta');
        if (!downloadButton) return;

        downloadButton.addEventListener('click', function (event) {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (!window.ALHQuizAdGate || typeof window.ALHQuizAdGate.gate !== 'function') return;

            event.preventDefault();
            const downloadUrl = downloadButton.href;
            window.ALHQuizAdGate.gate({
                storageKey: 'alh_note_download_unlock_<?= (int)$note['id'] ?>',
                message: 'Watch 1 ad to unlock this note download for free!',
                adUrl: 'https://uplcm.com/4/10866850',
                onContinue: function () {
                    window.location.assign(downloadUrl);
                }
            });
        });
    })();
    </script>
</body>
</html>
