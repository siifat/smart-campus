<?php
/**
 * Check for Duplicate Records in Database
 * Find and optionally remove duplicate entries
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../config/database.php');

$results = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'duplicates_found' => [],
    'total_duplicates' => 0,
    'removed' => []
];

try {
    // Check for duplicate enrollments
    $query = "SELECT student_id, course_id, trimester_id, COUNT(*) as count,
              GROUP_CONCAT(enrollment_id ORDER BY enrollment_id) as enrollment_ids
              FROM enrollments 
              GROUP BY student_id, course_id, trimester_id 
              HAVING count > 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $enrollments = [];
        while ($row = $result->fetch_assoc()) {
            $enrollments[] = [
                'student_id' => $row['student_id'],
                'course_id' => $row['course_id'],
                'trimester_id' => $row['trimester_id'],
                'duplicate_count' => $row['count'],
                'enrollment_ids' => $row['enrollment_ids']
            ];
        }
        $results['duplicates_found']['enrollments'] = $enrollments;
        $results['total_duplicates'] += count($enrollments);
    }
    
    // Check for duplicate admin users by username
    $query = "SELECT username, COUNT(*) as count,
              GROUP_CONCAT(admin_id ORDER BY admin_id) as admin_ids
              FROM admin_users 
              GROUP BY username 
              HAVING count > 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $admins = [];
        while ($row = $result->fetch_assoc()) {
            $admins[] = [
                'username' => $row['username'],
                'duplicate_count' => $row['count'],
                'admin_ids' => $row['admin_ids']
            ];
        }
        $results['duplicates_found']['admin_users'] = $admins;
        $results['total_duplicates'] += count($admins);
    }
    
    // Check for duplicate resource likes
    $query = "SELECT resource_id, student_id, COUNT(*) as count,
              GROUP_CONCAT(like_id ORDER BY like_id) as like_ids
              FROM resource_likes 
              GROUP BY resource_id, student_id 
              HAVING count > 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $likes = [];
        while ($row = $result->fetch_assoc()) {
            $likes[] = [
                'resource_id' => $row['resource_id'],
                'student_id' => $row['student_id'],
                'duplicate_count' => $row['count'],
                'like_ids' => $row['like_ids']
            ];
        }
        $results['duplicates_found']['resource_likes'] = $likes;
        $results['total_duplicates'] += count($likes);
    }
    
    // Check for duplicate bookmarks
    $query = "SELECT resource_id, student_id, COUNT(*) as count,
              GROUP_CONCAT(bookmark_id ORDER BY bookmark_id) as bookmark_ids
              FROM resource_bookmarks 
              GROUP BY resource_id, student_id 
              HAVING count > 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $bookmarks = [];
        while ($row = $result->fetch_assoc()) {
            $bookmarks[] = [
                'resource_id' => $row['resource_id'],
                'student_id' => $row['student_id'],
                'duplicate_count' => $row['count'],
                'bookmark_ids' => $row['bookmark_ids']
            ];
        }
        $results['duplicates_found']['resource_bookmarks'] = $bookmarks;
        $results['total_duplicates'] += count($bookmarks);
    }
    
    // Check for duplicate student achievements
    $query = "SELECT student_id, achievement_id, COUNT(*) as count,
              GROUP_CONCAT(student_achievement_id ORDER BY student_achievement_id) as achievement_ids
              FROM student_achievements 
              GROUP BY student_id, achievement_id 
              HAVING count > 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $achievements = [];
        while ($row = $result->fetch_assoc()) {
            $achievements[] = [
                'student_id' => $row['student_id'],
                'achievement_id' => $row['achievement_id'],
                'duplicate_count' => $row['count'],
                'achievement_ids' => $row['achievement_ids']
            ];
        }
        $results['duplicates_found']['student_achievements'] = $achievements;
        $results['total_duplicates'] += count($achievements);
    }
    
    // Auto-remove duplicates if requested
    if (isset($_POST['remove_duplicates']) && $_POST['remove_duplicates'] === 'true') {
        $removed_count = 0;
        
        // Remove duplicate enrollments (keep oldest)
        if (isset($results['duplicates_found']['enrollments'])) {
            foreach ($results['duplicates_found']['enrollments'] as $dup) {
                $ids = explode(',', $dup['enrollment_ids']);
                $keep_id = $ids[0]; // Keep the first (oldest) one
                $remove_ids = array_slice($ids, 1);
                
                if (!empty($remove_ids)) {
                    $id_list = implode(',', $remove_ids);
                    $delete_query = "DELETE FROM enrollments WHERE enrollment_id IN ($id_list)";
                    if ($conn->query($delete_query)) {
                        $removed_count += $conn->affected_rows;
                    }
                }
            }
        }
        
        // Remove duplicate likes
        if (isset($results['duplicates_found']['resource_likes'])) {
            foreach ($results['duplicates_found']['resource_likes'] as $dup) {
                $ids = explode(',', $dup['like_ids']);
                $keep_id = $ids[0];
                $remove_ids = array_slice($ids, 1);
                
                if (!empty($remove_ids)) {
                    $id_list = implode(',', $remove_ids);
                    $delete_query = "DELETE FROM resource_likes WHERE like_id IN ($id_list)";
                    if ($conn->query($delete_query)) {
                        $removed_count += $conn->affected_rows;
                    }
                }
            }
        }
        
        // Remove duplicate bookmarks
        if (isset($results['duplicates_found']['resource_bookmarks'])) {
            foreach ($results['duplicates_found']['resource_bookmarks'] as $dup) {
                $ids = explode(',', $dup['bookmark_ids']);
                $keep_id = $ids[0];
                $remove_ids = array_slice($ids, 1);
                
                if (!empty($remove_ids)) {
                    $id_list = implode(',', $remove_ids);
                    $delete_query = "DELETE FROM resource_bookmarks WHERE bookmark_id IN ($id_list)";
                    if ($conn->query($delete_query)) {
                        $removed_count += $conn->affected_rows;
                    }
                }
            }
        }
        
        // Remove duplicate achievements
        if (isset($results['duplicates_found']['student_achievements'])) {
            foreach ($results['duplicates_found']['student_achievements'] as $dup) {
                $ids = explode(',', $dup['achievement_ids']);
                $keep_id = $ids[0];
                $remove_ids = array_slice($ids, 1);
                
                if (!empty($remove_ids)) {
                    $id_list = implode(',', $remove_ids);
                    $delete_query = "DELETE FROM student_achievements WHERE student_achievement_id IN ($id_list)";
                    if ($conn->query($delete_query)) {
                        $removed_count += $conn->affected_rows;
                    }
                }
            }
        }
        
        $results['removed'] = [
            'status' => 'success',
            'total_removed' => $removed_count,
            'message' => "Successfully removed {$removed_count} duplicate record(s)"
        ];
    }
    
    $results['summary'] = [
        'total_duplicate_groups' => $results['total_duplicates'],
        'status' => $results['total_duplicates'] == 0 ? 'No duplicates found' : "{$results['total_duplicates']} duplicate group(s) found"
    ];
    
} catch (Exception $e) {
    $results['success'] = false;
    $results['message'] = 'Error checking duplicates: ' . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
