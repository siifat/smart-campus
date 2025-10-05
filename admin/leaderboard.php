<?php
/**
 * Student Leaderboard - Top Contributors
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

$filter = $_GET['filter'] ?? 'points';
$program_filter = $_GET['program'] ?? 'all';

// Build query
$where = '';
if ($program_filter !== 'all') {
    $where = "WHERE s.program_id = " . (int)$program_filter;
}

$order_by = $filter === 'cgpa' ? 's.current_cgpa' : 's.total_points';

$query = "SELECT s.student_id, s.full_name, s.total_points, s.current_cgpa, s.current_trimester_number,
                 p.program_name, p.program_code,
                 (SELECT COUNT(*) FROM notes WHERE student_id = s.student_id AND status = 'approved') as notes_count,
                 (SELECT COUNT(*) FROM question_solutions WHERE student_id = s.student_id AND status = 'approved') as solutions_count
          FROM students s
          JOIN programs p ON s.program_id = p.program_id
          $where
          ORDER BY $order_by DESC
          LIMIT 100";

$result = $conn->query($query);

// Get programs for filter
$programs = $conn->query("SELECT program_id, program_name, program_code FROM programs ORDER BY program_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/manage-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .leaderboard-filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-group label {
            font-weight: 600;
        }
        .filter-group select {
            padding: 8px 15px;
            border: 2px solid #e1e4e8;
            border-radius: 8px;
            font-size: 0.95em;
        }
        .rank-position {
            font-size: 1.5em;
            font-weight: bold;
        }
        .rank-1 { color: #FFD700; }
        .rank-2 { color: #C0C0C0; }
        .rank-3 { color: #CD7F32; }
        .medal-icon {
            font-size: 1.8em;
            margin-right: 10px;
        }
        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2em;
        }
        .stat-mini {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            background: var(--light);
            border-radius: 12px;
            font-size: 0.85em;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="dashboard-container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-trophy"></i> Student Leaderboard</h1>
                    <p class="subtitle">Top performing students and contributors</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-outline" onclick="exportData()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="leaderboard-filters">
                <div class="filter-group">
                    <label><i class="fas fa-sort"></i> Rank By:</label>
                    <select onchange="window.location.href='?filter=' + this.value + '&program=<?php echo $program_filter; ?>'">
                        <option value="points" <?php echo $filter === 'points' ? 'selected' : ''; ?>>Total Points</option>
                        <option value="cgpa" <?php echo $filter === 'cgpa' ? 'selected' : ''; ?>>CGPA</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Program:</label>
                    <select onchange="window.location.href='?filter=<?php echo $filter; ?>&program=' + this.value">
                        <option value="all">All Programs</option>
                        <?php while ($prog = $programs->fetch_assoc()): ?>
                            <option value="<?php echo $prog['program_id']; ?>" 
                                    <?php echo $program_filter == $prog['program_id'] ? 'selected' : ''; ?>>
                                <?php echo $prog['program_code']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- Leaderboard Table -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="data-table" id="leaderboardTable">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Rank</th>
                                <th>Student</th>
                                <th>Program</th>
                                <th class="text-center">Total Points</th>
                                <th class="text-center">CGPA</th>
                                <th class="text-center">Contributions</th>
                                <th class="text-center">Trimester</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php $rank = 1; while($student = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php if ($rank <= 3): ?>
                                                <div class="rank-position rank-<?php echo $rank; ?>">
                                                    <?php if ($rank == 1): ?>
                                                        <i class="fas fa-medal medal-icon" style="color: #FFD700;"></i>
                                                    <?php elseif ($rank == 2): ?>
                                                        <i class="fas fa-medal medal-icon" style="color: #C0C0C0;"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-medal medal-icon" style="color: #CD7F32;"></i>
                                                    <?php endif; ?>
                                                    #<?php echo $rank; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size: 1.2em; font-weight: 600; color: #666;">#<?php echo $rank; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 15px;">
                                                <div class="student-avatar">
                                                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                                    <br>
                                                    <span style="color: #666; font-size: 0.9em;"><?php echo $student['student_id']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?php echo $student['program_code']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <strong style="font-size: 1.2em; color: var(--primary);">
                                                <?php echo number_format($student['total_points']); ?>
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            <strong style="font-size: 1.1em;">
                                                <?php echo number_format($student['current_cgpa'], 2); ?>
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="stat-mini">
                                                <i class="fas fa-file-alt"></i> <?php echo $student['notes_count']; ?>
                                            </span>
                                            <span class="stat-mini">
                                                <i class="fas fa-question-circle"></i> <?php echo $student['solutions_count']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $student['current_trimester_number']; ?>
                                        </td>
                                    </tr>
                                <?php $rank++; endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No students found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function exportData() {
            window.location.href = 'export.php?table=leaderboard&filter=<?php echo $filter; ?>&program=<?php echo $program_filter; ?>';
        }
    </script>
</body>
</html>
