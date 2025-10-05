<?php
/**
 * Universal CRUD Management System
 * Handles all database tables with full CRUD operations
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

$table = $_GET['table'] ?? 'students';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? $_POST['id'] ?? null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Table configurations
$table_config = [
    'students' => [
        'title' => 'Students',
        'icon' => 'fa-user-graduate',
        'primary_key' => 'student_id',
        'display_fields' => ['student_id', 'full_name', 'email', 'program_id', 'current_cgpa', 'status'],
        'editable_fields' => ['student_id', 'full_name', 'email', 'password_hash', 'phone', 'program_id', 'current_cgpa', 'total_points', 'status'],
        'required_fields' => ['student_id', 'full_name', 'email', 'password_hash', 'program_id'],
        'readonly_on_edit' => ['student_id'], // Can't change ID when editing
        'searchable_fields' => ['student_id', 'full_name', 'email']
    ],
    'teachers' => [
        'title' => 'Teachers',
        'icon' => 'fa-chalkboard-teacher',
        'primary_key' => 'teacher_id',
        'display_fields' => ['teacher_id', 'full_name', 'initial', 'email', 'department_id', 'status'],
        'editable_fields' => ['username', 'full_name', 'initial', 'email', 'password_hash', 'phone', 'department_id', 'designation', 'status'],
        'required_fields' => ['username', 'full_name', 'initial', 'email', 'password_hash', 'department_id'],
        'readonly_on_edit' => ['username'], // Username can't be changed after creation
        'searchable_fields' => ['full_name', 'initial', 'email']
    ],
    'courses' => [
        'title' => 'Courses',
        'icon' => 'fa-book',
        'primary_key' => 'course_id',
        'display_fields' => ['course_id', 'course_code', 'course_name', 'credit_hours', 'department_id', 'course_type'],
        'editable_fields' => ['course_id', 'course_code', 'course_name', 'credit_hours', 'department_id', 'course_type'],
        'required_fields' => ['course_id', 'course_code', 'course_name', 'credit_hours', 'department_id'],
        'readonly_on_edit' => ['course_id'],
        'searchable_fields' => ['course_code', 'course_name']
    ],
    'enrollments' => [
        'title' => 'Enrollments',
        'icon' => 'fa-clipboard-list',
        'primary_key' => 'enrollment_id',
        'display_fields' => ['enrollment_id', 'student_id', 'course_id', 'trimester_id', 'section', 'status'],
        'editable_fields' => ['student_id', 'course_id', 'trimester_id', 'section', 'teacher_id', 'status'],
        'required_fields' => ['student_id', 'course_id', 'trimester_id', 'section'],
        'readonly_on_edit' => [],
        'searchable_fields' => ['student_id', 'section']
    ],
    'departments' => [
        'title' => 'Departments',
        'icon' => 'fa-building',
        'primary_key' => 'department_id',
        'display_fields' => ['department_id', 'department_code', 'department_name'],
        'editable_fields' => ['department_id', 'department_code', 'department_name'],
        'required_fields' => ['department_id', 'department_code', 'department_name'],
        'readonly_on_edit' => ['department_id'],
        'searchable_fields' => ['department_code', 'department_name']
    ],
    'programs' => [
        'title' => 'Programs',
        'icon' => 'fa-graduation-cap',
        'primary_key' => 'program_id',
        'display_fields' => ['program_id', 'program_code', 'program_name', 'department_id', 'total_required_credits'],
        'editable_fields' => ['program_id', 'program_code', 'program_name', 'department_id', 'total_required_credits', 'duration_years'],
        'required_fields' => ['program_id', 'program_code', 'program_name', 'department_id'],
        'readonly_on_edit' => ['program_id'],
        'searchable_fields' => ['program_code', 'program_name']
    ],
    'trimesters' => [
        'title' => 'Trimesters',
        'icon' => 'fa-calendar-alt',
        'primary_key' => 'trimester_id',
        'display_fields' => ['trimester_id', 'trimester_code', 'trimester_name', 'year', 'is_current'],
        'editable_fields' => ['trimester_id', 'trimester_code', 'trimester_name', 'trimester_type', 'year', 'start_date', 'end_date', 'is_current'],
        'required_fields' => ['trimester_id', 'trimester_code', 'trimester_name', 'year'],
        'readonly_on_edit' => ['trimester_id'],
        'searchable_fields' => ['trimester_code', 'trimester_name']
    ],
    'notes' => [
        'title' => 'Notes',
        'icon' => 'fa-file-alt',
        'primary_key' => 'note_id',
        'display_fields' => ['note_id', 'student_id', 'course_id', 'title', 'status', 'upload_date'],
        'editable_fields' => ['student_id', 'course_id', 'title', 'description', 'status', 'points_awarded'],
        'required_fields' => ['student_id', 'course_id', 'title'],
        'readonly_on_edit' => [],
        'searchable_fields' => ['title', 'student_id']
    ],
    'question_solutions' => [
        'title' => 'Question Solutions',
        'icon' => 'fa-question-circle',
        'primary_key' => 'solution_id',
        'display_fields' => ['solution_id', 'student_id', 'course_id', 'question_title', 'exam_type', 'status'],
        'editable_fields' => ['student_id', 'course_id', 'question_title', 'exam_type', 'status', 'points_awarded'],
        'required_fields' => ['student_id', 'course_id', 'question_title'],
        'readonly_on_edit' => [],
        'searchable_fields' => ['question_title', 'student_id']
    ]
];

if (!isset($table_config[$table])) {
    die('Invalid table');
}

$config = $table_config[$table];

// Handle Actions
// Handle DELETE via GET (with confirmation from JavaScript)
if ($action === 'delete' && $id) {
    $sql = "DELETE FROM $table WHERE {$config['primary_key']} = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['message'] = 'SQL Error: ' . $conn->error;
        $_SESSION['message_type'] = 'error';
    } else {
        $stmt->bind_param('s', $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = 'Record deleted successfully!';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Record not found';
                $_SESSION['message_type'] = 'warning';
            }
        } else {
            $_SESSION['message'] = 'Error: ' . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
    }
    
    header("Location: manage.php?table=$table");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'edit' || $action === 'add') {
        $fields = [];
        $values = [];
        $types = '';
        
        foreach ($config['editable_fields'] as $field) {
            // For password field: skip if empty during edit (keep current password)
            if ($field === 'password_hash' && $action === 'edit' && empty($_POST[$field])) {
                continue;
            }
            
            // Skip readonly fields during edit
            if ($action === 'edit' && in_array($field, $config['readonly_on_edit'] ?? [])) {
                continue;
            }
            
            // Only include fields that have values
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                $fields[] = $field;
                
                // Hash password before storing
                if ($field === 'password_hash') {
                    $values[] = password_hash($_POST[$field], PASSWORD_DEFAULT);
                } else {
                    $values[] = $_POST[$field];
                }
                $types .= 's';
            }
        }
        
        if ($action === 'edit' && $id) {
            if (count($fields) > 0) {
                $set_clause = implode(' = ?, ', $fields) . ' = ?';
                $sql = "UPDATE $table SET $set_clause WHERE {$config['primary_key']} = ?";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    $_SESSION['message'] = 'SQL Error: ' . $conn->error;
                    $_SESSION['message_type'] = 'error';
                } else {
                    $values[] = $id;
                    $types .= 's';
                    $stmt->bind_param($types, ...$values);
                    
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $_SESSION['message'] = 'Record updated successfully!';
                            $_SESSION['message_type'] = 'success';
                        } else {
                            $_SESSION['message'] = 'No changes made';
                            $_SESSION['message_type'] = 'info';
                        }
                    } else {
                        $_SESSION['message'] = 'Error: ' . $stmt->error;
                        $_SESSION['message_type'] = 'error';
                    }
                    $stmt->close();
                }
            } else {
                $_SESSION['message'] = 'No fields to update!';
                $_SESSION['message_type'] = 'warning';
            }
        } else {
            // For ADD action
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $fields_str = implode(', ', $fields);
            $stmt = $conn->prepare("INSERT INTO $table ($fields_str) VALUES ($placeholders)");
            $stmt->bind_param($types, ...$values);
            $success_msg = 'Record added successfully!';
            
            if ($stmt->execute()) {
                $_SESSION['message'] = $success_msg;
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error adding: ' . $stmt->error;
                $_SESSION['message_type'] = 'error';
            }
            $stmt->close();
        }
        
        header("Location: manage.php?table=$table");
        exit;
    }
}

// Fetch data
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

$where_clause = '';
if ($search) {
    $search_conditions = [];
    foreach ($config['searchable_fields'] as $field) {
        $search_conditions[] = "$field LIKE '%" . $conn->real_escape_string($search) . "%'";
    }
    $where_clause = 'WHERE ' . implode(' OR ', $search_conditions);
}

$count_query = "SELECT COUNT(*) as total FROM $table $where_clause";
$total_records = $conn->query($count_query)->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get record for editing FIRST (before listing query)
$edit_record = [];
if ($action === 'edit' && $id) {
    $edit_query = "SELECT * FROM $table WHERE {$config['primary_key']} = ?";
    $stmt = $conn->prepare($edit_query);
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $edit_result = $stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $edit_record = $edit_result->fetch_assoc();
    } else {
        $_SESSION['message'] = 'Record not found!';
        $_SESSION['message_type'] = 'error';
        header("Location: manage.php?table=$table");
        exit;
    }
    $stmt->close();
}

// Now get listing data
$query = "SELECT * FROM $table $where_clause ORDER BY {$config['primary_key']} DESC LIMIT $per_page OFFSET $offset";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?php echo $config['title']; ?> - Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/manage-style.css">
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
                    <h1><i class="fas <?php echo $config['icon']; ?>"></i> Manage <?php echo $config['title']; ?></h1>
                    <p class="subtitle">View, add, edit, and delete <?php echo strtolower($config['title']); ?> records</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-success" onclick="window.location.href='manage.php?table=<?php echo $table; ?>&action=add';">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                    <button class="btn btn-outline" onclick="exportData()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Search and Filter Bar -->
            <div class="toolbar">
                <form method="GET" action="manage.php" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="table" value="<?php echo $table; ?>">
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" id="searchInput" placeholder="Search <?php echo strtolower($config['title']); ?>..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 24px; border-radius: 8px; white-space: nowrap; height: 44px;">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($search): ?>
                        <button type="button" class="btn-icon" onclick="window.location.href='manage.php?table=<?php echo $table; ?>'" title="Clear search">
                            <i class="fas fa-times"></i>
                        </button>
                    <?php endif; ?>
                </form>
                <div class="toolbar-actions">
                    <?php if ($search): ?>
                        <span class="record-count" style="color: #007bff; font-weight: bold;">
                            <?php echo number_format($total_records); ?> result<?php echo $total_records != 1 ? 's' : ''; ?> found
                        </span>
                    <?php else: ?>
                        <span class="record-count"><?php echo number_format($total_records); ?> total records</span>
                    <?php endif; ?>
                    <button class="btn-icon" onclick="location.reload()" title="Refresh">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>

            <!-- Data Table -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="data-table" id="dataTable">
                        <thead>
                            <tr>
                                <?php foreach ($config['display_fields'] as $field): ?>
                                    <th><?php echo ucwords(str_replace('_', ' ', $field)); ?></th>
                                <?php endforeach; ?>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <?php foreach ($config['display_fields'] as $field): ?>
                                            <td>
                                                <?php 
                                                $value = $row[$field] ?? '';
                                                if ($field === 'status') {
                                                    $status_class = $value === 'active' || $value === 'enrolled' || $value === 'approved' ? 'success' : 
                                                                   ($value === 'pending' ? 'warning' : 'danger');
                                                    echo "<span class='badge badge-$status_class'>" . ucfirst($value) . "</span>";
                                                } elseif ($field === 'is_current' || strpos($field, 'is_') === 0) {
                                                    echo $value ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>';
                                                } elseif (strpos($field, 'date') !== false || strpos($field, 'time') !== false) {
                                                    echo $value ? date('M d, Y', strtotime($value)) : '-';
                                                } else {
                                                    echo htmlspecialchars($value);
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="text-center action-buttons">
                                            <button class="btn-action btn-edit" onclick="window.location.href='manage.php?table=<?php echo $table; ?>&action=edit&id=<?php echo $row[$config['primary_key']]; ?>';" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action btn-delete" onclick="if(confirm('Are you sure you want to delete this record?')) { window.location.href='manage.php?table=<?php echo $table; ?>&action=delete&id=<?php echo $row[$config['primary_key']]; ?>'; }" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($config['display_fields']) + 1; ?>" class="text-center">
                                        No records found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?table=<?php echo $table; ?>&page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?table=<?php echo $table; ?>&page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="addEditModal" class="modal" style="display: <?php echo ($action === 'add' || $action === 'edit') ? 'flex' : 'none'; ?>;">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3>
                    <i class="fas <?php echo $action === 'add' ? 'fa-plus' : 'fa-edit'; ?>"></i> 
                    <?php echo $action === 'add' ? 'Add New' : 'Edit'; ?> <?php echo rtrim($config['title'], 's'); ?>
                </h3>
                <button class="modal-close" onclick="window.location.href='manage.php?table=<?php echo $table; ?>';">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 15px; color: #666;">
                    <span style="color: red;">*</span> Required fields
                </p>
                <form method="POST" action="manage.php?table=<?php echo $table; ?>&action=<?php echo $action; ?><?php echo $id ? '&id='.$id : ''; ?>">
                    <?php if ($action === 'edit' && $id): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                    <?php endif; ?>
                    <div class="form-row">
                        <?php foreach ($config['editable_fields'] as $field): 
                            $field_value = $edit_record[$field] ?? '';
                            // Display "Password" instead of "Password Hash"
                            $field_label = $field === 'password_hash' ? 'Password' : ucwords(str_replace('_', ' ', $field));
                            $is_required = in_array($field, $config['required_fields'] ?? []);
                            $is_readonly = ($action === 'edit' && in_array($field, $config['readonly_on_edit'] ?? []));
                            $required_attr = $is_required ? 'required' : '';
                            $readonly_attr = $is_readonly ? 'readonly' : '';
                        ?>
                            <div class="form-group">
                                <label>
                                    <?php echo $field_label; ?>
                                    <?php if ($is_required): ?>
                                        <span style="color: red;">*</span>
                                    <?php endif; ?>
                                    <?php if ($is_readonly): ?>
                                        <span style="color: #888; font-size: 0.85em;">(Cannot be changed)</span>
                                    <?php endif; ?>
                                </label>
                                <?php if ($field === 'password_hash'): ?>
                                    <input type="password" name="<?php echo $field; ?>" class="form-control" 
                                           value="" 
                                           <?php echo ($action === 'add' ? $required_attr : ''); ?>
                                           placeholder="<?php echo $action === 'edit' ? 'Leave blank to keep current password' : 'Enter password'; ?>">
                                    <?php if ($action === 'edit'): ?>
                                        <small style="color: #666;">Leave blank to keep current password</small>
                                    <?php endif; ?>
                                <?php elseif ($field === 'status'): ?>
                                    <select name="<?php echo $field; ?>" class="form-control" <?php echo $required_attr; ?>>
                                        <option value="">-- Select Status --</option>
                                        <option value="active" <?php echo $field_value === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $field_value === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <?php if (in_array($table, ['notes', 'question_solutions'])): ?>
                                            <option value="pending" <?php echo $field_value === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo $field_value === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $field_value === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        <?php endif; ?>
                                        <?php if ($table === 'enrollments'): ?>
                                            <option value="enrolled" <?php echo $field_value === 'enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                                            <option value="dropped" <?php echo $field_value === 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                                            <option value="completed" <?php echo $field_value === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <?php endif; ?>
                                    </select>
                                <?php elseif ($field === 'course_type'): ?>
                                    <select name="<?php echo $field; ?>" class="form-control" <?php echo $required_attr; ?>>
                                        <option value="">-- Select Type --</option>
                                        <option value="theory" <?php echo $field_value === 'theory' ? 'selected' : ''; ?>>Theory</option>
                                        <option value="lab" <?php echo $field_value === 'lab' ? 'selected' : ''; ?>>Lab</option>
                                        <option value="both" <?php echo $field_value === 'both' ? 'selected' : ''; ?>>Both</option>
                                    </select>
                                <?php elseif ($field === 'trimester_type'): ?>
                                    <select name="<?php echo $field; ?>" class="form-control" <?php echo $required_attr; ?>>
                                        <option value="">-- Select Type --</option>
                                        <option value="spring" <?php echo $field_value === 'spring' ? 'selected' : ''; ?>>Spring</option>
                                        <option value="summer" <?php echo $field_value === 'summer' ? 'selected' : ''; ?>>Summer</option>
                                        <option value="fall" <?php echo $field_value === 'fall' ? 'selected' : ''; ?>>Fall</option>
                                    </select>
                                <?php elseif ($field === 'exam_type'): ?>
                                    <select name="<?php echo $field; ?>" class="form-control" <?php echo $required_attr; ?>>
                                        <option value="">-- Select Exam Type --</option>
                                        <option value="midterm" <?php echo $field_value === 'midterm' ? 'selected' : ''; ?>>Midterm</option>
                                        <option value="final" <?php echo $field_value === 'final' ? 'selected' : ''; ?>>Final</option>
                                        <option value="quiz" <?php echo $field_value === 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                                    </select>
                                <?php elseif (strpos($field, 'date') !== false): ?>
                                    <input type="date" name="<?php echo $field; ?>" class="form-control" 
                                           value="<?php echo htmlspecialchars($field_value); ?>" 
                                           <?php echo $required_attr; ?> <?php echo $readonly_attr; ?>>
                                <?php elseif (strpos($field, 'is_') === 0 || $field === 'is_current'): ?>
                                    <select name="<?php echo $field; ?>" class="form-control" <?php echo $required_attr; ?>>
                                        <option value="0" <?php echo $field_value == 0 ? 'selected' : ''; ?>>No</option>
                                        <option value="1" <?php echo $field_value == 1 ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                <?php elseif ($field === 'description' || $field === 'content'): ?>
                                    <textarea name="<?php echo $field; ?>" class="form-control" rows="4" 
                                              <?php echo $required_attr; ?>><?php echo htmlspecialchars($field_value); ?></textarea>
                                <?php elseif (strpos($field, 'email') !== false): ?>
                                    <input type="email" name="<?php echo $field; ?>" class="form-control" 
                                           value="<?php echo htmlspecialchars($field_value); ?>" 
                                           <?php echo $required_attr; ?> <?php echo $readonly_attr; ?>
                                           placeholder="example@uiu.ac.bd">
                                <?php elseif ($field === 'phone'): ?>
                                    <input type="tel" name="<?php echo $field; ?>" class="form-control" 
                                           value="<?php echo htmlspecialchars($field_value); ?>" 
                                           <?php echo $required_attr; ?>
                                           placeholder="+880 1XXX-XXXXXX">
                                <?php elseif (in_array($field, ['credit_hours', 'total_required_credits', 'duration_years', 'points_awarded', 'total_points', 'current_cgpa'])): ?>
                                    <input type="number" step="0.01" name="<?php echo $field; ?>" class="form-control" 
                                           value="<?php echo htmlspecialchars($field_value); ?>" 
                                           <?php echo $required_attr; ?> <?php echo $readonly_attr; ?>
                                           min="0">
                                <?php elseif ($field === 'year'): ?>
                                    <input type="number" name="<?php echo $field; ?>" class="form-control" 
                                           value="<?php echo htmlspecialchars($field_value); ?>" 
                                           <?php echo $required_attr; ?>
                                           min="2020" max="2030" placeholder="2025">
                                <?php elseif (strpos($field, '_id') !== false): ?>
                                    <input type="text" name="<?php echo $field; ?>" class="form-control" 
                                           value="<?php echo htmlspecialchars($field_value); ?>" 
                                           <?php echo $required_attr; ?> <?php echo $readonly_attr; ?>
                                           placeholder="Enter <?php echo $field_label; ?>">
                                <?php else: ?>
                                    <input type="text" name="<?php echo $field; ?>" class="form-control" 
                                           value="<?php echo htmlspecialchars($field_value); ?>" 
                                           <?php echo $required_attr; ?> <?php echo $readonly_attr; ?>>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" onclick="window.location.href='manage.php?table=<?php echo $table; ?>';">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Add' : 'Update'; ?> Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="assets/js/manage.js"></script>
    <script>
        const table = '<?php echo $table; ?>';
        
        function exportData() {
            window.location.href = `export.php?table=${table}`;
        }
        
        // Auto-scroll to form if action is add or edit
        <?php if ($action === 'add' || $action === 'edit'): ?>
        window.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('addEditModal');
            if (modal) {
                modal.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
