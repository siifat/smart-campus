<?php
/**
 * Student Activity API - Handle activity logging and retrieval
 * Database: uiu_smart_campus
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
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Get recent activities for the student
            $limit = intval($_GET['limit'] ?? 10);
            
            $stmt = $conn->prepare("
                SELECT 
                    sa.*,
                    c.course_code,
                    c.course_name
                FROM student_activities sa
                LEFT JOIN courses c ON sa.related_course_id = c.course_id
                WHERE sa.student_id = ?
                ORDER BY sa.activity_date DESC
                LIMIT ?
            ");
            $stmt->bind_param('si', $student_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $activities = $result->fetch_all(MYSQLI_ASSOC);
            
            // Format dates
            foreach ($activities as &$activity) {
                $activity['formatted_date'] = formatActivityDate($activity['activity_date']);
                $activity['icon'] = getActivityIcon($activity['activity_type'], $activity['icon_class']);
                $activity['color'] = getActivityColor($activity['activity_type']);
            }
            
            echo json_encode([
                'success' => true,
                'activities' => $activities
            ]);
            break;
            
        case 'add':
            // Log a new activity
            $activity_type = $_POST['activity_type'] ?? '';
            $activity_title = $_POST['activity_title'] ?? '';
            $activity_description = $_POST['activity_description'] ?? null;
            $related_course_id = !empty($_POST['related_course_id']) ? intval($_POST['related_course_id']) : null;
            $related_id = !empty($_POST['related_id']) ? intval($_POST['related_id']) : null;
            $icon_class = $_POST['icon_class'] ?? null;
            
            if (empty($activity_type) || empty($activity_title)) {
                echo json_encode(['success' => false, 'message' => 'Activity type and title are required']);
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO student_activities 
                (student_id, activity_type, activity_title, activity_description, 
                 related_course_id, related_id, icon_class)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssssiis', 
                $student_id, 
                $activity_type, 
                $activity_title, 
                $activity_description,
                $related_course_id,
                $related_id,
                $icon_class
            );
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Activity logged successfully',
                    'activity_id' => $conn->insert_id
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to log activity']);
            }
            break;
            
        case 'delete':
            // Delete an activity (optional feature)
            $activity_id = intval($_GET['activity_id'] ?? 0);
            
            $stmt = $conn->prepare("
                DELETE FROM student_activities 
                WHERE activity_id = ? AND student_id = ?
            ");
            $stmt->bind_param('is', $activity_id, $student_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Activity deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete activity']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Helper function to format activity date
function formatActivityDate($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

// Helper function to get icon based on activity type
function getActivityIcon($type, $custom_icon = null) {
    if ($custom_icon) {
        return $custom_icon;
    }
    
    $icons = [
        'login' => 'fa-sign-in-alt',
        'course_view' => 'fa-book-open',
        'assignment_submit' => 'fa-file-upload',
        'note_upload' => 'fa-sticky-note',
        'question_post' => 'fa-question-circle',
        'quiz_complete' => 'fa-clipboard-check',
        'grade_received' => 'fa-chart-line',
        'attendance_marked' => 'fa-user-check',
        'todo_complete' => 'fa-check-circle',
        'study_session' => 'fa-graduation-cap',
        'other' => 'fa-circle'
    ];
    
    return $icons[$type] ?? 'fa-circle';
}

// Helper function to get color based on activity type
function getActivityColor($type) {
    $colors = [
        'login' => '#3b82f6',
        'course_view' => '#8b5cf6',
        'assignment_submit' => '#10b981',
        'note_upload' => '#f59e0b',
        'question_post' => '#ef4444',
        'quiz_complete' => '#06b6d4',
        'grade_received' => '#10b981',
        'attendance_marked' => '#8b5cf6',
        'todo_complete' => '#10b981',
        'study_session' => '#f68b1f',
        'other' => '#6b7280'
    ];
    
    return $colors[$type] ?? '#6b7280';
}
?>
