<?php
/**
 * Advanced Analytics Dashboard
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

// Get date range
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Student Growth Over Time
$student_growth_query = "SELECT DATE(created_at) as date, COUNT(*) as count 
                         FROM students 
                         WHERE created_at BETWEEN '$date_from' AND '$date_to'
                         GROUP BY DATE(created_at) 
                         ORDER BY date";
$student_growth = $conn->query($student_growth_query);
$growth_data = [];
while ($row = $student_growth->fetch_assoc()) {
    $growth_data[] = $row;
}

// Course Popularity (most enrolled)
$course_popularity_query = "SELECT c.course_code, c.course_name, COUNT(e.enrollment_id) as enrollment_count
                            FROM courses c
                            LEFT JOIN enrollments e ON c.course_id = e.course_id
                            GROUP BY c.course_id, c.course_code, c.course_name
                            ORDER BY enrollment_count DESC
                            LIMIT 10";
$course_popularity = $conn->query($course_popularity_query);

// Department-wise Statistics
$dept_stats_query = "SELECT d.department_name, d.department_code,
                     COUNT(DISTINCT s.student_id) as student_count,
                     COUNT(DISTINCT t.teacher_id) as teacher_count,
                     COUNT(DISTINCT c.course_id) as course_count
                     FROM departments d
                     LEFT JOIN programs p ON d.department_id = p.department_id
                     LEFT JOIN students s ON p.program_id = s.program_id
                     LEFT JOIN teachers t ON d.department_id = t.department_id
                     LEFT JOIN courses c ON d.department_id = c.department_id
                     GROUP BY d.department_id, d.department_name, d.department_code
                     ORDER BY student_count DESC";
$dept_stats = $conn->query($dept_stats_query);

// Teacher Workload
$teacher_workload_query = "SELECT t.full_name, t.initial, 
                           COUNT(DISTINCT e.enrollment_id) as total_students,
                           COUNT(DISTINCT e.course_id) as courses_teaching
                           FROM teachers t
                           LEFT JOIN enrollments e ON t.teacher_id = e.teacher_id
                           GROUP BY t.teacher_id, t.full_name, t.initial
                           HAVING courses_teaching > 0
                           ORDER BY total_students DESC
                           LIMIT 15";
$teacher_workload = $conn->query($teacher_workload_query);

// Enrollment Status Distribution
$enrollment_status_query = "SELECT status, COUNT(*) as count 
                            FROM enrollments 
                            GROUP BY status";
$enrollment_status = $conn->query($enrollment_status_query);
$status_data = [];
while ($row = $enrollment_status->fetch_assoc()) {
    $status_data[] = $row;
}

// Student Status Distribution
$student_status_query = "SELECT status, COUNT(*) as count 
                         FROM students 
                         GROUP BY status";
$student_status = $conn->query($student_status_query);
$student_status_data = [];
while ($row = $student_status->fetch_assoc()) {
    $student_status_data[] = $row;
}

// Content Statistics
$notes_by_status = $conn->query("SELECT status, COUNT(*) as count FROM notes GROUP BY status");
$solutions_by_status = $conn->query("SELECT status, COUNT(*) as count FROM question_solutions GROUP BY status");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/manage-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="dashboard-container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-chart-pie"></i> Advanced Analytics</h1>
                    <p class="subtitle">Comprehensive system analytics and insights</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-outline" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button class="btn btn-primary" onclick="exportAllData()">
                        <i class="fas fa-download"></i> Export All
                    </button>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="toolbar">
                <form method="GET" style="display: flex; gap: 15px; align-items: center;">
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> From:</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                               style="padding: 8px; border: 2px solid #e1e4e8; border-radius: 8px;">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> To:</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" 
                               style="padding: 8px; border: 2px solid #e1e4e8; border-radius: 8px;">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                </form>
            </div>

            <!-- Charts Grid -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Student Growth Over Time</h3>
                    </div>
                    <canvas id="studentGrowthChart"></canvas>
                </div>

                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> Course Popularity</h3>
                    </div>
                    <canvas id="coursePopularityChart"></canvas>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> Enrollment Status</h3>
                    </div>
                    <canvas id="enrollmentStatusChart"></canvas>
                </div>

                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-doughnut"></i> Student Status</h3>
                    </div>
                    <canvas id="studentStatusChart"></canvas>
                </div>
            </div>

            <!-- Department Statistics -->
            <div class="table-card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Department Statistics</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Code</th>
                                <th>Students</th>
                                <th>Teachers</th>
                                <th>Courses</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($dept = $dept_stats->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                    <td><span class="badge badge-info"><?php echo $dept['department_code']; ?></span></td>
                                    <td><?php echo number_format($dept['student_count']); ?></td>
                                    <td><?php echo number_format($dept['teacher_count']); ?></td>
                                    <td><?php echo number_format($dept['course_count']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Teacher Workload -->
            <div class="table-card">
                <div class="card-header">
                    <h3><i class="fas fa-chalkboard-teacher"></i> Teacher Workload</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Initial</th>
                                <th>Total Students</th>
                                <th>Courses Teaching</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($teacher = $teacher_workload->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                    <td><span class="badge badge-success"><?php echo $teacher['initial']; ?></span></td>
                                    <td><?php echo number_format($teacher['total_students']); ?></td>
                                    <td><?php echo $teacher['courses_teaching']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Student Growth Chart
        const growthCtx = document.getElementById('studentGrowthChart').getContext('2d');
        new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($growth_data, 'date')); ?>,
                datasets: [{
                    label: 'New Students',
                    data: <?php echo json_encode(array_column($growth_data, 'count')); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        // Course Popularity Chart
        const popularityData = [];
        const popularityLabels = [];
        <?php 
        $course_popularity->data_seek(0);
        while($course = $course_popularity->fetch_assoc()): 
        ?>
        popularityLabels.push('<?php echo addslashes($course['course_code']); ?>');
        popularityData.push(<?php echo $course['enrollment_count']; ?>);
        <?php endwhile; ?>

        const popularityCtx = document.getElementById('coursePopularityChart').getContext('2d');
        new Chart(popularityCtx, {
            type: 'bar',
            data: {
                labels: popularityLabels,
                datasets: [{
                    label: 'Enrollments',
                    data: popularityData,
                    backgroundColor: 'rgba(46, 213, 115, 0.8)',
                    borderColor: 'rgba(46, 213, 115, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        // Enrollment Status Chart
        const enrollmentCtx = document.getElementById('enrollmentStatusChart').getContext('2d');
        new Chart(enrollmentCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($status_data, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($status_data, 'count')); ?>,
                    backgroundColor: ['#667eea', '#2ed573', '#ffa502', '#ff4757']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Student Status Chart
        const studentStatusCtx = document.getElementById('studentStatusChart').getContext('2d');
        new Chart(studentStatusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($student_status_data, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($student_status_data, 'count')); ?>,
                    backgroundColor: ['#667eea', '#ff4757', '#2ed573', '#ffa502']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        function exportAllData() {
            window.location.href = 'export.php?type=analytics';
        }
    </script>
</body>
</html>
