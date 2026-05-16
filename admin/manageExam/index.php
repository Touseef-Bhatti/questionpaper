<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

include_once __DIR__ . '/../header.php';

$msg = $_GET['msg'] ?? '';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🎓 Exam Preparation Management</h2>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New Exam
        </a>
    </div>

    <?php if ($msg === 'created'): ?>
        <div class="alert alert-success">Exam created successfully!</div>
    <?php elseif ($msg === 'updated'): ?>
        <div class="alert alert-success">Exam updated successfully!</div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert-warning">Exam deleted successfully!</div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Class</th>
                            <th>Book</th>
                            <th>Type</th>
                            <th>Questions</th>
                            <th>Date Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT e.*, c.class_name, b.book_name 
                                  FROM exam_preparations e
                                  JOIN class c ON e.class_id = c.class_id
                                  JOIN book b ON e.book_id = b.book_id
                                  ORDER BY e.created_at DESC";
                        $result = $conn->query($query);
                        if ($result && $result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                                $q_counts = "MCQs: {$row['mcq_count']}, Short: {$row['short_count']}, Long: {$row['long_count']}";
                        ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['class_name']) ?></td>
                                <td><?= htmlspecialchars($row['book_name']) ?></td>
                                <td><span class="badge bg-info"><?= ucfirst($row['selection_type']) ?></span></td>
                                <td><small><?= $q_counts ?></small></td>
                                <td><?= date('Y-m-d', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')" title="Delete"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No exams found. Click "Create New" to start.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

