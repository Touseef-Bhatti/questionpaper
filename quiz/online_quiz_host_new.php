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
$pageTitle = "Live Quiz Maker for Teachers | AI-Powered Quiz Generator";

$metaDescription = "Create and host live quizzes using AI or your own questions. Conduct real-time classroom assessments with instant results, interactive leaderboards, and performance tracking for students.";

$metaKeywords = "live quiz maker for teachers, AI quiz generator, host live quiz online, classroom assessment tool, MCQ quiz maker, online test platform, live leaderboard quizzes, teacher dashboard tool, digital learning platform";

include_once '../header.php';
?>

<!-- JSON-LD Structured Data for Teachers -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "Live Quiz Hosting Tool",
  "operatingSystem": "Web",
  "applicationCategory": "EducationSupport",
  "description": "An interactive digital classroom tool to host live MCQ competitions and assessments."
}
</script>

<!DOCTYPE html>
<html lang="en">
<head>
    
<link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
<link rel="stylesheet" href="<?= $assetBase ?>css/online_quiz_host_new.css">
<link rel="stylesheet" href="<?= $assetBase ?>css/mcqs_topic.css">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Live Quiz Maker for Teachers | AI-Powered Quiz Generator</title>

<meta name="description" content="Create and host live quizzes using AI or your own questions. Conduct real-time classroom assessments with instant results, interactive leaderboards, and performance tracking for students.">

<meta name="keywords" content="live quiz maker for teachers, AI quiz generator, host live quiz online, classroom assessment tool, MCQ quiz maker, online test platform, live leaderboard quizzes, teacher dashboard tool, digital learning platform">

</head>

<body>
    





