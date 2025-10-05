-- Focus Session Feature Database Schema
-- UIU Smart Campus

-- Table: focus_sessions
-- Stores all focus/pomodoro sessions completed by students
CREATE TABLE IF NOT EXISTS `focus_sessions` (
  `session_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` VARCHAR(20) NOT NULL,
  `session_duration` INT NOT NULL COMMENT 'Duration in minutes',
  `session_mode` ENUM('pomodoro', 'short-break', 'long-break') DEFAULT 'pomodoro',
  `points_earned` INT NOT NULL DEFAULT 0,
  `session_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
  INDEX `idx_student_date` (`student_id`, `session_date`),
  INDEX `idx_session_date` (`session_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: focus_achievements
-- Defines available achievements for focus sessions
CREATE TABLE IF NOT EXISTS `focus_achievements` (
  `achievement_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `achievement_type` ENUM('sessions', 'minutes', 'streak') NOT NULL,
  `required_value` INT NOT NULL COMMENT 'Value required to unlock',
  `points_reward` INT NOT NULL DEFAULT 0,
  `icon` VARCHAR(50) DEFAULT 'fa-trophy',
  `created_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: student_achievements
-- Tracks which achievements each student has earned
CREATE TABLE IF NOT EXISTS `student_achievements` (
  `student_achievement_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` VARCHAR(20) NOT NULL,
  `achievement_id` INT NOT NULL,
  `earned_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`achievement_id`) REFERENCES `focus_achievements`(`achievement_id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_student_achievement` (`student_id`, `achievement_id`),
  INDEX `idx_student` (`student_id`),
  INDEX `idx_earned_date` (`earned_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add focus_streak column to students table if not exists
ALTER TABLE `students` 
ADD COLUMN IF NOT EXISTS `focus_streak` INT DEFAULT 0 COMMENT 'Current daily focus streak';

-- Insert default achievements
INSERT INTO `focus_achievements` (`title`, `description`, `achievement_type`, `required_value`, `points_reward`, `icon`) VALUES
('First Focus', 'Complete your first focus session', 'sessions', 1, 50, 'fa-star'),
('Focus Beginner', 'Complete 5 focus sessions', 'sessions', 5, 100, 'fa-medal'),
('Focus Enthusiast', 'Complete 10 focus sessions', 'sessions', 10, 200, 'fa-fire'),
('Focus Expert', 'Complete 25 focus sessions', 'sessions', 25, 500, 'fa-crown'),
('Focus Master', 'Complete 50 focus sessions', 'sessions', 50, 1000, 'fa-gem'),
('Focus Legend', 'Complete 100 focus sessions', 'sessions', 100, 2500, 'fa-trophy'),

('1 Hour Focus', 'Complete 60 minutes of focus time', 'minutes', 60, 100, 'fa-clock'),
('5 Hours Focus', 'Complete 300 minutes of focus time', 'minutes', 300, 250, 'fa-hourglass-half'),
('10 Hours Focus', 'Complete 600 minutes of focus time', 'minutes', 600, 500, 'fa-hourglass'),
('Marathon Focus', 'Complete 1000 minutes of focus time', 'minutes', 1000, 1000, 'fa-running'),
('Ultra Focus', 'Complete 2500 minutes of focus time', 'minutes', 2500, 2500, 'fa-rocket'),

('2 Day Streak', 'Focus for 2 days in a row', 'streak', 2, 100, 'fa-fire'),
('Week Warrior', 'Focus for 7 days in a row', 'streak', 7, 300, 'fa-calendar-check'),
('Fortnight Fighter', 'Focus for 14 days in a row', 'streak', 14, 700, 'fa-bolt'),
('Monthly Master', 'Focus for 30 days in a row', 'streak', 30, 1500, 'fa-star'),
('Consistency King', 'Focus for 60 days in a row', 'streak', 60, 3000, 'fa-crown'),
('Legendary Streak', 'Focus for 100 days in a row', 'streak', 100, 5000, 'fa-infinity')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Sample data for testing (optional)
-- Uncomment the following lines to add sample focus sessions

-- INSERT INTO `focus_sessions` (`student_id`, `session_duration`, `session_mode`, `points_earned`, `session_date`) VALUES
-- ('0112320240', 25, 'pomodoro', 50, '2025-10-01 10:30:00'),
-- ('0112320240', 25, 'pomodoro', 50, '2025-10-01 15:00:00'),
-- ('0112320240', 25, 'pomodoro', 50, '2025-10-02 09:00:00'),
-- ('0112320240', 25, 'pomodoro', 50, '2025-10-03 11:00:00');

-- Verify tables created successfully
SELECT 'Focus Session tables created successfully!' AS Status;
