<?php
/**
 * Calendar API - Handle calendar events for students
 */
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../config/database.php');

$student_id = $_SESSION['student_id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_events':
            // Get events for a specific month
            $month = intval($_GET['month'] ?? date('n'));
            $year = intval($_GET['year'] ?? date('Y'));
            
            $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
            $end_date = date("Y-m-t", strtotime($start_date));
            
            // Get class schedule events
            $stmt = $conn->prepare("
                SELECT DISTINCT
                    cr.day_of_week,
                    c.course_code,
                    c.course_name,
                    cr.start_time,
                    cr.end_time,
                    'class' as event_type
                FROM class_routine cr
                JOIN enrollments e ON cr.enrollment_id = e.enrollment_id
                JOIN courses c ON e.course_id = c.course_id
                WHERE e.student_id = ? AND e.status = 'enrolled'
            ");
            $stmt->bind_param('s', $student_id);
            $stmt->execute();
            $schedule = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Convert day names to dates for the month
            $events = [];
            $dayMap = [
                'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2,
                'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6
            ];
            
            foreach ($schedule as $class) {
                $dayNum = $dayMap[$class['day_of_week']] ?? null;
                if ($dayNum === null) continue;
                
                // Find all dates in the month that match this day
                for ($day = 1; $day <= date('t', strtotime($start_date)); $day++) {
                    $date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $dow = date('w', strtotime($date));
                    
                    if ($dow == $dayNum) {
                        $events[$day][] = [
                            'title' => $class['course_code'],
                            'description' => $class['course_name'],
                            'time' => substr($class['start_time'], 0, 5),
                            'type' => 'class'
                        ];
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'events' => $events,
                'month' => $month,
                'year' => $year
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
