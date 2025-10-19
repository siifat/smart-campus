<?php
/**
 * Student Assignments Page
 * View all assignments and submit work
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

// Get current trimester
$current_trimester = $conn->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_trimester_id = $current_trimester['trimester_id'] ?? 1;

// Get filter parameters
$filter_course = $_GET['course'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';

// Build query to get assignments for student's enrolled courses
$query = "
    SELECT 
        a.*,
        c.course_code,
        c.course_name,
        e.section,
        t.full_name as teacher_name,
        t.initial as teacher_initial,
        sub.submission_id,
        sub.submitted_at,
        sub.is_late,
        sub.status as submission_status,
        sub.marks_obtained,
        sub.feedback,
        CASE 
            WHEN sub.submission_id IS NOT NULL THEN 'submitted'
            WHEN NOW() > a.due_date THEN 'overdue'
            WHEN DATEDIFF(a.due_date, NOW()) <= 3 THEN 'due_soon'
            ELSE 'pending'
        END as assignment_status
    FROM assignments a
    JOIN courses c ON a.course_id = c.course_id
    JOIN enrollments e ON a.course_id = e.course_id 
        AND a.trimester_id = e.trimester_id
        AND (a.section IS NULL OR a.section = e.section)
    LEFT JOIN teachers t ON e.teacher_id = t.teacher_id
    LEFT JOIN assignment_submissions sub ON a.assignment_id = sub.assignment_id 
        AND sub.student_id = ?
    WHERE e.student_id = ?
        AND e.status = 'enrolled'
        AND a.is_published = 1
";

$params = [$student_id, $student_id];
$types = 'ss';

if ($filter_course) {
    $query .= " AND a.course_id = ?";
    $params[] = $filter_course;
    $types .= 'i';
}

if ($filter_type) {
    $query .= " AND a.assignment_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if ($filter_status === 'submitted') {
    $query .= " AND sub.submission_id IS NOT NULL";
} elseif ($filter_status === 'pending') {
    $query .= " AND sub.submission_id IS NULL AND NOW() <= a.due_date";
} elseif ($filter_status === 'overdue') {
    $query .= " AND sub.submission_id IS NULL AND NOW() > a.due_date";
}

$query .= " ORDER BY 
    CASE 
        WHEN sub.submission_id IS NULL AND NOW() <= a.due_date THEN 1
        WHEN sub.submission_id IS NULL AND NOW() > a.due_date THEN 2
        ELSE 3
    END,
    a.due_date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get student's courses for filter
$courses_stmt = $conn->prepare("
    SELECT DISTINCT c.course_id, c.course_code, c.course_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ? AND e.trimester_id = ? AND e.status = 'enrolled'
    ORDER BY c.course_code
");
$courses_stmt->bind_param('si', $student_id, $current_trimester_id);
$courses_stmt->execute();
$student_courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

// Calculate statistics
$stats = [
    'total' => count($assignments),
    'pending' => 0,
    'submitted' => 0,
    'overdue' => 0,
    'graded' => 0
];

foreach ($assignments as $assignment) {
    if ($assignment['submission_id']) {
        $stats['submitted']++;
        if ($assignment['submission_status'] === 'graded') {
            $stats['graded']++;
        }
    } else {
        if ($assignment['assignment_status'] === 'overdue') {
            $stats['overdue']++;
        } else {
            $stats['pending']++;
        }
    }
}

// Page configuration
$page_title = 'Assignments';
$page_icon = 'fas fa-clipboard-list';
$show_page_title = true;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - UIU Smart Campus</title>
    
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
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
        }
        
        .sidebar-logo i {
            font-size: 28px;
            color: white;
        }
        
        .sidebar-logo span {
            font-size: 20px;
            font-weight: 700;
            color: white;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background: rgba(255, 255, 255, 0.25);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .nav-item i {
            font-size: 18px;
            width: 24px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: calc(var(--topbar-height) + 24px) 24px 24px;
        }
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--topbar-height);
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 99;
            box-shadow: 0 2px 10px var(--shadow-color);
        }
        
        .search-box {
            flex: 1;
            max-width: 500px;
            position: relative;
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
            gap: 12px;
        }
        
        .icon-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .icon-btn:hover {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
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
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-profile:hover {
            border-color: #f68b1f;
            transform: translateY(-2px);
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
            font-size: 18px;
            font-weight: 700;
        }
        
        /* Glass Card */
        .glass-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .glass-card:hover {
            transform: translateY(-4px);
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
            border-radius: 50%;
            opacity: 0.1;
            transform: translate(30%, -30%);
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
            color: white;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        /* Assignment Card */
        .assignment-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-color);
            border-left: 4px solid #f68b1f;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 16px;
        }
        
        .assignment-card:hover {
            transform: translateX(8px);
            box-shadow: 0 4px 20px var(--shadow-color);
            border-left-width: 6px;
        }
        
        .assignment-card.overdue {
            border-left-color: #ef4444;
        }
        
        .assignment-card.submitted {
            border-left-color: #10b981;
        }
        
        .assignment-card.due-soon {
            border-left-color: #f59e0b;
        }
        
        /* Badge */
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge-pending {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-submitted {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-overdue {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-due-soon {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-graded {
            background: #e9d5ff;
            color: #6b21a8;
        }
        
        /* Button */
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
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
            box-shadow: 0 4px 12px rgba(246, 139, 31, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(246, 139, 31, 0.4);
        }
        
        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }
        
        /* Form Elements */
        .form-select {
            padding: 10px 16px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #f68b1f;
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
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8 fade-in-up">
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f68b1f, #fbbf24);">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Assignments</div>
            </div>
            
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
            
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?= $stats['submitted'] ?></div>
                <div class="stat-label">Submitted</div>
            </div>
            
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?= $stats['overdue'] ?></div>
                <div class="stat-label">Overdue</div>
            </div>
            
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-value"><?= $stats['graded'] ?></div>
                <div class="stat-label">Graded</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="glass-card mb-6 fade-in-up" style="animation-delay: 0.1s;">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary);">Course</label>
                    <select name="course" class="form-select w-full">
                        <option value="">All Courses</option>
                        <?php foreach ($student_courses as $course): ?>
                        <option value="<?= $course['course_id'] ?>" <?= $filter_course == $course['course_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary);">Type</label>
                    <select name="type" class="form-select w-full">
                        <option value="">All Types</option>
                        <?php 
                        $types = ['homework', 'project', 'quiz', 'lab', 'bonus', 'midterm', 'final'];
                        foreach ($types as $type): ?>
                        <option value="<?= $type ?>" <?= $filter_type === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary);">Status</label>
                    <select name="status" class="form-select w-full">
                        <option value="">All Status</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="submitted" <?= $filter_status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="overdue" <?= $filter_status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    </select>
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-1">
                        <i class="fas fa-filter"></i>Apply
                    </button>
                    <a href="assignments.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Assignments List -->
        <div class="fade-in-up" style="animation-delay: 0.2s;">
            <h2 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 20px;">
                <i class="fas fa-list"></i> Your Assignments
                <span style="font-size: 16px; font-weight: 400; color: var(--text-secondary); margin-left: 8px;">
                    (<?= count($assignments) ?> total)
                </span>
            </h2>
            
            <?php if (empty($assignments)): ?>
            <div class="glass-card text-center" style="padding: 60px 20px;">
                <div style="width: 80px; height: 80px; background: var(--bg-secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-clipboard-check" style="font-size: 40px; color: var(--text-secondary); opacity: 0.5;"></i>
                </div>
                <h3 style="font-size: 20px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">No Assignments Found</h3>
                <p style="color: var(--text-secondary);">You don't have any assignments yet. Check back later!</p>
            </div>
            <?php else: ?>
                <?php foreach ($assignments as $assignment): 
                    $due_date = new DateTime($assignment['due_date']);
                    $now = new DateTime();
                    $is_past_due = $due_date < $now && !$assignment['submission_id'];
                    $days_until = $due_date->diff($now)->days;
                    $status_class = $assignment['assignment_status'];
                    
                    // Status badge
                    $badge_class = 'badge-pending';
                    $badge_text = 'Pending';
                    $badge_icon = 'fa-clock';
                    
                    if ($assignment['submission_id']) {
                        if ($assignment['submission_status'] === 'graded') {
                            $badge_class = 'badge-graded';
                            $badge_text = 'Graded';
                            $badge_icon = 'fa-star';
                        } else {
                            $badge_class = 'badge-submitted';
                            $badge_text = 'Submitted';
                            $badge_icon = 'fa-check-circle';
                        }
                    } elseif ($is_past_due) {
                        $badge_class = 'badge-overdue';
                        $badge_text = 'Overdue';
                        $badge_icon = 'fa-exclamation-triangle';
                    } elseif ($days_until <= 3) {
                        $badge_class = 'badge-due-soon';
                        $badge_text = 'Due Soon';
                        $badge_icon = 'fa-exclamation-circle';
                    }
                ?>
                <div class="assignment-card <?= $status_class ?>" onclick="window.location.href='assignment_detail.php?id=<?= $assignment['assignment_id'] ?>'">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
                                <?= htmlspecialchars($assignment['title']) ?>
                                <?php if ($assignment['is_bonus']): ?>
                                <span class="badge" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white;">
                                    <i class="fas fa-star"></i> Bonus
                                </span>
                                <?php endif; ?>
                            </h3>
                            <div class="flex items-center gap-4 text-sm" style="color: var(--text-secondary); margin-bottom: 8px;">
                                <span>
                                    <i class="fas fa-book"></i>
                                    <?= htmlspecialchars($assignment['course_code']) ?>
                                </span>
                                <span>
                                    <i class="fas fa-user-tie"></i>
                                    <?= htmlspecialchars($assignment['teacher_initial'] ?? 'Staff') ?>
                                </span>
                                <span>
                                    <i class="fas fa-users"></i>
                                    Section <?= htmlspecialchars($assignment['section']) ?>
                                </span>
                                <span>
                                    <i class="fas fa-tag"></i>
                                    <?= ucfirst($assignment['assignment_type']) ?>
                                </span>
                            </div>
                        </div>
                        <span class="badge <?= $badge_class ?>">
                            <i class="fas <?= $badge_icon ?>"></i>
                            <?= $badge_text ?>
                        </span>
                    </div>
                    
                    <p style="color: var(--text-secondary); margin-bottom: 12px; line-height: 1.6;">
                        <?= htmlspecialchars(substr($assignment['description'], 0, 150)) ?><?= strlen($assignment['description']) > 150 ? '...' : '' ?>
                    </p>
                    
                    <div class="flex items-center justify-between" style="padding-top: 12px; border-top: 1px solid var(--border-color);">
                        <div class="flex items-center gap-6 text-sm">
                            <span style="color: var(--text-primary); font-weight: 600;">
                                <i class="fas fa-award" style="color: #f68b1f;"></i>
                                <?= $assignment['total_marks'] ?> marks
                            </span>
                            <span style="<?= $is_past_due ? 'color: #ef4444; font-weight: 600;' : 'color: var(--text-secondary);' ?>">
                                <i class="fas fa-calendar-alt"></i>
                                Due: <?= $due_date->format('M d, Y h:i A') ?>
                                <?php if (!$is_past_due): ?>
                                    (<?= $days_until ?> day<?= $days_until != 1 ? 's' : '' ?>)
                                <?php endif; ?>
                            </span>
                            <?php if ($assignment['submission_id'] && $assignment['marks_obtained']): ?>
                            <span style="color: #10b981; font-weight: 600;">
                                <i class="fas fa-check-circle"></i>
                                Score: <?= $assignment['marks_obtained'] ?>/<?= $assignment['total_marks'] ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); window.location.href='assignment_detail.php?id=<?= $assignment['assignment_id'] ?>'">
                            <i class="fas fa-arrow-right"></i>
                            <?= $assignment['submission_id'] ? 'View Details' : 'Submit Now' ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        // Initialize theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        });
    </script>
</body>
</html>
