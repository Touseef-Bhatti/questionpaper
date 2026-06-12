<?php
// online_quiz_join.php - Student joins a room with name and roll number
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db_connect.php';

$room_code = strtoupper(trim($_GET['room'] ?? ''));
$error = '';

$quizSeoSections = [
    [
        'heading' => 'Join an online quiz with a room code',
        'paragraphs' => [
            'Ahmad Learning Hub provides a simple online quiz room system for students, teachers, tutors and academies. A participant joins by entering the room code shared by the host, followed by a full name and numeric roll number. The room code connects the student with the correct live session, while the name and roll number help the host identify participants during the activity. After successful entry, the student is taken to the lobby when the quiz has not started, or directly to the active quiz when the host has already begun the session.',
            'This join page is useful for classroom MCQ tests, academy competitions, revision sessions, homework checks and quick formative assessments. Students do not need to search for the correct quiz or configure a subject themselves. The host prepares the room and shares one code or join link with the group. Participants should enter the details requested by their teacher and keep the page open while waiting in the lobby. If a code is rejected, check every letter and number, confirm that the room is still active and ask the host whether a new room has been created.',
        ],
    ],
    [
        'heading' => 'You can host your own live quiz',
        'paragraphs' => [
            'Teachers and registered users are not limited to joining rooms. You can create and host your own online quiz from Ahmad Learning Hub. The host tool lets you choose a class and book, select chapters, search for questions by topic, add custom MCQs, reuse saved questions or generate MCQs from an uploaded file. You can then set the total number of questions and the quiz duration before creating the room. This makes the system suitable for planned lessons as well as short activities prepared immediately before class.',
            'Hosting gives the teacher control over the quiz content and timing. A mathematics teacher can prepare a chapter test, a science tutor can check definitions and concepts, and an academy can run a mixed revision competition. The host receives a room code and shareable join link after creating the room. Students use this page to enter that room. The host dashboard can then be used to manage the session, monitor participation and start the quiz when the class is ready. Creating a focused room is usually more useful than sending students to an unrelated public quiz.',
        ],
    ],
    [
        'heading' => 'Step-by-step guide to creating a quiz room',
        'paragraphs' => [
            'Start by opening the Host Your Own Quiz page and signing in when required. Choose how you want to build the question set. You can select a class, book and chapters from the available question bank; search for questions by topic; add your own MCQs; select questions saved to your profile; or upload a supported document or image to create MCQs from its content. Review the questions carefully, especially when they come from an uploaded file or custom entry, so that every option is clear and only one answer is correct.',
            'Next, choose the number of questions and set a realistic duration. A short warm-up may need only a few questions, while a revision assessment may require a larger set and more time. Create the room after checking the preview. The system generates a room code and join link that can be shared through a classroom display, messaging group or learning platform. Ask students to join with recognizable names and their assigned roll numbers. Once the expected participants are present, use the host controls to begin the session and monitor its progress.',
        ],
    ],
    [
        'heading' => 'How to prepare a fair and useful live MCQ quiz',
        'paragraphs' => [
            'A strong live quiz should have a clear learning purpose. Decide whether the activity is checking prior knowledge, recent teaching, chapter revision or exam readiness. Select questions that match that purpose instead of choosing a large quantity simply to make the quiz longer. A balanced set normally includes straightforward recall, understanding and application. Avoid several questions that test exactly the same small fact. When using different chapters, distribute questions in a way that reflects the material taught and the importance of each topic.',
            'Read every MCQ from the student perspective. The question should be complete, the alternatives should be plausible and the correct option should be defensible. Remove accidental clues such as one option being much longer than the others. Check scientific terms, formulas, spellings, dates and names. Set enough time for students to read and think without turning the activity into a typing or internet-speed contest. After the quiz, use the results as evidence for feedback and revision rather than treating the score as the only measure of learning.',
        ],
    ],
    [
        'heading' => 'Using live quizzes in schools and academies',
        'paragraphs' => [
            'Live quizzes can support teaching before, during and after a lesson. A short diagnostic room at the beginning can reveal what students already know. Mid-lesson questions can check whether an explanation was understood before the teacher moves forward. An end-of-lesson quiz can summarize key ideas and identify topics that need another example. Weekly academy sessions can combine several chapters so learners practise retrieval across the syllabus instead of reviewing one topic in isolation.',
            'The room format also works for remote classes and blended learning. A host can share the join link with students who are using phones, tablets or computers, provided they have a stable browser connection. Clear instructions are important: tell participants when to join, which name format to use, whether books are allowed and what to do if the connection drops. For formal assessment, the institution should consider supervision, device access and its own academic policies. For low-stakes practice, the system offers a quick way to make participation visible and discussion more focused.',
        ],
    ],
    [
        'heading' => 'Online quiz hosting for Pakistan classrooms',
        'paragraphs' => [
            'Teachers in Pakistan often work with different class sizes, board schedules and levels of device access. A room-code system keeps the joining process short because students only need the code, their name and roll number. The host can create quizzes for Matric, Intermediate and other available classes and subjects using the books and questions present on the platform. Punjab Board and BISE preparation can be supported by selecting textbook chapters and MCQs relevant to the class currently being taught.',
            'The platform should be used alongside the latest syllabus, pairing scheme and instructions from the relevant board or institution. A quiz created here is a teacher-made learning activity, not an official board examination. That flexibility is valuable: schools in Lahore, Multan, Faisalabad, Gujranwala, Rawalpindi, Sargodha, Sahiwal, Bahawalpur and other regions can adapt rooms to their own timetable. Tutors can also create smaller targeted quizzes for students who need additional practice in one chapter or concept.',
        ],
    ],
    [
        'heading' => 'Room codes, lobby access and participant details',
        'paragraphs' => [
            'The room code is the key that identifies a live quiz. Enter it exactly as the host provides it. Codes may contain letters and numbers, so students should avoid adding spaces or changing similar-looking characters. The roll number field accepts numbers and is used to distinguish participants who may have similar names. Students should not enter another learner\'s information. Accurate details make the participant list, activity record and final results more useful to the teacher.',
            'When the lobby is active, joining does not mean the quiz has started. Wait for the host to admit or start the group and avoid repeatedly refreshing the page unless instructed. A room can be closed by the host, and an old code may no longer work. If the quiz has already started, the system may take an accepted participant directly to the quiz. Students should read the first screen carefully and contact the host when they see an invalid or closed-room message rather than attempting unrelated codes.',
        ],
    ],
    [
        'heading' => 'Choosing questions from books, topics or your own material',
        'paragraphs' => [
            'The host tool supports several question-building routes. Selecting a class, book and chapter is useful when the quiz must follow the current textbook sequence. Topic search is helpful when one concept appears across a broader question collection. Custom questions allow a teacher to assess a recent classroom example, local context or learning outcome not already available. Saved questions make repeated course planning faster because a good item can be selected again without being typed from the beginning.',
            'File-based MCQ creation can help turn a teacher\'s own PDF, Word document, presentation or image into a starting set of questions. Generated questions still require review. Source text may contain headings, incomplete extracts or formatting that changes meaning, and an automated option can occasionally be ambiguous. The host should verify the question, all four choices and the marked answer. Combining platform questions with carefully reviewed custom material can produce a quiz that is both syllabus-aware and closely connected to the lesson students actually received.',
        ],
    ],
    [
        'heading' => 'After the quiz: feedback and next steps',
        'paragraphs' => [
            'The most valuable part of a quiz often happens after students submit. Review questions that produced many incorrect answers and determine whether the issue was weak preparation, confusing wording or a concept that needs reteaching. Ask students to explain why the correct option works and why the distractors do not. This turns a competitive activity into a learning conversation. A low score should lead to a specific revision action, while a high score should still be checked for understanding rather than speed alone.',
            'Hosts can use patterns from the room to plan the next class. If most participants miss one topic, prepare another explanation and a shorter follow-up quiz. If only a few students struggle, provide targeted exercises or pair support. Students can record difficult terms, formulas or misconceptions and revise them before another attempt. Repeated quizzes are most effective when the second activity responds to evidence from the first instead of simply presenting another random set of MCQs.',
        ],
    ],
    [
        'heading' => 'A simple checklist before you host',
        'paragraphs' => [
            'Before creating the room, confirm the class, subject, chapters, question count and duration. Preview custom or generated MCQs and remove duplicates. Decide how students will receive the code and what name format they should use. Check that the classroom has an appropriate internet connection and provide an alternative activity for any learner who cannot access a device. Explain whether the quiz is practice, competition or graded assessment so students understand the expectations.',
            'After creating the room, keep the host dashboard open, share the correct join link and wait until the participant list is ready. Start only when instructions have been given. During the session, avoid changing expectations without explaining them. At the end, discuss key answers and save useful observations for future planning. This short preparation process helps the technology support the lesson instead of distracting from it.',
        ],
    ],
];

