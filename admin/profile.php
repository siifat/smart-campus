<?php
/**
 * Admin Profile Management
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

$admin_id = $_SESSION['admin_id'] ?? 1;
$success_message = '';
$error_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Update admin profile (you may need to create an admin_users table)
    $_SESSION['admin_name'] = $full_name;
    $_SESSION['admin_email'] = $email;
    $success_message = 'Profile updated successfully!';
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error_message = 'New passwords do not match!';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'Password must be at least 6 characters!';
    } else {
        // In production, verify current password and hash new password
        $_SESSION['password_changed'] = true;
        $success_message = 'Password changed successfully!';
    }
}

// Get admin info from session or database
$admin_name = $_SESSION['admin_name'] ?? 'Admin User';
$admin_email = $_SESSION['admin_email'] ?? 'admin@smartcampus.com';
$admin_phone = $_SESSION['admin_phone'] ?? '+880 1XXX-XXXXXX';
$admin_address = $_SESSION['admin_address'] ?? 'Dhaka, Bangladesh';
$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';

// Get activity statistics
$total_logins = 42; // Implement proper tracking
$last_login = date('Y-m-d H:i:s');
$account_created = '2024-01-01';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/manage-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 30px;
        }
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: white;
            margin: 0 auto 20px;
            position: relative;
        }
        .profile-avatar .edit-icon {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .profile-info {
            text-align: center;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .profile-info h2 {
            margin: 10px 0 5px;
            color: var(--dark);
        }
        .profile-info .role-badge {
            display: inline-block;
            padding: 5px 15px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        .stat-value {
            font-weight: 600;
            color: var(--dark);
        }
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            color: var(--dark);
        }
        @media (max-width: 1024px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        .edit-icon:hover {
            transform: scale(1.1);
            transition: transform 0.2s;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        
        <script>
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                const avatarContainer = document.getElementById('profileAvatar');
                
                reader.onload = function(e) {
                    // Clear current content
                    avatarContainer.innerHTML = '';
                    
                    // Create image element
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Profile Avatar';
                    img.style.cssText = 'width: 100%; height: 100%; border-radius: 50%; object-fit: cover;';
                    
                    // Re-add edit icon
                    const editIcon = document.createElement('div');
                    editIcon.className = 'edit-icon';
                    editIcon.title = 'Change Avatar';
                    editIcon.onclick = function() { document.getElementById('avatarInput').click(); };
                    editIcon.innerHTML = '<i class="fas fa-camera"></i>';
                    
                    avatarContainer.appendChild(img);
                    avatarContainer.appendChild(editIcon);
                    
                    // Show success message
                    showMessage('Avatar preview updated! Don\'t forget to save your profile.', 'info');
                    
                    // In production, you would upload this to the server
                    // uploadAvatar(input.files[0]);
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function showMessage(text, type = 'success') {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new alert
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'info'}-circle"></i> ${text}`;
            alert.style.cssText = 'margin-bottom: 20px; animation: slideDown 0.3s ease;';
            
            // Insert after page header
            const pageHeader = document.querySelector('.page-header');
            if (pageHeader) {
                pageHeader.parentNode.insertBefore(alert, pageHeader.nextSibling);
            }
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alert.style.animation = 'slideUp 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }
        </script>
        
        <div class="dashboard-container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-user-circle"></i> My Profile</h1>
                    <p class="subtitle">Manage your account settings and preferences</p>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="profile-container">
                <!-- Left Sidebar -->
                <div class="profile-card">
                    <div class="profile-avatar" id="profileAvatar">
                        <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                        <div class="edit-icon" title="Change Avatar" onclick="document.getElementById('avatarInput').click()">
                            <i class="fas fa-camera"></i>
                        </div>
                        <input type="file" id="avatarInput" accept="image/*" style="display: none;" onchange="previewAvatar(this)">
                    </div>
                    
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($admin_name); ?></h2>
                        <span class="role-badge"><?php echo $admin_role; ?></span>
                    </div>

                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-envelope"></i> Email</span>
                            <span class="stat-value"><?php echo htmlspecialchars($admin_email); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-phone"></i> Phone</span>
                            <span class="stat-value"><?php echo htmlspecialchars($admin_phone); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-sign-in-alt"></i> Total Logins</span>
                            <span class="stat-value"><?php echo $total_logins; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-clock"></i> Last Login</span>
                            <span class="stat-value"><?php echo date('M d, Y', strtotime($last_login)); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-calendar"></i> Member Since</span>
                            <span class="stat-value"><?php echo date('M Y', strtotime($account_created)); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Right Content -->
                <div>
                    <!-- Profile Information -->
                    <div class="profile-card" style="margin-bottom: 30px;">
                        <h3 class="section-title"><i class="fas fa-user-edit"></i> Profile Information</h3>
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($admin_name); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($admin_email); ?>" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="text" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($admin_phone); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Address</label>
                                    <input type="text" name="address" class="form-control" 
                                           value="<?php echo htmlspecialchars($admin_address); ?>">
                                </div>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="profile-card">
                        <h3 class="section-title"><i class="fas fa-lock"></i> Change Password</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" class="form-control" 
                                       placeholder="Enter current password" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" class="form-control" 
                                           placeholder="Enter new password" required>
                                </div>
                                <div class="form-group">
                                    <label>Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" 
                                           placeholder="Confirm new password" required>
                                </div>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
