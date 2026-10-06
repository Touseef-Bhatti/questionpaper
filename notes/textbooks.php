<?php
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/seo.php';

$booksByClass = [];
$books = [];
$libraryError = false;

try {
    // book_uploads is maintained by the textbook uploader and Drive sync job.
    // Only the newest available upload for each book is exposed to students.
    $librarySql = "
        SELECT
            u.id AS upload_id,
            u.drive_file_id,
            u.drive_url,
            u.file_size,
            b.book_id,
            b.book_name,
            c.class_id,
            c.class_name
        FROM book_uploads u
        INNER JOIN book b ON b.book_id = u.book_id
        INNER JOIN class c ON c.class_id = u.class_id
        WHERE u.status = 'active'
          AND u.drive_status = 'available'
          AND u.drive_file_id <> ''
          AND NOT EXISTS (
              SELECT 1
              FROM book_uploads newer
              WHERE newer.book_id = u.book_id
                AND newer.class_id = u.class_id
                AND newer.status = 'active'
                AND newer.drive_status = 'available'
                AND newer.drive_file_id <> ''
                AND newer.id > u.id
          )
        ORDER BY c.class_id ASC, b.book_name ASC, u.id DESC
    ";

    $libraryResult = $conn->query($librarySql);
    if (!$libraryResult) {
        throw new RuntimeException('Textbook library query failed.');
    }

    while ($row = $libraryResult->fetch_assoc()) {
        $classId = (int) $row['class_id'];
        $book = [
            'id' => (int) $row['book_id'],
            'uploadId' => (int) $row['upload_id'],
            'title' => trim((string) $row['book_name']),
            'classId' => $classId,
            'className' => trim((string) $row['class_name']),
            'driveId' => trim((string) $row['drive_file_id']),
            'driveUrl' => trim((string) $row['drive_url']),
            'fileSize' => (int) $row['file_size'],
        ];

        $books[] = $book;
        $booksByClass[$classId]['name'] = $book['className'];
        $booksByClass[$classId]['books'][] = $book;
    }
} catch (Throwable $e) {
    $libraryError = true;
    error_log('Textbook library load failed: ' . $e->getMessage());
}

ksort($booksByClass);

function textbookFormatBytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
    return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1) . ' ' . $units[$power];
}

function textbookDriveUrl(array $book): string
{
    $driveUrl = trim((string) ($book['driveUrl'] ?? ''));
    if (preg_match('#^https://drive\.google\.com/#i', $driveUrl)) {
        return $driveUrl;
    }

    return 'https://drive.google.com/file/d/' . rawurlencode((string) ($book['driveId'] ?? '')) . '/view';
}

