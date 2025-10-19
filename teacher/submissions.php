<?php
/**
 * Teacher Submissions Page
 * View and grade student assignment submissions
 */
session_start();

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php?error=unauthorized');
    exit();
}

// Check session timeout (2 hours)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=session_expired');
    exit();
}
$_SESSION['last_activity'] = time();

require_once '../config/database.php';

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_initial = $_SESSION['teacher_initial'] ?? '';

// Get current trimester
$stmt = $pdo->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1");
$current_trimester = $stmt->fetch(PDO::FETCH_ASSOC);
$current_trimester_id = $current_trimester['trimester_id'] ?? 1;

// Get teacher's courses for filter
$stmt = $pdo->prepare("
    SELECT DISTINCT
        c.course_id,
        c.course_code,
        c.course_name,
        e.section
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.teacher_id = ? 
      AND e.trimester_id = ?
      AND e.status = 'enrolled'
    ORDER BY c.course_code, e.section
");
$stmt->execute([$teacher_id, $current_trimester_id]);
$teacher_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teacher's assignments for filter
$stmt = $pdo->prepare("
    SELECT 
        a.assignment_id,
        a.title,
        c.course_code,
        a.section
    FROM assignments a
    JOIN courses c ON a.course_id = c.course_id
    WHERE a.teacher_id = ? 
      AND a.trimester_id = ?
      AND a.is_published = 1
    ORDER BY a.due_date DESC
");
$stmt->execute([$teacher_id, $current_trimester_id]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$filter_course = $_GET['course'] ?? '';
$filter_assignment = $_GET['assignment'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_late = $_GET['late'] ?? '';
$search = $_GET['search'] ?? '';

// Build submissions query
$query = "
    SELECT 
        sub.*,
        a.title as assignment_title,
        a.assignment_type,
        a.total_marks,
        a.due_date,
        c.course_code,
        c.course_name,
        s.student_id as student_number,
        s.full_name as student_name,
        s.email as student_email,
        e.section,
        CASE 
            WHEN sub.submitted_at > a.due_date THEN 1
            ELSE 0
        END as is_late
    FROM assignment_submissions sub
    JOIN assignments a ON sub.assignment_id = a.assignment_id
    JOIN courses c ON a.course_id = c.course_id
    JOIN students s ON sub.student_id = s.student_id
    JOIN enrollments e ON sub.enrollment_id = e.enrollment_id
    WHERE a.teacher_id = ? AND a.trimester_id = ?
";

$params = [$teacher_id, $current_trimester_id];

if ($search) {
    $query .= " AND (s.full_name LIKE ? OR s.student_id LIKE ? OR a.title LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter_course) {
    $query .= " AND a.course_id = ?";
    $params[] = $filter_course;
}

if ($filter_assignment) {
    $query .= " AND a.assignment_id = ?";
    $params[] = $filter_assignment;
}

if ($filter_status === 'submitted') {
    $query .= " AND sub.status = 'submitted'";
} elseif ($filter_status === 'graded') {
    $query .= " AND sub.status = 'graded'";
}

if ($filter_late === 'yes') {
    $query .= " AND sub.submitted_at > a.due_date";
} elseif ($filter_late === 'no') {
    $query .= " AND sub.submitted_at <= a.due_date";
}

$query .= " ORDER BY sub.submitted_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
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
$stmt->execute([$teacher_id, $current_trimester_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Set page title for topbar
$page_title = 'Submissions';
$page_icon = 'fas fa-file-upload';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions - Teacher Portal</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
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
        
        .user-info {
            display: none;
            line-height: 1.2;
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
            display: inline-flex;
            align-items: center;
            gap: 4px;
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
            font-size: 14px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
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
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .btn-outline:hover {
            background: var(--bg-secondary);
        }
        
        .btn-icon {
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
            color: var(--text-secondary);
        }
        
        .btn-icon:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 20px 60px var(--shadow-color);
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            z-index: 9999;
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            background: var(--card-bg);
            z-index: 10;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        /* Search Bar */
        .search-bar {
            position: relative;
            flex: 1;
            max-width: 400px;
        }
        
        .search-bar input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .search-bar input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .search-bar i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
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
    </style>
</head>
<body>
    <?php require_once('includes/sidebar.php'); ?>
    <?php require_once('includes/topbar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" style="padding: calc(var(--topbar-height) + 32px) 32px 32px 32px;">
        <!-- Header -->
        <div class="fade-in-up" style="margin-bottom: 32px;">
            <h2 style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
                <i class="fas fa-file-upload" style="margin-right: 12px;"></i>Assignment Submissions
            </h2>
            <p style="color: var(--text-secondary);">
                Review and grade student submissions for <?= htmlspecialchars($current_trimester['trimester_name']) ?>
            </p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8 fade-in-up" style="animation-delay: 0.1s;">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value"><?= $stats['total_submissions'] ?? 0 ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?= $stats['pending_count'] ?? 0 ?></div>
                <div class="stat-label">Pending Review</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?= $stats['graded_count'] ?? 0 ?></div>
                <div class="stat-label">Graded</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?= $stats['late_count'] ?? 0 ?></div>
                <div class="stat-label">Late Submissions</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?= $stats['average_marks'] ? number_format($stats['average_marks'], 1) : '0' ?></div>
                <div class="stat-label">Average Score</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="fade-in-up" style="animation-delay: 0.2s; background: var(--card-bg); border-radius: 16px; padding: 24px; margin-bottom: 24px; border: 1px solid var(--border-color); box-shadow: 0 2px 8px var(--shadow-color);">
            <form method="GET" style="display: flex; flex-direction: column; gap: 20px;">
                <!-- Search Bar -->
                <div class="search-bar" style="max-width: 100%;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search by student name, ID, or assignment..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>

                <!-- Filter Dropdowns -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label"><i class="fas fa-book"></i> Course</label>
                        <select name="course" class="form-select">
                            <option value="">All Courses</option>
                            <?php 
                            $unique_courses = [];
                            foreach ($teacher_courses as $course) {
                                $key = $course['course_id'];
                                if (!isset($unique_courses[$key])) {
                                    $unique_courses[$key] = $course;
                                }
                            }
                            foreach ($unique_courses as $course): ?>
                                <option value="<?= $course['course_id'] ?>" <?= $filter_course == $course['course_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label"><i class="fas fa-clipboard-list"></i> Assignment</label>
                        <select name="assignment" class="form-select">
                            <option value="">All Assignments</option>
                            <?php foreach ($assignments as $assignment): ?>
                                <option value="<?= $assignment['assignment_id'] ?>" <?= $filter_assignment == $assignment['assignment_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($assignment['course_code']) ?> - <?= htmlspecialchars($assignment['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label"><i class="fas fa-tasks"></i> Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="submitted" <?= $filter_status === 'submitted' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="graded" <?= $filter_status === 'graded' ? 'selected' : '' ?>>Graded</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label"><i class="fas fa-clock"></i> Late Status</label>
                        <select name="late" class="form-select">
                            <option value="">All Submissions</option>
                            <option value="yes" <?= $filter_late === 'yes' ? 'selected' : '' ?>>Late Only</option>
                            <option value="no" <?= $filter_late === 'no' ? 'selected' : '' ?>>On Time Only</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="opacity: 0;">Actions</label>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            <a href="submissions.php" class="btn btn-outline">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Submissions Table -->
        <div class="fade-in-up" style="animation-delay: 0.3s; background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 2px 8px var(--shadow-color); overflow: hidden;">
            <div style="padding: 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-list" style="margin-right: 8px;"></i>Submissions
                    <span style="font-size: 14px; font-weight: 400; color: var(--text-secondary); margin-left: 8px;">(<?= count($submissions) ?> found)</span>
                </h2>
                <?php if (!empty($submissions)): ?>
                <button class="btn-icon" onclick="location.reload()" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <?php endif; ?>
            </div>

            <?php if (empty($submissions)): ?>
                <div style="padding: 60px 24px; text-align: center;">
                    <div style="width: 80px; height: 80px; background: var(--bg-secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="fas fa-inbox" style="font-size: 36px; color: var(--text-secondary); opacity: 0.5;"></i>
                    </div>
                    <h3 style="font-size: 20px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">No Submissions Found</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 24px;">Try adjusting your filters or wait for students to submit</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Assignment</th>
                                <th>Course</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $sub): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?= htmlspecialchars($sub['student_name']) ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">
                                        <?= htmlspecialchars($sub['student_number']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: var(--text-primary); max-width: 200px;">
                                        <?= htmlspecialchars($sub['assignment_title']) ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">
                                        <?= htmlspecialchars($sub['assignment_type']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?= htmlspecialchars($sub['course_code']) ?>
                                        <?php if ($sub['section']): ?>- <?= htmlspecialchars($sub['section']) ?><?php endif; ?>
                                    </span>
                                </td>
                                <td style="font-size: 13px;">
                                    <div><?= date('M d, Y', strtotime($sub['submitted_at'])) ?></div>
                                    <div style="color: var(--text-secondary);"><?= date('h:i A', strtotime($sub['submitted_at'])) ?></div>
                                </td>
                                <td>
                                    <?php if ($sub['status'] === 'submitted'): ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-clock"></i> Pending
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check"></i> Graded
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($sub['is_late']): ?>
                                        <br><span class="badge badge-danger" style="margin-top: 4px;">
                                            <i class="fas fa-exclamation-triangle"></i> Late
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sub['status'] === 'graded'): ?>
                                        <div style="font-weight: 700; color: var(--text-primary); font-size: 16px;">
                                            <?= $sub['marks_obtained'] ?>/<?= $sub['total_marks'] ?>
                                        </div>
                                        <?php 
                                        $percentage = ($sub['marks_obtained'] / $sub['total_marks']) * 100;
                                        $color = $percentage >= 80 ? '#10b981' : ($percentage >= 60 ? '#f59e0b' : '#ef4444');
                                        ?>
                                        <div style="font-size: 12px; color: <?= $color ?>;">
                                            <?= number_format($percentage, 1) ?>%
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary); font-size: 13px;">Not graded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <button onclick="viewSubmission(<?= $sub['submission_id'] ?>)" 
                                                class="btn btn-sm btn-primary" title="View & Grade">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if ($sub['file_path']): ?>
                                        <button onclick="downloadSubmission(<?= $sub['submission_id'] ?>)" 
                                                class="btn btn-sm btn-outline" title="Download">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Grading Modal -->
    <div id="gradingModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="font-size: 24px; font-weight: 700; color: var(--text-primary);" id="modalTitle">Grade Submission</h2>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div id="modalContent" class="modal-body">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <script>
        // View submission details and grade
        async function viewSubmission(submissionId) {
            try {
                const response = await fetch(`api/get_submission.php?id=${submissionId}`);
                const result = await response.json();
                
                if (result.success) {
                    const sub = result.data;
                    const isLate = new Date(sub.submitted_at) > new Date(sub.due_date);
                    const isGraded = sub.status === 'graded';
                    
                    document.getElementById('modalContent').innerHTML = `
                        <div style="display: grid; gap: 20px;">
                            <!-- Student Info -->
                            <div style="background: var(--bg-secondary); padding: 16px; border-radius: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-primary); font-size: 16px;">${sub.student_name}</div>
                                        <div style="font-size: 13px; color: var(--text-secondary);">${sub.student_number}</div>
                                    </div>
                                    ${isLate ? '<span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Late Submission</span>' : '<span class="badge badge-success"><i class="fas fa-check"></i> On Time</span>'}
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; font-size: 13px;">
                                    <div><strong>Course:</strong> ${sub.course_code} ${sub.section ? '- ' + sub.section : ''}</div>
                                    <div><strong>Assignment:</strong> ${sub.assignment_title}</div>
                                    <div><strong>Submitted:</strong> ${new Date(sub.submitted_at).toLocaleString()}</div>
                                    <div><strong>Due Date:</strong> ${new Date(sub.due_date).toLocaleString()}</div>
                                </div>
                            </div>

                            <!-- Submission Content -->
                            <div>
                                <label class="form-label">Submission Text</label>
                                <div style="background: var(--bg-secondary); padding: 16px; border-radius: 12px; min-height: 100px; max-height: 200px; overflow-y: auto;">
                                    ${sub.submission_text ? `<p style="white-space: pre-wrap; color: var(--text-primary);">${sub.submission_text}</p>` : '<p style="color: var(--text-secondary); font-style: italic;">No text submission</p>'}
                                </div>
                            </div>

                            ${sub.file_path ? `
                            <div>
                                <label class="form-label">Attached File</label>
                                <div style="display: flex; align-items: center; gap: 12px; background: var(--bg-secondary); padding: 12px; border-radius: 12px;">
                                    <i class="fas fa-file-alt" style="font-size: 24px; color: #667eea;"></i>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text-primary);">${sub.file_path.split('/').pop()}</div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">Uploaded file</div>
                                    </div>
                                    <button onclick="downloadSubmission(${submissionId})" class="btn btn-sm btn-primary">
                                        <i class="fas fa-download"></i> Download
                                    </button>
                                </div>
                            </div>
                            ` : ''}

                            <!-- Grading Section -->
                            <form id="gradingForm" style="border-top: 2px solid var(--border-color); padding-top: 20px;">
                                <input type="hidden" name="submission_id" value="${submissionId}">
                                
                                <div class="form-group">
                                    <label class="form-label">Marks Obtained (Total: ${sub.total_marks})</label>
                                    <input type="number" name="marks_obtained" class="form-input" 
                                           min="0" max="${sub.total_marks}" step="0.5" 
                                           value="${isGraded ? sub.marks_obtained : ''}" 
                                           placeholder="Enter marks" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Feedback / Comments</label>
                                    <textarea name="feedback" class="form-input" rows="4" 
                                              placeholder="Provide constructive feedback to the student..."
                                              style="resize: vertical;">${isGraded && sub.feedback ? sub.feedback : ''}</textarea>
                                </div>

                                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                                    <button type="button" onclick="closeModal()" class="btn btn-outline">
                                        Cancel
                                    </button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> ${isGraded ? 'Update Grade' : 'Submit Grade'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    `;
                    
                    document.getElementById('gradingModal').style.display = 'flex';
                    
                    // Add form submit handler
                    document.getElementById('gradingForm').addEventListener('submit', handleGradeSubmission);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message,
                        confirmButtonColor: '#7c3aed'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load submission details',
                    confirmButtonColor: '#7c3aed'
                });
            }
        }

        // Handle grade submission
        async function handleGradeSubmission(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('api/grade_submission.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeModal();
                    
                    await Swal.fire({
                        icon: 'success',
                        title: 'Grade Submitted!',
                        text: result.message,
                        confirmButtonColor: '#7c3aed'
                    });
                    
                    location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message,
                        confirmButtonColor: '#7c3aed'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to submit grade. Please try again.',
                    confirmButtonColor: '#7c3aed'
                });
            }
        }

        // Download submission file
        async function downloadSubmission(submissionId) {
            window.location.href = `api/download_submission.php?id=${submissionId}`;
        }

        // Close modal
        function closeModal() {
            document.getElementById('gradingModal').style.display = 'none';
        }

        // Close modal on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Initialize theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        });
    </script>
</body>
</html>
