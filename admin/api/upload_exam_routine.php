<?php
/**
 * Admin API - Upload Exam Routine
 * Handles CSV file upload and parsing for exam schedules
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = require_once('../../config/database.php');

// Helper function to parse date
function parseDate($dateStr) {
    if (empty($dateStr)) return null;
    
    // Try various date formats
    $formats = [
        'Y-m-d',
        'd/m/Y',
        'm/d/Y',
        'F d, Y', // October 25, 2025
        'M d, Y'
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateStr);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }
    
    // Try strtotime as fallback
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        if (!isset($_POST['department_id']) || !isset($_POST['trimester_id']) || !isset($_POST['exam_type'])) {
            throw new Exception('Missing required fields');
        }
        
        $department_id = intval($_POST['department_id']);
        $trimester_id = intval($_POST['trimester_id']);
        $exam_type = $_POST['exam_type'];
        
        if (!in_array($exam_type, ['Midterm', 'Final'])) {
            throw new Exception('Invalid exam type');
        }
        
        // Validate file upload
        if (!isset($_FILES['exam_file']) || $_FILES['exam_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded or upload error occurred');
        }
        
        $file = $_FILES['exam_file'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($fileExt !== 'csv') {
            throw new Exception('Invalid file type. Please upload a CSV file');
        }
        
        // Verify department exists
        $deptCheck = $conn->query("SELECT department_id FROM departments WHERE department_id = $department_id");
        if ($deptCheck->num_rows === 0) {
            throw new Exception('Invalid department selected');
        }
        
        // Verify trimester exists
        $trimCheck = $conn->query("SELECT trimester_id FROM trimesters WHERE trimester_id = $trimester_id");
        if ($trimCheck->num_rows === 0) {
            throw new Exception('Invalid trimester selected');
        }
        
        // Delete existing routine for this department/trimester/exam_type
        $deleteStmt = $conn->prepare("DELETE FROM exam_routines WHERE department_id = ? AND trimester_id = ? AND exam_type = ?");
        $deleteStmt->bind_param('iis', $department_id, $trimester_id, $exam_type);
        $deleteStmt->execute();
        
        // Parse CSV file
        $insertCount = 0;
        $errors = [];
        
        if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
            // Read header row
            $headers = fgetcsv($handle);
            
            if (!$headers) {
                throw new Exception('CSV file is empty or invalid');
            }
            
            // Find column indices (flexible column names)
            $deptCol = false;
            $courseCodeCol = false;
            $courseTitleCol = false;
            $sectionCol = false;
            $teacherCol = false;
            $dateCol = false;
            $timeCol = false;
            $roomCol = false;
            
            foreach ($headers as $index => $header) {
                $header = trim($header);
                if (stripos($header, 'dept') !== false) $deptCol = $index;
                else if (stripos($header, 'course code') !== false) $courseCodeCol = $index;
                else if (stripos($header, 'course title') !== false) $courseTitleCol = $index;
                else if (stripos($header, 'section') !== false) $sectionCol = $index;
                else if (stripos($header, 'teacher') !== false) $teacherCol = $index;
                else if (stripos($header, 'exam date') !== false || stripos($header, 'date') !== false) $dateCol = $index;
                else if (stripos($header, 'exam time') !== false || stripos($header, 'time') !== false) $timeCol = $index;
                else if (stripos($header, 'room') !== false) $roomCol = $index;
            }
            
            if ($courseCodeCol === false || $sectionCol === false) {
                throw new Exception('CSV must have "Course Code" and "Section" columns');
            }
            
            // Prepare insert statement
            $insertStmt = $conn->prepare("INSERT INTO exam_routines 
                (department_id, trimester_id, exam_type, course_code, course_title, section, teacher_initial, exam_date, exam_time, room, original_filename, uploaded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $uploaded_by = 'admin';
            $original_filename = $file['name'];
            
            // Read data rows
            $rowNum = 1;
            while (($row = fgetcsv($handle)) !== FALSE) {
                $rowNum++;
                
                if (count($row) < 2) continue; // Skip empty rows
                
                $courseCode = $courseCodeCol !== false ? trim($row[$courseCodeCol]) : '';
                $section = $sectionCol !== false ? trim($row[$sectionCol]) : '';
                
                if (empty($courseCode) || empty($section)) {
                    $errors[] = "Row $rowNum: Missing course code or section";
                    continue;
                }
                
                $courseTitle = $courseTitleCol !== false ? trim($row[$courseTitleCol]) : '';
                $teacherInitial = $teacherCol !== false ? trim($row[$teacherCol]) : '';
                $examDate = $dateCol !== false ? parseDate(trim($row[$dateCol])) : null;
                $examTime = $timeCol !== false ? trim($row[$timeCol]) : '';
                $room = $roomCol !== false ? trim($row[$roomCol]) : '';
                
                // Insert into database
                $insertStmt->bind_param('iissssssssss',
                    $department_id,
                    $trimester_id,
                    $exam_type,
                    $courseCode,
                    $courseTitle,
                    $section,
                    $teacherInitial,
                    $examDate,
                    $examTime,
                    $room,
                    $original_filename,
                    $uploaded_by
                );
                
                if ($insertStmt->execute()) {
                    $insertCount++;
                } else {
                    $errors[] = "Row $rowNum: Database insert failed";
                }
            }
            
            fclose($handle);
        } else {
            throw new Exception('Failed to open CSV file');
        }
        
        if ($insertCount === 0) {
            throw new Exception('No valid entries found in CSV file. Errors: ' . implode(', ', $errors));
        }
        
        $message = "Successfully uploaded $insertCount exam schedule entries";
        if (!empty($errors)) {
            $message .= ". " . count($errors) . " rows had errors.";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'inserted' => $insertCount,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
