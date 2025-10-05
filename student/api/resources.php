<?php
/**
 * Resources API - Handle resource browsing, interactions, and downloads
 * Database: uiu_smart_campus
 */

// Enable error logging but suppress display
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();

// Check authentication
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../config/database.php');

$student_id = $_SESSION['student_id'];

// Get action from POST, GET, or JSON body
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// If action is empty, try to get it from JSON body
if (empty($action)) {
    $json_data = json_decode(file_get_contents('php://input'), true);
    $action = $json_data['action'] ?? '';
}

error_log("RESOURCES API: Action='$action', Student='$student_id'");

// Handle file download separately (not JSON)
if ($action === 'download') {
    handleDownload($conn, $student_id);
    exit;
}

// Set JSON header for all other actions
header('Content-Type: application/json');

try {
    switch ($action) {
        case 'get_all':
            getAllResources($conn, $student_id);
            break;
            
        case 'get_details':
            $resource_id = intval($_GET['resource_id'] ?? 0);
            getResourceDetails($conn, $student_id, $resource_id);
            break;
            
        case 'toggle_like':
            $data = json_decode(file_get_contents('php://input'), true);
            $resource_id = intval($data['resource_id'] ?? 0);
            toggleLike($conn, $student_id, $resource_id);
            break;
            
        case 'toggle_bookmark':
            $data = json_decode(file_get_contents('php://input'), true);
            $resource_id = intval($data['resource_id'] ?? 0);
            toggleBookmark($conn, $student_id, $resource_id);
            break;
            
        case 'track_view':
            $data = json_decode(file_get_contents('php://input'), true);
            $resource_id = intval($data['resource_id'] ?? 0);
            trackView($conn, $student_id, $resource_id);
            break;
            
        case 'track_download':
            $data = json_decode(file_get_contents('php://input'), true);
            $resource_id = intval($data['resource_id'] ?? 0);
            trackDownload($conn, $resource_id);
            break;
            
        case 'add_comment':
            $data = json_decode(file_get_contents('php://input'), true);
            $resource_id = intval($data['resource_id'] ?? 0);
            $comment_text = trim($data['comment_text'] ?? '');
            addComment($conn, $student_id, $resource_id, $comment_text);
            break;
            
        case 'delete_comment':
            $data = json_decode(file_get_contents('php://input'), true);
            $comment_id = intval($data['comment_id'] ?? 0);
            deleteComment($conn, $student_id, $comment_id);
            break;
            
        case 'delete_resource':
            $data = json_decode(file_get_contents('php://input'), true);
            $resource_id = intval($data['resource_id'] ?? 0);
            deleteResource($conn, $student_id, $resource_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log('Resources API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// ============================================================================
// FUNCTION IMPLEMENTATIONS
// ============================================================================

/**
 * Get all approved resources with user interaction status
 */
function getAllResources($conn, $student_id) {
    $query = "
        SELECT 
            ur.*,
            rc.category_name,
            rc.category_icon,
            rc.category_color,
            c.course_code,
            c.course_name,
            s.full_name as student_name,
            EXISTS(SELECT 1 FROM resource_likes WHERE resource_id = ur.resource_id AND student_id = ?) as user_liked,
            EXISTS(SELECT 1 FROM resource_bookmarks WHERE resource_id = ur.resource_id AND student_id = ?) as user_bookmarked,
            (SELECT COUNT(*) FROM resource_comments WHERE resource_id = ur.resource_id) as comments_count
        FROM uploaded_resources ur
        LEFT JOIN resource_categories rc ON ur.category_id = rc.category_id
        LEFT JOIN courses c ON ur.course_id = c.course_id
        LEFT JOIN students s ON ur.student_id = s.student_id
        WHERE ur.is_approved = 1
        ORDER BY ur.uploaded_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param('ss', $student_id, $student_id);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Database execute error: ' . $stmt->error]);
        return;
    }
    
    $result = $stmt->get_result();
    
    $resources = [];
    while ($row = $result->fetch_assoc()) {
        // Convert boolean fields
        $row['user_liked'] = (bool)$row['user_liked'];
        $row['user_bookmarked'] = (bool)$row['user_bookmarked'];
        $row['is_approved'] = (bool)$row['is_approved'];
        $row['is_featured'] = (bool)$row['is_featured'];
        
        $resources[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'resources' => $resources,
        'count' => count($resources)
    ]);
}

/**
 * Get detailed information about a specific resource
 */
function getResourceDetails($conn, $student_id, $resource_id) {
    if ($resource_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid resource ID']);
        return;
    }
    
    // Get resource details
    $query = "
        SELECT 
            ur.*,
            rc.category_name,
            rc.category_icon,
            rc.category_color,
            c.course_code,
            c.course_name,
            s.full_name as student_name,
            EXISTS(SELECT 1 FROM resource_likes WHERE resource_id = ur.resource_id AND student_id = ?) as user_liked,
            EXISTS(SELECT 1 FROM resource_bookmarks WHERE resource_id = ur.resource_id AND student_id = ?) as user_bookmarked
        FROM uploaded_resources ur
        LEFT JOIN resource_categories rc ON ur.category_id = rc.category_id
        LEFT JOIN courses c ON ur.course_id = c.course_id
        LEFT JOIN students s ON ur.student_id = s.student_id
        WHERE ur.resource_id = ? AND ur.is_approved = 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssi', $student_id, $student_id, $resource_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Resource not found']);
        return;
    }
    
    $resource = $result->fetch_assoc();
    
    // Convert boolean fields
    $resource['user_liked'] = (bool)$resource['user_liked'];
    $resource['user_bookmarked'] = (bool)$resource['user_bookmarked'];
    $resource['is_approved'] = (bool)$resource['is_approved'];
    $resource['is_featured'] = (bool)$resource['is_featured'];
    
    // Get comments
    $comments_query = "
        SELECT 
            rcom.*,
            s.full_name as student_name
        FROM resource_comments rcom
        LEFT JOIN students s ON rcom.student_id = s.student_id
        WHERE rcom.resource_id = ?
        ORDER BY rcom.commented_at DESC
    ";
    
    $comments_stmt = $conn->prepare($comments_query);
    $comments_stmt->bind_param('i', $resource_id);
    $comments_stmt->execute();
    $comments_result = $comments_stmt->get_result();
    
    $comments = [];
    while ($comment = $comments_result->fetch_assoc()) {
        $comments[] = $comment;
    }
    
    $resource['comments'] = $comments;
    $resource['comments_count'] = count($comments);
    
    // Track view
    trackView($conn, $student_id, $resource_id, false);
    
    echo json_encode([
        'success' => true,
        'resource' => $resource
    ]);
}

