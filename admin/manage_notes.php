<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

// Create table if not exists
// Schema creation moved to install.php

// File upload configuration
$uploadDir = __DIR__ . '/../uploads/notes/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Allowed file types with MIME types
$allowedTypes = [
    'pdf' => ['application/pdf'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'doc' => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'txt' => ['text/plain'],
    'png' => ['image/png'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'gif' => ['image/gif'],
    'webp' => ['image/webp']
];

$maxFileSize = 50 * 1024 * 1024; // 50MB

$message = '';
$error = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $classId = validateInt($_POST['class_id'] ?? 0);
        $bookId = validateInt($_POST['book_id'] ?? 0);
        $chapterId = validateInt($_POST['chapter_id'] ?? 0);
        
        if (empty($title)) {
            $error = 'Title is required';
        } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload failed';
        } else {
            $file = $_FILES['file'];
            $fileSize = $file['size'];
            $fileTmpPath = $file['tmp_name'];
            $originalFileName = basename($file['name']);
            $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
            
            // Validate file size
            if ($fileSize > $maxFileSize) {
                $error = 'File size exceeds 50MB limit';
            }
            // Validate file extension
            elseif (!array_key_exists($fileExtension, $allowedTypes)) {
                $error = 'File type not allowed. Allowed types: ' . implode(', ', array_keys($allowedTypes));
            } else {
                // Get MIME type securely
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $fileTmpPath);
                finfo_close($finfo);
                
                // Validate MIME type
                if (!in_array($mimeType, $allowedTypes[$fileExtension])) {
                    $error = 'Invalid file type. File content does not match extension.';
                } else {
                    // Generate secure filename
                    $newFileName = uniqid('note_', true) . '_' . time() . '.' . $fileExtension;
                    $destination = $uploadDir . $newFileName;
                    
                    // Move uploaded file
                    if (move_uploaded_file($fileTmpPath, $destination)) {
                        // Insert into database
                        $stmt = $conn->prepare("INSERT INTO uploaded_notes 
                            (title, description, file_name, original_file_name, file_path, file_type, file_size, mime_type, class_id, book_id, chapter_id, uploaded_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        $relativePath = 'uploads/notes/' . $newFileName;
                        $uploadedBy = $_SESSION['user_id'];
                        
                        $stmt->bind_param('ssssssisisii', 
                            $title, $description, $newFileName, $originalFileName, $relativePath, 
                            $fileExtension, $fileSize, $mimeType, $classId, $bookId, $chapterId, $uploadedBy
                        );
                        
                        if ($stmt->execute()) {
                            $message = 'File uploaded successfully!';
                            logAdminAction('upload_note', "Uploaded: $title");
                        } else {
                            $error = 'Database error: ' . $stmt->error;
                            unlink($destination); // Remove file if DB insert fails
                        }
                        $stmt->close();
                    } else {
                        $error = 'Failed to move uploaded file';
                    }
                }
            }
        }
    }
}

// Handle soft delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $noteId = validateInt($_POST['note_id'] ?? 0);
        if ($noteId) {
            $stmt = $conn->prepare("UPDATE uploaded_notes SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE note_id = ?");
            $adminId = $_SESSION['user_id'];
            $stmt->bind_param('ii', $adminId, $noteId);
            if ($stmt->execute()) {
                $message = 'Note moved to trash';
                logAdminAction('soft_delete_note', "Note ID: $noteId");
            }
            $stmt->close();
        }
    }
}

// Handle restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $noteId = validateInt($_POST['note_id'] ?? 0);
        if ($noteId) {
            $stmt = $conn->prepare("UPDATE uploaded_notes SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL WHERE note_id = ?");
            $stmt->bind_param('i', $noteId);
            if ($stmt->execute()) {
                $message = 'Note restored successfully';
                logAdminAction('restore_note', "Note ID: $noteId");
            }
            $stmt->close();
        }
    }
}

