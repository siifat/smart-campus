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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>
    
    <div class="main-content">
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
    </div>

    <style>
        .notification-row:hover {
            background: var(--bg-secondary) !important;
        }
        .filter-btn.active {
            background: linear-gradient(135deg, #f68b1f, #fbbf24) !important;
            color: white !important;
            border-color: #f68b1f !important;
        }
    </style>

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
