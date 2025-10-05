<?php
/**
 * Focus Session API - Handle focus sessions, achievements, and statistics
 * Database: uiu_smart_campus
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
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'save_session':
            // Save a completed focus session
            $duration = intval($_POST['duration'] ?? 0);
            $mode = $_POST['mode'] ?? 'pomodoro';
            
            if ($duration <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid duration']);
                exit;
            }
            
            // Calculate points: 2 points per minute for pomodoro, 1 for breaks
            $points_earned = $mode === 'pomodoro' ? ($duration * 2) : $duration;
            
            // Save session
            $stmt = $conn->prepare("
                INSERT INTO focus_sessions 
                (student_id, session_duration, session_mode, points_earned)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param('sisi', $student_id, $duration, $mode, $points_earned);
            $stmt->execute();
            $session_id = $conn->insert_id;
            
            // Update student's total points
            $stmt = $conn->prepare("
                UPDATE students 
                SET total_points = COALESCE(total_points, 0) + ?
                WHERE student_id = ?
            ");
            $stmt->bind_param('is', $points_earned, $student_id);
            $stmt->execute();
            
            // Update focus streak
            updateFocusStreak($conn, $student_id);
            
            // Log activity
            logFocusActivity($conn, $student_id, $duration, $mode);
            
            echo json_encode([
                'success' => true,
                'message' => 'Session saved successfully',
                'session_id' => $session_id,
                'points_earned' => $points_earned
            ]);
            break;
            
        case 'get_stats':
            // Get focus statistics
            $stats = getFocusStats($conn, $student_id);
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        case 'check_achievements':
            // Check for new achievements
            $achievements = checkAchievements($conn, $student_id);
            echo json_encode([
                'success' => true,
                'new_achievements' => $achievements
            ]);
            break;
            
        case 'get_achievements':
            // Get all achievements (earned and available)
            $stmt = $conn->prepare("
                SELECT 
                    fa.*,
                    sa.earned_date,
                    CASE WHEN sa.achievement_id IS NOT NULL THEN 1 ELSE 0 END as is_earned
                FROM focus_achievements fa
                LEFT JOIN student_achievements sa 
                    ON fa.achievement_id = sa.achievement_id 
                    AND sa.student_id = ?
                ORDER BY fa.required_value ASC
            ");
            $stmt->bind_param('s', $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $achievements = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode([
                'success' => true,
                'achievements' => $achievements
            ]);
            break;
            
        case 'get_history':
            // Get focus session history
            $limit = intval($_GET['limit'] ?? 30);
            
            $stmt = $conn->prepare("
                SELECT 
                    session_id,
                    session_duration,
                    session_mode,
                    points_earned,
                    session_date,
                    DATE(session_date) as session_day
                FROM focus_sessions
                WHERE student_id = ?
                ORDER BY session_date DESC
                LIMIT ?
            ");
            $stmt->bind_param('si', $student_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $history = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Helper Functions

function getFocusStats($conn, $student_id) {
    $stats = [];
    
    // Today's sessions
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as session_count,
            COALESCE(SUM(session_duration), 0) as total_minutes,
            COALESCE(SUM(points_earned), 0) as total_points
        FROM focus_sessions
        WHERE student_id = ? 
        AND DATE(session_date) = CURDATE()
        AND session_mode = 'pomodoro'
    ");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $stats['today_sessions'] = $result['session_count'];
    $stats['today_minutes'] = $result['total_minutes'];
    $stats['today_points'] = $result['total_points'];
    
    // Current streak
    $stmt = $conn->prepare("
        SELECT COALESCE(focus_streak, 0) as streak
        FROM students
        WHERE student_id = ?
    ");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['current_streak'] = $result['streak'];
    
    // All-time stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            COALESCE(SUM(session_duration), 0) as total_minutes,
            COALESCE(SUM(points_earned), 0) as total_points
        FROM focus_sessions
        WHERE student_id = ?
        AND session_mode = 'pomodoro'
    ");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $stats['total_sessions'] = $result['total_sessions'];
    $stats['total_minutes'] = $result['total_minutes'];
    $stats['total_points_earned'] = $result['total_points'];
    
    return $stats;
}

function updateFocusStreak($conn, $student_id) {
    // Get last session date (excluding today)
    $stmt = $conn->prepare("
        SELECT DATE(session_date) as last_date
        FROM focus_sessions
        WHERE student_id = ? 
        AND DATE(session_date) < CURDATE()
        AND session_mode = 'pomodoro'
        ORDER BY session_date DESC
        LIMIT 1
    ");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $last_session = $result->fetch_assoc();
    
    // Get current streak
    $stmt = $conn->prepare("SELECT COALESCE(focus_streak, 0) as streak FROM students WHERE student_id = ?");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $current_streak = $stmt->get_result()->fetch_assoc()['streak'];
    
    $new_streak = 1;
    
    if ($last_session) {
        $last_date = new DateTime($last_session['last_date']);
        $yesterday = new DateTime('yesterday');
        
        if ($last_date->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            // Streak continues
            $new_streak = $current_streak + 1;
        } elseif ($last_date->format('Y-m-d') === date('Y-m-d')) {
            // Already did today, keep streak
            $new_streak = $current_streak;
        }
        // else: streak broken, reset to 1
    }
    
    // Update streak
    $stmt = $conn->prepare("UPDATE students SET focus_streak = ? WHERE student_id = ?");
    $stmt->bind_param('is', $new_streak, $student_id);
    $stmt->execute();
    
    return $new_streak;
}

function checkAchievements($conn, $student_id) {
    $new_achievements = [];
    
    // Get student stats
    $stats = getFocusStats($conn, $student_id);
    
    // Define achievement criteria
    $achievements_to_check = [
        ['type' => 'sessions', 'value' => $stats['total_sessions']],
        ['type' => 'minutes', 'value' => $stats['total_minutes']],
        ['type' => 'streak', 'value' => $stats['current_streak']]
    ];
    
    foreach ($achievements_to_check as $check) {
        // Find achievements of this type that haven't been earned yet
        $stmt = $conn->prepare("
            SELECT fa.*
            FROM focus_achievements fa
            LEFT JOIN student_achievements sa 
                ON fa.achievement_id = sa.achievement_id 
                AND sa.student_id = ?
            WHERE fa.achievement_type = ?
            AND fa.required_value <= ?
            AND sa.achievement_id IS NULL
            ORDER BY fa.required_value ASC
        ");
        $stmt->bind_param('ssi', $student_id, $check['type'], $check['value']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($achievement = $result->fetch_assoc()) {
            // Award achievement
            $stmt2 = $conn->prepare("
                INSERT INTO student_achievements (student_id, achievement_id)
                VALUES (?, ?)
            ");
            $stmt2->bind_param('si', $student_id, $achievement['achievement_id']);
            $stmt2->execute();
            
            // Award bonus points
            $stmt2 = $conn->prepare("
                UPDATE students 
                SET total_points = COALESCE(total_points, 0) + ?
                WHERE student_id = ?
            ");
            $stmt2->bind_param('is', $achievement['points_reward'], $student_id);
            $stmt2->execute();
            
            $new_achievements[] = $achievement;
        }
    }
    
    return $new_achievements;
}

function logFocusActivity($conn, $student_id, $duration, $mode) {
    $activity_title = "Completed {$duration} min focus session";
    $activity_description = "Successfully completed a {$mode} session and earned points!";
    
    $stmt = $conn->prepare("
        INSERT INTO student_activities 
        (student_id, activity_type, activity_title, activity_description, icon_class)
        VALUES (?, 'study_session', ?, ?, 'fa-brain')
    ");
    $stmt->bind_param('sss', $student_id, $activity_title, $activity_description);
    $stmt->execute();
}
?>
