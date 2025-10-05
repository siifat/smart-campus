<?php
/**
 * To-Do API - Handle CRUD operations for student to-do items
 */
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../config/database.php');

$student_id = $_SESSION['student_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Get all to-do items for this student
            $stmt = $conn->prepare("
                SELECT todo_id, task, completed, created_at, updated_at
                FROM student_todos
                WHERE student_id = ?
                ORDER BY completed ASC, created_at DESC
            ");
            $stmt->bind_param('s', $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $todos = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'todos' => $todos]);
            break;
            
        case 'add':
            // Add new to-do item
            $task = trim($_POST['task'] ?? '');
            
            if (empty($task)) {
                echo json_encode(['success' => false, 'message' => 'Task cannot be empty']);
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO student_todos (student_id, task, completed)
                VALUES (?, ?, 0)
            ");
            $stmt->bind_param('ss', $student_id, $task);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Task added successfully',
                'todo_id' => $conn->insert_id
            ]);
            break;
            
        case 'toggle':
            // Toggle task completion
            $todo_id = intval($_POST['todo_id'] ?? $_GET['todo_id'] ?? 0);
            
            if ($todo_id == 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid todo_id']);
                exit;
            }
            
            $stmt = $conn->prepare("
                UPDATE student_todos
                SET completed = NOT completed,
                    updated_at = CURRENT_TIMESTAMP
                WHERE todo_id = ? AND student_id = ?
            ");
            $stmt->bind_param('is', $todo_id, $student_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Task updated', 'affected_rows' => $stmt->affected_rows]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Task not found or not updated']);
            }
            break;
            
        case 'delete':
            // Delete task
            $todo_id = intval($_POST['todo_id'] ?? $_GET['todo_id'] ?? 0);
            
            if ($todo_id == 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid todo_id']);
                exit;
            }
            
            $stmt = $conn->prepare("
                DELETE FROM student_todos
                WHERE todo_id = ? AND student_id = ?
            ");
            $stmt->bind_param('is', $todo_id, $student_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Task deleted', 'affected_rows' => $stmt->affected_rows]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Task not found or not deleted']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
