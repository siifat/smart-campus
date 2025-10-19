-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 19, 2025 at 07:58 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `uiu_smart_campus`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_archive_old_logs` (IN `days_old` INT)   BEGIN
    DECLARE deleted_count INT;
    
    DELETE FROM activity_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_old DAY);
    
    SET deleted_count = ROW_COUNT();
    
    SELECT deleted_count as archived_logs, 
           CONCAT('Archived logs older than ', days_old, ' days') as message;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_assignment_analytics` (IN `p_assignment_id` INT)   BEGIN
    DECLARE total_enrolled INT;
    DECLARE total_subs INT;
    DECLARE on_time INT;
    DECLARE late_subs INT;
    DECLARE avg_score DECIMAL(5,2);
    DECLARE med_score DECIMAL(5,2);
    DECLARE max_score DECIMAL(5,2);
    DECLARE min_score DECIMAL(5,2);
    
    -- Get total enrolled students
    SELECT COUNT(DISTINCT e.student_id) INTO total_enrolled
    FROM enrollments e
    JOIN assignments a ON e.course_id = a.course_id 
        AND e.trimester_id = a.trimester_id
        AND (a.section IS NULL OR e.section = a.section)
    WHERE a.assignment_id = p_assignment_id
      AND e.status = 'enrolled';
    
    -- Get submission stats
    SELECT 
        COUNT(*),
        SUM(CASE WHEN is_late = 0 THEN 1 ELSE 0 END),
        SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END)
    INTO total_subs, on_time, late_subs
    FROM assignment_submissions
    WHERE assignment_id = p_assignment_id;
    
    -- Get grade statistics
    SELECT 
        AVG(marks_obtained),
        MAX(marks_obtained),
        MIN(marks_obtained)
    INTO avg_score, max_score, min_score
    FROM submission_grades sg
    JOIN assignment_submissions sub ON sg.submission_id = sub.submission_id
    WHERE sub.assignment_id = p_assignment_id;
    
    -- Insert or update analytics
    INSERT INTO assignment_analytics (
        assignment_id, total_submissions, on_time_submissions, 
        late_submissions, missing_submissions, average_score, 
        highest_score, lowest_score
    ) VALUES (
        p_assignment_id, total_subs, on_time, late_subs,
        total_enrolled - total_subs, avg_score, max_score, min_score
    ) ON DUPLICATE KEY UPDATE
        total_submissions = VALUES(total_submissions),
        on_time_submissions = VALUES(on_time_submissions),
        late_submissions = VALUES(late_submissions),
        missing_submissions = VALUES(missing_submissions),
        average_score = VALUES(average_score),
        highest_score = VALUES(highest_score),
        lowest_score = VALUES(lowest_score),
        last_calculated = CURRENT_TIMESTAMP;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cleanup_expired_sessions` ()   BEGIN
    UPDATE admin_sessions 
    SET is_active = FALSE 
    WHERE expires_at < NOW() AND is_active = TRUE;
    
    SELECT ROW_COUNT() as expired_sessions_count;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_admin_dashboard_stats` ()   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_analytics_data` (IN `p_date_from` DATE, IN `p_date_to` DATE)   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_class_performance` (IN `p_course_id` INT, IN `p_section` VARCHAR(10), IN `p_trimester_id` INT)   BEGIN
    -- Get overall performance
    SELECT 
        COUNT(DISTINCT e.student_id) as total_students,
        AVG(g.trimester_gpa) as average_gpa,
        COUNT(DISTINCT CASE WHEN g.trimester_gpa >= 3.5 THEN e.student_id END) as excelling,
        COUNT(DISTINCT CASE WHEN g.trimester_gpa < 2.5 THEN e.student_id END) as struggling,
        AVG(a.present_count / a.total_classes * 100) as attendance_rate
    FROM enrollments e
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    LEFT JOIN attendance a ON e.enrollment_id = a.enrollment_id
    WHERE e.course_id = p_course_id
      AND e.section = p_section
      AND e.trimester_id = p_trimester_id
      AND e.status = 'enrolled';
      
    -- Get top 5 students
    SELECT 
        s.student_id,
        s.full_name,
        g.trimester_gpa,
        g.total_marks
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    WHERE e.course_id = p_course_id
      AND e.section = p_section
      AND e.trimester_id = p_trimester_id
      AND e.status = 'enrolled'
    ORDER BY g.trimester_gpa DESC, g.total_marks DESC
    LIMIT 5;
    
    -- Get bottom 5 students (struggling)
    SELECT 
        s.student_id,
        s.full_name,
        g.trimester_gpa,
        g.total_marks,
        COUNT(DISTINCT CASE 
            WHEN sub.submission_id IS NULL AND a.due_date < NOW() 
            THEN a.assignment_id 
        END) as missing_assignments
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    LEFT JOIN assignments a ON e.course_id = a.course_id 
        AND e.trimester_id = a.trimester_id
        AND (a.section IS NULL OR e.section = a.section)
    LEFT JOIN assignment_submissions sub ON a.assignment_id = sub.assignment_id 
        AND e.student_id = sub.student_id
    WHERE e.course_id = p_course_id
      AND e.section = p_section
      AND e.trimester_id = p_trimester_id
      AND e.status = 'enrolled'
    GROUP BY s.student_id, s.full_name, g.trimester_gpa, g.total_marks
    ORDER BY g.trimester_gpa ASC, g.total_marks ASC, missing_assignments DESC
    LIMIT 5;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_student_dashboard` (IN `p_student_id` VARCHAR(10))   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_system_health` ()   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_teacher_dashboard_stats` (IN `p_teacher_id` INT, IN `p_trimester_id` INT)   BEGIN
    -- Total courses teaching
    SELECT COUNT(DISTINCT course_id) as total_courses
    FROM enrollments
    WHERE teacher_id = p_teacher_id 
      AND trimester_id = p_trimester_id
      AND status = 'enrolled';
    
    -- Total students
    SELECT COUNT(DISTINCT student_id) as total_students
    FROM enrollments
    WHERE teacher_id = p_teacher_id 
      AND trimester_id = p_trimester_id
      AND status = 'enrolled';
    
    -- Pending submissions to grade
    SELECT COUNT(*) as pending_grades
    FROM assignment_submissions sub
    JOIN assignments a ON sub.assignment_id = a.assignment_id
    LEFT JOIN submission_grades g ON sub.submission_id = g.submission_id
    WHERE a.teacher_id = p_teacher_id
      AND a.trimester_id = p_trimester_id
      AND sub.status = 'submitted'
      AND g.grade_id IS NULL;
    
    -- Upcoming deadlines (next 7 days)
    SELECT COUNT(*) as upcoming_deadlines
    FROM assignments
    WHERE teacher_id = p_teacher_id
      AND trimester_id = p_trimester_id
      AND due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
      AND is_published = 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_moderate_note` (IN `p_note_id` INT, IN `p_action` ENUM('approve','reject'), IN `p_admin_id` INT)   BEGIN
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

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT 1,
  `action_type` varchar(50) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `request_url` varchar(255) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `admin_id`, `action_type`, `table_name`, `record_id`, `description`, `ip_address`, `user_agent`, `request_method`, `request_url`, `old_values`, `new_values`, `created_at`) VALUES
