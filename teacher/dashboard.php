<?php
/**
 * Teacher Dashboard - UIU Smart Campus
 * Main hub for teachers to manage courses, assignments, and student submissions
 */
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

// Get teacher details
$teacher_stmt = $conn->prepare("SELECT t.*, d.department_name FROM teachers t JOIN departments d ON t.department_id = d.department_id WHERE t.teacher_id = ?");
$teacher_stmt->bind_param('i', $teacher_id);
$teacher_stmt->execute();
$teacher = $teacher_stmt->get_result()->fetch_assoc();
$teacher_stmt->close();

// Get current trimester
$current_trimester = $conn->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_trimester_id = $current_trimester['trimester_id'] ?? null;

// Get courses count for this teacher
$courses_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT CONCAT(course_id, '-', section)) as count
    FROM enrollments 
    WHERE teacher_id = ? AND trimester_id = ? AND status = 'enrolled'
");
$courses_stmt->bind_param('ii', $teacher_id, $current_trimester_id);
$courses_stmt->execute();
$courses_count = $courses_stmt->get_result()->fetch_assoc()['count'];
$courses_stmt->close();

// Get total students count
$students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT student_id) as count
    FROM enrollments
    WHERE teacher_id = ? AND trimester_id = ? AND status = 'enrolled'
");
$students_stmt->bind_param('ii', $teacher_id, $current_trimester_id);
$students_stmt->execute();
$students_count = $students_stmt->get_result()->fetch_assoc()['count'];
$students_stmt->close();

// Get assignments count
$assignments_stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM assignments
    WHERE teacher_id = ? AND trimester_id = ?
");
$assignments_stmt->bind_param('ii', $teacher_id, $current_trimester_id);
$assignments_stmt->execute();
$assignments_count = $assignments_stmt->get_result()->fetch_assoc()['count'];
$assignments_stmt->close();

// Get pending submissions count
$pending_stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM assignment_submissions sub
    JOIN assignments a ON sub.assignment_id = a.assignment_id
    WHERE a.teacher_id = ? AND sub.status = 'submitted'
");
$pending_stmt->bind_param('i', $teacher_id);
$pending_stmt->execute();
$pending_count = $pending_stmt->get_result()->fetch_assoc()['count'];
$pending_stmt->close();

// Get courses with enrollment details
$courses_query = "
    SELECT 
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        e.section,
        COUNT(DISTINCT e.student_id) as student_count
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.teacher_id = ? AND e.trimester_id = ? AND e.status = 'enrolled'
    GROUP BY c.course_id, c.course_code, c.course_name, c.credit_hours, e.section
    ORDER BY c.course_code, e.section
";
$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->bind_param('ii', $teacher_id, $current_trimester_id);
$courses_stmt->execute();
$courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

// Get recent submissions (last 10)
$recent_submissions_query = "
    SELECT 
        sub.submission_id,
        sub.submitted_at,
        sub.is_late,
        sub.status,
        a.title as assignment_title,
        a.course_id,
        c.course_code,
        s.student_id,
        s.full_name as student_name,
        e.section
    FROM assignment_submissions sub
    JOIN assignments a ON sub.assignment_id = a.assignment_id
    JOIN courses c ON a.course_id = c.course_id
    JOIN students s ON sub.student_id = s.student_id
    JOIN enrollments e ON sub.enrollment_id = e.enrollment_id
    WHERE a.teacher_id = ?
    ORDER BY sub.submitted_at DESC
    LIMIT 10
";
$recent_stmt = $conn->prepare($recent_submissions_query);
$recent_stmt->bind_param('i', $teacher_id);
$recent_stmt->execute();
$recent_submissions = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();

