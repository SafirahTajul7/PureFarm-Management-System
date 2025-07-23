<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Performance Analytics';
include 'includes/header.php';

try {
    // Health records query
    $stmt = $pdo->query("
        SELECT `condition`, COUNT(*) as count 
        FROM health_records 
        GROUP BY `condition`
    ");
    $healthData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Vaccination query
    $stmt = $pdo->query("
        SELECT COUNT(*) as total,
        SUM(CASE WHEN next_due >= CURRENT_DATE THEN 1 ELSE 0 END) as up_to_date
        FROM vaccinations
    ");
    $vaccData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Weight query
    $stmt = $pdo->query("
        SELECT animal_id, 
        ROUND(AVG(weight), 2) as avg_weight
        FROM weight_records
        WHERE weight > 0
        GROUP BY animal_id
    ");
    $weightData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    error_log($e->getMessage());
    $healthData = [['condition' => 'No Data', 'count' => 1]];
    $vaccData = ['total' => 0, 'up_to_date' => 0];
    $weightData = [['animal_id' => 'No Data', 'avg_weight' => 0]];
}
?>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> Performance Analytics</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='animal_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Animal Management
            </button>
            
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3>Analytics Overview</h3>
                <div class="nav-pills">
                    <button class="nav-btn active" data-target="health">Health Distribution</button>
                    <button class="nav-btn" data-target="vaccination">Vaccination Status</button>
                    <button class="nav-btn" data-target="weight">Weight Analysis</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <div id="health" class="chart-panel active">
                    <canvas id="healthChart"></canvas>
                </div>
                <div id="vaccination" class="chart-panel">
                    <canvas id="vaccinationChart"></canvas>
                </div>
                <div id="weight" class="chart-panel">
                    <canvas id="weightChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.nav-pills {
    display: flex;
    gap: 10px;
}

.nav-btn {
    padding: 8px 16px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    color: #666;
    transition: all 0.2s;
}

.nav-btn:hover {
    background: #f8f9fa;
}

.nav-btn.active {
    background: #007bff;
    color: white;
    border-color: #0056b3;
}

.chart-container {
    position: relative;
    height: 500px;
    margin: 0 auto;
}

.chart-panel {
    display: none;
    height: 100%;
    width: 100%;
}

.chart-panel.active {
    display: block;
}

.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
    color: #333;
}

.d-flex {
    display: flex;
}

.justify-content-between {
    justify-content: space-between;
}

.align-items-center {
    align-items: center;
}

@media (max-width: 768px) {
    .card-header .d-flex {
        flex-direction: column;
        gap: 1rem;
    }
    
    .nav-pills {
        flex-wrap: wrap;
    }
    
    .nav-btn {
        flex: 1 1 calc(33.333% - 10px);
        text-align: center;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Navigation
    document.querySelectorAll('.nav-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.chart-panel').forEach(panel => panel.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(this.dataset.target).classList.add('active');
        });
    });

    // Health Chart
    new Chart(document.getElementById('healthChart'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($healthData, 'condition')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($healthData, 'count')); ?>,
                backgroundColor: [
                    '#20c997',  // Teal
                    '#0d6efd',  // Blue
                    '#ffc107',  // Yellow
                    '#dc3545'   // Red
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });

    // Vaccination Chart
    new Chart(document.getElementById('vaccinationChart'), {
        type: 'doughnut',
        data: {
            labels: ['Up to Date', 'Due for Vaccination'],
            datasets: [{
                data: [
                    <?php echo $vaccData['up_to_date']; ?>,
                    <?php echo $vaccData['total'] - $vaccData['up_to_date']; ?>
                ],
                backgroundColor: ['#20c997', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true
                    }
                }
            }
        }
    });

    // Weight Chart
    new Chart(document.getElementById('weightChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($weightData, 'animal_id')); ?>.map(id => 'Animal ' + id),
            datasets: [{
                label: 'Average Weight',
                data: <?php echo json_encode(array_column($weightData, 'avg_weight')); ?>,
                backgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Weight (kg)'
                    }
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>