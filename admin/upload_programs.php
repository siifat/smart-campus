<?php
/**
 * Upload Programs from CSV
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext !== 'csv') {
        $message = 'Invalid file type. Please upload CSV file.';
        $message_type = 'error';
    } else {
        $uploaded_file = $file['tmp_name'];
        $programs = [];
        
        if (($handle = fopen($uploaded_file, 'r')) !== FALSE) {
            $header = fgetcsv($handle); // Skip header row
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) >= 5 && !empty($data[0])) {
                    $programs[] = [
                        'code' => trim($data[0]),
                        'name' => trim($data[1]),
                        'dept_code' => trim($data[2]),
                        'credits' => (int)trim($data[3]),
                        'duration' => (float)trim($data[4])
                    ];
                }
            }
            fclose($handle);
        }
        
        if (!empty($programs)) {
            $inserted = 0;
            $skipped = 0;
            $errors = [];
            
            foreach ($programs as $prog) {
                // Get department_id from department_code
                $dept_result = $conn->query("SELECT department_id FROM departments WHERE department_code = '" . $conn->real_escape_string($prog['dept_code']) . "'");
                
                if ($dept_result && $dept_result->num_rows > 0) {
                    $dept = $dept_result->fetch_assoc();
                    $dept_id = $dept['department_id'];
                    
                    $stmt = $conn->prepare("INSERT INTO programs (program_code, program_name, department_id, total_required_credits, duration_years) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE program_name = VALUES(program_name), total_required_credits = VALUES(total_required_credits), duration_years = VALUES(duration_years)");
                    $stmt->bind_param('ssiid', $prog['code'], $prog['name'], $dept_id, $prog['credits'], $prog['duration']);
                    
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $inserted++;
                        } else {
                            $skipped++;
                        }
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Department code '{$prog['dept_code']}' not found for program '{$prog['code']}'";
                }
            }
            
            $message = "✅ Upload complete! Inserted/Updated: $inserted programs. Skipped: $skipped";
            if (!empty($errors)) {
                $message .= "<br><br>Errors:<br>" . implode("<br>", $errors);
            }
            $message_type = empty($errors) ? 'success' : 'warning';
        }
    }
}

$result = $conn->query("SELECT p.*, d.department_code, d.department_name FROM programs p JOIN departments d ON p.department_id = d.department_id ORDER BY p.program_code");
$existing_programs = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Programs - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.3);
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-color: #334155;
            --shadow-color: rgba(0, 0, 0, 0.3);
            --glass-bg: rgba(30, 41, 59, 0.7);
            --glass-border: rgba(71, 85, 105, 0.3);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            transition: background 0.3s ease;
        }
        
        [data-theme="light"] body {
            background: linear-gradient(135deg, #fbbf24 0%, #f68b1f 50%, #ea580c 100%);
        }
        
        #particle-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
            position: relative;
            z-index: 1;
        }
        
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: 0 8px 32px var(--shadow-color);
            padding: 30px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px var(--shadow-color);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: var(--text-primary);
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .theme-toggle {
            position: fixed;
            top: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px var(--shadow-color);
        }
        
        .theme-toggle:hover {
            transform: rotate(180deg) scale(1.1);
            box-shadow: 0 6px 30px var(--shadow-color);
        }
        
        .theme-toggle i {
            font-size: 20px;
            color: #f68b1f;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            font-weight: 600;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f68b1f 0%, #fbbf24 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(246, 139, 31, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(246, 139, 31, 0.5);
        }
        
        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid;
            animation: slideInDown 0.5s ease;
        }
        
        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border-color: #10b981;
            color: #10b981;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border-color: #ef4444;
            color: #ef4444;
        }
        
        .alert-warning {
            background: rgba(251, 191, 36, 0.15);
            border-color: #fbbf24;
            color: #f59e0b;
        }
        
        .alert i {
            font-size: 1.5rem;
            margin-top: 2px;
        }
        
        .upload-area {
            border: 3px dashed var(--border-color);
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            background: var(--bg-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .upload-area::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(246, 139, 31, 0.1), transparent);
            transition: 0.5s;
        }
        
        .upload-area:hover::before {
            left: 100%;
        }
        
        .upload-area:hover {
            border-color: #f68b1f;
            background: linear-gradient(135deg, rgba(246, 139, 31, 0.05), rgba(251, 191, 36, 0.05));
            transform: scale(1.02);
        }
        
        .upload-area.drag-over {
            border-color: #f68b1f;
            background: rgba(246, 139, 31, 0.1);
            transform: scale(1.02);
        }
        
        .upload-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #f68b1f;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .upload-area h3 {
            color: var(--text-primary);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .upload-area p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        #fileName {
            margin-top: 15px;
            color: #10b981;
            font-weight: 600;
            font-size: 1rem;
        }
        
        input[type="file"] { display: none; }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        
        th, td {
            padding: 16px;
            text-align: left;
        }
        
        th {
            background: linear-gradient(135deg, rgba(246, 139, 31, 0.1), rgba(251, 191, 36, 0.1));
            color: var(--text-primary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #f68b1f;
        }
        
        th:first-child { border-top-left-radius: 12px; }
        th:last-child { border-top-right-radius: 12px; }
        
        tbody tr {
            background: var(--bg-secondary);
            transition: all 0.3s ease;
        }
        
        tbody tr:hover {
            background: linear-gradient(135deg, rgba(246, 139, 31, 0.05), rgba(251, 191, 36, 0.05));
            transform: translateX(5px);
        }
        
        td {
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
        }
        
        tbody tr:last-child td:first-child { border-bottom-left-radius: 12px; }
        tbody tr:last-child td:last-child { border-bottom-right-radius: 12px; }
        
        .format-guide {
            background: var(--bg-secondary);
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
            border-left: 4px solid #f68b1f;
        }
        
        .format-guide pre {
            background: var(--bg-primary);
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
            overflow-x: auto;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .format-guide ul {
            margin: 15px 0 0 20px;
            line-height: 2;
            color: var(--text-secondary);
        }
        
        code {
            background: linear-gradient(135deg, rgba(246, 139, 31, 0.1), rgba(251, 191, 36, 0.1));
            padding: 4px 8px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            color: #f68b1f;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .container { padding: 20px 15px; }
            .header { flex-direction: column; gap: 15px; }
            .header h1 { font-size: 1.5rem; }
            .glass-card { padding: 20px; }
            .upload-area { padding: 40px 20px; }
            .theme-toggle { top: 15px; right: 15px; }
            table { font-size: 0.8rem; }
            th, td { padding: 10px; }
        }
    </style>
</head>
<body>
    <canvas id="particle-canvas"></canvas>
    
    <div class="theme-toggle" onclick="toggleTheme()">
        <i class="fas fa-moon" id="theme-icon"></i>
    </div>
    
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-user-graduate"></i>
                Upload Programs
            </h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php 
                    echo $message_type === 'success' ? 'check-circle' : 
                        ($message_type === 'error' ? 'exclamation-circle' : 'exclamation-triangle'); 
                ?>"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>
        
        <div class="glass-card">
            <h2 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 1.5rem; font-weight: 600;">
                <i class="fas fa-cloud-upload-alt" style="color: #f68b1f;"></i>
                Upload File
            </h2>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-file-csv upload-icon"></i>
                    <h3>Click to select or drag & drop file</h3>
                    <p style="margin-top: 10px;">Supported format: CSV</p>
                    <p id="fileName"></p>
                </div>
                <input type="file" id="fileInput" name="file" accept=".csv">
                
                <div style="text-align: center; margin-top: 30px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i>
                        Upload & Import
                    </button>
                    <a href="download_template.php?type=programs" class="btn btn-secondary">
                        <i class="fas fa-download"></i>
                        Download Template
                    </a>
                </div>
            </form>
        </div>
        
        <div class="glass-card">
            <h2 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 1.5rem; font-weight: 600;">
                <i class="fas fa-book" style="color: #f68b1f;"></i>
                File Format Guide
            </h2>
            <div class="format-guide">
                <strong style="color: var(--text-primary); font-size: 1.1rem;">CSV Format:</strong>
                <pre>program_code,program_name,department_code,total_required_credits,duration_years
BSc-CSE,Bachelor of Science in Computer Science and Engineering,CSE,150,4
BSc-EEE,Bachelor of Science in Electrical and Electronic Engineering,EEE,148,4
BBA,Bachelor of Business Administration,BBA,126,4
BSc-CE,Bachelor of Science in Civil Engineering,CE,152,4</pre>
                <ul>
                    <li><code>program_code</code>: Unique program identifier</li>
                    <li><code>program_name</code>: Full program name</li>
                    <li><code>department_code</code>: Must exist in Departments table</li>
                    <li><code>total_required_credits</code>: Total credits required for graduation</li>
                    <li><code>duration_years</code>: Duration in years (e.g., 4, 3.5)</li>
                </ul>
                <p style="margin-top: 15px; color: #f68b1f; font-weight: 600;">
                    <i class="fas fa-exclamation-triangle"></i> Important: Department code must exist first!
                </p>
            </div>
        </div>
        
        <div class="glass-card">
            <h2 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 1.5rem; font-weight: 600;">
                <i class="fas fa-list" style="color: #f68b1f;"></i>
                Current Programs
                <span style="background: linear-gradient(135deg, #f68b1f, #fbbf24); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.9rem; margin-left: 10px;">
                    <?php echo count($existing_programs); ?>
                </span>
            </h2>
            
            <?php if (empty($existing_programs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p style="font-size: 1.1rem; margin-top: 10px;">No programs found. Upload a file to add programs.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-code"></i> Code</th>
                                <th><i class="fas fa-book-open"></i> Program Name</th>
                                <th><i class="fas fa-building"></i> Department</th>
                                <th><i class="fas fa-star"></i> Credits</th>
                                <th><i class="fas fa-clock"></i> Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existing_programs as $prog): ?>
                                <tr>
                                    <td><strong style="color: #f68b1f;"><?php echo htmlspecialchars($prog['program_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($prog['program_name']); ?></td>
                                    <td><?php echo htmlspecialchars($prog['department_code']); ?></td>
                                    <td><strong><?php echo $prog['total_required_credits']; ?></strong></td>
                                    <td><?php echo $prog['duration_years']; ?> years</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Theme Toggle
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            const icon = document.getElementById('theme-icon');
            icon.className = newTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }
        
        // Initialize Theme
        (function initTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            const icon = document.getElementById('theme-icon');
            if (icon) {
                icon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            }
        })();
        
        // Particle Animation
        const canvas = document.getElementById('particle-canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        
        const particles = [];
        const particleCount = 80;
        
        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2 + 1;
                this.speedX = Math.random() * 1 - 0.5;
                this.speedY = Math.random() * 1 - 0.5;
                this.opacity = Math.random() * 0.5 + 0.2;
            }
            
            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                
                if (this.x > canvas.width) this.x = 0;
                else if (this.x < 0) this.x = canvas.width;
                
                if (this.y > canvas.height) this.y = 0;
                else if (this.y < 0) this.y = canvas.height;
            }
            
            draw() {
                const theme = document.documentElement.getAttribute('data-theme');
                ctx.fillStyle = theme === 'dark' ? `rgba(251, 191, 36, ${this.opacity})` : `rgba(255, 255, 255, ${this.opacity})`;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }
        
        function initParticles() {
            particles.length = 0;
            for (let i = 0; i < particleCount; i++) {
                particles.push(new Particle());
            }
        }
        
        function animateParticles() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            particles.forEach(particle => {
                particle.update();
                particle.draw();
            });
            
            requestAnimationFrame(animateParticles);
        }
        
        initParticles();
        animateParticles();
        
        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            initParticles();
        });
        
        // File Input Handling
        const fileInput = document.getElementById('fileInput');
        const uploadArea = document.getElementById('uploadArea');
        const fileName = document.getElementById('fileName');
        
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileName.innerHTML = `<i class="fas fa-check-circle"></i> Selected: ${this.files[0].name}`;
            }
        });
        
        // Drag and Drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileName.innerHTML = `<i class="fas fa-check-circle"></i> Selected: ${files[0].name}`;
            }
        });
    </script>
</body>
</html>
