<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }

        /* Main container */
        .verification-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 60px 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease-out;
        }

        /* Headers */
        h2 {
            font-size: 1.8rem;
            margin-bottom: 30px;
            color: #333;
            font-weight: 600;
        }

        /* Success message styling */
        .success h2 {
            color: #27ae60;
        }

        .success::before {
            content: "✓";
            display: block;
            font-size: 4rem;
            color: #27ae60;
            margin-bottom: 20px;
            line-height: 1;
        }

        /* Error message styling */
        .error h2 {
            color: #e74c3c;
        }

        .error::before {
            content: "✗";
            display: block;
            font-size: 4rem;
            color: #e74c3c;
            margin-bottom: 20px;
            line-height: 1;
        }

        /* Warning message styling */
        .warning h2 {
            color: #f39c12;
        }

        .warning::before {
            content: "⚠";
            display: block;
            font-size: 4rem;
            color: #f39c12;
            margin-bottom: 20px;
            line-height: 1;
        }

      /* Turn links into modern buttons */
a {
    display: inline-block;
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    font-weight: 600;
    text-decoration: none;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
    position: relative;
    transition: all 0.25s ease;
}

/* Hover effect */
a:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.25);
    background: linear-gradient(135deg, #5a67d8, #6b46c1);
}

/* Active click effect */
a:active {
    transform: translateY(0);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
}

/* Optional focus ring for accessibility */
a:focus-visible {
    outline: 3px solid rgba(102, 126, 234, 0.5);
    outline-offset: 4px;
}


        /* Responsive design */
        @media (max-width: 600px) {
            .verification-container {
                padding: 40px 30px;
                margin: 10px;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .success::before,
            .error::before,
            .warning::before {
                font-size: 3rem;
            }
        }

        /* Animation for container */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Brand area */
        .brand {
            margin-bottom: 30px;
        }

        .brand h1 {
            color: #667eea;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="brand">
            <h1>Ahmad Learning Hub</h1>
        </div>
        
        <?php
        require_once __DIR__ . '/../db_connect.php';
        
        if (isset($_GET['token'])) {
            $token = trim($_GET['token']);
            
            // Debug: Log the token being verified
            error_log("Verifying token: " . $token);
            
            if (empty($token)) {
                echo '<div class="error"><h2>Invalid verification link.</h2></div>';
                error_log("Empty token received");
            } else {
                try {
                    // Check pending_users for this token using prepared statement
                    $stmt = $conn->prepare("SELECT id, name, email, password FROM pending_users WHERE token = ? LIMIT 1");
                    $stmt->bind_param('s', $token);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    // Debug: Log query result
                    error_log("Pending users query returned " . $result->num_rows . " rows");
                
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    error_log("Found pending user: " . $row['email']);
                    $stmt->close();
                    
                    // Create users table if not exists with all required fields
                    $tableCreated = $conn->query("CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY, 
                        name VARCHAR(191) NOT NULL, 
                        email VARCHAR(191) NOT NULL UNIQUE, 
                        password VARCHAR(255) NOT NULL, 
                        token VARCHAR(64), 
                        verified TINYINT(1) DEFAULT 0, 
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    
                    if (!$tableCreated) {
                        error_log("Table creation failed: " . $conn->error);
                    }
                    
                    // Check if email already exists in users table
                    $checkExisting = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $checkExisting->bind_param('s', $row['email']);
                    $checkExisting->execute();
                    $existingResult = $checkExisting->get_result();
                    
                    if ($existingResult->num_rows > 0) {
                        $checkExisting->close();
                        error_log("Email already exists in users table: " . $row['email']);
                        
                        // Remove from pending_users since user already exists
                        $deleteStmt = $conn->prepare("DELETE FROM pending_users WHERE id = ?");
                        $deleteStmt->bind_param('i', $row['id']);
                        $deleteStmt->execute();
                        $deleteStmt->close();
                        
                        echo '<div class="warning"><h2>Email already verified! You can <a href="../auth/login.php">login</a></h2></div>';
                    } else {
                        $checkExisting->close();
                        
                        // Insert into users table as verified using prepared statement
                        $insertStmt = $conn->prepare("INSERT INTO users (name, email, password, token, verified) VALUES (?, ?, ?, ?, 1)");
                        if (!$insertStmt) {
                            error_log("Prepare statement failed: " . $conn->error);
                            echo '<div class="error"><h2>Database error. Please try again.</h2></div>';
                        } else {
                            $insertStmt->bind_param('ssss', $row['name'], $row['email'], $row['password'], $token);
                            
                            if ($insertStmt->execute()) {
                                error_log("User successfully inserted: " . $row['email']);
                                $insertStmt->close();
                                
                                // Remove from pending_users using prepared statement
                                $deleteStmt = $conn->prepare("DELETE FROM pending_users WHERE id = ?");
                                $deleteStmt->bind_param('i', $row['id']);
                                $deleteStmt->execute();
                                $deleteStmt->close();
                                
                                error_log("Email verification completed successfully for: " . $row['email']);
                                echo '<div class="success"><h2>Email verified successfully! You can now <a href="../auth/login.php">login</a></h2></div>';
                            } else {
                                error_log("Email verification - Insert failed: " . $conn->error . " | Email: " . $row['email']);
                                $insertStmt->close();
                                
                                // Show more specific error
                                if (strpos($conn->error, 'Duplicate entry') !== false) {
                                    echo '<div class="warning"><h2>This email is already registered! You can <a href="../auth/login.php">login</a></h2></div>';
                                } else {
                                    echo '<div class="error"><h2>Database error during verification. Please contact support.</h2></div>';
                                }
                            }
                        }
                    }
                } else {
                    $stmt->close();
                    
                    // Check if already verified using prepared statement
                    $checkStmt = $conn->prepare("SELECT id FROM users WHERE token = ? AND verified = 1 LIMIT 1");
                    $checkStmt->bind_param('s', $token);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    
                    if ($checkResult && $checkResult->num_rows > 0) {
                        $checkStmt->close();
                        echo '<div class="warning"><h2>Email already verified. You can <a href="../auth/login.php">login</a></h2></div>';
                    } else {
                        $checkStmt->close();
                        echo '<div class="error"><h2>Invalid or expired token.</h2></div>';
                    }
                }
                } catch (Exception $e) {
                    error_log("Email verification error: " . $e->getMessage());
                    echo '<div class="error"><h2>Verification failed. Please try again later.</h2></div>';
                }
            }
        } else {
            echo '<div class="error"><h2>No token provided.</h2></div>';
        }
        ?>
    </div>
</body>
</html>