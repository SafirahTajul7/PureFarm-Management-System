<?php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // SUPERVISOR ACCESS ONLY

require_once 'includes/db.php';

$supervisor_id = $_SESSION['user_id'];

// Get recent weather data from environmental readings
try {
    $weather_stmt = $pdo->prepare("
        SELECT 
            er.*,
            f.field_name,
            f.location
        FROM environmental_readings er
        LEFT JOIN fields f ON er.field_id = f.id
        WHERE er.supervisor_id = ?
        AND er.reading_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)
        ORDER BY er.reading_date DESC, er.reading_time DESC
        LIMIT 10
    ");
    $weather_stmt->execute([$supervisor_id]);
    $recent_weather = $weather_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current conditions (latest reading)
    $current_conditions = !empty($recent_weather) ? $recent_weather[0] : null;
    
} catch(PDOException $e) {
    $recent_weather = [];
    $current_conditions = null;
}

// Generate weather summary
$weather_summary = [
    'temperature' => $current_conditions ? $current_conditions['temperature'] : 25,
    'humidity' => $current_conditions ? $current_conditions['humidity'] : 65,
    'wind_speed' => $current_conditions ? $current_conditions['wind_speed'] : 10,
    'wind_direction' => $current_conditions ? $current_conditions['wind_direction'] : 'NW',
    'pressure' => $current_conditions ? $current_conditions['barometric_pressure'] : 1013,
    'visibility' => $current_conditions ? $current_conditions['visibility'] : 10,
    'uv_index' => $current_conditions ? $current_conditions['uv_index'] : 5,
    'last_updated' => $current_conditions ? $current_conditions['reading_date'] . ' ' . $current_conditions['reading_time'] : date('Y-m-d H:i:s')
];

