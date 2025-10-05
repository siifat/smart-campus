-- ============================================
-- UIU Smart Campus Database Schema
-- To-Do Items and Recent Activity Tables
-- Database: uiu_smart_campus
-- ============================================

USE uiu_smart_campus;

-- ============================================
-- Table: student_todos
-- Purpose: Store student to-do list items
-- ============================================
CREATE TABLE IF NOT EXISTS student_todos (
    todo_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    task TEXT NOT NULL,
    completed BOOLEAN DEFAULT 0,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    due_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_completed (completed),
    INDEX idx_created_at (created_at),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Student to-do list items';

-- ============================================
-- Table: student_activities
-- Purpose: Track student activity log/feed
-- ============================================
CREATE TABLE IF NOT EXISTS student_activities (
    activity_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    activity_type ENUM('login', 'course_view', 'assignment_submit', 'note_upload', 
                       'question_post', 'quiz_complete', 'grade_received', 
                       'attendance_marked', 'todo_complete', 'study_session', 'other') NOT NULL,
    activity_title VARCHAR(255) NOT NULL,
    activity_description TEXT NULL,
    related_course_id INT NULL,
    related_id INT NULL COMMENT 'ID of related entity (assignment_id, note_id, etc.)',
    icon_class VARCHAR(50) DEFAULT 'fa-circle' COMMENT 'FontAwesome icon class',
    activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (related_course_id) REFERENCES courses(course_id) ON DELETE SET NULL,
    INDEX idx_student_id (student_id),
    INDEX idx_activity_date (activity_date),
    INDEX idx_activity_type (activity_type),
    INDEX idx_related_course (related_course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Student activity feed/history';

-- ============================================
-- Sample Data (Optional - Uncomment to use)
-- ============================================
-- INSERT INTO student_todos (student_id, task, completed, priority, due_date) VALUES
-- ('0112330011', 'Complete DSA assignment', 0, 'high', '2025-10-05'),
-- ('0112330011', 'Study for midterm exam', 0, 'high', '2025-10-08'),
-- ('0112330011', 'Submit database project', 0, 'medium', '2025-10-10'),
-- ('0112330011', 'Review React documentation', 0, 'low', NULL),
-- ('0112330011', 'Prepare presentation slides', 1, 'medium', '2025-10-01');

-- INSERT INTO student_activities (student_id, activity_type, activity_title, activity_description, icon_class) VALUES
-- ('0112330011', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', 'fa-sign-in-alt'),
-- ('0112330011', 'assignment_submit', 'Submitted DSA Assignment #3', 'Submitted on time', 'fa-file-upload'),
-- ('0112330011', 'grade_received', 'Received grade for Quiz 1', 'Score: 95/100 in Database Systems', 'fa-chart-line'),
-- ('0112330011', 'todo_complete', 'Completed task', 'Upload study notes - Completed', 'fa-check-circle'),
-- ('0112330011', 'note_upload', 'Uploaded study notes', 'Shared notes for Algorithms course', 'fa-book');
