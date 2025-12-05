<?php
// online_quiz_join.php - Student joins a room with name and roll number
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

$room_code = strtoupper(trim($_GET['room'] ?? ''));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_code = strtoupper(trim($_POST['room_code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $roll = trim($_POST['roll_number'] ?? '');

    if ($room_code === '' || $name === '' || $roll === '') {
        $error = 'All fields are required.';
    } else {
        // Lookup room and check lobby settings
        $stmt = $conn->prepare("SELECT id, status, lobby_enabled, quiz_started FROM quiz_rooms WHERE room_code = ?");
        $stmt->bind_param('s', $room_code);
        $stmt->execute();
        $res = $stmt->get_result();
        $room = $res->fetch_assoc();
        $stmt->close();

        if (!$room) {
            $error = 'Invalid room code.';
        } elseif ($room['status'] !== 'active') {
            $error = 'This room is closed.';
        } else {
            // Create participant with waiting status for lobby
            $room_id = (int)$room['id'];
            $stmt = $conn->prepare("INSERT INTO quiz_participants (room_id, name, roll_number, status) VALUES (?, ?, ?, 'waiting')");
            $stmt->bind_param('iss', $room_id, $name, $roll);
            if ($stmt->execute()) {
                $participant_id = $stmt->insert_id;
                $_SESSION['quiz_participant_id'] = $participant_id;
                $_SESSION['quiz_room_code'] = $room_code;
                $_SESSION['quiz_room_id'] = $room_id;
                
                // Check if quiz has already started
                if ($room['quiz_started']) {
                    // Quiz already started, set status to active and go directly to quiz page
                    $conn->query("UPDATE quiz_participants SET status = 'active' WHERE id = $participant_id");
                    header('Location: online_quiz_take.php?room=' . urlencode($room_code));
                } else {
                    // Quiz not started yet, go to lobby
                    header('Location: online_quiz_lobby.php?room=' . urlencode($room_code));
                }
                exit;
            } else {
                $error = 'Failed to join the room. Please try again.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Join Quiz Room - Ahmad Learning Hub</title>
  <link rel="stylesheet" href="../css/main.css">
  <style>
    .join-card { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); padding: 24px; }
    .join-card h1 { margin: 0 0 8px; }
    .field { margin: 12px 0; }
    label { display: block; margin-bottom: 6px; font-weight: 600; }
    input[type="text"] { width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; }
    .btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .btn.primary { background: #4f6ef7; color: white; }
    .error { color: #dc2626; margin: 8px 0; }
  </style>
</head>
<body>
<?php include '../header.php'; ?>
<div class="main-content">
  <div class="join-card">
    <h1>Join Quiz Room</h1>
    <?php if ($error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="online_quiz_join.php">
      <div class="field">
        <label for="room_code">Room Code</label>
        <input type="text" id="room_code" name="room_code" value="<?php echo htmlspecialchars($room_code); ?>" placeholder="e.g., ABC123" required />
      </div>
      <div class="field">
        <label for="name">Your Name</label>
        <input type="text" id="name" name="name" placeholder="Enter your name" required />
      </div>
      <div class="field">
        <label for="roll_number">Roll Number</label>
        <input type="text" id="roll_number" name="roll_number" placeholder="Enter your roll number" required />
      </div>
      <button type="submit" class="btn primary">Join</button>
    </form>
  </div>
</div>
<?php include '../footer.php'; ?>
</body>
</html>
