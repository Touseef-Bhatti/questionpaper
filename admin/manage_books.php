<?php
// Enable error reporting for debugging (only in development)
if (isset($_GET['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

try {
    require_once __DIR__ . '/../db_connect.php';
} catch (Exception $e) {
    error_log('Database connection error in manage_books.php: ' . $e->getMessage());
    die('Database connection failed. Please check server configuration.');
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    header('Location: login.php');
    exit;
}

// Load classes for FK with error handling
try {
    $classes = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
    if (!$classes) {
        throw new Exception('Failed to fetch classes: ' . $conn->error);
    }
    $classOptions = [];
    while ($row = $classes->fetch_assoc()) { 
        $classOptions[] = $row; 
    }
} catch (Exception $e) {
    error_log('Error loading classes in manage_books.php: ' . $e->getMessage());
    $classOptions = [];
    $message = 'Error loading classes. Please contact administrator.';
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = trim($_POST['book_name'] ?? '');
        $classId = intval($_POST['class_id'] ?? 0);
        if ($name !== '' && $classId > 0) {
            $nameEsc = $conn->real_escape_string($name);
            if ($conn->query("INSERT INTO book (book_name, class_id) VALUES ('$nameEsc', $classId)")) {
                header('Location: manage_books.php?msg=created');
                exit;
            } else {
                $message = 'Error creating book: ' . $conn->error;
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['book_id'] ?? 0);
        $name = trim($_POST['book_name'] ?? '');
        $classId = intval($_POST['class_id'] ?? 0);
        if ($id > 0 && $name !== '' && $classId > 0) {
            $nameEsc = $conn->real_escape_string($name);
            if ($conn->query("UPDATE book SET book_name='$nameEsc', class_id=$classId WHERE book_id=$id")) {
                header('Location: manage_books.php?msg=updated');
                exit;
            } else {
                $message = 'Error updating book: ' . $conn->error;
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['book_id'] ?? 0);
        if ($id > 0) {
            if ($conn->query("DELETE FROM book WHERE book_id=$id")) {
                header('Location: manage_books.php?msg=deleted');
                exit;
            } else {
                $message = 'Error deleting book: ' . $conn->error;
            }
        }
    }
}

// Handle success messages from redirects
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $message = 'Book created successfully.';
            break;
        case 'updated':
            $message = 'Book updated successfully.';
            break;
        case 'deleted':
            $message = 'Book deleted successfully.';
            break;
    }
}

$books = $conn->query("SELECT b.book_id, b.book_name, c.class_id, c.class_name FROM book b JOIN class c ON c.class_id=b.class_id ORDER BY b.book_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/footer.css">
    
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="wrap">
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        <h1>Manage Books</h1>
        <?php if ($message): ?><p class="msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <h3>Create New Book</h3>
        <form method="POST" class="row">
            <input type="hidden" name="action" value="create">
            <input type="text" name="book_name" placeholder="Book name" required>
            <select name="class_id" required>
                <option value="">Select class</option>
                <?php foreach ($classOptions as $co): ?>
                    <option value="<?= (int)$co['class_id'] ?>"><?= htmlspecialchars($co['class_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Add</button>
        </form>

        <h3>Existing Books</h3>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Class</th><th>Actions</th></tr></thead>
            <tbody>
            <?php while ($row = $books->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row['book_id'] ?></td>
                    <td>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="book_id" value="<?= (int)$row['book_id'] ?>">
                            <input type="text" name="book_name" value="<?= htmlspecialchars($row['book_name']) ?>" required>
                            <select name="class_id" required>
                                <?php foreach ($classOptions as $co): ?>
                                    <option value="<?= (int)$co['class_id'] ?>" <?= ((int)$co['class_id'] === (int)$row['class_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($co['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Save</button>
                        </form>
                    </td>
                    <td><?= htmlspecialchars($row['class_name']) ?></td>
                    <td>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this book?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="book_id" value="<?= (int)$row['book_id'] ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>


