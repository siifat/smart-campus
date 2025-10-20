<?php
/**
 * Teacher Announcements - UIU Smart Campus
 * Create and manage announcements for students
 */
session_start();

// Check if teacher is logged in
if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_initial = $_SESSION['teacher_initial'] ?? '';

// Get current trimester
$current_trimester = $conn->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_trimester_id = $current_trimester['trimester_id'] ?? null;

// Get teacher's courses for dropdown
$courses_query = "
    SELECT DISTINCT c.course_id, c.course_code, c.course_name, e.section
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.teacher_id = ? AND e.trimester_id = ? AND e.status = 'enrolled'
    ORDER BY c.course_code, e.section
";
$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->bind_param('ii', $teacher_id, $current_trimester_id);
$courses_stmt->execute();
$teacher_courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

// Fetch announcements with student reach count
$announcements_query = "
    SELECT 
        ta.*,
        c.course_code,
        c.course_name,
        COUNT(DISTINCT ar.student_id) as read_count,
        (SELECT COUNT(DISTINCT e.student_id) 
         FROM enrollments e 
         WHERE e.course_id = ta.course_id 
         AND e.trimester_id = ta.trimester_id
         AND (ta.section IS NULL OR e.section = ta.section)
         AND e.status = 'enrolled') as target_students
    FROM teacher_announcements ta
    LEFT JOIN courses c ON ta.course_id = c.course_id
    LEFT JOIN announcement_reads ar ON ta.announcement_id = ar.announcement_id
    WHERE ta.teacher_id = ? AND ta.trimester_id = ?
    GROUP BY ta.announcement_id
    ORDER BY ta.is_pinned DESC, ta.published_at DESC
";
$ann_stmt = $conn->prepare($announcements_query);
$ann_stmt->bind_param('ii', $teacher_id, $current_trimester_id);
$ann_stmt->execute();
$announcements = $ann_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ann_stmt->close();