(1, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 3 exams, 1 students affected', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-02 21:06:40'),
(2, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 exams, 0 students affected', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-02 21:08:57'),
(3, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 exams, 0 students affected', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-02 21:09:23'),
(4, 1, 'delete', 'exam_schedule', NULL, 'Deleted exam schedule: 3 records, 0 files', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-02 21:17:24'),
(5, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 unique exams, 0 students affected, 0 total records inserted', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-16 14:24:27'),
(6, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 unique exams, 0 students affected, 0 total records inserted', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-16 14:34:47'),
(7, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 unique exams, 0 students affected, 0 total records inserted', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-16 14:35:17'),
(8, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 unique exams, 0 students affected, 0 total records inserted', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-16 14:35:38'),
(9, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 unique exams, 0 students affected, 0 total records inserted', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-16 14:38:25'),
(10, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 unique exams, 0 students affected, 0 total records inserted', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-16 14:38:42'),
(11, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 unique exams, 0 students affected, 0 total records inserted', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-16 14:38:43'),
(12, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 unique exams, 0 students affected, 0 total records inserted', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-16 14:38:43'),
(13, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 unique exams, 0 students affected, 0 total records inserted', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-16 14:38:48'),
(14, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 unique exams, 0 students affected, 0 total records inserted', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-16 14:38:49'),
(15, 1, 'upload', 'exam_schedule', NULL, 'Uploaded exam schedule: 0 unique exams, 0 students affected, 0 total records inserted', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-16 14:39:01');

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `notification_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `category` enum('system','approval','user_action','backup','security') DEFAULT 'system',
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_sessions`
--

CREATE TABLE `admin_sessions` (
  `session_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `session_token` varchar(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `role` enum('super_admin','admin','moderator','viewer') DEFAULT 'admin',
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`admin_id`, `username`, `password_hash`, `full_name`, `email`, `phone`, `address`, `role`, `profile_picture`, `is_active`, `last_login`, `login_count`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@smartcampus.com', '+880 1XXX-XXXXXX', NULL, 'super_admin', NULL, 1, NULL, 0, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(2, 'moderator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Content Moderator', 'moderator@smartcampus.com', '+880 1XXX-XXXXXX', NULL, 'moderator', NULL, 1, NULL, 0, '2025-10-01 20:12:28', '2025-10-01 20:12:28');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_reads`
--

CREATE TABLE `announcement_reads` (
  `read_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `assignment_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `trimester_id` int(11) NOT NULL,
  `section` varchar(10) DEFAULT NULL COMMENT 'NULL = all sections',
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `assignment_type` enum('homework','project','quiz','lab','bonus','midterm','final') NOT NULL,
  `total_marks` decimal(5,2) NOT NULL DEFAULT 100.00,
  `weight_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Weight in final grade (optional)',
  `file_path` varchar(255) DEFAULT NULL COMMENT 'Attached assignment file',
  `due_date` datetime NOT NULL,
  `late_submission_allowed` tinyint(1) DEFAULT 1,
  `late_penalty_per_day` decimal(5,2) DEFAULT 5.00 COMMENT 'Percentage penalty per day',
  `is_published` tinyint(1) DEFAULT 0,
  `is_bonus` tinyint(1) DEFAULT 0 COMMENT 'Bonus assignment flag',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_analytics`
--

CREATE TABLE `assignment_analytics` (
  `analytics_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `total_submissions` int(11) DEFAULT 0,
  `on_time_submissions` int(11) DEFAULT 0,
  `late_submissions` int(11) DEFAULT 0,
  `missing_submissions` int(11) DEFAULT 0,
  `average_score` decimal(5,2) DEFAULT NULL,
  `median_score` decimal(5,2) DEFAULT NULL,
  `highest_score` decimal(5,2) DEFAULT NULL,
  `lowest_score` decimal(5,2) DEFAULT NULL,
  `standard_deviation` decimal(5,2) DEFAULT NULL,
  `grade_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"A": 10, "B": 15, ...}' CHECK (json_valid(`grade_distribution`)),
  `last_calculated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submissions`
--

CREATE TABLE `assignment_submissions` (
  `submission_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `submission_text` text DEFAULT NULL COMMENT 'For text-based submissions',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_late` tinyint(1) DEFAULT 0,
  `late_days` int(11) DEFAULT 0,
  `status` enum('submitted','graded','returned','resubmitted') DEFAULT 'submitted',
  `attempt_number` int(11) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `assignment_submissions`
--
DELIMITER $$
CREATE TRIGGER `after_submission_insert` AFTER INSERT ON `assignment_submissions` FOR EACH ROW BEGIN
    INSERT INTO `teacher_notifications` (teacher_id, notification_type, title, message, related_type, related_id, priority)
    SELECT 
        a.teacher_id,
        IF(NEW.is_late = 1, 'late_submission', 'new_submission'),
        IF(NEW.is_late = 1, 'Late Submission Received', 'New Submission Received'),
        CONCAT(
            'Student ', NEW.student_id, ' submitted "', a.title, '"',
            IF(NEW.is_late = 1, CONCAT(' (', NEW.late_days, ' days late)'), ' on time')
        ),
        'submission',
        NEW.submission_id,
        IF(NEW.is_late = 1, 'high', 'normal')
    FROM assignments a
    WHERE a.assignment_id = NEW.assignment_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_submission_insert` BEFORE INSERT ON `assignment_submissions` FOR EACH ROW BEGIN
    DECLARE due_date DATETIME;
    DECLARE is_late_allowed TINYINT(1);
    
    SELECT due_date, late_submission_allowed INTO due_date, is_late_allowed
    FROM assignments WHERE assignment_id = NEW.assignment_id;
    
    IF NEW.submitted_at > due_date THEN
        SET NEW.is_late = 1;
        SET NEW.late_days = DATEDIFF(NEW.submitted_at, due_date);
        
        IF is_late_allowed = 0 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Late submission not allowed for this assignment';
        END IF;
    ELSE
        SET NEW.is_late = 0;
        SET NEW.late_days = 0;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `present_count` int(11) DEFAULT 0,
  `absent_count` int(11) DEFAULT 0,
  `remaining_classes` int(11) DEFAULT 0,
  `total_classes` int(11) DEFAULT 0,
  `attendance_percentage` decimal(5,2) DEFAULT 0.00,
  `last_updated` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `attendance`
--
DELIMITER $$
CREATE TRIGGER `trg_calculate_attendance` BEFORE UPDATE ON `attendance` FOR EACH ROW BEGIN
    SET NEW.total_classes = NEW.present_count + NEW.absent_count;
    IF NEW.total_classes > 0 THEN
        SET NEW.attendance_percentage = (NEW.present_count * 100.0) / NEW.total_classes;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `backup_history`
--

CREATE TABLE `backup_history` (
  `backup_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(500) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `backup_type` enum('manual','automatic','scheduled') DEFAULT 'manual',
  `status` enum('pending','completed','failed','deleted') DEFAULT 'completed',
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_performance_snapshots`
--

CREATE TABLE `class_performance_snapshots` (
  `snapshot_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `section` varchar(10) NOT NULL,
  `trimester_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `snapshot_date` date NOT NULL,
  `average_grade` decimal(5,2) DEFAULT NULL,
  `median_grade` decimal(5,2) DEFAULT NULL,
  `total_students` int(11) NOT NULL,
  `active_students` int(11) NOT NULL,
  `struggling_students` int(11) DEFAULT 0 COMMENT 'Grade < 60%',
  `excelling_students` int(11) DEFAULT 0 COMMENT 'Grade >= 80%',
  `attendance_rate` decimal(5,2) DEFAULT NULL,
  `submission_rate` decimal(5,2) DEFAULT NULL COMMENT 'Assignment submission rate',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_routine`
--

CREATE TABLE `class_routine` (
  `routine_id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `day_of_week` enum('Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `building` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class_routine`
--

INSERT INTO `class_routine` (`routine_id`, `enrollment_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `building`, `created_at`) VALUES
(28, 28, 'Saturday', '09:51:00', '11:10:00', NULL, NULL, '2025-10-19 14:08:05'),
(29, 29, 'Saturday', '08:30:00', '09:50:00', NULL, NULL, '2025-10-19 14:08:05'),
(30, 30, 'Saturday', '11:11:00', '13:40:00', NULL, NULL, '2025-10-19 14:08:05'),
(31, 31, 'Sunday', '08:30:00', '11:00:00', NULL, NULL, '2025-10-19 14:08:05'),
(32, 32, 'Sunday', '12:31:00', '13:50:00', NULL, NULL, '2025-10-19 14:08:05'),
(33, 28, 'Tuesday', '09:51:00', '11:10:00', NULL, NULL, '2025-10-19 14:08:05'),
(34, 34, 'Tuesday', '11:11:00', '13:40:00', NULL, NULL, '2025-10-19 14:08:05'),
(35, 29, 'Tuesday', '08:30:00', '09:50:00', NULL, NULL, '2025-10-19 14:08:05'),
(36, 32, 'Wednesday', '12:31:00', '13:50:00', NULL, NULL, '2025-10-19 14:08:05');

-- --------------------------------------------------------

--
-- Table structure for table `content_modules`
--

CREATE TABLE `content_modules` (
  `module_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `trimester_id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL COMMENT 'e.g., Week 1, Module 1: Introduction',
  `module_order` int(11) DEFAULT 1,
  `description` text DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(200) NOT NULL,
  `credit_hours` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `course_type` enum('theory','lab','project') DEFAULT 'theory',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `credit_hours`, `department_id`, `course_type`, `description`, `created_at`) VALUES
(1, 'ENG 1011', 'English I', 3, 4, 'theory', NULL, '2025-10-01 23:22:14'),
(2, 'BDS 1201', 'History of the Emergence of Bangladesh', 2, 4, 'theory', NULL, '2025-10-01 23:22:14'),
(3, 'CSE 1110', 'Introduction to Computer Systems', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(4, 'MATH 1151', 'Fundamental Calculus', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(5, 'ENG 1013', 'English II', 3, 4, 'theory', NULL, '2025-10-01 23:22:14'),
(6, 'CSE 1111', 'Structured Programming Language', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(7, 'CSE 1112', 'Structured Programming Language Laboratory', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(8, 'CSE 2213', 'Discrete Mathematics', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(9, 'MATH 2183', 'Calculus and Linear Algebra', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(10, 'PHY 2105', 'Physics', 3, 2, 'theory', NULL, '2025-10-01 23:22:14'),
(11, 'PHY 2106', 'Physics Lab', 1, 2, 'lab', NULL, '2025-10-01 23:22:14'),
(12, 'CSE 2215', 'Data Structures and Algorithms I', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(13, 'CSE 2216', 'Data Structures and Algorithms I Laboratory', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(14, 'MATH 2201', 'Coordinate Geometry and Vector Analysis', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(15, 'CSE 1325', 'Digital Logic Design', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(16, 'CSE 1326', 'Digital Logic Design Lab', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(17, 'CSE 1115', 'Object Oriented Programming', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(18, 'CSE 1116', 'Object Oriented Programming Lab', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(19, 'MATH 2205', 'Probability and Statistics', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(20, 'SOC 2101', 'Society, Technology and Engineering Ethics', 3, 4, 'theory', NULL, '2025-10-01 23:22:14'),
(21, 'CSE 2217', 'Data Structures and Algorithms II', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(22, 'CSE 2218', 'Data Structures and Algorithms II Laboratory', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(23, 'EEE 2113', 'Electrical Circuits', 3, 2, 'theory', NULL, '2025-10-01 23:22:14'),
(24, 'CSE 3521', 'Database Management Systems', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(25, 'CSE 3522', 'Database Management Systems Laboratory', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(26, 'EEE 2123', 'Electronics', 3, 2, 'theory', NULL, '2025-10-01 23:22:14'),
(27, 'EEE 2124', 'Electronics Lab', 1, 2, 'lab', NULL, '2025-10-01 23:22:14'),
(28, 'CSE 4165', 'Web Programming', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(29, 'CSE 3313', 'Computer Architecture', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(30, 'CSE 2118', 'Advanced Object Oriented Programming Lab', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(31, 'BIO 3105', 'Biology for Engineers', 3, 4, 'theory', NULL, '2025-10-01 23:22:14'),
(32, 'CSE 3411', 'System Analysis and Design', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(33, 'CSE 3412', 'System Analysis and Design Lab', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(34, 'CSE 4325', 'Microprocessors and Microcontrollers', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(35, 'CSE 4326', 'Microprocessors and Microcontrollers Lab', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(36, 'CSE 3421', 'Software Engineering', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(37, 'CSE 3422', 'Software Engineering Lab', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(38, 'CSE 3811', 'Artificial Intelligence', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(39, 'CSE 3812', 'Artificial Intelligence Lab', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(40, 'CSE 2233', 'Theory of Computation', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(41, 'GED OPT1', 'General Education Optional-I', 3, 4, 'theory', NULL, '2025-10-01 23:22:14'),
(42, 'PMG 4101', 'Project Management', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(43, 'CSE 3711', 'Computer Networks', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(44, 'CSE 3712', 'Computer Networks Lab', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(45, 'GED OPT2', 'General Education Optional-II', 3, 4, 'theory', NULL, '2025-10-01 23:22:14'),
(46, 'CSE 4000 A', 'Final Year Design Project - I', 2, 1, 'project', NULL, '2025-10-01 23:22:14'),
(47, 'CSE ****', 'Elective - V', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(48, 'CSE 4509', 'Operating Systems', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(49, 'CSE 4510', 'Operating Systems Laboratory', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(50, 'GED OPT3', 'General Education Optional – III', 3, 4, 'theory', NULL, '2025-10-01 23:22:14'),
(51, 'CSE 4000 B', 'Final Year Design Project - II', 2, 1, 'project', NULL, '2025-10-01 23:22:14'),
(52, 'CSE 4531', 'Computer Security', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(53, 'CSE 4000 C', 'Final Year Design Project - III', 2, 1, 'project', NULL, '2025-10-01 23:22:14'),
(54, 'EEE 4261', 'Green Computing', 3, 2, 'theory', NULL, '2025-10-01 23:22:14'),
(55, 'ECO 4101', 'Economics', 3, 3, 'theory', NULL, '2025-10-01 23:22:14'),
(56, 'ACT 2111', 'Financial and Managerial Accounting', 3, 3, 'theory', NULL, '2025-10-01 23:22:14'),
(57, 'IPE 3401', 'Industrial and Operational Management', 3, 3, 'theory', NULL, '2025-10-01 23:22:14'),
(58, 'TEC 2499', 'Technology Entrepreneurship', 3, 3, 'theory', NULL, '2025-10-01 23:22:14'),
(59, 'CSE 4611', 'Compiler Design', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(60, 'CSE 4621', 'Computer Graphics', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(61, 'CSE 4783', 'Cryptography', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(62, 'CSE 4777', 'Network Security', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(63, 'CSE 4587', 'Cloud Computing', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(64, 'CSE 4889', 'Machine Learning', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(65, 'CSE 4891', 'Data Mining', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(66, 'CSE 4883', 'Digital Image Processing', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(67, 'CSE 4451', 'Human Computer Interaction', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(68, 'CSE 4435', 'Software Architecture', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(69, 'CSE 4181', 'Mobile Application Development', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(70, 'CSE 4495', 'Software Testing and Quality Assurance', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(71, 'CSE 4945', 'UI: Concepts and Design', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(72, 'DS 1501', 'Programming for Data Science', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(73, 'DS 1502', 'Programming for Data Science Laboratory', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(74, 'DS 1115', 'Object Oriented Programming for Data Science', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(75, 'DS 1116', 'Object Oriented Programming for Data Science Laboratory', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(76, 'DS 1101', 'Fundamentals of Data Science', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(77, 'BIO 3107', 'Biology', 3, 4, 'theory', NULL, '2025-10-01 23:22:14'),
(78, 'MATH 2107', 'Linear Algebra', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(79, 'MATH 1153', 'Advanced Calculus', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(80, 'SOC 2102', 'Society, Environment and Computing Ethics', 3, 4, 'theory', NULL, '2025-10-01 23:22:14'),
(81, 'PSY 2101', 'Psychology', 3, 4, 'theory', NULL, '2025-10-01 23:22:14'),
(82, 'DS 3885', 'Data Wrangling', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(83, 'DS 3101', 'Advanced Probability and Statistics', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(84, 'DS 3521', 'Data Visualization', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(85, 'DS 3522', 'Data Visualization Lab', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(86, 'DS 4889', 'Machine Learning', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(87, 'DS 4523', 'Simulation and Modeling', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(88, 'DS 4891', 'Data Analytics', 3, 1, 'theory', NULL, '2025-10-01 23:22:14'),
(89, 'DS 4892', 'Data Analytics Laboratory', 1, 1, 'lab', NULL, '2025-10-01 23:22:14'),
(90, 'DS 3120', 'Technical Report Writing and Presentation', 2, 4, 'theory', NULL, '2025-10-01 23:22:14');

-- --------------------------------------------------------

--
-- Table structure for table `course_contents`
--

CREATE TABLE `course_contents` (
  `content_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `content_type` enum('lecture_slide','reading','video','link','document','other') NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL COMMENT 'in bytes',
  `file_type` varchar(50) DEFAULT NULL COMMENT 'MIME type',
  `external_url` text DEFAULT NULL COMMENT 'For videos/links',
  `content_order` int(11) DEFAULT 1,
  `is_downloadable` tinyint(1) DEFAULT 1,
  `view_count` int(11) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_code` varchar(10) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_code`, `department_name`, `created_at`) VALUES
(1, 'CSE', 'Computer Science and Engineering', '2025-10-01 20:12:28'),
(2, 'EEE', 'Electrical and Electronic Engineering', '2025-10-01 20:12:28'),
(3, 'BBA', 'Business Administration', '2025-10-01 20:12:28'),
(4, 'ENG', 'English', '2025-10-01 20:12:28'),
(5, 'CE', 'Civil Engineering', '2025-10-01 20:12:28');

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `email_id` int(11) NOT NULL,
  `recipient_email` varchar(100) NOT NULL,
  `recipient_name` varchar(100) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `template_name` varchar(50) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('pending','sent','failed','cancelled') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `error_message` text DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `course_id` int(11) NOT NULL,
  `trimester_id` int(11) NOT NULL,
  `section` varchar(5) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `enrollment_date` date NOT NULL,
  `status` enum('enrolled','dropped','completed','failed') DEFAULT 'enrolled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enrollment_id`, `student_id`, `course_id`, `trimester_id`, `section`, `teacher_id`, `enrollment_date`, `status`, `created_at`) VALUES
(28, '0112320240', 32, 1, 'C', 3, '2025-10-19', 'enrolled', '2025-10-19 14:08:05'),
(29, '0112320240', 38, 1, 'B', 3, '2025-10-19', 'enrolled', '2025-10-19 14:08:05'),
(30, '0112320240', 39, 1, 'C', 3, '2025-10-19', 'enrolled', '2025-10-19 14:08:05'),
(31, '0112320240', 33, 1, 'I', 3, '2025-10-19', 'enrolled', '2025-10-19 14:08:05'),
(32, '0112320240', 24, 1, 'G', 3, '2025-10-19', 'enrolled', '2025-10-19 14:08:05'),
(34, '0112320240', 25, 1, 'K', 3, '2025-10-19', 'enrolled', '2025-10-19 14:08:05');

--
-- Triggers `enrollments`
--
DELIMITER $$
CREATE TRIGGER `trg_update_student_credits` AFTER UPDATE ON `enrollments` FOR EACH ROW BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE students s
        JOIN courses c ON NEW.course_id = c.course_id
        SET s.total_completed_credits = s.total_completed_credits + c.credit_hours
        WHERE s.student_id = NEW.student_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `exam_grades`
--

CREATE TABLE `exam_grades` (
  `exam_grade_id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `exam_type` enum('midterm','final','quiz','practical','viva') NOT NULL,
  `marks_obtained` decimal(5,2) NOT NULL,
  `total_marks` decimal(5,2) NOT NULL DEFAULT 100.00,
  `percentage` decimal(5,2) GENERATED ALWAYS AS (`marks_obtained` / `total_marks` * 100) STORED,
  `exam_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `entered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_routines`
--

CREATE TABLE `exam_routines` (
  `routine_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `trimester_id` int(11) NOT NULL,
  `exam_type` enum('Midterm','Final') NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_title` varchar(255) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `teacher_initial` varchar(50) DEFAULT NULL,
  `exam_date` date DEFAULT NULL,
  `exam_time` varchar(50) DEFAULT NULL,
  `room` varchar(100) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `uploaded_by` varchar(50) DEFAULT 'admin',
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_routines`
--

INSERT INTO `exam_routines` (`routine_id`, `department_id`, `trimester_id`, `exam_type`, `course_code`, `course_title`, `section`, `teacher_initial`, `exam_date`, `exam_time`, `room`, `original_filename`, `uploaded_by`, `upload_date`) VALUES
(946, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I', 'CA', 'MFRK', '2025-10-25', '09:00 AM - 11:00 AM', '302 (0312330017-0312520014)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(947, 1, 1, 'Final', 'CE 2201', 'Engineering Geology and Geomorphology', 'A', 'ShMS', '2025-10-25', '09:00 AM - 11:00 AM', '302 (031203010-0312430020)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(948, 1, 1, 'Final', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'A', 'SabAd', '2025-10-25', '09:00 AM - 11:00 AM', '325 (011182070-0112230029)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(949, 1, 1, 'Final', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'B', 'RbAn', '2025-10-25', '09:00 AM - 11:00 AM', '329 (011202283-0112230104)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(950, 1, 1, 'Final', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'C', 'ARnA', '2025-10-25', '09:00 AM - 11:00 AM', '401 (011162003-011221483)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(951, 1, 1, 'Final', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'D', 'ARnA', '2025-10-25', '09:00 AM - 11:00 AM', '403 (011192148-011222171)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(952, 1, 1, 'Final', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'E', 'KMRH', '2025-10-25', '09:00 AM - 11:00 AM', '425 (011182045-011221567)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(953, 1, 1, 'Final', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'F', 'RaK', '2025-10-25', '09:00 AM - 11:00 AM', '429 (011201043-011222129)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(954, 1, 1, 'Final', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'G', 'ARnA', '2025-10-25', '09:00 AM - 11:00 AM', '432 (011201059-011221217)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(955, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AA', 'ChMR', '2025-10-25', '09:00 AM - 11:00 AM', '307 (0112510086-0112520286)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(956, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AB', 'ChMR', '2025-10-25', '09:00 AM - 11:00 AM', '309 (0112330068-0112520049)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(957, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AC', 'AShK', '2025-10-25', '09:00 AM - 11:00 AM', '323 (0112230685-0112520277)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(958, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AD', 'AShK', '2025-10-25', '09:00 AM - 11:00 AM', '325 (011221015-0112520090)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(959, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AE', 'ChMR', '2025-10-25', '09:00 AM - 11:00 AM', '329 (011212126-0112520106)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(960, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AF', 'AShK', '2025-10-25', '09:00 AM - 11:00 AM', '401 (0112330056-0112520137)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(961, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AG', 'AShK', '2025-10-25', '09:00 AM - 11:00 AM', '403 (0112510061-0112520253)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(962, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AH', 'SaHn', '2025-10-25', '09:00 AM - 11:00 AM', '728 (0112510069-0152520077)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(963, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AI', 'ShMSh', '2025-10-25', '09:00 AM - 11:00 AM', '428 (0112330433-0112520218)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(964, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AJ', 'MARi', '2025-10-25', '09:00 AM - 11:00 AM', '725 (0112320080-0152520078)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(965, 1, 1, 'Final', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'A', 'ShSg', '2025-10-25', '09:00 AM - 11:00 AM', '432 (011191246-0112320171)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(966, 1, 1, 'Final', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'B', 'PPDR', '2025-10-25', '09:00 AM - 11:00 AM', '602 (011201252-0112310289)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(967, 1, 1, 'Final', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'C', 'NMAI', '2025-10-25', '09:00 AM - 11:00 AM', '604 (011201420-0112310597)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(968, 1, 1, 'Final', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'D', 'AbTr', '2025-10-25', '09:00 AM - 11:00 AM', '630 (011181193-0112230565)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(969, 1, 1, 'Final', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'E', 'RkAf', '2025-10-25', '09:00 AM - 11:00 AM', '632 (011213102-0152330147)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(970, 1, 1, 'Final', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'F', 'NMAI', '2025-10-25', '09:00 AM - 11:00 AM', '701 (011203005-0112310573)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(971, 1, 1, 'Final', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'G', 'AbTr', '2025-10-25', '09:00 AM - 11:00 AM', '703 (011202015-0112320052)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(972, 1, 1, 'Final', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'H', 'RkAf', '2025-10-25', '09:00 AM - 11:00 AM', '707 (011211034-0112230716)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(973, 1, 1, 'Final', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'I', 'PPDR', '2025-10-25', '09:00 AM - 11:00 AM', '711 (011201174-0112330221)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(974, 1, 1, 'Final', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'J', 'ShSg', '2025-10-25', '09:00 AM - 11:00 AM', '723 (011191053-0112320081)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(975, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'E', 'SaHn', '2025-10-25', '09:00 AM - 11:00 AM', '602 (0212410048-0212520048)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(976, 1, 1, 'Final', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'F', 'UH', '2025-10-25', '09:00 AM - 11:00 AM', '303 (0212420050-0212520028)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(977, 1, 1, 'Final', 'ENG 1013/ENG 103/ENG', 'English II', 'CA', 'KhJS', '2025-10-25', '11:30 AM - 01:30 PM', '307 (0152510002-0312510031)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(978, 1, 1, 'Final', 'CE 3231', 'Wastewater Engineering', 'A', 'MrRh', '2025-10-25', '11:30 AM - 01:30 PM', '307 (031211007-0312330019)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(979, 1, 1, 'Final', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AA', 'MZK', '2025-10-25', '11:30 AM - 01:30 PM', '328 (0112230626-0112510193)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(980, 1, 1, 'Final', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AB', 'MARi', '2025-10-25', '11:30 AM - 01:30 PM', '330 (0112231034-0112510120)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(981, 1, 1, 'Final', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AC', 'RnIm', '2025-10-25', '11:30 AM - 01:30 PM', '402 (011211045-0112510085)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(982, 1, 1, 'Final', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AD', 'ShMSh', '2025-10-25', '11:30 AM - 01:30 PM', '404 (0112231061-0112510306)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(983, 1, 1, 'Final', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AF', 'KhJS', '2025-10-25', '11:30 AM - 01:30 PM', '428 (0112230058-0112510155)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(984, 1, 1, 'Final', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AG', 'KhJS', '2025-10-25', '11:30 AM - 01:30 PM', '431 (0112420167-0112510253)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(985, 1, 1, 'Final', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AH', 'MZK', '2025-10-25', '11:30 AM - 01:30 PM', '601 (011202200-0112510166)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(986, 1, 1, 'Final', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AI', 'KhJS', '2025-10-25', '11:30 AM - 01:30 PM', '603 (011201410-0112510263)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(987, 1, 1, 'Final', 'DS 3885', 'Data Wrangling', 'BA', 'MdTH', '2025-10-25', '11:30 AM - 01:30 PM', '305 (0152310002-0152330112)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(988, 1, 1, 'Final', 'EEE 301/EEE 3107', 'Electrical Properties of Materials', 'A', 'IBC', '2025-10-25', '11:30 AM - 01:30 PM', '302 (021201068-0212310049)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(989, 1, 1, 'Final', 'EEE 301/EEE 3107', 'Electrical Properties of Materials', 'B', 'IBC', '2025-10-25', '11:30 AM - 01:30 PM', '304 (021182009-0212330152)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(990, 1, 1, 'Final', 'EEE 305/EEE 3205', 'Power System', 'A', 'SMLK', '2025-10-25', '11:30 AM - 01:30 PM', '305 (021131165-021221085)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(991, 1, 1, 'Final', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'F', 'ShMSh', '2025-10-25', '11:30 AM - 01:30 PM', '302 (0212330120-0212510035)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(992, 1, 1, 'Final', 'ENG 1013/ENG 1207', 'English II/Intensive English II', 'G', 'ChMR', '2025-10-25', '11:30 AM - 01:30 PM', '301 (0212330022-0212510046)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(993, 1, 1, 'Final', 'CHEM 1211', 'Chemistry', 'CA', 'SdRd', '2025-10-25', '02:00 PM - 04:00 PM', '303 (031221042-0312510001)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(994, 1, 1, 'Final', 'CHEM 1211', 'Chemistry', 'CB', 'SdRd', '2025-10-25', '02:00 PM - 04:00 PM', '711 (031221002-0312430068)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(995, 1, 1, 'Final', 'CE 4217', 'Design of Concrete Structures II', 'A', 'JAJ', '2025-10-25', '02:00 PM - 04:00 PM', '601 (031213008-0312230033)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(996, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'A', 'STT', '2025-10-25', '02:00 PM - 04:00 PM', '707 (011181177-0112330205)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(997, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'B', 'SAhSh', '2025-10-25', '02:00 PM - 04:00 PM', '304 (011181042-0112330292)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(998, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'C', 'HAN', '2025-10-25', '02:00 PM - 04:00 PM', '306 (011202272-0112320105)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(999, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'D', 'SAhSh', '2025-10-25', '02:00 PM - 04:00 PM', '308 (011221148-0112330412)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1000, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'E', 'SAhSh', '2025-10-25', '02:00 PM - 04:00 PM', '322 (011212006-0112330340)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1001, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'F', 'STT', '2025-10-25', '02:00 PM - 04:00 PM', '324 (011201316-0112330097)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1002, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'G', 'TaSa', '2025-10-25', '02:00 PM - 04:00 PM', '328 (011153072-0112330072)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1003, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'H', 'TaSa', '2025-10-25', '02:00 PM - 04:00 PM', '330 (011212011-0112320043)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1004, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'I', 'SAhSh', '2025-10-25', '02:00 PM - 04:00 PM', '402 (011172045-0112330067)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1005, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'J', 'STT', '2025-10-25', '02:00 PM - 04:00 PM', '404 (011193122-0112330237)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1006, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'K', 'MoIsm', '2025-10-25', '02:00 PM - 04:00 PM', '428 (011201310-0112330232)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1007, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'L', 'STT', '2025-10-25', '02:00 PM - 04:00 PM', '429 (011201401-0112330151)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1008, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'M', 'SAhSh', '2025-10-25', '02:00 PM - 04:00 PM', '432 (011182080-0112330360)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1009, 1, 1, 'Final', 'CSE 313/CSE 3313', 'Computer Architecture', 'O', 'MdMrRn', '2025-10-25', '02:00 PM - 04:00 PM', '602 (011162047-0112410508)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1010, 1, 1, 'Final', 'CSE 4889/CSE 489', 'Machine Learning', 'A', 'ARnA', '2025-10-25', '02:00 PM - 04:00 PM', '703 (011192073-011221292)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1011, 1, 1, 'Final', 'CSE 4889/CSE 489', 'Machine Learning', 'B', 'Ojn', '2025-10-25', '02:00 PM - 04:00 PM', '701 (011201209-011221334)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1012, 1, 1, 'Final', 'CSE 4889/CSE 489', 'Machine Learning', 'C', 'Ojn', '2025-10-25', '02:00 PM - 04:00 PM', '402 (011201194-011221263)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1013, 1, 1, 'Final', 'CSE 4889/CSE 489', 'Machine Learning', 'D', 'ShAhd', '2025-10-25', '02:00 PM - 04:00 PM', '308 (011183060-011221170)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1014, 1, 1, 'Final', 'CSE 4889/CSE 489', 'Machine Learning', 'E', 'SaIs', '2025-10-25', '02:00 PM - 04:00 PM', '322 (011203016-011221454)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1015, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'A', 'MMBK', '2025-10-25', '02:00 PM - 04:00 PM', '725 (011213198-0112430148)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1016, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'B', 'SMYA', '2025-10-25', '02:00 PM - 04:00 PM', '729 (011213212-0112430005)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1017, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'C', 'ShIA', '2025-10-25', '02:00 PM - 04:00 PM', '731 (011221316-0112430636)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1018, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'D', 'NzHn', '2025-10-25', '02:00 PM - 04:00 PM', '732 (011193157-0112430125)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1019, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'E', 'SamAr', '2025-10-25', '02:00 PM - 04:00 PM', '802 (011202157-0112430639)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1020, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'F', 'NPn', '2025-10-25', '02:00 PM - 04:00 PM', '803 (011221302-0112420544)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1021, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'G', 'AKMRn', '2025-10-25', '02:00 PM - 04:00 PM', '805 (011203048-0112430082)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1022, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'H', 'AKMRn', '2025-10-25', '02:00 PM - 04:00 PM', '901 (011221484-0112430710)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1023, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'I', 'MdAsn', '2025-10-25', '02:00 PM - 04:00 PM', '902 (011221136-0112420161)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1024, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'J', 'SamAr', '2025-10-25', '02:00 PM - 04:00 PM', '904 (011213172-0112430706)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1025, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'K', 'SIsm', '2025-10-25', '02:00 PM - 04:00 PM', '907 (011221268-0112430730)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1026, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'L', 'NzHn', '2025-10-25', '02:00 PM - 04:00 PM', '932 (011222094-0112430075)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1027, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'M', 'SIsm', '2025-10-25', '02:00 PM - 04:00 PM', '1029 (011201079-0112430740)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1028, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'N', 'MdAsn', '2025-10-25', '02:00 PM - 04:00 PM', '1030 (0112230188-0112430122)                                                                        ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1029, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'O', 'SamAr', '2025-10-25', '02:00 PM - 04:00 PM', '602 (011191001-0112430133)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1030, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'P', 'NSM', '2025-10-25', '02:00 PM - 04:00 PM', '302 (011221241-0112430720)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1031, 1, 1, 'Final', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'Q', 'MMBK', '2025-10-25', '02:00 PM - 04:00 PM', '431 (011201122-0112430029)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1032, 1, 1, 'Final', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'A', 'ShAhd', '2025-10-25', '02:00 PM - 04:00 PM', '703 (011213054-0112330779)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1033, 1, 1, 'Final', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'B', 'MdShA', '2025-10-25', '02:00 PM - 04:00 PM', '701 (011202283-0112331033)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1034, 1, 1, 'Final', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'C', 'AHMOH', '2025-10-25', '02:00 PM - 04:00 PM', '631 (011162109-0112330535)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1035, 1, 1, 'Final', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'D', 'MiBa', '2025-10-25', '02:00 PM - 04:00 PM', '605 (011202015-0112330763)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1036, 1, 1, 'Final', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'E', 'KAN', '2025-10-25', '02:00 PM - 04:00 PM', '603 (011222081-0112331048)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1037, 1, 1, 'Final', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'F', 'AbHn', '2025-10-25', '02:00 PM - 04:00 PM', '324 (011221099-0112330746)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1038, 1, 1, 'Final', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'G', 'MTR', '2025-10-25', '02:00 PM - 04:00 PM', '328 (011193074-0112410282)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1039, 1, 1, 'Final', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'H', 'KBJ', '2025-10-25', '02:00 PM - 04:00 PM', '330 (011203006-0112330347)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1040, 1, 1, 'Final', 'MATH 2107', 'Linear Algebra', 'BA', 'AkAd', '2025-10-25', '02:00 PM - 04:00 PM', '302 (0152330012-0152430033)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1041, 1, 1, 'Final', 'MATH 2107', 'Linear Algebra', 'BB', 'AkAd', '2025-10-25', '02:00 PM - 04:00 PM', '304 (0152310008-0152430090)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1042, 1, 1, 'Final', 'EEE 2101', 'Electronics I', 'B', 'BKM', '2025-10-25', '02:00 PM - 04:00 PM', '305 (021201089-0212430008)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1043, 1, 1, 'Final', 'EEE 2101', 'Electronics I', 'A', 'BKM', '2025-10-25', '02:00 PM - 04:00 PM', '307 (021191026-0212430079)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1044, 1, 1, 'Final', 'EEE 401/EEE 4109', 'Control System', 'B', 'MKMR', '2025-10-25', '02:00 PM - 04:00 PM', '428 (021162038-021213004)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1045, 1, 1, 'Final', 'EEE 401/EEE 4109', 'Control System', 'A', 'BKM', '2025-10-25', '02:00 PM - 04:00 PM', '404 (021131142-021221026)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1046, 1, 1, 'Final', 'MAT 2109/MATH 201', 'Coordinate Geometry and Vector Analysis', 'A', 'TN', '2025-10-25', '02:00 PM - 04:00 PM', '707 (021193005-0212330016)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1047, 1, 1, 'Final', 'CSE 3521/CSI 221', 'Database Management Systems', 'A', 'DID', '2025-10-26', '09:00 AM - 11:00 AM', '602 (011153072-0112310081)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1048, 1, 1, 'Final', 'CSE 3521/CSI 221', 'Database Management Systems', 'B', 'MahHsn', '2025-10-26', '09:00 AM - 11:00 AM', '604 (011203045-0112310484)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1049, 1, 1, 'Final', 'CSE 3521/CSI 221', 'Database Management Systems', 'C', 'TBD', '2025-10-26', '09:00 AM - 11:00 AM', '630 (011202103-0112310358)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1050, 1, 1, 'Final', 'CSE 3521/CSI 221', 'Database Management Systems', 'D', 'SaIs', '2025-10-26', '09:00 AM - 11:00 AM', '632 (011162109-0112310218)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1051, 1, 1, 'Final', 'CSE 3521/CSI 221', 'Database Management Systems', 'E', 'TBS', '2025-10-26', '09:00 AM - 11:00 AM', '702 (011202035-0112310074)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1052, 1, 1, 'Final', 'CSE 3521/CSI 221', 'Database Management Systems', 'F', 'SaIs', '2025-10-26', '09:00 AM - 11:00 AM', '706 (011183060-0112230806)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1053, 1, 1, 'Final', 'CSE 3521/CSI 221', 'Database Management Systems', 'G', 'SaIs', '2025-10-26', '09:00 AM - 11:00 AM', '708 (011192099-0112310363)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1054, 1, 1, 'Final', 'CSE 3521/CSI 221', 'Database Management Systems', 'H', 'SMSR', '2025-10-26', '09:00 AM - 11:00 AM', '722 (011201209-0112310122)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1055, 1, 1, 'Final', 'CSE 3521/CSI 221', 'Database Management Systems', 'I', 'FAH', '2025-10-26', '09:00 AM - 11:00 AM', '724 (011182070-0112310115)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1056, 1, 1, 'Final', 'CSE 4611/CSI 411', 'Compiler/Compiler Design', 'A', 'NSS', '2025-10-26', '09:00 AM - 11:00 AM', '305 (011172007-011221039)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1057, 1, 1, 'Final', 'CSE 3521/CSI 221', 'Database Management Systems', 'BB', 'MahHsn', '2025-10-26', '09:00 AM - 11:00 AM', '305 (015221001-0152330067)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1058, 1, 1, 'Final', 'CSE 3521/CSI 221', 'Database Management Systems', 'BA', 'MahHsn', '2025-10-26', '09:00 AM - 11:00 AM', '304 (015222002-0152330142)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1059, 1, 1, 'Final', 'MAT 1103', 'Calculus II', 'B', 'AM', '2025-10-26', '09:00 AM - 11:00 AM', '302 (0212330055-0212510045)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1060, 1, 1, 'Final', 'MAT 1103/MATH 151', 'Calculus II/Differential and Integral Calculus', 'A', 'ShIA', '2025-10-26', '09:00 AM - 11:00 AM', '301 (021212006-0212510038)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1061, 1, 1, 'Final', 'SOC 3101', 'Society, Environment and Engineering Ethics', 'A', 'JJM', '2025-10-26', '09:00 AM - 11:00 AM', '307 (021193023-0212230026)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1062, 1, 1, 'Final', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'A', 'ShMd', '2025-10-26', '11:30 AM - 01:30 PM', '302 (011191060-0112310423)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1063, 1, 1, 'Final', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'B', 'KMRH', '2025-10-26', '11:30 AM - 01:30 PM', '303 (011203065-0112230693)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1064, 1, 1, 'Final', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'C', 'MSTR', '2025-10-26', '11:30 AM - 01:30 PM', '305 (011181116-0112230247)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1065, 1, 1, 'Final', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'D', 'KMRH', '2025-10-26', '11:30 AM - 01:30 PM', '307 (011193060-0112230425)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1066, 1, 1, 'Final', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'E', 'MSTR', '2025-10-26', '11:30 AM - 01:30 PM', '309 (011183016-011222177)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1067, 1, 1, 'Final', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'F', 'RNF', '2025-10-26', '11:30 AM - 01:30 PM', '323 (011181303-0112310317)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1068, 1, 1, 'Final', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'G', 'KMRH', '2025-10-26', '11:30 AM - 01:30 PM', '324 (011221076-0112230339)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1069, 1, 1, 'Final', 'EEE 207/EEE 2103', 'Electronics II', 'A', 'SdM', '2025-10-26', '11:30 AM - 01:30 PM', '306 (021193025-0212410002)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1070, 1, 1, 'Final', 'EEE 207/EEE 2103', 'Electronics II', 'B', 'SwMr', '2025-10-26', '11:30 AM - 01:30 PM', '308 (021131142-0212330106)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1071, 1, 1, 'Final', 'IPE 401/IPE 4101', 'Industrial Management/Industrial Production Engineering', 'A', 'PPDR', '2025-10-26', '11:30 AM - 01:30 PM', '303 (021193032-021221042)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1072, 1, 1, 'Final', 'ACT 111/ACT 2111', 'Financial and Managerial Accounting', 'A', 'ItJn', '2025-10-26', '02:00 PM - 04:00 PM', '302 (011191277-0112231034)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1073, 1, 1, 'Final', 'ACT 111/ACT 2111', 'Financial and Managerial Accounting', 'B', 'IJ', '2025-10-26', '02:00 PM - 04:00 PM', '304 (011201048-0112310412)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1074, 1, 1, 'Final', 'ACT 111/ACT 2111', 'Financial and Managerial Accounting', 'C', 'IJ', '2025-10-26', '02:00 PM - 04:00 PM', '306 (011172045-0112330082)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1075, 1, 1, 'Final', 'CSE 4893', 'Introduction to Bioinformatics', 'A', 'RtAm', '2025-10-26', '02:00 PM - 04:00 PM', '301 (011183087-0112230227)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1076, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'A', 'JAS', '2025-10-26', '02:00 PM - 04:00 PM', '604 (011163060-0112330031)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1077, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'B', 'MUn', '2025-10-26', '02:00 PM - 04:00 PM', '630 (011162003-0112410169)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1078, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'C', 'MUn', '2025-10-26', '02:00 PM - 04:00 PM', '632 (011212022-0112331034)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1079, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'D', 'MUn', '2025-10-26', '02:00 PM - 04:00 PM', '702 (011191059-0112410001)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1080, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'E', 'AkAd', '2025-10-26', '02:00 PM - 04:00 PM', '706 (011181190-0152410007)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1081, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'F', 'MoIm', '2025-10-26', '02:00 PM - 04:00 PM', '707 (011192034-0112330248)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1082, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'G', 'MUn', '2025-10-26', '02:00 PM - 04:00 PM', '711 (011221093-0112331009)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1083, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'H', 'MoIm', '2025-10-26', '02:00 PM - 04:00 PM', '723 (011203054-0112330328)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1084, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'I', 'AKMRn', '2025-10-26', '02:00 PM - 04:00 PM', '725 (011193123-0112330389)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1085, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'J', 'AKMRn', '2025-10-26', '02:00 PM - 04:00 PM', '729 (011192131-0112320155)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1086, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'K', 'MoIm', '2025-10-26', '02:00 PM - 04:00 PM', '731 (011202081-0112330949)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1087, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'L', 'AkAd', '2025-10-26', '02:00 PM - 04:00 PM', '801 (011193065-0112410480)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1088, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'M', 'JAS', '2025-10-26', '02:00 PM - 04:00 PM', '803 (011193102-0112320128)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1089, 1, 1, 'Final', 'MATH 2205/STAT 205', 'Probability and Statistics', 'N', 'SamAr', '2025-10-26', '02:00 PM - 04:00 PM', '805 (011202157-0152330078)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1090, 1, 1, 'Final', 'EEE 203/EEE 2201', 'Energy Conversion I', 'B', 'MFK', '2025-10-26', '02:00 PM - 04:00 PM', '302 (021173033-0212330020)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1091, 1, 1, 'Final', 'EEE 203/EEE 2201', 'Energy Conversion I', 'A', 'AkHd', '2025-10-26', '02:00 PM - 04:00 PM', '304 (021191010-0212320015)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1092, 1, 1, 'Final', 'EEE 4121/EEE 441', 'VLSI Design', 'A', 'MdHn', '2025-10-26', '02:00 PM - 04:00 PM', '308 (021181027-021221026)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1093, 1, 1, 'Final', 'MATH 1101', 'Differential and Integral Calculus', 'A', 'SIsm', '2025-10-27', '09:00 AM - 11:00 AM', '302 (031221042-0312520014)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1094, 1, 1, 'Final', 'CE 4151', 'Highway Design and Railway Engineering', 'A', 'NT', '2025-10-27', '09:00 AM - 11:00 AM', '301 (031211007-0312310001)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1095, 1, 1, 'Final', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'A', 'FzAn', '2025-10-27', '09:00 AM - 11:00 AM', '602 (011173058-0112320059)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1096, 1, 1, 'Final', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'B', 'AHMOH', '2025-10-27', '09:00 AM - 11:00 AM', '603 (011181142-0112230742)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1097, 1, 1, 'Final', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'C', 'AHMOH', '2025-10-27', '09:00 AM - 11:00 AM', '605 (011192088-0112231023)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1098, 1, 1, 'Final', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'D', 'SMSR', '2025-10-27', '09:00 AM - 11:00 AM', '631 (011193029-0112230034)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1099, 1, 1, 'Final', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'E', 'SMSR', '2025-10-27', '09:00 AM - 11:00 AM', '701 (011183078-0112230331)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1100, 1, 1, 'Final', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'F', 'ShAhd', '2025-10-27', '09:00 AM - 11:00 AM', '703 (011201356-0112330124)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1101, 1, 1, 'Final', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'G', 'ShAhd', '2025-10-27', '09:00 AM - 11:00 AM', '706 (011201441-0112330457)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1102, 1, 1, 'Final', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'H', 'AHMOH', '2025-10-27', '09:00 AM - 11:00 AM', '707 (011191246-0112230744)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1103, 1, 1, 'Final', 'CSE 4451/CSE 451', 'Human Computer Interaction', 'A', 'NoNn', '2025-10-27', '09:00 AM - 11:00 AM', '306 (011191078-011221044)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1104, 1, 1, 'Final', 'CSE 4451/CSE 451', 'Human Computer Interaction', 'B', 'NoNn', '2025-10-27', '09:00 AM - 11:00 AM', '308 (011183065-011212069)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1105, 1, 1, 'Final', 'EEE 205/EEE 2203', 'Energy Conversion II', 'A', 'HB', '2025-10-27', '09:00 AM - 11:00 AM', '303 (021182041-0212230064)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1106, 1, 1, 'Final', 'EEE 205/EEE 2203', 'Energy Conversion II', 'B', 'SMB', '2025-10-27', '09:00 AM - 11:00 AM', '305 (021131142-021221098)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1107, 1, 1, 'Final', 'MAT 1101', 'Calculus I', 'B', 'MMBK', '2025-10-27', '09:00 AM - 11:00 AM', '302 (021222058-0212510039)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1108, 1, 1, 'Final', 'MAT 1101/MATH 151', 'Calculus I/Differential and Integral Calculus', 'A', 'AM', '2025-10-27', '09:00 AM - 11:00 AM', '308 (021181106-0212520014)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23');
INSERT INTO `exam_routines` (`routine_id`, `department_id`, `trimester_id`, `exam_type`, `course_code`, `course_title`, `section`, `teacher_initial`, `exam_date`, `exam_time`, `room`, `original_filename`, `uploaded_by`, `upload_date`) VALUES
(1109, 1, 1, 'Final', 'CE 2101', 'Engineering Materials', 'CA', 'NT', '2025-10-27', '11:30 AM - 01:30 PM', '302 (031211005-0312430065)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1110, 1, 1, 'Final', 'CE 2101', 'Engineering Materials', 'CB', 'NT', '2025-10-27', '11:30 AM - 01:30 PM', '303 (0312230003-0312430068)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1111, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'A', 'FT', '2025-10-27', '11:30 AM - 01:30 PM', '802 (011202339-0112520006)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1112, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'B', 'ArBh', '2025-10-27', '11:30 AM - 01:30 PM', '804 (0112230109-0112430458)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1113, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'C', 'DID', '2025-10-27', '11:30 AM - 01:30 PM', '806 (0112331006-0112520066)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1114, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'D', 'MdMrRn', '2025-10-27', '11:30 AM - 01:30 PM', '902 (011221146-0112520095)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1115, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'E', 'RbAn', '2025-10-27', '11:30 AM - 01:30 PM', '904 (011213052-0112520112)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1116, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'F', 'CAG', '2025-10-27', '11:30 AM - 01:30 PM', '932 (0112230968-0112520061)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1117, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'G', 'FT', '2025-10-27', '11:30 AM - 01:30 PM', '1029 (011201347-0112510231)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1118, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'H', 'RRK', '2025-10-27', '11:30 AM - 01:30 PM', '1031 (011202136-0112430051)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1119, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'I', 'HHCh', '2025-10-27', '11:30 AM - 01:30 PM', '324 (0112230308-0112520197)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1120, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'J', 'SabAd', '2025-10-27', '11:30 AM - 01:30 PM', '304 (011221478-0112520181)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1121, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'K', 'RaHR', '2025-10-27', '11:30 AM - 01:30 PM', '306 (0112330689-0112520263)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1122, 1, 1, 'Final', 'CSE 469/PMG 4101', 'Project Management', 'A', 'MdMH', '2025-10-27', '11:30 AM - 01:30 PM', '324 (011192048-0112230115)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1123, 1, 1, 'Final', 'CSE 469/PMG 4101', 'Project Management', 'B', 'MdMH', '2025-10-27', '11:30 AM - 01:30 PM', '304 (011181018-011221307)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1124, 1, 1, 'Final', 'CSE 469/PMG 4101', 'Project Management', 'C', 'RJR', '2025-10-27', '11:30 AM - 01:30 PM', '306 (011191176-011221052)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1125, 1, 1, 'Final', 'CSE 469/PMG 4101', 'Project Management', 'D', 'SA', '2025-10-27', '11:30 AM - 01:30 PM', '308 (011163072-011221435)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1126, 1, 1, 'Final', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'BA', 'KBJ', '2025-10-27', '11:30 AM - 01:30 PM', '322 (0152330036-0152430014)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1127, 1, 1, 'Final', 'EEE 3403/EEE 423', 'Microprocessor and Interfacing', 'A', 'SMLK', '2025-10-27', '11:30 AM - 01:30 PM', '308 (021181065-021221052)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1128, 1, 1, 'Final', 'PHY 1103', 'Physics II', 'A', 'MASn', '2025-10-27', '11:30 AM - 01:30 PM', '302 (021201071-0212510045)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1129, 1, 1, 'Final', 'PHY 1103', 'Physics II', 'B', 'MASn', '2025-10-27', '11:30 AM - 01:30 PM', '303 (0212410040-0212430091)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1130, 1, 1, 'Final', 'EEE 1201', 'Basic Electrical Engineering', 'A', 'SwMr', '2025-10-27', '02:00 PM - 04:00 PM', '302 (031213003-0312510031)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:23'),
(1131, 1, 1, 'Final', 'CE 3241', 'Soil Mechanics', 'A', 'TRnA', '2025-10-27', '02:00 PM - 04:00 PM', '303 (031203010-0312310018)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1132, 1, 1, 'Final', 'BIO 3105', 'Biology for Engineers', 'A', 'HAA', '2025-10-27', '02:00 PM - 04:00 PM', '308 (011202103-0112420547)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1133, 1, 1, 'Final', 'BIO 3105', 'Biology for Engineers', 'B', 'HAA', '2025-10-27', '02:00 PM - 04:00 PM', '309 (011191152-0112230828)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1134, 1, 1, 'Final', 'BIO 3105', 'Biology for Engineers', 'C', 'NaTa', '2025-10-27', '02:00 PM - 04:00 PM', '323 (011191053-0112230945)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1135, 1, 1, 'Final', 'BIO 3105', 'Biology for Engineers', 'D', 'BHR', '2025-10-27', '02:00 PM - 04:00 PM', '325 (011191001-0112310037)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1136, 1, 1, 'Final', 'BIO 3105', 'Biology for Engineers', 'E', 'SMRI', '2025-10-27', '02:00 PM - 04:00 PM', '329 (011212096-0112310203)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1137, 1, 1, 'Final', 'BIO 3105', 'Biology for Engineers', 'F', 'HAA', '2025-10-27', '02:00 PM - 04:00 PM', '401 (011221119-0112520264)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1138, 1, 1, 'Final', 'BIO 3105', 'Biology for Engineers', 'G', 'SMRI', '2025-10-27', '02:00 PM - 04:00 PM', '402 (011193102-0112230991)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1139, 1, 1, 'Final', 'BIO 3105', 'Biology for Engineers', 'H', 'BHR', '2025-10-27', '02:00 PM - 04:00 PM', '404 (011201399-0112310173)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1140, 1, 1, 'Final', 'BIO 3105', 'Biology for Engineers', 'I', 'KhSh', '2025-10-27', '02:00 PM - 04:00 PM', '428 (011221062-0112230990)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1141, 1, 1, 'Final', 'BIO 3105', 'Biology for Engineers', 'J', 'MobIm', '2025-10-27', '02:00 PM - 04:00 PM', '431 (011192048-0112230900)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1142, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'A', 'AsTn', '2025-10-27', '02:00 PM - 04:00 PM', '604 (0112230686-0112430038)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1143, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'B', 'HAN', '2025-10-27', '02:00 PM - 04:00 PM', '630 (011202100-0112430075)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1144, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'C', 'MBAd', '2025-10-27', '02:00 PM - 04:00 PM', '632 (011222164-0112430640)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1145, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'D', 'SmSd', '2025-10-27', '02:00 PM - 04:00 PM', '701 (0112230023-0112430291)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1146, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'E', 'MSTR', '2025-10-27', '02:00 PM - 04:00 PM', '703 (0112230626-0112430354)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1147, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'F', 'RtAm', '2025-10-27', '02:00 PM - 04:00 PM', '707 (0112230361-0112430061)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1148, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'G', 'MBAd', '2025-10-27', '02:00 PM - 04:00 PM', '711 (011202170-0112510321)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1149, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'H', 'AsTn', '2025-10-27', '02:00 PM - 04:00 PM', '722 (011221499-0112430212)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1150, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'I', 'AsTn', '2025-10-27', '02:00 PM - 04:00 PM', '724 (011221488-0112430088)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1151, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'J', 'MSTR', '2025-10-27', '02:00 PM - 04:00 PM', '728 (011222320-0112430115)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1152, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'K', 'TW', '2025-10-27', '02:00 PM - 04:00 PM', '730 (0112230151-0112430157)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1153, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'L', 'MMIN', '2025-10-27', '02:00 PM - 04:00 PM', '732 (0112330250-0112430289)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1154, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'M', 'SmSd', '2025-10-27', '02:00 PM - 04:00 PM', '802 (0112310273-0112430253)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1155, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'N', 'RtAm', '2025-10-27', '02:00 PM - 04:00 PM', '804 (0112320266-0112430409)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1156, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'O', 'ShMd', '2025-10-27', '02:00 PM - 04:00 PM', '806 (011222286-0112430320)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1157, 1, 1, 'Final', 'CSE 1325/CSE 225', 'Digital Logic Design', 'P', 'TBS', '2025-10-27', '02:00 PM - 04:00 PM', '902 (0112320120-0112430246)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1158, 1, 1, 'Final', 'BIO 3107', 'Biology', 'BA', 'NaTa', '2025-10-27', '02:00 PM - 04:00 PM', '305 (0152330055-0152510067)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1159, 1, 1, 'Final', 'EEE 121/EEE 2401', 'Structured Programming Language', 'A', 'SwMr', '2025-10-27', '02:00 PM - 04:00 PM', '322 (021161098-0212330088)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1160, 1, 1, 'Final', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'A', 'AAU', '2025-10-28', '09:00 AM - 11:00 AM', '801 (011201422-0112420479)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1161, 1, 1, 'Final', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'B', 'AAU', '2025-10-28', '09:00 AM - 11:00 AM', '802 (011201061-0112330708)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1162, 1, 1, 'Final', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'C', 'NSS', '2025-10-28', '09:00 AM - 11:00 AM', '804 (011213064-0112331025)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1163, 1, 1, 'Final', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'D', 'MiBa', '2025-10-28', '09:00 AM - 11:00 AM', '806 (011193142-0112410476)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1164, 1, 1, 'Final', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'E', 'AAU', '2025-10-28', '09:00 AM - 11:00 AM', '901 (011183014-0112330235)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1165, 1, 1, 'Final', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'F', 'KAN', '2025-10-28', '09:00 AM - 11:00 AM', '903 (011201059-0112330255)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1166, 1, 1, 'Final', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'G', 'KAN', '2025-10-28', '09:00 AM - 11:00 AM', '907 (011213134-0112330616)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1167, 1, 1, 'Final', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'H', 'NSS', '2025-10-28', '09:00 AM - 11:00 AM', '1028 (011193123-0112320134)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1168, 1, 1, 'Final', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'I', 'MMIN', '2025-10-28', '09:00 AM - 11:00 AM', '1030 (011182080-0112410008)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1169, 1, 1, 'Final', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'J', 'MiBa', '2025-10-28', '09:00 AM - 11:00 AM', '1032 (011211098-0112410525)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1170, 1, 1, 'Final', 'CSE 4165/CSE 465', 'Web Programming', 'A', 'NHn', '2025-10-28', '09:00 AM - 11:00 AM', '424 (Computer Lab) (011172007-011221450)                                                            ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1171, 1, 1, 'Final', 'CSE 4165/CSE 465', 'Web Programming', 'B', 'NHn', '2025-10-28', '09:00 AM - 11:00 AM', '427 (Computer Lab) (011201174-011221256)                                                            ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1172, 1, 1, 'Final', 'CSE 4165/CSE 465', 'Web Programming', 'C', 'NHn', '2025-10-28', '09:00 AM - 11:00 AM', '523 (Computer Lab) (011163072-0112320074)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1173, 1, 1, 'Final', 'CSE 4165/CSE 465', 'Web Programming', 'D', 'NHn', '2025-10-28', '09:00 AM - 11:00 AM', '524 (Computer Lab) (011183048-011222058)                                                            ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1174, 1, 1, 'Final', 'EEE 1003', 'Electrical Circuits II', 'B', 'IA', '2025-10-28', '09:00 AM - 11:00 AM', '303 (021212006-0212510038)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1175, 1, 1, 'Final', 'EEE 1003', 'Electrical Circuits II', 'A', 'HB', '2025-10-28', '09:00 AM - 11:00 AM', '304 (021213051-0212510045)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1176, 1, 1, 'Final', 'EEE 303/EEE 3305', 'Engineering Electromagnetics', 'A', 'BKM', '2025-10-28', '09:00 AM - 11:00 AM', '301 (021171078-021222052)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1177, 1, 1, 'Final', 'CSE 1111', 'Structured Programming Language', 'M', 'MHO', '2025-10-28', '11:30 AM - 01:30 PM', '425 (011202157-0112430395)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1178, 1, 1, 'Final', 'CSE 1111', 'Structured Programming Language', 'N', 'PB', '2025-10-28', '11:30 AM - 01:30 PM', '429 (011202100-0112430176)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1179, 1, 1, 'Final', 'CSE 1111', 'Structured Programming Language', 'O', 'SmSd', '2025-10-28', '11:30 AM - 01:30 PM', '432 (011202170-0112430654)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1180, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'A', 'MNH', '2025-10-28', '11:30 AM - 01:30 PM', '601 (011193072-0112430173)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1181, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'B', 'MNH', '2025-10-28', '11:30 AM - 01:30 PM', '603 (011201115-0112430557)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1182, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'C', 'MNH', '2025-10-28', '11:30 AM - 01:30 PM', '605 (011201052-0112430026)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1183, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'D', 'HS', '2025-10-28', '11:30 AM - 01:30 PM', '631 (011203038-0112430430)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1184, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'E', 'MMAS', '2025-10-28', '11:30 AM - 01:30 PM', '701 (011213054-0112510010)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1185, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'F', 'MBAd', '2025-10-28', '11:30 AM - 01:30 PM', '703 (011221049-0112510334)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1186, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'G', 'MShH', '2025-10-28', '11:30 AM - 01:30 PM', '706 (0112310196-0112510360)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1187, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'H', 'MNH', '2025-10-28', '11:30 AM - 01:30 PM', '707 (011221454-0112430743)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1188, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'I', 'NSS', '2025-10-28', '11:30 AM - 01:30 PM', '711 (011193086-0112420433)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1189, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'J', 'MMAS', '2025-10-28', '11:30 AM - 01:30 PM', '723 (0112230187-0112430109)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1190, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'K', 'RaK', '2025-10-28', '11:30 AM - 01:30 PM', '725 (011221158-0112430067)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1191, 1, 1, 'Final', 'CSE 1111/CSI 121', 'Structured Programming Language', 'L', 'SSSk', '2025-10-28', '11:30 AM - 01:30 PM', '729 (0112230293-0112510150)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1192, 1, 1, 'Final', 'CSE 4435', 'Software Architecture', 'A', 'TBD', '2025-10-28', '11:30 AM - 01:30 PM', '303 (011191271-0112310573)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1193, 1, 1, 'Final', 'CSE 4435', 'Software Architecture', 'B', 'TBD', '2025-10-28', '11:30 AM - 01:30 PM', '304 (011191112-011221007)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1194, 1, 1, 'Final', 'DS 1501', 'Programming for Data Science', 'BB', 'KBJ', '2025-10-28', '11:30 AM - 01:30 PM', '308 (0152310006-0152520021)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1195, 1, 1, 'Final', 'DS 1501', 'Programming for Data Science', 'BC', 'TaMo', '2025-10-28', '11:30 AM - 01:30 PM', '322 (0152330029-0152520048)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1196, 1, 1, 'Final', 'DS 1501', 'Programming for Data Science', 'BA', 'KBJ', '2025-10-28', '11:30 AM - 01:30 PM', '324 (0152410007-0152510065)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1197, 1, 1, 'Final', 'CHE 2101', 'Chemistry', 'A', 'MASq', '2025-10-28', '11:30 AM - 01:30 PM', '302 (021213027-0212420002)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1198, 1, 1, 'Final', 'CHE 2101', 'Chemistry', 'B', 'MASq', '2025-10-28', '11:30 AM - 01:30 PM', '304 (021213053-0212420018)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1199, 1, 1, 'Final', 'EEE 307/EEE 3207', 'Power Electronics', 'A', 'IA', '2025-10-28', '11:30 AM - 01:30 PM', '301 (021131142-021202005)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1200, 1, 1, 'Final', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'A', 'ME', '2025-10-28', '02:00 PM - 04:00 PM', '330 (011162109-021222030)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1201, 1, 1, 'Final', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'B', 'AKMMI', '2025-10-28', '02:00 PM - 04:00 PM', '303 (011172009-0112230425)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1202, 1, 1, 'Final', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'C', 'ME', '2025-10-28', '02:00 PM - 04:00 PM', '305 (011212021-0112230492)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1203, 1, 1, 'Final', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'D', 'MNK', '2025-10-28', '02:00 PM - 04:00 PM', '307 (011203057-021212027)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1204, 1, 1, 'Final', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'E', 'ME', '2025-10-28', '02:00 PM - 04:00 PM', '308 (011202035-021162038)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1205, 1, 1, 'Final', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'F', 'AKMMI', '2025-10-28', '02:00 PM - 04:00 PM', '309 (011192127-011221550)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1206, 1, 1, 'Final', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'G', 'ASKP', '2025-10-28', '02:00 PM - 04:00 PM', '323 (011202166-0112230471)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1207, 1, 1, 'Final', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'H', 'MNK', '2025-10-28', '02:00 PM - 04:00 PM', '325 (011192011-0112230378)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1208, 1, 1, 'Final', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'I', 'MdMH', '2025-10-28', '02:00 PM - 04:00 PM', '329 (011213122-0112331064)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1209, 1, 1, 'Final', 'DS 3101', 'Advanced Probability and Statistics', 'BA', 'TaMo', '2025-10-28', '02:00 PM - 04:00 PM', '301 (0152230003-0152330074)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1210, 1, 1, 'Final', 'EEE 2105/EEE 223', 'Digital Electronics', 'A', 'TTM', '2025-10-28', '02:00 PM - 04:00 PM', '401 (021182041-0212330015)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1211, 1, 1, 'Final', 'EEE 313/EEE 4111', 'Solid State Devices', 'A', 'IBC', '2025-10-28', '02:00 PM - 04:00 PM', '401 (021183015-021221027)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1212, 1, 1, 'Final', 'CE 2211', 'Mechanics of Solids II', 'A', 'TRnA', '2025-10-29', '09:00 AM - 11:00 AM', '302 (0312310005-0312410022)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1213, 1, 1, 'Final', 'CE 3211', 'Design of Steel Structures', 'A', 'JAJ', '2025-10-29', '09:00 AM - 11:00 AM', '432 (031203010-0312230037)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1214, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'A', 'MAnAm', '2025-10-29', '09:00 AM - 11:00 AM', '303 (011201353-0112230117)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1215, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'B', 'NZMa', '2025-10-29', '09:00 AM - 11:00 AM', '630 (011172009-0112230816)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1216, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'C', 'SA', '2025-10-29', '09:00 AM - 11:00 AM', '307 (011203010-0112230524)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1217, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'D', 'HHCh', '2025-10-29', '09:00 AM - 11:00 AM', '309 (011201441-0112230472)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1218, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'E', 'DID', '2025-10-29', '09:00 AM - 11:00 AM', '323 (011202177-0112230955)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1219, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'F', 'NZMa', '2025-10-29', '09:00 AM - 11:00 AM', '325 (011213125-0112230437)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1220, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'G', 'SiMa', '2025-10-29', '09:00 AM - 11:00 AM', '329 (011202038-0112230965)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1221, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'H', 'SiMa', '2025-10-29', '09:00 AM - 11:00 AM', '401 (011201091-0112230571)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1222, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'I', 'TaSa', '2025-10-29', '09:00 AM - 11:00 AM', '403 (011201376-0112230641)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1223, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'J', 'TaSa', '2025-10-29', '09:00 AM - 11:00 AM', '425 (011203064-0112230542)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1224, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'K', 'MAnAm', '2025-10-29', '09:00 AM - 11:00 AM', '429 (011202338-0112230231)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1225, 1, 1, 'Final', 'CSE 3411/CSI 311', 'System Analysis and Design', 'L', 'SiMa', '2025-10-29', '09:00 AM - 11:00 AM', '602 (011192026-0112230478)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1226, 1, 1, 'Final', 'EEE 4261', 'Green Computing', 'A', 'MdMIm', '2025-10-29', '09:00 AM - 11:00 AM', '303 (011183048-011213078)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1227, 1, 1, 'Final', 'EEE 4261', 'Green Computing', 'B', 'MNTA', '2025-10-29', '09:00 AM - 11:00 AM', '305 (011183065-011213009)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1228, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'A', 'ShIA', '2025-10-29', '09:00 AM - 11:00 AM', '305 (011163072-0112430617)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1229, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'B', 'MIA', '2025-10-29', '09:00 AM - 11:00 AM', '306 (011172007-0112420212)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1230, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'C', 'MIA', '2025-10-29', '09:00 AM - 11:00 AM', '308 (011211053-0112420004)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1231, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'D', 'MIA', '2025-10-29', '09:00 AM - 11:00 AM', '322 (011163056-0112410283)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1232, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'E', 'MdAIm', '2025-10-29', '09:00 AM - 11:00 AM', '324 (011173058-0112320184)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1233, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'F', 'SiMu', '2025-10-29', '09:00 AM - 11:00 AM', '328 (011161085-0112330021)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1234, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'G', 'NzHn', '2025-10-29', '09:00 AM - 11:00 AM', '330 (011191271-0112330589)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1235, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'H', 'NzHn', '2025-10-29', '09:00 AM - 11:00 AM', '402 (011211050-0112420637)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1236, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'I', 'MoIm', '2025-10-29', '09:00 AM - 11:00 AM', '403 (011201048-0112330354)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1237, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'K', 'MIA', '2025-10-29', '09:00 AM - 11:00 AM', '425 (011212131-0112410472)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1238, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'L', 'SiMu', '2025-10-29', '09:00 AM - 11:00 AM', '429 (011193060-0112330260)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1239, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'M', 'MdAIm', '2025-10-29', '09:00 AM - 11:00 AM', '432 (011171008-0112420663)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1240, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'N', 'SIsm', '2025-10-29', '09:00 AM - 11:00 AM', '601 (011213088-0112410133)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1241, 1, 1, 'Final', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'O', 'MoIm', '2025-10-29', '09:00 AM - 11:00 AM', '603 (011181190-0112420734)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1242, 1, 1, 'Final', 'MATH 1153', 'Advanced Calculus', 'BA', 'MUn', '2025-10-29', '09:00 AM - 11:00 AM', '601 (0152330046-0152410048)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1243, 1, 1, 'Final', 'EEE 1001', 'Electrical Circuits I', 'B', 'SMB', '2025-10-29', '09:00 AM - 11:00 AM', '630 (0212330022-0212520006)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1244, 1, 1, 'Final', 'EEE 1001', 'Electrical Circuits I', 'C', 'KAM', '2025-10-29', '09:00 AM - 11:00 AM', '701 (021183013-0212510008)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1245, 1, 1, 'Final', 'EEE 311/EEE 3309', 'Digital Signal Processing', 'A', 'RM', '2025-10-29', '09:00 AM - 11:00 AM', '604 (021201069-0212230109)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1246, 1, 1, 'Final', 'EEE 311/EEE 3309', 'Digital Signal Processing', 'B', 'RM', '2025-10-29', '09:00 AM - 11:00 AM', '701 (021181034-0212230083)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1247, 1, 1, 'Final', 'PHY 1201', 'Physics', 'A', 'NBZ', '2025-10-29', '11:30 AM - 01:30 PM', '305 (0312230016-0312510027)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1248, 1, 1, 'Final', 'MATH 2103', 'Laplace Transformation, Probability and Statistics', 'A', 'MIA', '2025-10-29', '11:30 AM - 01:30 PM', '301 (031221004-0312230035)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1249, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'A', 'MdShA', '2025-10-29', '11:30 AM - 01:30 PM', '603 (011183048-0112310459)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1250, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'B', 'ArBh', '2025-10-29', '11:30 AM - 01:30 PM', '605 (011192131-0112330189)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1251, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'C', 'UR', '2025-10-29', '11:30 AM - 01:30 PM', '631 (011161085-0112330304)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1252, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'D', 'MdShA', '2025-10-29', '11:30 AM - 01:30 PM', '701 (011212076-0112330162)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1253, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'E', 'NtJT', '2025-10-29', '11:30 AM - 01:30 PM', '703 (011213200-0112330292)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1254, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'F', 'MdShA', '2025-10-29', '11:30 AM - 01:30 PM', '707 (011183014-0112330089)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1255, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'G', 'NtJT', '2025-10-29', '11:30 AM - 01:30 PM', '711 (0112230007-0112330547)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1256, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'H', 'IAb', '2025-10-29', '11:30 AM - 01:30 PM', '723 (011192099-0112320221)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1257, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'I', 'TaMo', '2025-10-29', '11:30 AM - 01:30 PM', '725 (011213023-0112330165)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1258, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'J', 'NtJT', '2025-10-29', '11:30 AM - 01:30 PM', '729 (011201265-0112330391)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1259, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'K', 'UR', '2025-10-29', '11:30 AM - 01:30 PM', '731 (011191152-0112330646)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1260, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'BB', 'JNM', '2025-10-29', '11:30 AM - 01:30 PM', '302 (011182094-0152410063)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1261, 1, 1, 'Final', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'BA', 'TaMo', '2025-10-29', '11:30 AM - 01:30 PM', '303 (015222002-0152330079)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1262, 1, 1, 'Final', 'DS 1101', 'Fundamentals of Data Science', 'BB', 'FAH', '2025-10-29', '11:30 AM - 01:30 PM', '305 (0152330040-0152510009)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1263, 1, 1, 'Final', 'DS 1101', 'Fundamentals of Data Science', 'BA', 'FAH', '2025-10-29', '11:30 AM - 01:30 PM', '306 (015222006-0152510065)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1264, 1, 1, 'Final', 'DS 1101', 'Fundamentals of Data Science', 'BC', 'FAH', '2025-10-29', '11:30 AM - 01:30 PM', '307 (0152330005-0152510031)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1265, 1, 1, 'Final', 'MAT 2105', 'Linear Algebra and Differential Equations', 'B', 'TN', '2025-10-29', '11:30 AM - 01:30 PM', '302 (021213034-0212430091)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1266, 1, 1, 'Final', 'MAT 2105', 'Linear Algebra and Differential Equations', 'A', 'AM', '2025-10-29', '11:30 AM - 01:30 PM', '303 (021202073-0212430070)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1267, 1, 1, 'Final', 'SOC 4101', 'Introduction to Sociology', 'A', 'JJM', '2025-10-29', '02:00 PM - 04:00 PM', '302 (0312520001-0312520014)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1268, 1, 1, 'Final', 'MATH 2101', 'Matrices and Vector Analysis', 'CA', 'MdAIm', '2025-10-29', '02:00 PM - 04:00 PM', '302 (031211005-0312430063)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1269, 1, 1, 'Final', 'MATH 2101', 'Matrices and Vector Analysis', 'CB', 'MdAIm', '2025-10-29', '02:00 PM - 04:00 PM', '303 (031221009-0312430065)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1270, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'A', 'MTR', '2025-10-29', '02:00 PM - 04:00 PM', '601 (011203012-0112420554)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1271, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'B', 'MdRIm', '2025-10-29', '02:00 PM - 04:00 PM', '603 (011222025-0112430043)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1272, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'C', 'ATR', '2025-10-29', '02:00 PM - 04:00 PM', '605 (0112230317-0112430727)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1273, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'D', 'ATR', '2025-10-29', '02:00 PM - 04:00 PM', '630 (0112230183-0112430729)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1274, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'E', 'TW', '2025-10-29', '02:00 PM - 04:00 PM', '631 (011222286-0112430697)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1275, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'F', 'MdRIm', '2025-10-29', '02:00 PM - 04:00 PM', '632 (011222184-0112420665)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1276, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'G', 'MNTA', '2025-10-29', '02:00 PM - 04:00 PM', '702 (011222106-0112430678)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1277, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'H', 'HAN', '2025-10-29', '02:00 PM - 04:00 PM', '703 (011183006-0112430673)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1278, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'I', 'SSSk', '2025-10-29', '02:00 PM - 04:00 PM', '706 (011202081-0112430137)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1279, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'J', 'SdACh', '2025-10-29', '02:00 PM - 04:00 PM', '708 (011221255-0112430510)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1280, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'K', 'HAN', '2025-10-29', '02:00 PM - 04:00 PM', '711 (011221158-0112420517)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1281, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'L', 'RJR', '2025-10-29', '02:00 PM - 04:00 PM', '723 (0112230649-0112430374)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1282, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'M', 'RaHR', '2025-10-29', '02:00 PM - 04:00 PM', '724 (011202059-0112430662)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24');
INSERT INTO `exam_routines` (`routine_id`, `department_id`, `trimester_id`, `exam_type`, `course_code`, `course_title`, `section`, `teacher_initial`, `exam_date`, `exam_time`, `room`, `original_filename`, `uploaded_by`, `upload_date`) VALUES
(1283, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'O', 'MHAK', '2025-10-29', '02:00 PM - 04:00 PM', '725 (0112320042-0112430478)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1284, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'P', 'RaHR', '2025-10-29', '02:00 PM - 04:00 PM', '728 (011163013-0112430740)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1285, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'Q', 'MdRIm', '2025-10-29', '02:00 PM - 04:00 PM', '729 (011202182-0112420295)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1286, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'S', 'PB', '2025-10-29', '02:00 PM - 04:00 PM', '731 (011221535-0112430730)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1287, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'T', 'MAnAm', '2025-10-29', '02:00 PM - 04:00 PM', '732 (011221108-0112430685)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1288, 1, 1, 'Final', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'U', 'ArMd', '2025-10-29', '02:00 PM - 04:00 PM', '801 (0112230272-0112430579)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1289, 1, 1, 'Final', 'CSE 3421', 'Software Engineering', 'G', 'RNF', '2025-10-29', '02:00 PM - 04:00 PM', '330 (011213062-011222263)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1290, 1, 1, 'Final', 'CSE 3421/CSI 321', 'Software Engineering', 'A', 'ShArn', '2025-10-29', '02:00 PM - 04:00 PM', '401 (011192153-0112230101)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1291, 1, 1, 'Final', 'CSE 3421/CSI 321', 'Software Engineering', 'B', 'ShArn', '2025-10-29', '02:00 PM - 04:00 PM', '403 (011163056-011221494)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1292, 1, 1, 'Final', 'CSE 3421/CSI 321', 'Software Engineering', 'C', 'SSSk', '2025-10-29', '02:00 PM - 04:00 PM', '425 (011193110-011221565)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1293, 1, 1, 'Final', 'CSE 3421/CSI 321', 'Software Engineering', 'D', 'ShArn', '2025-10-29', '02:00 PM - 04:00 PM', '429 (011163072-0112310509)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1294, 1, 1, 'Final', 'CSE 3421/CSI 321', 'Software Engineering', 'E', 'SSSk', '2025-10-29', '02:00 PM - 04:00 PM', '431 (011193029-011222060)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1295, 1, 1, 'Final', 'CSE 3421/CSI 321', 'Software Engineering', 'F', 'ArMd', '2025-10-29', '02:00 PM - 04:00 PM', '601 (011181193-0112230454)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1296, 1, 1, 'Final', 'DS 1115', 'Object Oriented Programming for Data Science', 'BA', 'RaK', '2025-10-29', '02:00 PM - 04:00 PM', '305 (0152310008-0152510031)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1297, 1, 1, 'Final', 'DS 1115', 'Object Oriented Programming for Data Science', 'BB', 'MTR', '2025-10-29', '02:00 PM - 04:00 PM', '306 (0152330059-0152430102)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1298, 1, 1, 'Final', 'DS 1115', 'Object Oriented Programming for Data Science', 'BC', 'IAb', '2025-10-29', '02:00 PM - 04:00 PM', '308 (015222004-0152510067)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1299, 1, 1, 'Final', 'ECO 2101', 'Economics', 'A', 'TrAd', '2025-10-29', '02:00 PM - 04:00 PM', '303 (021193011-0212330055)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1300, 1, 1, 'Final', 'EEE 4217/EEE 477', 'Power System Protection', 'A', 'MFK', '2025-10-29', '02:00 PM - 04:00 PM', '301 (021131142-021221066)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1301, 1, 1, 'Final', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AA', 'NtAl', '2025-10-30', '09:00 AM - 11:00 AM', '302 (0112410095-0112520025)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1302, 1, 1, 'Final', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AB', 'SaB', '2025-10-30', '09:00 AM - 11:00 AM', '304 (0112420079-0112520166)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1303, 1, 1, 'Final', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AC', 'MD', '2025-10-30', '09:00 AM - 11:00 AM', '306 (0112320260-0112520073)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1304, 1, 1, 'Final', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AD', 'TMT', '2025-10-30', '09:00 AM - 11:00 AM', '308 (0112331023-0112520095)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1305, 1, 1, 'Final', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AE', 'MfHn', '2025-10-30', '09:00 AM - 11:00 AM', '322 (0112330540-0112520117)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1306, 1, 1, 'Final', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AF', 'SaB', '2025-10-30', '09:00 AM - 11:00 AM', '324 (0112410236-0112520141)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1307, 1, 1, 'Final', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AG', 'AJMSh', '2025-10-30', '09:00 AM - 11:00 AM', '328 (0112230449-0112520250)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1308, 1, 1, 'Final', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AH', 'NNS', '2025-10-30', '09:00 AM - 11:00 AM', '330 (011221488-0112520183)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1309, 1, 1, 'Final', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AI', 'AJMSh', '2025-10-30', '09:00 AM - 11:00 AM', '402 (0112410151-0152520043)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1310, 1, 1, 'Final', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AJ', 'NaHu', '2025-10-30', '09:00 AM - 11:00 AM', '403 (0112310344-0112520238)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1311, 1, 1, 'Final', 'ECO 213/ECO 4101', 'Economics', 'A', 'TaA', '2025-10-30', '09:00 AM - 11:00 AM', '302 (011181177-0112310249)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1312, 1, 1, 'Final', 'ECO 213/ECO 4101', 'Economics', 'B', 'TaA', '2025-10-30', '09:00 AM - 11:00 AM', '304 (011193102-0112230247)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1313, 1, 1, 'Final', 'ECO 213/ECO 4101', 'Economics', 'C', 'AAsh', '2025-10-30', '09:00 AM - 11:00 AM', '306 (011172009-0112230642)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1314, 1, 1, 'Final', 'ECO 213/ECO 4101', 'Economics', 'D', 'TJT', '2025-10-30', '09:00 AM - 11:00 AM', '308 (011161085-0112310265)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1315, 1, 1, 'Final', 'ECO 213/ECO 4101', 'Economics', 'E', 'MdAkh', '2025-10-30', '09:00 AM - 11:00 AM', '322 (011193065-0112230663)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1316, 1, 1, 'Final', 'ECO 213/ECO 4101', 'Economics', 'F', 'SMRn', '2025-10-30', '09:00 AM - 11:00 AM', '324 (011202072-0112230720)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1317, 1, 1, 'Final', 'DS 3521', 'Data Visualization', 'BA', 'IRR', '2025-10-30', '09:00 AM - 11:00 AM', '425 (015221001-0152330150)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1318, 1, 1, 'Final', 'BDS 1201', 'History of the Emergence of Bangladesh', 'A', 'NaSa', '2025-10-30', '09:00 AM - 11:00 AM', '428 (0152520029-0212520025)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1319, 1, 1, 'Final', 'EEE 309/EEE 3307', 'Communication Theory', 'A', 'RM', '2025-10-30', '09:00 AM - 11:00 AM', '328 (021183010-021221035)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1320, 1, 1, 'Final', 'MATH 1151', 'Fundamental Calculus', 'Z', 'ShIA', '2025-10-30', '11:30 AM - 01:30 PM', '605 (011202170-0112420690)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1321, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'A', 'TN', '2025-10-30', '11:30 AM - 01:30 PM', '631 (011221542-0112510046)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1322, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'B', 'NPn', '2025-10-30', '11:30 AM - 01:30 PM', '701 (011182094-0112420016)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1323, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'C', 'NPn', '2025-10-30', '11:30 AM - 01:30 PM', '703 (0112230065-0112510042)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1324, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'D', 'NSM', '2025-10-30', '11:30 AM - 01:30 PM', '707 (011221352-0152430003)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1325, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'E', 'TN', '2025-10-30', '11:30 AM - 01:30 PM', '708 (011221488-0112510215)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1326, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'F', 'JAS', '2025-10-30', '11:30 AM - 01:30 PM', '722 (011212025-0112510052)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1327, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'G', 'MMBK', '2025-10-30', '11:30 AM - 01:30 PM', '724 (011222154-0112430196)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1328, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'H', 'SMYA', '2025-10-30', '11:30 AM - 01:30 PM', '728 (011212019-0112420100)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1329, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'I', 'JAS', '2025-10-30', '11:30 AM - 01:30 PM', '730 (011213133-0112420718)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1330, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'J', 'SIsm', '2025-10-30', '11:30 AM - 01:30 PM', '732 (011222025-0112430741)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1331, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'K', 'SMYA', '2025-10-30', '11:30 AM - 01:30 PM', '802 (011213122-0112430110)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1332, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'L', 'SiMu', '2025-10-30', '11:30 AM - 01:30 PM', '804 (011221017-0112420407)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1333, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'M', 'NSM', '2025-10-30', '11:30 AM - 01:30 PM', '806 (011221255-0112510390)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1334, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'N', 'NPn', '2025-10-30', '11:30 AM - 01:30 PM', '901 (0112230373-0112510007)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1335, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'O', 'NSM', '2025-10-30', '11:30 AM - 01:30 PM', '903 (011202283-0112410151)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1336, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'P', 'MMBK', '2025-10-30', '11:30 AM - 01:30 PM', '907 (011222065-0112430074)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1337, 1, 1, 'Final', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'Q', 'NSM', '2025-10-30', '11:30 AM - 01:30 PM', '1028 (011203038-0152510031)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1338, 1, 1, 'Final', 'MAT 2107/MATH 187', 'Complex Variables, Fourier and Laplace Transforms/Fourier & Laplace Transformations & Complex Variable', 'B', 'AM', '2025-10-30', '11:30 AM - 01:30 PM', '302 (021182041-0212330041)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1339, 1, 1, 'Final', 'MAT 2107/MATH 187', 'Complex Variables, Fourier and Laplace Transforms/Fourier & Laplace Transformations & Complex Variable', 'A', 'TN', '2025-10-30', '11:30 AM - 01:30 PM', '304 (021161098-0212330165)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1340, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'A', 'RbAn', '2025-10-30', '02:00 PM - 04:00 PM', '731 (011201400-0112420543)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1341, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'B', 'FaHa', '2025-10-30', '02:00 PM - 04:00 PM', '732 (011211118-0112420078)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1342, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'C', 'AIMM', '2025-10-30', '02:00 PM - 04:00 PM', '802 (011201422-0112420065)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1343, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'D', 'IHn', '2025-10-30', '02:00 PM - 04:00 PM', '804 (011221382-0112330949)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1344, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'E', 'FzAn', '2025-10-30', '02:00 PM - 04:00 PM', '806 (011222015-0112420218)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1345, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'F', 'FaHa', '2025-10-30', '02:00 PM - 04:00 PM', '902 (0112230337-0112410091)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1346, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'G', 'SdACh', '2025-10-30', '02:00 PM - 04:00 PM', '904 (011221255-0112430166)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1347, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'H', 'IHn', '2025-10-30', '02:00 PM - 04:00 PM', '907 (011192056-0112410152)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1348, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'I', 'MMIN', '2025-10-30', '02:00 PM - 04:00 PM', '1028 (011211080-0112420013)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1349, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'J', 'MFRn', '2025-10-30', '02:00 PM - 04:00 PM', '1030 (011211049-0112330971)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1350, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'K', 'AIMM', '2025-10-30', '02:00 PM - 04:00 PM', '428 (011203048-0112410153)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1351, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'L', 'FaHa', '2025-10-30', '02:00 PM - 04:00 PM', '302 (011221160-0112410380)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1352, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'M', 'SdACh', '2025-10-30', '02:00 PM - 04:00 PM', '304 (011182080-0112510336)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1353, 1, 1, 'Final', 'CSE 113/EEE 2113', 'Electrical Circuits', 'N', 'MFRn', '2025-10-30', '02:00 PM - 04:00 PM', '305 (011193123-0112331107)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1354, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'A', 'TY', '2025-10-30', '02:00 PM - 04:00 PM', '307 (011191246-0112330048)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1355, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'B', 'TY', '2025-10-30', '02:00 PM - 04:00 PM', '309 (011221173-0112330156)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1356, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'C', 'AbHn', '2025-10-30', '02:00 PM - 04:00 PM', '323 (011162003-0112320272)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1357, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'D', 'TY', '2025-10-30', '02:00 PM - 04:00 PM', '325 (011192103-0112330405)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1358, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'E', 'AbHn', '2025-10-30', '02:00 PM - 04:00 PM', '329 (011193142-0112410411)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1359, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'F', 'AdAn', '2025-10-30', '02:00 PM - 04:00 PM', '330 (011222319-0112330231)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1360, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'G', 'NoAA', '2025-10-30', '02:00 PM - 04:00 PM', '402 (011161085-0112330154)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1361, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'H', 'NoAA', '2025-10-30', '02:00 PM - 04:00 PM', '404 (011192127-0112330389)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1362, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'I', 'AbHn', '2025-10-30', '02:00 PM - 04:00 PM', '428 (011221570-0112330485)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1363, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'J', 'AdAn', '2025-10-30', '02:00 PM - 04:00 PM', '431 (011221518-0112330212)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1364, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'K', 'NoAA', '2025-10-30', '02:00 PM - 04:00 PM', '601 (011203038-0112330082)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1365, 1, 1, 'Final', 'CSE 123/EEE 2123', 'Electronics', 'L', 'AbHn', '2025-10-30', '02:00 PM - 04:00 PM', '603 (011211053-0112331071)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1366, 1, 1, 'Final', 'CSE 315/CSE 3715', 'Data Communication', 'A', 'AIMM', '2025-10-30', '02:00 PM - 04:00 PM', '306 (011181190-011213082)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1367, 1, 1, 'Final', 'CSE 4181/CSE 481', 'Mobile Application Development', 'A', 'KAN', '2025-10-30', '02:00 PM - 04:00 PM', '308 (011183016-011222129)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1368, 1, 1, 'Final', 'CSE 4531', 'Computer Security', 'A', 'MShH', '2025-10-30', '02:00 PM - 04:00 PM', '328 (011183078-011213075)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1369, 1, 1, 'Final', 'CSE 4531', 'Computer Security', 'B', 'MMAS', '2025-10-30', '02:00 PM - 04:00 PM', '330 (011201333-011221096)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1370, 1, 1, 'Final', 'CSE 4531', 'Computer Security', 'C', 'MMAS', '2025-10-30', '02:00 PM - 04:00 PM', '402 (011183048-011213146)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1371, 1, 1, 'Final', 'CSE 4531', 'Computer Security', 'D', 'MNTA', '2025-10-30', '02:00 PM - 04:00 PM', '404 (011191017-011221322)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1372, 1, 1, 'Final', 'EEE 255/EEE 3303', 'Probability and Random Signal Analysis/Probability, Statistics and Random Variables', 'A', 'NAD', '2025-10-30', '02:00 PM - 04:00 PM', '323 (021193011-0212310005)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1373, 1, 1, 'Final', 'PHY 1101', 'Physics I', 'A', 'MASn', '2025-10-30', '02:00 PM - 04:00 PM', '304 (021211012-0212430083)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1374, 1, 1, 'Final', 'CE 4221', 'Dynamics of Structures', 'A', 'TRnA', '2025-11-01', '09:00 AM - 11:00 AM', '303 (031213008-031221059)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1375, 1, 1, 'Final', 'CE 4141', 'Foundation Engineering', 'A', 'SAPa', '2025-11-01', '09:00 AM - 11:00 AM', '305 (031221040-0312230037)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1376, 1, 1, 'Final', 'CSE 4891/CSE 491', 'Data Mining', 'A', 'Ojn', '2025-11-01', '09:00 AM - 11:00 AM', '302 (011192011-011213109)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1377, 1, 1, 'Final', 'CSE 4891/CSE 491', 'Data Mining', 'B', 'Ojn', '2025-11-01', '09:00 AM - 11:00 AM', '304 (011201135-011221129)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1378, 1, 1, 'Final', 'PSY 101/PSY 2101', 'Psychology', 'A', 'NF', '2025-11-01', '09:00 AM - 11:00 AM', '306 (011201020-0152520063)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1379, 1, 1, 'Final', 'EEE 211/EEE 2301', 'Signals and Linear System/Signals and Linear Systems', 'A', 'NAD', '2025-11-01', '09:00 AM - 11:00 AM', '301 (021181044-0212320009)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1380, 1, 1, 'Final', 'CE 1101', 'Engineering Mechanics', 'A', 'JAJ', '2025-11-01', '11:30 AM - 01:30 PM', '306 (031221014-0312510031)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1381, 1, 1, 'Final', 'CE 2111', 'Mechanics of Solids I', 'CA', 'MSaIm', '2025-11-01', '11:30 AM - 01:30 PM', '302 (031221042-0312430065)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1382, 1, 1, 'Final', 'CE 2111', 'Mechanics of Solids I', 'CB', 'MSaIm', '2025-11-01', '11:30 AM - 01:30 PM', '303 (031221035-0312430068)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1383, 1, 1, 'Final', 'CE 2171', 'Fluid Mechanics', 'A', 'JFN', '2025-11-01', '11:30 AM - 01:30 PM', '303 (031221055-0312410022)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1384, 1, 1, 'Final', 'CE 3131', 'Water Supply Engineering', 'A', 'RAf', '2025-11-01', '11:30 AM - 01:30 PM', '301 (031211007-0312330018)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1385, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'A', 'MiBa', '2025-11-01', '11:30 AM - 01:30 PM', '308 (011221370-0112420675)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1386, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'B', 'IRR', '2025-11-01', '11:30 AM - 01:30 PM', '309 (0112230044-0112420184)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1387, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'C', 'CAG', '2025-11-01', '11:30 AM - 01:30 PM', '322 (011193029-0112330608)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1388, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'D', 'MHO', '2025-11-01', '11:30 AM - 01:30 PM', '324 (011211075-0112331113)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1389, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'E', 'CAG', '2025-11-01', '11:30 AM - 01:30 PM', '328 (011192127-0112330850)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1390, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'F', 'RRK', '2025-11-01', '11:30 AM - 01:30 PM', '330 (011213104-0112410520)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1391, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'G', 'MdTH', '2025-11-01', '11:30 AM - 01:30 PM', '401 (011193102-0112410115)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1392, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'H', 'MHO', '2025-11-01', '11:30 AM - 01:30 PM', '403 (011182045-0112330604)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1393, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'I', 'MdTH', '2025-11-01', '11:30 AM - 01:30 PM', '425 (011221099-0112330790)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1394, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'J', 'MdTH', '2025-11-01', '11:30 AM - 01:30 PM', '429 (011201400-0112330822)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1395, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'K', 'RRK', '2025-11-01', '11:30 AM - 01:30 PM', '432 (011211106-0112410459)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1396, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'L', 'MMAS', '2025-11-01', '11:30 AM - 01:30 PM', '601 (011212121-0112330208)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1397, 1, 1, 'Final', 'CSE 4945', 'UI: Concepts and Design', 'A', 'IAb', '2025-11-01', '11:30 AM - 01:30 PM', '304 (011183065-011212067)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1398, 1, 1, 'Final', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'BA', 'CAG', '2025-11-01', '11:30 AM - 01:30 PM', '301 (0152310002-0152430008)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1399, 1, 1, 'Final', 'ACT 3101', 'Financial and Managerial Accounting', 'A', 'IZC', '2025-11-01', '11:30 AM - 01:30 PM', '304 (021191026-0212310034)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1400, 1, 1, 'Final', 'CE 1201', 'Surveying', 'A', 'JFN', '2025-11-01', '02:00 PM - 04:00 PM', '302 (031221042-0312520014)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1401, 1, 1, 'Final', 'CE 3113', 'Design of Concrete Structures', 'A', 'MSaIm', '2025-11-01', '02:00 PM - 04:00 PM', '301 (031211005-0312310013)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1402, 1, 1, 'Final', 'CSE 4495/CSE 495', 'Software Testing and Quality Assurance/Software Testing, Verification and Quality Assurance', 'A', 'MoIsm', '2025-11-01', '02:00 PM - 04:00 PM', '322 (011191060-011222315)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1403, 1, 1, 'Final', 'CSE 4495/CSE 495', 'Software Testing and Quality Assurance/Software Testing, Verification and Quality Assurance', 'B', 'MoIsm', '2025-11-01', '02:00 PM - 04:00 PM', '323 (011183065-011213016)                                                                           ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1404, 1, 1, 'Final', 'CSE 483/CSE 4883', 'Digital Image Processing', 'A', 'MTR', '2025-11-01', '02:00 PM - 04:00 PM', '404 (011193074-0112230839)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1405, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'A', 'SaDa', '2025-11-01', '02:00 PM - 04:00 PM', '806 (011213151-0112420039)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1406, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'B', 'AnAd', '2025-11-01', '02:00 PM - 04:00 PM', '902 (011213093-0112410229)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1407, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'C', 'MAn', '2025-11-01', '02:00 PM - 04:00 PM', '904 (011163060-0112330887)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1408, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'D', 'MAn', '2025-11-01', '02:00 PM - 04:00 PM', '932 (011211001-0112410538)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1409, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'E', 'RBS', '2025-11-01', '02:00 PM - 04:00 PM', '1029 (011211132-0152430056)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1410, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'F', 'GMB', '2025-11-01', '02:00 PM - 04:00 PM', '1030 (011202094-0112410292)                                                                         ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1411, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'G', 'MNHn', '2025-11-01', '02:00 PM - 04:00 PM', '1032 (0112230466-0152410071)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1412, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'H', 'MAn', '2025-11-01', '02:00 PM - 04:00 PM', '301 (011211049-0112420004)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1413, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'I', 'GMB', '2025-11-01', '02:00 PM - 04:00 PM', '303 (011163013-0112330554)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1414, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'J', 'SaDa', '2025-11-01', '02:00 PM - 04:00 PM', '305 (011221519-0112420205)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1415, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'K', 'HaHR', '2025-11-01', '02:00 PM - 04:00 PM', '307 (011212031-0112420108)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1416, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'L', 'HaHR', '2025-11-01', '02:00 PM - 04:00 PM', '309 (011203053-0112420348)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1417, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'M', 'NBZ', '2025-11-01', '02:00 PM - 04:00 PM', '323 (011221255-0112420712)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1418, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'N', 'SaDa', '2025-11-01', '02:00 PM - 04:00 PM', '324 (011212110-0112420267)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1419, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'O', 'RaAN', '2025-11-01', '02:00 PM - 04:00 PM', '328 (011213089-0152410027)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1420, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'P', 'AyFz', '2025-11-01', '02:00 PM - 04:00 PM', '329 (011211097-0112410099)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1421, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'Q', 'AyFz', '2025-11-01', '02:00 PM - 04:00 PM', '401 (011202081-0112331148)                                                                          ', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1422, 1, 1, 'Final', 'PHY 105/PHY 2105', 'Physics', 'R', 'RBS', '2025-11-01', '02:00 PM - 04:00 PM', '403 (011211002-0152520043)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1423, 1, 1, 'Final', 'EEE 4331', 'Biomedical Engineering', 'A', 'MdHn', '2025-11-01', '02:00 PM - 04:00 PM', '303 (021191050-021222030)', 'final-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:09:24'),
(1424, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I', 'CA', 'MFRK', '2025-09-07', '09:00 AM - 11:00 AM', '306 (0312330017-0312520014)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1425, 1, 1, 'Midterm', 'CE 2201', 'Engineering Geology and Geomorphology', 'A', 'ShMS', '2025-09-07', '09:00 AM - 11:00 AM', '301 (031203010-0312430020)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1426, 1, 1, 'Midterm', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'A', 'SabAd', '2025-09-07', '09:00 AM - 11:00 AM', '406 (011181060-011222221)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1427, 1, 1, 'Midterm', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'B', 'RbAn', '2025-09-07', '09:00 AM - 11:00 AM', '428 (011202283-0112230104)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1428, 1, 1, 'Midterm', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'C', 'MSSh', '2025-09-07', '09:00 AM - 11:00 AM', '431 (011162003-011221483)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1429, 1, 1, 'Midterm', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'D', 'ARnA', '2025-09-07', '09:00 AM - 11:00 AM', '601 (011192148-011222115)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1430, 1, 1, 'Midterm', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'E', 'MSSh', '2025-09-07', '09:00 AM - 11:00 AM', '603 (011182045-011221567)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1431, 1, 1, 'Midterm', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'F', 'MSSh', '2025-09-07', '09:00 AM - 11:00 AM', '605 (011201043-011222030)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1432, 1, 1, 'Midterm', 'CSE 4509/CSI 309', 'Operating System Concepts/Operating Systems', 'G', 'ARnA', '2025-09-07', '09:00 AM - 11:00 AM', '631 (011201059-011221217)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1433, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AA', 'ChMR', '2025-09-07', '09:00 AM - 11:00 AM', '601 (0112420534-0112520284)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1434, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AB', 'ChMR', '2025-09-07', '09:00 AM - 11:00 AM', '603 (0112320221-0112520048)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1435, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AC', 'AShK', '2025-09-07', '09:00 AM - 11:00 AM', '605 (0112230685-0112520075)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1436, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AD', 'AShK', '2025-09-07', '09:00 AM - 11:00 AM', '631 (011221015-0112520089)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1437, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AE', 'ChMR', '2025-09-07', '09:00 AM - 11:00 AM', '701 (011212126-0112520106)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1438, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AF', 'AShK', '2025-09-07', '09:00 AM - 11:00 AM', '703 (0112330056-0112520132)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1439, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AG', 'AShK', '2025-09-07', '09:00 AM - 11:00 AM', '707 (0112430205-0112520252)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1440, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AH', 'SaHn', '2025-09-07', '09:00 AM - 11:00 AM', '711 (0112430182-0112520184)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1441, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AI', 'ShMSh', '2025-09-07', '09:00 AM - 11:00 AM', '723 (0112330433-0112520217)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1442, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I/Intensive English I', 'AJ', 'MARi', '2025-09-07', '09:00 AM - 11:00 AM', '725 (0112320080-0152520078)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1443, 1, 1, 'Midterm', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'A', 'ShSg', '2025-09-07', '09:00 AM - 11:00 AM', '728 (011191246-0112320173)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1444, 1, 1, 'Midterm', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'B', 'PPDR', '2025-09-07', '09:00 AM - 11:00 AM', '730 (011201252-0112310415)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1445, 1, 1, 'Midterm', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'C', 'NMAI', '2025-09-07', '09:00 AM - 11:00 AM', '732 (011201420-0112310597)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1446, 1, 1, 'Midterm', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'D', 'AbTr', '2025-09-07', '09:00 AM - 11:00 AM', '802 (011181193-0112230887)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1447, 1, 1, 'Midterm', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'E', 'RkAf', '2025-09-07', '09:00 AM - 11:00 AM', '804 (011211026-0152330147)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1448, 1, 1, 'Midterm', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'F', 'NMAI', '2025-09-07', '09:00 AM - 11:00 AM', '805 (011203005-0112320118)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1449, 1, 1, 'Midterm', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'G', 'AbTr', '2025-09-07', '09:00 AM - 11:00 AM', '901 (011202015-0112320052)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1450, 1, 1, 'Midterm', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'H', 'RkAf', '2025-09-07', '09:00 AM - 11:00 AM', '903 (011211034-0112230698)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1451, 1, 1, 'Midterm', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'I', 'PPDR', '2025-09-07', '09:00 AM - 11:00 AM', '905 (011201174-0112330199)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1452, 1, 1, 'Midterm', 'IPE 3401/IPE 401', 'Industrial and Operational Management/Industrial Management', 'J', 'ShSg', '2025-09-07', '09:00 AM - 11:00 AM', '907 (011191053-0112320087)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1453, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I', 'F', 'UH', '2025-09-07', '09:00 AM - 11:00 AM', '302 (0212410041-0212520027)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00');
INSERT INTO `exam_routines` (`routine_id`, `department_id`, `trimester_id`, `exam_type`, `course_code`, `course_title`, `section`, `teacher_initial`, `exam_date`, `exam_time`, `room`, `original_filename`, `uploaded_by`, `upload_date`) VALUES
(1454, 1, 1, 'Midterm', 'ENG 101/ENG 1011/ENG', 'English I', 'E', 'SaHn', '2025-09-07', '09:00 AM - 11:00 AM', '304 (0212410048-0212520048)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1455, 1, 1, 'Midterm', 'ENG 1013/ENG 103/ENG', 'English II', 'CA', 'KhJS', '2025-09-07', '11:30 AM - 01:30 PM', '323 (0152510002-0312510031)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1456, 1, 1, 'Midterm', 'CE 3231', 'Wastewater Engineering', 'A', 'MrRh', '2025-09-07', '11:30 AM - 01:30 PM', '301 (031211007-0312330019)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1457, 1, 1, 'Midterm', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AA', 'MZK', '2025-09-07', '11:30 AM - 01:30 PM', '702 (0112230574-0112510193)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1458, 1, 1, 'Midterm', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AB', 'MARi', '2025-09-07', '11:30 AM - 01:30 PM', '706 (0112231034-0112510200)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1459, 1, 1, 'Midterm', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AC', 'RnIm', '2025-09-07', '11:30 AM - 01:30 PM', '708 (011211045-0112510080)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1460, 1, 1, 'Midterm', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AD', 'ShMSh', '2025-09-07', '11:30 AM - 01:30 PM', '722 (0112231061-0112510306)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1461, 1, 1, 'Midterm', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AF', 'KhJS', '2025-09-07', '11:30 AM - 01:30 PM', '724 (0112230058-0112510155)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1462, 1, 1, 'Midterm', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AG', 'KhJS', '2025-09-07', '11:30 AM - 01:30 PM', '728 (0112410247-0112510249)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1463, 1, 1, 'Midterm', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AH', 'MZK', '2025-09-07', '11:30 AM - 01:30 PM', '730 (011202200-0112510166)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1464, 1, 1, 'Midterm', 'ENG 1013/ENG 103/ENG', 'English II/Intensive English II', 'AI', 'KhJS', '2025-09-07', '11:30 AM - 01:30 PM', '732 (011201410-0112510218)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1465, 1, 1, 'Midterm', 'EEE 301/EEE 3107', 'Electrical Properties of Materials', 'A', 'IBC', '2025-09-07', '11:30 AM - 01:30 PM', '305 (021201068-0212310049)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1466, 1, 1, 'Midterm', 'EEE 301/EEE 3107', 'Electrical Properties of Materials', 'B', 'IBC', '2025-09-07', '11:30 AM - 01:30 PM', '304 (021171016-0212330152)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1467, 1, 1, 'Midterm', 'EEE 305/EEE 3205', 'Power System', 'A', 'SMLK', '2025-09-07', '11:30 AM - 01:30 PM', '308 (021131165-021221085)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1468, 1, 1, 'Midterm', 'ENG 1013/ENG 103/ENG', 'English II', 'F', 'ShMSh', '2025-09-07', '11:30 AM - 01:30 PM', '324 (0212330120-0212510035)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1469, 1, 1, 'Midterm', 'ENG 1013/ENG 1207', 'English II', 'G', 'ChMR', '2025-09-07', '11:30 AM - 01:30 PM', '307 (0212330022-0212510046)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1470, 1, 1, 'Midterm', 'CHEM 1211', 'Chemistry', 'CA', 'SdRd', '2025-09-07', '02:00 PM - 04:00 PM', '302 (031221042-0312510001)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1471, 1, 1, 'Midterm', 'CHEM 1211', 'Chemistry', 'CB', 'SdRd', '2025-09-07', '02:00 PM - 04:00 PM', '901 (031221002-0312430001)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1472, 1, 1, 'Midterm', 'CE 4217', 'Design of Concrete Structures II', 'A', 'JAJ', '2025-09-07', '02:00 PM - 04:00 PM', '301 (031213008-0312230033)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1473, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'A', 'STT', '2025-09-07', '02:00 PM - 04:00 PM', '425 (011181177-0112330222)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1474, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'B', 'SAhSh', '2025-09-07', '02:00 PM - 04:00 PM', '429 (011181042-0112330292)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1475, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'C', 'HAN', '2025-09-07', '02:00 PM - 04:00 PM', '432 (011202272-0112320131)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1476, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'D', 'SAhSh', '2025-09-07', '02:00 PM - 04:00 PM', '602 (011201264-0112330299)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1477, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'E', 'SAhSh', '2025-09-07', '02:00 PM - 04:00 PM', '604 (011202248-0112330340)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1478, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'F', 'STT', '2025-09-07', '02:00 PM - 04:00 PM', '630 (011201316-0112330097)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1479, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'G', 'TaSa', '2025-09-07', '02:00 PM - 04:00 PM', '632 (011153072-0112330030)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1480, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'H', 'TaSa', '2025-09-07', '02:00 PM - 04:00 PM', '702 (011212011-0112320043)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1481, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'I', 'SAhSh', '2025-09-07', '02:00 PM - 04:00 PM', '706 (011172045-0112330085)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1482, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'J', 'STT', '2025-09-07', '02:00 PM - 04:00 PM', '708 (011193122-0112330237)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1483, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'K', 'MoIsm', '2025-09-07', '02:00 PM - 04:00 PM', '722 (011201310-0112410525)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1484, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'L', 'STT', '2025-09-07', '02:00 PM - 04:00 PM', '723 (011201401-0112330151)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1485, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'M', 'MHAK', '2025-09-07', '02:00 PM - 04:00 PM', '725 (011182080-0112330360)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1486, 1, 1, 'Midterm', 'CSE 313/CSE 3313', 'Computer Architecture', 'O', 'MdMrRn', '2025-09-07', '02:00 PM - 04:00 PM', '729 (011162047-0112410508)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1487, 1, 1, 'Midterm', 'CSE 4889/CSE 489', 'Machine Learning', 'A', 'ARnA', '2025-09-07', '02:00 PM - 04:00 PM', '903 (011192073-011221299)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1488, 1, 1, 'Midterm', 'CSE 4889/CSE 489', 'Machine Learning', 'B', 'Ojn', '2025-09-07', '02:00 PM - 04:00 PM', '304 (011201209-011221334)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1489, 1, 1, 'Midterm', 'CSE 4889/CSE 489', 'Machine Learning', 'C', 'Ojn', '2025-09-07', '02:00 PM - 04:00 PM', '402 (011201194-011221236)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1490, 1, 1, 'Midterm', 'CSE 4889/CSE 489', 'Machine Learning', 'D', 'ShAhd', '2025-09-07', '02:00 PM - 04:00 PM', '308 (011183060-011221170)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1491, 1, 1, 'Midterm', 'CSE 4889/CSE 489', 'Machine Learning', 'E', 'SaIs', '2025-09-07', '02:00 PM - 04:00 PM', '322 (011183087-011221376)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1492, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'A', 'MMBK', '2025-09-07', '02:00 PM - 04:00 PM', '701 (011213198-0112430156)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1493, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'B', 'SMYA', '2025-09-07', '02:00 PM - 04:00 PM', '304 (011213212-0112430012)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1494, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'C', 'ShIA', '2025-09-07', '02:00 PM - 04:00 PM', '306 (011212106-0112410366)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1495, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'D', 'NzHn', '2025-09-07', '02:00 PM - 04:00 PM', '308 (011193157-0112430072)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1496, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'E', 'SamAr', '2025-09-07', '02:00 PM - 04:00 PM', '322 (011202157-0112330438)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1497, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'F', 'NPn', '2025-09-07', '02:00 PM - 04:00 PM', '324 (011221302-0112420540)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1498, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'G', 'AKMRn', '2025-09-07', '02:00 PM - 04:00 PM', '328 (011203048-0112430065)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1499, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'H', 'AKMRn', '2025-09-07', '02:00 PM - 04:00 PM', '330 (011221484-0112430710)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1500, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'I', 'MdAsn', '2025-09-07', '02:00 PM - 04:00 PM', '401 (011221136-0112420161)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1501, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'J', 'SamAr', '2025-09-07', '02:00 PM - 04:00 PM', '403 (011213172-0112420703)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1502, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'K', 'SIsm', '2025-09-07', '02:00 PM - 04:00 PM', '405 (011193147-0112430112)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1503, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'L', 'NzHn', '2025-09-07', '02:00 PM - 04:00 PM', '425 (011221382-0112430066)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1504, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'M', 'SIsm', '2025-09-07', '02:00 PM - 04:00 PM', '806 (011201079-0112430740)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1505, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'N', 'MdAsn', '2025-09-07', '02:00 PM - 04:00 PM', '431 (0112230008-0112430120)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1506, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'O', 'SamAr', '2025-09-07', '02:00 PM - 04:00 PM', '601 (011191001-0112430121)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1507, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'P', 'NSM', '2025-09-07', '02:00 PM - 04:00 PM', '603 (011212159-0112420185)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1508, 1, 1, 'Midterm', 'MATH 183/MATH 2183', 'Calculus and Linear Algebra/Linear Algebra, Ordinary & Partial Differential Equations', 'Q', 'MMBK', '2025-09-07', '02:00 PM - 04:00 PM', '605 (011201122-0112430030)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1509, 1, 1, 'Midterm', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'A', 'NtAl', '2025-09-07', '02:00 PM - 04:00 PM', '707 (011213054-0112330779)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1510, 1, 1, 'Midterm', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'B', 'MMiIm', '2025-09-07', '02:00 PM - 04:00 PM', '725 (011202283-0112331033)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1511, 1, 1, 'Midterm', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'C', 'NaSa', '2025-09-07', '02:00 PM - 04:00 PM', '803 (011162109-0112330535)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1512, 1, 1, 'Midterm', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'D', 'TMT', '2025-09-07', '02:00 PM - 04:00 PM', '801 (011202015-0112330763)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1513, 1, 1, 'Midterm', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'E', 'NaSa', '2025-09-07', '02:00 PM - 04:00 PM', '730 (011222081-0112330897)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1514, 1, 1, 'Midterm', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'F', 'MMiIm', '2025-09-07', '02:00 PM - 04:00 PM', '324 (011211030-0112330746)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1515, 1, 1, 'Midterm', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'G', 'NtAl', '2025-09-07', '02:00 PM - 04:00 PM', '328 (011193074-0112410282)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1516, 1, 1, 'Midterm', 'SOC 101/SOC 2101/SOC', 'Society, Environment and Computing Ethics/Society, Environment and Engineering Ethics/Society, Technology and Engineering Ethics', 'H', 'SKS', '2025-09-07', '02:00 PM - 04:00 PM', '330 (011203006-0112330283)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1517, 1, 1, 'Midterm', 'MATH 2107', 'Linear Algebra', 'BA', 'AkAd', '2025-09-07', '02:00 PM - 04:00 PM', '302 (0152330012-0152430034)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1518, 1, 1, 'Midterm', 'MATH 2107', 'Linear Algebra', 'BB', 'AkAd', '2025-09-07', '02:00 PM - 04:00 PM', '805 (0152230002-0152430090)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1519, 1, 1, 'Midterm', 'EEE 2101', 'Electronics I', 'A', 'BKM', '2025-09-07', '02:00 PM - 04:00 PM', '306 (021191026-0212430089)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1520, 1, 1, 'Midterm', 'EEE 2101', 'Electronics I', 'B', 'BKM', '2025-09-07', '02:00 PM - 04:00 PM', '404 (021201089-0212430003)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1521, 1, 1, 'Midterm', 'EEE 401/EEE 4109', 'Control System', 'A', 'BKM', '2025-09-07', '02:00 PM - 04:00 PM', '703 (021131142-021221026)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1522, 1, 1, 'Midterm', 'EEE 401/EEE 4109', 'Control System', 'B', 'MKMR', '2025-09-07', '02:00 PM - 04:00 PM', '723 (021162038-021213004)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1523, 1, 1, 'Midterm', 'MAT 2109/MATH 201', 'Coordinate Geometry and Vector Analysis', 'A', 'TN', '2025-09-07', '02:00 PM - 04:00 PM', '631 (021181044-0212330007)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1524, 1, 1, 'Midterm', 'CSE 3521/CSI 221', 'Database Management Systems', 'A', 'DID', '2025-09-08', '09:00 AM - 11:00 AM', '302 (011153072-0112310063)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1525, 1, 1, 'Midterm', 'CSE 3521/CSI 221', 'Database Management Systems', 'B', 'MahHsn', '2025-09-08', '09:00 AM - 11:00 AM', '304 (011203045-0112310463)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1526, 1, 1, 'Midterm', 'CSE 3521/CSI 221', 'Database Management Systems', 'C', 'TBD', '2025-09-08', '09:00 AM - 11:00 AM', '306 (011202103-0112310366)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1527, 1, 1, 'Midterm', 'CSE 3521/CSI 221', 'Database Management Systems', 'D', 'SaIs', '2025-09-08', '09:00 AM - 11:00 AM', '308 (011162109-0112310156)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1528, 1, 1, 'Midterm', 'CSE 3521/CSI 221', 'Database Management Systems', 'E', 'TBS', '2025-09-08', '09:00 AM - 11:00 AM', '322 (011202035-0112231051)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1529, 1, 1, 'Midterm', 'CSE 3521/CSI 221', 'Database Management Systems', 'F', 'SaIs', '2025-09-08', '09:00 AM - 11:00 AM', '324 (011183060-0112230806)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1530, 1, 1, 'Midterm', 'CSE 3521/CSI 221', 'Database Management Systems', 'G', 'SaIs', '2025-09-08', '09:00 AM - 11:00 AM', '328 (011192099-0112310375)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1531, 1, 1, 'Midterm', 'CSE 3521/CSI 221', 'Database Management Systems', 'H', 'SMSR', '2025-09-08', '09:00 AM - 11:00 AM', '330 (011201209-0112310059)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1532, 1, 1, 'Midterm', 'CSE 3521/CSI 221', 'Database Management Systems', 'I', 'FTK', '2025-09-08', '09:00 AM - 11:00 AM', '402 (011182070-0112310233)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1533, 1, 1, 'Midterm', 'CSE 4611/CSI 411', 'Compiler/Compiler Design', 'A', 'NSS', '2025-09-08', '09:00 AM - 11:00 AM', '425 (011172007-011221039)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1534, 1, 1, 'Midterm', 'CSE 3521/CSI 221', 'Database Management Systems', 'BA', 'MahHsn', '2025-09-08', '09:00 AM - 11:00 AM', '301 (015222002-0152330142)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1535, 1, 1, 'Midterm', 'CSE 3521/CSI 221', 'Database Management Systems', 'BB', 'MahHsn', '2025-09-08', '09:00 AM - 11:00 AM', '404 (015221001-0152330067)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1536, 1, 1, 'Midterm', 'MAT 1103/MATH 151', 'Calculus II', 'A', 'ShIA', '2025-09-08', '09:00 AM - 11:00 AM', '404 (021211020-0212410043)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1537, 1, 1, 'Midterm', 'MAT 1103', 'Calculus II', 'B', 'AM', '2025-09-08', '09:00 AM - 11:00 AM', '306 (0212320031-0212420073)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1538, 1, 1, 'Midterm', 'SOC 3101', 'Society, Environment and Engineering Ethics', 'A', 'JJM', '2025-09-08', '09:00 AM - 11:00 AM', '304 (021193023-0212230024)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1539, 1, 1, 'Midterm', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'A', 'ShMd', '2025-09-08', '11:30 AM - 01:30 PM', '322 (011191060-0112310423)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1540, 1, 1, 'Midterm', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'B', 'KMRH', '2025-09-08', '11:30 AM - 01:30 PM', '323 (011203065-0112230660)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1541, 1, 1, 'Midterm', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'C', 'MSTR', '2025-09-08', '11:30 AM - 01:30 PM', '325 (011181116-0112230247)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1542, 1, 1, 'Midterm', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'D', 'KMRH', '2025-09-08', '11:30 AM - 01:30 PM', '329 (011193060-0112230369)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1543, 1, 1, 'Midterm', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'E', 'MSTR', '2025-09-08', '11:30 AM - 01:30 PM', '401 (011183016-011222177)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1544, 1, 1, 'Midterm', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'F', 'RNF', '2025-09-08', '11:30 AM - 01:30 PM', '403 (011181303-0112310317)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1545, 1, 1, 'Midterm', 'CSE 425/CSE 4325', 'Microprocessor, Microcontroller and Interfacing/Microprocessors and Microcontrollers', 'G', 'KMRH', '2025-09-08', '11:30 AM - 01:30 PM', '404 (011221076-0112230339)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1546, 1, 1, 'Midterm', 'EEE 207/EEE 2103', 'Electronics II', 'A', 'SdM', '2025-09-08', '11:30 AM - 01:30 PM', '307 (021193025-0212410002)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1547, 1, 1, 'Midterm', 'EEE 207/EEE 2103', 'Electronics II', 'B', 'SwMr', '2025-09-08', '11:30 AM - 01:30 PM', '309 (021131142-0212330075)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1548, 1, 1, 'Midterm', 'IPE 401/IPE 4101', 'Industrial Production Engineering', 'A', 'PPDR', '2025-09-08', '11:30 AM - 01:30 PM', '301 (021193032-021221042)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1549, 1, 1, 'Midterm', 'ACT 111/ACT 2111', 'Financial and Managerial Accounting', 'A', 'ItJn', '2025-09-08', '02:00 PM - 04:00 PM', '308 (011191277-0112231034)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1550, 1, 1, 'Midterm', 'ACT 111/ACT 2111', 'Financial and Managerial Accounting', 'B', 'IJ', '2025-09-08', '02:00 PM - 04:00 PM', '304 (011201048-0112310417)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1551, 1, 1, 'Midterm', 'ACT 111/ACT 2111', 'Financial and Managerial Accounting', 'C', 'IJ', '2025-09-08', '02:00 PM - 04:00 PM', '306 (011172045-0112330090)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1552, 1, 1, 'Midterm', 'CSE 4893', 'Introduction to Bioinformatics', 'A', 'RtAm', '2025-09-08', '02:00 PM - 04:00 PM', '303 (011183087-0112230227)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1553, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'A', 'JAS', '2025-09-08', '02:00 PM - 04:00 PM', '601 (011163060-0112330011)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1554, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'B', 'MUn', '2025-09-08', '02:00 PM - 04:00 PM', '603 (011162003-0112410082)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1555, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'C', 'MUn', '2025-09-08', '02:00 PM - 04:00 PM', '605 (011201059-0112331034)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1556, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'D', 'MUn', '2025-09-08', '02:00 PM - 04:00 PM', '631 (011191059-0112331140)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1557, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'E', 'AkAd', '2025-09-08', '02:00 PM - 04:00 PM', '701 (011171257-0112320297)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1558, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'F', 'MoIm', '2025-09-08', '02:00 PM - 04:00 PM', '703 (011192034-0112330248)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1559, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'G', 'MUn', '2025-09-08', '02:00 PM - 04:00 PM', '707 (011221093-0112331009)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1560, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'H', 'MoIm', '2025-09-08', '02:00 PM - 04:00 PM', '711 (011203054-0112330355)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1561, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'I', 'AKMRn', '2025-09-08', '02:00 PM - 04:00 PM', '723 (011193123-0112330214)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1562, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'J', 'AKMRn', '2025-09-08', '02:00 PM - 04:00 PM', '725 (011192131-0112310367)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1563, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'K', 'MoIm', '2025-09-08', '02:00 PM - 04:00 PM', '729 (011202081-0112331049)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1564, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'L', 'AkAd', '2025-09-08', '02:00 PM - 04:00 PM', '731 (011193065-0112410480)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1565, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'M', 'JAS', '2025-09-08', '02:00 PM - 04:00 PM', '801 (011193102-0112320128)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1566, 1, 1, 'Midterm', 'MATH 2205/STAT 205', 'Probability and Statistics', 'N', 'SamAr', '2025-09-08', '02:00 PM - 04:00 PM', '803 (011202157-0152330078)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1567, 1, 1, 'Midterm', 'EEE 203/EEE 2201', 'Energy Conversion I', 'A', 'SAC', '2025-09-08', '02:00 PM - 04:00 PM', '302 (021191010-0212320015)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1568, 1, 1, 'Midterm', 'EEE 203/EEE 2201', 'Energy Conversion I', 'B', 'MFK', '2025-09-08', '02:00 PM - 04:00 PM', '304 (021171016-0212330015)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1569, 1, 1, 'Midterm', 'EEE 4121/EEE 441', 'VLSI Design', 'A', 'MdHn', '2025-09-08', '02:00 PM - 04:00 PM', '301 (021181027-021221026)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1570, 1, 1, 'Midterm', 'MATH 1101', 'Differential and Integral Calculus', 'A', 'SIsm', '2025-09-09', '09:00 AM - 11:00 AM', '401 (031221042-0312520014)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1571, 1, 1, 'Midterm', 'CE 4151', 'Highway Design and Railway Engineering', 'A', 'NT', '2025-09-09', '09:00 AM - 11:00 AM', '301 (031211007-0312310001)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1572, 1, 1, 'Midterm', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'A', 'FzAn', '2025-09-09', '09:00 AM - 11:00 AM', '302 (011173058-0112320059)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:00'),
(1573, 1, 1, 'Midterm', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'B', 'AHMOH', '2025-09-09', '09:00 AM - 11:00 AM', '329 (011181142-0112230742)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1574, 1, 1, 'Midterm', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'C', 'AHMOH', '2025-09-09', '09:00 AM - 11:00 AM', '305 (011192088-0112231023)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1575, 1, 1, 'Midterm', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'D', 'SMSR', '2025-09-09', '09:00 AM - 11:00 AM', '307 (011163060-011222182)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1576, 1, 1, 'Midterm', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'E', 'SMSR', '2025-09-09', '09:00 AM - 11:00 AM', '309 (011183078-0112230331)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1577, 1, 1, 'Midterm', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'F', 'ShAhd', '2025-09-09', '09:00 AM - 11:00 AM', '323 (011201356-0112330124)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1578, 1, 1, 'Midterm', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'G', 'ShAhd', '2025-09-09', '09:00 AM - 11:00 AM', '324 (011201441-0112330457)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1579, 1, 1, 'Midterm', 'CSE 3811/CSI 341', 'Artificial Intelligence', 'H', 'AHMOH', '2025-09-09', '09:00 AM - 11:00 AM', '325 (011191246-0112230666)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1580, 1, 1, 'Midterm', 'CSE 4451/CSE 451', 'Human Computer Interaction', 'A', 'NoNn', '2025-09-09', '09:00 AM - 11:00 AM', '402 (011191078-011221044)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1581, 1, 1, 'Midterm', 'CSE 4451/CSE 451', 'Human Computer Interaction', 'B', 'NoNn', '2025-09-09', '09:00 AM - 11:00 AM', '404 (011183065-011212069)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1582, 1, 1, 'Midterm', 'EEE 205/EEE 2203', 'Energy Conversion II', 'A', 'HB', '2025-09-09', '09:00 AM - 11:00 AM', '329 (021182041-0212230064)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1583, 1, 1, 'Midterm', 'EEE 205/EEE 2203', 'Energy Conversion II', 'B', 'SAC', '2025-09-09', '09:00 AM - 11:00 AM', '305 (021131142-021221088)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1584, 1, 1, 'Midterm', 'MAT 1101/MATH 151', 'Calculus I', 'A', 'AM', '2025-09-09', '09:00 AM - 11:00 AM', '302 (021181106-0212520013)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1585, 1, 1, 'Midterm', 'MAT 1101', 'Calculus I', 'B', 'MMBK', '2025-09-09', '09:00 AM - 11:00 AM', '309 (021222058-0212510039)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1586, 1, 1, 'Midterm', 'CE 2101', 'Engineering Materials', 'CA', 'NT', '2025-09-09', '11:30 AM - 01:30 PM', '302 (031211005-0312430065)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1587, 1, 1, 'Midterm', 'CE 2101', 'Engineering Materials', 'CB', 'NT', '2025-09-09', '11:30 AM - 01:30 PM', '303 (0312230003-0312430068)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1588, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'A', 'FT', '2025-09-09', '11:30 AM - 01:30 PM', '308 (011202100-0112520002)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1589, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'B', 'ArBh', '2025-09-09', '11:30 AM - 01:30 PM', '322 (0112230109-0112430458)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1590, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'C', 'DID', '2025-09-09', '11:30 AM - 01:30 PM', '324 (011222241-0112520060)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1591, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'D', 'MdMrRn', '2025-09-09', '11:30 AM - 01:30 PM', '328 (011221146-0112520093)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1592, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'E', 'RbAn', '2025-09-09', '11:30 AM - 01:30 PM', '330 (011213052-0112520112)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1593, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'F', 'CAG', '2025-09-09', '11:30 AM - 01:30 PM', '402 (0112230968-0112520115)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1594, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'G', 'FT', '2025-09-09', '11:30 AM - 01:30 PM', '404 (011201347-0112510044)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1595, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'H', 'RRK', '2025-09-09', '11:30 AM - 01:30 PM', '406 (011193072-0112430027)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1596, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'I', 'HHCh', '2025-09-09', '11:30 AM - 01:30 PM', '428 (0112230308-0112520163)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1597, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'J', 'SabAd', '2025-09-09', '11:30 AM - 01:30 PM', '431 (011221478-0112520180)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1598, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'K', 'RaHR', '2025-09-09', '11:30 AM - 01:30 PM', '601 (0112330147-0112520263)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1599, 1, 1, 'Midterm', 'CSE 469/PMG 4101', 'Project Management', 'A', 'MdMH', '2025-09-09', '11:30 AM - 01:30 PM', '405 (011192048-0112230115)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1600, 1, 1, 'Midterm', 'CSE 469/PMG 4101', 'Project Management', 'B', 'MdMH', '2025-09-09', '11:30 AM - 01:30 PM', '304 (011181018-011221293)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1601, 1, 1, 'Midterm', 'CSE 469/PMG 4101', 'Project Management', 'C', 'RJR', '2025-09-09', '11:30 AM - 01:30 PM', '306 (011191176-011221052)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1602, 1, 1, 'Midterm', 'CSE 469/PMG 4101', 'Project Management', 'D', 'SA', '2025-09-09', '11:30 AM - 01:30 PM', '308 (011163072-011221407)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1603, 1, 1, 'Midterm', 'CSE 2213/CSI 219', 'Discrete Mathematics', 'BA', 'KBJ', '2025-09-09', '11:30 AM - 01:30 PM', '605 (0152330036-0152430014)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1604, 1, 1, 'Midterm', 'EEE 3403/EEE 423', 'Microprocessor and Interfacing', 'A', 'SMLK', '2025-09-09', '11:30 AM - 01:30 PM', '603 (021171016-021221024)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1605, 1, 1, 'Midterm', 'PHY 1103', 'Physics II', 'A', 'MASn', '2025-09-09', '11:30 AM - 01:30 PM', '302 (021201071-0212510045)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1606, 1, 1, 'Midterm', 'PHY 1103', 'Physics II', 'B', 'MASn', '2025-09-09', '11:30 AM - 01:30 PM', '303 (0212230111-0212430091)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1607, 1, 1, 'Midterm', 'EEE 1201', 'Basic Electrical Engineering', 'A', 'SwMr', '2025-09-09', '02:00 PM - 04:00 PM', '302 (031213003-0312510031)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1608, 1, 1, 'Midterm', 'CE 3241', 'Soil Mechanics', 'A', 'TRnA', '2025-09-09', '02:00 PM - 04:00 PM', '307 (031203010-0312310018)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1609, 1, 1, 'Midterm', 'BIO 3105', 'Biology for Engineers', 'A', 'HAA', '2025-09-09', '02:00 PM - 04:00 PM', '308 (011202103-0112420547)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1610, 1, 1, 'Midterm', 'BIO 3105', 'Biology for Engineers', 'B', 'HAA', '2025-09-09', '02:00 PM - 04:00 PM', '309 (011191152-0112230828)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1611, 1, 1, 'Midterm', 'BIO 3105', 'Biology for Engineers', 'C', 'NaTa', '2025-09-09', '02:00 PM - 04:00 PM', '323 (011191053-0112310248)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1612, 1, 1, 'Midterm', 'BIO 3105', 'Biology for Engineers', 'D', 'BHR', '2025-09-09', '02:00 PM - 04:00 PM', '325 (011191001-0112310037)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1613, 1, 1, 'Midterm', 'BIO 3105', 'Biology for Engineers', 'E', 'SMRI', '2025-09-09', '02:00 PM - 04:00 PM', '329 (011212096-0112310447)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1614, 1, 1, 'Midterm', 'BIO 3105', 'Biology for Engineers', 'F', 'HAA', '2025-09-09', '02:00 PM - 04:00 PM', '401 (011221119-0112520264)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1615, 1, 1, 'Midterm', 'BIO 3105', 'Biology for Engineers', 'H', 'BHR', '2025-09-09', '02:00 PM - 04:00 PM', '402 (011201399-0112310087)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01');
INSERT INTO `exam_routines` (`routine_id`, `department_id`, `trimester_id`, `exam_type`, `course_code`, `course_title`, `section`, `teacher_initial`, `exam_date`, `exam_time`, `room`, `original_filename`, `uploaded_by`, `upload_date`) VALUES
(1616, 1, 1, 'Midterm', 'BIO 3105', 'Biology for Engineers', 'G', 'SMRI', '2025-09-09', '02:00 PM - 04:00 PM', '404 (011193102-0112230991)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1617, 1, 1, 'Midterm', 'BIO 3105', 'Biology for Engineers', 'I', 'KhSh', '2025-09-09', '02:00 PM - 04:00 PM', '406 (011211030-0112230992)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1618, 1, 1, 'Midterm', 'BIO 3105', 'Biology for Engineers', 'J', 'RNr', '2025-09-09', '02:00 PM - 04:00 PM', '428 (011192048-0112230999)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1619, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'A', 'AsTn', '2025-09-09', '02:00 PM - 04:00 PM', '724 (011222119-0112420655)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1620, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'B', 'FTK', '2025-09-09', '02:00 PM - 04:00 PM', '728 (011202100-0112430078)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1621, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'C', 'MBAd', '2025-09-09', '02:00 PM - 04:00 PM', '730 (011222019-0112430640)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1622, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'D', 'SmSd', '2025-09-09', '02:00 PM - 04:00 PM', '731 (0112230023-0112430291)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1623, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'E', 'MSTR', '2025-09-09', '02:00 PM - 04:00 PM', '801 (0112230626-0112430283)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1624, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'F', 'RtAm', '2025-09-09', '02:00 PM - 04:00 PM', '803 (0112230361-0112430061)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1625, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'G', 'MBAd', '2025-09-09', '02:00 PM - 04:00 PM', '805 (011202170-0112420482)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1626, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'H', 'AsTn', '2025-09-09', '02:00 PM - 04:00 PM', '901 (011221499-0112430212)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1627, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'I', 'AsTn', '2025-09-09', '02:00 PM - 04:00 PM', '903 (011183006-0112430055)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1628, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'J', 'MSTR', '2025-09-09', '02:00 PM - 04:00 PM', '905 (011212166-0112430115)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1629, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'K', 'TW', '2025-09-09', '02:00 PM - 04:00 PM', '907 (0112230151-0112430148)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1630, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'L', 'MMIN', '2025-09-09', '02:00 PM - 04:00 PM', '1029 (011222192-0112430274)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1631, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'M', 'SmSd', '2025-09-09', '02:00 PM - 04:00 PM', '1031 (011213101-0112430229)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1632, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'N', 'RtAm', '2025-09-09', '02:00 PM - 04:00 PM', '302 (0112320266-0112430398)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1633, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'O', 'ShMd', '2025-09-09', '02:00 PM - 04:00 PM', '304 (011222286-0112430320)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1634, 1, 1, 'Midterm', 'CSE 1325/CSE 225', 'Digital Logic Design', 'P', 'TBS', '2025-09-09', '02:00 PM - 04:00 PM', '306 (0112320120-0112430246)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1635, 1, 1, 'Midterm', 'BIO 3107', 'Biology', 'BA', 'NaTa', '2025-09-09', '02:00 PM - 04:00 PM', '431 (0152230003-0152510067)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1636, 1, 1, 'Midterm', 'EEE 121/EEE 2401', 'Structured Programming Language', 'A', 'SwMr', '2025-09-09', '02:00 PM - 04:00 PM', '309 (021161098-0212330088)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1637, 1, 1, 'Midterm', 'EEE 4331', 'Biomedical Engineering', 'A', 'MdHn', '2025-09-09', '02:00 PM - 04:00 PM', '306 (021191050-021222030)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1638, 1, 1, 'Midterm', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'A', 'AAU', '2025-09-10', '09:00 AM - 11:00 AM', '305 (011201115-0112330506)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1639, 1, 1, 'Midterm', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'B', 'AAU', '2025-09-10', '09:00 AM - 11:00 AM', '307 (011201061-0112330682)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1640, 1, 1, 'Midterm', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'C', 'NSS', '2025-09-10', '09:00 AM - 11:00 AM', '309 (011213064-0112331025)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1641, 1, 1, 'Midterm', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'D', 'MiBa', '2025-09-10', '09:00 AM - 11:00 AM', '323 (011193112-0112410476)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1642, 1, 1, 'Midterm', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'E', 'AAU', '2025-09-10', '09:00 AM - 11:00 AM', '324 (011183014-0112320196)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1643, 1, 1, 'Midterm', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'F', 'KAN', '2025-09-10', '09:00 AM - 11:00 AM', '328 (011201059-0112330255)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1644, 1, 1, 'Midterm', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'G', 'KAN', '2025-09-10', '09:00 AM - 11:00 AM', '330 (011213134-0112330481)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1645, 1, 1, 'Midterm', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'H', 'NSS', '2025-09-10', '09:00 AM - 11:00 AM', '402 (011193123-0112320101)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1646, 1, 1, 'Midterm', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'I', 'MMIN', '2025-09-10', '09:00 AM - 11:00 AM', '404 (011182080-0112410008)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1647, 1, 1, 'Midterm', 'CSE 2233/CSI 233', 'Theory of Computation/Theory of Computing', 'J', 'MiBa', '2025-09-10', '09:00 AM - 11:00 AM', '406 (011211098-0112410525)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1648, 1, 1, 'Midterm', 'CSE 4165/CSE 465', 'Web Programming', 'A', 'NHn', '2025-09-10', '09:00 AM - 11:00 AM', 'Computer Lab 3 (0327) (011172007-011221518)                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1649, 1, 1, 'Midterm', 'CSE 4165/CSE 465', 'Web Programming', 'B', 'NHn', '2025-09-10', '09:00 AM - 11:00 AM', 'Computer Lab 4 (0427) (011201174-011221256)                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1650, 1, 1, 'Midterm', 'CSE 4165/CSE 465', 'Web Programming', 'C', 'NHn', '2025-09-10', '09:00 AM - 11:00 AM', 'Computer Lab 5 (0523) (011163072-0112320074)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1651, 1, 1, 'Midterm', 'CSE 4165/CSE 465', 'Web Programming', 'D', 'NHn', '2025-09-10', '09:00 AM - 11:00 AM', 'Computer Lab 7 (0522) (011183048-011222058)                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1652, 1, 1, 'Midterm', 'EEE 1003', 'Electrical Circuits II', 'A', 'HB', '2025-09-10', '09:00 AM - 11:00 AM', '303 (021213051-0212420052)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1653, 1, 1, 'Midterm', 'EEE 1003', 'Electrical Circuits II', 'B', 'IA', '2025-09-10', '09:00 AM - 11:00 AM', '305 (021212006-0212430066)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1654, 1, 1, 'Midterm', 'EEE 303/EEE 3305', 'Engineering Electromagnetics', 'A', 'BKM', '2025-09-10', '09:00 AM - 11:00 AM', '301 (021171021-021222041)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1655, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'A', 'MNH', '2025-09-10', '11:30 AM - 01:30 PM', '405 (011193072-0112430329)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1656, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'B', 'MNH', '2025-09-10', '11:30 AM - 01:30 PM', '425 (011201115-0112430652)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1657, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'C', 'MNH', '2025-09-10', '11:30 AM - 01:30 PM', '429 (011201052-0112420600)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1658, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'D', 'HS', '2025-09-10', '11:30 AM - 01:30 PM', '432 (011203038-0112430738)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1659, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'E', 'MMAS', '2025-09-10', '11:30 AM - 01:30 PM', '602 (011213054-0112430346)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1660, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'F', 'MBAd', '2025-09-10', '11:30 AM - 01:30 PM', '604 (011221049-0112430300)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1661, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'G', 'MShH', '2025-09-10', '11:30 AM - 01:30 PM', '630 (0112310196-0112430390)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1662, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'H', 'MNH', '2025-09-10', '11:30 AM - 01:30 PM', '632 (011221454-0112430743)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1663, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'I', 'NSS', '2025-09-10', '11:30 AM - 01:30 PM', '702 (011193086-0112420433)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1664, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'J', 'MMAS', '2025-09-10', '11:30 AM - 01:30 PM', '706 (0112230187-0112430109)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1665, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'K', 'RaK', '2025-09-10', '11:30 AM - 01:30 PM', '708 (011213101-0112420669)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1666, 1, 1, 'Midterm', 'CSE 1111/CSI 121', 'Structured Programming Language', 'L', 'SSSk', '2025-09-10', '11:30 AM - 01:30 PM', '722 (0112230158-0112510148)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1667, 1, 1, 'Midterm', 'CSE 1111', 'Structured Programming Language', 'M (New sec', 'MHO', '2025-09-10', '11:30 AM - 01:30 PM', '402 (011202157-0112430438)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1668, 1, 1, 'Midterm', 'CSE 1111', 'Structured Programming Language', 'N (New Ope', 'PB', '2025-09-10', '11:30 AM - 01:30 PM', '308 (011202100-0112430181)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1669, 1, 1, 'Midterm', 'CSE 1111', 'Structured Programming Language', 'O (New Sec', 'SmSd', '2025-09-10', '11:30 AM - 01:30 PM', '322 (011202170-0112430654)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1670, 1, 1, 'Midterm', 'CSE 4435', 'Software Architecture', 'A', 'TBD', '2025-09-10', '11:30 AM - 01:30 PM', '329 (011191271-0112310573)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1671, 1, 1, 'Midterm', 'CSE 4435', 'Software Architecture', 'B', 'TBD', '2025-09-10', '11:30 AM - 01:30 PM', '330 (011191112-011221007)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1672, 1, 1, 'Midterm', 'DS 1501', 'Programming for Data Science', 'BA', 'KBJ', '2025-09-10', '11:30 AM - 01:30 PM', '302 (0152230002-0152510062)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1673, 1, 1, 'Midterm', 'DS 1501', 'Programming for Data Science', 'BB', 'KBJ', '2025-09-10', '11:30 AM - 01:30 PM', '304 (0152310006-0152520021)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1674, 1, 1, 'Midterm', 'DS 1501', 'Programming for Data Science', 'BC', 'TaMo', '2025-09-10', '11:30 AM - 01:30 PM', '306 (0152330029-0152520049)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1675, 1, 1, 'Midterm', 'CHE 2101', 'Chemistry', 'A', 'MASq', '2025-09-10', '11:30 AM - 01:30 PM', '302 (021213027-0212420021)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1676, 1, 1, 'Midterm', 'CHE 2101', 'Chemistry', 'B', 'MASq', '2025-09-10', '11:30 AM - 01:30 PM', '404 (021213053-0212330161)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1677, 1, 1, 'Midterm', 'EEE 307/EEE 3207', 'Power Electronics', 'A', 'IA', '2025-09-10', '11:30 AM - 01:30 PM', '306 (021131142-021202019)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1678, 1, 1, 'Midterm', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'A', 'ME', '2025-09-10', '02:00 PM - 04:00 PM', '302 (011162109-0112230863)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1679, 1, 1, 'Midterm', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'B', 'AKMMI', '2025-09-10', '02:00 PM - 04:00 PM', '304 (011172009-0112230589)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1680, 1, 1, 'Midterm', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'C', 'ME', '2025-09-10', '02:00 PM - 04:00 PM', '306 (011192022-0112230492)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1681, 1, 1, 'Midterm', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'D', 'MNK', '2025-09-10', '02:00 PM - 04:00 PM', '308 (011203057-021212027)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1682, 1, 1, 'Midterm', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'E', 'ME', '2025-09-10', '02:00 PM - 04:00 PM', '309 (011202035-0112310016)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1683, 1, 1, 'Midterm', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'F', 'AKMMI', '2025-09-10', '02:00 PM - 04:00 PM', '323 (011192127-011222270)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1684, 1, 1, 'Midterm', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'G', 'ASKP', '2025-09-10', '02:00 PM - 04:00 PM', '325 (011202166-0112230496)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1685, 1, 1, 'Midterm', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'H', 'MNK', '2025-09-10', '02:00 PM - 04:00 PM', '329 (011192011-0112230379)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1686, 1, 1, 'Midterm', 'CSE 323/CSE 3711/EEE', 'Computer Networks', 'I', 'MdMH', '2025-09-10', '02:00 PM - 04:00 PM', '401 (011162093-0112331064)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1687, 1, 1, 'Midterm', 'DS 3101', 'Advanced Probability and Statistics', 'BA', 'TaMo', '2025-09-10', '02:00 PM - 04:00 PM', '305 (0152230003-0152330074)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1688, 1, 1, 'Midterm', 'EEE 2105/EEE 223', 'Digital Electronics', 'A', 'TTM', '2025-09-10', '02:00 PM - 04:00 PM', '301 (021182041-0212330014)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1689, 1, 1, 'Midterm', 'EEE 313/EEE 4111', 'Solid State Devices', 'A', 'IBC', '2025-09-10', '02:00 PM - 04:00 PM', '307 (021183015-021221027)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1690, 1, 1, 'Midterm', 'CE 2211', 'Mechanics of Solids II', 'A', 'TRnA', '2025-09-11', '09:00 AM - 11:00 AM', '302 (031221009-0312410022)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1691, 1, 1, 'Midterm', 'CE 3211', 'Design of Steel Structures', 'A', 'JAJ', '2025-09-11', '09:00 AM - 11:00 AM', '303 (031203010-0312230037)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1692, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'A', 'MAnAm', '2025-09-11', '09:00 AM - 11:00 AM', '730 (011201353-0112230117)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1693, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'B', 'NZMa', '2025-09-11', '09:00 AM - 11:00 AM', '732 (011172009-0112230816)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1694, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'C', 'SA', '2025-09-11', '09:00 AM - 11:00 AM', '802 (011203010-0112230524)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1695, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'D', 'HHCh', '2025-09-11', '09:00 AM - 11:00 AM', '804 (011201441-0112230472)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1696, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'E', 'DID', '2025-09-11', '09:00 AM - 11:00 AM', '806 (011202177-0112230955)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1697, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'F', 'NZMa', '2025-09-11', '09:00 AM - 11:00 AM', '902 (011213125-0112230424)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1698, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'G', 'SiMa', '2025-09-11', '09:00 AM - 11:00 AM', '904 (011202038-0112230965)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1699, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'H', 'SiMa', '2025-09-11', '09:00 AM - 11:00 AM', '906 (011201091-0112230571)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1700, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'I', 'TaSa', '2025-09-11', '09:00 AM - 11:00 AM', '1028 (011201376-0112230594)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1701, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'J', 'TaSa', '2025-09-11', '09:00 AM - 11:00 AM', '1030 (011203064-0112230542)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1702, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'K', 'MAnAm', '2025-09-11', '09:00 AM - 11:00 AM', '1032 (011202338-0112230224)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1703, 1, 1, 'Midterm', 'CSE 3411/CSI 311', 'System Analysis and Design', 'L', 'SiMa', '2025-09-11', '09:00 AM - 11:00 AM', '303 (011192026-0112230478)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1704, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'A', 'ShIA', '2025-09-11', '09:00 AM - 11:00 AM', '605 (011163072-0112430617)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1705, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'B', 'MIA', '2025-09-11', '09:00 AM - 11:00 AM', '603 (011172007-0112420204)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1706, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'C', 'MIA', '2025-09-11', '09:00 AM - 11:00 AM', '306 (011211053-0112410278)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1707, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'D', 'MIA', '2025-09-11', '09:00 AM - 11:00 AM', '308 (011163056-0112330971)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1708, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'E', 'MdAIm', '2025-09-11', '09:00 AM - 11:00 AM', '322 (011173058-0112330122)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1709, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'F', 'SiMu', '2025-09-11', '09:00 AM - 11:00 AM', '324 (011161085-0112310521)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1710, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'G', 'NzHn', '2025-09-11', '09:00 AM - 11:00 AM', '328 (011191271-0112330589)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1711, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'H', 'NzHn', '2025-09-11', '09:00 AM - 11:00 AM', '330 (011211050-0112420637)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1712, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'I', 'MoIm', '2025-09-11', '09:00 AM - 11:00 AM', '401 (011201048-0112330400)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1713, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'K', 'MIA', '2025-09-11', '09:00 AM - 11:00 AM', '403 (011212131-0112330923)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1714, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'L', 'SiMu', '2025-09-11', '09:00 AM - 11:00 AM', '405 (011193060-0112330707)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1715, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'M', 'MdAIm', '2025-09-11', '09:00 AM - 11:00 AM', '425 (011171008-0112310453)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1716, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'N', 'SIsm', '2025-09-11', '09:00 AM - 11:00 AM', '429 (011211030-0112331001)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1717, 1, 1, 'Midterm', 'MATH 201/MATH 2201', 'Coordinate Geometry and Vector Analysis', 'O', 'MoIm', '2025-09-11', '09:00 AM - 11:00 AM', '432 (011181190-0112420734)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1718, 1, 1, 'Midterm', 'MATH 1153', 'Advanced Calculus', 'BA', 'MUn', '2025-09-11', '09:00 AM - 11:00 AM', '301 (0152330026-0152410048)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1719, 1, 1, 'Midterm', 'EEE 1001', 'Electrical Circuits I', 'B', 'MRK', '2025-09-11', '09:00 AM - 11:00 AM', '601 (0212320017-0212520003)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1720, 1, 1, 'Midterm', 'EEE 1001', 'Electrical Circuits I', 'C', 'MRK', '2025-09-11', '09:00 AM - 11:00 AM', '322 (021183013-0212510008)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1721, 1, 1, 'Midterm', 'EEE 311/EEE 3309', 'Digital Signal Processing', 'A', 'RM', '2025-09-11', '09:00 AM - 11:00 AM', '603 (021171021-0212230092)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1722, 1, 1, 'Midterm', 'EEE 311/EEE 3309', 'Digital Signal Processing', 'B', 'RM', '2025-09-11', '09:00 AM - 11:00 AM', '306 (021181034-0212230083)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1723, 1, 1, 'Midterm', 'PHY 1201', 'Physics', 'A', 'NBZ', '2025-09-11', '11:30 AM - 01:30 PM', '302 (0312230005-0312510031)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1724, 1, 1, 'Midterm', 'MATH 2103', 'Laplace Transformation, Probability and Statistics', 'A', 'MIA', '2025-09-11', '11:30 AM - 01:30 PM', '301 (031221002-0312230035)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1725, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'A', 'MdShA', '2025-09-11', '11:30 AM - 01:30 PM', '732 (011181060-0112310441)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1726, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'B', 'ArBh', '2025-09-11', '11:30 AM - 01:30 PM', '802 (011192131-0112330137)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1727, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'C', 'UR', '2025-09-11', '11:30 AM - 01:30 PM', '804 (011161085-0112330296)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1728, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'D', 'MdShA', '2025-09-11', '11:30 AM - 01:30 PM', '806 (011212076-0112330187)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1729, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'E', 'NtJT', '2025-09-11', '11:30 AM - 01:30 PM', '902 (011201401-0112330240)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1730, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'F', 'MdShA', '2025-09-11', '11:30 AM - 01:30 PM', '904 (011183014-0112310564)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1731, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'G', 'NtJT', '2025-09-11', '11:30 AM - 01:30 PM', '906 (0112230007-0112330479)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1732, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'H', 'IAb', '2025-09-11', '11:30 AM - 01:30 PM', '1028 (011192099-0112320221)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1733, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'I', 'TaMo', '2025-09-11', '11:30 AM - 01:30 PM', '1030 (011213023-0112330149)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1734, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'J', 'NtJT', '2025-09-11', '11:30 AM - 01:30 PM', '1032 (011201054-0112330386)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1735, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'K', 'UR', '2025-09-11', '11:30 AM - 01:30 PM', '307 (011191152-0112330646)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1736, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'BA', 'TaMo', '2025-09-11', '11:30 AM - 01:30 PM', '309 (015222002-0152330090)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1737, 1, 1, 'Midterm', 'CSE 2217/CSI 227', 'Algorithms/Data Structure and Algorithms II', 'BB', 'JNM', '2025-09-11', '11:30 AM - 01:30 PM', '305 (011182094-0152410063)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1738, 1, 1, 'Midterm', 'DS 1101', 'Fundamentals of Data Science', 'BA', 'FAH', '2025-09-11', '11:30 AM - 01:30 PM', '303 (015222006-0152510066)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1739, 1, 1, 'Midterm', 'DS 1101', 'Fundamentals of Data Science', 'BB', 'FAH', '2025-09-11', '11:30 AM - 01:30 PM', '304 (0152230002-0152430004)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1740, 1, 1, 'Midterm', 'DS 1101', 'Fundamentals of Data Science', 'BC', 'FAH', '2025-09-11', '11:30 AM - 01:30 PM', '306 (0152330005-0152510031)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1741, 1, 1, 'Midterm', 'MAT 2105', 'Linear Algebra and Differential Equations', 'A', 'AM', '2025-09-11', '11:30 AM - 01:30 PM', '307 (021202073-0212410038)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1742, 1, 1, 'Midterm', 'MAT 2105', 'Linear Algebra and Differential Equations', 'B', 'TN', '2025-09-11', '11:30 AM - 01:30 PM', '304 (021192051-0212430091)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1743, 1, 1, 'Midterm', 'SOC 4101', 'Introduction to Sociology', 'A', 'JJM', '2025-09-11', '02:00 PM - 04:00 PM', '302 (0312520001-0312520014)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1744, 1, 1, 'Midterm', 'MATH 2101', 'Matrices and Vector Analysis', 'CA', 'MdAIm', '2025-09-11', '02:00 PM - 04:00 PM', '302 (031211005-0312430063)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1745, 1, 1, 'Midterm', 'MATH 2101', 'Matrices and Vector Analysis', 'CB', 'MdAIm', '2025-09-11', '02:00 PM - 04:00 PM', '303 (031221009-0312430065)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1746, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'A', 'MTR', '2025-09-11', '02:00 PM - 04:00 PM', '604 (011203012-0112420046)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1747, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'B', 'MdRIm', '2025-09-11', '02:00 PM - 04:00 PM', '630 (011222025-0112420570)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1748, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'C', 'ATR', '2025-09-11', '02:00 PM - 04:00 PM', '632 (011221468-0112420249)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1749, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'D', 'ATR', '2025-09-11', '02:00 PM - 04:00 PM', '702 (011221509-0112420528)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1750, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'E', 'TW', '2025-09-11', '02:00 PM - 04:00 PM', '706 (011222286-0112420537)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1751, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'F', 'MdRIm', '2025-09-11', '02:00 PM - 04:00 PM', '708 (011222184-0112420546)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1752, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'G', 'MNTA', '2025-09-11', '02:00 PM - 04:00 PM', '722 (011222106-0112430128)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1753, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'H', 'HAN', '2025-09-11', '02:00 PM - 04:00 PM', '724 (011183006-0112420438)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1754, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'I', 'SSSk', '2025-09-11', '02:00 PM - 04:00 PM', '728 (011193112-0112430005)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1755, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'J', 'SdACh', '2025-09-11', '02:00 PM - 04:00 PM', '730 (011212042-0112430716)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1756, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'K', 'HAN', '2025-09-11', '02:00 PM - 04:00 PM', '731 (011221158-0112420517)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1757, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'L', 'RJR', '2025-09-11', '02:00 PM - 04:00 PM', '801 (011221354-0112430374)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1758, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'M', 'RaHR', '2025-09-11', '02:00 PM - 04:00 PM', '802 (011202059-0112430662)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1759, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'O', 'MHAK', '2025-09-11', '02:00 PM - 04:00 PM', '803 (011222051-0112430478)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1760, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'P', 'RaHR', '2025-09-11', '02:00 PM - 04:00 PM', '804 (011163013-0112430134)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1761, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'Q', 'MdRIm', '2025-09-11', '02:00 PM - 04:00 PM', '806 (011202182-0112420121)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1762, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'S', 'PB', '2025-09-11', '02:00 PM - 04:00 PM', '902 (011221535-0112420271)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1763, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'T', 'MAnAm', '2025-09-11', '02:00 PM - 04:00 PM', '904 (011221108-0112420343)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1764, 1, 1, 'Midterm', 'CSE 1115/CSI 211', 'Object Oriented Programming/Object-Oriented Programming', 'U', 'ArMd', '2025-09-11', '02:00 PM - 04:00 PM', '906 (011203038-0112430579)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1765, 1, 1, 'Midterm', 'CSE 3421/CSI 321', 'Software Engineering', 'A', 'ShArn', '2025-09-11', '02:00 PM - 04:00 PM', '305 (011192153-0112230101)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1766, 1, 1, 'Midterm', 'CSE 3421/CSI 321', 'Software Engineering', 'B', 'ShArn', '2025-09-11', '02:00 PM - 04:00 PM', '307 (011163056-011221494)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1767, 1, 1, 'Midterm', 'CSE 3421/CSI 321', 'Software Engineering', 'C', 'SSSk', '2025-09-11', '02:00 PM - 04:00 PM', '309 (011193110-011221565)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1768, 1, 1, 'Midterm', 'CSE 3421/CSI 321', 'Software Engineering', 'D', 'ShArn', '2025-09-11', '02:00 PM - 04:00 PM', '323 (011163072-0112310509)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1769, 1, 1, 'Midterm', 'CSE 3421/CSI 321', 'Software Engineering', 'E', 'SSSk', '2025-09-11', '02:00 PM - 04:00 PM', '324 (011193029-011222060)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1770, 1, 1, 'Midterm', 'CSE 3421/CSI 321', 'Software Engineering', 'F', 'ArMd', '2025-09-11', '02:00 PM - 04:00 PM', '328 (011181193-0112230454)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1771, 1, 1, 'Midterm', 'CSE 3421', 'Software Engineering', 'G (New ope', 'RNF', '2025-09-11', '02:00 PM - 04:00 PM', '301 (011211013-011222263)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1772, 1, 1, 'Midterm', 'DS 1115', 'Object Oriented Programming for Data Science', 'BA', 'RaK', '2025-09-11', '02:00 PM - 04:00 PM', '305 (0152310008-0152510031)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1773, 1, 1, 'Midterm', 'DS 1115', 'Object Oriented Programming for Data Science', 'BB', 'MTR', '2025-09-11', '02:00 PM - 04:00 PM', '306 (0152330019-0152430080)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1774, 1, 1, 'Midterm', 'DS 1115', 'Object Oriented Programming for Data Science', 'BC', 'IAb', '2025-09-11', '02:00 PM - 04:00 PM', '308 (015222004-0152510067)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1775, 1, 1, 'Midterm', 'ECO 2101', 'Economics', 'A', 'TrAd', '2025-09-11', '02:00 PM - 04:00 PM', '324 (021193011-0212330067)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1776, 1, 1, 'Midterm', 'EEE 4217/EEE 477', 'Power System Protection', 'A', 'MFK', '2025-09-11', '02:00 PM - 04:00 PM', '304 (021131142-021221066)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1777, 1, 1, 'Midterm', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AA', 'NtAl', '2025-09-12', '08:30 AM - 10:30 AM', '405 (0112410095-0112520023)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1778, 1, 1, 'Midterm', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AB', 'SaB', '2025-09-12', '08:30 AM - 10:30 AM', '304 (0112330241-0112520166)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1779, 1, 1, 'Midterm', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AC', 'MD', '2025-09-12', '08:30 AM - 10:30 AM', '306 (0112320260-0112520073)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1780, 1, 1, 'Midterm', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AD', 'TMT', '2025-09-12', '08:30 AM - 10:30 AM', '308 (0112331023-0112520096)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1781, 1, 1, 'Midterm', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AE', 'MfHn', '2025-09-12', '08:30 AM - 10:30 AM', '322 (0112330540-0112520117)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01');
INSERT INTO `exam_routines` (`routine_id`, `department_id`, `trimester_id`, `exam_type`, `course_code`, `course_title`, `section`, `teacher_initial`, `exam_date`, `exam_time`, `room`, `original_filename`, `uploaded_by`, `upload_date`) VALUES
(1782, 1, 1, 'Midterm', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AF', 'SaB', '2025-09-12', '08:30 AM - 10:30 AM', '324 (0112410236-0112520142)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1783, 1, 1, 'Midterm', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AG', 'AJMSh', '2025-09-12', '08:30 AM - 10:30 AM', '328 (0112230449-0112520249)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1784, 1, 1, 'Midterm', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AH', 'NNS', '2025-09-12', '08:30 AM - 10:30 AM', '330 (011221488-0112520182)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1785, 1, 1, 'Midterm', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AI', 'AJMSh', '2025-09-12', '08:30 AM - 10:30 AM', '402 (0112410151-0152520043)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1786, 1, 1, 'Midterm', 'BDS 1201', 'History of the Emergence of Bangladesh', 'AJ', 'NaHu', '2025-09-12', '08:30 AM - 10:30 AM', '403 (0112310344-0112520238)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1787, 1, 1, 'Midterm', 'ECO 213/ECO 4101', 'Economics', 'A', 'SCT', '2025-09-12', '08:30 AM - 10:30 AM', '302 (011181177-0112310249)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1788, 1, 1, 'Midterm', 'ECO 213/ECO 4101', 'Economics', 'B', 'TaA', '2025-09-12', '08:30 AM - 10:30 AM', '304 (011193102-0112230300)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1789, 1, 1, 'Midterm', 'ECO 213/ECO 4101', 'Economics', 'C', 'AAsh', '2025-09-12', '08:30 AM - 10:30 AM', '306 (011172009-0112230722)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1790, 1, 1, 'Midterm', 'ECO 213/ECO 4101', 'Economics', 'D', 'TJT', '2025-09-12', '08:30 AM - 10:30 AM', '308 (011161085-0112310113)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1791, 1, 1, 'Midterm', 'ECO 213/ECO 4101', 'Economics', 'E', 'MdAkh', '2025-09-12', '08:30 AM - 10:30 AM', '322 (011193065-0112230512)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1792, 1, 1, 'Midterm', 'ECO 213/ECO 4101', 'Economics', 'F', 'SMRn', '2025-09-12', '08:30 AM - 10:30 AM', '324 (011202072-0112231049)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1793, 1, 1, 'Midterm', 'DS 3521', 'Data Visualization', 'BA', 'IRR', '2025-09-12', '08:30 AM - 10:30 AM', '328 (015221001-0152330075)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1794, 1, 1, 'Midterm', 'BDS 1201', 'History of the Emergence of Bangladesh', 'A', 'TaIm', '2025-09-12', '08:30 AM - 10:30 AM', '431 (0152520029-0212520025)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1795, 1, 1, 'Midterm', 'EEE 309/EEE 3307', 'Communication Theory', 'A', 'RM', '2025-09-12', '08:30 AM - 10:30 AM', '425 (021171021-021221035)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1796, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'A', 'TN', '2025-09-12', '11:00 AM - 01:00 PM', '802 (011221542-0112510053)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1797, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'B', 'NPn', '2025-09-12', '11:00 AM - 01:00 PM', '804 (011182094-0112420281)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1798, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'C', 'NPn', '2025-09-12', '11:00 AM - 01:00 PM', '806 (0112230065-0112510066)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1799, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'D', 'NSM', '2025-09-12', '11:00 AM - 01:00 PM', '902 (011221313-0112510038)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1800, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'E', 'TN', '2025-09-12', '11:00 AM - 01:00 PM', '904 (011221488-0112510150)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1801, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'F', 'JAS', '2025-09-12', '11:00 AM - 01:00 PM', '906 (011212025-0112510049)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1802, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'G', 'MMBK', '2025-09-12', '11:00 AM - 01:00 PM', '1028 (011222154-0112430196)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1803, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'H', 'SMYA', '2025-09-12', '11:00 AM - 01:00 PM', '1030 (011212019-0112420084)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1804, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'I', 'JAS', '2025-09-12', '11:00 AM - 01:00 PM', '404 (011213133-0112420453)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1805, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'J', 'SIsm', '2025-09-12', '11:00 AM - 01:00 PM', '402 (011222025-0112430741)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1806, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'K', 'SMYA', '2025-09-12', '11:00 AM - 01:00 PM', '304 (011213122-0112430305)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1807, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'L', 'SiMu', '2025-09-12', '11:00 AM - 01:00 PM', '306 (011213150-0112420521)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1808, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'M', 'NSM', '2025-09-12', '11:00 AM - 01:00 PM', '308 (011221255-0112420222)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1809, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'N', 'NPn', '2025-09-12', '11:00 AM - 01:00 PM', '322 (0112230373-0112510017)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1810, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'O', 'NSM', '2025-09-12', '11:00 AM - 01:00 PM', '324 (011193112-0112420026)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1811, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'P', 'MMBK', '2025-09-12', '11:00 AM - 01:00 PM', '328 (011221468-0112430074)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1812, 1, 1, 'Midterm', 'MATH 1151/MATH 151', 'Differential and Integral Calculus/Fundamental Calculus', 'Q', 'NSM', '2025-09-12', '11:00 AM - 01:00 PM', '330 (011203038-0112430071)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1813, 1, 1, 'Midterm', 'MATH 1151', 'Fundamental Calculus', 'Z', 'ShIA', '2025-09-12', '11:00 AM - 01:00 PM', '301 (011202170-0112430031)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1814, 1, 1, 'Midterm', 'MAT 2107/MATH 187', 'Complex Variables, Fourier and Laplace Transforms', 'A', 'TN', '2025-09-12', '11:00 AM - 01:00 PM', '303 (021161098-0212330121)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1815, 1, 1, 'Midterm', 'MAT 2107/MATH 187', 'Complex Variables, Fourier and Laplace Transforms', 'B', 'AM', '2025-09-12', '11:00 AM - 01:00 PM', '305 (021182041-0212330044)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1816, 1, 1, 'Midterm', 'CSE 315/CSE 3715', 'Data Communication', 'A', 'AIMM', '2025-09-12', '02:30 PM - 04:30 PM', '304 (011181190-011213076)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1817, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'A', 'RbAn', '2025-09-12', '02:30 PM - 04:30 PM', '302 (011201400-0112330501)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1818, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'B', 'FaHa', '2025-09-12', '02:30 PM - 04:30 PM', '304 (011211118-0112410341)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1819, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'C', 'AIMM', '2025-09-12', '02:30 PM - 04:30 PM', '306 (011201422-0112420065)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1820, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'D', 'IHn', '2025-09-12', '02:30 PM - 04:30 PM', '308 (011221382-0112330874)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1821, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'E', 'FzAn', '2025-09-12', '02:30 PM - 04:30 PM', '322 (011211106-0112420056)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1822, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'F', 'FaHa', '2025-09-12', '02:30 PM - 04:30 PM', '324 (011212117-0112410091)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1823, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'G', 'SdACh', '2025-09-12', '02:30 PM - 04:30 PM', '706 (011193112-0112430166)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1824, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'H', 'IHn', '2025-09-12', '02:30 PM - 04:30 PM', '329 (011192056-0112410152)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1825, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'I', 'MMIN', '2025-09-12', '02:30 PM - 04:30 PM', '401 (011211080-0112420013)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1826, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'J', 'MFRn', '2025-09-12', '02:30 PM - 04:30 PM', '403 (011211049-0112331000)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1827, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'K', 'AIMM', '2025-09-12', '02:30 PM - 04:30 PM', '405 (011203048-0112410153)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1828, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'L', 'FaHa', '2025-09-12', '02:30 PM - 04:30 PM', '425 (011212076-0112331144)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1829, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'M', 'SdACh', '2025-09-12', '02:30 PM - 04:30 PM', '429 (011182080-0112510336)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1830, 1, 1, 'Midterm', 'CSE 113/EEE 2113', 'Electrical Circuits', 'N', 'MFRn', '2025-09-12', '02:30 PM - 04:30 PM', '431 (011193123-0112331109)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1831, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'A', 'TY', '2025-09-12', '02:30 PM - 04:30 PM', '328 (011191246-0112330041)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1832, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'B', 'TY', '2025-09-12', '02:30 PM - 04:30 PM', '330 (011221156-0112330156)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1833, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'C', 'AbHn', '2025-09-12', '02:30 PM - 04:30 PM', '402 (011162003-0112320221)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1834, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'D', 'TY', '2025-09-12', '02:30 PM - 04:30 PM', '404 (011192103-0112330467)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1835, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'E', 'AbHn', '2025-09-12', '02:30 PM - 04:30 PM', '406 (011193142-0112320263)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1836, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'F', 'AdAn', '2025-09-12', '02:30 PM - 04:30 PM', '428 (011222319-0112330256)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1837, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'G', 'NoAA', '2025-09-12', '02:30 PM - 04:30 PM', '431 (011161085-0112330089)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1838, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'H', 'NoAA', '2025-09-12', '02:30 PM - 04:30 PM', '601 (011192127-0112330411)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1839, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'I', 'AbHn', '2025-09-12', '02:30 PM - 04:30 PM', '603 (011211022-0112330487)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1840, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'J', 'AdAn', '2025-09-12', '02:30 PM - 04:30 PM', '605 (011201098-0112330165)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1841, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'K', 'NoAA', '2025-09-12', '02:30 PM - 04:30 PM', '631 (011203038-0112330082)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1842, 1, 1, 'Midterm', 'CSE 123/EEE 2123', 'Electronics', 'L', 'AbHn', '2025-09-12', '02:30 PM - 04:30 PM', '701 (011201353-0112331071)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1843, 1, 1, 'Midterm', 'EEE 255/EEE 3303', 'Probability, Statistics and Random Variables', 'A', 'NAD', '2025-09-12', '02:30 PM - 04:30 PM', '603 (021171016-0212310005)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1844, 1, 1, 'Midterm', 'PHY 1101', 'Physics I', 'A', 'MASn', '2025-09-12', '02:30 PM - 04:30 PM', '702 (021211012-0212430078)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1845, 1, 1, 'Midterm', 'CE 4221', 'Dynamics of Structures', 'A', 'TRnA', '2025-09-13', '09:00 AM - 11:00 AM', '303 (031213008-031221059)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1846, 1, 1, 'Midterm', 'CE 4141', 'Foundation Engineering', 'A', 'SAPa', '2025-09-13', '09:00 AM - 11:00 AM', '306 (031221040-0312230037)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1847, 1, 1, 'Midterm', 'CSE 4891/CSE 491', 'Data Mining', 'A', 'Ojn', '2025-09-13', '09:00 AM - 11:00 AM', '302 (011183060-011213109)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1848, 1, 1, 'Midterm', 'CSE 4891/CSE 491', 'Data Mining', 'B', 'Ojn', '2025-09-13', '09:00 AM - 11:00 AM', '304 (011201135-011221129)                                                                           ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1849, 1, 1, 'Midterm', 'PSY 101/PSY 2101', 'Psychology', 'A', 'NF', '2025-09-13', '09:00 AM - 11:00 AM', '307 (011201020-0152520063)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1850, 1, 1, 'Midterm', 'EEE 211/EEE 2301', 'Signals and Linear Systems', 'A', 'NAD', '2025-09-13', '09:00 AM - 11:00 AM', '301 (021181044-0212320009)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1851, 1, 1, 'Midterm', 'CE 1101', 'Engineering Mechanics', 'A', 'JAJ', '2025-09-13', '11:30 AM - 01:30 PM', '302 (031221014-0312430062)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1852, 1, 1, 'Midterm', 'CE 2111', 'Mechanics of Solids I', 'CA', 'MSaIm', '2025-09-13', '11:30 AM - 01:30 PM', '302 (031221042-0312430065)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1853, 1, 1, 'Midterm', 'CE 2111', 'Mechanics of Solids I', 'CB', 'MSaIm', '2025-09-13', '11:30 AM - 01:30 PM', '303 (031221035-0312430068)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1854, 1, 1, 'Midterm', 'CE 2171', 'Fluid Mechanics', 'A', 'JFN', '2025-09-13', '11:30 AM - 01:30 PM', '304 (031221055-0312410022)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1855, 1, 1, 'Midterm', 'CE 3131', 'Water Supply Engineering', 'A', 'RAf', '2025-09-13', '11:30 AM - 01:30 PM', '301 (031211007-0312330018)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1856, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'A', 'MiBa', '2025-09-13', '11:30 AM - 01:30 PM', '603 (011193147-0112420675)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1857, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'B', 'IRR', '2025-09-13', '11:30 AM - 01:30 PM', '604 (011213115-0112330674)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1858, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'C', 'CAG', '2025-09-13', '11:30 AM - 01:30 PM', '630 (011193029-0112330608)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1859, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'D', 'MHO', '2025-09-13', '11:30 AM - 01:30 PM', '632 (011211075-0112330920)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1860, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'K', 'RRK', '2025-09-13', '11:30 AM - 01:30 PM', '702 (011211106-0112410459)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1861, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'E', 'CAG', '2025-09-13', '11:30 AM - 01:30 PM', '703 (011181177-0112330675)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1862, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'F', 'RRK', '2025-09-13', '11:30 AM - 01:30 PM', '707 (011213104-0112410520)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1863, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'G', 'MdTH', '2025-09-13', '11:30 AM - 01:30 PM', '708 (011193102-0112410115)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1864, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'H', 'MHO', '2025-09-13', '11:30 AM - 01:30 PM', '722 (011182045-0112330589)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1865, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'I', 'MdTH', '2025-09-13', '11:30 AM - 01:30 PM', '724 (011211080-0112330493)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1866, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'J', 'MdTH', '2025-09-13', '11:30 AM - 01:30 PM', '728 (011201400-0112330816)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1867, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'L', 'MMAS', '2025-09-13', '11:30 AM - 01:30 PM', '730 (011212121-0112330214)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1868, 1, 1, 'Midterm', 'CSE 2215/CSI 217', 'Data Structure/Data Structure and Algorithms I', 'BA', 'CAG', '2025-09-13', '11:30 AM - 01:30 PM', '306 (0152310002-0152430008)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1869, 1, 1, 'Midterm', 'ACT 3101', 'Financial and Managerial Accounting', 'A', 'IZC', '2025-09-13', '11:30 AM - 01:30 PM', '304 (021191026-0212230120)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1870, 1, 1, 'Midterm', 'CE 1201', 'Surveying', 'A', 'JFN', '2025-09-13', '02:00 PM - 04:00 PM', '302 (031221042-0312520014)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1871, 1, 1, 'Midterm', 'CE 3113', 'Design of Concrete Structures', 'A', 'MSaIm', '2025-09-13', '02:00 PM - 04:00 PM', '301 (031211005-0312310013)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1872, 1, 1, 'Midterm', 'CSE 483/CSE 4883', 'Digital Image Processing', 'A', 'MTR', '2025-09-13', '02:00 PM - 04:00 PM', '301 (011193074-0112230839)', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1873, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'A', 'SaDa', '2025-09-13', '02:00 PM - 04:00 PM', '631 (011213151-0112420009)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1874, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'B', 'AnAd', '2025-09-13', '02:00 PM - 04:00 PM', '701 (011213093-0112410221)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1875, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'C', 'MAn', '2025-09-13', '02:00 PM - 04:00 PM', '703 (011163060-0112330836)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1876, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'D', 'MAn', '2025-09-13', '02:00 PM - 04:00 PM', '707 (011201122-0112410299)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1877, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'E', 'RBS', '2025-09-13', '02:00 PM - 04:00 PM', '711 (011211132-0112320288)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1878, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'F', 'GMB', '2025-09-13', '02:00 PM - 04:00 PM', '723 (011202094-0112410205)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1879, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'G', 'MNHn', '2025-09-13', '02:00 PM - 04:00 PM', '725 (011221160-0112420144)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1880, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'H', 'MAn', '2025-09-13', '02:00 PM - 04:00 PM', '729 (011211049-0112410126)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1881, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'I', 'GMB', '2025-09-13', '02:00 PM - 04:00 PM', '731 (011163013-0112410051)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1882, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'J', 'SaDa', '2025-09-13', '02:00 PM - 04:00 PM', '801 (011221519-0112420227)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1883, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'K', 'HaHR', '2025-09-13', '02:00 PM - 04:00 PM', '803 (011212031-0112420108)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1884, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'L', 'HaHR', '2025-09-13', '02:00 PM - 04:00 PM', '805 (011201319-0112420233)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1885, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'M', 'NBZ', '2025-09-13', '02:00 PM - 04:00 PM', '901 (011221255-0112410287)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1886, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'N', 'SaDa', '2025-09-13', '02:00 PM - 04:00 PM', '903 (011211080-0112420208)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1887, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'O', 'RaAN', '2025-09-13', '02:00 PM - 04:00 PM', '905 (011213089-0112410302)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1888, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'P', 'AyFz', '2025-09-13', '02:00 PM - 04:00 PM', '907 (011211097-0112410099)                                                                          ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1889, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'Q', 'AyFz', '2025-09-13', '02:00 PM - 04:00 PM', '1029 (011202081-0112330918)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01'),
(1890, 1, 1, 'Midterm', 'PHY 105/PHY 2105', 'Physics', 'R', 'RBS', '2025-09-13', '02:00 PM - 04:00 PM', '1031 (011211002-0112330351)                                                                         ', 'mid-term-exam-schedule_252_sose_notice-board.csv', 'admin', '2025-10-19 14:16:01');

-- --------------------------------------------------------

--
-- Table structure for table `exam_schedule`
--

CREATE TABLE `exam_schedule` (
  `exam_schedule_id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `trimester_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `building` varchar(50) DEFAULT NULL,
  `exam_type` enum('Midterm','Final','Quiz','Make-up') DEFAULT 'Midterm',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `focus_achievements`
--

CREATE TABLE `focus_achievements` (
  `achievement_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `achievement_type` enum('sessions','minutes','streak') NOT NULL,
  `required_value` int(11) NOT NULL COMMENT 'Value required to unlock',
  `points_reward` int(11) NOT NULL DEFAULT 0,
  `icon` varchar(50) DEFAULT 'fa-trophy',
  `created_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `focus_achievements`
--

INSERT INTO `focus_achievements` (`achievement_id`, `title`, `description`, `achievement_type`, `required_value`, `points_reward`, `icon`, `created_date`) VALUES
(1, 'First Focus', 'Complete your first focus session', 'sessions', 1, 50, 'fa-star', '2025-10-02 19:02:52'),
(2, 'Focus Beginner', 'Complete 5 focus sessions', 'sessions', 5, 100, 'fa-medal', '2025-10-02 19:02:52'),
(3, 'Focus Enthusiast', 'Complete 10 focus sessions', 'sessions', 10, 200, 'fa-fire', '2025-10-02 19:02:52'),
(4, 'Focus Expert', 'Complete 25 focus sessions', 'sessions', 25, 500, 'fa-crown', '2025-10-02 19:02:52'),
(5, 'Focus Master', 'Complete 50 focus sessions', 'sessions', 50, 1000, 'fa-gem', '2025-10-02 19:02:52'),
(6, 'Focus Legend', 'Complete 100 focus sessions', 'sessions', 100, 2500, 'fa-trophy', '2025-10-02 19:02:52'),
(7, '1 Hour Focus', 'Complete 60 minutes of focus time', 'minutes', 60, 100, 'fa-clock', '2025-10-02 19:02:52'),
(8, '5 Hours Focus', 'Complete 300 minutes of focus time', 'minutes', 300, 250, 'fa-hourglass-half', '2025-10-02 19:02:52'),
(9, '10 Hours Focus', 'Complete 600 minutes of focus time', 'minutes', 600, 500, 'fa-hourglass', '2025-10-02 19:02:52'),
(10, 'Marathon Focus', 'Complete 1000 minutes of focus time', 'minutes', 1000, 1000, 'fa-running', '2025-10-02 19:02:52'),
(11, 'Ultra Focus', 'Complete 2500 minutes of focus time', 'minutes', 2500, 2500, 'fa-rocket', '2025-10-02 19:02:52'),
(12, '2 Day Streak', 'Focus for 2 days in a row', 'streak', 2, 100, 'fa-fire', '2025-10-02 19:02:52'),
(13, 'Week Warrior', 'Focus for 7 days in a row', 'streak', 7, 300, 'fa-calendar-check', '2025-10-02 19:02:52'),
(14, 'Fortnight Fighter', 'Focus for 14 days in a row', 'streak', 14, 700, 'fa-bolt', '2025-10-02 19:02:52'),
(15, 'Monthly Master', 'Focus for 30 days in a row', 'streak', 30, 1500, 'fa-star', '2025-10-02 19:02:52'),
(16, 'Consistency King', 'Focus for 60 days in a row', 'streak', 60, 3000, 'fa-crown', '2025-10-02 19:02:52'),
(17, 'Legendary Streak', 'Focus for 100 days in a row', 'streak', 100, 5000, 'fa-infinity', '2025-10-02 19:02:52');

-- --------------------------------------------------------

--
-- Table structure for table `focus_sessions`
--

CREATE TABLE `focus_sessions` (
  `session_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `session_duration` int(11) NOT NULL COMMENT 'Duration in minutes',
  `session_mode` enum('pomodoro','short-break','long-break') DEFAULT 'pomodoro',
  `points_earned` int(11) NOT NULL DEFAULT 0,
  `session_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `grade_id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `trimester_gpa` decimal(3,2) DEFAULT 0.00,
  `letter_grade` varchar(2) DEFAULT NULL,
  `grade_points` decimal(3,2) DEFAULT NULL,
  `midterm_marks` decimal(5,2) DEFAULT NULL,
  `final_marks` decimal(5,2) DEFAULT NULL,
  `total_marks` decimal(5,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `note_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `points_awarded` int(11) DEFAULT 10,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `downloads_count` int(11) DEFAULT 0,
  `likes_count` int(11) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `notes`
--
DELIMITER $$
CREATE TRIGGER `trg_update_points_notes` AFTER INSERT ON `notes` FOR EACH ROW BEGIN
    IF NEW.status = 'approved' THEN
        UPDATE students 
        SET total_points = total_points + NEW.points_awarded
        WHERE student_id = NEW.student_id;
        
        INSERT INTO student_points (student_id, points, action_type, reference_id, reference_type, description)
        VALUES (NEW.student_id, NEW.points_awarded, 'note_upload', NEW.note_id, 'note', 
                CONCAT('Points earned for uploading note: ', NEW.title));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notices`
--

CREATE TABLE `notices` (
  `notice_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `notice_type` enum('faculty','other','general') DEFAULT 'general',
  `posted_by` varchar(100) DEFAULT NULL,
  `posted_date` date NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','expired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `program_id` int(11) NOT NULL,
  `program_code` varchar(20) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `department_id` int(11) NOT NULL,
  `total_required_credits` int(11) NOT NULL,
  `duration_years` decimal(3,1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`program_id`, `program_code`, `program_name`, `department_id`, `total_required_credits`, `duration_years`, `created_at`) VALUES
(1, 'BSC_CSE', 'Bachelor of Science in Computer Science and Engineering', 1, 138, 4.0, '2025-10-01 20:12:28'),
(2, 'BSC_EEE', 'Bachelor of Science in Electrical and Electronic Engineering', 2, 140, 4.0, '2025-10-01 20:12:28'),
(3, 'BBA', 'Bachelor of Business Administration', 3, 120, 4.0, '2025-10-01 20:12:28'),
(4, 'MBA', 'Master of Business Administration', 3, 48, 1.5, '2025-10-01 20:12:28'),
(5, 'MSCSE', 'Master of Science in Computer Science and Engineering', 1, 36, 1.5, '2025-10-01 20:12:28');

-- --------------------------------------------------------

--
-- Table structure for table `question_solutions`
--

CREATE TABLE `question_solutions` (
  `solution_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `course_id` int(11) NOT NULL,
  `question_title` varchar(200) NOT NULL,
  `question_text` text DEFAULT NULL,
  `solution_text` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `exam_type` enum('midterm','final','quiz','assignment','practice') NOT NULL,
  `trimester_id` int(11) DEFAULT NULL,
  `points_awarded` int(11) DEFAULT 15,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `downloads_count` int(11) DEFAULT 0,
  `likes_count` int(11) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `question_solutions`
--
DELIMITER $$
CREATE TRIGGER `trg_update_points_solutions` AFTER INSERT ON `question_solutions` FOR EACH ROW BEGIN
    IF NEW.status = 'approved' THEN
        UPDATE students 
        SET total_points = total_points + NEW.points_awarded
        WHERE student_id = NEW.student_id;
        
        INSERT INTO student_points (student_id, points, action_type, reference_id, reference_type, description)
        VALUES (NEW.student_id, NEW.points_awarded, 'solution_upload', NEW.solution_id, 'solution', 
                CONCAT('Points earned for uploading solution: ', NEW.question_title));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `resource_bookmarks`
--

CREATE TABLE `resource_bookmarks` (
  `bookmark_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `bookmarked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resource_categories`
--

CREATE TABLE `resource_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_icon` varchar(50) DEFAULT 'fa-folder',
  `category_color` varchar(20) DEFAULT '#3b82f6',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resource_categories`
--

INSERT INTO `resource_categories` (`category_id`, `category_name`, `category_icon`, `category_color`, `created_at`) VALUES
(1, 'Study Notes', 'fas fa-sticky-note', '#f68b1f', '2025-10-02 12:36:30'),
(2, 'Past Papers', 'fas fa-file-alt', '#3b82f6', '2025-10-02 12:36:30'),
(3, 'CT Solutions', 'fas fa-check-circle', '#10b981', '2025-10-02 12:36:30'),
(4, 'Assignment Solutions', 'fas fa-clipboard-check', '#8b5cf6', '2025-10-02 12:36:30'),
(5, 'Video Lectures', 'fas fa-video', '#ef4444', '2025-10-02 12:36:30'),
(6, 'Books & PDFs', 'fas fa-book', '#f59e0b', '2025-10-02 12:36:30'),
(7, 'Code & Projects', 'fas fa-code', '#06b6d4', '2025-10-02 12:36:30'),
(8, 'Other Resources', 'fas fa-folder-open', '#6b7280', '2025-10-02 12:36:30');

-- --------------------------------------------------------

--
-- Table structure for table `resource_comments`
--

CREATE TABLE `resource_comments` (
  `comment_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `comment_text` text NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL COMMENT 'For nested replies',
  `commented_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resource_likes`
--

CREATE TABLE `resource_likes` (
  `like_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `liked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `resource_likes`
--
DELIMITER $$
CREATE TRIGGER `after_like_delete` AFTER DELETE ON `resource_likes` FOR EACH ROW UPDATE uploaded_resources 
        SET likes_count = likes_count - 1 
        WHERE resource_id = OLD.resource_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_like_insert` AFTER INSERT ON `resource_likes` FOR EACH ROW UPDATE uploaded_resources 
        SET likes_count = likes_count + 1 
        WHERE resource_id = NEW.resource_id
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `resource_views`
--

CREATE TABLE `resource_views` (
  `view_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `student_id` varchar(10) DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resource_views`
--

INSERT INTO `resource_views` (`view_id`, `resource_id`, `student_id`, `viewed_at`) VALUES
(16, 12, '0112320240', '2025-10-19 16:10:33'),
(17, 12, '0112320240', '2025-10-19 17:22:06');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` varchar(10) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `program_id` int(11) NOT NULL,
  `current_trimester_number` int(11) DEFAULT 1,
  `total_completed_credits` int(11) DEFAULT 0,
  `current_cgpa` decimal(3,2) DEFAULT 0.00,
  `total_points` int(11) DEFAULT 0,
  `profile_picture` varchar(255) DEFAULT NULL,
  `admission_date` date DEFAULT NULL,
  `status` enum('active','inactive','graduated','withdrawn') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `focus_streak` int(11) DEFAULT 0 COMMENT 'Current daily focus streak'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `password_hash`, `full_name`, `email`, `phone`, `date_of_birth`, `blood_group`, `father_name`, `mother_name`, `program_id`, `current_trimester_number`, `total_completed_credits`, `current_cgpa`, `total_points`, `profile_picture`, `admission_date`, `status`, `created_at`, `updated_at`, `focus_streak`) VALUES
('0112320240', '$2y$10$xzeO35iY.jE08O8YRqA.xuvAqwA3gKbBPwscf5JJlT15cIoWtyJVS', 'Sifatullah', NULL, '+8801608962341', '2004-08-22', 'A+', 'Mohammad Abdus Salam', 'Shoheli Parvin Nazma', 1, 1, 65, 4.00, 50, '9cf6a546-c5d4-42b1-8c18-6e9cccd167ca.jpg', NULL, 'active', '2025-10-19 14:08:05', '2025-10-19 16:02:02', 0);

-- --------------------------------------------------------

--
-- Table structure for table `student_achievements`
--

CREATE TABLE `student_achievements` (
  `student_achievement_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `earned_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_activities`
--

CREATE TABLE `student_activities` (
  `activity_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `activity_type` enum('login','course_view','assignment_submit','note_upload','question_post','quiz_complete','grade_received','attendance_marked','todo_complete','study_session','other') NOT NULL,
  `activity_title` varchar(255) NOT NULL,
  `activity_description` text DEFAULT NULL,
  `related_course_id` int(11) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL COMMENT 'ID of related entity (assignment_id, note_id, etc.)',
  `icon_class` varchar(50) DEFAULT 'fa-circle' COMMENT 'FontAwesome icon class',
  `activity_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Student activity feed/history';

--
-- Dumping data for table `student_activities`
--

INSERT INTO `student_activities` (`activity_id`, `student_id`, `activity_type`, `activity_title`, `activity_description`, `related_course_id`, `related_id`, `icon_class`, `activity_date`) VALUES
(51, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-19 14:08:45'),
(52, '0112320240', 'other', 'Added new task', 'Need to complete UCAM integration', NULL, NULL, 'fa-plus-circle', '2025-10-19 14:17:04'),
(53, '0112320240', 'other', 'Added new task', 'Integrate Teacher part', NULL, NULL, 'fa-plus-circle', '2025-10-19 14:18:17'),
(54, '0112320240', 'todo_complete', 'Completed task', 'Integrate Teacher part', NULL, 4, 'fa-check-circle', '2025-10-19 14:18:18'),
(55, '0112320240', 'note_upload', 'Uploaded Resource', 'Uploaded: abc', 33, 12, 'fa-upload', '2025-10-19 16:02:02'),
(56, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-19 17:21:06');

-- --------------------------------------------------------

--
-- Table structure for table `student_advisors`
--

CREATE TABLE `student_advisors` (
  `advisor_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_advisors`
--

INSERT INTO `student_advisors` (`advisor_id`, `student_id`, `teacher_id`, `assigned_date`, `is_current`) VALUES
(4, '0112320240', 3, '2025-10-19', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_billing`
--

CREATE TABLE `student_billing` (
  `billing_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `total_billed` decimal(10,2) DEFAULT 0.00,
  `total_paid` decimal(10,2) DEFAULT 0.00,
  `total_waived` decimal(10,2) DEFAULT 0.00,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `last_payment_date` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_billing`
--

INSERT INTO `student_billing` (`billing_id`, `student_id`, `total_billed`, `total_paid`, `total_waived`, `current_balance`, `last_payment_date`, `updated_at`) VALUES
(5, '0112320240', 104793.80, 98294.00, 388131.30, 6499.75, NULL, '2025-10-19 14:08:05');

-- --------------------------------------------------------

--
-- Table structure for table `student_notifications`
--

CREATE TABLE `student_notifications` (
  `notification_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `notification_type` enum('assignment','grade','announcement','deadline_reminder','system') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_points`
--

CREATE TABLE `student_points` (
  `point_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `points` int(11) NOT NULL,
  `action_type` enum('note_upload','solution_upload','note_download','solution_download','bonus','penalty') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` enum('note','solution') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_todos`
--

CREATE TABLE `student_todos` (
  `todo_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `task` text NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Student to-do list items';

--
-- Dumping data for table `student_todos`
--

INSERT INTO `student_todos` (`todo_id`, `student_id`, `task`, `completed`, `priority`, `due_date`, `created_at`, `updated_at`) VALUES
(3, '0112320240', 'Need to complete UCAM integration', 0, 'medium', NULL, '2025-10-19 14:17:04', '2025-10-19 14:17:04'),
(4, '0112320240', 'Integrate Teacher part', 0, 'medium', NULL, '2025-10-19 14:18:17', '2025-10-19 14:18:19');

-- --------------------------------------------------------

--
-- Table structure for table `submission_grades`
--

CREATE TABLE `submission_grades` (
  `grade_id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `marks_obtained` decimal(5,2) NOT NULL,
  `marks_after_penalty` decimal(5,2) DEFAULT NULL COMMENT 'After late penalty',
  `feedback` text DEFAULT NULL,
  `rubric_scores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Detailed rubric breakdown' CHECK (json_valid(`rubric_scores`)),
  `graded_by` int(11) NOT NULL COMMENT 'teacher_id',
  `graded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `submission_grades`
--
DELIMITER $$
CREATE TRIGGER `after_grade_insert_analytics` AFTER INSERT ON `submission_grades` FOR EACH ROW BEGIN
    DECLARE assign_id INT;
    
    SELECT assignment_id INTO assign_id
    FROM assignment_submissions
    WHERE submission_id = NEW.submission_id;
    
    -- Recalculate analytics (simple version - full calculation in stored procedure)
    INSERT INTO assignment_analytics (assignment_id, last_calculated)
    VALUES (assign_id, CURRENT_TIMESTAMP)
    ON DUPLICATE KEY UPDATE last_calculated = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_submission_grade_insert` AFTER INSERT ON `submission_grades` FOR EACH ROW BEGIN
    UPDATE `assignment_submissions`
    SET `status` = 'graded'
    WHERE `submission_id` = NEW.submission_id;
    
    -- Create notification for student
    INSERT INTO `student_notifications` (student_id, notification_type, title, message, related_type, related_id)
    SELECT 
        s.student_id,
        'grade_posted',
        'Assignment Graded',
        CONCAT('Your submission for "', a.title, '" has been graded. Score: ', NEW.marks_obtained, '/', a.total_marks),
        'assignment',
        a.assignment_id
    FROM assignment_submissions sub
    JOIN assignments a ON sub.assignment_id = a.assignment_id
    WHERE sub.submission_id = NEW.submission_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `is_public`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Smart Campus', 'string', 'general', 'Name of the campus system', 1, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(2, 'site_tagline', 'Intelligent Campus Management System', 'string', 'general', 'System tagline', 1, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(3, 'points_per_note', '10', 'integer', 'rewards', 'Points awarded for uploading notes', 0, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(4, 'points_per_solution', '15', 'integer', 'rewards', 'Points awarded for uploading solutions', 0, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(5, 'auto_backup_enabled', 'false', 'boolean', 'backup', 'Enable automatic database backups', 0, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(6, 'backup_frequency', 'daily', 'string', 'backup', 'Backup frequency (daily, weekly, monthly)', 0, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(7, 'max_upload_size', '10485760', 'integer', 'uploads', 'Maximum file upload size in bytes (10MB)', 0, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(8, 'allowed_file_types', '[\"pdf\", \"docx\", \"pptx\", \"txt\", \"jpg\", \"png\"]', 'json', 'uploads', 'Allowed file extensions for uploads', 0, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(9, 'email_notifications', 'false', 'boolean', 'notifications', 'Enable email notifications', 0, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(10, 'maintenance_mode', 'false', 'boolean', 'system', 'Put system in maintenance mode', 0, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(11, 'session_timeout', '3600', 'integer', 'security', 'Admin session timeout in seconds', 0, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(12, 'pagination_limit', '50', 'integer', 'interface', 'Number of records per page', 1, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28'),
(13, 'theme_color', '#667eea', 'string', 'interface', 'Primary theme color', 1, NULL, '2025-10-01 20:12:28', '2025-10-01 20:12:28');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `initial` varchar(10) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','on_leave') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `username`, `password_hash`, `full_name`, `initial`, `email`, `phone`, `room_number`, `department_id`, `designation`, `profile_picture`, `status`, `created_at`, `updated_at`) VALUES
(3, 'ShArn', '$2y$10$Acr.FNuWGZtYk3WouFZZf.psJayi7NRcriN.IY5mP31mqLngrC.Oe', 'Sherajul Arifin', 'ShArn', 'sherajul@cse.uiu.ac.bd', '+8801747504514', '319 C', 1, NULL, NULL, 'active', '2025-10-19 14:08:05', '2025-10-19 14:08:05');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_announcements`
--

CREATE TABLE `teacher_announcements` (
  `announcement_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL COMMENT 'NULL = general announcement',
  `trimester_id` int(11) NOT NULL,
  `section` varchar(10) DEFAULT NULL COMMENT 'NULL = all sections',
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `announcement_type` enum('urgent','important','general','reminder') DEFAULT 'general',
  `file_path` varchar(255) DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `published_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_notifications`
--

CREATE TABLE `teacher_notifications` (
  `notification_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `notification_type` enum('new_submission','late_submission','student_query','system','deadline_reminder') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `related_type` enum('assignment','submission','course','student','other') DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_sessions`
--

CREATE TABLE `teacher_sessions` (
  `session_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_sessions`
--

INSERT INTO `teacher_sessions` (`session_id`, `teacher_id`, `session_token`, `ip_address`, `user_agent`, `login_time`, `last_activity`, `expires_at`, `is_active`) VALUES
(1, 3, '482a534f93221e2602a1ba2c2e1b7ce659680f7de7ccb2724567c55357c3f862', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-19 17:07:20', '2025-10-19 17:07:20', '2025-10-19 15:07:20', 1),
(2, 3, '8a66f0a64844e079be67710cd5c72503fc5e7b96f70637a27eb3471c44f4b45e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-19 17:20:01', '2025-10-19 17:20:01', '2025-10-19 15:20:01', 1),
(3, 3, '939e7878dca1292786513e2c1279cba023e6bcaeb373265a0d1c466aa589411c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-19 17:25:18', '2025-10-19 17:25:18', '2025-10-19 15:25:18', 1);

-- --------------------------------------------------------

--
-- Table structure for table `trimesters`
--

CREATE TABLE `trimesters` (
  `trimester_id` int(11) NOT NULL,
  `trimester_code` varchar(20) NOT NULL,
  `trimester_name` varchar(50) NOT NULL,
  `trimester_type` enum('trimester','semester') NOT NULL,
  `year` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trimesters`
--

INSERT INTO `trimesters` (`trimester_id`, `trimester_code`, `trimester_name`, `trimester_type`, `year`, `start_date`, `end_date`, `is_current`, `created_at`) VALUES
(1, '252', 'Summer 2025', 'trimester', 2025, '2025-06-01', '2025-08-31', 1, '2025-10-01 20:12:28'),
(2, '253', 'Fall 2025', 'trimester', 2025, '2025-09-01', '2025-12-31', 0, '2025-10-01 20:12:28');

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_resources`
--

CREATE TABLE `uploaded_resources` (
  `resource_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `resource_type` enum('file','link','google_drive','youtube','other_cloud') NOT NULL,
  `file_path` varchar(500) DEFAULT NULL COMMENT 'Local file path if uploaded',
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'File size in bytes',
  `file_type` varchar(100) DEFAULT NULL COMMENT 'MIME type',
  `external_link` varchar(500) DEFAULT NULL COMMENT 'Google Drive, YouTube, or other links',
  `trimester_id` int(11) DEFAULT NULL,
  `points_awarded` int(11) DEFAULT 50,
  `views_count` int(11) DEFAULT 0,
  `downloads_count` int(11) DEFAULT 0,
  `likes_count` int(11) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 1 COMMENT 'Admin approval status',
  `is_featured` tinyint(1) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Uploaded resources and notes by students';

--
-- Dumping data for table `uploaded_resources`
--

INSERT INTO `uploaded_resources` (`resource_id`, `student_id`, `course_id`, `category_id`, `title`, `description`, `resource_type`, `file_path`, `file_name`, `file_size`, `file_type`, `external_link`, `trimester_id`, `points_awarded`, `views_count`, `downloads_count`, `likes_count`, `is_approved`, `is_featured`, `uploaded_at`) VALUES
(12, '0112320240', 33, 3, 'abc', '', 'file', 'uploads/resources/0112320240_1760889722_68f50b7a59c75.pdf', 'SRS Draft by me.pdf', 2137263, '0', '', NULL, 50, 2, 0, 0, 1, 0, '2025-10-19 16:02:02');

--
-- Triggers `uploaded_resources`
--
DELIMITER $$
CREATE TRIGGER `after_resource_insert` AFTER INSERT ON `uploaded_resources` FOR EACH ROW BEGIN
            IF NEW.is_approved = 1 THEN
                UPDATE students 
                SET total_points = total_points + NEW.points_awarded 
                WHERE student_id = NEW.student_id;
                
                INSERT INTO student_activities 
                (student_id, activity_type, activity_title, activity_description, related_course_id, related_id, icon_class)
                VALUES 
                (NEW.student_id, 'note_upload', 'Uploaded Resource', CONCAT('Uploaded: ', NEW.title), NEW.course_id, NEW.resource_id, 'fa-upload');
            END IF;
        END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_assignment_status`
-- (See below for the actual view)
--
CREATE TABLE `vw_assignment_status` (
`assignment_id` int(11)
,`title` varchar(200)
,`course_id` int(11)
,`course_code` varchar(20)
,`section` varchar(10)
,`due_date` datetime
,`is_published` tinyint(1)
,`total_marks` decimal(5,2)
,`total_students` bigint(21)
,`total_submissions` bigint(21)
,`graded_submissions` bigint(21)
,`on_time_submissions` bigint(21)
,`late_submissions` bigint(21)
,`average_score` decimal(6,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_pending_grading`
-- (See below for the actual view)
--
CREATE TABLE `vw_pending_grading` (
`submission_id` int(11)
,`assignment_id` int(11)
,`assignment_title` varchar(200)
,`teacher_id` int(11)
,`student_id` varchar(10)
,`student_name` varchar(100)
,`course_code` varchar(20)
,`course_name` varchar(200)
,`section` varchar(5)
,`submitted_at` timestamp
,`is_late` tinyint(1)
,`late_days` int(11)
,`total_marks` decimal(5,2)
,`days_pending` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_teacher_courses`
-- (See below for the actual view)
--
CREATE TABLE `vw_teacher_courses` (
`teacher_id` int(11)
,`teacher_name` varchar(100)
,`teacher_initial` varchar(10)
,`course_id` int(11)
,`course_code` varchar(20)
,`course_name` varchar(200)
,`section` varchar(5)
,`trimester_id` int(11)
,`trimester_name` varchar(50)
,`enrolled_students` bigint(21)
,`total_assignments` bigint(21)
,`average_class_gpa` decimal(7,6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_student_academic_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_student_academic_summary` (
`student_id` varchar(10)
,`full_name` varchar(100)
,`program_name` varchar(100)
,`current_trimester_number` int(11)
,`total_completed_credits` int(11)
,`current_cgpa` decimal(3,2)
,`total_points` int(11)
,`current_enrolled_courses` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_student_course_attendance`
-- (See below for the actual view)
--
CREATE TABLE `v_student_course_attendance` (
`student_id` varchar(10)
,`course_code` varchar(20)
,`course_name` varchar(200)
,`section` varchar(5)
,`trimester_name` varchar(50)
,`present_count` int(11)
,`absent_count` int(11)
,`remaining_classes` int(11)
,`total_classes` int(11)
,`attendance_percentage` decimal(5,2)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_assignment_status`
--
DROP TABLE IF EXISTS `vw_assignment_status`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_assignment_status`  AS SELECT `a`.`assignment_id` AS `assignment_id`, `a`.`title` AS `title`, `a`.`course_id` AS `course_id`, `c`.`course_code` AS `course_code`, `a`.`section` AS `section`, `a`.`due_date` AS `due_date`, `a`.`is_published` AS `is_published`, `a`.`total_marks` AS `total_marks`, count(distinct `e`.`student_id`) AS `total_students`, count(distinct `sub`.`submission_id`) AS `total_submissions`, count(distinct case when `g`.`grade_id` is not null then `sub`.`submission_id` end) AS `graded_submissions`, count(distinct case when `sub`.`is_late` = 0 then `sub`.`submission_id` end) AS `on_time_submissions`, count(distinct case when `sub`.`is_late` = 1 then `sub`.`submission_id` end) AS `late_submissions`, round(avg(`g`.`marks_obtained`),2) AS `average_score` FROM ((((`assignments` `a` join `courses` `c` on(`a`.`course_id` = `c`.`course_id`)) left join `enrollments` `e` on(`a`.`course_id` = `e`.`course_id` and `a`.`trimester_id` = `e`.`trimester_id` and (`a`.`section` is null or `e`.`section` = `a`.`section`) and `e`.`status` = 'enrolled')) left join `assignment_submissions` `sub` on(`a`.`assignment_id` = `sub`.`assignment_id` and `e`.`student_id` = `sub`.`student_id`)) left join `submission_grades` `g` on(`sub`.`submission_id` = `g`.`submission_id`)) GROUP BY `a`.`assignment_id`, `a`.`title`, `a`.`course_id`, `c`.`course_code`, `a`.`section`, `a`.`due_date`, `a`.`is_published`, `a`.`total_marks` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_pending_grading`
--
DROP TABLE IF EXISTS `vw_pending_grading`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_pending_grading`  AS SELECT `sub`.`submission_id` AS `submission_id`, `sub`.`assignment_id` AS `assignment_id`, `a`.`title` AS `assignment_title`, `a`.`teacher_id` AS `teacher_id`, `sub`.`student_id` AS `student_id`, `s`.`full_name` AS `student_name`, `c`.`course_code` AS `course_code`, `c`.`course_name` AS `course_name`, `e`.`section` AS `section`, `sub`.`submitted_at` AS `submitted_at`, `sub`.`is_late` AS `is_late`, `sub`.`late_days` AS `late_days`, `a`.`total_marks` AS `total_marks`, to_days(current_timestamp()) - to_days(`sub`.`submitted_at`) AS `days_pending` FROM (((((`assignment_submissions` `sub` join `assignments` `a` on(`sub`.`assignment_id` = `a`.`assignment_id`)) join `students` `s` on(`sub`.`student_id` = `s`.`student_id`)) join `courses` `c` on(`a`.`course_id` = `c`.`course_id`)) join `enrollments` `e` on(`sub`.`enrollment_id` = `e`.`enrollment_id`)) left join `submission_grades` `g` on(`sub`.`submission_id` = `g`.`submission_id`)) WHERE `sub`.`status` = 'submitted' AND `g`.`grade_id` is null ORDER BY `sub`.`is_late` DESC, `sub`.`submitted_at` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_teacher_courses`
--
DROP TABLE IF EXISTS `vw_teacher_courses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_teacher_courses`  AS SELECT `e`.`teacher_id` AS `teacher_id`, `t`.`full_name` AS `teacher_name`, `t`.`initial` AS `teacher_initial`, `c`.`course_id` AS `course_id`, `c`.`course_code` AS `course_code`, `c`.`course_name` AS `course_name`, `e`.`section` AS `section`, `tr`.`trimester_id` AS `trimester_id`, `tr`.`trimester_name` AS `trimester_name`, count(distinct `e`.`student_id`) AS `enrolled_students`, count(distinct `a`.`assignment_id`) AS `total_assignments`, avg(`g`.`trimester_gpa`) AS `average_class_gpa` FROM (((((`enrollments` `e` join `teachers` `t` on(`e`.`teacher_id` = `t`.`teacher_id`)) join `courses` `c` on(`e`.`course_id` = `c`.`course_id`)) join `trimesters` `tr` on(`e`.`trimester_id` = `tr`.`trimester_id`)) left join `assignments` `a` on(`e`.`course_id` = `a`.`course_id` and `e`.`trimester_id` = `a`.`trimester_id` and `e`.`teacher_id` = `a`.`teacher_id` and (`a`.`section` is null or `a`.`section` = `e`.`section`))) left join `grades` `g` on(`e`.`enrollment_id` = `g`.`enrollment_id`)) WHERE `e`.`status` = 'enrolled' GROUP BY `e`.`teacher_id`, `t`.`full_name`, `t`.`initial`, `c`.`course_id`, `c`.`course_code`, `c`.`course_name`, `e`.`section`, `tr`.`trimester_id`, `tr`.`trimester_name` ;

-- --------------------------------------------------------

--
-- Structure for view `v_student_academic_summary`
--
DROP TABLE IF EXISTS `v_student_academic_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_student_academic_summary`  AS SELECT `s`.`student_id` AS `student_id`, `s`.`full_name` AS `full_name`, `p`.`program_name` AS `program_name`, `s`.`current_trimester_number` AS `current_trimester_number`, `s`.`total_completed_credits` AS `total_completed_credits`, `s`.`current_cgpa` AS `current_cgpa`, `s`.`total_points` AS `total_points`, count(`e`.`enrollment_id`) AS `current_enrolled_courses` FROM ((`students` `s` join `programs` `p` on(`s`.`program_id` = `p`.`program_id`)) left join `enrollments` `e` on(`s`.`student_id` = `e`.`student_id` and `e`.`status` = 'enrolled')) GROUP BY `s`.`student_id`, `s`.`full_name`, `p`.`program_name`, `s`.`current_trimester_number`, `s`.`total_completed_credits`, `s`.`current_cgpa`, `s`.`total_points` ;

-- --------------------------------------------------------

--
-- Structure for view `v_student_course_attendance`
--
DROP TABLE IF EXISTS `v_student_course_attendance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_student_course_attendance`  AS SELECT `e`.`student_id` AS `student_id`, `c`.`course_code` AS `course_code`, `c`.`course_name` AS `course_name`, `e`.`section` AS `section`, `t`.`trimester_name` AS `trimester_name`, `a`.`present_count` AS `present_count`, `a`.`absent_count` AS `absent_count`, `a`.`remaining_classes` AS `remaining_classes`, `a`.`total_classes` AS `total_classes`, `a`.`attendance_percentage` AS `attendance_percentage` FROM (((`enrollments` `e` join `courses` `c` on(`e`.`course_id` = `c`.`course_id`)) join `trimesters` `t` on(`e`.`trimester_id` = `t`.`trimester_id`)) left join `attendance` `a` on(`e`.`enrollment_id` = `a`.`enrollment_id`)) WHERE `e`.`status` = 'enrolled' ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_table_name` (`table_name`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ip_address` (`ip_address`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_token` (`session_token`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD PRIMARY KEY (`read_id`),
  ADD UNIQUE KEY `unique_read` (`announcement_id`,`student_id`),
  ADD KEY `idx_announcement` (`announcement_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `idx_course_trimester` (`course_id`,`trimester_id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `fk_assignment_trimester` (`trimester_id`),
  ADD KEY `idx_assignment_teacher_trimester` (`teacher_id`,`trimester_id`,`is_published`);

--
-- Indexes for table `assignment_analytics`
--
ALTER TABLE `assignment_analytics`
  ADD PRIMARY KEY (`analytics_id`),
  ADD UNIQUE KEY `unique_assignment` (`assignment_id`),
  ADD KEY `idx_assignment` (`assignment_id`);

--
-- Indexes for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD UNIQUE KEY `unique_submission` (`assignment_id`,`student_id`,`attempt_number`),
  ADD KEY `idx_assignment` (`assignment_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_enrollment` (`enrollment_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_submission_status_date` (`status`,`submitted_at`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `unique_attendance` (`enrollment_id`),
  ADD KEY `idx_enrollment` (`enrollment_id`);

--
-- Indexes for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD PRIMARY KEY (`backup_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `class_performance_snapshots`
--
ALTER TABLE `class_performance_snapshots`
  ADD PRIMARY KEY (`snapshot_id`),
  ADD UNIQUE KEY `unique_snapshot` (`course_id`,`section`,`trimester_id`,`snapshot_date`),
  ADD KEY `idx_course_section` (`course_id`,`section`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_date` (`snapshot_date`),
  ADD KEY `fk_snapshot_trimester` (`trimester_id`);

--
-- Indexes for table `class_routine`
--
ALTER TABLE `class_routine`
  ADD PRIMARY KEY (`routine_id`),
  ADD UNIQUE KEY `unique_class_schedule` (`enrollment_id`,`day_of_week`,`start_time`),
  ADD KEY `idx_enrollment` (`enrollment_id`),
  ADD KEY `idx_day` (`day_of_week`);

--
-- Indexes for table `content_modules`
--
ALTER TABLE `content_modules`
  ADD PRIMARY KEY (`module_id`),
  ADD KEY `idx_course_trimester` (`course_id`,`trimester_id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `fk_module_trimester` (`trimester_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `unique_course` (`course_code`,`department_id`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_course_code` (`course_code`);

--
-- Indexes for table `course_contents`
--
ALTER TABLE `course_contents`
  ADD PRIMARY KEY (`content_id`),
  ADD KEY `idx_module` (`module_id`),
  ADD KEY `idx_content_type` (`content_type`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `department_code` (`department_code`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`email_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_scheduled` (`scheduled_at`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`,`trimester_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_trimester` (`trimester_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_enrollment_status` (`status`),
  ADD KEY `idx_teacher_course_trimester` (`teacher_id`,`course_id`,`trimester_id`);

--
-- Indexes for table `exam_grades`
--
ALTER TABLE `exam_grades`
  ADD PRIMARY KEY (`exam_grade_id`),
  ADD UNIQUE KEY `unique_exam_grade` (`enrollment_id`,`exam_type`),
  ADD KEY `idx_enrollment` (`enrollment_id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_exam_type` (`exam_type`);

--
-- Indexes for table `exam_routines`
--
ALTER TABLE `exam_routines`
  ADD PRIMARY KEY (`routine_id`),
  ADD KEY `trimester_id` (`trimester_id`),
  ADD KEY `idx_dept_trimester` (`department_id`,`trimester_id`),
  ADD KEY `idx_exam_type` (`exam_type`),
  ADD KEY `idx_course_section` (`course_code`,`section`);

--
-- Indexes for table `exam_schedule`
--
ALTER TABLE `exam_schedule`
  ADD PRIMARY KEY (`exam_schedule_id`),
  ADD UNIQUE KEY `unique_exam` (`enrollment_id`,`exam_date`,`start_time`),
  ADD KEY `idx_enrollment` (`enrollment_id`),
  ADD KEY `idx_exam_date` (`exam_date`),
  ADD KEY `idx_exam_type` (`exam_type`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_trimester` (`trimester_id`);

--
-- Indexes for table `focus_achievements`
--
ALTER TABLE `focus_achievements`
  ADD PRIMARY KEY (`achievement_id`);

--
-- Indexes for table `focus_sessions`
--
ALTER TABLE `focus_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_student_date` (`student_id`,`session_date`),
  ADD KEY `idx_session_date` (`session_date`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`grade_id`),
  ADD KEY `idx_enrollment` (`enrollment_id`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`note_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_notes_status` (`status`);

--
-- Indexes for table `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`notice_id`),
  ADD KEY `idx_type` (`notice_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_program` (`program_id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`program_id`),
  ADD UNIQUE KEY `program_code` (`program_code`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `question_solutions`
--
ALTER TABLE `question_solutions`
  ADD PRIMARY KEY (`solution_id`),
  ADD KEY `trimester_id` (`trimester_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_solutions_status` (`status`);

--
-- Indexes for table `resource_bookmarks`
--
ALTER TABLE `resource_bookmarks`
  ADD PRIMARY KEY (`bookmark_id`),
  ADD UNIQUE KEY `unique_bookmark` (`resource_id`,`student_id`),
  ADD KEY `idx_resource` (`resource_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `resource_categories`
--
ALTER TABLE `resource_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `resource_comments`
--
ALTER TABLE `resource_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `idx_resource` (`resource_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_parent` (`parent_comment_id`);

--
-- Indexes for table `resource_likes`
--
ALTER TABLE `resource_likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `unique_like` (`resource_id`,`student_id`),
  ADD KEY `idx_resource` (`resource_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `resource_views`
--
ALTER TABLE `resource_views`
  ADD PRIMARY KEY (`view_id`),
  ADD KEY `idx_resource` (`resource_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_viewed_at` (`viewed_at`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD KEY `idx_program` (`program_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_student_cgpa` (`current_cgpa`),
  ADD KEY `idx_student_credits` (`total_completed_credits`),
  ADD KEY `idx_student_points` (`total_points`);

--
-- Indexes for table `student_achievements`
--
ALTER TABLE `student_achievements`
  ADD PRIMARY KEY (`student_achievement_id`),
  ADD UNIQUE KEY `unique_student_achievement` (`student_id`,`achievement_id`),
  ADD KEY `achievement_id` (`achievement_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_earned_date` (`earned_date`);

--
-- Indexes for table `student_activities`
--
ALTER TABLE `student_activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_activity_date` (`activity_date`),
  ADD KEY `idx_activity_type` (`activity_type`),
  ADD KEY `idx_related_course` (`related_course_id`);

--
-- Indexes for table `student_advisors`
--
ALTER TABLE `student_advisors`
  ADD PRIMARY KEY (`advisor_id`),
  ADD UNIQUE KEY `unique_current_advisor` (`student_id`,`teacher_id`,`is_current`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_current` (`is_current`);

--
-- Indexes for table `student_billing`
--
ALTER TABLE `student_billing`
  ADD PRIMARY KEY (`billing_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `student_notifications`
--
ALTER TABLE `student_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_student_unread` (`student_id`,`is_read`,`created_at`);

--
-- Indexes for table `student_points`
--
ALTER TABLE `student_points`
  ADD PRIMARY KEY (`point_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_action` (`action_type`);

--
-- Indexes for table `student_todos`
--
ALTER TABLE `student_todos`
  ADD PRIMARY KEY (`todo_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_completed` (`completed`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_due_date` (`due_date`);

--
-- Indexes for table `submission_grades`
--
ALTER TABLE `submission_grades`
  ADD PRIMARY KEY (`grade_id`),
  ADD UNIQUE KEY `unique_grade` (`submission_id`),
  ADD KEY `idx_submission` (`submission_id`),
  ADD KEY `idx_graded_by` (`graded_by`),
  ADD KEY `idx_grade_marks` (`marks_obtained`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_key` (`setting_key`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `initial` (`initial`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `teacher_announcements`
--
ALTER TABLE `teacher_announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_course_section` (`course_id`,`section`),
  ADD KEY `idx_trimester` (`trimester_id`),
  ADD KEY `idx_published` (`published_at`);

--
-- Indexes for table `teacher_notifications`
--
ALTER TABLE `teacher_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `teacher_sessions`
--
ALTER TABLE `teacher_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `trimesters`
--
ALTER TABLE `trimesters`
  ADD PRIMARY KEY (`trimester_id`),
  ADD UNIQUE KEY `trimester_code` (`trimester_code`),
  ADD KEY `idx_current` (`is_current`),
  ADD KEY `idx_year` (`year`);

--
-- Indexes for table `uploaded_resources`
--
ALTER TABLE `uploaded_resources`
  ADD PRIMARY KEY (`resource_id`),
  ADD KEY `trimester_id` (`trimester_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_uploaded_at` (`uploaded_at`),
  ADD KEY `idx_approved` (`is_approved`);
ALTER TABLE `uploaded_resources` ADD FULLTEXT KEY `idx_search` (`title`,`description`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  MODIFY `read_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_analytics`
--
ALTER TABLE `assignment_analytics`
  MODIFY `analytics_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_history`
--
ALTER TABLE `backup_history`
  MODIFY `backup_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_performance_snapshots`
--
ALTER TABLE `class_performance_snapshots`
  MODIFY `snapshot_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_routine`
--
ALTER TABLE `class_routine`
  MODIFY `routine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `content_modules`
--
ALTER TABLE `content_modules`
  MODIFY `module_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `course_contents`
--
ALTER TABLE `course_contents`
  MODIFY `content_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `email_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `exam_grades`
--
ALTER TABLE `exam_grades`
  MODIFY `exam_grade_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_routines`
--
ALTER TABLE `exam_routines`
  MODIFY `routine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1891;

--
-- AUTO_INCREMENT for table `exam_schedule`
--
ALTER TABLE `exam_schedule`
  MODIFY `exam_schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `focus_achievements`
--
ALTER TABLE `focus_achievements`
  MODIFY `achievement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `focus_sessions`
--
ALTER TABLE `focus_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `grade_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notices`
--
ALTER TABLE `notices`
  MODIFY `notice_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `question_solutions`
--
ALTER TABLE `question_solutions`
  MODIFY `solution_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_bookmarks`
--
ALTER TABLE `resource_bookmarks`
  MODIFY `bookmark_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `resource_categories`
--
ALTER TABLE `resource_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `resource_comments`
--
ALTER TABLE `resource_comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `resource_likes`
--
ALTER TABLE `resource_likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_views`
--
ALTER TABLE `resource_views`
  MODIFY `view_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `student_achievements`
--
ALTER TABLE `student_achievements`
  MODIFY `student_achievement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_activities`
--
ALTER TABLE `student_activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `student_advisors`
--
ALTER TABLE `student_advisors`
  MODIFY `advisor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `student_billing`
--
ALTER TABLE `student_billing`
  MODIFY `billing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `student_notifications`
--
ALTER TABLE `student_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_points`
--
ALTER TABLE `student_points`
  MODIFY `point_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_todos`
--
ALTER TABLE `student_todos`
  MODIFY `todo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `submission_grades`
--
ALTER TABLE `submission_grades`
  MODIFY `grade_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `teacher_announcements`
--
ALTER TABLE `teacher_announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_notifications`
--
ALTER TABLE `teacher_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_sessions`
--
ALTER TABLE `teacher_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `trimesters`
--
ALTER TABLE `trimesters`
  MODIFY `trimester_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `uploaded_resources`
--
ALTER TABLE `uploaded_resources`
  MODIFY `resource_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD CONSTRAINT `admin_sessions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD CONSTRAINT `fk_read_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `teacher_announcements` (`announcement_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_read_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `fk_assignment_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_trimester` FOREIGN KEY (`trimester_id`) REFERENCES `trimesters` (`trimester_id`) ON DELETE CASCADE;

--
-- Constraints for table `assignment_analytics`
--
ALTER TABLE `assignment_analytics`
  ADD CONSTRAINT `fk_analytics_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`) ON DELETE CASCADE;

--
-- Constraints for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD CONSTRAINT `fk_submission_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_submission_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`enrollment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_submission_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`enrollment_id`) ON DELETE CASCADE;

--
-- Constraints for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD CONSTRAINT `backup_history_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `class_performance_snapshots`
--
ALTER TABLE `class_performance_snapshots`
  ADD CONSTRAINT `fk_snapshot_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_snapshot_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_snapshot_trimester` FOREIGN KEY (`trimester_id`) REFERENCES `trimesters` (`trimester_id`) ON DELETE CASCADE;

--
-- Constraints for table `class_routine`
--
ALTER TABLE `class_routine`
  ADD CONSTRAINT `class_routine_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`enrollment_id`) ON DELETE CASCADE;

--
-- Constraints for table `content_modules`
--
ALTER TABLE `content_modules`
  ADD CONSTRAINT `fk_module_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_module_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_module_trimester` FOREIGN KEY (`trimester_id`) REFERENCES `trimesters` (`trimester_id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE;

--
-- Constraints for table `course_contents`
--
ALTER TABLE `course_contents`
  ADD CONSTRAINT `fk_content_module` FOREIGN KEY (`module_id`) REFERENCES `content_modules` (`module_id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`trimester_id`) REFERENCES `trimesters` (`trimester_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE SET NULL;

--
-- Constraints for table `exam_grades`
--
ALTER TABLE `exam_grades`
  ADD CONSTRAINT `fk_exam_grade_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`enrollment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_exam_grade_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `exam_routines`
--
ALTER TABLE `exam_routines`
  ADD CONSTRAINT `exam_routines_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_routines_ibfk_2` FOREIGN KEY (`trimester_id`) REFERENCES `trimesters` (`trimester_id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_schedule`
--
ALTER TABLE `exam_schedule`
  ADD CONSTRAINT `exam_schedule_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`enrollment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedule_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedule_ibfk_3` FOREIGN KEY (`trimester_id`) REFERENCES `trimesters` (`trimester_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedule_ibfk_4` FOREIGN KEY (`uploaded_by`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `focus_sessions`
--
ALTER TABLE `focus_sessions`
  ADD CONSTRAINT `focus_sessions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`enrollment_id`) ON DELETE CASCADE;

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `notices`
--
ALTER TABLE `notices`
  ADD CONSTRAINT `notices_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE SET NULL;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE;

--
-- Constraints for table `question_solutions`
--
ALTER TABLE `question_solutions`
  ADD CONSTRAINT `question_solutions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `question_solutions_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `question_solutions_ibfk_3` FOREIGN KEY (`trimester_id`) REFERENCES `trimesters` (`trimester_id`) ON DELETE SET NULL;

--
-- Constraints for table `resource_bookmarks`
--
ALTER TABLE `resource_bookmarks`
  ADD CONSTRAINT `resource_bookmarks_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `uploaded_resources` (`resource_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_bookmarks_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `resource_comments`
--
ALTER TABLE `resource_comments`
  ADD CONSTRAINT `resource_comments_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `uploaded_resources` (`resource_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_comments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_comments_ibfk_3` FOREIGN KEY (`parent_comment_id`) REFERENCES `resource_comments` (`comment_id`) ON DELETE CASCADE;

--
-- Constraints for table `resource_likes`
--
ALTER TABLE `resource_likes`
  ADD CONSTRAINT `resource_likes_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `uploaded_resources` (`resource_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_likes_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `resource_views`
--
ALTER TABLE `resource_views`
  ADD CONSTRAINT `resource_views_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `uploaded_resources` (`resource_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_views_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_achievements`
--
ALTER TABLE `student_achievements`
  ADD CONSTRAINT `student_achievements_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_achievements_ibfk_2` FOREIGN KEY (`achievement_id`) REFERENCES `focus_achievements` (`achievement_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_activities`
--
ALTER TABLE `student_activities`
  ADD CONSTRAINT `student_activities_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_activities_ibfk_2` FOREIGN KEY (`related_course_id`) REFERENCES `courses` (`course_id`) ON DELETE SET NULL;

--
-- Constraints for table `student_advisors`
--
ALTER TABLE `student_advisors`
  ADD CONSTRAINT `student_advisors_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_advisors_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_billing`
--
ALTER TABLE `student_billing`
  ADD CONSTRAINT `student_billing_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_notifications`
--
ALTER TABLE `student_notifications`
  ADD CONSTRAINT `fk_student_notifications_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_points`
--
ALTER TABLE `student_points`
  ADD CONSTRAINT `student_points_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_todos`
--
ALTER TABLE `student_todos`
  ADD CONSTRAINT `student_todos_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `submission_grades`
--
ALTER TABLE `submission_grades`
  ADD CONSTRAINT `fk_grade_submission` FOREIGN KEY (`submission_id`) REFERENCES `assignment_submissions` (`submission_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_grade_teacher` FOREIGN KEY (`graded_by`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_announcements`
--
ALTER TABLE `teacher_announcements`
  ADD CONSTRAINT `fk_announcement_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_announcement_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_announcement_trimester` FOREIGN KEY (`trimester_id`) REFERENCES `trimesters` (`trimester_id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_notifications`
--
ALTER TABLE `teacher_notifications`
  ADD CONSTRAINT `fk_notification_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_sessions`
--
ALTER TABLE `teacher_sessions`
  ADD CONSTRAINT `fk_session_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE;

--
-- Constraints for table `uploaded_resources`
--
ALTER TABLE `uploaded_resources`
  ADD CONSTRAINT `uploaded_resources_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `uploaded_resources_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `uploaded_resources_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `resource_categories` (`category_id`),
  ADD CONSTRAINT `uploaded_resources_ibfk_4` FOREIGN KEY (`trimester_id`) REFERENCES `trimesters` (`trimester_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
