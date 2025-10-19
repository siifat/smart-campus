<?php
/**
 * Manage Course Material API
 * Handle upload, update of course materials
 */
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['teacher_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../../config/database.php';

$teacher_id = $_SESSION['teacher_id'];

try {
    // Get current trimester
    $stmt = $pdo->query("SELECT trimester_id FROM trimesters WHERE is_current = 1 LIMIT 1");
    $current_trimester_id = $stmt->fetchColumn();
    
    $content_id = $_POST['content_id'] ?? null;
    $course_id = $_POST['course_id'] ?? null;
    $section = $_POST['section'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $content_type = $_POST['content_type'] ?? 'pdf';
    $external_url = trim($_POST['external_url'] ?? '');
    $content_text = $_POST['content_text'] ?? '';
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    // Validation
    if (!$course_id || !$section || !$title) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Verify teacher has access to this course-section
    $verify_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM enrollments 
        WHERE teacher_id = ? AND course_id = ? AND section = ? AND trimester_id = ? AND status = 'enrolled'
    ");
    $verify_stmt->execute([$teacher_id, $course_id, $section, $current_trimester_id]);
    
    if ($verify_stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Access denied to this course']);
        exit;
    }
    
    $file_path = null;
    $file_size = null;
    $mime_type = null;
    
    // Handle file upload for pdf, document, other types
    if (in_array($content_type, ['pdf', 'document', 'other']) && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $allowed_types = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
            'application/x-rar-compressed'
        ];
        
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
            exit;
        }
        
        $max_size = 50 * 1024 * 1024; // 50MB
        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds 50MB limit']);
            exit;
        }
        
        // Create upload directory if not exists
        $upload_dir = '../../uploads/course_materials/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'material_' . $teacher_id . '_' . $course_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
        $file_path = $upload_dir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            exit;
        }
        
        $file_size = $file['size'];
        $mime_type = $file['type'];
        $file_path = 'uploads/course_materials/' . $filename; // Store relative path
    }
    
    // For video and link types, validate URL
    if (in_array($content_type, ['video', 'link']) && !$external_url) {
        echo json_encode(['success' => false, 'message' => 'URL is required for this content type']);
        exit;
    }
    
    // For code type, validate content_text
    if ($content_type === 'code' && !$content_text) {
        echo json_encode(['success' => false, 'message' => 'Code content is required']);
        exit;
    }
    
    if ($content_id) {
        // Update existing material
        $update_query = "
            UPDATE course_materials 
            SET title = ?,
                description = ?,
                content_type = ?,
                external_url = ?,
                content_text = ?,
                is_published = ?
        ";
        
        $params = [$title, $description, $content_type, $external_url, $content_text, $is_published];
        
        if ($file_path) {
            $update_query .= ", file_path = ?, file_size = ?, mime_type = ?";
            $params[] = $file_path;
            $params[] = $file_size;
            $params[] = $mime_type;
        }
        
        $update_query .= " WHERE content_id = ? AND teacher_id = ?";
        $params[] = $content_id;
        $params[] = $teacher_id;
        
        $stmt = $pdo->prepare($update_query);
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'Material updated successfully']);
    } else {
        // Insert new material
        $stmt = $pdo->prepare("
            INSERT INTO course_materials (
                course_id, teacher_id, trimester_id, section, title, description,
                content_type, file_path, external_url, content_text, file_size, mime_type, is_published
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $course_id,
            $teacher_id,
            $current_trimester_id,
            $section,
            $title,
            $description,
            $content_type,
            $file_path,
            $external_url,
            $content_text,
            $file_size,
            $mime_type,
            $is_published
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Material uploaded successfully']);
    }
    
} catch (PDOException $e) {
    error_log("Course Material Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
