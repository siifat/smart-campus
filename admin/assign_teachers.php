<?php
/**
 * Admin - Assign Teachers to Courses
 * Allows admin to assign teachers to specific course-section combinations
 */
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

// Get current trimester
$current_trimester = $conn->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_trimester_id = $current_trimester['trimester_id'] ?? 1;

// Get all active teachers
$teachers_stmt = $conn->query("
    SELECT t.*, d.department_name 
    FROM teachers t 
    LEFT JOIN departments d ON t.department_id = d.department_id 
    WHERE t.status = 'active' 
    ORDER BY t.full_name
");
$teachers = $teachers_stmt->fetch_all(MYSQLI_ASSOC);

// Get filter parameters
$filter_teacher = $_GET['teacher'] ?? '';
$filter_course = $_GET['course'] ?? '';
$filter_department = $_GET['department'] ?? '';
$search = $_GET['search'] ?? '';

// Get all departments
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);

// Get assignments query
$query = "
    SELECT 
        e.enrollment_id,
        e.teacher_id,
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        e.section,
        t.full_name as teacher_name,
        t.initial as teacher_initial,
        COUNT(DISTINCT CASE WHEN e.student_id IS NOT NULL THEN e.student_id END) as student_count
    FROM courses c
    CROSS JOIN (SELECT DISTINCT section FROM enrollments WHERE trimester_id = ?) sections
    LEFT JOIN enrollments e ON c.course_id = e.course_id 
        AND sections.section = e.section 
        AND e.trimester_id = ?
        AND e.status = 'enrolled'
    LEFT JOIN teachers t ON e.teacher_id = t.teacher_id
    WHERE 1=1
";

$params = [$current_trimester_id, $current_trimester_id];
$param_types = 'ii';

