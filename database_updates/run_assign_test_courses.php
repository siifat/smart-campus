<?php
/**
 * Assign test courses to teacher for testing
 */

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=uiu_smart_campus;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "Assigning courses to teacher ShArn...\n\n";
    
    // Assign teacher to student enrollments
    $sql = "UPDATE enrollments 
            SET teacher_id = 3 
            WHERE course_id IN (24, 25, 32, 33, 38, 39)
              AND trimester_id = 1
              AND status = 'enrolled'
            LIMIT 20";
    
    $affected = $pdo->exec($sql);
    echo "✅ Updated {$affected} enrollment records\n\n";
    
    // Show assigned courses
    $stmt = $pdo->query("
        SELECT 
            c.course_code,
            c.course_name,
            e.section,
            COUNT(DISTINCT e.student_id) as student_count
        FROM enrollments e
        JOIN courses c ON e.course_id = c.course_id
        WHERE e.teacher_id = 3
        GROUP BY c.course_id, e.section
        ORDER BY c.course_code, e.section
    ");
    
    $courses = $stmt->fetchAll();
    
    echo "📚 Assigned Courses:\n";
    echo str_repeat("-", 70) . "\n";
    printf("%-15s %-35s %-10s %-10s\n", "Course Code", "Course Name", "Section", "Students");
    echo str_repeat("-", 70) . "\n";
    
    foreach ($courses as $course) {
        printf("%-15s %-35s %-10s %-10s\n", 
            $course['course_code'],
            substr($course['course_name'], 0, 35),
            $course['section'],
            $course['student_count']
        );
    }
    
    echo str_repeat("-", 70) . "\n";
    echo "\n✅ Total: " . count($courses) . " course-section combinations\n";
    echo "\n🎓 Teacher ShArn can now create assignments!\n";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
