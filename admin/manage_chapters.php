<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAdminAuth();

// Load classes and books for selection
$classes = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
$books = $conn->query("SELECT book_id, book_name, class_id FROM book ORDER BY book_name ASC");
$bookMap = [];
while ($books && $row = $books->fetch_assoc()) { $bookMap[] = $row; }

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token. Please reload the page.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
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
                try {
                    // Get book_id from book table based on class_id and book_name
                    $bookStmt = $conn->prepare("SELECT book_id FROM book WHERE class_id = ? AND book_name = ? LIMIT 1");
                    $bookId = null;
                    if ($bookStmt) {
                        $bookStmt->bind_param("is", $classId, $bookName);
                        $bookStmt->execute();
                        $result = $bookStmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $bookId = intval($row['book_id']);
                        }
                        $bookStmt->close();
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO chapter (chapter_name, chapter_no, class_id, book_id, book_name) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("siiis", $name, $chapterNo, $classId, $bookId, $bookName);
                        if ($stmt->execute()) {
                            header('Location: manage_chapters.php?msg=created');
                            exit;
                        } else {
                            $error = 'Failed to create chapter: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error = 'Database prepare error: ' . $conn->error;
                    }
                } catch (Exception $e) {
                    $error = 'Error creating chapter: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'update') {
            $id = intval($_POST['chapter_id'] ?? 0);
            $name = trim($_POST['chapter_name'] ?? '');
            $classId = intval($_POST['class_id'] ?? 0);
            $bookName = trim($_POST['book_name'] ?? '');
            
            if ($id <= 0) {
                $error = 'Invalid chapter ID.';
            } elseif ($name === '') {
                $error = 'Chapter name is required.';
            } elseif ($classId <= 0) {
                $error = 'Please select a valid class.';
            } elseif ($bookName === '') {
                $error = 'Please select a book.';
            } else {
                try {
                    $stmt = $conn->prepare("UPDATE chapter SET chapter_name=?, class_id=?, book_name=? WHERE chapter_id=?");
                    if ($stmt) {
                        $stmt->bind_param("sisi", $name, $classId, $bookName, $id);
                        if ($stmt->execute()) {
                            if ($stmt->affected_rows > 0) {
                                header('Location: manage_chapters.php?msg=updated');
                                exit;
                            } else {
                                $error = 'Chapter not found or no changes made.';
                            }
                        } else {
                            $error = 'Failed to update chapter: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error = 'Database prepare error: ' . $conn->error;
                    }
                } catch (Exception $e) {
                    $error = 'Error updating chapter: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['chapter_id'] ?? 0);
            if ($id <= 0) {
                $error = 'Invalid chapter ID.';
            } else {
                try {
                    $stmt = $conn->prepare("DELETE FROM chapter WHERE chapter_id=?");
                    if ($stmt) {
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            if ($stmt->affected_rows > 0) {
                                header('Location: manage_chapters.php?msg=deleted');
                                exit;
                            } else {
                                $error = 'Chapter not found.';
                            }
                        } else {
                            $error = 'Failed to delete chapter: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error = 'Database prepare error: ' . $conn->error;
                    }
                } catch (Exception $e) {
                    $error = 'Error deleting chapter: ' . $e->getMessage();
                }
            }
        }
    }
}

// Handle success messages from redirects
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $message = 'Chapter created successfully.';
            break;
        case 'updated':
            $message = 'Chapter updated successfully.';
            break;
        case 'deleted':
            $message = 'Chapter deleted successfully.';
            break;
    }
}

// Filters and sorting
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$match = isset($_GET['match']) && strtolower($_GET['match']) === 'exact' ? 'exact' : 'contains';
$filterClassId = isset($_GET['filter_class_id']) ? intval($_GET['filter_class_id']) : 0;
$filterBookId = isset($_GET['filter_book_id']) ? intval($_GET['filter_book_id']) : 0;
$sortBy = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'chapter_id';
$sortDir = strtolower($_GET['sort_dir'] ?? 'asc');
$sortDir = $sortDir === 'desc' ? 'DESC' : 'ASC';

$sortMap = [
    'chapter_id' => 'ch.chapter_id',
    'chapter_name' => 'ch.chapter_name',
    'class_id' => 'ch.class_id',
    'book_name' => 'ch.book_name',
];
$orderExpr = $sortMap[$sortBy] ?? 'ch.chapter_id';