if ($search) {
    $query .= " AND (c.course_code LIKE ? OR c.course_name LIKE ? OR t.full_name LIKE ? OR t.initial LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

if ($filter_teacher) {
    $query .= " AND e.teacher_id = ?";
    $params[] = $filter_teacher;
    $param_types .= 'i';
}

if ($filter_course) {
    $query .= " AND c.course_id = ?";
    $params[] = $filter_course;
    $param_types .= 'i';
}

if ($filter_department) {
    $query .= " AND c.department_id = ?";
    $params[] = $filter_department;
    $param_types .= 'i';
}

$query .= " GROUP BY c.course_id, sections.section ORDER BY c.course_code, sections.section";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all courses for dropdown
$courses = $conn->query("SELECT * FROM courses ORDER BY course_code")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT CONCAT(course_id, '-', section)) as total_sections,
        COUNT(DISTINCT CASE WHEN teacher_id IS NOT NULL THEN CONCAT(course_id, '-', section) END) as assigned_sections,
        COUNT(DISTINCT CASE WHEN teacher_id IS NULL THEN CONCAT(course_id, '-', section) END) as unassigned_sections,
        COUNT(DISTINCT teacher_id) as active_teachers
    FROM enrollments
    WHERE trimester_id = ? AND status = 'enrolled'
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param('i', $current_trimester_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Teachers - Admin Portal</title>
    
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/manage-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .section-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .form-group label i {
            color: #f68b1f;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="dashboard-container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-user-check"></i> Assign Teachers to Courses</h1>
                    <p class="subtitle">Manage teacher assignments for <?= htmlspecialchars($current_trimester['trimester_name']) ?></p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card card-blue">
                    <div class="stat-icon"><i class="fas fa-th-large"></i></div>
                    <div class="stat-details">
                        <h3><?= $stats['total_sections'] ?? 0 ?></h3>
                        <p>Total Sections</p>
                        <span class="stat-badge">Course Sections</span>
                    </div>
                </div>

                <div class="stat-card card-green">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-details">
                        <h3><?= $stats['assigned_sections'] ?? 0 ?></h3>
                        <p>Assigned</p>
                        <span class="stat-badge">With Teachers</span>
                    </div>
                </div>

                <div class="stat-card card-pink">
                    <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="stat-details">
                        <h3><?= $stats['unassigned_sections'] ?? 0 ?></h3>
                        <p>Unassigned</p>
                        <span class="stat-badge">Need Teachers</span>
                    </div>
                </div>

                <div class="stat-card card-purple">
                    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-details">
                        <h3><?= $stats['active_teachers'] ?? 0 ?></h3>
                        <p>Active Teachers</p>
                        <span class="stat-badge">Available</span>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Filter Course Sections</h3>
                </div>
                <div class="card-body">
                    <form method="GET" style="display: flex; flex-direction: column; gap: 20px;">
                        <!-- Search Bar -->
                        <div class="search-bar" style="max-width: 100%;">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" id="searchInput" placeholder="Search by course code, name, or teacher..." 
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="width: 100%;">
                        </div>

                        <!-- Filter Dropdowns -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label><i class="fas fa-chalkboard-teacher"></i> Teacher</label>
                                <select name="teacher" class="form-control">
                                    <option value="">All Teachers</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?= $teacher['teacher_id'] ?>" <?= $filter_teacher == $teacher['teacher_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($teacher['full_name']) ?> (<?= htmlspecialchars($teacher['initial']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label><i class="fas fa-book"></i> Course</label>
                                <select name="course" class="form-control">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['course_id'] ?>" <?= $filter_course == $course['course_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label><i class="fas fa-building"></i> Department</label>
                                <select name="department" class="form-control">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['department_id'] ?>" <?= $filter_department == $dept['department_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="opacity: 0;">Actions</label>
                                <div style="display: flex; gap: 10px;">
                                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <button type="button" class="btn btn-outline" onclick="window.location.href='assign_teachers.php'" title="Clear all filters">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Assignments Table -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3><i class="fas fa-list"></i> Course Sections</h3>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <?php if ($search || $filter_teacher || $filter_course || $filter_department): ?>
                            <span class="badge badge-primary" style="font-size: 0.9rem; padding: 6px 12px;">
                                <i class="fas fa-filter"></i> <?= count($assignments) ?> result<?= count($assignments) != 1 ? 's' : '' ?> found
                            </span>
                        <?php else: ?>
                            <span style="color: #64748b; font-weight: 500; font-size: 0.9rem;">
                                <?= count($assignments) ?> total sections
                            </span>
                        <?php endif; ?>
                        <button class="btn-icon" onclick="location.reload()" title="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Section</th>
                                <th>Students</th>
                                <th>Assigned Teacher</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                                <?php if (empty($assignments)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                                            <i class="fas fa-inbox" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 16px;"></i>
                                            <p style="font-size: 1.125rem; font-weight: 600; margin-bottom: 8px;">No course sections found</p>
                                            <p style="font-size: 0.875rem;">Try adjusting your filters</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: #1a202c;"><?= htmlspecialchars($assignment['course_code']) ?></div>
                                                <div style="font-size: 0.875rem; color: #718096; margin-top: 2px;"><?= htmlspecialchars($assignment['course_name']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge badge-primary">
                                                    Section <?= htmlspecialchars($assignment['section']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 500;">
                                                    <i class="fas fa-user-graduate" style="color: #94a3b8; margin-right: 4px;"></i>
                                                    <?= $assignment['student_count'] ?> students
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($assignment['teacher_id']): ?>
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-check-circle"></i>
                                                        <?= htmlspecialchars($assignment['teacher_name']) ?>
                                                        (<?= htmlspecialchars($assignment['teacher_initial']) ?>)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">
                                                        <i class="fas fa-exclamation-circle"></i>
                                                        Not Assigned
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 8px;">
                                                    <button 
                                                        onclick="assignTeacher(<?= $assignment['course_id'] ?>, '<?= htmlspecialchars($assignment['section'], ENT_QUOTES) ?>', '<?= htmlspecialchars($assignment['course_code'] . ' - ' . $assignment['course_name'], ENT_QUOTES) ?>', <?= $assignment['teacher_id'] ?? 'null' ?>)"
                                                        class="btn btn-sm btn-primary"
                                                    >
                                                        <i class="fas fa-<?= $assignment['teacher_id'] ? 'edit' : 'user-plus' ?>"></i>
                                                        <?= $assignment['teacher_id'] ? 'Change' : 'Assign' ?>
                                                    </button>
                                                    <?php if ($assignment['teacher_id']): ?>
                                                        <button 
                                                            onclick="removeTeacher(<?= $assignment['course_id'] ?>, '<?= htmlspecialchars($assignment['section'], ENT_QUOTES) ?>')"
                                                            class="btn btn-sm btn-danger"
                                                        >
                                                            <i class="fas fa-times"></i>
                                                            Remove
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const teachers = <?= json_encode($teachers) ?>;
        const currentTrimester = <?= $current_trimester_id ?>;

        async function assignTeacher(courseId, section, courseName, currentTeacherId) {
            const teacherOptions = teachers.reduce((acc, teacher) => {
                const selected = teacher.teacher_id == currentTeacherId ? 'selected' : '';
                acc += `<option value="${teacher.teacher_id}" ${selected}>${teacher.full_name} (${teacher.initial})</option>`;
                return acc;
            }, '<option value="">Select a teacher</option>');

            const { value: teacherId } = await Swal.fire({
                title: 'Assign Teacher',
                html: `
                    <div style="text-align: left; padding: 10px;">
                        <p style="margin-bottom: 15px; color: #64748b; font-size: 0.95rem;">
                            <strong>Course:</strong> ${courseName}<br>
                            <strong>Section:</strong> ${section}
                        </p>
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #334155;">Select Teacher:</label>
                        <select id="teacher-select" class="swal2-input" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                            ${teacherOptions}
                        </select>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Assign',
                confirmButtonColor: '#3b82f6',
                preConfirm: () => {
                    const selected = document.getElementById('teacher-select').value;
                    if (!selected) {
                        Swal.showValidationMessage('Please select a teacher');
                        return false;
                    }
                    return selected;
                }
            });

            if (teacherId) {
                try {
                    const response = await fetch('api/assign_teacher.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            course_id: courseId,
                            section: section,
                            teacher_id: teacherId,
                            trimester_id: currentTrimester
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: result.message,
                            confirmButtonColor: '#3b82f6'
                        });
                        location.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: result.message,
                            confirmButtonColor: '#3b82f6'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to assign teacher. Please try again.',
                        confirmButtonColor: '#3b82f6'
                    });
                }
            }
        }

        async function removeTeacher(courseId, section) {
            const result = await Swal.fire({
                title: 'Remove Teacher?',
                text: 'This will unassign the teacher from this section.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Yes, Remove',
                cancelButtonText: 'Cancel'
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('api/remove_teacher.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            course_id: courseId,
                            section: section,
                            trimester_id: currentTrimester
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Removed!',
                            text: 'Teacher has been unassigned.',
                            confirmButtonColor: '#3b82f6'
                        });
                        location.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message,
                            confirmButtonColor: '#3b82f6'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to remove teacher.',
                        confirmButtonColor: '#3b82f6'
                    });
                }
            }
        }
    </script>
</body>
</html>
