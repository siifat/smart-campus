-- Migration: Add grading columns to assignment_submissions table
-- Date: 2025-10-20
-- Purpose: Support teacher grading functionality

-- Add marks_obtained column (nullable until graded)
ALTER TABLE `assignment_submissions` 
ADD COLUMN `marks_obtained` DECIMAL(5,2) DEFAULT NULL COMMENT 'Marks awarded by teacher' AFTER `status`;

-- Add feedback column for teacher comments
ALTER TABLE `assignment_submissions` 
ADD COLUMN `feedback` TEXT DEFAULT NULL COMMENT 'Teacher feedback on submission' AFTER `marks_obtained`;

-- Add graded_at timestamp
ALTER TABLE `assignment_submissions` 
ADD COLUMN `graded_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When the submission was graded' AFTER `feedback`;

-- Add index for faster grading queries
ALTER TABLE `assignment_submissions`
ADD INDEX `idx_status` (`status`),
ADD INDEX `idx_graded_at` (`graded_at`);

-- Update existing graded submissions if any (set graded_at to updated_at)
UPDATE `assignment_submissions` 
SET `graded_at` = `updated_at` 
WHERE `status` = 'graded' AND `graded_at` IS NULL;

SELECT 'Migration completed successfully! Grading columns added to assignment_submissions table.' as Status;