$wheres = [];
if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    if ($match === 'exact') {
        $wheres[] = "(ch.chapter_name = '$safe' OR ch.book_name = '$safe' OR c.class_name = '$safe' OR CAST(ch.class_id AS CHAR) = '$safe')";
    } else {
        $like = "%$safe%";
        $wheres[] = "(ch.chapter_name LIKE '$like' OR ch.book_name LIKE '$like' OR c.class_name LIKE '$like' OR CAST(ch.class_id AS CHAR) LIKE '$like')";
    }
}
if ($filterClassId > 0) {
    $wheres[] = "ch.class_id = $filterClassId";
}
if ($filterBookId > 0) {
    // join book to filter by book_id against chapter.book_name + class_id
    $wheres[] = "(b.book_id = $filterBookId)";
}
$whereSql = count($wheres) ? ('WHERE ' . implode(' AND ', $wheres)) : '';

$chapters = $conn->query("SELECT ch.chapter_id, ch.chapter_name, ch.class_id, ch.book_name, c.class_name, b.book_id FROM chapter ch LEFT JOIN class c ON c.class_id = ch.class_id LEFT JOIN book b ON b.class_id = ch.class_id AND b.book_name = ch.book_name $whereSql ORDER BY $orderExpr $sortDir");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Chapters</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/footer.css">
    
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="wrap">
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        <h1>Manage Chapters</h1>
        <?php if ($message): ?><p class="msg" style="color:green;"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error" style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <h3>Create New Chapter</h3>
        <form method="POST" class="row">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="create">
            <input type="text" name="chapter_name" placeholder="Chapter name" required>
            <input type="number" name="chapter_no" placeholder="Chapter number" min="1" required>
            <select name="class_id" id="class_select" required onchange="updateBookOptions()">
                <option value="">Select class</option>
                <?php if ($classes) while ($c = $classes->fetch_assoc()): ?>
                    <option value="<?= (int)$c['class_id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <select name="book_name" id="book_select" required>
                <option value="">Select book</option>
            </select>
            <button type="submit">Add</button>
        </form>

        <h3>Existing Chapters</h3>
        <form method="GET" class="row" style="margin-bottom:8px;">
            <input type="text" name="search" placeholder="Search by chapter, book, or class" value="<?= htmlspecialchars($search) ?>">
            <select name="match">
                <option value="contains" <?= $match==='contains'?'selected':'' ?>>Contains</option>
                <option value="exact" <?= $match==='exact'?'selected':'' ?>>Exact</option>
            </select>
            <select name="filter_class_id">
                <option value="0">All classes</option>
                <?php
                $clsRes2 = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
                while ($cc = $clsRes2->fetch_assoc()): ?>
                    <option value="<?= (int)$cc['class_id'] ?>" <?= $filterClassId===(int)$cc['class_id']?'selected':'' ?>><?= htmlspecialchars($cc['class_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <select name="filter_book_id">
                <option value="0">All books</option>
                <?php foreach ($bookMap as $bk): ?>
                    <option value="<?= (int)$bk['book_id'] ?>" <?= $filterBookId===(int)$bk['book_id']?'selected':'' ?>><?= htmlspecialchars($bk['book_name']) ?> (Class <?= (int)$bk['class_id'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <select name="sort_by">
                <option value="chapter_id" <?= $sortBy==='chapter_id'?'selected':'' ?>>Sort by ID</option>
                <option value="chapter_name" <?= $sortBy==='chapter_name'?'selected':'' ?>>Sort by Name</option>
                <option value="class_id" <?= $sortBy==='class_id'?'selected':'' ?>>Sort by Class</option>
                <option value="book_name" <?= $sortBy==='book_name'?'selected':'' ?>>Sort by Book</option>
            </select>
            <select name="sort_dir">
                <option value="asc" <?= strtolower($sortDir)==='asc'?'selected':'' ?>>ASC</option>
                <option value="desc" <?= strtolower($sortDir)==='desc'?'selected':'' ?>>DESC</option>
            </select>
            <button type="submit">Apply</button>
        </form>
        <table>
            <thead><tr>
                <th><a href="?<?= http_build_query(['search'=>$search,'sort_by'=>'chapter_id','sort_dir'=> strtolower($sortDir)==='asc'?'desc':'asc']) ?>">ID</a></th>
                <th><a href="?<?= http_build_query(['search'=>$search,'sort_by'=>'chapter_name','sort_dir'=> strtolower($sortDir)==='asc'?'desc':'asc']) ?>">Name</a></th>
                <th>Class</th>
                <th><a href="?<?= http_build_query(['search'=>$search,'sort_by'=>'book_name','sort_dir'=> strtolower($sortDir)==='asc'?'desc':'asc']) ?>">Book Name</a></th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php $rowIndex = 0; ?>
            <?php while ($row = $chapters->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row['chapter_id'] ?></td>
                    <td>
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="chapter_id" value="<?= (int)$row['chapter_id'] ?>">
                            <input type="text" name="chapter_name" value="<?= htmlspecialchars($row['chapter_name']) ?>" required>
                            <select name="class_id" id="edit_class_select_<?= $rowIndex ?>" required onchange="updateEditBookOptions(<?= $rowIndex ?>)">
                                <?php
                                // reload classes for each row
                                $clsRes = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
                                while ($cls = $clsRes->fetch_assoc()): ?>
                                    <option value="<?= (int)$cls['class_id'] ?>" <?= ((int)$cls['class_id'] === (int)$row['class_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cls['class_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <select name="book_name" id="edit_book_select_<?= $rowIndex ?>" required>
                                <option value="<?= htmlspecialchars($row['book_name']) ?>" selected><?= htmlspecialchars($row['book_name']) ?></option>
                            </select>
                            <button type="submit">Save</button>
                        </form>
                        <script>
                            // Initialize edit form dropdowns for row <?= $rowIndex ?>
                            document.addEventListener('DOMContentLoaded', function() {
                                updateEditBookOptions(<?= $rowIndex ?>);
                            });
                        </script>
                    </td>
                    <td><?= htmlspecialchars($row['class_name'] ?? (string)(int)$row['class_id']) ?></td>
                    <td><?= htmlspecialchars($row['book_name']) ?></td>
                    <td>
                        <!-- Delete button commented out -->
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this chapter?');">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="chapter_id" value="<?= (int)$row['chapter_id'] ?>">
                            <button type="submit">Delete</button>
                        </form>
                       
                    </td>
                </tr>
            <?php $rowIndex++; endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../footer.php'; ?>
    
    <script>
        // Book data from PHP
        const bookData = <?= json_encode($bookMap) ?>;
        
        function updateBookOptions() {
            const classSelect = document.getElementById('class_select');
            const bookSelect = document.getElementById('book_select');
            const selectedClassId = parseInt(classSelect.value);
            
            // Clear existing options
            bookSelect.innerHTML = '<option value="">Select book</option>';
            
            if (selectedClassId) {
                // Filter books by selected class
                const filteredBooks = bookData.filter(book => parseInt(book.class_id) === selectedClassId);
                
                // Add book options
                filteredBooks.forEach(book => {
                    const option = document.createElement('option');
                    option.value = book.book_name;
                    option.textContent = book.book_name;
                    bookSelect.appendChild(option);
                });
                
                // Enable book select
                bookSelect.disabled = false;
            } else {
                // Disable book select if no class is selected
                bookSelect.disabled = true;
            }
        }
        
        function updateEditBookOptions(rowIndex) {
            const classSelect = document.getElementById('edit_class_select_' + rowIndex);
            const bookSelect = document.getElementById('edit_book_select_' + rowIndex);
            const selectedClassId = parseInt(classSelect.value);
            const currentBookName = bookSelect.options[0].value; // preserve current selection
            
            // Clear existing options
            bookSelect.innerHTML = '';
            
            if (selectedClassId) {
                // Filter books by selected class
                const filteredBooks = bookData.filter(book => parseInt(book.class_id) === selectedClassId);
                
                // Add book options
                filteredBooks.forEach(book => {
                    const option = document.createElement('option');
                    option.value = book.book_name;
                    option.textContent = book.book_name;
                    // Keep the current book selected if it exists in the filtered list
                    if (book.book_name === currentBookName) {
                        option.selected = true;
                    }
                    bookSelect.appendChild(option);
                });
                
                // If current book is not in the filtered list, add it as the first option
                if (!filteredBooks.some(book => book.book_name === currentBookName) && currentBookName) {
                    const currentOption = document.createElement('option');
                    currentOption.value = currentBookName;
                    currentOption.textContent = currentBookName + ' (current)';
                    currentOption.selected = true;
                    bookSelect.insertBefore(currentOption, bookSelect.firstChild);
                }
                
                // Enable book select
                bookSelect.disabled = false;
            } else {
                // Disable book select if no class is selected
                bookSelect.disabled = true;
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const bookSelect = document.getElementById('book_select');
            bookSelect.disabled = true; // Initially disabled
        });
    </script>
</body>
</html>


