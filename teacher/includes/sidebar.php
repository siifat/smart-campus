<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Teacher Portal</span>
    </div>
    
    <nav>
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="courses.php" class="nav-item <?php echo $current_page == 'courses' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i>
            <span>My Courses</span>
        </a>
        <a href="assignments.php" class="nav-item <?php echo $current_page == 'assignments' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Assignments</span>
        </a>
        <a href="submissions.php" class="nav-item <?php echo $current_page == 'submissions' ? 'active' : ''; ?>">
            <i class="fas fa-file-upload"></i>
            <span>Submissions</span>
        </a>
        <a href="students.php" class="nav-item <?php echo $current_page == 'students' ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate"></i>
            <span>Students</span>
        </a>
        <a href="grades.php" class="nav-item <?php echo $current_page == 'grades' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Grades & Analytics</span>
        </a>
        <a href="#" class="nav-item <?php echo $current_page == 'announcements' ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </a>
        <a href="#" class="nav-item <?php echo $current_page == 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i>
            <span>Profile</span>
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

// Preserve sidebar scroll position
(function() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    
    const savedScrollPos = sessionStorage.getItem('teacherSidebarScrollPos');
    if (savedScrollPos !== null) {
        sidebar.scrollTop = parseInt(savedScrollPos, 10);
    }
    
    const saveScrollPosition = function() {
        sessionStorage.setItem('teacherSidebarScrollPos', sidebar.scrollTop);
    };
    
    let scrollTimeout;
    sidebar.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(saveScrollPosition, 100);
    });
    
    const menuLinks = sidebar.querySelectorAll('.nav-item');
    menuLinks.forEach(function(link) {
        link.addEventListener('click', saveScrollPosition);
    });
    
    window.addEventListener('beforeunload', saveScrollPosition);
})();
</script>
