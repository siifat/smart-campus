<?php
/**
 * Grades & Analytics - UIU Smart Campus
 * Comprehensive analytics dashboard for teachers to track student performance,
 * course statistics, and identify high-performing students
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent caching during development
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();

// Check if teacher is logged in
if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit;
}

// Check session timeout (2 hours)
if (isset($_SESSION['session_timeout']) && time() > $_SESSION['session_timeout']) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=session_expired');
    exit;
}

// Update session timeout
$_SESSION['session_timeout'] = time() + (2 * 60 * 60);

require_once('../config/database.php');

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_initial = $_SESSION['teacher_initial'] ?? '';
$teacher_email = $_SESSION['teacher_email'] ?? '';

// Get current trimester
$current_trimester = $conn->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_trimester_id = $current_trimester['trimester_id'] ?? null;

// Get all courses taught by this teacher in current trimester
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

// Get selected course and section from URL or use first course
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : ($courses[0]['course_id'] ?? null);
$selected_section = isset($_GET['section']) ? $_GET['section'] : ($courses[0]['section'] ?? null);

// DEBUG: Log what we're working with
if (isset($_GET['debug'])) {
    error_log("GRADES DEBUG: course_id=" . var_export($selected_course_id, true) . ", section=" . var_export($selected_section, true));
    error_log("GRADES DEBUG: GET params=" . json_encode($_GET));
    error_log("GRADES DEBUG: Available courses=" . json_encode($courses));
}

// Get course details
$course_details = null;
if ($selected_course_id) {
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
    $course_stmt->bind_param('sii', $selected_section, $current_trimester_id, $selected_course_id);
    $course_stmt->execute();
    $course_details = $course_stmt->get_result()->fetch_assoc();
    $course_stmt->close();
}

// Get assignments statistics for selected course/section
$assignments_stats = [];
if ($selected_course_id && $selected_section) {
    $assign_stats_query = "
        SELECT 
            a.assignment_id,
            a.title,
            a.assignment_type,
            a.total_marks,
            a.is_bonus,
            a.due_date,
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
    $assign_stmt->bind_param('iisi', $teacher_id, $selected_course_id, $selected_section, $current_trimester_id);
    $assign_stmt->execute();
    $assignments_stats = $assign_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $assign_stmt->close();
}

// Get student performance data
$student_performance = [];
if ($selected_course_id && $selected_section) {
    $perf_query = "
        SELECT 
            s.student_id,
            s.full_name,
            s.email,
            COUNT(DISTINCT sub.submission_id) as total_submissions,
            AVG(CASE WHEN sub.status = 'graded' THEN sub.marks_obtained END) as avg_marks,
            SUM(CASE WHEN sub.status = 'graded' THEN sub.marks_obtained ELSE 0 END) as total_marks,
            COUNT(DISTINCT CASE WHEN a.is_bonus = 1 AND sub.submission_id IS NOT NULL THEN sub.submission_id END) as bonus_submissions,
            COUNT(DISTINCT CASE WHEN sub.is_late = 1 THEN sub.submission_id END) as late_submissions,
            MIN(sub.submitted_at) as first_submission,
            MAX(sub.submitted_at) as last_submission
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
    $perf_stmt->bind_param('iisi', $teacher_id, $selected_course_id, $selected_section, $current_trimester_id);
    $perf_stmt->execute();
    $student_performance = $perf_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $perf_stmt->close();
}

// Get overall course statistics (all sections)
$overall_stats = null;
if ($selected_course_id) {
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
}

// Get section comparison data (for multi-section courses)
$section_comparison = [];
if ($selected_course_id) {
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
    $section_stmt->bind_param('iiii', $teacher_id, $teacher_id, $selected_course_id, $current_trimester_id);
    $section_stmt->execute();
    $section_comparison = $section_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $section_stmt->close();
}

// Get grade distribution for selected section
$grade_distribution = [];
if ($selected_course_id && $selected_section) {
    foreach ($student_performance as $student) {
        $avg = $student['avg_marks'] ?? 0;
        if ($avg >= 90) $grade = 'A+';
        elseif ($avg >= 85) $grade = 'A';
        elseif ($avg >= 80) $grade = 'A-';
        elseif ($avg >= 75) $grade = 'B+';
        elseif ($avg >= 70) $grade = 'B';
        elseif ($avg >= 65) $grade = 'B-';
        elseif ($avg >= 60) $grade = 'C+';
        elseif ($avg >= 55) $grade = 'C';
        elseif ($avg >= 50) $grade = 'D';
        else $grade = 'F';
        
        $grade_distribution[$grade] = ($grade_distribution[$grade] ?? 0) + 1;
    }
}

// Prepare chart data as JSON to avoid inline PHP in JavaScript
$chart_data = [
    'gradeDistribution' => [
        'labels' => !empty($grade_distribution) ? array_keys($grade_distribution) : [],
        'values' => !empty($grade_distribution) ? array_values($grade_distribution) : []
    ],
    'assignmentTrend' => [
        'labels' => array_map(function($a) { 
            return substr($a['title'], 0, 20) . (strlen($a['title']) > 20 ? '...' : ''); 
        }, $assignments_stats),
        'avgMarks' => array_map(function($a) { return round($a['avg_marks'] ?? 0, 1); }, $assignments_stats)
    ],
    'assignmentStats' => [
        'labels' => array_map(function($a) { 
            return substr($a['title'], 0, 25) . (strlen($a['title']) > 25 ? '...' : ''); 
        }, $assignments_stats),
        'submissionRates' => array_map(function($a) use ($course_details) {
            $total_students = $course_details['total_students'] ?? 1;
            return $total_students > 0 ? round(($a['submission_count'] / $total_students) * 100, 1) : 0;
        }, $assignments_stats),
        'avgMarks' => array_map(function($a) { return round($a['avg_marks'] ?? 0, 1); }, $assignments_stats)
    ]
];

// Debug data (comment out in production)
// Page configuration
$page_title = 'Grades & Analytics';
$page_icon = 'fas fa-chart-bar';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades & Analytics - UIU Smart Campus</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --topbar-height: 70px;
            --sidebar-width: 260px;
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-color: #334155;
            --shadow-color: rgba(0, 0, 0, 0.3);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-secondary);
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--card-bg);
            border-right: 1px solid var(--border-color);
            padding: 24px 0;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        [data-theme="dark"] .sidebar {
            background: #1a202c;
        }
        
        .sidebar-logo {
            padding: 0 24px 24px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-logo i {
            font-size: 28px;
            color: #667eea;
        }
        
        .sidebar-logo span {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .nav-item:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            color: #667eea;
            border-right: 3px solid #667eea;
        }
        
        .nav-item i {
            font-size: 18px;
            width: 20px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding-top: var(--topbar-height);
        }
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--topbar-height);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 999;
            box-shadow: 0 2px 8px var(--shadow-color);
        }
        
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            color: var(--text-secondary);
        }
        
        .icon-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .icon-btn .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 6px;
            border-radius: 10px;
            min-width: 18px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 12px;
            transition: all 0.2s;
        }
        
        .user-profile:hover {
            background: var(--bg-secondary);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 700;
        }
        
        /* Cards */
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px var(--shadow-color);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid var(--border-color);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px var(--shadow-color);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* Chart Container */
        .chart-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px var(--shadow-color);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-title i {
            color: #667eea;
        }
        
        .download-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .data-table thead {
            background: var(--bg-secondary);
        }
        
        .data-table th {
            padding: 16px;
            text-align: left;
            font-weight: 700;
            color: var(--text-primary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 16px;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .data-table tbody tr {
            transition: background 0.2s;
        }
        
        .data-table tbody tr:hover {
            background: var(--bg-secondary);
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fed7aa; color: #92400e; }
        .badge-danger { background: #fecaca; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-purple { background: #e9d5ff; color: #6b21a8; }
        .badge-gold { background: #fef3c7; color: #92400e; }
        
        /* Course Selector */
        .course-selector {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow-color);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }
        
        .course-selector select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .course-selector select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Student Rank Badge */
        .rank-badge {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
        }
        
        .rank-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
        .rank-2 { background: linear-gradient(135deg, #94a3b8, #64748b); color: white; }
        .rank-3 { background: linear-gradient(135deg, #fb923c, #ea580c); color: white; }
        .rank-other { background: var(--bg-secondary); color: var(--text-secondary); }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .topbar {
                left: 0;
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }
        
        ::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <?php require_once('includes/sidebar.php'); ?>
    <?php require_once('includes/topbar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" style="padding: calc(var(--topbar-height) + 32px) 32px 32px 32px;">
        
        <!-- Course Selector -->
        <div class="course-selector fade-in-up">
            <form method="GET" id="courseSelectForm">
                <label style="display: block; margin-bottom: 12px; font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-book" style="color: #667eea;"></i> Select Course & Section
                </label>
                <select name="course_section" id="courseSection">
                    <option value="">-- Select Course & Section --</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['course_id'] . '|' . $course['section']; ?>"
                                <?php echo ($course['course_id'] == $selected_course_id && $course['section'] == $selected_section) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_code']) . ' - ' . 
                                       htmlspecialchars($course['course_name']) . ' (Section ' . 
                                       htmlspecialchars($course['section']) . ') - ' . 
                                       $course['student_count'] . ' students'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <?php if ($selected_course_id && $selected_section && $course_details): ?>
        
        <!-- Course Info Header -->
        <div class="fade-in-up" style="margin-bottom: 24px;">
            <div style="background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 16px; padding: 32px; color: white; box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);">
                <h2 style="font-size: 28px; font-weight: 800; margin-bottom: 8px;">
                    <?php echo htmlspecialchars($course_details['course_code']); ?>
                </h2>
                <p style="font-size: 18px; opacity: 0.95; margin-bottom: 16px;">
                    <?php echo htmlspecialchars($course_details['course_name']); ?>
                </p>
                <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                    <div>
                        <i class="fas fa-users"></i> 
                        <strong>Section:</strong> <?php echo htmlspecialchars($selected_section); ?>
                    </div>
                    <div>
                        <i class="fas fa-user-graduate"></i> 
                        <strong>Students:</strong> <?php echo $course_details['total_students']; ?>
                    </div>
                    <div>
                        <i class="fas fa-credit-card"></i> 
                        <strong>Credits:</strong> <?php echo $course_details['credit_hours']; ?>
                    </div>
                    <div>
                        <i class="fas fa-tag"></i> 
                        <strong>Type:</strong> <?php echo ucfirst($course_details['course_type']); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Overall Statistics -->
        <?php if ($overall_stats): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 fade-in-up" style="animation-delay: 0.1s;">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $overall_stats['total_students_all_sections']; ?></div>
                <div class="stat-label">Total Students (All Sections)</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-value"><?php echo $overall_stats['total_assignments']; ?></div>
                <div class="stat-label">Total Assignments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
                    <i class="fas fa-file-upload"></i>
                </div>
                <div class="stat-value"><?php echo $overall_stats['total_submissions']; ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($overall_stats['overall_avg_marks'] ?? 0, 1); ?>%</div>
                <div class="stat-label">Overall Average</div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <!-- Grade Distribution Chart -->
            <div class="chart-container fade-in-up" style="animation-delay: 0.2s;">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-pie"></i>
                        Grade Distribution (Section <?php echo htmlspecialchars($selected_section); ?>)
                    </div>
                    <button class="download-btn" onclick="downloadChart('gradeDistChart', 'grade-distribution')">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
                <?php if (!empty($grade_distribution)): ?>
                    <canvas id="gradeDistChart" height="300"></canvas>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                        <i class="fas fa-chart-pie" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px;"></i>
                        <p style="font-size: 16px; font-weight: 600;">No grade data available yet</p>
                        <p style="font-size: 14px; margin-top: 8px;">Students need to have graded assignments to display grade distribution.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Section Comparison Chart -->
            <?php if (count($section_comparison) > 1): ?>
            <div class="chart-container fade-in-up" style="animation-delay: 0.3s;">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-bar"></i>
                        Section Performance Comparison
                    </div>
                    <button class="download-btn" onclick="downloadChart('sectionCompChart', 'section-comparison')">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
                <canvas id="sectionCompChart" height="300"></canvas>
            </div>
            <?php else: ?>
            <div class="chart-container fade-in-up" style="animation-delay: 0.3s;">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        Assignment Performance Trend
                    </div>
                    <button class="download-btn" onclick="downloadChart('assignmentTrendChart', 'assignment-trend')">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
                <?php if (!empty($assignments_stats)): ?>
                    <canvas id="assignmentTrendChart" height="300"></canvas>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                        <i class="fas fa-chart-line" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px;"></i>
                        <p style="font-size: 16px; font-weight: 600;">No assignment data available</p>
                        <p style="font-size: 14px; margin-top: 8px;">Create and publish assignments to track performance trends.</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Assignment Statistics Chart -->
        <?php if (!empty($assignments_stats)): ?>
        <div class="chart-container fade-in-up" style="animation-delay: 0.4s;">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-tasks"></i>
                    Assignment Statistics
                </div>
                <button class="download-btn" onclick="downloadChart('assignmentStatsChart', 'assignment-statistics')">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
            <canvas id="assignmentStatsChart" height="120"></canvas>
        </div>
        <?php else: ?>
        <div class="chart-container fade-in-up" style="animation-delay: 0.4s;">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-tasks"></i>
                    Assignment Statistics
                </div>
            </div>
            <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                <i class="fas fa-tasks" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px;"></i>
                <p style="font-size: 16px; font-weight: 600;">No assignments published yet</p>
                <p style="font-size: 14px; margin-top: 8px;">Create and publish assignments to view submission statistics.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Top Performers Section -->
        <?php if (!empty($student_performance)): ?>
        <div class="chart-container fade-in-up" style="animation-delay: 0.5s;">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-trophy"></i>
                    Top Performers & Student Rankings
                </div>
                <button class="download-btn" onclick="exportStudentData()">
                    <i class="fas fa-file-excel"></i> Export Data
                </button>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Total Marks</th>
                            <th>Average</th>
                            <th>Submissions</th>
                            <th>Bonus Work</th>
                            <th>Late Submissions</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($student_performance as $student): 
                            $avg = $student['avg_marks'] ?? 0;
                            $total_marks = $student['total_marks'] ?? 0;
                            $submissions = $student['total_submissions'] ?? 0;
                            $bonus = $student['bonus_submissions'] ?? 0;
                            $late = $student['late_submissions'] ?? 0;
                            
                            // Determine rank class
                            $rank_class = 'rank-other';
                            if ($rank == 1) $rank_class = 'rank-1';
                            elseif ($rank == 2) $rank_class = 'rank-2';
                            elseif ($rank == 3) $rank_class = 'rank-3';
                            
                            // Determine performance badge
                            if ($avg >= 90) {
                                $perf_badge = '<span class="badge badge-success">Excellent</span>';
                            } elseif ($avg >= 80) {
                                $perf_badge = '<span class="badge badge-info">Very Good</span>';
                            } elseif ($avg >= 70) {
                                $perf_badge = '<span class="badge badge-warning">Good</span>';
                            } elseif ($avg >= 60) {
                                $perf_badge = '<span class="badge badge-purple">Average</span>';
                            } else {
                                $perf_badge = '<span class="badge badge-danger">Needs Improvement</span>';
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="rank-badge <?php echo $rank_class; ?>">
                                    <?php echo $rank <= 3 ? '<i class="fas fa-medal"></i>' : $rank; ?>
                                </div>
                            </td>
                            <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><strong style="color: #667eea;"><?php echo number_format($total_marks, 1); ?></strong></td>
                            <td><strong><?php echo number_format($avg, 1); ?>%</strong></td>
                            <td><?php echo $submissions; ?></td>
                            <td>
                                <?php if ($bonus > 0): ?>
                                    <span class="badge badge-gold">
                                        <i class="fas fa-star"></i> <?php echo $bonus; ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($late > 0): ?>
                                    <span class="badge badge-warning"><?php echo $late; ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success">0</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $perf_badge; ?></td>
                        </tr>
                        <?php 
                            $rank++;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- No Data Message -->
        <?php if (empty($student_performance) && empty($assignments_stats) && empty($grade_distribution)): ?>
        <div class="chart-container fade-in-up" style="animation-delay: 0.5s;">
            <div style="text-align: center; padding: 80px 20px;">
                <i class="fas fa-chart-line" style="font-size: 80px; color: var(--border-color); margin-bottom: 24px;"></i>
                <h3 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
                    No Analytics Data Available Yet
                </h3>
                <p style="color: var(--text-secondary); font-size: 16px; margin-bottom: 32px; max-width: 600px; margin-left: auto; margin-right: auto;">
                    To view analytics and student performance data, you need to create assignments, have students submit them, and grade the submissions.
                </p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; max-width: 800px; margin: 0 auto; text-align: left;">
                    <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px;">
                        <div style="font-size: 32px; margin-bottom: 12px;">📝</div>
                        <strong style="display: block; color: var(--text-primary); margin-bottom: 8px;">Step 1: Create Assignments</strong>
                        <p style="font-size: 14px; color: var(--text-secondary);">Go to Assignments page and create assignments for this course and section.</p>
                    </div>
                    <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px;">
                        <div style="font-size: 32px; margin-bottom: 12px;">📤</div>
                        <strong style="display: block; color: var(--text-primary); margin-bottom: 8px;">Step 2: Students Submit</strong>
                        <p style="font-size: 14px; color: var(--text-secondary);">Students will submit their work through the student portal.</p>
                    </div>
                    <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px;">
                        <div style="font-size: 32px; margin-bottom: 12px;">✅</div>
                        <strong style="display: block; color: var(--text-primary); margin-bottom: 8px;">Step 3: Grade Submissions</strong>
                        <p style="font-size: 14px; color: var(--text-secondary);">Review and grade student submissions from the Submissions page.</p>
                    </div>
                    <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px;">
                        <div style="font-size: 32px; margin-bottom: 12px;">📊</div>
                        <strong style="display: block; color: var(--text-primary); margin-bottom: 8px;">Step 4: View Analytics</strong>
                        <p style="font-size: 14px; color: var(--text-secondary);">Return here to see charts, rankings, and performance trends.</p>
                    </div>
                </div>
                <a href="assignments.php" style="display: inline-block; margin-top: 32px; padding: 14px 28px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 10px; font-weight: 600; transition: transform 0.2s;">
                    <i class="fas fa-plus-circle"></i> Create Your First Assignment
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Assignment-wise Performance -->
        <?php if (!empty($assignments_stats)): ?>
        <div class="chart-container fade-in-up" style="animation-delay: 0.6s;">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-clipboard-check"></i>
                    Assignment-wise Performance
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Type</th>
                            <th>Total Marks</th>
                            <th>Submissions</th>
                            <th>Average</th>
                            <th>Highest</th>
                            <th>Lowest</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments_stats as $assignment): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($assignment['title']); ?></strong>
                                <?php if ($assignment['is_bonus']): ?>
                                    <span class="badge badge-gold" style="margin-left: 8px;">
                                        <i class="fas fa-star"></i> Bonus
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo ucfirst($assignment['assignment_type']); ?>
                                </span>
                            </td>
                            <td><?php echo $assignment['total_marks']; ?></td>
                            <td><?php echo $assignment['submission_count']; ?></td>
                            <td>
                                <strong style="color: #667eea;">
                                    <?php echo number_format($assignment['avg_marks'] ?? 0, 1); ?>
                                </strong>
                            </td>
                            <td>
                                <span class="badge badge-success">
                                    <?php echo number_format($assignment['max_marks'] ?? 0, 1); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-warning">
                                    <?php echo number_format($assignment['min_marks'] ?? 0, 1); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $due = new DateTime($assignment['due_date']);
                                echo $due->format('M d, Y');
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        
        <!-- No Course Selected -->
        <div style="text-align: center; padding: 80px 20px;">
            <i class="fas fa-chart-bar" style="font-size: 80px; color: var(--border-color); margin-bottom: 24px;"></i>
            <h3 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px;">
                No Course Selected
            </h3>
            <p style="color: var(--text-secondary); font-size: 16px;">
                Please select a course and section from the dropdown above to view analytics.
            </p>
        </div>
        
        <?php endif; ?>
        
    </main>
    
    <script>
        // Handle course selection change (with reload prevention)
        let isPageLoad = true;
        const courseSelector = document.getElementById('courseSection');
        
        // Prevent change event on initial page load
        setTimeout(() => { isPageLoad = false; }, 500);
        
        courseSelector.addEventListener('change', function() {
            if (isPageLoad) {
                console.log('Ignoring change event on page load');
                return;
            }
            
            const value = this.value;
            console.log('Course selection changed:', value);
            if (value) {
                const [courseId, section] = value.split('|');
                console.log('Redirecting to: course_id=' + courseId + ', section=' + section);
                const newUrl = `?course_id=${courseId}&section=${section}`;
                console.log('Full URL:', newUrl);
                window.location.href = newUrl;
            } else {
                // If empty option selected, reload without parameters
                console.log('Reloading page without parameters');
                window.location.href = window.location.pathname;
            }
        });
    </script>
    
    <?php if ($selected_course_id && $selected_section): ?>
    <?php require_once('includes/charts.js.php'); ?>
    <?php endif; ?>
    
    <script>
        // Download Chart Function
        function downloadChart(chartId, filename) {
            const canvas = document.getElementById(chartId);
            if (!canvas) {
                alert('Chart not found!');
                return;
            }
            
            // Get the chart instance to ensure it's rendered
            let chart = null;
            if (chartId === 'gradeDistChart') chart = window.gradeChart;
            else if (chartId === 'assignmentTrendChart') chart = window.trendChart;
            else if (chartId === 'assignmentStatsChart') chart = window.statsChart;
            else if (chartId === 'sectionCompChart') chart = window.sectionChart;
            
            if (!chart) {
                // Fallback to canvas if chart instance not found
                try {
                    const url = canvas.toDataURL('image/png');
                    const link = document.createElement('a');
                    link.download = filename + '-' + new Date().getTime() + '.png';
                    link.href = url;
                    link.click();
                    return;
                } catch (e) {
                    alert('Chart not initialized or no data available!');
                    return;
                }
            }
            
            // Use Chart.js toBase64Image method for better quality
            const url = chart.toBase64Image();
            const link = document.createElement('a');
            link.download = filename + '-' + new Date().getTime() + '.png';
            link.href = url;
            link.click();
        }
        
        // Export Student Data to CSV
        function exportStudentData() {
            <?php if (!empty($student_performance)): ?>
            const data = <?php echo json_encode($student_performance); ?>;
            
            let csv = 'Rank,Student ID,Name,Email,Total Marks,Average,Submissions,Bonus Work,Late Submissions\n';
            
            data.forEach((student, index) => {
                const rank = index + 1;
                
                // Convert to numbers safely
                const totalMarks = parseFloat(student.total_marks) || 0;
                const avgMarks = parseFloat(student.avg_marks) || 0;
                const totalSubmissions = parseInt(student.total_submissions) || 0;
                const bonusSubmissions = parseInt(student.bonus_submissions) || 0;
                const lateSubmissions = parseInt(student.late_submissions) || 0;
                
                const row = [
                    rank,
                    student.student_id,
                    `"${student.full_name}"`,
                    student.email || '',
                    totalMarks.toFixed(1),
                    avgMarks.toFixed(1),
                    totalSubmissions,
                    bonusSubmissions,
                    lateSubmissions
                ];
                csv += row.join(',') + '\n';
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'student-performance-<?php echo $selected_course_id . '-' . $selected_section; ?>-' + new Date().getTime() + '.csv';
            link.click();
            window.URL.revokeObjectURL(url);
            <?php else: ?>
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: 'No Data',
                    text: 'No student data available to export.'
                });
            } else {
                alert('No student data available to export.');
            }
            <?php endif; ?>
        }
    </script>
</body>
</html>
