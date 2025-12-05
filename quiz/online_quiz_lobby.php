<?php
// online_quiz_lobby.php - Participant lobby waiting page
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

$room_code = strtoupper(trim($_GET['room'] ?? ''));
$participant_id = isset($_SESSION['quiz_participant_id']) ? (int)$_SESSION['quiz_participant_id'] : 0;

if (empty($room_code) || !$participant_id) {
    header('Location: online_quiz_join.php' . ($room_code ? '?room=' . urlencode($room_code) : ''));
    exit;
}

// Get room and participant info
$stmt = $conn->prepare("
    SELECT r.id, r.room_code, r.quiz_started, r.lobby_enabled, r.start_time, r.status, r.quiz_duration_minutes,
           c.class_name, b.book_name, 
           (SELECT COUNT(*) FROM quiz_room_questions WHERE room_id = r.id) as question_count
    FROM quiz_rooms r 
    LEFT JOIN class c ON c.class_id = r.class_id
    LEFT JOIN book b ON b.book_id = r.book_id
    WHERE r.room_code = ? AND r.status = 'active'
");
$stmt->bind_param('s', $room_code);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    die('<h2 style="color:red;">Room not found or inactive.</h2><p><a href="online_quiz_join.php">Join another room</a></p>');
}

// Check if quiz has started - redirect if so
if ($room['quiz_started']) {
    header('Location: online_quiz_take.php?room=' . urlencode($room_code));
    exit;
}

// Get participant info
$stmt = $conn->prepare("SELECT id, name, roll_number, status FROM quiz_participants WHERE id = ? AND room_id = ?");
$stmt->bind_param('ii', $participant_id, $room['id']);
$stmt->execute();
$participant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$participant) {
    header('Location: online_quiz_join.php?room=' . urlencode($room_code));
    exit;
}

// Update participant's last activity
$conn->query("UPDATE quiz_participants SET last_activity = NOW() WHERE id = " . $participant_id);

// Record participant joined event if not already recorded
$event_check = $conn->prepare("SELECT id FROM live_quiz_events WHERE room_id = ? AND participant_id = ? AND event_type = 'participant_joined' LIMIT 1");
$event_check->bind_param('ii', $room['id'], $participant_id);
$event_check->execute();
$existing_event = $event_check->get_result()->fetch_assoc();
$event_check->close();

