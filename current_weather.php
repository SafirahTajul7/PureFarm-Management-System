<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Set timezone to Malaysia time (MYT/UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Fetch weather data - in a real implementation, this might come from a weather API
// For now, we'll use sample data or fetch from a local database table if available
// Weather data will follow Malaysian time (MYT/UTC+8)
try {
    // Check if we have a weather_data table
    $hasWeatherTable = false;
    $tables = $pdo->query("SHOW TABLES LIKE 'weather_data'")->fetchAll();
    if (count($tables) > 0) {
        $hasWeatherTable = true;
    }
    
    if ($hasWeatherTable) {
        // Fetch the most recent weather data
        $stmt = $pdo->prepare("
            SELECT * FROM weather_data 
            WHERE location_id = ? 
            ORDER BY recorded_at DESC 
            LIMIT 1
        ");
        $stmt->execute([1]); // Assuming 1 is the ID for the main farm location
        $weatherData = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Use sample data if no table exists
        $weatherData = [
            'temperature' => 24.5,
            'humidity' => 65,
            'wind_speed' => 12,
            'wind_direction' => 'NE',
            'pressure' => 1012,
            'precipitation' => 0,
            'condition' => 'Partly Cloudy',
            'recorded_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Fetch locations/fields
    $locations = $pdo->query("
        SELECT id, name FROM fields
        WHERE status = 'active'
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($locations)) {
        // Sample locations if none in database
        $locations = [
            ['id' => 1, 'name' => 'Main Field'],
            ['id' => 2, 'name' => 'North Field'],
            ['id' => 3, 'name' => 'South Field'],
            ['id' => 4, 'name' => 'East Field']
        ];
    }
    
    // Get hourly forecast data (sample data)
    $hourlyForecast = [
        ['time' => '12:00', 'temp' => 25, 'condition' => 'Sunny', 'icon' => 'sun'],
        ['time' => '13:00', 'temp' => 26, 'condition' => 'Sunny', 'icon' => 'sun'],
        ['time' => '14:00', 'temp' => 26, 'condition' => 'Partly Cloudy', 'icon' => 'cloud-sun'],
        ['time' => '15:00', 'temp' => 25, 'condition' => 'Partly Cloudy', 'icon' => 'cloud-sun'],
        ['time' => '16:00', 'temp' => 24, 'condition' => 'Cloudy', 'icon' => 'cloud'],
        ['time' => '17:00', 'temp' => 23, 'condition' => 'Cloudy', 'icon' => 'cloud'],
        ['time' => '18:00', 'temp' => 22, 'condition' => 'Clear', 'icon' => 'moon']
    ];
    
    // Get daily forecast data (sample data)
    $dailyForecast = [
        ['day' => 'Today', 'high' => 26, 'low' => 18, 'condition' => 'Partly Cloudy', 'icon' => 'cloud-sun'],
        ['day' => 'Tomorrow', 'high' => 28, 'low' => 19, 'condition' => 'Sunny', 'icon' => 'sun'],
        ['day' => date('D', strtotime('+2 days')), 'high' => 27, 'low' => 20, 'condition' => 'Sunny', 'icon' => 'sun'],
        ['day' => date('D', strtotime('+3 days')), 'high' => 24, 'low' => 18, 'condition' => 'Rain', 'icon' => 'cloud-rain'],
        ['day' => date('D', strtotime('+4 days')), 'high' => 23, 'low' => 17, 'condition' => 'Rain', 'icon' => 'cloud-rain']
    ];
    
    // Weather alerts (sample data)
    $weatherAlerts = [
        [
            'type' => 'warning',
            'title' => 'Heavy Rain Expected',
            'description' => 'Heavy rainfall expected in 3 days. Consider adjusting irrigation schedules.',
            'time' => date('Y-m-d', strtotime('+3 days'))
        ]
    ];

} catch(PDOException $e) {
    error_log("Error fetching weather data: " . $e->getMessage());
    // Set default values in case of error
    $weatherData = [
        'temperature' => 24.5,
        'humidity' => 65,
        'wind_speed' => 12,
        'wind_direction' => 'NE',
        'pressure' => 1012,
        'precipitation' => 0,
        'condition' => 'Partly Cloudy',
        'recorded_at' => date('Y-m-d H:i:s')
    ];
    $locations = [
        ['id' => 1, 'name' => 'Main Field'],
        ['id' => 2, 'name' => 'North Field'],
        ['id' => 3, 'name' => 'South Field'],
        ['id' => 4, 'name' => 'East Field']
    ];
    $hourlyForecast = [
        ['time' => '12:00', 'temp' => 25, 'condition' => 'Sunny', 'icon' => 'sun'],
        ['time' => '13:00', 'temp' => 26, 'condition' => 'Sunny', 'icon' => 'sun'],
        ['time' => '14:00', 'temp' => 26, 'condition' => 'Partly Cloudy', 'icon' => 'cloud-sun'],
        ['time' => '15:00', 'temp' => 25, 'condition' => 'Partly Cloudy', 'icon' => 'cloud-sun'],
        ['time' => '16:00', 'temp' => 24, 'condition' => 'Cloudy', 'icon' => 'cloud'],
        ['time' => '17:00', 'temp' => 23, 'condition' => 'Cloudy', 'icon' => 'cloud'],
        ['time' => '18:00', 'temp' => 22, 'condition' => 'Clear', 'icon' => 'moon']
    ];
    $dailyForecast = [
        ['day' => 'Today', 'high' => 26, 'low' => 18, 'condition' => 'Partly Cloudy', 'icon' => 'cloud-sun'],
        ['day' => 'Tomorrow', 'high' => 28, 'low' => 19, 'condition' => 'Sunny', 'icon' => 'sun'],
        ['day' => date('D', strtotime('+2 days')), 'high' => 27, 'low' => 20, 'condition' => 'Sunny', 'icon' => 'sun'],
        ['day' => date('D', strtotime('+3 days')), 'high' => 24, 'low' => 18, 'condition' => 'Rain', 'icon' => 'cloud-rain'],
        ['day' => date('D', strtotime('+4 days')), 'high' => 23, 'low' => 17, 'condition' => 'Rain', 'icon' => 'cloud-rain']
    ];
    $weatherAlerts = [
        [
            'type' => 'warning',
            'title' => 'Heavy Rain Expected',
            'description' => 'Heavy rainfall expected in 3 days. Consider adjusting irrigation schedules.',
            'time' => date('Y-m-d', strtotime('+3 days'))
        ]
    ];
}

// Get weather condition icon class
function getWeatherIconClass($condition) {
    $condition = strtolower($condition);
    if (strpos($condition, 'sun') !== false && strpos($condition, 'cloud') !== false) {
        return 'cloud-sun';
    } elseif (strpos($condition, 'sun') !== false || strpos($condition, 'clear') !== false) {
        return 'sun';
    } elseif (strpos($condition, 'cloud') !== false) {
        return 'cloud';
    } elseif (strpos($condition, 'rain') !== false) {
        return 'cloud-rain';
    } elseif (strpos($condition, 'storm') !== false || strpos($condition, 'thunder') !== false) {
        return 'bolt';
    } elseif (strpos($condition, 'snow') !== false) {
        return 'snowflake';
    } elseif (strpos($condition, 'fog') !== false || strpos($condition, 'mist') !== false) {
        return 'smog';
    } else {
        return 'cloud';
    }
}

$pageTitle = 'Current Weather';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-cloud-sun"></i> Current Weather</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="refreshWeatherData()">
                <i class="fas fa-sync-alt"></i> Refresh Data
            </button>
        </div>
    </div>
    
    <!-- Current Weather Card -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="current-weather-card">
                <div class="weather-header">
                    <div class="location-info">
                        <h3>Farm Main Location</h3>
                        <p class="text-muted">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php echo isset($weatherData['location']) ? $weatherData['location'] : 'Main Farm'; ?>
                        </p>
                        <p class="text-muted">
                            <i class="fas fa-clock"></i> 
                            Last Updated: <?php echo date('M d, Y g:i A', strtotime($weatherData['recorded_at'])); ?> (MYT)
                        </p>
                    </div>
                    <div class="weather-condition text-center">
                        <i class="fas fa-<?php echo getWeatherIconClass($weatherData['condition']); ?> weather-icon"></i>
                        <div class="condition-text"><?php echo $weatherData['condition']; ?></div>
                    </div>
                </div>
                
                <div class="weather-details">
                    <div class="temperature">
                        <span class="temp-value"><?php echo round($weatherData['temperature']); ?></span>
                        <span class="temp-unit">°C</span>
                    </div>
                    
                    <div class="other-details">
                        <div class="detail-item">
                            <i class="fas fa-tint"></i>
                            <span class="detail-label">Humidity</span>
                            <span class="detail-value"><?php echo $weatherData['humidity']; ?>%</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-wind"></i>
                            <span class="detail-label">Wind</span>
                            <span class="detail-value"><?php echo $weatherData['wind_speed']; ?> km/h <?php echo $weatherData['wind_direction']; ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-compress-alt"></i>
                            <span class="detail-label">Pressure</span>
                            <span class="detail-value"><?php echo $weatherData['pressure']; ?> hPa</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-cloud-rain"></i>
                            <span class="detail-label">Precipitation</span>
                            <span class="detail-value"><?php echo $weatherData['precipitation']; ?> mm</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Weather Alerts -->
        <div class="col-lg-4">
            <div class="weather-alerts-card">
                <h4><i class="fas fa-exclamation-triangle"></i> Weather Alerts</h4>
                
                <?php if (empty($weatherAlerts)): ?>
                <div class="no-alerts">
                    <i class="fas fa-check-circle"></i>
                    <p>No active weather alerts</p>
                </div>
                <?php else: ?>
                    <?php foreach ($weatherAlerts as $alert): ?>
                    <div class="alert alert-<?php echo $alert['type']; ?> weather-alert">
                        <h5><?php echo $alert['title']; ?></h5>
                        <p><?php echo $alert['description']; ?></p>
                        <small class="text-muted">Expected: <?php echo date('M d, Y', strtotime($alert['time'])); ?></small>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    
    <!-- Hourly Forecast -->
    <div class="section-header">
        <h3><i class="fas fa-clock"></i> Hourly Forecast</h3>
    </div>
    
    <div class="hourly-forecast mb-4">
        <?php foreach ($hourlyForecast as $forecast): ?>
        <div class="forecast-item">
            <div class="forecast-time"><?php echo $forecast['time']; ?></div>
            <div class="forecast-icon">
                <i class="fas fa-<?php echo $forecast['icon']; ?>"></i>
            </div>
            <div class="forecast-temp"><?php echo $forecast['temp']; ?>°C</div>
            <div class="forecast-condition"><?php echo $forecast['condition']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Daily Forecast -->
    <div class="section-header">
        <h3><i class="fas fa-calendar-day"></i> 5-Day Forecast</h3>
    </div>
    
    <div class="daily-forecast mb-4">
        <?php foreach ($dailyForecast as $forecast): ?>
        <div class="forecast-day">
            <div class="day-name"><?php echo $forecast['day']; ?></div>
            <div class="day-icon">
                <i class="fas fa-<?php echo $forecast['icon']; ?>"></i>
            </div>
            <div class="day-condition"><?php echo $forecast['condition']; ?></div>
            <div class="day-temp">
                <span class="high-temp"><?php echo $forecast['high']; ?>°</span>
                <span class="low-temp"><?php echo $forecast['low']; ?>°</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Location Weather Cards -->
    <div class="section-header">
        <h3><i class="fas fa-map-marked-alt"></i> Field Conditions</h3>
    </div>
    
    <div class="field-conditions mb-5">
        <div class="row">
            <?php foreach ($locations as $location): ?>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="location-weather-card">
                    <div class="location-name"><?php echo $location['name']; ?></div>
                    
                    <?php 
                    // In a real app, you would fetch specific data for each location
                    // Here we're just using sample data with slight variations
                    $locTemp = $weatherData['temperature'] + (mt_rand(-20, 20) / 10);
                    $locHumidity = min(95, max(30, $weatherData['humidity'] + mt_rand(-10, 10)));
                    $locWind = $weatherData['wind_speed'] + (mt_rand(-30, 30) / 10);
                    ?>
                    
                    <div class="location-weather">
                        <div class="location-temp">
                            <i class="fas fa-<?php echo getWeatherIconClass($weatherData['condition']); ?>"></i>
                            <span><?php echo round($locTemp); ?>°C</span>
                        </div>
                        <div class="location-details">
                            <div class="detail">
                                <i class="fas fa-tint"></i> <?php echo round($locHumidity); ?>%
                            </div>
                            <div class="detail">
                                <i class="fas fa-wind"></i> <?php echo round($locWind, 1); ?> km/h
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
/* Current Weather Card */
.current-weather-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 20px;
}

.weather-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: linear-gradient(to right, #20c997, #0d6efd);
    color: white;
}

.location-info h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.weather-condition {
    text-align: center;
}

.weather-icon {
    font-size: 48px;
    margin-bottom: 5px;
}

.condition-text {
    font-weight: 500;
}

.weather-details {
    display: flex;
    padding: 20px;
}

.temperature {
    display: flex;
    align-items: baseline;
    font-weight: 300;
    margin-right: 40px;
}

.temp-value {
    font-size: 64px;
    line-height: 1;
}

.temp-unit {
    font-size: 24px;
    margin-left: 5px;
}

.other-details {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-gap: 15px;
    align-content: center;
}

.detail-item {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.detail-item i {
    font-size: 18px;
    color: #6c757d;
    margin-bottom: 5px;
}

.detail-label {
    font-size: 14px;
    color: #6c757d;
}

.detail-value {
    font-size: 18px;
    font-weight: 500;
}

/* Weather Alerts */
.weather-alerts-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 20px;
    height: 100%;
}

.weather-alerts-card h4 {
    margin-bottom: 15px;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.no-alerts {
    text-align: center;
    color: #28a745;
    padding: 20px 0;
}

.no-alerts i {
    font-size: 48px;
    margin-bottom: 10px;
}

.weather-alert {
    margin-bottom: 10px;
}

.weather-alert h5 {
    font-size: 16px;
    margin-bottom: 5px;
}

.weather-alert p {
    font-size: 14px;
    margin-bottom: 5px;
}

/* Hourly Forecast */
.hourly-forecast {
    display: flex;
    overflow-x: auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 15px;
}

.forecast-item {
    min-width: 100px;
    text-align: center;
    padding: 10px;
    border-right: 1px solid #eee;
}

.forecast-item:last-child {
    border-right: none;
}

.forecast-time {
    font-weight: 500;
    margin-bottom: 8px;
}

.forecast-icon {
    font-size: 24px;
    margin-bottom: 8px;
    color: #6c757d;
}

.forecast-temp {
    font-size: 20px;
    font-weight: 500;
    margin-bottom: 5px;
}

.forecast-condition {
    font-size: 12px;
    color: #6c757d;
}

/* Daily Forecast */
.daily-forecast {
    display: flex;
    flex-wrap: wrap;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 15px;
}

.forecast-day {
    flex: 1;
    min-width: 120px;
    text-align: center;
    padding: 15px 10px;
    border-right: 1px solid #eee;
}

.forecast-day:last-child {
    border-right: none;
}

.day-name {
    font-weight: 600;
    margin-bottom: 10px;
}

.day-icon {
    font-size: 28px;
    margin-bottom: 10px;
    color: #6c757d;
}

.day-condition {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 10px;
}

.day-temp {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
}

.high-temp {
    font-size: 18px;
    font-weight: 500;
}

.low-temp {
    font-size: 16px;
    color: #6c757d;
}

/* Location Weather Cards */
.location-weather-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 15px;
    height: 100%;
}

.location-name {
    font-weight: 600;
    font-size: 18px;
    margin-bottom: 15px;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 8px;
}

.location-weather {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.location-temp {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.location-temp i {
    font-size: 32px;
    margin-bottom: 5px;
    color: #6c757d;
}

.location-temp span {
    font-size: 24px;
    font-weight: 500;
}

.location-details {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.detail {
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail i {
    width: 16px;
    color: #6c757d;
}

/* Section Headers */
.section-header {
    margin: 30px 0 15px 0;
    display: flex;
    align-items: center;
}

.section-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.section-header i {
    margin-right: 10px;
    color: #20c997;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .weather-header {
        flex-direction: column;
        text-align: center;
    }
    
    .location-info {
        margin-bottom: 20px;
    }
    
    .weather-details {
        flex-direction: column;
    }
    
    .temperature {
        justify-content: center;
        margin-right: 0;
        margin-bottom: 20px;
    }
    
    .other-details {
        grid-template-columns: 1fr 1fr;
    }
    
    .forecast-day {
        min-width: 100px;
        flex: none;
        width: 50%;
        border-bottom: 1px solid #eee;
        border-right: none;
    }
    
    .forecast-day:nth-child(odd) {
        border-right: 1px solid #eee;
    }
    
    .forecast-day:nth-last-child(-n+2) {
        border-bottom: none;
    }
}

@media (max-width: 576px) {
    .forecast-day {
        width: 100%;
        border-right: none !important;
    }
    
    .forecast-day:last-child {
        border-bottom: none;
    }
}
</style>

<script>
function refreshWeatherData() {
    // Simulate data refresh with loading animation
    const weatherCard = document.querySelector('.current-weather-card');
    weatherCard.style.opacity = '0.6';
    weatherCard.style.pointerEvents = 'none';
    
    // Add loading spinner
    const loadingEl = document.createElement('div');
    loadingEl.classList.add('text-center', 'py-4');
    loadingEl.innerHTML = '<i class="fas fa-sync fa-spin fa-2x"></i><p class="mt-2">Updating weather data...</p>';
    weatherCard.appendChild(loadingEl);
    
    // Simulate API call delay
    setTimeout(() => {
        // In a real application, this would be an AJAX call to refresh the data
        location.reload();
    }, 1500);
}

// Initialize any charts or interactive elements
document.addEventListener('DOMContentLoaded', function() {
    console.log('Current Weather page loaded');
    // Any additional JavaScript initialization would go here
});
</script>

<?php include 'includes/footer.php'; ?>