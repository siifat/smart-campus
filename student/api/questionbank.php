<?php
/**
 * Question Bank API - Browse UIU Question Bank
 * Integrates with UIUQuestionBank folder structure
 */

session_start();

// Check authentication
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../config/database.php');

$student_id = $_SESSION['student_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json');

$QUESTION_BANK_PATH = dirname(dirname(dirname(__FILE__))) . '/UIUQuestionBank/question/';

try {
    switch ($action) {
        case 'get_courses':
            getCourses($conn, $QUESTION_BANK_PATH);
            break;
            
        case 'get_questions':
            $course_code = $_GET['course_code'] ?? '';
            getQuestions($conn, $QUESTION_BANK_PATH, $course_code);
            break;
            
        case 'get_combined_resources':
            getCombinedResources($conn, $student_id, $QUESTION_BANK_PATH);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Question Bank API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Get all courses with available questions
 */
function getCourses($conn, $questionBankPath) {
    $courses = [];
    
    // Scan question bank directory
    if (is_dir($questionBankPath)) {
        $courseFolders = array_diff(scandir($questionBankPath), ['.', '..']);
        
        foreach ($courseFolders as $folder) {
            $folderPath = $questionBankPath . $folder;
            
            if (is_dir($folderPath)) {
                // Clean course code (remove spaces)
                $cleanCourseCode = str_replace(' ', '', $folder);
                
                // Try to match with database
                $stmt = $conn->prepare("
                    SELECT course_id, course_code, course_name 
                    FROM courses 
                    WHERE REPLACE(course_code, ' ', '') = ?
                    LIMIT 1
                ");
                $stmt->bind_param('s', $cleanCourseCode);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $course = $result->fetch_assoc();
                    
                    // Count questions
                    $midCount = 0;
                    $finalCount = 0;
                    
                    if (is_dir($folderPath . '/mid')) {
                        $midFiles = glob($folderPath . '/mid/*.pdf');
                        $midCount = count($midFiles);
                    }
                    
                    if (is_dir($folderPath . '/final')) {
                        $finalFiles = glob($folderPath . '/final/*.pdf');
                        $finalCount = count($finalFiles);
                    }
                    
                    if ($midCount > 0 || $finalCount > 0) {
                        $courses[] = [
                            'course_id' => $course['course_id'],
                            'course_code' => $course['course_code'],
                            'course_code_clean' => $cleanCourseCode,
                            'course_name' => $course['course_name'],
                            'folder_name' => $folder,
                            'mid_count' => $midCount,
                            'final_count' => $finalCount,
                            'total_questions' => $midCount + $finalCount
                        ];
                    }
                }
            }
        }
    }
    
    // Sort by course code
    usort($courses, function($a, $b) {
        return strcmp($a['course_code'], $b['course_code']);
    });
    
    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'total_courses' => count($courses)
    ]);
}

/**
 * Get questions for a specific course
 */
