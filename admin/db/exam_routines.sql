-- Exam Routines Table for Admin Uploads
-- Admin uploads exam routines by department and trimester
-- Students view personalized schedules based on their enrolled courses

CREATE TABLE IF NOT EXISTS exam_routines (
    routine_id INT PRIMARY KEY AUTO_INCREMENT,
    department_id INT NOT NULL,
    trimester_id INT NOT NULL,
    exam_type ENUM('Midterm', 'Final') NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    course_title VARCHAR(255),
    section VARCHAR(10),
    teacher_initial VARCHAR(50),
    exam_date DATE,
    exam_time VARCHAR(50),
    room VARCHAR(100),
    original_filename VARCHAR(255),
    uploaded_by VARCHAR(50) DEFAULT 'admin',
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    FOREIGN KEY (trimester_id) REFERENCES trimesters(trimester_id) ON DELETE CASCADE,
    
    INDEX idx_dept_trimester (department_id, trimester_id),
    INDEX idx_exam_type (exam_type),
    INDEX idx_course_section (course_code, section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
