-- Migration: Create class_notes table
-- Stores metadata for notes uploaded by users and admins.
-- Actual files are stored on Google Drive; only the Drive file ID and URL are kept here.

CREATE TABLE IF NOT EXISTS class_notes (
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
    uploader_type ENUM('admin','user') DEFAULT 'user',
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_class (class),
    INDEX idx_status (status),
    INDEX idx_subject (subject),
    INDEX idx_class_status (class, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