function getQuestions($conn, $questionBankPath, $courseCode) {
    if (empty($courseCode)) {
        echo json_encode(['success' => false, 'message' => 'Course code required']);
        return;
    }
    
    // Clean course code
    $cleanCourseCode = str_replace(' ', '', $courseCode);
    
    // Get course info from database
    $stmt = $conn->prepare("
        SELECT course_id, course_code, course_name 
        FROM courses 
        WHERE REPLACE(course_code, ' ', '') = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $cleanCourseCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
        return;
    }
    
    $course = $result->fetch_assoc();
    
    // Find matching folder in question bank
    $courseFolders = array_diff(scandir($questionBankPath), ['.', '..']);
    $matchedFolder = null;
    
    foreach ($courseFolders as $folder) {
        if (str_replace(' ', '', $folder) === $cleanCourseCode) {
            $matchedFolder = $folder;
            break;
        }
    }
    
    if (!$matchedFolder) {
        echo json_encode([
            'success' => true,
            'course' => $course,
            'questions' => [],
            'mid_questions' => [],
            'final_questions' => []
        ]);
        return;
    }
    
    $folderPath = $questionBankPath . $matchedFolder;
    $questions = [
        'mid' => [],
        'final' => []
    ];
    
    // Scan mid-term folder
    if (is_dir($folderPath . '/mid')) {
        $midFiles = glob($folderPath . '/mid/*.pdf');
        foreach ($midFiles as $file) {
            $filename = basename($file);
            $questions['mid'][] = [
                'filename' => $filename,
                'path' => 'UIUQuestionBank/question/' . $matchedFolder . '/mid/' . $filename,
                'size' => filesize($file),
                'modified' => filemtime($file),
                'type' => 'Midterm',
                'exam_type' => 'mid',
                'trimester' => extractTrimester($filename)
            ];
        }
        
        // Sort by filename (which includes trimester)
        usort($questions['mid'], function($a, $b) {
            return strcmp($b['filename'], $a['filename']);
        });
    }
    
    // Scan final exam folder
    if (is_dir($folderPath . '/final')) {
        $finalFiles = glob($folderPath . '/final/*.pdf');
        foreach ($finalFiles as $file) {
            $filename = basename($file);
            $questions['final'][] = [
                'filename' => $filename,
                'path' => 'UIUQuestionBank/question/' . $matchedFolder . '/final/' . $filename,
                'size' => filesize($file),
                'modified' => filemtime($file),
                'type' => 'Final',
                'exam_type' => 'final',
                'trimester' => extractTrimester($filename)
            ];
        }
        
        // Sort by filename (which includes trimester)
        usort($questions['final'], function($a, $b) {
            return strcmp($b['filename'], $a['filename']);
        });
    }
    
    echo json_encode([
        'success' => true,
        'course' => $course,
        'questions' => array_merge($questions['mid'], $questions['final']),
        'mid_questions' => $questions['mid'],
        'final_questions' => $questions['final'],
        'total_questions' => count($questions['mid']) + count($questions['final'])
    ]);
}

/**
 * Get combined resources (uploaded + question bank)
 */
