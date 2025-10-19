-- Quick SQL to assign test courses to teacher ShArn (teacher_id = 3)
-- This allows testing of the assignments page

-- Assign teacher to existing student enrollments
UPDATE enrollments 
SET teacher_id = 3 
WHERE course_id IN (24, 25, 32, 33, 38, 39)
  AND trimester_id = 1
  AND status = 'enrolled'
LIMIT 20;

-- Verify the assignments
SELECT 
    c.course_code,
    c.course_name,
    e.section,
    COUNT(DISTINCT e.student_id) as student_count
FROM enrollments e
JOIN courses c ON e.course_id = c.course_id
WHERE e.teacher_id = 3
GROUP BY c.course_id, e.section
ORDER BY c.course_code, e.section;
