-- UIU Smart Campus Database Schema
-- Database Management System Course Project
-- Normalized to BCNF (Boyce-Codd Normal Form)

-- Drop existing tables if they exist (in reverse dependency order)
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS admin_sessions;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS notes;
DROP TABLE IF EXISTS question_solutions;
DROP TABLE IF EXISTS student_points;
DROP TABLE IF EXISTS class_routine;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS grades;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS trimesters;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS programs;
DROP TABLE IF EXISTS departments;

-- Departments Table
CREATE TABLE departments (
    department_id INT PRIMARY KEY AUTO_INCREMENT,
    department_code VARCHAR(10) NOT NULL UNIQUE,
    department_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Programs Table (e.g., BSc in CSE, BBA, MBA)
CREATE TABLE programs (
    program_id INT PRIMARY KEY AUTO_INCREMENT,
    program_code VARCHAR(20) NOT NULL UNIQUE,
    program_name VARCHAR(100) NOT NULL,
    department_id INT NOT NULL,
    total_required_credits INT NOT NULL,
    duration_years DECIMAL(3,1) NOT NULL,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Students Table
CREATE TABLE students (
    student_id VARCHAR(10) PRIMARY KEY, -- 10-digit student ID
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    date_of_birth DATE,
    blood_group VARCHAR(5),
    father_name VARCHAR(100),
    mother_name VARCHAR(100),
    program_id INT NOT NULL,
    current_trimester_number INT DEFAULT 1,
    total_completed_credits INT DEFAULT 0,
    current_cgpa DECIMAL(3,2) DEFAULT 0.00,
    total_points INT DEFAULT 0, -- Points earned from uploading notes/solutions
    profile_picture VARCHAR(255),
    admission_date DATE,
    status ENUM('active', 'inactive', 'graduated', 'withdrawn') DEFAULT 'active',
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_program (program_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teachers/Faculty Table
CREATE TABLE teachers (
    teacher_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    initial VARCHAR(10) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    room_number VARCHAR(20),
    department_id INT NOT NULL,
    designation VARCHAR(50),
    profile_picture VARCHAR(255),
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_department (department_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trimesters/Semesters Table
CREATE TABLE trimesters (
    trimester_id INT PRIMARY KEY AUTO_INCREMENT,
    trimester_code VARCHAR(20) NOT NULL UNIQUE, -- e.g., "252", "253"
    trimester_name VARCHAR(50) NOT NULL, -- e.g., "Summer 2025", "Fall 2025"
    trimester_type ENUM('trimester', 'semester') NOT NULL,
    year INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_current (is_current),
    INDEX idx_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Courses Table
CREATE TABLE courses (
    course_id INT PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(20) NOT NULL,
    course_name VARCHAR(200) NOT NULL,
    credit_hours INT NOT NULL,
    department_id INT NOT NULL,
    course_type ENUM('theory', 'lab', 'project') DEFAULT 'theory',
    description TEXT,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    UNIQUE KEY unique_course (course_code, department_id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_department (department_id),
    INDEX idx_course_code (course_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enrollments Table (Links Students to Courses in specific Trimesters with Sections)
CREATE TABLE enrollments (
    enrollment_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    course_id INT NOT NULL,
    trimester_id INT NOT NULL,
    section VARCHAR(5) NOT NULL, -- e.g., "A", "B", "C"
    teacher_id INT,
    enrollment_date DATE NOT NULL,
    status ENUM('enrolled', 'dropped', 'completed', 'failed') DEFAULT 'enrolled',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (trimester_id) REFERENCES trimesters(trimester_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE SET NULL,
    UNIQUE KEY unique_enrollment (student_id, course_id, trimester_id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_course (course_id),
    INDEX idx_trimester (trimester_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grades Table
CREATE TABLE grades (
    grade_id INT PRIMARY KEY AUTO_INCREMENT,
    enrollment_id INT NOT NULL,
    trimester_gpa DECIMAL(3,2) DEFAULT 0.00,
    letter_grade VARCHAR(2),
    grade_points DECIMAL(3,2),
    midterm_marks DECIMAL(5,2),
    final_marks DECIMAL(5,2),
    total_marks DECIMAL(5,2),
    remarks TEXT,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enrollment (enrollment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance Table
CREATE TABLE attendance (
    attendance_id INT PRIMARY KEY AUTO_INCREMENT,
    enrollment_id INT NOT NULL,
    present_count INT DEFAULT 0,
    absent_count INT DEFAULT 0,
    remaining_classes INT DEFAULT 0,
    total_classes INT DEFAULT 0,
    attendance_percentage DECIMAL(5,2) DEFAULT 0.00,
    last_updated DATE,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (enrollment_id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enrollment (enrollment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class Routine Table
CREATE TABLE class_routine (
    routine_id INT PRIMARY KEY AUTO_INCREMENT,
    enrollment_id INT NOT NULL,
    day_of_week ENUM('Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room_number VARCHAR(20),
    building VARCHAR(50),
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id) ON DELETE CASCADE,
    UNIQUE KEY unique_class_schedule (enrollment_id, day_of_week, start_time),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_enrollment (enrollment_id),
    INDEX idx_day (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student Advisor Relationship (Many-to-Many with history tracking)
CREATE TABLE student_advisors (
    advisor_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    teacher_id INT NOT NULL,
    assigned_date DATE NOT NULL,
    is_current BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    UNIQUE KEY unique_current_advisor (student_id, teacher_id, is_current),
    INDEX idx_student (student_id),
    INDEX idx_teacher (teacher_id),
    INDEX idx_current (is_current)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes Table (Students upload notes)
CREATE TABLE notes (
    note_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    points_awarded INT DEFAULT 10, -- Points earned for uploading
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    downloads_count INT DEFAULT 0,
    likes_count INT DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_course (course_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Question Solutions Table (Students upload question solutions)
CREATE TABLE question_solutions (
    solution_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    course_id INT NOT NULL,
    question_title VARCHAR(200) NOT NULL,
    question_text TEXT,
    solution_text TEXT,
    file_path VARCHAR(255),
    exam_type ENUM('midterm', 'final', 'quiz', 'assignment', 'practice') NOT NULL,
    trimester_id INT,
    points_awarded INT DEFAULT 15, -- Points earned for uploading
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    downloads_count INT DEFAULT 0,
    likes_count INT DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (trimester_id) REFERENCES trimesters(trimester_id) ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_course (course_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student Points History Table (Track all point transactions)
CREATE TABLE student_points (
    point_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    points INT NOT NULL,
    action_type ENUM('note_upload', 'solution_upload', 'note_download', 'solution_download', 'bonus', 'penalty') NOT NULL,
    reference_id INT, -- ID of the note/solution that earned points
    reference_type ENUM('note', 'solution'),
    description TEXT,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_action (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notices Table
CREATE TABLE notices (
    notice_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    notice_type ENUM('faculty', 'other', 'general') DEFAULT 'general',
    posted_by VARCHAR(100),
    posted_date DATE NOT NULL,
    program_id INT,
    file_path VARCHAR(255),
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (notice_type),
    INDEX idx_status (status),
    INDEX idx_program (program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Financial Information Table (Student Billing)
CREATE TABLE student_billing (
    billing_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    total_billed DECIMAL(10,2) DEFAULT 0.00,
    total_paid DECIMAL(10,2) DEFAULT 0.00,
    total_waived DECIMAL(10,2) DEFAULT 0.00,
    current_balance DECIMAL(10,2) DEFAULT 0.00,
    last_payment_date DATE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ADMIN PANEL TABLES
-- ============================================================================

-- Admin Users Table (Supports multiple administrators)
CREATE TABLE admin_users (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    address VARCHAR(255),
    role ENUM('super_admin', 'admin', 'moderator', 'viewer') DEFAULT 'admin',
    profile_picture VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    login_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin Sessions Table (Track active login sessions)
CREATE TABLE admin_sessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_admin (admin_id),
    INDEX idx_active (is_active),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs Table (Track all admin actions)
CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT DEFAULT 1,
    action_type VARCHAR(50) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_method VARCHAR(10),
    request_url VARCHAR(255),
    old_values JSON,
    new_values JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE SET NULL,
    INDEX idx_admin (admin_id),
    INDEX idx_action_type (action_type),
    INDEX idx_table_name (table_name),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Settings Table (Store configuration)
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admin_users(admin_id) ON DELETE SET NULL,
    INDEX idx_key (setting_key),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup History Table (Track database backups)
CREATE TABLE backup_history (
    backup_id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    file_size BIGINT,
    backup_type ENUM('manual', 'automatic', 'scheduled') DEFAULT 'manual',
    status ENUM('pending', 'completed', 'failed', 'deleted') DEFAULT 'completed',
    created_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(admin_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin Notifications Table (Internal admin notifications)
CREATE TABLE admin_notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    category ENUM('system', 'approval', 'user_action', 'backup', 'security') DEFAULT 'system',
    reference_type VARCHAR(50),
    reference_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_is_read (is_read),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email Queue Table (For sending emails)
CREATE TABLE email_queue (
    email_id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(100) NOT NULL,
    recipient_name VARCHAR(100),
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    template_name VARCHAR(50),
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_scheduled (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SAMPLE DATA INSERTIONS
-- ============================================================================

-- Insert Departments
INSERT INTO departments (department_code, department_name) VALUES
('CSE', 'Computer Science and Engineering'),
('EEE', 'Electrical and Electronic Engineering'),
('BBA', 'Business Administration'),
('ENG', 'English'),
('CE', 'Civil Engineering');

-- Insert Programs
INSERT INTO programs (program_code, program_name, department_id, total_required_credits, duration_years) VALUES
('BSC_CSE', 'Bachelor of Science in Computer Science and Engineering', 1, 138, 4.0),
('BSC_EEE', 'Bachelor of Science in Electrical and Electronic Engineering', 2, 140, 4.0),
('BBA', 'Bachelor of Business Administration', 3, 120, 4.0),
('MBA', 'Master of Business Administration', 3, 48, 1.5),
('MSCSE', 'Master of Science in Computer Science and Engineering', 1, 36, 1.5);

-- Insert Current Trimesters
INSERT INTO trimesters (trimester_code, trimester_name, trimester_type, year, start_date, end_date, is_current) VALUES
('252', 'Summer 2025', 'trimester', 2025, '2025-06-01', '2025-08-31', FALSE),
('253', 'Fall 2025', 'trimester', 2025, '2025-09-01', '2025-12-31', TRUE);

-- Insert Default Admin User
-- Default password: admin123 (hashed with PASSWORD function for demo - use proper bcrypt in production)
INSERT INTO admin_users (username, password_hash, full_name, email, phone, role, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@smartcampus.com', '+880 1XXX-XXXXXX', 'super_admin', TRUE),
('moderator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Content Moderator', 'moderator@smartcampus.com', '+880 1XXX-XXXXXX', 'moderator', TRUE);

-- Insert Default System Settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description, is_public) VALUES
('site_name', 'Smart Campus', 'string', 'general', 'Name of the campus system', TRUE),
('site_tagline', 'Intelligent Campus Management System', 'string', 'general', 'System tagline', TRUE),
('points_per_note', '10', 'integer', 'rewards', 'Points awarded for uploading notes', FALSE),
('points_per_solution', '15', 'integer', 'rewards', 'Points awarded for uploading solutions', FALSE),
('auto_backup_enabled', 'false', 'boolean', 'backup', 'Enable automatic database backups', FALSE),
('backup_frequency', 'daily', 'string', 'backup', 'Backup frequency (daily, weekly, monthly)', FALSE),
('max_upload_size', '10485760', 'integer', 'uploads', 'Maximum file upload size in bytes (10MB)', FALSE),
('allowed_file_types', '["pdf", "docx", "pptx", "txt", "jpg", "png"]', 'json', 'uploads', 'Allowed file extensions for uploads', FALSE),
('email_notifications', 'false', 'boolean', 'notifications', 'Enable email notifications', FALSE),
('maintenance_mode', 'false', 'boolean', 'system', 'Put system in maintenance mode', FALSE),
('session_timeout', '3600', 'integer', 'security', 'Admin session timeout in seconds', FALSE),
('pagination_limit', '50', 'integer', 'interface', 'Number of records per page', TRUE),
('theme_color', '#667eea', 'string', 'interface', 'Primary theme color', TRUE);



-- Create Views for Common Queries

-- View for Student Academic Summary
CREATE VIEW v_student_academic_summary AS
SELECT 
    s.student_id,
    s.full_name,
    p.program_name,
    s.current_trimester_number,
    s.total_completed_credits,
    s.current_cgpa,
    s.total_points,
    COUNT(e.enrollment_id) as current_enrolled_courses
FROM students s
JOIN programs p ON s.program_id = p.program_id
LEFT JOIN enrollments e ON s.student_id = e.student_id 
    AND e.status = 'enrolled'
GROUP BY s.student_id, s.full_name, p.program_name, s.current_trimester_number, 
         s.total_completed_credits, s.current_cgpa, s.total_points;

-- View for Student Course Details with Attendance
CREATE VIEW v_student_course_attendance AS
SELECT 
    e.student_id,
    c.course_code,
    c.course_name,
    e.section,
    t.trimester_name,
    a.present_count,
    a.absent_count,
    a.remaining_classes,
    a.total_classes,
    a.attendance_percentage
FROM enrollments e
JOIN courses c ON e.course_id = c.course_id
JOIN trimesters t ON e.trimester_id = t.trimester_id
LEFT JOIN attendance a ON e.enrollment_id = a.enrollment_id
WHERE e.status = 'enrolled';

-- Triggers

-- Trigger to update student's total completed credits when course is completed
DELIMITER $$
CREATE TRIGGER trg_update_student_credits
AFTER UPDATE ON enrollments
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE students s
        JOIN courses c ON NEW.course_id = c.course_id
        SET s.total_completed_credits = s.total_completed_credits + c.credit_hours
        WHERE s.student_id = NEW.student_id;
    END IF;
END$$

-- Trigger to update student's total points when notes are uploaded
CREATE TRIGGER trg_update_points_notes
AFTER INSERT ON notes
FOR EACH ROW
BEGIN
    IF NEW.status = 'approved' THEN
        UPDATE students 
        SET total_points = total_points + NEW.points_awarded
        WHERE student_id = NEW.student_id;
        
        INSERT INTO student_points (student_id, points, action_type, reference_id, reference_type, description)
        VALUES (NEW.student_id, NEW.points_awarded, 'note_upload', NEW.note_id, 'note', 
                CONCAT('Points earned for uploading note: ', NEW.title));
    END IF;
END$$

-- Trigger to update student's total points when solutions are uploaded
CREATE TRIGGER trg_update_points_solutions
AFTER INSERT ON question_solutions
FOR EACH ROW
BEGIN
    IF NEW.status = 'approved' THEN
        UPDATE students 
        SET total_points = total_points + NEW.points_awarded
        WHERE student_id = NEW.student_id;
        
        INSERT INTO student_points (student_id, points, action_type, reference_id, reference_type, description)
        VALUES (NEW.student_id, NEW.points_awarded, 'solution_upload', NEW.solution_id, 'solution', 
                CONCAT('Points earned for uploading solution: ', NEW.question_title));
    END IF;
END$$

-- Trigger to calculate attendance percentage
CREATE TRIGGER trg_calculate_attendance
BEFORE UPDATE ON attendance
FOR EACH ROW
BEGIN
    SET NEW.total_classes = NEW.present_count + NEW.absent_count;
    IF NEW.total_classes > 0 THEN
        SET NEW.attendance_percentage = (NEW.present_count * 100.0) / NEW.total_classes;
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

-- Procedure to get student dashboard data
DELIMITER $$
CREATE PROCEDURE sp_get_student_dashboard(IN p_student_id VARCHAR(10))
BEGIN
    -- Student basic info
    SELECT 
        s.*,
        p.program_name,
        sb.current_balance,
        sb.total_billed,
        sb.total_paid
    FROM students s
    JOIN programs p ON s.program_id = p.program_id
    LEFT JOIN student_billing sb ON s.student_id = sb.student_id
    WHERE s.student_id = p_student_id;
    
    -- Current enrollments with attendance
    SELECT 
        c.course_code,
        c.course_name,
        e.section,
        a.present_count,
        a.absent_count,
        a.remaining_classes,
        a.attendance_percentage
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    LEFT JOIN attendance a ON e.enrollment_id = a.enrollment_id
    WHERE e.student_id = p_student_id 
    AND e.status = 'enrolled';
    
    -- Class routine
    SELECT 
        cr.day_of_week,
        cr.start_time,
        cr.end_time,
        c.course_code,
        c.course_name,
        e.section,
        cr.room_number
    FROM class_routine cr
    JOIN enrollments e ON cr.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = p_student_id 
    AND e.status = 'enrolled'
    ORDER BY 
        FIELD(cr.day_of_week, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
        cr.start_time;
END$$

-- Procedure to get admin dashboard statistics
CREATE PROCEDURE sp_get_admin_dashboard_stats()
BEGIN
    -- Get counts for all major tables
    SELECT 
        (SELECT COUNT(*) FROM students) as total_students,
        (SELECT COUNT(*) FROM teachers) as total_teachers,
        (SELECT COUNT(*) FROM courses) as total_courses,
        (SELECT COUNT(*) FROM enrollments WHERE status = 'enrolled') as active_enrollments,
        (SELECT COUNT(*) FROM departments) as total_departments,
        (SELECT COUNT(*) FROM programs) as total_programs,
        (SELECT COUNT(*) FROM trimesters) as total_trimesters,
        (SELECT COUNT(*) FROM notes WHERE status = 'pending') as pending_notes,
        (SELECT COUNT(*) FROM question_solutions WHERE status = 'pending') as pending_solutions,
        (SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()) as today_activities;
END$$

-- Procedure to get system health status
CREATE PROCEDURE sp_get_system_health()
BEGIN
    SELECT 
        -- Database size
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as db_size_mb,
        -- Table counts
        (SELECT COUNT(*) FROM departments) as dept_count,
        (SELECT COUNT(*) FROM programs) as prog_count,
        (SELECT COUNT(*) FROM students) as student_count,
        (SELECT COUNT(*) FROM courses) as course_count,
        (SELECT COUNT(*) FROM trimesters WHERE is_current = 1) as current_trimester_count,
        -- Activity counts
        (SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as logs_last_7_days,
        (SELECT COUNT(*) FROM admin_sessions WHERE is_active = 1) as active_sessions
    FROM information_schema.TABLES 
    WHERE table_schema = 'uiu_smart_campus';
END$$

-- Procedure to archive old activity logs
CREATE PROCEDURE sp_archive_old_logs(IN days_old INT)
BEGIN
    DECLARE deleted_count INT;
    
    DELETE FROM activity_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_old DAY);
    
    SET deleted_count = ROW_COUNT();
    
    SELECT deleted_count as archived_logs, 
           CONCAT('Archived logs older than ', days_old, ' days') as message;
END$$

-- Procedure to cleanup expired sessions
CREATE PROCEDURE sp_cleanup_expired_sessions()
BEGIN
    UPDATE admin_sessions 
    SET is_active = FALSE 
    WHERE expires_at < NOW() AND is_active = TRUE;
    
    SELECT ROW_COUNT() as expired_sessions_count;
END$$

-- Procedure to approve/reject notes
CREATE PROCEDURE sp_moderate_note(
    IN p_note_id INT,
    IN p_action ENUM('approve', 'reject'),
    IN p_admin_id INT
)
BEGIN
    DECLARE v_student_id VARCHAR(10);
    DECLARE v_points INT;
    DECLARE v_title VARCHAR(200);
    
    -- Get note details
    SELECT student_id, points_awarded, title 
    INTO v_student_id, v_points, v_title
    FROM notes WHERE note_id = p_note_id;
    
    IF p_action = 'approve' THEN
        -- Approve note
        UPDATE notes SET status = 'approved' WHERE note_id = p_note_id;
        
        -- Award points
        UPDATE students SET total_points = total_points + v_points WHERE student_id = v_student_id;
        
        -- Log points transaction
        INSERT INTO student_points (student_id, points, action_type, reference_id, reference_type, description)
        VALUES (v_student_id, v_points, 'note_upload', p_note_id, 'note', 
                CONCAT('Points earned for approved note: ', v_title));
        
        -- Create notification for student
        -- (Would need student_notifications table)
        
    ELSE
        -- Reject note
        UPDATE notes SET status = 'rejected' WHERE note_id = p_note_id;
    END IF;
    
    -- Log admin activity
    INSERT INTO activity_logs (admin_id, action_type, table_name, record_id, description)
    VALUES (p_admin_id, LOWER(p_action), 'notes', p_note_id, 
            CONCAT(p_action, 'd note: ', v_title));
    
    SELECT 'SUCCESS' as status, CONCAT('Note ', p_action, 'd successfully') as message;
END$$

-- Procedure to get analytics data
CREATE PROCEDURE sp_get_analytics_data(
    IN p_date_from DATE,
    IN p_date_to DATE
)
BEGIN
    -- Student growth over time
    SELECT 
        DATE(created_at) as date, 
        COUNT(*) as new_students
    FROM students
    WHERE DATE(created_at) BETWEEN p_date_from AND p_date_to
    GROUP BY DATE(created_at)
    ORDER BY date;
    
    -- Course popularity
    SELECT 
        c.course_code,
        c.course_name,
        COUNT(e.enrollment_id) as enrollment_count
    FROM courses c
    LEFT JOIN enrollments e ON c.course_id = e.course_id
    GROUP BY c.course_id, c.course_code, c.course_name
    ORDER BY enrollment_count DESC
    LIMIT 10;
    
    -- Department statistics
    SELECT 
        d.department_name,
        COUNT(DISTINCT s.student_id) as student_count,
        COUNT(DISTINCT t.teacher_id) as teacher_count,
        COUNT(DISTINCT c.course_id) as course_count
    FROM departments d
    LEFT JOIN programs p ON d.department_id = p.department_id
    LEFT JOIN students s ON p.program_id = s.program_id
    LEFT JOIN teachers t ON d.department_id = t.department_id
    LEFT JOIN courses c ON d.department_id = c.department_id
    GROUP BY d.department_id, d.department_name;
END$$

DELIMITER ;

-- Create indexes for better query performance
CREATE INDEX idx_student_cgpa ON students(current_cgpa);
CREATE INDEX idx_student_credits ON students(total_completed_credits);
CREATE INDEX idx_student_points ON students(total_points);
CREATE INDEX idx_enrollment_status ON enrollments(status);
CREATE INDEX idx_notes_status ON notes(status);
CREATE INDEX idx_solutions_status ON question_solutions(status);
