<?php
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['teacher_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../config/database.php';

$teacher_id = $_SESSION['teacher_id'];
$submission_id = $_GET['id'] ?? null;

if (!$submission_id) {
    echo json_encode(['success' => false, 'message' => 'Submission ID required']);
    exit();
}

try {
    // Get submission with assignment and student details
    $stmt = $pdo->prepare("
        SELECT 
            sub.*,
            a.title as assignment_title,
            a.assignment_type,
            a.total_marks,
            a.due_date,
            c.course_code,
            c.course_name,
            s.student_id as student_number,
            s.full_name as student_name,
            s.email as student_email,
            e.section
        FROM assignment_submissions sub
        JOIN assignments a ON sub.assignment_id = a.assignment_id
        JOIN courses c ON a.course_id = c.course_id
        JOIN students s ON sub.student_id = s.student_id
        JOIN enrollments e ON sub.enrollment_id = e.enrollment_id
        WHERE sub.submission_id = ? AND a.teacher_id = ?
    ");
    $stmt->execute([$submission_id, $teacher_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        echo json_encode(['success' => false, 'message' => 'Submission not found or access denied']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $submission
    ]);
    
} catch (PDOException $e) {
    error_log("Get submission error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
