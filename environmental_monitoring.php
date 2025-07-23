<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';


// Fetch summary statistics
try {
    // Total fields being monitored
    $total_fields = $pdo->query("SELECT COUNT(*) FROM fields")->fetchColumn();
    
    // Soil tests count
    $soil_tests = $pdo->query("
        SELECT COUNT(*) 
        FROM soil_tests 
        WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ")->fetchColumn();
    
    // Temperature readings (mock data - replace with actual query if available)
    $temperature = $pdo->query("
        SELECT AVG(temperature) 
        FROM soil_tests 
        WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)
    ")->fetchColumn();
    $temperature = $temperature ?: 25; // Default value if null

    // Humidity readings (mock data - replace with actual query if available)
    $humidity = $pdo->query("
        SELECT AVG(moisture_percentage) 
        FROM soil_tests 
        WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)
    ")->fetchColumn();
    $humidity = $humidity ?: 65; // Default value if null

    // Query to get temperature and humidity data for the last 7 days
    $stmt = $pdo->prepare("
        SELECT 
            DATE(test_date) as date,
            AVG(temperature) as avg_temp,
            AVG(moisture_percentage) as avg_humidity,
            COUNT(*) as reading_count
        FROM soil_tests
        WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
        GROUP BY DATE(test_date)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $env_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data for the chart
    $dates = [];
    $temps = [];
    $humidity_values = [];

    // Check if we have data from the database
    if (!empty($env_data)) {
        foreach($env_data as $row) {
            $dates[] = date('M d', strtotime($row['date']));
            $temps[] = round($row['avg_temp'], 1);  // Round to 1 decimal place
            $humidity_values[] = round($row['avg_humidity'], 1);  // Round to 1 decimal place
        }
        
        // If we don't have a full week of data, fill in missing days
        if (count($dates) < 7) {
            $today = new DateTime();
            $sixDaysAgo = new DateTime();
            $sixDaysAgo->modify('-6 days');
            
            $existingDates = array_map(function($dateStr) {
                return DateTime::createFromFormat('M d', $dateStr)->format('Y-m-d');
            }, $dates);
            
            for ($i = 0; $i < 7; $i++) {
                $checkDate = clone $sixDaysAgo;
                $checkDate->modify("+{$i} days");
                $formattedDate = $checkDate->format('Y-m-d');
                $displayDate = $checkDate->format('M d');
                
                if (!in_array($formattedDate, $existingDates)) {
                    // Insert the missing date at the correct position
                    $pos = 0;
                    while ($pos < count($dates) && 
                        strtotime($dates[$pos]) < strtotime($displayDate)) {
                        $pos++;
                    }
                    
                    array_splice($dates, $pos, 0, [$displayDate]);
                    array_splice($temps, $pos, 0, [null]);  // Use null for missing data
                    array_splice($humidity_values, $pos, 0, [null]);
                }
            }
        }
    } 

    // If no database data OR empty arrays after processing, use sample data
    if (empty($dates)) {
        $dates = ['Apr 18', 'Apr 19', 'Apr 20', 'Apr 21', 'Apr 22', 'Apr 23', 'Apr 24'];
        $temps = [22, 23, 25, 24, 22, 26, 25];
        $humidity_values = [65, 68, 62, 70, 72, 60, 65];
    }

    // Always encode to JSON, whether using database or sample data
    $dates_json = json_encode($dates);
    $temps_json = json_encode($temps);
    $humidity_json = json_encode($humidity_values);

} catch(PDOException $e) {
    error_log("Error fetching environmental monitoring data: " . $e->getMessage());
    // Set default values in case of error
    $total_fields = 0;
    $soil_tests = 0;
    $temperature = 25;
    $humidity = 65;
    
    // Set empty arrays for chart data
    $dates_json = json_encode([]);
    $temps_json = json_encode([]);
    $humidity_json = json_encode([]);
}

$pageTitle = 'Environmental Monitoring';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-cloud-sun"></i> Environmental Monitoring</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='add_soil_test.php'">
                <i class="fas fa-plus"></i> Add Soil Test
            </button>
            <button class="btn btn-primary" onclick="location.href='environmental_reports.php'">
                <i class="fas fa-chart-bar"></i> View Reports
            </button>
            
            <button class="btn btn-secondary" onclick="location.href='crop_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Crop Management
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <style>
    .bg-teal { background: #20c997 !important; }
    .bg-purple { background: #6f42c1 !important; }
    .bg-orange { background: #fd7e14 !important; }
    .bg-blue { background: #0d6efd !important; }
    </style>


    <!-- Feature Grid -->
    <style>
    /* Card Themes */
    .weather-monitoring { border-top: 4px solid #20c997; }
    .soil-conditions { border-top: 4px solid #0d6efd; }

    /* Header Icons Colors */
    .weather-monitoring h3 i { color: #20c997; }
    .soil-conditions h3 i { color: #0d6efd; }

    /* Card Hover Effects */
    .weather-monitoring:hover { box-shadow: 0 6px 12px rgba(32, 201, 151, 0.2); }
    .soil-conditions:hover { box-shadow: 0 6px 12px rgba(13, 110, 253, 0.2); }

    /* Theme-specific hover backgrounds */
    .weather-monitoring .menu-item:hover { background: #20c997; }
    .soil-conditions .menu-item:hover { background: #0d6efd; }
    </style>
    
    <div class="features-grid-2col">
        <!-- Weather Monitoring - Teal Theme -->
        <div class="feature-card weather-monitoring">
            <h3><i class="fas fa-cloud-sun-rain"></i> Weather Monitoring</h3>
            <ul>
                <li onclick="location.href='current_weather.php'">
                    <div class="menu-item">
                        <i class="fas fa-cloud-sun"></i>
                        <div class="menu-content">
                            <span class="menu-title">Current Weather</span>
                            <span class="menu-desc">View real-time weather conditions</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='weather_history.php'">
                    <div class="menu-item">
                        <i class="fas fa-history"></i>
                        <div class="menu-content">
                            <span class="menu-title">Weather History</span>
                            <span class="menu-desc">Review historical weather data</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Soil Conditions - Blue Theme -->
        <div class="feature-card soil-conditions">
            <h3><i class="fas fa-seedling"></i> Soil Conditions</h3>
            <ul>
                <li onclick="location.href='soil_tests.php'">
                    <div class="menu-item">
                        <i class="fas fa-vial"></i>
                        <div class="menu-content">
                            <span class="menu-title">Soil Tests</span>
                            <span class="menu-desc">Manage soil test records</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='soil_moisture.php'">
                    <div class="menu-item">
                        <i class="fas fa-tint"></i>
                        <div class="menu-content">
                            <span class="menu-title">Soil Moisture</span>
                            <span class="menu-desc">Track soil moisture levels</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='soil_nutrients.php'">
                    <div class="menu-item">
                        <i class="fas fa-flask"></i>
                        <div class="menu-content">
                            <span class="menu-title">Soil Nutrients</span>
                            <span class="menu-desc">Monitor pH and nutrient levels</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='soil_treatments.php'">
                    <div class="menu-item">
                        <i class="fas fa-prescription-bottle"></i>
                        <div class="menu-content">
                            <span class="menu-title">Soil Treatments</span>
                            <span class="menu-desc">Track soil amendments and treatments</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <!-- Environmental Conditions Dashboard -->
    <div class="section-header">
        <h3><i class="fas fa-tachometer-alt"></i> Current Environmental Conditions</h3>
    </div>

    <div class="current-conditions">
        <div class="conditions-chart">
            <!-- Chart container -->
            <div id="conditions-chart-container" style="height: 300px; background: #f8f9fa; border-radius: 8px; padding: 15px;">
                <canvas id="environmental-chart"></canvas>
            </div>
        </div>
        
        <div class="conditions-grid">
            <!-- Latest readings from different fields -->
            <div class="condition-card">
                <div class="condition-title">Main Field</div>
                <div class="condition-data">
                    <div class="condition-reading">
                        <i class="fas fa-thermometer-half"></i>
                        <span>24°C</span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-tint"></i>
                        <span>65%</span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-water"></i>
                        <span>42%</span>
                    </div>
                </div>
                <div class="condition-updated">Updated: Today, 10:45 AM</div>
            </div>
            
            <div class="condition-card">
                <div class="condition-title">North Field</div>
                <div class="condition-data">
                    <div class="condition-reading">
                        <i class="fas fa-thermometer-half"></i>
                        <span>23°C</span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-tint"></i>
                        <span>68%</span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-water"></i>
                        <span>37%</span>
                    </div>
                </div>
                <div class="condition-updated">Updated: Today, 10:30 AM</div>
            </div>
            
            <div class="condition-card">
                <div class="condition-title">South Field</div>
                <div class="condition-data">
                    <div class="condition-reading">
                        <i class="fas fa-thermometer-half"></i>
                        <span>25°C</span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-tint"></i>
                        <span>60%</span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-water"></i>
                        <span>31%</span>
                    </div>
                </div>
                <div class="condition-updated">Updated: Today, 10:15 AM</div>
            </div>
            
            <div class="condition-card needs-attention">
                <div class="condition-title">East Field</div>
                <div class="condition-data">
                    <div class="condition-reading">
                        <i class="fas fa-thermometer-half"></i>
                        <span>26°C</span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-tint"></i>
                        <span>55%</span>
                    </div>
                    <div class="condition-reading alert">
                        <i class="fas fa-water"></i>
                        <span>22%</span>
                    </div>
                </div>
                <div class="condition-updated">Updated: Today, 10:00 AM</div>
                <div class="condition-alert"><i class="fas fa-exclamation-triangle"></i> Low soil moisture</div>
            </div>
        </div>
    </div>

    <!-- Recent Soil Tests -->
    <div class="section-header">
        <h3><i class="fas fa-flask"></i> Recent Soil Tests</h3>
    </div>

    <div class="soil-tests-table">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Test Date</th>
                    <th>pH Level</th>
                    <th>Moisture</th>
                    <th>Temperature</th>
                    <th>Nitrogen</th>
                    <th>Phosphorus</th>
                    <th>Potassium</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Main Field</td>
                    <td>Apr 15, 2025</td>
                    <td>6.5</td>
                    <td>42%</td>
                    <td>24°C</td>
                    <td>Medium</td>
                    <td>High</td>
                    <td>Medium</td>
                    <td>
                        <a href="view_soil_test.php?id=1" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                        <a href="edit_soil_test.php?id=1" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
                <tr>
                    <td>North Field</td>
                    <td>Apr 14, 2025</td>
                    <td>6.8</td>
                    <td>37%</td>
                    <td>23°C</td>
                    <td>Low</td>
                    <td>Medium</td>
                    <td>Medium</td>
                    <td>
                        <a href="view_soil_test.php?id=2" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                        <a href="edit_soil_test.php?id=2" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
                <tr>
                    <td>South Field</td>
                    <td>Apr 12, 2025</td>
                    <td>7.2</td>
                    <td>31%</td>
                    <td>25°C</td>
                    <td>Medium</td>
                    <td>Low</td>
                    <td>High</td>
                    <td>
                        <a href="view_soil_test.php?id=3" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                        <a href="edit_soil_test.php?id=3" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
                <tr class="table-warning">
                    <td>East Field</td>
                    <td>Apr 10, 2025</td>
                    <td>5.9</td>
                    <td>22%</td>
                    <td>26°C</td>
                    <td>High</td>
                    <td>Low</td>
                    <td>Low</td>
                    <td>
                        <a href="view_soil_test.php?id=4" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                        <a href="edit_soil_test.php?id=4" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="text-end">
            <a href="soil_tests.php" class="btn btn-primary">View All Soil Tests</a>
        </div>
    </div>
</div>

<style>
   .main-content {
    padding-bottom: 60px; /* Add space for footer */
    min-height: calc(100vh - 60px); /* Ensure content takes up full height minus footer */
}

footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 15px 0;
    background-color: #f8f9fa;
    text-align: center;
    border-top: 1px solid #dee2e6;
    height: 50px;
}

/* Features Grid - 2 columns */
.features-grid-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 991px) {
    .features-grid-2col {
        grid-template-columns: 1fr;
    }
}

/* Additional spacing for the features grid */
.features-grid-2col {
    margin-bottom: 30px; /* Add space after features grid */
}

/* Current Conditions Dashboard Styles */
.section-header {
    margin: 20px 0;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.current-conditions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.conditions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.condition-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    padding: 15px;
    transition: all 0.3s ease;
}

.condition-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.condition-title {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 10px;
    color: #333;
}

.condition-data {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.condition-reading {
    text-align: center;
    padding: 5px;
}

.condition-reading i {
    display: block;
    font-size: 18px;
    margin-bottom: 5px;
    color: #6c757d;
}

.condition-reading span {
    font-size: 16px;
}

.condition-updated {
    font-size: 12px;
    color: #6c757d;
    text-align: right;
}

.needs-attention {
    border-left: 3px solid #dc3545;
}

.condition-alert {
    color: #dc3545;
    font-size: 13px;
    margin-top: 8px;
}

.alert i, .alert span {
    color: #dc3545 !important;
}

/* Soil Tests Table */
.soil-tests-table {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 30px;
}

.table {
    margin-bottom: 20px;
}

@media (min-width: 992px) {
    .current-conditions {
        grid-template-columns: 2fr 3fr;
    }
}

/* Prevent content from being hidden behind footer */
body {
    padding-bottom: 60px;
} 
</style>

<footer class="footer">
    <div class="container">
        <p>&copy; 2025 PureFarm Management System. All rights reserved. <span class="float-end">Version 1.0</span></p>
    </div>
</footer>

<!-- Add Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // Prepare the chart container
    const chartContainer = document.getElementById('conditions-chart-container');
    
    // Add a canvas element inside the container
    chartContainer.innerHTML = '<canvas id="environmental-chart"></canvas>';

    // Get data from PHP
    const dates = <?php echo $dates_json; ?>;
    const temps = <?php echo $temps_json; ?>;
    const humidity = <?php echo $humidity_json; ?>;

    console.log("Chart data:", { 
        dates: dates, 
        temps: temps, 
        humidity: humidity 
    });
    
    // Check if we have data
    if (dates && dates.length > 0) {
        const ctx = document.getElementById('environmental-chart').getContext('2d');
        
        // Create chart
        const envChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Temperature (°C)',
                        data: temps,
                        borderColor: '#fd7e14',
                        backgroundColor: 'rgba(253, 126, 20, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Humidity (%)',
                        data: humidity,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Temperature & Humidity (Last 7 Days)',
                        font: {
                            size: 16
                        }
                    },
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Temperature (°C)'
                        },
                        min: 0,
                        suggestedMax: 40,
                        grid: {
                            drawOnChartArea: false
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Humidity (%)'
                        },
                        min: 0,
                        max: 100,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    } else {
        // If no data, display a message
        chartContainer.innerHTML = 
            '<div style="display: flex; align-items: center; justify-content: center; height: 100%;">' +
            '<p><i class="fas fa-info-circle" style="font-size: 24px; margin-right: 10px;"></i> ' +
            'No temperature and humidity data available for the last 7 days.</p></div>';
    }
});
</script>