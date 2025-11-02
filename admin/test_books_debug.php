<?php
/**
 * Diagnostic Test File for Books Management
 * This file helps identify issues with the manage_books.php functionality
 * 
 * Usage: Upload to production server and visit:
 * https://paper.bhattichemicalsindustry.com.pk/admin/test_books_debug.php
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>QPaperGen Books Management Diagnostic Test</h1>";
echo "<hr>";

// Test 1: PHP Version and Extensions
echo "<h2>1. PHP Environment Check</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "MySQLi Extension: " . (extension_loaded('mysqli') ? '✓ Available' : '✗ Missing') . "<br>";
echo "JSON Extension: " . (extension_loaded('json') ? '✓ Available' : '✗ Missing') . "<br>";
echo "Session Support: " . (function_exists('session_start') ? '✓ Available' : '✗ Missing') . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown' . "<br>";
echo "<br>";

// Test 2: File System Check
echo "<h2>2. File System Check</h2>";
$required_files = [
    '../db_connect.php',
    '../config/env.php',
    '../config/.env.production',
    'header.php',
    '../footer.php',
    '../css/admin.css',
    '../css/footer.css'
];

foreach ($required_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        echo "✓ {$file} - exists<br>";
    } else {
        echo "✗ {$file} - <span style='color:red'>MISSING</span><br>";
    }
}
echo "<br>";

// Test 3: Environment Configuration
echo "<h2>3. Environment Configuration</h2>";
try {
    require_once __DIR__ . '/../config/env.php';
    
    echo "Environment Loader: ✓ Loaded successfully<br>";
    echo "App Environment: " . EnvLoader::get('APP_ENV', 'not set') . "<br>";
    echo "Database Host: " . EnvLoader::get('DB_HOST', 'not set') . "<br>";
    echo "Database Name: " . EnvLoader::get('DB_NAME', 'not set') . "<br>";
    echo "Database User: " . EnvLoader::get('DB_USER', 'not set') . "<br>";
    echo "Database Password: " . (EnvLoader::get('DB_PASSWORD') ? 'Set (****)' : 'Not set') . "<br>";
    
} catch (Exception $e) {
    echo "✗ Environment Error: " . $e->getMessage() . "<br>";
}
echo "<br>";

// Test 4: Database Connection
echo "<h2>4. Database Connection Test</h2>";
try {
    require_once __DIR__ . '/../db_connect.php';
    
    if (isset($conn) && $conn instanceof mysqli) {
        echo "✓ Database connection established<br>";
        echo "Connection ID: " . $conn->thread_id . "<br>";
        echo "Server Info: " . $conn->server_info . "<br>";
        echo "Character Set: " . $conn->character_set_name() . "<br>";
        
        // Test basic query
        $result = $conn->query("SELECT 1 as test");
        if ($result) {
            echo "✓ Basic query test passed<br>";
        } else {
            echo "✗ Basic query failed: " . $conn->error . "<br>";
        }
    } else {
        echo "✗ Database connection failed or not available<br>";
    }
    
} catch (Exception $e) {
    echo "✗ Database Connection Error: " . $e->getMessage() . "<br>";
}
echo "<br>";

// Test 5: Database Schema Check
echo "<h2>5. Database Schema Check</h2>";
if (isset($conn) && $conn instanceof mysqli) {
    
    // Check if tables exist
    $tables = ['class', 'book', 'chapter', 'users', 'admins'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "✓ Table '{$table}' exists<br>";
        } else {
            echo "✗ Table '{$table}' <span style='color:red'>MISSING</span><br>";
        }
    }
    
    echo "<br>";
    
    // Check class table structure and data
    $result = $conn->query("DESCRIBE class");
    if ($result) {
        echo "<strong>Class table structure:</strong><br>";
        echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if there are classes
        $count_result = $conn->query("SELECT COUNT(*) as count FROM class");
        if ($count_result) {
            $count = $count_result->fetch_assoc()['count'];
            echo "Classes available: {$count}<br>";
            
            if ($count > 0) {
                $classes_result = $conn->query("SELECT class_id, class_name FROM class LIMIT 5");
                echo "<strong>Sample classes:</strong><br>";
                while ($class = $classes_result->fetch_assoc()) {
                    echo "- ID: {$class['class_id']}, Name: {$class['class_name']}<br>";
                }
            } else {
                echo "<span style='color:red'>⚠️ NO CLASSES FOUND - This will cause book creation to fail!</span><br>";
            }
        }
    } else {
        echo "✗ Could not describe class table: " . $conn->error . "<br>";
    }
    
    echo "<br>";
    
    // Check book table structure
    $result = $conn->query("DESCRIBE book");
    if ($result) {
        echo "<strong>Book table structure:</strong><br>";
        echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check books count
        $count_result = $conn->query("SELECT COUNT(*) as count FROM book");
        if ($count_result) {
            $count = $count_result->fetch_assoc()['count'];
            echo "Books available: {$count}<br>";
        }
    } else {
        echo "✗ Could not describe book table: " . $conn->error . "<br>";
    }
    
} else {
    echo "Cannot check schema - no database connection available<br>";
}
echo "<br>";

// Test 6: Session Test
echo "<h2>6. Session Test</h2>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    echo "Session Status: " . session_status() . " (1=disabled, 2=enabled)<br>";
    echo "Session ID: " . session_id() . "<br>";
    echo "Session Name: " . session_name() . "<br>";
    
    // Check if admin session exists
    if (isset($_SESSION['role'])) {
        echo "User Role: " . $_SESSION['role'] . "<br>";
        echo "User ID: " . ($_SESSION['admin_id'] ?? 'not set') . "<br>";
    } else {
        echo "<span style='color:orange'>⚠️ No admin session found - you may need to login first</span><br>";
    }
    
} catch (Exception $e) {
    echo "✗ Session Error: " . $e->getMessage() . "<br>";
}
echo "<br>";

// Test 7: Simulate Book Creation
echo "<h2>7. Book Creation Simulation</h2>";
if (isset($conn) && $conn instanceof mysqli) {
    
    // Get first available class
    $class_result = $conn->query("SELECT class_id, class_name FROM class LIMIT 1");
    if ($class_result && $class_result->num_rows > 0) {
        $class = $class_result->fetch_assoc();
        $class_id = $class['class_id'];
        $class_name = $class['class_name'];
        
        echo "Using class: {$class_name} (ID: {$class_id})<br>";
        
        // Try to insert a test book
        $test_book_name = 'Test Book ' . date('Y-m-d H:i:s');
        $nameEsc = $conn->real_escape_string($test_book_name);
        
        $insert_sql = "INSERT INTO book (book_name, class_id) VALUES ('$nameEsc', $class_id)";
        echo "Test SQL: {$insert_sql}<br>";
        
        $result = $conn->query($insert_sql);
        if ($result) {
            $book_id = $conn->insert_id;
            echo "✓ Test book created successfully! Book ID: {$book_id}<br>";
            
            // Clean up - delete the test book
            $delete_result = $conn->query("DELETE FROM book WHERE book_id = $book_id");
            if ($delete_result) {
                echo "✓ Test book cleaned up successfully<br>";
            }
        } else {
            echo "✗ Test book creation failed: " . $conn->error . "<br>";
        }
        
    } else {
        echo "✗ Cannot test book creation - no classes available<br>";
        echo "<strong>Solution:</strong> Add at least one class first:<br>";
        echo "<code>INSERT INTO class (class_name) VALUES ('Class 1');</code><br>";
    }
    
} else {
    echo "Cannot test book creation - no database connection<br>";
}
echo "<br>";

// Test 8: Server Environment
echo "<h2>8. Server Environment</h2>";
echo "Server Name: " . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . "<br>";
echo "Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown') . "<br>";
echo "HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "<br>";
echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "<br>";
echo "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "<br>";
echo "<br>";

// Test 9: Memory and Limits
echo "<h2>9. PHP Limits</h2>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . " seconds<br>";
echo "Max Input Vars: " . ini_get('max_input_vars') . "<br>";
echo "Post Max Size: " . ini_get('post_max_size') . "<br>";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "<br>";

// Test 10: Permissions Test
echo "<h2>10. File Permissions Test</h2>";
$test_dirs = [
    __DIR__,
    __DIR__ . '/../',
    __DIR__ . '/../config',
    __DIR__ . '/../css'
];

foreach ($test_dirs as $dir) {
    if (is_dir($dir)) {
        echo "Directory: " . basename($dir) . "<br>";
        echo "  - Readable: " . (is_readable($dir) ? '✓' : '✗') . "<br>";
        echo "  - Writable: " . (is_writable($dir) ? '✓' : '✗') . "<br>";
        echo "  - Executable: " . (is_executable($dir) ? '✓' : '✗') . "<br>";
    }
}
echo "<br>";

echo "<hr>";
echo "<h2>Test Complete</h2>";
echo "<p>If you see any ✗ marks or errors above, those are likely causing the HTTP 500 error.</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If database connection failed: Check .env.production credentials</li>";
echo "<li>If tables are missing: Import schema.sql to database</li>";
echo "<li>If no classes exist: Add classes first before creating books</li>";
echo "<li>If session issues: Check session configuration on server</li>";
echo "</ul>";

// Clean up
if (isset($conn)) {
    $conn->close();
}
?>
