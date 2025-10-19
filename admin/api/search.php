<?php
/**
 * Enhanced Global Search API with Fuzzy Finding
 * Features:
 * - Fuzzy matching (handles typos, partial matches)
 * - Relevance scoring
 * - Auto-suggestions
 * - Multi-field search
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
$suggestions = [];

if (strlen($search_term) < 2) {
    header('Content-Type: application/json');
    echo json_encode(['results' => [], 'suggestions' => []]);
    exit;
}

$search_term = $conn->real_escape_string($search_term);
$search_words = explode(' ', $search_term);

/**
 * Fuzzy Search Helper Function
 * Generates SQL LIKE patterns for fuzzy matching
 */
function generateFuzzyPatterns($term) {
    $patterns = [];
    
    // Exact match (highest priority)
    $patterns[] = "%$term%";
    
    // Start with term
    $patterns[] = "$term%";
    
    // Words in any order
    $words = explode(' ', $term);
    if (count($words) > 1) {
        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $patterns[] = "%$word%";
            }
        }
    }
    
    // Handle common typos (missing/extra letters)
    if (strlen($term) >= 4) {
        // Missing one letter
        for ($i = 0; $i < strlen($term); $i++) {
            $typo = substr($term, 0, $i) . substr($term, $i + 1);
            if (strlen($typo) >= 3) {
                $patterns[] = "%$typo%";
            }
        }
    }
    
    return array_unique($patterns);
}

/**
 * Calculate relevance score
 */
function calculateRelevance($haystack, $needle) {
    $haystack = strtolower($haystack);
    $needle = strtolower($needle);
    
    $score = 0;
    
    // Exact match = highest score
    if ($haystack === $needle) return 100;
    
    // Starts with search term = high score
    if (strpos($haystack, $needle) === 0) $score += 80;
    
    // Contains exact term = medium-high score
    if (strpos($haystack, $needle) !== false) $score += 60;
    
    // Word boundary match
    if (preg_match('/\b' . preg_quote($needle, '/') . '/i', $haystack)) $score += 40;
    
    // Individual word matches
    $words = explode(' ', $needle);
    foreach ($words as $word) {
        if (strlen($word) > 2 && stripos($haystack, $word) !== false) {
            $score += 20;
        }
    }
    
    // Length similarity (shorter differences = better match)
    $lengthDiff = abs(strlen($haystack) - strlen($needle));
    $score += max(0, 20 - $lengthDiff);
    
    return min(100, $score);
}

$search_term = $conn->real_escape_string($search_term);

