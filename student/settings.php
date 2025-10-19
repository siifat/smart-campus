<?php
session_start();
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Location: ../login.html');
    exit;
}

require_once('../config/database.php');
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$message = '';
$message_type = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $email_notifications = isset($_POST['email_notifications_enabled']) ? 1 : 0;
    $notification_prefs = [
        'assignment_reminders' => isset($_POST['assignment_reminders']),
        'grade_updates' => isset($_POST['grade_updates']),
        'course_announcements' => isset($_POST['course_announcements']),
        'schedule_changes' => isset($_POST['schedule_changes']),
        'resource_updates' => isset($_POST['resource_updates']),
        'achievement_notifications' => isset($_POST['achievement_notifications'])
    ];
    $prefs_json = json_encode($notification_prefs);
    
    $update_query = "UPDATE students SET email_notifications_enabled = ?, notification_preferences = ? WHERE student_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('iss', $email_notifications, $prefs_json, $student_id);
    
    if ($stmt->execute()) {
        $message = 'Settings saved successfully!';
        $message_type = 'success';
    } else {
        $message = 'Error saving settings.';
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'All password fields are required.';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New passwords do not match.';
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = 'Password must be at least 6 characters.';
        $message_type = 'error';
    } else {
        $verify_query = "SELECT password_hash FROM students WHERE student_id = ?";
        $stmt = $conn->prepare($verify_query);
        $stmt->bind_param('s', $student_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        
        if ($user_data && password_verify($current_password, $user_data['password_hash'])) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE students SET password_hash = ? WHERE student_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('ss', $new_hash, $student_id);
            
            if ($stmt->execute()) {
                $message = 'Password changed successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error changing password.';
                $message_type = 'error';
            }
        } else {
            $message = 'Current password is incorrect.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Fetch current settings
$query = "SELECT email_notifications_enabled, notification_preferences, full_name FROM students WHERE student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$notification_prefs = $student['notification_preferences'] ? json_decode($student['notification_preferences'], true) : [];
$default_prefs = ['assignment_reminders' => true, 'grade_updates' => true, 'course_announcements' => true, 'schedule_changes' => true, 'resource_updates' => false, 'achievement_notifications' => true];
foreach ($default_prefs as $key => $value) {
    if (!isset($notification_prefs[$key])) {
        $notification_prefs[$key] = $value;
    }
}

$email_notifications_enabled = $student['email_notifications_enabled'] ?? 1;
$page_title = 'Settings';
$page_icon = 'fas fa-cog';
$show_page_title = true;
$total_points = 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - UIU Smart Campus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --bg-primary: #ffffff; --bg-secondary: #f8fafc; --text-primary: #0f172a; --text-secondary: #475569; --border-color: #e2e8f0; --shadow-color: rgba(0, 0, 0, 0.1); --card-bg: rgba(255, 255, 255, 0.9); --sidebar-width: 280px; --topbar-height: 72px; }
        [data-theme="dark"] { --bg-primary: #0f172a; --bg-secondary: #1e293b; --text-primary: #f1f5f9; --text-secondary: #cbd5e1; --border-color: #334155; --shadow-color: rgba(0, 0, 0, 0.3); --card-bg: rgba(30, 41, 59, 0.9); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-secondary); color: var(--text-secondary); transition: all 0.3s ease; }
        .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: var(--sidebar-width); background: linear-gradient(180deg, #f68b1f 0%, #fbbf24 50%, #f68b1f 100%); padding: 24px; overflow-y: auto; z-index: 100; transition: transform 0.3s ease; box-shadow: 4px 0 20px rgba(246, 139, 31, 0.15); }
        [data-theme="dark"] .sidebar { background: linear-gradient(180deg, #d97706 0%, #f59e0b 50%, #d97706 100%); }
        .sidebar-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 32px; padding: 12px; background: rgba(255, 255, 255, 0.2); border-radius: 12px; backdrop-filter: blur(10px); }
        .sidebar-logo i { font-size: 32px; color: white; }
        .sidebar-logo span { font-size: 20px; font-weight: 800; color: white; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; margin-bottom: 8px; border-radius: 12px; color: rgba(255, 255, 255, 0.9); text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; }
        .nav-item:hover { background: rgba(255, 255, 255, 0.2); transform: translateX(5px); }
        .nav-item.active { background: rgba(255, 255, 255, 0.3); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .nav-item i { font-size: 20px; width: 24px; }
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; padding: 24px; padding-top: calc(var(--topbar-height) + 24px); }
        .topbar { position: fixed; top: 0; left: var(--sidebar-width); right: 0; height: var(--topbar-height); background: var(--card-bg); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); padding: 0 32px; display: flex; align-items: center; justify-content: space-between; z-index: 90; box-shadow: 0 2px 12px var(--shadow-color); }
        .search-box { flex: 1; max-width: 500px; position: relative; }
        .search-box input { width: 100%; padding: 12px 16px 12px 48px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-size: 14px; transition: all 0.3s ease; }
        .search-box input:focus { outline: none; border-color: #f68b1f; box-shadow: 0 0 0 3px rgba(246, 139, 31, 0.1); }
        .search-box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); }
        .topbar-actions { display: flex; align-items: center; gap: 16px; }
        .icon-btn { width: 44px; height: 44px; border-radius: 12px; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; position: relative; }
        .icon-btn:hover { background: #f68b1f; color: white; transform: translateY(-2px); }
        .icon-btn .badge { position: absolute; top: -4px; right: -4px; background: #ef4444; color: white; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 10px; border: 2px solid var(--bg-primary); }
        .user-profile { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 12px; background: var(--bg-secondary); cursor: pointer; transition: all 0.3s ease; }
        .user-profile:hover { background: var(--border-color); }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #f68b1f, #fbbf24); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; }
        .glass-card { background: var(--card-bg); backdrop-filter: blur(20px); border: 1px solid var(--border-color); border-radius: 20px; padding: 24px; box-shadow: 0 4px 20px var(--shadow-color); transition: all 0.3s ease; margin-bottom: 24px; }
        .glass-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px var(--shadow-color); }
        .toggle-switch { position: relative; display: inline-block; width: 56px; height: 30px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: 0.3s; border-radius: 30px; }
        .toggle-slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 4px; bottom: 4px; background-color: white; transition: 0.3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .toggle-switch input:checked + .toggle-slider { background: linear-gradient(135deg, #f68b1f 0%, #fbbf24 100%); }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(26px); }
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } .topbar { left: 0; } }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>
    
    <div class="main-content">
        <?php if ($message): ?>
            <div class="glass-card" style="padding: 16px; border-left: 4px solid <?php echo $message_type === 'success' ? '#10b981' : '#ef4444'; ?>;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>" style="color: <?php echo $message_type === 'success' ? '#10b981' : '#ef4444'; ?>; font-size: 20px;"></i>
                    <span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($message); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="max-width: 900px;">
            <!-- Notifications Settings -->
            <div class="glass-card">
                <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--border-color); display: flex; align-items: center; gap: 12px;"><i class="fas fa-bell" style="color: #f68b1f;"></i> Email Notifications</h2>
                <form method="POST">
                    <div style="background: linear-gradient(135deg, rgba(246, 139, 31, 0.1), rgba(251, 191, 36, 0.1)); border: 2px solid #f68b1f; border-radius: 16px; padding: 24px; margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div><h3 style="font-size: 18px; font-weight: 700; color: #f68b1f; margin-bottom: 4px;"><i class="fas fa-envelope"></i> Get Email Notifications</h3><p style="color: var(--text-secondary); margin: 0;">Receive important updates via email</p></div>
                            <label class="toggle-switch"><input type="checkbox" name="email_notifications_enabled" id="master-toggle" <?php echo $email_notifications_enabled ? 'checked' : ''; ?> onchange="toggleNotifications()"><span class="toggle-slider"></span></label>
                        </div>
                    </div>
                    
                    <div id="notification-options" style="<?php echo !$email_notifications_enabled ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 14px;"><i class="fas fa-info-circle"></i> Choose what you want to be notified about</p>
                        <?php
                        $notif_types = [
                            ['assignment_reminders', 'Assignment Reminders', 'Get reminded about upcoming deadlines'],
                            ['grade_updates', 'Grade Updates', 'Be notified when new grades are published'],
                            ['course_announcements', 'Course Announcements', 'Receive important announcements'],
                            ['schedule_changes', 'Schedule Changes', 'Get notified about class schedule changes'],
                            ['resource_updates', 'Resource Updates', 'Be notified when materials are uploaded'],
                            ['achievement_notifications', 'Achievement Notifications', 'Get notified when you earn badges']
                        ];
                        foreach ($notif_types as $notif):
                        ?>
                        <div style="padding: 20px 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                            <div><h4 style="font-weight: 600; color: var(--text-primary); font-size: 16px; margin-bottom: 4px;"><?php echo $notif[1]; ?></h4><p style="color: var(--text-secondary); font-size: 14px; margin: 0;"><?php echo $notif[2]; ?></p></div>
                            <label class="toggle-switch"><input type="checkbox" name="<?php echo $notif[0]; ?>" <?php echo $notification_prefs[$notif[0]] ? 'checked' : ''; ?>><span class="toggle-slider"></span></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top: 24px;">
                        <button type="submit" name="update_settings" style="padding: 12px 24px; border: none; border-radius: 12px; background: linear-gradient(135deg, #f68b1f, #fbbf24); color: white; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(246, 139, 31, 0.3);"><i class="fas fa-save"></i> Save Notification Settings</button>
                    </div>
                </form>
            </div>
            
            <!-- Password Change -->
            <div class="glass-card">
                <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--border-color); display: flex; align-items: center; gap: 12px;"><i class="fas fa-lock" style="color: #f68b1f;"></i> Change Password</h2>
                <form method="POST">
                    <div style="margin-bottom: 20px;"><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Current Password</label><input type="password" name="current_password" required style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"></div>
                    <div style="margin-bottom: 20px;"><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">New Password</label><input type="password" name="new_password" required minlength="6" style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"><small style="color: var(--text-secondary); font-size: 13px;">Must be at least 6 characters</small></div>
                    <div style="margin-bottom: 20px;"><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Confirm New Password</label><input type="password" name="confirm_password" required minlength="6" style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"></div>
                    <button type="submit" name="change_password" style="padding: 12px 24px; border: none; border-radius: 12px; background: #ef4444; color: white; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);"><i class="fas fa-key"></i> Change Password</button>
                </form>
            </div>
            
            <!-- Account Info -->
            <div class="glass-card" style="margin-bottom: 0;">
                <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--border-color); display: flex; align-items: center; gap: 12px;"><i class="fas fa-info-circle" style="color: #f68b1f;"></i> Account Information</h2>
                <div style="padding: 20px 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;"><div><h4 style="font-weight: 600; color: var(--text-primary); font-size: 16px; margin-bottom: 4px;">Student ID</h4><p style="color: var(--text-secondary); font-size: 14px; margin: 0;"><?php echo htmlspecialchars($student_id); ?></p></div></div>
                <div style="padding: 20px 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;"><div><h4 style="font-weight: 600; color: var(--text-primary); font-size: 16px; margin-bottom: 4px;">Name</h4><p style="color: var(--text-secondary); font-size: 14px; margin: 0;"><?php echo htmlspecialchars($student['full_name']); ?></p></div></div>
                <div style="padding: 20px 0; display: flex; justify-content: space-between; align-items: center;"><div><h4 style="font-weight: 600; color: var(--text-primary); font-size: 16px; margin-bottom: 4px;">Edit Profile</h4><p style="color: var(--text-secondary); font-size: 14px; margin: 0;">Update your personal information</p></div><a href="profile.php" style="padding: 12px 24px; border: none; border-radius: 12px; background: linear-gradient(135deg, #f68b1f, #fbbf24); color: white; font-weight: 600; text-decoration: none; box-shadow: 0 4px 12px rgba(246, 139, 31, 0.3); display: inline-flex; align-items: center; gap: 8px;"><i class="fas fa-user-edit"></i> Go to Profile</a></div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleNotifications() {
            const master = document.getElementById('master-toggle');
            const options = document.getElementById('notification-options');
            options.style.opacity = master.checked ? '1' : '0.5';
            options.style.pointerEvents = master.checked ? 'auto' : 'none';
        }
    </script>
</body>
</html>
