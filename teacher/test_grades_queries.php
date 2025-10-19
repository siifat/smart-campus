<?php
// Test script to verify grades.php queries work correctly
require_once(__DIR__ . '/../config/database.php');

echo "=== TESTING GRADES.PHP DATABASE QUERIES ===\n\n";

// Test data
$teacher_id = 3;
$course_id = 25;
$section = 'K';
$trimester_id = 1;

echo "Testing with:\n";
echo "Teacher ID: $teacher_id\n";
echo "Course ID: $course_id\n";
echo "Section: $section\n";
echo "Trimester ID: $trimester_id\n\n";

// Test 1: Course Details
echo "--- TEST 1: Course Details ---\n";
$course_stmt = $conn->prepare("
    SELECT c.*, COUNT(DISTINCT e.student_id) as total_students
    FROM courses c
    LEFT JOIN enrollments e ON c.course_id = e.course_id 
        AND e.section = ? 
        AND e.trimester_id = ?
        AND e.status = 'enrolled'
    WHERE c.course_id = ?
    GROUP BY c.course_id
");
$course_stmt->bind_param('sii', $section, $trimester_id, $course_id);
$course_stmt->execute();
$result = $course_stmt->get_result()->fetch_assoc();
echo "Course: " . ($result['course_code'] ?? 'NOT FOUND') . " - " . ($result['course_name'] ?? '') . "\n";
echo "Total Students in Section $section: " . ($result['total_students'] ?? 0) . "\n\n";
$course_stmt->close();

// Test 2: Assignment Statistics
echo "--- TEST 2: Assignment Statistics ---\n";
$assign_stats_query = "
    SELECT 
        a.assignment_id,
        a.title,
        a.assignment_type,
        a.total_marks,
        a.is_bonus,
        a.due_date,
        a.section as assignment_section,
        COUNT(DISTINCT sub.student_id) as submission_count,
        AVG(sub.marks_obtained) as avg_marks,
        MAX(sub.marks_obtained) as max_marks,
        MIN(sub.marks_obtained) as min_marks
    FROM assignments a
    LEFT JOIN assignment_submissions sub ON a.assignment_id = sub.assignment_id 
        AND sub.status IN ('graded', 'submitted')
    WHERE a.teacher_id = ? 
        AND a.course_id = ? 
        AND (a.section = ? OR a.section IS NULL)
        AND a.trimester_id = ?
        AND a.is_published = 1
    GROUP BY a.assignment_id
    ORDER BY a.due_date DESC
";
$assign_stmt = $conn->prepare($assign_stats_query);
$assign_stmt->bind_param('iisi', $teacher_id, $course_id, $section, $trimester_id);
$assign_stmt->execute();
$assignments = $assign_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo "Found " . count($assignments) . " assignments\n";
foreach ($assignments as $a) {
    echo "  - Assignment #" . $a['assignment_id'] . ": " . $a['title'] . "\n";
    echo "    Section: " . ($a['assignment_section'] ?? 'NULL (all sections)') . "\n";
    echo "    Submissions: " . $a['submission_count'] . "\n";
    echo "    Avg Marks: " . round($a['avg_marks'] ?? 0, 2) . "\n\n";
}
$assign_stmt->close();

// Test 3: Student Performance
echo "--- TEST 3: Student Performance ---\n";
$perf_query = "
    SELECT 
        s.student_id,
        s.full_name,
        s.email,
        COUNT(DISTINCT sub.submission_id) as total_submissions,
        AVG(CASE WHEN sub.status = 'graded' THEN sub.marks_obtained END) as avg_marks,
        SUM(CASE WHEN sub.status = 'graded' THEN sub.marks_obtained ELSE 0 END) as total_marks,
        COUNT(DISTINCT CASE WHEN a.is_bonus = 1 AND sub.submission_id IS NOT NULL THEN sub.submission_id END) as bonus_submissions,
        COUNT(DISTINCT CASE WHEN sub.is_late = 1 THEN sub.submission_id END) as late_submissions
    FROM students s
    JOIN enrollments e ON s.student_id = e.student_id
    LEFT JOIN assignments a ON e.course_id = a.course_id 
        AND (a.section = e.section OR a.section IS NULL)
        AND e.trimester_id = a.trimester_id
        AND a.is_published = 1
    LEFT JOIN assignment_submissions sub ON a.assignment_id = sub.assignment_id 
        AND s.student_id = sub.student_id
    WHERE e.teacher_id = ? 
        AND e.course_id = ? 
        AND e.section = ?
        AND e.trimester_id = ?
        AND e.status = 'enrolled'
    GROUP BY s.student_id
    ORDER BY total_marks DESC, avg_marks DESC
";
$perf_stmt = $conn->prepare($perf_query);
$perf_stmt->bind_param('iisi', $teacher_id, $course_id, $section, $trimester_id);
$perf_stmt->execute();
$students = $perf_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo "Found " . count($students) . " students\n";
foreach ($students as $st) {
    echo "  - " . $st['student_id'] . " (" . $st['full_name'] . ")\n";
    echo "    Total Submissions: " . $st['total_submissions'] . "\n";
    echo "    Total Marks: " . round($st['total_marks'] ?? 0, 2) . "\n";
    echo "    Average: " . round($st['avg_marks'] ?? 0, 2) . "% \n";
    echo "    Bonus Work: " . $st['bonus_submissions'] . "\n";
    echo "    Late Submissions: " . $st['late_submissions'] . "\n\n";
}
$perf_stmt->close();

// Test 4: Overall Statistics
echo "--- TEST 4: Overall Statistics (All Sections) ---\n";
$overall_query = "
    SELECT 
        COUNT(DISTINCT e.student_id) as total_students_all_sections,
        COUNT(DISTINCT e.section) as total_sections,
        COUNT(DISTINCT a.assignment_id) as total_assignments,
        COUNT(DISTINCT sub.submission_id) as total_submissions,
        AVG(CASE WHEN sub.status = 'graded' THEN sub.marks_obtained END) as overall_avg_marks
    FROM enrollments e
    LEFT JOIN assignments a ON e.course_id = a.course_id 
        AND e.trimester_id = a.trimester_id
        AND a.teacher_id = ?
        AND a.is_published = 1
        AND (a.section = e.section OR a.section IS NULL)
    LEFT JOIN assignment_submissions sub ON a.assignment_id = sub.assignment_id
        AND sub.student_id = e.student_id
    WHERE e.teacher_id = ? 
        AND e.course_id = ? 
        AND e.trimester_id = ?
        AND e.status = 'enrolled'
";
$overall_stmt = $conn->prepare($overall_query);
$overall_stmt->bind_param('iiii', $teacher_id, $teacher_id, $course_id, $trimester_id);
$overall_stmt->execute();
$overall = $overall_stmt->get_result()->fetch_assoc();
echo "Total Students (All Sections): " . $overall['total_students_all_sections'] . "\n";
echo "Total Sections: " . $overall['total_sections'] . "\n";
echo "Total Assignments: " . $overall['total_assignments'] . "\n";
echo "Total Submissions: " . $overall['total_submissions'] . "\n";
echo "Overall Average: " . round($overall['overall_avg_marks'] ?? 0, 2) . "%\n\n";
$overall_stmt->close();

// Test 5: Section Comparison
echo "--- TEST 5: Section Comparison ---\n";
$section_query = "
    SELECT 
        e.section,
        COUNT(DISTINCT e.student_id) as student_count,
        COUNT(DISTINCT sub.submission_id) as submission_count,
        AVG(CASE WHEN sub.status = 'graded' THEN sub.marks_obtained END) as avg_marks,
        COUNT(DISTINCT CASE WHEN sub.is_late = 1 THEN sub.submission_id END) as late_count
    FROM enrollments e
    LEFT JOIN assignments a ON e.course_id = a.course_id 
        AND (a.section = e.section OR a.section IS NULL)
        AND e.trimester_id = a.trimester_id
        AND a.teacher_id = ?
        AND a.is_published = 1
    LEFT JOIN assignment_submissions sub ON a.assignment_id = sub.assignment_id
        AND sub.student_id = e.student_id
    WHERE e.teacher_id = ? 
        AND e.course_id = ? 
        AND e.trimester_id = ?
        AND e.status = 'enrolled'
    GROUP BY e.section
    ORDER BY e.section
";
$section_stmt = $conn->prepare($section_query);
$section_stmt->bind_param('iiii', $teacher_id, $teacher_id, $course_id, $trimester_id);
$section_stmt->execute();
$sections = $section_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo "Found " . count($sections) . " sections\n";
foreach ($sections as $sec) {
    echo "  Section " . $sec['section'] . ":\n";
    echo "    Students: " . $sec['student_count'] . "\n";
    echo "    Submissions: " . $sec['submission_count'] . "\n";
    echo "    Average Marks: " . round($sec['avg_marks'] ?? 0, 2) . "%\n";
    echo "    Late Submissions: " . $sec['late_count'] . "\n\n";
}
$section_stmt->close();

echo "=== ALL TESTS COMPLETE ===\n";
?>
