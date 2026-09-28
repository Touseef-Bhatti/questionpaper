<?php
/**
 * Reconciles the managed Google Drive folder structure with local metadata.
 * Files themselves remain in Google Drive; only searchable metadata is stored.
 */

require_once __DIR__ . '/GoogleDriveService.php';

class GoogleDriveContentSyncService
{
    private const CLASS_FOLDER_PATTERN = '/^class[\s_-]*(9|10|11|12)$/i';

    /**
     * @return array<string,mixed>
     */
    public function sync(mysqli $conn, int $adminId): array
    {
        $this->ensureClassNotesSchema($conn);
        $this->ensureBookUploadsSchema($conn);
        $this->ensureLegacyNotesSchema($conn);

        $drive = new GoogleDriveService();
        $files = $drive->listManagedFiles();
        $classes = $this->loadClasses($conn);
        $result = [
            'ok' => true,
            'scanned_files' => count($files),
            'notes_imported' => 0,
            'notes_updated' => 0,
            'books_imported' => 0,
            'books_updated' => 0,
            'books_created' => 0,
            'notes_missing' => 0,
            'legacy_notes_missing' => 0,
            'books_missing' => 0,
            'skipped_files' => 0,
            'warnings' => [],
        ];
        $noteFileIds = [];
        $bookFileIds = [];

        foreach ($files as $file) {
            $path = is_array($file['path_segments'] ?? null) ? $file['path_segments'] : [];
            if ($this->isNotesFile($path)) {
                $noteFileIds[] = (string) $file['file_id'];
                $outcome = $this->syncNote($conn, $file, $adminId, $classes);
                if ($outcome === 'imported') {
                    $result['notes_imported']++;
                } elseif ($outcome === 'updated') {
                    $result['notes_updated']++;
                } else {
                    $result['skipped_files']++;
                }
                continue;
            }

            if ($this->isBookFile($path, (string) ($file['mime_type'] ?? ''), (string) ($file['name'] ?? ''))) {
                $bookFileIds[] = (string) $file['file_id'];
                $outcome = $this->syncBook($conn, $file, $adminId, $classes);
                if ($outcome === 'imported') {
                    $result['books_imported']++;
                } elseif ($outcome === 'updated') {
                    $result['books_updated']++;
                } elseif ($outcome === 'book_created') {
                    $result['books_created']++;
                    $result['books_imported']++;
                } else {
                    $result['skipped_files']++;
                }
                continue;
            }

            $result['skipped_files']++;
        }

        // A successful Drive scan is the source of truth. Keep missing records
        // visible to an administrator so they can delete or replace them.
        $uniqueNoteFileIds = array_values(array_unique($noteFileIds));
        $result['notes_missing'] = $this->markMissingNotes($conn, $uniqueNoteFileIds);
        $result['legacy_notes_missing'] = $this->markMissingLegacyNotes($conn, $uniqueNoteFileIds);
        $result['books_missing'] = $this->markMissingBooks($conn, array_values(array_unique($bookFileIds)));

        if ($result['scanned_files'] === 0) {
            $result['warnings'][] = 'No files were found below the configured AhmadLearningHub root. Existing Drive-managed records were marked as deleted from Drive for administrator review.';
        }

        return $result;
    }

    /**
     * @return array<int,array{class_id:int,class_name:string}>
     */
    private function loadClasses(mysqli $conn): array
    {
        $classes = [];
        $res = $conn->query('SELECT class_id, class_name FROM class ORDER BY class_id ASC');
        while ($res && ($row = $res->fetch_assoc())) {
            $classes[] = [
                'class_id' => (int) $row['class_id'],
                'class_name' => (string) $row['class_name'],
            ];
        }
        return $classes;
    }

