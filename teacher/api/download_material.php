<?php
/**
 * Download Course Material API
 * Increments download count and serves the file
 */
session_start();

// Check authentication
if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['teacher_id'])) {
    header('Location: ../../login.html');
    exit;
}

require_once '../../config/database.php';

$teacher_id = $_SESSION['teacher_id'];
$content_id = $_GET['content_id'] ?? null;

if (!$content_id) {
    die('Material ID is required');
}

try {
    // Get material details and verify access
    $stmt = $pdo->prepare("
        SELECT cm.*, c.course_code, c.course_name 
        FROM course_materials cm
        JOIN courses c ON cm.course_id = c.course_id
        JOIN enrollments e ON cm.course_id = e.course_id 
            AND cm.section = e.section 
            AND e.teacher_id = cm.teacher_id
        WHERE cm.content_id = ? AND cm.teacher_id = ?
    ");
    $stmt->execute([$content_id, $teacher_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    
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
        $update_stmt = $pdo->prepare("UPDATE course_materials SET download_count = download_count + 1 WHERE content_id = ?");
        $update_stmt->execute([$content_id]);
        
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
        $update_stmt = $pdo->prepare("UPDATE course_materials SET download_count = download_count + 1 WHERE content_id = ?");
        $update_stmt->execute([$content_id]);
        
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
    
} catch (PDOException $e) {
    error_log("Download Material Error: " . $e->getMessage());
    die('Failed to download material');
}
?>
