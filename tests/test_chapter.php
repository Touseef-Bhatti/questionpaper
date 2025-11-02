<?php
// Test file for chapter management debugging
require_once __DIR__ . '/db_connect.php';

echo "<h2>Chapter Management Debug Test</h2>";

// Test 1: Check if chapter table exists and its structure
echo "<h3>Test 1: Chapter Table Structure</h3>";
try {
    $result = $conn->query("DESCRIBE chapter");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Exception: " . $e->getMessage() . "</p>";
}

// Test 2: Check current chapters
echo "<h3>Test 2: Current Chapters</h3>";
try {
    $result = $conn->query("SELECT * FROM chapter LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>Chapter ID</th><th>Chapter Name</th><th>Class ID</th><th>Book Name</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['chapter_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['chapter_name'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['class_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['book_name'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No chapters found or error: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Exception: " . $e->getMessage() . "</p>";
}

// Test 3: Check available classes
echo "<h3>Test 3: Available Classes</h3>";
try {
    $result = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>Class ID</th><th>Class Name</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['class_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['class_name'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No classes found or error: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Exception: " . $e->getMessage() . "</p>";
}

// Test 4: Test INSERT with explicit NULL for auto-increment
echo "<h3>Test 4: Test Chapter Insert</h3>";
try {
    // First, let's see what the next auto-increment value should be
    $result = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chapter'");
    if ($result && $row = $result->fetch_assoc()) {
        echo "<p>Next AUTO_INCREMENT value should be: " . $row['AUTO_INCREMENT'] . "</p>";
    }
    
    // Test INSERT without specifying chapter_id (let auto-increment handle it)
    $testName = "Test Chapter " . date('Y-m-d H:i:s');
    $testClassId = 1; // Assuming class 1 exists
    $testBookName = "Test Book";
    
    echo "<p>Attempting to insert: Chapter='$testName', Class_ID=$testClassId, Book='$testBookName'</p>";
    
    // Using prepared statement to be safe
    $stmt = $conn->prepare("INSERT INTO chapter (chapter_name, class_id, book_name) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sis", $testName, $testClassId, $testBookName);
        if ($stmt->execute()) {
            echo "<p style='color:green;'>SUCCESS: Chapter inserted with ID: " . $conn->insert_id . "</p>";
            
            // Clean up - delete the test record
            $testId = $conn->insert_id;
            $conn->query("DELETE FROM chapter WHERE chapter_id = $testId");
            echo "<p>Test record cleaned up.</p>";
        } else {
            echo "<p style='color:red;'>ERROR executing statement: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red;'>ERROR preparing statement: " . $conn->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Exception during insert test: " . $e->getMessage() . "</p>";
}

// Test 5: Check for any constraints or triggers
echo "<h3>Test 5: Check Table Constraints</h3>";
try {
    $result = $conn->query("SELECT 
        CONSTRAINT_NAME, 
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME = 'chapter' 
        AND TABLE_SCHEMA = DATABASE()");
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>Constraint Name</th><th>Column Name</th><th>Referenced Table</th><th>Referenced Column</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['CONSTRAINT_NAME'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['COLUMN_NAME'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['REFERENCED_TABLE_NAME'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['REFERENCED_COLUMN_NAME'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No key constraints found.</p>";
    }
    
    // Also check indexes
    echo "<h4>Indexes:</h4>";
    $indexResult = $conn->query("SHOW INDEX FROM chapter");
    if ($indexResult && $indexResult->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>Key Name</th><th>Column Name</th><th>Key Type</th><th>Unique</th></tr>";
        while ($row = $indexResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Key_name'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['Column_name'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['Index_type'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['Non_unique'] == 0 ? 'YES' : 'NO') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No indexes found.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Exception: " . $e->getMessage() . "</p>";
}

echo "<h3>Test Complete</h3>";
echo "<p><a href='admin/manage_chapters.php'>Back to Manage Chapters</a></p>";
?>
