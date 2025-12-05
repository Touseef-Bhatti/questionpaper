<?php
// online_quiz_host.php - Professional quiz room creation
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';

include '../db_connect.php';

// Get user info for personalized experience
$user_name = $_SESSION['name'] ?? 'Instructor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Host Online Quiz - Ahmad Learning Hub</title>
    <link rel="stylesheet" href="../css/main.css">
  
    <style>
        .host-container { max-width: 1000px; margin: 30px auto; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .host-container h1 { margin: 0 0 10px; color: #2d3e50; }
        .desc { color: #555; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .grid.full { grid-template-columns: 1fr; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #2d3e50; }
        select, input[type="number"], input[type="text"] { width: 100%; padding: 10px; border: 1px solid #dbe1ea; border-radius: 8px; background: #f9fbff; }
        .chapter-selector { background: #f8fafc; border: 1px solid #dbe1ea; border-radius: 8px; padding: 12px; min-height: 120px; max-height: 200px; overflow-y: auto; }
        .chapter-item { display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 6px; cursor: pointer; transition: background-color 0.2s; }
        .chapter-item:hover { background: #e5e7eb; }
        .chapter-item input[type="checkbox"] { margin: 0; }
        .chapter-item label { margin: 0; cursor: pointer; flex: 1; font-weight: normal; }
        .selector-hint { color: #6b7280; font-style: italic; text-align: center; padding: 40px 20px; }
        .actions { margin-top: 20px; display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn.primary { background: #4f6ef7; color: white; }
        .btn.secondary { background: #e9eef8; color: #2d3e50; }
        .section-title { margin: 8px 0 12px; font-weight: 700; color: #1f2937; }
        .section-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #ffffff; margin-top: 16px; }
        .mcq-list { display: grid; gap: 12px; }
        .mcq-card { background: #f8fafc; border: 1px solid #dbe1ea; border-radius: 8px; padding: 12px; }
        .mcq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .mcq-actions { display: flex; gap: 8px; margin-top: 8px; }
        .hint { font-size: 13px; color: #666; margin-top: 6px; }
        /* Custom-only banner + toggle */
        .custom-only-banner { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid #dbe1ea; border-radius: 12px; background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%); box-shadow: 0 4px 12px rgba(0,0,0,0.04); margin-bottom: 12px; cursor: pointer; }
        .custom-only-banner .title { font-weight: 700; color: #1f2937; }
        .custom-only-banner .subtitle { color: #6b7280; font-size: 13px; margin-top: 2px; }
        .switch { position: relative; display: inline-block; width: 56px; height: 32px; flex: 0 0 auto; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #e5e7eb; transition: 0.3s; border-radius: 9999px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.12); }
        .slider:before { position: absolute; content: ""; height: 24px; width: 24px; left: 4px; top: 4px; background: #fff; transition: 0.3s; border-radius: 50%; box-shadow: 0 1px 2px rgba(0,0,0,0.2); }
        .switch input:checked + .slider { background: #4f6ef7; }
        .switch input:checked + .slider:before { transform: translateX(24px); }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } .mcq-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php include '../header.php'; ?>

<div class="main-content">
  <div class="host-container">
    <h1>Host Online Quiz</h1>
    <p class="desc">Create a room and share the room code with students. You can select MCQs from the database and/or add your own custom MCQs.</p>

    <form id="hostForm" method="POST" action="online_quiz_create_room.php">
      <div class="section-card">
        <h2 class="section-title">Auto-select MCQs (from Books)</h2>
        <div class="grid">
        <div>
          <label for="class_id">Class</label>
          <select id="class_id" name="class_id" required>
            <option value="">Select a class</option>
            <?php
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
          <select id="book_id" name="book_id" required disabled>
            <option value="">Select a book</option>
          </select>
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
          <label for="mcq_count">Number of Random MCQs (from database)</label>
          <input type="number" id="mcq_count" name="mcq_count" min="0" max="100" value="10" required>
          <div class="hint">Set to 0 if you only want to use custom MCQs.</div>
        </div>
      </div>

      </div>

      <div class="section-card">
        <h2 class="section-title">Add Custom MCQs (optional)</h2>
        <div class="custom-only-banner">
          <label class="switch">
            <input type="checkbox" id="customOnly">
            <span class="slider"></span>
          </label>
          <div class="banner-text">
            <div class="title">Create with Custom MCQs only</div>
            <div class="subtitle">No class/book/chapter needed</div>
          </div>
        </div>
        <div class="mcq-action">
          <button type="button" class="btn secondary" id="addMcqBtn">Add MCQ</button>
          <button type="button" class="btn secondary" id="clearMcqBtn">Clear All</button>
        </div>
        <div id="customMcqList" class="mcq-list"></div>
        <input type="hidden" name="custom_mcqs" id="custom_mcqs">
      </div>

      <div class="actions">
        <button type="button" class="btn secondary" id="resetBtn">Reset</button>
        <button type="submit" class="btn primary">Create Room</button>
      </div>
    </form>
  </div>
</div>
<?php include '../footer.php'; ?>

<script>
const classSel = document.getElementById('class_id');
const bookSel = document.getElementById('book_id');
const mcqCountInput = document.getElementById('mcq_count');
const chapterSelector = document.getElementById('chapterSelector');
const chapterIdsInput = document.getElementById('chapter_ids');
const resetBtn = document.getElementById('resetBtn');
const customOnly = document.getElementById('customOnly');
const customOnlyBanner = document.querySelector('.custom-only-banner');
if (customOnlyBanner) {
  customOnlyBanner.addEventListener('click', (e) => {
    if (e.target.closest('.switch')) return; // avoid double toggle when clicking actual switch
    customOnly.checked = !customOnly.checked;
    customOnly.dispatchEvent(new Event('change'));
  });
}

let selectedChapterIds = [];
let customMcqs = [];

function toQuery(params) {
  return Object.entries(params).map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
}

async function loadBooks() {
  bookSel.innerHTML = '<option value="">Loading...</option>';
  bookSel.disabled = true;
  clearChapters();
  const cid = classSel.value;
  if (!cid) { bookSel.innerHTML = '<option value="">Select a book</option>'; return; }
  try {
    const res = await fetch('quiz_data.php?' + toQuery({ type: 'books', class_id: cid }));
    const data = await res.json();
    bookSel.innerHTML = '<option value="">Select a book</option>' + data.map(b => `<option value="${b.book_id}">${b.book_name}</option>`).join('');
    bookSel.disabled = false;
  } catch (e) {
    bookSel.innerHTML = '<option value="">Error loading books</option>';
    console.error(e);
  }
}

async function loadChapters() {
  clearChapters();
  chapterSelector.innerHTML = '<div class="selector-hint">Loading chapters...</div>';
  const cid = classSel.value; 
  const bid = bookSel.value;
  if (!cid || !bid) { chapterSelector.innerHTML = '<div class="selector-hint">Select a book first to see available chapters</div>'; return; }
  try {
    const res = await fetch('quiz_data.php?' + toQuery({ type: 'chapters', class_id: cid, book_id: bid }));
    const data = await res.json();
    if (data.length === 0) { chapterSelector.innerHTML = '<div class="selector-hint">No chapters found for this book</div>'; return; }
    const chapterHTML = data.map(chapter => `
      <div class=\"chapter-item\" onclick=\"toggleChapter(${chapter.chapter_id}, this)\">
        <input type=\"checkbox\" id=\"ch_${chapter.chapter_id}\" value=\"${chapter.chapter_id}\" onchange=\"handleChapterChange(${chapter.chapter_id})\">
        <label for=\"ch_${chapter.chapter_id}\">${chapter.chapter_name}</label>
      </div>
    `).join('');
    chapterSelector.innerHTML = chapterHTML;
  } catch (e) {
    chapterSelector.innerHTML = '<div class="selector-hint">Error loading chapters</div>';
    console.error(e);
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

// Custom MCQ UI
const customMcqList = document.getElementById('customMcqList');
const customMcqsInput = document.getElementById('custom_mcqs');
const addMcqBtn = document.getElementById('addMcqBtn');
const clearMcqBtn = document.getElementById('clearMcqBtn');

function renderCustomMcqs() {
  customMcqList.innerHTML = customMcqs.map((mcq, idx) => `
    <div class=\"mcq-card\">
      <div class=\"mcq-grid\">
        <div>
          <label>Question</label>
          <input type=\"text\" value=\"${mcq.question || ''}\" oninput=\"updateMcq(${idx}, 'question', this.value)\" placeholder=\"Enter question\" />
        </div>
        <div>
          <label>Correct Option</label>
          <select onchange=\"updateMcq(${idx}, 'correct', this.value)\">
            <option value=\"A\" ${mcq.correct === 'A' ? 'selected' : ''}>A</option>
            <option value=\"B\" ${mcq.correct === 'B' ? 'selected' : ''}>B</option>
            <option value=\"C\" ${mcq.correct === 'C' ? 'selected' : ''}>C</option>
            <option value=\"D\" ${mcq.correct === 'D' ? 'selected' : ''}>D</option>
          </select>
        </div>
        <div>
          <label>Option A</label>
          <input type=\"text\" value=\"${mcq.option_a || ''}\" oninput=\"updateMcq(${idx}, 'option_a', this.value)\" placeholder=\"Option A\" />
        </div>
        <div>
          <label>Option B</label>
          <input type=\"text\" value=\"${mcq.option_b || ''}\" oninput=\"updateMcq(${idx}, 'option_b', this.value)\" placeholder=\"Option B\" />
        </div>
        <div>
          <label>Option C</label>
          <input type=\"text\" value=\"${mcq.option_c || ''}\" oninput=\"updateMcq(${idx}, 'option_c', this.value)\" placeholder=\"Option C\" />
        </div>
        <div>
          <label>Option D</label>
          <input type=\"text\" value=\"${mcq.option_d || ''}\" oninput=\"updateMcq(${idx}, 'option_d', this.value)\" placeholder=\"Option D\" />
        </div>
      </div>
      <div class=\"mcq-actions\">
        <button class=\"btn secondary\" type=\"button\" onclick=\"removeMcq(${idx})\">Remove</button>
      </div>
    </div>
  `).join('');
  syncCustomMcqsInput();
}

function updateMcq(idx, key, value) {
  customMcqs[idx][key] = value;
  syncCustomMcqsInput();
}

function removeMcq(idx) {
  customMcqs.splice(idx, 1);
  renderCustomMcqs();
}

function syncCustomMcqsInput() {
  customMcqsInput.value = JSON.stringify(customMcqs);
}

addMcqBtn.addEventListener('click', () => {
  customMcqs.push({ question: '', option_a: '', option_b: '', option_c: '', option_d: '', correct: 'A' });
  renderCustomMcqs();
});

clearMcqBtn.addEventListener('click', () => {
  if (confirm('Clear all custom MCQs?')) {
    customMcqs = [];
    renderCustomMcqs();
  }
});

// Reset button
resetBtn.addEventListener('click', () => {
  const form = document.getElementById('hostForm');
  form.reset();
  bookSel.innerHTML = '<option value="">Select a book</option>';
  bookSel.disabled = true;
  clearChapters();
  customMcqs = [];
  renderCustomMcqs();
  setCustomOnlyMode(false);
});

function setCustomOnlyMode(on) {
  if (on) {
    classSel.required = false;
    bookSel.required = false;
    mcqCountInput.required = false;
    classSel.disabled = true;
    bookSel.disabled = true;
    mcqCountInput.disabled = true;
    // Clear dependent selections
    bookSel.innerHTML = '<option value="">Select a book</option>';
    clearChapters();
  } else {
    classSel.disabled = false;
    bookSel.disabled = true; // stays disabled until class selected
    mcqCountInput.disabled = false;
    classSel.required = true;
    bookSel.required = true;
    mcqCountInput.required = true;
  }
}

customOnly.addEventListener('change', (e) => setCustomOnlyMode(e.target.checked));

// Keep custom MCQs synced on submit
const hostForm = document.getElementById('hostForm');
hostForm.addEventListener('submit', (ev) => {
  syncCustomMcqsInput();
  if (customOnly.checked) {
    mcqCountInput.value = 0; // force zero random mcqs
    // Validate at least one custom MCQ and fields are filled
    const hasValid = customMcqs.some(m => (m.question||'').trim() && (m.option_a||'').trim() && (m.option_b||'').trim() && (m.option_c||'').trim() && (m.option_d||'').trim());
    if (!hasValid) {
      ev.preventDefault();
      alert('Please add at least one complete Custom MCQ to create a room.');
      return;
    }
  }
});

// Events
classSel.addEventListener('change', loadBooks);
bookSel.addEventListener('change', loadChapters);

// Initialize UI
setCustomOnlyMode(false);
</script>
</body>
</html>
