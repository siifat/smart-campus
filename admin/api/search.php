<?php
/**
 * Global Search API
 * Searches across multiple tables
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once('../../config/database.php');

if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$search_term = $_GET['q'] ?? '';
$results = [];

if (strlen($search_term) < 2) {
    header('Content-Type: application/json');
    echo json_encode(['results' => []]);
    exit;
}

$search_term = $conn->real_escape_string($search_term);

try {
    // Search Students
    $students = $conn->query("SELECT student_id as id, full_name as name, student_id as detail, 
                              'student' as type, 'fas fa-user-graduate' as icon
                              FROM students 
                              WHERE full_name LIKE '%$search_term%' 
                              OR student_id LIKE '%$search_term%'
                              OR email LIKE '%$search_term%'
                              LIMIT 5");
    if ($students) {
        while ($row = $students->fetch_assoc()) {
            $row['url'] = "manage.php?table=students&highlight=" . $row['id'];
            $results[] = $row;
        }
    }

    // Search Teachers
    $teachers = $conn->query("SELECT teacher_id as id, full_name as name, initial as detail, 
                              'teacher' as type, 'fas fa-chalkboard-teacher' as icon
                              FROM teachers 
                              WHERE full_name LIKE '%$search_term%' 
                              OR initial LIKE '%$search_term%'
                              OR email LIKE '%$search_term%'
                              LIMIT 5");
    if ($teachers) {
        while ($row = $teachers->fetch_assoc()) {
            $row['url'] = "manage.php?table=teachers&highlight=" . $row['id'];
            $results[] = $row;
        }
    }

    // Search Courses
    $courses = $conn->query("SELECT course_id as id, course_name as name, course_code as detail, 
                             'course' as type, 'fas fa-book' as icon
                             FROM courses 
                             WHERE course_name LIKE '%$search_term%' 
                             OR course_code LIKE '%$search_term%'
                             LIMIT 5");
    if ($courses) {
        while ($row = $courses->fetch_assoc()) {
            $row['url'] = "manage.php?table=courses&highlight=" . $row['id'];
            $results[] = $row;
        }
    }

    // Search Departments
    $departments = $conn->query("SELECT department_id as id, department_name as name, department_code as detail, 
                                 'department' as type, 'fas fa-building' as icon
                                 FROM departments 
                                 WHERE department_name LIKE '%$search_term%' 
                                 OR department_code LIKE '%$search_term%'
                                 LIMIT 3");
    if ($departments) {
        while ($row = $departments->fetch_assoc()) {
            $row['url'] = "manage.php?table=departments&highlight=" . $row['id'];
            $results[] = $row;
        }
    }

    // Search Programs
    $programs = $conn->query("SELECT program_id as id, program_name as name, program_code as detail, 
                              'program' as type, 'fas fa-graduation-cap' as icon
                              FROM programs 
                              WHERE program_name LIKE '%$search_term%' 
                              OR program_code LIKE '%$search_term%'
                              LIMIT 3");
    if ($programs) {
        while ($row = $programs->fetch_assoc()) {
            $row['url'] = "manage.php?table=programs&highlight=" . $row['id'];
            $results[] = $row;
        }
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Search error: ' . $e->getMessage(),
        'results' => []
    ]);
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'results' => $results,
    'count' => count($results),
    'query' => $search_term
]);
