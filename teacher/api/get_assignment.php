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
$assignment_id = $_GET['id'] ?? null;

if (!$assignment_id) {
    echo json_encode(['success' => false, 'message' => 'Assignment ID required']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM assignments 
        WHERE assignment_id = ? AND teacher_id = ?
    ");
    $stmt->execute([$assignment_id, $teacher_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($assignment) {
        echo json_encode([
            'success' => true,
            'data' => $assignment
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Assignment not found or access denied'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Get assignment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
?>