/**
 * Toggle like status for a resource
 */
function toggleLike($conn, $student_id, $resource_id) {
    if ($resource_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid resource ID']);
        return;
    }
    
    // Check if already liked
    $check_stmt = $conn->prepare("SELECT like_id FROM resource_likes WHERE resource_id = ? AND student_id = ?");
    $check_stmt->bind_param('is', $resource_id, $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Unlike
        $delete_stmt = $conn->prepare("DELETE FROM resource_likes WHERE resource_id = ? AND student_id = ?");
        $delete_stmt->bind_param('is', $resource_id, $student_id);
        $delete_stmt->execute();
        $liked = false;
    } else {
        // Like
        $insert_stmt = $conn->prepare("INSERT INTO resource_likes (resource_id, student_id) VALUES (?, ?)");
        $insert_stmt->bind_param('is', $resource_id, $student_id);
        $insert_stmt->execute();
        $liked = true;
    }
    
    // Get updated like count
    $count_stmt = $conn->prepare("SELECT likes_count FROM uploaded_resources WHERE resource_id = ?");
    $count_stmt->bind_param('i', $resource_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'likes_count' => $count_row['likes_count'] ?? 0
    ]);
}

/**
 * Toggle bookmark status for a resource
 */
function toggleBookmark($conn, $student_id, $resource_id) {
    if ($resource_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid resource ID']);
        return;
    }
    
    // Check if already bookmarked
    $check_stmt = $conn->prepare("SELECT bookmark_id FROM resource_bookmarks WHERE resource_id = ? AND student_id = ?");
    $check_stmt->bind_param('is', $resource_id, $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Remove bookmark
        $delete_stmt = $conn->prepare("DELETE FROM resource_bookmarks WHERE resource_id = ? AND student_id = ?");
        $delete_stmt->bind_param('is', $resource_id, $student_id);
        $delete_stmt->execute();
        $bookmarked = false;
    } else {
        // Add bookmark
        $insert_stmt = $conn->prepare("INSERT INTO resource_bookmarks (resource_id, student_id) VALUES (?, ?)");
        $insert_stmt->bind_param('is', $resource_id, $student_id);
        $insert_stmt->execute();
        $bookmarked = true;
    }
    
    echo json_encode([
        'success' => true,
        'bookmarked' => $bookmarked
    ]);
}

