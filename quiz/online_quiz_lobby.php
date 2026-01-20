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
        body {
            background: #f3f4f6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .lobby-container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .lobby-header {
            background: linear-gradient(120deg, #4f6ef7, #ec4899);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .lobby-header::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
        }
        
        .lobby-header h1 {
            margin: 0 0 12px;
            font-size: 36px;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .lobby-info {
            font-size: 18px;
            opacity: 0.95;
            margin: 8px 0;
            font-weight: 500;
        }
        
        .lobby-body {
            padding: 40px;
            text-align: center;
        }
        
        .waiting-animation {
            margin: 30px 0;
            display: flex;
            justify-content: center;
            gap: 8px;
        }
        
        .dot {
            width: 12px;
            height: 12px;
            background: #4f6ef7;
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out both;
        }
        
        .dot:nth-child(1) { animation-delay: -0.32s; }
        .dot:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }
        
        .participant-info {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 24px;
            margin: 20px 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }
        
        .participant-info:hover {
            transform: translateY(-2px);
            border-color: #4f6ef7;
        }
        
        .participants-list {
            background: #f8fafc;
            border-radius: 16px;
            padding: 24px;
            margin: 30px 0;
            text-align: left;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .participant-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: white;
            border-radius: 12px;
            margin-bottom: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        
        .participant-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .participant-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4f6ef7, #6366f1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            margin-right: 16px;
            box-shadow: 0 2px 4px rgba(79, 110, 247, 0.3);
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            background: #dcfce7;
            color: #166534;
            margin-left: auto;
        }
        
        .status-indicator::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #16a34a;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .quiz-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .detail-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .detail-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .detail-value {
            font-size: 24px;
            font-weight: 800;
            color: #111827;
        }
        
        .leave-btn {
            background: #fff;
            color: #ef4444;
            border: 2px solid #ef4444;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            margin-top: 20px;
            transition: all 0.2s;
        }
        
        .leave-btn:hover {
            background: #ef4444;
            color: white;
        }
        
        @media (max-width: 600px) {
            .lobby-container { margin: 0; border-radius: 0; min-height: 100vh; }
            .lobby-header { padding: 30px 20px; }
            .lobby-body { padding: 24px; }
            .quiz-details { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="lobby-container">
        <div class="lobby-header">
            <h1>Quiz Lobby</h1>
            <div class="lobby-info">Room: <?= h($room['room_code']) ?></div>
            <?php if (!empty($room['class_name']) || !empty($room['book_name'])): ?>
                <div class="lobby-info"><?= h($room['class_name'] ?? 'Class ' . $room['class_id']) ?> • <?= h($room['book_name'] ?? 'Book ' . $room['book_id']) ?></div>
            <?php else: ?>
                <div class="lobby-info">Topic-based Quiz</div>
            <?php endif; ?>
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
                    <div class="p-avatar">${p.name.charAt(0).toUpperCase()}</div>
                    <div class="p-info">
                        <div class="p-name">${escapeHtml(p.name)}</div>
                        <div class="p-roll">#${escapeHtml(p.roll_number)}</div>
                    </div>
                    <div class="p-status"></div>
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