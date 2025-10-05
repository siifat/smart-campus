<?php
/**
 * Resources Page - Browse and interact with shared resources
 * Database: uiu_smart_campus
 */
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: ../index.html');
    exit;
}

// Session timeout check (2 hours)
if (isset($_SESSION['session_timeout']) && time() > $_SESSION['session_timeout']) {
    session_destroy();
    header('Location: ../index.html');
    exit;
}
$_SESSION['session_timeout'] = time() + 7200;

require_once('../config/database.php');

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

// Get student's total points
$points_stmt = $conn->prepare("SELECT total_points FROM students WHERE student_id = ?");
$points_stmt->bind_param('s', $student_id);
$points_stmt->execute();
$points_result = $points_stmt->get_result();
$student_data = $points_result->fetch_assoc();
$total_points = $student_data['total_points'] ?? 0;

// Get categories for filter
$categories = $conn->query("SELECT * FROM resource_categories ORDER BY category_name");
if (!$categories) {
    die("Database error: " . $conn->error);
}

// Get courses for filter (may return empty result)
$courses = $conn->query("SELECT DISTINCT c.course_id, c.course_code, c.course_name 
                         FROM courses c 
                         INNER JOIN uploaded_resources ur ON c.course_id = ur.course_id 
                         ORDER BY c.course_code");

// Get trimesters for filter (may return empty result)
$trimesters = $conn->query("SELECT DISTINCT t.trimester_id, t.trimester_name 
                            FROM trimesters t 
                            INNER JOIN uploaded_resources ur ON t.trimester_id = ur.trimester_id 
                            ORDER BY t.trimester_name DESC");

// Page title configuration for topbar
$page_title = 'Resources Library';
$page_icon = 'fas fa-folder-open';
$show_page_title = true;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources Library - UIU Smart Campus</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
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
            --card-bg: rgba(255, 255, 255, 0.9);
            --sidebar-width: 280px;
            --topbar-height: 72px;
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-color: #334155;
            --shadow-color: rgba(0, 0, 0, 0.3);
            --card-bg: rgba(30, 41, 59, 0.9);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #f68b1f 0%, #fbbf24 50%, #f68b1f 100%);
            padding: 24px;
            overflow-y: auto;
            z-index: 100;
            transition: transform 0.3s ease;
            box-shadow: 4px 0 20px rgba(246, 139, 31, 0.15);
        }
        
        [data-theme="dark"] .sidebar {
            background: linear-gradient(180deg, #d97706 0%, #f59e0b 50%, #d97706 100%);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .sidebar-logo i {
            font-size: 32px;
            color: white;
        }
        
        .sidebar-logo span {
            font-size: 20px;
            font-weight: 800;
            color: white;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            margin-bottom: 8px;
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .nav-item i {
            font-size: 20px;
            width: 24px;
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
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 90;
            box-shadow: 0 2px 12px var(--shadow-color);
        }
        
        .search-box {
            flex: 1;
            max-width: 500px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 48px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #f68b1f;
            box-shadow: 0 0 0 3px rgba(246, 139, 31, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .icon-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--bg-secondary);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .icon-btn:hover {
            background: #f68b1f;
            color: white;
            transform: translateY(-2px);
        }
        
        .icon-btn .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            border: 2px solid var(--bg-primary);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 12px;
            background: var(--bg-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-profile:hover {
            background: var(--border-color);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }
        
        /* Glass Cards */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px var(--shadow-color);
            transition: all 0.3s ease;
        }
        
        .glass-card:hover {
            box-shadow: 0 8px 24px var(--shadow-color);
        }
        
        /* Resource Cards */
        .resource-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .resource-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(246, 139, 31, 0.15);
            border-color: #f68b1f;
        }
        
        /* Filter Tags */
        .filter-tag {
            padding: 10px 16px;
            border-radius: 10px;
            background: var(--bg-secondary);
            border: 2px solid transparent;
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-tag:hover {
            background: rgba(246, 139, 31, 0.1);
            border-color: #f68b1f;
        }
        
        .filter-tag.active {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
            border-color: #f68b1f;
            box-shadow: 0 4px 12px rgba(246, 139, 31, 0.3);
        }
        
        /* Category Badge */
        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* Stats Icons */
        .stat-icon {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
        }
        
        /* Input Fields */
        input, select, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #f68b1f;
            box-shadow: 0 0 0 3px rgba(246, 139, 31, 0.1);
        }
        
        /* Buttons */
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
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(246, 139, 31, 0.4);
        }
        
        .btn-secondary {
            padding: 10px 20px;
            border-radius: 10px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-weight: 600;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: rgba(246, 139, 31, 0.1);
            border-color: #f68b1f;
        }
        
        /* Action Buttons */
        .action-btn {
            padding: 10px 16px;
            border-radius: 10px;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn:hover {
            background: rgba(246, 139, 31, 0.1);
            border-color: #f68b1f;
        }
        
        .action-btn.active {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
            border-color: #f68b1f;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-primary);
            border-radius: 20px;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Comment Section */
        .comment-item {
            padding: 16px;
            border-left: 3px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.2s ease;
            background: var(--bg-secondary);
        }
        
        .comment-item:hover {
            border-left-color: #f68b1f;
            background: rgba(246, 139, 31, 0.05);
        }
        
        /* Emoji Picker */
        .emoji-picker {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
            padding: 12px;
            background: var(--bg-secondary);
            border-radius: 12px;
            margin-top: 8px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .emoji-btn {
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 20px;
            background: transparent;
            border: none;
        }
        
        .emoji-btn:hover {
            background: var(--bg-primary);
            transform: scale(1.2);
        }
        
        /* Skeleton Loading */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 16px;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Pagination */
        .pagination-btn {
            padding: 10px 16px;
            border-radius: 10px;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: rgba(246, 139, 31, 0.1);
            border-color: #f68b1f;
        }
        
        .pagination-btn.active {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
            border-color: #f68b1f;
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }
        
        ::-webkit-scrollbar-thumb {
            background: #f68b1f;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #e57a0f;
        }
        
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
        
        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* PDF Preview Modal Styling */
        .pdf-preview-modal .swal2-popup {
            padding: 20px;
        }
        
        .pdf-preview-modal iframe {
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        /* Delete Button Hover Effect */
        .action-btn[style*="fee2e2"]:hover {
            background: #fecaca !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(220, 38, 38, 0.2);
        }
    </style>
</head>
<body>
    <?php require_once('includes/sidebar.php'); ?>
    <?php require_once('includes/topbar.php'); ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Filters Sidebar -->
            <div class="lg:col-span-1">
                <div class="glass-card sticky top-24">
                    <h2 class="text-lg font-bold mb-4" style="color: var(--text-primary);">
                        <i class="fas fa-filter"></i> Filters
                    </h2>
                    
                    <!-- Search -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold mb-2" style="color: var(--text-secondary);">
                            <i class="fas fa-search"></i> Search
                        </label>
                        <input type="text" id="searchInput" 
                               placeholder="Search resources...">
                    </div>
                    
                    <!-- Categories -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold mb-3" style="color: var(--text-secondary);">
                            <i class="fas fa-tags"></i> Category
                        </label>
                        <div class="space-y-2">
                            <button class="filter-tag active w-full" 
                                    data-category="all">
                                <i class="fas fa-th"></i> 
                                <span>All Categories</span>
                            </button>
                            <?php while ($category = $categories->fetch_assoc()): ?>
                            <button class="filter-tag w-full" 
                                    data-category="<?php echo $category['category_id']; ?>">
                                <i class="<?php echo $category['category_icon']; ?>"></i> 
                                <span><?php echo htmlspecialchars($category['category_name']); ?></span>
                            </button>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <!-- Sort -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold mb-2" style="color: var(--text-secondary);">
                            <i class="fas fa-sort"></i> Sort By
                        </label>
                        <select id="sortSelect">
                            <option value="recent">Most Recent</option>
                            <option value="popular">Most Popular</option>
                            <option value="views">Most Viewed</option>
                            <option value="likes">Most Liked</option>
                            <option value="downloads">Most Downloaded</option>
                        </select>
                    </div>
                    
                    <!-- Resource Type -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold mb-3" style="color: var(--text-secondary);">
                            <i class="fas fa-file"></i> Type
                        </label>
                        <div class="space-y-2">
                            <button class="filter-tag active w-full" 
                                    data-type="all">
                                <i class="fas fa-th"></i>
                                <span>All Types</span>
                            </button>
                            <button class="filter-tag w-full" 
                                    data-type="file">
                                <i class="fas fa-file-pdf"></i>
                                <span>Files</span>
                            </button>
                            <button class="filter-tag w-full" 
                                    data-type="youtube">
                                <i class="fab fa-youtube"></i>
                                <span>Videos</span>
                            </button>
                            <button class="filter-tag w-full" 
                                    data-type="google_drive">
                                <i class="fab fa-google-drive"></i>
                                <span>Google Drive</span>
                            </button>
                            <button class="filter-tag w-full" 
                                    data-type="link">
                                <i class="fas fa-link"></i>
                                <span>Links</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Clear Filters -->
                    <button id="clearFilters" class="btn-secondary w-full">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                </div>
            </div>
            
            <!-- Resources Grid -->
            <div class="lg:col-span-3">
                <!-- Stats Bar -->
                <div class="glass-card mb-6 fade-in">
                    <div class="flex items-center justify-between">
                        <div style="color: var(--text-secondary); font-weight: 600;">
                            <i class="fas fa-info-circle"></i>
                            <span id="totalResources">Loading...</span> resources found
                        </div>
                        <div class="flex gap-3">
                            <button id="gridView" class="action-btn active">
                                <i class="fas fa-th"></i>
                            </button>
                            <button id="listView" class="action-btn">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Resources Container -->
                <div id="resourcesContainer" class="grid grid-cols-1 md:grid-cols-2 gap-6 fade-in">
                    <!-- Loading skeleton -->
                    <div class="skeleton h-64"></div>
                    <div class="skeleton h-64"></div>
                    <div class="skeleton h-64"></div>
                    <div class="skeleton h-64"></div>
                </div>
                
                <!-- Pagination -->
                <div id="pagination" class="mt-8 flex justify-center gap-2 flex-wrap">
                    <!-- Pagination will be generated by JS -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Resource Details Modal -->
    <div id="resourceModal" class="modal">
        <div class="modal-content w-full mx-4">
            <div id="modalContentArea">
                <!-- Modal content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // State management
        let currentPage = 1;
        let itemsPerPage = 12;
        let allResources = [];
        let filteredResources = [];
        let currentFilters = {
            category: 'all',
            type: 'all',
            search: '',
            sort: 'recent'
        };
        let viewMode = 'grid';
        
        // Load resources on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadResources();
            setupEventListeners();
        });
        
        // Setup event listeners
        function setupEventListeners() {
            // Search input
            document.getElementById('searchInput').addEventListener('input', debounce(function(e) {
                currentFilters.search = e.target.value;
                filterResources();
            }, 300));
            
            // Sort select
            document.getElementById('sortSelect').addEventListener('change', function(e) {
                currentFilters.sort = e.target.value;
                filterResources();
            });
            
            // Category filters
            document.querySelectorAll('[data-category]').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('[data-category]').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentFilters.category = this.dataset.category;
                    filterResources();
                });
            });
            
            // Type filters
            document.querySelectorAll('[data-type]').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('[data-type]').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentFilters.type = this.dataset.type;
                    filterResources();
                });
            });
            
            // Clear filters
            document.getElementById('clearFilters').addEventListener('click', function() {
                document.getElementById('searchInput').value = '';
                document.getElementById('sortSelect').value = 'recent';
                document.querySelectorAll('[data-category]')[0].click();
                document.querySelectorAll('[data-type]')[0].click();
            });
            
            // View mode toggle
            document.getElementById('gridView').addEventListener('click', function() {
                viewMode = 'grid';
                document.getElementById('gridView').classList.add('active');
                document.getElementById('listView').classList.remove('active');
                renderResources();
            });
            
            document.getElementById('listView').addEventListener('click', function() {
                viewMode = 'list';
                document.getElementById('listView').classList.add('active');
                document.getElementById('gridView').classList.remove('active');
                renderResources();
            });
            
            // Close modal on outside click
            document.getElementById('resourceModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeResourceModal();
                }
            });
        }
        
        // Load resources from API
        async function loadResources() {
            try {
                const response = await fetch('api/resources.php?action=get_all');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Resources API response:', data);
                
                if (data.success) {
                    allResources = data.resources;
                    filteredResources = [...allResources];
                    filterResources();
                } else {
                    console.error('API returned error:', data.message);
                    showError('Failed to load resources: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error loading resources:', error);
                showError('Failed to load resources: ' + error.message);
            }
        }
        
        // Filter resources based on current filters
        function filterResources() {
            filteredResources = allResources.filter(resource => {
                // Category filter
                if (currentFilters.category !== 'all' && resource.category_id != currentFilters.category) {
                    return false;
                }
                
                // Type filter
                if (currentFilters.type !== 'all' && resource.resource_type !== currentFilters.type) {
                    return false;
                }
                
                // Search filter
                if (currentFilters.search) {
                    const searchLower = currentFilters.search.toLowerCase();
                    const matchesTitle = resource.title.toLowerCase().includes(searchLower);
                    const matchesDescription = resource.description?.toLowerCase().includes(searchLower);
                    const matchesCourse = resource.course_code?.toLowerCase().includes(searchLower);
                    
                    if (!matchesTitle && !matchesDescription && !matchesCourse) {
                        return false;
                    }
                }
                
                return true;
            });
            
            // Sort filtered resources
            sortResources();
            
            // Reset to page 1
            currentPage = 1;
            
            // Render
            renderResources();
        }
        
        // Sort resources
        function sortResources() {
            switch (currentFilters.sort) {
                case 'popular':
                    filteredResources.sort((a, b) => 
                        (b.views_count + b.likes_count * 2 + b.downloads_count) - 
                        (a.views_count + a.likes_count * 2 + a.downloads_count)
                    );
                    break;
                case 'views':
                    filteredResources.sort((a, b) => b.views_count - a.views_count);
                    break;
                case 'likes':
                    filteredResources.sort((a, b) => b.likes_count - a.likes_count);
                    break;
                case 'downloads':
                    filteredResources.sort((a, b) => b.downloads_count - a.downloads_count);
                    break;
                case 'recent':
                default:
                    filteredResources.sort((a, b) => new Date(b.uploaded_at) - new Date(a.uploaded_at));
            }
        }
        
        // Render resources
        function renderResources() {
            const container = document.getElementById('resourcesContainer');
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageResources = filteredResources.slice(start, end);
            
            // Update total count
            document.getElementById('totalResources').textContent = filteredResources.length;
            
            // Clear container
            container.innerHTML = '';
            
            // Update grid class based on view mode
            if (viewMode === 'list') {
                container.className = 'space-y-4';
            } else {
                container.className = 'grid grid-cols-1 md:grid-cols-2 gap-6';
            }
            
            // Render resources
            if (pageResources.length === 0) {
                container.innerHTML = `
                    <div class="col-span-2 glass-card text-center py-16">
                        <i class="fas fa-inbox text-6xl mb-4" style="color: var(--text-secondary); opacity: 0.3;"></i>
                        <p style="color: var(--text-secondary); font-size: 18px; font-weight: 600;">No resources found</p>
                        <p style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">Try adjusting your filters</p>
                    </div>
                `;
            } else {
                pageResources.forEach(resource => {
                    if (viewMode === 'grid') {
                        container.appendChild(createResourceCard(resource));
                    } else {
                        container.appendChild(createResourceListItem(resource));
                    }
                });
            }
            
            // Render pagination
            renderPagination();
        }
        
        // Create resource card (grid view)
        function createResourceCard(resource) {
            const card = document.createElement('div');
            card.className = 'resource-card bg-white rounded-xl p-6 cursor-pointer';
            card.onclick = () => openResourceModal(resource.resource_id);
            
            const typeIcon = getResourceTypeIcon(resource.resource_type);
            const categoryColor = resource.category_color || '#6b7280';
            
            card.innerHTML = `
                <div class="flex items-start justify-between mb-3">
                    <div class="category-badge" style="background: ${categoryColor}20; color: ${categoryColor};">
                        <i class="${resource.category_icon}"></i>
                        <span>${resource.category_name}</span>
                    </div>
                    <div class="text-2xl">
                        ${typeIcon}
                    </div>
                </div>
                
                <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-2">
                    ${resource.title}
                </h3>
                
                <p class="text-sm text-gray-600 mb-4 line-clamp-2">
                    ${resource.description || 'No description provided'}
                </p>
                
                ${resource.course_code ? `
                    <div class="text-xs text-gray-500 mb-3">
                        <i class="fas fa-book"></i> ${resource.course_code}
                    </div>
                ` : ''}
                
                <div class="flex items-center justify-between text-sm border-t pt-3">
                    <div class="flex items-center gap-2">
                        <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(resource.student_name)}&background=f68b1f&color=fff&size=32" 
                             class="w-8 h-8 rounded-full" alt="${resource.student_name}"
                             title="Uploaded by ${resource.student_name}">
                        <div>
                            <span class="text-gray-700 font-medium block">${resource.student_name}</span>
                            <span class="text-gray-500 text-xs">Uploader</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between mt-3 text-sm">
                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                        <span>${resource.views_count}</span>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                        <span>${resource.likes_count}</span>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-download"></i>
                        <span>${resource.downloads_count}</span>
                    </div>
                    <div class="stat-icon">
                        <i class="far fa-clock"></i>
                        <span>${formatDate(resource.uploaded_at)}</span>
                    </div>
                </div>
            `;
            
            return card;
        }
        
        // Create resource list item (list view)
        function createResourceListItem(resource) {
            const item = document.createElement('div');
            item.className = 'resource-card bg-white rounded-xl p-6 cursor-pointer flex items-center gap-6';
            item.onclick = () => openResourceModal(resource.resource_id);
            
            const typeIcon = getResourceTypeIcon(resource.resource_type);
            const categoryColor = resource.category_color || '#6b7280';
            
            item.innerHTML = `
                <div class="text-4xl flex-shrink-0">
                    ${typeIcon}
                </div>
                
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="category-badge" style="background: ${categoryColor}20; color: ${categoryColor};">
                            <i class="${resource.category_icon}"></i>
                            <span>${resource.category_name}</span>
                        </div>
                        ${resource.course_code ? `
                            <span class="text-xs text-gray-500">
                                <i class="fas fa-book"></i> ${resource.course_code}
                            </span>
                        ` : ''}
                    </div>
                    
                    <h3 class="text-lg font-bold text-gray-900 mb-1">
                        ${resource.title}
                    </h3>
                    
                    <p class="text-sm text-gray-600 mb-2 line-clamp-1">
                        ${resource.description || 'No description provided'}
                    </p>
                    
                    <div class="flex items-center gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(resource.student_name)}&background=f68b1f&color=fff&size=32" 
                                 class="w-6 h-6 rounded-full" alt="${resource.student_name}">
                            <span class="text-gray-700">${resource.student_name}</span>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-eye"></i>
                            <span>${resource.views_count}</span>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-heart"></i>
                            <span>${resource.likes_count}</span>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-download"></i>
                            <span>${resource.downloads_count}</span>
                        </div>
                        <div class="stat-icon">
                            <i class="far fa-clock"></i>
                            <span>${formatDate(resource.uploaded_at)}</span>
                        </div>
                    </div>
                </div>
            `;
            
            return item;
        }
        
        // Render pagination
        // Render pagination
        function renderPagination() {
            const pagination = document.getElementById('pagination');
            const totalPages = Math.ceil(filteredResources.length / itemsPerPage);
            
            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }
            
            let html = '';
            
            // Previous button
            html += `
                <button onclick="changePage(${currentPage - 1})" 
                        ${currentPage === 1 ? 'disabled' : ''}
                        class="pagination-btn">
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += `
                        <button onclick="changePage(${i})" 
                                class="pagination-btn ${i === currentPage ? 'active' : ''}">
                            ${i}
                        </button>
                    `;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += '<span class="px-2" style="color: var(--text-secondary);">...</span>';
                }
            }
            
            // Next button
            html += `
                <button onclick="changePage(${currentPage + 1})" 
                        ${currentPage === totalPages ? 'disabled' : ''}
                        class="pagination-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;
            
            pagination.innerHTML = html;
        }
        
        // Change page
        function changePage(page) {
            const totalPages = Math.ceil(filteredResources.length / itemsPerPage);
            if (page < 1 || page > totalPages) return;
            
            currentPage = page;
            renderResources();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Open resource details modal
        async function openResourceModal(resourceId) {
            const modal = document.getElementById('resourceModal');
            const content = document.getElementById('modalContentArea');
            
            // Show modal with loading state
            modal.classList.add('active');
            content.innerHTML = '<div class="p-12 text-center"><i class="fas fa-spinner fa-spin text-4xl text-orange-500"></i></div>';
            
            try {
                const response = await fetch(`api/resources.php?action=get_details&resource_id=${resourceId}`);
                const data = await response.json();
                
                if (data.success) {
                    renderResourceDetails(data.resource);
                } else {
                    showError('Failed to load resource details');
                    closeResourceModal();
                }
            } catch (error) {
                console.error('Error loading resource details:', error);
                showError('Failed to load resource details');
                closeResourceModal();
            }
        }
        
        // Render resource details in modal
        function renderResourceDetails(resource) {
            const content = document.getElementById('modalContentArea');
            const typeIcon = getResourceTypeIcon(resource.resource_type);
            const categoryColor = resource.category_color || '#6b7280';
            
            content.innerHTML = `
                <div class="p-8">
                    <!-- Header -->
                    <div class="flex items-start justify-between mb-6">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="category-badge" style="background: ${categoryColor}20; color: ${categoryColor};">
                                    <i class="${resource.category_icon}"></i>
                                    <span>${resource.category_name}</span>
                                </div>
                                ${resource.course_code ? `
                                    <span class="text-sm text-gray-600">
                                        <i class="fas fa-book"></i> ${resource.course_code} - ${resource.course_name}
                                    </span>
                                ` : ''}
                            </div>
                            <h2 class="text-3xl font-bold text-gray-900 mb-2">
                                ${typeIcon} ${resource.title}
                            </h2>
                            <p class="text-gray-600">
                                ${resource.description || 'No description provided'}
                            </p>
                        </div>
                        <button onclick="closeResourceModal()" class="text-gray-400 hover:text-gray-600 text-2xl ml-4">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Uploader Info -->
                    <div class="flex items-center gap-3 mb-6 pb-6 border-b">
                        <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(resource.student_name)}&background=f68b1f&color=fff&size=48" 
                             class="w-12 h-12 rounded-full" alt="${resource.student_name}">
                        <div>
                            <div class="font-semibold text-gray-900">${resource.student_name}</div>
                            <div class="text-sm text-gray-500">Uploaded ${formatDate(resource.uploaded_at)}</div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <button onclick="likeResource(${resource.resource_id})" 
                                id="likeBtn_${resource.resource_id}"
                                class="action-btn ${resource.user_liked ? 'active' : ''} text-center">
                            <i class="fas fa-heart"></i>
                            <span id="likeCount_${resource.resource_id}">${resource.likes_count}</span>
                            Like
                        </button>
                        <button onclick="bookmarkResource(${resource.resource_id})" 
                                id="bookmarkBtn_${resource.resource_id}"
                                class="action-btn ${resource.user_bookmarked ? 'active' : ''} text-center">
                            <i class="fas fa-bookmark"></i>
                            ${resource.user_bookmarked ? 'Bookmarked' : 'Bookmark'}
                        </button>
                        ${resource.resource_type === 'file' ? `
                            ${resource.file_type === 'application/pdf' ? `
                                <button onclick="previewPDF(${resource.resource_id}, '${resource.file_path}', '${resource.title}')" 
                                        class="action-btn text-center">
                                    <i class="fas fa-eye"></i>
                                    Preview PDF
                                </button>
                            ` : ''}
                            <button onclick="downloadResource(${resource.resource_id})" 
                                    class="action-btn text-center ${resource.file_type === 'application/pdf' ? '' : 'col-span-2'}">
                                <i class="fas fa-download"></i>
                                Download (${resource.downloads_count})
                            </button>
                        ` : `
                            <a href="${resource.external_link}" target="_blank" 
                               onclick="trackView(${resource.resource_id})"
                               class="action-btn text-center col-span-2 block">
                                <i class="fas fa-external-link-alt"></i>
                                Open Link
                            </a>
                        `}
                        ${resource.student_id === '<?php echo $student_id; ?>' ? `
                            <button onclick="deleteMyResource(${resource.resource_id}, '${resource.title.replace(/'/g, "\\'")}')" 
                                    class="action-btn text-center col-span-2"
                                    style="background: #fee2e2; color: #991b1b; border-color: #fecaca;">
                                <i class="fas fa-trash-alt"></i>
                                Delete My Resource (-50 Points)
                            </button>
                        ` : ''}
                    </div>
                    
                    <!-- Stats -->
                    <div class="grid grid-cols-4 gap-4 mb-8 p-4 bg-gray-50 rounded-lg">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">${resource.views_count}</div>
                            <div class="text-sm text-gray-600">Views</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">${resource.likes_count}</div>
                            <div class="text-sm text-gray-600">Likes</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">${resource.downloads_count}</div>
                            <div class="text-sm text-gray-600">Downloads</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">${resource.comments_count || 0}</div>
                            <div class="text-sm text-gray-600">Comments</div>
                        </div>
                    </div>
                    
                    <!-- Comments Section -->
                    <div class="border-t pt-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-comments"></i> Comments
                        </h3>
                        
                        <!-- Add Comment -->
                        <div class="mb-6">
                            <textarea id="commentText_${resource.resource_id}" 
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-orange-500 resize-none"
                                      rows="3" placeholder="Add a comment..."></textarea>
                            <div class="flex items-center justify-between mt-2">
                                <button onclick="toggleEmojiPicker(${resource.resource_id})" class="text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-smile text-xl"></i>
                                </button>
                                <button onclick="addComment(${resource.resource_id})" 
                                        class="px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                                    <i class="fas fa-paper-plane"></i> Post Comment
                                </button>
                            </div>
                            <div id="emojiPicker_${resource.resource_id}" class="emoji-picker hidden">
                                ${['😀','😃','😄','😁','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🥸','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','💩','👻','💀','☠️','👽','👾','🤖','🎃','😺','😸','😹','😻','😼','😽','🙀','😿','😾'].map(emoji => `<button class="emoji-btn" onclick="insertEmoji('${emoji}', ${resource.resource_id})">${emoji}</button>`).join('')}
                            </div>
                        </div>
                        
                        <!-- Comments List -->
                        <div id="commentsList_${resource.resource_id}" class="comment-section space-y-4">
                            ${resource.comments && resource.comments.length > 0 ? 
                                resource.comments.map(comment => createCommentHTML(comment, resource.resource_id)).join('') :
                                '<p class="text-gray-500 text-center py-8">No comments yet. Be the first to comment!</p>'
                            }
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Create comment HTML
        function createCommentHTML(comment, resourceId) {
            return `
                <div class="comment-item pl-4 py-3">
                    <div class="flex items-start gap-3">
                        <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(comment.student_name)}&background=random&size=40" 
                             class="w-10 h-10 rounded-full flex-shrink-0" alt="${comment.student_name}">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-semibold text-gray-900">${comment.student_name}</span>
                                <span class="text-xs text-gray-500">${formatDate(comment.commented_at)}</span>
                            </div>
                            <p class="text-gray-700">${comment.comment_text}</p>
                        </div>
                        ${comment.student_id === '<?php echo $student_id; ?>' ? `
                            <button onclick="deleteComment(${comment.comment_id}, ${resourceId})" 
                                    class="text-gray-400 hover:text-red-500 transition">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }
        
        // Close modal
        function closeResourceModal() {
            document.getElementById('resourceModal').classList.remove('active');
        }
        
        // Like resource
        async function likeResource(resourceId) {
            try {
                const response = await fetch('api/resources.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggle_like', resource_id: resourceId })
                });
                
                const data = await response.json();
                if (data.success) {
                    const btn = document.getElementById(`likeBtn_${resourceId}`);
                    const count = document.getElementById(`likeCount_${resourceId}`);
                    
                    if (data.liked) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                    
                    count.textContent = data.likes_count;
                    
                    // Update in allResources array
                    const resource = allResources.find(r => r.resource_id == resourceId);
                    if (resource) {
                        resource.likes_count = data.likes_count;
                        resource.user_liked = data.liked;
                    }
                }
            } catch (error) {
                console.error('Error liking resource:', error);
            }
        }
        
        // Bookmark resource
        async function bookmarkResource(resourceId) {
            try {
                const response = await fetch('api/resources.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggle_bookmark', resource_id: resourceId })
                });
                
                const data = await response.json();
                if (data.success) {
                    const btn = document.getElementById(`bookmarkBtn_${resourceId}`);
                    
                    if (data.bookmarked) {
                        btn.classList.add('active');
                        btn.innerHTML = '<i class="fas fa-bookmark"></i> Bookmarked';
                        showSuccess('Resource bookmarked!');
                    } else {
                        btn.classList.remove('active');
                        btn.innerHTML = '<i class="fas fa-bookmark"></i> Bookmark';
                        showSuccess('Bookmark removed');
                    }
                    
                    // Update in allResources array
                    const resource = allResources.find(r => r.resource_id == resourceId);
                    if (resource) {
                        resource.user_bookmarked = data.bookmarked;
                    }
                }
            } catch (error) {
                console.error('Error bookmarking resource:', error);
            }
        }
        
        // Download resource
        async function downloadResource(resourceId) {
            try {
                // Track download
                await fetch('api/resources.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'track_download', resource_id: resourceId })
                });
                
                // Trigger download
                window.location.href = `api/resources.php?action=download&resource_id=${resourceId}`;
                
                // Update download count in UI
                setTimeout(() => {
                    openResourceModal(resourceId);
                }, 1000);
            } catch (error) {
                console.error('Error downloading resource:', error);
            }
        }
        
        // Track view
        async function trackView(resourceId) {
            try {
                await fetch('api/resources.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'track_view', resource_id: resourceId })
                });
            } catch (error) {
                console.error('Error tracking view:', error);
            }
        }
        
        // Add comment
        async function addComment(resourceId) {
            const textarea = document.getElementById(`commentText_${resourceId}`);
            const commentText = textarea.value.trim();
            
            if (!commentText) {
                showError('Please enter a comment');
                return;
            }
            
            try {
                const response = await fetch('api/resources.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_comment',
                        resource_id: resourceId,
                        comment_text: commentText
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    showSuccess('Comment posted!');
                    textarea.value = '';
                    
                    // Reload resource details to show new comment
                    openResourceModal(resourceId);
                } else {
                    showError(data.message || 'Failed to post comment');
                }
            } catch (error) {
                console.error('Error adding comment:', error);
                showError('Failed to post comment');
            }
        }
        
        // Delete comment
        async function deleteComment(commentId, resourceId) {
            const confirm = await Swal.fire({
                title: 'Delete Comment?',
                text: 'This action cannot be undone',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            });
            
            if (!confirm.isConfirmed) return;
            
            try {
                const response = await fetch('api/resources.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_comment',
                        comment_id: commentId
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    showSuccess('Comment deleted');
                    openResourceModal(resourceId);
                } else {
                    showError(data.message || 'Failed to delete comment');
                }
            } catch (error) {
                console.error('Error deleting comment:', error);
                showError('Failed to delete comment');
            }
        }
        
        // Toggle emoji picker
        function toggleEmojiPicker(resourceId) {
            const picker = document.getElementById(`emojiPicker_${resourceId}`);
            picker.classList.toggle('hidden');
        }
        
        // Insert emoji
        function insertEmoji(emoji, resourceId) {
            const textarea = document.getElementById(`commentText_${resourceId}`);
            textarea.value += emoji;
            textarea.focus();
        }
        
        // Utility functions
        function getResourceTypeIcon(type) {
            const icons = {
                'file': '<i class="fas fa-file-pdf text-red-500"></i>',
                'youtube': '<i class="fab fa-youtube text-red-600"></i>',
                'google_drive': '<i class="fab fa-google-drive text-green-500"></i>',
                'link': '<i class="fas fa-link text-blue-500"></i>',
                'other_cloud': '<i class="fas fa-cloud text-blue-400"></i>'
            };
            return icons[type] || icons['file'];
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays < 7) return `${diffDays}d ago`;
            
            return date.toLocaleDateString();
        }
        
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        function showSuccess(message) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: message,
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }
        
        function showError(message) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                confirmButtonColor: '#f68b1f'
            });
        }
        
        // Delete resource function with point deduction warning
        async function deleteMyResource(resourceId, resourceTitle) {
            const result = await Swal.fire({
                title: 'Delete Resource?',
                html: `
                    <p class="text-gray-700 mb-4">Are you sure you want to delete:</p>
                    <p class="font-bold text-lg mb-4">"${resourceTitle}"</p>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                        <p class="text-red-800 font-semibold"><i class="fas fa-exclamation-triangle"></i> Warning:</p>
                        <ul class="text-red-700 text-sm mt-2 text-left list-disc list-inside">
                            <li>This action cannot be undone</li>
                            <li>The file will be permanently deleted</li>
                            <li><strong class="text-red-900">50 points will be deducted</strong> from your total</li>
                            <li>All likes, comments, and bookmarks will be lost</li>
                        </ul>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Delete (-50 Points)',
                cancelButtonText: 'Cancel',
                width: '600px'
            });
            
            if (result.isConfirmed) {
                try {
                    console.log('Deleting resource:', resourceId);
                    const response = await fetch('api/resources.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'delete_resource',
                            resource_id: resourceId
                        })
                    });
                    
                    console.log('Response status:', response.status);
                    const responseText = await response.text();
                    console.log('Response text:', responseText);
                    
                    const data = JSON.parse(responseText);
                    console.log('Parsed data:', data);
                    
                    if (data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            html: `
                                <p>Resource has been deleted.</p>
                                <p class="text-red-600 font-semibold mt-2">-50 points deducted</p>
                                <p class="text-gray-600 mt-2">New balance: ${data.new_points} points</p>
                            `,
                            confirmButtonColor: '#f68b1f'
                        });
                        
                        // Reload page to refresh everything
                        window.location.reload();
                    } else {
                        showError(data.message || 'Failed to delete resource');
                    }
                } catch (error) {
                    console.error('Error deleting resource:', error);
                    showError('An error occurred while deleting the resource');
                }
            }
        }
        
        // Preview PDF in modal
        function previewPDF(resourceId, filePath, title) {
            Swal.fire({
                title: title,
                html: `
                    <div style="width: 100%; height: 70vh;">
                        <iframe src="../${filePath}" 
                                style="width: 100%; height: 100%; border: none;" 
                                type="application/pdf">
                        </iframe>
                    </div>
                `,
                width: '90%',
                showConfirmButton: true,
                confirmButtonText: '<i class="fas fa-download"></i> Download',
                confirmButtonColor: '#f68b1f',
                showCancelButton: true,
                cancelButtonText: 'Close',
                customClass: {
                    container: 'pdf-preview-modal'
                },
                didOpen: () => {
                    // Track view
                    trackView(resourceId);
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    downloadResource(resourceId);
                }
            });
        }
    </script>
</body>
</html>
