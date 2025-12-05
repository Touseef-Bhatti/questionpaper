<?php
// online_quiz_dashboard.php - Teacher dashboard to view rooms and performance
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

// Current user
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$room_code = strtoupper(trim($_GET['room'] ?? ''));
$status_filter = strtolower(trim($_GET['status'] ?? ''));
$valid_status = ['active','closed',''];
if (!in_array($status_filter, $valid_status, true)) { $status_filter = ''; }

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Helper: get join URL
function join_url($code){ return 'online_quiz_join.php?room=' . urlencode($code); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Quiz Dashboard - Ahmad Learning Hub</title>
  <link rel="stylesheet" href="../css/main.css">
  <style>
    .container-narrow { max-width: 1100px; margin: 24px auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); padding: 20px; }
    .flex { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .btn { padding: 8px 14px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .btn.primary { background: #4f6ef7; color: #fff; }
    .btn.secondary { background: #e9eef8; color: #2d3e50; }
    .btn.danger { background: #ef4444; color: #fff; }
    .muted { color: #6b7280; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e5e7eb; }
    th { background: #f9fafb; font-weight: 700; color: #374151; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 700; }
    .badge.active { background: #dcfce7; color: #166534; }
    .badge.closed { background: #fee2e2; color: #991b1b; }
    .right { float: right; }
    .field { margin: 6px 0; }
    input.inline { padding: 6px 8px; border: 1px solid #e5e7eb; border-radius: 6px; width: 100%; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
    .status-waiting { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .status-active { background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .status-completed { background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
  </style>
</head>
<body>
<?php include '../header.php'; ?>
<div class="main-content">
  <div class="container-narrow">
  <?php if ($room_code === ''): ?>
    <div class="flex" style="justify-content: space-between;">
      <h1 style="margin: 0;">Live Quiz Dashboard</h1>
      <div class="flex">
        <form method="GET" action="online_quiz_dashboard.php" class="flex">
          <label class="muted">Status</label>
          <select name="status" class="input inline" style="height: 36px;">
            <option value="" <?= $status_filter===''?'selected':''; ?>>All</option>
            <option value="active" <?= $status_filter==='active'?'selected':''; ?>>Active</option>
            <option value="closed" <?= $status_filter==='closed'?'selected':''; ?>>Closed</option>
          </select>
          <button class="btn secondary" type="submit">Filter</button>
        </form>
        <a class="btn primary" href="online_quiz_host_new.php">Create Room</a>
      </div>
    </div>
    <?php
      // List rooms with stats - restrict to current user
      $where = "WHERE r.user_id = " . $user_id;
      if ($status_filter !== '') {
        $where .= " AND r.status = '" . $conn->real_escape_string($status_filter) . "'";
      }
      $sql = "SELECT r.id, r.room_code, r.created_at, r.status, r.class_id, r.book_id,
                     c.class_name, b.book_name,
                     (SELECT COUNT(*) FROM quiz_room_questions q WHERE q.room_id = r.id) AS q_count,
                     (SELECT COUNT(*) FROM quiz_participants p WHERE p.room_id = r.id) AS p_count
              FROM quiz_rooms r
              LEFT JOIN class c ON c.class_id = r.class_id
              LEFT JOIN book b ON b.book_id = r.book_id
              $where
              ORDER BY r.id DESC LIMIT 200";
      $res = $conn->query($sql);
    ?>
    <table>
      <thead>
        <tr>
          <th>Room</th>
          <th>Class / Book</th>
          <th>Created</th>
          <th>Questions</th>
          <th>Participants</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): while ($row = $res->fetch_assoc()): ?>
          <tr>
            <td>
              <div><strong><?= h($row['room_code']) ?></strong></div>
              <div class="muted"><a href="<?= h(join_url($row['room_code'])) ?>" target="_blank">Join link</a></div>
            </td>
            <td>
              <div><?= h($row['class_name'] ?? 'Class ' . (int)$row['class_id']) ?></div>
              <div class="muted"><?= h($row['book_name'] ?? ('Book ' . (int)$row['book_id'])) ?></div>
            </td>
            <td><?= h($row['created_at']) ?></td>
            <td><?= (int)$row['q_count'] ?></td>
            <td><?= (int)$row['p_count'] ?></td>
            <td><span class="badge <?= $row['status']==='active'?'active':'closed' ?>"><?= h(ucfirst($row['status'])) ?></span></td>
            <td class="flex">
              <a class="btn secondary" href="online_quiz_dashboard.php?room=<?= h($row['room_code']) ?>">View</a>
              <?php if ($row['status']==='active'): ?>
                <form method="POST" action="online_quiz_room_status.php" style="display:inline;">
                  <input type="hidden" name="room_code" value="<?= h($row['room_code']) ?>" />
                  <input type="hidden" name="action" value="close" />
                  <button class="btn danger" type="submit">Close</button>
                </form>
              <?php else: ?>
                <form method="POST" action="online_quiz_room_status.php" style="display:inline;">
                  <input type="hidden" name="room_code" value="<?= h($row['room_code']) ?>" />
                  <input type="hidden" name="action" value="open" />
                  <button class="btn primary" type="submit">Open</button>
                </form>
              <?php endif; ?>
              <a class="btn secondary" href="online_quiz_export.php?room=<?= h($row['room_code']) ?>">Export Excel File</a>
              <form method="POST" action="online_quiz_delete_room.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete room <?= h($row['room_code']) ?>? This action cannot be undone.');">
                <input type="hidden" name="room_code" value="<?= h($row['room_code']) ?>" />
                <button class="btn danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="7" class="muted">No rooms found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  <?php else: ?>
    <?php
      // Room detail view - restrict to current user
      $stmt = $conn->prepare("SELECT r.id, r.room_code, r.created_at, r.status, r.class_id, r.book_id, c.class_name, b.book_name,
                                     r.quiz_started, r.lobby_enabled, r.start_time, r.quiz_duration_minutes,
                                     (SELECT COUNT(*) FROM quiz_room_questions q WHERE q.room_id = r.id) AS q_count,
                                     (SELECT COUNT(*) FROM quiz_participants WHERE room_id = r.id AND status = 'waiting') AS waiting_count,
                                     (SELECT COUNT(*) FROM quiz_participants WHERE room_id = r.id AND status = 'active') AS active_count
                              FROM quiz_rooms r
                              LEFT JOIN class c ON c.class_id = r.class_id
                              LEFT JOIN book b ON b.book_id = r.book_id
                              WHERE r.room_code = ? AND r.user_id = ?");
      $stmt->bind_param('si', $room_code, $user_id);
      $stmt->execute();
      $room = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if (!$room) {
        echo '<h2 style="color:red;">Room not found.</h2>';
      } else {
        $room_id = (int)$room['id'];
        $pstmt = $conn->prepare("SELECT id, name, roll_number, started_at, finished_at, score, total_questions, status, current_question, last_activity FROM quiz_participants WHERE room_id = ? ORDER BY started_at ASC");
        $pstmt->bind_param('i', $room_id);
        $pstmt->execute();
        $participants = $pstmt->get_result();
        $pstmt->close();
    ?>
    <div class="flex" style="justify-content: space-between; align-items: flex-start;">
      <div>
        <h1 style="margin:0;">Room <?= h($room['room_code']) ?> <span class="badge <?= $room['status']==='active'?'active':'closed' ?>"><?= h(ucfirst($room['status'])) ?></span></h1>
        <div class="muted">Class: <?= h($room['class_name'] ?? 'Class ' . (int)$room['class_id']) ?> • Book: <?= h($room['book_name'] ?? ('Book ' . (int)$room['book_id'])) ?></div>
        <div class="muted">Created: <?= h($room['created_at']) ?> • Questions: <?= (int)$room['q_count'] ?></div>
        <div class="field" style="margin-top:8px;">
          <label class="muted">Join link</label>
          <input class="inline" readonly value="<?= h(join_url($room['room_code'])) ?>" onclick="this.select();" />
        </div>
      </div>
      <div class="flex">
        <a class="btn secondary" href="online_quiz_dashboard.php">Back</a>
        <?php if ($room['status']==='active'): ?>
          <form method="POST" action="online_quiz_room_status.php" style="display:inline;">
            <input type="hidden" name="room_code" value="<?= h($room['room_code']) ?>" />
            <input type="hidden" name="action" value="close" />
            <button class="btn danger" type="submit">Close Room</button>
          </form>
        <?php else: ?>
          <form method="POST" action="online_quiz_room_status.php" style="display:inline;">
            <input type="hidden" name="room_code" value="<?= h($room['room_code']) ?>" />
            <input type="hidden" name="action" value="open" />
            <button class="btn primary" type="submit">Open Room</button>
          </form>
        <?php endif; ?>
        <a class="btn secondary" href="online_quiz_export.php?room=<?= h($room['room_code']) ?>">Export CSV</a>
      </div>
    </div>

    <!-- Lobby Management Section -->
    <?php if (!$room['quiz_started']): ?>
    <div style="background: #f0f9ff; border: 2px solid #0ea5e9; border-radius: 12px; padding: 20px; margin: 20px 0;">
      <div class="flex" style="justify-content: space-between; align-items: center;">
        <div>
          <h3 style="margin: 0 0 8px; color: #0c4a6e;">🏠 Quiz Lobby</h3>
          <p style="margin: 0; color: #075985;">Participants are waiting in the lobby. Start the quiz when ready!</p>
        </div>
        <div class="flex">
          <div style="text-align: center; margin-right: 20px;">
            <div style="font-size: 24px; font-weight: bold; color: #0c4a6e;"><?= (int)$room['waiting_count'] ?></div>
            <div style="font-size: 12px; color: #0369a1;">Waiting</div>
          </div>
          <?php if ($room['waiting_count'] > 0): ?>
          <button class="btn primary" onclick="startQuiz('<?= h($room['room_code']) ?>')" id="startQuizBtn" style="background: #10b981; padding: 12px 24px;">
            🚀 Start Quiz
          </button>
          <?php else: ?>
          <button class="btn" disabled style="background: #9ca3af; padding: 12px 24px; cursor: not-allowed;">
            🚀 Start Quiz (No participants)
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php elseif ($room['quiz_started']): ?>
    <div style="background: #f0fdf4; border: 2px solid #22c55e; border-radius: 12px; padding: 20px; margin: 20px 0;">
      <div class="flex" style="justify-content: space-between; align-items: center;">
        <div>
          <h3 style="margin: 0 0 8px; color: #15803d;">✅ Quiz In Progress</h3>
          <p style="margin: 0; color: #166534;">Started: <?= $room['start_time'] ? h($room['start_time']) : 'Just now' ?></p>
        </div>
        <div class="flex">
          <div style="text-align: center; margin-right: 20px;">
            <div style="font-size: 24px; font-weight: bold; color: #15803d;"><?= (int)$room['active_count'] ?></div>
            <div style="font-size: 12px; color: #166534;">Active</div>
          </div>
          <button class="btn secondary" onclick="refreshParticipants()" style="padding: 12px 20px;">
            🔄 Refresh
          </button>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <h2 style="margin-top:20px;">Participants</h2>
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Roll #</th>
          <th>Started</th>
          <th>Finished</th>
          <th>Score</th>
          <th>Percent</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($participants && $participants->num_rows > 0): while ($p = $participants->fetch_assoc()):
              $finished = !empty($p['finished_at']);
              $score = is_null($p['score']) ? '-' : (int)$p['score'];
              $total = is_null($p['total_questions']) ? '-' : (int)$p['total_questions'];
              $percent = ($score!=='-' && $total!=='-' && $total>0) ? round(($score/$total)*100) . '%' : '-';
        ?>
          <tr>
            <td>
              <?= h($p['name']) ?>
              <div><span class="status-<?= h($p['status']) ?>"><?= ucfirst(h($p['status'])) ?></span></div>
            </td>
            <td><?= h($p['roll_number']) ?></td>
            <td><?= h($p['started_at']) ?></td>
            <td>
              <?= h($p['finished_at'] ?? '') ?>
              <?php if (!$finished && $p['status'] === 'active' && $p['current_question']): ?>
                <div class="muted" style="font-size: 11px;">Q<?= (int)$p['current_question'] ?></div>
              <?php endif; ?>
            </td>
            <td><?= h((string)$score) ?> / <?= h((string)$total) ?></td>
            <td><?= h((string)$percent) ?></td>
            <td>
              <?php if ($finished): ?>
                <a class="btn secondary" href="online_quiz_participant.php?pid=<?= (int)$p['id'] ?>">View</a>
              <?php elseif ($p['status'] === 'waiting'): ?>
                <span class="muted">Waiting in lobby</span>
              <?php else: ?>
                <span class="muted">In progress</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="7" class="muted">No participants yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php } // end room else ?>
  <?php endif; ?>
  </div>
</div>
<?php include '../footer.php'; ?>

<script>
// Dashboard live functionality
let updateInterval;
let currentRoomCode = '<?= isset($room['room_code']) ? h($room['room_code']) : '' ?>';

function startQuiz(roomCode) {
    const btn = document.getElementById('startQuizBtn');
    if (!btn) return;
    
    btn.disabled = true;
    btn.textContent = '🚀 Starting...';
    
    fetch('online_quiz_start.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            room_code: roomCode
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert('Quiz started successfully! ' + data.participants_moved + ' participants moved to active.');
            // Reload page to show updated status
            window.location.reload();
        } else {
            alert('Error starting quiz: ' + (data.error || data.message || 'Unknown error'));
            btn.disabled = false;
            btn.textContent = '🚀 Start Quiz';
        }
    })
    .catch(error => {
        console.error('Error starting quiz:', error);
        alert('Error starting quiz. Please try again.');
        btn.disabled = false;
        btn.textContent = '🚀 Start Quiz';
    });
}

function refreshParticipants() {
    if (!currentRoomCode) return;
    
    // Reload the page to get updated participant data
    window.location.reload();
}

// Auto-refresh for live updates when quiz is in progress
function startLiveUpdates() {
    if (!currentRoomCode) return;
    
    updateInterval = setInterval(() => {
        // Only refresh if quiz is in progress
        const quizInProgress = document.querySelector('.badge.active') && 
                              document.querySelector('[style*="background: #f0fdf4"]');
        if (quizInProgress) {
            // Silent refresh of participant data
            refreshParticipants();
        }
    }, 10000); // Update every 10 seconds
}

// Initialize live updates if we're viewing a room
if (currentRoomCode) {
    // Check if quiz is in progress and start live updates
    const quizInProgress = document.querySelector('[style*="background: #f0fdf4"]');
    if (quizInProgress) {
        startLiveUpdates();
    }
    
    // Auto-refresh lobby every 5 seconds if quiz hasn't started
    const lobbySection = document.querySelector('[style*="background: #f0f9ff"]');
    if (lobbySection) {
        updateInterval = setInterval(() => {
            window.location.reload();
        }, 5000);
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
});
</script>

</body>
</html>