$page_title = 'Announcements';
$page_icon = 'fas fa-bullhorn';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - UIU Smart Campus</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --topbar-height: 70px;
            --sidebar-width: 260px;
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-color: #334155;
            --shadow-color: rgba(0, 0, 0, 0.3);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-secondary);
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--card-bg);
            border-right: 1px solid var(--border-color);
            padding: 24px 0;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        [data-theme="dark"] .sidebar {
            background: #1a202c;
        }
        
        .sidebar-logo {
            padding: 0 24px 24px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-logo i {
            font-size: 28px;
            color: #667eea;
        }
        
        .sidebar-logo span {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .nav-item:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            color: #667eea;
            border-right: 3px solid #667eea;
        }
        
        .nav-item i {
            font-size: 18px;
            width: 20px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 24px;
            padding-top: calc(var(--topbar-height) + 24px);
        }
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--topbar-height);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 999;
            box-shadow: 0 2px 8px var(--shadow-color);
        }
        
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            color: var(--text-secondary);
        }
        
        .icon-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .icon-btn .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 6px;
            border-radius: 10px;
            min-width: 18px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 12px;
            transition: all 0.2s;
        }
        
        .user-profile:hover {
            background: var(--bg-secondary);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 700;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 4px 16px var(--shadow-color);
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-urgent { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .badge-important { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .badge-general { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .badge-reminder { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .topbar {
                left: 0;
            }
        }
        
        .announcement-card {
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .announcement-card:hover {
            transform: translateX(5px);
        }
        
        .announcement-card.pinned {
            border-left-color: #f59e0b;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), transparent);
        }
    </style>
</head>
<body>
    <?php require_once('includes/sidebar.php'); ?>
    <?php require_once('includes/topbar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div style="margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
                    <i class="fas fa-bullhorn" style="color: #667eea;"></i> Announcements
                </h1>
                <p style="color: var(--text-secondary);">Create and manage announcements for your students</p>
            </div>
            <button onclick="showCreateModal()" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> New Announcement
            </button>
        </div>
        
        <!-- Announcements List -->
        <div style="display: grid; gap: 20px;">
            <?php if (empty($announcements)): ?>
                <div class="card" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-bullhorn" style="font-size: 72px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 20px;"></i>
                    <h3 style="font-size: 20px; font-weight: 600; color: var(--text-primary); margin: 0 0 10px 0;">No Announcements Yet</h3>
                    <p style="color: var(--text-secondary); margin: 0 0 20px 0;">Create your first announcement to notify students</p>
                    <button onclick="showCreateModal()" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Create Announcement
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                <div class="card announcement-card <?php echo $ann['is_pinned'] ? 'pinned' : ''; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                <?php if ($ann['is_pinned']): ?>
                                    <i class="fas fa-thumbtack" style="color: #f59e0b;"></i>
                                <?php endif; ?>
                                <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 0;">
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                </h3>
                                <span class="badge badge-<?php echo $ann['announcement_type']; ?>">
                                    <?php echo ucfirst($ann['announcement_type']); ?>
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 16px; font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y \a\t h:i A', strtotime($ann['published_at'])); ?></span>
                                <?php if ($ann['course_id']): ?>
                                    <span>
                                        <i class="fas fa-book"></i> 
                                        <?php echo htmlspecialchars($ann['course_code']); ?>
                                        <?php echo $ann['section'] ? ' - ' . htmlspecialchars($ann['section']) : ' (All Sections)'; ?>
                                    </span>
                                <?php else: ?>
                                    <span><i class="fas fa-globe"></i> All Students</span>
                                <?php endif; ?>
                                <span>
                                    <i class="fas fa-eye"></i> 
                                    <?php echo $ann['read_count']; ?>/<?php echo $ann['target_students']; ?> read
                                </span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="togglePin(<?php echo $ann['announcement_id']; ?>, <?php echo $ann['is_pinned']; ?>)" 
                                    class="icon-btn" title="<?php echo $ann['is_pinned'] ? 'Unpin' : 'Pin'; ?>">
                                <i class="fas fa-thumbtack"></i>
                            </button>
                            <button onclick="editAnnouncement(<?php echo $ann['announcement_id']; ?>)" 
                                    class="icon-btn" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteAnnouncement(<?php echo $ann['announcement_id']; ?>)" 
                                    class="icon-btn" title="Delete" style="color: #ef4444;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 12px;">
                        <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
                    </div>
                    <?php if ($ann['file_path']): ?>
                        <div style="padding: 12px; background: var(--bg-secondary); border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-paperclip" style="color: #667eea;"></i>
                            <a href="../<?php echo htmlspecialchars($ann['file_path']); ?>" 
                               target="_blank" 
                               style="color: #667eea; text-decoration: none; font-weight: 600;">
                                View Attachment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Create/Edit Announcement Modal -->
    <div id="announcementModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
        <div style="background: var(--card-bg); border-radius: 20px; padding: 32px; max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin: 0;">
                    <i class="fas fa-bullhorn"></i> <span id="modalTitle">New Announcement</span>
                </h2>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="announcementForm">
                <input type="hidden" id="announcement_id" name="announcement_id">
                
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" class="form-control" required placeholder="Enter announcement title">
                </div>
                
                <div class="form-group">
                    <label for="content">Content *</label>
                    <textarea id="content" name="content" class="form-control" rows="6" required placeholder="Enter announcement content"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="announcement_type">Type *</label>
                    <select id="announcement_type" name="announcement_type" class="form-control" required>
                        <option value="general">General</option>
                        <option value="important">Important</option>
                        <option value="urgent">Urgent</option>
                        <option value="reminder">Reminder</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="target_audience">Target Audience *</label>
                    <select id="target_audience" name="target_audience" class="form-control" onchange="toggleCourseSection()" required>
                        <option value="all">All My Students</option>
                        <option value="course">Specific Course</option>
                    </select>
                </div>
                
                <div id="courseSelection" style="display: none;">
                    <div class="form-group">
                        <label for="course_id">Select Course *</label>
                        <select id="course_id" name="course_id" class="form-control" onchange="toggleSectionField()">
                            <option value="">Choose a course...</option>
                            <?php foreach ($teacher_courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" data-section="<?php echo htmlspecialchars($course['section']); ?>">
                                    <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['section']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="sectionGroup" style="display: none;">
                        <label for="section">Section (Leave empty for all sections)</label>
                        <input type="text" id="section" name="section" class="form-control" placeholder="e.g., A, B, C">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="file">Attachment (Optional)</label>
                    <input type="file" id="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png">
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="is_pinned" name="is_pinned" value="1">
                        <span>Pin this announcement to top</span>
                    </label>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                    <button type="button" onclick="closeModal()" class="btn" style="background: var(--bg-secondary); color: var(--text-secondary);">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> <span id="submitBtnText">Publish Announcement</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'New Announcement';
            document.getElementById('submitBtnText').textContent = 'Publish Announcement';
            document.getElementById('announcementForm').reset();
            document.getElementById('announcement_id').value = '';
            document.getElementById('announcementModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('announcementModal').style.display = 'none';
        }
        
        function toggleCourseSection() {
            const target = document.getElementById('target_audience').value;
            const courseSelection = document.getElementById('courseSelection');
            const courseId = document.getElementById('course_id');
            
            if (target === 'course') {
                courseSelection.style.display = 'block';
                courseId.required = true;
            } else {
                courseSelection.style.display = 'none';
                courseId.required = false;
                courseId.value = '';
                document.getElementById('section').value = '';
            }
        }
        
        function toggleSectionField() {
            const courseSelect = document.getElementById('course_id');
            const selectedOption = courseSelect.options[courseSelect.selectedIndex];
            const sectionGroup = document.getElementById('sectionGroup');
            
            if (courseSelect.value) {
                sectionGroup.style.display = 'block';
                // Pre-fill with the course's section
                const section = selectedOption.getAttribute('data-section');
                document.getElementById('section').value = section || '';
            } else {
                sectionGroup.style.display = 'none';
            }
        }
        
        document.getElementById('announcementForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('api/announcements.php?action=create', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#ef4444'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to create announcement',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
        
        async function togglePin(announcementId, currentlyPinned) {
            try {
                const response = await fetch('api/announcements.php?action=toggle_pin', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `announcement_id=${announcementId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#ef4444'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }
        
        async function deleteAnnouncement(announcementId) {
            const result = await Swal.fire({
                title: 'Delete Announcement?',
                text: 'This action cannot be undone',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it'
            });
            
            if (result.isConfirmed) {
                try {
                    const response = await fetch('api/announcements.php?action=delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `announcement_id=${announcementId}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: data.message,
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message,
                            confirmButtonColor: '#ef4444'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to delete announcement',
                        confirmButtonColor: '#ef4444'
                    });
                }
            }
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('announcementModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'announcementModal') {
                closeModal();
            }
        });
    </script>
</body>
</html>
