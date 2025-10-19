<?php
session_start();
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Location: ../login.html');
    exit;
}

require_once('../config/database.php');
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$message = '';
$message_type = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $blood_group = $_POST['blood_group'] ?? '';
    $father_name = $_POST['father_name'] ?? '';
    $mother_name = $_POST['mother_name'] ?? '';
    $address = $_POST['address'] ?? '';
    $emergency_contact_name = $_POST['emergency_contact_name'] ?? '';
    $emergency_contact_phone = $_POST['emergency_contact_phone'] ?? '';
    $bio = $_POST['bio'] ?? '';
    
    $update_query = "UPDATE students SET full_name = ?, email = ?, phone = ?, date_of_birth = ?, blood_group = ?, father_name = ?, mother_name = ?, address = ?, emergency_contact_name = ?, emergency_contact_phone = ?, bio = ? WHERE student_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('ssssssssssss', $full_name, $email, $phone, $date_of_birth, $blood_group, $father_name, $mother_name, $address, $emergency_contact_name, $emergency_contact_phone, $bio, $student_id);
    
    if ($stmt->execute()) {
        $message = 'Profile updated successfully!';
        $message_type = 'success';
        $_SESSION['student_name'] = $full_name;
        $student_name = $full_name;
    } else {
        $message = 'Error updating profile.';
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024;
        
        if (!in_array($file['type'], $allowed_types)) {
            $message = 'Invalid file type. Only JPG, PNG, and GIF allowed.';
            $message_type = 'error';
        } elseif ($file['size'] > $max_size) {
            $message = 'File too large. Maximum size is 5MB.';
            $message_type = 'error';
        } else {
            $upload_dir = '../uploads/profile_pictures/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '_' . $student_id . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $old_pic_query = "SELECT profile_picture FROM students WHERE student_id = ?";
                $stmt = $conn->prepare($old_pic_query);
                $stmt->bind_param('s', $student_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $old_data = $result->fetch_assoc();
                
                if ($old_data && $old_data['profile_picture']) {
                    $old_file = $upload_dir . $old_data['profile_picture'];
                    if (file_exists($old_file)) unlink($old_file);
                }
                
                $update_pic_query = "UPDATE students SET profile_picture = ? WHERE student_id = ?";
                $stmt = $conn->prepare($update_pic_query);
                $stmt->bind_param('ss', $new_filename, $student_id);
                
                if ($stmt->execute()) {
                    $message = 'Profile picture updated successfully!';
                    $message_type = 'success';
                }
                $stmt->close();
            }
        }
    }
}

