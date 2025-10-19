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
$trimester_id = $data['trimester_id'] ?? null;

// Validation
if (!$course_id || !$section || !$trimester_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Remove teacher assignment (set to NULL)
    $update_stmt = $conn->prepare("
        UPDATE enrollments 
        SET teacher_id = NULL 
        WHERE course_id = ? 
          AND section = ? 
          AND trimester_id = ?
          AND status = 'enrolled'
    ");
    $update_stmt->bind_param('isi', $course_id, $section, $trimester_id);
    $update_stmt->execute();
    
    $affected_rows = $update_stmt->affected_rows;
    
    if ($affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Teacher removed from section ({$affected_rows} student enrollments updated)"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No teacher assignment found for this section'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Remove teacher error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
