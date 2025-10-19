<?php
// Fetch student points if not already set
if (!isset($total_points) && isset($student_id)) {
    $stmt = $conn->prepare("SELECT COALESCE(total_points, 0) as total_points FROM students WHERE student_id = ?");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_data = $result->fetch_assoc();
    $total_points = $student_data['total_points'] ?? 0;
    $stmt->close();
}

// Set default page title if not defined
if (!isset($page_title)) {
    $page_title = 'Dashboard';
}
if (!isset($page_icon)) {
    $page_icon = 'fas fa-home';
}
?>
<header class="topbar">
    <button class="icon-btn" id="menuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>
    
    <?php if (isset($show_page_title) && $show_page_title): ?>
    <h1 style="font-size: 24px; font-weight: 700; color: var(--text-primary);">
        <i class="<?php echo $page_icon; ?>"></i> <?php echo $page_title; ?>
    </h1>
    <?php else: ?>
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search courses, assignments, resources...">
    </div>
    <?php endif; ?>
    
    <div class="topbar-actions">
        <!-- Points Display -->
        <div style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: linear-gradient(135deg, #f68b1f, #fbbf24); border-radius: 12px; margin-right: 12px;">
            <i class="fas fa-star" style="color: white; font-size: 16px;"></i>
            <div style="display: flex; flex-direction: column; line-height: 1;">
                <span style="font-size: 10px; color: rgba(255,255,255,0.8); font-weight: 500;">POINTS</span>
                <span id="user-points" style="font-size: 18px; color: white; font-weight: 700;"><?php echo number_format($total_points ?? 0); ?></span>
            </div>
        </div>
        
        <a href="focus.php" class="icon-btn" title="Focus Session" style="text-decoration: none; color: inherit;">
            <i class="fas fa-brain"></i>
        </a>
        
        <button class="icon-btn" id="themeToggle" title="Toggle Theme">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        
        <button class="icon-btn" title="Notifications" onclick="toggleNotifications()">
            <i class="fas fa-bell"></i>
            <span class="badge">5</span>
        </button>
        
        <button class="icon-btn" title="Messages">
            <i class="fas fa-envelope"></i>
            <span class="badge">3</span>
        </button>
        
        <div class="user-profile" onclick="toggleUserMenu()" style="cursor: pointer;">
            <div class="user-avatar">
                <?php echo strtoupper(substr($student_name, 0, 1)); ?>
            </div>
            <div style="display: none; line-height: 1.2;" class="user-info">
                <div style="font-weight: 700; color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($student_name); ?></div>
                <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($student_id); ?></div>
            </div>
            <i class="fas fa-chevron-down" style="margin-left: 8px; font-size: 12px; color: var(--text-secondary);"></i>
        </div>
    </div>
</header>

<!-- Notification Dropdown -->
<div id="notificationDropdown" class="dropdown-menu" style="display: none;">
    <div class="dropdown-header">
        <h4>Notifications</h4>
        <span class="badge badge-primary">5 New</span>
    </div>
    <div class="dropdown-body">
        <a href="#" class="notification-item">
            <i class="fas fa-trophy" style="color: #fbbf24;"></i>
            <div>
                <strong>Achievement Unlocked!</strong>
                <p>You earned 100 points this week</p>
            </div>
        </a>
        <a href="#" class="notification-item">
            <i class="fas fa-clipboard-list" style="color: #3b82f6;"></i>
            <div>
                <strong>New Assignment</strong>
                <p>Database Management - Due in 3 days</p>
            </div>
        </a>
        <a href="#" class="notification-item">
            <i class="fas fa-calendar-alt" style="color: #10b981;"></i>
            <div>
                <strong>Upcoming Exam</strong>
                <p>Web Development Final - Oct 15, 2025</p>
            </div>
        </a>
        <a href="#" class="notification-item">
            <i class="fas fa-book" style="color: #f68b1f;"></i>
            <div>
                <strong>New Resource Available</strong>
                <p>Java Programming Notes uploaded</p>
            </div>
        </a>
        <a href="#" class="notification-item">
            <i class="fas fa-comment" style="color: #8b5cf6;"></i>
            <div>
                <strong>New Comment</strong>
                <p>Someone commented on your resource</p>
            </div>
        </a>
    </div>
    <div class="dropdown-footer">
        <a href="#" style="text-decoration: none;">View All Notifications</a>
    </div>
</div>

<!-- User Menu Dropdown -->
<div id="userMenuDropdown" class="dropdown-menu" style="display: none; right: 20px; width: 280px;">
    <div class="dropdown-header" style="flex-direction: column; align-items: flex-start; gap: 8px; padding-bottom: 15px;">
        <div style="display: flex; align-items: center; gap: 12px; width: 100%;">
            <div class="user-avatar" style="width: 48px; height: 48px; font-size: 20px;">
                <?php echo strtoupper(substr($student_name, 0, 1)); ?>
            </div>
            <div style="flex: 1; overflow: hidden;">
                <div style="font-weight: 700; font-size: 16px; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($student_name); ?></div>
                <div style="font-size: 13px; color: var(--text-secondary);"><?php echo htmlspecialchars($student_id); ?></div>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: linear-gradient(135deg, #f68b1f, #fbbf24); border-radius: 8px; width: 100%;">
            <i class="fas fa-star" style="color: white;"></i>
            <span style="color: white; font-weight: 600; font-size: 14px;"><?php echo number_format($total_points ?? 0); ?> Points</span>
        </div>
    </div>
    <div class="dropdown-body" style="padding: 0;">
        <a href="dashboard.php" class="notification-item">
            <i class="fas fa-home" style="color: #f68b1f;"></i>
            <div>
                <strong>Dashboard</strong>
                <p>Back to home</p>
            </div>
        </a>
        <a href="profile.php" class="notification-item">
            <i class="fas fa-user" style="color: #3b82f6;"></i>
            <div>
                <strong>My Profile</strong>
                <p>View and edit your profile</p>
            </div>
        </a>
        <a href="schedule.php" class="notification-item">
            <i class="fas fa-calendar-alt" style="color: #10b981;"></i>
            <div>
                <strong>My Schedule</strong>
                <p>View class schedule</p>
            </div>
        </a>
        <a href="resources.php" class="notification-item">
            <i class="fas fa-folder-open" style="color: #fbbf24;"></i>
            <div>
                <strong>Resources</strong>
                <p>Browse study materials</p>
            </div>
        </a>
        <a href="settings.php" class="notification-item">
            <i class="fas fa-cog" style="color: #6b7280;"></i>
            <div>
                <strong>Settings</strong>
                <p>Account preferences</p>
            </div>
        </a>
        <hr style="margin: 5px 0; border: none; border-top: 1px solid var(--border-color);">
        <a href="logout.php" class="notification-item" style="color: #ef4444;">
            <i class="fas fa-sign-out-alt"></i>
            <div>
                <strong>Logout</strong>
                <p>Sign out from your account</p>
            </div>
        </a>
    </div>
</div>

<!-- Overlay for mobile sidebar -->
<div id="sidebarOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999;" onclick="toggleSidebar()"></div>

<style>
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
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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
    color: #f68b1f;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
}

.dropdown-footer a:hover {
    text-decoration: underline;
}

.badge-primary {
    background: linear-gradient(135deg, #f68b1f, #fbbf24);
    color: white;
    padding: 5px 12px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 700;
}
</style>

<script>
// Toggle Notifications Dropdown
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    const userMenu = document.getElementById('userMenuDropdown');
    
    // Hide user menu if open
    if (userMenu) userMenu.style.display = 'none';
    
    // Toggle notifications
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Toggle User Menu Dropdown
function toggleUserMenu() {
    const dropdown = document.getElementById('userMenuDropdown');
    const notifications = document.getElementById('notificationDropdown');
    
    // Hide notifications dropdown
    if (notifications) notifications.style.display = 'none';
    
    // Toggle user menu
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    const notificationDropdown = document.getElementById('notificationDropdown');
    const userMenuDropdown = document.getElementById('userMenuDropdown');
    
    // Close notification dropdown if clicking outside
    if (!e.target.closest('.icon-btn[title="Notifications"]') && 
        !e.target.closest('#notificationDropdown')) {
        if (notificationDropdown) notificationDropdown.style.display = 'none';
    }
    
    // Close user menu if clicking outside
    if (!e.target.closest('.user-profile') && 
        !e.target.closest('#userMenuDropdown')) {
        if (userMenuDropdown) userMenuDropdown.style.display = 'none';
    }
});

// Prevent clicks inside dropdowns from closing them
document.addEventListener('click', function(e) {
    if (e.target.closest('.dropdown-menu')) {
        e.stopPropagation();
    }
}, true);
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
const sidebar = document.getElementById('sidebar');

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
