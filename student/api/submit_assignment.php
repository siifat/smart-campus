<?php
/**
 * Student Assignment Submission API
 * Handle file uploads and submission records
 */
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../config/database.php';

$student_id = $_SESSION['student_id'];
$assignment_id = $_POST['assignment_id'] ?? null;
$enrollment_id = $_POST['enrollment_id'] ?? null;
$submission_text = trim($_POST['submission_text'] ?? '');

if (!$assignment_id || !$enrollment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Verify assignment exists and student is enrolled
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            e.student_id
        FROM assignments a
        JOIN enrollments e ON a.course_id = e.course_id 
            AND a.trimester_id = e.trimester_id
            AND (a.section IS NULL OR a.section = e.section)
        WHERE a.assignment_id = ?
            AND e.enrollment_id = ?
            AND e.student_id = ?
            AND e.status = 'enrolled'
            AND a.is_published = 1
    ");
    $stmt->bind_param('iis', $assignment_id, $enrollment_id, $student_id);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$assignment) {
        echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
        exit();
    }
    
    // Check if already submitted
    $check_stmt = $conn->prepare("
        SELECT submission_id FROM assignment_submissions 
        WHERE assignment_id = ? AND student_id = ?
    ");
    $check_stmt->bind_param('is', $assignment_id, $student_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'You have already submitted this assignment']);
        exit();
    }
    
    // Check if past due and late submission not allowed
    $due_date = new DateTime($assignment['due_date']);
    $now = new DateTime();
    $is_late = $now > $due_date;
    
    if ($is_late && !$assignment['late_submission_allowed']) {
        echo json_encode(['success' => false, 'message' => 'Late submission not allowed for this assignment']);
        exit();
    }
    
    // Validate submission (must have file or text)
    $has_file = isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] !== UPLOAD_ERR_NO_FILE;
    $has_text = !empty($submission_text);
    
    if (!$has_file && !$has_text) {
        echo json_encode(['success' => false, 'message' => 'Please upload a file or enter text submission']);
        exit();
    }
    
    // Handle file upload
    $file_path = null;
    $file_size = null;
    $file_type = null;
    
    if ($has_file) {
        $file = $_FILES['submission_file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File upload error']);
            exit();
        }
        
        // Check file size (10MB limit)
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size must be less than 10MB']);
            exit();
        }
        
        // Check file type
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, DOC, DOCX, and ZIP are allowed']);
            exit();
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = '../../uploads/submissions/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'submission_' . $student_id . '_' . $assignment_id . '_' . time() . '.' . $extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
            exit();
        }
        
        $file_size = $file['size'];
        $file_type = $mime_type;
        $file_path = 'uploads/submissions/' . $filename; // Store relative path
    }
    
    // Calculate late days
    $late_days = 0;
    if ($is_late) {
        $late_days = $now->diff($due_date)->days;
    }
    
    // Insert submission
    $stmt = $conn->prepare("
        INSERT INTO assignment_submissions 
        (assignment_id, student_id, enrollment_id, file_path, file_size, file_type, submission_text, is_late, late_days, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
    ");
    $stmt->bind_param('isisisiii', $assignment_id, $student_id, $enrollment_id, $file_path, $file_size, $file_type, $submission_text, $is_late, $late_days);
    $stmt->execute();
    $submission_id = $conn->insert_id;
    $stmt->close();
    
    // Award points for submission (e.g., 10 points per submission)
    $points = $is_late ? 5 : 10;
    $conn->query("UPDATE students SET total_points = COALESCE(total_points, 0) + $points WHERE student_id = '$student_id'");
    
    // Update session points
    $_SESSION['total_points'] = ($student['total_points'] ?? 0) + $points;
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment submitted successfully' . ($is_late ? ' (late submission)' : ''),
        'submission_id' => $submission_id,
        'points_earned' => $points
    ]);
    
} catch (Exception $e) {
    error_log("Submission error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
