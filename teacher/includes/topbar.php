<?php
// Set default page title if not defined
if (!isset($page_title)) {
    $page_title = 'Dashboard';
}
if (!isset($page_icon)) {
    $page_icon = 'fas fa-home';
}

// Get unread notifications count
$unread_notifications = 0;
if (isset($teacher_id)) {
    $notif_stmt = $conn->prepare("SELECT COUNT(*) as count FROM teacher_notifications WHERE teacher_id = ? AND is_read = 0");
    $notif_stmt->bind_param('i', $teacher_id);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->get_result();
    $unread_notifications = $notif_result->fetch_assoc()['count'];
    $notif_stmt->close();
}
?>
<header class="topbar">
    <button class="icon-btn" id="menuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>
    
    <h1 style="font-size: 24px; font-weight: 700; color: var(--text-primary);">
        <i class="<?php echo $page_icon; ?>"></i> <?php echo $page_title; ?>
    </h1>
    
    <div class="topbar-actions">
        <button class="icon-btn" id="themeToggle" title="Toggle Theme">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        
        <button class="icon-btn" title="Notifications" onclick="toggleNotifications()">
            <i class="fas fa-bell"></i>
            <?php if ($unread_notifications > 0): ?>
            <span class="badge"><?php echo $unread_notifications; ?></span>
            <?php endif; ?>
        </button>
        
        <div class="user-profile" onclick="toggleUserMenu()" style="cursor: pointer;">
            <div class="user-avatar">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div style="display: none; line-height: 1.2;" class="user-info">
                <div style="font-weight: 600; color: var(--text-primary);"><?php echo $teacher_initial ?? 'Teacher'; ?></div>
                <div style="font-size: 12px; color: var(--text-secondary);">Instructor</div>
            </div>
            <i class="fas fa-chevron-down" style="margin-left: 8px; font-size: 12px; color: var(--text-secondary);"></i>
        </div>
    </div>
</header>

<!-- Notification Dropdown -->
<div id="notificationDropdown" class="dropdown-menu" style="display: none;">
    <div class="dropdown-header">
        <h4>Notifications</h4>
        <?php if ($unread_notifications > 0): ?>
        <span class="badge badge-primary"><?php echo $unread_notifications; ?> New</span>
        <?php endif; ?>
    </div>
    <div class="dropdown-body" id="notificationList">
        <div class="text-center p-4">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>
    </div>
    <div class="dropdown-footer">
        <a href="notifications.php" style="text-decoration: none;">View All Notifications</a>
    </div>
</div>

<!-- User Menu Dropdown -->
<div id="userMenuDropdown" class="dropdown-menu" style="display: none; right: 20px; width: 280px;">
    <div class="dropdown-header" style="flex-direction: column; align-items: flex-start; gap: 8px; padding-bottom: 15px;">
        <div style="display: flex; align-items: center; gap: 12px; width: 100%;">
            <div class="user-avatar" style="width: 50px; height: 50px; font-size: 24px;">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div>
                <div style="font-weight: 700; color: var(--text-primary);"><?php echo $teacher_name ?? 'Teacher'; ?></div>
                <div style="font-size: 13px; color: var(--text-secondary);"><?php echo $teacher_email ?? ''; ?></div>
            </div>
        </div>
    </div>
    <div class="dropdown-body" style="padding: 0;">
        <a href="dashboard.php" class="notification-item">
            <i class="fas fa-home" style="color: #667eea;"></i>
            <div>
                <strong>Dashboard</strong>
                <p>View overview</p>
            </div>
        </a>
        <a href="courses.php" class="notification-item">
            <i class="fas fa-book" style="color: #10b981;"></i>
            <div>
                <strong>My Courses</strong>
                <p>Manage courses</p>
            </div>
        </a>
        <a href="#" class="notification-item">
            <i class="fas fa-user-circle" style="color: #f59e0b;"></i>
            <div>
                <strong>Profile Settings</strong>
                <p>Update your profile</p>
            </div>
        </a>
        <hr style="margin: 5px 0; border: none; border-top: 1px solid var(--border-color);">
        <a href="logout.php" class="notification-item" style="color: #ef4444;">
            <i class="fas fa-sign-out-alt" style="color: #ef4444;"></i>
            <div>
                <strong>Logout</strong>
                <p>Sign out of your account</p>
            </div>
        </a>
    </div>
</div>

<!-- Overlay for mobile sidebar -->
<div id="sidebarOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999;" onclick="toggleSidebar()"></div>

