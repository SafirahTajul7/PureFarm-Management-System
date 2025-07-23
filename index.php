<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Fetch summary statistics
try {
    // Total animals count
    $total_animals = $pdo->query("SELECT COUNT(*) FROM animals WHERE id NOT IN (SELECT COALESCE(animal_id, 0) FROM deceased_animals)")->fetchColumn();
    
    // Active crops count
    $active_crops = $pdo->query("SELECT COUNT(*) FROM crops WHERE status = 'active'")->fetchColumn();
    
    // Inventory items below reorder level
    $low_stock = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE current_quantity <= reorder_level")->fetchColumn();
    
    // Pending tasks
    $pending_tasks = $pdo->query("SELECT COUNT(*) FROM staff_tasks WHERE status = 'pending'")->fetchColumn();
    
    // Recent activities - last 7 days
    $recent_activities = $pdo->query("
        SELECT a.activity_type, a.description, a.timestamp, 
               CONCAT(s.first_name, ' ', s.last_name) as staff_name
        FROM activities a
        LEFT JOIN staff s ON a.performed_by = s.id
        WHERE a.timestamp >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
        ORDER BY a.timestamp DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Upcoming harvest dates
    $upcoming_harvests = $pdo->query("
        SELECT c.id, c.crop_name, c.variety, c.expected_harvest_date, f.field_name
        FROM crops c
        JOIN fields f ON c.field_id = f.id
        WHERE c.status = 'active' 
        AND c.expected_harvest_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
        ORDER BY c.expected_harvest_date ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get monthly health records data for chart (replacing production data)
    $monthly_health_records = $pdo->query("
        SELECT 
            DATE_FORMAT(hr.date, '%Y-%m') as month,
            COUNT(hr.id) as record_count
        FROM health_records hr
        WHERE hr.date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(hr.date, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Format chart data for health records
    $chart_months = [];
    $chart_data = [];
    
    // Fill in missing months with 0 values
    $start_date = new DateTime('first day of -11 months');
    $end_date = new DateTime('last day of this month');
    
    while ($start_date <= $end_date) {
        $month_key = $start_date->format('Y-m');
        $month_label = $start_date->format('M');
        
        $chart_months[] = $month_label;
        
        // Find matching data or use 0
        $found_data = 0;
        foreach ($monthly_health_records as $record) {
            if ($record['month'] === $month_key) {
                $found_data = (int)$record['record_count'];
                break;
            }
        }
        $chart_data[] = $found_data;
        
        $start_date->modify('first day of next month');
    }
    
} catch(PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    // Set defaults if query fails
    $total_animals = 25; // Sample data
    $active_crops = 15;  // Sample data
    $low_stock = 8;      // Sample data
    $pending_tasks = 12; // Sample data
    $recent_activities = [
        [
            'activity_type' => 'animal',
            'description' => 'Vaccinated 5 cattle',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'staff_name' => 'John Doe'
        ],
        [
            'activity_type' => 'crop',
            'description' => 'Planted new corn in Field 3',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'staff_name' => 'Jane Smith'
        ]
    ];
    $upcoming_harvests = [
        [
            'id' => 1,
            'crop_name' => 'Corn',
            'variety' => 'Sweet Corn',
            'expected_harvest_date' => date('Y-m-d', strtotime('+15 days')),
            'field_name' => 'Field 1'
        ],
        [
            'id' => 2,
            'crop_name' => 'Tomatoes',
            'variety' => 'Roma',
            'expected_harvest_date' => date('Y-m-d', strtotime('+25 days')),
            'field_name' => 'Field 2'
        ]
    ];
    
    // Default health records data
    $chart_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $chart_data = [12, 8, 15, 9, 11, 7, 13, 10, 14, 6, 9, 11];
}

// Replace this with your actual weather API key and location
$weather_api_key = "0c61f0b78fa96be7b044b3014a64ed7b"; // Get from https://openweathermap.org/api
$location = "Kuala Lumpur,MY"; // Change to your farm location

$pageTitle = 'Dashboard';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
        <div class="date-display">
            <span class="current-date"><?php echo date('l'); ?></span>
            <span class="current-time"><?php echo date('F d, Y'); ?></span>
        </div>
    </div>
    
    <!-- Top Dashboard Widgets - Weather and Growth side by side -->
    <div class="top-widgets-row">
        <!-- Weather Today Card - Left Side -->
        <div class="weather-card">
            <div class="card-header">
                <h3>Weather today</h3>
                <span id="weather-date"><?php echo date('D, M Y'); ?></span>
            </div>
            <div class="weather-content">
                <div class="temp-info">
                    <span class="temp-value" id="current-temp">Loading...</span>
                    <span class="temp-location">in 24 hours</span>
                </div>
                <div class="weather-circle">
                    <div class="circle-inner">
                        <div class="circle-temp" id="circle-temp">--°</div>
                    </div>
                </div>
                <div class="weather-conditions">
                    <div class="condition">
                        <i class="fas fa-wind"></i>
                        <span id="wind-speed">--</span>
                    </div>
                    <div class="condition">
                        <i class="fas fa-tint"></i>
                        <span id="humidity">--%</span>
                    </div>
                    <div class="condition">
                        <i class="fas fa-cloud-sun"></i>
                        <span id="pressure">----hPa</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Plant Growth Activity Card - Right Side -->
        <div class="growth-card">
            <div class="card-header">
                <h3>Plant growth activity</h3>
                <span class="period-badge" id="growth-period">Weekly</span>
            </div>
            <div class="growth-chart">
                <canvas id="growthChart"></canvas>
            </div>
            <div class="growth-stages">
                <div class="stage">
                    <div class="stage-icon seed"></div>
                    <span>Seed Phase (<?php echo rand(30, 50); ?>)</span>
                </div>
                <div class="stage">
                    <div class="stage-icon growth"></div>
                    <span>Growth (<?php echo rand(60, 100); ?>)</span>
                </div>
                <div class="stage">
                    <div class="stage-icon vegetable"></div>
                    <span>Vegetative (<?php echo rand(70, 120); ?>)</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Monthly Health Records Trend Section (Replacing Production Section) -->
    <div class="health-records-section">
        <div class="section-header">
            <h3><i class="fas fa-chart-line"></i> Monthly Health Records Trend</h3>
            <div class="chart-controls">
                <button class="btn btn-sm btn-outline-secondary" id="refresh-health-chart">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="fullscreen-health-chart">
                    <i class="fas fa-expand-alt"></i>
                </button>
                <a href="health_analytics.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-chart-pie"></i> View Full Analytics
                </a>
            </div>
        </div>
        
        <div class="health-records-content">
            <div class="chart-info">
                <div class="info-item">
                    <span class="info-label">Total Records (Last 12 months):</span>
                    <span class="info-value"><?php echo array_sum($chart_data); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Average per month:</span>
                    <span class="info-value"><?php echo round(array_sum($chart_data) / 12, 1); ?></span>
                </div>
            </div>
            
            <div class="health-chart-container" id="health-chart-container">
                <canvas id="healthRecordsChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Bottom Stats Cards -->
    <div class="dashboard-bottom-row">
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card bg-primary">
                <div class="stat-icon">
                    <i class="fas fa-paw"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Animals</h3>
                    <div class="stat-value"><?php echo $total_animals; ?></div>
                </div>
            </div>
            
            <div class="stat-card bg-success">
                <div class="stat-icon">
                    <i class="fas fa-seedling"></i>
                </div>
                <div class="stat-info">
                    <h3>Active Crops</h3>
                    <div class="stat-value"><?php echo $active_crops; ?></div>
                </div>
            </div>
            
            <div class="stat-card bg-warning">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3>Low Stock Items</h3>
                    <div class="stat-value"><?php echo $low_stock; ?></div>
                </div>
            </div>
            
            <div class="stat-card bg-info">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-info">
                    <h3>Pending Tasks</h3>
                    <div class="stat-value"><?php echo $pending_tasks; ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity and Upcoming Harvest Section -->
    <div class="info-section">
        <div class="recent-activity-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
            </div>
            <div class="activity-list">
                <?php if (empty($recent_activities)): ?>
                    <div class="no-activity">No recent activities found.</div>
                <?php else: ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php
                                $icon = 'clipboard-list';
                                switch ($activity['activity_type']) {
                                    case 'animal':
                                        $icon = 'paw';
                                        break;
                                    case 'crop':
                                        $icon = 'seedling';
                                        break;
                                    case 'inventory':
                                        $icon = 'box';
                                        break;
                                    case 'staff':
                                        $icon = 'user';
                                        break;
                                }
                                ?>
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-description"><?php echo htmlspecialchars($activity['description']); ?></div>
                                <div class="activity-meta">
                                    <span class="activity-by"><?php echo htmlspecialchars($activity['staff_name']); ?></span>
                                    <span class="activity-time"><?php echo date('M d, H:i', strtotime($activity['timestamp'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="upcoming-harvest-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Upcoming Harvests</h3>
            </div>
            <?php if (empty($upcoming_harvests)): ?>
                <div class="no-harvests">No upcoming harvests in the next 30 days.</div>
            <?php else: ?>
                <div class="harvest-list">
                    <?php foreach ($upcoming_harvests as $harvest): ?>
                        <div class="harvest-item">
                            <div class="harvest-date">
                                <div class="month"><?php echo date('M', strtotime($harvest['expected_harvest_date'])); ?></div>
                                <div class="day"><?php echo date('d', strtotime($harvest['expected_harvest_date'])); ?></div>
                            </div>
                            <div class="harvest-details">
                                <div class="harvest-crop"><?php echo htmlspecialchars($harvest['crop_name'] . ' (' . $harvest['variety'] . ')'); ?></div>
                                <div class="harvest-field">Field: <?php echo htmlspecialchars($harvest['field_name']); ?></div>
                            </div>
                            <a href="crop_details.php?id=<?php echo $harvest['id']; ?>" class="harvest-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for fullscreen chart -->
<div id="fullscreen-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <div class="modal-header">
            <h3>Monthly Health Records Trend - Full View</h3>
        </div>
        <div class="modal-body">
            <canvas id="healthRecordsChartModal"></canvas>
        </div>
    </div>
</div>

<!-- Chart.js Library for Growth Chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Global chart instances
let growthChart, healthRecordsChart, healthRecordsChartModal;

// Weather data fetching function
async function fetchWeatherData() {
    const apiKey = '<?php echo $weather_api_key; ?>'; // Replace with your OpenWeatherMap API key
    const location = '<?php echo $location; ?>'; // Replace with your location
    
    if (!apiKey || apiKey === 'YOUR_OPENWEATHERMAP_API_KEY') {
        // Demo mode - use static data
        updateWeatherUI({
            temp: 29,
            humidity: 68,
            windSpeed: 9,
            pressure: 1004,
            weatherMain: 'Clear'
        });
        return;
    }
    
    try {
        const response = await fetch(`https://api.openweathermap.org/data/2.5/weather?q=${encodeURIComponent(location)}&appid=${apiKey}&units=metric`);
        const data = await response.json();
        
        if (response.ok) {
            updateWeatherUI({
                temp: Math.round(data.main.temp),
                humidity: data.main.humidity,
                windSpeed: Math.round(data.wind.speed * 3.6), // Convert m/s to km/h
                pressure: data.main.pressure,
                weatherMain: data.weather[0].main
            });
        } else {
            console.error('Weather API error:', data);
            // Fallback to demo data
            updateWeatherUI({
                temp: 29,
                humidity: 68,
                windSpeed: 9,
                pressure: 1004,
                weatherMain: 'Clear'
            });
        }
    } catch (error) {
        console.error('Failed to fetch weather data:', error);
        // Fallback to demo data
        updateWeatherUI({
            temp: 29,
            humidity: 68,
            windSpeed: 9,
            pressure: 1004,
            weatherMain: 'Clear'
        });
    }
}

function updateWeatherUI(weatherData) {
    document.getElementById('current-temp').textContent = `${weatherData.temp}°C`;
    document.getElementById('circle-temp').textContent = `${weatherData.temp}°`;
    document.getElementById('wind-speed').textContent = `${weatherData.windSpeed}km/h`;
    document.getElementById('humidity').textContent = `${weatherData.humidity}%`;
    document.getElementById('pressure').textContent = `${weatherData.pressure}hPa`;
}

document.addEventListener('DOMContentLoaded', function() {
    // Fetch weather data on page load
    fetchWeatherData();
    
    // Update weather data every 30 minutes
    setInterval(fetchWeatherData, 30 * 60 * 1000);
    
    // Plant Growth Activity Chart
    const growthCtx = document.getElementById('growthChart').getContext('2d');
    const gradientFill = growthCtx.createLinearGradient(0, 0, 0, 220);
    
    gradientFill.addColorStop(0, 'rgba(76, 175, 80, 0.6)');
    gradientFill.addColorStop(1, 'rgba(76, 175, 80, 0.0)');
    
    growthChart = new Chart(growthCtx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Growth Rate',
                data: [20, 25, 30, 35, 40, 38, 45],
                borderColor: '#4CAF50',
                borderWidth: 3,
                pointBackgroundColor: '#4CAF50',
                pointRadius: 4,
                tension: 0.4,
                fill: true,
                backgroundColor: gradientFill
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: false
                    },
                    ticks: {
                        display: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            },
            elements: {
                point: {
                    radius: 0
                }
            }
        }
    });

    // Health Records Chart
    const healthRecordsCtx = document.getElementById('healthRecordsChart').getContext('2d');
    
    // Chart data from PHP
    const chartData = {
        labels: <?php echo json_encode($chart_months); ?>,
        data: <?php echo json_encode($chart_data); ?>
    };
    
    // Chart configuration
    const chartConfig = {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Health Records',
                    data: chartData.data,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#4CAF50',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 10,
                    top: 20,
                    bottom: 10
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#eee'
                    },
                    ticks: {
                        color: '#666',
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#666'
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.7)',
                    titleFont: {
                        size: 13
                    },
                    bodyFont: {
                        size: 13
                    },
                    padding: 10,
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            return `Health Records: ${context.raw}`;
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    };
    
    // Create main chart
    healthRecordsChart = new Chart(healthRecordsCtx, chartConfig);
    
    // Button functionality
    
    // Refresh chart button
    document.getElementById('refresh-health-chart').addEventListener('click', function() {
        const icon = this.querySelector('i');
        icon.classList.add('fa-spin');
        
        // Simulate data refresh by reloading the page or making an AJAX call
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    });
    
    // Fullscreen chart button
    document.getElementById('fullscreen-health-chart').addEventListener('click', function() {
        const modal = document.getElementById('fullscreen-modal');
        modal.style.display = 'block';
        
        // Create fullscreen chart if it doesn't exist
        if (!healthRecordsChartModal) {
            const modalCtx = document.getElementById('healthRecordsChartModal').getContext('2d');
            
            // Clone the chart config
            const modalConfig = JSON.parse(JSON.stringify(chartConfig));
            modalConfig.options.layout.padding = { left: 20, right: 20, top: 40, bottom: 40 };
            
            healthRecordsChartModal = new Chart(modalCtx, modalConfig);
        } else {
            // Update with current data
            healthRecordsChartModal.data.datasets[0].data = healthRecordsChart.data.datasets[0].data;
            healthRecordsChartModal.update();
        }
    });
    
    // Close modal
    document.querySelector('.close').addEventListener('click', function() {
        document.getElementById('fullscreen-modal').style.display = 'none';
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('fullscreen-modal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<style>

/* Dashboard Styles */
.main-content {
    padding: 20px;
    background-color: #f0f2f5;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.date-display {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.current-date {
    font-size: 18px;
    font-weight: bold;
    color: #333;
}

.current-time {
    font-size: 14px;
    color: #666;
}

/* Top row with weather and growth side by side */
.top-widgets-row {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

/* Weather Card */
.weather-card {
    flex: 1;
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.card-header h3 {
    margin: 0;
    font-size: 16px;
    color: #333;
}

.card-header span {
    font-size: 12px;
    color: #888;
}

.weather-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.temp-info {
    display: flex;
    flex-direction: column;
}

.temp-value {
    font-size: 28px;
    font-weight: bold;
    color: #333;
}

.temp-location {
    font-size: 12px;
    color: #888;
}

.weather-circle {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 15px auto;
}

.circle-inner {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background-color: #1a472a;
    display: flex;
    justify-content: center;
    align-items: center;
    border: 8px solid #2e7d32;
}

.circle-temp {
    color: white;
    font-size: 24px;
    font-weight: bold;
}

.weather-conditions {
    display: flex;
    justify-content: space-between;
    margin-top: auto;
}

.condition {
    display: flex;
    align-items: center;
    font-size: 14px;
    color: #555;
}

.condition i {
    margin-right: 5px;
    color: #4caf50;
}

/* Growth Card */
.growth-card {
    flex: 1;
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
}

.period-badge {
    background-color: #e8f5e9;
    color: #4caf50;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
}

.period-badge:hover {
    background-color: #c8e6c9;
}

.growth-chart {
    flex: 1;
    position: relative;
    min-height: 180px;
}

.growth-stages {
    display: flex;
    justify-content: space-between;
    margin-top: 15px;
}

.stage {
    display: flex;
    align-items: center;
    font-size: 12px;
    color: #555;
}

.stage-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    margin-right: 5px;
}

.seed {
    background-color: #ffecb3;
}

.growth {
    background-color: #c8e6c9;
}

.vegetable {
    background-color: #bbdefb;
}

/* Health Records Section (Replacing Production Section) */
.health-records-section {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.section-header h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.chart-controls {
    display: flex;
    gap: 10px;
}

.chart-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    align-items: center;
}

.info-item {
    display: flex;
    align-items: center;
    font-size: 14px;
    color: #666;
}

.info-label {
    margin-right: 8px;
}

.info-value {
    font-weight: bold;
    color: #4caf50;
}

.health-chart-container {
    height: 300px;
    width: 100%;
    overflow: hidden;
}

/* Dashboard Bottom Row */
.dashboard-bottom-row {
    margin-bottom: 20px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 8px;
    height: 100%;
}

.stat-card.bg-primary::before {
    background-color: #3f51b5;
}

.stat-card.bg-success::before {
    background-color: #4caf50;
}

.stat-card.bg-warning::before {
    background-color: #ff9800;
}

.stat-card.bg-info::before {
    background-color: #00bcd4;
}

.stat-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    margin-right: 15px;
    font-size: 24px;
}

.bg-primary .stat-icon {
    background-color: #e8eaf6;
    color: #3f51b5;
}

.bg-success .stat-icon {
    background-color: #e8f5e9;
    color: #4caf50;
}

.bg-warning .stat-icon {
    background-color: #fff3e0;
    color: #ff9800;
}

.bg-info .stat-icon {
    background-color: #e0f7fa;
    color: #00bcd4;
}

.stat-info h3 {
    margin: 0;
    font-size: 14px;
    color: #555;
    margin-bottom: 5px;
}

.stat-info .stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

/* Info Section (Recent Activity and Upcoming Harvests) */
.info-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.recent-activity-card, .upcoming-harvest-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    height: 400px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.activity-list, .harvest-list {
    flex: 1;
    overflow-y: auto;
}

/* Recent Activity Styles */
.activity-item {
    display: flex;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 36px;
    height: 36px;
    background-color: #e8f5e9;
    color: #4caf50;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-size: 14px;
}

.activity-details {
    flex: 1;
}

.activity-description {
    font-size: 14px;
    color: #333;
    margin-bottom: 5px;
}

.activity-meta {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #888;
}

.no-activity, .no-harvests {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #888;
    font-style: italic;
}

/* Upcoming Harvest Styles */
.harvest-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.harvest-item:last-child {
    border-bottom: none;
}

.harvest-date {
    width: 50px;
    height: 50px;
    background-color: #e8f5e9;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.harvest-date .month {
    font-size: 10px;
    text-transform: uppercase;
    color: #4caf50;
    font-weight: bold;
}

.harvest-date .day {
    font-size: 18px;
    font-weight: bold;
    color: #333;
}

.harvest-details {
    flex: 1;
}

.harvest-crop {
    font-size: 14px;
    color: #333;
    font-weight: 500;
    margin-bottom: 5px;
}

.harvest-field {
    font-size: 12px;
    color: #888;
}

.harvest-link {
    color: #4caf50;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    transition: background-color 0.2s;
}

.harvest-link:hover {
    background-color: #e8f5e9;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.6);
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border-radius: 10px;
    width: 90%;
    max-width: 1000px;
    max-height: 80vh;
    box-shadow: 0 5px 25px rgba(0,0,0,0.2);
    animation: slideIn 0.3s ease;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    background-color: #f9f9f9;
    border-radius: 10px 10px 0 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.modal-body {
    padding: 20px;
    height: 500px;
}

.close {
    position: absolute;
    top: 15px;
    right: 20px;
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
}

.close:hover,
.close:focus {
    color: #000;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    border-radius: 4px;
    border: none;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.btn i {
    margin-right: 6px;
}

.btn-outline-secondary {
    background-color: transparent;
    border: 1px solid #ddd;
    color: #666;
}

.btn-outline-secondary:hover {
    background-color: #f5f5f5;
    border-color: #4caf50;
    color: #4caf50;
}

.btn-outline-primary {
    background-color: transparent;
    border: 1px solid #007bff;
    color: #007bff;
}

.btn-outline-primary:hover {
    background-color: #007bff;
    color: white;
}

.btn-outline-secondary:active {
    transform: scale(0.98);
}

/* Spinning animation for refresh button */
.fa-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive styles */
@media (max-width: 992px) {
    .top-widgets-row,
    .info-section {
        flex-direction: column;
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .chart-info {
        flex-direction: column;
        gap: 10px;
    }
    
    .chart-controls {
        flex-wrap: wrap;
        gap: 5px;
    }
}

@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .weather-conditions {
        flex-direction: column;
        gap: 10px;
        align-items: center;
    }
    
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
}   

/* Loading state for weather */
#current-temp.loading,
#circle-temp.loading,
#wind-speed.loading,
#humidity.loading,
#pressure.loading {
    color: #999;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { opacity: 0.6; }
    50% { opacity: 1; }
    100% { opacity: 0.6; }
}