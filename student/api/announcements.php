<?php
/**
 * Student Announcements API
 * Handle announcement read tracking
 */
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once('../../config/database.php');

$student_id = $_SESSION['student_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'mark_read':
        markAnnouncementAsRead($conn, $student_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();

/**
 * Mark announcement as read
 */
function markAnnouncementAsRead($conn, $student_id) {
    if (!isset($_POST['announcement_id'])) {
        echo json_encode(['success' => false, 'message' => 'Announcement ID required']);
        return;
    }
    
    $announcement_id = $_POST['announcement_id'];
    
    // Check if announcement exists and student has access to it
    $check_query = "
        SELECT ta.announcement_id, ta.trimester_id, ta.course_id, ta.section
        FROM teacher_announcements ta
        WHERE ta.announcement_id = ?
    ";
    
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $announcement_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Announcement not found']);
        $check_stmt->close();
        return;
    }
    
    $announcement = $result->fetch_assoc();
    $check_stmt->close();
    
    // Verify student has access
    if ($announcement['course_id']) {
        $access_query = "
            SELECT 1 FROM enrollments
            WHERE student_id = ?
            AND course_id = ?
            AND trimester_id = ?
            AND (? IS NULL OR section = ?)
            AND status = 'enrolled'
        ";
        
        $access_stmt = $conn->prepare($access_query);
        $access_stmt->bind_param('siiss', 
            $student_id, 
            $announcement['course_id'], 
            $announcement['trimester_id'],
            $announcement['section'],
            $announcement['section']
        );
        $access_stmt->execute();
        $access_result = $access_stmt->get_result();
        
        if ($access_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            $access_stmt->close();
            return;
        }
        $access_stmt->close();
    }
    
    // Insert or update read record
    $insert_query = "
        INSERT INTO announcement_reads (announcement_id, student_id, read_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE read_at = NOW()
    ";
    
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param('is', $announcement_id, $student_id);
    
    if ($insert_stmt->execute()) {
        // Update view count
        $update_query = "
            UPDATE teacher_announcements
            SET view_count = (
                SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = ?
            )
            WHERE announcement_id = ?
        ";
        
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('ii', $announcement_id, $announcement_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark as read']);
    }
    
    $insert_stmt->close();
}
?>
