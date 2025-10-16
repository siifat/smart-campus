<?php
/**
 * PDF/Resource Viewer - Display resources using WebViewer
 * Database: uiu_smart_campus
 */
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Location: ../login.html?error=unauthorized');
    exit;
}

require_once('../config/database.php');

$student_id = $_SESSION['student_id'];
$resource_id = intval($_GET['id'] ?? 0);

if ($resource_id <= 0) {
    die('Invalid resource ID');
}

// Get resource details
$stmt = $conn->prepare("
    SELECT 
        ur.*,
        rc.category_name,
        rc.category_icon,
        c.course_code,
        c.course_name,
        s.full_name as student_name
    FROM uploaded_resources ur
    LEFT JOIN resource_categories rc ON ur.category_id = rc.category_id
    LEFT JOIN courses c ON ur.course_id = c.course_id
    LEFT JOIN students s ON ur.student_id = s.student_id
    WHERE ur.resource_id = ? AND ur.is_approved = 1
");
$stmt->bind_param('i', $resource_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Resource not found');
}

$resource = $result->fetch_assoc();
$stmt->close();

// Track view
$check_stmt = $conn->prepare("
    SELECT view_id FROM resource_views 
    WHERE resource_id = ? AND student_id = ? 
    AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$check_stmt->bind_param('is', $resource_id, $student_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $insert_stmt = $conn->prepare("INSERT INTO resource_views (resource_id, student_id) VALUES (?, ?)");
    $insert_stmt->bind_param('is', $resource_id, $student_id);
    $insert_stmt->execute();
    
    $update_stmt = $conn->prepare("UPDATE uploaded_resources SET views_count = views_count + 1 WHERE resource_id = ?");
    $update_stmt->bind_param('i', $resource_id);
    $update_stmt->execute();
}

// Determine file path
$file_path = '../' . $resource['file_path'];
$file_url = $resource['file_path']; // Relative URL for WebViewer
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($resource['title']); ?> - UIU Smart Campus</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- WebViewer Library -->
    <script src="../UIUQuestionBank/WebViewer/lib/webviewer.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --topbar-height: 64px;
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-color: #334155;
            --shadow-color: rgba(0, 0, 0, 0.3);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            overflow: hidden;
        }
        
        .viewer-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--topbar-height);
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            box-shadow: 0 2px 12px rgba(246, 139, 31, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
        }
        
        .viewer-topbar .left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
            min-width: 0;
        }
        
        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-2px);
        }
        
        .resource-info {
            flex: 1;
            min-width: 0;
        }
        
        .resource-title {
            font-size: 18px;
            font-weight: 700;
            color: white;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .resource-meta {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .resource-meta i {
            margin-right: 4px;
        }
        
        .viewer-topbar .right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        
        .action-btn {
            padding: 10px 18px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .action-btn i {
            font-size: 16px;
        }
        
        .menu-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            font-size: 18px;
            position: relative;
        }
        
        .menu-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        #viewer {
            position: fixed;
            top: var(--topbar-height);
            left: 0;
            right: 0;
            bottom: 0;
            background: #525659;
        }
        
        .dropdown-menu {
            position: absolute;
            top: calc(var(--topbar-height) + 8px);
            right: 20px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 40px var(--shadow-color);
            z-index: 1001;
            min-width: 200px;
            display: none;
            animation: slideDown 0.2s ease;
        }
        
        .dropdown-menu.active {
            display: block;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dropdown-item {
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-primary);
            text-decoration: none;
            border-bottom: 1px solid var(--border-color);
        }
        
        .dropdown-item:last-child {
            border-bottom: none;
            border-radius: 0 0 12px 12px;
        }
        
        .dropdown-item:first-child {
            border-radius: 12px 12px 0 0;
        }
        
        .dropdown-item:hover {
            background: var(--bg-secondary);
        }
        
        .dropdown-item i {
            font-size: 16px;
            width: 20px;
            color: #f68b1f;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-content {
            text-align: center;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--border-color);
            border-top-color: #f68b1f;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-text {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .loading-subtext {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .btn-primary {
            padding: 12px 24px;
            border-radius: 12px;
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(246, 139, 31, 0.3);
            text-decoration: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(246, 139, 31, 0.4);
        }
        
        @media (max-width: 768px) {
            .resource-title {
                font-size: 16px;
            }
            
            .resource-meta {
                font-size: 12px;
            }
            
            .action-btn span {
                display: none;
            }
            
            .action-btn {
                padding: 10px 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <div class="loading-text">Loading Document</div>
            <div class="loading-subtext">Please wait...</div>
        </div>
    </div>
    
    <!-- Topbar -->
    <div class="viewer-topbar">
        <div class="left">
            <button class="back-btn" onclick="goBack()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="resource-info">
                <div class="resource-title"><?php echo htmlspecialchars($resource['title']); ?></div>
                <div class="resource-meta">
                    <?php if ($resource['course_code']): ?>
                        <span><i class="fas fa-book"></i><?php echo htmlspecialchars($resource['course_code']); ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-user"></i><?php echo htmlspecialchars($resource['student_name']); ?></span>
                    <span><i class="fas fa-eye"></i><?php echo number_format($resource['views_count']); ?> views</span>
                </div>
            </div>
        </div>
        <div class="right">
            <a href="api/resources.php?action=download&resource_id=<?php echo $resource_id; ?>" 
               class="action-btn" title="Download">
                <i class="fas fa-download"></i>
                <span>Download</span>
            </a>
            <button class="menu-btn" onclick="toggleMenu()" id="menuBtn">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
    </div>
    
    <!-- Dropdown Menu -->
    <div class="dropdown-menu" id="dropdownMenu">
        <a href="resources.php" class="dropdown-item">
            <i class="fas fa-folder-open"></i>
            <span>Back to Resources</span>
        </a>
        <a href="api/resources.php?action=download&resource_id=<?php echo $resource_id; ?>" class="dropdown-item">
            <i class="fas fa-download"></i>
            <span>Download PDF</span>
        </a>
        <div class="dropdown-item" onclick="toggleBookmark()">
            <i class="fas fa-bookmark" id="bookmarkIcon"></i>
            <span id="bookmarkText">Bookmark</span>
        </div>
        <div class="dropdown-item" onclick="toggleLike()">
            <i class="fas fa-heart" id="likeIcon"></i>
            <span id="likeText">Like</span>
        </div>
        <a href="resources.php?id=<?php echo $resource_id; ?>" class="dropdown-item">
            <i class="fas fa-info-circle"></i>
            <span>Resource Details</span>
        </a>
        <?php if ($resource['student_id'] === $student_id): ?>
        <div class="dropdown-item" onclick="deleteMyResource()" style="color: #dc2626;">
            <i class="fas fa-trash-alt" style="color: #dc2626;"></i>
            <span>Delete My Resource (-50 Points)</span>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- PDF Viewer Container -->
    <div id="viewer"></div>

    <script>
        const resourceId = <?php echo $resource_id; ?>;
        const filePath = '<?php echo addslashes($file_url); ?>';
        
        // Initialize WebViewer
        WebViewer({
            path: '../UIUQuestionBank/WebViewer/lib',
            licenseKey: 'ECxAg7jf3wu94z4yftWW',
            initialDoc: '../' + filePath,
            disabledElements: [
                'menuButton',
                'contextMenuPopup'
            ],
            enableMeasurement: true,
        }, document.getElementById('viewer'))
        .then(instance => {
            // Hide loading overlay when document is loaded
            instance.UI.addEventListener('documentLoaded', () => {
                document.getElementById('loadingOverlay').style.display = 'none';
            });
            
            // Add fullscreen button
            instance.UI.setHeaderItems((header) => {
                header.getHeader('default').push({
                    img: "icon-header-full-screen",
                    type: "actionButton",
                    element: 'fullScreenButton',
                    onClick: () => {
                        instance.UI.toggleFullScreen();
                    },
                    title: 'Full Screen',
                });
            });
            
            // Set theme based on user's system preference or default to light
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                instance.UI.setTheme(instance.UI.Theme.DARK);
            } else {
                instance.UI.setTheme(instance.UI.Theme.LIGHT);
            }
        })
        .catch(error => {
            console.error('WebViewer Error:', error);
            document.querySelector('.loading-text').textContent = 'Error Loading Document';
            document.querySelector('.loading-subtext').textContent = 'Please try downloading the file instead';
            
            // Show download button if error occurs
            const loadingOverlay = document.getElementById('loadingOverlay');
            const downloadBtn = document.createElement('a');
            downloadBtn.href = 'api/resources.php?action=download&resource_id=' + resourceId;
            downloadBtn.className = 'btn-primary';
            downloadBtn.style.marginTop = '20px';
            downloadBtn.style.display = 'inline-block';
            downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download File';
            loadingOverlay.querySelector('.loading-content').appendChild(downloadBtn);
        });
        
        // Go back function
        function goBack() {
            if (window.history.length >= 2) {
                window.history.back();
            } else {
                window.location.href = 'resources.php';
            }
        }
        
        // Toggle dropdown menu
        function toggleMenu() {
            const menu = document.getElementById('dropdownMenu');
            menu.classList.toggle('active');
        }
        
        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            const menu = document.getElementById('dropdownMenu');
            const menuBtn = document.getElementById('menuBtn');
            
            if (!menu.contains(e.target) && !menuBtn.contains(e.target)) {
                menu.classList.remove('active');
            }
        });
        
        // Bookmark functionality
        async function toggleBookmark() {
            try {
                const response = await fetch('api/resources.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggle_bookmark', resource_id: resourceId })
                });
                
                const data = await response.json();
                if (data.success) {
                    const icon = document.getElementById('bookmarkIcon');
                    const text = document.getElementById('bookmarkText');
                    
                    if (data.bookmarked) {
                        icon.style.color = '#fbbf24';
                        text.textContent = 'Bookmarked';
                    } else {
                        icon.style.color = '#f68b1f';
                        text.textContent = 'Bookmark';
                    }
                }
            } catch (error) {
                console.error('Bookmark error:', error);
            }
        }
        
        // Like functionality
        async function toggleLike() {
            try {
                const response = await fetch('api/resources.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggle_like', resource_id: resourceId })
                });
                
                const data = await response.json();
                if (data.success) {
                    const icon = document.getElementById('likeIcon');
                    const text = document.getElementById('likeText');
                    
                    if (data.liked) {
                        icon.style.color = '#ef4444';
                        text.textContent = 'Liked';
                    } else {
                        icon.style.color = '#f68b1f';
                        text.textContent = 'Like';
                    }
                }
            } catch (error) {
                console.error('Like error:', error);
            }
        }
        
        // Delete resource function
        async function deleteMyResource() {
            const result = await Swal.fire({
                title: 'Delete Resource?',
                html: `
                    <div style="text-align: left; margin-top: 10px;">
                        <p style="margin-bottom: 15px; color: #64748b;">Are you sure you want to delete this resource?</p>
                        <div style="background: #fee2e2; padding: 15px; border-radius: 8px; border-left: 4px solid #dc2626;">
                            <strong style="color: #991b1b;">⚠️ Warning:</strong>
                            <ul style="margin: 10px 0 0 20px; color: #991b1b;">
                                <li>This action cannot be undone</li>
                                <li><strong>50 points will be deducted</strong> from your balance</li>
                                <li>The file will be permanently deleted</li>
                            </ul>
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Delete Resource',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'rounded-lg',
                    confirmButton: 'rounded-lg px-6 py-3',
                    cancelButton: 'rounded-lg px-6 py-3'
                }
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('api/resources.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'delete_resource',
                            resource_id: resourceId
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        await Swal.fire({
                            title: 'Deleted!',
                            html: `
                                <p>Your resource has been deleted.</p>
                                <p style="color: #dc2626; font-weight: 600; margin-top: 10px;">
                                    -50 points deducted
                                </p>
                                <p style="color: #64748b; margin-top: 5px;">
                                    New balance: <strong>${data.new_points} points</strong>
                                </p>
                            `,
                            icon: 'success',
                            confirmButtonColor: '#f68b1f',
                            customClass: {
                                popup: 'rounded-lg',
                                confirmButton: 'rounded-lg px-6 py-3'
                            }
                        });
                        
                        // Redirect back to resources page
                        window.location.href = 'resources.php';
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to delete resource',
                            icon: 'error',
                            confirmButtonColor: '#f68b1f'
                        });
                    }
                } catch (error) {
                    console.error('Delete error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred while deleting the resource',
                        icon: 'error',
                        confirmButtonColor: '#f68b1f'
                    });
                }
            }
        }
        
        // Theme toggle
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // ESC to go back
            if (e.key === 'Escape') {
                goBack();
            }
        });
    </script>
</body>
</html>
