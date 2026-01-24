<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

if (session_status() === PHP_SESSION_NONE) session_start();

// Create / Update / Delete actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid CSRF token. Please reload the page.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $name = trim($_POST['class_name'] ?? '');
            if ($name !== '') {
                $stmt = $conn->prepare("INSERT INTO class (class_name) VALUES (?)");
                $stmt->bind_param("s", $name);
                if ($stmt->execute()) {
                    header('Location: manage_classes.php?msg=created');
                    exit;
                } else {
                    $message = 'Error creating class: ' . $stmt->error;
                }
                $stmt->close();
            }
        } elseif ($action === 'update') {
            $id = intval($_POST['class_id'] ?? 0);
            $name = trim($_POST['class_name'] ?? '');
            if ($id > 0 && $name !== '') {
                $stmt = $conn->prepare("UPDATE class SET class_name=? WHERE class_id=?");
                $stmt->bind_param("si", $name, $id);
                if ($stmt->execute()) {
                    header('Location: manage_classes.php?msg=updated');
                    exit;
                } else {
                    $message = 'Error updating class: ' . $stmt->error;
                }
                $stmt->close();
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['class_id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM class WHERE class_id=?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    header('Location: manage_classes.php?msg=deleted');
                    exit;
                } else {
                    $message = 'Error deleting class: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle success messages from redirects
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $message = 'Class created successfully.';
            break;
        case 'updated':
            $message = 'Class updated successfully.';
            break;
        case 'deleted':
            $message = 'Class deleted successfully.';
            break;
    }
}

$classes = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/footer.css">
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="wrap">
    <div class="wrap">
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        <h1>Manage Classes</h1>
        <?php if ($message): ?><p class="msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <h3>Create New Class</h3>
        <form method="POST" class="row">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="create">
            <input type="text" name="class_name" placeholder="Class name" required>
            <button type="submit">Add</button>
        </form>

        <h3>Existing Classes</h3>
        <table>
            <thead>
                <tr><th>ID</th><th>Name</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php while ($row = $classes->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row['class_id'] ?></td>
                    <td>
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="class_id" value="<?= (int)$row['class_id'] ?>">
                            <input type="text" name="class_name" value="<?= htmlspecialchars($row['class_name']) ?>" required>
                            <button type="submit">Save</button>
                        </form>
                    </td>
                    <td>
                        <!-- Delete button commented out -->
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this class?');">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="class_id" value="<?= (int)$row['class_id'] ?>">
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