// Fetch student data
$query = "SELECT s.*, p.program_name, d.department_name FROM students s JOIN programs p ON s.program_id = p.program_id JOIN departments d ON p.department_id = d.department_id WHERE s.student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get enrollment stats
$stats_query = "SELECT COUNT(DISTINCT e.enrollment_id) as total_courses, SUM(c.credit_hours) as total_credits FROM enrollments e JOIN courses c ON e.course_id = c.course_id WHERE e.student_id = ? AND e.status = 'enrolled'";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param('s', $student_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = 'My Profile';
$page_icon = 'fas fa-user';
$show_page_title = true;
$total_points = $student['total_points'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - UIU Smart Campus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --bg-primary: #ffffff; --bg-secondary: #f8fafc; --text-primary: #0f172a; --text-secondary: #475569; --border-color: #e2e8f0; --shadow-color: rgba(0, 0, 0, 0.1); --card-bg: rgba(255, 255, 255, 0.9); --sidebar-width: 280px; --topbar-height: 72px; }
        [data-theme="dark"] { --bg-primary: #0f172a; --bg-secondary: #1e293b; --text-primary: #f1f5f9; --text-secondary: #cbd5e1; --border-color: #334155; --shadow-color: rgba(0, 0, 0, 0.3); --card-bg: rgba(30, 41, 59, 0.9); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-secondary); color: var(--text-secondary); transition: all 0.3s ease; }
        .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: var(--sidebar-width); background: linear-gradient(180deg, #f68b1f 0%, #fbbf24 50%, #f68b1f 100%); padding: 24px; overflow-y: auto; z-index: 100; transition: transform 0.3s ease; box-shadow: 4px 0 20px rgba(246, 139, 31, 0.15); }
        [data-theme="dark"] .sidebar { background: linear-gradient(180deg, #d97706 0%, #f59e0b 50%, #d97706 100%); }
        .sidebar-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 32px; padding: 12px; background: rgba(255, 255, 255, 0.2); border-radius: 12px; backdrop-filter: blur(10px); }
        .sidebar-logo i { font-size: 32px; color: white; }
        .sidebar-logo span { font-size: 20px; font-weight: 800; color: white; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; margin-bottom: 8px; border-radius: 12px; color: rgba(255, 255, 255, 0.9); text-decoration: none; font-weight: 600; transition: all 0.3s ease; cursor: pointer; }
        .nav-item:hover { background: rgba(255, 255, 255, 0.2); transform: translateX(5px); }
        .nav-item.active { background: rgba(255, 255, 255, 0.3); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .nav-item i { font-size: 20px; width: 24px; }
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; padding: 24px; padding-top: calc(var(--topbar-height) + 24px); }
        .topbar { position: fixed; top: 0; left: var(--sidebar-width); right: 0; height: var(--topbar-height); background: var(--card-bg); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); padding: 0 32px; display: flex; align-items: center; justify-content: space-between; z-index: 90; box-shadow: 0 2px 12px var(--shadow-color); }
        .search-box { flex: 1; max-width: 500px; position: relative; }
        .search-box input { width: 100%; padding: 12px 16px 12px 48px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-size: 14px; transition: all 0.3s ease; }
        .search-box input:focus { outline: none; border-color: #f68b1f; box-shadow: 0 0 0 3px rgba(246, 139, 31, 0.1); }
        .search-box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); }
        .topbar-actions { display: flex; align-items: center; gap: 16px; }
        .icon-btn { width: 44px; height: 44px; border-radius: 12px; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; position: relative; }
        .icon-btn:hover { background: #f68b1f; color: white; transform: translateY(-2px); }
        .icon-btn .badge { position: absolute; top: -4px; right: -4px; background: #ef4444; color: white; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 10px; border: 2px solid var(--bg-primary); }
        .user-profile { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 12px; background: var(--bg-secondary); cursor: pointer; transition: all 0.3s ease; }
        .user-profile:hover { background: var(--border-color); }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #f68b1f, #fbbf24); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; }
        .glass-card { background: var(--card-bg); backdrop-filter: blur(20px); border: 1px solid var(--border-color); border-radius: 20px; padding: 24px; box-shadow: 0 4px 20px var(--shadow-color); transition: all 0.3s ease; }
        .glass-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px var(--shadow-color); }
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } .topbar { left: 0; } }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>
    
    <div class="main-content">
        <?php if ($message): ?>
            <div class="glass-card" style="margin-bottom: 24px; padding: 16px; border-left: 4px solid <?php echo $message_type === 'success' ? '#10b981' : '#ef4444'; ?>;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>" style="color: <?php echo $message_type === 'success' ? '#10b981' : '#ef4444'; ?>; font-size: 20px;"></i>
                    <span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($message); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Profile Header -->
        <div class="glass-card" style="background: linear-gradient(135deg, #f68b1f 0%, #fbbf24 100%); color: white; margin-bottom: 24px;">
            <div style="display: flex; align-items: center; gap: 32px; flex-wrap: wrap;">
                <div style="position: relative;">
                    <?php if ($student['profile_picture']): ?>
                        <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($student['profile_picture']); ?>" alt="Profile" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,0.3);">
                    <?php else: ?>
                        <div style="width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; border: 4px solid rgba(255,255,255,0.3);"><i class="fas fa-user" style="font-size: 48px;"></i></div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" style="display: inline;">
                        <input type="file" id="profile-pic" name="profile_picture" accept="image/*" style="display: none;" onchange="this.form.submit()">
                        <button type="button" onclick="document.getElementById('profile-pic').click()" style="position: absolute; bottom: 0; right: 0; width: 36px; height: 36px; border-radius: 50%; background: white; color: #f68b1f; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2);"><i class="fas fa-camera"></i></button>
                    </form>
                </div>
                <div style="flex: 1;">
                    <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 8px;"><?php echo htmlspecialchars($student['full_name']); ?></h1>
                    <p style="margin: 4px 0;"><i class="fas fa-id-card" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($student['student_id']); ?></p>
                    <p style="margin: 4px 0;"><i class="fas fa-graduation-cap" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($student['program_name']); ?></p>
                    <p style="margin: 4px 0;"><i class="fas fa-building" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($student['department_name']); ?></p>
                </div>
                <div style="display: flex; gap: 24px;">
                    <div style="text-align: center;"><div style="font-size: 24px; font-weight: 800;"><?php echo number_format($student['current_cgpa'], 2); ?></div><div style="font-size: 12px; opacity: 0.9;">CGPA</div></div>
                    <div style="text-align: center;"><div style="font-size: 24px; font-weight: 800;"><?php echo $stats['total_courses'] ?? 0; ?></div><div style="font-size: 12px; opacity: 0.9;">Courses</div></div>
                    <div style="text-align: center;"><div style="font-size: 24px; font-weight: 800;"><?php echo $student['total_completed_credits']; ?></div><div style="font-size: 12px; opacity: 0.9;">Credits</div></div>
                    <div style="text-align: center;"><div style="font-size: 24px; font-weight: 800;"><?php echo $student['total_points']; ?></div><div style="font-size: 12px; opacity: 0.9;">Points</div></div>
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
            <!-- Edit Profile -->
            <div class="glass-card" style="grid-column: 1 / -1;">
                <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 24px; display: flex; align-items: center; gap: 12px;"><i class="fas fa-user-edit" style="color: #f68b1f;"></i> Edit Profile</h2>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Full Name *</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"></div>
                        <div><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Email *</label><input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"></div>
                        <div><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Phone</label><input type="tel" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"></div>
                        <div><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Date of Birth</label><input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>" style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"></div>
                        <div><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Blood Group</label><select name="blood_group" style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"><option value="">Select...</option><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?><option value="<?php echo $bg; ?>" <?php echo $student['blood_group'] === $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option><?php endforeach; ?></select></div>
                        <div><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Father's Name</label><input type="text" name="father_name" value="<?php echo htmlspecialchars($student['father_name'] ?? ''); ?>" style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"></div>
                        <div><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Mother's Name</label><input type="text" name="mother_name" value="<?php echo htmlspecialchars($student['mother_name'] ?? ''); ?>" style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"></div>
                        <div><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Emergency Contact Name</label><input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($student['emergency_contact_name'] ?? ''); ?>" style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"></div>
                        <div><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Emergency Phone</label><input type="tel" name="emergency_contact_phone" value="<?php echo htmlspecialchars($student['emergency_contact_phone'] ?? ''); ?>" style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"></div>
                        <div style="grid-column: 1 / -1;"><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Address</label><input type="text" name="address" value="<?php echo htmlspecialchars($student['address'] ?? ''); ?>" style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit;"></div>
                        <div style="grid-column: 1 / -1;"><label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 14px;">Bio</label><textarea name="bio" rows="4" style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); font-family: inherit; resize: vertical;"><?php echo htmlspecialchars($student['bio'] ?? ''); ?></textarea></div>
                    </div>
                    <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="location.reload()" style="padding: 12px 24px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-secondary); font-weight: 600; cursor: pointer;"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" name="update_profile" style="padding: 12px 24px; border: none; border-radius: 12px; background: linear-gradient(135deg, #f68b1f, #fbbf24); color: white; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(246, 139, 31, 0.3);"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
            
            <!-- Account Info -->
            <div class="glass-card">
                <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 24px; display: flex; align-items: center; gap: 12px;"><i class="fas fa-info-circle" style="color: #f68b1f;"></i> Account Information</h2>
                <?php $info_items = [['Student ID', $student['student_id']], ['Status', '<span style="color: #10b981; font-weight: 700;"><i class="fas fa-check-circle"></i> ' . ucfirst($student['status']) . '</span>'], ['Admission', $student['admission_date'] ? date('M d, Y', strtotime($student['admission_date'])) : 'N/A'], ['Trimester', $student['current_trimester_number']], ['Member Since', date('M d, Y', strtotime($student['created_at']))]]; foreach ($info_items as $item): ?>
                <div style="padding: 16px 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;"><span style="font-weight: 600; color: var(--text-secondary); font-size: 14px;"><?php echo $item[0]; ?></span><span style="color: var(--text-primary); font-weight: 600;"><?php echo $item[1]; ?></span></div>
                <?php endforeach; ?>
            </div>
            
            <!-- Academic Progress -->
            <div class="glass-card">
                <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 24px; display: flex; align-items: center; gap: 12px;"><i class="fas fa-chart-line" style="color: #f68b1f;"></i> Academic Progress</h2>
                <?php $academic = [['Program', $student['program_name']], ['CGPA', '<span style="font-size: 20px; color: #f68b1f; font-weight: 800;">' . number_format($student['current_cgpa'], 2) . '</span>'], ['Credits', $student['total_completed_credits'] . ' credits'], ['Courses', ($stats['total_courses'] ?? 0) . ' courses'], ['Points', '<i class="fas fa-star" style="color: #fbbf24;"></i> ' . $student['total_points']]]; foreach ($academic as $item): ?>
                <div style="padding: 16px 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;"><span style="font-weight: 600; color: var(--text-secondary); font-size: 14px;"><?php echo $item[0]; ?></span><span style="color: var(--text-primary); font-weight: 600;"><?php echo $item[1]; ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
