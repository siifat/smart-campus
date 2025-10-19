<?php
/**
 * View Course Material API
 * Increments view count and returns material details or redirects
 */
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['teacher_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../../config/database.php';

$teacher_id = $_SESSION['teacher_id'];
$content_id = $_GET['content_id'] ?? null;

if (!$content_id) {
    echo json_encode(['success' => false, 'message' => 'Material ID is required']);
    exit;
}

try {
    // Get material details and verify access
    $stmt = $pdo->prepare("
        SELECT cm.*, c.course_code, c.course_name 
        FROM course_materials cm
        JOIN courses c ON cm.course_id = c.course_id
        JOIN enrollments e ON cm.course_id = e.course_id 
            AND cm.section = e.section 
            AND e.teacher_id = cm.teacher_id
        WHERE cm.content_id = ? AND cm.teacher_id = ?
    ");
    $stmt->execute([$content_id, $teacher_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$material) {
        echo json_encode(['success' => false, 'message' => 'Material not found or access denied']);
        exit;
    }
    
    // Increment view count
    $update_stmt = $pdo->prepare("UPDATE course_materials SET view_count = view_count + 1 WHERE content_id = ?");
    $update_stmt->execute([$content_id]);
    
    // Return material details with incremented view count
    $material['view_count']++;
    
    echo json_encode([
        'success' => true,
        'material' => $material
    ]);
    
} catch (PDOException $e) {
    error_log("View Material Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to view material']);
}
?>
