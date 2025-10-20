<?php
session_start();
if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');
$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? '';
$teacher_initial = $_SESSION['teacher_initial'] ?? '';
$page_title = 'Notifications';
$page_icon = 'fas fa-bell';

// Fetch all notifications
$query = "SELECT * FROM teacher_notifications WHERE teacher_id = ? ORDER BY created_at DESC LIMIT 100";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $teacher_id);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// Get unread count
$count_query = "SELECT COUNT(*) as unread_count FROM teacher_notifications WHERE teacher_id = ? AND is_read = 0";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param('i', $teacher_id);
$count_stmt->execute();
$unread_count = $count_stmt->get_result()->fetch_assoc()['unread_count'];
$count_stmt->close();
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
        
        /* Glass Card */
        .glass-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px var(--shadow-color);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid var(--border-color);
        }
        
        .glass-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px var(--shadow-color);
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
        
        .filter-btn {
            padding: 10px 20px;
            border: 2px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-secondary);
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            color: white !important;
            border-color: #667eea !important;
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
    <main class="main-content">
        <!-- Header Stats -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="glass-card" style="padding: 20px; text-align: center;">
                <i class="fas fa-bell" style="font-size: 32px; color: #667eea; margin-bottom: 10px;"></i>
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
            <button onclick="markAllAsRead()" style="padding: 10px 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-check-double"></i> Mark All as Read
            </button>
            <button onclick="filterNotifications('all')" id="btn-all" class="filter-btn active">
                <i class="fas fa-list"></i> All
            </button>
            <button onclick="filterNotifications('unread')" id="btn-unread" class="filter-btn">
                <i class="fas fa-envelope"></i> Unread
            </button>
            <button onclick="filterNotifications('read')" id="btn-read" class="filter-btn">
                <i class="fas fa-envelope-open"></i> Read
            </button>
        </div>

        <!-- Notifications List -->
        <div class="glass-card" style="padding: 0; overflow: hidden;">
            <?php if ($notifications->num_rows > 0): ?>
                <?php 
                $icons = [
                    'new_submission' => ['icon' => 'file-upload', 'color' => '#3b82f6'],
                    'late_submission' => ['icon' => 'clock', 'color' => '#ef4444'],
                    'student_query' => ['icon' => 'question-circle', 'color' => '#f59e0b'],
                    'deadline_reminder' => ['icon' => 'bell', 'color' => '#8b5cf6'],
                    'system' => ['icon' => 'info-circle', 'color' => '#667eea']
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
                <div class="notification-row <?php echo $isUnread ? 'unread' : ''; ?>" data-read="<?php echo $notif['is_read']; ?>" data-id="<?php echo $notif['notification_id']; ?>" onclick="markAsRead(<?php echo $notif['notification_id']; ?>, '<?php echo $notif['action_url'] ?? '#'; ?>')">
                    <div style="display: flex; align-items: flex-start; gap: 16px; padding: 20px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s; <?php echo $isUnread ? 'background: rgba(102, 126, 234, 0.05); border-left: 4px solid #667eea;' : ''; ?>">
                        <div style="min-width: 48px; height: 48px; border-radius: 50%; background: <?php echo $iconData['color']; ?>15; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-<?php echo $iconData['icon']; ?>" style="color: <?php echo $iconData['color']; ?>; font-size: 20px;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0 0 8px 0;">
                                <?php echo htmlspecialchars($notif['title']); ?>
                                <?php if ($isUnread): ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; background: #667eea; border-radius: 50%; margin-left: 8px;"></span>
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
                        <?php if ($notif['action_url']): ?>
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