// Handle permanent delete (Super Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'permanent_delete') {
    if ($_SESSION['role'] !== 'superadmin') {
        $error = 'Only super admin can permanently delete notes';
    } elseif (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $noteId = validateInt($_POST['note_id'] ?? 0);
        if ($noteId) {
            // Get file path before deleting
            $stmt = $conn->prepare("SELECT file_path FROM uploaded_notes WHERE note_id = ? AND is_deleted = 1");
            $stmt->bind_param('i', $noteId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $filePath = __DIR__ . '/../' . $row['file_path'];
                
                // Delete from database
                $deleteStmt = $conn->prepare("DELETE FROM uploaded_notes WHERE note_id = ?");
                $deleteStmt->bind_param('i', $noteId);
                if ($deleteStmt->execute()) {
                    // Delete physical file
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    $message = 'Note permanently deleted';
                    logAdminAction('permanent_delete_note', "Note ID: $noteId");
                }
                $deleteStmt->close();
            }
            $stmt->close();
        }
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $noteId = validateInt($_POST['note_id'] ?? 0);
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $classId = validateInt($_POST['class_id'] ?? 0);
        $bookId = validateInt($_POST['book_id'] ?? 0);
        $chapterId = validateInt($_POST['chapter_id'] ?? 0);
        
        if ($noteId && !empty($title)) {
            $stmt = $conn->prepare("UPDATE uploaded_notes SET title = ?, description = ?, class_id = ?, book_id = ?, chapter_id = ? WHERE note_id = ?");
            $stmt->bind_param('ssiiii', $title, $description, $classId, $bookId, $chapterId, $noteId);
            if ($stmt->execute()) {
                $message = 'Note updated successfully';
                logAdminAction('update_note', "Note ID: $noteId");
            }
            $stmt->close();
        }
    }
}

// Fetch classes for filter
$classesQuery = "SELECT class_id, class_name FROM class ORDER BY class_id ASC";
$classesResult = $conn->query($classesQuery);
$classes = [];
while ($row = $classesResult->fetch_assoc()) {
    $classes[] = $row;
}

// Fetch active notes
$notesQuery = "SELECT n.*, c.class_name, b.book_name, ch.chapter_name, a.name as uploader_name 
               FROM uploaded_notes n 
               LEFT JOIN class c ON n.class_id = c.class_id 
               LEFT JOIN book b ON n.book_id = b.book_id 
               LEFT JOIN chapter ch ON n.chapter_id = ch.chapter_id 
               LEFT JOIN admins a ON n.uploaded_by = a.id 
               WHERE n.is_deleted = 0 
               ORDER BY n.created_at DESC";
$notesResult = $conn->query($notesQuery);

// Fetch deleted notes
$deletedQuery = "SELECT n.*, c.class_name, b.book_name, ch.chapter_name, a.name as uploader_name, d.name as deleter_name 
                 FROM uploaded_notes n 
                 LEFT JOIN class c ON n.class_id = c.class_id 
                 LEFT JOIN book b ON n.book_id = b.book_id 
                 LEFT JOIN chapter ch ON n.chapter_id = ch.chapter_id 
                 LEFT JOIN admins a ON n.uploaded_by = a.id 
                 LEFT JOIN admins d ON n.deleted_by = d.id 
                 WHERE n.is_deleted = 1 
                 ORDER BY n.deleted_at DESC";