if (!$existing_event) {
    $event_stmt = $conn->prepare("INSERT INTO live_quiz_events (room_id, participant_id, event_type, event_data) VALUES (?, ?, 'participant_joined', ?)");
    $event_data = json_encode(['name' => $participant['name'], 'roll_number' => $participant['roll_number']]);
    $event_stmt->bind_param('iis', $room['id'], $participant_id, $event_data);
    $event_stmt->execute();
    $event_stmt->close();
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Lobby - <?= h($room['room_code']) ?> | Ahmad Learning Hub</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        .lobby-container {
            max-width: 800px;
            margin: 20px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .lobby-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .lobby-header h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }
        
        .lobby-info {
            font-size: 16px;
            opacity: 0.9;
            margin: 5px 0;
        }
        
        .lobby-body {
            padding: 40px 30px;
            text-align: center;
        }
        
        .waiting-animation {
            display: inline-block;
            margin: 20px 0;
        }
        
        .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
            margin: 0 3px;
            animation: pulse 1.4s ease-in-out infinite;
        }
        
        .dot:nth-child(1) { animation-delay: -0.32s; }
        .dot:nth-child(2) { animation-delay: -0.16s; }
        .dot:nth-child(3) { animation-delay: 0; }
        
        @keyframes pulse {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }
        
        .participant-info {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .participants-list {
            background: #f0f4f8;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .participant-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .participant-item:last-child {
            border-bottom: none;
        }
        
        .participant-avatar {
            width: 32px;
            height: 32px;
            background: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 12px;
        }
        
        .status-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: #10b981;
            color: white;
            margin-left: auto;
        }
        
        .quiz-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .detail-item {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
        }
        
        .detail-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
        }
        
        .refresh-notice {
            font-size: 14px;
            color: #6b7280;
            margin-top: 20px;
        }
        
        .leave-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 20px;
        }
        
        .leave-btn:hover {
            background: #dc2626;
        }
        
        @media (max-width: 600px) {
            .lobby-container { margin: 10px; }
            .lobby-header, .lobby-body { padding: 20px; }
            .quiz-details { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="lobby-container">
        <div class="lobby-header">
            <h1>Quiz Lobby</h1>
            <div class="lobby-info">Room: <?= h($room['room_code']) ?></div>
            <div class="lobby-info"><?= h($room['class_name'] ?? 'Class ' . $room['class_id']) ?> • <?= h($room['book_name'] ?? 'Book ' . $room['book_id']) ?></div>
        </div>
        
        <div class="lobby-body">
            <div class="participant-info">
                <h3>You're in the lobby!</h3>
                <p><strong>Name:</strong> <?= h($participant['name']) ?></p>
                <p><strong>Roll Number:</strong> <?= h($participant['roll_number']) ?></p>
            </div>
            
            <div class="waiting-animation">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
            
            <h3>Waiting for host to start the quiz...</h3>
            <p>The quiz will begin automatically when your instructor starts it.</p>
            
            <div class="quiz-details">
                <div class="detail-item">
                    <div class="detail-label">Questions</div>
                    <div class="detail-value"><?= (int)$room['question_count'] ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Duration</div>
                    <div class="detail-value"><?= (int)$room['quiz_duration_minutes'] ?> min</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value" id="quiz-status">Waiting</div>
                </div>
            </div>
            
            <div class="participants-list">
                <h4>Other Participants (<span id="participant-count">...</span>)</h4>
                <div id="participants-container">
                    <!-- Participants will be loaded via JavaScript -->
                </div>
            </div>
            
            <div class="refresh-notice">
                <p>✨ This page updates automatically. Stay on this page until the quiz starts.</p>
            </div>
            
            <button class="leave-btn" onclick="leaveLobby()">Leave Lobby</button>
        </div>
    </div>

    <script>
        let checkInterval;
        let participantId = <?= $participant_id ?>;
        let roomId = <?= $room['id'] ?>;
        let roomCode = '<?= h($room['room_code']) ?>';
        
        // Check quiz status every 2 seconds
        function checkQuizStatus() {
            fetch('online_quiz_lobby_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    room_code: roomCode,
                    participant_id: participantId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.quiz_started) {
                    // Quiz has started! Redirect to quiz page
                    clearInterval(checkInterval);
                    document.getElementById('quiz-status').textContent = 'Starting...';
                    document.getElementById('quiz-status').style.color = '#10b981';
                    
                    // Small delay to show status change
                    setTimeout(() => {
                        window.location.href = 'online_quiz_take.php?room=' + encodeURIComponent(roomCode);
                    }, 1000);
                } else if (data.room_closed) {
                    // Room was closed
                    clearInterval(checkInterval);
                    alert('The quiz room has been closed by the host.');
                    window.location.href = 'online_quiz_join.php';
                } else {
                    // Update participant list
                    updateParticipantsList(data.participants);
                }
            })
            .catch(error => {
                console.error('Error checking quiz status:', error);
            });
        }
        
        function updateParticipantsList(participants) {
            const container = document.getElementById('participants-container');
            const countSpan = document.getElementById('participant-count');
            
            countSpan.textContent = participants.length;
            
            container.innerHTML = participants.map(p => `
                <div class="participant-item">
                    <div class="participant-avatar">${p.name.charAt(0).toUpperCase()}</div>
                    <div>
                        <div style="font-weight: 600;">${escapeHtml(p.name)}</div>
                        <div style="font-size: 12px; color: #6b7280;">Roll: ${escapeHtml(p.roll_number)}</div>
                    </div>
                    <span class="status-indicator">Ready</span>
                </div>
            `).join('');
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        function leaveLobby() {
            if (confirm('Are you sure you want to leave the lobby?')) {
                clearInterval(checkInterval);
                
                fetch('online_quiz_leave.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        participant_id: participantId,
                        room_code: roomCode
                    })
                })
                .then(() => {
                    window.location.href = 'online_quiz_join.php';
                })
                .catch(error => {
                    console.error('Error leaving lobby:', error);
                    window.location.href = 'online_quiz_join.php';
                });
            }
        }
        
        // Update participant activity every 30 seconds
        function updateActivity() {
            fetch('online_quiz_activity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    participant_id: participantId
                })
            })
            .catch(error => {
                console.error('Error updating activity:', error);
            });
        }
        
        // Start checking quiz status
        checkInterval = setInterval(checkQuizStatus, 2000);
        
        // Update activity every 30 seconds
        setInterval(updateActivity, 30000);
        
        // Initial status check
        checkQuizStatus();
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            clearInterval(checkInterval);
        });
    </script>
</body>
</html>
