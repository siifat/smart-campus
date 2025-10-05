<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<div class="sidebar">
    <div class="sidebar-header">
        <span class="sidebar-logo">🎓 Smart Campus</span>
        <span class="sidebar-subtitle">Admin Control Panel</span>
    </div>
    
    <div class="sidebar-menu">
        <!-- Main Section -->
        <div class="menu-section">
            <div class="menu-section-title">Main</div>
            <a href="dashboard.php" class="menu-item <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="analytics.php" class="menu-item <?php echo $current_page == 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i>
                <span>Analytics</span>
            </a>
        </div>
        
        <!-- Data Management Section -->
        <div class="menu-section">
            <div class="menu-section-title">Data Management</div>
            <a href="manage.php?table=students" class="menu-item <?php echo $current_page == 'manage' && $_GET['table'] == 'students' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
            <a href="manage.php?table=teachers" class="menu-item <?php echo $current_page == 'manage' && $_GET['table'] == 'teachers' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Teachers</span>
            </a>
            <a href="manage.php?table=courses" class="menu-item <?php echo $current_page == 'manage' && $_GET['table'] == 'courses' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span>Courses</span>
            </a>
            <a href="manage.php?table=enrollments" class="menu-item <?php echo $current_page == 'manage' && $_GET['table'] == 'enrollments' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Enrollments</span>
            </a>
            <a href="manage.php?table=departments" class="menu-item <?php echo $current_page == 'manage' && $_GET['table'] == 'departments' ? 'active' : ''; ?>">
                <i class="fas fa-building"></i>
                <span>Departments</span>
            </a>
            <a href="manage.php?table=programs" class="menu-item <?php echo $current_page == 'manage' && $_GET['table'] == 'programs' ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap"></i>
                <span>Programs</span>
            </a>
            <a href="manage.php?table=trimesters" class="menu-item <?php echo $current_page == 'manage' && $_GET['table'] == 'trimesters' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Trimesters</span>
            </a>
        </div>
        
        <!-- Content Section -->
        <div class="menu-section">
            <div class="menu-section-title">Content</div>
            <a href="manage.php?table=notes" class="menu-item <?php echo $current_page == 'manage' && $_GET['table'] == 'notes' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Notes</span>
                <?php
                $pending_notes = $conn->query("SELECT COUNT(*) as count FROM notes WHERE status = 'pending'")->fetch_assoc()['count'];
                if ($pending_notes > 0) echo "<span class='badge'>$pending_notes</span>";
                ?>
            </a>
            <a href="manage.php?table=question_solutions" class="menu-item <?php echo $current_page == 'manage' && $_GET['table'] == 'question_solutions' ? 'active' : ''; ?>">
                <i class="fas fa-question-circle"></i>
                <span>Solutions</span>
                <?php
                $pending_solutions = $conn->query("SELECT COUNT(*) as count FROM question_solutions WHERE status = 'pending'")->fetch_assoc()['count'];
                if ($pending_solutions > 0) echo "<span class='badge'>$pending_solutions</span>";
                ?>
            </a>
            <a href="leaderboard.php" class="menu-item <?php echo $current_page == 'leaderboard' ? 'active' : ''; ?>">
                <i class="fas fa-trophy"></i>
                <span>Leaderboard</span>
            </a>
        </div>
        
        <!-- File Uploads Section -->
        <div class="menu-section">
            <div class="menu-section-title">Bulk Operations</div>
            <a href="upload_departments.php" class="menu-item <?php echo $current_page == 'upload_departments' ? 'active' : ''; ?>">
                <i class="fas fa-upload"></i>
                <span>Upload Departments</span>
            </a>
            <a href="upload_programs.php" class="menu-item <?php echo $current_page == 'upload_programs' ? 'active' : ''; ?>">
                <i class="fas fa-upload"></i>
                <span>Upload Programs</span>
            </a>
            <a href="upload_trimesters.php" class="menu-item <?php echo $current_page == 'upload_trimesters' ? 'active' : ''; ?>">
                <i class="fas fa-upload"></i>
                <span>Upload Trimesters</span>
            </a>
            <a href="upload_courses.php" class="menu-item <?php echo $current_page == 'upload_courses' ? 'active' : ''; ?>">
                <i class="fas fa-upload"></i>
                <span>Upload Courses</span>
            </a>
        </div>
        
        <!-- System Section -->
        <div class="menu-section">
            <div class="menu-section-title">System</div>
            <a href="backup.php" class="menu-item <?php echo $current_page == 'backup' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i>
                <span>Backup & Restore</span>
            </a>
            <a href="logs.php" class="menu-item <?php echo $current_page == 'logs' ? 'active' : ''; ?>">
                <i class="fas fa-list-alt"></i>
                <span>Activity Logs</span>
            </a>
            <a href="settings.php" class="menu-item <?php echo $current_page == 'settings' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
        
        <!-- Account Section -->
        <div class="menu-section">
            <div class="menu-section-title">Account</div>
            <a href="profile.php" class="menu-item <?php echo $current_page == 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<script>
// Preserve sidebar scroll position across page navigation
(function() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    
    // Check if this is the first page load after login (dashboard without referrer from within admin)
    const currentPage = window.location.pathname;
    const isDashboard = currentPage.includes('dashboard.php');
    const hasAdminReferrer = document.referrer && document.referrer.includes('/admin/');
    
    // If on dashboard and no admin referrer, clear saved scroll (fresh login)
    if (isDashboard && !hasAdminReferrer) {
        sessionStorage.removeItem('sidebarScrollPos');
        sidebar.scrollTop = 0; // Reset to top
    } else {
        // Restore scroll position on page load
        const savedScrollPos = sessionStorage.getItem('sidebarScrollPos');
        if (savedScrollPos !== null) {
            sidebar.scrollTop = parseInt(savedScrollPos, 10);
        }
    }
    
    // Save scroll position before navigation
    const saveScrollPosition = function() {
        sessionStorage.setItem('sidebarScrollPos', sidebar.scrollTop);
    };
    
    // Save on scroll (debounced)
    let scrollTimeout;
    sidebar.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(saveScrollPosition, 100);
    });
    
    // Save before clicking any link
    const menuLinks = sidebar.querySelectorAll('.menu-item');
    menuLinks.forEach(function(link) {
        link.addEventListener('click', saveScrollPosition);
    });
    
    // Save before page unload
    window.addEventListener('beforeunload', saveScrollPosition);
})();
</script>
