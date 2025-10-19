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
    // Check if assignment belongs to teacher
    $stmt = $pdo->prepare("
        SELECT file_path FROM assignments 
        WHERE assignment_id = ? AND teacher_id = ?
    ");
    $stmt->execute([$assignment_id, $teacher_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
        exit();
    }
    
    // Check for existing submissions
    $check_stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM assignment_submissions 
        WHERE assignment_id = ?
    ");
    $check_stmt->execute([$assignment_id]);
    $submission_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($submission_count > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete assignment with existing submissions. Consider unpublishing instead.'
        ]);
        exit();
    }
    
    // Delete the assignment
    $delete_stmt = $pdo->prepare("DELETE FROM assignments WHERE assignment_id = ?");
    $delete_stmt->execute([$assignment_id]);
    
    // Delete file if exists
    if ($assignment['file_path'] && file_exists('../../' . $assignment['file_path'])) {
        unlink('../../' . $assignment['file_path']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment deleted successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Delete assignment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
