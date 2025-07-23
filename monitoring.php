<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

$pageTitle = 'Growth Monitoring';

// Define preset periods
$period = isset($_GET['period']) ? $_GET['period'] : '3months';

// Calculate start date based on selected period
switch ($period) {
    case '3months':
        $startDate = date('Y-m-d', strtotime('-3 months'));
        break;
    case '6months':
        $startDate = date('Y-m-d', strtotime('-6 months'));
        break;
    case '9months':
        $startDate = date('Y-m-d', strtotime('-9 months'));
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-3 months'));
}

$endDate = date('Y-m-d'); // Today

try {
    // Fetch weight records with date range
    $stmt = $pdo->prepare("
        SELECT w.*, a.species, a.breed 
        FROM weight_records w
        JOIN animals a ON w.animal_id = a.id
        WHERE w.date BETWEEN :start_date AND :end_date
        ORDER BY w.date ASC
    ");
    
    $stmt->execute([
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);
    $weightRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug - log the number of records found to error log
    error_log("Found " . count($weightRecords) . " weight records between $startDate and $endDate");

} catch(PDOException $e) {
    error_log("Error fetching monitoring data: " . $e->getMessage());
    $weightRecords = [];
}

include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-weight"></i> Growth Monitoring</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='animal_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Animal Management
            </button>
        </div>
    </div>

    <!-- Weight Tracking Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Weight Tracking</h3>
                <!-- Period Selection Buttons -->
                <div class="btn-group" role="group" aria-label="Time period">
                    <a href="?period=3months" class="btn btn-<?php echo $period == '3months' ? 'primary' : 'secondary'; ?>">3 Months</a>
                    <a href="?period=6months" class="btn btn-<?php echo $period == '6months' ? 'primary' : 'secondary'; ?>">6 Months</a>
                    <a href="?period=9months" class="btn btn-<?php echo $period == '9months' ? 'primary' : 'secondary'; ?>">9 Months</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($weightRecords)): ?>
                <div class="alert alert-info">No weight records found for the selected period. Current date range: <?php echo $startDate; ?> to <?php echo $endDate; ?></div>
            <?php else: ?>
                <canvas id="weightChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.card-header {
    padding: 1rem 1.25rem;
}

.form-control-sm {
    width: auto;
}

.gap-2 {
    gap: 0.5rem;
}

.gap-3 {
    gap: 1rem;
}

#weightChart {
    height: 500px !important;
    width: 100% !important;
}

@media (max-width: 768px) {
    .card-header .d-flex {
        flex-direction: column;
        align-items: stretch !important;
        gap: 1rem;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const weightRecords = <?php echo json_encode($weightRecords); ?>;
    
    if (weightRecords.length > 0) {
        // Process and group data by animal
        const groupedData = {};
        
        console.log("Processing " + weightRecords.length + " weight records");
        
        weightRecords.forEach(record => {
            const key = `${record.species} - ${record.breed} (ID: ${record.animal_id})`;
            if (!groupedData[key]) {
                groupedData[key] = [];
            }
            groupedData[key].push({
                x: record.date,
                y: parseFloat(record.weight)
            });
        });

        // Chart colors
        const colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'];

        // Create datasets
        const datasets = Object.keys(groupedData).map((key, index) => ({
            label: key,
            data: groupedData[key],
            borderColor: colors[index % colors.length],
            backgroundColor: colors[index % colors.length],
            fill: false,
            tension: 0.1,
            pointRadius: 5,
            pointHoverRadius: 7
        }));

        // Create chart
        const ctx = document.getElementById('weightChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.parsed.y} kg`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'MMM d, yyyy'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Weight (kg)'
                        },
                        ticks: {
                            callback: function(value) {
                                return value + ' kg';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>