/**
 * Track resource view
 */
function trackView($conn, $student_id, $resource_id, $output = true) {
    if ($resource_id <= 0) {
        if ($output) {
            echo json_encode(['success' => false, 'message' => 'Invalid resource ID']);
        }
        return;
    }
    
    // Check if already viewed recently (within last hour)
    $check_stmt = $conn->prepare("
        SELECT view_id FROM resource_views 
        WHERE resource_id = ? AND student_id = ? 
        AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $check_stmt->bind_param('is', $resource_id, $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        // Record new view
        $insert_stmt = $conn->prepare("INSERT INTO resource_views (resource_id, student_id) VALUES (?, ?)");
        $insert_stmt->bind_param('is', $resource_id, $student_id);
        $insert_stmt->execute();
        
        // Update view count
        $update_stmt = $conn->prepare("UPDATE uploaded_resources SET views_count = views_count + 1 WHERE resource_id = ?");
        $update_stmt->bind_param('i', $resource_id);
        $update_stmt->execute();
    }
    
    if ($output) {
        echo json_encode(['success' => true]);
    }
}

/**
 * Track resource download
 */
function trackDownload($conn, $resource_id) {
    if ($resource_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid resource ID']);
        return;
    }
    
    // Increment download count
    $stmt = $conn->prepare("UPDATE uploaded_resources SET downloads_count = downloads_count + 1 WHERE resource_id = ?");
    $stmt->bind_param('i', $resource_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

/**
 * Handle file download
 */
function handleDownload($conn, $student_id) {
    $resource_id = intval($_GET['resource_id'] ?? 0);
    
    if ($resource_id <= 0) {
        die('Invalid resource ID');
    }
    
    // Get resource details
    $stmt = $conn->prepare("SELECT file_path, file_name, file_type FROM uploaded_resources WHERE resource_id = ? AND is_approved = 1");
    $stmt->bind_param('i', $resource_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die('Resource not found');
    }
    
    $resource = $result->fetch_assoc();
    $file_path = '../../' . $resource['file_path'];
    
    if (!file_exists($file_path)) {
        die('File not found');
    }
    
    // Increment download count
    $update_stmt = $conn->prepare("UPDATE uploaded_resources SET downloads_count = downloads_count + 1 WHERE resource_id = ?");
    $update_stmt->bind_param('i', $resource_id);
    $update_stmt->execute();
    
    // Set headers for download
    header('Content-Type: ' . $resource['file_type']);
    header('Content-Disposition: attachment; filename="' . $resource['file_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($file_path);
    exit;
}

/**
 * Add a comment to a resource
 */
function addComment($conn, $student_id, $resource_id, $comment_text) {
    if ($resource_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid resource ID']);
        return;
    }
    
    if (empty($comment_text)) {
        echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
        return;
    }
    
    // Check if resource exists
    $check_stmt = $conn->prepare("SELECT resource_id FROM uploaded_resources WHERE resource_id = ? AND is_approved = 1");
    $check_stmt->bind_param('i', $resource_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Resource not found']);
        return;
    }
    
    // Insert comment
    $stmt = $conn->prepare("
        INSERT INTO resource_comments (resource_id, student_id, comment_text)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param('iss', $resource_id, $student_id, $comment_text);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Comment added successfully',
            'comment_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
    }
}

/**
 * Delete a comment (only by comment owner)
 */
function deleteComment($conn, $student_id, $comment_id) {
    if ($comment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid comment ID']);
        return;
    }
    
    // Check if comment belongs to student
    $check_stmt = $conn->prepare("SELECT comment_id FROM resource_comments WHERE comment_id = ? AND student_id = ?");
    $check_stmt->bind_param('is', $comment_id, $student_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Comment not found or unauthorized']);
        return;
    }
    
    // Delete comment
    $stmt = $conn->prepare("DELETE FROM resource_comments WHERE comment_id = ? AND student_id = ?");
    $stmt->bind_param('is', $comment_id, $student_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Comment deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete comment']);
    }
}

/**
 * Delete a resource (only by resource owner) - Deducts 50 points
 */
function deleteResource($conn, $student_id, $resource_id) {
    error_log("DELETE RESOURCE CALLED: resource_id=$resource_id, student_id=$student_id");
    
    if ($resource_id <= 0) {
        error_log("DELETE RESOURCE FAILED: Invalid resource ID");
        echo json_encode(['success' => false, 'message' => 'Invalid resource ID']);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if resource belongs to student and get file path
        $check_stmt = $conn->prepare("SELECT file_path FROM uploaded_resources WHERE resource_id = ? AND student_id = ?");
        $check_stmt->bind_param('is', $resource_id, $student_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        error_log("DELETE RESOURCE: Query executed, rows found: " . $result->num_rows);
        
        if ($result->num_rows === 0) {
            $conn->rollback();
            error_log("DELETE RESOURCE FAILED: Resource not found or unauthorized");
            echo json_encode(['success' => false, 'message' => 'Resource not found or unauthorized']);
            return;
        }
        
        $resource = $result->fetch_assoc();
        $file_path = $resource['file_path'];
        
        // Delete related data first (due to foreign key constraints)
        
        // Delete views
        $stmt = $conn->prepare("DELETE FROM resource_views WHERE resource_id = ?");
        $stmt->bind_param('i', $resource_id);
        $stmt->execute();
        
        // Delete likes
        $stmt = $conn->prepare("DELETE FROM resource_likes WHERE resource_id = ?");
        $stmt->bind_param('i', $resource_id);
        $stmt->execute();
        
        // Delete bookmarks
        $stmt = $conn->prepare("DELETE FROM resource_bookmarks WHERE resource_id = ?");
        $stmt->bind_param('i', $resource_id);
        $stmt->execute();
        
        // Delete comments
        $stmt = $conn->prepare("DELETE FROM resource_comments WHERE resource_id = ?");
        $stmt->bind_param('i', $resource_id);
        $stmt->execute();
        
        // Delete the resource record
        $stmt = $conn->prepare("DELETE FROM uploaded_resources WHERE resource_id = ? AND student_id = ?");
        $stmt->bind_param('is', $resource_id, $student_id);
        
        error_log("DELETE RESOURCE: Executing DELETE query for resource_id=$resource_id, student_id=$student_id");
        
        if (!$stmt->execute()) {
            error_log("DELETE RESOURCE FAILED: SQL Error - " . $stmt->error);
            throw new Exception('Failed to delete resource from database: ' . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        error_log("DELETE RESOURCE: Deleted $affected_rows row(s)");
        
        // Delete the physical file if it exists
        if (!empty($file_path)) {
            $full_file_path = '../../' . $file_path;
            error_log("DELETE RESOURCE: Attempting to delete file: $full_file_path");
            
            if (file_exists($full_file_path)) {
                error_log("DELETE RESOURCE: File exists, deleting...");
                if (unlink($full_file_path)) {
                    error_log("DELETE RESOURCE: File deleted successfully");
                } else {
                    error_log("DELETE RESOURCE WARNING: Failed to delete file: $full_file_path");
                    // Don't fail the whole operation if file deletion fails
                }
            } else {
                error_log("DELETE RESOURCE WARNING: File not found: $full_file_path");
            }
        }
        
        // Deduct 50 points from student (but don't go below 0)
        $update_points_stmt = $conn->prepare("
            UPDATE students 
            SET total_points = GREATEST(0, COALESCE(total_points, 0) - 50)
            WHERE student_id = ?
        ");
        $update_points_stmt->bind_param('s', $student_id);
        $update_points_stmt->execute();
        
        // Get updated points
        $points_stmt = $conn->prepare("SELECT COALESCE(total_points, 0) as total_points FROM students WHERE student_id = ?");
        $points_stmt->bind_param('s', $student_id);
        $points_stmt->execute();
        $points_result = $points_stmt->get_result();
        $student_data = $points_result->fetch_assoc();
        $new_points = $student_data['total_points'];
        
        // Log activity (optional - skip if table doesn't exist)
        try {
            $activity_stmt = $conn->prepare("
                INSERT INTO student_activity (student_id, activity_type, activity_description, points_change)
                VALUES (?, 'resource_delete', 'Deleted a resource', -50)
            ");
            if ($activity_stmt) {
                $activity_stmt->bind_param('s', $student_id);
                $activity_stmt->execute();
            }
        } catch (Exception $e) {
            // Ignore if activity table doesn't exist
            error_log("DELETE RESOURCE: Could not log activity (table may not exist): " . $e->getMessage());
        }
        
        // Commit transaction
        $conn->commit();
        
        error_log("DELETE RESOURCE: Successfully deleted resource $resource_id");
        
        echo json_encode([
            'success' => true,
            'message' => 'Resource deleted successfully. 50 points deducted.',
            'new_points' => $new_points
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log('Delete resource error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to delete resource: ' . $e->getMessage()]);
    }
}
?>