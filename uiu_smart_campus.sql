-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 16, 2025 at 04:12 PM
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
(4, 1, 'delete', 'exam_schedule', NULL, 'Deleted exam schedule: 3 records, 0 files', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-02 21:17:24');

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
(10, 1, 'Saturday', '09:51:00', '11:10:00', NULL, NULL, '2025-10-02 10:21:34'),
(11, 2, 'Saturday', '08:30:00', '09:50:00', NULL, NULL, '2025-10-02 10:21:34'),
(12, 3, 'Saturday', '11:11:00', '13:40:00', NULL, NULL, '2025-10-02 10:21:34'),
(13, 4, 'Sunday', '08:30:00', '11:00:00', NULL, NULL, '2025-10-02 10:21:34'),
(14, 5, 'Sunday', '12:31:00', '13:50:00', NULL, NULL, '2025-10-02 10:21:34'),
(15, 1, 'Tuesday', '09:51:00', '11:10:00', NULL, NULL, '2025-10-02 10:21:34'),
(16, 7, 'Tuesday', '11:11:00', '13:40:00', NULL, NULL, '2025-10-02 10:21:34'),
(17, 2, 'Tuesday', '08:30:00', '09:50:00', NULL, NULL, '2025-10-02 10:21:34'),
(18, 5, 'Wednesday', '12:31:00', '13:50:00', NULL, NULL, '2025-10-02 10:21:34');

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
(1, '0112320240', 32, 2, 'C', NULL, '2025-10-02', 'enrolled', '2025-10-02 09:02:25'),
(2, '0112320240', 38, 2, 'B', NULL, '2025-10-02', 'enrolled', '2025-10-02 09:02:25'),
(3, '0112320240', 39, 2, 'C', NULL, '2025-10-02', 'enrolled', '2025-10-02 09:02:25'),
(4, '0112320240', 33, 2, 'I', NULL, '2025-10-02', 'enrolled', '2025-10-02 09:02:25'),
(5, '0112320240', 24, 2, 'G', NULL, '2025-10-02', 'enrolled', '2025-10-02 09:02:25'),
(7, '0112320240', 25, 2, 'K', NULL, '2025-10-02', 'enrolled', '2025-10-02 09:02:25');

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
('0112320240', '$2y$10$mgklUzlH9GUrwXCHTYFzGu.2Q4.86CX9heeSa9G8qH05au3B06Gpa', 'Sifatullah', NULL, '+8801608962341', '2004-08-22', 'A+', 'Mohammad Abdus Salam', 'Shoheli Parvin Nazma', 1, 1, 0, 4.00, 0, '9cf6a546-c5d4-42b1-8c18-6e9cccd167ca.jpg', NULL, 'active', '2025-10-02 09:02:25', '2025-10-02 22:24:53', 0),
('0112320253', '$2y$10$pEZlUnfwgnbfB7hV9t0ti.p5hIwn53s0W9u7stlxpSFsBZD7GQMIy', 'Samiul Hoque', NULL, '+8801613236321', '2004-01-21', 'A+', 'S. M. MASUDUL HOQUE', 'SHAHANAJ BEGUM', 1, 1, 65, 3.00, 0, 'ecfc0f88-c6e0-4fe5-b7b6-8e2c7fdf70cb.jpg', NULL, 'active', '2025-10-05 15:11:33', '2025-10-05 15:18:24', 0);

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
(1, '0112320240', 'other', 'Added new task', 'asdasd', NULL, NULL, 'fa-plus-circle', '2025-10-02 11:53:49'),
(2, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-02 11:54:04'),
(3, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 11:54:15'),
(4, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 11:54:16'),
(5, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 11:54:16'),
(6, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-02 12:00:10'),
(7, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 12:00:16'),
(8, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 12:00:16'),
(9, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 12:00:17'),
(10, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 12:00:17'),
(11, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 12:00:49'),
(12, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 12:00:49'),
(13, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 12:00:49'),
(14, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 12:00:50'),
(15, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 12:00:50'),
(16, '0112320240', 'todo_complete', 'Completed task', 'asdasd', NULL, 1, 'fa-check-circle', '2025-10-02 12:04:24'),
(17, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-02 12:35:14'),
(18, '0112320240', 'note_upload', 'Uploaded Resource', 'Uploaded: asdas', NULL, 1, 'fa-upload', '2025-10-02 12:44:43'),
(19, '0112320240', 'note_upload', 'Uploaded Resource', 'asdas', NULL, 1, 'fa-cloud-upload-alt', '2025-10-02 12:44:44'),
(20, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-02 18:21:10'),
(21, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-02 21:40:43'),
(22, '0112320240', 'note_upload', 'Uploaded Resource', 'Uploaded: hello', NULL, 2, 'fa-upload', '2025-10-02 21:47:06'),
(23, '0112320240', 'note_upload', 'Uploaded Resource', 'hello', NULL, 2, 'fa-cloud-upload-alt', '2025-10-02 21:47:08'),
(24, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-02 22:04:28'),
(25, '0112320240', 'note_upload', 'Uploaded Resource', 'Uploaded: sadas', NULL, 3, 'fa-upload', '2025-10-02 22:04:55'),
(26, '0112320240', 'note_upload', 'Uploaded Resource', 'sadas', NULL, 3, 'fa-cloud-upload-alt', '2025-10-02 22:04:56'),
(27, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-02 22:05:32'),
(28, '0112320240', 'other', 'Added new task', 'a', NULL, NULL, 'fa-plus-circle', '2025-10-02 22:05:44'),
(29, '0112320240', 'todo_complete', 'Completed task', 'a', NULL, 2, 'fa-check-circle', '2025-10-02 22:05:46'),
(30, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-05 15:06:55'),
(31, '0112320253', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-05 15:15:22'),
(32, '0112320253', 'note_upload', 'Uploaded Resource', 'Uploaded: Samiul', NULL, 4, 'fa-upload', '2025-10-05 15:15:45'),
(33, '0112320253', 'note_upload', 'Uploaded Resource', 'Samiul', NULL, 4, 'fa-cloud-upload-alt', '2025-10-05 15:15:47'),
(34, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-05 15:15:59'),
(35, '0112320253', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-05 15:18:03'),
(36, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-05 15:19:04'),
(37, '0112320240', 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', NULL, NULL, 'fa-sign-in-alt', '2025-10-16 13:52:00');

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
(1, '0112320240', 1, '2025-10-02', 1),
(2, '0112320253', 1, '2025-10-05', 1);

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
(1, '0112320240', 104793.80, 98294.00, 388131.30, 6499.75, NULL, '2025-10-02 09:02:25'),
(2, '0112320240', 104793.80, 98294.00, 388131.30, 6499.75, NULL, '2025-10-02 10:21:34'),
(3, '0112320253', 248165.50, 238741.00, 242409.50, 9424.50, NULL, '2025-10-05 15:11:33');

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
(1, 'ShArn', '$2y$10$NilDG.qwRvynZs3L2DyyFOs2gla0RJmPPUg9p/.6vIDKF14YRGXd.', 'Sherajul Arifin', 'ShArn', 'sherajul@cse.uiu.ac.bd', '+8801747504514', '319 (C)', 1, NULL, NULL, 'active', '2025-10-02 09:02:25', '2025-10-02 09:02:25');

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
(1, '252', 'Summer 2025', 'trimester', 2025, '2025-06-01', '2025-08-31', 0, '2025-10-01 20:12:28'),
(2, '253', 'Fall 2025', 'trimester', 2025, '2025-09-01', '2025-12-31', 1, '2025-10-01 20:12:28');

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
-- Indexes for table `class_routine`
--
ALTER TABLE `class_routine`
  ADD PRIMARY KEY (`routine_id`),
  ADD UNIQUE KEY `unique_class_schedule` (`enrollment_id`,`day_of_week`,`start_time`),
  ADD KEY `idx_enrollment` (`enrollment_id`),
  ADD KEY `idx_day` (`day_of_week`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `unique_course` (`course_code`,`department_id`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_course_code` (`course_code`);

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
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_trimester` (`trimester_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_enrollment_status` (`status`);

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- AUTO_INCREMENT for table `class_routine`
--
ALTER TABLE `class_routine`
  MODIFY `routine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

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
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
  MODIFY `bookmark_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `view_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_achievements`
--
ALTER TABLE `student_achievements`
  MODIFY `student_achievement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_activities`
--
ALTER TABLE `student_activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `student_advisors`
--
ALTER TABLE `student_advisors`
  MODIFY `advisor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_billing`
--
ALTER TABLE `student_billing`
  MODIFY `billing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_points`
--
ALTER TABLE `student_points`
  MODIFY `point_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_todos`
--
ALTER TABLE `student_todos`
  MODIFY `todo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `trimesters`
--
ALTER TABLE `trimesters`
  MODIFY `trimester_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `uploaded_resources`
--
ALTER TABLE `uploaded_resources`
  MODIFY `resource_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- Constraints for table `class_routine`
--
ALTER TABLE `class_routine`
  ADD CONSTRAINT `class_routine_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`enrollment_id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`trimester_id`) REFERENCES `trimesters` (`trimester_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE SET NULL;

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
