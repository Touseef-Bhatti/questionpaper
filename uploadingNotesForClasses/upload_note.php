<?php
include '../db_connect.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../services/GoogleDriveService.php';

// User is guaranteed to be logged in at this point (auth_check.php handles redirect)
$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'] ?? $_SESSION['name'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';

$emailColumnCheck = $conn->query("SHOW COLUMNS FROM class_notes LIKE 'uploader_email'");
if (!$emailColumnCheck || $emailColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE class_notes ADD COLUMN uploader_email VARCHAR(255) DEFAULT NULL AFTER uploader_name");
}

$userStmt = $conn->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userRow = $userStmt->get_result()->fetch_assoc();
$userStmt->close();
if ($userRow) {
    if (!empty($userRow['name'])) {
        $userName = $userRow['name'];
    }
    if (!empty($userRow['email'])) {
        $userEmail = $userRow['email'];
    }
}

$message = '';
$error = '';

// Allowed file types
$allowedExtensions = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
$allowedMimes = [
    'application/pdf',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/png', 'image/jpeg', 'image/gif', 'image/webp'
];
$maxFileSize = 50 * 1024 * 1024; // 50MB

$booksQuery = $conn->query("SELECT book_id, book_name, class_id FROM book ORDER BY class_id ASC, book_name ASC");
$dbBooksByClass = [];
while ($booksQuery && $bRow = $booksQuery->fetch_assoc()) {
    $cid = (string)$bRow['class_id'];
    $dbBooksByClass[$cid][] = $bRow['book_name'];
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simple CSRF via session check (user is already authenticated)
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $customSubject = trim($_POST['custom_subject'] ?? '');
    if ($subject === 'Other' && !empty($customSubject)) {
        $subject = $customSubject;
    }

    $chapter = trim($_POST['chapter'] ?? '');
    $customChapter = trim($_POST['custom_chapter'] ?? '');
    if ($chapter === '__custom__' && !empty($customChapter)) {
        $chapter = $customChapter;
    }

    // Validation
    if (empty($title)) {
        $error = 'Title is required.';
    } elseif (!in_array($class, ['9', '10', '11', '12'])) {
        $error = 'Please select a valid class.';
    } elseif (empty($subject)) {
        $error = 'Subject is required.';
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        ];
        $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error = $uploadErrors[$errCode] ?? 'File upload failed.';
    } else {
        $file = $_FILES['file'];
        $fileSize = $file['size'];
        $fileTmpPath = $file['tmp_name'];
        $originalFileName = basename($file['name']);
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

        if ($fileSize > $maxFileSize) {
            $error = 'File size exceeds 50MB limit.';
        } elseif (!in_array($fileExtension, $allowedExtensions)) {
            $error = 'File type not allowed. Accepted: ' . implode(', ', $allowedExtensions);
        } else {
            // Verify MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileTmpPath);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimes)) {
                $error = 'File content does not match its extension.';
            } else {
                // Upload to Google Drive
                try {
                    $driveService = new GoogleDriveService();
                    $driveResult = $driveService->uploadFile($fileTmpPath, $originalFileName, $mimeType, $class, $subject);

                    // Save metadata to database
                    $stmt = $conn->prepare("INSERT INTO class_notes 
                        (title, description, subject, class, chapter, drive_file_id, drive_url, original_filename, mime_type, file_size, status, uploaded_by, uploader_name, uploader_email, uploader_type) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, 'user')");
                    
                    $stmt->bind_param('sssssssssiiss',
                        $title, $description, $subject, $class, $chapter,
                        $driveResult['file_id'], $driveResult['url'],
                        $originalFileName, $mimeType, $fileSize,
                        $userId, $userName, $userEmail
                    );

                    if ($stmt->execute()) {
                        $message = 'Your notes have been uploaded successfully! They will be visible after admin review.';
                        // Clear form data
                        $title = $description = $class = $subject = $chapter = '';
                    } else {
                        $error = 'Failed to save note metadata. Please try again.';
                        error_log("class_notes insert error: " . $stmt->error);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    $error = 'Upload to Google Drive failed. Please try again later.';
                    error_log("Google Drive upload error: " . $e->getMessage());
                }

                // Delete temporary file
                if (file_exists($fileTmpPath)) {
                    @unlink($fileTmpPath);
                }
            }
        }
    }
}

