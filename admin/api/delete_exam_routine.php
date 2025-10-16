<?php
/**
 * Admin API - Delete Exam Routine
 * Deletes all entries for a specific department/trimester/exam_type combination
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = require_once('../../config/database.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['department_id']) || !isset($input['trimester_id']) || !isset($input['exam_type'])) {
            throw new Exception('Missing required parameters');
        }
        
        $department_id = intval($input['department_id']);
        $trimester_id = intval($input['trimester_id']);
        $exam_type = $input['exam_type'];
        
        // Delete the routine
        $deleteStmt = $conn->prepare("DELETE FROM exam_routines WHERE department_id = ? AND trimester_id = ? AND exam_type = ?");
        $deleteStmt->bind_param('iis', $department_id, $trimester_id, $exam_type);
        
        if ($deleteStmt->execute()) {
            $deleted = $deleteStmt->affected_rows;
            echo json_encode([
                'success' => true,
                'message' => "Successfully deleted $deleted exam schedule entries"
            ]);
        } else {
            throw new Exception('Failed to delete exam routine');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
