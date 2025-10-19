<?php
/**
 * Course Content Management Page
 * Upload and manage course materials
 */
session_start();

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php?error=unauthorized');
    exit();
}

// Check session timeout
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

// Get course and section from URL
$course_id = $_GET['course_id'] ?? null;
$section = $_GET['section'] ?? null;

if (!$course_id || !$section) {
    header('Location: courses.php');
    exit();
}

// Get current trimester
$stmt = $pdo->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1");
$current_trimester = $stmt->fetch(PDO::FETCH_ASSOC);
$current_trimester_id = $current_trimester['trimester_id'] ?? 1;

// Verify teacher has access to this course-section
$verify_stmt = $pdo->prepare("
    SELECT 
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        c.course_type,
        c.description,
        e.section,
        d.department_name,
        COUNT(DISTINCT e.student_id) as student_count
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    JOIN departments d ON c.department_id = d.department_id
    WHERE e.teacher_id = ? 
      AND e.course_id = ? 
      AND e.section = ?
      AND e.trimester_id = ?
      AND e.status = 'enrolled'
    GROUP BY c.course_id, e.section
    LIMIT 1
");
$verify_stmt->execute([$teacher_id, $course_id, $section, $current_trimester_id]);
$course = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: courses.php');
    exit();
}

// Get filter parameter
$filter_type = $_GET['type'] ?? '';

// Fetch course materials
$materials_query = "
    SELECT 
        content_id,
        title,
        description,
        content_type,
        file_path,
        external_url,
        file_size,
        is_published,
        view_count,
        download_count,
        created_at
    FROM course_materials
    WHERE course_id = ? 
      AND teacher_id = ?
      AND section = ?
      AND trimester_id = ?
";

$params = [$course_id, $teacher_id, $section, $current_trimester_id];

if ($filter_type) {
    $materials_query .= " AND content_type = ?";
    $params[] = $filter_type;
}

