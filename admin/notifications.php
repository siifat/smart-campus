<?php
/**
 * Notifications Page
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

// Get all notifications (pending items)
$pending_notes = $conn->query("SELECT n.*, s.student_name, s.student_id_card 
                               FROM notes n 
                               JOIN students s ON n.student_id = s.student_id 
                               WHERE n.status = 'pending' 
                               ORDER BY n.created_at DESC");

$pending_solutions = $conn->query("SELECT qs.*, s.student_name, s.student_id_card 
                                   FROM question_solutions qs 
                                   JOIN students s ON qs.student_id = s.student_id 
                                   WHERE qs.status = 'pending' 
                                   ORDER BY qs.created_at DESC");

// Get recent enrollments
$recent_enrollments = $conn->query("SELECT e.*, s.student_name, c.course_name, c.course_code 
                                    FROM enrollments e 
                                    JOIN students s ON e.student_id = s.student_id 
                                    JOIN courses c ON e.course_id = c.course_id 
                                    ORDER BY e.enrollment_date DESC 
                                    LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/manage-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .notification-header {
            padding: 20px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-header h3 {
            margin: 0;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notification-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            align-items: start;
            gap: 15px;
            transition: background 0.2s;
        }
        .notification-item:hover {
            background: #f8f9fa;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .icon-pending { background: #fff3cd; color: #ffa502; }
        .icon-approved { background: #d4edda; color: #2ed573; }
        .icon-rejected { background: #f8d7da; color: #ff4757; }
        .icon-enrollment { background: #d1ecf1; color: #17a2b8; }
        .notification-content {
            flex: 1;
        }
        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        .notification-meta {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
        }
        .notification-time {
            font-size: 0.8em;
            color: #999;
        }
        .notification-actions {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="dashboard-container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-bell"></i> Notifications</h1>
                    <p class="subtitle">Stay updated with all system activities</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-outline" onclick="markAllAsRead()">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                </div>
            </div>

            <!-- Pending Notes -->
            <div class="notification-card">
                <div class="notification-header">
                    <h3>
                        <i class="fas fa-file-alt"></i>
                        Pending Notes Approval
                    </h3>
                    <span class="badge badge-warning"><?php echo $pending_notes->num_rows; ?> items</span>
                </div>
                <ul class="notification-list">
                    <?php if ($pending_notes->num_rows > 0): ?>
                        <?php while($note = $pending_notes->fetch_assoc()): ?>
                            <li class="notification-item">
                                <div class="notification-icon icon-pending">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">
                                        New note submitted by <?php echo htmlspecialchars($note['student_name']); ?>
                                    </div>
                                    <div class="notification-meta">
                                        Title: <?php echo htmlspecialchars($note['title']); ?>
                                    </div>
                                    <div class="notification-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M d, Y H:i', strtotime($note['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="notification-actions">
                                    <a href="manage.php?table=notes&highlight=<?php echo $note['note_id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="notification-item" style="text-align: center; color: #999;">
                            <div style="width: 100%;">
                                <i class="fas fa-check-circle" style="font-size: 30px; margin-bottom: 10px;"></i>
                                <p>No pending notes</p>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Pending Solutions -->
            <div class="notification-card">
                <div class="notification-header">
                    <h3>
                        <i class="fas fa-question-circle"></i>
                        Pending Solution Approval
                    </h3>
                    <span class="badge badge-warning"><?php echo $pending_solutions->num_rows; ?> items</span>
                </div>
                <ul class="notification-list">
                    <?php if ($pending_solutions->num_rows > 0): ?>
                        <?php while($solution = $pending_solutions->fetch_assoc()): ?>
                            <li class="notification-item">
                                <div class="notification-icon icon-pending">
                                    <i class="fas fa-question-circle"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">
                                        New solution submitted by <?php echo htmlspecialchars($solution['student_name']); ?>
                                    </div>
                                    <div class="notification-meta">
                                        Question: <?php echo htmlspecialchars($solution['question_title']); ?>
                                    </div>
                                    <div class="notification-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M d, Y H:i', strtotime($solution['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="notification-actions">
                                    <a href="manage.php?table=question_solutions&highlight=<?php echo $solution['solution_id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="notification-item" style="text-align: center; color: #999;">
                            <div style="width: 100%;">
                                <i class="fas fa-check-circle" style="font-size: 30px; margin-bottom: 10px;"></i>
                                <p>No pending solutions</p>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Recent Enrollments -->
            <div class="notification-card">
                <div class="notification-header">
                    <h3>
                        <i class="fas fa-user-plus"></i>
                        Recent Enrollments
                    </h3>
                </div>
                <ul class="notification-list">
                    <?php while($enrollment = $recent_enrollments->fetch_assoc()): ?>
                        <li class="notification-item">
                            <div class="notification-icon icon-enrollment">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?php echo htmlspecialchars($enrollment['student_name']); ?> enrolled in 
                                    <?php echo htmlspecialchars($enrollment['course_code']); ?>
                                </div>
                                <div class="notification-meta">
                                    Course: <?php echo htmlspecialchars($enrollment['course_name']); ?>
                                </div>
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?>
                                </div>
                            </div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function markAllAsRead() {
            alert('All notifications marked as read!');
            // Implement actual mark as read functionality
        }
    </script>
</body>
</html>
