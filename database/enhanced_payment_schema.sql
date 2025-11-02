-- Enhanced Payment System Schema
-- Adds refund management, logging, and retry tracking

-- Add missing columns to payments table
ALTER TABLE payments ADD COLUMN IF NOT EXISTS retry_count INT DEFAULT 0;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS last_retry_at TIMESTAMP NULL;
ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'expired', 'refunded') DEFAULT 'pending';

-- Payment Refunds Table
CREATE TABLE IF NOT EXISTS payment_refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    reason TEXT,
    admin_user_id INT,
    status ENUM('pending', 'processed', 'failed') DEFAULT 'pending',
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    INDEX idx_payment_id (payment_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment Logs Table for Enhanced Tracking
CREATE TABLE IF NOT EXISTS payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event VARCHAR(100) NOT NULL,
    user_id INT,
    order_id VARCHAR(100),
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event),
    INDEX idx_user_id (user_id),
    INDEX idx_order_id (order_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate Limiting Table for Webhook Protection
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    request_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ip_endpoint (ip_address, endpoint),
    INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment Alerts Table
CREATE TABLE IF NOT EXISTS payment_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
    data JSON,
    is_resolved TINYINT(1) DEFAULT 0,
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_is_resolved (is_resolved),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enhanced Payment Statistics View
CREATE OR REPLACE VIEW payment_summary AS
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_payments,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_payments,
    COUNT(CASE WHEN status = 'refunded' THEN 1 END) as refunded_payments,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as daily_revenue,
    AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_transaction_value,
    (COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*)) as success_rate
FROM payments 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Revenue Trends View
CREATE OR REPLACE VIEW revenue_trends AS
SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as monthly_revenue,
    COUNT(*) as total_attempts,
    (COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*)) as conversion_rate
FROM payments 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month DESC;

-- Popular Plans View
CREATE OR REPLACE VIEW popular_plans AS
SELECT 
    sp.id,
    sp.display_name,
    sp.price,
    COUNT(p.id) as total_purchases,
    COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as successful_purchases,
    SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as plan_revenue,
    (COUNT(CASE WHEN p.status = 'completed' THEN 1 END) * 100.0 / COUNT(p.id)) as conversion_rate
FROM subscription_plans sp
LEFT JOIN payments p ON sp.id = p.plan_id AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
WHERE sp.is_active = 1
GROUP BY sp.id, sp.display_name, sp.price
ORDER BY successful_purchases DESC;

-- Clean up old webhook logs (keep only last 30 days)
DELETE FROM payment_webhooks WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Clean up old payment logs (keep only last 90 days)  
DELETE FROM payment_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Daily Reports Table for Analytics
CREATE TABLE IF NOT EXISTS daily_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL UNIQUE,
    total_payments INT DEFAULT 0,
    successful_payments INT DEFAULT 0,
    daily_revenue DECIMAL(12, 2) DEFAULT 0.00,
    avg_transaction_value DECIMAL(10, 2) DEFAULT 0.00,
    plan_breakdown JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_report_date (report_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clean up old webhook logs (keep only last 30 days)
DELETE FROM payment_webhooks WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Clean up old payment logs (keep only last 90 days)  
DELETE FROM payment_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Clean up old rate limit records (keep only last 24 hours)
DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 24 HOUR);
