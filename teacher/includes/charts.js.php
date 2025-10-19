<?php
// This file generates the Chart.js initialization code
// Called from grades.php with $chart_data already defined
?>
<script>
    // Chart initialization - FIXED version (no responsive mode)
    console.log('Initializing charts v5.0 (responsive disabled)...');
    
    const chartData = <?php echo json_encode($chart_data); ?>;
    console.log('Chart data:', chartData);
    
    // Destroy any existing chart instances to prevent duplicates
    if (window.gradeChart) { window.gradeChart.destroy(); window.gradeChart = null; }
    if (window.trendChart) { window.trendChart.destroy(); window.trendChart = null; }
    if (window.statsChart) { window.statsChart.destroy(); window.statsChart = null; }
    
    // Grade Distribution Chart
    const gradeCanvas = document.getElementById('gradeDistChart');
    if (gradeCanvas && chartData.gradeDistribution.labels.length > 0) {
        window.gradeChart = new Chart(gradeCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: chartData.gradeDistribution.labels,
                datasets: [{
                    data: chartData.gradeDistribution.values,
                    backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4']
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                animation: false
            }
        });
        console.log('✓ Grade Distribution chart created');
    }
    
    // Assignment Trend Chart  
    const trendCanvas = document.getElementById('assignmentTrendChart');
    if (trendCanvas && chartData.assignmentTrend.labels.length > 0) {
        window.trendChart = new Chart(trendCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.assignmentTrend.labels,
                datasets: [{
                    label: 'Average Marks',
                    data: chartData.assignmentTrend.avgMarks,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                animation: false,
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
        console.log('✓ Assignment Trend chart created');
    }
    
    // Assignment Statistics Chart
    const statsCanvas = document.getElementById('assignmentStatsChart');
    if (statsCanvas && chartData.assignmentStats.labels.length > 0) {
        window.statsChart = new Chart(statsCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartData.assignmentStats.labels,
                datasets: [{
                    label: 'Submission %',
                    data: chartData.assignmentStats.submissionRates,
                    backgroundColor: '#f59e0b'
                }, {
                    label: 'Avg Marks',
                    data: chartData.assignmentStats.avgMarks,
                    backgroundColor: '#10b981'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: false,
                maintainAspectRatio: false,
                animation: false,
                scales: { x: { beginAtZero: true, max: 100 } }
            }
        });
        console.log('✓ Assignment Statistics chart created');
    }
    
    console.log('✅ All charts initialized successfully!');
</script>