<div class="quiz-creator">
    <div class="creator-header">
            <div>
                <h1>🚀 Create Quiz Room</h1>
                <p class="subtitle">Welcome back, <?= htmlspecialchars($user_name) ?>! Let's create an amazing quiz experience.</p>
            </div>
            
        </div>
        
        <?php
        $selectedTopics = $_SESSION['host_quiz_topics'] ?? [];
        $hasTopics = !empty($selectedTopics);
        $urlMcqCount = $_GET['mcq_count'] ?? 10;
        $urlDuration = $_GET['duration'] ?? 10;
        ?>

        <div class="creator-body">
            <form id="quizForm" method="POST" action="online_quiz_create_room.php">
                <input type="hidden" name="source" value="host">
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
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;">
                                <button type="button" class="btn btn-secondary" style="width: 100%; border: 2px dashed #6366f1; color: #6366f1; background: #f5f3ff; font-weight: 600;" onclick="goToTopicSearch()">
                                    🔍 Search Questions by Topic
                                </button>
                                <button type="button" class="btn btn-secondary" style="width: 100%; border: 2px dashed #0d9488; color: #0d9488; background: #ecfdf5; font-weight: 600;" onclick="openHostFileUploadModal()">
                                    📄 Upload file → MCQs
                                </button>
                            </div>
                            <div class="form-hint" style="text-align: center; margin-top: 8px;">Search by topic, or upload a PDF/Word/PPT/image and AI will build MCQs from your file only (max 10 MB)</div>
                            
                            <?php if ($hasTopics): ?>
                            <div id="selectedTopicsContainer" class="topic-container">
                                <h4 class="topic-header">
                                    <span>✅ Selected Topics (<?= count($selectedTopics) ?>)</span>
                                    <button type="button" onclick="clearSelectedTopics()" class="topic-clear-btn">Clear</button>
                                </h4>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    <?php foreach ($selectedTopics as $topic): ?>
                                        <span class="topic-badge">
                                            <?= htmlspecialchars($topic) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="topics" value="<?= htmlspecialchars(json_encode($selectedTopics)) ?>">
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$hasTopics): ?>
                        <div class="form-group">
                            <label class="form-label">📚 Class</label>
                            <select class="form-select" id="class_id" name="class_id">
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
                            <select class="form-select" id="book_id" name="book_id" disabled>
                                <option value="">Select class first</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        </div> 
                        <!-- Custom Questions -->
                <div class="toggle-section">
                    <div class="toggle-header" onclick="toggleCustomQuestions()">
                        <div class="toggle-switch" id="customToggle">
                            <div class="toggle-knob"></div>
                        </div>
                        <div>
                            <h3 style="margin: 0; font-size: 1.25rem;">✨ Add Custom Questions</h3>
                            <p style="margin: 5px 0 0; color: #6b7280;">Create your own questions. They will be <b>automatically saved</b> to your profile.</p>
                        </div>
                    </div>
                    
                    <div class="mcq-builder" id="customQuestions">
                        <div style="margin-bottom: 20px;">
                            <button type="button" class="btn btn-secondary" style="color: black;border: 1px solid black;" onclick="addCustomQuestion()">
                                ➕ Add Question
                            </button>
                            <button type="button" class="btn btn-secondary" style="color: black;border: 1px solid black;" onclick="openSavedQuestionsModal()">
                                📂 Select from Saved Questions
                            </button>
                            <button type="button" class="btn btn-secondary" style="color: black;border: 1px solid black;" onclick="clearCustomQuestions()">
                                🗑️ Clear All
                            </button>
                        </div>
                        
                        <div id="customQuestionsList"></div>
                        <input type="hidden" name="custom_mcqs" id="custom_mcqs">
                        <input type="hidden" name="selected_mcq_ids" id="selected_mcq_ids_input">
                    </div>
                </div>

                <!-- Saved Questions Modal -->
                <div id="savedQuestionsModal" class="modal-overlay" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>📂 Select Saved Questions</h3>
                            <button type="button" class="close-btn" onclick="closeSavedQuestionsModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div id="savedQuestionsLoader" class="loader-container" style="display: none;">
                                <div class="spinner"></div>
                                <div style="margin-top: 10px;">Loading questions...</div>
                            </div>
                            <div id="savedQuestionsList">
                                <!-- Questions will be loaded here -->
                            </div>
                        </div>
                        <div class="modal-footer">
                             <button type="button" class="btn btn-secondary" onclick="closeSavedQuestionsModal()">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="addSelectedSavedQuestions()">Add Selected Questions</button>
                        </div>
                    </div>
                </div>
                </div>

                <!-- File upload → MCQs (same pipeline as topic quiz; MCQs only) -->
                <div class="text-upload-modal" id="hostTextUploadModal" style="display:none;">
                    <div class="text-upload-modal-card">
                        <div class="text-upload-modal-header">
                            <div>
                                <h3 class="text-upload-modal-title"><i class="fas fa-magic"></i> AI File → MCQs for live room</h3>
                                <p class="text-upload-modal-subtitle">Upload one file; questions are generated only from its content</p>
                            </div>
                            <button type="button" class="text-upload-close-btn" onclick="closeHostFileUploadModal()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="text-upload-modal-body">
                            <div class="text-upload-file-wrapper">
                                <label class="text-upload-file-label" for="hostDocumentUploadInput">
                                    <i class="fas fa-paperclip"></i> Choose file
                                </label>
                                <input type="file" id="hostDocumentUploadInput" class="text-upload-file-input" name="document" accept=".pdf,.doc,.docx,.ppt,.pptx,.png,.jpg,.jpeg,.webp,.gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,image/*">
                                <div class="text-upload-file-meta">
                                    <span id="hostDocumentUploadFilename" class="text-upload-filename">No file selected</span>
                                    <span class="text-upload-hint">PDF, DOC, DOCX, PPT, PPTX, PNG, JPG, WEBP, GIF · max 10 MB</span>
                                </div>
                            </div>
                            <div class="text-upload-config">
                                <div class="text-upload-config-title">Number of MCQs</div>
                                <div class="text-upload-types">
                                    <label class="text-upload-type-checkbox" style="width:100%;">
                                        <span class="text-upload-type-label mcq-label"><i class="fas fa-list-ul"></i> MCQs to generate</span>
                                        <div class="text-upload-count-control">
                                            <button type="button" onclick="adjustHostTextCount('hostTextCountMcqs', -1)">−</button>
                                            <input type="number" id="hostTextCountMcqs" value="10" min="1" max="30">
                                            <button type="button" onclick="adjustHostTextCount('hostTextCountMcqs', 1)">+</button>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="text-upload-error" id="hostTextUploadError" style="display:none;"></div>
                            <div class="text-upload-progress" id="hostTextUploadProgress" style="display:none;">
                                <div class="text-upload-progress-bar-track">
                                    <div class="text-upload-progress-bar" id="hostTextProgressBar"></div>
                                </div>
                                <div class="text-upload-progress-text" id="hostTextProgressText">Reading your file...</div>
                            </div>
                            <div class="text-upload-results" id="hostTextUploadResults" style="display:none;"></div>
                        </div>
                        <div class="text-upload-modal-footer">
                            <button type="button" class="text-upload-cancel-btn" onclick="closeHostFileUploadModal()">Close</button>
                            <button type="button" class="text-upload-generate-btn" id="hostTextGenerateBtn" onclick="generateHostMcqsFromFile()">
                                <i class="fas fa-bolt"></i> Generate MCQs
                            </button>
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
                        
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">⚙️</div>
                        <div>
                            <h2 class="section-title">Quiz Settings</h2>
                            <p class="section-description">Finalize question count and duration</p>
                        </div>
                    </div>
                    
                    <div class="form-grid">
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

              


              

                <!-- Styles moved to CSS file -->

                <!-- Quiz Preview -->
                <div class="preview-section">
                    <h3 style="margin: 0 0 20px; color: #0369a1; display: flex; justify-content: space-between; align-items: center;">
                        <span>📊 Quiz Preview</span>

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
                
                <!-- SEO Informational Section for Teachers -->
                <div style="margin-top: 60px; padding: 40px; background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0;">
                    <h2 style="color: #1e293b; font-size: 1.5rem; font-weight: 800; margin-bottom: 24px; text-align: center;">Enhanced Teaching with AI-Driven Assessments</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 30px;">
                        <div>
                            <h4 style="color: var(--primary); margin-bottom: 10px;">📊 Real-time Analytics</h4>
                            <p style="font-size: 0.9rem; line-height: 1.6; color: #64748b;">Monitor student progress live as they answer. Identify collective weak points and 2026 Board Exam readiness instantly.</p>
                        </div>
                        <div>
                            <h4 style="color: var(--primary); margin-bottom: 10px;">✨ AI Content Creation</h4>
                            <p style="font-size: 0.9rem; line-height: 1.6; color: #64748b;">Save hours of preparation. Our AI can draft questions for any subject level, mirroring the difficulty of new syllabus board papers.</p>
                        </div>
                        <div>
                            <h4 style="color: var(--primary); margin-bottom: 10px;">🥇 Gamified Learning</h4>
                            <p style="font-size: 0.9rem; line-height: 1.6; color: #64748b;">Boost classroom engagement. Students compete on a live leaderboard, making exam preparation an exciting and collaborative activity.</p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/ai_loader.php'; ?>



    <script>

        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
            window.location.href = `topic-wise-mcqs-test?source=host&mcq_count=${mcqCount}&quiz_duration=${duration}`;
        }

        // --- File upload → MCQs (same API as mcqs_topic; merged into custom questions) ---
        function openHostFileUploadModal() {
            const modal = document.getElementById('hostTextUploadModal');
            const fin = document.getElementById('hostDocumentUploadInput');
            const fn = document.getElementById('hostDocumentUploadFilename');
            const err = document.getElementById('hostTextUploadError');
            const prog = document.getElementById('hostTextUploadProgress');
            const res = document.getElementById('hostTextUploadResults');
            if (fin) fin.value = '';
            if (fn) fn.textContent = 'No file selected';
            if (err) err.style.display = 'none';
            if (prog) prog.style.display = 'none';
            if (res) res.style.display = 'none';
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                setTimeout(() => modal.classList.add('active'), 10);
            }
        }

        function closeHostFileUploadModal() {
            const modal = document.getElementById('hostTextUploadModal');
            if (modal) {
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }, 300);
            }
        }

        function adjustHostTextCount(inputId, delta) {
            const inp = document.getElementById(inputId);
            if (!inp) return;
            let val = parseInt(inp.value, 10) || 1;
            val = Math.max(parseInt(inp.min, 10) || 1, Math.min(val + delta, parseInt(inp.max, 10) || 30));
            inp.value = val;
        }

        document.getElementById('hostDocumentUploadInput')?.addEventListener('change', function() {
            const fn = document.getElementById('hostDocumentUploadFilename');
            if (fn) fn.textContent = (this.files && this.files[0]) ? this.files[0].name : 'No file selected';
        });

        function hostNormalizeCorrectLetter(m) {
            let c = String(m.correct_option ?? m.correct ?? 'A').trim();
            const u = c.toUpperCase();
            if (/^[ABCD]$/.test(u)) return u;
            const opts = [m.option_a, m.option_b, m.option_c, m.option_d];
            const letters = ['A', 'B', 'C', 'D'];
            for (let i = 0; i < 4; i++) {
                if (String(opts[i] ?? '').trim() === c) return letters[i];
            }
            return 'A';
        }

        async function generateHostMcqsFromFile() {
            const fileInput = document.getElementById('hostDocumentUploadInput');
            const file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            const errorEl = document.getElementById('hostTextUploadError');
            const progressEl = document.getElementById('hostTextUploadProgress');
            const progressBar = document.getElementById('hostTextProgressBar');
            const progressText = document.getElementById('hostTextProgressText');
            const resultsEl = document.getElementById('hostTextUploadResults');
            const genBtn = document.getElementById('hostTextGenerateBtn');

            errorEl.style.display = 'none';
            resultsEl.style.display = 'none';

            if (!file) {
                errorEl.textContent = 'Please choose a file to upload.';
                errorEl.style.display = 'block';
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                errorEl.textContent = 'File is too large. Maximum size is 10 MB.';
                errorEl.style.display = 'block';
                return;
            }

            progressEl.style.display = 'block';
            genBtn.disabled = true;
            genBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

            const progressSteps = [
                'Reading your file...',
                'Sending to AI...',
                'Crafting MCQs...',
                'Validating answers...',
                'Finalizing...'
            ];
            let stepIdx = 0;
            let pVal = 0;
            const pInterval = setInterval(() => {
                pVal = Math.min(pVal + 3, 92);
                if (progressBar) progressBar.style.width = pVal + '%';
                if (pVal % 18 === 0 && stepIdx < progressSteps.length - 1) {
                    stepIdx++;
                    if (progressText) progressText.textContent = progressSteps[stepIdx];
                }
            }, 400);

            try {
                const formData = new FormData();
                formData.append('document', file);
                formData.append('question_types[]', 'mcqs');
                formData.append('count_mcqs', document.getElementById('hostTextCountMcqs')?.value || 10);

                const res = await fetch('../questionPaperFromTopic/generate_from_upload.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();
                clearInterval(pInterval);
                if (progressBar) progressBar.style.width = '100%';

                if (data.success && data.mcqs?.length > 0) {
                    progressEl.style.display = 'none';
                    displayHostMcqResults(data);
                } else {
                    progressEl.style.display = 'none';
                    errorEl.textContent = data.error || 'Failed to generate MCQs. Please try again.';
                    errorEl.style.display = 'block';
                }
            } catch (e) {
                clearInterval(pInterval);
                progressEl.style.display = 'none';
                errorEl.textContent = 'Network error. Please check your connection and try again.';
                errorEl.style.display = 'block';
                console.error('Host file MCQ error:', e);
            } finally {
                genBtn.disabled = false;
                genBtn.innerHTML = '<i class="fas fa-bolt"></i> Generate MCQs';
            }
        }

        function displayHostMcqResults(data) {
            data.mcqs.forEach((m) => {
                customQuestions.push({
                    question: String(m.question || '').trim(),
                    option_a: String(m.option_a ?? ''),
                    option_b: String(m.option_b ?? ''),
                    option_c: String(m.option_c ?? ''),
                    option_d: String(m.option_d ?? ''),
                    correct: hostNormalizeCorrectLetter(m)
                });
            });

            const mcqInput = document.getElementById('mcq_count');
            const n = Math.min(50, Math.max(1, data.mcqs.length));
            if (mcqInput) mcqInput.value = n;

            const toggle = document.getElementById('customToggle');
            const section = document.getElementById('customQuestions');
            if (toggle && section && !toggle.classList.contains('active')) {
                toggle.classList.add('active');
                section.classList.add('active');
            }

            renderCustomQuestions();
            updatePreview();
            closeHostFileUploadModal();
            window._autoSubmitFromFileUpload = true;
            const form = document.getElementById('quizForm');
            if (form) {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
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
        const classSelect = document.getElementById('class_id');
        if (classSelect) {
            classSelect.addEventListener('change', async function() {
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
        }
        
        // Load chapters based on book selection
        const bookSelect = document.getElementById('book_id');
        if (bookSelect) {
            bookSelect.addEventListener('change', async function() {
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
                            <div class="chapter-item" onclick="toggleChapter(${chapter.chapter_id}, this)">
                                <input type="checkbox" id="ch_${chapter.chapter_id}" value="${chapter.chapter_id}" onchange="handleChapterChange(${chapter.chapter_id})">
                                <label for="ch_${chapter.chapter_id}">${chapter.chapter_name}</label>
                            </div>
                        `).join('');
                } catch (error) {
                    chapterSelector.innerHTML = '<div class="selector-hint">Error loading chapters</div>';
                    console.error('Error loading chapters:', error);
                }
            });
        }
        
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
            
            // Visual feedback
            if (checkbox.checked) {
                element.classList.add('selected');
                element.style.backgroundColor = '#eff6ff';
                element.style.borderColor = '#bfdbfe';
            } else {
                element.classList.remove('selected');
                element.style.backgroundColor = '';
                element.style.borderColor = '';
            }
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
        
        function saveCustomQuestionToProfile(index) {
            const q = customQuestions[index];
            if (!q.question.trim() || !q.option_a.trim() || !q.option_b.trim() || !q.option_c.trim() || !q.option_d.trim()) {
                alert('Please fill all fields before saving.');
                return;
            }

            const formData = new FormData();
            formData.append('question', q.question);
            formData.append('option_a', q.option_a);
            formData.append('option_b', q.option_b);
            formData.append('option_c', q.option_c);
            formData.append('option_d', q.option_d);
            formData.append('correct_option', q.correct);

            fetch('ajax_save_question_to_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Question saved to your profile!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving.');
            });
        }

        function renderCustomQuestions() {
            const container = document.getElementById('customQuestionsList');
            
            container.innerHTML = customQuestions.map((q, index) => `
                <div class="mcq-card">
                    <div class="mcq-card-header">
                        <h4 class="mcq-card-title">Question ${index + 1}</h4>
                        <div class="mcq-card-actions">
                            <button type="button" class="btn btn-primary btn-remove-question" onclick="removeCustomQuestion(${index})">
                                🗑️ Remove
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label">Question Text</label>
                        <input type="text" class="form-input" value="${escapeHtml(q.question)}" 
                               onchange="updateCustomQuestion(${index}, 'question', this.value)"
                               placeholder="Enter your question">
                    </div>
                    
                    <div class="mcq-options">
                        <div class="form-group">
                            <label class="form-label">Option A</label>
                            <input type="text" class="form-input" value="${escapeHtml(q.option_a)}"
                                   onchange="updateCustomQuestion(${index}, 'option_a', this.value)"
                                   placeholder="Option A">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Option B</label>
                            <input type="text" class="form-input" value="${escapeHtml(q.option_b)}"
                                   onchange="updateCustomQuestion(${index}, 'option_b', this.value)"
                                   placeholder="Option B">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Option C</label>
                            <input type="text" class="form-input" value="${escapeHtml(q.option_c)}"
                                   onchange="updateCustomQuestion(${index}, 'option_c', this.value)"
                                   placeholder="Option C">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Option D</label>
                            <input type="text" class="form-input" value="${escapeHtml(q.option_d)}"
                                   onchange="updateCustomQuestion(${index}, 'option_d', this.value)"
                                   placeholder="Option D">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
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
            
            const classSelect = document.getElementById('class_id');
            const bookSelect = document.getElementById('book_id');
            const hasClassAndBook = classSelect && bookSelect && !classSelect.disabled && classSelect.value && bookSelect.value;
            
            const topicsInput = document.querySelector('input[name="topics"]');
            const hasTopics = topicsInput && topicsInput.value && topicsInput.value !== '[]';

            // Calculate effective total
            let total = 0;
            if (hasClassAndBook || hasTopics) {
                total = Math.max(mcqCount, customCount);
            } else {
                total = customCount;
            }
            
            document.getElementById('totalQuestions').textContent = total;
            document.getElementById('estimatedTime').textContent = duration;
            document.getElementById('customCount').textContent = customCount;
            document.getElementById('selectedChapters').textContent = chapterCount === 0 ? (hasClassAndBook ? 'All' : 'None') : chapterCount;
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
                const defaultPreset = document.querySelector('.time-preset[data-time="10"]');
                if (defaultPreset) defaultPreset.classList.add('active');
            }
        }
        
        // Form submission
        window._autoSubmitFromFileUpload = false;
        document.getElementById('quizForm').addEventListener('submit', function(e) {
            let mcqCount = parseInt(document.getElementById('mcq_count').value) || 0;
            const customCount = customQuestions.length;
            
            // Logic to determine if we can generate random questions
            const classSelect = document.getElementById('class_id');
            const bookSelect = document.getElementById('book_id');
            const hasClassAndBook = classSelect && bookSelect && !classSelect.disabled && classSelect.value && bookSelect.value;
            
            // Check for topics
            const topicsInput = document.querySelector('input[name="topics"]');
            const hasTopics = topicsInput && topicsInput.value && topicsInput.value !== '[]';

            // If we have no class/book selected, we can ONLY use custom questions.
            let randomQuestionsNeeded = 0;
            let effectiveTotal = 0;
            
            if (hasClassAndBook || hasTopics) {
                // Standard mode: We can generate random questions
                randomQuestionsNeeded = Math.max(0, mcqCount - customCount);
                effectiveTotal = Math.max(mcqCount, customCount);
            } else {
                // Custom-only mode: Ignore mcqCount if it's higher than customCount
                // We will force the quiz to be custom-only
                randomQuestionsNeeded = 0;
                effectiveTotal = customCount;
            }
            
            if (effectiveTotal === 0) {
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

            // Confirmation Dialog
            let message = "📝 Quiz Confirmation\n\n";
            message += `Total Questions: ${effectiveTotal}\n`;
            message += `------------------------\n`;
            message += `• Custom Questions: ${customCount}\n`;
            
            if (randomQuestionsNeeded > 0) {
                message += `• Random Questions: ${randomQuestionsNeeded}\n`;
            } else {
                 if (mcqCount > customCount && !hasClassAndBook && !hasTopics) {
                     message += `(Note: You requested ${mcqCount} questions but didn't select a Class/Book or Topics. The quiz will only contain your ${customCount} custom questions.)\n`;
                 } else if (customCount > mcqCount) {
                     message += `(Note: You have more custom questions than the requested count. All ${customCount} will be included.)\n`;
                 }
            }
            
            message += `\nDuration: ${document.getElementById('quiz_duration').value} minutes\n`;
            message += `\nDo you want to create this quiz?`;

            if (!window._autoSubmitFromFileUpload && !confirm(message)) {
                e.preventDefault();
            } else {
                // If we are in custom-only fallback mode, update the input so the backend doesn't get confused
                if (!hasClassAndBook && !hasTopics && customCount > 0) {
                     document.getElementById('mcq_count').value = customCount;
                }
                
                // Show the shared AI loader animation
                launchHostAILoader();
            }
            window._autoSubmitFromFileUpload = false;
        });
        
        function launchHostAILoader() {
            if (typeof showAILoader !== 'function') return;
            showAILoader(
                [
                    { label: 'Analyzing quiz parameters', duration: 2500 },
                    { label: 'Preparing curriculum data', duration: 2500 },
                    { label: 'Generating and fetching MCQs', duration: 3500 },
                    { label: 'Building quiz room', duration: 2500 },
                    { label: 'Finalizing live setup', duration: 2000 }
                ],
                'Synthesizing quiz questions via Ahmad Learning Hub Engine...',
                'Neural Engine Processing'
            );
        }
        
        // Update preview on input changes
        document.getElementById('mcq_count').addEventListener('input', updatePreview);
        document.getElementById('quiz_duration').addEventListener('input', updatePreview);
        
        // Initial preview update
        updatePreview();

        // Saved Questions Logic
        let savedQuestions = [];

        function openSavedQuestionsModal() {
            const modal = document.getElementById('savedQuestionsModal');
            modal.style.display = 'flex';
            fetchSavedQuestions();
            
            // Close on click outside
            modal.onclick = function(e) {
                if (e.target === this) {
                    closeSavedQuestionsModal();
                }
            };
        }

        function closeSavedQuestionsModal() {
            document.getElementById('savedQuestionsModal').style.display = 'none';
        }

        // Close modal on Escape key
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSavedQuestionsModal();
            }
        });

        function fetchSavedQuestions() {
            const loader = document.getElementById('savedQuestionsLoader');
            const list = document.getElementById('savedQuestionsList');
            
            loader.style.display = 'block';
            list.innerHTML = '';
            
            fetch('ajax_get_saved_questions.php')
                .then(response => response.json())
                .then(data => {
                    loader.style.display = 'none';
                    if (data.success) {
                        if (data.questions.length > 0) {
                            savedQuestions = data.questions;
                            renderSavedQuestions(data.questions);
                        } else {
                            list.innerHTML = '<div style="text-align: center; color: #6b7280; padding: 20px;">No saved questions found.</div>';
                        }
                    } else {
                        list.innerHTML = `<div style="text-align: center; color: red; padding: 20px;">Error: ${escapeHtml(data.message)}</div>`;
                    }
                })
                .catch(error => {
                    loader.style.display = 'none';
                    list.innerHTML = '<div style="text-align: center; color: red; padding: 20px;">Error loading questions.</div>';
                    console.error('Error:', error);
                });
        }

        function renderSavedQuestions(questions) {
            const list = document.getElementById('savedQuestionsList');
            list.innerHTML = questions.map((q, index) => {
                const correctOption = q.correct;
                return `
                <div class="saved-question-item" id="saved_item_${index}" onclick="document.getElementById('saved_q_${index}').click()">
                    <input type="checkbox" id="saved_q_${index}" value="${index}" class="saved-question-checkbox" onclick="event.stopPropagation()" onchange="updateItemState(${index})">
                    <div class="saved-question-content">
                        <div class="saved-question-text">${escapeHtml(q.question)}</div>
                        <div class="saved-question-options">
                            <div class="option-pill ${correctOption === 'A' ? 'correct' : ''}">
                                <strong>A:</strong> ${escapeHtml(q.option_a)}
                            </div>
                            <div class="option-pill ${correctOption === 'B' ? 'correct' : ''}">
                                <strong>B:</strong> ${escapeHtml(q.option_b)}
                            </div>
                            <div class="option-pill ${correctOption === 'C' ? 'correct' : ''}">
                                <strong>C:</strong> ${escapeHtml(q.option_c)}
                            </div>
                            <div class="option-pill ${correctOption === 'D' ? 'correct' : ''}">
                                <strong>D:</strong> ${escapeHtml(q.option_d)}
                            </div>
                        </div>
                    </div>
                </div>
            `}).join('');
        }

        function updateItemState(index) {
            const checkbox = document.getElementById(`saved_q_${index}`);
            const item = document.getElementById(`saved_item_${index}`);
            if (checkbox.checked) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        }

        function addSelectedSavedQuestions() {
            const checkboxes = document.querySelectorAll('#savedQuestionsList input[type="checkbox"]:checked');
            let addedCount = 0;
            
            checkboxes.forEach(cb => {
                const index = parseInt(cb.value);
                const q = savedQuestions[index];
                
                // Add to custom questions
                customQuestions.push({
                    question: q.question,
                    option_a: q.option_a,
                    option_b: q.option_b,
                    option_c: q.option_c,
                    option_d: q.option_d,
                    correct: q.correct
                });
                addedCount++;
            });
            
            if (addedCount > 0) {
                renderCustomQuestions();
                closeSavedQuestionsModal();
                
                // Ensure the custom questions section is visible
                const toggle = document.getElementById('customToggle');
                const section = document.getElementById('customQuestions');
                if (!toggle.classList.contains('active')) {
                    toggle.classList.add('active');
                    section.classList.add('active');
                }
            } else {
                alert('Please select at least one question.');
            }
        }
    </script>
</body>
</html>
