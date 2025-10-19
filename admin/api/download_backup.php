<?php
/**
 * Download Backup File API
 */
session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in'])) {
    die('Unauthorized access');
}

// Get and sanitize filename
if (!isset($_GET['file']) || empty($_GET['file'])) {
    die('No file specified');
}

$filename = basename($_GET['file']); // Prevent directory traversal
$filepath = __DIR__ . '/../../backups/' . $filename;

// Verify file exists and is a SQL file
if (!file_exists($filepath)) {
    die('File not found');
}

if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
    die('Invalid file type');
}

// Set headers for download
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache');
header('Pragma: no-cache');

// Read and output file
readfile($filepath);
exit;
?>
