<?php
/**
 * Student Download Material API
 * Increments download count and serves the file
 */
session_start();

// Check authentication
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Location: ../../login.html');
    exit;
}

require_once('../../config/database.php');

$student_id = $_SESSION['student_id'];
$content_id = $_GET['content_id'] ?? null;

if (!$content_id) {
    die('Material ID is required');
}

try {
    // Get current trimester
    $current_trimester = $conn->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $current_trimester_id = $current_trimester['trimester_id'] ?? 1;
    
    // Get material details and verify student access
    $stmt = $conn->prepare("
        SELECT cm.*, c.course_code, c.course_name 
        FROM course_materials cm
        JOIN courses c ON cm.course_id = c.course_id
        JOIN enrollments e ON cm.course_id = e.course_id 
            AND cm.trimester_id = e.trimester_id
            AND (cm.section IS NULL OR cm.section = e.section)
        WHERE cm.content_id = ? 
            AND e.student_id = ?
            AND e.status = 'enrolled'
            AND cm.is_published = 1
    ");
    $stmt->bind_param('is', $content_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $material = $result->fetch_assoc();
    $stmt->close();
    
    if (!$material) {
        die('Material not found or access denied');
    }
    
    // Handle external URLs (redirect)
    if ($material['external_url']) {
        header('Location: ' . $material['external_url']);
        exit;
    }
    
    // Handle code snippets (download as .txt file)
    if ($material['content_type'] === 'code' && $material['content_text']) {
        // Increment download count
        $update_stmt = $conn->prepare("UPDATE course_materials SET download_count = download_count + 1 WHERE content_id = ?");
        $update_stmt->bind_param('i', $content_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Create filename
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $material['title']) . '.txt';
        
        // Set headers for download
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($material['content_text']));
        
        echo $material['content_text'];
        exit;
    }
    
    // Handle file downloads
    if ($material['file_path']) {
        $file_path = '../../' . $material['file_path'];
        
        if (!file_exists($file_path)) {
            die('File not found on server');
        }
        
        // Increment download count
        $update_stmt = $conn->prepare("UPDATE course_materials SET download_count = download_count + 1 WHERE content_id = ?");
        $update_stmt->bind_param('i', $content_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Get file info
        $file_size = filesize($file_path);
        $file_name = basename($file_path);
        
        // Determine MIME type
        $mime_type = $material['mime_type'] ?? 'application/octet-stream';
        
        // Set headers for download
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . $file_size);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Clear output buffer
        ob_clean();
        flush();
        
        // Read and output file
        readfile($file_path);
        exit;
    }
    
    die('No downloadable content found');
    
} catch (Exception $e) {
    error_log("Download Material Error: " . $e->getMessage());
    die('Failed to download material');
}
?>
