<?php
// debug_participant_session.php - Debug participant session issues
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db_connect.php';

echo "<h1>Participant Session Debug</h1>";
echo "<style>body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:20px;}table{width:100%;border-collapse:collapse;}th,td{padding:8px;border:1px solid #ddd;text-align:left;}</style>";

// Display current session data
echo "<h2>Current Session Data</h2>";
echo "<table>";
foreach ($_SESSION as $key => $value) {
    echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars(print_r($value, true)) . "</td></tr>";
}
echo "</table>";

// Check if we have participant session data
$participant_id = intval($_SESSION['quiz_participant_id'] ?? $_SESSION['participant_id'] ?? 0);
$room_code = $_SESSION['quiz_room_code'] ?? $_GET['room'] ?? '';
$room_id = intval($_SESSION['quiz_room_id'] ?? $_SESSION['participant_room_id'] ?? 0);

echo "<h2>Extracted Values</h2>";
echo "<p><strong>Participant ID:</strong> $participant_id</p>";
echo "<p><strong>Room Code:</strong> $room_code</p>";
echo "<p><strong>Room ID:</strong> $room_id</p>";

if ($participant_id > 0) {
    // Get participant info
    $stmt = $conn->prepare("
        SELECT p.*, r.room_code, r.quiz_started, r.status as room_status 
        FROM quiz_participants p 
        JOIN quiz_rooms r ON r.id = p.room_id 
        WHERE p.id = ?
    ");
    $stmt->bind_param('i', $participant_id);
    $stmt->execute();
    $participant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($participant) {
        echo "<h2>Participant Database Record</h2>";
        echo "<table>";
        foreach ($participant as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value) . "</td></tr>";
        }
        echo "</table>";
        
        // Fix session if needed
        if (!isset($_SESSION['quiz_participant_id'])) {
            $_SESSION['quiz_participant_id'] = $participant['id'];
            echo "<p style='color:green;'>✓ Fixed quiz_participant_id session</p>";
        }
        if (!isset($_SESSION['quiz_room_code'])) {
            $_SESSION['quiz_room_code'] = $participant['room_code'];
            echo "<p style='color:green;'>✓ Fixed quiz_room_code session</p>";
        }
        if (!isset($_SESSION['quiz_room_id'])) {
            $_SESSION['quiz_room_id'] = $participant['room_id'];
            echo "<p style='color:green;'>✓ Fixed quiz_room_id session</p>";
        }
        
        // Show appropriate actions
        echo "<h2>Actions</h2>";
        if ($participant['quiz_started']) {
            echo "<p><a href='online_quiz_take.php?room=" . urlencode($participant['room_code']) . "' style='padding:10px 20px;background:#10b981;color:white;text-decoration:none;border-radius:5px;'>🚀 Go to Quiz</a></p>";
        } else {
            echo "<p><a href='online_quiz_lobby.php?room=" . urlencode($participant['room_code']) . "' style='padding:10px 20px;background:#4f6ef7;color:white;text-decoration:none;border-radius:5px;'>🏠 Go to Lobby</a></p>";
        }
        
    } else {
        echo "<p style='color:red;'>❌ Participant not found in database</p>";
        echo "<p><a href='online_quiz_join.php' style='padding:10px 20px;background:#ef4444;color:white;text-decoration:none;border-radius:5px;'>Join a Quiz Room</a></p>";
    }
} else {
    echo "<h2>No Participant Session Found</h2>";
    echo "<p>You need to join a quiz room first.</p>";
    echo "<p><a href='online_quiz_join.php' style='padding:10px 20px;background:#4f6ef7;color:white;text-decoration:none;border-radius:5px;'>Join a Quiz Room</a></p>";
}

// Show all active rooms for reference
echo "<h2>Active Quiz Rooms</h2>";
$rooms_query = "SELECT room_code, quiz_started, status, created_at, 
                (SELECT COUNT(*) FROM quiz_participants WHERE room_id = quiz_rooms.id) as participant_count
                FROM quiz_rooms 
                WHERE status = 'active' 
                ORDER BY created_at DESC LIMIT 10";
$rooms_result = $conn->query($rooms_query);

if ($rooms_result && $rooms_result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Room Code</th><th>Status</th><th>Quiz Started</th><th>Participants</th><th>Created</th><th>Action</th></tr>";
    while ($room = $rooms_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($room['room_code']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($room['status']) . "</td>";
        echo "<td>" . ($room['quiz_started'] ? '✅ Started' : '⏳ Lobby') . "</td>";
        echo "<td>" . (int)$room['participant_count'] . "</td>";
        echo "<td>" . htmlspecialchars($room['created_at']) . "</td>";
        echo "<td><a href='online_quiz_join.php?room=" . urlencode($room['room_code']) . "'>Join</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No active rooms found.</p>";
}

echo "<hr><p><small>Debug page - you can delete this file after fixing issues.</small></p>";
?>