function getCombinedResources($conn, $student_id, $questionBankPath) {
    $resources = [];
    
    // 1. Get uploaded resources
    $stmt = $conn->prepare("
        SELECT 
            ur.*,
            rc.category_name,
            rc.category_icon,
            rc.category_color,
            c.course_code,
            c.course_name,
            s.full_name as student_name,
            t.trimester_name,
            (SELECT COUNT(*) FROM resource_likes WHERE resource_id = ur.resource_id) as likes_count,
            (SELECT COUNT(*) FROM resource_views WHERE resource_id = ur.resource_id) as views_count,
            (SELECT COUNT(*) FROM resource_comments WHERE resource_id = ur.resource_id) as comments_count,
            (SELECT COUNT(*) FROM resource_likes WHERE resource_id = ur.resource_id AND student_id = ?) as user_liked,
            (SELECT COUNT(*) FROM resource_bookmarks WHERE resource_id = ur.resource_id AND student_id = ?) as user_bookmarked,
            'uploaded' as source_type
        FROM uploaded_resources ur
        LEFT JOIN resource_categories rc ON ur.category_id = rc.category_id
        LEFT JOIN courses c ON ur.course_id = c.course_id
        LEFT JOIN students s ON ur.student_id = s.student_id
        LEFT JOIN trimesters t ON ur.trimester_id = t.trimester_id
        WHERE ur.is_approved = 1
        ORDER BY ur.uploaded_at DESC
    ");
    $stmt->bind_param('ss', $student_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $resources[] = $row;
    }
    
    // 2. Add question bank items
    if (is_dir($questionBankPath)) {
        $courseFolders = array_diff(scandir($questionBankPath), ['.', '..']);
        
        foreach ($courseFolders as $folder) {
            $folderPath = $questionBankPath . $folder;
            
            if (is_dir($folderPath)) {
                $cleanCourseCode = str_replace(' ', '', $folder);
                
                // Get course info
                $stmt = $conn->prepare("
                    SELECT course_id, course_code, course_name 
                    FROM courses 
                    WHERE REPLACE(course_code, ' ', '') = ?
                    LIMIT 1
                ");
                $stmt->bind_param('s', $cleanCourseCode);
                $stmt->execute();
                $courseResult = $stmt->get_result();
                
                if ($courseResult->num_rows > 0) {
                    $course = $courseResult->fetch_assoc();
                    
                    // Scan mid-term questions
                    if (is_dir($folderPath . '/mid')) {
                        $midFiles = glob($folderPath . '/mid/*.pdf');
                        foreach ($midFiles as $file) {
                            $filename = basename($file);
                            $resources[] = [
                                'resource_id' => 'qb_' . md5($file), // Unique ID for question bank items
                                'title' => $course['course_code'] . ' - Midterm ' . extractTrimester($filename),
                                'description' => 'Previous midterm question paper',
                                'resource_type' => 'file',
                                'file_path' => 'UIUQuestionBank/question/' . $folder . '/mid/' . $filename,
                                'file_name' => $filename,
                                'file_size' => filesize($file),
                                'file_type' => 'application/pdf',
                                'category_id' => 2, // Past Papers category
                                'category_name' => 'Past Papers',
                                'category_icon' => 'fas fa-file-alt',
                                'category_color' => '#3b82f6',
                                'course_id' => $course['course_id'],
                                'course_code' => $course['course_code'],
                                'course_name' => $course['course_name'],
                                'student_name' => 'UIU Question Bank',
                                'student_id' => 'system',
                                'uploaded_at' => date('Y-m-d H:i:s', filemtime($file)),
                                'views_count' => 0,
                                'likes_count' => 0,
                                'downloads_count' => 0,
                                'comments_count' => 0,
                                'user_liked' => 0,
                                'user_bookmarked' => 0,
                                'source_type' => 'questionbank',
                                'exam_type' => 'Midterm',
                                'trimester' => extractTrimester($filename)
                            ];
                        }
                    }
                    
                    // Scan final exam questions
                    if (is_dir($folderPath . '/final')) {
                        $finalFiles = glob($folderPath . '/final/*.pdf');
                        foreach ($finalFiles as $file) {
                            $filename = basename($file);
                            $resources[] = [
                                'resource_id' => 'qb_' . md5($file),
                                'title' => $course['course_code'] . ' - Final ' . extractTrimester($filename),
                                'description' => 'Previous final exam question paper',
                                'resource_type' => 'file',
                                'file_path' => 'UIUQuestionBank/question/' . $folder . '/final/' . $filename,
                                'file_name' => $filename,
                                'file_size' => filesize($file),
                                'file_type' => 'application/pdf',
                                'category_id' => 2,
                                'category_name' => 'Past Papers',
                                'category_icon' => 'fas fa-file-alt',
                                'category_color' => '#3b82f6',
                                'course_id' => $course['course_id'],
                                'course_code' => $course['course_code'],
                                'course_name' => $course['course_name'],
                                'student_name' => 'UIU Question Bank',
                                'student_id' => 'system',
                                'uploaded_at' => date('Y-m-d H:i:s', filemtime($file)),
                                'views_count' => 0,
                                'likes_count' => 0,
                                'downloads_count' => 0,
                                'comments_count' => 0,
                                'user_liked' => 0,
                                'user_bookmarked' => 0,
                                'source_type' => 'questionbank',
                                'exam_type' => 'Final',
                                'trimester' => extractTrimester($filename)
                            ];
                        }
                    }
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'resources' => $resources,
        'total' => count($resources)
    ]);
}

/**
 * Extract trimester from filename
 * Example: CSE3521_Final_241.pdf -> 241
 */
function extractTrimester($filename) {
    if (preg_match('/_(\d{3})\.pdf$/', $filename, $matches)) {
        return $matches[1];
    }
    return 'Unknown';
}

$conn->close();
