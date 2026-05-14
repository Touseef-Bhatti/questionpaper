<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

$id = intval($_GET['id'] ?? 0);
if ($id) {
    $stmt = $conn->prepare("DELETE FROM exam_preparations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}
header("Location: index.php?msg=deleted");
?>
