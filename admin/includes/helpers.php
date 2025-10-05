<?php
/**
 * Admin Helper Functions
 * Utility functions for the admin panel
 */

/**
 * Log admin activity
 * 
 * @param mysqli $conn Database connection
 * @param string $action_type Type of action (create, update, delete, login, export)
 * @param string $table_name Name of the table affected
 * @param int $record_id ID of the affected record
 * @param string $description Human-readable description
 * @return bool Success status
 */
function logActivity($conn, $action_type, $table_name = null, $record_id = null, $description = '') {
    // Create table if it doesn't exist
    $create_table = "CREATE TABLE IF NOT EXISTS activity_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT DEFAULT 1,
        action_type VARCHAR(50) NOT NULL,
        table_name VARCHAR(50),
        record_id INT,
        description TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        INDEX idx_action_type (action_type)
    )";
    $conn->query($create_table);
    
    $admin_id = $_SESSION['admin_id'] ?? 1;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $conn->prepare("INSERT INTO activity_logs 
                           (admin_id, action_type, table_name, record_id, description, ip_address, user_agent) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        error_log("Activity log failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("ississ", $admin_id, $action_type, $table_name, $record_id, $description, $ip_address, $user_agent);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Format file size to human readable format
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Sanitize input for display
 */
function sanitizeOutput($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if admin is logged in
 */
function requireAdmin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Generate random color for avatars
 */
function getAvatarColor($name) {
    $colors = [
        '#667eea', '#764ba2', '#f093fb', '#4facfe',
        '#43e97b', '#38f9d7', '#fa709a', '#fee140',
        '#30cfd0', '#330867', '#a8edea', '#fed6e3'
    ];
    
    $hash = crc32($name);
    return $colors[$hash % count($colors)];
}

/**
 * Get relative time (e.g., "2 hours ago")
 */
function getRelativeTime($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
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
        return date('M d, Y', $timestamp);
    }
}

/**
 * Generate CSV from array
 */
function arrayToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Add headers
        fputcsv($output, array_keys($data[0]));
        
        // Add data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure random password
 */
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $max = strlen($chars) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    return $password;
}

/**
 * Send notification (can be extended to email/SMS)
 */
function sendNotification($type, $title, $message, $recipient = null) {
    // For now, just log to session
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    
    $_SESSION['notifications'][] = [
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'recipient' => $recipient,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    return true;
}

/**
 * Get admin statistics
 */
function getAdminStats($conn) {
    $stats = [];
    
    $tables = ['students', 'teachers', 'courses', 'enrollments', 
               'departments', 'programs', 'trimesters', 'notes', 'question_solutions'];
    
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) as count FROM $table");
        $stats[$table] = $result->fetch_assoc()['count'];
    }
    
    return $stats;
}

/**
 * Check system health
 */
function checkSystemHealth($conn) {
    $health = [
        'database' => false,
        'reference_data' => false,
        'current_trimester' => false,
        'student_data' => false,
        'course_data' => false
    ];
    
    // Check database connection
    $health['database'] = $conn->ping();
    
    // Check reference data
    $dept_count = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];
    $prog_count = $conn->query("SELECT COUNT(*) as count FROM programs")->fetch_assoc()['count'];
    $health['reference_data'] = ($dept_count > 0 && $prog_count > 0);
    
    // Check current trimester
    $current_trim = $conn->query("SELECT COUNT(*) as count FROM trimesters WHERE is_current = 1")->fetch_assoc()['count'];
    $health['current_trimester'] = ($current_trim > 0);
    
    // Check student data
    $student_count = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
    $health['student_data'] = ($student_count > 0);
    
    // Check course data
    $course_count = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
    $health['course_data'] = ($course_count > 0);
    
    $health['score'] = (int)((array_sum(array_map(fn($v) => $v ? 1 : 0, $health)) / count($health)) * 100);
    
    return $health;
}

/**
 * Backup database
 */
function createBackup($conn, $db_name, $backup_dir = '../backups/') {
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . $filename;
    
    $command = "mysqldump --host=localhost --user=root --password= $db_name > $filepath";
    exec($command, $output, $return_var);
    
    if ($return_var === 0 && file_exists($filepath)) {
        logActivity($conn, 'backup', null, null, "Database backup created: $filename");
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    }
    
    return ['success' => false, 'error' => 'Backup failed'];
}

/**
 * Get pending approvals count
 */
function getPendingApprovalsCount($conn) {
    $notes = $conn->query("SELECT COUNT(*) as count FROM notes WHERE status = 'pending'")->fetch_assoc()['count'];
    $solutions = $conn->query("SELECT COUNT(*) as count FROM question_solutions WHERE status = 'pending'")->fetch_assoc()['count'];
    
    return [
        'notes' => $notes,
        'solutions' => $solutions,
        'total' => $notes + $solutions
    ];
}
