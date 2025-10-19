<?php
/**
 * Smart Campus API - Sync Student Data Endpoint
 * This script receives student data from the Tampermonkey userscript and syncs it to the database
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Allow CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database configuration
require_once('../config/database.php');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Only POST requests are allowed']);
}

// Get the JSON data from the request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data) {
    respond(['success' => false, 'message' => 'Invalid JSON data']);
}

// Validate required fields
if (empty($data['student_id'])) {
    respond(['success' => false, 'message' => 'Student ID is required']);
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // 1. Update or Insert Student Information
    $student_id = sanitize($data['student_id']);
    $full_name = sanitize($data['full_name'] ?? '');
    $phone = sanitize($data['phone'] ?? '');
    $date_of_birth = convertDate($data['date_of_birth'] ?? '');
    $blood_group = sanitize($data['blood_group'] ?? '');
    $father_name = sanitize($data['father_name'] ?? '');
    $mother_name = sanitize($data['mother_name'] ?? '');
    $program_id = intval($data['program_id'] ?? 1);
    $current_cgpa = floatval($data['transcript_cgpa'] ?? 0);
    $completed_credits = intval($data['completed_credits'] ?? 0);
    $profile_picture = sanitize(basename($data['profile_picture'] ?? ''));
    
    // Handle password - if provided from login capture, hash it; otherwise use student_id as default
    $password_updated = false;
    if (!empty($data['password'])) {
        // Password was captured from login - hash it securely
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $password_updated = true;
    } else {
        // No password provided - use student_id as default (for new accounts only)
        $password_hash = password_hash($student_id, PASSWORD_DEFAULT);
    }
    
    // Check if student exists
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing student
        if ($password_updated) {
            // Update with new password from login capture
            $stmt = $conn->prepare("
                UPDATE students SET 
                    password_hash = ?,
                    full_name = ?,
                    phone = ?,
                    date_of_birth = ?,
                    blood_group = ?,
                    father_name = ?,
                    mother_name = ?,
                    program_id = ?,
                    total_completed_credits = ?,
                    current_cgpa = ?,
                    profile_picture = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE student_id = ?
            ");
            $stmt->bind_param("sssssssiidss", 
                $password_hash, $full_name, $phone, $date_of_birth, $blood_group, 
                $father_name, $mother_name, $program_id, $completed_credits, 
                $current_cgpa, $profile_picture, $student_id
            );
        } else {
            // Update without changing password
            $stmt = $conn->prepare("
                UPDATE students SET 
                    full_name = ?,
                    phone = ?,
                    date_of_birth = ?,
                    blood_group = ?,
                    father_name = ?,
                    mother_name = ?,
                    program_id = ?,
                    total_completed_credits = ?,
                    current_cgpa = ?,
                    profile_picture = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE student_id = ?
            ");
            $stmt->bind_param("ssssssiidss", 
                $full_name, $phone, $date_of_birth, $blood_group, 
                $father_name, $mother_name, $program_id, $completed_credits, 
                $current_cgpa, $profile_picture, $student_id
            );
        }
        $stmt->execute();
        $operation = $password_updated ? 'updated_with_password' : 'updated';
    } else {
        // Insert new student with password (either captured or default)
        $stmt = $conn->prepare("
            INSERT INTO students (
                student_id, password_hash, full_name, phone, date_of_birth, 
                blood_group, father_name, mother_name, program_id, 
                total_completed_credits, current_cgpa, profile_picture
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssssiiis", 
            $student_id, $password_hash, $full_name, $phone, $date_of_birth, 
            $blood_group, $father_name, $mother_name, $program_id, 
            $completed_credits, $current_cgpa, $profile_picture
        );
        $stmt->execute();
        $operation = $password_updated ? 'created_with_password' : 'created_with_default_password';
    }
    
    // 2. Update or Insert Financial Information
    if (isset($data['total_billed'])) {
        $total_billed = floatval($data['total_billed']);
        $current_balance = floatval($data['current_balance'] ?? 0);
        $total_waived = floatval($data['total_waived'] ?? 0);
        $total_paid = floatval($data['total_paid'] ?? 0);
        
        $stmt = $conn->prepare("
            INSERT INTO student_billing (student_id, total_billed, total_paid, total_waived, current_balance)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_billed = VALUES(total_billed),
                total_paid = VALUES(total_paid),
                total_waived = VALUES(total_waived),
                current_balance = VALUES(current_balance),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param("sdddd", $student_id, $total_billed, $total_paid, $total_waived, $current_balance);
        $stmt->execute();
    }
    
    // 3. Sync Advisor Information
    if (!empty($data['advisor_initial'])) {
        $advisor_initial = sanitize($data['advisor_initial']);
        
        // Find or create the teacher
        $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE initial = ?");
        $stmt->bind_param("s", $advisor_initial);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $teacher_id = $result->fetch_assoc()['teacher_id'];
        } else {
            // Create the teacher if doesn't exist
            $advisor_name = sanitize($data['advisor_name'] ?? '');
            $advisor_email = sanitize($data['advisor_email'] ?? '');
            $advisor_phone = sanitize($data['advisor_phone'] ?? '');
            $advisor_room = sanitize($data['advisor_room'] ?? '');
            // $default_password = password_hash($advisor_initial, PASSWORD_BCRYPT);
            // use 123 as the default password for advisors and teachers
            $default_password = password_hash('123', PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("
                INSERT INTO teachers (username, password_hash, full_name, initial, email, phone, room_number, department_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("sssssss", 
                $advisor_initial, $default_password, $advisor_name, 
                $advisor_initial, $advisor_email, $advisor_phone, $advisor_room
            );
            $stmt->execute();
            $teacher_id = $conn->insert_id;
        }
        
        // Assign advisor to student (check if already exists)
        $stmt = $conn->prepare("
            SELECT advisor_id FROM student_advisors 
            WHERE student_id = ? AND teacher_id = ? AND is_current = TRUE
        ");
        $stmt->bind_param("si", $student_id, $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Only insert if this advisor assignment doesn't exist
            $stmt = $conn->prepare("
                INSERT INTO student_advisors (student_id, teacher_id, assigned_date, is_current)
                VALUES (?, ?, CURDATE(), TRUE)
            ");
            $stmt->bind_param("si", $student_id, $teacher_id);
            $stmt->execute();
        }
    }
    
    // 4. Get current trimester from synced data or database
    $current_trimester_id = null;
    
    // Try to use the trimester code from the synced data
    if (!empty($data['current_trimester_code'])) {
        $trimester_code = sanitize($data['current_trimester_code']);
        $stmt = $conn->prepare("SELECT trimester_id FROM trimesters WHERE trimester_code = ? LIMIT 1");
        $stmt->bind_param("s", $trimester_code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $current_trimester_id = $result->fetch_assoc()['trimester_id'];
        }
    }
    
    // Fallback: Get from database is_current flag
    if (!$current_trimester_id) {
        $stmt = $conn->query("SELECT trimester_id FROM trimesters WHERE is_current = TRUE LIMIT 1");
        $current_trimester_id = $stmt->fetch_assoc()['trimester_id'] ?? 2;
    }
    
    // 4.5. Delete existing class routine entries for this student and current trimester to prevent duplicates
    $stmt = $conn->prepare("
        DELETE cr FROM class_routine cr
        JOIN enrollments e ON cr.enrollment_id = e.enrollment_id
        WHERE e.student_id = ? AND e.trimester_id = ?
    ");
    $stmt->bind_param("si", $student_id, $current_trimester_id);
    $stmt->execute();
    
    // 5. Sync Class Routine and Enrollments
    $courses_synced = 0;
    if (!empty($data['class_routine']) && is_array($data['class_routine'])) {
        foreach ($data['class_routine'] as $class) {
            $course_code = sanitize($class['course_code'] ?? '');
            $course_name = sanitize($class['course_name'] ?? '');
            $day = sanitize($class['day'] ?? '');
            $start_time = sanitize($class['start_time'] ?? '');
            $end_time = sanitize($class['end_time'] ?? '');
            
            if (empty($course_code)) continue;
            
            // Extract section from course code (e.g., "CSE 3411 (C)" -> section C)
            preg_match('/\(([A-Z])\)/', $course_code, $matches);
            $section = $matches[1] ?? 'A';
            $course_code_clean = preg_replace('/\s*\([A-Z]\)/', '', $course_code);
            
            // Find or create course
            $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_code = ? LIMIT 1");
            $stmt->bind_param("s", $course_code_clean);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $course_id = $result->fetch_assoc()['course_id'];
            } else {
                // Create course if doesn't exist
                $credit_hours = (strpos($course_name, 'Lab') !== false) ? 1 : 3;
                $course_type = (strpos($course_name, 'Lab') !== false) ? 'lab' : 'theory';
                
                $stmt = $conn->prepare("
                    INSERT INTO courses (course_code, course_name, credit_hours, department_id, course_type)
                    VALUES (?, ?, ?, 1, ?)
                ");
                $stmt->bind_param("ssis", $course_code_clean, $course_name, $credit_hours, $course_type);
                $stmt->execute();
                $course_id = $conn->insert_id;
            }
            
            // Create or update enrollment
            $stmt = $conn->prepare("
                INSERT INTO enrollments (student_id, course_id, trimester_id, section, enrollment_date, status)
                VALUES (?, ?, ?, ?, CURDATE(), 'enrolled')
                ON DUPLICATE KEY UPDATE section = VALUES(section), status = 'enrolled'
            ");
            $stmt->bind_param("siis", $student_id, $course_id, $current_trimester_id, $section);
            $stmt->execute();
            $enrollment_id = ($stmt->affected_rows > 0) ? $conn->insert_id : null;
            
            // If new enrollment, get the ID
            if (!$enrollment_id) {
                $stmt = $conn->prepare("
                    SELECT enrollment_id FROM enrollments 
                    WHERE student_id = ? AND course_id = ? AND trimester_id = ?
                ");
                $stmt->bind_param("sii", $student_id, $course_id, $current_trimester_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $enrollment_id = $result->fetch_assoc()['enrollment_id'] ?? null;
            }
            
            // Add class routine entry
            if ($enrollment_id && $day && $start_time && $end_time) {
                $stmt = $conn->prepare("
                    INSERT INTO class_routine (enrollment_id, day_of_week, start_time, end_time)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time)
                ");
                $stmt->bind_param("isss", $enrollment_id, $day, $start_time, $end_time);
                $stmt->execute();
            }
            
            $courses_synced++;
        }
    }
    
    // 6. Sync Attendance Data
    $attendance_synced = 0;
    if (!empty($data['attendance']) && is_array($data['attendance'])) {
        foreach ($data['attendance'] as $att) {
            $course_code = preg_replace('/\s*\([A-Z]\)/', '', sanitize($att['course_code'] ?? ''));
            $present = intval($att['present_count'] ?? 0);
            $absent = intval($att['absent_count'] ?? 0);
            $remaining = intval($att['remaining_classes'] ?? 0);
            
            if (empty($course_code)) continue;
            
            // Find enrollment
            $stmt = $conn->prepare("
                SELECT e.enrollment_id 
                FROM enrollments e
                JOIN courses c ON e.course_id = c.course_id
                WHERE e.student_id = ? AND c.course_code = ? AND e.trimester_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("ssi", $student_id, $course_code, $current_trimester_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $enrollment_id = $result->fetch_assoc()['enrollment_id'];
                
                // Update attendance
                $stmt = $conn->prepare("
                    INSERT INTO attendance (enrollment_id, present_count, absent_count, remaining_classes, last_updated)
                    VALUES (?, ?, ?, ?, CURDATE())
                    ON DUPLICATE KEY UPDATE
                        present_count = VALUES(present_count),
                        absent_count = VALUES(absent_count),
                        remaining_classes = VALUES(remaining_classes),
                        last_updated = VALUES(last_updated)
                ");
                $stmt->bind_param("iiii", $enrollment_id, $present, $absent, $remaining);
                $stmt->execute();
                $attendance_synced++;
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Success response
    respond([
        'success' => true,
        'message' => 'Data synced successfully',
        'data' => [
            'student_operation' => $operation,
            'student_id' => $student_id,
            'password_synced' => $password_updated,
            'current_trimester' => $data['current_trimester'] ?? 'Unknown',
            'trimester_id_used' => $current_trimester_id,
            'courses_synced' => $courses_synced,
            'attendance_synced' => $attendance_synced
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    respond([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

// Helper Functions

function sanitize($input) {
    return trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
}

function convertDate($dateStr) {
    if (empty($dateStr)) return null;
    
    // Try to parse "22 August, 2004" format
    $date = DateTime::createFromFormat('d F, Y', $dateStr);
    if ($date) {
        return $date->format('Y-m-d');
    }
    
    return null;
}

function respond($data) {
    echo json_encode($data);
    exit();
}
