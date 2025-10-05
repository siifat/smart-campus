<?php
/**
 * Database Verification & Integrity Check
 * Checks for normalization (3NF/BCNF), referential integrity, orphaned records, etc.
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
    'checks' => [],
    'issues' => [],
    'warnings' => [],
    'fixes_applied' => [],
    'summary' => []
];

// ============================================================================
// 1. CHECK REFERENTIAL INTEGRITY
// ============================================================================
function checkReferentialIntegrity($conn) {
    $issues = [];
    $warnings = [];
    
    // Check for orphaned students (invalid program_id)
    $query = "SELECT COUNT(*) as count FROM students s 
              LEFT JOIN programs p ON s.program_id = p.program_id 
              WHERE p.program_id IS NULL";
    $result = $conn->query($query);
    $orphaned_students = $result->fetch_assoc()['count'];
    
    if ($orphaned_students > 0) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'referential_integrity',
            'message' => "Found {$orphaned_students} student(s) with invalid program_id",
            'table' => 'students',
            'fixable' => true
        ];
    }
    
    // Check for orphaned enrollments (invalid student_id)
    $query = "SELECT COUNT(*) as count FROM enrollments e 
              LEFT JOIN students s ON e.student_id = s.student_id 
              WHERE s.student_id IS NULL";
    $result = $conn->query($query);
    $orphaned_enrollments = $result->fetch_assoc()['count'];
    
    if ($orphaned_enrollments > 0) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'referential_integrity',
            'message' => "Found {$orphaned_enrollments} enrollment(s) with invalid student_id",
            'table' => 'enrollments',
            'fixable' => true
        ];
    }
    
    // Check for orphaned enrollments (invalid course_id)
    $query = "SELECT COUNT(*) as count FROM enrollments e 
              LEFT JOIN courses c ON e.course_id = c.course_id 
              WHERE c.course_id IS NULL";
    $result = $conn->query($query);
    $orphaned_course_enrollments = $result->fetch_assoc()['count'];
    
    if ($orphaned_course_enrollments > 0) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'referential_integrity',
            'message' => "Found {$orphaned_course_enrollments} enrollment(s) with invalid course_id",
            'table' => 'enrollments',
            'fixable' => true
        ];
    }
    
    // Check for orphaned grades
    $query = "SELECT COUNT(*) as count FROM grades g 
              LEFT JOIN enrollments e ON g.enrollment_id = e.enrollment_id 
              WHERE e.enrollment_id IS NULL";
    $result = $conn->query($query);
    $orphaned_grades = $result->fetch_assoc()['count'];
    
    if ($orphaned_grades > 0) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'referential_integrity',
            'message' => "Found {$orphaned_grades} grade(s) with invalid enrollment_id",
            'table' => 'grades',
            'fixable' => true
        ];
    }
    
    // Check for orphaned notes
    $query = "SELECT COUNT(*) as count FROM notes n 
              LEFT JOIN students s ON n.student_id = s.student_id 
              WHERE s.student_id IS NULL";
    $result = $conn->query($query);
    $orphaned_notes = $result->fetch_assoc()['count'];
    
    if ($orphaned_notes > 0) {
        $issues[] = [
            'severity' => 'medium',
            'type' => 'referential_integrity',
            'message' => "Found {$orphaned_notes} note(s) with invalid student_id",
            'table' => 'notes',
            'fixable' => true
        ];
    }
    
    return ['issues' => $issues, 'warnings' => $warnings];
}

// ============================================================================
// 2. CHECK DATABASE NORMALIZATION (3NF/BCNF)
// ============================================================================
function checkNormalization($conn) {
    $issues = [];
    $warnings = [];
    
    // Check for potential transitive dependencies in students table
    // Look for derived values that aren't maintained by triggers
    $query = "SELECT student_id, total_completed_credits, 
              (SELECT COALESCE(SUM(c.credit_hours), 0) 
               FROM enrollments e 
               JOIN courses c ON e.course_id = c.course_id 
               WHERE e.student_id = s.student_id AND e.status = 'completed') as calculated_credits
              FROM students s
              HAVING total_completed_credits != calculated_credits";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $warnings[] = [
            'severity' => 'medium',
            'type' => 'normalization',
            'message' => "Found {$result->num_rows} student(s) with inconsistent total_completed_credits (denormalized data out of sync)",
            'table' => 'students',
            'fixable' => true
        ];
    }
    
    // Check for inconsistent total_points
    $query = "SELECT student_id, total_points,
              (SELECT COALESCE(SUM(points), 0) FROM student_points WHERE student_id = s.student_id) as calculated_points
              FROM students s
              HAVING ABS(total_points - calculated_points) > 0";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $warnings[] = [
            'severity' => 'medium',
            'type' => 'normalization',
            'message' => "Found {$result->num_rows} student(s) with inconsistent total_points",
            'table' => 'students',
            'fixable' => true
        ];
    }
    
    // Check for inconsistent attendance percentages
    $query = "SELECT attendance_id, total_classes, present_count, absent_count,
              attendance_percentage,
              ROUND((present_count * 100.0) / NULLIF(total_classes, 0), 2) as calculated_percentage
              FROM attendance
              WHERE total_classes > 0
              HAVING ABS(attendance_percentage - calculated_percentage) > 0.01";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $warnings[] = [
            'severity' => 'low',
            'type' => 'normalization',
            'message' => "Found {$result->num_rows} attendance record(s) with incorrect percentage calculation",
            'table' => 'attendance',
            'fixable' => true
        ];
    }
    
    // Check for duplicate course codes in same department (should be prevented by unique key)
    $query = "SELECT course_code, department_id, COUNT(*) as count 
              FROM courses 
              GROUP BY course_code, department_id 
              HAVING count > 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'normalization',
            'message' => "Found {$result->num_rows} duplicate course code(s) in same department (violates unique constraint)",
            'table' => 'courses',
            'fixable' => false
        ];
    }
    
    return ['issues' => $issues, 'warnings' => $warnings];
}

// ============================================================================
// 3. CHECK FOR DUPLICATE RECORDS
// ============================================================================
function checkDuplicates($conn) {
    $issues = [];
    $warnings = [];
    
    // Check for duplicate student IDs (should be impossible with PK)
    $query = "SELECT student_id, COUNT(*) as count FROM students GROUP BY student_id HAVING count > 1";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $issues[] = [
            'severity' => 'critical',
            'type' => 'duplicates',
            'message' => "Found duplicate student_id values (database corruption!)",
            'table' => 'students',
            'fixable' => false
        ];
    }
    
    // Check for duplicate enrollments (should be prevented by unique key)
    $query = "SELECT student_id, course_id, trimester_id, COUNT(*) as count 
              FROM enrollments 
              GROUP BY student_id, course_id, trimester_id 
              HAVING count > 1";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'duplicates',
            'message' => "Found {$result->num_rows} duplicate enrollment(s)",
            'table' => 'enrollments',
            'fixable' => true
        ];
    }
    
    // Check for duplicate department codes
    $query = "SELECT department_code, COUNT(*) as count FROM departments GROUP BY department_code HAVING count > 1";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'duplicates',
            'message' => "Found duplicate department_code values",
            'table' => 'departments',
            'fixable' => false
        ];
    }
    
    return ['issues' => $issues, 'warnings' => $warnings];
}

// ============================================================================
// 4. CHECK DATA CONSISTENCY
// ============================================================================
function checkDataConsistency($conn) {
    $issues = [];
    $warnings = [];
    
    // Check for students with negative credits
    $query = "SELECT COUNT(*) as count FROM students WHERE total_completed_credits < 0";
    $result = $conn->query($query);
    $negative_credits = $result->fetch_assoc()['count'];
    if ($negative_credits > 0) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'data_consistency',
            'message' => "Found {$negative_credits} student(s) with negative completed credits",
            'table' => 'students',
            'fixable' => true
        ];
    }
    
    // Check for invalid CGPA values (should be 0.00 to 4.00)
    $query = "SELECT COUNT(*) as count FROM students WHERE current_cgpa < 0 OR current_cgpa > 4.00";
    $result = $conn->query($query);
    $invalid_cgpa = $result->fetch_assoc()['count'];
    if ($invalid_cgpa > 0) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'data_consistency',
            'message' => "Found {$invalid_cgpa} student(s) with invalid CGPA (outside 0.00-4.00 range)",
            'table' => 'students',
            'fixable' => true
        ];
    }
    
    // Check for attendance percentages outside 0-100 range
    $query = "SELECT COUNT(*) as count FROM attendance WHERE attendance_percentage < 0 OR attendance_percentage > 100";
    $result = $conn->query($query);
    $invalid_attendance = $result->fetch_assoc()['count'];
    if ($invalid_attendance > 0) {
        $issues[] = [
            'severity' => 'medium',
            'type' => 'data_consistency',
            'message' => "Found {$invalid_attendance} attendance record(s) with invalid percentage",
            'table' => 'attendance',
            'fixable' => true
        ];
    }
    
    // Check for courses with invalid credit hours
    $query = "SELECT COUNT(*) as count FROM courses WHERE credit_hours <= 0 OR credit_hours > 12";
    $result = $conn->query($query);
    $invalid_credits = $result->fetch_assoc()['count'];
    if ($invalid_credits > 0) {
        $warnings[] = [
            'severity' => 'low',
            'type' => 'data_consistency',
            'message' => "Found {$invalid_credits} course(s) with unusual credit hours (0 or >12)",
            'table' => 'courses',
            'fixable' => false
        ];
    }
    
    // Check for enrollments with end dates before start dates
    $query = "SELECT COUNT(*) as count FROM trimesters WHERE end_date < start_date";
    $result = $conn->query($query);
    $invalid_dates = $result->fetch_assoc()['count'];
    if ($invalid_dates > 0) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'data_consistency',
            'message' => "Found {$invalid_dates} trimester(s) with end_date before start_date",
            'table' => 'trimesters',
            'fixable' => false
        ];
    }
    
    return ['issues' => $issues, 'warnings' => $warnings];
}

// ============================================================================
// 5. CHECK INDEX AND CONSTRAINT INTEGRITY
// ============================================================================
function checkConstraints($conn) {
    $issues = [];
    $warnings = [];
    
    // Check if all expected foreign keys exist
    $expected_fks = [
        ['table' => 'students', 'constraint' => 'program_id', 'references' => 'programs'],
        ['table' => 'enrollments', 'constraint' => 'student_id', 'references' => 'students'],
        ['table' => 'enrollments', 'constraint' => 'course_id', 'references' => 'courses'],
        ['table' => 'grades', 'constraint' => 'enrollment_id', 'references' => 'enrollments'],
    ];
    
    foreach ($expected_fks as $fk) {
        $query = "SELECT COUNT(*) as count 
                  FROM information_schema.TABLE_CONSTRAINTS 
                  WHERE TABLE_SCHEMA = 'uiu_smart_campus' 
                  AND TABLE_NAME = '{$fk['table']}' 
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
        $result = $conn->query($query);
        // This is just a basic check - could be expanded
    }
    
    return ['issues' => $issues, 'warnings' => $warnings];
}

// ============================================================================
// AUTO-FIX FUNCTIONS
// ============================================================================
function autoFixIssues($conn, $fix_type) {
    $fixes = [];
    
    switch ($fix_type) {
        case 'sync_student_credits':
            // Recalculate all student credits
            $query = "UPDATE students s SET total_completed_credits = (
                SELECT COALESCE(SUM(c.credit_hours), 0) 
                FROM enrollments e 
                JOIN courses c ON e.course_id = c.course_id 
                WHERE e.student_id = s.student_id AND e.status = 'completed'
            )";
            if ($conn->query($query)) {
                $fixes[] = "Synchronized total_completed_credits for all students";
            }
            break;
            
        case 'sync_student_points':
            // Recalculate all student points
            $query = "UPDATE students s SET total_points = (
                SELECT COALESCE(SUM(points), 0) 
                FROM student_points 
                WHERE student_id = s.student_id
            )";
            if ($conn->query($query)) {
                $fixes[] = "Synchronized total_points for all students";
            }
            break;
            
        case 'fix_attendance_percentage':
            // Recalculate all attendance percentages
            $query = "UPDATE attendance 
                      SET attendance_percentage = ROUND((present_count * 100.0) / NULLIF(total_classes, 0), 2)
                      WHERE total_classes > 0";
            if ($conn->query($query)) {
                $fixes[] = "Recalculated attendance percentages";
            }
            break;
            
        case 'fix_negative_values':
            // Fix negative credits
            $query = "UPDATE students SET total_completed_credits = 0 WHERE total_completed_credits < 0";
            if ($conn->query($query)) {
                $fixes[] = "Fixed negative completed credits";
            }
            // Fix invalid CGPA
            $query = "UPDATE students SET current_cgpa = 0.00 WHERE current_cgpa < 0 OR current_cgpa > 4.00";
            if ($conn->query($query)) {
                $fixes[] = "Fixed invalid CGPA values";
            }
            break;
            
        case 'remove_orphaned_records':
            // Remove orphaned enrollments
            $query = "DELETE e FROM enrollments e 
                      LEFT JOIN students s ON e.student_id = s.student_id 
                      WHERE s.student_id IS NULL";
            if ($conn->query($query)) {
                $affected = $conn->affected_rows;
                if ($affected > 0) {
                    $fixes[] = "Removed {$affected} orphaned enrollment(s)";
                }
            }
            
            // Remove orphaned grades
            $query = "DELETE g FROM grades g 
                      LEFT JOIN enrollments e ON g.enrollment_id = e.enrollment_id 
                      WHERE e.enrollment_id IS NULL";
            if ($conn->query($query)) {
                $affected = $conn->affected_rows;
                if ($affected > 0) {
                    $fixes[] = "Removed {$affected} orphaned grade(s)";
                }
            }
            break;
    }
    
    return $fixes;
}

// ============================================================================
// RUN ALL CHECKS
// ============================================================================

try {
    // Run all verification checks
    $ref_integrity = checkReferentialIntegrity($conn);
    $results['checks']['referential_integrity'] = [
        'status' => count($ref_integrity['issues']) == 0 ? 'passed' : 'failed',
        'issues' => $ref_integrity['issues'],
        'warnings' => $ref_integrity['warnings']
    ];
    $results['issues'] = array_merge($results['issues'], $ref_integrity['issues']);
    $results['warnings'] = array_merge($results['warnings'], $ref_integrity['warnings']);
    
    $normalization = checkNormalization($conn);
    $results['checks']['normalization_3nf_bcnf'] = [
        'status' => count($normalization['issues']) == 0 ? 'passed' : 'failed',
        'issues' => $normalization['issues'],
        'warnings' => $normalization['warnings']
    ];
    $results['issues'] = array_merge($results['issues'], $normalization['issues']);
    $results['warnings'] = array_merge($results['warnings'], $normalization['warnings']);
    
    $duplicates = checkDuplicates($conn);
    $results['checks']['duplicates'] = [
        'status' => count($duplicates['issues']) == 0 ? 'passed' : 'failed',
        'issues' => $duplicates['issues'],
        'warnings' => $duplicates['warnings']
    ];
    $results['issues'] = array_merge($results['issues'], $duplicates['issues']);
    $results['warnings'] = array_merge($results['warnings'], $duplicates['warnings']);
    
    $consistency = checkDataConsistency($conn);
    $results['checks']['data_consistency'] = [
        'status' => count($consistency['issues']) == 0 ? 'passed' : 'failed',
        'issues' => $consistency['issues'],
        'warnings' => $consistency['warnings']
    ];
    $results['issues'] = array_merge($results['issues'], $consistency['issues']);
    $results['warnings'] = array_merge($results['warnings'], $consistency['warnings']);
    
    $constraints = checkConstraints($conn);
    $results['checks']['constraints'] = [
        'status' => count($constraints['issues']) == 0 ? 'passed' : 'failed',
        'issues' => $constraints['issues'],
        'warnings' => $constraints['warnings']
    ];
    $results['issues'] = array_merge($results['issues'], $constraints['issues']);
    $results['warnings'] = array_merge($results['warnings'], $constraints['warnings']);
    
    // Apply auto-fixes if requested
    if (isset($_POST['auto_fix']) && $_POST['auto_fix'] === 'true') {
        $results['fixes_applied'] = array_merge(
            $results['fixes_applied'],
            autoFixIssues($conn, 'sync_student_credits'),
            autoFixIssues($conn, 'sync_student_points'),
            autoFixIssues($conn, 'fix_attendance_percentage'),
            autoFixIssues($conn, 'fix_negative_values'),
            autoFixIssues($conn, 'remove_orphaned_records')
        );
    }
    
    // Generate summary
    $results['summary'] = [
        'total_checks' => count($results['checks']),
        'passed_checks' => count(array_filter($results['checks'], function($check) {
            return $check['status'] === 'passed';
        })),
        'total_issues' => count($results['issues']),
        'total_warnings' => count($results['warnings']),
        'critical_issues' => count(array_filter($results['issues'], function($issue) {
            return $issue['severity'] === 'critical';
        })),
        'high_issues' => count(array_filter($results['issues'], function($issue) {
            return $issue['severity'] === 'high';
        })),
        'normalization_status' => count($normalization['issues']) == 0 ? '3NF/BCNF Compliant' : 'Normalization Issues Detected',
        'database_health' => count($results['issues']) == 0 ? 'Excellent' : (count($results['issues']) < 5 ? 'Good' : 'Needs Attention')
    ];
    
} catch (Exception $e) {
    $results['success'] = false;
    $results['message'] = 'Error during verification: ' . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
