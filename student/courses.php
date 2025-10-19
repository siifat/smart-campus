<?php
/**
 * Student Courses - UIU Smart Campus
 * View all enrolled courses with materials count
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

// Fetch enrolled courses with material counts
$stmt = $conn->prepare("
    SELECT 
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        c.course_type,
        c.description,
        e.section,
        e.enrollment_id,
        t.full_name as teacher_name,
        t.initial as teacher_initial,
        d.department_name,
        d.department_code,
        COUNT(DISTINCT cm.content_id) as material_count,
        SUM(CASE WHEN cm.content_type = 'pdf' THEN 1 ELSE 0 END) as pdf_count,
        SUM(CASE WHEN cm.content_type = 'video' THEN 1 ELSE 0 END) as video_count,
        SUM(CASE WHEN cm.content_type = 'link' THEN 1 ELSE 0 END) as link_count
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    LEFT JOIN teachers t ON e.teacher_id = t.teacher_id
    LEFT JOIN departments d ON c.department_id = d.department_id
    LEFT JOIN course_materials cm ON c.course_id = cm.course_id 
        AND cm.trimester_id = e.trimester_id
        AND (cm.section IS NULL OR cm.section = e.section)
        AND cm.is_published = 1
    WHERE e.student_id = ?
        AND e.trimester_id = ?
        AND e.status = 'enrolled'
    GROUP BY c.course_id, e.section, e.enrollment_id
    ORDER BY c.course_code
");
$stmt->bind_param('si', $student_id, $current_trimester_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate statistics
$total_courses = count($courses);
$total_credits = array_sum(array_column($courses, 'credit_hours'));
$total_materials = array_sum(array_column($courses, 'material_count'));

// Get unique departments for filter
$departments = [];
foreach ($courses as $course) {
    if (!isset($departments[$course['department_code']])) {
        $departments[$course['department_code']] = $course['department_name'];
    }
}

$page_title = 'My Courses';
$page_icon = 'fas fa-book';
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
        
        .glass-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 48px var(--shadow-color);
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
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-orange), var(--secondary-orange));
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(246, 139, 31, 0.3);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 12px 0 4px;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* Course Card */
        .course-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 24px;
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
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }
        
        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px var(--shadow-color);
            border-color: #f68b1f;
        }
        
        .course-card:hover::before {
            transform: scaleX(1);
        }
        
        .course-code {
            font-size: 13px;
            font-weight: 700;
            color: #f68b1f;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
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
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        
        .course-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .course-meta-item i {
            color: #f68b1f;
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
        
        .badge-theory {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .badge-lab {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .badge-materials {
            background: linear-gradient(135deg, rgba(246, 139, 31, 0.1), rgba(251, 191, 36, 0.1));
            color: #f68b1f;
            font-weight: 700;
        }
        
        /* Filter Section */
        .filter-section {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px var(--shadow-color);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 12px;
            align-items: center;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid var(--border-color);
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
        
        select {
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        select:focus {
            outline: none;
            border-color: #f68b1f;
            box-shadow: 0 0 0 3px rgba(246, 139, 31, 0.1);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: calc(var(--topbar-height) + 20px) 20px 20px;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
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
    </style>
</head>
<body>
    <?php require_once('includes/sidebar.php'); ?>
    <?php require_once('includes/topbar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 fade-in-up">
            <div class="glass-card stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value"><?php echo $total_courses; ?></div>
                <div class="stat-label">Enrolled Courses</div>
            </div>
            
            <div class="glass-card stat-card">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-value"><?php echo $total_credits; ?></div>
                <div class="stat-label">Total Credits</div>
            </div>
            
            <div class="glass-card stat-card">
                <div class="stat-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="stat-value"><?php echo $total_materials; ?></div>
                <div class="stat-label">Course Materials</div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section fade-in-up" style="animation-delay: 0.1s;">
            <div class="filter-grid">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search courses by code or name..." onkeyup="filterCourses()">
                </div>
                
                <select id="departmentFilter" onchange="filterCourses()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $code => $name): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($name); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="typeFilter" onchange="filterCourses()">
                    <option value="">All Types</option>
                    <option value="theory">Theory</option>
                    <option value="lab">Lab</option>
                </select>
            </div>
        </div>
        
        <!-- Courses Grid -->
        <div id="coursesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 fade-in-up" style="animation-delay: 0.2s;">
            <?php if (count($courses) > 0): ?>
                <?php foreach ($courses as $course): ?>
                    <div class="course-card" 
                         data-course-code="<?php echo strtolower($course['course_code']); ?>"
                         data-course-name="<?php echo strtolower($course['course_name']); ?>"
                         data-department="<?php echo htmlspecialchars($course['department_code']); ?>"
                         data-type="<?php echo $course['course_type']; ?>"
                         onclick="window.location.href='course_materials.php?course_id=<?php echo $course['course_id']; ?>&section=<?php echo urlencode($course['section']); ?>'">
                        
                        <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                        <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                        
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;">
                            <span class="badge badge-<?php echo $course['course_type']; ?>">
                                <i class="fas fa-<?php echo $course['course_type'] === 'lab' ? 'flask' : 'book-open'; ?>"></i>
                                <?php echo ucfirst($course['course_type']); ?>
                            </span>
                            <?php if ($course['material_count'] > 0): ?>
                                <span class="badge badge-materials">
                                    <i class="fas fa-folder"></i>
                                    <?php echo $course['material_count']; ?> Materials
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 4px;">
                            <i class="fas fa-chalkboard-teacher" style="color: var(--primary-orange);"></i>
                            <?php echo htmlspecialchars($course['teacher_name']); ?>
                            <?php if ($course['teacher_initial']): ?>
                                (<?php echo htmlspecialchars($course['teacher_initial']); ?>)
                            <?php endif; ?>
                        </div>
                        
                        <div class="course-meta">
                            <div class="course-meta-item">
                                <i class="fas fa-users"></i>
                                <span>Section <?php echo htmlspecialchars($course['section']); ?></span>
                            </div>
                            <div class="course-meta-item">
                                <i class="fas fa-award"></i>
                                <span><?php echo $course['credit_hours']; ?> Credits</span>
                            </div>
                            <?php if ($course['pdf_count'] > 0): ?>
                                <div class="course-meta-item">
                                    <i class="fas fa-file-pdf"></i>
                                    <span><?php echo $course['pdf_count']; ?> PDFs</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full">
                    <div class="glass-card empty-state">
                        <i class="fas fa-book-open"></i>
                        <h3>No Courses Found</h3>
                        <p>You are not enrolled in any courses for this trimester.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- No Results Message -->
        <div id="noResults" class="glass-card empty-state" style="display: none;">
            <i class="fas fa-search"></i>
            <h3>No Courses Found</h3>
            <p>Try adjusting your search or filter criteria.</p>
        </div>
    </main>
    
    <script>
        // Filter courses
        function filterCourses() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const departmentValue = document.getElementById('departmentFilter').value;
            const typeValue = document.getElementById('typeFilter').value;
            
            const courseCards = document.querySelectorAll('.course-card');
            let visibleCount = 0;
            
            courseCards.forEach(card => {
                const courseCode = card.dataset.courseCode;
                const courseName = card.dataset.courseName;
                const department = card.dataset.department;
                const type = card.dataset.type;
                
                const matchesSearch = courseCode.includes(searchValue) || courseName.includes(searchValue);
                const matchesDepartment = !departmentValue || department === departmentValue;
                const matchesType = !typeValue || type === typeValue;
                
                if (matchesSearch && matchesDepartment && matchesType) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            const noResults = document.getElementById('noResults');
            const coursesGrid = document.getElementById('coursesGrid');
            
            if (visibleCount === 0 && courseCards.length > 0) {
                noResults.style.display = 'block';
                coursesGrid.style.display = 'none';
            } else {
                noResults.style.display = 'none';
                coursesGrid.style.display = 'grid';
            }
        }
    </script>
</body>
</html>
