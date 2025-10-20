<?php
/**
 * Student Notifications API
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../config/database.php');

$student_id = $_SESSION['student_id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_recent':
            $limit = intval($_GET['limit'] ?? 10);
            $stmt = $conn->prepare("
                SELECT * FROM student_notifications 
                WHERE student_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->bind_param('si', $student_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            
            // Get unread count
            $count_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM student_notifications WHERE student_id = ? AND is_read = 0");
            $count_stmt->bind_param('s', $student_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $unread_count = $count_result->fetch_assoc()['unread_count'];
            
            echo json_encode([
                'success' => true, 
                'notifications' => $notifications,
                'unread_count' => $unread_count
            ]);
            break;
            
        case 'mark_read':
            $notification_id = intval($_POST['notification_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE student_notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND student_id = ?");
            $stmt->bind_param('is', $notification_id, $student_id);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            break;
            
        case 'mark_all_read':
            $stmt = $conn->prepare("UPDATE student_notifications SET is_read = 1, read_at = NOW() WHERE student_id = ? AND is_read = 0");
            $stmt->bind_param('s', $student_id);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('Notifications API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
