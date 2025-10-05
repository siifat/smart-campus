<?php
/**
 * Clear System Cache
 * Remove temporary files, session data, and cached information
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../config/database.php');

$results = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'actions' => [],
    'errors' => []
];

try {
    // 1. Clear expired admin sessions
    $query = "UPDATE admin_sessions SET is_active = FALSE WHERE expires_at < NOW()";
    if ($conn->query($query)) {
        $affected = $conn->affected_rows;
        $results['actions'][] = "Cleared {$affected} expired admin session(s)";
    }
    
    // 2. Delete old activity logs (older than 90 days)
    $query = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    if ($conn->query($query)) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            $results['actions'][] = "Deleted {$affected} old activity log(s) (>90 days)";
        } else {
            $results['actions'][] = "No old activity logs to delete";
        }
    }
    
    // 3. Clean up orphaned resource views (no student_id and older than 30 days)
    $query = "DELETE FROM resource_views WHERE student_id IS NULL AND viewed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    if ($conn->query($query)) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            $results['actions'][] = "Cleaned up {$affected} anonymous resource view(s)";
        }
    }
    
    // 4. Clear read admin notifications (older than 60 days)
    $query = "DELETE FROM admin_notifications WHERE is_read = TRUE AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)";
    if ($conn->query($query)) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            $results['actions'][] = "Deleted {$affected} old read notification(s)";
        }
    }
    
    // 5. Clear failed email queue items (older than 30 days)
    $query = "DELETE FROM email_queue WHERE status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    if ($conn->query($query)) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            $results['actions'][] = "Cleared {$affected} failed email(s) from queue";
        }
    }
    
    // 6. Clear sent emails (older than 30 days)
    $query = "DELETE FROM email_queue WHERE status = 'sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    if ($conn->query($query)) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            $results['actions'][] = "Cleared {$affected} sent email(s) from queue";
        }
    }
    
    // 7. Optimize all tables
    $tables = ['students', 'teachers', 'courses', 'enrollments', 'departments', 'programs', 
               'trimesters', 'notes', 'question_solutions', 'activity_logs', 'admin_sessions',
               'uploaded_resources', 'student_todos', 'focus_sessions'];
    
    $optimized = 0;
    foreach ($tables as $table) {
        if ($conn->query("OPTIMIZE TABLE $table")) {
            $optimized++;
        }
    }
    $results['actions'][] = "Optimized {$optimized} database table(s)";
    
    // 8. Clear PHP session files (optional - be careful)
    // session_destroy(); // Don't destroy current admin session
    
    $results['summary'] = [
        'total_actions' => count($results['actions']),
        'cache_cleared' => true,
        'database_optimized' => true
    ];
    
} catch (Exception $e) {
    $results['success'] = false;
    $results['errors'][] = 'Error clearing cache: ' . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
