<?php
/**
 * Notification Helper Functions
 * Create notifications for different user types
 */

/**
 * Create notification for student
 */
function createStudentNotification($conn, $student_id, $type, $title, $message, $link = null, $priority = 'normal') {
    // Check if student has notifications enabled
    $settings_query = "SELECT email_notifications_enabled, notification_preferences FROM students WHERE student_id = ?";
    $stmt = $conn->prepare($settings_query);
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    
    if (!$student || !$student['email_notifications_enabled']) {
        return false; // Notifications disabled
    }
    
    // Check granular preferences
    $prefs = json_decode($student['notification_preferences'], true) ?? [];
    $pref_map = [
        'assignment' => 'assignment_reminders',
        'grade' => 'grade_updates',
        'announcement' => 'course_announcements',
        'deadline_reminder' => 'assignment_reminders',
        'resource' => 'resource_updates'
    ];
    
    if (isset($pref_map[$type]) && isset($prefs[$pref_map[$type]]) && !$prefs[$pref_map[$type]]) {
        return false; // This notification type is disabled
    }
    
    // Create notification
    $insert_query = "INSERT INTO student_notifications (student_id, notification_type, title, message, link, priority) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param('ssssss', $student_id, $type, $title, $message, $link, $priority);
    return $stmt->execute();
}

/**
 * Create notification for teacher
 */
function createTeacherNotification($conn, $teacher_id, $type, $title, $message, $related_type = null, $related_id = null, $action_url = null, $priority = 'normal') {
    $insert_query = "INSERT INTO teacher_notifications (teacher_id, notification_type, title, message, related_type, related_id, action_url, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param('issssiis', $teacher_id, $type, $title, $message, $related_type, $related_id, $action_url, $priority);
    return $stmt->execute();
}

/**
 * Create notification for admin
 */
function createAdminNotification($conn, $admin_id, $type, $title, $message, $action_url = null, $priority = 'normal') {
    $insert_query = "INSERT INTO admin_notifications (admin_id, notification_type, title, message, action_url, priority) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param('isssss', $admin_id, $type, $title, $message, $action_url, $priority);
    return $stmt->execute();
}

/**
 * Notify all students in a course about a resource upload
 */
function notifyStudentsAboutResource($conn, $course_id, $trimester_id, $section, $resource_title, $uploaded_by) {
    // Get all enrolled students in this course/section
    $query = "SELECT DISTINCT e.student_id, s.full_name 
              FROM enrollments e 
              JOIN students s ON e.student_id = s.student_id 
              WHERE e.course_id = ? AND e.trimester_id = ? AND e.section = ? AND e.status = 'enrolled'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iis', $course_id, $trimester_id, $section);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count = 0;
    while ($student = $result->fetch_assoc()) {
        $title = "New Resource Available";
        $message = "New resource '{$resource_title}' has been uploaded by {$uploaded_by}";
        $link = "/student/resources.php?course_id={$course_id}";
        
        if (createStudentNotification($conn, $student['student_id'], 'resource', $title, $message, $link, 'normal')) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Notify all students in a course about an announcement
 */
function notifyStudentsAboutAnnouncement($conn, $course_id, $trimester_id, $section, $announcement_title, $posted_by) {
    $query = "SELECT DISTINCT e.student_id 
              FROM enrollments e 
              WHERE e.course_id = ? AND e.trimester_id = ? AND e.section = ? AND e.status = 'enrolled'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iis', $course_id, $trimester_id, $section);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count = 0;
    while ($student = $result->fetch_assoc()) {
        $title = "New Announcement";
        $message = "{$posted_by} posted: {$announcement_title}";
        $link = "/student/dashboard.php?course_id={$course_id}";
        
        if (createStudentNotification($conn, $student['student_id'], 'announcement', $title, $message, $link, 'normal')) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Notify teacher about assignment submission
 */
function notifyTeacherAboutSubmission($conn, $teacher_id, $student_id, $assignment_title, $submission_id, $is_late = false) {
    $query = "SELECT full_name FROM students WHERE student_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    
    $type = $is_late ? 'late_submission' : 'new_submission';
    $status = $is_late ? 'late' : 'on time';
    $title = $is_late ? "Late Submission Received" : "New Submission Received";
    $message = "Student {$student['full_name']} ({$student_id}) submitted \"{$assignment_title}\" {$status}";
    
    return createTeacherNotification($conn, $teacher_id, $type, $title, $message, 'submission', $submission_id, null, $is_late ? 'high' : 'normal');
}

/**
 * Notify student about grade
 */
function notifyStudentAboutGrade($conn, $student_id, $assignment_title, $score, $total, $assignment_id) {
    $title = "Assignment Graded";
    $message = "Your submission for \"{$assignment_title}\" has been graded. Score: {$score}/{$total}";
    $link = "/student/assignment_detail.php?id={$assignment_id}";
    
    return createStudentNotification($conn, $student_id, 'grade', $title, $message, $link, 'high');
}
?>
