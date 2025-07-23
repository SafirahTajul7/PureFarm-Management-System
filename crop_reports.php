<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Get report type from query string, default to 'overview'
$report_type = isset($_GET['type']) ? $_GET['type'] : 'overview';

// Fetch aggregate data for reports
try {
    // Total crops by status
    $statusStmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM crops 
        GROUP BY status
    ");
    $status_data = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Total crops by growth stage
    $stageStmt = $pdo->query("
        SELECT growth_stage, COUNT(*) as count 
        FROM crops 
        GROUP BY growth_stage
    ");
    $stage_data = $stageStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Crops by field
    $fieldStmt = $pdo->query("
        SELECT f.field_name, COUNT(c.id) as count 
        FROM fields f
        LEFT JOIN crops c ON f.id = c.field_id
        GROUP BY f.id
        ORDER BY count DESC
    ");
    $field_data = $fieldStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Crop activities in the last 30 days
    $activityStmt = $pdo->query("
        SELECT activity_type, COUNT(*) as count 
        FROM crop_activities 
        WHERE activity_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
        GROUP BY activity_type
    ");
    $activity_data = $activityStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Issues by type
    $issueStmt = $pdo->query("
        SELECT issue_type, COUNT(*) as count 
        FROM crop_issues 
        GROUP BY issue_type
    ");
    $issue_data = $issueStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Monthly planting data for the current year
    $monthlyPlantingStmt = $pdo->query("
        SELECT MONTH(planting_date) as month, COUNT(*) as count 
        FROM crops 
        WHERE YEAR(planting_date) = YEAR(CURRENT_DATE)
        GROUP BY MONTH(planting_date)
    ");
    $monthly_planting_data = [];
    while ($row = $monthlyPlantingStmt->fetch(PDO::FETCH_ASSOC)) {
        $monthly_planting_data[$row['month']] = $row['count'];
    }
    
    // Crop growth timeline (current active crops)
    $timelineStmt = $pdo->query("
        SELECT c.id, c.crop_name, c.variety, c.planting_date, c.expected_harvest_date, 
               DATEDIFF(c.expected_harvest_date, c.planting_date) as growth_days,
               DATEDIFF(CURRENT_DATE, c.planting_date) as days_since_planting,
               f.field_name
        FROM crops c
        JOIN fields f ON c.field_id = f.id
        WHERE c.status = 'active'
        ORDER BY c.expected_harvest_date ASC
    ");
    $timeline_data = $timelineStmt->fetchAll();
    
    // Detailed crop list for report
    if ($report_type == 'detailed') {
        $detailedStmt = $pdo->prepare("
            SELECT c.*, f.field_name, f.location,
                  (SELECT COUNT(*) FROM crop_activities WHERE crop_id = c.id) as activity_count,
                  (SELECT COUNT(*) FROM crop_issues WHERE crop_id = c.id) as issue_count
            FROM crops c
            LEFT JOIN fields f ON c.field_id = f.id
            ORDER BY c.planting_date DESC
        ");
        $detailedStmt->execute();
        $detailed_crops = $detailedStmt->fetchAll();
    }
    
} catch(PDOException $e) {
    error_log("Error fetching report data: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error generating reports: ' . $e->getMessage();
}

$pageTitle = 'Crop Reports';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-bar"></i> Crop Reports</h2>
        <div class="action-buttons">
            <div class="btn-group">
                <button class="btn btn-primary <?php echo $report_type == 'overview' ? 'active' : ''; ?>" onclick="location.href='crop_reports.php?type=overview'">
                    <i class="fas fa-chart-pie"></i> Overview
                </button>
                <button class="btn btn-primary <?php echo $report_type == 'timeline' ? 'active' : ''; ?>" onclick="location.href='crop_reports.php?type=timeline'">
                    <i class="fas fa-calendar-alt"></i> Timeline
                </button>
                <button class="btn btn-primary <?php echo $report_type == 'detailed' ? 'active' : ''; ?>" onclick="location.href='crop_reports.php?type=detailed'">
                    <i class="fas fa-list"></i> Detailed Report
                </button>
            </div>
            <button class="btn btn-secondary" onclick="location.href='crop_list.php'">
                <i class="fas fa-arrow-left"></i> Back to Crop List
            </button>
        </div>
    </div>

    <!-- Report Content -->
    <?php if ($report_type == 'overview'): ?>
        <!-- Overview Report -->
        <div class="report-section">
            <div class="report-grid">
                <!-- Crop Status Chart -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3>Crop Status Distribution</h3>
                    </div>
                    <div class="content-card-body">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                
                <!-- Growth Stage Chart -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3>Growth Stage Distribution</h3>
                    </div>
                    <div class="content-card-body">
                        <canvas id="stageChart"></canvas>
                    </div>
                </div>
                
                <!-- Field Distribution Chart -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3>Crops by Field</h3>
                    </div>
                    <div class="content-card-body">
                        <canvas id="fieldChart"></canvas>
                    </div>
                </div>
                
                <!-- Activities Chart -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3>Activities (Last 30 Days)</h3>
                    </div>
                    <div class="content-card-body">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
                
                <!-- Issues Chart -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3>Issues by Type</h3>
                    </div>
                    <div class="content-card-body">
                        <canvas id="issueChart"></canvas>
                    </div>
                </div>
                
                <!-- Monthly Planting Chart -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3>Monthly Planting (<?php echo date('Y'); ?>)</h3>
                    </div>
                    <div class="content-card-body">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($report_type == 'timeline'): ?>
        <!-- Timeline Report -->
        <div class="report-section">
            <div class="content-card">
                <div class="content-card-header">
                    <h3>Crop Growth Timeline</h3>
                </div>
                <div class="content-card-body">
                    <?php if (empty($timeline_data)): ?>
                        <p class="text-center">No active crops found for timeline.</p>
                    <?php else: ?>
                        <div class="crop-timeline">
                            <?php foreach ($timeline_data as $crop): ?>
                                <?php 
                                    $progress = ($crop['days_since_planting'] / $crop['growth_days']) * 100;
                                    $progress = min(100, max(0, $progress)); // Ensure progress is between 0 and 100
                                    
                                    // Determine progress color
                                    $progressColor = 'var(--primary-color)';
                                    if ($progress < 30) {
                                        $progressColor = '#4caf50'; // Early stage - green
                                    } else if ($progress < 70) {
                                        $progressColor = '#ff9800'; // Mid stage - orange
                                    } else {
                                        $progressColor = '#e91e63'; // Late stage - red/pink
                                    }
                                ?>
                                <div class="timeline-item">
                                    <div class="timeline-info">
                                        <h4><?php echo htmlspecialchars($crop['crop_name'] . ' (' . $crop['variety'] . ')'); ?></h4>
                                        <p>
                                            <strong>Field:</strong> <?php echo htmlspecialchars($crop['field_name']); ?><br>
                                            <strong>Planted:</strong> <?php echo date('M d, Y', strtotime($crop['planting_date'])); ?><br>
                                            <strong>Expected Harvest:</strong> <?php echo date('M d, Y', strtotime($crop['expected_harvest_date'])); ?><br>
                                            <strong>Growth Period:</strong> <?php echo $crop['growth_days']; ?> days<br>
                                            <strong>Current Age:</strong> <?php echo $crop['days_since_planting']; ?> days
                                        </p>
                                    </div>
                                    <div class="timeline-progress">
                                        <div class="progress-container">
                                            <div class="progress-bar" style="width: <?php echo $progress; ?>%; background-color: <?php echo $progressColor; ?>;"></div>
                                        </div>
                                        <div class="progress-labels">
                                            <span class="progress-start">Planting<br><?php echo date('M d', strtotime($crop['planting_date'])); ?></span>
                                            <span class="progress-value"><?php echo round($progress); ?>%</span>
                                            <span class="progress-end">Harvest<br><?php echo date('M d', strtotime($crop['expected_harvest_date'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    <?php elseif ($report_type == 'detailed'): ?>
        <!-- Detailed Report -->
        <div class="report-section">
            <div class="content-card">
                <div class="content-card-header">
                    <h3>Detailed Crop Report</h3>
                    <button class="btn btn-sm btn-secondary" onclick="printReport()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
                <div class="content-card-body">
                    <div class="table-responsive">
                        <table class="data-table" id="detailedReportTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Crop Name</th>
                                    <th>Variety</th>
                                    <th>Field Location</th>
                                    <th>Planting Date</th>
                                    <th>Expected Harvest</th>
                                    <th>Growth Stage</th>
                                    <th>Status</th>
                                    <th>Activities</th>
                                    <th>Issues</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($detailed_crops)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center">No crops found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($detailed_crops as $crop): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($crop['id']); ?></td>
                                            <td><?php echo htmlspecialchars($crop['crop_name']); ?></td>
                                            <td><?php echo htmlspecialchars($crop['variety']); ?></td>
                                            <td><?php echo htmlspecialchars($crop['field_name'] . ' (' . $crop['location'] . ')'); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($crop['planting_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($crop['expected_harvest_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($crop['growth_stage']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo htmlspecialchars(strtolower($crop['status'])); ?>">
                                                    <?php echo htmlspecialchars(ucfirst($crop['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $crop['activity_count']; ?></td>
                                            <td><?php echo $crop['issue_count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
<?php if ($report_type == 'overview'): ?>
// Chart.js configurations for Overview report
document.addEventListener('DOMContentLoaded', function() {
    // Common chart options
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    };
    
    // Color schemes
    const statusColors = ['#4caf50', '#2196f3', '#f44336'];
    const stageColors = ['#8bc34a', '#4caf50', '#009688', '#00bcd4', '#3f51b5'];
    const activityColors = ['#ff9800', '#4caf50', '#e91e63', '#9c27b0', '#607d8b', '#795548', '#9e9e9e'];
    const issueColors = ['#ff5722', '#f44336', '#ff9800', '#9e9e9e'];
    const monthlyColors = '#3f51b5';
    
    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: [
                'Active (<?php echo isset($status_data['active']) ? $status_data['active'] : 0; ?>)',
                'Harvested (<?php echo isset($status_data['harvested']) ? $status_data['harvested'] : 0; ?>)',
                'Failed (<?php echo isset($status_data['failed']) ? $status_data['failed'] : 0; ?>)'
            ],
            datasets: [{
                data: [
                    <?php echo isset($status_data['active']) ? $status_data['active'] : 0; ?>,
                    <?php echo isset($status_data['harvested']) ? $status_data['harvested'] : 0; ?>,
                    <?php echo isset($status_data['failed']) ? $status_data['failed'] : 0; ?>
                ],
                backgroundColor: statusColors
            }]
        },
        options: chartOptions
    });
    
    // Growth Stage Chart
    const stageCtx = document.getElementById('stageChart').getContext('2d');
    new Chart(stageCtx, {
        type: 'pie',
        data: {
            labels: [
                'Seedling (<?php echo isset($stage_data['seedling']) ? $stage_data['seedling'] : 0; ?>)',
                'Vegetative (<?php echo isset($stage_data['vegetative']) ? $stage_data['vegetative'] : 0; ?>)',
                'Flowering (<?php echo isset($stage_data['flowering']) ? $stage_data['flowering'] : 0; ?>)',
                'Fruiting (<?php echo isset($stage_data['fruiting']) ? $stage_data['fruiting'] : 0; ?>)',
                'Mature (<?php echo isset($stage_data['mature']) ? $stage_data['mature'] : 0; ?>)'
            ],
            datasets: [{
                data: [
                    <?php echo isset($stage_data['seedling']) ? $stage_data['seedling'] : 0; ?>,
                    <?php echo isset($stage_data['vegetative']) ? $stage_data['vegetative'] : 0; ?>,
                    <?php echo isset($stage_data['flowering']) ? $stage_data['flowering'] : 0; ?>,
                    <?php echo isset($stage_data['fruiting']) ? $stage_data['fruiting'] : 0; ?>,
                    <?php echo isset($stage_data['mature']) ? $stage_data['mature'] : 0; ?>
                ],
                backgroundColor: stageColors
            }]
        },
        options: chartOptions
    });
    
    // Field Chart
    const fieldCtx = document.getElementById('fieldChart').getContext('2d');
    new Chart(fieldCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php 
                foreach ($field_data as $field => $count) {
                    echo "'" . addslashes($field) . "',";
                }
                ?>
            ],
            datasets: [{
                label: 'Crops',
                data: [
                    <?php 
                    foreach ($field_data as $count) {
                        echo $count . ",";
                    }
                    ?>
                ],
                backgroundColor: '#4caf50'
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Activity Chart
    const activityCtx = document.getElementById('activityChart').getContext('2d');
    new Chart(activityCtx, {
        type: 'doughnut',
        data: {
            labels: [
                'Planting (<?php echo isset($activity_data['planting']) ? $activity_data['planting'] : 0; ?>)',
                'Irrigation (<?php echo isset($activity_data['irrigation']) ? $activity_data['irrigation'] : 0; ?>)',
                'Fertilization (<?php echo isset($activity_data['fertilization']) ? $activity_data['fertilization'] : 0; ?>)',
                'Pesticide (<?php echo isset($activity_data['pesticide']) ? $activity_data['pesticide'] : 0; ?>)',
                'Weeding (<?php echo isset($activity_data['weeding']) ? $activity_data['weeding'] : 0; ?>)',
                'Harvest (<?php echo isset($activity_data['harvest']) ? $activity_data['harvest'] : 0; ?>)',
                'Other (<?php echo isset($activity_data['other']) ? $activity_data['other'] : 0; ?>)'
            ],
            datasets: [{
                data: [
                    <?php echo isset($activity_data['planting']) ? $activity_data['planting'] : 0; ?>,
                    <?php echo isset($activity_data['irrigation']) ? $activity_data['irrigation'] : 0; ?>,
                    <?php echo isset($activity_data['fertilization']) ? $activity_data['fertilization'] : 0; ?>,
                    <?php echo isset($activity_data['pesticide']) ? $activity_data['pesticide'] : 0; ?>,
                    <?php echo isset($activity_data['weeding']) ? $activity_data['weeding'] : 0; ?>,
                    <?php echo isset($activity_data['harvest']) ? $activity_data['harvest'] : 0; ?>,
                    <?php echo isset($activity_data['other']) ? $activity_data['other'] : 0; ?>
                ],
                backgroundColor: activityColors
            }]
        },
        options: chartOptions
    });
    
    // Issue Chart
    const issueCtx = document.getElementById('issueChart').getContext('2d');
    new Chart(issueCtx, {
        type: 'pie',
        data: {
            labels: [
                'Pest (<?php echo isset($issue_data['pest']) ? $issue_data['pest'] : 0; ?>)',
                'Disease (<?php echo isset($issue_data['disease']) ? $issue_data['disease'] : 0; ?>)',
                'Nutrient (<?php echo isset($issue_data['nutrient']) ? $issue_data['nutrient'] : 0; ?>)',
                'Other (<?php echo isset($issue_data['other']) ? $issue_data['other'] : 0; ?>)'
            ],
            datasets: [{
                data: [
                    <?php echo isset($issue_data['pest']) ? $issue_data['pest'] : 0; ?>,
                    <?php echo isset($issue_data['disease']) ? $issue_data['disease'] : 0; ?>,
                    <?php echo isset($issue_data['nutrient']) ? $issue_data['nutrient'] : 0; ?>,
                    <?php echo isset($issue_data['other']) ? $issue_data['other'] : 0; ?>
                ],
                backgroundColor: issueColors
            }]
        },
        options: chartOptions
    });
    
    // Monthly Planting Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Crops Planted',
                data: [
                    <?php 
                    for ($i = 1; $i <= 12; $i++) {
                        echo isset($monthly_planting_data[$i]) ? $monthly_planting_data[$i] : 0;
                        echo ($i < 12) ? ',' : '';
                    }
                    ?>
                ],
                backgroundColor: monthlyColors
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
<?php endif; ?>

// Print function for detailed report
function printReport() {
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    
    // Get the report title and current date
    const title = 'PureFarm - Detailed Crop Report';
    const date = new Date().toLocaleDateString();
    
    // Get the table data
    const table = document.getElementById('detailedReportTable');
    
    // Create HTML content for the print window
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                }
                h1 {
                    text-align: center;
                    margin-bottom: 10px;
                }
                .report-date {
                    text-align: center;
                    margin-bottom: 30px;
                    font-style: italic;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }
                th {
                    background-color: #f2f2f2;
                }
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .status-badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-weight: bold;
                }
                .status-active {
                    background-color: #d4edda;
                    color: #155724;
                }
                .status-harvested {
                    background-color: #d1ecf1;
                    color: #0c5460;
                }
                .status-failed {
                    background-color: #f8d7da;
                    color: #721c24;
                }
            </style>
        </head>
        <body>
            <h1>${title}</h1>
            <div class="report-date">Generated on: ${date}</div>
            ${table.outerHTML}
        </body>
        </html>
    `);
    
    // Trigger the print dialog
    printWindow.document.close();
    printWindow.focus();
    
    // Add a slight delay to ensure content is fully loaded
    setTimeout(function() {
        printWindow.print();
        printWindow.close();
    }, 250);
}
</script>

<style>
/* Report styling */
.report-section {
    margin-bottom: 30px;
}

.report-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.content-card {
    background-color: #fff;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.content-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #eee;
}

.content-card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #333;
}

.content-card-body {
    padding: 20px;
    min-height: 300px;
}

/* Timeline styling */
.crop-timeline {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.timeline-item {
    display: flex;
    flex-direction: column;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 6px;
    border-left: 4px solid var(--primary-color);
}

.timeline-info h4 {
    margin-top: 0;
    margin-bottom: 8px;
    color: #333;
}

.timeline-info p {
    margin-bottom: 15px;
    line-height: 1.5;
}

.timeline-progress {
    width: 100%;
}

.progress-container {
    height: 12px;
    background-color: #e9ecef;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-bar {
    height: 100%;
    background-color: var(--primary-color);
    transition: width 0.3s ease;
}

.progress-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #666;
}

.progress-value {
    font-weight: bold;
}

/* Status badge styling */
.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 4px;
    font-weight: 600;
    text-align: center;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-harvested {
    background-color: #d1ecf1;
    color: #0c5460;
}

.status-failed {
    background-color: #f8d7da;
    color: #721c24;
}

/* Button group styling */
.btn-group {
    display: flex;
}

.btn-group .btn {
    border-radius: 0;
}

.btn-group .btn:first-child {
    border-top-left-radius: 4px;
    border-bottom-left-radius: 4px;
}

.btn-group .btn:last-child {
    border-top-right-radius: 4px;
    border-bottom-right-radius: 4px;
}

.btn-group .btn.active {
    background-color: var(--primary-color-dark);
}

/* Media queries */
@media (max-width: 992px) {
    .report-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>