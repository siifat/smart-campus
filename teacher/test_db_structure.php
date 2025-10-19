<?php
// Test database structure
require_once '../config/database.php';

echo "<h2>Testing assignment_submissions table structure</h2>";

try {
    // Test 1: Get table structure
    echo "<h3>Test 1: Table Columns</h3>";
    $stmt = $pdo->query("DESCRIBE assignment_submissions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Test 2: Try to select the problematic columns
    echo "<h3>Test 2: Select marks_obtained column</h3>";
    $stmt = $pdo->query("SELECT submission_id, marks_obtained, feedback, graded_at FROM assignment_submissions LIMIT 1");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    // Test 3: Run the actual statistics query
    echo "<h3>Test 3: Statistics Query</h3>";
    $teacher_id = 3;
    $trimester_id = 1;
    $stats_query = "
        SELECT 
            COUNT(*) as total_submissions,
            SUM(CASE WHEN sub.status = 'submitted' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN sub.status = 'graded' THEN 1 ELSE 0 END) as graded_count,
            SUM(CASE WHEN sub.submitted_at > a.due_date THEN 1 ELSE 0 END) as late_count,
            AVG(CASE WHEN sub.status = 'graded' THEN sub.marks_obtained END) as average_marks
        FROM assignment_submissions sub
        JOIN assignments a ON sub.assignment_id = a.assignment_id
        WHERE a.teacher_id = ? AND a.trimester_id = ?
    ";
    $stmt = $pdo->prepare($stats_query);
    $stmt->execute([$teacher_id, $trimester_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($stats);
    echo "</pre>";
    
    echo "<h3 style='color: green;'>✓ All tests passed!</h3>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>✗ Error: " . $e->getMessage() . "</h3>";
    echo "<pre>";
    print_r($e);
    echo "</pre>";
}
?>
