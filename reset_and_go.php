<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Clear relevant session variables to "ignore mode"
unset($_SESSION['selected_topics']);
unset($_SESSION['host_quiz_topics']);
unset($_SESSION['source']);
unset($_SESSION['quiz_duration']);
unset($_SESSION['study_level']);
unset($_SESSION['mcq_count']);

// Redirect to the target page
$redirect = $_GET['redirect'] ?? 'index.php';
header("Location: " . $redirect);
exit;
?>