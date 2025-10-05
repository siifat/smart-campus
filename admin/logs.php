<?php
/**
 * Activity Logs System
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

// Create logs table if it doesn't exist
$create_logs_table = "CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT 1,
    action_type VARCHAR(50) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_action_type (action_type)
)";
$conn->query($create_logs_table);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filters
$filter_action = $_GET['filter_action'] ?? '';
$filter_table = $_GET['filter_table'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$where_conditions[] = "DATE(created_at) BETWEEN '$date_from' AND '$date_to'";

if ($filter_action) {
    $where_conditions[] = "action_type = '" . $conn->real_escape_string($filter_action) . "'";
}
if ($filter_table) {
    $where_conditions[] = "table_name = '" . $conn->real_escape_string($filter_table) . "'";
}
if ($search) {
    $search_escaped = $conn->real_escape_string($search);
    $where_conditions[] = "(description LIKE '%$search_escaped%' OR ip_address LIKE '%$search_escaped%')";
}

$where_sql = implode(' AND ', $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) as total FROM activity_logs WHERE $where_sql";
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get logs
$logs_query = "SELECT * FROM activity_logs 
               WHERE $where_sql 
               ORDER BY created_at DESC 
               LIMIT $per_page OFFSET $offset";
$logs_result = $conn->query($logs_query);

// Get unique action types and tables for filters
$action_types = $conn->query("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type");
$table_names = $conn->query("SELECT DISTINCT table_name FROM activity_logs WHERE table_name IS NOT NULL ORDER BY table_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/manage-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filters-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filter-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e4e8;
            border-radius: 8px;
            font-size: 14px;
        }
        .log-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .log-create { background: #e8f5e9; color: #2ed573; }
        .log-update { background: #e3f2fd; color: #1976d2; }
        .log-delete { background: #ffebee; color: #d32f2f; }
        .log-login { background: #f3e5f5; color: #9c27b0; }
        .log-export { background: #fff3e0; color: #f57c00; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="dashboard-container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-history"></i> Activity Logs</h1>
                    <p class="subtitle">Track all system activities and changes</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-outline" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                    <button class="btn btn-primary" onclick="exportLogs()">
                        <i class="fas fa-download"></i> Export Logs
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="filters-bar">
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> From Date</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> To Date</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Action Type</label>
                    <select name="filter_action">
                        <option value="">All Actions</option>
                        <?php while($action = $action_types->fetch_assoc()): ?>
                            <option value="<?php echo $action['action_type']; ?>" 
                                    <?php echo $filter_action == $action['action_type'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst($action['action_type']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-table"></i> Table</label>
                    <select name="filter_table">
                        <option value="">All Tables</option>
                        <?php while($table = $table_names->fetch_assoc()): ?>
                            <option value="<?php echo $table['table_name']; ?>" 
                                    <?php echo $filter_table == $table['table_name'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $table['table_name'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" placeholder="Search logs..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
            </form>

            <!-- Logs Table -->
            <div class="table-card">
                <div class="toolbar">
                    <span class="record-count">
                        <i class="fas fa-list"></i> 
                        <?php echo number_format($total_records); ?> records found
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;"></th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs_result->num_rows > 0): ?>
                                <?php while($log = $logs_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="log-icon log-<?php echo $log['action_type']; ?>">
                                                <i class="fas fa-<?php 
                                                    echo $log['action_type'] == 'create' ? 'plus' : 
                                                         ($log['action_type'] == 'update' ? 'edit' : 
                                                         ($log['action_type'] == 'delete' ? 'trash' : 
                                                         ($log['action_type'] == 'login' ? 'sign-in-alt' : 'file-export'))); 
                                                ?>"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $log['action_type'] == 'create' ? 'success' : 
                                                     ($log['action_type'] == 'update' ? 'info' : 
                                                     ($log['action_type'] == 'delete' ? 'danger' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($log['action_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $log['table_name'] ? ucfirst(str_replace('_', ' ', $log['table_name'])) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                                        <td><code><?php echo $log['ip_address']; ?></code></td>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No activity logs found</p>
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
                            <a href="?page=<?php echo $page - 1; ?>&filter_action=<?php echo $filter_action; ?>&filter_table=<?php echo $filter_table; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>" 
                               class="page-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&filter_action=<?php echo $filter_action; ?>&filter_table=<?php echo $filter_table; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>" 
                               class="page-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function clearFilters() {
            window.location.href = 'logs.php';
        }

        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'logs.php?' + params.toString();
        }
    </script>
</body>
</html>
