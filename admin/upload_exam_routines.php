<?php
/**
 * Admin - Upload Exam Routines
 * Upload Midterm/Final exam schedules by department and trimester
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

// Get all departments
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name");

// Get all trimesters
$trimesters = $conn->query("SELECT * FROM trimesters ORDER BY year DESC, trimester_code DESC");

// Get current trimester
$current_trimester = $conn->query("SELECT * FROM trimesters WHERE is_current = 1 LIMIT 1")->fetch_assoc();

// Get statistics
$stats_query = "SELECT 
    department_id,
    trimester_id,
    exam_type,
    COUNT(*) as total_entries,
    COUNT(DISTINCT course_code) as unique_courses,
    upload_date,
    original_filename
FROM exam_routines
GROUP BY department_id, trimester_id, exam_type
ORDER BY upload_date DESC";
$stats_result = $conn->query($stats_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Exam Routines - Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .upload-form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .form-group select,
        .form-group input {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .file-drop-zone {
            border: 3px dashed #cbd5e0;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background: #f7fafc;
            transition: all 0.3s;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .file-drop-zone:hover,
        .file-drop-zone.drag-over {
            border-color: #667eea;
            background: #edf2f7;
        }
        
        .file-drop-zone i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .file-info {
            background: #e6fffa;
            border-left: 4px solid #38b2ac;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .file-info.show {
            display: block;
        }
        
        .stats-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .stats-table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert.show {
            display: block;
        }
        
        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        
        .alert-info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .delete-btn:hover {
            background: #c82333;
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
                    <h1><i class="fas fa-calendar-check"></i> Upload Exam Routines</h1>
                    <p class="subtitle">Upload Midterm/Final exam schedules for departments and trimesters</p>
                </div>
            </div>

            <!-- Alert Messages -->
            <div id="alertSuccess" class="alert alert-success">
                <i class="fas fa-check-circle"></i> <span id="successMessage"></span>
            </div>
            <div id="alertError" class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <span id="errorMessage"></span>
            </div>

            <!-- Upload Form -->
            <div class="upload-form-container">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-upload"></i> Upload New Exam Routine</h3>
                
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="department_id">Department *</label>
                            <select id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php while ($dept = $departments->fetch_assoc()): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?> (<?php echo htmlspecialchars($dept['department_code']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="trimester_id">Trimester *</label>
                            <select id="trimester_id" name="trimester_id" required>
                                <option value="">Select Trimester</option>
                                <?php 
                                $trimesters->data_seek(0);
                                while ($trim = $trimesters->fetch_assoc()): 
                                    $is_current = $current_trimester && $trim['trimester_id'] == $current_trimester['trimester_id'];
                                ?>
                                    <option value="<?php echo $trim['trimester_id']; ?>" <?php echo $is_current ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($trim['trimester_name']); ?> 
                                        <?php echo $is_current ? '(Current)' : ''; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="exam_type">Exam Type *</label>
                            <select id="exam_type" name="exam_type" required>
                                <option value="">Select Exam Type</option>
                                <option value="Midterm">Midterm</option>
                                <option value="Final">Final</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info show" style="margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <strong>File Format:</strong> Upload CSV file with columns: Dept., Course Code, Course Title, Section, Teacher, Exam Date, Exam Time, Room
                    </div>
                    
                    <div class="file-drop-zone" id="dropZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h3>Drag & Drop CSV File Here</h3>
                        <p>or click to browse</p>
                        <input type="file" id="exam_file" name="exam_file" accept=".csv" style="display: none;" required>
                    </div>
                    
                    <div class="file-info" id="fileInfo">
                        <strong><i class="fas fa-file-csv"></i> Selected File:</strong> <span id="fileName"></span>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-upload"></i> Upload Exam Routine
                    </button>
                </form>
            </div>

            <!-- Uploaded Routines Statistics -->
            <div class="stats-table">
                <div class="stats-table-header">
                    <h3><i class="fas fa-list"></i> Uploaded Exam Routines</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Trimester</th>
                                <th>Exam Type</th>
                                <th>Entries</th>
                                <th>Courses</th>
                                <th>Filename</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stats_result->num_rows > 0): ?>
                                <?php while($stat = $stats_result->fetch_assoc()): 
                                    $dept = $conn->query("SELECT department_name, department_code FROM departments WHERE department_id = " . $stat['department_id'])->fetch_assoc();
                                    $trim = $conn->query("SELECT trimester_name FROM trimesters WHERE trimester_id = " . $stat['trimester_id'])->fetch_assoc();
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($dept['department_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($trim['trimester_name']); ?></td>
                                        <td><span class="badge badge-<?php echo $stat['exam_type'] == 'Midterm' ? 'info' : 'warning'; ?>"><?php echo $stat['exam_type']; ?></span></td>
                                        <td><?php echo $stat['total_entries']; ?></td>
                                        <td><?php echo $stat['unique_courses']; ?></td>
                                        <td><?php echo htmlspecialchars($stat['original_filename']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($stat['upload_date'])); ?></td>
                                        <td>
                                            <button class="delete-btn" onclick="deleteRoutine(<?php echo $stat['department_id']; ?>, <?php echo $stat['trimester_id']; ?>, '<?php echo $stat['exam_type']; ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center">No exam routines uploaded yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // File drop zone functionality
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('exam_file');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const uploadForm = document.getElementById('uploadForm');
        const submitBtn = document.getElementById('submitBtn');

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                displayFileName(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                displayFileName(e.target.files[0]);
            }
        });

        function displayFileName(file) {
            fileName.textContent = file.name;
            fileInfo.classList.add('show');
        }

        // Form submission
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(uploadForm);
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            
            try {
                const response = await fetch('api/upload_exam_routine.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    uploadForm.reset();
                    fileInfo.classList.remove('show');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('error', result.message || 'Upload failed');
                }
            } catch (error) {
                showAlert('error', 'Network error: ' + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Exam Routine';
            }
        });

        function showAlert(type, message) {
            const alertSuccess = document.getElementById('alertSuccess');
            const alertError = document.getElementById('alertError');
            
            if (type === 'success') {
                document.getElementById('successMessage').textContent = message;
                alertSuccess.classList.add('show');
                alertError.classList.remove('show');
            } else {
                document.getElementById('errorMessage').textContent = message;
                alertError.classList.add('show');
                alertSuccess.classList.remove('show');
            }
            
            setTimeout(() => {
                alertSuccess.classList.remove('show');
                alertError.classList.remove('show');
            }, 5000);
        }

        async function deleteRoutine(deptId, trimId, examType) {
            if (!confirm(`Are you sure you want to delete this ${examType} exam routine?`)) {
                return;
            }
            
            try {
                const response = await fetch('api/delete_exam_routine.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        department_id: deptId,
                        trimester_id: trimId,
                        exam_type: examType
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('error', result.message);
                }
            } catch (error) {
                showAlert('error', 'Network error: ' + error.message);
            }
        }
    </script>
</body>
</html>
