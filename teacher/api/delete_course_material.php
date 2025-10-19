<?php
/**
 * Delete Course Material API
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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $content_id = $input['content_id'] ?? null;
    
    if (!$content_id) {
        echo json_encode(['success' => false, 'message' => 'Material ID is required']);
        exit;
    }
    
    // Get material details to verify ownership and delete file
    $stmt = $pdo->prepare("
        SELECT file_path FROM course_materials 
        WHERE content_id = ? AND teacher_id = ?
    ");
    $stmt->execute([$content_id, $teacher_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$material) {
        echo json_encode(['success' => false, 'message' => 'Material not found or access denied']);
        exit;
    }
    
    // Delete the file if it exists
    if ($material['file_path'] && file_exists('../../' . $material['file_path'])) {
        unlink('../../' . $material['file_path']);
    }
    
    // Delete from database
    $delete_stmt = $pdo->prepare("DELETE FROM course_materials WHERE content_id = ? AND teacher_id = ?");
    $delete_stmt->execute([$content_id, $teacher_id]);
    
    echo json_encode(['success' => true, 'message' => 'Material deleted successfully']);
    
} catch (PDOException $e) {
    error_log("Delete Material Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete material']);
}
?>
