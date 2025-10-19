-- Add email notification preferences to students table
-- Run this migration to enable notification settings

ALTER TABLE `students` 
ADD COLUMN `email_notifications_enabled` TINYINT(1) DEFAULT 1 COMMENT 'Email notifications toggle (1=on, 0=off)' AFTER `focus_streak`,
ADD COLUMN `notification_preferences` JSON DEFAULT NULL COMMENT 'JSON object for granular notification settings' AFTER `email_notifications_enabled`,
ADD COLUMN `address` TEXT DEFAULT NULL COMMENT 'Student address' AFTER `mother_name`,
ADD COLUMN `emergency_contact_name` VARCHAR(100) DEFAULT NULL COMMENT 'Emergency contact person' AFTER `address`,
ADD COLUMN `emergency_contact_phone` VARCHAR(20) DEFAULT NULL COMMENT 'Emergency contact phone' AFTER `emergency_contact_name`,
ADD COLUMN `bio` TEXT DEFAULT NULL COMMENT 'Student bio/about' AFTER `emergency_contact_phone`;

-- Set default notification preferences for existing students
UPDATE `students` 
SET `notification_preferences` = JSON_OBJECT(
    'assignment_reminders', true,
    'grade_updates', true,
    'course_announcements', true,
    'schedule_changes', true,
    'resource_updates', false,
    'achievement_notifications', true
)
WHERE `notification_preferences` IS NULL;

-- Index for faster queries
CREATE INDEX `idx_email_notifications` ON `students` (`email_notifications_enabled`);
