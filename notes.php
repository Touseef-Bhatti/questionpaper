<?php
// Require authentication before accessing this page
require_once 'auth/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes - Ahmad Learning Hub</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/notes.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="notes-content">
            <h2>Notes</h2>
            <p>Access and download notes for various subjects and classes. More features coming soon!</p>
        </div>
    </div> <!-- main-content -->

    <?php include 'footer.php'; ?>
</body>
</html>