$bookCount = count($books);
$classCount = count($booksByClass);
$pageTitle = 'Digital Textbooks by Class in Pakistan | Ahmad Learning Hub';
$metaDescription = 'Browse official Punjab Board textbooks by class on Ahmad Learning Hub. Syllabus-aligned PDF books are sourced from the Punjab Curriculum and Textbook Board.';
$officialSourceUrl = 'https://pctb.punjab.gov.pk';
$canonicalUrl = alh_seo_absolute_url('/textbooks');
$textbookItems = [];
foreach ($books as $position => $book) {
    $textbookItems[] = [
        '@type' => 'ListItem',
        'position' => $position + 1,
        'item' => [
            '@type' => 'Book',
            '@id' => $canonicalUrl . '#book-' . $book['id'],
            'name' => $book['title'],
            'url' => textbookDriveUrl($book),
            'isBasedOn' => $officialSourceUrl,
            'educationalLevel' => $book['className'],
            'inLanguage' => 'en-PK',
        ],
    ];
}
$textbookStructuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'BreadcrumbList',
            '@id' => $canonicalUrl . '#breadcrumb',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => alh_seo_absolute_url('/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Study materials',
                    'item' => alh_seo_absolute_url('/study-material-for-board-exam-preparations'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => 'Digital textbooks',
                    'item' => $canonicalUrl,
                ],
            ],
        ],
        [
            '@type' => 'ItemList',
            '@id' => $canonicalUrl . '#textbook-list',
            'name' => 'Digital textbooks by class',
            'description' => $metaDescription,
            'mainEntityOfPage' => ['@id' => $canonicalUrl . '#webpage'],
            'numberOfItems' => $bookCount,
            'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
            'itemListElement' => $textbookItems,
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en-PK">
<head>
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php alh_render_seo_head([
        'title' => $pageTitle,
        'description' => $metaDescription,
        'canonical' => $canonicalUrl,
        'page_type' => 'CollectionPage',
    ]); ?>
    <script type="application/ld+json"><?= alh_seo_json($textbookStructuredData) ?></script>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/notes.css">
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/textbook.css">
</head>
<body>
    <?php include '../header.php'; ?>

    <main class="main-content textbook-page">
        <div class="textbooks-container">
            <section class="library-hero" aria-labelledby="libraryTitle">
                <div class="library-hero-copy">
                    <p class="library-kicker"><i class="fa-solid fa-book-open" aria-hidden="true"></i> Punjab Board study library</p>
                    <h1 id="libraryTitle">Digital textbooks by class</h1>
                    <p class="library-hero-text">Find official Punjab Board PDF textbooks aligned with the relevant syllabus. Books are grouped by class so students can choose a shelf, search a title, and start reading online.</p>
                    <div class="library-route" aria-label="Book storage route">
                        <span><i class="fa-solid fa-landmark" aria-hidden="true"></i> Punjab Board</span>
                        <span class="route-divider">/</span>
                        <span>Official textbooks</span>
                        <span class="route-divider">/</span>
                        <span>Class</span>
                    </div>
                </div>
                <div class="library-hero-stats" aria-label="Library statistics">
                    <div class="hero-stat-number"><?= $bookCount ?></div>
                    <div class="hero-stat-label">Official<br>titles</div>
                    <div class="hero-stat-rule"></div>
                    <div class="hero-stat-meta"><strong><?= $classCount ?></strong> <?= $classCount === 1 ? 'class shelf' : 'class shelves' ?></div>
                </div>
            </section>

            <section class="library-toolbar" aria-label="Textbook filters">
                <div class="toolbar-heading">
                    <p class="section-kicker">Your shelves</p>
                    <h2>Choose a class</h2>
                </div>
                <div class="class-tabs" id="classTabs" role="tablist" aria-label="Filter textbooks by class">
                    <button type="button" class="class-tab is-active" data-class-filter="all" role="tab" aria-selected="true">All classes <span><?= $bookCount ?></span></button>
                    <?php foreach ($booksByClass as $classId => $classShelf): ?>
                        <button type="button" class="class-tab" data-class-filter="<?= (int) $classId ?>" role="tab" aria-selected="false">
                            <?= htmlspecialchars($classShelf['name'], ENT_QUOTES, 'UTF-8') ?> <span><?= count($classShelf['books']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <label class="library-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <span class="visually-hidden">Search textbooks</span>
                    <input type="search" id="bookSearch" placeholder="Search by book name or subject" autocomplete="off">
                </label>
            </section>

            <?php if ($libraryError): ?>
                <section class="library-empty library-empty-error" role="alert">
                    <span class="empty-icon"><i class="fa-solid fa-cloud-exclamation" aria-hidden="true"></i></span>
                    <h2>The library is taking a short pause.</h2>
                    <p>We could not load the textbook collection right now. Please refresh in a moment.</p>
                    <button type="button" class="library-action" onclick="window.location.reload()">Try again</button>
                </section>
            <?php elseif ($bookCount === 0): ?>
                <section class="library-empty">
                    <span class="empty-icon"><i class="fa-solid fa-books" aria-hidden="true"></i></span>
                    <h2>No textbooks have landed yet.</h2>
                    <p>Once a book is uploaded through the textbook workspace, it will appear here automatically under its class.</p>
                    <a class="library-action" href="../study-material-for-board-exam-preparations">Explore study notes</a>
                </section>
            <?php else: ?>
                <p class="library-result-count" id="libraryResultCount" aria-live="polite">Showing <?= $bookCount ?> <?= $bookCount === 1 ? 'title' : 'titles' ?> across <?= $classCount ?> <?= $classCount === 1 ? 'class shelf' : 'class shelves' ?>.</p>
                <div class="class-shelves" id="classShelves">
                    <?php foreach ($booksByClass as $classId => $classShelf): ?>
                        <section class="class-shelf" data-class-section="<?= (int) $classId ?>" data-class-name="<?= htmlspecialchars($classShelf['name'], ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="classHeading<?= (int) $classId ?>">
                            <div class="shelf-heading">
                                <div>
                                    <p class="section-kicker">Class shelf</p>
                                    <h2 id="classHeading<?= (int) $classId ?>"><?= htmlspecialchars($classShelf['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                                </div>
                                <span class="shelf-count"><?= count($classShelf['books']) ?> <?= count($classShelf['books']) === 1 ? 'book' : 'books' ?></span>
                            </div>
                            <div class="books-grid">
                                <?php foreach ($classShelf['books'] as $book): ?>
                                    <?php $fileSize = textbookFormatBytes($book['fileSize']); ?>
                                    <a class="library-book-card" href="<?= htmlspecialchars(textbookDriveUrl($book), ENT_QUOTES, 'UTF-8') ?>" data-book-id="<?= (int) $book['id'] ?>" data-class-id="<?= (int) $classId ?>" aria-label="Open <?= htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') ?> textbook for <?= htmlspecialchars($book['className'], ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="book-card-topline">
                                            <span class="book-card-mark"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span>
                                            <span class="drive-badge"><i class="fa-solid fa-certificate" aria-hidden="true"></i> Punjab Board</span>
                                        </span>
                                        <span class="book-card-title"><?= htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="book-card-file"><?= htmlspecialchars($book['className'] . ' · Punjab Board textbook', ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="book-card-footer">
                                            <span><?= $fileSize !== '' ? htmlspecialchars($fileSize, ENT_QUOTES, 'UTF-8') : 'PDF' ?></span>
                                            <span class="book-card-open">Read <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
                <section class="library-note">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <p>All textbooks listed here are sourced from the <a href="<?= htmlspecialchars($officialSourceUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">official Punjab Curriculum and Textbook Board website</a> and organized according to the relevant Punjab Board syllabus. Check the official source for the latest edition and board instructions.</p>
                </section>
            <?php endif; ?>

            <section class="library-seo-copy" aria-labelledby="libraryAnswerTitle">
                <p class="section-kicker">Digital textbook guide</p>
                <h2 id="libraryAnswerTitle">How to use these online textbooks</h2>
                <p>Ahmad Learning Hub's digital textbook library gives students a simple way to find official Punjab Board books online. Each available title is grouped under its class shelf and aligned with the relevant syllabus. Use the class filters to narrow the library, search by book name or subject, then select Read to open the book on your phone, tablet, or computer. All listed textbooks are sourced from the official Punjab Curriculum and Textbook Board website; this page organizes them for convenient revision and classroom preparation. Always compare textbook editions, syllabus requirements, and board notices with the official source before an examination. After reading, continue to class notes, chapter-wise MCQs, or the question paper generator for focused exam preparation.</p>
                <nav aria-label="Related study resources">
                    <a href="../study-material-for-board-exam-preparations">Class notes</a>
                    <a href="../class-9-10-11-12-mcqs-for-board-exams">Chapter-wise MCQs</a>
                    <a href="../online-question-paper-generator">Question paper generator</a>
                </nav>
            </section>
        </div>
    </main>

    <section class="book-viewer-view" id="bookViewerView" aria-hidden="true">
        <div class="reader-shell">
            <div class="reader-topbar">
                <button type="button" class="reader-back" id="backToBooks"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Library</button>
                <div class="reader-heading">
                    <p class="section-kicker">Now reading</p>
                    <h1 id="viewerTitle">Select a book to start reading</h1>
                    <p id="viewerMeta">Select a book · Punjab Board</p>
                </div>
                <div class="reader-actions">
                    <div class="reader-zoom" aria-label="Zoom controls">
                        <button type="button" class="reader-control" id="zoomOut" title="Zoom out" aria-label="Zoom out">−</button>
                        <span id="zoomLevel">100%</span>
                        <button type="button" class="reader-control" id="zoomIn" title="Zoom in" aria-label="Zoom in">+</button>
                    </div>
                    <button type="button" class="reader-control" id="darkModeBtn" title="Toggle reading contrast" aria-label="Toggle reading contrast"><i class="fa-solid fa-moon" aria-hidden="true"></i></button>
                    <button type="button" class="reader-control" id="openDriveBtn" title="Open source PDF" aria-label="Open source PDF"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></button>
                    <button type="button" class="reader-control" id="fullscreenBtn" title="Fullscreen" aria-label="Fullscreen"><i class="fa-solid fa-expand" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="reader-frame-wrap" id="bookViewer">
                <div class="no-book-selected">Select a title from the library to begin.</div>
            </div>
        </div>
    </section>

    <?php include '../footer.php'; ?>

    <script>
        const textbookBooks = <?= alh_seo_json($books) ?>;
        const bookById = new Map(textbookBooks.map((book) => [String(book.id), book]));
        let activeClass = 'all';
        let searchTerm = '';
        let currentBook = null;
        let zoomLevel = 100;

        const booksView = document.querySelector('.textbook-page');
        const viewerView = document.getElementById('bookViewerView');
        const searchInput = document.getElementById('bookSearch');
        const resultCount = document.getElementById('libraryResultCount');

        function applyLibraryFilters() {
            let visibleCount = 0;
            document.querySelectorAll('.class-shelf').forEach((shelf) => {
                const classMatches = activeClass === 'all' || shelf.dataset.classSection === activeClass;
                let visibleInShelf = 0;

                shelf.querySelectorAll('.library-book-card').forEach((card) => {
                    const book = bookById.get(card.dataset.bookId);
                    const haystack = `${book?.title || ''} ${book?.className || ''}`.toLowerCase();
                    const matches = classMatches && haystack.includes(searchTerm);
                    card.hidden = !matches;
                    if (matches) {
                        visibleInShelf += 1;
                        visibleCount += 1;
                    }
                });

                shelf.hidden = visibleInShelf === 0;
            });

            if (resultCount) {
                resultCount.textContent = visibleCount === 0
                    ? 'No titles match your search.'
                    : `Showing ${visibleCount} ${visibleCount === 1 ? 'title' : 'titles'} in the library.`;
            }
        }

        document.querySelectorAll('.class-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                activeClass = tab.dataset.classFilter || 'all';
                document.querySelectorAll('.class-tab').forEach((item) => {
                    const selected = item === tab;
                    item.classList.toggle('is-active', selected);
                    item.setAttribute('aria-selected', selected ? 'true' : 'false');
                });
                applyLibraryFilters();
            });
        });

        searchInput?.addEventListener('input', (event) => {
            searchTerm = event.target.value.trim().toLowerCase();
            applyLibraryFilters();
        });

        function showLibrary() {
            viewerView.classList.remove('is-active');
            viewerView.setAttribute('aria-hidden', 'true');
            booksView.hidden = false;
            document.body.classList.remove('reader-open');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showReader(book) {
            currentBook = book;
            booksView.hidden = true;
            viewerView.classList.add('is-active');
            viewerView.setAttribute('aria-hidden', 'false');
            document.body.classList.add('reader-open');

            document.getElementById('viewerTitle').textContent = book.title;
            document.getElementById('viewerMeta').textContent = `${book.className} · ${book.title} · Punjab Board`;
            const viewer = document.getElementById('bookViewer');
            viewer.replaceChildren();
            const iframe = document.createElement('iframe');
            iframe.className = 'book-viewer-iframe';
            iframe.id = 'bookIframe';
            iframe.src = `https://drive.google.com/file/d/${encodeURIComponent(book.driveId)}/preview`;
            iframe.title = book.title;
            iframe.allow = 'autoplay';
            iframe.allowFullscreen = true;
            viewer.appendChild(iframe);
            zoomLevel = 100;
            applyZoom();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function applyZoom() {
            const iframe = document.getElementById('bookIframe');
            const zoomLabel = document.getElementById('zoomLevel');
            if (iframe) {
                iframe.style.transform = `scale(${zoomLevel / 100})`;
                iframe.style.width = `${100 / (zoomLevel / 100)}%`;
            }
            if (zoomLabel) zoomLabel.textContent = `${zoomLevel}%`;
        }

        document.querySelectorAll('.library-book-card').forEach((card) => {
            card.addEventListener('click', (event) => {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                const book = bookById.get(card.dataset.bookId);
                if (book) {
                    event.preventDefault();
                    showReader(book);
                }
            });
        });

        document.getElementById('backToBooks')?.addEventListener('click', showLibrary);
        document.getElementById('zoomIn')?.addEventListener('click', () => {
            zoomLevel = Math.min(150, zoomLevel + 10);
            applyZoom();
        });
        document.getElementById('zoomOut')?.addEventListener('click', () => {
            zoomLevel = Math.max(70, zoomLevel - 10);
            applyZoom();
        });
        document.getElementById('openDriveBtn')?.addEventListener('click', () => {
            if (currentBook) {
                const fallbackUrl = `https://drive.google.com/file/d/${encodeURIComponent(currentBook.driveId)}/view`;
                const driveUrl = /^https:\/\/drive\.google\.com\//i.test(currentBook.driveUrl) ? currentBook.driveUrl : fallbackUrl;
                window.open(driveUrl, '_blank', 'noopener');
            }
        });
        document.getElementById('fullscreenBtn')?.addEventListener('click', () => {
            document.getElementById('bookViewer')?.requestFullscreen?.();
        });
        document.getElementById('darkModeBtn')?.addEventListener('click', () => {
            document.body.classList.toggle('reader-contrast');
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && viewerView.classList.contains('is-active')) showLibrary();
            if (event.key === '/' && !viewerView.classList.contains('is-active') && document.activeElement !== searchInput) {
                event.preventDefault();
                searchInput?.focus();
            }
        });

        applyLibraryFilters();
    </script>
</body>
</html>
