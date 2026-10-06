<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAdminAuth();

$classOptions = [];
$classResult = $conn->query('SELECT class_id, class_name FROM class ORDER BY class_id ASC');
if ($classResult) {
    while ($classRow = $classResult->fetch_assoc()) {
        $classOptions[] = $classRow;
    }
}

$bookMap = [];
$bookResult = $conn->query('SELECT book_id, book_name, class_id FROM book ORDER BY book_name ASC');
if ($bookResult) {
    while ($bookRow = $bookResult->fetch_assoc()) {
        $bookMap[] = $bookRow;
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token. Please reload the page.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'bulk_create') {
            $classId = intval($_POST['class_id'] ?? 0);
            $bookId = intval($_POST['book_id'] ?? 0);
            $chapterNames = is_array($_POST['chapter_name'] ?? null) ? $_POST['chapter_name'] : [];
            $chapterNumbers = is_array($_POST['chapter_no'] ?? null) ? $_POST['chapter_no'] : [];
            $rows = [];

            if ($classId <= 0 || $bookId <= 0) {
                $error = 'Select both a class and a book before adding chapters.';
            } elseif (count($chapterNames) === 0) {
                $error = 'Add at least one chapter row.';
            } else {
                foreach ($chapterNames as $index => $rawName) {
                    $name = trim((string) $rawName);
                    $number = intval($chapterNumbers[$index] ?? 0);
                    if ($name === '' && $number === 0) {
                        continue;
                    }
                    if ($name === '' || $number <= 0) {
                        $error = 'Every chapter row needs a name and a chapter number.';
                        break;
                    }
                    $rows[] = [$name, $number];
                }

                if ($error === '' && count($rows) === 0) {
                    $error = 'Add at least one completed chapter row.';
                }
            }

            if ($error === '') {
                $bookStmt = $conn->prepare('SELECT book_name FROM book WHERE book_id = ? AND class_id = ? LIMIT 1');
                if (!$bookStmt) {
                    $error = 'Unable to verify the selected book.';
                } else {
                    $bookStmt->bind_param('ii', $bookId, $classId);
                    $bookStmt->execute();
                    $bookResult = $bookStmt->get_result();
                    $bookRow = $bookResult ? $bookResult->fetch_assoc() : null;
                    $bookStmt->close();

                    if (!$bookRow) {
                        $error = 'The selected book does not belong to the selected class.';
                    } else {
                        $bookName = $bookRow['book_name'];
                        $insertStmt = $conn->prepare('INSERT INTO chapter (chapter_name, chapter_no, class_id, book_id, book_name) VALUES (?, ?, ?, ?, ?)');
                        if (!$insertStmt) {
                            $error = 'Unable to prepare the chapter insert.';
                        } else {
                            try {
                                $conn->begin_transaction();
                                $inserted = 0;
                                foreach ($rows as [$name, $number]) {
                                    $insertStmt->bind_param('siiis', $name, $number, $classId, $bookId, $bookName);
                                    if (!$insertStmt->execute()) {
                                        throw new RuntimeException($insertStmt->error);
                                    }
                                    $inserted++;
                                }
                                $conn->commit();
                                $insertStmt->close();
                                header('Location: manage_chapters.php?msg=bulk_created&count=' . $inserted);
                                exit;
                            } catch (Throwable $e) {
                                $conn->rollback();
                                $insertStmt->close();
                                $error = 'No chapters were added. Please check the values and try again.';
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'create') {
            $name = trim($_POST['chapter_name'] ?? '');
            $chapterNo = intval($_POST['chapter_no'] ?? 0);
            $classId = intval($_POST['class_id'] ?? 0);
            $bookName = trim($_POST['book_name'] ?? '');

            if ($name === '') {
                $error = 'Chapter name is required.';
            } elseif ($chapterNo <= 0) {
                $error = 'Please enter a valid chapter number.';
            } elseif ($classId <= 0) {
                $error = 'Please select a valid class.';
            } elseif ($bookName === '') {
                $error = 'Please select a book.';
            } else {
                $bookStmt = $conn->prepare('SELECT book_id FROM book WHERE class_id = ? AND book_name = ? LIMIT 1');
                $bookId = null;
                if ($bookStmt) {
                    $bookStmt->bind_param('is', $classId, $bookName);
                    $bookStmt->execute();
                    $bookResult = $bookStmt->get_result();
                    if ($bookResult && ($bookRow = $bookResult->fetch_assoc())) {
                        $bookId = intval($bookRow['book_id']);
                    }
                    $bookStmt->close();
                }

                if (!$bookId) {
                    $error = 'The selected book could not be found.';
                } else {
                    $stmt = $conn->prepare('INSERT INTO chapter (chapter_name, chapter_no, class_id, book_id, book_name) VALUES (?, ?, ?, ?, ?)');
                    if ($stmt) {
                        $stmt->bind_param('siiis', $name, $chapterNo, $classId, $bookId, $bookName);
                        if ($stmt->execute()) {
                            header('Location: manage_chapters.php?msg=created');
                            exit;
                        }
                        $error = 'Failed to create chapter.';
                        $stmt->close();
                    } else {
                        $error = 'Database prepare error.';
                    }
                }
            }
        } elseif ($action === 'update') {
            $id = intval($_POST['chapter_id'] ?? 0);
            $name = trim($_POST['chapter_name'] ?? '');
            $chapterNo = intval($_POST['chapter_no'] ?? 0);
            $classId = intval($_POST['class_id'] ?? 0);
            $bookId = intval($_POST['book_id'] ?? 0);

            if ($id <= 0 || $name === '' || $chapterNo <= 0 || $classId <= 0 || $bookId <= 0) {
                $error = 'Chapter name, number, class, and book are required.';
            } else {
                $bookRow = null;
                $bookStmt = $conn->prepare('SELECT book_name FROM book WHERE book_id=? AND class_id=? LIMIT 1');
                if ($bookStmt) {
                    $bookStmt->bind_param('ii', $bookId, $classId);
                    $bookStmt->execute();
                    $bookResult = $bookStmt->get_result();
                    $bookRow = $bookResult ? $bookResult->fetch_assoc() : null;
                    $bookStmt->close();
                }
                if (!$bookRow) {
                    $error = 'The selected book does not belong to the selected class.';
                } else {
                    $bookName = $bookRow['book_name'];
                    $stmt = $conn->prepare('UPDATE chapter SET chapter_name=?, chapter_no=?, class_id=?, book_id=?, book_name=? WHERE chapter_id=?');
                    if ($stmt) {
                        $stmt->bind_param('siiisi', $name, $chapterNo, $classId, $bookId, $bookName, $id);
                        if ($stmt->execute()) {
                            header('Location: manage_chapters.php?msg=updated');
                            exit;
                        }
                        $error = 'Failed to update chapter.';
                        $stmt->close();
                    } else {
                        $error = 'Database prepare error.';
                    }
                }
            }
        } elseif ($action === 'delete') {
            // Delete is intentionally disabled in the UI. Keep this handler for legacy links only.
            $error = 'Chapter deletion is currently disabled.';
        }
    }
}

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $message = 'Chapter created successfully.';
            break;
        case 'bulk_created':
            $createdCount = max(0, intval($_GET['count'] ?? 0));
            $message = $createdCount . ' chapter' . ($createdCount === 1 ? '' : 's') . ' added successfully.';
            break;
        case 'updated':
            $message = 'Chapter updated successfully.';
            break;
    }
}

$search = trim($_GET['search'] ?? '');
$match = strtolower($_GET['match'] ?? '') === 'exact' ? 'exact' : 'contains';
$filterClassId = intval($_GET['filter_class_id'] ?? 0);
$filterBookId = intval($_GET['filter_book_id'] ?? 0);
$sortBy = trim($_GET['sort_by'] ?? 'chapter_id');
$sortDir = strtolower($_GET['sort_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$sortMap = [
    'chapter_id' => 'ch.chapter_id',
    'chapter_name' => 'ch.chapter_name',
    'chapter_no' => 'ch.chapter_no',
    'class_id' => 'ch.class_id',
    'book_name' => 'ch.book_name',
];
$orderExpr = $sortMap[$sortBy] ?? 'ch.chapter_id';

$wheres = [];
if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $operator = $match === 'exact' ? '=' : 'LIKE';
    $value = $match === 'exact' ? "'$safe'" : "'%$safe%'";
    $wheres[] = "(ch.chapter_name $operator $value OR ch.book_name $operator $value OR c.class_name $operator $value OR CAST(ch.chapter_no AS CHAR) $operator $value)";
}
if ($filterClassId > 0) $wheres[] = 'ch.class_id = ' . $filterClassId;
if ($filterBookId > 0) $wheres[] = 'b.book_id = ' . $filterBookId;
$whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$chapters = $conn->query("SELECT ch.chapter_id, ch.chapter_name, ch.chapter_no, ch.class_id, ch.book_name, c.class_name, b.book_id FROM chapter ch LEFT JOIN class c ON c.class_id = ch.class_id LEFT JOIN book b ON b.class_id = ch.class_id AND b.book_name = ch.book_name $whereSql ORDER BY $orderExpr $sortDir");
$chapterCount = $chapters ? $chapters->num_rows : 0;

include_once __DIR__ . '/../header.php';
?>

<main class="school-workspace" id="main-content">
    <a class="school-breadcrumb" href="../dashboard.php">← Back to dashboard</a>

    <section class="school-hero" aria-labelledby="chapter-page-title">
        <div>
            <span class="school-kicker">School content studio</span>
            <h1 id="chapter-page-title">Manage chapters</h1>
            <p>Set up the structure of each book once, then keep chapters tidy for question writing, notes, and exam generation.</p>
        </div>
        <div class="school-hero-mark" aria-hidden="true">01</div>
    </section>

    <?php if ($message): ?><div class="school-alert success" role="status">✓ <span><?= htmlspecialchars($message) ?></span></div><?php endif; ?>
    <?php if ($error): ?><div class="school-alert error" role="alert">! <span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>

    <div class="school-stats" aria-label="Chapter summary">
        <div class="school-stat"><span class="school-stat-value"><?= (int) $chapterCount ?></span><span class="school-stat-label">Visible chapters</span></div>
        <div class="school-stat"><span class="school-stat-value"><?= count($classOptions) ?></span><span class="school-stat-label">Classes available</span></div>
        <div class="school-stat"><span class="school-stat-value"><?= count($bookMap) ?></span><span class="school-stat-label">Books available</span></div>
    </div>

    <div class="school-layout">
        <section class="school-panel" aria-labelledby="bulk-chapter-heading">
            <div class="school-panel-header">
                <div>
                    <h2 id="bulk-chapter-heading">Add chapters in one go</h2>
                    <p>Choose the class and book once. Add as many chapter rows as you need.</p>
                </div>
                <span class="question-type-pill">Bulk create</span>
            </div>
            <div class="school-panel-body">
                <form method="POST" id="bulk-chapter-form">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="bulk_create">

                    <div class="school-step">
                        <span class="school-step-number">1</span>
                        <div class="school-grid-2" style="flex:1">
                            <div class="school-field">
                                <label for="chapter-class">Class</label>
                                <select class="school-select" id="chapter-class" name="class_id" required>
                                    <option value="">Choose a class</option>
                                    <?php foreach ($classOptions as $class): ?>
                                        <option value="<?= (int) $class['class_id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="school-field">
                                <label for="chapter-book">Book</label>
                                <select class="school-select" id="chapter-book" name="book_id" required disabled>
                                    <option value="">Choose a class first</option>
                                    <?php foreach ($bookMap as $book): ?>
                                        <option value="<?= (int) $book['book_id'] ?>" data-class-id="<?= (int) $book['class_id'] ?>"><?= htmlspecialchars($book['book_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="school-step">
                        <span class="school-step-number">2</span>
                        <div style="flex:1; min-width:0">
                            <div class="question-set-head">
                                <div><strong class="question-set-title">Chapter list</strong><small class="school-field-help">Chapter numbers are used for sorting and paper generation.</small></div>
                                <button class="school-button-secondary" type="button" id="add-chapter-row">＋ Add row</button>
                            </div>
                            <div class="chapter-rows" id="chapter-rows">
                                <div class="chapter-row" data-chapter-row>
                                    <span class="chapter-row-index">01</span>
                                    <div class="chapter-row-fields">
                                        <div class="school-field"><label>Chapter name</label><input class="school-input" type="text" name="chapter_name[]" placeholder="e.g. Sets and Functions" required></div>
                                        <div class="school-field"><label>No.</label><input class="school-input" type="number" name="chapter_no[]" min="1" placeholder="01" required></div>
                                    </div>
                                    <button class="school-button-quiet chapter-row-remove" type="button" aria-label="Remove chapter row" title="Remove row">×</button>
                                </div>
                            </div>
                            <p class="school-hint">ⓘ Empty rows are ignored. Completed rows are added together in one transaction, so the batch stays consistent if validation fails.</p>
                        </div>
                    </div>

                    <div class="school-actions">
                        <button class="school-button" type="submit">Save chapters</button>
                        <button class="school-button-quiet" type="reset" id="reset-chapter-form">Clear form</button>
                    </div>
                </form>
            </div>
        </section>

        <aside class="school-panel">
            <div class="school-panel-header">
                <div><h2>Keep the catalogue clean</h2><p>A couple of small habits make question management much faster later.</p></div>
            </div>
            <div class="school-panel-body">
                <div class="school-sidebar-note"><strong>Deletion is disabled</strong>Delete controls remain commented out on this page to protect chapter history. Use edit to correct names, numbers, or book placement.</div>
                <div class="school-hint">① Start chapter numbers at 1 and keep them unique within each book.</div>
                <div class="school-hint">② Use names students will recognize; they appear in filters and generated papers.</div>
                <div class="school-hint">③ Pick the class before the book so the selector only shows valid books.</div>
            </div>
        </aside>
    </div>

    <section class="school-panel" style="margin-top:22px" aria-labelledby="chapter-list-heading">
        <div class="school-panel-header">
            <div><h2 id="chapter-list-heading">Chapter catalogue</h2><p><?= (int) $chapterCount ?> result<?= $chapterCount === 1 ? '' : 's' ?> · Edit in place when a detail needs correcting.</p></div>
        </div>
        <form method="GET" class="school-filter" aria-label="Filter chapters">
            <div class="school-filter-row">
                <div><label class="school-filter-label" for="chapter-search">Search</label><input id="chapter-search" type="search" name="search" placeholder="Chapter, book, class, or number" value="<?= htmlspecialchars($search) ?>"></div>
                <div><label class="school-filter-label" for="chapter-match">Match</label><select id="chapter-match" name="match"><option value="contains" <?= $match === 'contains' ? 'selected' : '' ?>>Contains</option><option value="exact" <?= $match === 'exact' ? 'selected' : '' ?>>Exact</option></select></div>
                <div><label class="school-filter-label" for="chapter-class-filter">Class</label><select id="chapter-class-filter" name="filter_class_id"><option value="0">All classes</option><?php foreach ($classOptions as $class): ?><option value="<?= (int) $class['class_id'] ?>" <?= $filterClassId === (int) $class['class_id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="school-filter-row">
                <div><label class="school-filter-label" for="chapter-book-filter">Book</label><select id="chapter-book-filter" name="filter_book_id"><option value="0">All books</option><?php foreach ($bookMap as $book): ?><option value="<?= (int) $book['book_id'] ?>" data-class-id="<?= (int) $book['class_id'] ?>" <?= $filterBookId === (int) $book['book_id'] ? 'selected' : '' ?>><?= htmlspecialchars($book['book_name']) ?> · <?= htmlspecialchars((string) $book['class_id']) ?></option><?php endforeach; ?></select></div>
                <div><label class="school-filter-label" for="chapter-sort">Sort by</label><select id="chapter-sort" name="sort_by"><option value="chapter_id" <?= $sortBy === 'chapter_id' ? 'selected' : '' ?>>Recently added</option><option value="chapter_no" <?= $sortBy === 'chapter_no' ? 'selected' : '' ?>>Chapter number</option><option value="chapter_name" <?= $sortBy === 'chapter_name' ? 'selected' : '' ?>>Name</option><option value="book_name" <?= $sortBy === 'book_name' ? 'selected' : '' ?>>Book</option></select></div>
                <div><label class="school-filter-label" for="chapter-direction">Direction</label><select id="chapter-direction" name="sort_dir"><option value="asc" <?= $sortDir === 'ASC' ? 'selected' : '' ?>>Ascending</option><option value="desc" <?= $sortDir === 'DESC' ? 'selected' : '' ?>>Descending</option></select></div>
                <div class="school-filter-actions" style="align-items:end"><button class="school-button" type="submit">Apply filters</button><a class="school-button-quiet" href="manage_chapters.php">Reset</a></div>
            </div>
        </form>
        <div class="school-table-wrap">
            <table class="school-table">
                <thead><tr><th>ID</th><th>Chapter</th><th>Class</th><th>Book</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (!$chapters || $chapterCount === 0): ?><tr><td colspan="5" class="school-empty">No chapters match these filters yet.</td></tr><?php endif; ?>
                <?php $rowIndex = 0; while ($chapters && ($row = $chapters->fetch_assoc())): ?>
                    <tr>
                        <td class="row-id">#<?= (int) $row['chapter_id'] ?></td>
                        <td><span class="row-title"><?= htmlspecialchars($row['chapter_name']) ?></span><span class="row-meta">Chapter <?= (int) $row['chapter_no'] ?></span></td>
                        <td><?= htmlspecialchars($row['class_name'] ?? (string) $row['class_id']) ?></td>
                        <td><?= htmlspecialchars($row['book_name']) ?></td>
                        <td>
                            <div class="action-stack">
                                <button class="school-button-secondary" type="button" data-edit-chapter="<?= (int) $row['chapter_id'] ?>">Edit</button>
                                <!-- <button type="submit" class="school-button-quiet">Delete</button> -->
                                <span class="school-disabled-action" title="Delete is disabled">Delete disabled</span>
                            </div>
                        </td>
                    </tr>
                    <tr id="edit-chapter-<?= (int) $row['chapter_id'] ?>" hidden>
                        <td colspan="5">
                            <form method="POST" class="inline-edit">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="chapter_id" value="<?= (int) $row['chapter_id'] ?>">
                                <input class="school-input" type="text" name="chapter_name" value="<?= htmlspecialchars($row['chapter_name']) ?>" aria-label="Chapter name" required>
                                <input class="school-input" type="number" name="chapter_no" value="<?= (int) $row['chapter_no'] ?>" min="1" aria-label="Chapter number" required>
                                <select class="school-select" name="class_id" aria-label="Class" required><?php foreach ($classOptions as $class): ?><option value="<?= (int) $class['class_id'] ?>" <?= (int) $class['class_id'] === (int) $row['class_id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?></select>
                                <select class="school-select" name="book_id" aria-label="Book" required><?php foreach ($bookMap as $book): ?><option value="<?= (int) $book['book_id'] ?>" data-class-id="<?= (int) $book['class_id'] ?>" <?= (int) $book['book_id'] === (int) $row['book_id'] ? 'selected' : '' ?>><?= htmlspecialchars($book['book_name']) ?></option><?php endforeach; ?></select>
                                <button class="school-button" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php $rowIndex++; endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
(function () {
    const bookData = <?= json_encode($bookMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const classSelect = document.getElementById('chapter-class');
    const bookSelect = document.getElementById('chapter-book');
    const rows = document.getElementById('chapter-rows');
    const addRowButton = document.getElementById('add-chapter-row');
    const form = document.getElementById('bulk-chapter-form');

    function refreshBooks(select, classId, selectedValue) {
        if (!select) return;
        select.innerHTML = '<option value="">' + (classId ? 'Choose a book' : 'Choose a class first') + '</option>';
        bookData.filter(book => String(book.class_id) === String(classId)).forEach(book => {
            const option = document.createElement('option');
            option.value = book.book_id;
            option.textContent = book.book_name;
            option.dataset.classId = book.class_id;
            option.selected = String(book.book_id) === String(selectedValue || '');
            select.appendChild(option);
        });
        select.disabled = !classId;
    }

    function renumberRows() {
        rows.querySelectorAll('[data-chapter-row]').forEach((row, index) => {
            row.querySelector('.chapter-row-index').textContent = String(index + 1).padStart(2, '0');
            row.querySelector('.chapter-row-remove').hidden = rows.children.length === 1;
        });
    }

    function addRow() {
        const row = rows.querySelector('[data-chapter-row]').cloneNode(true);
        row.querySelectorAll('input').forEach(input => { input.value = ''; });
        rows.appendChild(row);
        renumberRows();
        row.querySelector('input[name="chapter_name[]"]').focus();
    }

    classSelect?.addEventListener('change', () => refreshBooks(bookSelect, classSelect.value));
    addRowButton?.addEventListener('click', addRow);
    rows?.addEventListener('click', event => {
        if (!event.target.closest('.chapter-row-remove')) return;
        const row = event.target.closest('[data-chapter-row]');
        if (rows.children.length > 1) row.remove();
        renumberRows();
    });
    form?.addEventListener('reset', () => setTimeout(() => {
        refreshBooks(bookSelect, '');
        rows.innerHTML = rows.firstElementChild.outerHTML;
        renumberRows();
    }, 0));
    renumberRows();

    document.querySelectorAll('[data-edit-chapter]').forEach(button => {
        button.addEventListener('click', () => {
            const row = document.getElementById('edit-chapter-' + button.dataset.editChapter);
            if (row) row.hidden = !row.hidden;
        });
    });

    document.querySelectorAll('.inline-edit').forEach(editForm => {
        const classSelect = editForm.querySelector('select[name="class_id"]');
        const bookSelect = editForm.querySelector('select[name="book_id"]');
        if (!classSelect || !bookSelect) return;
        const allBooks = Array.from(bookSelect.options).map(option => ({ value: option.value, label: option.textContent, classId: option.dataset.classId, selected: option.selected }));
        const syncEditBooks = () => {
            const currentBook = bookSelect.value;
            bookSelect.innerHTML = '';
            allBooks.filter(book => book.classId === classSelect.value).forEach(book => {
                const option = new Option(book.label, book.value);
                option.dataset.classId = book.classId;
                option.selected = book.value === currentBook || (currentBook === '' && book.selected);
                bookSelect.add(option);
            });
            if (!bookSelect.options.length) bookSelect.add(new Option('No books for this class', ''));
        };
        classSelect.addEventListener('change', syncEditBooks);
        syncEditBooks();
    });

    const filterClass = document.getElementById('chapter-class-filter');
    const filterBook = document.getElementById('chapter-book-filter');
    function filterBookOptions() {
        const selectedClass = filterClass?.value || '0';
        if (!filterBook) return;
        Array.from(filterBook.options).forEach(option => {
            option.hidden = option.value !== '0' && selectedClass !== '0' && option.dataset.classId !== selectedClass;
        });
        if (filterBook.selectedOptions[0]?.hidden) filterBook.value = '0';
    }
    filterClass?.addEventListener('change', filterBookOptions);
    filterBookOptions();
})();
</script>

<?php include_once __DIR__ . '/../footer.php'; ?>
