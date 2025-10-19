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
$submission_id = $_POST['submission_id'] ?? null;
$marks_obtained = $_POST['marks_obtained'] ?? null;
$feedback = $_POST['feedback'] ?? '';

if (!$submission_id || $marks_obtained === null) {
    echo json_encode(['success' => false, 'message' => 'Submission ID and marks are required']);
    exit();
}

try {
    // Verify ownership and get submission details
    $stmt = $pdo->prepare("
        SELECT sub.*, a.total_marks, a.title as assignment_title, s.full_name as student_name
        FROM assignment_submissions sub
        JOIN assignments a ON sub.assignment_id = a.assignment_id
        JOIN students s ON sub.student_id = s.student_id
        WHERE sub.submission_id = ? AND a.teacher_id = ?
    ");
    $stmt->execute([$submission_id, $teacher_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        echo json_encode(['success' => false, 'message' => 'Submission not found or access denied']);
        exit();
    }
    
    // Validate marks
    if ($marks_obtained < 0 || $marks_obtained > $submission['total_marks']) {
        $max_marks = $submission['total_marks'];
        echo json_encode(['success' => false, 'message' => "Marks must be between 0 and $max_marks"]);
        exit();
    }
    
    // Update submission with grade
    $update_stmt = $pdo->prepare("UPDATE assignment_submissions SET marks_obtained = ?, feedback = ?, status = 'graded', graded_at = NOW() WHERE submission_id = ?");
    $update_stmt->execute([$marks_obtained, $feedback, $submission_id]);
    
    // Create notification for student
    $notif_stmt = $pdo->prepare("
        INSERT INTO student_notifications (student_id, notification_type, title, message, link)
        VALUES (?, 'grade', 'Assignment Graded', ?, ?)
    ");
    $notif_message = "Your submission for \"" . $submission['assignment_title'] . "\" has been graded. Score: " . $marks_obtained . "/" . $submission['total_marks'];
    $notif_link = "/student/assignment_detail.php?id=" . $submission['assignment_id'];
    $notif_stmt->execute([$submission['student_id'], $notif_message, $notif_link]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Grade submitted successfully. Student has been notified.'
    ]);
    
} catch (PDOException $e) {
    error_log("Grade submission error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
