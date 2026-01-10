<?php
// require_once 'auth_check.php';
// quiz_setup.php - Public quiz setup page
include '../db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take a Quiz - Ahmad Learning Hub</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/quiz_setup.css">
</head>
<body>
<?php include '../header.php'; ?>
<div class="main-content">
    <div class="quiz-setup-container">
        <h1>Take a Quiz</h1>
        <p class="desc">Select your class, book, chapters, and the number of MCQs you want. We'll pick random questions for you from the selected content.</p>

        <form id="quizForm" method="POST" action="quiz.php">
            <div class="grid">
                <div>
                    <label for="class_id">Class</label>
                    <select class="select" id="class_id" name="class_id" required>
                        <option value="">Select a class</option>
                        <?php
                        // Load classes
                        $cls = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
                        if ($cls) {
                            while ($row = $cls->fetch_assoc()) {
                                echo '<option value="' . (int)$row['class_id'] . '">' . htmlspecialchars($row['class_name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label for="book_id">Book</label>
                    <div class="input-with-action">
                        <select id="book_id" name="book_id" required disabled>
                            <option value="">Select a book</option>
                        </select>
                        <button type="button" class="btn topic-btn" onclick="window.location.href='mcqs_topic.php'">Topic</button>
                    </div>
                    <div class="hint">Books are filtered by class.</div>
                </div>
            </div>

            <div class="grid full">
                <div>
                    <label for="chapters">Chapters (optional, multi-select)</label>
                    <div class="chapter-selector" id="chapterSelector">
                        <div class="selector-hint">Select a book first to see available chapters</div>
                    </div>
                    <input type="hidden" name="chapter_ids" id="chapter_ids">
                    <div class="hint">If you don't select any chapters, we'll include all chapters from the selected book.</div>
                </div>
            </div>

            <div class="grid full">
                <div>
                    <label for="mcq_count">Number of MCQs</label>
                    <input type="number" id="mcq_count" name="mcq_count" min="1" max="100" value="10" required>
                    <div class="hint">We will pick this many MCQs randomly from your selection.</div>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn secondary" id="resetBtn">Reset</button>
                <button type="submit" class="btn primary">Start Quiz</button>
            </div>
        </form>
    </div>
</div>
<?php include '../footer.php'; ?>

<script>
const classSel = document.getElementById('class_id');
const bookSel = document.getElementById('book_id');
const chapterSelector = document.getElementById('chapterSelector');
const chapterIdsInput = document.getElementById('chapter_ids');
const resetBtn = document.getElementById('resetBtn');

let selectedChapterIds = [];

function toQuery(params) {
  return Object.entries(params).map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
}

async function loadBooks() {
  bookSel.innerHTML = '<option value="">Loading...</option>';
  bookSel.disabled = true;
  clearChapters();
  
  const cid = classSel.value;
  if (!cid) { 
    bookSel.innerHTML = '<option value="">Select a book</option>'; 
    return; 
  }
  
  try {
    const res = await fetch('quiz_data.php?' + toQuery({ type: 'books', class_id: cid }));
    const data = await res.json();
    bookSel.innerHTML = '<option value="">Select a book</option>' + data.map(b => `<option value="${b.book_id}">${b.book_name}</option>`).join('');
    bookSel.disabled = false;
  } catch (error) {
    bookSel.innerHTML = '<option value="">Error loading books</option>';
    console.error('Error loading books:', error);
  }
}

async function loadChapters() {
  clearChapters();
  chapterSelector.innerHTML = '<div class="selector-hint">Loading chapters...</div>';
  
  const cid = classSel.value; 
  const bid = bookSel.value;
  
  if (!cid || !bid) {
    chapterSelector.innerHTML = '<div class="selector-hint">Select a book first to see available chapters</div>';
    return;
  }
  
  try {
    const res = await fetch('quiz_data.php?' + toQuery({ type: 'chapters', class_id: cid, book_id: bid }));
    const data = await res.json();
    
    if (data.length === 0) {
      chapterSelector.innerHTML = '<div class="selector-hint">No chapters found for this book</div>';
      return;
    }
    
    // Create checkbox list for chapters
    const chapterHTML = data.map(chapter => `
      <div class="chapter-item" onclick="toggleChapter(${chapter.chapter_id}, this)">
        <input type="checkbox" id="ch_${chapter.chapter_id}" value="${chapter.chapter_id}" onchange="handleChapterChange(${chapter.chapter_id})" onclick="event.stopPropagation()">
        <label for="ch_${chapter.chapter_id}" onclick="event.stopPropagation()">${chapter.chapter_name}</label>
      </div>
    `).join('');
    
    chapterSelector.innerHTML = chapterHTML;
  } catch (error) {
    chapterSelector.innerHTML = '<div class="selector-hint">Error loading chapters</div>';
    console.error('Error loading chapters:', error);
  }
}

function toggleChapter(chapterId, itemElement) {
  const checkbox = itemElement.querySelector('input[type="checkbox"]');
  checkbox.checked = !checkbox.checked;
  handleChapterChange(chapterId);
}

function handleChapterChange(chapterId) {
  if (selectedChapterIds.includes(chapterId)) {
    selectedChapterIds = selectedChapterIds.filter(id => id !== chapterId);
  } else {
    selectedChapterIds.push(chapterId);
  }
  updateChapterInput();
}

function updateChapterInput() {
  chapterIdsInput.value = selectedChapterIds.join(',');
}

function clearChapters() {
  selectedChapterIds = [];
  chapterIdsInput.value = '';
  chapterSelector.innerHTML = '<div class="selector-hint">Select a book first to see available chapters</div>';
}

// Event listeners
classSel.addEventListener('change', loadBooks);
bookSel.addEventListener('change', loadChapters);

// Pre-fill form from URL parameters
(async function() {
  const urlParams = new URLSearchParams(window.location.search);
  const urlClassId = urlParams.get('class_id');
  const urlBookId = urlParams.get('book_id');
  const urlChapterId = urlParams.get('chapter_id');

  if (urlClassId) {
    classSel.value = urlClassId;
    await loadBooks();
    
    if (urlBookId) {
      bookSel.value = urlBookId;
      await loadChapters();
      
      // If a specific chapter is provided, select it
      if (urlChapterId) {
        setTimeout(() => {
          const chapterCheckbox = document.getElementById(`ch_${urlChapterId}`);
          if (chapterCheckbox) {
            chapterCheckbox.checked = true;
            handleChapterChange(parseInt(urlChapterId));
          }
        }, 500);
      }
    }
  }
})();

// Reset button
resetBtn.addEventListener('click', () => {
  const form = document.getElementById('quizForm');
  form.reset();
  bookSel.innerHTML = '<option value="">Select a book</option>';
  bookSel.disabled = true;
  clearChapters();
});
</script>
</body>
</html>

