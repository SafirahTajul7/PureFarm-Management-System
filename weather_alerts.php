<script>
document.addEventListener('DOMContentLoaded', function() {
    // Alert filtering
    const filterButtons = document.querySelectorAll('.alerts-filter .btn');
    const alertItems = document.querySelectorAll('.alert-item');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Update active button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Get filter type
            const filterType = this.getAttribute('data-filter');
            
            // Filter alerts
            alertItems.forEach(item => {
                if (filterType === 'all' || item.getAttribute('data-alert-type') === filterType) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script><?php include 'includes/footer.php'; ?><?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Set timezone to Malaysia time (MYT/UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Function to get weather condition icon class
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

// Get weather alerts
try {
    // Check if notifications table exists
    $hasNotificationsTable = false;
    $tables = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetchAll();
    if (count($tables) > 0) {
        $hasNotificationsTable = true;
    }
    
    if ($hasNotificationsTable) {
        // Get alerts from the notifications table with weather-related types
        $stmt = $pdo->query("
            SELECT * FROM notifications 
            WHERE type LIKE '%temperature%' 
               OR type LIKE '%humidity%' 
               OR type LIKE '%wind%' 
               OR type LIKE '%precipitation%' 
               OR type LIKE '%severe%' 
               OR type LIKE '%daily_summary%'
            ORDER BY created_at DESC
        ");
        $weatherAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Sample alerts if no notifications table exists
        $weatherAlerts = [
            [
                'id' => 1,
                'type' => 'severe_weather',
                'message' => 'Heavy Rain Expected: Heavy rainfall expected in 3 days. Consider adjusting irrigation schedules.',
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'id' => 2,
                'type' => 'temperature_high',
                'message' => 'High temperature alert: Current temperature is 36°C, which exceeds your threshold of 35°C.',
                'is_read' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
            ],
            [
                'id' => 3,
                'type' => 'humidity_high',
                'message' => 'High humidity alert: Current humidity is 92%, which exceeds your threshold of 90%.',
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
            ]
        ];
    }
    
    // Get alert types for filtering
    if (!empty($weatherAlerts)) {
        $alertTypes = [];
        foreach ($weatherAlerts as $alert) {
            $type = explode('_', $alert['type'])[0];
            if (!in_array($type, $alertTypes) && $type !== 'daily') {
                $alertTypes[] = $type;
            }
        }
    } else {
        $alertTypes = ['temperature', 'humidity', 'wind', 'precipitation', 'severe'];
    }
    
    // Get current forecast data for context
    // In a real app, this would come from your weather API or database
    $forecastData = [
        'current' => [
            'condition' => 'Partly Cloudy',
            'temperature' => 28,
            'humidity' => 65,
            'wind_speed' => 12,
            'precipitation' => 0
        ],
        'forecast' => [
            ['day' => 'Today', 'high' => 32, 'low' => 24, 'condition' => 'Partly Cloudy'],
            ['day' => 'Tomorrow', 'high' => 33, 'low' => 25, 'condition' => 'Sunny'],
            ['day' => date('D', strtotime('+2 days')), 'high' => 30, 'low' => 24, 'condition' => 'Chance of Rain'],
            ['day' => date('D', strtotime('+3 days')), 'high' => 29, 'low' => 23, 'condition' => 'Heavy Rain'],
            ['day' => date('D', strtotime('+4 days')), 'high' => 30, 'low' => 24, 'condition' => 'Scattered Showers']
        ]
    ];
    
} catch (PDOException $e) {
    error_log("Error fetching weather alerts: " . $e->getMessage());
    // Set default values in case of error
    $weatherAlerts = [
        [
            'id' => 1,
            'type' => 'severe_weather',
            'message' => 'Heavy Rain Expected: Heavy rainfall expected in 3 days. Consider adjusting irrigation schedules.',
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]
    ];
    $alertTypes = ['severe'];
    $forecastData = [
        'current' => [
            'condition' => 'Partly Cloudy',
            'temperature' => 28,
            'humidity' => 65,
            'wind_speed' => 12,
            'precipitation' => 0
        ],
        'forecast' => [
            ['day' => 'Today', 'high' => 32, 'low' => 24, 'condition' => 'Partly Cloudy'],
            ['day' => 'Tomorrow', 'high' => 33, 'low' => 25, 'condition' => 'Sunny'],
            ['day' => date('D', strtotime('+2 days')), 'high' => 30, 'low' => 24, 'condition' => 'Chance of Rain'],
            ['day' => date('D', strtotime('+3 days')), 'high' => 29, 'low' => 23, 'condition' => 'Heavy Rain'],
            ['day' => date('D', strtotime('+4 days')), 'high' => 30, 'low' => 24, 'condition' => 'Scattered Showers']
        ]
    ];
}

// Process mark as read action
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read']) && $hasNotificationsTable) {
    $id = $_GET['mark_read'];
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        // Redirect to avoid refresh issues
        header("Location: weather_alerts.php?marked=1");
        exit;
    } catch (PDOException $e) {
        error_log("Error marking alert as read: " . $e->getMessage());
    }
}

// Process mark all as read action
if (isset($_GET['mark_all_read']) && $hasNotificationsTable) {
    try {
        $pdo->exec("UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND (
            type LIKE '%temperature%' 
            OR type LIKE '%humidity%' 
            OR type LIKE '%wind%' 
            OR type LIKE '%precipitation%' 
            OR type LIKE '%severe%'
            OR type LIKE '%daily_summary%'
        )");
        
        // Redirect to avoid refresh issues
        header("Location: weather_alerts.php?marked_all=1");
        exit;
    } catch (PDOException $e) {
        error_log("Error marking all alerts as read: " . $e->getMessage());
    }
}

$pageTitle = 'Weather Alerts';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-exclamation-triangle"></i> Weather Alerts</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='current_weather.php'">
                <i class="fas fa-cloud-sun"></i> Current Weather
            </button>
            <button class="btn btn-primary" onclick="location.href='weather_settings.php'">
                <i class="fas fa-cog"></i> Weather Settings
            </button>
        </div>
    </div>
    
    <?php if (isset($_GET['marked'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Alert marked as read.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['marked_all'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        All alerts marked as read.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <!-- Weather Overview Card -->
        <div class="col-lg-6">
            <div class="alerts-overview-card">
                <div class="card-header">
                    <h3><i class="fas fa-cloud-sun"></i> Weather Overview</h3>
                </div>
                <div class="card-body">
                    <div class="current-summary">
                        <div class="condition-icon">
                            <i class="fas fa-<?php echo getWeatherIconClass($forecastData['current']['condition']); ?>"></i>
                        </div>
                        <div class="current-details">
                            <div class="condition-text"><?php echo $forecastData['current']['condition']; ?></div>
                            <div class="temp"><?php echo $forecastData['current']['temperature']; ?>°C</div>
                            <div class="other-data">
                                <span><i class="fas fa-tint"></i> <?php echo $forecastData['current']['humidity']; ?>%</span>
                                <span><i class="fas fa-wind"></i> <?php echo $forecastData['current']['wind_speed']; ?> km/h</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="forecast-summary">
                        <h4>3-Day Forecast</h4>
                        <div class="mini-forecast">
                            <?php for ($i = 2; $i <= 4; $i++): ?>
                            <div class="forecast-day-mini">
                                <div class="day"><?php echo $forecastData['forecast'][$i]['day']; ?></div>
                                <div class="icon"><i class="fas fa-<?php echo getWeatherIconClass($forecastData['forecast'][$i]['condition']); ?>"></i></div>
                                <div class="temp"><?php echo $forecastData['forecast'][$i]['high']; ?>° / <?php echo $forecastData['forecast'][$i]['low']; ?>°</div>
                                <div class="condition"><?php echo $forecastData['forecast'][$i]['condition']; ?></div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alert Stats Card -->
        <div class="col-lg-6">
            <div class="alerts-stats-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Alert Statistics</h3>
                </div>
                <div class="card-body">
                    <div class="alerts-count">
                        <div class="total-alerts">
                            <span class="number"><?php echo count($weatherAlerts); ?></span>
                            <span class="label">Total Alerts</span>
                        </div>
                        
                        <div class="alert-types">
                            <?php 
                            $typeColors = [
                                'temperature' => '#dc3545',
                                'humidity' => '#0dcaf0',
                                'wind' => '#6c757d',
                                'precipitation' => '#0d6efd',
                                'severe' => '#fd7e14'
                            ];
                            
                            foreach ($alertTypes as $type): 
                                $typeCount = 0;
                                foreach ($weatherAlerts as $alert) {
                                    if (strpos($alert['type'], $type) !== false) {
                                        $typeCount++;
                                    }
                                }
                            ?>
                            <div class="alert-type-count" style="background-color: <?php echo $typeColors[$type] ?? '#6c757d'; ?>">
                                <span class="number"><?php echo $typeCount; ?></span>
                                <span class="label"><?php echo ucfirst($type); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="alert-actions mt-4">
                        <?php if (count($weatherAlerts) > 0 && $hasNotificationsTable): ?>
                        <a href="weather_alerts.php?mark_all_read=1" class="btn btn-primary">
                            <i class="fas fa-check-double"></i> Mark All as Read
                        </a>
                        <?php endif; ?>
                        <a href="weather_settings.php" class="btn btn-outline-primary">
                            <i class="fas fa-cog"></i> Configure Alert Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alerts List -->
    <div class="section-header">
        <h3><i class="fas fa-bell"></i> Active Weather Alerts</h3>
    </div>
    
    <div class="weather-alerts-container">
        <?php if (empty($weatherAlerts)): ?>
        <div class="no-alerts">
            <i class="fas fa-check-circle"></i>
            <p>No active weather alerts</p>
            <p class="text-muted">You'll see alerts here when weather conditions trigger your configured thresholds</p>
        </div>
        <?php else: ?>
        <div class="alerts-filter mb-3">
            <div class="btn-group" role="group" aria-label="Filter alerts">
                <button type="button" class="btn btn-outline-primary active" data-filter="all">All Alerts</button>
                <?php foreach ($alertTypes as $type): ?>
                <button type="button" class="btn btn-outline-primary" data-filter="<?php echo $type; ?>"><?php echo ucfirst($type); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="alerts-list">
            <?php foreach ($weatherAlerts as $alert): 
                // Extract the main alert type (e.g., temperature_high -> temperature)
                $mainType = explode('_', $alert['type'])[0];
                
                // Set alert icon and color based on type
                $alertIcon = 'info-circle';
                $alertColor = '#0d6efd';
                
                if (strpos($alert['type'], 'temperature') !== false) {
                    $alertIcon = 'thermometer-half';
                    $alertColor = '#dc3545';
                } else if (strpos($alert['type'], 'humidity') !== false) {
                    $alertIcon = 'tint';
                    $alertColor = '#0dcaf0';
                } else if (strpos($alert['type'], 'wind') !== false) {
                    $alertIcon = 'wind';
                    $alertColor = '#6c757d';
                } else if (strpos($alert['type'], 'precipitation') !== false) {
                    $alertIcon = 'cloud-rain';
                    $alertColor = '#0d6efd';
                } else if (strpos($alert['type'], 'severe') !== false) {
                    $alertIcon = 'exclamation-triangle';
                    $alertColor = '#fd7e14';
                } else if (strpos($alert['type'], 'daily_summary') !== false) {
                    $alertIcon = 'calendar-day';
                    $alertColor = '#20c997';
                    $mainType = 'daily';
                }
            ?>
            <div class="alert-item <?php echo $alert['is_read'] ? 'read' : 'unread'; ?>" data-alert-type="<?php echo $mainType; ?>">
                <div class="alert-icon" style="background-color: <?php echo $alertColor; ?>">
                    <i class="fas fa-<?php echo $alertIcon; ?>"></i>
                </div>
                <div class="alert-content">
                    <div class="alert-header">
                        <h4><?php echo ucfirst(str_replace('_', ' ', $alert['type'])); ?></h4>
                        <span class="alert-time"><?php echo date('M d, Y g:i A', strtotime($alert['created_at'])); ?></span>
                    </div>
                    <div class="alert-message">
                        <?php echo nl2br(htmlspecialchars($alert['message'])); ?>
                    </div>
                    <div class="alert-recommendations">
                        <?php 
                        // Custom recommendations based on alert type
                        if (strpos($alert['type'], 'rain') !== false || strpos($alert['type'], 'precipitation') !== false): 
                        ?>
                        <div class="recommendations-list">
                            <span class="recommendation-title">Recommendations:</span>
                            <ul>
                                <li>Adjust irrigation schedules to account for natural rainfall</li>
                                <li>Check drainage systems to prevent waterlogging</li>
                                <li>Protect sensitive crops from heavy rain damage</li>
                            </ul>
                        </div>
                        <?php 
                        elseif (strpos($alert['type'], 'temperature_high') !== false): 
                        ?>
                        <div class="recommendations-list">
                            <span class="recommendation-title">Recommendations:</span>
                            <ul>
                                <li>Ensure adequate water supply for crops and livestock</li>
                                <li>Consider providing shade for sensitive crops</li>
                                <li>Monitor animals for signs of heat stress</li>
                            </ul>
                        </div>
                        <?php 
                        elseif (strpos($alert['type'], 'temperature_low') !== false): 
                        ?>
                        <div class="recommendations-list">
                            <span class="recommendation-title">Recommendations:</span>
                            <ul>
                                <li>Protect sensitive crops from frost damage</li>
                                <li>Ensure livestock have adequate shelter</li>
                                <li>Check irrigation systems for freezing risks</li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="alert-actions">
                    <?php if (!$alert['is_read'] && $hasNotificationsTable): ?>
                    <a href="weather_alerts.php?mark_read=<?php echo $alert['id']; ?>" class="btn btn-sm btn-outline-primary" title="Mark as Read">
                        <i class="fas fa-check"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Farm-Specific Guidance -->
    <div class="section-header mt-4">
        <h3><i class="fas fa-seedling"></i> Weather Impact on Farm Operations</h3>
    </div>
    
    <div class="farm-guidance-container mb-5">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="guidance-card">
                    <div class="guidance-header">
                        <i class="fas fa-leaf"></i>
                        <h4>Crop Management</h4>
                    </div>
                    <div class="guidance-body">
                        <p>Based on current forecasts and alerts:</p>
                        <ul>
                            <li>Current conditions are suitable for most field operations</li>
                            <li>Be prepared for heavy rainfall in 3 days</li>
                            <li>Consider harvesting sensitive crops before rainfall</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="guidance-card">
                    <div class="guidance-header">
                        <i class="fas fa-tint"></i>
                        <h4>Irrigation Planning</h4>
                    </div>
                    <div class="guidance-body">
                        <p>Recommended irrigation adjustments:</p>
                        <ul>
                            <li>Reduce irrigation today and tomorrow</li>
                            <li>Pause irrigation schedules in 3 days due to expected rainfall</li>
                            <li>Check drainage systems to prevent water pooling</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="guidance-card">
                    <div class="guidance-header">
                        <i class="fas fa-tasks"></i>
                        <h4>Field Operations</h4>
                    </div>
                    <div class="guidance-body">
                        <p>Recommended task prioritization:</p>
                        <ul>
                            <li>Prioritize spraying and fertilizing today</li>
                            <li>Schedule indoor tasks for days with heavy rainfall</li>
                            <li>Inspect equipment and prepare for post-rain field work</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Card Styling */
.alerts-overview-card,
.alerts-stats-card,
.weather-alerts-container,
.guidance-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.card-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
}

.card-header h3 i {
    margin-right: 10px;
    color: #20c997;
}

.card-body {
    padding: 20px;
}

/* Weather Overview Card */
.current-summary {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}

.condition-icon {
    font-size: 48px;
    margin-right: 20px;
    color: #20c997;
}

.current-details {
    flex: 1;
}

.condition-text {
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 5px;
}

.current-details .temp {
    font-size: 36px;
    font-weight: 300;
    line-height: 1;
    margin-bottom: 10px;
}

.other-data {
    display: flex;
    gap: 15px;
    color: #6c757d;
}

.forecast-summary h4 {
    font-size: 16px;
    margin-bottom: 15px;
    color: #495057;
}

.mini-forecast {
    display: flex;
    justify-content: space-between;
}

.forecast-day-mini {
    text-align: center;
    padding: 10px;
    flex: 1;
}

.forecast-day-mini .day {
    font-weight: 600;
    margin-bottom: 5px;
}

.forecast-day-mini .icon {
    font-size: 24px;
    margin-bottom: 5px;
    color: #6c757d;
}

.forecast-day-mini .temp {
    font-weight: 500;
    margin-bottom: 5px;
}

.forecast-day-mini .condition {
    font-size: 12px;
    color: #6c757d;
}

/* Alert Stats Card */
.alerts-count {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

.total-alerts {
    text-align: center;
}

.total-alerts .number {
    font-size: 48px;
    font-weight: 300;
    line-height: 1;
    display: block;
    margin-bottom: 5px;
}

.total-alerts .label {
    font-size: 16px;
    color: #6c757d;
}

.alert-types {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 15px;
}

.alert-type-count {
    text-align: center;
    padding: 10px 15px;
    border-radius: 8px;
    color: white;
    min-width: 80px;
}

.alert-type-count .number {
    font-size: 24px;
    font-weight: 500;
    line-height: 1;
    display: block;
    margin-bottom: 5px;
}

.alert-type-count .label {
    font-size: 12px;
    font-weight: 500;
}

/* Alerts List */
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

.alerts-filter {
    padding: 15px 20px;
    background: #f8f9fa;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
    border-bottom: 1px solid #e9ecef;
}

.alerts-list {
    padding: 0;
}

.no-alerts {
    text-align: center;
    padding: 40px 20px;
    color: #28a745;
}

.no-alerts i {
    font-size: 48px;
    margin-bottom: 10px;
}

.no-alerts p {
    margin: 5px 0;
    font-size: 16px;
}

.no-alerts p.text-muted {
    font-size: 14px;
    color: #6c757d;
}

.alert-item {
    display: flex;
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.2s;
}

.alert-item:last-child {
    border-bottom: none;
}

.alert-item:hover {
    background-color: #f8f9fa;
}

.alert-item.unread {
    background-color: #f0f9ff;
}

.alert-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: white;
    font-size: 20px;
}

.alert-content {
    flex: 1;
    min-width: 0;
}

.alert-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.alert-header h4 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.alert-time {
    font-size: 12px;
    color: #6c757d;
}

.alert-message {
    font-size: 14px;
    color: #495057;
    margin-bottom: 15px;
}

.alert-recommendations {
    background-color: #f8f9fa;
    padding: 10px 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.recommendation-title {
    font-weight: 600;
    display: block;
    margin-bottom: 5px;
}

.recommendations-list ul {
    margin: 0;
    padding-left: 20px;
}

.recommendations-list li {
    font-size: 13px;
    margin-bottom: 5px;
}

.alert-actions {
    margin-left: 15px;
    align-self: flex-start;
}

/* Farm Guidance Cards */
.guidance-card {
    height: 100%;
}

.guidance-header {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.guidance-header i {
    font-size: 20px;
    margin-right: 10px;
    color: #20c997;
}

.guidance-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.guidance-body {
    padding: 15px 20px;
}

.guidance-body p {
    font-weight: 500;
    margin-bottom: 10px;
}

.guidance-body ul {
    margin: 0;
    padding-left: 20px;
}

.guidance-body li {
    margin-bottom: 8px;
    font-size: 14px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .current-summary {
        flex-direction: column;
        text-align: center;
    }
    
    .condition-icon {
        margin-right: 0;
        margin-bottom: 15px;
    }
    
    .mini-forecast {
        flex-direction: column;
    }
    
    .forecast-day-mini {
        border-bottom: 1px solid #e9ecef;
        padding: 15px 0;
    }
    
    .forecast-day-mini:last-child {
        border-bottom: none;
    }
    
    .alert-item {
        flex-direction: column;
    }
    
    .alert-icon {
        margin-right: 0;
        margin-bottom: 15px;
        align-self: center;
    }
    
    .alert-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .alert-time {
        margin-top: 5px;
    }
    
    .alert-actions {
        margin-left: 0;
        margin-top: 15px;
        align-self: flex-end;
    }
}