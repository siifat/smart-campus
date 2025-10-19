<?php
/**
 * Assignment Detail & Submission Page
 * View assignment details and submit work
 */
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Location: ../login.html?error=unauthorized');
    exit;
}

// Check session timeout
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
$assignment_id = $_GET['id'] ?? null;

if (!$assignment_id) {
    header('Location: assignments.php');
    exit;
}

// Fetch student data
$stmt = $conn->prepare("SELECT *, COALESCE(total_points, 0) as total_points FROM students WHERE student_id = ?");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$total_points = $student['total_points'] ?? 0;
$stmt->close();

// Get assignment details with enrollment check
$stmt = $conn->prepare("
    SELECT 
        a.*,
        c.course_code,
        c.course_name,
        e.section,
        e.enrollment_id,
        t.full_name as teacher_name,
        t.initial as teacher_initial,
        t.email as teacher_email,
        sub.submission_id,
        sub.file_path,
        sub.submission_text,
        sub.submitted_at,
        sub.is_late,
        sub.late_days,
        sub.status as submission_status,
        sub.marks_obtained,
        sub.feedback,
        sub.graded_at
    FROM assignments a
    JOIN courses c ON a.course_id = c.course_id
    JOIN enrollments e ON a.course_id = e.course_id 
        AND a.trimester_id = e.trimester_id
        AND (a.section IS NULL OR a.section = e.section)
    LEFT JOIN teachers t ON e.teacher_id = t.teacher_id
    LEFT JOIN assignment_submissions sub ON a.assignment_id = sub.assignment_id 
        AND sub.student_id = ?
    WHERE a.assignment_id = ?
        AND e.student_id = ?
        AND e.status = 'enrolled'
        AND a.is_published = 1
    LIMIT 1
");
$stmt->bind_param('sis', $student_id, $assignment_id, $student_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assignment) {
    header('Location: assignments.php');
    exit;
}

// Calculate time remaining
$due_date = new DateTime($assignment['due_date']);
$now = new DateTime();
$is_past_due = $due_date < $now;
$time_diff = $due_date->diff($now);

// Page configuration
$page_title = 'Assignment Details';
$page_icon = 'fas fa-clipboard-list';
$show_page_title = true;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($assignment['title']) ?> - UIU Smart Campus</title>
    
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
        
        /* Badge */
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        /* Button */
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
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
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
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
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #f68b1f;
            box-shadow: 0 0 0 3px rgba(246, 139, 31, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: #f68b1f;
            background: rgba(246, 139, 31, 0.05);
        }
        
        .file-upload-area.drag-over {
            border-color: #f68b1f;
            background: rgba(246, 139, 31, 0.1);
        }
        
        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: start;
            gap: 12px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
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
        <!-- Back Button -->
        <div class="mb-6 fade-in-up">
            <a href="assignments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>Back to Assignments
            </a>
        </div>
        
        <!-- Assignment Header -->
        <div class="glass-card mb-6 fade-in-up" style="animation-delay: 0.1s;">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <h1 style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px;">
                        <?= htmlspecialchars($assignment['title']) ?>
                        <?php if ($assignment['is_bonus']): ?>
                        <span class="badge" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white;">
                            <i class="fas fa-star"></i> Bonus Assignment
                        </span>
                        <?php endif; ?>
                    </h1>
                    <div class="flex items-center gap-6 text-sm" style="color: var(--text-secondary);">
                        <span>
                            <i class="fas fa-book"></i>
                            <?= htmlspecialchars($assignment['course_code']) ?> - <?= htmlspecialchars($assignment['course_name']) ?>
                        </span>
                        <span>
                            <i class="fas fa-user-tie"></i>
                            <?= htmlspecialchars($assignment['teacher_name']) ?>
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
                <?php if ($assignment['submission_id']): ?>
                <span class="badge badge-success" style="font-size: 14px; padding: 10px 16px;">
                    <i class="fas fa-check-circle"></i>
                    <?= $assignment['submission_status'] === 'graded' ? 'Graded' : 'Submitted' ?>
                </span>
                <?php elseif ($is_past_due): ?>
                <span class="badge badge-danger" style="font-size: 14px; padding: 10px 16px;">
                    <i class="fas fa-exclamation-triangle"></i>Overdue
                </span>
                <?php else: ?>
                <span class="badge badge-warning" style="font-size: 14px; padding: 10px 16px;">
                    <i class="fas fa-clock"></i>Pending
                </span>
                <?php endif; ?>
            </div>
            
            <!-- Key Info Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6 pt-6" style="border-top: 1px solid var(--border-color);">
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Total Marks</div>
                    <div style="font-size: 24px; font-weight: 700; color: #f68b1f;">
                        <i class="fas fa-award"></i> <?= $assignment['total_marks'] ?>
                    </div>
                </div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Due Date</div>
                    <div style="font-size: 16px; font-weight: 600; color: var(--text-primary);">
                        <i class="fas fa-calendar-alt"></i> <?= $due_date->format('M d, Y') ?>
                    </div>
                    <div style="font-size: 12px; color: var(--text-secondary);">
                        <?= $due_date->format('h:i A') ?>
                    </div>
                </div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Time Remaining</div>
                    <div style="font-size: 16px; font-weight: 600; <?= $is_past_due ? 'color: #ef4444;' : 'color: var(--text-primary);' ?>">
                        <?php if ($is_past_due): ?>
                            <i class="fas fa-times-circle"></i> Past Due
                        <?php else: ?>
                            <i class="fas fa-clock"></i>
                            <?= $time_diff->days ?> days <?= $time_diff->h ?> hours
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Late Submission</div>
                    <div style="font-size: 16px; font-weight: 600; color: var(--text-primary);">
                        <?php if ($assignment['late_submission_allowed']): ?>
                            <i class="fas fa-check-circle" style="color: #10b981;"></i> Allowed
                            <div style="font-size: 12px; color: var(--text-secondary);">
                                -<?= $assignment['late_penalty_per_day'] ?>% per day
                            </div>
                        <?php else: ?>
                            <i class="fas fa-times-circle" style="color: #ef4444;"></i> Not Allowed
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Assignment Details -->
            <div class="lg:col-span-2">
                <!-- Description -->
                <div class="glass-card mb-6 fade-in-up" style="animation-delay: 0.2s;">
                    <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
                        <i class="fas fa-align-left"></i> Description
                    </h2>
                    <div style="color: var(--text-secondary); line-height: 1.8;">
                        <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                    </div>
                </div>
                
                <?php if ($assignment['file_path']): ?>
                <!-- Attached Files -->
                <div class="glass-card mb-6 fade-in-up" style="animation-delay: 0.3s;">
                    <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
                        <i class="fas fa-paperclip"></i> Attached Files
                    </h2>
                    <a href="<?= htmlspecialchars($assignment['file_path']) ?>" target="_blank" 
                       class="flex items-center gap-3 p-4 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition" 
                       style="border: 1px solid var(--border-color);">
                        <i class="fas fa-file-pdf" style="font-size: 32px; color: #ef4444;"></i>
                        <div>
                            <div style="font-weight: 600; color: var(--text-primary);">
                                <?= basename($assignment['file_path']) ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-secondary);">
                                Click to download
                            </div>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($assignment['submission_id'] && $assignment['submission_status'] === 'graded'): ?>
                <!-- Grading -->
                <div class="glass-card mb-6 fade-in-up" style="animation-delay: 0.4s;">
                    <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
                        <i class="fas fa-star"></i> Your Grade
                    </h2>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="text-center p-6 rounded-lg" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <div style="font-size: 14px; color: rgba(255,255,255,0.9); margin-bottom: 8px;">Score</div>
                            <div style="font-size: 36px; font-weight: 800; color: white;">
                                <?= $assignment['marks_obtained'] ?>/<?= $assignment['total_marks'] ?>
                            </div>
                            <div style="font-size: 14px; color: rgba(255,255,255,0.9); margin-top: 4px;">
                                <?= round(($assignment['marks_obtained'] / $assignment['total_marks']) * 100, 1) ?>%
                            </div>
                        </div>
                        <div class="text-center p-6 rounded-lg" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 8px;">Graded On</div>
                            <div style="font-size: 16px; font-weight: 600; color: var(--text-primary);">
                                <?= date('M d, Y', strtotime($assignment['graded_at'])) ?>
                            </div>
                            <div style="font-size: 14px; color: var(--text-secondary); margin-top: 4px;">
                                <?= date('h:i A', strtotime($assignment['graded_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($assignment['feedback']): ?>
                    <div style="background: var(--bg-secondary); border-radius: 10px; padding: 16px; border-left: 4px solid #f68b1f;">
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">
                            <i class="fas fa-comment-alt"></i> Teacher Feedback
                        </div>
                        <div style="color: var(--text-secondary); line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($assignment['feedback'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Column: Submission Area -->
            <div>
                <?php if (!$assignment['submission_id']): ?>
                    <?php if (!$is_past_due || $assignment['late_submission_allowed']): ?>
                    <!-- Submit Assignment -->
                    <div class="glass-card fade-in-up" style="animation-delay: 0.2s;">
                        <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
                            <i class="fas fa-upload"></i> Submit Your Work
                        </h2>
                        
                        <?php if ($is_past_due): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i>
                            <div>
                                <strong>Late Submission</strong>
                                <p style="margin-top: 4px; font-size: 13px;">
                                    This assignment is past due. A penalty of <?= $assignment['late_penalty_per_day'] ?>% per day will be applied.
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <form id="submissionForm" enctype="multipart/form-data">
                            <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                            <input type="hidden" name="enrollment_id" value="<?= $assignment['enrollment_id'] ?>">
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-file"></i> Upload File (Optional)
                                </label>
                                <div class="file-upload-area" id="fileUploadArea">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #f68b1f; margin-bottom: 16px;"></i>
                                    <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">
                                        Click to upload or drag and drop
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-secondary);">
                                        PDF, DOC, DOCX, ZIP (Max 10MB)
                                    </div>
                                    <input type="file" name="submission_file" id="submissionFile" 
                                           accept=".pdf,.doc,.docx,.zip" style="display: none;">
                                </div>
                                <div id="fileInfo" style="display: none; margin-top: 12px; padding: 12px; background: var(--bg-secondary); border-radius: 8px;">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <i class="fas fa-file" style="font-size: 24px; color: #f68b1f;"></i>
                                            <div>
                                                <div id="fileName" style="font-weight: 600; color: var(--text-primary);"></div>
                                                <div id="fileSize" style="font-size: 12px; color: var(--text-secondary);"></div>
                                            </div>
                                        </div>
                                        <button type="button" onclick="clearFile()" class="icon-btn" style="width: 32px; height: 32px;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-align-left"></i> Text Submission (Optional)
                                </label>
                                <textarea name="submission_text" class="form-textarea" 
                                          placeholder="Enter your answer or additional notes here..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-full">
                                <i class="fas fa-paper-plane"></i>Submit Assignment
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <!-- Past Due - No Submission Allowed -->
                    <div class="glass-card fade-in-up" style="animation-delay: 0.2s;">
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle" style="font-size: 20px;"></i>
                            <div>
                                <strong>Submission Closed</strong>
                                <p style="margin-top: 4px; font-size: 13px;">
                                    This assignment is past due and late submissions are not allowed.
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Submitted -->
                    <div class="glass-card fade-in-up" style="animation-delay: 0.2s;">
                        <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
                            <i class="fas fa-check-circle"></i> Your Submission
                        </h2>
                        
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle" style="font-size: 20px;"></i>
                            <div>
                                <strong>Submitted Successfully</strong>
                                <p style="margin-top: 4px; font-size: 13px;">
                                    <?= date('M d, Y h:i A', strtotime($assignment['submitted_at'])) ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($assignment['is_late']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i>
                            <div>
                                <strong>Late Submission</strong>
                                <p style="margin-top: 4px; font-size: 13px;">
                                    Submitted <?= $assignment['late_days'] ?> day<?= $assignment['late_days'] > 1 ? 's' : '' ?> late
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($assignment['file_path']): ?>
                        <div style="margin-top: 20px;">
                            <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 12px;">
                                <i class="fas fa-file"></i> Submitted File
                            </div>
                            <a href="<?= htmlspecialchars($assignment['file_path']) ?>" target="_blank" 
                               class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition" 
                               style="border: 1px solid var(--border-color);">
                                <i class="fas fa-file-pdf" style="font-size: 24px; color: #ef4444;"></i>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--text-primary); font-size: 14px;">
                                        <?= basename($assignment['file_path']) ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($assignment['submission_text']): ?>
                        <div style="margin-top: 20px;">
                            <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 12px;">
                                <i class="fas fa-align-left"></i> Text Submission
                            </div>
                            <div style="background: var(--bg-secondary); border-radius: 10px; padding: 16px; border: 1px solid var(--border-color);">
                                <div style="color: var(--text-secondary); line-height: 1.6;">
                                    <?= nl2br(htmlspecialchars($assignment['submission_text'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script>
        // File upload handling
        const fileUploadArea = document.getElementById('fileUploadArea');
        const submissionFile = document.getElementById('submissionFile');
        const fileInfo = document.getElementById('fileInfo');
        
        fileUploadArea.addEventListener('click', () => submissionFile.click());
        
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('drag-over');
        });
        
        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('drag-over');
        });
        
        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('drag-over');
            
            if (e.dataTransfer.files.length) {
                submissionFile.files = e.dataTransfer.files;
                displayFileInfo(e.dataTransfer.files[0]);
            }
        });
        
        submissionFile.addEventListener('change', (e) => {
            if (e.target.files.length) {
                displayFileInfo(e.target.files[0]);
            }
        });
        
        function displayFileInfo(file) {
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            
            fileName.textContent = file.name;
            fileSize.textContent = formatBytes(file.size);
            
            fileInfo.style.display = 'block';
            fileUploadArea.style.display = 'none';
        }
        
        function clearFile() {
            submissionFile.value = '';
            fileInfo.style.display = 'none';
            fileUploadArea.style.display = 'block';
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        // Form submission
        document.getElementById('submissionForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            // Validate
            if (!formData.get('submission_file').size && !formData.get('submission_text').trim()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Required',
                    text: 'Please upload a file or enter text submission',
                    confirmButtonColor: '#f68b1f'
                });
                return;
            }
            
            try {
                const response = await fetch('api/submit_assignment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Submitted!',
                        text: result.message,
                        confirmButtonColor: '#f68b1f'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Submission Failed',
                        text: result.message,
                        confirmButtonColor: '#f68b1f'
                    });
                }
            } catch (error) {
                console.error('Submission error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to submit assignment. Please try again.',
                    confirmButtonColor: '#f68b1f'
                });
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
