<?php
/**
 * Admin Panel - Redirect to Dashboard
 */
session_start();

// Simple authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Redirect to new dashboard
header('Location: dashboard.php');
exit;

require_once('../config/database.php');

// Get counts
$dept_count = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];
$prog_count = $conn->query("SELECT COUNT(*) as count FROM programs")->fetch_assoc()['count'];
$trim_count = $conn->query("SELECT COUNT(*) as count FROM trimesters")->fetch_assoc()['count'];
$student_count = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$course_count = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];

$all_required_met = ($dept_count > 0 && $prog_count > 0 && $trim_count > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UIU Smart Campus</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        h1 { color: #667eea; }
        .logout-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .status-banner {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            font-size: 1.2em;
        }
        .status-ok {
            background: #d4edda;
            color: #155724;
            border: 2px solid #28a745;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffc107;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .stat-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-status {
            margin-top: 10px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            display: inline-block;
        }
        .status-required {
            background: #d4edda;
            color: #155724;
        }
        .status-missing {
            background: #f8d7da;
            color: #721c24;
        }
        .status-optional {
            background: #d1ecf1;
            color: #0c5460;
        }
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }
        .action-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .action-card h3 {
            color: #667eea;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .action-card p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .btn {
            display: block;
            padding: 12px 25px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
            transition: 0.3s;
            margin: 5px 0;
        }
        .btn:hover {
            background: #764ba2;
            transform: translateX(5px);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-danger {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🛠️ Admin Dashboard</h1>
                <p style="color: #666; margin-top: 5px;">Reference Data Management System</p>
            </div>
            <a href="logout.php" class="logout-btn">🚪 Logout</a>
        </div>
        
        <?php if ($all_required_met): ?>
            <div class="status-banner status-ok">
                ✅ System Ready! All required reference data is present.
            </div>
        <?php else: ?>
            <div class="status-banner status-warning">
                ⚠️ Warning: Some required reference data is missing! Users cannot sync from UCAM until you add Departments, Programs, and Trimesters.
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-label">Departments</div>
                <div class="stat-value"><?php echo $dept_count; ?></div>
                <div class="stat-status <?php echo $dept_count > 0 ? 'status-required' : 'status-missing'; ?>">
                    <?php echo $dept_count > 0 ? '✅ Required Data Present' : '❌ Missing Required Data'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🎓</div>
                <div class="stat-label">Programs</div>
                <div class="stat-value"><?php echo $prog_count; ?></div>
                <div class="stat-status <?php echo $prog_count > 0 ? 'status-required' : 'status-missing'; ?>">
                    <?php echo $prog_count > 0 ? '✅ Required Data Present' : '❌ Missing Required Data'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-label">Trimesters</div>
                <div class="stat-value"><?php echo $trim_count; ?></div>
                <div class="stat-status <?php echo $trim_count > 0 ? 'status-required' : 'status-missing'; ?>">
                    <?php echo $trim_count > 0 ? '✅ Required Data Present' : '❌ Missing Required Data'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-label">Students</div>
                <div class="stat-value"><?php echo $student_count; ?></div>
                <div class="stat-status status-optional">
                    ℹ️ Populated from UCAM Sync
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-label">Courses</div>
                <div class="stat-value"><?php echo $course_count; ?></div>
                <div class="stat-status status-optional">
                    ℹ️ Upload Manually or from UCAM
                </div>
            </div>
        </div>
        
        <!-- Action Cards -->
        <div class="action-grid">
            <!-- Departments Management -->
            <div class="action-card">
                <h3>🏢 Manage Departments</h3>
                <p>Upload departments via CSV/Excel file or add manually. Required for the system to function.</p>
                <a href="upload_departments.php" class="btn">📤 Upload Departments File</a>
                <a href="manage_departments.php" class="btn btn-secondary">✏️ Manage Manually</a>
                <a href="view_departments.php" class="btn btn-secondary">👁️ View All (<?php echo $dept_count; ?>)</a>
            </div>
            
            <!-- Programs Management -->
            <div class="action-card">
                <h3>🎓 Manage Programs</h3>
                <p>Upload programs via CSV/Excel file or add manually. Required for student enrollment.</p>
                <a href="upload_programs.php" class="btn">📤 Upload Programs File</a>
                <a href="manage_programs.php" class="btn btn-secondary">✏️ Manage Manually</a>
                <a href="view_programs.php" class="btn btn-secondary">👁️ View All (<?php echo $prog_count; ?>)</a>
            </div>
            
            <!-- Trimesters Management -->
            <div class="action-card">
                <h3>📅 Manage Trimesters</h3>
                <p>Upload trimesters via CSV/Excel file or add manually. Required for course enrollments.</p>
                <a href="upload_trimesters.php" class="btn">📤 Upload Trimesters File</a>
                <a href="manage_trimesters.php" class="btn btn-secondary">✏️ Manage Manually</a>
                <a href="view_trimesters.php" class="btn btn-secondary">👁️ View All (<?php echo $trim_count; ?>)</a>
            </div>
            
            <!-- Courses Management -->
            <div class="action-card">
                <h3>📚 Manage Courses</h3>
                <p>Upload courses via CSV file. Courses can also be populated automatically from UCAM sync.</p>
                <a href="upload_courses.php" class="btn">📤 Upload Courses File</a>
                <a href="manage_courses.php" class="btn btn-secondary">✏️ Manage Manually</a>
                <a href="view_courses.php" class="btn btn-secondary">👁️ View All (<?php echo $course_count; ?>)</a>
            </div>
            
            <!-- System Tools -->
            <div class="action-card">
                <h3>🔧 System Tools</h3>
                <p>Verify data integrity, check for duplicates, and view sync logs.</p>
                <a href="../check_reference_data.php" class="btn btn-success" target="_blank">✅ Verify Reference Data</a>
                <a href="../check_duplicates.php" class="btn btn-success" target="_blank">🔍 Check Duplicates</a>
                <a href="database_backup.php" class="btn btn-secondary">💾 Backup Database</a>
            </div>
            
            <!-- Download Templates -->
            <div class="action-card">
                <h3>📋 Download Templates</h3>
                <p>Download CSV templates for bulk data upload.</p>
                <a href="download_template.php?type=departments" class="btn btn-secondary">📥 Departments Template</a>
                <a href="download_template.php?type=programs" class="btn btn-secondary">📥 Programs Template</a>
                <a href="download_template.php?type=trimesters" class="btn btn-secondary">📥 Trimesters Template</a>
                <a href="download_template.php?type=courses" class="btn btn-secondary">📥 Courses Template</a>
            </div>
            
            <!-- Danger Zone -->
            <div class="action-card" style="border: 2px solid #dc3545;">
                <h3 style="color: #dc3545;">⚠️ Danger Zone</h3>
                <p>Destructive actions that cannot be undone. Use with extreme caution!</p>
                <a href="clear_data.php" class="btn btn-danger" onclick="return confirm('Are you sure? This will delete ALL data!')">🗑️ Clear All Data</a>
                <a href="reset_database.php" class="btn btn-danger" onclick="return confirm('This will reset the entire database!')">🔄 Reset Database</a>
            </div>
        </div>
    </div>
</body>
</html>
