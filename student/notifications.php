<?php
session_start();
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Location: ../login.html');
    exit;
}

require_once('../config/database.php');
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$page_title = 'Notifications';
$page_icon = 'fas fa-bell';
$show_page_title = true;

// Fetch all notifications
$query = "SELECT * FROM student_notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 50";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $student_id);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// Get unread count
$count_query = "SELECT COUNT(*) as unread_count FROM student_notifications WHERE student_id = ? AND is_read = 0";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param('s', $student_id);
$count_stmt->execute();
$unread_count = $count_stmt->get_result()->fetch_assoc()['unread_count'];
$count_stmt->close();

$total_points = 0;
$points_query = "SELECT total_points FROM students WHERE student_id = ?";
$points_stmt = $conn->prepare($points_query);
$points_stmt->bind_param('s', $student_id);
$points_stmt->execute();
$points_result = $points_stmt->get_result();
if ($row = $points_result->fetch_assoc()) {
    $total_points = $row['total_points'];
}
$points_stmt->close();
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
        
        .notification-row:hover {
            background: var(--bg-secondary) !important;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #f68b1f, #fbbf24) !important;
            color: white !important;
            border-color: #f68b1f !important;
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
        <!-- Header Stats -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="glass-card" style="padding: 20px; text-align: center;">
                <i class="fas fa-bell" style="font-size: 32px; color: #f68b1f; margin-bottom: 10px;"></i>
                <h3 style="font-size: 28px; font-weight: 800; color: var(--text-primary); margin: 0;"><?php echo $notifications->num_rows; ?></h3>
                <p style="color: var(--text-secondary); margin: 5px 0 0 0;">Total Notifications</p>
            </div>
            <div class="glass-card" style="padding: 20px; text-align: center;">
                <i class="fas fa-envelope-open" style="font-size: 32px; color: #10b981; margin-bottom: 10px;"></i>
                <h3 style="font-size: 28px; font-weight: 800; color: var(--text-primary); margin: 0;"><?php echo $notifications->num_rows - $unread_count; ?></h3>
                <p style="color: var(--text-secondary); margin: 5px 0 0 0;">Read</p>
            </div>
            <div class="glass-card" style="padding: 20px; text-align: center;">
                <i class="fas fa-envelope" style="font-size: 32px; color: #ef4444; margin-bottom: 10px;"></i>
                <h3 style="font-size: 28px; font-weight: 800; color: var(--text-primary); margin: 0;"><?php echo $unread_count; ?></h3>
                <p style="color: var(--text-secondary); margin: 5px 0 0 0;">Unread</p>
            </div>
        </div>

        <!-- Actions -->
        <div style="margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
            <button onclick="markAllAsRead()" style="padding: 10px 20px; background: linear-gradient(135deg, #f68b1f, #fbbf24); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-check-double"></i> Mark All as Read
            </button>
            <button onclick="filterNotifications('all')" id="btn-all" class="filter-btn active" style="padding: 10px 20px; border: 2px solid #f68b1f; background: var(--bg-primary); color: #f68b1f; border-radius: 10px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-list"></i> All
            </button>
            <button onclick="filterNotifications('unread')" id="btn-unread" class="filter-btn" style="padding: 10px 20px; border: 2px solid var(--border-color); background: var(--bg-primary); color: var(--text-secondary); border-radius: 10px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-envelope"></i> Unread
            </button>
            <button onclick="filterNotifications('read')" id="btn-read" class="filter-btn" style="padding: 10px 20px; border: 2px solid var(--border-color); background: var(--bg-primary); color: var(--text-secondary); border-radius: 10px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-envelope-open"></i> Read
            </button>
        </div>

        <!-- Notifications List -->
        <div class="glass-card" style="padding: 0; overflow: hidden;">
            <?php if ($notifications->num_rows > 0): ?>
                <?php 
                $icons = [
                    'assignment' => ['icon' => 'clipboard-list', 'color' => '#3b82f6'],
                    'grade' => ['icon' => 'star', 'color' => '#fbbf24'],
                    'announcement' => ['icon' => 'bullhorn', 'color' => '#f68b1f'],
                    'deadline_reminder' => ['icon' => 'clock', 'color' => '#ef4444'],
                    'resource' => ['icon' => 'book', 'color' => '#10b981'],
                    'system' => ['icon' => 'info-circle', 'color' => '#8b5cf6']
                ];
                
                while ($notif = $notifications->fetch_assoc()): 
                    $iconData = $icons[$notif['notification_type']] ?? $icons['system'];
                    $isUnread = $notif['is_read'] == 0;
                    
                    // Format time
                    $time = strtotime($notif['created_at']);
                    $now = time();
                    $diff = $now - $time;
                    
                    if ($diff < 60) $timeStr = 'Just now';
                    elseif ($diff < 3600) $timeStr = floor($diff / 60) . 'm ago';
                    elseif ($diff < 86400) $timeStr = floor($diff / 3600) . 'h ago';
                    elseif ($diff < 604800) $timeStr = floor($diff / 86400) . 'd ago';
                    else $timeStr = date('M d, Y', $time);
                ?>
                <div class="notification-row <?php echo $isUnread ? 'unread' : ''; ?>" data-read="<?php echo $notif['is_read']; ?>" data-id="<?php echo $notif['notification_id']; ?>" onclick="markAsRead(<?php echo $notif['notification_id']; ?>, '<?php echo $notif['link'] ?? '#'; ?>')">
                    <div style="display: flex; align-items: flex-start; gap: 16px; padding: 20px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s; <?php echo $isUnread ? 'background: rgba(246, 139, 31, 0.05); border-left: 4px solid #f68b1f;' : ''; ?>">
                        <div style="min-width: 48px; height: 48px; border-radius: 50%; background: <?php echo $iconData['color']; ?>15; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-<?php echo $iconData['icon']; ?>" style="color: <?php echo $iconData['color']; ?>; font-size: 20px;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0 0 8px 0;">
                                <?php echo htmlspecialchars($notif['title']); ?>
                                <?php if ($isUnread): ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; background: #f68b1f; border-radius: 50%; margin-left: 8px;"></span>
                                <?php endif; ?>
                            </h4>
                            <p style="color: var(--text-secondary); margin: 0 0 8px 0; line-height: 1.5;">
                                <?php echo htmlspecialchars($notif['message']); ?>
                            </p>
                            <div style="display: flex; align-items: center; gap: 12px; font-size: 13px; color: var(--text-secondary);">
                                <span><i class="fas fa-clock"></i> <?php echo $timeStr; ?></span>
                                <span><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $notif['notification_type'])); ?></span>
                                <?php if ($notif['priority'] != 'normal'): ?>
                                    <span style="color: <?php echo $notif['priority'] == 'high' ? '#f59e0b' : ($notif['priority'] == 'urgent' ? '#ef4444' : '#6b7280'); ?>;"><i class="fas fa-flag"></i> <?php echo ucfirst($notif['priority']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($notif['link']): ?>
                            <div style="display: flex; align-items: center;">
                                <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-bell-slash" style="font-size: 72px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 20px;"></i>
                    <h3 style="font-size: 20px; font-weight: 600; color: var(--text-primary); margin: 0 0 10px 0;">No Notifications</h3>
                    <p style="color: var(--text-secondary); margin: 0;">You're all caught up!</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function markAsRead(notificationId, link) {
            fetch('api/notifications.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'notification_id=' + notificationId
            }).then(() => {
                if (link && link !== '#') {
                    window.location.href = link;
                } else {
                    location.reload();
                }
            });
        }

        function markAllAsRead() {
            if (confirm('Mark all notifications as read?')) {
                fetch('api/notifications.php?action=mark_all_read', {
                    method: 'POST'
                }).then(() => location.reload());
            }
        }

        function filterNotifications(filter) {
            const rows = document.querySelectorAll('.notification-row');
            const buttons = document.querySelectorAll('.filter-btn');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-' + filter).classList.add('active');
            
            rows.forEach(row => {
                if (filter === 'all') {
                    row.style.display = 'block';
                } else if (filter === 'unread') {
                    row.style.display = row.dataset.read == '0' ? 'block' : 'none';
                } else if (filter === 'read') {
                    row.style.display = row.dataset.read == '1' ? 'block' : 'none';
                }
            });
        }
    </script>
</body>
</html>
