<?php
/**
 * Student API - Get Exam Routine
 * Fetches personalized exam schedule based on student's enrolled courses, department, and current trimester
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$conn = require_once('../../config/database.php');
$student_id = $_SESSION['student_id'];

try {
    // Get exam type from request
    $exam_type = isset($_GET['exam_type']) ? $_GET['exam_type'] : 'Midterm';
    
    if (!in_array($exam_type, ['Midterm', 'Final'])) {
        $exam_type = 'Midterm';
    }
    
    // Get student's program and department
    $studentQuery = "SELECT p.department_id, s.program_id 
                     FROM students s 
                     JOIN programs p ON s.program_id = p.program_id 
                     WHERE s.student_id = ?";
    $studentStmt = $conn->prepare($studentQuery);
    $studentStmt->bind_param('s', $student_id);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    
    if ($studentResult->num_rows === 0) {
        throw new Exception('Student record not found');
    }
    
    $studentData = $studentResult->fetch_assoc();
    $department_id = $studentData['department_id'];
    
    // Get current trimester
    $trimesterQuery = "SELECT trimester_id FROM trimesters WHERE is_current = 1 LIMIT 1";
    $trimesterResult = $conn->query($trimesterQuery);
    
    if ($trimesterResult->num_rows === 0) {
        throw new Exception('No current trimester found');
    }
    
    $trimester = $trimesterResult->fetch_assoc();
    $trimester_id = $trimester['trimester_id'];
    
    // Get student's enrolled courses with sections
    $enrollmentQuery = "SELECT DISTINCT 
                            e.course_id,
                            c.course_code,
                            c.course_name,
                            e.section
                        FROM enrollments e
                        JOIN courses c ON e.course_id = c.course_id
                        WHERE e.student_id = ?
                          AND e.trimester_id = ?
                          AND e.status = 'enrolled'";
    $enrollmentStmt = $conn->prepare($enrollmentQuery);
    $enrollmentStmt->bind_param('si', $student_id, $trimester_id);
    $enrollmentStmt->execute();
    $enrollmentResult = $enrollmentStmt->get_result();
    
    $enrolledCourses = [];
    while ($row = $enrollmentResult->fetch_assoc()) {
        $courseKey = $row['course_code'] . '_' . $row['section'];
        $enrolledCourses[$courseKey] = [
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'section' => $row['section']
        ];
    }
    
    if (empty($enrolledCourses)) {
        echo json_encode([
            'success' => true,
            'exams' => [],
            'message' => 'No enrolled courses found for current trimester'
        ]);
        exit;
    }
    
    // Fetch exam routines from admin-uploaded data for student's department and current trimester
    $routineQuery = "SELECT 
                        er.routine_id,
                        er.course_code,
                        er.course_title,
                        er.section,
                        er.teacher_initial,
                        er.exam_date,
                        er.exam_time,
                        er.room
                     FROM exam_routines er
                     WHERE er.department_id = ?
                       AND er.trimester_id = ?
                       AND er.exam_type = ?
                     ORDER BY er.exam_date ASC, er.exam_time ASC";
    
    $routineStmt = $conn->prepare($routineQuery);
    $routineStmt->bind_param('iis', $department_id, $trimester_id, $exam_type);
    $routineStmt->execute();
    $routineResult = $routineStmt->get_result();
    
    $exams = [];
    
    while ($row = $routineResult->fetch_assoc()) {
        // Check if this exam matches student's enrolled course and section
        $examCourseCode = trim($row['course_code']);
        $examSection = trim($row['section']);
        
        // Try to find matching enrollment
        $matched = false;
        $matchedEnrollment = null;
        foreach ($enrolledCourses as $key => $enrollment) {
            $enrolledCode = trim($enrollment['course_code']);
            $enrolledSection = trim($enrollment['section']);
            
            // Match course code (handle multiple codes separated by /)
            $courseCodes = array_map('trim', explode('/', $examCourseCode));
            $courseMatches = in_array($enrolledCode, $courseCodes);
            
            // Match section (case-insensitive)
            $sectionMatches = (strcasecmp($examSection, $enrolledSection) === 0);
            
            if ($courseMatches && $sectionMatches) {
                $matched = true;
                $matchedEnrollment = $enrollment;
                break;
            }
        }
        
        // Only include exams for enrolled courses
        if ($matched) {
            $exams[] = [
                'routine_id' => $row['routine_id'],
                'course_code' => $row['course_code'],
                'course_title' => $row['course_title'] ?: $matchedEnrollment['course_name'],
                'section' => $row['section'],
                'teacher_initial' => $row['teacher_initial'],
                'exam_date' => $row['exam_date'],
                'exam_time' => $row['exam_time'],
                'room' => $row['room']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'exams' => $exams,
        'exam_type' => $exam_type,
        'department_id' => $department_id,
        'trimester_id' => $trimester_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

