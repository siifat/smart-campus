<?php
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
$teacher_email = $_SESSION['teacher_email'] ?? '';

// Get current trimester
$stmt = $pdo->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1");
$current_trimester = $stmt->fetch(PDO::FETCH_ASSOC);
$current_trimester_id = $current_trimester['trimester_id'] ?? 1;

// Get teacher's courses with student counts
$stmt = $pdo->prepare("
    SELECT 
        c.course_id,
        c.course_code,
        c.course_name,
        e.section,
        COUNT(DISTINCT e.student_id) as student_count
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.teacher_id = ? 
      AND e.trimester_id = ?
      AND e.status = 'enrolled'
    GROUP BY c.course_id, e.section
    ORDER BY c.course_code, e.section
");
$stmt->execute([$teacher_id, $current_trimester_id]);
$teacher_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$filter_course = $_GET['course'] ?? '';
$filter_section = $_GET['section'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Build query for assignments
$query = "
    SELECT 
        a.*,
        c.course_code,
        c.course_name,
        COALESCE(a.section, 'All') as section_display,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.assignment_id) as submission_count,
        (SELECT COUNT(DISTINCT e.student_id) 
         FROM enrollments e 
         WHERE e.course_id = a.course_id 
           AND e.trimester_id = a.trimester_id
           AND e.teacher_id = ?
           AND e.status = 'enrolled'
           AND (a.section IS NULL OR e.section = a.section)
        ) as total_students
    FROM assignments a
    JOIN courses c ON a.course_id = c.course_id
    WHERE a.teacher_id = ? AND a.trimester_id = ?
";

$params = [$teacher_id, $teacher_id, $current_trimester_id];

if ($filter_course) {
    $query .= " AND a.course_id = ?";
    $params[] = $filter_course;
}

if ($filter_section) {
    $query .= " AND a.section = ?";
    $params[] = $filter_section;
}

if ($filter_type) {
    $query .= " AND a.assignment_type = ?";
    $params[] = $filter_type;
}

if ($filter_status === 'published') {
    $query .= " AND a.is_published = 1";
} elseif ($filter_status === 'draft') {
    $query .= " AND a.is_published = 0";
}

$query .= " ORDER BY a.due_date DESC, a.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_assignments,
        SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published_count,
        SUM(CASE WHEN is_published = 0 THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN is_bonus = 1 THEN 1 ELSE 0 END) as bonus_count,
        SUM(CASE WHEN due_date > NOW() THEN 1 ELSE 0 END) as upcoming_count,
        SUM(CASE WHEN due_date < NOW() THEN 1 ELSE 0 END) as past_count
    FROM assignments
    WHERE teacher_id = ? AND trimester_id = ?
";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([$teacher_id, $current_trimester_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Set page title and icon for topbar
$page_title = 'Assignments';
$page_icon = 'fas fa-clipboard-list';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Teacher Portal</title>
    
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
            transform: translateY(-2px);
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
        
        /* Modal Styles */
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
            max-width: 800px;
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
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
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
        <!-- Header -->
        <div class="fade-in-up" style="margin-bottom: 32px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
                        <i class="fas fa-clipboard-list" style="margin-right: 12px;"></i>Assignments
                    </h2>
                    <p style="color: var(--text-secondary);">
                        Manage assignments and bonus work for your courses
                    </p>
                </div>
                <button onclick="showCreateModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i>Create Assignment
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 fade-in-up" style="animation-delay: 0.1s;">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value"><?= $stats['total_assignments'] ?? 0 ?></div>
                <div class="stat-label">Total Assignments</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?= $stats['published_count'] ?? 0 ?></div>
                <div class="stat-label">Published</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?= $stats['draft_count'] ?? 0 ?></div>
                <div class="stat-label">Drafts</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white;">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-value"><?= $stats['bonus_count'] ?? 0 ?></div>
                <div class="stat-label">Bonus Work</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="fade-in-up" style="animation-delay: 0.2s; background: var(--card-bg); border-radius: 16px; padding: 24px; margin-bottom: 24px; border: 1px solid var(--border-color); box-shadow: 0 2px 8px var(--shadow-color);">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Course</label>
                    <select name="course" class="form-select">
                        <option value="">All Courses</option>
                        <?php 
                        $unique_courses = [];
                        foreach ($teacher_courses as $course) {
                            $key = $course['course_id'];
                            if (!isset($unique_courses[$key])) {
                                $unique_courses[$key] = $course;
                                $selected = ($filter_course == $course['course_id']) ? 'selected' : '';
                                echo "<option value='{$course['course_id']}' {$selected}>{$course['course_code']} - {$course['course_name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Section</label>
                    <select name="section" class="form-select">
                        <option value="">All Sections</option>
                        <?php 
                        $unique_sections = array_unique(array_column($teacher_courses, 'section'));
                        sort($unique_sections);
                        foreach ($unique_sections as $section) {
                            $selected = ($filter_section == $section) ? 'selected' : '';
                            echo "<option value='{$section}' {$selected}>{$section}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <?php 
                        $types = ['homework', 'project', 'quiz', 'lab', 'bonus', 'midterm', 'final'];
                        foreach ($types as $type) {
                            $selected = ($filter_type == $type) ? 'selected' : '';
                            echo "<option value='{$type}' {$selected}>" . ucfirst($type) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="published" <?= $filter_status === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    </select>
                </div>

                <div class="md:col-span-4 flex gap-3" style="margin-top: 16px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>Apply Filters
                    </button>
                    <a href="assignments.php" class="btn" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <i class="fas fa-times"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Assignments List -->
        <div class="fade-in-up" style="animation-delay: 0.3s; background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 2px 8px var(--shadow-color); overflow: hidden;">
            <div style="padding: 24px; border-bottom: 1px solid var(--border-color);">
                <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-list" style="margin-right: 8px;"></i>Assignment List
                    <span style="font-size: 14px; font-weight: 400; color: var(--text-secondary); margin-left: 8px;">(<?= count($assignments) ?> found)</span>
                </h2>
            </div>

            <?php if (empty($assignments)): ?>
                <div style="padding: 60px 24px; text-align: center;">
                    <div style="width: 80px; height: 80px; background: var(--bg-secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="fas fa-inbox" style="font-size: 40px; color: var(--text-secondary); opacity: 0.3;"></i>
                    </div>
                    <h3 style="font-size: 20px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">No Assignments Found</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 24px;">Create your first assignment to get started</p>
                    <button onclick="showCreateModal()" class="btn btn-primary">
                        <i class="fas fa-plus"></i>Create Assignment
                    </button>
                </div>
            <?php else: ?>
                <div style="border-top: 1px solid var(--border-color);">
                    <?php foreach ($assignments as $assignment): 
                        $due_date = new DateTime($assignment['due_date']);
                        $now = new DateTime();
                        $is_overdue = $due_date < $now;
                        $days_until = $due_date->diff($now)->days;
                                
                                // Type color mapping
                                $type_colors = [
                                    'homework' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                    'project' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
                                    'quiz' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                    'lab' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                    'bonus' => 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300',
                                    'midterm' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
                                    'final' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                                ];
                                $type_color = $type_colors[$assignment['assignment_type']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <div class="p-6 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors assignment-card">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($assignment['title']) ?>
                                            </h3>
                                            
                                            <?php if ($assignment['is_bonus']): ?>
                                                <span class="badge bg-gradient-to-r from-yellow-400 to-orange-500 text-white">
                                                    <i class="fas fa-star mr-1"></i>Bonus
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($assignment['is_published']): ?>
                                                <span class="badge bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                                    <i class="fas fa-check-circle mr-1"></i>Published
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                    <i class="fas fa-eye-slash mr-1"></i>Draft
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex items-center gap-6 text-sm text-gray-600 dark:text-gray-400 mb-3">
                                            <span class="badge <?= $type_color ?>">
                                                <?= ucfirst($assignment['assignment_type']) ?>
                                            </span>
                                            <span>
                                                <i class="fas fa-book-open mr-1"></i>
                                                <?= htmlspecialchars($assignment['course_code']) ?>
                                            </span>
                                            <span>
                                                <i class="fas fa-users mr-1"></i>
                                                Section <?= htmlspecialchars($assignment['section_display']) ?>
                                            </span>
                                            <span>
                                                <i class="fas fa-clipboard-check mr-1"></i>
                                                <?= $assignment['submission_count'] ?>/<?= $assignment['total_students'] ?> submitted
                                            </span>
                                            <span>
                                                <i class="fas fa-award mr-1"></i>
                                                <?= $assignment['total_marks'] ?> marks
                                            </span>
                                        </div>

                                        <p class="text-gray-700 dark:text-gray-300 mb-3 line-clamp-2">
                                            <?= htmlspecialchars(substr($assignment['description'], 0, 150)) ?>
                                            <?= strlen($assignment['description']) > 150 ? '...' : '' ?>
                                        </p>

                                        <div class="flex items-center gap-4">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-calendar text-<?= $is_overdue ? 'red' : 'purple' ?>-500"></i>
                                                <span class="text-sm font-medium text-<?= $is_overdue ? 'red' : 'gray' ?>-700 dark:text-<?= $is_overdue ? 'red' : 'gray' ?>-300">
                                                    Due: <?= $due_date->format('M j, Y g:i A') ?>
                                                    <?php if ($is_overdue): ?>
                                                        <span class="text-red-600">(Overdue)</span>
                                                    <?php else: ?>
                                                        <span class="text-gray-500">(in <?= $days_until ?> days)</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 ml-4">
                                        <button onclick="viewAssignment(<?= $assignment['assignment_id'] ?>)" class="p-2 text-gray-600 hover:text-purple-600 dark:text-gray-400 dark:hover:text-purple-400 transition-colors" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editAssignment(<?= $assignment['assignment_id'] ?>)" class="p-2 text-gray-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 transition-colors" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteAssignment(<?= $assignment['assignment_id'] ?>, '<?= htmlspecialchars($assignment['title'], ENT_QUOTES) ?>')" class="p-2 text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 transition-colors" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php if (!$assignment['is_published']): ?>
                                            <button onclick="publishAssignment(<?= $assignment['assignment_id'] ?>)" class="p-2 text-gray-600 hover:text-green-600 dark:text-gray-400 dark:hover:text-green-400 transition-colors" title="Publish">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>

    <!-- Create/Edit Assignment Modal -->
    <div id="assignmentModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="font-size: 24px; font-weight: 700; color: var(--text-primary);" id="modalTitle">Create New Assignment</h2>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer; padding: 0; width: 32px; height: 32px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="assignmentForm" class="modal-body">
                <input type="hidden" id="assignment_id" name="assignment_id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6" style="margin-bottom: 20px;">
                    <!-- Course Selection -->
                    <div class="form-group">
                        <label class="form-label">
                            Course <span style="color: #ef4444;">*</span>
                        </label>
                        <select id="course_id" name="course_id" required class="form-select">
                            <option value="">Select Course</option>
                            <?php 
                            $unique_courses = [];
                            foreach ($teacher_courses as $course) {
                                $key = $course['course_id'];
                                if (!isset($unique_courses[$key])) {
                                    $unique_courses[$key] = $course;
                                    echo "<option value='{$course['course_id']}'>{$course['course_code']} - {$course['course_name']}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Section Selection -->
                    <div class="form-group">
                        <label class="form-label">
                            Section <span style="color: #ef4444;">*</span>
                        </label>
                        <select id="section" name="section" required class="form-select">
                            <option value="">Select Course First</option>
                        </select>
                    </div>
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Assignment Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="title" name="title" required maxlength="200" placeholder="e.g., Data Structures Assignment 1" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-purple-500">
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Description <span class="text-red-500">*</span>
                    </label>
                    <textarea id="description" name="description" required rows="5" placeholder="Detailed instructions for the assignment..." class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-purple-500"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Assignment Type -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Type <span class="text-red-500">*</span>
                        </label>
                        <select id="assignment_type" name="assignment_type" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-purple-500">
                            <option value="homework">Homework</option>
                            <option value="project">Project</option>
                            <option value="quiz">Quiz</option>
                            <option value="lab">Lab</option>
                            <option value="bonus">Bonus</option>
                            <option value="midterm">Midterm</option>
                            <option value="final">Final</option>
                        </select>
                    </div>

                    <!-- Total Marks -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Total Marks <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="total_marks" name="total_marks" required min="0" step="0.01" value="100" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-purple-500">
                    </div>

                    <!-- Weight Percentage -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Weight % (Optional)
                        </label>
                        <input type="number" id="weight_percentage" name="weight_percentage" min="0" max="100" step="0.01" placeholder="e.g., 10" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <!-- Due Date -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Due Date & Time <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="due_date" name="due_date" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-purple-500">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Late Submission -->
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" id="late_submission_allowed" name="late_submission_allowed" checked class="w-5 h-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Allow Late Submissions</span>
                        </label>
                    </div>

                    <!-- Late Penalty -->
                    <div id="latePenaltyDiv">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Late Penalty (% per day)
                        </label>
                        <input type="number" id="late_penalty_per_day" name="late_penalty_per_day" min="0" max="100" step="0.01" value="5" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <!-- File Upload -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Attach File (Optional)
                    </label>
                    <input type="file" id="file_upload" name="file_upload" accept=".pdf,.doc,.docx,.txt,.zip,.rar" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-purple-500">
                    <p class="text-xs text-gray-500 mt-1">Supported: PDF, DOC, DOCX, TXT, ZIP, RAR (Max 10MB)</p>
                </div>

                <!-- Checkboxes -->
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" id="is_bonus" name="is_bonus" class="w-5 h-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                            <i class="fas fa-star text-yellow-500 mr-1"></i>Mark as Bonus
                        </span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" id="is_published" name="is_published" class="w-5 h-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                            <i class="fas fa-paper-plane text-green-500 mr-1"></i>Publish Immediately
                        </span>
                    </label>
                </div>

                <!-- Buttons -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="closeModal()" class="px-6 py-3 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-3 rounded-lg bg-gradient-to-r from-purple-600 to-purple-800 text-white font-semibold hover:from-purple-700 hover:to-purple-900 transition-all">
                        <i class="fas fa-save mr-2"></i>Save Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Course-Section mapping from PHP
        const courseSections = <?= json_encode(array_reduce($teacher_courses, function($carry, $item) {
            $carry[$item['course_id']][] = $item['section'];
            return $carry;
        }, [])) ?>;

        // Update sections when course is selected
        document.getElementById('course_id').addEventListener('change', function() {
            const courseId = this.value;
            const sectionSelect = document.getElementById('section');
            sectionSelect.innerHTML = '<option value="">Select Section</option>';
            
            if (courseId && courseSections[courseId]) {
                // Add all sections option
                sectionSelect.innerHTML += '<option value="">All Sections</option>';
                
                // Add individual sections
                courseSections[courseId].forEach(section => {
                    sectionSelect.innerHTML += `<option value="${section}">${section}</option>`;
                });
            }
        });

        // Toggle late penalty field
        document.getElementById('late_submission_allowed').addEventListener('change', function() {
            document.getElementById('latePenaltyDiv').style.display = this.checked ? 'block' : 'none';
        });

        // Show create modal
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create New Assignment';
            document.getElementById('assignmentForm').reset();
            document.getElementById('assignment_id').value = '';
            document.getElementById('assignmentModal').style.display = 'flex';
        }

        // Close modal
        function closeModal() {
            document.getElementById('assignmentModal').style.display = 'none';
        }

        // Submit form
        document.getElementById('assignmentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const assignmentId = document.getElementById('assignment_id').value;
            
            try {
                const response = await fetch('api/manage_assignment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: assignmentId ? 'Assignment Updated!' : 'Assignment Created!',
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
                    text: 'Failed to save assignment. Please try again.',
                    confirmButtonColor: '#7c3aed'
                });
            }
        });

        // View assignment
        function viewAssignment(id) {
            window.location.href = `assignment_detail.php?id=${id}`;
        }

        // Edit assignment
        async function editAssignment(id) {
            try {
                const response = await fetch(`api/get_assignment.php?id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    
                    document.getElementById('modalTitle').textContent = 'Edit Assignment';
                    document.getElementById('assignment_id').value = data.assignment_id;
                    document.getElementById('course_id').value = data.course_id;
                    
                    // Trigger course change to load sections
                    document.getElementById('course_id').dispatchEvent(new Event('change'));
                    
                    setTimeout(() => {
                        document.getElementById('section').value = data.section || '';
                        document.getElementById('title').value = data.title;
                        document.getElementById('description').value = data.description;
                        document.getElementById('assignment_type').value = data.assignment_type;
                        document.getElementById('total_marks').value = data.total_marks;
                        document.getElementById('weight_percentage').value = data.weight_percentage || '';
                        document.getElementById('due_date').value = data.due_date.replace(' ', 'T').slice(0, 16);
                        document.getElementById('late_submission_allowed').checked = data.late_submission_allowed == 1;
                        document.getElementById('late_penalty_per_day').value = data.late_penalty_per_day;
                        document.getElementById('is_bonus').checked = data.is_bonus == 1;
                        document.getElementById('is_published').checked = data.is_published == 1;
                        
                        document.getElementById('assignmentModal').classList.remove('hidden');
                    }, 100);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load assignment data.',
                    confirmButtonColor: '#7c3aed'
                });
            }
        }

        // Delete assignment
        async function deleteAssignment(id, title) {
            const result = await Swal.fire({
                icon: 'warning',
                title: 'Delete Assignment?',
                html: `Are you sure you want to delete:<br><strong>${title}</strong><br><br>This action cannot be undone.`,
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            });
            
            if (result.isConfirmed) {
                try {
                    const response = await fetch('api/delete_assignment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ assignment_id: id })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Assignment has been deleted.',
                            confirmButtonColor: '#7c3aed'
                        });
                        location.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message,
                            confirmButtonColor: '#7c3aed'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to delete assignment.',
                        confirmButtonColor: '#7c3aed'
                    });
                }
            }
        }

        // Publish assignment
        async function publishAssignment(id) {
            const result = await Swal.fire({
                icon: 'question',
                title: 'Publish Assignment?',
                text: 'Students will be able to see and submit this assignment.',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Publish',
                cancelButtonText: 'Cancel'
            });
            
            if (result.isConfirmed) {
                try {
                    const response = await fetch('api/publish_assignment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ assignment_id: id })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Published!',
                            text: 'Assignment is now visible to students.',
                            confirmButtonColor: '#7c3aed'
                        });
                        location.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message,
                            confirmButtonColor: '#7c3aed'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to publish assignment.',
                        confirmButtonColor: '#7c3aed'
                    });
                }
            }
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Initialize dark mode on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            
            // Check if action=create is in URL and open modal
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'create') {
                showCreateModal();
                // Remove the action parameter from URL to prevent reopening on refresh
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>
