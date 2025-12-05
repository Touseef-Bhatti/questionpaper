<?php
// 🚀 Ahmad Learning Hub Environment Test

// 1. Show PHP version
echo "<h2>🚀 Ahmad Learning Hub Environment Test</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

// 2. Database connection test
$host = "localhost";
$user = "bhattich_touseef";       // 👉 Replace with your real DB username from cPanel
$pass = "Touseef@321";   // 👉 Replace with your DB password
$db   = "bhattich_questionbank";       // 👉 Replace with your DB name

echo "<h3>1️⃣ Database Connection Test</h3>";

$conn = @new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("<p>❌ Database connection failed: " . htmlspecialchars($conn->connect_error) . "</p>");
} else {
    echo "<p>✔ Database connected successfully!</p>";
}

// 3. Simple query test
echo "<h3>2️⃣ Database Query Test</h3>";

$result = $conn->query("SELECT NOW() AS `db_time`");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>✔ Simple query OK. Current DB time: " . htmlspecialchars($row['db_time']) . "</p>";
} else {
    echo "<p>❌ Query failed: " . htmlspecialchars($conn->error) . "</p>";
}

// 4. Close connection
$conn->close();

echo "<h3>✅ Test Finished</h3>";
?>
