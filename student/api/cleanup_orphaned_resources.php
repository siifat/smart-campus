<?php
/**
 * Cleanup Orphaned Resources - Remove database records for files that don't exist
 * Run this script to clean up the database
 */
session_start();

// Check if student is logged in (or you can make this admin-only)
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    die('Unauthorized');
}

require_once('../../config/database.php');

// Get all file resources from database
$query = "SELECT resource_id, file_path, title FROM uploaded_resources WHERE resource_type = 'file'";
$result = $conn->query($query);

$deleted_count = 0;
$checked_count = 0;
$orphaned_resources = [];

echo "<h2>Checking Resources...</h2>";
echo "<pre>";

while ($row = $result->fetch_assoc()) {
    $checked_count++;
    $file_path = '../../' . $row['file_path'];
    
    echo "\nChecking: {$row['title']} ({$row['resource_id']})\n";
    echo "Path: {$row['file_path']}\n";
    
    if (!empty($row['file_path']) && !file_exists($file_path)) {
        echo "❌ FILE NOT FOUND - Will delete from database\n";
        $orphaned_resources[] = $row;
        
        // Delete the orphaned record
        $delete_stmt = $conn->prepare("DELETE FROM uploaded_resources WHERE resource_id = ?");
        $delete_stmt->bind_param('i', $row['resource_id']);
        
        if ($delete_stmt->execute()) {
            $deleted_count++;
            echo "✅ Deleted from database\n";
        } else {
            echo "⚠️ Failed to delete from database\n";
        }
        
        $delete_stmt->close();
    } else {
        echo "✅ File exists\n";
    }
}

echo "\n\n=== CLEANUP SUMMARY ===\n";
echo "Total resources checked: {$checked_count}\n";
echo "Orphaned resources deleted: {$deleted_count}\n";
echo "Valid resources: " . ($checked_count - $deleted_count) . "\n";

if (count($orphaned_resources) > 0) {
    echo "\n=== DELETED RESOURCES ===\n";
    foreach ($orphaned_resources as $resource) {
        echo "- {$resource['title']} (ID: {$resource['resource_id']})\n";
    }
}

echo "</pre>";

echo "<br><a href='../resources.php' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background: #f68b1f; color: white; text-decoration: none; border-radius: 8px;'>Back to Resources</a>";
?>
