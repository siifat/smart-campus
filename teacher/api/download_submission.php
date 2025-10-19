<?php
session_start();

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['teacher_logged_in'])) {
    header('Location: ../login.php?error=unauthorized');
    exit();
}

require_once '../../config/database.php';

$teacher_id = $_SESSION['teacher_id'];
$submission_id = $_GET['id'] ?? null;

if (!$submission_id) {
    die('Submission ID required');
}

try {
    // Get submission with file path, verify ownership
    $stmt = $pdo->prepare("
        SELECT sub.file_path, s.full_name as student_name, a.title as assignment_title
        FROM assignment_submissions sub
        JOIN assignments a ON sub.assignment_id = a.assignment_id
        JOIN students s ON sub.student_id = s.student_id
        WHERE sub.submission_id = ? AND a.teacher_id = ?
    ");
    $stmt->execute([$submission_id, $teacher_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        die('Submission not found or access denied');
    }
    
    if (!$submission['file_path']) {
        die('No file attached to this submission');
    }
    
    // Construct full file path
    $file_path = '../../' . $submission['file_path'];
    
    if (!file_exists($file_path)) {
        die('File not found on server');
    }
    
    // Get file info
    $file_name = basename($file_path);
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Set content type based on extension
    $content_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
    ];
    
    $content_type = $content_types[$file_extension] ?? 'application/octet-stream';
    
    // Create a clean download filename
    $clean_student_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $submission['student_name']);
    $clean_assignment = preg_replace('/[^a-zA-Z0-9_-]/', '_', $submission['assignment_title']);
    $download_name = $clean_student_name . '_' . $clean_assignment . '.' . $file_extension;
    
    // Set headers for download
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $download_name . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read and output file
    readfile($file_path);
    exit();
    
} catch (PDOException $e) {
    error_log("Download submission error: " . $e->getMessage());
    die('Database error occurred');
}
?>
