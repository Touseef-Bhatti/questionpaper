<?php
// online_quiz_dashboard.php - Teacher dashboard to view rooms and performance
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

// Current user
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Ensure necessary columns exist in quiz_rooms
$checkCols = $conn->query("SHOW COLUMNS FROM quiz_rooms LIKE 'custom_class'");
if ($checkCols && $checkCols->num_rows == 0) {
    $conn->query("ALTER TABLE quiz_rooms ADD COLUMN custom_class VARCHAR(255) DEFAULT NULL AFTER quiz_duration_minutes");
    $conn->query("ALTER TABLE quiz_rooms ADD COLUMN custom_book VARCHAR(255) DEFAULT NULL AFTER custom_class");
}

// Fix quiz_participants status enum if needed
$checkPStatus = $conn->query("SHOW COLUMNS FROM quiz_participants LIKE 'status'");
if ($checkPStatus) {
    $row = $checkPStatus->fetch_assoc();
    if (strpos($row['Type'], "'waiting'") === false) {
        $conn->query("ALTER TABLE quiz_participants MODIFY COLUMN status ENUM('waiting', 'active', 'finished') DEFAULT 'waiting'");
    }
}

$room_code = strtoupper(trim($_GET['room'] ?? ''));
$status_filter = strtolower(trim($_GET['status'] ?? ''));
$valid_status = ['active','closed',''];
if (!in_array($status_filter, $valid_status, true)) { $status_filter = ''; }

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Helper: get join URL
function join_url($code){ 
    $baseUrl = rtrim(EnvLoader::get('BASE_URL', 'https://ahmadlearninghub.com.pk'), '/');
    return $baseUrl . '/quiz/online_quiz_join.php?room=' . urlencode($code); 
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Quiz Dashboard - Ahmad Learning Hub</title>
  <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
  <style>
    .container-narrow { max-width: 1100px; margin: 24px auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); padding: 20px; }
    .flex { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .btn { padding: 10px 18px; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.95rem; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); white-space: nowrap; }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .btn:active { transform: translateY(0); box-shadow: none; }
    .btn i { font-size: 1.1em; }
    .btn.primary { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color: #fff; box-shadow: 0 4px 12px rgba(99,102,241,0.25); }
    .btn.primary:hover { box-shadow: 0 6px 16px rgba(99,102,241,0.35); }
    .btn.secondary { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
    .btn.secondary:hover { background: #f1f5f9; color: #1e293b; border-color: #cbd5e1; }
    .btn.danger { background: linear-gradient(135deg, #ef4444 0%, #f87171 100%); color: #fff; box-shadow: 0 4px 12px rgba(239,68,68,0.25); }
    .btn.danger:hover { box-shadow: 0 6px 16px rgba(239,68,68,0.35); }
    .btn.info { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff; box-shadow: 0 4px 12px rgba(14,165,233,0.25); }
    .btn.info:hover { box-shadow: 0 6px 16px rgba(14,165,233,0.35); }
    .btn.warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; box-shadow: 0 4px 12px rgba(245,158,11,0.25); }
    .btn.warning:hover { box-shadow: 0 6px 16px rgba(245,158,11,0.35); }
    .btn.success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; box-shadow: 0 4px 12px rgba(16,185,129,0.25); }
    .btn.success:hover { box-shadow: 0 6px 16px rgba(16,185,129,0.35); }
    .btn.purple { background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: #fff; box-shadow: 0 4px 12px rgba(139,92,246,0.25); }
    .btn.purple:hover { box-shadow: 0 6px 16px rgba(139,92,246,0.35); }
    .mobile-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: flex-end; }
    .muted { color: #6b7280; }
    .table-responsive { overflow-x: auto; width: 100%; margin-top: 12px; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; min-width: 600px; } /* Ensure table doesn't squash too much */
    th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e5e7eb; }
    th { background: #f9fafb; font-weight: 700; color: #374151; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 700; }
    .badge.active { background: #dcfce7; color: #166534; }
    .badge.closed { background: #fee2e2; color: #991b1b; }
    .right { float: right; }
    .field { margin: 6px 0; }
    input.inline { padding: 6px 8px; border: 1px solid #e5e7eb; border-radius: 6px; width: 100%; box-sizing: border-box; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .status-waiting { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .status-active { background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .status-completed { background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .row-locked { background: #fef2f2; }
    .row-locked td { border-bottom-color: #fecaca; }
    .row-locked:hover { background: #fee2e2; }
    .locked-pill { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 800; background: #dc2626; color: #fff; margin-left: 8px; }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        .container-narrow { margin: 10px; padding: 15px; border-radius: 8px; }
        .grid-2 { grid-template-columns: 1fr; }
        
        /* Stack headers */
        .header-stack { flex-direction: column; align-items: stretch !important; gap: 15px; }
        .header-stack > div { width: 100%; }
        .header-stack .flex { width: 100%; justify-content: space-between; }
        .header-stack h1 { font-size: 1.5rem; margin-bottom: 5px; }
        
        /* Mobile Actions */
        .mobile-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; width: 100%; margin-top: 10px; }
        .mobile-actions .btn, .mobile-actions form, .mobile-actions form button { width: 100%; display: flex; margin: 0; flex-basis: 100%; }
        .mobile-actions .btn { font-size: 0.85rem; padding: 10px 4px; }
        
        /* Larger touch targets */
        .btn { padding: 12px; font-size: 1rem; }
        input.inline, select { height: 44px !important; font-size: 1rem; }
        
        /* Stack table actions */
        td.flex { flex-direction: column; gap: 5px; }
        td.flex .btn, td.flex form, td.flex form button { width: 100%; }
    }
  </style>
</head>
<body>
<?php include_once '../header.php'; ?>
<div class="main-content" style="margin-top: 10%;">
  <div class="container-narrow">
  <?php if ($room_code === ''): ?>
    <div class="flex header-stack" style="justify-content: space-between;">
      <h1 style="margin: 0;">Live Quiz Dashboard</h1>
      <div class="flex mobile-actions">
        <!-- Refresh Button -->
        <button class="btn info" onclick="window.location.reload();">
          <i class="fas fa-sync-alt"></i> Refresh
        </button>

        <form method="GET" action="online_quiz_dashboard.php" class="flex">
          <label class="muted">Status</label>
          <select name="status" class="input inline" style="height: 36px;">
            <option value="" <?= $status_filter===''?'selected':''; ?>>All</option>
            <option value="active" <?= $status_filter==='active'?'selected':''; ?>>Active</option>
            <option value="closed" <?= $status_filter==='closed'?'selected':''; ?>>Closed</option>
          </select>
          <button class="btn info" type="submit" style="padding: 8px 20px;">
            <i class="fas fa-filter"></i> Filter
          </button>
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
                     r.custom_class, r.custom_book,
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
    <div class="table-responsive">
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
              <?php 
                $display_class = !empty($row['custom_class']) ? $row['custom_class'] : ($row['class_name'] ?? 'Class ' . (int)$row['class_id']);
                $display_book = !empty($row['custom_book']) ? $row['custom_book'] : ($row['book_name'] ?? ('Book ' . (int)$row['book_id']));
              ?>
              <div><?= h($display_class) ?></div>
              <div class="muted"><?= h($display_book) ?></div>
            </td>
            <td><?= h($row['created_at']) ?></td>
            <td><?= (int)$row['q_count'] ?></td>
            <td><?= (int)$row['p_count'] ?></td>
            <td><span class="badge <?= $row['status']==='active'?'active':'closed' ?>"><?= h(ucfirst($row['status'])) ?></span></td>
            <td class="flex">
              <a class="btn info" href="online_quiz_dashboard.php?room=<?= h($row['room_code']) ?>">
                <i class="fas fa-eye"></i> View
              </a>
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
              <a class="btn warning" href="online_quiz_rehost.php?room=<?= h($row['room_code']) ?>" onclick="return confirm('Create a new room with the same questions?');">Rehost</a>
              <a class="btn success" href="online_quiz_export.php?room=<?= h($row['room_code']) ?>">Download</a>
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
    </div>
  <?php else: ?>
    <?php
      // Room detail view - restrict to current user
      $stmt = $conn->prepare("SELECT r.id, r.room_code, r.created_at, r.status, r.class_id, r.book_id, 
                                     r.custom_class, r.custom_book,
                                     c.class_name, b.book_name,
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
        $pstmt = $conn->prepare("SELECT p.id, p.name, p.roll_number, p.started_at, p.finished_at, p.score, p.total_questions, p.status, p.current_question, p.last_activity,
                                        COALESCE(p.is_screen_locked, 0) as is_screen_locked,
                                        (SELECT JSON_ARRAYAGG(JSON_OBJECT('type', event_type, 'data', event_data, 'at', created_at)) 
                                         FROM live_quiz_events e 
                                         WHERE e.participant_id = p.id AND e.room_id = ? AND e.event_type IN ('tab_switch', 'window_blur', 'copy_text', 'inspect_mode', 'right_click')) as alerts
                                 FROM quiz_participants p 
                                 WHERE p.room_id = ? 
                                 ORDER BY p.score DESC, TIMESTAMPDIFF(SECOND, p.started_at, COALESCE(p.finished_at, NOW())) ASC");
        $pstmt->bind_param('ii', $room_id, $room_id);
        $pstmt->execute();
        $participants = $pstmt->get_result();
        $pstmt->close();
    ?>
    <div class="flex header-stack" style="justify-content: space-between; align-items: flex-start;">
      <div>
        <h1 style="margin:0;">Room <?= h($room['room_code']) ?> <span class="badge <?= $room['status']==='active'?'active':'closed' ?>"><?= h(ucfirst($room['status'])) ?></span></h1>
        
        <?php 
          $class_display = !empty($room['custom_class']) ? $room['custom_class'] : ($room['class_name'] ?? 'Class ' . (int)$room['class_id']);
          $book_display = !empty($room['custom_book']) ? $room['custom_book'] : ($room['book_name'] ?? ('Book ' . (int)$room['book_id']));
          $is_customizable = ((int)$room['class_id'] === 0);
        ?>

        <div class="muted" id="roomContextDisplay">
          Class: <span id="displayClass"><?= h($class_display) ?></span> • 
          Book: <span id="displayBook"><?= h($book_display) ?></span>
          <?php if ($is_customizable): ?>
            <button class="btn info" style="padding: 6px 15px; font-size: 11.5px; margin-left: 12px; border-radius: 50px; border: none; box-shadow: 0 4px 10px rgba(14, 165, 233, 0.2);" onclick="toggleEditDetails()">
              <i class="fas fa-edit"></i> Edit Details
            </button>
          <?php endif; ?>
        </div>

        <?php if ($is_customizable): ?>
        <div id="roomContextEdit" style="display: none; margin-top: 10px; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
          <div class="flex" style="gap: 10px;">
            <input type="text" id="inputCustomClass" class="inline" placeholder="Custom Class Name" value="<?= h($room['custom_class'] ?? '') ?>" style="width: 150px;">
            <input type="text" id="inputCustomBook" class="inline" placeholder="Custom Book Name" value="<?= h($room['custom_book'] ?? '') ?>" style="width: 150px;">
            <button class="btn success" style="padding: 8px 16px; border-radius: 50px;" onclick="saveRoomDetails('<?= h($room['room_code']) ?>')">
              <i class="fas fa-check"></i> Save
            </button>
            <button class="btn secondary" style="padding: 8px 16px; border-radius: 50px;" onclick="toggleEditDetails()">
              <i class="fas fa-times"></i> Cancel
            </button>
          </div>
        </div>
        <?php endif; ?>

        <div class="muted">Created: <?= h($room['created_at']) ?> • Questions: <?= (int)$room['q_count'] ?></div>
        <div class="field" style="margin-top:8px;">
          <label class="muted">Join link</label>
          <input class="inline" readonly value="<?= h(join_url($room['room_code'])) ?>" onclick="this.select();" />
        </div>
      </div>
      <div class="flex mobile-actions">
        <!-- Refresh Button -->
        <button class="btn info" onclick="window.location.reload();">
          <i class="fas fa-sync-alt"></i> Refresh
        </button>
        
        <a class="btn secondary" href="online_quiz_dashboard.php"><i class="fas fa-arrow-left"></i> Back</a>
        <?php if ($room['status']==='active'): ?>
          <form method="POST" action="online_quiz_room_status.php" style="display:inline;">
            <input type="hidden" name="room_code" value="<?= h($room['room_code']) ?>" />
            <input type="hidden" name="action" value="close" />
            <button class="btn danger" type="submit"><i class="fas fa-times-circle"></i> Close Room</button>
          </form>
        <?php else: ?>
          <form method="POST" action="online_quiz_room_status.php" style="display:inline;">
            <input type="hidden" name="room_code" value="<?= h($room['room_code']) ?>" />
            <input type="hidden" name="action" value="open" />
            <button class="btn primary" type="submit"><i class="fas fa-play-circle"></i> Open Room</button>
          </form>
        <?php endif; ?>
        <a class="btn warning" href="online_quiz_rehost.php?room=<?= h($room['room_code']) ?>" onclick="return confirm('Create a new room with the same questions?');"><i class="fas fa-redo"></i> Rehost</a>
        <a class="btn purple" href="online_quiz_room_questions.php?room=<?= h($room['room_code']) ?>"><i class="fas fa-edit"></i> Edit Questions</a>
        <a class="btn success" href="online_quiz_export.php?room=<?= h($room['room_code']) ?>"><i class="fas fa-download"></i> Download</a>
      </div>
    </div>

    <!-- Lobby Management Section -->
    <?php if (!$room['quiz_started']): ?>
    <div style="background: #f0f9ff; border: 2px solid #0ea5e9; border-radius: 12px; padding: 20px; margin: 20px 0;">
      <div class="flex header-stack" style="justify-content: space-between; align-items: center;">
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
          <button class="btn success" onclick="startQuiz('<?= h($room['room_code']) ?>')" id="startQuizBtn" style="padding: 12px 28px; font-size: 1.1rem; border-radius: 50px;">
            <i class="fas fa-rocket"></i> Start Quiz
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
      <div class="flex header-stack" style="justify-content: space-between; align-items: center;">
        <div>
          <h3 style="margin: 0 0 8px; color: #15803d;">✅ Quiz In Progress</h3>
          <p style="margin: 0; color: #166534;">
            Started: <?= $room['start_time'] ? h($room['start_time']) : 'Just now' ?>
            <span id="hostTimer" style="margin-left: 15px; font-weight: bold; background: rgba(255,255,255,0.5); padding: 2px 8px; border-radius: 6px;"></span>
          </p>
        </div>
        <div class="flex">
          <div style="text-align: center; margin-right: 20px;">
            <div style="font-size: 24px; font-weight: bold; color: #15803d;"><?= (int)$room['active_count'] ?></div>
            <div style="font-size: 12px; color: #166534;">Active</div>
          </div>
          <button class="btn info" onclick="refreshParticipants()" style="padding: 12px 24px; border-radius: 50px;">
            <i class="fas fa-sync-alt"></i> Refresh
          </button>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <h2 style="margin-top:20px;">Participants</h2>
    <div class="table-responsive">
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
      <tbody id="participantsBody">
        <?php if ($participants && $participants->num_rows > 0): while ($p = $participants->fetch_assoc()):
              $finished = !empty($p['finished_at']);
              $score = is_null($p['score']) ? '-' : (int)$p['score'];
              $total = is_null($p['total_questions']) ? '-' : (int)$p['total_questions'];
              $percent = ($score!=='-' && $total!=='-' && $total>0) ? round(($score/$total)*100) . '%' : '-';
              $alerts = json_decode($p['alerts'] ?? '[]', true);
              $has_alerts = !empty($alerts);
              $is_locked = !empty($p['is_screen_locked']);
        ?>
          <tr class="<?= $is_locked ? 'row-locked' : '' ?>">
            <td>
              <div class="flex" style="gap: 8px; align-items: center;">
                <?= h($p['name']) ?>
                <?php if ($is_locked): ?>
                  <span class="locked-pill" title="Student screen is locked">LOCKED</span>
                <?php endif; ?>
                <?php if ($has_alerts): ?>
                  <span class="badge danger" style="font-size: 10px; padding: 2px 6px; cursor: help;" title="<?= count($alerts) ?> Suspicious activities detected">
                    ⚠️ ALERT
                  </span>
                <?php endif; ?>
              </div>
              <div><span class="status-<?= h($p['status']) ?>"><?= ucfirst(h($p['status'])) ?></span></div>
              <?php if ($has_alerts): ?>
                <div style="font-size: 10px; color: #ef4444; margin-top: 4px; max-width: 200px;">
                  <?php 
                    $counts = [];
                    foreach ($alerts as $a) {
                        $t = $a['type'] ?? '';
                        if ($t === '') continue;
                        $counts[$t] = ($counts[$t] ?? 0) + 1;
                    }
                    foreach ($counts as $type => $count) {
                        $type_label = $type;
                        if ($type === 'tab_switch') $type_label = 'Tab Changed';
                        elseif ($type === 'window_blur') $type_label = 'Window Switched';
                        elseif ($type === 'copy_text') $type_label = 'Text Copied';
                        elseif ($type === 'inspect_mode') $type_label = 'Inspect Mode / DevTools';
                        elseif ($type === 'right_click') $type_label = 'Right Click';
                        echo '• ' . $type_label . '(' . (int)$count . ') ';
                    }
                  ?>
                </div>
              <?php endif; ?>
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
            <td class="flex" style="gap: 6px; flex-wrap: wrap;">
              <?php if ($finished): ?>
                <a class="btn info" href="online_quiz_participant.php?pid=<?= (int)$p['id'] ?>">
                  <i class="fas fa-eye"></i> View
                </a>
              <?php elseif ($p['status'] === 'waiting'): ?>
                <span class="muted">Waiting in lobby</span>
              <?php else: ?>
                <span class="muted">In progress</span>
                <?php if (!empty($p['is_screen_locked'])): ?>
                  <button
                    type="button"
                    class="btn secondary"
                    style="padding: 6px 10px; font-size: 0.8rem;"
                    onclick="moderateParticipant(<?= (int)$p['id'] ?>, '<?= h($room['room_code']) ?>', 'unlock_screen', 'Unlock screen for <?= h($p['name']) ?>?')"
                  >
                    <i class="fas fa-lock-open"></i> Unlock
                  </button>
                <?php elseif ($has_alerts): ?>
                  <button
                    type="button"
                    class="btn warning"
                    style="padding: 6px 10px; font-size: 0.8rem;"
                    onclick="moderateParticipant(<?= (int)$p['id'] ?>, '<?= h($room['room_code']) ?>', 'lock_screen', 'Lock screen for <?= h($p['name']) ?>?')"
                  >
                    <i class="fas fa-lock"></i> Lock Screen
                  </button>
                  <button
                    type="button"
                    class="btn danger"
                    style="padding: 6px 10px; font-size: 0.8rem;"
                    onclick="moderateParticipant(<?= (int)$p['id'] ?>, '<?= h($room['room_code']) ?>', 'end_quiz', 'End quiz for <?= h($p['name']) ?>?')"
                  >
                    <i class="fas fa-ban"></i> End Quiz
                  </button>
                <?php else: ?>
                  <span class="muted">No alerts</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="7" class="muted">No participants yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
    <?php } // end room else ?>
  <?php endif; ?>
  </div>
</div>
<?php include '../footer.php'; ?>

<script>
// Dashboard live functionality
let updateInterval;
let timerInterval;
let currentRoomCode = '<?= isset($room['room_code']) ? h($room['room_code']) : '' ?>';
let currentRoomStatus = '<?= isset($room['status']) ? h($room['status']) : '' ?>';

// Timer logic for Host
function startHostTimer(startTimeStr, durationMin) {
    if (!startTimeStr) return;
    
    const startTime = new Date(startTimeStr).getTime();
    const durationMs = durationMin * 60 * 1000;
    const endTime = startTime + durationMs;
    
    function update() {
        const now = new Date().getTime();
        const diff = endTime - now;
        
        if (diff <= 0) {
            document.getElementById('hostTimer').textContent = "Time Over";
            clearInterval(timerInterval);

            // Auto close the room AND end all participants if it's currently active
            if (currentRoomStatus === 'active') {
                fetch('online_quiz_close_room.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'room_code=' + encodeURIComponent(currentRoomCode)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' || data.status === 'info') {
                        currentRoomStatus = 'closed';
                        // Update participants table immediately (no full reload)
                        try { refreshParticipants(); } catch(e) {}
                    }
                })
                .catch(err => console.error('Error auto-closing room:', err));
            }
            return;
        }
        
        const minutes = Math.floor(diff / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        
        const timerEl = document.getElementById('hostTimer');
        if (timerEl) {
            timerEl.textContent = `Time Remaining: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            if (diff < 300000) { // 5 mins
                timerEl.style.color = '#b91c1c'; // red
                timerEl.classList.add('pulse'); 
            }
        }
    }
    
    update(); // initial call
    timerInterval = setInterval(update, 1000);
}

<?php if (isset($room['quiz_started']) && $room['quiz_started'] && isset($room['start_time'])): ?>
 startHostTimer('<?= $room['start_time'] ?>', <?= (int)$room['quiz_duration_minutes'] ?>);
 
 // True realtime participants updates (SSE). Falls back to fast polling if needed.
 function renderParticipants(participants) {
     const tbody = document.getElementById('participantsBody');
     if (!tbody) return;

     tbody.innerHTML = '';
     let active = 0;

     const h = (s) => s ? String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') : '';
     const capitalize = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : '';

     (participants || []).forEach(p => {
         if (p.status === 'active') active++;

         const locked = (p.is_screen_locked === 1 || p.is_screen_locked === '1' || p.is_screen_locked === true);
         const finished = !!p.finished_at;
         const score = p.score !== null ? parseInt(p.score) : '-';
         const total = p.total_questions !== null ? parseInt(p.total_questions) : '-';
         let percent = '-';
         if (score !== '-' && total !== '-' && total > 0) {
             percent = Math.round((score / total) * 100) + '%';
         }

         let finishedDisplay = h(p.finished_at || '');
         if (!finished && p.status === 'active' && p.current_question) {
             finishedDisplay += `<div class="muted" style="font-size: 11px;">Q${p.current_question}</div>`;
         }

         let alerts = [];
         try {
             alerts = typeof p.alerts === 'string' ? JSON.parse(p.alerts) : (p.alerts || []);
         } catch(e) {}

         const hasAlerts = !!(alerts && alerts.length > 0);
         let actions = '';
         if (finished) {
             actions = `<a class="btn info" href="online_quiz_participant.php?pid=${p.id}"><i class="fas fa-eye"></i> View</a>`;
         } else if (p.status === 'waiting') {
             actions = '<span class="muted">Waiting in lobby</span>';
         } else {
             actions = `
                 <span class="muted">In progress</span>
                 ${locked
                     ? `<button type="button" class="btn secondary" style="padding: 6px 10px; font-size: 0.8rem;" onclick="moderateParticipant(${p.id}, '${h(currentRoomCode)}', 'unlock_screen', 'Unlock screen for ${h(p.name)}?')">
                             <i class="fas fa-lock-open"></i> Unlock
                        </button>`
                     : (hasAlerts
                         ? `<button type="button" class="btn warning" style="padding: 6px 10px; font-size: 0.8rem;" onclick="moderateParticipant(${p.id}, '${h(currentRoomCode)}', 'lock_screen', 'Lock screen for ${h(p.name)}?')">
                                 <i class="fas fa-lock"></i> Lock Screen
                            </button>`
                         : `<span class="muted">No alerts</span>`
                       )
                 }
                 ${(!locked && hasAlerts) ? `<button type="button" class="btn danger" style="padding: 6px 10px; font-size: 0.8rem;" onclick="moderateParticipant(${p.id}, '${h(currentRoomCode)}', 'end_quiz', 'End quiz for ${h(p.name)}?')">
                         <i class="fas fa-ban"></i> End Quiz
                     </button>` : ``}
             `;
         }

         const counts = {};
         if (alerts && alerts.length > 0) {
             alerts.forEach(a => {
                 const t = a && a.type ? a.type : '';
                 if (!t) return;
                 counts[t] = (counts[t] || 0) + 1;
             });
         }
         const alertLabels = Object.keys(counts).map(t => {
             let label = t;
             if (t === 'tab_switch') label = 'Tab Changed';
             if (t === 'window_blur') label = 'Window Switched';
             if (t === 'copy_text') label = 'Text Copied';
             if (t === 'inspect_mode') label = 'Inspect Mode / DevTools';
             if (t === 'right_click') label = 'Right Click';
             return `${label}(${counts[t]})`;
         }).join(' • ');

         const row = document.createElement('tr');
         if (locked) row.classList.add('row-locked');
         row.innerHTML = `
             <td>
                 <div class="flex" style="gap: 8px; align-items: center;">
                     ${h(p.name)}
                     ${locked ? `<span class="locked-pill" title="Student screen is locked">LOCKED</span>` : ''}
                     ${hasAlerts ? `<span class="badge danger" style="font-size: 10px; padding: 2px 6px; cursor: help;" title="${alerts.length} Suspicious activities detected">⚠️ ALERT</span>` : ''}
                 </div>
                 <div><span class="status-${h(p.status)}">${capitalize(h(p.status))}</span></div>
                 ${hasAlerts ? `<div style="font-size: 10px; color: #ef4444; margin-top: 4px; max-width: 200px;">• ${alertLabels}</div>` : ``}
             </td>
             <td>${h(p.roll_number)}</td>
             <td>${h(p.started_at || '')}</td>
             <td>${finishedDisplay}</td>
             <td>${score} / ${total}</td>
             <td>${percent}</td>
             <td class="flex" style="gap: 6px; flex-wrap: wrap;">${actions}</td>
         `;
         tbody.appendChild(row);
     });

     const ac = document.getElementById('activeCount');
     if (ac) ac.textContent = active;
 }

 function startParticipantsPolling() {
     if (window.__participantsPolling) return;
     window.__participantsPolling = setInterval(() => {
         fetch(`online_quiz_live_stats.php?room_code=${currentRoomCode}`)
             .then(res => res.json())
             .then(data => {
                 if (data.participants) renderParticipants(data.participants);
             })
             .catch(() => {});
     }, 2000);
 }

 function startParticipantsRealtime() {
     if (window.EventSource) {
         const es = new EventSource(`online_quiz_live_stats_sse.php?room_code=${encodeURIComponent(currentRoomCode)}`);
         es.addEventListener('participants', (e) => {
             try {
                 const data = JSON.parse(e.data);
                 if (data && data.participants) renderParticipants(data.participants);
             } catch (err) {}
         });
         es.addEventListener('error', () => {
             try { es.close(); } catch(e) {}
             startParticipantsPolling();
         });
         window.__participantsEventSource = es;
     } else {
         startParticipantsPolling();
     }
 }

 startParticipantsRealtime();
 
 <?php endif; ?>

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
    fetch(`online_quiz_live_stats.php?room_code=${currentRoomCode}`)
        .then(res => res.json())
        .then(data => {
            if (data.participants && typeof renderParticipants === 'function') {
                renderParticipants(data.participants);
            } else {
                window.location.reload();
            }
        })
        .catch(() => window.location.reload());
}

function moderateParticipant(participantId, roomCode, action, confirmationMessage) {
    if (!participantId || !roomCode || !action) return;
    if (confirmationMessage && !window.confirm(confirmationMessage)) return;

    const formData = new FormData();
    formData.append('participant_id', participantId);
    formData.append('room_code', roomCode);
    formData.append('action', action);

    fetch('online_quiz_moderate_participant.php', {
        method: 'POST',
        body: formData
    })
    .then(async (res) => {
        let data = {};
        try {
            data = await res.json();
        } catch (e) {
            data = { success: false, message: 'Invalid server response' };
        }

        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Failed to perform action');
        }

        alert(data.message || 'Action completed successfully');
        refreshParticipants();
    })
    .catch(err => {
        console.error('Participant moderation failed:', err);
        alert(err.message || 'Failed to perform action');
    });
}

function toggleEditDetails() {
    const display = document.getElementById('roomContextDisplay');
    const edit = document.getElementById('roomContextEdit');
    if (edit.style.display === 'none') {
        edit.style.display = 'block';
    } else {
        edit.style.display = 'none';
    }
}

function saveRoomDetails(roomCode) {
    const customClass = document.getElementById('inputCustomClass').value;
    const customBook = document.getElementById('inputCustomBook').value;
    
    fetch('ajax_update_room_details.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `room_code=${encodeURIComponent(roomCode)}&custom_class=${encodeURIComponent(customClass)}&custom_book=${encodeURIComponent(customBook)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('displayClass').textContent = customClass || 'Class 0';
            document.getElementById('displayBook').textContent = customBook || 'Book 0';
            toggleEditDetails();
            // Optional: reload to update other parts of the UI if needed
            // window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Failed to save details');
    });
}

// NOTE: Full page auto-refresh disabled for participants; they update in realtime now.
// Lobby auto-refresh is kept as-is to avoid changing that workflow.
if (currentRoomCode) {
    const lobbySection = document.querySelector('[style*="background: #f0f9ff"]');
    if (lobbySection) {
        updateInterval = setInterval(() => {
            window.location.reload();
        }, 15000);
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (updateInterval) clearInterval(updateInterval);
    if (timerInterval) clearInterval(timerInterval);
    if (window.__participantsPolling) clearInterval(window.__participantsPolling);
    if (window.__participantsEventSource) {
        try { window.__participantsEventSource.close(); } catch(e) {}
    }
});
</script>

</body>
</html>
