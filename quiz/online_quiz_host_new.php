<?php
// online_quiz_host_new.php - Professional quiz room creation
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
    
    <title>Create Quiz Room - Ahmad Learning Hub</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/online_quiz_host_new.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   
     <?php include '../header.php'; ?>
</head>

<body>
    <div class="quiz-creator">
        <div class="creator-header">
            <h1>🚀 Create Quiz Room</h1>
            <p class="subtitle">Welcome back, <?= htmlspecialchars($user_name) ?>! Let's create an amazing quiz experience.</p>
        </div>
        
        <div class="creator-body">
            <form id="quizForm" method="POST" action="online_quiz_create_room.php">
                <!-- Quiz Configuration -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">⚙️</div>
                        <div>
                            <h2 class="section-title">Quiz Configuration</h2>
                            <p class="section-description">Set up basic quiz parameters and timing</p>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">📚 Class</label>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <option value="">Choose your class</option>
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
                        
                        <div class="form-group">
                            <label class="form-label">📖 Book</label>
                            <select class="form-select" id="book_id" name="book_id" required disabled>
                                <option value="">Select class first</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">🎯 Number of Questions</label>
                            <input type="number" class="form-input" id="mcq_count" name="mcq_count" min="1" max="50" value="10" required>
                            <div class="form-hint">Recommended: 10-20 questions for optimal experience</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">⏱️ Quiz Duration</label>
                            <input type="number" class="form-input" id="quiz_duration" name="quiz_duration_minutes" min="1" max="120" value="10" required>
                            <div class="form-hint">Duration in minutes</div>
                            
                            <div class="time-grid">
                                <div class="time-preset" data-time="3">3 min</div>
                                <div class="time-preset" data-time="5">5 min</div>
                                <div class="time-preset active" data-time="10">10 min</div>
                                <div class="time-preset" data-time="15">15 min</div>
                                <div class="time-preset" data-time="20">20 min</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Question Selection -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">❓</div>
                        <div>
                            <h2 class="section-title">Question Selection</h2>
                            <p class="section-description">Choose which topics and chapters to include</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">📑 Chapters (Optional)</label>
                        <div id="chapterSelector" class="chapter-selector">
                            <div class="selector-hint">Select a book to view available chapters</div>
                        </div>
                        <input type="hidden" name="chapter_ids" id="chapter_ids">
                        <div class="form-hint">Leave empty to include all chapters from selected book</div>
                    </div>
                </div>

                <!-- Custom Questions -->
                <div class="toggle-section">
                    <div class="toggle-header" onclick="toggleCustomQuestions()">
                        <div class="toggle-switch" id="customToggle">
                            <div class="toggle-knob"></div>
                        </div>
                        <div>
                            <h3 style="margin: 0; font-size: 1.25rem;">✨ Add Custom Questions</h3>
                            <p style="margin: 5px 0 0; color: #6b7280;">Create your own questions for this quiz</p>
                        </div>
                    </div>
                    
                    <div class="mcq-builder" id="customQuestions">
                        <div style="margin-bottom: 20px;">
                            <button type="button" class="btn btn-secondary" onclick="addCustomQuestion()">
                                ➕ Add Question
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearCustomQuestions()">
                                🗑️ Clear All
                            </button>
                        </div>
                        
                        <div id="customQuestionsList"></div>
                        <input type="hidden" name="custom_mcqs" id="custom_mcqs">
                    </div>
                </div>

                <!-- Quiz Preview -->
                <div class="preview-section">
                    <h3 style="margin: 0 0 20px; color: #0369a1;">📊 Quiz Preview</h3>
                    <div class="preview-stats">
                        <div class="stat-card">
                            <div class="stat-number" id="totalQuestions">10</div>
                            <div class="stat-label">Total Questions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="estimatedTime">30</div>
                            <div class="stat-label">Minutes</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="customCount">0</div>
                            <div class="stat-label">Custom Questions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="selectedChapters">All</div>
                            <div class="stat-label">Chapters</div>
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="action-bar">
                    <button  type="button" class="btn " onclick="resetForm()">
                        🔄 Reset Form
                    </button>
                    <button type="submit" class="btn btn-primary">
                        🚀 Create Quiz Room
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let customQuestions = [];
        let selectedChapterIds = [];
        
        // Time preset selection
        document.querySelectorAll('.time-preset').forEach(preset => {
            preset.addEventListener('click', function() {
                document.querySelectorAll('.time-preset').forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('quiz_duration').value = this.dataset.time;
                updatePreview();
            });
        });
        
        // Custom questions toggle
        function toggleCustomQuestions() {
            const toggle = document.getElementById('customToggle');
            const section = document.getElementById('customQuestions');
            
            toggle.classList.toggle('active');
            section.classList.toggle('active');
        }
        
        // Load books based on class selection
        document.getElementById('class_id').addEventListener('change', async function() {
            const bookSelect = document.getElementById('book_id');
            const classId = this.value;
            
            if (!classId) {
                bookSelect.innerHTML = '<option value="">Select class first</option>';
                bookSelect.disabled = true;
                clearChapters();
                return;
            }
            
            bookSelect.innerHTML = '<option value="">Loading books...</option>';
            
            try {
                const response = await fetch(`quiz_data.php?type=books&class_id=${classId}`);
                const books = await response.json();
                
                bookSelect.innerHTML = '<option value="">Choose a book</option>' + 
                    books.map(book => `<option value="${book.book_id}">${book.book_name}</option>`).join('');
                bookSelect.disabled = false;
            } catch (error) {
                bookSelect.innerHTML = '<option value="">Error loading books</option>';
                console.error('Error loading books:', error);
            }
        });
        
        // Load chapters based on book selection
        document.getElementById('book_id').addEventListener('change', async function() {
            const chapterSelector = document.getElementById('chapterSelector');
            const classId = document.getElementById('class_id').value;
            const bookId = this.value;
            
            if (!classId || !bookId) {
                clearChapters();
                return;
            }
            
            chapterSelector.innerHTML = '<div class="selector-hint">Loading chapters...</div>';
            
            try {
                const response = await fetch(`quiz_data.php?type=chapters&class_id=${classId}&book_id=${bookId}`);
                const chapters = await response.json();
                
                if (chapters.length === 0) {
                    chapterSelector.innerHTML = '<div class="selector-hint">No chapters available</div>';
                    return;
                }
                
                chapterSelector.innerHTML = chapters.map(chapter => `
                    <div class="chapter-item" style="padding: 8px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 10px;" 
                         onclick="toggleChapter(${chapter.chapter_id}, this)">
                        <input type="checkbox" id="ch_${chapter.chapter_id}" value="${chapter.chapter_id}" onchange="handleChapterChange(${chapter.chapter_id})">
                        <label for="ch_${chapter.chapter_id}" style="cursor: pointer; flex: 1;">${chapter.chapter_name}</label>
                    </div>
                `).join('');
            } catch (error) {
                chapterSelector.innerHTML = '<div class="selector-hint">Error loading chapters</div>';
                console.error('Error loading chapters:', error);
            }
        });
        
        function clearChapters() {
            selectedChapterIds = [];
            document.getElementById('chapter_ids').value = '';
            document.getElementById('chapterSelector').innerHTML = '<div class="selector-hint">Select a book to view available chapters</div>';
            updatePreview();
        }
        
        function toggleChapter(chapterId, element) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            handleChapterChange(chapterId);
        }
        
        function handleChapterChange(chapterId) {
            if (selectedChapterIds.includes(chapterId)) {
                selectedChapterIds = selectedChapterIds.filter(id => id !== chapterId);
            } else {
                selectedChapterIds.push(chapterId);
            }
            
            document.getElementById('chapter_ids').value = selectedChapterIds.join(',');
            updatePreview();
        }
        
        function addCustomQuestion() {
            customQuestions.push({
                question: '',
                option_a: '',
                option_b: '',
                option_c: '',
                option_d: '',
                correct: 'A'
            });
            renderCustomQuestions();
        }
        
        function removeCustomQuestion(index) {
            customQuestions.splice(index, 1);
            renderCustomQuestions();
        }
        
        function clearCustomQuestions() {
            if (confirm('Remove all custom questions?')) {
                customQuestions = [];
                renderCustomQuestions();
            }
        }
        
        function renderCustomQuestions() {
            const container = document.getElementById('customQuestionsList');
            
            container.innerHTML = customQuestions.map((q, index) => `
                <div class="mcq-card">
                    <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 15px;">
                        <h4 style="margin: 0; color: #374151;">Question ${index + 1}</h4>
                        <button type="button" class="btn btn-secondary" onclick="removeCustomQuestion(${index})" style="padding: 6px 12px; font-size: 0.8rem;">
                            🗑️ Remove
                        </button>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label">Question Text</label>
                        <input type="text" class="form-input" value="${q.question}" 
                               onchange="updateCustomQuestion(${index}, 'question', this.value)"
                               placeholder="Enter your question">
                    </div>
                    
                    <div class="mcq-options">
                        <div class="form-group">
                            <label class="form-label">Option A</label>
                            <input type="text" class="form-input" value="${q.option_a}"
                                   onchange="updateCustomQuestion(${index}, 'option_a', this.value)"
                                   placeholder="Option A">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Option B</label>
                            <input type="text" class="form-input" value="${q.option_b}"
                                   onchange="updateCustomQuestion(${index}, 'option_b', this.value)"
                                   placeholder="Option B">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Option C</label>
                            <input type="text" class="form-input" value="${q.option_c}"
                                   onchange="updateCustomQuestion(${index}, 'option_c', this.value)"
                                   placeholder="Option C">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Option D</label>
                            <input type="text" class="form-input" value="${q.option_d}"
                                   onchange="updateCustomQuestion(${index}, 'option_d', this.value)"
                                   placeholder="Option D">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">Correct Answer</label>
                        <select class="form-select" onchange="updateCustomQuestion(${index}, 'correct', this.value)">
                            <option value="A" ${q.correct === 'A' ? 'selected' : ''}>A</option>
                            <option value="B" ${q.correct === 'B' ? 'selected' : ''}>B</option>
                            <option value="C" ${q.correct === 'C' ? 'selected' : ''}>C</option>
                            <option value="D" ${q.correct === 'D' ? 'selected' : ''}>D</option>
                        </select>
                    </div>
                </div>
            `).join('');
            
            document.getElementById('custom_mcqs').value = JSON.stringify(customQuestions);
            updatePreview();
        }
        
        function updateCustomQuestion(index, field, value) {
            customQuestions[index][field] = value;
            document.getElementById('custom_mcqs').value = JSON.stringify(customQuestions);
            updatePreview();
        }
        
        function updatePreview() {
            const mcqCount = parseInt(document.getElementById('mcq_count').value) || 0;
            const customCount = customQuestions.length;
            const duration = parseInt(document.getElementById('quiz_duration').value) || 0;
            const chapterCount = selectedChapterIds.length;
            
            document.getElementById('totalQuestions').textContent = mcqCount + customCount;
            document.getElementById('estimatedTime').textContent = duration;
            document.getElementById('customCount').textContent = customCount;
            document.getElementById('selectedChapters').textContent = chapterCount === 0 ? 'All' : chapterCount;
        }
        
        function resetForm() {
            if (confirm('Reset all form data?')) {
                document.getElementById('quizForm').reset();
                customQuestions = [];
                selectedChapterIds = [];
                document.getElementById('book_id').disabled = true;
                clearChapters();
                renderCustomQuestions();
                updatePreview();
                
                // Reset time preset
                document.querySelectorAll('.time-preset').forEach(p => p.classList.remove('active'));
                document.querySelector('.time-preset[data-time="30"]').classList.add('active');
            }
        }
        
        // Form submission
        document.getElementById('quizForm').addEventListener('submit', function(e) {
            const mcqCount = parseInt(document.getElementById('mcq_count').value) || 0;
            const customCount = customQuestions.length;
            
            if (mcqCount === 0 && customCount === 0) {
                e.preventDefault();
                alert('Please add at least one question to create a quiz room.');
                return;
            }
            
            // Validate custom questions
            if (customCount > 0) {
                const invalidQuestions = customQuestions.filter(q => 
                    !q.question.trim() || !q.option_a.trim() || !q.option_b.trim() || 
                    !q.option_c.trim() || !q.option_d.trim()
                );
                
                if (invalidQuestions.length > 0) {
                    e.preventDefault();
                    alert('Please complete all custom questions before submitting.');
                    return;
                }
            }
        });
        
        // Update preview on input changes
        document.getElementById('mcq_count').addEventListener('input', updatePreview);
        document.getElementById('quiz_duration').addEventListener('input', updatePreview);
        
        // Initial preview update
        updatePreview();
    </script>
</body>
</html>