$deletedResult = $conn->query($deletedQuery);

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Uploaded Notes - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .notes-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .upload-form {
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .notes-table {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .notes-table h2 {
            padding: 1.5rem;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .file-info {
            font-size: 0.85rem;
            color: #666;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .actions button {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .file-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            background: #e0e0e0;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <div class="notes-container">
        <h1>📚 Manage Uploaded Notes</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Upload Form -->
        <div class="upload-form">
            <h2>📤 Upload New Note</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="upload">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="file">File * (Max 50MB)</label>
                        <input type="file" id="file" name="file" required accept=".pdf,.ppt,.pptx,.doc,.docx,.txt,.png,.jpg,.jpeg,.gif,.webp">
                    </div>
                    
                    <div class="form-group">
                        <label for="class_id">Class (Optional)</label>
                        <select id="class_id" name="class_id" onchange="loadBooks(this.value)">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['class_id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="book_id">Book (Optional)</label>
                        <select id="book_id" name="book_id" onchange="loadChapters()">
                            <option value="0">All Books</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="chapter_id">Chapter (Optional)</label>
                        <select id="chapter_id" name="chapter_id">
                            <option value="0">All Chapters</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                
                <div style="margin-top: 1rem;">
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">
                        <strong>Allowed file types:</strong> PDF, PPT, PPTX, DOC, DOCX, TXT, PNG, JPG, JPEG, GIF, WEBP
                    </p>
                    <button type="submit" class="btn btn-primary">📤 Upload Note</button>
                </div>
            </form>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('active')">Active Notes</button>
            <button class="tab" onclick="switchTab('deleted')">Recently Deleted</button>
        </div>
        
        <!-- Active Notes -->
        <div id="active-tab" class="tab-content active">
            <div class="notes-table">
                <h2>Active Notes</h2>
                <table>
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Size</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($note = $notesResult->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="file-icon"><?= getFileIcon($note['file_type']) ?></span>
                                    <span class="file-info">.<?= htmlspecialchars($note['file_type']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($note['title']) ?></strong>
                                    <?php if ($note['description']): ?>
                                        <br><small style="color: #666;"><?= htmlspecialchars(substr($note['description'], 0, 100)) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($note['class_name']): ?>
                                        <div><?= htmlspecialchars($note['class_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($note['book_name']): ?>
                                        <div style="font-size: 0.85rem; color: #666;"><?= htmlspecialchars($note['book_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($note['chapter_name']): ?>
                                        <div style="font-size: 0.85rem; color: #666;"><?= htmlspecialchars($note['chapter_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatFileSize($note['file_size']) ?></td>
                                <td><?= htmlspecialchars($note['uploader_name'] ?? 'Unknown') ?></td>
                                <td><?= date('M d, Y', strtotime($note['created_at'])) ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-warning" onclick="editNote(<?= $note['note_id'] ?>)">✏️ Edit</button>
                                        <a href="../<?= htmlspecialchars($note['file_path']) ?>" target="_blank" class="btn btn-success">👁️ View</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Move to trash?')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="note_id" value="<?= $note['note_id'] ?>">
                                            <button type="submit" class="btn btn-danger">🗑️ Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Deleted Notes -->
        <div id="deleted-tab" class="tab-content">
            <div class="notes-table">
                <h2>Recently Deleted Notes</h2>
                <table>
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Title</th>
                            <th>Deleted By</th>
                            <th>Deleted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($note = $deletedResult->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="file-icon"><?= getFileIcon($note['file_type']) ?></span>
                                    <span class="file-info">.<?= htmlspecialchars($note['file_type']) ?></span>
                                </td>
                                <td><strong><?= htmlspecialchars($note['title']) ?></strong></td>
                                <td><?= htmlspecialchars($note['deleter_name'] ?? 'Unknown') ?></td>
                                <td><?= date('M d, Y H:i', strtotime($note['deleted_at'])) ?></td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="note_id" value="<?= $note['note_id'] ?>">
                                            <button type="submit" class="btn btn-success">♻️ Restore</button>
                                        </form>
                                        <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Permanently delete this note? This cannot be undone!')">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="action" value="permanent_delete">
                                                <input type="hidden" name="note_id" value="<?= $note['note_id'] ?>">
                                                <button type="submit" class="btn btn-danger">❌ Delete Forever</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit Note</h2>
                <button class="close-modal" onclick="closeEditModal()">×</button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="note_id" id="edit_note_id">
                
                <div class="form-group">
                    <label for="edit_title">Title *</label>
                    <input type="text" id="edit_title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_class_id">Class</label>
                    <select id="edit_class_id" name="class_id" onchange="loadBooksForEdit(this.value)">
                        <option value="0">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_book_id">Book</label>
                    <select id="edit_book_id" name="book_id" onchange="loadChaptersForEdit()">
                        <option value="0">All Books</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_chapter_id">Chapter</label>
                    <select id="edit_chapter_id" name="chapter_id">
                        <option value="0">All Chapters</option>
                    </select>
                </div>
                
                <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                    <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if (tab === 'active') {
                document.querySelector('.tab:first-child').classList.add('active');
                document.getElementById('active-tab').classList.add('active');
            } else {
                document.querySelector('.tab:last-child').classList.add('active');
                document.getElementById('deleted-tab').classList.add('active');
            }
        }
        
        function loadBooks(classId) {
            const bookSelect = document.getElementById('book_id');
            const chapterSelect = document.getElementById('chapter_id');
            
            bookSelect.innerHTML = '<option value="0">Loading...</option>';
            chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
            
            if (classId == 0) {
                bookSelect.innerHTML = '<option value="0">All Books</option>';
                return;
            }
            
            fetch(`../quiz/quiz_data.php?type=books&class_id=${classId}`)
                .then(response => response.json())
                .then(books => {
                    bookSelect.innerHTML = '<option value="0">All Books</option>';
                    books.forEach(book => {
                        const option = document.createElement('option');
                        option.value = book.book_id;
                        option.textContent = book.book_name;
                        bookSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading books:', error);
                    bookSelect.innerHTML = '<option value="0">Error loading books</option>';
                });
        }
        
        function loadChapters() {
            const classId = document.getElementById('class_id').value;
            const bookId = document.getElementById('book_id').value;
            const chapterSelect = document.getElementById('chapter_id');
            
            chapterSelect.innerHTML = '<option value="0">Loading...</option>';
            
            if (bookId == 0 || classId == 0) {
                chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
                return;
            }
            
            fetch(`../quiz/quiz_data.php?type=chapters&class_id=${classId}&book_id=${bookId}`)
                .then(response => response.json())
                .then(chapters => {
                    chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
                    chapters.forEach(chapter => {
                        const option = document.createElement('option');
                        option.value = chapter.chapter_id;
                        option.textContent = chapter.chapter_name;
                        chapterSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading chapters:', error);
                    chapterSelect.innerHTML = '<option value="0">Error loading chapters</option>';
                });
        }
        
        function editNote(noteId) {
            // Fetch note data via AJAX
            fetch(`get_note.php?note_id=${noteId}`)
                .then(response => response.json())
                .then(note => {
                    document.getElementById('edit_note_id').value = note.note_id;
                    document.getElementById('edit_title').value = note.title;
                    document.getElementById('edit_description').value = note.description || '';
                    document.getElementById('edit_class_id').value = note.class_id || 0;
                    
                    if (note.class_id) {
                        loadBooksForEdit(note.class_id, note.book_id, note.chapter_id);
                    }
                    
                    document.getElementById('editModal').classList.add('active');
                })
                .catch(error => {
                    console.error('Error loading note:', error);
                    alert('Failed to load note data');
                });
        }
        
        function loadBooksForEdit(classId, selectedBookId = null, selectedChapterId = null) {
            const bookSelect = document.getElementById('edit_book_id');
            const chapterSelect = document.getElementById('edit_chapter_id');
            
            bookSelect.innerHTML = '<option value="0">Loading...</option>';
            chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
            
            if (classId == 0) {
                bookSelect.innerHTML = '<option value="0">All Books</option>';
                return;
            }
            
            fetch(`../quiz/quiz_data.php?type=books&class_id=${classId}`)
                .then(response => response.json())
                .then(books => {
                    bookSelect.innerHTML = '<option value="0">All Books</option>';
                    books.forEach(book => {
                        const option = document.createElement('option');
                        option.value = book.book_id;
                        option.textContent = book.book_name;
                        if (selectedBookId && book.book_id == selectedBookId) {
                            option.selected = true;
                        }
                        bookSelect.appendChild(option);
                    });
                    
                    if (selectedBookId) {
                        loadChaptersForEdit(selectedChapterId);
                    }
                });
        }
        
        function loadChaptersForEdit(selectedChapterId = null) {
            const classId = document.getElementById('edit_class_id').value;
            const bookId = document.getElementById('edit_book_id').value;
            const chapterSelect = document.getElementById('edit_chapter_id');
            
            chapterSelect.innerHTML = '<option value="0">Loading...</option>';
            
            if (bookId == 0 || classId == 0) {
                chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
                return;
            }
            
            fetch(`../quiz/quiz_data.php?type=chapters&class_id=${classId}&book_id=${bookId}`)
                .then(response => response.json())
                .then(chapters => {
                    chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
                    chapters.forEach(chapter => {
                        const option = document.createElement('option');
                        option.value = chapter.chapter_id;
                        option.textContent = chapter.chapter_name;
                        if (selectedChapterId && chapter.chapter_id == selectedChapterId) {
                            option.selected = true;
                        }
                        chapterSelect.appendChild(option);
                    });
                });
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>

<?php
function getFileIcon($fileType) {
    $icons = [
        'pdf' => '📄',
        'ppt' => '📊',
        'pptx' => '📊',
        'doc' => '📝',
        'docx' => '📝',
        'txt' => '📃',
        'png' => '🖼️',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'gif' => '🖼️',
        'webp' => '🖼️'
    ];
    return $icons[$fileType] ?? '📎';
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
