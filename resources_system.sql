-- ============================================
-- UIU Smart Campus - Resources & Upload System
-- Database: uiu_smart_campus
-- ============================================

USE uiu_smart_campus;

-- ============================================
-- Table: resource_categories
-- Purpose: Categories for uploaded resources
-- ============================================
CREATE TABLE IF NOT EXISTS resource_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    category_icon VARCHAR(50) DEFAULT 'fa-folder',
    category_color VARCHAR(20) DEFAULT '#3b82f6',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO resource_categories (category_name, category_icon, category_color) VALUES
('Study Notes', 'fa-sticky-note', '#f68b1f'),
('Past Papers', 'fa-file-alt', '#3b82f6'),
('CT Solutions', 'fa-check-circle', '#10b981'),
('Assignment Solutions', 'fa-clipboard-check', '#8b5cf6'),
('Video Lectures', 'fa-video', '#ef4444'),
('Books & PDFs', 'fa-book', '#f59e0b'),
('Code & Projects', 'fa-code', '#06b6d4'),
('Other Resources', 'fa-folder-open', '#6b7280')
ON DUPLICATE KEY UPDATE category_name=category_name;

-- ============================================
-- Table: uploaded_resources
-- Purpose: Store all uploaded resources/notes
-- ============================================
CREATE TABLE IF NOT EXISTS uploaded_resources (
    resource_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    course_id INT NULL,
    category_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    resource_type ENUM('file', 'link', 'google_drive', 'youtube', 'other_cloud') NOT NULL,
    file_path VARCHAR(500) NULL COMMENT 'Local file path if uploaded',
    file_name VARCHAR(255) NULL,
    file_size INT NULL COMMENT 'File size in bytes',
    file_type VARCHAR(100) NULL COMMENT 'MIME type',
    external_link VARCHAR(500) NULL COMMENT 'Google Drive, YouTube, or other links',
    trimester_id INT NULL,
    points_awarded INT DEFAULT 50,
    views_count INT DEFAULT 0,
    downloads_count INT DEFAULT 0,
    likes_count INT DEFAULT 0,
    is_approved BOOLEAN DEFAULT 1 COMMENT 'Admin approval status',
    is_featured BOOLEAN DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES resource_categories(category_id),
    FOREIGN KEY (trimester_id) REFERENCES trimesters(trimester_id) ON DELETE SET NULL,
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_category (category_id),
    INDEX idx_uploaded_at (uploaded_at),
    INDEX idx_approved (is_approved),
    FULLTEXT INDEX idx_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Uploaded resources and notes by students';

-- ============================================
-- Table: resource_likes
-- Purpose: Track who liked which resources
-- ============================================
CREATE TABLE IF NOT EXISTS resource_likes (
    like_id INT PRIMARY KEY AUTO_INCREMENT,
    resource_id INT NOT NULL,
    student_id VARCHAR(10) NOT NULL,
    liked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES uploaded_resources(resource_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (resource_id, student_id),
    INDEX idx_resource (resource_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: resource_bookmarks
-- Purpose: Track bookmarked resources
-- ============================================
CREATE TABLE IF NOT EXISTS resource_bookmarks (
    bookmark_id INT PRIMARY KEY AUTO_INCREMENT,
    resource_id INT NOT NULL,
    student_id VARCHAR(10) NOT NULL,
    bookmarked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES uploaded_resources(resource_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_bookmark (resource_id, student_id),
    INDEX idx_resource (resource_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: resource_comments
-- Purpose: Comments on uploaded resources
-- ============================================
CREATE TABLE IF NOT EXISTS resource_comments (
    comment_id INT PRIMARY KEY AUTO_INCREMENT,
    resource_id INT NOT NULL,
    student_id VARCHAR(10) NOT NULL,
    comment_text TEXT NOT NULL,
    parent_comment_id INT NULL COMMENT 'For nested replies',
    commented_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES uploaded_resources(resource_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES resource_comments(comment_id) ON DELETE CASCADE,
    INDEX idx_resource (resource_id),
    INDEX idx_student (student_id),
    INDEX idx_parent (parent_comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: resource_views
-- Purpose: Track resource views (analytics)
-- ============================================
CREATE TABLE IF NOT EXISTS resource_views (
    view_id INT PRIMARY KEY AUTO_INCREMENT,
    resource_id INT NOT NULL,
    student_id VARCHAR(10) NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES uploaded_resources(resource_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE SET NULL,
    INDEX idx_resource (resource_id),
    INDEX idx_student (student_id),
    INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Update students table to add total_points if not exists
-- ============================================
ALTER TABLE students 
ADD COLUMN IF NOT EXISTS total_points INT DEFAULT 0 
COMMENT 'Points earned from uploads, activities, etc.';

-- ============================================
-- Note: Triggers are created via separate PHP execution
-- Cannot use DELIMITER in mysqli multi-query
-- ============================================

-- ============================================
-- Sample Data (Optional - for testing)
-- ============================================
-- INSERT INTO uploaded_resources 
-- (student_id, course_id, category_id, title, description, resource_type, external_link, points_awarded)
-- VALUES
-- ('0112330011', 1, 1, 'Database Normalization Notes', 'Comprehensive notes on BCNF and 3NF', 'link', 'https://drive.google.com/sample', 50);
