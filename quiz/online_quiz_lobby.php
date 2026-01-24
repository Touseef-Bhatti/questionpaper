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
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            --glass: rgba(255, 255, 255, 0.95);
            --card-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0, transparent 50%), 
                radial-gradient(at 50% 0%, rgba(168, 85, 247, 0.15) 0, transparent 50%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .lobby-wrapper {
            max-width: 900px;
            width: 100%;
            margin: 40px auto;
            padding: 0 20px;
            animation: fadeIn 0.8s ease-out;
        }

        .lobby-container {
            background: white;
            border-radius: 30px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .lobby-header {
            background: var(--primary-gradient);
            padding: 50px 40px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .lobby-header::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -100px;
            right: -100px;
        }

        .lobby-header h1 {
            font-size: 3rem;
            font-weight: 800;
            margin: 0;
            color: white;
            letter-spacing: -1px;
            margin-bottom: 10px;
        }

        .room-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 24px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 1.2rem;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 15px;
        }

        .lobby-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 500;
        }

        .lobby-body {
            padding: 40px;
        }

        /* User Identity Card */
        .user-identity {
            display: flex;
            align-items: center;
            background: white;
            padding: 25px;
            border-radius: 20px;
            gap: 20px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid #f1f5f9;
        }

        .user-avatar-large {
            width: 70px;
            height: 70px;
            background: var(--primary-gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 800;
            box-shadow: 0 8px 16px rgba(99, 102, 241, 0.2);
        }

        .user-details h3 {
            margin: 0;
            font-size: 1.4rem;
            color: #1e293b;
        }

        .user-details p {
            margin: 4px 0 0 0;
            color: #64748b;
            font-weight: 500;
        }

        /* Status & Animation */
        .waiting-status {
            text-align: center;
            margin: 40px 0;
        }

        .pulse-animation {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            position: relative;
        }

        .pulse-circle {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: #6366f1;
            opacity: 0.6;
            animation: pulse 2s infinite;
            position: absolute;
        }

        .pulse-icon {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            z-index: 10;
            position: relative;
        }

        @keyframes pulse {
            0% { transform: scale(0.8); opacity: 0.8; }
            50% { transform: scale(1.2); opacity: 0.3; }
            100% { transform: scale(0.8); opacity: 0.8; }
        }

        .waiting-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 8px;
        }

        .sub-waiting-text {
            color: #64748b;
        }

        /* Quiz Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 40px 0;
        }

        .info-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .info-card i {
            font-size: 1.5rem;
            color: #6366f1;
            margin-bottom: 10px;
            display: block;
        }

        .info-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e293b;
        }

        /* Participants List */
        .participants-section {
            background: white;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
        }

        .participants-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .participants-header h4 {
            margin: 0;
            font-size: 1.2rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .count-badge {
            background: #e0e7ff;
            color: #4338ca;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .participants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }

        /* Custom Scrollbar */
        .participants-grid::-webkit-scrollbar { width: 6px; }
        .participants-grid::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .participants-grid::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .participant-card {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: #f8fafc;
            border-radius: 15px;
            gap: 12px;
            animation: slideIn 0.4s ease-out both;
            border: 1px solid transparent;
            transition: all 0.3s ease;
        }

        .participant-card:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateX(3px);
        }

        .p-avatar-small {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            background: #e2e8f0;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .p-name-small {
            font-weight: 600;
            color: #334155;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .p-roll-small {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Footer Actions */
        .lobby-footer {
            margin-top: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .leave-btn {
            background: transparent;
            color: #ef4444;
            border: 2px solid #fee2e2;
            padding: 12px 30px;
            border-radius: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .leave-btn:hover {
            background: #fef2f2;
            border-color: #ef4444;
        }

        .notice-pill {
            background: #ecfdf5;
            color: #047857;
            padding: 8px 20px;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .lobby-header h1 { font-size: 2.2rem; }
            .lobby-wrapper { margin: 20px auto; }
        }
    </style>
</head>
<?php include '../header.php'; ?>
<body>
    <div class="lobby-wrapper">
        <div class="lobby-container">
            <header class="lobby-header">
                <div class="room-badge">ROOM: <?= h($room['room_code']) ?></div>
                <h1>Quiz Lobby</h1>
                <div class="lobby-subtitle">
                    <?php if (!empty($room['class_name']) || !empty($room['book_name'])): ?>
                        <i class="fas fa-graduation-cap"></i> <?= h($room['class_name'] ?? 'Class ' . $room['class_id']) ?> 
                        <span style="margin: 0 10px; opacity: 0.5;">|</span>
                        <i class="fas fa-book-open"></i> <?= h($room['book_name'] ?? 'Book ' . $room['book_id']) ?>
                    <?php else: ?>
                        <i class="fas fa-tags"></i> Topic-based Quiz
                    <?php endif; ?>
                </div>
            </header>
            
            <main class="lobby-body">
                <div class="user-identity">
                    <div class="user-avatar-large">
                        <?= strtoupper(substr($participant['name'], 0, 1)) ?>
                    </div>
                    <div class="user-details">
                        <p>Welcome back,</p>
                        <h3><?= h($participant['name']) ?></h3>
                        <p><i class="fas fa-id-badge"></i> Roll No: <?= h($participant['roll_number']) ?></p>
                    </div>
                </div>
                
                <div class="waiting-status">
                    <div class="pulse-animation">
                        <div class="pulse-circle"></div>
                        <div class="pulse-icon"><i class="fas fa-hourglass-half"></i></div>
                    </div>
                    <div class="waiting-text">Waiting for the host...</div>
                    <div class="sub-waiting-text">The quiz will start automatically. Take a breath and get ready!</div>
                </div>
                
                <div class="info-grid">
                    <div class="info-card">
                        <i class="fas fa-question-circle"></i>
                        <div class="info-label">Questions</div>
                        <div class="info-value"><?= (int)$room['question_count'] ?></div>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-clock"></i>
                        <div class="info-label">Duration</div>
                        <div class="info-value"><?= (int)$room['quiz_duration_minutes'] ?>m</div>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-signal"></i>
                        <div class="info-label">Room Status</div>
                        <div class="info-value" id="quiz-status" style="color: #6366f1;">Waiting</div>
                    </div>
                </div>
                
                <div class="participants-section">
                    <div class="participants-header">
                        <h4><i class="fas fa-users"></i> Online Participants</h4>
                        <span class="count-badge" id="participant-count">...</span>
                    </div>
                    <div class="participants-grid" id="participants-container">
                        <!-- Participants will be loaded via JavaScript -->
                    </div>
                </div>
                
                <div class="lobby-footer">
                    <div class="notice-pill">
                        <i class="fas fa-magic"></i> Live updates enabled. Don't refresh the page.
                    </div>
                    <button class="leave-btn" onclick="leaveLobby()">
                        <i class="fas fa-sign-out-alt"></i> Leave Lobby
                    </button>
                </div>
            </main>
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
        
        let lastParticipantsJSON = '';

        function updateParticipantsList(participants) {
            const currentJSON = JSON.stringify(participants);
            if (currentJSON === lastParticipantsJSON) return;
            lastParticipantsJSON = currentJSON;

            const container = document.getElementById('participants-container');
            const countSpan = document.getElementById('participant-count');
            
            countSpan.textContent = participants.length;
            
            container.innerHTML = participants.map(p => `
                <div class="participant-card">
                    <div class="p-avatar-small">${p.name.charAt(0).toUpperCase()}</div>
                    <div class="p-info-small">
                        <div class="p-name-small">${escapeHtml(p.name)}</div>
                        <div class="p-roll-small">#${escapeHtml(p.roll_number)}</div>
                    </div>
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