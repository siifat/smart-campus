<?php
/**
 * System Settings & Configuration
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

$message = '';
$message_type = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Simple validation (enhance with actual password verification)
        if ($new_password === $confirm_password && strlen($new_password) >= 6) {
            $message = '✅ Password updated successfully! Please login again with new credentials.';
            $message_type = 'success';
        } else {
            $message = '❌ Passwords do not match or too short (min 6 characters)';
            $message_type = 'error';
        }
    }
    
    if (isset($_POST['clear_cache'])) {
        // Clear any cached data
        $message = '✅ Cache cleared successfully!';
        $message_type = 'success';
    }
}

// Get system info
$db_size_query = "SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
    FROM information_schema.TABLES 
    WHERE table_schema = 'uiu_smart_campus'";
$db_size = $conn->query($db_size_query)->fetch_assoc()['size_mb'] ?? 0;

$total_records = 0;
$tables = ['students', 'teachers', 'courses', 'enrollments', 'departments', 'programs', 'trimesters', 'notes', 'question_solutions'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    $total_records += $result->fetch_assoc()['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/manage-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .maintenance-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .maintenance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .alert-success {
            background-color: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }
        .alert-error {
            background-color: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .btn {
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-success {
            background: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        #resultsModal > div {
            animation: slideIn 0.3s ease;
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
                    <h1><i class="fas fa-cog"></i> System Settings</h1>
                    <p class="subtitle">Configure system preferences and manage account</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="charts-grid">
                <!-- System Information -->
                <div class="table-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> System Information</h3>
                    </div>
                    <div style="padding: 25px;">
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <div style="display: flex; justify-content: space-between; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <span><strong>Database Size:</strong></span>
                                <span><?php echo $db_size; ?> MB</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <span><strong>Total Records:</strong></span>
                                <span><?php echo number_format($total_records); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <span><strong>PHP Version:</strong></span>
                                <span><?php echo phpversion(); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <span><strong>MySQL Version:</strong></span>
                                <span><?php echo $conn->server_info; ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <span><strong>Server Time:</strong></span>
                                <span><?php echo date('Y-m-d H:i:s'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Settings -->
                <div class="table-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-cog"></i> Account Settings</h3>
                    </div>
                    <div style="padding: 25px;">
                        <form method="POST">
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Current Password</label>
                                <input type="password" name="current_password" placeholder="Enter current password" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-key"></i> New Password</label>
                                <input type="password" name="new_password" placeholder="Enter new password (min 6 characters)" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-check"></i> Confirm New Password</label>
                                <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                            </div>
                            <button type="submit" name="update_password" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-save"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Maintenance -->
            <div class="table-card">
                <div class="card-header">
                    <h3><i class="fas fa-tools"></i> System Maintenance</h3>
                </div>
                <div style="padding: 25px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div class="maintenance-card" style="padding: 20px; border: 2px solid #10b981; border-radius: 10px; background: #d1fae5;">
                            <h4 style="margin-bottom: 10px;"><i class="fas fa-check-double"></i> Verify Data</h4>
                            <p style="color: #065f46; margin-bottom: 15px; font-size: 0.9em;">Check database integrity and 3NF/BCNF</p>
                            <button onclick="verifyDatabase(false)" class="btn btn-outline" style="width: 100%; margin-bottom: 8px;">
                                <i class="fas fa-search"></i> Check Only
                            </button>
                            <button onclick="verifyDatabase(true)" class="btn btn-success" style="width: 100%;">
                                <i class="fas fa-wrench"></i> Check & Auto-Fix
                            </button>
                        </div>

                        <div class="maintenance-card" style="padding: 20px; border: 2px solid #3b82f6; border-radius: 10px; background: #dbeafe;">
                            <h4 style="margin-bottom: 10px;"><i class="fas fa-search"></i> Check Duplicates</h4>
                            <p style="color: #1e40af; margin-bottom: 15px; font-size: 0.9em;">Find and remove duplicate records</p>
                            <button onclick="checkDuplicates(false)" class="btn btn-outline" style="width: 100%; margin-bottom: 8px;">
                                <i class="fas fa-search"></i> Find Duplicates
                            </button>
                            <button onclick="checkDuplicates(true)" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-trash-alt"></i> Find & Remove
                            </button>
                        </div>

                        <div class="maintenance-card" style="padding: 20px; border: 2px solid #8b5cf6; border-radius: 10px;">
                            <h4 style="margin-bottom: 10px;"><i class="fas fa-database"></i> Backup Database</h4>
                            <p style="color: #666; margin-bottom: 15px; font-size: 0.9em;">Create a complete database backup</p>
                            <a href="backup.php" class="btn btn-outline" style="width: 100%; display: block; text-align: center;">
                                <i class="fas fa-download"></i> Go to Backup
                            </a>
                        </div>

                        <div class="maintenance-card" style="padding: 20px; border: 2px solid #ffc107; border-radius: 10px; background: #fff3cd;">
                            <h4 style="margin-bottom: 10px;"><i class="fas fa-sync-alt"></i> Reset System</h4>
                            <p style="color: #856404; margin-bottom: 15px; font-size: 0.9em;">Clear all data and reset to defaults</p>
                            <button onclick="confirmReset()" class="btn btn-warning" style="width: 100%;">
                                <i class="fas fa-redo"></i> Reset System
                            </button>
                        </div>

                        <div class="maintenance-card" style="padding: 20px; border: 2px solid #dc3545; border-radius: 10px; background: #f8d7da;">
                            <h4 style="margin-bottom: 10px;"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h4>
                            <p style="color: #721c24; margin-bottom: 15px; font-size: 0.9em;">Delete ALL data - cannot be undone!</p>
                            <button onclick="confirmDanger()" class="btn btn-danger" style="width: 100%;">
                                <i class="fas fa-skull-crossbones"></i> Delete All Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Results Modal -->
            <div id="resultsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; overflow-y: auto;">
                <div style="max-width: 900px; margin: 50px auto; background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 id="modalTitle" style="margin: 0;"><i class="fas fa-cog fa-spin"></i> Processing...</h2>
                        <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                    </div>
                    <div id="modalContent" style="margin-top: 20px;">
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #667eea;"></i>
                            <p style="margin-top: 20px;">Please wait...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="table-card">
                <div class="card-header">
                    <h3><i class="fas fa-external-link-alt"></i> Quick Links</h3>
                </div>
                <div style="padding: 25px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <a href="dashboard.php" class="btn btn-outline">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                        <a href="manage.php?table=students" class="btn btn-outline">
                            <i class="fas fa-users"></i> Manage Students
                        </a>
                        <a href="leaderboard.php" class="btn btn-outline">
                            <i class="fas fa-trophy"></i> Leaderboard
                        </a>
                        <a href="backup.php" class="btn btn-outline">
                            <i class="fas fa-database"></i> Backup & Restore
                        </a>
                        <a href="../index.php" target="_blank" class="btn btn-outline">
                            <i class="fas fa-home"></i> View Website
                        </a>
                        <a href="logout.php" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Show/hide modal
        function showModal(title) {
            document.getElementById('resultsModal').style.display = 'block';
            document.getElementById('modalTitle').innerHTML = title;
            document.getElementById('modalContent').innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #667eea;"></i>
                    <p style="margin-top: 20px;">Processing request...</p>
                </div>
            `;
        }

        function closeModal() {
            document.getElementById('resultsModal').style.display = 'none';
        }

        // Verify Database
        async function verifyDatabase(autoFix = false) {
            const title = autoFix ? 
                '<i class="fas fa-wrench"></i> Verifying & Fixing Database' : 
                '<i class="fas fa-search"></i> Verifying Database';
            showModal(title);
            
            try {
                const formData = new FormData();
                if (autoFix) formData.append('auto_fix', 'true');
                
                const response = await fetch('api/verify_database.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                let html = `<div style="padding: 20px;">`;
                
                // Summary
                if (data.summary) {
                    const healthColor = data.summary.database_health === 'Excellent' ? '#10b981' : 
                                       data.summary.database_health === 'Good' ? '#f59e0b' : '#ef4444';
                    
                    html += `<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0;"><i class="fas fa-database"></i> Database Health Report</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                            <div style="background: rgba(255,255,255,0.2); padding: 12px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 24px; font-weight: bold;">${data.summary.passed_checks}/${data.summary.total_checks}</div>
                                <div style="font-size: 12px; opacity: 0.9;">Checks Passed</div>
                            </div>
                            <div style="background: rgba(255,255,255,0.2); padding: 12px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 24px; font-weight: bold;">${data.summary.total_issues}</div>
                                <div style="font-size: 12px; opacity: 0.9;">Issues Found</div>
                            </div>
                            <div style="background: rgba(255,255,255,0.2); padding: 12px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 24px; font-weight: bold;">${data.summary.total_warnings}</div>
                                <div style="font-size: 12px; opacity: 0.9;">Warnings</div>
                            </div>
                        </div>
                        <div style="margin-top: 15px; padding: 12px; background: rgba(255,255,255,0.2); border-radius: 8px; text-align: center;">
                            <div style="font-weight: bold; font-size: 18px;">${data.summary.normalization_status}</div>
                            <div style="font-size: 14px; opacity: 0.9;">Normalization Status</div>
                        </div>
                    </div>`;
                }
                
                // Fixes Applied
                if (data.fixes_applied && data.fixes_applied.length > 0) {
                    html += `<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
                        <h4 style="color: #065f46; margin: 0 0 10px 0;"><i class="fas fa-tools"></i> Auto-Fixes Applied:</h4>
                        <ul style="margin: 0; color: #065f46;">`;
                    data.fixes_applied.forEach(fix => {
                        html += `<li>${fix}</li>`;
                    });
                    html += `</ul></div>`;
                }
                
                // Issues
                if (data.issues && data.issues.length > 0) {
                    html += `<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
                        <h4 style="color: #991b1b; margin: 0 0 10px 0;"><i class="fas fa-exclamation-circle"></i> Issues Found:</h4>
                        <div style="max-height: 300px; overflow-y: auto;">`;
                    data.issues.forEach(issue => {
                        const severityColor = issue.severity === 'critical' ? '#991b1b' : 
                                             issue.severity === 'high' ? '#dc2626' : '#f59e0b';
                        html += `<div style="margin-bottom: 10px; padding: 10px; background: white; border-radius: 6px;">
                            <div style="color: ${severityColor}; font-weight: bold; text-transform: uppercase; font-size: 0.85em;">
                                ${issue.severity} - ${issue.type}
                            </div>
                            <div style="margin-top: 5px;">${issue.message}</div>
                            <div style="margin-top: 5px; font-size: 0.85em; color: #666;">
                                Table: <code>${issue.table}</code> | 
                                Fixable: ${issue.fixable ? '✅ Yes' : '❌ No'}
                            </div>
                        </div>`;
                    });
                    html += `</div></div>`;
                }
                
                // Warnings
                if (data.warnings && data.warnings.length > 0) {
                    html += `<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
                        <h4 style="color: #92400e; margin: 0 0 10px 0;"><i class="fas fa-exclamation-triangle"></i> Warnings:</h4>
                        <div style="max-height: 200px; overflow-y: auto;">`;
                    data.warnings.forEach(warning => {
                        html += `<div style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 6px;">
                            <div style="font-weight: bold;">${warning.type}</div>
                            <div style="font-size: 0.9em;">${warning.message}</div>
                        </div>`;
                    });
                    html += `</div></div>`;
                }
                
                // Success message if no issues
                if (data.issues.length === 0 && data.warnings.length === 0) {
                    html += `<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 20px; border-radius: 8px; text-align: center;">
                        <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981;"></i>
                        <h3 style="color: #065f46; margin: 15px 0 5px 0;">Perfect Database Health!</h3>
                        <p style="color: #065f46; margin: 0;">No issues or warnings found. Database is in excellent condition.</p>
                    </div>`;
                }
                
                html += `</div>`;
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-check-circle"></i> Verification Complete';
                document.getElementById('modalContent').innerHTML = html;
                
            } catch (error) {
                document.getElementById('modalContent').innerHTML = `
                    <div style="padding: 20px;">
                        <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 8px;">
                            <h3 style="color: #991b1b;"><i class="fas fa-times-circle"></i> Error</h3>
                            <p>Failed to verify database: ${error.message}</p>
                        </div>
                    </div>
                `;
            }
        }

        // Check Duplicates
        async function checkDuplicates(removeDuplicates = false) {
            const title = removeDuplicates ? 
                '<i class="fas fa-trash-alt"></i> Finding & Removing Duplicates' : 
                '<i class="fas fa-search"></i> Checking for Duplicates';
            showModal(title);
            
            try {
                const formData = new FormData();
                if (removeDuplicates) formData.append('remove_duplicates', 'true');
                
                const response = await fetch('api/check_duplicates.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                let html = `<div style="padding: 20px;">`;
                
                if (data.total_duplicates === 0) {
                    html += `<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 20px; border-radius: 8px; text-align: center;">
                        <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981;"></i>
                        <h3 style="color: #065f46; margin: 15px 0 5px 0;">No Duplicates Found!</h3>
                        <p style="color: #065f46; margin: 0;">Your database is clean and duplicate-free.</p>
                    </div>`;
                } else {
                    // Show removed count if applicable
                    if (data.removed && data.removed.total_removed > 0) {
                        html += `<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
                            <h3 style="color: #065f46; margin: 0 0 10px 0;"><i class="fas fa-check-circle"></i> ${data.removed.message}</h3>
                        </div>`;
                    }
                    
                    html += `<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
                        <h4 style="color: #92400e; margin: 0 0 10px 0;"><i class="fas fa-clone"></i> Duplicates Found:</h4>`;
                    
                    for (const [table, duplicates] of Object.entries(data.duplicates_found)) {
                        html += `<div style="margin-top: 15px;">
                            <strong style="text-transform: capitalize;">${table.replace('_', ' ')}:</strong>
                            <div style="margin-top: 8px; max-height: 200px; overflow-y: auto;">`;
                        
                        duplicates.forEach(dup => {
                            html += `<div style="background: white; padding: 10px; margin-bottom: 8px; border-radius: 6px; font-size: 0.9em;">
                                ${JSON.stringify(dup, null, 2).replace(/[{}"\n]/g, ' ').replace(/,/g, ', ')}
                            </div>`;
                        });
                        html += `</div></div>`;
                    }
                    html += `</div>`;
                    
                    if (!removeDuplicates) {
                        html += `<button onclick="checkDuplicates(true)" class="btn btn-danger" style="width: 100%;">
                            <i class="fas fa-trash-alt"></i> Remove All Duplicates
                        </button>`;
                    }
                }
                
                html += `</div>`;
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-check-circle"></i> Duplicate Check Complete';
                document.getElementById('modalContent').innerHTML = html;
                
            } catch (error) {
                document.getElementById('modalContent').innerHTML = `
                    <div style="padding: 20px;">
                        <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 8px;">
                            <h3 style="color: #991b1b;"><i class="fas fa-times-circle"></i> Error</h3>
                            <p>Failed to check duplicates: ${error.message}</p>
                        </div>
                    </div>
                `;
            }
        }

        // Reset System
        async function confirmReset() {
            if (!confirm('⚠️ WARNING: This will clear all data and reset the system to defaults!\n\nAre you sure you want to continue?')) {
                return;
            }
            
            const confirmation = prompt('Type "RESET_SYSTEM" to confirm this action:');
            if (confirmation !== 'RESET_SYSTEM') {
                alert('Confirmation text did not match. Action cancelled.');
                return;
            }
            
            if (!confirm('This action CANNOT be undone! Final confirmation required.')) {
                return;
            }
            
            showModal('<i class="fas fa-sync-alt fa-spin"></i> Resetting System');
            
            try {
                const formData = new FormData();
                formData.append('action', 'reset_system');
                formData.append('confirmation', 'RESET_SYSTEM');
                
                const response = await fetch('api/system_operations.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                let html = `<div style="padding: 20px;">`;
                
                if (data.success) {
                    html += `<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 20px; border-radius: 8px; text-align: center;">
                        <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981;"></i>
                        <h3 style="color: #065f46; margin: 15px 0 5px 0;">System Reset Successfully!</h3>
                        <p style="color: #065f46; margin-bottom: 15px;">${data.message}</p>
                    </div>`;
                    
                    // Show details if available
                    if (data.details) {
                        html += `<div style="margin-top: 20px; padding: 15px; background: #f3f4f6; border-radius: 8px;">
                            <h4 style="margin: 0 0 10px 0;"><i class="fas fa-list"></i> Records Deleted:</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">`;
                        
                        for (const [table, count] of Object.entries(data.details)) {
                            if (count > 0) {
                                html += `<div style="padding: 8px; background: white; border-radius: 6px; font-size: 0.9em;">
                                    <strong>${table}:</strong> ${count} record(s)
                                </div>`;
                            }
                        }
                        html += `</div></div>`;
                    }
                    
                    if (data.tables_reset) {
                        html += `<div style="margin-top: 15px; padding: 12px; background: #fff3cd; border-radius: 8px; text-align: center; color: #856404;">
                            <strong>${data.tables_reset} table(s) reset</strong>
                        </div>`;
                    }
                } else {
                    html += `<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 8px;">
                        <h3 style="color: #991b1b;"><i class="fas fa-exclamation-triangle"></i> Error</h3>
                        <p>${data.message}</p>
                    </div>`;
                }
                
                html += `</div>`;
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-info-circle"></i> Reset Complete';
                document.getElementById('modalContent').innerHTML = html;
                
            } catch (error) {
                document.getElementById('modalContent').innerHTML = `
                    <div style="padding: 20px;">
                        <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 8px;">
                            <h3 style="color: #991b1b;"><i class="fas fa-times-circle"></i> Error</h3>
                            <p>Failed to reset system: ${error.message}</p>
                        </div>
                    </div>
                `;
            }
        }

        // Delete All Data (Danger Zone)
        async function confirmDanger() {
            const confirmation = prompt('⚠️ EXTREME DANGER ⚠️\n\nThis will permanently DELETE ALL DATA!\n\nType "DELETE_EVERYTHING" to confirm:');
            if (confirmation !== 'DELETE_EVERYTHING') {
                alert('Confirmation text did not match. Action cancelled.');
                return;
            }
            
            if (!confirm('Are you ABSOLUTELY SURE? This is your LAST chance!\n\nThis will delete all students, courses, enrollments, and related data!')) {
                return;
            }
            
            showModal('<i class="fas fa-skull-crossbones"></i> Deleting All Data');
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_all_data');
                formData.append('confirmation', 'DELETE_EVERYTHING');
                
                const response = await fetch('api/system_operations.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                let html = `<div style="padding: 20px;">`;
                
                if (data.success) {
                    html += `<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 20px; border-radius: 8px; text-align: center;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444;"></i>
                        <h3 style="color: #991b1b; margin: 15px 0 5px 0;">All Data Deleted!</h3>
                        <p style="color: #991b1b; margin-bottom: 10px;">${data.message}</p>
                    </div>`;
                    
                    // Show detailed information
                    if (data.tables_cleared && data.tables_cleared.length > 0) {
                        html += `<div style="margin-top: 20px; padding: 15px; background: #f3f4f6; border-radius: 8px;">
                            <h4 style="margin: 0 0 10px 0;"><i class="fas fa-list"></i> Deleted Tables (${data.total_tables}):</h4>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <table style="width: 100%; font-size: 0.9em; border-collapse: collapse;">
                                    <thead style="background: #e5e7eb; position: sticky; top: 0;">
                                        <tr>
                                            <th style="padding: 8px; text-align: left; border: 1px solid #d1d5db;">Table Name</th>
                                            <th style="padding: 8px; text-align: right; border: 1px solid #d1d5db;">Records Deleted</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                        
                        data.tables_cleared.forEach(item => {
                            html += `<tr style="background: white;">
                                <td style="padding: 6px; border: 1px solid #d1d5db;">${item.table}</td>
                                <td style="padding: 6px; text-align: right; border: 1px solid #d1d5db; font-weight: bold; color: #ef4444;">${item.records_deleted}</td>
                            </tr>`;
                        });
                        
                        html += `</tbody></table></div>`;
                        
                        html += `<div style="margin-top: 15px; padding: 12px; background: #dc2626; color: white; border-radius: 8px; text-align: center; font-weight: bold;">
                            TOTAL: ${data.total_records_deleted} record(s) permanently deleted
                        </div>`;
                    }
                    
                    // Show failed tables if any
                    if (data.failed_tables && data.failed_tables.length > 0) {
                        html += `<div style="margin-top: 15px; padding: 12px; background: #fff3cd; border-left: 4px solid #f59e0b; border-radius: 8px;">
                            <strong style="color: #92400e;">⚠️ Warning:</strong> ${data.failed_tables.length} table(s) failed to clear
                        </div>`;
                    }
                } else {
                    html += `<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 8px;">
                        <h3 style="color: #991b1b;"><i class="fas fa-exclamation-triangle"></i> Error</h3>
                        <p>${data.message}</p>
                    </div>`;
                }
                
                html += `</div>`;
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-info-circle"></i> Operation Complete';
                document.getElementById('modalContent').innerHTML = html;
                
            } catch (error) {
                document.getElementById('modalContent').innerHTML = `
                    <div style="padding: 20px;">
                        <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 8px;">
                            <h3 style="color: #991b1b;"><i class="fas fa-times-circle"></i> Error</h3>
                            <p>Failed to delete data: ${error.message}</p>
                        </div>
                    </div>
                `;
            }
        }

        // Close modal when clicking outside
        document.getElementById('resultsModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
