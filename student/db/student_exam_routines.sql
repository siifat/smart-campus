-- Table for storing student-uploaded exam routines
-- This allows students to upload and view their personalized exam schedules

CREATE TABLE IF NOT EXISTS `student_exam_routines` (
  `routine_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(10) NOT NULL,
  `trimester_id` int(11) NOT NULL,
  `exam_type` enum('Midterm','Final') NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `course_title` varchar(255) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `teacher_initial` varchar(50) DEFAULT NULL,
  `exam_date` date NOT NULL,
  `exam_time` varchar(50) DEFAULT NULL,
  `room` varchar(100) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`routine_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_trimester` (`trimester_id`),
  KEY `idx_exam_type` (`exam_type`),
  KEY `idx_exam_date` (`exam_date`),
  CONSTRAINT `fk_student_exam_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_exam_trimester` FOREIGN KEY (`trimester_id`) REFERENCES `trimesters` (`trimester_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
