<?php
/**
 * Student Announcements View
 * View announcements from teachers
 */
session_start();

if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Location: ../login.html');
    exit;
}

require_once('../config/database.php');
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$page_title = 'Announcements';
$page_icon = 'fas fa-bullhorn';
$show_page_title = true;

// Get current trimester
$current_trimester = $conn->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_trimester_id = $current_trimester['trimester_id'] ?? 1;

// Fetch student points
$points_query = "SELECT COALESCE(total_points, 0) as total_points FROM students WHERE student_id = ?";
$points_stmt = $conn->prepare($points_query);
$points_stmt->bind_param('s', $student_id);
$points_stmt->execute();
$points_result = $points_stmt->get_result();
$total_points = $points_result->fetch_assoc()['total_points'] ?? 0;
$points_stmt->close();

// Fetch announcements relevant to this student
$announcements_query = "
    SELECT DISTINCT
        ta.*,
        t.full_name as teacher_name,
        t.initial as teacher_initial,
        c.course_code,
        c.course_name,
        ar.read_at,
        (ar.announcement_id IS NOT NULL) as is_read
    FROM teacher_announcements ta
    INNER JOIN teachers t ON ta.teacher_id = t.teacher_id
    LEFT JOIN courses c ON ta.course_id = c.course_id
    LEFT JOIN announcement_reads ar ON ta.announcement_id = ar.announcement_id AND ar.student_id = ?
    WHERE ta.trimester_id = ?
    AND (
        ta.course_id IS NULL  -- General announcements
        OR EXISTS (
            SELECT 1 FROM enrollments e
            WHERE e.student_id = ?
            AND e.course_id = ta.course_id
            AND e.trimester_id = ta.trimester_id
            AND (ta.section IS NULL OR e.section = ta.section)
            AND e.status = 'enrolled'
        )
    )
    ORDER BY ta.is_pinned DESC, ta.published_at DESC
";

$ann_stmt = $conn->prepare($announcements_query);
$ann_stmt->bind_param('sis', $student_id, $current_trimester_id, $student_id);
$ann_stmt->execute();
$announcements = $ann_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ann_stmt->close();
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
            z-index: 90;
            box-shadow: 0 2px 12px var(--shadow-color);
        }
        
        .search-box {
            flex: 1;
            max-width: 500px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 48px;
            border: 2px solid var(--border-color);
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
        
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px var(--shadow-color);
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-urgent { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .badge-important { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .badge-general { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .badge-reminder { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        
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
        
        .announcement-card {
            border-left: 4px solid #f68b1f;
            cursor: pointer;
        }
        
        .announcement-card.unread {
            background: linear-gradient(135deg, rgba(246, 139, 31, 0.05), transparent);
            border-left-width: 5px;
        }
        
        .announcement-card.pinned {
            border-left-color: #fbbf24;
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.08), transparent);
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
        <!-- Page Header -->
        <div style="margin-bottom: 32px;">
            <h1 style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
                <i class="fas fa-bullhorn" style="color: #f68b1f;"></i> Announcements
            </h1>
            <p style="color: var(--text-secondary);">
                Stay updated with announcements from your teachers
            </p>
        </div>
        
        <!-- Announcements List -->
        <div style="display: grid; gap: 20px;">
            <?php if (empty($announcements)): ?>
                <div class="glass-card" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-bullhorn" style="font-size: 72px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 20px;"></i>
                    <h3 style="font-size: 20px; font-weight: 600; color: var(--text-primary); margin: 0 0 10px 0;">No Announcements</h3>
                    <p style="color: var(--text-secondary); margin: 0;">You're all caught up! No new announcements at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                <div class="glass-card announcement-card <?php echo !$ann['is_read'] ? 'unread' : ''; ?> <?php echo $ann['is_pinned'] ? 'pinned' : ''; ?>"
                     onclick="markAsRead(<?php echo $ann['announcement_id']; ?>)">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                <?php if ($ann['is_pinned']): ?>
                                    <i class="fas fa-thumbtack" style="color: #fbbf24;"></i>
                                <?php endif; ?>
                                <?php if (!$ann['is_read']): ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; background: #f68b1f; border-radius: 50%;"></span>
                                <?php endif; ?>
                                <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 0;">
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                </h3>
                                <span class="badge badge-<?php echo $ann['announcement_type']; ?>">
                                    <?php echo ucfirst($ann['announcement_type']); ?>
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 16px; font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                                <span>
                                    <i class="fas fa-chalkboard-teacher"></i> 
                                    <?php echo htmlspecialchars($ann['teacher_initial'] ?: $ann['teacher_name']); ?>
                                </span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y \a\t h:i A', strtotime($ann['published_at'])); ?></span>
                                <?php if ($ann['course_id']): ?>
                                    <span>
                                        <i class="fas fa-book"></i> 
                                        <?php echo htmlspecialchars($ann['course_code']); ?>
                                        <?php echo $ann['section'] ? ' - ' . htmlspecialchars($ann['section']) : ''; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 12px;">
                        <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
                    </div>
                    <?php if ($ann['file_path']): ?>
                        <div style="padding: 12px; background: var(--bg-secondary); border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-paperclip" style="color: #f68b1f;"></i>
                            <a href="../<?php echo htmlspecialchars($ann['file_path']); ?>" 
                               target="_blank" 
                               style="color: #f68b1f; text-decoration: none; font-weight: 600;"
                               onclick="event.stopPropagation();">
                                View Attachment
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if ($ann['is_read']): ?>
                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-color); font-size: 12px; color: var(--text-secondary);">
                            <i class="fas fa-check-circle"></i> Read on <?php echo date('M d, Y \a\t h:i A', strtotime($ann['read_at'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        async function markAsRead(announcementId) {
            try {
                const response = await fetch('api/announcements.php?action=mark_read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `announcement_id=${announcementId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Optionally reload or update UI
                    location.reload();
                }
            } catch (error) {
                console.error('Error marking announcement as read:', error);
            }
        }
    </script>
</body>
</html>
