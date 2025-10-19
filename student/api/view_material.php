<?php
/**
 * Student View Material API
 * Increments view count and returns material details
 */
session_start();

// Check authentication
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once('../../config/database.php');

$student_id = $_SESSION['student_id'];
$content_id = $_GET['content_id'] ?? null;

if (!$content_id) {
    echo json_encode(['success' => false, 'message' => 'Material ID is required']);
    exit;
}

try {
    // Get current trimester
    $current_trimester = $conn->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $current_trimester_id = $current_trimester['trimester_id'] ?? 1;
    
    // Get material details and verify student access
    $stmt = $conn->prepare("
        SELECT cm.*, c.course_code, c.course_name 
        FROM course_materials cm
        JOIN courses c ON cm.course_id = c.course_id
        JOIN enrollments e ON cm.course_id = e.course_id 
            AND cm.trimester_id = e.trimester_id
            AND (cm.section IS NULL OR cm.section = e.section)
        WHERE cm.content_id = ? 
            AND e.student_id = ?
            AND e.status = 'enrolled'
            AND cm.is_published = 1
    ");
    $stmt->bind_param('is', $content_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $material = $result->fetch_assoc();
    $stmt->close();
    
    if (!$material) {
        echo json_encode(['success' => false, 'message' => 'Material not found or access denied']);
        exit;
    }
    
    // Increment view count
    $update_stmt = $conn->prepare("UPDATE course_materials SET view_count = view_count + 1 WHERE content_id = ?");
    $update_stmt->bind_param('i', $content_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Return material details with incremented view count
    $material['view_count']++;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'material' => $material
    ]);
    
} catch (Exception $e) {
    error_log("View Material Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to view material']);
}
?>