// SEO
$pageTitle = "Upload Study Notes – Share Your Knowledge | Ahmad Learning Hub";
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$siteUrl = $protocol . "://" . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        .upload-page { background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%); min-height: 100vh; }
        .upload-container { max-width: 720px; margin: 0 auto; padding: 2rem 1.5rem 3rem; }
        
        .upload-hero {
            text-align: center;
            padding: 2.5rem 2rem;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 0 0 32px 32px;
            color: #fff;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .upload-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 50% 0%, rgba(99,102,241,0.25) 0%, transparent 60%);
            pointer-events: none;
        }
        .upload-hero h1 { font-size: 1.8rem; font-weight: 800; position: relative; z-index: 1; }
        .upload-hero p { color: #94a3b8; position: relative; z-index: 1; }
        
        .upload-form-card {
            background: #fff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e293b;
            font-size: 0.92rem;
        }
        .form-group label .required { color: #ef4444; }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.25s;
            background: #f8fafc;
            color: #0f172a;
            font-family: 'Inter', sans-serif;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group .hint { font-size: 0.78rem; color: #94a3b8; margin-top: 0.3rem; }
        
        /* File Upload Zone */
        .file-drop-zone {
            border: 3px dashed #cbd5e1;
            border-radius: 16px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafbfc;
            position: relative;
        }
        .file-drop-zone:hover, .file-drop-zone.dragover {
            border-color: #6366f1;
            background: rgba(99,102,241,0.04);
        }
        .file-drop-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .file-drop-icon { font-size: 3rem; margin-bottom: 0.75rem; }
        .file-drop-text { font-weight: 600; color: #475569; margin-bottom: 0.25rem; }
        .file-drop-hint { font-size: 0.8rem; color: #94a3b8; }
        .file-selected { margin-top: 1rem; padding: 0.75rem; background: #ecfdf5; border-radius: 10px; color: #059669; font-weight: 600; font-size: 0.9rem; display: none; }
        .file-selected.show { display: block; }
        
        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 600;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .alert-success { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        
        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(99,102,241,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: 'Inter', sans-serif;
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99,102,241,0.4); }
        .submit-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.88rem;
            color: #1d4ed8;
            line-height: 1.6;
        }
        .info-box strong { display: block; margin-bottom: 0.25rem; }
        
        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
            .upload-container { padding: 1rem; }
        }
    </style>
</head>
<body class="upload-page">
    <?php include '../header.php'; ?>
    
    <div class="main-content">
        <div class="upload-hero">
            <h1>📤 Upload Study Notes</h1>
            <p>Share your knowledge and help fellow students succeed</p>
        </div>
        
        <div class="upload-container">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>ℹ️ How it works</strong>
                Upload your notes and they'll be reviewed by our team. Once approved, they'll be visible to all students on the platform. Your name will be credited as the uploader.
            </div>
            
            <div class="upload-form-card">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="form-group">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" required maxlength="255" 
                               placeholder="e.g., Physics Chapter 1 - Measurements Notes" 
                               value="<?= htmlspecialchars($title ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" maxlength="1000" 
                                  placeholder="Brief description of what's in these notes..."><?= htmlspecialchars($description ?? '') ?></textarea>
                        <div class="hint">Optional — helps students find your notes</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Class <span class="required">*</span></label>
                            <select name="class" id="userClass" required>
                                <option value="">Select Class</option>
                                <option value="9" <?= ($class ?? '') === '9' ? 'selected' : '' ?>>Class 9</option>
                                <option value="10" <?= ($class ?? '') === '10' ? 'selected' : '' ?>>Class 10</option>
                                <option value="11" <?= ($class ?? '') === '11' ? 'selected' : '' ?>>Class 11</option>
                                <option value="12" <?= ($class ?? '') === '12' ? 'selected' : '' ?>>Class 12</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Subject (Book) <span class="required">*</span></label>
                            <select name="subject" id="userSubject" required disabled>
                                <option value="">-- Select Class First --</option>
                            </select>
                            <div id="userCustomSubjectWrap" style="display:none; margin-top:0.5rem;">
                                <input type="text" name="custom_subject" id="userCustomSubject" 
                                       placeholder="Enter custom subject name" maxlength="100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Chapter</label>
                        <select name="chapter" id="userChapter" disabled>
                            <option value="">-- Select Subject First --</option>
                        </select>
                        <div id="userCustomChapterWrap" style="display:none; margin-top:0.5rem;">
                            <input type="text" name="custom_chapter" id="userCustomChapter" 
                                   placeholder="Enter custom chapter or topic name" maxlength="255">
                        </div>
                        <div class="hint">Optional — choose a chapter or keep as general / full-book notes</div>
                    </div>
                    
                    <div class="form-group">
                        <label>File <span class="required">*</span></label>
                        <div class="file-drop-zone" id="fileDropZone">
                            <input type="file" name="file" id="fileInput" required
                                   accept=".pdf,.ppt,.pptx,.doc,.docx,.png,.jpg,.jpeg,.gif,.webp">
                            <div class="file-drop-icon">📁</div>
                            <div class="file-drop-text">Click or drag & drop your file here</div>
                            <div class="file-drop-hint">PDF, Word, PowerPoint, Images — Max 50MB</div>
                        </div>
                        <div class="file-selected" id="fileSelected"></div>
                    </div>
                    
                    
                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fas fa-cloud-upload-alt"></i> Upload Notes
                    </button>
                </form>
            </div>
            
            <div class="go-back-section" style="margin-top: 2rem; text-align: center;">
                <a href="<?= $assetBase ?>class-notes" class="go-back-btn" style="display:inline-flex;align-items:center;gap:8px;padding:0.7rem 1.5rem;background:#f1f5f9;border-radius:12px;color:#475569;font-weight:600;text-decoration:none;transition:all 0.25s;">
                    <i class="fas fa-arrow-left"></i> Back to Class Notes
                </a>
            </div>
        </div>
    </div>
    
    <?php include '../footer.php'; ?>
    
    <script>
        // File drop zone interactions
        const dropZone = document.getElementById('fileDropZone');
        const fileInput = document.getElementById('fileInput');
        const fileSelected = document.getElementById('fileSelected');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('uploadForm');
        const notesApiUrl = <?= json_encode($assetBase . 'uploadingNotesForClasses/api_notes.php') ?>;
        const selectedSubject = <?= json_encode($subject ?? '') ?>;
        const selectedChapter = <?= json_encode($chapter ?? '') ?>;
        
        ['dragenter', 'dragover'].forEach(event => {
            dropZone.addEventListener(event, (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
        });
        
        ['dragleave', 'drop'].forEach(event => {
            dropZone.addEventListener(event, (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
            });
        });
        
        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                showSelectedFile(files[0]);
            }
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                showSelectedFile(this.files[0]);
            }
        });
        
        function showSelectedFile(file) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            fileSelected.textContent = `✅ Selected: ${file.name} (${sizeMB} MB)`;
            fileSelected.classList.add('show');
        }
        
        // Dynamic Book and Chapter dropdowns
        const dbBooksByClass = <?= json_encode($dbBooksByClass) ?>;
        const userClass = document.getElementById('userClass');
        const userSubject = document.getElementById('userSubject');
        const userChapter = document.getElementById('userChapter');
        const customSubjWrap = document.getElementById('userCustomSubjectWrap');
        const customChapWrap = document.getElementById('userCustomChapterWrap');

        function populateUserSubjects(classId, selectedValue = '') {
            userSubject.innerHTML = '<option value="">-- Select Subject (Book) --</option>';
            userChapter.innerHTML = '<option value="">-- Select Subject First --</option>';
            userChapter.disabled = true;
            customSubjWrap.style.display = 'none';
            customChapWrap.style.display = 'none';

            if (!classId || !dbBooksByClass[classId]) {
                userSubject.disabled = true;
                return;
            }

            dbBooksByClass[classId].forEach(bookName => {
                const opt = document.createElement('option');
                opt.value = bookName;
                opt.textContent = bookName;
                if (bookName === selectedValue) {
                    opt.selected = true;
                }
                userSubject.appendChild(opt);
            });

            const otherOpt = document.createElement('option');
            otherOpt.value = 'Other';
            otherOpt.textContent = '➕ Other / Custom Subject...';
            if (selectedValue && !dbBooksByClass[classId].includes(selectedValue)) {
                otherOpt.selected = true;
                userCustomSubject.value = selectedValue;
                customSubjWrap.style.display = 'block';
            }
            userSubject.appendChild(otherOpt);
            userSubject.disabled = false;
        }

        async function populateUserChapters(classId, subject, selectedValue = '') {
            userChapter.innerHTML = '<option value="">-- Complete Book / All Chapters / General Notes --</option>';
            customChapWrap.style.display = 'none';

            if (!classId || !subject || subject === 'Other') {
                userChapter.disabled = (subject !== 'Other');
                if (subject === 'Other') {
                    const customOpt = document.createElement('option');
                    customOpt.value = '__custom__';
                    customOpt.textContent = '✏️ Custom / Other Chapter...';
                    customOpt.selected = !!selectedValue;
                    userChapter.appendChild(customOpt);
                    userCustomChapter.value = selectedValue;
                    customChapWrap.style.display = selectedValue ? 'block' : 'none';
                }
                return;
            }

            try {
                userChapter.disabled = true;
                const resp = await fetch(`${notesApiUrl}?type=chapters&class=${encodeURIComponent(classId)}&subject=${encodeURIComponent(subject)}`);
                if (!resp.ok) {
                    throw new Error(`Server returned ${resp.status}`);
                }
                const chapters = await resp.json();
                let foundSelected = false;
                
                chapters.forEach(ch => {
                    const opt = document.createElement('option');
                    opt.value = ch.name;
                    opt.textContent = ch.display;
                    if (ch.name === selectedValue || ch.display === selectedValue) {
                        opt.selected = true;
                        foundSelected = true;
                    }
                    userChapter.appendChild(opt);
                });

                const customOpt = document.createElement('option');
                customOpt.value = '__custom__';
                customOpt.textContent = '✏️ Custom / Other Chapter...';
                if (selectedValue && !foundSelected) {
                    customOpt.selected = true;
                    userCustomChapter.value = selectedValue;
                    customChapWrap.style.display = 'block';
                }
                userChapter.appendChild(customOpt);
            } catch (err) {
                console.error('Failed to fetch chapters:', err);
                const errorOpt = document.createElement('option');
                errorOpt.value = '';
                errorOpt.textContent = 'Unable to load chapters';
                userChapter.appendChild(errorOpt);
            } finally {
                userChapter.disabled = false;
            }
        }

        userClass.addEventListener('change', function() {
            populateUserSubjects(this.value);
        });

        userSubject.addEventListener('change', function() {
            customSubjWrap.style.display = (this.value === 'Other') ? 'block' : 'none';
            populateUserChapters(userClass.value, this.value);
        });

        userChapter.addEventListener('change', function() {
            customChapWrap.style.display = (this.value === '__custom__') ? 'block' : 'none';
        });

        // Initialize if class pre-selected
        if (userClass.value) {
            populateUserSubjects(userClass.value, selectedSubject);
            if (selectedSubject) {
                populateUserChapters(userClass.value, selectedSubject, selectedChapter);
            }
        }

        // Disable submit on form submission to prevent double uploads
        form.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading to Google Drive...';
        });

    </script>
</body>
</html>
