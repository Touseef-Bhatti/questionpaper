<?php
// online_quiz_host_new.php - Professional quiz room creation
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

// Handle clearing of selected topics
if (isset($_GET['clear_topics'])) {
    unset($_SESSION['host_quiz_topics']);
    header("Location: online_quiz_host_new.php");
    exit;
}

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
        
        <?php
        $selectedTopics = $_SESSION['host_quiz_topics'] ?? [];
        $hasTopics = !empty($selectedTopics);
        $urlMcqCount = $_GET['mcq_count'] ?? 10;
        $urlDuration = $_GET['duration'] ?? 10;
        ?>

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
                        <div class="form-group" style="grid-column: 1 / -1;">
                             <button type="button" class="btn btn-secondary" style="width: 100%; border: 2px dashed #6366f1; color: #6366f1; background: #f5f3ff; font-weight: 600;" onclick="goToTopicSearch()">
                                🔍 Search Questions by Topic
                            </button>
                            <div class="form-hint" style="text-align: center; margin-top: 8px;">Instead of selecting class/book, search for specific topics</div>
                            
                            <?php if ($hasTopics): ?>
                            <div id="selectedTopicsContainer" style="margin-top: 15px; padding: 15px; background: #f0fdf4; border: 1px solid #16a34a; border-radius: 8px;">
                                <h4 style="margin: 0 0 10px; color: #15803d; display: flex; justify-content: space-between; align-items: center;">
                                    <span>✅ Selected Topics (<?= count($selectedTopics) ?>)</span>
                                    <button type="button" onclick="clearSelectedTopics()" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 0.9em; text-decoration: underline;">Clear</button>
                                </h4>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    <?php foreach ($selectedTopics as $topic): ?>
                                        <span style="background: white; padding: 4px 10px; border-radius: 15px; border: 1px solid #bbf7d0; font-size: 0.9em; color: #166534;">
                                            <?= htmlspecialchars($topic) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="topics" value="<?= htmlspecialchars(json_encode($selectedTopics)) ?>">
                                
                                <div style="margin-top: 15px; border-top: 1px dashed #bbf7d0; padding-top: 10px;">
                                    <button type="button" class="btn btn-sm" onclick="previewQuestions()" style="width: 100%; background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd;">
                                        👁️ View Available Questions
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$hasTopics): ?>
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
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="form-label">🎯 Number of Questions</label>
                            <input type="number" class="form-input" id="mcq_count" name="mcq_count" min="1" max="50" value="<?= htmlspecialchars($urlMcqCount) ?>" required>
                            <div class="form-hint">Recommended: 10-20 questions for optimal experience</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">⏱️ Quiz Duration</label>
                            <input type="number" class="form-input" id="quiz_duration" name="quiz_duration_minutes" min="1" max="120" value="<?= htmlspecialchars($urlDuration) ?>" required>
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
                <?php if (!$hasTopics): ?>
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
                <?php endif; ?>

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
                            <button type="button" class="btn btn-secondary" style="color: black;border: 1px solid black;" onclick="addCustomQuestion()">
                                ➕ Add Question
                            </button>
                            <button type="button" class="btn btn-secondary" style="color: black;border: 1px solid black;" onclick="clearCustomQuestions()">
                                🗑️ Clear All
                            </button>
                        </div>
                        
                        <div id="customQuestionsList"></div>
                        <input type="hidden" name="custom_mcqs" id="custom_mcqs">
                    </div>
                </div>

                <!-- Quiz Preview -->
                <div class="preview-section">
                    <h3 style="margin: 0 0 20px; color: #0369a1; display: flex; justify-content: space-between; align-items: center;">
                        <span>📊 Quiz Preview</span>
                        <?php if (!$hasTopics): ?>
                        <button type="button" class="btn btn-sm" onclick="previewQuestions()" style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd;">
                            👁️ View Available Questions
                        </button>
                        <?php endif; ?>
                    </h3>
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

    <!-- Question Preview Modal -->
    <div id="previewModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 0; border: 1px solid #888; width: 80%; max-width: 800px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
            <div class="modal-header" style="padding: 15px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; background: #f9fafb; border-radius: 12px 12px 0 0;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <h3 style="margin: 0; color: #111827;">Available Questions Preview</h3>
                    <button id="regenerateBtn" type="button" class="btn btn-sm" onclick="regenerateQuestions()" style="display: none; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; font-size: 0.85em; padding: 6px 12px; align-items: center; gap: 5px;">
                        🔄 Generate More 
                    </button>
                </div>
                <span class="close" onclick="closePreviewModal()" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            </div>
            <div class="modal-body" style="padding: 20px; max-height: 70vh; overflow-y: auto;">
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9em; display: flex; align-items: center; gap: 8px;">
                    <span>ℹ️</span>
                    <span><strong>Note:</strong> Correct answers are highlighted in <strong style="color: #16a34a;">green</strong>. Use the dropdown menu to change the correct answer key if needed.</span>
                </div>
                <div id="previewLoading" style="text-align: center; padding: 20px;">
                    <div class="spinner" style="width: 40px; height: 40px; border: 4px solid #e5e7eb; border-top: 4px solid #6366f1; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 10px;"></div>
                    <p id="previewLoadingText">Loading questions...</p>
                </div>
                <div id="previewContent"></div>
            </div>
            <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid #e5e7eb; text-align: right; background: #f9fafb; border-radius: 0 0 12px 12px;">
                <span id="previewCount" style="float: left; color: #6b7280; font-size: 0.9em; padding-top: 8px;"></span>
                <button type="button" class="btn btn-secondary" onclick="closePreviewModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function closePreviewModal() {
            document.getElementById('previewModal').style.display = 'none';
        }

        function previewQuestions() {
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewContent');
            const loading = document.getElementById('previewLoading');
            const loadingText = document.getElementById('previewLoadingText');
            const countSpan = document.getElementById('previewCount');
            const regenerateBtn = document.getElementById('regenerateBtn');
            
            modal.style.display = 'block';
            content.innerHTML = '';
            loading.style.display = 'block';
            loadingText.textContent = 'Loading questions...';
            countSpan.textContent = '';
            
            // Collect form data
            const formData = new FormData();
            
            // Check for topics
            const topicsInput = document.querySelector('input[name="topics"]');
            if (topicsInput) {
                formData.append('topics', topicsInput.value);
                if (regenerateBtn) regenerateBtn.style.display = 'flex';
            } else {
                if (regenerateBtn) regenerateBtn.style.display = 'none';
                // Check for class/book
                const classId = document.getElementById('class_id').value;
                const bookId = document.getElementById('class_id').value ? document.getElementById('book_id').value : '';
                const chapterIds = document.getElementById('chapter_ids').value;
                
                formData.append('class_id', classId);
                formData.append('book_id', bookId);
                formData.append('chapter_ids', chapterIds);
                
                if (!classId || (!topicsInput && !bookId)) {
                    loading.style.display = 'none';
                    content.innerHTML = '<div style="text-align: center; color: #6b7280; padding: 20px;">Please select Class and Book (or Topics) first to view questions.</div>';
                    return;
                }
            }
            
            // Fetch questions
            fetch('ajax_preview_questions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                if (data.success) {
                    if (data.questions.length === 0) {
                        content.innerHTML = '<div style="text-align: center; color: #6b7280; padding: 20px;">No questions found matching your criteria.</div>';
                        countSpan.textContent = '0 questions found';
                    } else {
                        let html = '<div style="display: flex; flex-direction: column; gap: 15px;">';
                        data.questions.forEach((q, index) => {
                            const badgeColor = q.source === 'ai' ? '#dbeafe' : '#f3f4f6';
                            const badgeText = q.source === 'ai' ? '#1e40af' : '#374151';
                            const badgeLabel = q.source === 'ai' ? 'AI Generated' : 'Standard';
                            
                            html += `
                                <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px; background: white;">
                                    <div style="margin-bottom: 8px; font-weight: 600; color: #111827; display: flex; justify-content: space-between;">
                                        <span>Q${index + 1}. ${escapeHtml(q.question)}</span>
                                        <div style="display: flex; gap: 5px; align-items: center;">
                                            <span style="font-size: 0.7em; background: ${badgeColor}; color: ${badgeText}; padding: 2px 6px; border-radius: 4px; height: fit-content;">${badgeLabel}</span>
                                            <select onchange="updateCorrectOption('${q.mcq_id}', '${q.source}', this.value)" style="font-size: 0.8em; padding: 2px; border: 1px solid #d1d5db; border-radius: 4px; background-color: #f0fdf4; border-color: #16a34a; color: #15803d; font-weight: 500;">
                                                <option value="A" ${q.correct_option.toUpperCase() === 'A' ? 'selected' : ''}>Key: A</option>
                                                <option value="B" ${q.correct_option.toUpperCase() === 'B' ? 'selected' : ''}>Key: B</option>
                                                <option value="C" ${q.correct_option.toUpperCase() === 'C' ? 'selected' : ''}>Key: C</option>
                                                <option value="D" ${q.correct_option.toUpperCase() === 'D' ? 'selected' : ''}>Key: D</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 0.9em; color: #4b5563;">
                                        <div class="${q.correct_option === 'A' || q.correct_option === 'a' ? 'text-green-600 font-bold' : ''}">A) ${escapeHtml(q.option_a)}</div>
                                        <div class="${q.correct_option === 'B' || q.correct_option === 'b' ? 'text-green-600 font-bold' : ''}">B) ${escapeHtml(q.option_b)}</div>
                                        <div class="${q.correct_option === 'C' || q.correct_option === 'c' ? 'text-green-600 font-bold' : ''}">C) ${escapeHtml(q.option_c)}</div>
                                        <div class="${q.correct_option === 'D' || q.correct_option === 'd' ? 'text-green-600 font-bold' : ''}">D) ${escapeHtml(q.option_d)}</div>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        content.innerHTML = html;
                        countSpan.textContent = `Showing ${data.questions.length} available questions`;
                    }
                } else {
                    content.innerHTML = `<div style="color: red; text-align: center;">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                console.error('Error:', error);
                content.innerHTML = '<div style="color: red; text-align: center;">An error occurred while fetching questions.</div>';
            });
        }
        
        function regenerateQuestions() {
            if (!confirm('This will generate more questions using AI for your selected topics. This may take a few seconds. Continue?')) {
                return;
            }
            
            const content = document.getElementById('previewContent');
            const loading = document.getElementById('previewLoading');
            const loadingText = document.getElementById('previewLoadingText');
            
            content.innerHTML = '';
            loading.style.display = 'block';
            loadingText.textContent = '🤖 AI is generating new questions... Please wait...';
            
            const formData = new FormData();
            const topicsInput = document.querySelector('input[name="topics"]');
            
            if (topicsInput) {
                formData.append('topics', topicsInput.value);
                formData.append('count', 3); // Generate 3 per topic
            } else {
                alert('No topics selected');
                loading.style.display = 'none';
                return;
            }
            
            fetch('ajax_regenerate_questions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh the list to show new questions
                    previewQuestions();
                    // Optionally show a toast or alert
                    // alert(data.message);
                } else {
                    loading.style.display = 'none';
                    content.innerHTML = `<div style="color: red; text-align: center;">Error generating questions: ${data.message}</div>`;
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                console.error('Error:', error);
                content.innerHTML = '<div style="color: red; text-align: center;">An error occurred while generating questions.</div>';
            });
        }
        
        function updateCorrectOption(mcqId, source, newOption) {
            fetch('ajax_update_correct_option.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `mcq_id=${mcqId}&source=${source}&correct_option=${newOption}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the visual indication of the correct answer
                    // We need to re-fetch or manually update the DOM classes
                    // For simplicity, let's just show a toast/alert or refresh the preview if needed
                    // But to make it smooth, let's just log it for now as the dropdown shows current state
                    console.log('Updated successfully');
                    
                    // Optional: refresh the view to update the green bold styling
                    previewQuestions();
                } else {
                    alert('Failed to update: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating.');
            });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('previewModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        function clearSelectedTopics() {
            if (confirm('Are you sure you want to clear the selected topics?')) {
                window.location.href = 'online_quiz_host_new.php?clear_topics=1';
            }
        }

        function goToTopicSearch() {
            const mcqCount = document.getElementById('mcq_count').value;
            const duration = document.getElementById('quiz_duration').value;
            
            // Redirect to topic search with host context
            window.location.href = `mcqs_topic.php?source=host&mcq_count=${mcqCount}&quiz_duration=${duration}`;
        }

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