$quizFaqs = [
    ['question' => 'How do students join an Ahmad Learning Hub live quiz?', 'answer' => 'Students enter the room code shared by the host, their full name and a numeric roll number, then select Enter Quiz Room. They will move to the lobby or active quiz depending on the room status.'],
    ['question' => 'Can I host my own online quiz?', 'answer' => 'Yes. Registered users can open the quiz host page, choose or create MCQs, set the question count and duration, create a room and share its code or join link with participants.'],
    ['question' => 'What can I use to create quiz questions?', 'answer' => 'Hosts can use available class and book questions, select chapters, search by topic, add custom MCQs, reuse saved questions or generate MCQs from a supported uploaded file.'],
    ['question' => 'Why is my room code not working?', 'answer' => 'The code may be typed incorrectly, the room may be closed or the host may have created a new room. Confirm the exact code with the host and try again without extra spaces.'],
    ['question' => 'Do I need an account to join a quiz?', 'answer' => 'The join form requests a room code, name and roll number. Hosting a quiz requires signing in so the room and host controls can be associated with the registered user.'],
];

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
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <?php include_once dirname(__DIR__) . '/includes/monetag_ads.php'; ?>
  <title>Join or Host an Online Live Quiz | Ahmad Learning Hub</title>
  <meta name="description" content="Join an Ahmad Learning Hub live quiz with a room code or host your own online MCQ quiz for students. Create rooms, select questions and share a join link.">
  <meta name="keywords" content="join online quiz with room code, host live quiz online, online MCQ quiz Pakistan, classroom quiz maker, quiz room for students, teacher live quiz host, Punjab Board MCQ quiz">
  <meta name="author" content="Ahmad Learning Hub">
  <meta name="robots" content="index, follow">

  <!-- Facebook / Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:title" content="Join or Host an Online Live Quiz | Ahmad Learning Hub">
  <meta property="og:description" content="Enter a room code to join a live MCQ quiz, or create and host your own quiz room for students.">
  <meta property="og:image" content="https://ahmadlearninghub.com.pk/assets/images/quiz-join-og.jpg">

  <!-- JSON-LD Structured Data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "WebPage",
        "name": "Join or Host an Online Live Quiz",
        "description": "Join a live MCQ quiz using a room code or create and host an online quiz room for students.",
        "publisher": {
          "@type": "Organization",
          "name": "Ahmad Learning Hub"
        }
      },
      {
        "@type": "HowTo",
        "name": "How to host an online quiz room",
        "step": [
          {"@type": "HowToStep", "name": "Open the host page", "text": "Sign in and open the Host Your Own Quiz page."},
          {"@type": "HowToStep", "name": "Choose questions", "text": "Select questions by class, book, chapter or topic, or add custom questions."},
          {"@type": "HowToStep", "name": "Configure the quiz", "text": "Set the number of questions and quiz duration, then review the preview."},
          {"@type": "HowToStep", "name": "Create and share the room", "text": "Create the room and share its room code or join link with participants."},
          {"@type": "HowToStep", "name": "Start the session", "text": "Wait for participants to join, then start and manage the live quiz."}
        ]
      },
      {
        "@type": "FAQPage",
        "mainEntity": <?= json_encode(array_map(static fn (array $faq): array => [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
        ], $quizFaqs), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
      }
    ]
  }
  </script>

  <link rel="stylesheet" href="<?= ($assetBase ?? '') ?>css/main.css">
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
      margin-top: 10%;
    }


    .join-card {
      width: 100%;
      max-width: 480px;
      background: #FFFFFF; /* Removed backdrop-filter blur for performance */
      border: 1px solid #E5E7EB;
      border-radius: 24px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
      padding: 3rem;
      transition: transform 0.2s ease;
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

    .quiz-seo-content {
      width: min(100%, 1040px);
      margin-top: 40px;
      padding: 44px;
      color: #475569;
      text-align: left;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 24px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    }

    .quiz-host-hero {
      max-width: 820px;
      margin: 0 auto 32px;
      text-align: center;
    }

    .quiz-host-eyebrow {
      display: inline-block;
      margin-bottom: 10px;
      color: var(--primary);
      font-size: 0.82rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .quiz-host-hero h2,
    .quiz-seo-faq h2 {
      margin: 0 0 14px;
      color: #0f172a;
      font-size: clamp(1.6rem, 4vw, 2.1rem);
      line-height: 1.25;
    }

    .quiz-host-hero p {
      margin: 0 auto;
      color: #64748b;
      font-size: 1.06rem;
      line-height: 1.75;
    }

    .quiz-host-actions {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 24px;
    }

    .quiz-host-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 48px;
      padding: 0 22px;
      border-radius: 12px;
      font-weight: 700;
      text-decoration: none;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .quiz-host-button:hover {
      transform: translateY(-2px);
    }

    .quiz-host-button--primary {
      color: #fff;
      background: linear-gradient(135deg, var(--primary), var(--accent));
      box-shadow: 0 10px 20px rgba(79, 70, 229, 0.22);
    }

    .quiz-host-button--secondary {
      color: var(--primary);
      background: var(--primary-light);
      border: 1px solid #c7d2fe;
    }

    .quiz-host-steps {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 14px;
      margin: 32px 0 12px;
    }

    .quiz-host-steps article {
      padding: 18px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
    }

    .quiz-host-steps strong,
    .quiz-host-steps span {
      display: block;
    }

    .quiz-host-steps strong {
      margin-bottom: 8px;
      color: #1e293b;
    }

    .quiz-host-steps span {
      font-size: 0.9rem;
      line-height: 1.55;
    }

    .quiz-seo-long-form {
      max-width: 900px;
      margin: 36px auto 0;
    }

    .quiz-guide-section {
      padding: 28px 0;
      border-top: 1px solid #e2e8f0;
    }

    .quiz-guide-section h3 {
      margin: 0 0 14px;
      color: #0f172a;
      font-size: 1.35rem;
      line-height: 1.35;
    }

    .quiz-guide-section p {
      margin: 0 0 14px;
      color: #475569;
      font-size: 1rem;
      line-height: 1.8;
    }

    .quiz-guide-section p:last-child {
      margin-bottom: 0;
    }

    .quiz-seo-faq {
      margin-top: 28px;
      padding-top: 36px;
      border-top: 1px solid #e2e8f0;
      text-align: center;
    }

    .quiz-faq-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
      margin-top: 24px;
      text-align: left;
    }

    .quiz-faq-item {
      padding: 20px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
    }

    .quiz-faq-item h3 {
      margin: 0 0 8px;
      color: #1e293b;
      font-size: 1rem;
      line-height: 1.45;
    }

    .quiz-faq-item p {
      margin: 0;
      color: #64748b;
      font-size: 0.94rem;
      line-height: 1.65;
    }

    @media (max-width: 480px) {
      .join-card {
        padding: 2rem;
      }
      .join-card h1 {
        font-size: 1.75rem;
      }

      .quiz-seo-content {
        padding: 26px 18px;
      }

      .quiz-host-actions,
      .quiz-faq-grid {
        display: grid;
        grid-template-columns: 1fr;
      }

      .quiz-host-button {
        width: 100%;
      }
    }

    @media (max-width: 900px) {
      .quiz-host-steps {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 600px) {
      .quiz-host-steps {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<?php include_once '../header.php'; ?>

<div class="main-content">
  <div class="join-container" style="flex-direction: column;">
    <div class="join-card">
      <h1>Join Live AI Quiz</h1>
      <p class="subtitle">Enter your details to join the live session</p>

      <?php if ($error): ?>
        <div class="error-box">
          <i class="fas fa-exclamation-circle"></i>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="online_quiz_join.php">
        <div class="field">
          <label for="room_code">📌 Room Code</label>
          <div class="input-wrapper">
            <i class="fas fa-hashtag"></i>
            <input type="text" id="room_code" name="room_code" value="<?php echo htmlspecialchars($room_code); ?>" placeholder="e.g., ABC123" required />
          </div>
        </div>
        
        <div class="field">
          <label for="name">👤 Your Full Name</label>
          <div class="input-wrapper">
            <i class="fas fa-user"></i>
            <input type="text" id="name" name="name" placeholder="Enter your name" required />
          </div>
        </div>

        <div class="field">
          <label for="roll_number">🔢 Roll Number</label>
          <div class="input-wrapper">
            <i class="fas fa-id-card"></i>
            <input type="text" id="roll_number" name="roll_number" placeholder="Enter your roll number" required />
          </div>
        </div>

        <button type="submit" class="btn-join">
          <span>Enter Quiz Room</span>
          <i class="fas fa-arrow-right"></i>
        </button>
      </form>
    </div>

    <section class="quiz-seo-content" aria-labelledby="host-quiz-guide-title">
      <div class="quiz-host-hero">
        <span class="quiz-host-eyebrow">For teachers, tutors and academies</span>
        <h2 id="host-quiz-guide-title">Host Your Own Online Quiz</h2>
        <p>Create a live MCQ room, choose questions from books and chapters, add your own questions, set the duration and share one room code with your students.</p>
        <div class="quiz-host-actions">
          <a class="quiz-host-button quiz-host-button--primary" href="online_quiz_host_new.php">Host Your Own Quiz</a>
          <a class="quiz-host-button quiz-host-button--secondary" href="#quiz-hosting-guide">Read Hosting Guide</a>
        </div>
      </div>

      <div class="quiz-host-steps" aria-label="Quiz hosting steps">
        <article><strong>1. Choose content</strong><span>Select a class, book, chapters, topics, saved questions or custom MCQs.</span></article>
        <article><strong>2. Configure room</strong><span>Set the question count and a suitable duration for your students.</span></article>
        <article><strong>3. Share the code</strong><span>Create the room and send its code or join link to participants.</span></article>
        <article><strong>4. Start and review</strong><span>Manage the live session and use the results to guide feedback.</span></article>
      </div>

      <div id="quiz-hosting-guide" class="quiz-seo-long-form">
        <?php foreach ($quizSeoSections as $section): ?>
          <article class="quiz-guide-section">
            <h3><?= htmlspecialchars($section['heading']) ?></h3>
            <?php foreach ($section['paragraphs'] as $paragraph): ?>
              <p><?= htmlspecialchars($paragraph) ?></p>
            <?php endforeach; ?>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="quiz-seo-faq">
        <h2>Online Quiz Join and Hosting FAQs</h2>
        <div class="quiz-faq-grid">
          <?php foreach ($quizFaqs as $faq): ?>
            <article class="quiz-faq-item">
              <h3><?= htmlspecialchars($faq['question']) ?></h3>
              <p><?= htmlspecialchars($faq['answer']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <?php if (false): ?>
    <!-- Legacy SEO section intentionally disabled. -->
    <div style="max-width: 800px; margin-top: 40px; color: #4b5563; text-align: center; background: white; padding: 40px; border-radius: 20px; border: 1px solid #e5e7eb; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <h2 style="color: #1e293b; font-size: 1.75rem; font-weight: 800; margin-bottom: 15px;">Why Join Ahmad Learning Hub Live Quizzes?</h2>
        <p style="line-height: 1.8; margin-bottom: 30px; font-size: 1.05rem;">Participate in real-time MCQ competitions designed around the <strong>New Syllabus Board Exams 2026</strong>. Our platform provides students with instant feedback, a dynamic competitive leaderboard, and <strong>AI-generated accuracy reports</strong>.</p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; text-align: left;">
            <div style="padding: 15px; border-radius: 12px; background: #f8fafc;">
                <h4 style="margin: 0; color: var(--primary); font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">🌐 Global Rankings</h4>
                <p style="font-size: 0.9rem; margin-top: 10px; color: #64748b;">See how you rank against peers in real-time as you answer questions correctly and quickly.</p>
            </div>
            <div style="padding: 15px; border-radius: 12px; background: #f8fafc;">
                <h4 style="margin: 0; color: var(--primary); font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">🤖 AI Evaluation</h4>
                <p style="font-size: 0.9rem; margin-top: 10px; color: #64748b;">Understand your performance with automated AI analysis that highlights your strengths and weaknesses.</p>
            </div>
            <div style="padding: 15px; border-radius: 12px; background: #f8fafc;">
                <h4 style="margin: 0; color: var(--primary); font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">📘 Syllabus Focused</h4>
                <p style="font-size: 0.9rem; margin-top: 10px; color: #64748b;">All room questions are verified and mapped to the latest board paper patterns (Punjab, Federal, and more).</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div>
  </div>
  </div>
</div>
<?php include '../footer.php'; ?>
</body>
</html>