$pageTitle = 'Current Weather';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-cloud-sun"></i> Current Weather Conditions</h2>
        <div class="action-buttons">
            <button class="btn btn-success" onclick="location.href='record_environmental_data.php'">
                <i class="fas fa-plus"></i> Record New Reading
            </button>
            <button class="btn btn-secondary" onclick="location.href='supervisor_environmental.php'">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
    </div>

    <!-- Current Weather Display -->
    <div class="current-weather-section">
        <div class="weather-main-card">
            <div class="weather-primary">
                <div class="temperature-display">
                    <span class="temp-value"><?php echo round($weather_summary['temperature']); ?></span>
                    <span class="temp-unit">°C</span>
                </div>
                <div class="weather-info">
                    <div class="location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo $current_conditions ? htmlspecialchars($current_conditions['field_name']) : 'Farm Location'; ?>
                    </div>
                    <div class="last-updated">
                        Last updated: <?php echo date('M d, Y g:i A', strtotime($weather_summary['last_updated'])); ?>
                    </div>
                    <div class="weather-status">
                        <?php
                        $temp = $weather_summary['temperature'];
                        $humidity = $weather_summary['humidity'];
                        
                        if ($temp > 30 && $humidity < 40) {
                            echo '<span class="status-hot"><i class="fas fa-sun"></i> Hot & Dry</span>';
                        } elseif ($temp > 25 && $humidity > 80) {
                            echo '<span class="status-humid"><i class="fas fa-water"></i> Hot & Humid</span>';
                        } elseif ($temp < 15) {
                            echo '<span class="status-cool"><i class="fas fa-snowflake"></i> Cool</span>';
                        } else {
                            echo '<span class="status-pleasant"><i class="fas fa-cloud-sun"></i> Pleasant</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="weather-details">
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="detail-info">
                        <span class="detail-label">Humidity</span>
                        <span class="detail-value"><?php echo round($weather_summary['humidity']); ?>%</span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-wind"></i>
                    </div>
                    <div class="detail-info">
                        <span class="detail-label">Wind</span>
                        <span class="detail-value">
                            <?php echo round($weather_summary['wind_speed']); ?> km/h
                            <?php if($weather_summary['wind_direction']): ?>
                            <small>(<?php echo $weather_summary['wind_direction']; ?>)</small>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-weight"></i>
                    </div>
                    <div class="detail-info">
                        <span class="detail-label">Pressure</span>
                        <span class="detail-value"><?php echo round($weather_summary['pressure']); ?> hPa</span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="detail-info">
                        <span class="detail-label">Visibility</span>
                        <span class="detail-value"><?php echo round($weather_summary['visibility']); ?> km</span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-sun"></i>
                    </div>
                    <div class="detail-info">
                        <span class="detail-label">UV Index</span>
                        <span class="detail-value uv-<?php echo ($weather_summary['uv_index'] <= 2) ? 'low' : (($weather_summary['uv_index'] <= 7) ? 'moderate' : 'high'); ?>">
                            <?php echo round($weather_summary['uv_index'], 1); ?>
                        </span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-thermometer-quarter"></i>
                    </div>
                    <div class="detail-info">
                        <span class="detail-label">Feels Like</span>
                        <span class="detail-value">
                            <?php 
                            // Simple heat index calculation
                            $feels_like = $weather_summary['temperature'] + (($weather_summary['humidity'] - 40) * 0.1);
                            echo round($feels_like); 
                            ?>°C
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Weather Alerts -->
    <div class="weather-alerts-section">
        <h3><i class="fas fa-exclamation-triangle"></i> Weather Alerts & Recommendations</h3>
        <div class="alerts-grid">
            <?php
            $alerts = [];
            
            // Generate alerts based on conditions
            if ($weather_summary['temperature'] > 35) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'fas fa-thermometer-full',
                    'title' => 'Extreme Heat Warning',
                    'message' => 'Temperature is extremely high. Ensure adequate irrigation and protect livestock from heat stress.',
                    'action' => 'Increase watering frequency'
                ];
            } elseif ($weather_summary['temperature'] > 30) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'fas fa-sun',
                    'title' => 'High Temperature Alert',
                    'message' => 'Hot weather conditions. Monitor crop water needs and livestock comfort.',
                    'action' => 'Check irrigation systems'
                ];
            }
            
            if ($weather_summary['humidity'] > 85) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'fas fa-tint',
                    'title' => 'High Humidity Alert',
                    'message' => 'Very humid conditions may increase disease risk in crops and livestock.',
                    'action' => 'Monitor for fungal diseases'
                ];
            } elseif ($weather_summary['humidity'] < 30) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'fas fa-wind',
                    'title' => 'Low Humidity Alert',
                    'message' => 'Dry conditions may stress crops and increase fire risk.',
                    'action' => 'Increase irrigation frequency'
                ];
            }
            
            if ($weather_summary['wind_speed'] > 25) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'fas fa-wind',
                    'title' => 'Strong Wind Alert',
                    'message' => 'High wind speeds may damage crops and affect spraying operations.',
                    'action' => 'Secure equipment and delay spraying'
                ];
            }
            
            if ($weather_summary['uv_index'] > 8) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'fas fa-sun',
                    'title' => 'High UV Index',
                    'message' => 'Very high UV levels. Ensure worker protection and monitor livestock.',
                    'action' => 'Provide shade and sun protection'
                ];
            }
            
            if (empty($alerts)) {
                $alerts[] = [
                    'type' => 'success',
                    'icon' => 'fas fa-check-circle',
                    'title' => 'Favorable Conditions',
                    'message' => 'Weather conditions are currently favorable for farming operations.',
                    'action' => 'Continue normal operations'
                ];
            }
            
            foreach($alerts as $alert): ?>
            <div class="alert-card alert-<?php echo $alert['type']; ?>">
                <div class="alert-icon">
                    <i class="<?php echo $alert['icon']; ?>"></i>
                </div>
                <div class="alert-content">
                    <h4><?php echo $alert['title']; ?></h4>
                    <p><?php echo $alert['message']; ?></p>
                    <div class="alert-action">
                        <strong>Recommended Action:</strong> <?php echo $alert['action']; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent Weather History -->
    <div class="weather-history-section">
        <h3><i class="fas fa-history"></i> Recent Weather History</h3>
        
        <?php if (empty($recent_weather)): ?>
        <div class="no-data">
            <i class="fas fa-cloud-sun"></i>
            <h4>No Recent Weather Data</h4>
            <p>No weather readings have been recorded recently for your assigned fields.</p>
            <button class="btn btn-success" onclick="location.href='record_environmental_data.php'">
                <i class="fas fa-plus"></i> Record Weather Data
            </button>
        </div>
        <?php else: ?>
        <div class="weather-timeline">
            <?php foreach($recent_weather as $reading): ?>
            <div class="timeline-item">
                <div class="timeline-time">
                    <div class="time-date"><?php echo date('M d', strtotime($reading['reading_date'])); ?></div>
                    <div class="time-hour"><?php echo date('g:i A', strtotime($reading['reading_time'])); ?></div>
                </div>
                <div class="timeline-content">
                    <div class="reading-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($reading['field_name']); ?>
                    </div>
                    <div class="reading-values">
                        <span class="temp-reading">
                            <i class="fas fa-thermometer-half"></i>
                            <?php echo round($reading['temperature']); ?>°C
                        </span>
                        <span class="humidity-reading">
                            <i class="fas fa-tint"></i>
                            <?php echo round($reading['humidity']); ?>%
                        </span>
                        <?php if($reading['wind_speed']): ?>
                        <span class="wind-reading">
                            <i class="fas fa-wind"></i>
                            <?php echo round($reading['wind_speed']); ?> km/h
                        </span>
                        <?php endif; ?>
                        <?php if($reading['rainfall'] && $reading['rainfall'] > 0): ?>
                        <span class="rain-reading">
                            <i class="fas fa-cloud-rain"></i>
                            <?php echo $reading['rainfall']; ?> mm
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if($reading['notes']): ?>
                    <div class="reading-notes">
                        <i class="fas fa-sticky-note"></i>
                        <?php echo htmlspecialchars($reading['notes']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Weather Summary Chart -->
    <div class="weather-chart-section">
        <h3><i class="fas fa-chart-line"></i> 24-Hour Weather Trend</h3>
        <div class="chart-container">
            <canvas id="weatherChart"></canvas>
        </div>
    </div>
</div>

<style>
/* Current Weather Section */
.current-weather-section {
    margin-bottom: 30px;
}

.weather-main-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.weather-main-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 200%;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    z-index: 1;
}

