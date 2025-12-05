<?php
/**
 * Book Table Schema Fix Script
 * This script fixes the book table structure to match the expected schema
 * 
 * Issue: book_id field missing AUTO_INCREMENT on production
 * Solution: Alter the table to add AUTO_INCREMENT and PRIMARY KEY
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Ahmad Learning Hub Book Table Schema Fix</h1>";
echo "<hr>";

try {
    require_once __DIR__ . '/../db_connect.php';
    echo "✓ Database connection established<br><br>";
} catch (Exception $e) {
    die("✗ Database connection failed: " . $e->getMessage());
}

// First, let's examine the current structure
echo "<h2>Current Book Table Structure:</h2>";
$result = $conn->query("DESCRIBE book");
if ($result) {
    echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['Extra'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check if this is a POST request to actually run the fix
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_fix'])) {
    
    echo "<h2>Applying Schema Fixes...</h2>";
    
    // Step 1: Check if book_id is already AUTO_INCREMENT
    $desc_result = $conn->query("DESCRIBE book");
    $book_id_has_auto_increment = false;
    $book_id_is_primary = false;
    
    while ($row = $desc_result->fetch_assoc()) {
        if ($row['Field'] === 'book_id') {
            $book_id_has_auto_increment = (strpos($row['Extra'], 'auto_increment') !== false);
            $book_id_is_primary = ($row['Key'] === 'PRI');
            break;
        }
    }
    
    if (!$book_id_has_auto_increment) {
        echo "Fixing book_id AUTO_INCREMENT...<br>";
        
        // First, make sure book_id is PRIMARY KEY
        if (!$book_id_is_primary) {
            $result = $conn->query("ALTER TABLE book ADD PRIMARY KEY (book_id)");
            if ($result) {
                echo "✓ Added PRIMARY KEY to book_id<br>";
            } else {
                echo "✗ Failed to add PRIMARY KEY: " . $conn->error . "<br>";
            }
        }
        
        // Then add AUTO_INCREMENT
        $result = $conn->query("ALTER TABLE book MODIFY COLUMN book_id INT AUTO_INCREMENT");
        if ($result) {
            echo "✓ Added AUTO_INCREMENT to book_id<br>";
        } else {
            echo "✗ Failed to add AUTO_INCREMENT: " . $conn->error . "<br>";
        }
    } else {
        echo "✓ book_id already has AUTO_INCREMENT<br>";
    }
    
    // Step 2: Ensure proper foreign key relationship
    echo "<br>Checking foreign key constraint...<br>";
    
    // Check if foreign key exists
    $fk_result = $conn->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'book' 
        AND COLUMN_NAME = 'class_id' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    if ($fk_result && $fk_result->num_rows > 0) {
        echo "✓ Foreign key constraint already exists<br>";
    } else {
        $result = $conn->query("ALTER TABLE book ADD CONSTRAINT fk_book_class FOREIGN KEY (class_id) REFERENCES class(class_id) ON DELETE CASCADE");
        if ($result) {
            echo "✓ Added foreign key constraint<br>";
        } else {
            echo "⚠ Warning: Could not add foreign key constraint: " . $conn->error . "<br>";
            echo "This is likely because some book records reference non-existent classes.<br>";
        }
    }
    
    // Step 3: Test book creation
    echo "<br><h3>Testing Book Creation...</h3>";
    
    $test_book_name = 'Test Book ' . date('Y-m-d H:i:s');
    $nameEsc = $conn->real_escape_string($test_book_name);
    $class_id = 9; // Use Class 9 which we know exists
    
    $insert_sql = "INSERT INTO book (book_name, class_id) VALUES ('$nameEsc', $class_id)";
    echo "Test SQL: {$insert_sql}<br>";
    
    $result = $conn->query($insert_sql);
    if ($result) {
        $book_id = $conn->insert_id;
        echo "✅ <strong>SUCCESS!</strong> Test book created with ID: {$book_id}<br>";
        
        // Clean up - delete the test book
        $delete_result = $conn->query("DELETE FROM book WHERE book_id = $book_id");
        if ($delete_result) {
            echo "✓ Test book cleaned up<br>";
        }
    } else {
        echo "❌ <strong>FAILED!</strong> Test book creation error: " . $conn->error . "<br>";
    }
    
    echo "<br><h2>✅ Schema Fix Complete!</h2>";
    echo "<p>Your book table should now work properly.</p>";
    echo "<p><a href='manage_books.php'>Try Managing Books Now</a></p>";
    
} else {
    // Show current issues and fix options
    echo "<h2>Detected Issues:</h2>";
    
    $desc_result = $conn->query("DESCRIBE book");
    $issues = [];
    
    while ($row = $desc_result->fetch_assoc()) {
        if ($row['Field'] === 'book_id') {
            if (strpos($row['Extra'], 'auto_increment') === false) {
                $issues[] = "book_id field is missing AUTO_INCREMENT";
            }
            if ($row['Key'] !== 'PRI') {
                $issues[] = "book_id field is not PRIMARY KEY";
            }
        }
    }
    
    if (empty($issues)) {
        echo "✓ No issues detected with book table structure<br>";
        echo "<p>The schema looks correct. The error might be intermittent.</p>";
    } else {
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li style='color: red;'>✗ {$issue}</li>";
        }
        echo "</ul>";
    }
    
    echo "<h2>Proposed Fixes:</h2>";
    echo "<ul>";
    echo "<li>Set book_id as PRIMARY KEY with AUTO_INCREMENT</li>";
    echo "<li>Ensure proper foreign key relationship with class table</li>";
    echo "<li>Test book creation functionality</li>";
    echo "</ul>";
    
    echo "<form method='POST'>";
    echo "<input type='hidden' name='confirm_fix' value='1'>";
    echo "<button type='submit' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 10px 0;'>Apply Schema Fixes</button>";
    echo "</form>";
    
    echo "<p><strong>Note:</strong> This will modify your database structure. Make sure you have a backup.</p>";
}

// Show updated structure
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_fix'])) {
    echo "<hr>";
    echo "<h2>Updated Book Table Structure:</h2>";
    $result = $conn->query("DESCRIBE book");
    if ($result) {
        echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['Extra'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

$conn->close();
?>
