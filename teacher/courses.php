<?php
/**
 * Teacher Courses Page
 * View all assigned courses
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
$teacher_email = $_SESSION['teacher_email'] ?? '';

// Get current trimester
$stmt = $pdo->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1");
$current_trimester = $stmt->fetch(PDO::FETCH_ASSOC);
$current_trimester_id = $current_trimester['trimester_id'] ?? 1;

// Get filter parameters
$filter_department = $_GET['department'] ?? '';
$filter_type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for courses
$query = "
    SELECT 
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        c.course_type,
        c.description,
        e.section,
        d.department_name,
        COUNT(DISTINCT e.student_id) as student_count,
        COUNT(DISTINCT cm.content_id) as content_count
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    JOIN departments d ON c.department_id = d.department_id
    LEFT JOIN course_materials cm ON c.course_id = cm.course_id 
        AND e.section = cm.section 
        AND cm.teacher_id = ?
    WHERE e.teacher_id = ? 
      AND e.trimester_id = ?
      AND e.status = 'enrolled'
";

$params = [$teacher_id, $teacher_id, $current_trimester_id];

if ($filter_department) {
    $query .= " AND d.department_id = ?";
    $params[] = $filter_department;
}

if ($filter_type) {
    $query .= " AND c.course_type = ?";
    $params[] = $filter_type;
}

if ($search) {
    $query .= " AND (c.course_code LIKE ? OR c.course_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " GROUP BY c.course_id, e.section
           ORDER BY c.course_code, e.section";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$dept_stmt = $pdo->query("SELECT DISTINCT d.department_id, d.department_name 
                          FROM departments d 
                          JOIN courses c ON d.department_id = c.department_id
                          ORDER BY d.department_name");
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT CONCAT(c.course_id, '-', e.section)) as total_courses,
        COUNT(DISTINCT e.student_id) as total_students,
        SUM(c.credit_hours) as total_credits
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.teacher_id = ? AND e.trimester_id = ? AND e.status = 'enrolled'
";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([$teacher_id, $current_trimester_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Set page title and icon for topbar
$page_title = 'My Courses';
$page_icon = 'fas fa-book-open';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Teacher Portal</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
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
            padding: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px var(--shadow-color);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px var(--shadow-color);
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
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* Course Card */
        .course-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px var(--shadow-color);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.2);
            border-color: #667eea;
        }
        
        .course-card:hover::before {
            transform: scaleX(1);
        }
        
        .course-code {
            font-size: 13px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .course-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            line-height: 1.4;
        }
        
        .course-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-theory { background: #dbeafe; color: #1e40af; }
        .badge-lab { background: #d1fae5; color: #065f46; }
        .badge-info { background: #e9d5ff; color: #6b21a8; }
        
        /* Search Box */
        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 45px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        /* Form Elements */
        .form-select {
            padding: 10px 16px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Button */
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
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
        <!-- Header -->
        <div class="fade-in-up" style="margin-bottom: 32px;">
            <h1 style="font-size: 32px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">
                My Courses
            </h1>
            <p style="font-size: 16px; color: var(--text-secondary);">
                Manage your courses and upload materials for students
            </p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 fade-in-up" style="animation-delay: 0.1s;">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value"><?= $stats['total_courses'] ?? 0 ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-value"><?= $stats['total_students'] ?? 0 ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-value"><?= $stats['total_credits'] ?? 0 ?></div>
                <div class="stat-label">Total Credits</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="fade-in-up" style="animation-delay: 0.2s; background: var(--card-bg); border-radius: 16px; padding: 24px; margin-bottom: 24px; border: 1px solid var(--border-color); box-shadow: 0 2px 8px var(--shadow-color);">
            <form method="GET" action="" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search courses..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['department_id'] ?>" <?= $filter_department == $dept['department_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="theory" <?= $filter_type === 'theory' ? 'selected' : '' ?>>Theory</option>
                    <option value="lab" <?= $filter_type === 'lab' ? 'selected' : '' ?>>Lab</option>
                </select>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                
                <?php if ($search || $filter_department || $filter_type): ?>
                <a href="courses.php" class="btn" style="background: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Courses Grid -->
        <div class="fade-in-up" style="animation-delay: 0.3s;">
            <?php if (count($courses) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($courses as $course): ?>
                    <div class="course-card" onclick="window.location.href='course_content.php?course_id=<?= $course['course_id'] ?>&section=<?= urlencode($course['section']) ?>'">
                        <div class="course-code">
                            <?= htmlspecialchars($course['course_code']) ?> - Section <?= htmlspecialchars($course['section']) ?>
                        </div>
                        <div class="course-name">
                            <?= htmlspecialchars($course['course_name']) ?>
                        </div>
                        
                        <div style="margin: 16px 0;">
                            <span class="badge <?= $course['course_type'] === 'lab' ? 'badge-lab' : 'badge-theory' ?>">
                                <i class="fas <?= $course['course_type'] === 'lab' ? 'fa-flask' : 'fa-book-open' ?>"></i>
                                <?= ucfirst($course['course_type']) ?>
                            </span>
                            <span class="badge badge-info" style="margin-left: 8px;">
                                <i class="fas fa-star"></i>
                                <?= $course['credit_hours'] ?> Credits
                            </span>
                        </div>
                        
                        <?php if ($course['description']): ?>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px; line-height: 1.6;">
                            <?= htmlspecialchars(substr($course['description'], 0, 100)) ?><?= strlen($course['description']) > 100 ? '...' : '' ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="course-meta">
                            <span>
                                <i class="fas fa-users"></i> <?= $course['student_count'] ?> Students
                            </span>
                            <span>
                                <i class="fas fa-folder"></i> <?= $course['content_count'] ?> Materials
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 80px 20px; background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border-color);">
                    <i class="fas fa-book-open" style="font-size: 64px; color: var(--border-color); margin-bottom: 24px;"></i>
                    <h3 style="font-size: 20px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">No Courses Found</h3>
                    <p style="color: var(--text-secondary);">
                        <?php if ($search || $filter_department || $filter_type): ?>
                            No courses match your search criteria. Try adjusting your filters.
                        <?php else: ?>
                            You don't have any assigned courses yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Initialize dark mode on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        });
    </script>
</body>
</html>