    /**
     * @param string[] $path
     */
    private function isNotesFile(array $path): bool
    {
        $root = strtolower(trim((string) ($path[0] ?? '')));
        $isLegacyBooksNote = $root === 'books';
        $isNotesRoot = $root === 'notes';
        return isset($path[0], $path[1], $path[2])
            && ($isNotesRoot || $isLegacyBooksNote)
            && $this->classNumber((string) $path[1]) !== null
            && trim((string) $path[2]) !== ''
            && isset($path[3], $path[4])
            && !$this->isOwnerFolder((string) $path[3])
            && $this->isOwnerFolder((string) $path[4]);
    }

    /**
     * @param string[] $path
     */
    private function isBookFile(array $path, string $mimeType, string $fileName): bool
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return isset($path[0], $path[1], $path[2])
            && strcasecmp((string) $path[0], 'Books') === 0
            && $this->classNumber((string) $path[1]) !== null
            && trim((string) $path[2]) !== ''
            && count($path) === 3
            && ($mimeType === 'application/pdf' || $extension === 'pdf');
    }

    private function classNumber(string $folderName): ?string
    {
        if (!preg_match(self::CLASS_FOLDER_PATTERN, trim($folderName), $matches)) {
            return null;
        }
        return (string) $matches[1];
    }

    private function isOwnerFolder(string $folderName): bool
    {
        $folderName = strtolower(trim($folderName));
        return $folderName === 'admin' || $folderName === 'user' || $folderName === 'users';
    }

    /**
     * @param array<string,mixed> $file
     * @param array<int,array{class_id:int,class_name:string}> $classes
     */
    private function syncNote(mysqli $conn, array $file, int $adminId, array $classes): string
    {
        $path = $file['path_segments'];
        $classNumber = $this->classNumber((string) $path[1]);
        $classId = $this->resolveClassId($classNumber, $classes);
        if ($classId === null) {
            return 'skipped';
        }

        $subject = trim((string) $path[2]);
        $chapter = '';
        $ownerIndex = null;
        if (isset($path[3])) {
            $chapter = trim((string) $path[3]);
            if (strcasecmp($chapter, 'General') === 0) {
                $chapter = '';
            }
            $ownerIndex = isset($path[4]) ? 4 : null;
        }
        $ownerFolder = $ownerIndex !== null ? strtolower(trim((string) $path[$ownerIndex])) : 'admin';
        $uploaderType = $ownerFolder === 'user' || $ownerFolder === 'users' ? 'user' : 'admin';
        $isAdmin = $uploaderType === 'admin';
        $fileId = (string) $file['file_id'];
        $fileName = (string) $file['name'];
        $driveUrl = $this->driveUrl($file);

        $existingStmt = $conn->prepare('SELECT id, chapter FROM class_notes WHERE drive_file_id = ? LIMIT 1');
        $existingStmt->bind_param('s', $fileId);
        $existingStmt->execute();
        $existing = $existingStmt->get_result()->fetch_assoc();
        $existingStmt->close();

        if ($existing) {
            $storedChapter = $chapter;
            if ($storedChapter === '' && isset($path[3]) && $this->isOwnerFolder((string) $path[3])) {
                $storedChapter = (string) ($existing['chapter'] ?? '');
            }
            $stmt = $conn->prepare("UPDATE class_notes
                SET subject = ?, class = ?, chapter = ?, drive_url = ?, original_filename = ?, mime_type = ?, file_size = ?, drive_status = 'available', drive_deleted_at = NULL
                WHERE id = ?");
            $classValue = (string) $classNumber;
            $mimeType = (string) ($file['mime_type'] ?? 'application/octet-stream');
            $size = (int) ($file['size'] ?? 0);
            $noteId = (int) $existing['id'];
            $stmt->bind_param('ssssssii', $subject, $classValue, $storedChapter, $driveUrl, $fileName, $mimeType, $size, $noteId);
            $stmt->execute();
            $stmt->close();
            return 'updated';
        }

        $title = $this->titleFromFilename($fileName);
        $description = 'Imported from Google Drive: ' . (string) ($file['relative_path'] ?? $fileName);
        $mimeType = (string) ($file['mime_type'] ?? 'application/octet-stream');
        $size = (int) ($file['size'] ?? 0);
        $status = $isAdmin ? 'approved' : 'pending';
        $uploadedBy = max(0, $adminId);
        $uploaderName = $isAdmin ? 'Google Drive import' : 'Google Drive user import';
        $uploaderEmail = '';
        $approvedBy = $isAdmin ? max(0, $adminId) : 0;
        $approvedAtSql = $isAdmin ? 'NOW()' : 'NULL';
        $classValue = (string) $classNumber;

        $stmt = $conn->prepare("INSERT INTO class_notes
            (title, description, subject, class, chapter, drive_file_id, drive_url, original_filename, mime_type, file_size, status, uploaded_by, uploader_name, uploader_email, uploader_type, approved_at, approved_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {$approvedAtSql}, ?)");
        $stmt->bind_param(
            'sssssssssisisssi',
            $title,
            $description,
            $subject,
            $classValue,
            $chapter,
            $fileId,
            $driveUrl,
            $fileName,
            $mimeType,
            $size,
            $status,
            $uploadedBy,
            $uploaderName,
            $uploaderEmail,
            $uploaderType,
            $approvedBy
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? 'imported' : 'skipped';
    }

    /**
     * @param array<string,mixed> $file
     * @param array<int,array{class_id:int,class_name:string}> $classes
     */
    private function syncBook(mysqli $conn, array $file, int $adminId, array $classes): string
    {
        $path = $file['path_segments'];
        $classNumber = $this->classNumber((string) $path[1]);
        $classId = $this->resolveClassId($classNumber, $classes);
        if ($classId === null) {
            return 'skipped';
        }

        $bookName = trim((string) $path[2]);
        $book = $this->findOrCreateBook($conn, $classId, $bookName);
        if (!$book) {
            return 'skipped';
        }

        $fileId = (string) $file['file_id'];
        $fileName = (string) $file['name'];
        $driveUrl = $this->driveUrl($file);
        $mimeType = (string) ($file['mime_type'] ?? 'application/pdf');
        $size = (int) ($file['size'] ?? 0);
        $folderId = (string) ($file['folder_id'] ?? '');

        $existingStmt = $conn->prepare('SELECT id FROM book_uploads WHERE drive_file_id = ? LIMIT 1');
        $existingStmt->bind_param('s', $fileId);
        $existingStmt->execute();
        $existing = $existingStmt->get_result()->fetch_assoc();
        $existingStmt->close();

        if ($existing) {
            $stmt = $conn->prepare("UPDATE book_uploads
                SET class_id = ?, book_id = ?, drive_url = ?, drive_folder_id = ?, local_pdf_path = '', original_filename = ?, mime_type = ?, file_size = ?, status = 'active', drive_status = 'available', drive_deleted_at = NULL
                WHERE id = ?");
            $uploadId = (int) $existing['id'];
            $stmt->bind_param('iissssii', $classId, $book['book_id'], $driveUrl, $folderId, $fileName, $mimeType, $size, $uploadId);
            $stmt->execute();
            $stmt->close();
            return 'updated';
        }

        $emptyLocalPath = '';
        $pdfPageCount = 0;
        $pageOffset = 0;
        $uploadedBy = max(0, $adminId);
        $stmt = $conn->prepare(
            'INSERT INTO book_uploads (class_id, book_id, drive_file_id, drive_url, drive_folder_id, local_pdf_path, original_filename, mime_type, file_size, pdf_page_count, page_offset, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('iissssssiiii', $classId, $book['book_id'], $fileId, $driveUrl, $folderId, $emptyLocalPath, $fileName, $mimeType, $size, $pdfPageCount, $pageOffset, $uploadedBy);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? ($book['created'] ? 'book_created' : 'imported') : 'skipped';
    }

    /**
     * @param array<int,array{class_id:int,class_name:string}> $classes
     */
    private function resolveClassId(?string $classNumber, array $classes): ?int
    {
        if ($classNumber === null) {
            return null;
        }
        foreach ($classes as $class) {
            if ((string) $class['class_id'] === $classNumber || preg_match('/(?<!\d)' . preg_quote($classNumber, '/') . '(?!\d)/', $class['class_name'])) {
                return (int) $class['class_id'];
            }
        }
        return null;
    }

    /**
     * @return array{book_id:int,book_name:string,created:bool}|null
     */
    private function findOrCreateBook(mysqli $conn, int $classId, string $bookName): ?array
    {
        $stmt = $conn->prepare('SELECT book_id, book_name FROM book WHERE class_id = ? AND LOWER(book_name) = LOWER(?) LIMIT 1');
        $stmt->bind_param('is', $classId, $bookName);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($book) {
            return ['book_id' => (int) $book['book_id'], 'book_name' => (string) $book['book_name'], 'created' => false];
        }

        $stmt = $conn->prepare('INSERT INTO book (book_name, class_id) VALUES (?, ?)');
        $stmt->bind_param('si', $bookName, $classId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $bookId = (int) $stmt->insert_id;
        $stmt->close();
        return ['book_id' => $bookId, 'book_name' => $bookName, 'created' => true];
    }

    private function titleFromFilename(string $fileName): string
    {
        $title = pathinfo($fileName, PATHINFO_FILENAME);
        $title = preg_replace('/[_-]+/', ' ', $title);
        $title = preg_replace('/\s+/', ' ', (string) $title);
        $title = trim((string) $title);
        return $title !== '' ? $title : 'Drive note';
    }

    /**
     * @param array<string,mixed> $file
     */
    private function driveUrl(array $file): string
    {
        $url = trim((string) ($file['web_url'] ?? ''));
        return $url !== '' ? $url : 'https://drive.google.com/file/d/' . rawurlencode((string) $file['file_id']) . '/view';
    }

    /** @param string[] $driveFileIds */
    private function markMissingNotes(mysqli $conn, array $driveFileIds): int
    {
        $rows = $this->selectMissingRows($conn, 'class_notes', 'id', $driveFileIds);
        $stmt = $conn->prepare("UPDATE class_notes SET drive_status = 'missing', drive_deleted_at = COALESCE(drive_deleted_at, NOW()) WHERE id = ?");
        $missing = 0;
        foreach ($rows as $row) {
            $noteId = (int) $row['id'];
            if ($stmt) {
                $stmt->bind_param('i', $noteId);
                $stmt->execute();
            }
            $missing++;
        }
        if ($stmt) {
            $stmt->close();
        }
        return $missing;
    }

    /** @param string[] $driveFileIds */
    private function markMissingLegacyNotes(mysqli $conn, array $driveFileIds): int
    {
        if (!$this->tableExists($conn, 'uploaded_notes')) {
            return 0;
        }

        $rows = $this->selectMissingRows($conn, 'uploaded_notes', 'note_id', $driveFileIds);
        $stmt = $conn->prepare("UPDATE uploaded_notes SET drive_status = 'missing', drive_deleted_at = COALESCE(drive_deleted_at, NOW()) WHERE note_id = ?");
        $missing = 0;
        foreach ($rows as $row) {
            $noteId = (int) $row['note_id'];
            if ($stmt) {
                $stmt->bind_param('i', $noteId);
                $stmt->execute();
            }
            $missing++;
        }
        if ($stmt) {
            $stmt->close();
        }
        return $missing;
    }

    /** @param string[] $driveFileIds */
    private function markMissingBooks(mysqli $conn, array $driveFileIds): int
    {
        $rows = $this->selectMissingRows($conn, 'book_uploads', 'id', $driveFileIds);
        $stmt = $conn->prepare("UPDATE book_uploads SET drive_status = 'missing', drive_deleted_at = COALESCE(drive_deleted_at, NOW()) WHERE id = ?");
        $missing = 0;
        foreach ($rows as $row) {
            $uploadId = (int) $row['id'];
            if ($stmt) {
                $stmt->bind_param('i', $uploadId);
                $stmt->execute();
            }
            $missing++;
        }
        if ($stmt) {
            $stmt->close();
        }
        return $missing;
    }

    /**
     * Return records which have a Drive ID but whose ID was not returned by a
     * successful, non-trashed Drive scan.
     *
     * @return array<int,array<string,mixed>>
     * @param string[] $driveFileIds
     */
    private function selectMissingRows(mysqli $conn, string $table, string $columns, array $driveFileIds): array
    {
        $sql = "SELECT {$columns} FROM {$table} WHERE drive_file_id <> ''";
        if ($driveFileIds !== []) {
            $placeholders = implode(',', array_fill(0, count($driveFileIds), '?'));
            $sql .= ' AND drive_file_id NOT IN (' . $placeholders . ')';
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($driveFileIds !== []) {
            $types = str_repeat('s', count($driveFileIds));
            $params = [$types];
            foreach ($driveFileIds as $key => $value) {
                $params[] = &$driveFileIds[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $params);
        }
        $stmt->execute();
        $rows = [];
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    private function tableExists(mysqli $conn, string $table): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }

    private function prepareIfTableExists(mysqli $conn, string $table, string $sql): ?mysqli_stmt
    {
        if (!$this->tableExists($conn, $table)) {
            return null;
        }
        $stmt = $conn->prepare($sql);
        return $stmt instanceof mysqli_stmt ? $stmt : null;
    }

    private function removeLegacyLocalNote(string $path): void
    {
        $this->removeFileInsideDirectory($path, dirname(__DIR__) . '/uploads/notes');
    }

    private function removeLegacyLocalPdf(string $path): void
    {
        $this->removeFileInsideDirectory($path, dirname(__DIR__) . '/storage/book_uploads');
    }

    private function removeFileInsideDirectory(string $path, string $allowedDirectory): void
    {
        $path = trim($path);
        if ($path === '') {
            return;
        }

        $storageRoot = realpath($allowedDirectory);
        if ($storageRoot === false) {
            return;
        }

        $candidate = $path;
        if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $candidate)) {
            $candidate = dirname(__DIR__) . '/' . ltrim($candidate, "\\/");
        }
        $resolved = realpath($candidate);
        if ($resolved === false) {
            return;
        }

        $root = rtrim(strtolower(str_replace('\\', '/', $storageRoot)), '/');
        $file = strtolower(str_replace('\\', '/', $resolved));
        if ($file !== $root && strpos($file, $root . '/') !== 0) {
            return;
        }
        if (is_file($resolved)) {
            @unlink($resolved);
        }
    }

    private function ensureClassNotesSchema(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS class_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            subject VARCHAR(100),
            class ENUM('9','10','11','12') NOT NULL,
            chapter VARCHAR(255),
            drive_file_id VARCHAR(255) NOT NULL,
            drive_url VARCHAR(500) NOT NULL,
            original_filename VARCHAR(255),
            mime_type VARCHAR(100) NOT NULL,
            file_size BIGINT DEFAULT 0,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            uploaded_by INT DEFAULT NULL,
            uploader_name VARCHAR(255),
            uploader_email VARCHAR(255) DEFAULT NULL,
            uploader_type ENUM('admin','user') DEFAULT 'user',
            rejection_reason TEXT,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_at DATETIME DEFAULT NULL,
            approved_by INT DEFAULT NULL,
            drive_status ENUM('available','missing') NOT NULL DEFAULT 'available',
            drive_deleted_at DATETIME DEFAULT NULL,
            INDEX idx_class (class),
            INDEX idx_status (status),
            INDEX idx_drive_status (drive_status),
            INDEX idx_subject (subject),
            INDEX idx_class_status (class, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $emailColumnCheck = $conn->query("SHOW COLUMNS FROM class_notes LIKE 'uploader_email'");
        if (!$emailColumnCheck || $emailColumnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE class_notes ADD COLUMN uploader_email VARCHAR(255) DEFAULT NULL AFTER uploader_name");
        }

        $driveStatusColumnCheck = $conn->query("SHOW COLUMNS FROM class_notes LIKE 'drive_status'");
        if (!$driveStatusColumnCheck || $driveStatusColumnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE class_notes ADD COLUMN drive_status ENUM('available','missing') NOT NULL DEFAULT 'available' AFTER approved_by");
        }
        $driveDeletedAtColumnCheck = $conn->query("SHOW COLUMNS FROM class_notes LIKE 'drive_deleted_at'");
        if (!$driveDeletedAtColumnCheck || $driveDeletedAtColumnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE class_notes ADD COLUMN drive_deleted_at DATETIME DEFAULT NULL AFTER drive_status");
        }
    }

    private function ensureBookUploadsSchema(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS book_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            book_id INT NOT NULL,
            drive_file_id VARCHAR(255) NOT NULL,
            drive_url VARCHAR(500) NOT NULL,
            drive_folder_id VARCHAR(255) DEFAULT NULL,
            local_pdf_path VARCHAR(500) NOT NULL DEFAULT '',
            original_filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
            file_size BIGINT DEFAULT 0,
            pdf_page_count INT NOT NULL DEFAULT 0,
            page_offset INT NOT NULL DEFAULT 0,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            drive_status ENUM('available','missing') NOT NULL DEFAULT 'available',
            drive_deleted_at DATETIME DEFAULT NULL,
            uploaded_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_book_uploads_book (class_id, book_id, status),
            INDEX idx_book_uploads_drive_status (drive_status),
            INDEX idx_book_uploads_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Older installations may already have book_uploads without the Drive
        // lifecycle columns. Add them before any sync query references them.
        $driveStatusColumnCheck = $conn->query("SHOW COLUMNS FROM book_uploads LIKE 'drive_status'");
        if (!$driveStatusColumnCheck || $driveStatusColumnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE book_uploads ADD COLUMN drive_status ENUM('available','missing') NOT NULL DEFAULT 'available' AFTER status");
        }

        $driveDeletedAtColumnCheck = $conn->query("SHOW COLUMNS FROM book_uploads LIKE 'drive_deleted_at'");
        if (!$driveDeletedAtColumnCheck || $driveDeletedAtColumnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE book_uploads ADD COLUMN drive_deleted_at DATETIME DEFAULT NULL AFTER drive_status");
        }
    }

    private function ensureLegacyNotesSchema(mysqli $conn): void
    {
        if (!$this->tableExists($conn, 'uploaded_notes')) {
            return;
        }

        $driveFileColumnCheck = $conn->query("SHOW COLUMNS FROM uploaded_notes LIKE 'drive_file_id'");
        if (!$driveFileColumnCheck || $driveFileColumnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE uploaded_notes ADD COLUMN drive_file_id VARCHAR(255) DEFAULT NULL AFTER file_path");
        }

        $driveUrlColumnCheck = $conn->query("SHOW COLUMNS FROM uploaded_notes LIKE 'drive_url'");
        if (!$driveUrlColumnCheck || $driveUrlColumnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE uploaded_notes ADD COLUMN drive_url VARCHAR(500) DEFAULT NULL AFTER drive_file_id");
        }

        $driveStatusColumnCheck = $conn->query("SHOW COLUMNS FROM uploaded_notes LIKE 'drive_status'");
        if (!$driveStatusColumnCheck || $driveStatusColumnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE uploaded_notes ADD COLUMN drive_status ENUM('available','missing') NOT NULL DEFAULT 'available' AFTER drive_url");
        }
        $driveDeletedAtColumnCheck = $conn->query("SHOW COLUMNS FROM uploaded_notes LIKE 'drive_deleted_at'");
        if (!$driveDeletedAtColumnCheck || $driveDeletedAtColumnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE uploaded_notes ADD COLUMN drive_deleted_at DATETIME DEFAULT NULL AFTER drive_status");
        }
    }
}
