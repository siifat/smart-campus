<?php
session_start();
require_once(__DIR__ . '/../config/database.php');

// Simulate being logged in as teacher
$_SESSION['teacher_logged_in'] = true;
$_SESSION['teacher_id'] = 3;

$teacher_id = 3;
$current_trimester_id = 1;

// Get URL parameters
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$selected_section = isset($_GET['section']) ? $_GET['section'] : null;

echo "<h1>DEBUG: Grades & Analytics Page</h1>";
echo "<p><strong>GET Parameters:</strong></p>";
echo "<pre>";
echo "course_id = " . var_export($selected_course_id, true) . "\n";
echo "section = " . var_export($selected_section, true) . "\n";
echo "</pre>";

// Get courses taught by teacher
echo "<h2>1. Courses Taught by Teacher</h2>";
$courses_query = "
    SELECT 
        c.course_id,
        c.course_code,
        c.course_name,
        e.section,
        COUNT(DISTINCT e.student_id) as student_count
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.teacher_id = ? AND e.trimester_id = ? AND e.status = 'enrolled'
    GROUP BY c.course_id, c.course_code, c.course_name, e.section
    ORDER BY c.course_code, e.section
";
$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->bind_param('ii', $teacher_id, $current_trimester_id);
$courses_stmt->execute();
$courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

echo "<pre>";
print_r($courses);
echo "</pre>";

// If no selection, use first course
if (!$selected_course_id) {
    $selected_course_id = $courses[0]['course_id'] ?? null;
    $selected_section = $courses[0]['section'] ?? null;
    echo "<p><strong>No selection, defaulting to:</strong> Course ID = $selected_course_id, Section = $selected_section</p>";
}

// Get assignments for this course/section
echo "<h2>2. Assignments for Course $selected_course_id, Section $selected_section</h2>";
$assign_stats_query = "
    SELECT 
        a.assignment_id,
        a.title,
        a.section as assignment_section,
        a.is_published,
        COUNT(DISTINCT sub.student_id) as submission_count,
        AVG(sub.marks_obtained) as avg_marks
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
$assign_stmt->bind_param('iisi', $teacher_id, $selected_course_id, $selected_section, $current_trimester_id);
$assign_stmt->execute();
$assignments = $assign_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$assign_stmt->close();

echo "<p><strong>Query Parameters:</strong> teacher_id=$teacher_id, course_id=$selected_course_id, section='$selected_section', trimester_id=$current_trimester_id</p>";
echo "<p><strong>Found " . count($assignments) . " assignments</strong></p>";
echo "<pre>";
print_r($assignments);
echo "</pre>";

// Get overall stats
echo "<h2>3. Overall Statistics</h2>";
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
$overall_stmt->bind_param('iiii', $teacher_id, $teacher_id, $selected_course_id, $current_trimester_id);
$overall_stmt->execute();
$overall_stats = $overall_stmt->get_result()->fetch_assoc();
$overall_stmt->close();

echo "<pre>";
print_r($overall_stats);
echo "</pre>";

// Get student performance
echo "<h2>4. Student Performance (Section $selected_section)</h2>";
$perf_query = "
    SELECT 
        s.student_id,
        s.full_name,
        COUNT(DISTINCT sub.submission_id) as total_submissions,
        AVG(CASE WHEN sub.status = 'graded' THEN sub.marks_obtained END) as avg_marks,
        SUM(CASE WHEN sub.status = 'graded' THEN sub.marks_obtained ELSE 0 END) as total_marks
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
    ORDER BY total_marks DESC
";
$perf_stmt = $conn->prepare($perf_query);
$perf_stmt->bind_param('iisi', $teacher_id, $selected_course_id, $selected_section, $current_trimester_id);
$perf_stmt->execute();
$students = $perf_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$perf_stmt->close();

echo "<p><strong>Found " . count($students) . " students</strong></p>";
echo "<pre>";
print_r($students);
echo "</pre>";

// Check what's in the database
echo "<h2>5. Raw Database Check</h2>";

echo "<h3>Enrollments for Course $selected_course_id</h3>";
$result = $conn->query("SELECT * FROM enrollments WHERE course_id = $selected_course_id AND teacher_id = $teacher_id AND trimester_id = $current_trimester_id");
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

echo "<h3>Assignments for Course $selected_course_id</h3>";
$result = $conn->query("SELECT * FROM assignments WHERE course_id = $selected_course_id AND teacher_id = $teacher_id AND trimester_id = $current_trimester_id");
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

echo "<h3>Submissions for these assignments</h3>";
$result = $conn->query("SELECT sub.*, a.title FROM assignment_submissions sub JOIN assignments a ON sub.assignment_id = a.assignment_id WHERE a.course_id = $selected_course_id AND a.teacher_id = $teacher_id");
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

?>
<hr>
<p><a href="?course_id=25&section=K">Test with Course 25, Section K</a></p>
<p><a href="grades.php">Go to actual Grades page</a></p>
