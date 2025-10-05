<?php
/**
 * Student Dashboard - UIU Smart Campus
 * Main hub for students to view assignments, courses, calendar, points, and to-do lists
 */
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Location: ../login.html?error=unauthorized');
    exit;
}

// Check session timeout (2 hours)
if (isset($_SESSION['session_timeout']) && time() > $_SESSION['session_timeout']) {
    session_unset();
    session_destroy();
    header('Location: ../login.html?error=session_expired');
    exit;
}

// Update session timeout
$_SESSION['session_timeout'] = time() + (2 * 60 * 60);

require_once('../config/database.php');

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

// Fetch student data including points
$stmt = $conn->prepare("SELECT *, COALESCE(total_points, 0) as total_points FROM students WHERE student_id = ?");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$total_points = $student['total_points'] ?? 0;
$stmt->close();

// Fetch enrolled courses count from enrollments table
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND status = 'enrolled'");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$courses_result = $stmt->get_result();
$courses_count = $courses_result->fetch_assoc()['count'];
$stmt->close();

// Fetch enrolled courses with details
$stmt = $conn->prepare("
    SELECT 
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        c.course_type,
        e.section,
        t.full_name as teacher_name,
        t.initial as teacher_initial
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    LEFT JOIN teachers t ON e.teacher_id = t.teacher_id
    WHERE e.student_id = ? AND e.status = 'enrolled'
    ORDER BY c.course_code
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$enrolled_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch assignments count (mock data for now - can be replaced with actual assignments table)
$assignments_count = 8;

// Fetch earned points from student record
$points_earned = $student['total_points'] ?? 0;

// Fetch GPA from student record
$current_gpa = $student['current_cgpa'] ?? 0.00;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - UIU Smart Campus</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --card-bg: rgba(255, 255, 255, 0.9);
            --sidebar-width: 280px;
            --topbar-height: 72px;
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-color: #334155;
            --shadow-color: rgba(0, 0, 0, 0.3);
            --card-bg: rgba(30, 41, 59, 0.9);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #f68b1f 0%, #fbbf24 50%, #f68b1f 100%);
            padding: 24px;
            overflow-y: auto;
            z-index: 100;
            transition: transform 0.3s ease;
            box-shadow: 4px 0 20px rgba(246, 139, 31, 0.15);
        }
        
        [data-theme="dark"] .sidebar {
            background: linear-gradient(180deg, #d97706 0%, #f59e0b 50%, #d97706 100%);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .sidebar-logo i {
            font-size: 32px;
            color: white;
        }
        
        .sidebar-logo span {
            font-size: 20px;
            font-weight: 800;
            color: white;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            margin-bottom: 8px;
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .nav-item i {
            font-size: 20px;
            width: 24px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 24px;
            padding-top: calc(var(--topbar-height) + 24px);
        }
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--topbar-height);
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 90;
            box-shadow: 0 2px 12px var(--shadow-color);
        }
        
        .search-box {
            flex: 1;
            max-width: 500px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 48px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #f68b1f;
            box-shadow: 0 0 0 3px rgba(246, 139, 31, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .icon-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--bg-secondary);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .icon-btn:hover {
            background: #f68b1f;
            color: white;
            transform: translateY(-2px);
        }
        
        .icon-btn .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            border: 2px solid var(--bg-primary);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 12px;
            background: var(--bg-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-profile:hover {
            background: var(--border-color);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }
        
        /* Glass Card */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 20px var(--shadow-color);
            transition: all 0.3s ease;
        }
        
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px var(--shadow-color);
        }
        
        /* Stats Card */
        .stat-card {
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(246, 139, 31, 0.1), rgba(251, 191, 36, 0.1));
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
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
            font-weight: 600;
        }
        
        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--bg-secondary);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 12px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #f68b1f, #fbbf24);
            border-radius: 10px;
            transition: width 1s ease;
        }
        
        /* Assignment Card */
        .assignment-card {
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 12px;
            background: var(--bg-secondary);
            border-left: 4px solid #f68b1f;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .assignment-card:hover {
            background: var(--border-color);
            transform: translateX(5px);
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }
        
        .assignment-title {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 15px;
        }
        
        .assignment-due {
            font-size: 12px;
            color: #ef4444;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .assignment-course {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        /* Badge */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-urgent {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .badge-soon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .badge-upcoming {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        /* Course Card */
        .course-card {
            padding: 20px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(246, 139, 31, 0.1), rgba(251, 191, 36, 0.05));
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(246, 139, 31, 0.2);
            border-color: #f68b1f;
        }
        
        .course-code {
            font-size: 13px;
            font-weight: 700;
            color: #f68b1f;
            margin-bottom: 8px;
        }
        
        .course-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
        }
        
        .course-instructor {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }
        
        /* To-Do Item */
        .todo-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 10px;
            background: var(--bg-secondary);
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        
        .todo-item:hover {
            background: var(--border-color);
        }
        
        .todo-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid #cbd5e1;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .todo-checkbox:hover {
            border-color: #f68b1f;
        }
        
        .todo-checkbox.checked {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            border-color: #f68b1f;
            position: relative;
        }
        
        .todo-checkbox.checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .todo-text {
            flex: 1;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .todo-text.completed {
            text-decoration: line-through;
            opacity: 0.5;
        }
        
        /* Calendar */
        .calendar {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 20px;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .calendar-month {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .calendar-nav {
            display: flex;
            gap: 8px;
        }
        
        .calendar-nav button {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: var(--bg-primary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .calendar-nav button:hover {
            background: #f68b1f;
            color: white;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        
        .calendar-day-label {
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            padding: 8px 0;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .calendar-day:hover {
            background: var(--border-color);
        }
        
        .calendar-day.today {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
        }
        
        .calendar-day.has-event::after {
            content: '';
            position: absolute;
            bottom: 4px;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #10b981;
        }
        
        .calendar-day.inactive {
            opacity: 0.3;
        }
        
        /* Achievement Badge */
        .achievement-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(246, 139, 31, 0.2));
            border: 2px solid rgba(246, 139, 31, 0.3);
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .achievement-badge i {
            color: #fbbf24;
        }
        
        /* Quick Action Buttons */
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 24px 16px;
            border-radius: 16px;
            border: none;
            color: white;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .quick-action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .quick-action-btn i {
            font-size: 28px;
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
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .pulse {
            animation: pulse 2s ease-in-out infinite;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-secondary); }
        ::-webkit-scrollbar-thumb { background: #f68b1f; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #fbbf24; }
    </style>
</head>
<body>
    <?php require_once('includes/sidebar.php'); ?>
    <?php require_once('includes/topbar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Section -->
        <div class="fade-in-up" style="margin-bottom: 32px;">
            <h1 style="font-size: 32px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">
                Welcome back, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>! 👋
            </h1>
            <p style="font-size: 16px; color: var(--text-secondary);">
                Here's what's happening with your studies today.
            </p>
        </div>
        
        <!-- Quick Actions (Top Section) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8 fade-in-up">
            <button class="quick-action-btn" onclick="showUploadModal()" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                <i class="fas fa-upload"></i>
                <span>Upload Notes</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='resources.php'" style="background: linear-gradient(135deg, #f68b1f, #e57a0f);">
                <i class="fas fa-folder-open"></i>
                <span>Browse Resources</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='ask_question.php'" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-question-circle"></i>
                <span>Ask Question</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='study_room.php'" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-users"></i>
                <span>Study Room</span>
            </button>
        </div>
        
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 fade-in-up" style="animation-delay: 0.1s;">
            <!-- Enrolled Courses -->
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-value"><?php echo $courses_count; ?></div>
                <div class="stat-label">Enrolled Courses</div>
            </div>
            
            <!-- Pending Assignments -->
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-value"><?php echo $assignments_count; ?></div>
                <div class="stat-label">Pending Assignments</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 60%; background: linear-gradient(90deg, #f59e0b, #fbbf24);"></div>
                </div>
            </div>
            
            <!-- Points Earned -->
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-value"><?php echo number_format($points_earned); ?></div>
                <div class="stat-label">Points Earned</div>
                <div class="achievement-badge" style="margin-top: 12px;">
                    <i class="fas fa-trophy"></i>
                    <span>Top 10%</span>
                </div>
            </div>
            
            <!-- Current GPA -->
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f68b1f, #fbbf24); color: white;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($current_gpa, 2); ?></div>
                <div class="stat-label">Current GPA</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo ($current_gpa / 4.0) * 100; ?>%;"></div>
                </div>
            </div>
        </div>
        
        <!-- Main Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column (2/3 width) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Upcoming Assignments -->
                <div class="glass-card fade-in-up" style="animation-delay: 0.2s;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary);">
                            <i class="fas fa-clipboard-list" style="color: #f68b1f; margin-right: 8px;"></i>
                            Upcoming Assignments
                        </h2>
                        <a href="#" style="color: #f68b1f; font-weight: 600; font-size: 14px;">View All →</a>
                    </div>
                    
                    <div class="assignment-card">
                        <div class="assignment-header">
                            <div>
                                <div class="assignment-title">Data Structure Implementation Project</div>
                                <div class="assignment-course">CSE 2103 - Data Structures</div>
                            </div>
                            <span class="badge badge-urgent">
                                <i class="fas fa-clock"></i> Due Tomorrow
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 45%; background: linear-gradient(90deg, #ef4444, #dc2626);"></div>
                        </div>
                    </div>
                    
                    <div class="assignment-card">
                        <div class="assignment-header">
                            <div>
                                <div class="assignment-title">Database Design & ER Diagram</div>
                                <div class="assignment-course">CSE 3521 - Database Management</div>
                            </div>
                            <span class="badge badge-soon">
                                <i class="fas fa-clock"></i> 3 Days Left
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 70%;"></div>
                        </div>
                    </div>
                    
                    <div class="assignment-card">
                        <div class="assignment-header">
                            <div>
                                <div class="assignment-title">React Component Development</div>
                                <div class="assignment-course">CSE 4589 - Web Engineering</div>
                            </div>
                            <span class="badge badge-upcoming">
                                <i class="fas fa-clock"></i> 1 Week
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 30%;"></div>
                        </div>
                    </div>
                    
                    <div class="assignment-card">
                        <div class="assignment-header">
                            <div>
                                <div class="assignment-title">Machine Learning Model Training</div>
                                <div class="assignment-course">CSE 4533 - Artificial Intelligence</div>
                            </div>
                            <span class="badge badge-upcoming">
                                <i class="fas fa-clock"></i> 2 Weeks
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 15%;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Enrolled Courses -->
                <div class="glass-card fade-in-up" style="animation-delay: 0.3s;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary);">
                            <i class="fas fa-book-open" style="color: #f68b1f; margin-right: 8px;"></i>
                            My Courses
                        </h2>
                        <a href="courses.php" style="color: #f68b1f; font-weight: 600; font-size: 14px;">View All →</a>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php if (count($enrolled_courses) > 0): ?>
                            <?php foreach ($enrolled_courses as $course): ?>
                                <div class="course-card">
                                    <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                    <div class="course-instructor">
                                        <i class="fas fa-user-tie" style="margin-right: 6px;"></i>
                                        <?php echo htmlspecialchars($course['teacher_name'] ?? $course['teacher_initial'] ?? 'TBA'); ?>
                                    </div>
                                    <div style="display: flex; gap: 8px; font-size: 12px;">
                                        <span class="badge" style="background: <?php echo $course['course_type'] === 'lab' ? '#fef3c7' : '#dbeafe'; ?>; color: <?php echo $course['course_type'] === 'lab' ? '#92400e' : '#1e40af'; ?>;">
                                            <?php echo ucfirst($course['course_type']); ?>
                                        </span>
                                        <span class="badge" style="background: #dcfce7; color: #166534;">
                                            <?php echo $course['credit_hours']; ?> Credit<?php echo $course['credit_hours'] > 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-secondary);">
                                <i class="fas fa-book-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px;"></i>
                                <p>No courses enrolled yet. Please sync your data from UCAM.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Performance Chart -->
                <div class="glass-card fade-in-up" style="animation-delay: 0.4s;">
                    <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 20px;">
                        <i class="fas fa-chart-area" style="color: #f68b1f; margin-right: 8px;"></i>
                        Performance Overview
                    </h2>
                    <canvas id="performanceChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
            
            <!-- Right Column (1/3 width) -->
            <div class="space-y-6">
                <!-- Calendar -->
                <div class="glass-card fade-in-up" style="animation-delay: 0.3s;">
                    <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 20px;">
                        <i class="fas fa-calendar-alt" style="color: #f68b1f; margin-right: 8px;"></i>
                        Calendar
                    </h2>
                    <div class="calendar">
                        <div class="calendar-header">
                            <div id="calendar-month-year" class="calendar-month"></div>
                            <div class="calendar-nav">
                                <button onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                                <button onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <div id="calendar-grid" class="calendar-grid">
                            <div class="calendar-day-label">Sun</div>
                            <div class="calendar-day-label">Mon</div>
                            <div class="calendar-day-label">Tue</div>
                            <div class="calendar-day-label">Wed</div>
                            <div class="calendar-day-label">Thu</div>
                            <div class="calendar-day-label">Fri</div>
                            <div class="calendar-day-label">Sat</div>
                            <!-- Calendar days will be dynamically loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- To-Do List -->
                <div class="glass-card fade-in-up" style="animation-delay: 0.4s;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary);">
                            <i class="fas fa-check-circle" style="color: #f68b1f; margin-right: 8px;"></i>
                            To-Do List
                        </h2>
                        <button onclick="showAddTodoDialog()" class="icon-btn" style="width: 32px; height: 32px;">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div id="todo-list">
                        <!-- To-do items will be dynamically loaded here -->
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="glass-card fade-in-up" style="animation-delay: 0.6s;">
                    <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 20px;">
                        <i class="fas fa-history" style="color: #f68b1f; margin-right: 8px;"></i>
                        Recent Activity
                    </h2>
                    
                    <div id="activity-feed">
                        <!-- Activity items will be dynamically loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // To-Do Checkbox Toggle (legacy - can be removed if not needed)
        document.querySelectorAll('.todo-checkbox').forEach(checkbox => {
            checkbox.addEventListener('click', function() {
                this.classList.toggle('checked');
                const todoText = this.nextElementSibling;
                todoText.classList.toggle('completed');
            });
        });
        
        // Performance Chart
        const ctx = document.getElementById('performanceChart');
        if (ctx) {
            const isDark = html.getAttribute('data-theme') === 'dark';
            const textColor = isDark ? '#cbd5e1' : '#475569';
            const gridColor = isDark ? '#334155' : '#e2e8f0';
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8'],
                    datasets: [{
                        label: 'Performance Score',
                        data: [75, 78, 82, 85, 88, 90, 92, 95],
                        borderColor: '#f68b1f',
                        backgroundColor: 'rgba(246, 139, 31, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: '#f68b1f',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#1e293b' : '#ffffff',
                            titleColor: textColor,
                            bodyColor: textColor,
                            borderColor: gridColor,
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Score: ' + context.parsed.y + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                color: textColor,
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            grid: {
                                color: gridColor
                            }
                        },
                        x: {
                            ticks: {
                                color: textColor
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
        
        // Animate progress bars on load
        window.addEventListener('load', () => {
            document.querySelectorAll('.progress-fill').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
        
        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                
                // Only handle if href is more than just "#"
                if (href && href.length > 1) {
                    e.preventDefault();
                    try {
                        const target = document.querySelector(href);
                        if (target) {
                            target.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                    } catch (error) {
                        console.error('Invalid selector:', href);
                    }
                }
            });
        });

        // Calendar functionality
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();
        let calendarEvents = {};

        function renderCalendar() {
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            
            document.getElementById('calendar-month-year').textContent = monthNames[currentMonth] + ' ' + currentYear;
            
            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();
            
            const today = new Date();
            const isCurrentMonth = today.getMonth() === currentMonth && today.getFullYear() === currentYear;
            const todayDate = today.getDate();
            
            let html = '';
            
            // Previous month days
            for (let i = firstDay - 1; i >= 0; i--) {
                html += `<div class="calendar-day inactive">${daysInPrevMonth - i}</div>`;
            }
            
            // Current month days
            for (let day = 1; day <= daysInMonth; day++) {
                const classes = ['calendar-day'];
                if (isCurrentMonth && day === todayDate) {
                    classes.push('today');
                }
                if (calendarEvents[day] && calendarEvents[day].length > 0) {
                    classes.push('has-event');
                }
                
                const events = calendarEvents[day] ? calendarEvents[day].map(e => 
                    `${e.time} - ${e.title}: ${e.description}`
                ).join('\\n') : '';
                
                const title = events ? `title="${events}"` : '';
                html += `<div class="${classes.join(' ')}" ${title}>${day}</div>`;
            }
            
            // Next month days
            const totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;
            const nextMonthDays = totalCells - (firstDay + daysInMonth);
            for (let i = 1; i <= nextMonthDays; i++) {
                html += `<div class="calendar-day inactive">${i}</div>`;
            }
            
            // Get the calendar grid and append days after the day labels
            const grid = document.getElementById('calendar-grid');
            // Remove all existing day elements (keep only the 7 day labels)
            while (grid.children.length > 7) {
                grid.removeChild(grid.lastChild);
            }
            // Insert the day elements
            grid.insertAdjacentHTML('beforeend', html);
        }

        function changeMonth(delta) {
            currentMonth += delta;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            } else if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            loadCalendarEvents();
        }

        function loadCalendarEvents() {
            fetch(`api/calendar.php?action=get_events&month=${currentMonth + 1}&year=${currentYear}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        calendarEvents = data.events;
                        renderCalendar();
                    }
                })
                .catch(error => console.error('Error loading calendar events:', error));
        }

        // To-Do List functionality
        function loadTodos() {
            fetch('api/todo.php?action=get')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderTodos(data.todos);
                    }
                })
                .catch(error => console.error('Error loading todos:', error));
        }

        function renderTodos(todos) {
            const todoList = document.getElementById('todo-list');
            if (todos.length === 0) {
                todoList.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">No tasks yet. Click + to add one!</p>';
                return;
            }
            
            console.log('Rendering todos:', todos);
            
            todoList.innerHTML = todos.map(todo => {
                // Convert completed to boolean (handles both "1"/"0" strings and true/false)
                const isCompleted = todo.completed == 1 || todo.completed === true || todo.completed === '1';
                
                return `
                    <div class="todo-item" data-task="${escapeHtml(todo.task)}" data-completed="${isCompleted}">
                        <div class="todo-checkbox ${isCompleted ? 'checked' : ''}" onclick="toggleTodo(${todo.todo_id}, event)"></div>
                        <div class="todo-text ${isCompleted ? 'completed' : ''}">${escapeHtml(todo.task)}</div>
                        <button onclick="deleteTodo(${todo.todo_id}, event)" style="color: #ef4444; background: none; border: none; cursor: pointer; padding: 4px 8px; opacity: 0.7; transition: opacity 0.3s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
            }).join('');
        }

        function showAddTodoDialog() {
            document.getElementById('addTodoModal').style.display = 'flex';
            document.getElementById('todoTaskInput').value = '';
            document.getElementById('todoTaskInput').focus();
        }

        function closeAddTodoModal() {
            document.getElementById('addTodoModal').style.display = 'none';
            document.getElementById('todoTaskInput').value = '';
        }

        function submitNewTodo(event) {
            event.preventDefault();
            const task = document.getElementById('todoTaskInput').value.trim();
            if (task) {
                addTodo(task);
                closeAddTodoModal();
            }
        }

        function addTodo(task) {
            fetch('api/todo.php?action=add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `task=${encodeURIComponent(task)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadTodos();
                    // Log activity
                    logActivity('other', 'Added new task', task, null, null, 'fa-plus-circle');
                } else {
                    alert('Error adding task: ' + data.message);
                }
            })
            .catch(error => console.error('Error adding todo:', error));
        }

        function toggleTodo(todoId, event) {
            // Get the task text before toggling
            const todoElement = event.target.parentElement;
            const taskText = todoElement.getAttribute('data-task') || todoElement.querySelector('.todo-text').textContent;
            const isCompleting = !event.target.classList.contains('checked');
            
            console.log('Toggling todo:', todoId, 'Completing:', isCompleting);
            
            fetch('api/todo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle&todo_id=${todoId}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Toggle response:', data);
                if (data.success) {
                    // Reload todos to get updated state
                    setTimeout(() => {
                        loadTodos();
                    }, 100);
                    
                    // Log activity only when completing (not uncompleting)
                    if (isCompleting) {
                        logActivity('todo_complete', 'Completed task', taskText, null, todoId, 'fa-check-circle');
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error toggling todo:', error);
                alert('Failed to update task. Please try again.');
            });
        }

        function deleteTodo(todoId, event) {
            if (confirm('Are you sure you want to delete this task?')) {
                // Prevent event bubbling
                if (event) {
                    event.stopPropagation();
                }
                
                console.log('Deleting todo:', todoId);
                
                fetch('api/todo.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&todo_id=${todoId}`
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Delete response:', data);
                    if (data.success) {
                        // Reload todos to get updated list
                        setTimeout(() => {
                            loadTodos();
                        }, 100);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error deleting todo:', error);
                    alert('Failed to delete task. Please try again.');
                });
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAddTodoModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('addTodoModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'addTodoModal') {
                closeAddTodoModal();
            }
        });

        // Activity Feed functionality
        function loadActivities() {
            fetch('api/activity.php?action=get&limit=10')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderActivities(data.activities);
                    }
                })
                .catch(error => console.error('Error loading activities:', error));
        }

        function renderActivities(activities) {
            const activityFeed = document.getElementById('activity-feed');
            if (activities.length === 0) {
                activityFeed.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">No recent activity yet.</p>';
                return;
            }
            
            activityFeed.innerHTML = activities.map(activity => `
                <div style="padding: 12px; background: var(--bg-secondary); border-radius: 10px; border-left: 3px solid ${activity.color}; margin-bottom: 8px; transition: all 0.3s ease;" onmouseover="this.style.transform='translateX(4px)'" onmouseout="this.style.transform='translateX(0)'">
                    <div style="display: flex; align-items: start; gap: 12px;">
                        <div style="width: 32px; height: 32px; border-radius: 8px; background: ${activity.color}20; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas ${activity.icon}" style="color: ${activity.color}; font-size: 14px;"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                                ${escapeHtml(activity.activity_title)}
                            </div>
                            <div style="font-size: 12px; color: var(--text-secondary);">
                                ${activity.activity_description ? escapeHtml(activity.activity_description) + ' • ' : ''}${activity.formatted_date}
                            </div>
                            ${activity.course_name ? `<div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;"><i class="fas fa-book" style="margin-right: 4px;"></i>${escapeHtml(activity.course_code)}</div>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function logActivity(type, title, description = null, courseId = null, relatedId = null, icon = null) {
            fetch('api/activity.php?action=add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    activity_type: type,
                    activity_title: title,
                    activity_description: description || '',
                    related_course_id: courseId || '',
                    related_id: relatedId || '',
                    icon_class: icon || ''
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadActivities();
                }
            })
            .catch(error => console.error('Error logging activity:', error));
        }

        // Upload Notes Modal Functions
        function showUploadModal() {
            document.getElementById('uploadNotesModal').style.display = 'flex';
            document.getElementById('resourceTitle').focus();
        }

        function closeUploadModal() {
            document.getElementById('uploadNotesModal').style.display = 'none';
            document.getElementById('uploadResourceForm').reset();
            document.getElementById('selectedFileName').textContent = '';
            selectUploadType('file');
        }

        function selectUploadType(type) {
            // Update UI
            document.querySelectorAll('.upload-type-btn').forEach(btn => {
                if (btn.getAttribute('data-type') === type) {
                    btn.style.borderColor = '#f68b1f';
                    btn.style.background = '#f68b1f20';
                    btn.style.color = '#f68b1f';
                } else {
                    btn.style.borderColor = 'var(--border-color)';
                    btn.style.background = 'transparent';
                    btn.style.color = 'var(--text-primary)';
                }
            });

            // Show/hide sections
            document.getElementById('fileUploadSection').style.display = type === 'file' ? 'block' : 'none';
            document.getElementById('linkUploadSection').style.display = type !== 'file' ? 'block' : 'none';
            
            // Update hidden field
            document.getElementById('uploadType').value = type;
            
            // Update link placeholder
            const linkInput = document.getElementById('resourceLink');
            if (type === 'google_drive') {
                linkInput.placeholder = 'Paste Google Drive share link...';
            } else if (type === 'youtube') {
                linkInput.placeholder = 'Paste YouTube video link...';
            } else {
                linkInput.placeholder = 'Paste your link here...';
            }
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                const fileName = file.name;
                const fileSize = (file.size / (1024 * 1024)).toFixed(2); // MB
                document.getElementById('selectedFileName').textContent = `✓ ${fileName} (${fileSize} MB)`;
                
                // Check file size (50MB limit)
                if (file.size > 50 * 1024 * 1024) {
                    alert('File size must be less than 50MB');
                    event.target.value = '';
                    document.getElementById('selectedFileName').textContent = '';
                }
            }
        }

        function submitUpload(event) {
            event.preventDefault();
            
            const form = document.getElementById('uploadResourceForm');
            const formData = new FormData(form);
            const submitBtn = document.getElementById('submitUploadBtn');
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            
            fetch('api/upload_resource.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update points in navbar
                    document.getElementById('user-points').textContent = new Intl.NumberFormat().format(data.new_points);
                    
                    // Show success message
                    alert(`🎉 Success! Your resource has been uploaded and you earned 50 points!\n\nTotal Points: ${data.new_points}`);
                    
                    // Log activity
                    logActivity('note_upload', 'Uploaded Resource', formData.get('title'), formData.get('course_id'), data.resource_id, 'fa-cloud-upload-alt');
                    
                    // Close modal
                    closeUploadModal();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                alert('Upload failed. Please try again.');
            })
            .finally(() => {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-upload"></i> Upload & Earn 50 Points';
            });
        }

        // Close modals with ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAddTodoModal();
                closeUploadModal();
            }
        });

        // Initialize calendar, todos, and activities on page load
        window.addEventListener('load', () => {
            loadCalendarEvents();
            loadTodos();
            loadActivities();
        });
    </script>

    <!-- Add Todo Modal -->
    <div id="addTodoModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: var(--card-bg); border-radius: 20px; padding: 32px; max-width: 500px; width: 90%; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3); position: relative;">
            <button onclick="closeAddTodoModal()" style="position: absolute; top: 16px; right: 16px; background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.3s;" onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='none'">
                <i class="fas fa-times"></i>
            </button>
            
            <h3 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
                <i class="fas fa-plus-circle" style="color: #f68b1f; margin-right: 8px;"></i>
                Add New Task
            </h3>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">Enter your task details below</p>
            
            <form id="addTodoForm" onsubmit="submitNewTodo(event)">
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">
                        Task Description
                    </label>
                    <textarea id="todoTaskInput" rows="3" required style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-size: 16px; font-family: 'Inter', sans-serif; resize: vertical; transition: all 0.3s;" placeholder="e.g., Complete DSA assignment, Study for midterm..." onfocus="this.style.borderColor='#f68b1f'" onblur="this.style.borderColor='var(--border-color)'"></textarea>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeAddTodoModal()" style="padding: 12px 24px; border-radius: 12px; border: 2px solid var(--border-color); background: transparent; color: var(--text-primary); font-weight: 600; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='transparent'">
                        Cancel
                    </button>
                    <button type="submit" style="padding: 12px 24px; border-radius: 12px; border: none; background: linear-gradient(135deg, #f68b1f, #fbbf24); color: white; font-weight: 600; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(246, 139, 31, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        <i class="fas fa-check" style="margin-right: 8px;"></i>
                        Add Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload Notes Modal -->
    <div id="uploadNotesModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
        <div style="background: var(--card-bg); border-radius: 20px; padding: 32px; max-width: 700px; width: 100%; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3); position: relative; max-height: 90vh; overflow-y: auto;">
            <button onclick="closeUploadModal()" style="position: absolute; top: 16px; right: 16px; background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.3s; z-index: 10;" onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='none'">
                <i class="fas fa-times"></i>
            </button>
            
            <h3 style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
                <i class="fas fa-cloud-upload-alt" style="color: #f68b1f; margin-right: 8px;"></i>
                Upload Resources
            </h3>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">
                Share your notes, solutions, or resources and earn <strong style="color: #f68b1f;">50 points</strong> 🎉
            </p>
            
            <form id="uploadResourceForm" enctype="multipart/form-data" onsubmit="submitUpload(event)">
                <!-- Title -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">
                        <i class="fas fa-heading" style="margin-right: 4px;"></i> Title *
                    </label>
                    <input type="text" id="resourceTitle" name="title" required style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-size: 16px; transition: all 0.3s;" placeholder="e.g., Database Normalization Notes" onfocus="this.style.borderColor='#f68b1f'" onblur="this.style.borderColor='var(--border-color)'">
                </div>
                
                <!-- Description -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">
                        <i class="fas fa-align-left" style="margin-right: 4px;"></i> Description
                    </label>
                    <textarea id="resourceDescription" name="description" rows="3" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-size: 16px; font-family: 'Inter', sans-serif; resize: vertical; transition: all 0.3s;" placeholder="Brief description of the content..." onfocus="this.style.borderColor='#f68b1f'" onblur="this.style.borderColor='var(--border-color)'"></textarea>
                </div>
                
                <!-- Category -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">
                        <i class="fas fa-folder" style="margin-right: 4px;"></i> Category *
                    </label>
                    <select id="resourceCategory" name="category_id" required style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-size: 16px; cursor: pointer; transition: all 0.3s;" onfocus="this.style.borderColor='#f68b1f'" onblur="this.style.borderColor='var(--border-color)'">
                        <option value="">Select a category...</option>
                        <option value="1">📝 Study Notes</option>
                        <option value="2">📄 Past Papers</option>
                        <option value="3">✅ CT Solutions</option>
                        <option value="4">📋 Assignment Solutions</option>
                        <option value="5">🎥 Video Lectures</option>
                        <option value="6">📚 Books & PDFs</option>
                        <option value="7">💻 Code & Projects</option>
                        <option value="8">📁 Other Resources</option>
                    </select>
                </div>
                
                <!-- Course (Optional) -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">
                        <i class="fas fa-book" style="margin-right: 4px;"></i> Related Course (Optional)
                    </label>
                    <select id="resourceCourse" name="course_id" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-size: 16px; cursor: pointer; transition: all 0.3s;" onfocus="this.style.borderColor='#f68b1f'" onblur="this.style.borderColor='var(--border-color)'">
                        <option value="">Select a course...</option>
                        <?php foreach ($enrolled_courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Upload Type Tabs -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: var(--text-primary); margin-bottom: 12px;">
                        <i class="fas fa-upload" style="margin-right: 4px;"></i> Upload Method *
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 16px;">
                        <button type="button" class="upload-type-btn active" data-type="file" onclick="selectUploadType('file')" style="padding: 12px; border-radius: 12px; border: 2px solid #f68b1f; background: #f68b1f20; color: #f68b1f; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                            <i class="fas fa-file-upload" style="display: block; font-size: 20px; margin-bottom: 4px;"></i>
                            File Upload
                        </button>
                        <button type="button" class="upload-type-btn" data-type="google_drive" onclick="selectUploadType('google_drive')" style="padding: 12px; border-radius: 12px; border: 2px solid var(--border-color); background: transparent; color: var(--text-primary); font-weight: 600; cursor: pointer; transition: all 0.3s;">
                            <i class="fab fa-google-drive" style="display: block; font-size: 20px; margin-bottom: 4px;"></i>
                            Google Drive
                        </button>
                        <button type="button" class="upload-type-btn" data-type="youtube" onclick="selectUploadType('youtube')" style="padding: 12px; border-radius: 12px; border: 2px solid var(--border-color); background: transparent; color: var(--text-primary); font-weight: 600; cursor: pointer; transition: all 0.3s;">
                            <i class="fab fa-youtube" style="display: block; font-size: 20px; margin-bottom: 4px;"></i>
                            YouTube
                        </button>
                        <button type="button" class="upload-type-btn" data-type="link" onclick="selectUploadType('link')" style="padding: 12px; border-radius: 12px; border: 2px solid var(--border-color); background: transparent; color: var(--text-primary); font-weight: 600; cursor: pointer; transition: all 0.3s;">
                            <i class="fas fa-link" style="display: block; font-size: 20px; margin-bottom: 4px;"></i>
                            Other Link
                        </button>
                    </div>
                    
                    <!-- File Upload Section -->
                    <div id="fileUploadSection" class="upload-section" style="display: block;">
                        <input type="file" id="resourceFile" name="file" style="display: none;" onchange="handleFileSelect(event)" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.txt">
                        <div onclick="document.getElementById('resourceFile').click()" style="border: 2px dashed var(--border-color); border-radius: 12px; padding: 32px; text-align: center; cursor: pointer; transition: all 0.3s; background: var(--bg-secondary);" onmouseover="this.style.borderColor='#f68b1f'" onmouseout="this.style.borderColor='var(--border-color)'">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 12px;"></i>
                            <p style="color: var(--text-primary); font-weight: 600; margin-bottom: 4px;">Click to browse files</p>
                            <p style="color: var(--text-secondary); font-size: 14px;">PDF, DOC, PPT, ZIP, Images (Max 50MB)</p>
                            <p id="selectedFileName" style="color: #f68b1f; font-weight: 600; margin-top: 12px;"></p>
                        </div>
                    </div>
                    
                    <!-- Link Upload Sections -->
                    <div id="linkUploadSection" class="upload-section" style="display: none;">
                        <input type="url" id="resourceLink" name="external_link" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-size: 16px; transition: all 0.3s;" placeholder="Paste your link here..." onfocus="this.style.borderColor='#f68b1f'" onblur="this.style.borderColor='var(--border-color)'">
                        <p style="color: var(--text-secondary); font-size: 12px; margin-top: 8px;">
                            <i class="fas fa-info-circle"></i> Paste Google Drive, Dropbox, OneDrive, YouTube, or any other link
                        </p>
                    </div>
                    
                    <input type="hidden" id="uploadType" name="resource_type" value="file">
                </div>
                
                <!-- Submit Buttons -->
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                    <button type="button" onclick="closeUploadModal()" style="padding: 12px 24px; border-radius: 12px; border: 2px solid var(--border-color); background: transparent; color: var(--text-primary); font-weight: 600; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='transparent'">
                        Cancel
                    </button>
                    <button type="submit" id="submitUploadBtn" style="padding: 12px 32px; border-radius: 12px; border: none; background: linear-gradient(135deg, #f68b1f, #fbbf24); color: white; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 8px;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(246, 139, 31, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        <i class="fas fa-upload"></i>
                        Upload & Earn 50 Points
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
