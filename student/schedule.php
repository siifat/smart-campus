<?php
/**
 * Student Schedule Page - UIU Smart Campus
 * Displays class routine and exam schedule
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

// Fetch student data
$stmt = $conn->prepare("SELECT s.*, p.program_name, p.program_code FROM students s JOIN programs p ON s.program_id = p.program_id WHERE s.student_id = ?");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$total_points = $student['total_points'] ?? 0;
$program_name = $student['program_name'] ?? '';
$program_id = $student['program_id'] ?? 0;
$stmt->close();

// Fetch class routine
$stmt = $conn->prepare("
    SELECT 
        cr.day_of_week,
        cr.start_time,
        cr.end_time,
        cr.room_number,
        cr.building,
        c.course_code,
        c.course_name,
        c.course_type,
        e.section,
        t.full_name as teacher_name,
        t.initial as teacher_initial
    FROM class_routine cr
    JOIN enrollments e ON cr.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    LEFT JOIN teachers t ON e.teacher_id = t.teacher_id
    WHERE e.student_id = ? AND e.status = 'enrolled'
    ORDER BY 
        FIELD(cr.day_of_week, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
        cr.start_time
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$class_routine = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get enrolled courses for exam schedule generation
$stmt = $conn->prepare("
    SELECT 
        c.course_id,
        c.course_code,
        c.course_name,
        e.section
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ? AND e.status = 'enrolled'
    ORDER BY c.course_code
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$enrolled_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get student's department
$dept_stmt = $conn->prepare("
    SELECT p.department_id 
    FROM students s 
    JOIN programs p ON s.program_id = p.program_id 
    WHERE s.student_id = ?
");
$dept_stmt->bind_param('s', $student_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result()->fetch_assoc();
$student_department_id = $dept_result['department_id'] ?? null;
$dept_stmt->close();

// Get current trimester
$trimester_stmt = $conn->query("
    SELECT trimester_id 
    FROM trimesters 
    WHERE is_current = 1 
    LIMIT 1
");
$current_trimester_id = null;
if ($trimester_result = $trimester_stmt->fetch_assoc()) {
    $current_trimester_id = $trimester_result['trimester_id'];
}

// Fetch exam schedule from database (admin-uploaded only, filtered by department and trimester)
$stmt = $conn->prepare("
    SELECT 
        es.exam_schedule_id,
        es.exam_date,
        es.start_time,
        es.end_time,
        es.room_number,
        es.building,
        es.exam_type,
        c.course_code,
        c.course_name,
        e.section
    FROM exam_schedule es
    JOIN enrollments e ON es.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ? 
    AND e.status = 'enrolled'
    AND es.department_id = ?
    AND es.trimester_id = ?
    ORDER BY es.exam_date, es.start_time
");
$stmt->bind_param('sii', $student_id, $student_department_id, $current_trimester_id);
$stmt->execute();
$exam_schedule = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Page title for topbar
$page_title = 'My Schedule';
$page_icon = 'fas fa-calendar-alt';
$show_page_title = true;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - UIU Smart Campus</title>
    
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
        
        /* Sidebar - Same as dashboard */
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
        
        /* Topbar - Same as dashboard */
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
        
        /* Tab Buttons */
        .tab-buttons {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
        }
        
        .tab-btn {
            padding: 12px 24px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        
        .tab-btn:hover {
            color: var(--text-primary);
        }
        
        .tab-btn.active {
            color: #f68b1f;
            border-bottom-color: #f68b1f;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Class Routine Table */
        .routine-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
        }
        
        .routine-table th {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
            padding: 16px 12px;
            text-align: left;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .routine-table th:first-child {
            border-top-left-radius: 12px;
        }
        
        .routine-table th:last-child {
            border-top-right-radius: 12px;
        }
        
        .routine-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .routine-table tr:last-child td {
            border-bottom: none;
        }
        
        .routine-table tr:hover td {
            background: var(--bg-secondary);
        }
        
        .course-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            margin-right: 8px;
        }
        
        .theory-badge {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .lab-badge {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .time-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--bg-secondary);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .room-info {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 64px;
            opacity: 0.3;
            margin-bottom: 20px;
            display: block;
        }
        
        .empty-state h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        /* Upload Button */
        .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(246, 139, 31, 0.3);
        }
        
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(246, 139, 31, 0.4);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 32px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-secondary);
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .modal-close:hover {
            background: var(--border-color);
        }
        
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--bg-secondary);
            margin: 20px 0;
        }
        
        .file-upload-area:hover {
            border-color: #f68b1f;
            background: rgba(246, 139, 31, 0.05);
        }
        
        .file-upload-area.drag-over {
            border-color: #f68b1f;
            background: rgba(246, 139, 31, 0.1);
        }
        
        /* Exam Card */
        .exam-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            border-left: 4px solid #f68b1f;
            transition: all 0.3s ease;
        }
        
        .exam-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px var(--shadow-color);
        }
        
        .exam-date {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        /* Exam Type Buttons */
        .exam-type-btn {
            padding: 12px 24px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .exam-type-btn:hover {
            color: var(--text-primary);
        }
        
        .exam-type-btn.active {
            color: #f68b1f;
            border-bottom-color: #f68b1f;
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        
        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #3b82f6;
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
            
            .routine-table {
                font-size: 13px;
            }
            
            .routine-table th,
            .routine-table td {
                padding: 12px 8px;
            }
        }
        
        @media (max-width: 768px) {
            .tab-buttons {
                flex-direction: column;
                gap: 8px;
            }
            
            .tab-btn {
                width: 100%;
                text-align: left;
            }
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
        <div class="glass-card">
            <!-- Tab Navigation -->
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="switchTab('class-routine')">
                    <i class="fas fa-clock"></i> Class Routine
                </button>
                <button class="tab-btn" onclick="switchTab('exam-schedule')">
                    <i class="fas fa-file-alt"></i> Exam Schedule
                </button>
            </div>
            
            <!-- Class Routine Tab -->
            <div id="class-routine" class="tab-content active">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h2 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                            Weekly Class Routine
                        </h2>
                        <p style="color: var(--text-secondary); font-size: 14px;">
                            Your regular class schedule for the current trimester
                        </p>
                    </div>
                </div>
                
                <?php if (count($class_routine) > 0): ?>
                    <?php
                    // Group classes by day of week
                    $routine_by_day = [];
                    foreach ($class_routine as $class) {
                        $routine_by_day[$class['day_of_week']][] = $class;
                    }
                    
                    $days_order = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                    ?>
                    
                    <div style="overflow-x: auto;">
                        <table class="routine-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Course</th>
                                    <th>Section</th>
                                    <th>Instructor</th>
                                    <th>Room</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($days_order as $day): ?>
                                    <?php if (isset($routine_by_day[$day])): ?>
                                        <?php $day_classes = $routine_by_day[$day]; ?>
                                        <?php foreach ($day_classes as $index => $class): ?>
                                            <tr>
                                                <?php if ($index === 0): ?>
                                                    <td rowspan="<?php echo count($day_classes); ?>" style="font-weight: 700; background: var(--bg-secondary);">
                                                        <?php echo htmlspecialchars($day); ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td>
                                                    <span class="time-badge">
                                                        <i class="fas fa-clock"></i>
                                                        <?php echo date('g:i A', strtotime($class['start_time'])); ?> - <?php echo date('g:i A', strtotime($class['end_time'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="course-badge <?php echo $class['course_type'] === 'lab' ? 'lab-badge' : 'theory-badge'; ?>">
                                                        <?php echo htmlspecialchars($class['course_code']); ?>
                                                    </span>
                                                    <div style="margin-top: 4px; font-weight: 600; color: var(--text-primary);">
                                                        <?php echo htmlspecialchars($class['course_name']); ?>
                                                    </div>
                                                </td>
                                                <td style="font-weight: 600;">
                                                    Sec <?php echo htmlspecialchars($class['section']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($class['teacher_name'] ?? $class['teacher_initial'] ?? 'TBA'); ?>
                                                </td>
                                                <td>
                                                    <span class="room-info">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                        <?php echo htmlspecialchars($class['room_number'] ?? 'TBA'); ?>
                                                        <?php if (!empty($class['building'])): ?>
                                                            <span style="opacity: 0.7;">(<?php echo htmlspecialchars($class['building']); ?>)</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Class Routine Available</h3>
                        <p>Your class routine will appear here once your courses are synced with UCAM.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Exam Schedule Tab -->
            <div id="exam-schedule" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h2 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                            Examination Schedule
                        </h2>
                        <p style="color: var(--text-secondary); font-size: 14px;">
                            View your personalized exam schedule for the current trimester
                        </p>
                    </div>
                </div>
                
                <!-- Exam Type Selector -->
                <div style="display: flex; gap: 12px; margin-bottom: 24px; border-bottom: 2px solid var(--border-color); padding-bottom: 0;">
                    <button class="exam-type-btn active" data-type="Midterm" onclick="switchExamType('Midterm')">
                        <i class="fas fa-file-alt"></i> Midterm Exams
                    </button>
                    <button class="exam-type-btn" data-type="Final" onclick="switchExamType('Final')">
                        <i class="fas fa-graduation-cap"></i> Final Exams
                    </button>
                </div>
                
                <div id="exam-schedule-container">
                    <?php if (count($exam_schedule) > 0): ?>
                        <?php foreach ($exam_schedule as $exam): ?>
                            <div class="exam-card">
                                <div class="exam-date">
                                    <i class="fas fa-calendar-day"></i>
                                    <?php echo date('l, F j, Y', strtotime($exam['exam_date'])); ?>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-top: 12px;">
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Course</div>
                                        <div style="font-weight: 700; color: var(--text-primary); font-size: 15px;">
                                            <?php echo htmlspecialchars($exam['course_code']); ?> - <?php echo htmlspecialchars($exam['course_name']); ?>
                                        </div>
                                        <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                            Section <?php echo htmlspecialchars($exam['section']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Time</div>
                                        <div style="font-weight: 700; color: var(--text-primary); font-size: 15px;">
                                            <i class="fas fa-clock" style="color: #f68b1f; margin-right: 6px;"></i>
                                            <?php echo date('g:i A', strtotime($exam['start_time'])); ?> - <?php echo date('g:i A', strtotime($exam['end_time'])); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Room</div>
                                        <div style="font-weight: 700; color: var(--text-primary); font-size: 15px;">
                                            <i class="fas fa-map-marker-alt" style="color: #f68b1f; margin-right: 6px;"></i>
                                            <?php echo htmlspecialchars($exam['room_number'] ?? 'TBA'); ?>
                                            <?php if (!empty($exam['building'])): ?>
                                                <span style="font-weight: 400; opacity: 0.7;">(<?php echo htmlspecialchars($exam['building']); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Exam Type</div>
                                        <div style="display: inline-block; padding: 6px 12px; background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 8px; font-weight: 700; font-size: 13px; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($exam['exam_type']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Exam Schedule Available</h3>
                            <p>The exam schedule for the current trimester has not been uploaded yet.</p>
                            <p style="margin-top: 12px; font-size: 13px; opacity: 0.8;">
                                <i class="fas fa-info-circle"></i> Exam schedules are uploaded by the administration team.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Current exam type being viewed
        let currentExamType = 'Midterm';
        
        // Tab Switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Activate corresponding button
            event.target.classList.add('active');
            
            // Load exam schedule when switching to exam tab
            if (tabName === 'exam-schedule') {
                loadExamSchedule(currentExamType);
            }
        }
        
        // Switch Exam Type
        function switchExamType(examType) {
            currentExamType = examType;
            
            // Update active button
            document.querySelectorAll('.exam-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Load schedule for selected type
            loadExamSchedule(examType);
        }
        
        // Load Exam Schedule
        async function loadExamSchedule(examType) {
            const container = document.getElementById('exam-schedule-container');
            container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading exam schedule...</p></div>';
            
            try {
                const response = await fetch(`api/get_exam_routine.php?exam_type=${examType}`);
                const data = await response.json();
                
                if (data.success && data.exams.length > 0) {
                    let html = '';
                    
                    data.exams.forEach(exam => {
                        const examDate = new Date(exam.exam_date);
                        const formattedDate = examDate.toLocaleDateString('en-US', { 
                            weekday: 'long', 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric' 
                        });
                        
                        html += `
                            <div class="exam-card">
                                <div class="exam-date">
                                    <i class="fas fa-calendar-day"></i>
                                    ${formattedDate}
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-top: 12px;">
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Course</div>
                                        <div style="font-weight: 700; color: var(--text-primary); font-size: 15px;">
                                            ${exam.course_code}
                                        </div>
                                        <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                            ${exam.course_title || 'N/A'}
                                        </div>
                                        <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                            Section ${exam.section}
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Time</div>
                                        <div style="font-weight: 700; color: var(--text-primary); font-size: 15px;">
                                            <i class="fas fa-clock" style="color: #f68b1f; margin-right: 6px;"></i>
                                            ${exam.exam_time || 'TBA'}
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Room</div>
                                        <div style="font-weight: 700; color: var(--text-primary); font-size: 15px;">
                                            <i class="fas fa-map-marker-alt" style="color: #f68b1f; margin-right: 6px;"></i>
                                            ${exam.room || 'TBA'}
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Instructor</div>
                                        <div style="font-weight: 700; color: var(--text-primary); font-size: 15px;">
                                            <i class="fas fa-user" style="color: #f68b1f; margin-right: 6px;"></i>
                                            ${exam.teacher_initial || 'TBA'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No ${examType} Exam Schedule Available</h3>
                            <p>The exam routine for this trimester has not been uploaded by the administration yet.</p>
                            <p style="margin-top: 8px; font-size: 13px; opacity: 0.8;">Please check back later or contact your department.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading exam schedule:', error);
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                        <h3>Error Loading Schedule</h3>
                        <p>There was an error loading your exam schedule. Please try again.</p>
                    </div>
                `;
            }
        }
        
        // Load initial exam schedule
        document.addEventListener('DOMContentLoaded', () => {
            loadExamSchedule(currentExamType);
        });
    </script>
</body>
</html>
