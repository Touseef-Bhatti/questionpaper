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
    } elseif (!ctype_digit($roll)) {
        $error = 'Roll Number must contain only numbers.';
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
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #4F46E5;
      --primary-dark: #3730A3;
      --primary-light: #EEF2FF;
      --accent: #0EA5E9;
      --text-main: #1F2937;
      --text-muted: #6B7280;
      --bg-gradient: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);
      --card-bg: rgba(255, 255, 255, 0.95);
      --glass-border: rgba(255, 255, 255, 0.3);
    }

    body {
      font-family: 'Outfit', sans-serif;
      background: var(--bg-gradient);
    }

    .join-container {
      min-height: calc(100vh - 160px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }

    .join-card {
      width: 100%;
      max-width: 480px;
      background: #FFFFFF; /* Removed backdrop-filter blur for performance */
      border: 1px solid #E5E7EB;
      border-radius: 24px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
      padding: 3rem;
      transition: transform 0.2s ease; /* Simplified transition: only transform */
      will-change: transform; /* Hint for hardware acceleration */
    }

    .join-card:hover {
      transform: translateY(-5px);
      /* Removed box-shadow transition here to prevent repaints */
    }

    .join-card h1 {
      font-size: 2rem;
      font-weight: 700;
      color: var(--text-main);
      margin-bottom: 0.5rem;
      text-align: center;
      background: linear-gradient(to right, var(--primary), var(--accent));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .join-card p.subtitle {
      color: var(--text-muted);
      text-align: center;
      margin-bottom: 2rem;
      font-size: 1rem;
    }

    .field {
      margin-bottom: 1.5rem;
      position: relative;
    }

    label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
      color: var(--text-main);
      font-size: 0.9rem;
      margin-left: 0.25rem;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-wrapper i {
      position: absolute;
      left: 1rem;
      color: var(--text-muted);
      font-size: 1rem;
      transition: color 0.3s ease;
    }

    input[type="text"] {
      width: 100%;
      padding: 0.875rem 1rem 0.875rem 2.75rem;
      background: #F9FAFB;
      border: 1px solid #E5E7EB;
      border-radius: 12px;
      font-size: 1rem;
      color: var(--text-main);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      font-family: inherit;
    }

    input[type="text"]:focus {
      background: #FFFFFF;
      border-color: var(--primary);
      box-shadow: 0 0 0 4px var(--primary-light);
      outline: none;
    }

    input[type="text"]:focus + i {
      color: var(--primary);
    }

    .btn-join {
      width: 100%;
      padding: 1rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      transition: transform 0.2s ease, opacity 0.2s ease;
      box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
      margin-top: 2rem;
    }

    .btn-join:hover {
      transform: translateY(-2px);
      opacity: 0.95;
    }

    .btn-join:active {
      transform: translateY(0);
    }

    .error-box {
      background: #FEF2F2;
      border-left: 4px solid #EF4444;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      color: #991B1B;
      font-size: 0.95rem;
      animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
    }

    @keyframes shake {
      10%, 90% { transform: translate3d(-1px, 0, 0); }
      20%, 80% { transform: translate3d(2px, 0, 0); }
      30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
      40%, 60% { transform: translate3d(4px, 0, 0); }
    }

    @media (max-width: 480px) {
      .join-card {
        padding: 2rem;
      }
      .join-card h1 {
        font-size: 1.75rem;
      }
    }
  </style>
</head>
<body>
<?php include '../header.php'; ?>
<div class="main-content">
  <div class="join-container">
    <div class="join-card">
      <h1>Join Quiz Room</h1>
      <p class="subtitle">Enter your details to join the live session</p>

      <?php if ($error): ?>
        <div class="error-box">
          <i class="fas fa-exclamation-circle"></i>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="online_quiz_join.php">
        <div class="field">
          <label for="room_code">Room Code</label>
          <div class="input-wrapper">
            <i class="fas fa-hashtag"></i>
            <input type="text" id="room_code" name="room_code" value="<?php echo htmlspecialchars($room_code); ?>" placeholder="e.g., ABC123" required />
          </div>
        </div>
        
        <div class="field">
          <label for="name">Your Full Name</label>
          <div class="input-wrapper">
            <i class="fas fa-user"></i>
            <input type="text" id="name" name="name" placeholder="Enter your name" required />
          </div>
        </div>

        <div class="field">
          <label for="roll_number">Roll Number</label>
          <div class="input-wrapper">
            <i class="fas fa-id-card"></i>
            <input type="text" id="roll_number" name="roll_number" placeholder="Enter your roll number" required />
          </div>
        </div>

        <button type="submit" class="btn-join">
          <span>Join Room</span>
          <i class="fas fa-arrow-right"></i>
        </button>
      </form>
    </div>
  </div>
</div>
<?php include '../footer.php'; ?>
</body>
</html>
