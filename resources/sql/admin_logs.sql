-- admin_logs.sql - Admin action logging table

CREATE TABLE IF NOT EXISTS admin_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  action VARCHAR(255) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_id (admin_id),
  INDEX idx_action (action),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key constraint if admins table exists
-- ALTER TABLE admin_logs ADD CONSTRAINT fk_admin_logs_admin_id 
-- FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE;