<style>
/* Same dropdown styles as student topbar */
.dropdown-menu {
    position: fixed;
    top: calc(var(--topbar-height) + 10px);
    right: 30px;
    width: 380px;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 10px 40px var(--shadow-color);
    z-index: 1000;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.dropdown-header {
    padding: 20px;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dropdown-header h4 {
    margin: 0;
    font-size: 1.1em;
    font-weight: 700;
    color: var(--text-primary);
}

.dropdown-body {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    align-items: start;
    gap: 15px;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    text-decoration: none;
    color: var(--text-primary);
    transition: background 0.2s;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item:hover {
    background: var(--bg-secondary);
}

.notification-item i {
    font-size: 1.3em;
    margin-top: 3px;
    min-width: 24px;
}

.notification-item strong {
    display: block;
    margin-bottom: 3px;
    color: var(--text-primary);
    font-weight: 600;
}

.notification-item p {
    margin: 0;
    font-size: 0.85em;
    color: var(--text-secondary);
}

.dropdown-footer {
    padding: 15px;
    text-align: center;
    border-top: 2px solid var(--border-color);
}

.dropdown-footer a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
}

.dropdown-footer a:hover {
    text-decoration: underline;
}

.badge-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 5px 12px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 700;
}
</style>

<script>
// Load notifications when dropdown opens
async function loadNotifications() {
    try {
        const response = await fetch('api/notifications.php?action=get_recent&limit=5');
        const data = await response.json();
        
        const container = document.getElementById('notificationList');
        if (data.success && data.notifications.length > 0) {
            container.innerHTML = data.notifications.map(n => `
                <a href="${n.action_url || '#'}" class="notification-item">
                    <i class="fas ${getNotificationIcon(n.notification_type)}" style="color: ${getNotificationColor(n.priority)};"></i>
                    <div>
                        <strong>${n.title}</strong>
                        <p>${n.message}</p>
                        <small style="color: var(--text-secondary);">${formatTime(n.created_at)}</small>
                    </div>
                </a>
            `).join('');
        } else {
            container.innerHTML = '<div class="text-center p-4" style="color: var(--text-secondary);">No notifications</div>';
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

function getNotificationIcon(type) {
    const icons = {
        'new_submission': 'fa-file-upload',
        'late_submission': 'fa-clock',
        'student_query': 'fa-question-circle',
        'deadline_reminder': 'fa-bell',
        'system': 'fa-info-circle'
    };
    return icons[type] || 'fa-bell';
}

function getNotificationColor(priority) {
    const colors = {
        'urgent': '#ef4444',
        'high': '#f59e0b',
        'normal': '#667eea',
        'low': '#6b7280'
    };
    return colors[priority] || '#667eea';
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return date.toLocaleDateString();
}

// Toggle Notifications
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    const userMenu = document.getElementById('userMenuDropdown');
    
    if (userMenu) userMenu.style.display = 'none';
    
    if (dropdown.style.display === 'none') {
        loadNotifications();
    }
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Toggle User Menu
function toggleUserMenu() {
    const dropdown = document.getElementById('userMenuDropdown');
    const notifications = document.getElementById('notificationDropdown');
    
    if (notifications) notifications.style.display = 'none';
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    const notificationDropdown = document.getElementById('notificationDropdown');
    const userMenuDropdown = document.getElementById('userMenuDropdown');
    
    if (!e.target.closest('.icon-btn[title="Notifications"]') && !e.target.closest('#notificationDropdown')) {
        if (notificationDropdown) notificationDropdown.style.display = 'none';
    }
    
    if (!e.target.closest('.user-profile') && !e.target.closest('#userMenuDropdown')) {
        if (userMenuDropdown) userMenuDropdown.style.display = 'none';
    }
});

// Theme Toggle
const themeToggle = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');
const html = document.documentElement;

const savedTheme = localStorage.getItem('theme') || 'light';
html.setAttribute('data-theme', savedTheme);
themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';

themeToggle.addEventListener('click', () => {
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
});

// Mobile Menu Toggle
const menuToggle = document.getElementById('menuToggle');
if (window.innerWidth <= 1024) {
    menuToggle.style.display = 'flex';
}

menuToggle.addEventListener('click', () => {
    toggleSidebar();
});

window.addEventListener('resize', () => {
    if (window.innerWidth <= 1024) {
        menuToggle.style.display = 'flex';
    } else {
        menuToggle.style.display = 'none';
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.style.display = 'none';
    }
});
</script>
