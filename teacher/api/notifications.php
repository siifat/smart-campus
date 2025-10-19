<?php
/**
 * Teacher Notifications API
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['teacher_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../config/database.php');

$teacher_id = $_SESSION['teacher_id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_recent':
            $limit = intval($_GET['limit'] ?? 10);
            $stmt = $conn->prepare("
                SELECT * FROM teacher_notifications 
                WHERE teacher_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->bind_param('ii', $teacher_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            
            echo json_encode(['success' => true, 'notifications' => $notifications]);
            break;
            
        case 'mark_read':
            $notification_id = intval($_POST['notification_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE teacher_notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND teacher_id = ?");
            $stmt->bind_param('ii', $notification_id, $teacher_id);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            break;
            
        case 'mark_all_read':
            $stmt = $conn->prepare("UPDATE teacher_notifications SET is_read = 1, read_at = NOW() WHERE teacher_id = ? AND is_read = 0");
            $stmt->bind_param('i', $teacher_id);
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
