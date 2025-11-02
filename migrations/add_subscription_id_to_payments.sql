-- Migration: Add subscription_id to payments table
-- This links payments to their resulting subscriptions

-- Add subscription_id column if it doesn't exist
ALTER TABLE payments 
ADD COLUMN subscription_id INT DEFAULT NULL;

-- Add index for better performance
ALTER TABLE payments 
ADD INDEX idx_payments_subscription_id (subscription_id);

-- Add status column values if not already present
ALTER TABLE payments 
MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'expired') DEFAULT 'pending';

-- Ensure updated_at column exists
ALTER TABLE payments 
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add tracker column for Safepay tracking
ALTER TABLE payments 
ADD COLUMN IF NOT EXISTS tracker VARCHAR(255) DEFAULT NULL;

-- Add safepay_token column if not exists
ALTER TABLE payments 
ADD COLUMN IF NOT EXISTS safepay_token VARCHAR(255) DEFAULT NULL;
