-- GreenBite feature update script
-- Run this in phpMyAdmin or MySQL before testing the updated PHP pages.
-- Existing tables are preserved. No tables are removed.

USE greenbite_db;

-- Step 1: Temporarily allow both old and new status values so old data can be migrated safely.
ALTER TABLE donations2
MODIFY status ENUM(
    'pending',
    'picked',
    'delivered',
    'Available',
    'Reserved',
    'Picked Up',
    'Delivered',
    'Expired',
    'Cancelled'
) DEFAULT 'Available';

-- Step 2: Convert old project status names into the new interview-friendly workflow labels.
UPDATE donations2 SET status = 'Available' WHERE status = 'pending';
UPDATE donations2 SET status = 'Picked Up' WHERE status = 'picked';
UPDATE donations2 SET status = 'Delivered' WHERE status = 'delivered';

-- Step 3: Keep only the final workflow values after old records are converted.
ALTER TABLE donations2
MODIFY status ENUM(
    'Available',
    'Reserved',
    'Picked Up',
    'Delivered',
    'Expired',
    'Cancelled'
) DEFAULT 'Available';

-- In-app notifications for donation accepted, delivered, cancelled, and nearby alerts.
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    donation_id INT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (donation_id),
    FOREIGN KEY (user_id) REFERENCES users3(id) ON DELETE CASCADE,
    FOREIGN KEY (donation_id) REFERENCES donations2(id) ON DELETE CASCADE
);