// Get upcoming assignment deadlines
$upcoming_query = "
    SELECT 
        a.assignment_id,
        a.title,
        a.due_date,
        a.assignment_type,
        a.is_bonus,
        c.course_code,
        a.section,
        COUNT(DISTINCT e.student_id) as total_students,
        COUNT(DISTINCT sub.submission_id) as submissions_count
    FROM assignments a
    JOIN courses c ON a.course_id = c.course_id
    LEFT JOIN enrollments e ON a.course_id = e.course_id 
        AND a.trimester_id = e.trimester_id 
        AND (a.section IS NULL OR e.section = a.section)
        AND e.status = 'enrolled'
    LEFT JOIN assignment_submissions sub ON a.assignment_id = sub.assignment_id
    WHERE a.teacher_id = ? 
        AND a.trimester_id = ?
        AND a.due_date >= NOW()
        AND a.is_published = 1
    GROUP BY a.assignment_id
    ORDER BY a.due_date ASC
    LIMIT 5
";
$upcoming_stmt = $conn->prepare($upcoming_query);
$upcoming_stmt->bind_param('ii', $teacher_id, $current_trimester_id);
$upcoming_stmt->execute();
$upcoming_assignments = $upcoming_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$upcoming_stmt->close();

// Page configuration
$page_title = 'Dashboard';
$page_icon = 'fas fa-home';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - UIU Smart Campus</title>
    
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
        
        /* Stats Cards */
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
        
        /* Course Card */
        .course-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .course-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-color);
        }
        
        /* Assignment Card */
        .assignment-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 16px;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .assignment-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px var(--shadow-color);
        }
        
        /* Table */
        .data-table {
            width: 100%;
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .data-table thead {
            background: var(--bg-secondary);
        }
        
        .data-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
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
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fed7aa; color: #92400e; }
        .badge-danger { background: #fecaca; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-purple { background: #e9d5ff; color: #6b21a8; }
        
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
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
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.5s ease;
        }
    </style>
</head>
<body>
    <?php require_once('includes/sidebar.php'); ?>
    <?php require_once('includes/topbar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" style="padding: calc(var(--topbar-height) + 32px) 32px 32px 32px;">
        <!-- Welcome Section -->
        <div class="fade-in-up" style="margin-bottom: 32px;">
            <h2 style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
                Welcome back, <?php echo htmlspecialchars($teacher['initial']); ?>! 👋
            </h2>
            <p style="color: var(--text-secondary);">
                Here's what's happening with your courses today
                <?php if ($current_trimester): ?>
                    • <span style="color: #667eea; font-weight: 600;"><?php echo $current_trimester['trimester_name']; ?></span>
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 fade-in-up" style="animation-delay: 0.1s;">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value"><?php echo $courses_count; ?></div>
                <div class="stat-label">Active Courses</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $students_count; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-value"><?php echo $assignments_count; ?></div>
                <div class="stat-label">Assignments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $pending_count; ?></div>
                <div class="stat-label">Pending Reviews</div>
            </div>
        </div>
        
        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column (2/3 width) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- My Courses -->
                <div class="fade-in-up" style="animation-delay: 0.2s;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary);">
                            <i class="fas fa-book-open mr-2"></i>My Courses
                        </h3>
                        <a href="courses.php" class="text-sm" style="color: #667eea; text-decoration: none; font-weight: 600;">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php if (empty($courses)): ?>
                            <div class="col-span-2 text-center py-8" style="color: var(--text-secondary);">
                                <i class="fas fa-book" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px;"></i>
                                <p>No courses assigned yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($courses, 0, 6) as $course): ?>
                            <div class="course-card" onclick="window.location.href='courses.php?course_id=<?php echo $course['course_id']; ?>&section=<?php echo urlencode($course['section']); ?>'">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                    <div>
                                        <div style="font-weight: 700; color: #667eea; font-size: 14px; margin-bottom: 4px;">
                                            <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['section']); ?>
                                        </div>
                                        <div style="font-weight: 600; color: var(--text-primary); font-size: 15px;">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </div>
                                    </div>
                                    <span class="badge badge-info"><?php echo $course['credit_hours']; ?> Cr</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 16px; padding-top: 12px; border-top: 1px solid var(--border-color); font-size: 13px;">
                                    <span style="color: var(--text-secondary);">
                                        <i class="fas fa-users mr-1"></i><?php echo $course['student_count']; ?> students
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Submissions -->
                <div class="fade-in-up" style="animation-delay: 0.3s;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary);">
                            <i class="fas fa-file-upload mr-2"></i>Recent Submissions
                        </h3>
                        <a href="submissions.php" class="text-sm" style="color: #667eea; text-decoration: none; font-weight: 600;">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <div class="data-table">
                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Assignment</th>
                                    <th>Course</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_submissions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-8" style="color: var(--text-secondary);">
                                        No submissions yet
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_submissions as $sub): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($sub['student_name']); ?></div>
                                            <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($sub['student_id']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($sub['assignment_title']); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($sub['course_code']); ?> - <?php echo htmlspecialchars($sub['section']); ?></span>
                                        </td>
                                        <td style="font-size: 13px;">
                                            <?php 
                                            $time_diff = time() - strtotime($sub['submitted_at']);
                                            if ($time_diff < 3600) echo floor($time_diff/60) . 'm ago';
                                            elseif ($time_diff < 86400) echo floor($time_diff/3600) . 'h ago';
                                            else echo floor($time_diff/86400) . 'd ago';
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($sub['status'] == 'submitted'): ?>
                                                <span class="badge <?php echo $sub['is_late'] ? 'badge-warning' : 'badge-info'; ?>">
                                                    <?php echo $sub['is_late'] ? 'Late' : 'On Time'; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Graded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button onclick="viewSubmission(<?php echo $sub['submission_id']; ?>)" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Right Column (1/3 width) -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="fade-in-up" style="animation-delay: 0.2s;">
                    <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
                        <i class="fas fa-bolt mr-2"></i>Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <button onclick="window.location.href='assignments.php?action=create'" class="btn btn-primary w-full justify-center">
                            <i class="fas fa-plus-circle"></i> Create Assignment
                        </button>
                        <button onclick="window.location.href='submissions.php'" class="btn" style="width: 100%; background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary); justify-content: center;">
                            <i class="fas fa-check-circle"></i> Grade Submissions
                        </button>
                        <button onclick="window.location.href='students.php'" class="btn" style="width: 100%; background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary); justify-content: center;">
                            <i class="fas fa-users"></i> View Students
                        </button>
                    </div>
                </div>
                
                <!-- Upcoming Deadlines -->
                <div class="fade-in-up" style="animation-delay: 0.3s;">
                    <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
                        <i class="fas fa-calendar-alt mr-2"></i>Upcoming Deadlines
                    </h3>
                    <div class="space-y-3">
                        <?php if (empty($upcoming_assignments)): ?>
                            <div class="text-center py-8" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar-check" style="font-size: 36px; opacity: 0.3; margin-bottom: 12px;"></i>
                                <p>No upcoming deadlines</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcoming_assignments as $assign): 
                                $days_left = floor((strtotime($assign['due_date']) - time()) / 86400);
                            ?>
                            <div class="assignment-card" onclick="window.location.href='assignments.php?id=<?php echo $assign['assignment_id']; ?>'">
                                <div style="display: flex; justify-content: between; align-items: start; margin-bottom: 8px;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text-primary); font-size: 14px; margin-bottom: 4px;">
                                            <?php echo htmlspecialchars($assign['title']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($assign['course_code']); ?> 
                                            <?php if ($assign['section']): ?>- <?php echo htmlspecialchars($assign['section']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($assign['is_bonus']): ?>
                                        <span class="badge badge-purple">Bonus</span>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 8px; border-top: 1px solid var(--border-color); font-size: 12px;">
                                    <span style="color: var(--text-secondary);">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php if ($days_left == 0): ?>
                                            Today
                                        <?php elseif ($days_left == 1): ?>
                                            Tomorrow
                                        <?php else: ?>
                                            <?php echo $days_left; ?> days left
                                        <?php endif; ?>
                                    </span>
                                    <span style="color: var(--text-secondary);">
                                        <?php echo $assign['submissions_count']; ?>/<?php echo $assign['total_students']; ?> submitted
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        function viewSubmission(submissionId) {
            window.location.href = `submissions.php?id=${submissionId}`;
        }
    </script>
</body>
</html>