.weather-primary {
    position: relative;
    z-index: 2;
}

.temperature-display {
    display: flex;
    align-items: baseline;
    margin-bottom: 20px;
}

.temp-value {
    font-size: 80px;
    font-weight: 300;
    line-height: 1;
}

.temp-unit {
    font-size: 32px;
    margin-left: 8px;
    opacity: 0.8;
}

.weather-info {
    font-size: 16px;
}

.location {
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 8px;
}

.last-updated {
    opacity: 0.8;
    margin-bottom: 15px;
}

.weather-status span {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    background: rgba(255,255,255,0.2);
    border-radius: 20px;
    font-weight: 500;
}

.weather-status i {
    margin-right: 8px;
}

.weather-details {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.detail-item {
    display: flex;
    align-items: center;
    padding: 15px;
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
    backdrop-filter: blur(10px);
}

.detail-icon {
    font-size: 24px;
    margin-right: 15px;
    opacity: 0.8;
}

.detail-info {
    display: flex;
    flex-direction: column;
}

.detail-label {
    font-size: 14px;
    opacity: 0.8;
    margin-bottom: 4px;
}

.detail-value {
    font-size: 18px;
    font-weight: 600;
}

.uv-low { color: #28a745; }
.uv-moderate { color: #ffc107; }
.uv-high { color: #dc3545; }

/* Weather Alerts */
.weather-alerts-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 30px;
}

.weather-alerts-section h3 {
    color: #333;
    margin-bottom: 20px;
    font-size: 18px;
    font-weight: 600;
}

.alerts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.alert-card {
    display: flex;
    padding: 20px;
    border-radius: 12px;
    border-left: 4px solid;
}

.alert-danger {
    background: #fff5f5;
    border-left-color: #dc3545;
}

.alert-warning {
    background: #fffbf0;
    border-left-color: #ffc107;
}

.alert-info {
    background: #f0f8ff;
    border-left-color: #17a2b8;
}

.alert-success {
    background: #f0fff4;
    border-left-color: #28a745;
}

.alert-icon {
    margin-right: 15px;
    font-size: 24px;
}

.alert-danger .alert-icon { color: #dc3545; }
.alert-warning .alert-icon { color: #ffc107; }
.alert-info .alert-icon { color: #17a2b8; }
.alert-success .alert-icon { color: #28a745; }

.alert-content h4 {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 600;
}

.alert-content p {
    margin: 0 0 10px 0;
    color: #666;
}

.alert-action {
    font-size: 14px;
    color: #333;
}

/* Weather History */
.weather-history-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 30px;
}

.weather-history-section h3 {
    color: #333;
    margin-bottom: 20px;
    font-size: 18px;
    font-weight: 600;
}

.weather-timeline {
    position: relative;
}

.weather-timeline::before {
    content: '';
    position: absolute;
    left: 80px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    display: flex;
    margin-bottom: 25px;
    position: relative;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 71px;
    top: 8px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #20c997;
    border: 3px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 2;
}

.timeline-time {
    width: 60px;
    text-align: center;
    margin-right: 30px;
    flex-shrink: 0;
}

.time-date {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.time-hour {
    font-size: 12px;
    color: #6c757d;
}

.timeline-content {
    flex: 1;
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.reading-location {
    font-weight: 600;
    color: #333;
    margin-bottom: 10px;
}

.reading-location i {
    color: #6c757d;
    margin-right: 5px;
}

.reading-values {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 10px;
}

.reading-values span {
    display: flex;
    align-items: center;
    font-size: 14px;
    font-weight: 500;
}

.reading-values i {
    margin-right: 5px;
    width: 16px;
}

.temp-reading { color: #fd7e14; }
.humidity-reading { color: #17a2b8; }
.wind-reading { color: #6c757d; }
.rain-reading { color: #007bff; }

.reading-notes {
    font-size: 14px;
    color: #666;
    font-style: italic;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #dee2e6;
}

.reading-notes i {
    margin-right: 5px;
    color: #ffc107;
}

/* Weather Chart */
.weather-chart-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 30px;
}

.weather-chart-section h3 {
    color: #333;
    margin-bottom: 20px;
    font-size: 18px;
    font-weight: 600;
}

.chart-container {
    height: 300px;
    position: relative;
}

/* No Data State */
.no-data {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.no-data i {
    font-size: 48px;
    margin-bottom: 20px;
    color: #dee2e6;
}

.no-data h4 {
    margin-bottom: 10px;
    color: #495057;
}

.no-data p {
    margin-bottom: 30px;
    font-size: 16px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .weather-main-card {
        grid-template-columns: 1fr;
        gap: 20px;
        padding: 20px;
    }
    
    .temp-value {
        font-size: 60px;
    }
    
    .temp-unit {
        font-size: 24px;
    }
    
    .weather-details {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .alerts-grid {
        grid-template-columns: 1fr;
    }
    
    .timeline-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .timeline-time {
        margin-bottom: 10px;
        margin-right: 0;
    }
    
    .weather-timeline::before {
        display: none;
    }
    
    .timeline-item::before {
        display: none;
    }
    
    .reading-values {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare weather chart data
    const recentWeather = <?php echo json_encode($recent_weather); ?>;
    
    if (recentWeather && recentWeather.length > 0) {
        // Process data for chart
        const labels = [];
        const tempData = [];
        const humidityData = [];
        const windData = [];
        
        // Reverse to show chronological order
        recentWeather.reverse().forEach(reading => {
            const time = new Date(reading.reading_date + ' ' + reading.reading_time);
            labels.push(time.toLocaleTimeString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                hour: 'numeric',
                minute: '2-digit'
            }));
            tempData.push(parseFloat(reading.temperature) || 0);
            humidityData.push(parseFloat(reading.humidity) || 0);
            windData.push(parseFloat(reading.wind_speed) || 0);
        });
        
        // Create chart
        const ctx = document.getElementById('weatherChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Temperature (°C)',
                        data: tempData,
                        borderColor: '#fd7e14',
                        backgroundColor: 'rgba(253, 126, 20, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Humidity (%)',
                        data: humidityData,
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'Wind Speed (km/h)',
                        data: windData,
                        borderColor: '#6c757d',
                        backgroundColor: 'rgba(108, 117, 125, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y2'
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
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Time'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Temperature (°C)'
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
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                    y2: {
                        type: 'linear',
                        display: false,
                        position: 'right',
                    }
                }
            }
        });
    } else {
        // No data available
        document.querySelector('.chart-container').innerHTML = 
            '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #6c757d;">' +
            '<div style="text-align: center;">' +
            '<i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>' +
            '<p>No weather data available for chart</p>' +
            '</div></div>';
    }
});

// Auto-refresh weather data every 10 minutes
setInterval(() => {
    if (!document.hidden) {
        location.reload();
    }
}, 600000); // 10 minutes

// Weather status animations
function animateWeatherStatus() {
    const statusElements = document.querySelectorAll('.weather-status span');
    statusElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
        element.classList.add('animate-fade-in');
    });
}

// Add weather animation styles
const weatherStyles = document.createElement('style');
weatherStyles.textContent = `
    @keyframes fade-in {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .animate-fade-in {
        animation: fade-in 0.5s ease-out forwards;
    }
    
    .weather-main-card {
        animation: slide-in 0.8s ease-out;
    }
    
    @keyframes slide-in {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(weatherStyles);

// Initialize animations when page loads
document.addEventListener('DOMContentLoaded', animateWeatherStatus);
</script>