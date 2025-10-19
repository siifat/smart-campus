<?php
/**
 * Student Course Materials Viewer - UIU Smart Campus
 * View and download course materials uploaded by teacher
 */
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Location: ../login.html?error=unauthorized');
    exit;
}

require_once('../config/database.php');

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$course_id = $_GET['course_id'] ?? null;
$section = $_GET['section'] ?? null;

if (!$course_id || !$section) {
    header('Location: courses.php');
    exit;
}

// Fetch student data including points
$stmt = $conn->prepare("SELECT *, COALESCE(total_points, 0) as total_points FROM students WHERE student_id = ?");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$total_points = $student['total_points'] ?? 0;
$stmt->close();

// Get current trimester
$current_trimester = $conn->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_trimester_id = $current_trimester['trimester_id'] ?? 1;

// Verify student is enrolled in this course-section
$stmt = $conn->prepare("
    SELECT 
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        c.course_type,
        e.section,
        t.full_name as teacher_name,
        t.initial as teacher_initial,
        d.department_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    LEFT JOIN teachers t ON e.teacher_id = t.teacher_id
    LEFT JOIN departments d ON c.department_id = d.department_id
    WHERE e.student_id = ?
        AND e.course_id = ?
        AND e.section = ?
        AND e.trimester_id = ?
        AND e.status = 'enrolled'
");
$stmt->bind_param('sisi', $student_id, $course_id, $section, $current_trimester_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    header('Location: courses.php?error=not_enrolled');
    exit;
}

// Get filter type
$filter_type = $_GET['type'] ?? 'all';

// Fetch course materials
$materials_query = "
    SELECT 
        cm.*,
        t.full_name as uploaded_by
    FROM course_materials cm
    LEFT JOIN teachers t ON cm.teacher_id = t.teacher_id
    WHERE cm.course_id = ?
        AND cm.trimester_id = ?
        AND (cm.section IS NULL OR cm.section = ?)
        AND cm.is_published = 1
";

if ($filter_type !== 'all') {
    $materials_query .= " AND cm.content_type = ?";
}

$materials_query .= " ORDER BY cm.created_at DESC";

$stmt = $conn->prepare($materials_query);
if ($filter_type !== 'all') {
    $stmt->bind_param('iiss', $course_id, $current_trimester_id, $section, $filter_type);
} else {
    $stmt->bind_param('iis', $course_id, $current_trimester_id, $section);
}
$stmt->execute();
$materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_materials,
        SUM(CASE WHEN content_type = 'pdf' THEN 1 ELSE 0 END) as pdf_count,
        SUM(CASE WHEN content_type = 'video' THEN 1 ELSE 0 END) as video_count,
        SUM(CASE WHEN content_type = 'link' THEN 1 ELSE 0 END) as link_count,
        SUM(CASE WHEN content_type = 'document' THEN 1 ELSE 0 END) as document_count,
        SUM(CASE WHEN content_type = 'code' THEN 1 ELSE 0 END) as code_count,
        SUM(view_count) as total_views,
        SUM(download_count) as total_downloads
    FROM course_materials
    WHERE course_id = ?
        AND trimester_id = ?
        AND (section IS NULL OR section = ?)
        AND is_published = 1
");
$stmt->bind_param('iis', $course_id, $current_trimester_id, $section);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = $course['course_code'] . ' - Course Materials';
$page_icon = 'fas fa-folder-open';
$show_page_title = true;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - UIU Smart Campus</title>
    
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
    
    <!-- Highlight.js for code syntax highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    
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
            font-size: 15px;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(4px);
        }
        
        .nav-item.active {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .nav-item i {
            font-size: 18px;
            width: 24px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: calc(var(--topbar-height) + 30px) 30px 30px;
            min-height: 100vh;
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
            z-index: 99;
            box-shadow: 0 2px 10px var(--shadow-color);
        }
        
        .search-box {
            position: relative;
            flex: 1;
            max-width: 500px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 48px;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 12px;
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
            font-size: 16px;
        }
        
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .icon-btn {
            position: relative;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 18px;
        }
        
        .icon-btn:hover {
            background: #f68b1f;
            border-color: #f68b1f;
            color: white;
            transform: translateY(-2px);
        }
        
        .icon-btn .badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #ef4444;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 7px;
            border-radius: 10px;
            border: 2px solid var(--card-bg);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .user-profile:hover {
            border-color: #f68b1f;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
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
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 24px;
            box-shadow: 0 8px 32px var(--shadow-color);
            transition: all 0.3s ease;
        }
        
        /* Course Header */
        .course-header {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            border-radius: 20px;
            padding: 32px;
            color: white;
            margin-bottom: 24px;
        }
        
        .course-header h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
        }
        
        .course-header p {
            font-size: 16px;
            opacity: 0.95;
        }
        
        .course-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        
        .course-meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 12px 16px;
            border-radius: 12px;
        }
        
        .course-meta-item i {
            font-size: 18px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card-small {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-card-small i {
            font-size: 32px;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-card-small .value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .stat-card-small .label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 24px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 12px;
            box-shadow: 0 4px 20px var(--shadow-color);
        }
        
        .filter-tab {
            padding: 10px 20px;
            border-radius: 10px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-tab:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
        }
        
        /* Material Card */
        .material-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px var(--shadow-color);
        }
        
        .material-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px var(--shadow-color);
            border-color: #f68b1f;
        }
        
        .material-header {
            display: flex;
            align-items: start;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .material-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }
        
        .material-icon.pdf { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .material-icon.document { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .material-icon.video { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .material-icon.link { background: linear-gradient(135deg, #10b981, #059669); }
        .material-icon.code { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .material-icon.other { background: linear-gradient(135deg, #6b7280, #4b5563); }
        
        .material-info {
            flex: 1;
            min-width: 0;
        }
        
        .material-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        
        .material-description {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 12px;
        }
        
        .material-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .material-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .material-meta-item i {
            color: #f68b1f;
        }
        
        .material-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(246, 139, 31, 0.3);
        }
        
        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-type {
            background: rgba(246, 139, 31, 0.1);
            color: #f68b1f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            background: var(--bg-secondary);
            border-color: #f68b1f;
            color: #f68b1f;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: var(--text-secondary);
            opacity: 0.5;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        /* Code Block Styles */
        .swal2-html-container pre {
            text-align: left;
            background: #282c34;
            border-radius: 8px;
            padding: 16px;
            overflow-x: auto;
            margin: 0;
        }
        
        .swal2-html-container code {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.5;
        }
        
        /* Copy Button in Code Modal */
        .copy-code-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 6px 12px;
            background: rgba(246, 139, 31, 0.9);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .copy-code-btn:hover {
            background: rgba(246, 139, 31, 1);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: calc(var(--topbar-height) + 20px) 20px 20px;
            }
            
            .material-header {
                flex-direction: column;
            }
            
            .material-actions {
                width: 100%;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
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
            animation: fadeInUp 0.5s ease forwards;
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
        <!-- Back Button -->
        <a href="courses.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Courses
        </a>
        
        <!-- Course Header -->
        <div class="course-header fade-in-up">
            <h1><?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_name']); ?></h1>
            <p>Section <?php echo htmlspecialchars($course['section']); ?></p>
            
            <div class="course-meta-grid">
                <div class="course-meta-item">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <div>
                        <div style="font-size: 12px; opacity: 0.8;">Instructor</div>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($course['teacher_name']); ?></div>
                    </div>
                </div>
                <div class="course-meta-item">
                    <i class="fas fa-building"></i>
                    <div>
                        <div style="font-size: 12px; opacity: 0.8;">Department</div>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($course['department_name']); ?></div>
                    </div>
                </div>
                <div class="course-meta-item">
                    <i class="fas fa-award"></i>
                    <div>
                        <div style="font-size: 12px; opacity: 0.8;">Credits</div>
                        <div style="font-weight: 600;"><?php echo $course['credit_hours']; ?> Credit Hours</div>
                    </div>
                </div>
                <div class="course-meta-item">
                    <i class="fas fa-book-open"></i>
                    <div>
                        <div style="font-size: 12px; opacity: 0.8;">Type</div>
                        <div style="font-weight: 600;"><?php echo ucfirst($course['course_type']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Grid -->
        <div class="stats-grid fade-in-up" style="animation-delay: 0.1s;">
            <div class="stat-card-small">
                <i class="fas fa-folder"></i>
                <div class="value"><?php echo $stats['total_materials'] ?? 0; ?></div>
                <div class="label">Total Materials</div>
            </div>
            <div class="stat-card-small">
                <i class="fas fa-file-pdf"></i>
                <div class="value"><?php echo $stats['pdf_count'] ?? 0; ?></div>
                <div class="label">PDFs</div>
            </div>
            <div class="stat-card-small">
                <i class="fas fa-video"></i>
                <div class="value"><?php echo $stats['video_count'] ?? 0; ?></div>
                <div class="label">Videos</div>
            </div>
            <div class="stat-card-small">
                <i class="fas fa-code"></i>
                <div class="value"><?php echo $stats['code_count'] ?? 0; ?></div>
                <div class="label">Code Snippets</div>
            </div>
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs fade-in-up" style="animation-delay: 0.2s;">
            <a href="?course_id=<?php echo $course_id; ?>&section=<?php echo urlencode($section); ?>&type=all" 
               class="filter-tab <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> All Materials
            </a>
            <a href="?course_id=<?php echo $course_id; ?>&section=<?php echo urlencode($section); ?>&type=pdf" 
               class="filter-tab <?php echo $filter_type === 'pdf' ? 'active' : ''; ?>">
                <i class="fas fa-file-pdf"></i> PDFs
            </a>
            <a href="?course_id=<?php echo $course_id; ?>&section=<?php echo urlencode($section); ?>&type=video" 
               class="filter-tab <?php echo $filter_type === 'video' ? 'active' : ''; ?>">
                <i class="fas fa-video"></i> Videos
            </a>
            <a href="?course_id=<?php echo $course_id; ?>&section=<?php echo urlencode($section); ?>&type=link" 
               class="filter-tab <?php echo $filter_type === 'link' ? 'active' : ''; ?>">
                <i class="fas fa-link"></i> Links
            </a>
            <a href="?course_id=<?php echo $course_id; ?>&section=<?php echo urlencode($section); ?>&type=code" 
               class="filter-tab <?php echo $filter_type === 'code' ? 'active' : ''; ?>">
                <i class="fas fa-code"></i> Code
            </a>
            <a href="?course_id=<?php echo $course_id; ?>&section=<?php echo urlencode($section); ?>&type=document" 
               class="filter-tab <?php echo $filter_type === 'document' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Documents
            </a>
        </div>
        
        <!-- Materials List -->
        <div class="fade-in-up" style="animation-delay: 0.3s;">
            <?php if (count($materials) > 0): ?>
                <?php foreach ($materials as $material): ?>
                    <div class="material-card">
                        <div class="material-header">
                            <div class="material-icon <?php echo $material['content_type']; ?>">
                                <?php
                                $icons = [
                                    'pdf' => 'fa-file-pdf',
                                    'document' => 'fa-file-alt',
                                    'video' => 'fa-video',
                                    'link' => 'fa-link',
                                    'code' => 'fa-code',
                                    'other' => 'fa-file'
                                ];
                                ?>
                                <i class="fas <?php echo $icons[$material['content_type']] ?? 'fa-file'; ?>"></i>
                            </div>
                            
                            <div class="material-info">
                                <div class="material-title"><?php echo htmlspecialchars($material['title']); ?></div>
                                <?php if ($material['description']): ?>
                                    <div class="material-description"><?php echo nl2br(htmlspecialchars($material['description'])); ?></div>
                                <?php endif; ?>
                                
                                <div class="material-meta">
                                    <div class="material-meta-item">
                                        <i class="fas fa-tag"></i>
                                        <span class="badge badge-type"><?php echo strtoupper($material['content_type']); ?></span>
                                    </div>
                                    <div class="material-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date('M d, Y', strtotime($material['created_at'])); ?></span>
                                    </div>
                                    <?php if ($material['uploaded_by']): ?>
                                        <div class="material-meta-item">
                                            <i class="fas fa-user"></i>
                                            <span><?php echo htmlspecialchars($material['uploaded_by']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="material-meta-item">
                                        <i class="fas fa-eye"></i>
                                        <span><?php echo number_format($material['view_count']); ?> views</span>
                                    </div>
                                    <?php if ($material['file_path']): ?>
                                        <div class="material-meta-item">
                                            <i class="fas fa-download"></i>
                                            <span><?php echo number_format($material['download_count']); ?> downloads</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($material['file_size']): ?>
                                        <div class="material-meta-item">
                                            <i class="fas fa-hdd"></i>
                                            <span><?php echo number_format($material['file_size'] / 1024 / 1024, 2); ?> MB</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="material-actions">
                                <?php if ($material['external_url']): ?>
                                    <a href="<?php echo htmlspecialchars($material['external_url']); ?>" 
                                       target="_blank"
                                       onclick="incrementViewCount(<?php echo $material['content_id']; ?>)"
                                       class="btn btn-primary">
                                        <i class="fas fa-external-link-alt"></i> Open
                                    </a>
                                <?php elseif ($material['file_path']): ?>
                                    <a href="api/download_material.php?content_id=<?php echo $material['content_id']; ?>" 
                                       class="btn btn-primary">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php elseif ($material['content_text']): ?>
                                    <button onclick="viewCode(<?php echo $material['content_id']; ?>)"
                                            class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Code
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Materials Found</h3>
                    <p>No course materials have been uploaded for this section yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        // View code snippet in modal with copy functionality
        async function viewCode(id) {
            try {
                const response = await fetch(`api/view_material.php?content_id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    const codeHtml = escapeHtml(data.material.content_text);
                    
                    Swal.fire({
                        title: data.material.title,
                        html: `
                            <div style="text-align: left; position: relative;">
                                ${data.material.description ? `<p style="color: #64748b; margin-bottom: 16px;">${escapeHtml(data.material.description)}</p>` : ''}
                                <div style="position: relative;">
                                    <button onclick="copyCode(this)" class="copy-code-btn" style="position: absolute; top: 10px; right: 10px; z-index: 10;">
                                        <i class="fas fa-copy"></i> Copy
                                    </button>
                                    <pre><code class="language-javascript">${codeHtml}</code></pre>
                                </div>
                            </div>
                        `,
                        width: '900px',
                        showCloseButton: true,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'code-modal-popup'
                        },
                        didOpen: () => {
                            // Apply syntax highlighting
                            if (typeof hljs !== 'undefined') {
                                document.querySelectorAll('pre code').forEach((block) => {
                                    hljs.highlightElement(block);
                                });
                            }
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#f68b1f'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load code snippet',
                    confirmButtonColor: '#f68b1f'
                });
            }
        }
        
        // Copy code to clipboard
        function copyCode(button) {
            const codeBlock = button.parentElement.querySelector('code');
            const text = codeBlock.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                const originalHtml = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                button.style.background = '#10b981';
                
                setTimeout(() => {
                    button.innerHTML = originalHtml;
                    button.style.background = 'rgba(246, 139, 31, 0.9)';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Copy Failed',
                    text: 'Could not copy code to clipboard',
                    confirmButtonColor: '#f68b1f',
                    timer: 2000
                });
            });
        }
        
        // Increment view count for external links
        function incrementViewCount(id) {
            fetch(`api/view_material.php?content_id=${id}`)
                .catch(error => console.error('Error updating view count:', error));
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
