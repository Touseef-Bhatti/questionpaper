-- Subscription and Payment System Schema for Question Paper Generator
-- Similar to ChatGPT Plus subscription model

-- 1. Subscription Plans Table
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'PKR',
    duration_days INT NOT NULL, -- 30 for monthly, 365 for yearly
    max_papers_per_month INT DEFAULT -1, -- -1 for unlimited
    max_chapters_per_paper INT DEFAULT -1,
    max_questions_per_paper INT DEFAULT -1,
    features JSON, -- JSON array of features
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. User Subscriptions Table
CREATE TABLE IF NOT EXISTS user_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    status ENUM('active', 'inactive', 'expired', 'cancelled') DEFAULT 'active',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    auto_renew TINYINT(1) DEFAULT 1,
    papers_used_this_month INT DEFAULT 0,
    last_usage_reset DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT,
    INDEX idx_user_status (user_id, status),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Payments Table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    order_id VARCHAR(100) UNIQUE NOT NULL,
    safepay_token VARCHAR(255),
    tracker VARCHAR(255),
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'PKR',
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    safepay_response JSON, -- Store SafePay response
    webhook_data JSON, -- Store webhook data
    failure_reason TEXT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT,
    INDEX idx_user_id (user_id),
    INDEX idx_order_id (order_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Payment Webhooks Log Table
CREATE TABLE IF NOT EXISTS payment_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(100),
    webhook_type VARCHAR(50),
    payload JSON,
    signature VARCHAR(500),
    verified TINYINT(1) DEFAULT 0,
    processed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_verified (verified),
    INDEX idx_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Usage Tracking Table
CREATE TABLE IF NOT EXISTS usage_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id INT,
    action VARCHAR(100) NOT NULL, -- 'paper_generated', 'question_added', etc.
    resource_type VARCHAR(50), -- 'paper', 'question', etc.
    resource_id INT,
    metadata JSON, -- Additional data
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default subscription plans
INSERT INTO subscription_plans (name, display_name, description, price, duration_days, max_papers_per_month, max_chapters_per_paper, max_questions_per_paper, features, sort_order) VALUES
('free', 'Free Plan', 'Basic access to question paper generation', 0.00, 30, 5, 3, 20, '["Basic question paper generation", "Up to 5 papers per month", "Maximum 3 chapters per paper", "Maximum 20 questions per paper", "Email support"]', 1),
('premium', 'Premium Plan', 'Enhanced features for teachers and educators', 999.00, 30, 50, 10, 100, '["Unlimited question paper generation", "Up to 50 papers per month", "Maximum 10 chapters per paper", "Maximum 100 questions per paper", "Priority email support", "Export to DOCX", "Custom paper templates", "Question bank access"]', 2),
('pro', 'Pro Plan', 'Complete solution for educational institutions', 1999.00, 30, -1, -1, -1, '["Unlimited everything", "Unlimited papers per month", "Unlimited chapters per paper", "Unlimited questions per paper", "24/7 priority support", "Advanced analytics", "Multi-user management", "API access", "White-label solution", "Custom integrations"]', 3),
('yearly_premium', 'Premium Yearly', 'Enhanced features with yearly discount', 9990.00, 365, 50, 10, 100, '["All Premium features", "2 months free with yearly plan", "Priority support", "Early access to new features"]', 4),
('yearly_pro', 'Pro Yearly', 'Complete solution with yearly discount', 19990.00, 365, -1, -1, -1, '["All Pro features", "2 months free with yearly plan", "Dedicated account manager", "Custom training sessions"]', 5);

-- Add subscription_status column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS subscription_status ENUM('free', 'premium', 'pro') DEFAULT 'free';

-- Add subscription_expires_at column if it doesn't exist
-- This query was removed because it was causing SQL syntax errors

-- Create indexes for better performance
CREATE INDEX idx_users_subscription ON users(subscription_status, subscription_expires_at);

-- Create a view for active subscriptions
CREATE OR REPLACE VIEW active_subscriptions AS
SELECT 
    us.*,
    sp.name as plan_name,
    sp.display_name as plan_display_name,
    sp.features as plan_features,
    sp.max_papers_per_month,
    sp.max_chapters_per_paper,
    sp.max_questions_per_paper,
    u.name as user_name,
    u.email as user_email
FROM user_subscriptions us
JOIN subscription_plans sp ON us.plan_id = sp.id
JOIN users u ON us.user_id = u.id
WHERE us.status = 'active' AND (us.expires_at IS NULL OR us.expires_at > NOW());
