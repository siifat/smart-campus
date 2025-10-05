<?php
/**
 * Modern Admin Dashboard with Analytics
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

// Get comprehensive statistics
$stats = [];

// Reference Data Counts
$stats['departments'] = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];
$stats['programs'] = $conn->query("SELECT COUNT(*) as count FROM programs")->fetch_assoc()['count'];
$stats['trimesters'] = $conn->query("SELECT COUNT(*) as count FROM trimesters")->fetch_assoc()['count'];
$stats['courses'] = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];

// User Counts
$stats['students'] = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$stats['teachers'] = $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
$stats['active_students'] = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'")->fetch_assoc()['count'];

// Academic Data
$stats['enrollments'] = $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'];
$stats['active_enrollments'] = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'enrolled'")->fetch_assoc()['count'];
$stats['notes'] = $conn->query("SELECT COUNT(*) as count FROM notes")->fetch_assoc()['count'];
$stats['solutions'] = $conn->query("SELECT COUNT(*) as count FROM question_solutions")->fetch_assoc()['count'];

// Get students by program (for chart)
$program_query = "SELECT p.program_name, COUNT(s.student_id) as student_count 
                  FROM programs p 
                  LEFT JOIN students s ON p.program_id = s.program_id 
                  GROUP BY p.program_id, p.program_name 
                  ORDER BY student_count DESC 
                  LIMIT 10";
$program_result = $conn->query($program_query);
$program_data = [];
while ($row = $program_result->fetch_assoc()) {
    $program_data[] = $row;
}

// Get recent students (last 10)
$recent_students_query = "SELECT s.student_id, s.full_name, p.program_name, s.created_at 
                          FROM students s 
                          JOIN programs p ON s.program_id = p.program_id 
                          ORDER BY s.created_at DESC 
                          LIMIT 10";
$recent_students = $conn->query($recent_students_query);

// Get top students by points
$top_students_query = "SELECT student_id, full_name, total_points, current_cgpa 
                       FROM students 
                       WHERE total_points > 0 
                       ORDER BY total_points DESC 
                       LIMIT 10";
$top_students = $conn->query($top_students_query);

// Get enrollment trends (enrollments per trimester)
$enrollment_trends_query = "SELECT t.trimester_name, COUNT(e.enrollment_id) as enrollment_count 
                            FROM trimesters t 
                            LEFT JOIN enrollments e ON t.trimester_id = e.trimester_id 
                            GROUP BY t.trimester_id, t.trimester_name 
                            ORDER BY t.year DESC, t.trimester_code DESC 
                            LIMIT 6";
$enrollment_trends = $conn->query($enrollment_trends_query);
$trend_data = [];
while ($row = $enrollment_trends->fetch_assoc()) {
    $trend_data[] = $row;
}

// System health check
$health_checks = [
    'required_data' => ($stats['departments'] > 0 && $stats['programs'] > 0 && $stats['trimesters'] > 0),
    'current_trimester' => $conn->query("SELECT COUNT(*) as count FROM trimesters WHERE is_current = TRUE")->fetch_assoc()['count'] > 0,
    'has_students' => $stats['students'] > 0,
    'has_courses' => $stats['courses'] > 0
];

$system_health_score = array_sum($health_checks) * 25; // Each check is 25%
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UIU Smart Campus</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="dashboard-container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-chart-line"></i> Dashboard Overview</h1>
                    <p class="subtitle">Complete system analytics and monitoring</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-outline" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>

            <!-- System Health -->
            <div class="health-banner health-<?php echo $system_health_score >= 75 ? 'good' : ($system_health_score >= 50 ? 'warning' : 'critical'); ?>">
                <div class="health-indicator">
                    <div class="health-circle">
                        <svg viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="45" class="health-bg"></circle>
                            <circle cx="50" cy="50" r="45" class="health-progress" 
                                    style="stroke-dashoffset: <?php echo 283 - (283 * $system_health_score / 100); ?>"></circle>
                        </svg>
                        <span class="health-score"><?php echo $system_health_score; ?>%</span>
                    </div>
                    <div class="health-info">
                        <h3>System Health</h3>
                        <p><?php echo $system_health_score >= 75 ? 'All systems operational' : 'Attention required'; ?></p>
                    </div>
                </div>
                <div class="health-checks">
                    <div class="health-check <?php echo $health_checks['required_data'] ? 'check-pass' : 'check-fail'; ?>">
                        <i class="fas <?php echo $health_checks['required_data'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                        <span>Reference Data</span>
                    </div>
                    <div class="health-check <?php echo $health_checks['current_trimester'] ? 'check-pass' : 'check-fail'; ?>">
                        <i class="fas <?php echo $health_checks['current_trimester'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                        <span>Current Trimester</span>
                    </div>
                    <div class="health-check <?php echo $health_checks['has_students'] ? 'check-pass' : 'check-fail'; ?>">
                        <i class="fas <?php echo $health_checks['has_students'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                        <span>Student Data</span>
                    </div>
                    <div class="health-check <?php echo $health_checks['has_courses'] ? 'check-pass' : 'check-fail'; ?>">
                        <i class="fas <?php echo $health_checks['has_courses'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                        <span>Course Data</span>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card card-blue">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['students']); ?></h3>
                        <p>Total Students</p>
                        <span class="stat-badge"><?php echo $stats['active_students']; ?> Active</span>
                    </div>
                </div>

                <div class="stat-card card-green">
                    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['teachers']); ?></h3>
                        <p>Total Teachers</p>
                        <span class="stat-badge">Faculty Members</span>
                    </div>
                </div>

                <div class="stat-card card-purple">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['courses']); ?></h3>
                        <p>Total Courses</p>
                        <span class="stat-badge"><?php echo $stats['enrollments']; ?> Enrollments</span>
                    </div>
                </div>

                <div class="stat-card card-orange">
                    <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['programs']); ?></h3>
                        <p>Academic Programs</p>
                        <span class="stat-badge"><?php echo $stats['departments']; ?> Departments</span>
                    </div>
                </div>

                <div class="stat-card card-pink">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['notes']); ?></h3>
                        <p>Uploaded Notes</p>
                        <span class="stat-badge">Student Contributions</span>
                    </div>
                </div>

                <div class="stat-card card-teal">
                    <div class="stat-icon"><i class="fas fa-question-circle"></i></div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['solutions']); ?></h3>
                        <p>Question Solutions</p>
                        <span class="stat-badge">Study Resources</span>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> Students by Program</h3>
                        <button class="btn-icon" title="Download Data"><i class="fas fa-download"></i></button>
                    </div>
                    <canvas id="programChart"></canvas>
                </div>

                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Enrollment Trends</h3>
                        <button class="btn-icon" title="Download Data"><i class="fas fa-download"></i></button>
                    </div>
                    <canvas id="enrollmentChart"></canvas>
                </div>
            </div>

            <!-- Data Tables Row -->
            <div class="tables-grid">
                <!-- Recent Students -->
                <div class="table-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Recent Students</h3>
                        <a href="manage.php?table=students" class="btn btn-sm">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Program</th>
                                    <th>Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_students->num_rows > 0): ?>
                                    <?php while($student = $recent_students->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                            <td><span class="badge badge-info"><?php echo htmlspecialchars($student['program_name']); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center">No students found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Students -->
                <div class="table-card">
                    <div class="card-header">
                        <h3><i class="fas fa-trophy"></i> Top Contributors</h3>
                        <a href="leaderboard.php" class="btn btn-sm">Full Leaderboard</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Points</th>
                                    <th>CGPA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($top_students->num_rows > 0): ?>
                                    <?php $rank = 1; while($student = $top_students->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php if ($rank <= 3): ?>
                                                    <span class="rank-badge rank-<?php echo $rank; ?>">
                                                        <i class="fas fa-medal"></i> <?php echo $rank; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <?php echo $rank; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                            <td><span class="badge badge-success"><?php echo number_format($student['total_points']); ?> pts</span></td>
                                            <td><?php echo number_format($student['current_cgpa'], 2); ?></td>
                                        </tr>
                                    <?php $rank++; endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center">No data available</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="action-buttons">
                    <button class="action-btn" onclick="location.href='manage.php?table=students'">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Student</span>
                    </button>
                    <button class="action-btn" onclick="location.href='manage.php?table=courses'">
                        <i class="fas fa-book-medical"></i>
                        <span>Add Course</span>
                    </button>
                    <button class="action-btn" onclick="location.href='upload_departments.php'">
                        <i class="fas fa-upload"></i>
                        <span>Upload Data</span>
                    </button>
                    <button class="action-btn" onclick="location.href='../check_reference_data.php'" target="_blank">
                        <i class="fas fa-check-circle"></i>
                        <span>Verify Data</span>
                    </button>
                    <button class="action-btn" onclick="location.href='backup.php'">
                        <i class="fas fa-database"></i>
                        <span>Backup DB</span>
                    </button>
                    <button class="action-btn" onclick="location.href='settings.php'">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Program Distribution Chart
        const programCtx = document.getElementById('programChart').getContext('2d');
        const programChart = new Chart(programCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($program_data, 'program_name')); ?>,
                datasets: [{
                    label: 'Students',
                    data: <?php echo json_encode(array_column($program_data, 'student_count')); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Enrollment Trends Chart
        const enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
        const enrollmentChart = new Chart(enrollmentCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse(array_column($trend_data, 'trimester_name'))); ?>,
                datasets: [{
                    label: 'Enrollments',
                    data: <?php echo json_encode(array_reverse(array_column($trend_data, 'enrollment_count'))); ?>,
                    backgroundColor: 'rgba(46, 213, 115, 0.2)',
                    borderColor: 'rgba(46, 213, 115, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>
