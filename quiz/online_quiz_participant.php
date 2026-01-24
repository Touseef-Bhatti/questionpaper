<?php
// online_quiz_participant.php - Detailed view of a participant's responses
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

$pid = intval($_GET['pid'] ?? 0);
if ($pid <= 0) {
  http_response_code(400);
  echo '<h2 style="color:red;">Missing participant ID</h2>';
  exit;
}

// Load participant and room
$stmt = $conn->prepare("SELECT p.id, p.name, p.roll_number, p.started_at, p.finished_at, p.score, p.total_questions, r.room_code, r.class_id, r.book_id
                        FROM quiz_participants p
                        JOIN quiz_rooms r ON r.id = p.room_id
                        WHERE p.id = ?");
$stmt->bind_param('i', $pid);
$stmt->execute();
$participant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$participant) {
  echo '<h2 style="color:red;">Participant not found</h2>';
  exit;
}

$room_code = $participant['room_code'];

// Load responses with questions
$q = $conn->prepare("SELECT qrq.id as question_id, qrq.question, qrq.option_a, qrq.option_b, qrq.option_c, qrq.option_d, qrq.correct_option,
                            r.selected_option, r.is_correct, r.time_spent_sec
                     FROM quiz_room_questions qrq
                     LEFT JOIN quiz_responses r ON r.question_id = qrq.id AND r.participant_id = ?
                     JOIN quiz_participants p ON p.room_id = qrq.room_id AND p.id = ?
                     ORDER BY qrq.id ASC");
$q->bind_param('ii', $pid, $pid);
$q->execute();
$responses = $q->get_result();
$q->close();

// Class and book names
$class_name = 'Unknown Class';
$book_name = 'Unknown Book';
$s = $conn->prepare("SELECT class_name FROM class WHERE class_id = ?");
$s->bind_param('i', $participant['class_id']);
$s->execute();
$r = $s->get_result();
if ($row = $r->fetch_assoc()) $class_name = $row['class_name'];
$s->close();
$s = $conn->prepare("SELECT book_name FROM book WHERE book_id = ?");
$s->bind_param('i', $participant['book_id']);
$s->execute();
$r = $s->get_result();
if ($row = $r->fetch_assoc()) $book_name = $row['book_name'];
$s->close();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participant Details | Ahmad Learning Hub</title>
    <?php include '../header.php'; ?>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --bg-glass: rgba(255, 255, 255, 0.9);
            --card-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: #f3f4f6;
            color: #1f2937;
        }

        .details-wrapper {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Profile Header Card */
        .profile-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            border-radius: 20px;
            padding: 40px;
            color: white;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .profile-header::after {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .profile-info h1 {
            font-size: 2.2rem;
            margin: 0 0 10px 0;
            color: white;
            font-weight: 800;
        }

        .profile-info .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .profile-info .subtitle span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .score-display {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .score-value {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1;
        }

        .score-total {
            font-size: 1.2rem;
            opacity: 0.8;
            margin-top: 5px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .icon-blue { background: #dbeafe; color: #1e40af; }
        .icon-purple { background: #f3e8ff; color: #6b21a8; }
        .icon-green { background: #dcfce7; color: #166534; }
        .icon-orange { background: #ffedd5; color: #9a3412; }

        .stat-content .label {
            font-size: 0.85rem;
            color: #6b7280;
            display: block;
        }

        .stat-content .value {
            font-weight: 700;
            color: #111827;
        }

        /* Responses Section */
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .question-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border-left: 6px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
        }

        .question-card.correct {
            border-left-color: var(--success);
        }

        .question-card.incorrect {
            border-left-color: var(--danger);
        }

        .question-card.unanswered {
            border-left-color: var(--warning);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            gap: 20px;
        }

        .question-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
        }

        .question-badge {
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .badge-correct { background: #dcfce7; color: #166534; }
        .badge-incorrect { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        .option-item {
            padding: 12px 15px;
            border-radius: 10px;
            border: 2px solid #f3f4f6;
            background: #f9fafb;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }

        .option-item.selected {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .option-item.correct {
            border-color: var(--success);
            background: #ecfdf5;
        }

        .option-item.incorrect-selection {
            border-color: var(--danger);
            background: #fef2f2;
        }

        .option-letter {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .selected .option-letter { background: #3b82f6; color: white; }
        .correct .option-letter { background: var(--success); color: white; }
        .incorrect-selection .option-letter { background: var(--danger); color: white; }

        .question-footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #6b7280;
        }

        .time-spent {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: white;
            color: #1e3a8a;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: #f9fafb;
            transform: translateX(-5px);
            color: #3b82f6;
        }

        @media (max-width: 768px) {
            .profile-header {
                padding: 30px;
                flex-direction: column;
                text-align: center;
            }
            .profile-info .subtitle {
                justify-content: center;
            }
            .score-display {
                width: 100%;
            }
        }
        .question-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.1);
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .responses-list > div {
            animation: fadeInUp 0.5s ease-out both;
        }

        /* Staggered animation for response cards */
        .responses-list > div:nth-child(1) { animation-delay: 0.1s; }
        .responses-list > div:nth-child(2) { animation-delay: 0.2s; }
        .responses-list > div:nth-child(3) { animation-delay: 0.3s; }
        .responses-list > div:nth-child(4) { animation-delay: 0.4s; }
        .responses-list > div:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body>
<?php include '../header.php'; ?>

<main class="main-content">
    <div class="details-wrapper">
        <a href="online_quiz_dashboard.php?room=<?= h($room_code) ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Room Dashboard
        </a>

        <!-- Header Card -->
        <div class="profile-header animate-fade-in-up">
            <div class="profile-info">
                <h1><?= h($participant['name']) ?></h1>
                <div class="subtitle">
                    <span><i class="fas fa-id-card"></i> Roll: <?= h($participant['roll_number']) ?></span>
                    <span><i class="fas fa-door-open"></i> Room: <?= h($room_code) ?></span>
                </div>
            </div>
            <div class="score-display">
                <div class="score-value"><?= h((string)$participant['score']) ?></div>
                <div class="score-total">out of <?= h((string)$participant['total_questions']) ?></div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-graduation-cap"></i></div>
                <div class="stat-content">
                    <span class="label">Class</span>
                    <span class="value"><?= h($class_name) ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-purple"><i class="fas fa-book"></i></div>
                <div class="stat-content">
                    <span class="label">Book</span>
                    <span class="value"><?= h($book_name) ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <span class="label">Started At</span>
                    <span class="value"><?= date('h:i A', strtotime($participant['started_at'])) ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-orange"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <span class="label">Finished At</span>
                    <span class="value"><?= $participant['finished_at'] ? date('h:i A', strtotime($participant['finished_at'])) : 'Ongoing' ?></span>
                </div>
            </div>
        </div>

        <h2 class="section-title"><i class="fas fa-list-check"></i> Response Breakdown</h2>

        <div class="responses-list">
            <?php if ($responses && $responses->num_rows > 0): 
                $qIdx = 1;
                while ($row = $responses->fetch_assoc()):
                    $selectedLetter = $row['selected_option'];
                    $correctLetter = '';
                    if ($row['correct_option'] === $row['option_a']) $correctLetter = 'A';
                    else if ($row['correct_option'] === $row['option_b']) $correctLetter = 'B';
                    else if ($row['correct_option'] === $row['option_c']) $correctLetter = 'C';
                    else if ($row['correct_option'] === $row['option_d']) $correctLetter = 'D';
                    
                    $isCorrect = !is_null($row['is_correct']) ? (int)$row['is_correct'] === 1 : null;
                    
                    $cardClass = 'unanswered';
                    $badgeText = 'Not Answered';
                    $badgeClass = 'badge-warning';
                    
                    if ($selectedLetter) {
                        if ($isCorrect) {
                            $cardClass = 'correct';
                            $badgeText = 'Correct';
                            $badgeClass = 'badge-correct';
                        } else {
                            $cardClass = 'incorrect';
                            $badgeText = 'Incorrect';
                            $badgeClass = 'badge-incorrect';
                        }
                    }
            ?>
                <div class="question-card <?= $cardClass ?>">
                    <div class="question-header">
                        <div class="question-text"><?= $qIdx++ ?>. <?= h($row['question']) ?></div>
                        <span class="question-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                    </div>

                    <div class="options-grid">
                        <?php 
                        $options = [
                            'A' => $row['option_a'],
                            'B' => $row['option_b'],
                            'C' => $row['option_c'],
                            'D' => $row['option_d']
                        ];
                        foreach ($options as $letter => $val): 
                            $optClass = '';
                            if ($letter === $correctLetter) {
                                $optClass = 'correct';
                            } elseif ($letter === $selectedLetter && !$isCorrect) {
                                $optClass = 'incorrect-selection';
                            } elseif ($letter === $selectedLetter) {
                                $optClass = 'selected';
                            }
                        ?>
                            <div class="option-item <?= $optClass ?>">
                                <div class="option-letter"><?= $letter ?></div>
                                <div class="option-text"><?= h($val) ?></div>
                                <?php if ($letter === $correctLetter): ?>
                                    <i class="fas fa-check-circle" style="margin-left:auto; color:var(--success)"></i>
                                <?php elseif ($letter === $selectedLetter && !$isCorrect): ?>
                                    <i class="fas fa-times-circle" style="margin-left:auto; color:var(--danger)"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="question-footer">
                        <div class="time-spent">
                            <i class="far fa-clock"></i> 
                            Time spent: <strong><?= h((string)($row['time_spent_sec'] ?? '0')) ?>s</strong>
                        </div>
                        <?php if ($selectedLetter && !$isCorrect): ?>
                            <div class="feedback">
                                <i class="fas fa-info-circle"></i> Correct answer was <strong><?= $correctLetter ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <div class="card p-4 text-center text-muted">
                    <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                    <p>No responses have been recorded for this participant yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../footer.php'; ?>
</body>
</html>
