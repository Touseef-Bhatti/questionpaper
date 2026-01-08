# Uploaded Notes Management System

## Overview
A secure file upload and management system for educational notes with role-based access control, soft delete functionality, and comprehensive security measures.

## Features

### Admin Features (`admin/manage_notes.php`)
- **Secure File Upload**: Upload PDF, PPT, PPTX, DOC, DOCX, TXT, PNG, JPG, JPEG, GIF, WEBP files
- **File Size Limit**: Maximum 50MB per file
- **Categorization**: Associate notes with Class, Book, and Chapter
- **Edit Functionality**: Update note metadata (title, description, categories)
- **Soft Delete**: Move notes to trash instead of permanent deletion
- **Restore**: Recover notes from trash
- **Permanent Delete**: Super Admin only - permanently remove files from trash
- **File Preview**: View/download uploaded files
- **Search & Filter**: Find notes by various criteria

### Public Features (`notes/uploaded_notes.php`)
- **Browse Notes**: View all available study materials
- **Filter by Category**: Class, Book, Chapter
- **File Type Filter**: PDF, PPT, DOC, Images
- **Search**: Find notes by title or description
- **Download**: Download or view files directly
- **Responsive Design**: Mobile-friendly card layout
- **SEO Optimized**: Dynamic meta tags and structured data

## Security Features

### 1. File Upload Security
- **MIME Type Validation**: Verifies actual file content matches extension
- **Extension Whitelist**: Only allowed file types can be uploaded
- **File Size Limits**: Prevents resource exhaustion (50MB max)
- **Secure Filename Generation**: Uses `uniqid()` with timestamp to prevent collisions
- **Path Traversal Prevention**: Uses `basename()` to sanitize filenames
- **Upload Directory Isolation**: Files stored in dedicated `/uploads/notes/` directory

### 2. Access Control
- **Admin Authentication**: Required for all management operations
- **Role-Based Permissions**: 
  - Regular Admin: Upload, edit, soft delete, restore
  - Super Admin: All admin permissions + permanent delete
- **Session Management**: Automatic timeout after 30 minutes of inactivity
- **CSRF Protection**: All forms use CSRF tokens

### 3. Database Security
- **Prepared Statements**: All queries use parameterized statements
- **Input Sanitization**: `sanitizeInput()` function for all user inputs
- **Integer Validation**: `validateInt()` for numeric inputs
- **SQL Injection Prevention**: No raw SQL with user input
- **Soft Delete**: Files marked as deleted, not immediately removed

### 4. Data Validation
```php
// File type validation
$allowedTypes = [
    'pdf' => ['application/pdf'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    // ... etc
];

// MIME type verification
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $fileTmpPath);
if (!in_array($mimeType, $allowedTypes[$fileExtension])) {
    // Reject file
}
```

### 5. Audit Trail
- **Action Logging**: All admin actions logged via `logAdminAction()`
- **Metadata Tracking**: 
  - `uploaded_by`: Who uploaded the file
  - `deleted_by`: Who deleted the file
  - `created_at`, `updated_at`, `deleted_at`: Timestamps
- **IP Address Logging**: Stored in admin logs

### 6. Error Handling
- **Graceful Failures**: User-friendly error messages
- **File Cleanup**: Removes uploaded file if database insert fails
- **Transaction Safety**: Database operations validated before file operations

## Database Schema

```sql
CREATE TABLE `uploaded_notes` (
    `note_id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `file_name` VARCHAR(255) NOT NULL,           -- Stored filename
    `original_file_name` VARCHAR(255) NOT NULL,  -- Original upload name
    `file_path` VARCHAR(500) NOT NULL,           -- Relative path
    `file_type` VARCHAR(50) NOT NULL,            -- Extension
    `file_size` BIGINT NOT NULL,                 -- Bytes
    `mime_type` VARCHAR(100) NOT NULL,           -- Verified MIME type
    `class_id` INT DEFAULT NULL,
    `book_id` INT DEFAULT NULL,
    `chapter_id` INT DEFAULT NULL,
    `uploaded_by` INT NOT NULL,                  -- Admin ID
    `is_deleted` TINYINT(1) DEFAULT 0,           -- Soft delete flag
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_class_book_chapter` (`class_id`, `book_id`, `chapter_id`),
    INDEX `idx_is_deleted` (`is_deleted`),
    INDEX `idx_uploaded_by` (`uploaded_by`)
);
```