$materials_query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($materials_query);
$stmt->execute($params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_materials,
        SUM(CASE WHEN content_type = 'pdf' THEN 1 ELSE 0 END) as pdf_count,
        SUM(CASE WHEN content_type = 'video' THEN 1 ELSE 0 END) as video_count,
        SUM(CASE WHEN content_type = 'link' THEN 1 ELSE 0 END) as link_count,
        SUM(view_count) as total_views,
        SUM(download_count) as total_downloads
    FROM course_materials
    WHERE course_id = ? AND teacher_id = ? AND section = ? AND trimester_id = ?
");
$stats_stmt->execute([$course_id, $teacher_id, $section, $current_trimester_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Page configuration
$page_title = $course['course_code'] . ' - ' . $course['course_name'];
$page_icon = 'fas fa-book-open';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Teacher Portal</title>
    
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
    
    <!-- Highlight.js for code snippets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    
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
            font-size: 16px;
            font-weight: 700;
        }
        
        /* Stats Cards */
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px var(--shadow-color);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-color);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 12px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        
        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* Material Card */
        .material-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px var(--shadow-color);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .material-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
            border-color: #667eea;
        }
        
        .material-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }
        
        .material-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .material-desc {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 16px;
            line-height: 1.6;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-pdf { background: #fee2e2; color: #991b1b; }
        .badge-video { background: #dbeafe; color: #1e40af; }
        .badge-link { background: #d1fae5; color: #065f46; }
        .badge-code { background: #fef3c7; color: #92400e; }
        .badge-document { background: #e9d5ff; color: #6b21a8; }
        .badge-other { background: #f1f5f9; color: #475569; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 32px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .modal-header h3 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .modal-body {
            margin-bottom: 24px;
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
        
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 32px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-area:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        /* Content Type Toggle */
        .content-type-toggle {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .type-btn {
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 13px;
            text-align: center;
        }
        
        .type-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .type-btn.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .type-btn i {
            display: block;
            font-size: 20px;
            margin-bottom: 6px;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
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
            animation: fadeInUp 0.6s ease;
        }
    </style>
</head>
<body>
    <?php require_once('includes/sidebar.php'); ?>
    <?php require_once('includes/topbar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" style="padding: calc(var(--topbar-height) + 32px) 32px 32px 32px;">
        <!-- Back Button and Header -->
        <div class="fade-in-up" style="margin-bottom: 32px;">
            <a href="courses.php" class="btn btn-secondary" style="margin-bottom: 16px;">
                <i class="fas fa-arrow-left"></i> Back to Courses
            </a>
            
            <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 16px;">
                <div>
                    <div style="font-size: 13px; font-weight: 700; color: #667eea; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                        <?= htmlspecialchars($course['course_code']) ?> - Section <?= htmlspecialchars($course['section']) ?>
                    </div>
                    <h1 style="font-size: 32px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">
                        <?= htmlspecialchars($course['course_name']) ?>
                    </h1>
                    <p style="font-size: 16px; color: var(--text-secondary);">
                        <i class="fas fa-users"></i> <?= $course['student_count'] ?> Students • 
                        <i class="fas fa-star"></i> <?= $course['credit_hours'] ?> Credits • 
                        <i class="fas fa-book"></i> <?= ucfirst($course['course_type']) ?>
                    </p>
                </div>
                <button onclick="showUploadModal()" class="btn btn-primary" style="margin-top: 8px;">
                    <i class="fas fa-plus-circle"></i> Upload Material
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8 fade-in-up" style="animation-delay: 0.1s;">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="stat-value"><?= $stats['total_materials'] ?? 0 ?></div>
                <div class="stat-label">Total Materials</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white;">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="stat-value"><?= $stats['pdf_count'] ?? 0 ?></div>
                <div class="stat-label">PDFs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;">
                    <i class="fas fa-video"></i>
                </div>
                <div class="stat-value"><?= $stats['video_count'] ?? 0 ?></div>
                <div class="stat-label">Videos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                    <i class="fas fa-link"></i>
                </div>
                <div class="stat-value"><?= $stats['link_count'] ?? 0 ?></div>
                <div class="stat-label">Links</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['total_views'] ?? 0) ?></div>
                <div class="stat-label">Total Views</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white;">
                    <i class="fas fa-download"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['total_downloads'] ?? 0) ?></div>
                <div class="stat-label">Downloads</div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="fade-in-up" style="animation-delay: 0.2s; margin-bottom: 24px;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="?course_id=<?= $course_id ?>&section=<?= urlencode($section) ?>" 
                   class="btn <?= !$filter_type ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                    <i class="fas fa-th-large"></i> All
                </a>
                <a href="?course_id=<?= $course_id ?>&section=<?= urlencode($section) ?>&type=pdf" 
                   class="btn <?= $filter_type === 'pdf' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                    <i class="fas fa-file-pdf"></i> PDFs
                </a>
                <a href="?course_id=<?= $course_id ?>&section=<?= urlencode($section) ?>&type=video" 
                   class="btn <?= $filter_type === 'video' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                    <i class="fas fa-video"></i> Videos
                </a>
                <a href="?course_id=<?= $course_id ?>&section=<?= urlencode($section) ?>&type=link" 
                   class="btn <?= $filter_type === 'link' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                    <i class="fas fa-link"></i> Links
                </a>
                <a href="?course_id=<?= $course_id ?>&section=<?= urlencode($section) ?>&type=code" 
                   class="btn <?= $filter_type === 'code' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                    <i class="fas fa-code"></i> Code
                </a>
                <a href="?course_id=<?= $course_id ?>&section=<?= urlencode($section) ?>&type=document" 
                   class="btn <?= $filter_type === 'document' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                    <i class="fas fa-file-alt"></i> Documents
                </a>
            </div>
        </div>

        <!-- Materials Grid -->
        <div class="fade-in-up" style="animation-delay: 0.3s;">
            <?php if (count($materials) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($materials as $material): 
                        $icon_map = [
                            'pdf' => ['icon' => 'fa-file-pdf', 'color' => '#ef4444'],
                            'video' => ['icon' => 'fa-video', 'color' => '#3b82f6'],
                            'link' => ['icon' => 'fa-link', 'color' => '#10b981'],
                            'code' => ['icon' => 'fa-code', 'color' => '#f59e0b'],
                            'document' => ['icon' => 'fa-file-alt', 'color' => '#8b5cf6'],
                            'other' => ['icon' => 'fa-file', 'color' => '#6b7280']
                        ];
                        $icon_info = $icon_map[$material['content_type']] ?? $icon_map['other'];
                    ?>
                    <div class="material-card">
                        <div class="material-icon" style="background: <?= $icon_info['color'] ?>15; color: <?= $icon_info['color'] ?>;">
                            <i class="fas <?= $icon_info['icon'] ?>"></i>
                        </div>
                        
                        <div class="material-title">
                            <?= htmlspecialchars($material['title']) ?>
                        </div>
                        
                        <?php if ($material['description']): ?>
                        <div class="material-desc">
                            <?= htmlspecialchars(substr($material['description'], 0, 120)) ?><?= strlen($material['description']) > 120 ? '...' : '' ?>
                        </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
                            <span class="badge badge-<?= $material['content_type'] ?>">
                                <i class="fas <?= $icon_info['icon'] ?>"></i>
                                <?= ucfirst($material['content_type']) ?>
                            </span>
                            <?php if (!$material['is_published']): ?>
                            <span class="badge" style="background: #fef3c7; color: #92400e;">
                                <i class="fas fa-eye-slash"></i> Draft
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid var(--border-color); font-size: 12px; color: var(--text-secondary);">
                            <div>
                                <i class="fas fa-eye"></i> <?= $material['view_count'] ?> views •
                                <i class="fas fa-download"></i> <?= $material['download_count'] ?> downloads
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 8px; margin-top: 16px;">
                            <?php if ($material['content_type'] === 'link' && $material['external_url']): ?>
                                <a href="<?= htmlspecialchars($material['external_url']) ?>" target="_blank" class="btn btn-primary btn-sm" style="flex: 1; justify-content: center;">
                                    <i class="fas fa-external-link-alt"></i> Open
                                </a>
                            <?php elseif ($material['file_path']): ?>
                                <a href="api/download_material.php?id=<?= $material['content_id'] ?>" class="btn btn-primary btn-sm" style="flex: 1; justify-content: center;">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            <?php endif; ?>
                            <button onclick="editMaterial(<?= $material['content_id'] ?>)" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteMaterial(<?= $material['content_id'] ?>, '<?= htmlspecialchars($material['title'], ENT_QUOTES) ?>')" class="btn btn-secondary btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 80px 20px; background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border-color);">
                    <i class="fas fa-folder-open" style="font-size: 64px; color: var(--border-color); margin-bottom: 24px;"></i>
                    <h3 style="font-size: 20px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">No Materials Yet</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 24px;">
                        Start uploading course materials for your students
                    </p>
                    <button onclick="showUploadModal()" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Upload First Material
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Upload Material Modal -->
    <div id="uploadModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Upload Course Material</h3>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" id="content_id" name="content_id" value="">
                <input type="hidden" name="course_id" value="<?= $course_id ?>">
                <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">
                
                <!-- Content Type Selection -->
                <div class="form-group">
                    <label class="form-label">Content Type</label>
                    <div class="content-type-toggle">
                        <div class="type-btn active" onclick="selectContentType('pdf')" data-type="pdf">
                            <i class="fas fa-file-pdf"></i>
                            <span>PDF</span>
                        </div>
                        <div class="type-btn" onclick="selectContentType('document')" data-type="document">
                            <i class="fas fa-file-alt"></i>
                            <span>Document</span>
                        </div>
                        <div class="type-btn" onclick="selectContentType('video')" data-type="video">
                            <i class="fas fa-video"></i>
                            <span>Video</span>
                        </div>
                        <div class="type-btn" onclick="selectContentType('link')" data-type="link">
                            <i class="fas fa-link"></i>
                            <span>Link</span>
                        </div>
                        <div class="type-btn" onclick="selectContentType('code')" data-type="code">
                            <i class="fas fa-code"></i>
                            <span>Code</span>
                        </div>
                        <div class="type-btn" onclick="selectContentType('other')" data-type="other">
                            <i class="fas fa-file"></i>
                            <span>Other</span>
                        </div>
                    </div>
                    <input type="hidden" id="content_type" name="content_type" value="pdf" required>
                </div>
                
                <!-- Title -->
                <div class="form-group">
                    <label class="form-label">Title <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="title" name="title" class="form-input" placeholder="e.g., Lecture 1: Introduction to Data Structures" required>
                </div>
                
                <!-- Description -->
                <div class="form-group">
                    <label class="form-label">Description (Optional)</label>
                    <textarea id="description" name="description" class="form-textarea" placeholder="Brief description of this material..."></textarea>
                </div>
                
                <!-- File Upload (for pdf, document, other) -->
                <div class="form-group" id="fileUploadGroup">
                    <label class="form-label">Upload File</label>
                    <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #667eea; margin-bottom: 16px;"></i>
                        <p style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">
                            Click to upload or drag and drop
                        </p>
                        <p style="font-size: 13px; color: var(--text-secondary);">
                            PDF, DOC, DOCX, PPT, ZIP (Max 50MB)
                        </p>
                    </div>
                    <input type="file" id="fileInput" name="file" style="display: none;" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar">
                    <div id="fileInfo" style="margin-top: 12px; display: none;"></div>
                </div>
                
                <!-- External URL (for video, link) -->
                <div class="form-group" id="urlGroup" style="display: none;">
                    <label class="form-label">URL</label>
                    <input type="url" id="external_url" name="external_url" class="form-input" placeholder="https://youtube.com/... or https://drive.google.com/...">
                    <p style="font-size: 12px; color: var(--text-secondary); margin-top: 6px;">
                        <i class="fas fa-info-circle"></i> Supports: YouTube, Google Drive, Dropbox, OneDrive, etc.
                    </p>
                </div>
                
                <!-- Code Content (for code) -->
                <div class="form-group" id="codeGroup" style="display: none;">
                    <label class="form-label">Code Snippet</label>
                    <textarea id="content_text" name="content_text" class="form-textarea" style="font-family: 'Courier New', monospace; min-height: 200px;" placeholder="Paste your code here..."></textarea>
                    <p style="font-size: 12px; color: var(--text-secondary); margin-top: 6px;">
                        <i class="fas fa-info-circle"></i> Syntax highlighting will be applied automatically
                    </p>
                </div>
                
                <!-- Publish Option -->
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                        <input type="checkbox" id="is_published" name="is_published" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
                        <span class="form-label" style="margin: 0; cursor: pointer;">
                            <i class="fas fa-eye" style="color: #10b981;"></i> Publish immediately (visible to students)
                        </span>
                    </label>
                </div>
                
                <!-- Buttons -->
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Material
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentContentType = 'pdf';
        
        // Content type selection
        function selectContentType(type) {
            currentContentType = type;
            document.getElementById('content_type').value = type;
            
            // Update active state
            document.querySelectorAll('.type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-type="${type}"]`).classList.add('active');
            
            // Show/hide relevant fields
            document.getElementById('fileUploadGroup').style.display = ['pdf', 'document', 'other'].includes(type) ? 'block' : 'none';
            document.getElementById('urlGroup').style.display = ['video', 'link'].includes(type) ? 'block' : 'none';
            document.getElementById('codeGroup').style.display = type === 'code' ? 'block' : 'none';
        }
        
        // File upload handling
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const fileInfo = document.getElementById('fileInfo');
                fileInfo.style.display = 'block';
                fileInfo.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-secondary); border-radius: 10px;">
                        <i class="fas fa-file" style="font-size: 24px; color: #667eea;"></i>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--text-primary);">${file.name}</div>
                            <div style="font-size: 12px; color: var(--text-secondary);">${formatBytes(file.size)}</div>
                        </div>
                        <button type="button" onclick="clearFile()" style="background: none; border: none; color: #ef4444; cursor: pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            }
        });
        
        function clearFile() {
            document.getElementById('fileInput').value = '';
            document.getElementById('fileInfo').style.display = 'none';
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        // Show modal
        function showUploadModal() {
            document.getElementById('modalTitle').textContent = 'Upload Course Material';
            document.getElementById('uploadForm').reset();
            document.getElementById('content_id').value = '';
            selectContentType('pdf');
            document.getElementById('uploadModal').style.display = 'flex';
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('uploadModal').style.display = 'none';
        }
        
        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const contentId = document.getElementById('content_id').value;
            
            try {
                const response = await fetch('api/manage_course_material.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to upload material',
                        confirmButtonColor: '#ef4444'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while uploading',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
        
        // Edit material
        async function editMaterial(id) {
            // Load material data and populate modal
            // Implementation similar to assignment edit
            Swal.fire({
                icon: 'info',
                title: 'Coming Soon',
                text: 'Edit functionality will be available soon',
                confirmButtonColor: '#667eea'
            });
        }
        
        // Delete material
        async function deleteMaterial(id, title) {
            const result = await Swal.fire({
                icon: 'warning',
                title: 'Delete Material?',
                text: `Are you sure you want to delete "${title}"? This action cannot be undone.`,
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel'
            });
            
            if (result.isConfirmed) {
                try {
                    const response = await fetch('api/delete_course_material.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ content_id: id })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: data.message,
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message,
                            confirmButtonColor: '#ef4444'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to delete material',
                        confirmButtonColor: '#ef4444'
                    });
                }
            }
        }
        
        // View code snippet in modal
        async function viewCode(id) {
            try {
                const response = await fetch(`api/view_material.php?content_id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        title: data.material.title,
                        html: `
                            <div class="text-left">
                                <p class="text-gray-600 mb-4">${data.material.description || ''}</p>
                                <pre><code class="language-javascript">${escapeHtml(data.material.content_text)}</code></pre>
                            </div>
                        `,
                        width: '800px',
                        showCloseButton: true,
                        showConfirmButton: false,
                        didOpen: () => {
                            // Apply syntax highlighting
                            if (typeof hljs !== 'undefined') {
                                hljs.highlightAll();
                            }
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load code snippet'
                });
            }
        }
        
        // Increment view count for external links
        function incrementViewCount(id) {
            fetch(`api/view_material.php?content_id=${id}`)
                .catch(error => console.error('Error updating view count:', error));
        }
        
        // Edit material
        function editMaterial(id) {
            // TODO: Implement edit functionality
            Swal.fire({
                icon: 'info',
                title: 'Coming Soon',
                text: 'Edit functionality will be implemented soon'
            });
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
