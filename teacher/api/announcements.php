<?php
/**
 * Teacher Announcements API
 * Handle CRUD operations for teacher announcements
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['teacher_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once('../../config/database.php');
require_once('../../includes/notification_helper.php');

$teacher_id = $_SESSION['teacher_id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            createAnnouncement($conn, $teacher_id);
            break;
            
        case 'toggle_pin':
            togglePin($conn, $teacher_id);
            break;
            
        case 'delete':
            deleteAnnouncement($conn, $teacher_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function createAnnouncement($conn, $teacher_id) {
    // Validate required fields
    if (empty($_POST['title']) || empty($_POST['content']) || empty($_POST['announcement_type'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $announcement_type = $_POST['announcement_type'];
    $target_audience = $_POST['target_audience'] ?? 'all';
    $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
    $section = !empty($_POST['section']) ? trim($_POST['section']) : null;
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    
    // Get current trimester
    $trimester_query = "SELECT trimester_id FROM trimesters WHERE is_current = 1 LIMIT 1";
    $trimester_result = $conn->query($trimester_query);
    $current_trimester = $trimester_result->fetch_assoc();
    $trimester_id = $current_trimester['trimester_id'] ?? 1;
    
    // Handle file upload
    $file_path = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/announcements/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $file_name = 'announcement_' . $teacher_id . '_' . time() . '.' . $file_extension;
        $file_path = 'uploads/announcements/' . $file_name;
        
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $file_name)) {
            $file_path = null;
        }
    }
    
    // Insert announcement
    $insert_query = "INSERT INTO teacher_announcements 
        (teacher_id, course_id, trimester_id, section, title, content, announcement_type, file_path, is_pinned) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param('iiisssssi', $teacher_id, $course_id, $trimester_id, $section, $title, $content, $announcement_type, $file_path, $is_pinned);
    
    if ($stmt->execute()) {
        $announcement_id = $conn->insert_id;
        
        // Notify students
        notifyStudentsAboutAnnouncement($conn, $announcement_id, $teacher_id, $course_id, $section, $trimester_id, $title, $announcement_type);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Announcement created and students notified successfully!',
            'announcement_id' => $announcement_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create announcement']);
    }
    
    $stmt->close();
}

function notifyStudentsAboutAnnouncement($conn, $announcement_id, $teacher_id, $course_id, $section, $trimester_id, $title, $type) {
    // Get teacher name
    $teacher_query = "SELECT full_name, initial FROM teachers WHERE teacher_id = ?";
    $teacher_stmt = $conn->prepare($teacher_query);
    $teacher_stmt->bind_param('i', $teacher_id);
    $teacher_stmt->execute();
    $teacher = $teacher_stmt->get_result()->fetch_assoc();
    $teacher_name = $teacher['initial'] ?? $teacher['full_name'];
    $teacher_stmt->close();
    
    // Build query based on target audience
    if ($course_id) {
        // Course-specific announcement
        $students_query = "
            SELECT DISTINCT s.student_id, c.course_code, c.course_name
            FROM enrollments e
            JOIN students s ON e.student_id = s.student_id
            JOIN courses c ON e.course_id = c.course_id
            WHERE e.course_id = ? 
            AND e.trimester_id = ?
            AND e.teacher_id = ?
            AND e.status = 'enrolled'
            " . ($section ? "AND e.section = ?" : "");
        
        $students_stmt = $conn->prepare($students_query);
        if ($section) {
            $students_stmt->bind_param('iiis', $course_id, $trimester_id, $teacher_id, $section);
        } else {
            $students_stmt->bind_param('iii', $course_id, $trimester_id, $teacher_id);
        }
    } else {
        // All students announcement
        $students_query = "
            SELECT DISTINCT s.student_id
            FROM enrollments e
            JOIN students s ON e.student_id = s.student_id
            WHERE e.teacher_id = ?
            AND e.trimester_id = ?
            AND e.status = 'enrolled'
        ";
        
        $students_stmt = $conn->prepare($students_query);
        $students_stmt->bind_param('ii', $teacher_id, $trimester_id);
    }
    
    $students_stmt->execute();
    $students = $students_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $students_stmt->close();
    
    // Create notifications for each student
    $notification_title = "New Announcement from " . $teacher_name;
    $notification_message = $title;
    $notification_link = null; // Could link to announcement detail page if created
    
    $priority = ($type === 'urgent') ? 'high' : (($type === 'important') ? 'normal' : 'low');
    
    $count = 0;
    foreach ($students as $student) {
        if (createStudentNotification($conn, $student['student_id'], 'announcement', $notification_title, $notification_message, $notification_link, $priority)) {
            $count++;
        }
    }
    
    return $count;
}

function togglePin($conn, $teacher_id) {
    if (empty($_POST['announcement_id'])) {
        echo json_encode(['success' => false, 'message' => 'Announcement ID required']);
        return;
    }
    
    $announcement_id = intval($_POST['announcement_id']);
    
    // Verify ownership
    $check_query = "SELECT is_pinned FROM teacher_announcements WHERE announcement_id = ? AND teacher_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('ii', $announcement_id, $teacher_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Announcement not found']);
        $check_stmt->close();
        return;
    }
    
    $announcement = $result->fetch_assoc();
    $new_pinned_status = $announcement['is_pinned'] ? 0 : 1;
    $check_stmt->close();
    
    // Toggle pin status
    $update_query = "UPDATE teacher_announcements SET is_pinned = ? WHERE announcement_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('ii', $new_pinned_status, $announcement_id);
    
    if ($update_stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => $new_pinned_status ? 'Announcement pinned' : 'Announcement unpinned',
            'is_pinned' => $new_pinned_status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update pin status']);
    }
    
    $update_stmt->close();
}

function deleteAnnouncement($conn, $teacher_id) {
    if (empty($_POST['announcement_id'])) {
        echo json_encode(['success' => false, 'message' => 'Announcement ID required']);
        return;
    }
    
    $announcement_id = intval($_POST['announcement_id']);
    
    // Verify ownership and get file path
    $check_query = "SELECT file_path FROM teacher_announcements WHERE announcement_id = ? AND teacher_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('ii', $announcement_id, $teacher_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Announcement not found']);
        $check_stmt->close();
        return;
    }
    
    $announcement = $result->fetch_assoc();
    $file_path = $announcement['file_path'];
    $check_stmt->close();
    
    // Delete announcement
    $delete_query = "DELETE FROM teacher_announcements WHERE announcement_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param('i', $announcement_id);
    
    if ($delete_stmt->execute()) {
        // Delete file if exists
        if ($file_path && file_exists('../../' . $file_path)) {
            unlink('../../' . $file_path);
        }
        
        // Delete related announcement reads
        $delete_reads_query = "DELETE FROM announcement_reads WHERE announcement_id = ?";
        $reads_stmt = $conn->prepare($delete_reads_query);
        $reads_stmt->bind_param('i', $announcement_id);
        $reads_stmt->execute();
        $reads_stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete announcement']);
    }
    
    $delete_stmt->close();
}
?>