## File Structure

```
/admin/
  ├── manage_notes.php      # Admin interface for file management
  ├── get_note.php          # API endpoint for fetching note data
  └── security.php          # Security helper functions

/notes/
  └── uploaded_notes.php    # Public-facing notes display page

/uploads/
  └── notes/                # File storage directory (auto-created)
      └── note_*.pdf/ppt/etc
```

## Usage

### For Admins

1. **Upload a Note**:
   - Navigate to Admin Dashboard → Manage Notes
   - Fill in title (required) and description (optional)
   - Select file (max 50MB)
   - Optionally categorize by Class/Book/Chapter
   - Click "Upload Note"

2. **Edit a Note**:
   - Click "Edit" button on any note
   - Update title, description, or categories
   - Click "Save Changes"

3. **Delete a Note**:
   - Click "Delete" button (moves to trash)
   - Note appears in "Recently Deleted" tab
   - Can be restored or permanently deleted

4. **Restore a Note**:
   - Switch to "Recently Deleted" tab
   - Click "Restore" button

5. **Permanent Delete** (Super Admin only):
   - Switch to "Recently Deleted" tab
   - Click "Delete Forever" button
   - Confirms before permanent removal

### For Students

1. Visit `/notes/uploaded_notes.php`
2. Use filters to find specific notes:
   - Select Class, Book, Chapter
   - Choose file type (PDF, PPT, DOC, Images)
   - Use search box for keywords
3. Click "Download / View" to access the file

## Security Best Practices Implemented

✅ **Input Validation**: All user inputs validated and sanitized
✅ **Output Encoding**: All outputs HTML-escaped to prevent XSS
✅ **CSRF Protection**: Tokens on all state-changing operations
✅ **File Type Verification**: MIME type checked, not just extension
✅ **Path Traversal Prevention**: Secure file path handling
✅ **Access Control**: Role-based permissions enforced
✅ **Audit Logging**: All actions tracked with timestamps
✅ **Soft Delete**: Prevents accidental data loss
✅ **Session Security**: Automatic timeout and validation
✅ **SQL Injection Prevention**: Prepared statements only
✅ **Error Handling**: No sensitive information in error messages
✅ **File Size Limits**: Prevents DoS attacks
✅ **Secure File Storage**: Files stored outside web root when possible

## Configuration

### Allowed File Types
Edit `$allowedTypes` array in `manage_notes.php`:
```php
$allowedTypes = [
    'extension' => ['mime/type1', 'mime/type2'],
    // Add more as needed
];
```

### File Size Limit
Edit `$maxFileSize` in `manage_notes.php`:
```php
$maxFileSize = 50 * 1024 * 1024; // 50MB
```

### Upload Directory
Files are stored in `/uploads/notes/` by default. To change:
```php
$uploadDir = __DIR__ . '/../uploads/notes/';
```

## Troubleshooting

### "Failed to move uploaded file"
- Check directory permissions: `chmod 755 uploads/notes/`
- Ensure directory exists and is writable
- Check PHP `upload_max_filesize` and `post_max_size` in php.ini

### "File type not allowed"
- Verify file extension is in allowed list
- Check MIME type matches expected type
- Some files may have multiple valid MIME types

### "Database error"
- Run the CREATE TABLE query to ensure table exists
- Check database connection in `db_connect.php`
- Verify admin_id exists in admins table

## Future Enhancements

- [ ] Virus scanning integration (ClamAV)
- [ ] File versioning system
- [ ] Bulk upload functionality
- [ ] Download statistics tracking
- [ ] File preview without download
- [ ] Automatic file compression
- [ ] CDN integration for large files
- [ ] Advanced search with full-text indexing

## License
Part of Ahmad Learning Hub educational platform.
