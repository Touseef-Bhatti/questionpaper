<?php
// Simplified Select Class Test
// This will help identify exactly where the error occurs
echo "<h2>Testing Select Class Functionality</h2>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .error{color:red;background:#ffe6e6;padding:10px;border-radius:5px;margin:10px 0;}</style>";

echo "<p><strong>Step 1:</strong> Testing basic PHP...</p>";
echo "✅ PHP is working<br>";

echo "<p><strong>Step 2:</strong> Testing environment loading...</p>";
try {
    require_once 'config/env.php';
    echo "✅ Environment loaded<br>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Environment Error: " . $e->getMessage() . "</div>";
    exit;
}

echo "<p><strong>Step 3:</strong> Testing database connection...</p>";
try {
    require_once 'db_connect.php';
    echo "✅ Database connected<br>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Database Error: " . $e->getMessage() . "</div>";
    exit;
}

echo "<p><strong>Step 4:</strong> Testing class query...</p>";
try {
    $classQuery = "SELECT class_id, class_name FROM class";
    $classResult = $conn->query($classQuery);
    
    if ($classResult) {
        echo "✅ Query executed successfully<br>";
        echo "✅ Found " . $classResult->num_rows . " classes<br>";
        
        if ($classResult->num_rows > 0) {
            echo "<p><strong>Available Classes:</strong></p>";
            echo "<ul>";
            while ($row = $classResult->fetch_assoc()) {
                echo "<li>" . htmlspecialchars($row['class_name']) . " (ID: " . $row['class_id'] . ")</li>";
            }
            echo "</ul>";
        } else {
            echo "<div class='error'>⚠️ No classes found in database</div>";
        }
    } else {
        echo "<div class='error'>❌ Query failed: " . $conn->error . "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Query Error: " . $e->getMessage() . "</div>";
}

echo "<p><strong>Step 5:</strong> Testing header inclusion...</p>";
try {
    ob_start();
    include 'header.php';
    $headerContent = ob_get_clean();
    echo "✅ Header loaded successfully<br>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Header Error: " . $e->getMessage() . "</div>";
}

echo "<br><h3>✅ All Tests Complete</h3>";
echo "<p>If all tests passed, the original select_class.php should work.</p>";
echo "<p><a href='select_class.php'>Try Select Class Page</a></p>";
echo "<p><a href='index.php'>Back to Homepage</a></p>";

echo "<br><p><strong>⚠️ DELETE THIS FILE after testing!</strong></p>";
?>
