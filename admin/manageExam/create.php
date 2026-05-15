<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

include_once __DIR__ . '/../header.php';

// Fetch classes
$classes = $conn->query("SELECT * FROM class ORDER BY class_id ASC");

// Fetch books for filtering
$books = $conn->query("SELECT * FROM book ORDER BY book_name ASC");
$bookOptions = [];
while ($b = $books->fetch_assoc()) {
    $bookOptions[] = $b;
}
?>

<style>
    .chapter-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        border-bottom: 1px solid #eee;
        transition: background 0.2s;
    }
    .chapter-item:hover {
        background: #f1f3f5;
    }
    .chapter-item.active {
        background: #e7f5ff;
        border-left: 4px solid #228be6;
    }
    .questions-container {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        background: white;
        min-height: 400px;
        max-height: 600px;
        overflow-y: auto;
    }
    .question-category-title {
        background: #f8f9fa;
        padding: 8px 12px;
        font-weight: bold;
        border-radius: 4px;
        margin-top: 15px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .question-list-item {
        padding: 6px 10px;
        border-bottom: 1px solid #f1f1f1;
        font-size: 0.9rem;
    }
    .question-list-item:last-child {
        border-bottom: none;
    }
    .badge-count {
        font-size: 0.75rem;
        background: #e9ecef;
        padding: 2px 8px;
        border-radius: 10px;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🎓 Create Exam Preparation</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <form action="save_test.php" method="POST" id="exam-prep-form">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">1. Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Exam Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Midterm Test - Chapter 1 & 2" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class</label>
                                <select name="class_id" id="class_id" class="form-select" required>
                                    <option value="">Select Class</option>
                                    <?php while ($c = $classes->fetch_assoc()): ?>
                                        <option value="<?= $c['class_id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Book</label>
                                <select name="book_id" id="book_id" class="form-select" required disabled>
                                    <option value="">Select Book</option>
                                    <?php foreach ($bookOptions as $bk): ?>
                                        <option value="<?= $bk['book_id'] ?>" data-class="<?= $bk['class_id'] ?>"><?= htmlspecialchars($bk['book_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-5">
                                <label class="form-label">Chapters</label>
                                <div id="chapter-list" class="border rounded" style="max-height: 500px; overflow-y: auto; background: #f8f9fa;">
                                    <p class="text-muted p-3">Select a book first...</p>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">Questions Selection</label>
                                </div>
                                <div id="questions-display" class="questions-container">
                                    <p class="text-muted text-center mt-5">Select a chapter to see questions</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">2. Global Selection Settings (Optional)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Default Mode</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="selection_type" id="type_random" value="random" checked>
                                    <label class="btn btn-outline-primary" for="type_random">System Random</label>
                                    
                                    <input type="radio" class="btn-check" name="selection_type" id="type_manual" value="manual">
                                    <label class="btn btn-outline-primary" for="type_manual">Manual Selection</label>
                                </div>
                            </div>
                        </div>

                        <div id="random-settings">
                            <p class="text-muted small mb-3">These settings apply if no manual questions are selected or if "System Random" is active.</p>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">No. of MCQs</label>
                                    <input type="number" name="mcq_count" class="form-control" value="10" min="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">No. of Short Qs</label>
                                    <input type="number" name="short_count" class="form-control" value="5" min="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">No. of Long Qs</label>
                                    <input type="number" name="long_count" class="form-control" value="2" min="0">
                                </div>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-info w-100" id="btn-global-auto-select">
                                    <i class="fas fa-magic"></i> Auto Select Questions from Selected Chapters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow position-sticky" style="top: 90px;">
                    <div class="card-body">
                        <h5>Summary</h5>
                        <hr>
                        <div id="summary-content">
                            <p><strong>Class:</strong> <span id="sum-class">-</span></p>
                            <p><strong>Book:</strong> <span id="sum-book">-</span></p>
                            <p><strong>Chapters:</strong> <span id="sum-chapters">0 selected</span></p>
                            <hr>
                            <h6>Selected Questions:</h6>
                            <p>MCQs: <span id="sum-mcqs">0</span></p>
                            <p>Short: <span id="sum-short">0</span></p>
                            <p>Long: <span id="sum-long">0</span></p>
                            <p><strong>Total:</strong> <span id="sum-total">0</span></p>
                        </div>
                        <button type="submit" class="btn btn-success w-100 py-2 mt-3">
                            <i class="fas fa-save"></i> Save Exam
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const classSel = document.getElementById('class_id');
    const bookSel = document.getElementById('book_id');
    const chapterList = document.getElementById('chapter-list');
    const questionsDisplay = document.getElementById('questions-display');
    const selectionType = document.getElementsByName('selection_type');
    
    const sumClass = document.getElementById('sum-class');
    const sumBook = document.getElementById('sum-book');
    const sumChapters = document.getElementById('sum-chapters');
    const sumMcqs = document.getElementById('sum-mcqs');
    const sumShort = document.getElementById('sum-short');
    const sumLong = document.getElementById('sum-long');
    const sumTotal = document.getElementById('sum-total');

    let currentChapterId = null;
    let selectedQuestionIds = new Set();
    const chapterQuestionsCache = {};
    const fetchingChapters = new Set();

    // Filter books by class
    classSel.addEventListener('change', function() {
        const clsId = this.value;
        sumClass.textContent = this.options[this.selectedIndex].text;
        
        bookSel.disabled = !clsId;
        bookSel.value = '';
        chapterList.innerHTML = '<p class="text-muted p-3">Select a book first...</p>';
        questionsDisplay.innerHTML = '<p class="text-muted text-center mt-5">Select a chapter to see questions</p>';
        selectedQuestionIds.clear();
        Object.keys(chapterQuestionsCache).forEach(key => delete chapterQuestionsCache[key]);
        fetchingChapters.clear();
        updateSummary();
        
        Array.from(bookSel.options).forEach(opt => {
            if (!opt.value) return;
            opt.hidden = opt.getAttribute('data-class') !== clsId;
        });
    });

    // Fetch chapters for selected book
    bookSel.addEventListener('change', async function() {
        const bookId = this.value;
        const clsId = classSel.value;
        const bookName = this.options[this.selectedIndex].text;
        sumBook.textContent = bookName;
        
        questionsDisplay.innerHTML = '<p class="text-muted text-center mt-5">Select a chapter to see questions</p>';
        selectedQuestionIds.clear();
        Object.keys(chapterQuestionsCache).forEach(key => delete chapterQuestionsCache[key]);
        fetchingChapters.clear();
        updateSummary();

        if (!bookId || !clsId) {
            chapterList.innerHTML = '<p class="text-muted p-3">Select a book first...</p>';
            return;
        }

        chapterList.innerHTML = '<div class="text-center p-3"><div class="spinner-border spinner-border-sm" role="status"></div> Loading...</div>';
        
        try {
            const response = await fetch(`ajax_get_chapters.php?class_id=${clsId}&book_id=${bookId}`);
            const bookChapters = await response.json();
            
            if (bookChapters.error) {
                chapterList.innerHTML = `<p class="text-danger p-3">${bookChapters.error}</p>`;
                return;
            }

            if (bookChapters.length === 0) {
                chapterList.innerHTML = '<p class="text-danger p-3">No chapters found for this book.</p>';
            } else {
                chapterList.innerHTML = '';
                bookChapters.forEach(ch => {
                    const item = document.createElement('div');
                    item.className = 'chapter-item';
                    item.dataset.id = ch.chapter_id;
                    item.innerHTML = `
                        <div class="d-flex align-items-center">
                            <input class="form-check-input chapter-checkbox me-2" type="checkbox" name="chapter_ids[]" value="${ch.chapter_id}" id="ch_${ch.chapter_id}">
                            <label class="form-check-label small" for="ch_${ch.chapter_id}">
                                Ch ${ch.chapter_no}: ${ch.chapter_name}
                            </label>
                        </div>
                    `;
                    chapterList.appendChild(item);
                });
                
                attachChapterListeners();
            }
        } catch (error) {
            chapterList.innerHTML = '<p class="text-danger p-3">Error loading chapters.</p>';
        }
    });

    function attachChapterListeners() {
        document.querySelectorAll('.chapter-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                updateChapterSummary();
                refreshQuestionsDisplay();
            });
        });
    }

    async function refreshQuestionsDisplay() {
        const selectedChapters = Array.from(document.querySelectorAll('.chapter-checkbox:checked')).map(cb => ({
            id: cb.value,
            name: cb.closest('.chapter-item').querySelector('label').textContent.trim()
        }));

        if (selectedChapters.length === 0) {
            questionsDisplay.innerHTML = '<p class="text-muted text-center mt-5">Select a chapter to see questions</p>';
            return;
        }

        questionsDisplay.innerHTML = '<div class="text-center mt-5"><div class="spinner-border" role="status"></div><p class="mt-2">Loading questions...</p></div>';

        // Fetch questions for all selected chapters that aren't cached
        for (const ch of selectedChapters) {
            if (!chapterQuestionsCache[ch.id] && !fetchingChapters.has(ch.id)) {
                fetchingChapters.add(ch.id);
                try {
                    const response = await fetch(`ajax_get_chapter_questions.php?chapter_id=${ch.id}&book_id=${bookSel.value}&class_id=${classSel.value}`);
                    const data = await response.json();
                    chapterQuestionsCache[ch.id] = data;
                } catch (error) {
                    console.error(`Error loading chapter ${ch.id}:`, error);
                } finally {
                    fetchingChapters.delete(ch.id);
                }
            }
        }

        renderAllQuestions(selectedChapters);
    }

    function renderAllQuestions(selectedChapters) {
        questionsDisplay.innerHTML = '';
        
        selectedChapters.forEach(ch => {
            const data = chapterQuestionsCache[ch.id];
            if (!data) return;

            const chapterSection = document.createElement('div');
            chapterSection.className = 'chapter-questions-block mb-4';
            chapterSection.innerHTML = `<h6 class="border-bottom pb-2 text-primary"><i class="fas fa-book-open me-2"></i>${ch.name}</h6>`;

            const categories = [
                { key: 'mcqs', label: 'MCQs', icon: 'check-square' },
                { key: 'short', label: 'Short Questions', icon: 'align-left' },
                { key: 'long', label: 'Long Questions', icon: 'file-alt' }
            ];

            categories.forEach(cat => {
                const section = document.createElement('div');
                section.className = 'question-category-section mb-2';
                
                const title = document.createElement('div');
                title.className = 'question-category-title py-1 px-2';
                title.style.fontSize = '0.85rem';
                title.innerHTML = `
                    <span><i class="fas fa-${cat.icon} me-1"></i>${cat.label}</span>
                    <span class="badge-count">${data[cat.key].length}</span>
                `;
                section.appendChild(title);

                if (data[cat.key].length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'text-muted small ps-3 mb-1';
                    empty.textContent = 'No questions.';
                    section.appendChild(empty);
                } else {
                    data[cat.key].forEach(q => {
                        const item = document.createElement('div');
                        item.className = 'question-list-item';
                        const isChecked = selectedQuestionIds.has(q.id);
                        item.innerHTML = `
                            <div class="form-check">
                                <input class="form-check-input q-checkbox" type="checkbox" value="${q.id}" id="q_${q.id}" ${isChecked ? 'checked' : ''}>
                                <label class="form-check-label small" for="q_${q.id}">
                                    ${q.text}
                                </label>
                            </div>
                        `;
                        section.appendChild(item);
                    });
                }
                chapterSection.appendChild(section);
            });
            questionsDisplay.appendChild(chapterSection);
        });

        // Attach listeners to question checkboxes
        document.querySelectorAll('.q-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                if (this.checked) {
                    selectedQuestionIds.add(this.value);
                    document.getElementById('type_manual').checked = true;
                } else {
                    selectedQuestionIds.delete(this.value);
                }
                updateSummary();
            });
        });
    }

    // Global Auto Select Logic
    document.getElementById('btn-global-auto-select').addEventListener('click', async function() {
        const mcqTotal = parseInt(document.getElementsByName('mcq_count')[0].value) || 0;
        const shortTotal = parseInt(document.getElementsByName('short_count')[0].value) || 0;
        const longTotal = parseInt(document.getElementsByName('long_count')[0].value) || 0;
        
        const selectedChapterIds = Array.from(document.querySelectorAll('.chapter-checkbox:checked')).map(cb => cb.value);
        if (selectedChapterIds.length === 0) {
            alert("Please select at least one chapter first.");
            return;
        }

        const btn = this;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Selecting...';

        // Clear existing selections before auto-selecting new ones
        selectedQuestionIds.clear();

        // Collect all available questions from selected chapters
        let allMcqs = [], allShort = [], allLong = [];
        
        for (const chId of selectedChapterIds) {
            if (chapterQuestionsCache[chId]) {
                allMcqs.push(...chapterQuestionsCache[chId].mcqs);
                allShort.push(...chapterQuestionsCache[chId].short);
                allLong.push(...chapterQuestionsCache[chId].long);
            }
        }

        // Randomly select from the pools
        autoSelectFromPool(allMcqs, mcqTotal);
        autoSelectFromPool(allShort, shortTotal);
        autoSelectFromPool(allLong, longTotal);

        refreshQuestionsDisplay();
        updateSummary();
        
        document.getElementById('type_manual').checked = true;
        
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Selection Applied';
        btn.classList.replace('btn-info', 'btn-success');
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.replace('btn-success', 'btn-info');
        }, 2000);
    });

    function autoSelectFromPool(pool, count) {
        const available = [...pool];
        const toSelect = Math.min(count, available.length);
        for (let i = 0; i < toSelect; i++) {
            const randomIndex = Math.floor(Math.random() * available.length);
            const q = available.splice(randomIndex, 1)[0];
            selectedQuestionIds.add(q.id);
        }
    }

    function updateChapterSummary() {
        const selected = document.querySelectorAll('.chapter-checkbox:checked').length;
        sumChapters.textContent = selected + ' selected';
    }

    function updateSummary() {
        let mcqs = 0, short = 0, long = 0;
        
        selectedQuestionIds.forEach(id => {
            if (id.startsWith('mcq_')) mcqs++;
            else if (id.startsWith('q_')) {
                // We need to know the type. Let's find it in cache.
                for (const chId in chapterQuestionsCache) {
                    const qShort = chapterQuestionsCache[chId].short.find(q => q.id === id);
                    if (qShort) { short++; break; }
                    const qLong = chapterQuestionsCache[chId].long.find(q => q.id === id);
                    if (qLong) { long++; break; }
                }
            }
        });

        sumMcqs.textContent = mcqs;
        sumShort.textContent = short;
        sumLong.textContent = long;
        sumTotal.textContent = selectedQuestionIds.size;
    }

    // Add hidden inputs for selected question IDs before form submission
    document.getElementById('exam-prep-form').addEventListener('submit', function(e) {
        // Remove existing question_ids hidden inputs
        document.querySelectorAll('input[name="question_ids[]"]').forEach(el => el.remove());
        
        selectedQuestionIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'question_ids[]';
            input.value = id;
            this.appendChild(input);
        });
    });
});
</script>


