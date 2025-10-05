<?php
/**
 * System Reset & Danger Zone Operations
 * Handle dangerous operations like data deletion and system reset
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../config/database.php');

$results = [
    'success' => false,
    'timestamp' => date('Y-m-d H:i:s'),
    'action' => '',
    'message' => ''
];

$action = $_POST['action'] ?? '';
$confirmation = $_POST['confirmation'] ?? '';

try {
    switch ($action) {
        case 'reset_system':
            // Reset system to defaults but keep structure
            if ($confirmation !== 'RESET_SYSTEM') {
                throw new Exception('Invalid confirmation code');
            }
            
            $deleted_counts = [];
            
            // Clear all student data (in correct order to avoid FK constraints)
            $deleted_counts['student_activities'] = $conn->query("DELETE FROM student_activities") ? $conn->affected_rows : 0;
            $deleted_counts['student_todos'] = $conn->query("DELETE FROM student_todos") ? $conn->affected_rows : 0;
            $deleted_counts['focus_sessions'] = $conn->query("DELETE FROM focus_sessions") ? $conn->affected_rows : 0;
            $deleted_counts['student_achievements'] = $conn->query("DELETE FROM student_achievements") ? $conn->affected_rows : 0;
            $deleted_counts['resource_views'] = $conn->query("DELETE FROM resource_views") ? $conn->affected_rows : 0;
            $deleted_counts['resource_comments'] = $conn->query("DELETE FROM resource_comments") ? $conn->affected_rows : 0;
            $deleted_counts['resource_likes'] = $conn->query("DELETE FROM resource_likes") ? $conn->affected_rows : 0;
            $deleted_counts['resource_bookmarks'] = $conn->query("DELETE FROM resource_bookmarks") ? $conn->affected_rows : 0;
            $deleted_counts['uploaded_resources'] = $conn->query("DELETE FROM uploaded_resources") ? $conn->affected_rows : 0;
            $deleted_counts['student_points'] = $conn->query("DELETE FROM student_points") ? $conn->affected_rows : 0;
            $deleted_counts['question_solutions'] = $conn->query("DELETE FROM question_solutions") ? $conn->affected_rows : 0;
            $deleted_counts['notes'] = $conn->query("DELETE FROM notes") ? $conn->affected_rows : 0;
            $deleted_counts['class_routine'] = $conn->query("DELETE FROM class_routine") ? $conn->affected_rows : 0;
            $deleted_counts['attendance'] = $conn->query("DELETE FROM attendance") ? $conn->affected_rows : 0;
            $deleted_counts['grades'] = $conn->query("DELETE FROM grades") ? $conn->affected_rows : 0;
            $deleted_counts['enrollments'] = $conn->query("DELETE FROM enrollments") ? $conn->affected_rows : 0;
            $deleted_counts['students'] = $conn->query("DELETE FROM students") ? $conn->affected_rows : 0;
            
            // Clear course data
            $deleted_counts['courses'] = $conn->query("DELETE FROM courses") ? $conn->affected_rows : 0;
            
            // Clear other data
            $deleted_counts['notices'] = $conn->query("DELETE FROM notices") ? $conn->affected_rows : 0;
            
            // Don't delete ALL activity logs, just student-related ones
            $conn->query("DELETE FROM activity_logs WHERE action_type LIKE '%student%' OR action_type LIKE '%course%'");
            $conn->query("DELETE FROM admin_notifications WHERE category = 'user_action'");
            
            // Reset auto-increment values
            $conn->query("ALTER TABLE students AUTO_INCREMENT = 1");
            $conn->query("ALTER TABLE enrollments AUTO_INCREMENT = 1");
            $conn->query("ALTER TABLE courses AUTO_INCREMENT = 1");
            $conn->query("ALTER TABLE student_todos AUTO_INCREMENT = 1");
            $conn->query("ALTER TABLE student_activities AUTO_INCREMENT = 1");
            $conn->query("ALTER TABLE uploaded_resources AUTO_INCREMENT = 1");
            
            $total_deleted = array_sum($deleted_counts);
            
            $results['success'] = true;
            $results['action'] = 'reset_system';
            $results['message'] = "System reset successfully! Total {$total_deleted} record(s) deleted.";
            $results['details'] = $deleted_counts;
            $results['tables_reset'] = count($deleted_counts);
            
            // Log the action
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $admin_username = $_SESSION['admin_username'] ?? 'Unknown';
            $conn->query("INSERT INTO activity_logs (admin_id, action_type, description) 
                         VALUES ($admin_id, 'system_reset', 'System reset performed by {$admin_username} - {$total_deleted} records deleted')");
            break;
            
        case 'delete_all_data':
            // EXTREME DANGER - Delete everything except admin users
            if ($confirmation !== 'DELETE_EVERYTHING') {
                throw new Exception('Invalid confirmation code');
            }
            
            // Log the action BEFORE deletion
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $admin_username = $_SESSION['admin_username'] ?? 'Unknown';
            $conn->query("INSERT INTO activity_logs (admin_id, action_type, description) 
                         VALUES ($admin_id, 'delete_all_data', '⚠️ DANGER: ALL DATA DELETION initiated by {$admin_username}')");
            
            // Disable foreign key checks temporarily
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            
            // Get all tables except admin-related and system tables
            $tables_query = "SELECT table_name 
                            FROM information_schema.tables 
                            WHERE table_schema = 'uiu_smart_campus' 
                            AND table_name NOT IN ('admin_users', 'admin_sessions', 'system_settings', 'backup_history')
                            ORDER BY table_name";
            $tables_result = $conn->query($tables_query);
            
            $deleted_tables = [];
            $failed_tables = [];
            $total_records_deleted = 0;
            
            while ($row = $tables_result->fetch_assoc()) {
                $table = $row['table_name'];
                
                // Count records before deletion
                $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
                $count = $count_result->fetch_assoc()['count'];
                
                if ($conn->query("TRUNCATE TABLE `$table`")) {
                    $deleted_tables[] = [
                        'table' => $table,
                        'records_deleted' => $count
                    ];
                    $total_records_deleted += $count;
                } else {
                    $failed_tables[] = [
                        'table' => $table,
                        'error' => $conn->error
                    ];
                }
            }
            
            // Re-enable foreign key checks
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            
            $results['success'] = true;
            $results['action'] = 'delete_all_data';
            $results['message'] = "⚠️ ALL DATA DELETED! {$total_records_deleted} total record(s) purged from " . count($deleted_tables) . " table(s).";
            $results['tables_cleared'] = $deleted_tables;
            $results['total_tables'] = count($deleted_tables);
            $results['total_records_deleted'] = $total_records_deleted;
            
            if (!empty($failed_tables)) {
                $results['failed_tables'] = $failed_tables;
                $results['message'] .= " Note: " . count($failed_tables) . " table(s) failed to clear.";
            }
            
            // Final log entry
            $conn->query("INSERT INTO activity_logs (admin_id, action_type, description) 
                         VALUES ($admin_id, 'delete_all_data', '☠️ COMPLETED: {$total_records_deleted} records deleted from {$results['total_tables']} tables by {$admin_username}')");
            break;
            
        case 'delete_student_data':
            // Delete only student-related data
            if ($confirmation !== 'DELETE_STUDENTS') {
                throw new Exception('Invalid confirmation code');
            }
            
            $conn->query("DELETE FROM student_activities");
            $conn->query("DELETE FROM student_todos");
            $conn->query("DELETE FROM focus_sessions");
            $conn->query("DELETE FROM student_achievements");
            $conn->query("DELETE FROM resource_views");
            $conn->query("DELETE FROM resource_comments");
            $conn->query("DELETE FROM resource_likes");
            $conn->query("DELETE FROM resource_bookmarks");
            $conn->query("DELETE FROM uploaded_resources");
            $conn->query("DELETE FROM student_points");
            $conn->query("DELETE FROM question_solutions");
            $conn->query("DELETE FROM notes");
            $conn->query("DELETE FROM class_routine");
            $conn->query("DELETE FROM attendance");
            $conn->query("DELETE FROM grades");
            $conn->query("DELETE FROM enrollments");
            $conn->query("DELETE FROM students");
            
            $results['success'] = true;
            $results['action'] = 'delete_student_data';
            $results['message'] = 'All student data deleted successfully.';
            
            // Log the action
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $conn->query("INSERT INTO activity_logs (admin_id, action_type, description) 
                         VALUES ($admin_id, 'delete_student_data', 'All student data deleted')");
            break;
            
        case 'clear_old_logs':
            // Clear activity logs older than specified days
            $days = intval($_POST['days'] ?? 30);
            
            $query = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)";
            if ($conn->query($query)) {
                $affected = $conn->affected_rows;
                $results['success'] = true;
                $results['action'] = 'clear_old_logs';
                $results['message'] = "Deleted {$affected} activity log(s) older than {$days} days.";
            }
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    $results['success'] = false;
    $results['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
