<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

// Fetch question statistics
$sql = "SELECT 
            c.class_name,
            b.book_name,
            COUNT(CASE WHEN q.question_type = 'mcq' THEN 1 END) as mcq_count,
            COUNT(CASE WHEN q.question_type = 'short' THEN 1 END) as short_count,
            COUNT(CASE WHEN q.question_type = 'long' THEN 1 END) as long_count,
            COUNT(q.id) as total_count
        FROM book b
        JOIN class c ON b.class_id = c.class_id
        LEFT JOIN questions q ON b.book_id = q.book_id
        GROUP BY c.class_id, b.book_id
        ORDER BY c.class_name, b.book_name";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questions Overview - Admin Dashboard</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stats-table th, .stats-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .stats-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .stats-table tr:hover {
            background-color: #f5f5f5;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
            min-width: 30px;
            text-align: center;
        }
        .badge-mcq { background-color: #17a2b8; }
        .badge-short { background-color: #ffc107; color: #000; }
        .badge-long { background-color: #28a745; }
        .badge-total { background-color: #6c757d; }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .print-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .print-btn:hover {
            background: #0056b3;
        }
        @media print {
            header, footer, .nav, .admin-navbar { display: none; }
            .admin-container { margin: 0; padding: 0; width: 100%; }
            .print-btn { display: none; }
            .card { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="admin-container">
        <div class="page-header">
            <h1>Questions Overview</h1>
            <button onclick="window.print()" class="print-btn">🖨️ Print Report</button>
        </div>

        <div class="card">
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Book</th>
                        <th>MCQs</th>
                        <th>Short Questions</th>
                        <th>Long Questions</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['class_name']) ?></td>
                                <td><?= htmlspecialchars($row['book_name']) ?></td>
                                <td><span class="badge badge-mcq"><?= $row['mcq_count'] ?></span></td>
                                <td><span class="badge badge-short"><?= $row['short_count'] ?></span></td>
                                <td><span class="badge badge-long"><?= $row['long_count'] ?></span></td>
                                <td><span class="badge badge-total"><?= $row['total_count'] ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">No books or questions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>
