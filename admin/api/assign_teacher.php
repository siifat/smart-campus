<?php
session_start();
header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);

$course_id = $data['course_id'] ?? null;
$section = $data['section'] ?? null;
$teacher_id = $data['teacher_id'] ?? null;
$trimester_id = $data['trimester_id'] ?? null;

// Validation
if (!$course_id || !$section || !$teacher_id || !$trimester_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Verify teacher exists and is active
    $check_teacher = $conn->prepare("SELECT full_name, initial FROM teachers WHERE teacher_id = ? AND status = 'active'");
    $check_teacher->bind_param('i', $teacher_id);
    $check_teacher->execute();
    $teacher = $check_teacher->get_result()->fetch_assoc();
    
    if (!$teacher) {
        echo json_encode(['success' => false, 'message' => 'Teacher not found or inactive']);
        exit();
    }
    
    // Update all enrollments for this course-section combination
    $update_stmt = $conn->prepare("
        UPDATE enrollments 
        SET teacher_id = ? 
        WHERE course_id = ? 
          AND section = ? 
          AND trimester_id = ?
          AND status = 'enrolled'
    ");
    $update_stmt->bind_param('iisi', $teacher_id, $course_id, $section, $trimester_id);
    $update_stmt->execute();
    
    $affected_rows = $update_stmt->affected_rows;
    
    if ($affected_rows > 0) {
        // Get course info for notification
        $course_info = $conn->prepare("SELECT course_code, course_name FROM courses WHERE course_id = ?");
        $course_info->bind_param('i', $course_id);
        $course_info->execute();
        $course = $course_info->get_result()->fetch_assoc();
        
        // Create notification for teacher
        $notif_stmt = $conn->prepare("
            INSERT INTO teacher_notifications (teacher_id, notification_type, title, message, priority)
            VALUES (?, 'system', 'New Course Assignment', ?, 'normal')
        ");
        $message = "You have been assigned to teach {$course['course_code']} - {$course['course_name']}, Section {$section}";
        $notif_stmt->bind_param('is', $teacher_id, $message);
        $notif_stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => "{$teacher['full_name']} has been assigned to this section ({$affected_rows} students enrolled)"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No students found in this section or teacher already assigned'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Assign teacher error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