try {
    // Search Students with Fuzzy Matching
    $student_conditions = [];
    $patterns = generateFuzzyPatterns($search_term);
    
    foreach ($patterns as $pattern) {
        $student_conditions[] = "full_name LIKE '$pattern'";
        $student_conditions[] = "student_id LIKE '$pattern'";
        $student_conditions[] = "email LIKE '$pattern'";
    }
    
    $student_query = "SELECT student_id as id, full_name as name, student_id as detail, email,
                      'student' as type, 'fas fa-user-graduate' as icon
                      FROM students 
                      WHERE " . implode(' OR ', $student_conditions) . "
                      LIMIT 15";
    
    $students = $conn->query($student_query);
    if ($students) {
        while ($row = $students->fetch_assoc()) {
            // Calculate relevance score
            $score = max(
                calculateRelevance($row['name'], $search_term),
                calculateRelevance($row['detail'], $search_term),
                calculateRelevance($row['email'], $search_term)
            );
            
            $row['url'] = "manage.php?table=students&highlight=" . $row['id'];
            $row['score'] = $score;
            $row['match_field'] = stripos($row['name'], $search_term) !== false ? 'name' : 
                                 (stripos($row['detail'], $search_term) !== false ? 'id' : 'email');
            unset($row['email']); // Don't send email to frontend
            $results[] = $row;
        }
    }

    // Search Teachers with Fuzzy Matching
    $teacher_conditions = [];
    foreach ($patterns as $pattern) {
        $teacher_conditions[] = "full_name LIKE '$pattern'";
        $teacher_conditions[] = "initial LIKE '$pattern'";
        $teacher_conditions[] = "email LIKE '$pattern'";
    }
    
    $teacher_query = "SELECT teacher_id as id, full_name as name, initial as detail, email,
                      'teacher' as type, 'fas fa-chalkboard-teacher' as icon
                      FROM teachers 
                      WHERE " . implode(' OR ', $teacher_conditions) . "
                      LIMIT 10";
    
    $teachers = $conn->query($teacher_query);
    if ($teachers) {
        while ($row = $teachers->fetch_assoc()) {
            $score = max(
                calculateRelevance($row['name'], $search_term),
                calculateRelevance($row['detail'], $search_term),
                calculateRelevance($row['email'], $search_term)
            );
            
            $row['url'] = "manage.php?table=teachers&highlight=" . $row['id'];
            $row['score'] = $score;
            $row['match_field'] = stripos($row['name'], $search_term) !== false ? 'name' : 'initial';
            unset($row['email']);
            $results[] = $row;
        }
    }

    // Search Courses with Fuzzy Matching
    $course_conditions = [];
    foreach ($patterns as $pattern) {
        $course_conditions[] = "course_name LIKE '$pattern'";
        $course_conditions[] = "course_code LIKE '$pattern'";
    }
    
    $course_query = "SELECT course_id as id, course_name as name, course_code as detail, 
                     'course' as type, 'fas fa-book' as icon
                     FROM courses 
                     WHERE " . implode(' OR ', $course_conditions) . "
                     LIMIT 10";
    
    $courses = $conn->query($course_query);
    if ($courses) {
        while ($row = $courses->fetch_assoc()) {
            $score = max(
                calculateRelevance($row['name'], $search_term),
                calculateRelevance($row['detail'], $search_term)
            );
            
            $row['url'] = "manage.php?table=courses&highlight=" . $row['id'];
            $row['score'] = $score;
            $row['match_field'] = stripos($row['name'], $search_term) !== false ? 'name' : 'code';
            $results[] = $row;
        }
    }

    // Search Departments with Fuzzy Matching
    $dept_conditions = [];
    foreach ($patterns as $pattern) {
        $dept_conditions[] = "department_name LIKE '$pattern'";
        $dept_conditions[] = "department_code LIKE '$pattern'";
    }
    
    $dept_query = "SELECT department_id as id, department_name as name, department_code as detail, 
                   'department' as type, 'fas fa-building' as icon
                   FROM departments 
                   WHERE " . implode(' OR ', $dept_conditions) . "
                   LIMIT 5";
    
    $departments = $conn->query($dept_query);
    if ($departments) {
        while ($row = $departments->fetch_assoc()) {
            $score = max(
                calculateRelevance($row['name'], $search_term),
                calculateRelevance($row['detail'], $search_term)
            );
            
            $row['url'] = "manage.php?table=departments&highlight=" . $row['id'];
            $row['score'] = $score;
            $results[] = $row;
        }
    }

    // Search Programs with Fuzzy Matching
    $program_conditions = [];
    foreach ($patterns as $pattern) {
        $program_conditions[] = "program_name LIKE '$pattern'";
        $program_conditions[] = "program_code LIKE '$pattern'";
    }
    
    $program_query = "SELECT program_id as id, program_name as name, program_code as detail, 
                      'program' as type, 'fas fa-graduation-cap' as icon
                      FROM programs 
                      WHERE " . implode(' OR ', $program_conditions) . "
                      LIMIT 5";
    
    $programs = $conn->query($program_query);
    if ($programs) {
        while ($row = $programs->fetch_assoc()) {
            $score = max(
                calculateRelevance($row['name'], $search_term),
                calculateRelevance($row['detail'], $search_term)
            );
            
            $row['url'] = "manage.php?table=programs&highlight=" . $row['id'];
            $row['score'] = $score;
            $results[] = $row;
        }
    }
    
    // Sort results by relevance score (highest first)
    usort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Limit to top 20 most relevant results
    $results = array_slice($results, 0, 20);
    
    // Generate search suggestions based on common searches
    if (count($results) > 0) {
        $top_types = array_count_values(array_column($results, 'type'));
        arsort($top_types);
        
        foreach ($top_types as $type => $count) {
            if ($count >= 2) {
                $suggestions[] = [
                    'text' => "Search more {$type}s",
                    'filter' => $type,
                    'count' => $count
                ];
            }
        }
    }
    
    // Add quick action suggestions
    if (strlen($search_term) >= 3) {
        $suggestions[] = [
            'text' => "View all results for \"$search_term\"",
            'action' => 'view_all',
            'query' => $search_term
        ];
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Search error: ' . $e->getMessage(),
        'results' => [],
        'suggestions' => []
    ]);
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'results' => $results,
    'suggestions' => $suggestions,
    'count' => count($results),
    'query' => $search_term,
    'fuzzy' => true,
    'timestamp' => microtime(true)
]);
