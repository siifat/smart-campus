<?php
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['teacher_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);
$teacher_id = $_SESSION['teacher_id'];
$assignment_id = $data['assignment_id'] ?? null;

if (!$assignment_id) {
    echo json_encode(['success' => false, 'message' => 'Assignment ID required']);
    exit();
}

try {
    // Verify ownership and get assignment details
    $stmt = $pdo->prepare("
        SELECT a.*, c.course_code 
        FROM assignments a
        JOIN courses c ON a.course_id = c.course_id
        WHERE a.assignment_id = ? AND a.teacher_id = ?
    ");
    $stmt->execute([$assignment_id, $teacher_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
        exit();
    }
    
    // Publish the assignment
    $update_stmt = $pdo->prepare("
        UPDATE assignments 
        SET is_published = 1 
        WHERE assignment_id = ?
    ");
    $update_stmt->execute([$assignment_id]);
    
    // Create notifications for enrolled students
    $notif_stmt = $pdo->prepare("
        INSERT INTO student_notifications (student_id, notification_type, title, message, link)
        SELECT DISTINCT e.student_id, 'assignment', 'New Assignment Posted', 
               CONCAT('New assignment \"', ?, '\" has been posted for ', ?),
               CONCAT('/student/assignment_detail.php?id=', ?)
        FROM enrollments e
        WHERE e.course_id = ? AND e.trimester_id = ? AND e.status = 'enrolled'
          AND (? IS NULL OR e.section = ?)
    ");
    $notif_stmt->execute([
        $assignment['title'], 
        $assignment['course_code'],
        $assignment_id,
        $assignment['course_id'], 
        $assignment['trimester_id'],
        $assignment['section'],
        $assignment['section']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment published successfully. Students have been notified.'
    ]);
    
} catch (PDOException $e) {
    error_log("Publish assignment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
