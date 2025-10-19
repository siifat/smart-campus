<?php
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['teacher_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../config/database.php';

$teacher_id = $_SESSION['teacher_id'];

try {
    // Get current trimester
    $stmt = $pdo->query("SELECT trimester_id FROM trimesters WHERE is_current = 1 LIMIT 1");
    $current_trimester = $stmt->fetch(PDO::FETCH_ASSOC);
    $trimester_id = $current_trimester['trimester_id'] ?? 1;
    
    $assignment_id = $_POST['assignment_id'] ?? null;
    $course_id = $_POST['course_id'] ?? null;
    $section = !empty($_POST['section']) ? $_POST['section'] : null;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $assignment_type = $_POST['assignment_type'] ?? 'homework';
    $total_marks = $_POST['total_marks'] ?? 100;
    $weight_percentage = !empty($_POST['weight_percentage']) ? $_POST['weight_percentage'] : null;
    $due_date = $_POST['due_date'] ?? '';
    $late_submission_allowed = isset($_POST['late_submission_allowed']) ? 1 : 0;
    $late_penalty_per_day = $_POST['late_penalty_per_day'] ?? 5;
    $is_bonus = isset($_POST['is_bonus']) ? 1 : 0;
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    // Validation
    if (empty($course_id) || empty($title) || empty($description) || empty($due_date)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        exit();
    }
    
    // Verify teacher teaches this course
    $verify_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM enrollments 
        WHERE teacher_id = ? AND course_id = ? AND trimester_id = ?
        AND (? IS NULL OR section = ?)
    ");
    $verify_stmt->execute([$teacher_id, $course_id, $trimester_id, $section, $section]);
    $verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($verify['count'] == 0) {
        echo json_encode(['success' => false, 'message' => 'You are not assigned to teach this course/section']);
        exit();
    }
    
    // Handle file upload
    $file_path = null;
    if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/assignments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['file_upload']['name'], PATHINFO_EXTENSION);
        $file_name = 'assignment_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
        $file_path = 'uploads/assignments/' . $file_name;
        
        if ($_FILES['file_upload']['size'] > 10485760) { // 10MB limit
            echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
            exit();
        }
        
        if (!move_uploaded_file($_FILES['file_upload']['tmp_name'], '../../' . $file_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            exit();
        }
    }
    
    if ($assignment_id) {
        // Update existing assignment
        $query = "
            UPDATE assignments SET
                course_id = ?,
                section = ?,
                title = ?,
                description = ?,
                assignment_type = ?,
                total_marks = ?,
                weight_percentage = ?,
                due_date = ?,
                late_submission_allowed = ?,
                late_penalty_per_day = ?,
                is_bonus = ?,
                is_published = ?
        ";
        
        $params = [
            $course_id, $section, $title, $description, $assignment_type,
            $total_marks, $weight_percentage, $due_date, $late_submission_allowed,
            $late_penalty_per_day, $is_bonus, $is_published
        ];
        
        if ($file_path) {
            $query .= ", file_path = ?";
            $params[] = $file_path;
        }
        
        $query .= " WHERE assignment_id = ? AND teacher_id = ?";
        $params[] = $assignment_id;
        $params[] = $teacher_id;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Assignment updated successfully',
            'assignment_id' => $assignment_id
        ]);
        
    } else {
        // Create new assignment
        $stmt = $pdo->prepare("
            INSERT INTO assignments (
                course_id, teacher_id, trimester_id, section, title, description,
                assignment_type, total_marks, weight_percentage, file_path, due_date,
                late_submission_allowed, late_penalty_per_day, is_bonus, is_published
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $course_id, $teacher_id, $trimester_id, $section, $title, $description,
            $assignment_type, $total_marks, $weight_percentage, $file_path, $due_date,
            $late_submission_allowed, $late_penalty_per_day, $is_bonus, $is_published
        ]);
        
        $new_id = $pdo->lastInsertId();
        
        // Create notification for students if published
        if ($is_published) {
            $notif_stmt = $pdo->prepare("
                INSERT INTO student_notifications (student_id, notification_type, title, message, link)
                SELECT DISTINCT e.student_id, 'assignment', 'New Assignment Posted', 
                       CONCAT('New assignment \"', ?, '\" has been posted for ', c.course_code),
                       '/student/assignment_detail.php?id=' 
                FROM enrollments e
                JOIN courses c ON e.course_id = c.course_id
                WHERE e.course_id = ? AND e.trimester_id = ? AND e.status = 'enrolled'
                  AND (? IS NULL OR e.section = ?)
            ");
            $notif_stmt->execute([$title, $course_id, $trimester_id, $section, $section]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Assignment created successfully',
            'assignment_id' => $new_id
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Assignment management error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
