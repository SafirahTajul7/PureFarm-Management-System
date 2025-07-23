<?php
// Start session before any output
session_start();

require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize arrays to prevent undefined variable errors
$healthConditions = [];
$monthlyRecords = [];
$vaccinationStats = ['total_vaccinations' => 0, 'overdue' => 0, 'due_soon' => 0];
$vetWorkload = [];
$commonConditions = [];
$error = null;

try {
    // Get total animals count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM animals");
    $totalAnimals = $stmt->fetch()['total'];

    // Get health condition distribution
    $stmt = $pdo->query("SELECT 
        CASE 
            WHEN `condition` IS NULL THEN 'Unknown'
            WHEN `condition` = '' THEN 'Unknown'
            ELSE `condition` 
        END as `condition`,
        COUNT(*) as count
    FROM health_records
    GROUP BY `condition`
    HAVING `condition` IS NOT NULL");
    $healthConditions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get monthly health records count for the last 12 months
    $stmt = $pdo->query("SELECT COUNT(*) as total 
                     FROM health_records 
                     WHERE date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)");
    $totalRecords = $stmt->fetch()['total'];

    // For the monthly trend, keep the existing query but update the WHERE clause
    $stmt = $pdo->query("SELECT 
        DATE_FORMAT(hr.date, '%Y-%m') as month,
        COUNT(hr.id) as record_count
    FROM health_records hr
    GROUP BY DATE_FORMAT(hr.date, '%Y-%m')
    ORDER BY month ASC");
    $monthlyRecords = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get vaccination statistics
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_vaccinations,
        SUM(CASE WHEN next_due < CURRENT_DATE() THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN next_due BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as due_soon
    FROM vaccinations");

    // Get veterinarian workload
    $stmt = $pdo->query("SELECT 
        vet_name,
        COUNT(*) as appointment_count
    FROM health_records
    WHERE vet_name IS NOT NULL
    GROUP BY vet_name");
    $vetWorkload = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get most common conditions
    $stmt = $pdo->query("SELECT 
        `condition`,
        COUNT(*) as count
    FROM health_records
    WHERE `condition` IS NOT NULL
    GROUP BY `condition`
    ORDER BY count DESC
    LIMIT 5");
    $commonConditions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Calculate total conditions for percentage
$totalConditions = !empty($commonConditions) ? array_sum(array_column($commonConditions, 'count')) : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Analytics - PureFarm</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f0f2f5;
        }

        .container {
            display: flex;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 15px;  /* Reduced padding */
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            min-height: 120px;
        }

        .stat-card h3 {
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 1rem;
            font-weight: 600;
        }

        .stat-card .value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2d3748;
            margin: 0;  /* Remove margin */
            line-height: 1.2;  /* Adjust line height */
        }

        .stat-card .sub-value {
            color: #718096;
            font-size: 0.875rem;
            margin-top: 4px;  /* Reduced margin */
        }

        /* Specific styling for cards that contain alerts */
        .stat-card .alert {
            padding: 8px;  /* Reduced padding */
            margin-top: 8px;  /* Reduced margin */
            margin-bottom: 0;  /* Remove bottom margin */
            border-radius: 4px;
        }

        /* Specific styling for Total Animals and Health Records cards */
        .stat-card:first-child .value,
        .stat-card:last-child .value {
            margin-top: auto;  /* Push value to the middle */
            margin-bottom: auto;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 450px;  /* Fixed height */
            width: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden; /* Prevent overflow */
        }

        .chart-container canvas {
            position: relative;
            width: 100% !important;
            height: calc(100% - 40px) !important;  /* Makes chart fill available space */
        }
        
        .chart-container h3 {
            color: #4a5568;
            margin-bottom: 15px;
            flex-shrink: 0;
        }

        /* Specific adjustments for the donut chart container */
        .chart-container:has(#conditionsChart) {
            padding-right: 120px; /* Extra space for legend */
            min-height: 400px;
        }

        /* Ensure charts grid maintains proper spacing */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
            align-items: stretch;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .analytics-table {
            width: 100%;
            border-collapse: collapse;
        }

        .analytics-table th,
        .analytics-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .analytics-table th {
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.875rem;
        }

        .status-good { background-color: #c6f6d5; color: #22543d; }
        .status-warning { background-color: #fefcbf; color: #744210; }
        .status-critical { background-color: #fed7d7; color: #742a2a; }

        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .text-center {
            text-align: center;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
            }
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-chart-line"></i> Health Analytics</h2>
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="location.href='animal_management.php'">
                    <i class="fas fa-arrow-left"></i> Back to Animal Management
                </button>
            </div>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Animals</h3>
                <div class="value"><?php echo $totalAnimals ?? 0; ?></div>
            </div>

            <div class="stat-card">
                <h3>Vaccinations</h3>
                <div class="value"><?php echo $vaccinationStats['total_vaccinations'] ?? 0; ?></div>
                <?php if(($vaccinationStats['overdue'] ?? 0) > 0): ?>
                    <div class="alert alert-danger">
                        <?php echo $vaccinationStats['overdue']; ?> overdue
                    </div>
                <?php endif; ?>
                <?php if(($vaccinationStats['due_soon'] ?? 0) > 0): ?>
                    <div class="alert alert-warning">
                        <?php echo $vaccinationStats['due_soon']; ?> due in 7 days
                    </div>
                <?php endif; ?>
            </div>

            <div class="stat-card">
                <h3>Health Records</h3>
                <div class="value"><?php echo $totalRecords; ?></div>
                <div class="sub-value">Last 12 months</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-container">
                <h3>Monthly Health Records Trend</h3>
                <canvas id="monthlyTrendChart"></canvas>
            </div>

            <div class="chart-container">
                <h3>Health Conditions Distribution</h3>
                <canvas id="conditionsChart"></canvas>
            </div>
        </div>

        <!-- Veterinarian Workload Table -->
        <div class="table-container">
            <h3>Veterinarian Workload (Last 30 Days)</h3>
            <table class="analytics-table">
                <thead>
                    <tr>
                        <th>Veterinarian</th>
                        <th>Appointments</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($vetWorkload)): ?>
                        <?php foreach($vetWorkload as $vet): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vet['vet_name']); ?></td>
                                <td><?php echo $vet['appointment_count']; ?></td>
                                <td>
                                    <?php
                                    $status = '';
                                    $statusClass = '';
                                    if($vet['appointment_count'] < 10) {
                                        $status = 'Normal Load';
                                        $statusClass = 'status-good';
                                    } elseif($vet['appointment_count'] < 20) {
                                        $status = 'Moderate Load';
                                        $statusClass = 'status-warning';
                                    } else {
                                        $status = 'High Load';
                                        $statusClass = 'status-critical';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">No workload data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Common Conditions Table -->
        <div class="table-container">
            <h3>Most Common Health Conditions (Last 30 Days)</h3>
            <table class="analytics-table">
                <thead>
                    <tr>
                        <th>Condition</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($commonConditions)): ?>
                        <?php foreach($commonConditions as $condition): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($condition['condition']); ?></td>
                                <td><?php echo $condition['count']; ?></td>
                                <td>
                                    <?php echo $totalConditions > 0 ? round(($condition['count'] / $totalConditions) * 100, 1) : 0; ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">No condition data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Monthly Trend Chart
        const monthlyTrendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthlyRecords); ?>;

        new Chart(monthlyTrendCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(record => {
                    const date = new Date(record.month + '-01');
                    return date.toLocaleDateString('default', { month: 'short', year: '2-digit' });
                }),
                datasets: [{
                    label: 'Number of Health Records',
                    data: monthlyData.map(record => record.record_count),
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        padding: 20
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            padding: 10
                        }
                    },
                    x: {
                        ticks: {
                            padding: 10
                        }
                    }
                },
                layout: {
                    padding: {
                        left: 10,
                        right: 10,
                        top: 0,
                        bottom: 10
                    }
                }
            }
        });

        // Health Conditions Chart
        const conditionsCtx = document.getElementById('conditionsChart').getContext('2d');
        const conditionsData = <?php echo json_encode($healthConditions); ?>;

        new Chart(conditionsCtx, {
            type: 'doughnut',
            data: {
                labels: conditionsData.map(item => item.condition),
                datasets: [{
                    data: conditionsData.map(item => item.count),
                    backgroundColor: [
                        '#4CAF50',  // Green
                        '#2196F3',  // Blue
                        '#FFC107',  // Yellow
                        '#F44336',  // Red
                        '#9C27B0'   // Purple
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        align: 'center',
                        labels: {
                            boxWidth: 15,
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                let value = context.raw || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = total > 0 ? ((value * 100) / total).toFixed(1) : 0;
                                return `${label}${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10,
                        left: 10,
                        right: 100  // Extra space for legend
                    }
                },
                cutout: '60%'  // Adjust donut hole size
            }
        });
    </script>
</body>
</html>