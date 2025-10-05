<?php
/**
 * Upload Resource API - Handle file uploads and resource creation
 * Database: uiu_smart_campus
 */

// Enable error logging but suppress display
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../config/database.php');

$student_id = $_SESSION['student_id'];

try {
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
    $resource_type = $_POST['resource_type'] ?? 'file';
    $external_link = trim($_POST['external_link'] ?? '');
    
    // Validation
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Title is required']);
        exit;
    }
    
    if ($category_id == 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a category']);
        exit;
    }
    
    // Initialize file variables
    $file_path = null;
    $file_name = null;
    $file_size = null;
    $file_type = null;
    
    // Handle file upload
    if ($resource_type === 'file' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/resources/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Get file info
        $original_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'txt', 'xlsx', 'xls'];
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'File type not allowed']);
            exit;
        }
        
        // Validate file size (50MB)
        if ($file_size > 50 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size must be less than 50MB']);
            exit;
        }
        
        // Generate unique filename
        $unique_name = $student_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
        $file_path = 'uploads/resources/' . $unique_name;
        $full_path = $upload_dir . $unique_name;
        
        // Move uploaded file
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $full_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            exit;
        }
        
        $file_name = $original_name;
        
    } elseif ($resource_type !== 'file' && !empty($external_link)) {
        // Validate URL
        if (!filter_var($external_link, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid URL provided']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Please upload a file or provide a link']);
        exit;
    }
    
    // Insert resource into database
    $stmt = $conn->prepare("
        INSERT INTO uploaded_resources 
        (student_id, course_id, category_id, title, description, resource_type, 
         file_path, file_name, file_size, file_type, external_link, points_awarded, is_approved)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 50, 1)
    ");
    
    $stmt->bind_param('siisssssiis', 
        $student_id,
        $course_id,
        $category_id,
        $title,
        $description,
        $resource_type,
        $file_path,
        $file_name,
        $file_size,
        $file_type,
        $external_link
    );
    
    if ($stmt->execute()) {
        $resource_id = $conn->insert_id;
        
        // Get updated points
        $points_stmt = $conn->prepare("SELECT total_points FROM students WHERE student_id = ?");
        $points_stmt->bind_param('s', $student_id);
        $points_stmt->execute();
        $result = $points_stmt->get_result();
        $student = $result->fetch_assoc();
        $new_points = $student['total_points'] ?? 50;
        
        echo json_encode([
            'success' => true,
            'message' => 'Resource uploaded successfully! You earned 50 points!',
            'resource_id' => $resource_id,
            'new_points' => $new_points,
            'points_earned' => 50
        ]);
        
    } else {
        // If database insert fails, delete uploaded file
        if ($file_path && file_exists('../../' . $file_path)) {
            unlink('../../' . $file_path);
        }
        echo json_encode(['success' => false, 'message' => 'Failed to save resource']);
    }
    
} catch (Exception $e) {
    error_log('Upload error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
