<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <i class="fas fa-graduation-cap"></i>
        <span>Smart Campus</span>
    </div>
    
    <nav>
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="courses.php" class="nav-item <?php echo $current_page == 'courses' || $current_page == 'course_materials' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i>
            <span>My Courses</span>
        </a>
        <a href="assignments.php" class="nav-item <?php echo $current_page == 'assignments' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Assignments</span>
        </a>
        <a href="schedule.php" class="nav-item <?php echo $current_page == 'schedule' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Schedule</span>
        </a>
        <a href="#" class="nav-item <?php echo $current_page == 'performance' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Performance</span>
        </a>
        <a href="resources.php" class="nav-item <?php echo $current_page == 'resources' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>Resources</span>
        </a>
        <a href="focus.php" class="nav-item <?php echo $current_page == 'focus' ? 'active' : ''; ?>">
            <i class="fas fa-brain"></i>
            <span>Focus Session</span>
        </a>
        <a href="#" class="nav-item <?php echo $current_page == 'achievements' ? 'active' : ''; ?>">
            <i class="fas fa-trophy"></i>
            <span>Achievements</span>
        </a>
        <a href="profile.php" class="nav-item <?php echo $current_page == 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
        <a href="settings.php" class="nav-item <?php echo $current_page == 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        <a href="logout.php" class="nav-item" style="margin-top: auto;">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<script>
// Mobile sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
    }
}

// Preserve sidebar scroll position across navigation
(function() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    
    // Restore scroll position on page load
    const savedScrollPos = sessionStorage.getItem('studentSidebarScrollPos');
    if (savedScrollPos !== null) {
        sidebar.scrollTop = parseInt(savedScrollPos, 10);
    }
    
    // Save scroll position before navigation
    const saveScrollPosition = function() {
        sessionStorage.setItem('studentSidebarScrollPos', sidebar.scrollTop);
    };
    
    // Save on scroll (debounced)
    let scrollTimeout;
    sidebar.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(saveScrollPosition, 100);
    });
    
    // Save before clicking any link
    const menuLinks = sidebar.querySelectorAll('.nav-item');
    menuLinks.forEach(function(link) {
        link.addEventListener('click', saveScrollPosition);
    });
    
    // Save before page unload
    window.addEventListener('beforeunload', saveScrollPosition);
})();
</script>
